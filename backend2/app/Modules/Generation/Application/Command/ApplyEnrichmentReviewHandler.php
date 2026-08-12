<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Generation\Application\Dto\ReviewOutcome;
use App\Modules\Generation\Application\Port\EnrichmentJournal;
use App\Modules\Generation\Domain\Service\EnrichmentValidator;
use App\Modules\Generation\Domain\ValueObject\EnrichmentCandidate;
use App\Modules\Generation\Domain\ValueObject\RawVariant;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Command\ImportTermEnrichment;
use App\Modules\Vocabulary\Application\Command\ImportTermEnrichmentHandler;
use App\Modules\Vocabulary\Application\Dto\AcceptedVariantInput;
use App\Modules\Vocabulary\Application\Port\TermReviewWriter;
use App\Modules\Vocabulary\Application\Query\EnrichmentTargetReader;

/**
 * Applies a proofreader's decisions to generated enrichment.
 *
 * The one rule worth stating: an ADDED variant goes through EnrichmentValidator exactly like a
 * model-proposed one. A human is a better judge of meaning than the model and a worse judge of the
 * mechanical traps — whether the "new" variant is already accepted after normalisation, whether it is a
 * sentence rather than an alternative form. Trusting the reviewer on meaning while still checking the
 * mechanics is what keeps the answer key honest, and it costs nothing.
 *
 * Removals are not validated: deleting a row can only ever narrow what the trainer accepts, and the
 * reviewer's judgement is the whole point.
 */
final readonly class ApplyEnrichmentReviewHandler
{
    public function __construct(
        private TermReviewWriter $review,
        private EnrichmentTargetReader $targets,
        private EnrichmentValidator $validator,
        private ImportTermEnrichmentHandler $import,
        private EnrichmentJournal $journal,
    ) {}

    public function __invoke(ApplyEnrichmentReview $command): ReviewOutcome
    {
        $r = $command->review;
        $unmatched = [];

        $distractorsRemoved = 0;
        foreach ($this->rows($r, 'remove_distractors') as $row) {
            $termId = $this->termId($row, $unmatched, 'дистрактор');
            if ($termId === null) {
                continue;
            }
            $contains = isset($row['contains']);
            $needle = $contains ? (string) $row['contains'] : (string) ($row['sentence'] ?? '');
            $hit = $this->review->removeDistractor($termId, $needle, $contains);
            $hit > 0
                ? $distractorsRemoved += $hit
                : $unmatched[] = "дистрактор «{$needle}» у «{$row['term']}» — не найден";
        }

        $variantsRemoved = 0;
        foreach ($this->rows($r, 'remove_variants') as $row) {
            $termId = $this->termId($row, $unmatched, 'вариант');
            if ($termId === null) {
                continue;
            }
            $hit = $this->review->removeVariant($termId, (string) $row['text']);
            $hit > 0
                ? $variantsRemoved += $hit
                : $unmatched[] = "вариант «{$row['text']}» у «{$row['term']}» — не найден";
        }

        [$variantsAdded, $variantsRejected] = $this->addVariants($r, $command->generatorVersion, $unmatched);

        $translations = 0;
        $exampleTranslations = 0;
        foreach ($this->rows($r, 'set_translations') as $row) {
            $termId = $this->termId($row, $unmatched, 'перевод');
            if ($termId === null) {
                continue;
            }
            if (isset($row['translation'])) {
                $hit = $this->review->setPrimaryTranslation($termId, (string) $row['translation']);
                $hit > 0 ? $translations++ : $unmatched[] = "перевод у «{$row['term']}» — нечего править";
            }
            if (isset($row['example_translation'])) {
                $hit = $this->review->setPinnedExampleTranslation($termId, (string) $row['example_translation']);
                $hit > 0 ? $exampleTranslations++ : $unmatched[] = "перевод примера у «{$row['term']}» — нечего править";
            }
        }

        // Everything the run flagged that the actions above did not resolve was read and kept — the
        // review says so explicitly. Acknowledging empties the worklist without erasing the log.
        $acknowledged = ($r['acknowledge_all_remaining_findings'] ?? false) === true
            ? $this->journal->acknowledgeOpenFindings($command->generatorVersion, 'store5 review')
            : 0;

        return new ReviewOutcome(
            distractorsRemoved: $distractorsRemoved,
            variantsRemoved: $variantsRemoved,
            variantsAdded: $variantsAdded,
            variantsRejected: $variantsRejected,
            translationsFixed: $translations,
            exampleTranslationsFixed: $exampleTranslations,
            findingsAcknowledged: $acknowledged,
            unmatched: $unmatched,
        );
    }

    /**
     * @param  array<string, mixed>  $review
     * @param  list<string>  $unmatched
     * @return array{0: int, 1: int}  added, rejected by the validator
     */
    private function addVariants(array $review, string $version, array &$unmatched): array
    {
        $added = 0;
        $rejected = 0;

        foreach ($this->rows($review, 'add_variants') as $row) {
            $termId = $this->termId($row, $unmatched, 'вариант+');
            if ($termId === null) {
                continue;
            }

            $target = $this->targets->byIds([TermId::fromString($termId)])[$termId] ?? null;
            if ($target === null) {
                $unmatched[] = "вариант+ «{$row['text']}»: термин «{$row['term']}» не читается";

                continue;
            }

            // Same gate as a model-proposed variant: no distractors in this candidate, so the run's
            // other rules stay inert and only the variant rules apply.
            $verdict = $this->validator->validate(new EnrichmentCandidate(
                termId: $termId,
                acceptedForms: $target->acceptedForms,
                exampleId: $target->exampleId,
                exampleSentence: $target->exampleSentence,
                translation: $target->translation,
                exampleTranslation: $target->exampleTranslation,
                distractors: [],
                variants: [new RawVariant((string) $row['text'], $this->noteOf($row))],
                // The term itself: this candidate is not being judged for ambiguity, and passing the
                // target keeps the (unused here) back-translation check from inventing a finding.
                backTranslation: $target->text,
            ));

            if ($verdict->variants === []) {
                $rejected++;
                $unmatched[] = "вариант+ «{$row['text']}» у «{$row['term']}» — не прошёл валидацию";

                continue;
            }

            ($this->import)(new ImportTermEnrichment(
                termId: TermId::fromString($termId),
                exampleId: $target->exampleId,
                variants: array_map(
                    static fn (RawVariant $v): AcceptedVariantInput => new AcceptedVariantInput($v->text, $v->note),
                    $verdict->variants,
                ),
                distractors: [],
                generatorVersion: $version,
            ));
            $added += count($verdict->variants);
        }

        return [$added, $rejected];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $unmatched
     */
    private function termId(array $row, array &$unmatched, string $what): ?string
    {
        $text = (string) ($row['term'] ?? '');
        $termId = $text !== '' ? $this->review->findTermIdByText($text) : null;
        if ($termId === null) {
            $unmatched[] = "{$what}: термина «{$text}» нет в базе";
        }

        return $termId;
    }

    /**
     * @param  array<string, mixed>  $review
     * @return list<array<string, mixed>>
     */
    private function rows(array $review, string $key): array
    {
        $rows = $review[$key] ?? [];

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /** @param  array<string, mixed>  $row */
    private function noteOf(array $row): ?string
    {
        $note = $row['note'] ?? null;

        return is_string($note) && trim($note) !== '' ? trim($note) : null;
    }
}

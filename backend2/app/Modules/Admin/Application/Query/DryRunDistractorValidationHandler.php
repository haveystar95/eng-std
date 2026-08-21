<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use App\Modules\Admin\Application\Dto\ExampleDistractorRow;
use App\Modules\Admin\Application\Dto\ListWindow;
use App\Modules\Admin\Application\Dto\PlaygroundValidation;
use App\Modules\Admin\Application\Dto\PlaygroundValidationRow;
use App\Modules\Admin\Application\Port\AdminContentHealthReader;
use App\Modules\Admin\Application\Port\AdminTermReader;
use App\Modules\Generation\Application\Dto\DryRunItemVerdict;
use App\Modules\Generation\Application\Dto\DryRunReference;
use App\Modules\Generation\Application\Service\DistractorDryRun;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Query\EnrichmentTargetReader;
use InvalidArgumentException;
use Throwable;

/**
 * Builds the reference the dry run is judged against, then hands the rows to the real validator.
 *
 * The TERM path is the honest one and is built the way production builds it: the reference comes
 * from {@see EnrichmentTargetReader::byIds()} — the same reader the станок uses — so the accepted
 * forms, the pinned example and the dedup set (stored rows, suppressions, and the same two for
 * sibling terms) are the production ones, not an approximation assembled here.
 *
 * The MANUAL path cannot be that, and does not pretend to be: there is no term, so the dedup set is
 * whatever a term with that exact text happens to have. It is narrower than production by design —
 * sibling terms are not consulted — and the response says which term it matched so nobody reads a
 * clean manual run as proof about a term.
 */
final readonly class DryRunDistractorValidationHandler
{
    /**
     * The learner language the target reader picks a translation in. The dry run never reads that
     * translation — it judges distractors — so this only has to be A language, and `ru` is the only
     * one any collection teaches from today.
     */
    private const LEARNER_LANG = 'ru';

    public function __construct(
        private EnrichmentTargetReader $targets,
        private AdminTermReader $terms,
        private AdminContentHealthReader $content,
        private DistractorDryRun $dryRun,
    ) {}

    public function __invoke(DryRunDistractorValidation $query): PlaygroundValidation
    {
        $reference = $query->termId !== null && trim($query->termId) !== ''
            ? $this->fromTerm(trim($query->termId))
            : $this->fromManual($query->manualTerm ?? '', $query->manualExample ?? '');

        $result = $this->dryRun->run($reference['reference'], $query->items);

        return new PlaygroundValidation(
            items: array_map(
                static fn (DryRunItemVerdict $v): PlaygroundValidationRow => new PlaygroundValidationRow(
                    index: $v->index,
                    sentence: $v->sentence,
                    errorSpan: $v->errorSpan,
                    correction: $v->correction,
                    errorType: $v->errorType,
                    kept: $v->kept,
                    gate: $v->gate,
                    reason: $v->reason,
                    errorTypeDefaulted: $v->errorTypeDefaulted,
                ),
                $result->items,
            ),
            kept: $result->kept,
            total: $result->total,
            source: $reference['source'],
            termId: $result->termId,
            termText: $result->termText,
            exampleSentence: $result->exampleSentence,
            existingCount: $result->existingCount,
            suppressedCount: $result->suppressedCount,
            matchedTermId: $reference['matchedTermId'],
        );
    }

    /** @return array{reference: DryRunReference, source: string, matchedTermId: string|null} */
    private function fromTerm(string $termId): array
    {
        try {
            $ids = [TermId::fromString($termId)];
        } catch (InvalidArgumentException) {
            // A malformed id is a reference we cannot build, not a crash: the run reports every row
            // against an empty reference, which reads as «нет примера» — the truth for this input.
            return $this->emptyReference(null, '');
        }

        $target = ($this->targets->byIds($ids, self::LEARNER_LANG))[$termId] ?? null;
        if ($target === null) {
            return $this->emptyReference($termId, '');
        }

        return [
            'reference' => new DryRunReference(
                termId: $target->termId,
                termText: $target->text,
                acceptedForms: $target->acceptedForms,
                exampleId: $target->exampleId,
                exampleSentence: $target->exampleSentence,
                // Verbatim — this is the set production deduplicates against, siblings included.
                existingDistractors: $target->existingDistractors,
                stored: $this->storedSentences($target->termId),
                suppressed: $this->suppressedSentences($target->termId),
            ),
            'source' => 'term',
            'matchedTermId' => null,
        ];
    }

    /** @return array{reference: DryRunReference, source: string, matchedTermId: string|null} */
    private function fromManual(string $term, string $example): array
    {
        $term = trim($term);
        $example = trim($example);
        $matched = $this->termIdByText($term);

        $stored = $matched !== null ? $this->storedSentences($matched) : [];
        $suppressed = $matched !== null ? $this->suppressedSentences($matched) : [];

        return [
            'reference' => new DryRunReference(
                termId: null,
                termText: $term,
                acceptedForms: [$term],
                // A non-null id is what tells the validator there IS an example to hang rows on; it
                // is never written and never read back, so a literal is honest here.
                exampleId: $example !== '' ? 'playground-example' : null,
                exampleSentence: $example !== '' ? $example : null,
                existingDistractors: [...$stored, ...$suppressed],
                stored: $stored,
                suppressed: $suppressed,
            ),
            'source' => 'manual',
            'matchedTermId' => $matched,
        ];
    }

    /** @return array{reference: DryRunReference, source: string, matchedTermId: string|null} */
    private function emptyReference(?string $termId, string $text): array
    {
        return [
            'reference' => new DryRunReference(
                termId: $termId,
                termText: $text,
                acceptedForms: [],
                exampleId: null,
                exampleSentence: null,
            ),
            'source' => 'term',
            'matchedTermId' => null,
        ];
    }

    /** An exact (case-insensitive) text match among the search hits, or null. */
    private function termIdByText(string $text): ?string
    {
        if ($text === '') {
            return null;
        }

        try {
            $page = $this->terms->list($text, new ListWindow(limit: 25));
        } catch (Throwable) {
            return null;
        }

        foreach ($page->items as $row) {
            if (mb_strtolower(trim($row->text)) === mb_strtolower($text)) {
                return $row->id;
            }
        }

        return null;
    }

    /**
     * Sentences currently stored against the term's PINNED example — the half of the dedup set that
     * means «уже записано». Read through the same detail projection the term page uses.
     *
     * @return list<string>
     */
    private function storedSentences(string $termId): array
    {
        $detail = $this->terms->detail($termId);
        if ($detail === null) {
            return [];
        }

        foreach ($detail->examples as $example) {
            if ($example->isPinned) {
                return array_map(
                    static fn (ExampleDistractorRow $d): string => $d->sentence,
                    $example->distractors,
                );
            }
        }

        return [];
    }

    /** @return list<string> */
    private function suppressedSentences(string $termId): array
    {
        return array_map(
            static fn (array $row): string => $row['sentence'],
            $this->content->suppressionsForTerm($termId),
        );
    }
}

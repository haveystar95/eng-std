<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Generation\Application\Dto\DistractorAuditOutcome;
use App\Modules\Generation\Domain\Service\DistractorRepair;
use App\Modules\Generation\Domain\Service\EnrichmentValidator;
use App\Modules\Shared\Domain\Service\LexicalNormalizer;
use App\Modules\Vocabulary\Application\Dto\DistractorAuditRow;
use App\Modules\Vocabulary\Application\Port\TermReviewWriter;
use App\Modules\Vocabulary\Application\Query\DistractorAuditReader;

/**
 * Applies the validator's checks to content that was written before those checks existed.
 *
 * The станок only ever judged a row on its way IN. Everything already in the database was judged by
 * whatever the validator knew on the day it was generated, so tightening the validator quietly splits
 * the content into "new rows that pass" and "old rows nobody re-asked". This closes that: the same
 * four questions, asked of the whole table, with the answer written back.
 *
 * The verdict per row is deliberately not uniform — the checks disagree about what a failure means:
 *
 *   · equality / dedup — the SENTENCE is the problem (it is the example, or a sibling). Nothing to
 *     repair; the row is scrap.
 *   · no-op / circular — the sentence may be perfectly good and only its LABEL wrong. The span and
 *     correction are derivable from the sentence itself (see {@see DistractorRepair}), so a row that
 *     breaks exactly one place is relabelled and kept. One that breaks two is not a distractor under
 *     our own rules and is deleted.
 *
 * Rerunnable by construction: a second pass finds nothing, because everything it would find it fixed.
 */
final readonly class AuditDistractorsHandler
{
    public function __construct(
        private DistractorAuditReader $reader,
        private TermReviewWriter $writer,
        private EnrichmentValidator $validator,
        private DistractorRepair $repair,
        private LexicalNormalizer $normalizer,
    ) {}

    public function __invoke(AuditDistractors $command): DistractorAuditOutcome
    {
        $rows = $this->reader->all();

        $fixed = [];
        $deleted = [];
        $notes = [];
        $seenByExample = [];

        foreach ($rows as $row) {
            $key = $this->normalizer->normalize($row->sentence);
            $check = $this->verdict($row, $key, $seenByExample[$row->exampleId] ?? []);
            $seenByExample[$row->exampleId][$key] = true;

            if ($check === null) {
                continue;
            }

            // Only the label checks have anything to repair; a sentence that IS the example or a
            // sibling cannot be relabelled into a distractor.
            $repair = in_array($check, ['noop', 'circular'], true)
                ? $this->repairFor($row)
                : null;

            if ($repair !== null) {
                $fixed[$check] = ($fixed[$check] ?? 0) + 1;
                $notes[] = sprintf(
                    '%s · починен · «%s» :: %s [%s → %s]',
                    $check, $row->termText, $row->sentence, $repair['span'], $repair['correction'],
                );
                if ($command->apply) {
                    $this->writer->fixDistractor($row->termId, $row->sentence, $repair['span'], $repair['correction']);
                }

                continue;
            }

            $deleted[$check] = ($deleted[$check] ?? 0) + 1;
            $notes[] = sprintf('%s · удалён · «%s» :: %s', $check, $row->termText, $row->sentence);
            if ($command->apply) {
                $this->writer->removeDistractor($row->termId, $row->sentence, false, 'audit');
            }
        }

        return new DistractorAuditOutcome(
            seen: count($rows),
            fixed: $fixed,
            deleted: $deleted,
            notes: $notes,
        );
    }

    /**
     * Which check this row fails, or null when it passes all four. Order matches the validator's, so
     * a row that is both the example and mislabelled is reported as the more fundamental problem.
     *
     * @param  array<string, true>  $seen  normalised sentences already kept on this example
     */
    private function verdict(DistractorAuditRow $row, string $key, array $seen): ?string
    {
        if ($this->validator->sentenceEquals($row->sentence, $row->exampleSentence)) {
            return 'equality';
        }
        if (isset($seen[$key])) {
            return 'dedup';
        }
        if ($this->normalizer->canonicalize($row->errorSpan) === $this->normalizer->canonicalize($row->correction)) {
            return 'noop';
        }
        if (! $this->validator->repairsTo($row->sentence, $row->errorSpan, $row->correction, $row->exampleSentence)) {
            return 'circular';
        }

        return null;
    }

    /**
     * The derived label, but only if it actually survives the check it is meant to satisfy — the diff
     * can produce a span that occurs earlier in the sentence than intended, and the underline follows
     * the FIRST occurrence. Verifying closes that gap instead of trusting the derivation.
     *
     * @return array{span: string, correction: string}|null
     */
    private function repairFor(DistractorAuditRow $row): ?array
    {
        $repair = $this->repair->derive($row->sentence, $row->exampleSentence);
        if ($repair === null) {
            return null;
        }

        return $this->validator->repairsTo($row->sentence, $repair['span'], $repair['correction'], $row->exampleSentence)
            ? $repair
            : null;
    }
}

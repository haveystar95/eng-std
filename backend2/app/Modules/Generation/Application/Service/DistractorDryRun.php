<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Application\Dto\DryRunItemVerdict;
use App\Modules\Generation\Application\Dto\DryRunReference;
use App\Modules\Generation\Application\Dto\DryRunResult;
use App\Modules\Generation\Domain\Service\DistractorGateLog;
use App\Modules\Generation\Domain\Service\EnrichmentValidator;
use App\Modules\Generation\Domain\ValueObject\DistractorGate;
use App\Modules\Generation\Domain\ValueObject\EnrichmentCandidate;
use App\Modules\Generation\Domain\ValueObject\ErrorType;
use App\Modules\Generation\Domain\ValueObject\RawDistractor;
use App\Modules\Shared\Domain\Service\LexicalNormalizer;

/**
 * «Что сделал бы станок с этими строками» — the REAL validator, on real reference content, writing
 * nothing.
 *
 * The verdicts are not simulated and not re-derived: this builds the same {@see EnrichmentCandidate}
 * {@see \App\Modules\Generation\Application\Command\BuildTermEnrichmentsHandler} builds and calls the
 * same {@see EnrichmentValidator::validate()}. What it adds is the reason, recorded by the validator
 * itself through {@see DistractorGateLog}, and one refinement on top: which SOURCE a duplicate
 * matched. The validator receives duplicates as one merged list because it does not care where a
 * sentence came from; a person looking at «дубль» does — «уже записано» and «человек это выкинул»
 * lead to different next actions.
 *
 * WRITES NOTHING. There is no repository, no journal and no import handler in reach of this class:
 * the two collaborators are a pure Domain service and a pure normaliser. That is enforced by
 * construction rather than by discipline, and by a test that counts rows before and after.
 */
final readonly class DistractorDryRun
{
    public function __construct(
        private EnrichmentValidator $validator,
        private LexicalNormalizer $normalizer = new LexicalNormalizer(),
    ) {}

    /**
     * Substituted when a row arrives without an `error_type`, and reported per row.
     *
     * Defaulting quietly would be worse than either alternative: every such row would otherwise be
     * refused by the type check, and the person would never learn what the other twelve checks
     * thought of it. The substitution is named in the verdict so nobody reads a KEEP as proof that
     * the model's own (absent) type was fine.
     */
    private const DEFAULT_ERROR_TYPE = ErrorType::Article;

    /**
     * @param  list<array{sentence: string, error_span: string, correction: string, error_type?: string|null}>  $rows
     *         primitives, not {@see RawDistractor}: the caller is a back-office projection that may
     *         not import this module's Domain, and the VO is built here where the enum lives
     */
    public function run(DryRunReference $reference, array $rows): DryRunResult
    {
        [$items, $defaulted] = $this->distractors($rows);
        $log = new DistractorGateLog();

        $verdict = $this->validator->validate(new EnrichmentCandidate(
            // A manual reference has no term id; the validator only echoes it into findings, which a
            // dry run does not report, so a placeholder cannot leak anywhere.
            termId: $reference->termId ?? 'playground',
            acceptedForms: $reference->acceptedForms !== [] ? $reference->acceptedForms : [$reference->termText],
            exampleId: $reference->exampleId,
            exampleSentence: $reference->exampleSentence,
            translation: null,
            exampleTranslation: null,
            distractors: $items,
            // Distractors only: the sandbox validates the product a person is debugging. An empty
            // variant list is also what keeps the two "correct" sets honest — nothing here can
            // invent a variant that then kills a distractor.
            variants: [],
            backTranslation: null,
            languageNotes: [],
            existingDistractors: $reference->existingDistractors,
            // Never asked, so the ambiguity check stays silent instead of flagging every run.
            backTranslationAsked: false,
        ), $log);

        $kept = [];
        foreach ($verdict->distractors as $distractor) {
            $kept[$this->normalizer->normalize($distractor->sentence)] = true;
        }

        $stored = $this->normalizedSet($reference->stored);
        $suppressed = $this->normalizedSet($reference->suppressed);
        $seenInBatch = [];

        $out = [];
        foreach ($items as $index => $item) {
            $gate = $log->gateFor($index) ?? DistractorGate::Kept;
            $key = $this->normalizer->normalize($item->sentence);

            [$code, $reason] = $gate === DistractorGate::Duplicate
                ? $this->duplicateSource($key, $stored, $suppressed, $seenInBatch)
                : [$gate->value, $gate->reason()];

            $seenInBatch[$key] = true;

            $out[] = new DryRunItemVerdict(
                index: $index,
                errorTypeDefaulted: $defaulted[$index] ?? false,
                sentence: $item->sentence,
                errorSpan: $item->errorSpan,
                correction: $item->correction,
                errorType: $item->errorType,
                // The verdict is the validator's own: a row is KEPT when it came out the other side,
                // not when the gate log happens to say so.
                kept: $key !== '' && isset($kept[$key]),
                gate: $code,
                reason: $reason,
            );
        }

        return new DryRunResult(
            items: $out,
            kept: count($verdict->distractors),
            total: count($items),
            termId: $reference->termId,
            termText: $reference->termText,
            exampleSentence: $reference->exampleSentence,
            existingCount: count($reference->existingDistractors),
            suppressedCount: count($reference->suppressed),
        );
    }

    /**
     * @param  list<array{sentence: string, error_span: string, correction: string, error_type?: string|null}>  $rows
     * @return array{0: list<RawDistractor>, 1: array<int, bool>}
     */
    private function distractors(array $rows): array
    {
        $items = [];
        $defaulted = [];
        foreach ($rows as $index => $row) {
            $type = trim((string) ($row['error_type'] ?? ''));
            if ($type === '') {
                $type = self::DEFAULT_ERROR_TYPE->value;
                $defaulted[$index] = true;
            }
            $items[] = new RawDistractor(
                sentence: (string) $row['sentence'],
                errorType: $type,
                errorSpan: (string) $row['error_span'],
                correction: (string) $row['correction'],
            );
        }

        return [$items, $defaulted];
    }

    /**
     * Which list a duplicate matched. Order matters: suppression is the more interesting fact, so a
     * sentence that is both stored and suppressed reports as suppressed.
     *
     * @param  array<string, true>  $stored
     * @param  array<string, true>  $suppressed
     * @param  array<string, true>  $seenInBatch
     * @return array{0: string, 1: string}
     */
    private function duplicateSource(string $key, array $stored, array $suppressed, array $seenInBatch): array
    {
        if (isset($suppressed[$key])) {
            return ['duplicate_suppressed', 'это предложение уже подавлено (вычитка или аудит выкинули его) — станок не предложит его снова.'];
        }
        if (isset($stored[$key])) {
            return ['duplicate_stored', 'такое предложение уже записано для этого примера.'];
        }
        if (isset($seenInBatch[$key])) {
            return ['duplicate_in_batch', 'дубль внутри присланного списка — выше уже была та же строка.'];
        }

        // Reachable for a sibling term's row: a different term whose text normalises identically has
        // its distractors in the dedup set too, and the sandbox was not handed that term's rows.
        return [DistractorGate::Duplicate->value, DistractorGate::Duplicate->reason()];
    }

    /**
     * @param  list<string>  $values
     * @return array<string, true>
     */
    private function normalizedSet(array $values): array
    {
        $set = [];
        foreach ($values as $value) {
            $key = $this->normalizer->normalize($value);
            if ($key !== '') {
                $set[$key] = true;
            }
        }

        return $set;
    }
}

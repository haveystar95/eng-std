<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

use App\Modules\Generation\Domain\ValueObject\EnrichmentCandidate;
use App\Modules\Generation\Domain\ValueObject\EnrichmentFinding;
use App\Modules\Generation\Domain\ValueObject\EnrichmentVerdict;
use App\Modules\Generation\Domain\ValueObject\ErrorType;
use App\Modules\Generation\Domain\ValueObject\FindingKind;
use App\Modules\Generation\Domain\ValueObject\RawDistractor;
use App\Modules\Generation\Domain\ValueObject\RawVariant;
use App\Modules\Shared\Domain\Service\LexicalNormalizer;

/**
 * The gate between the model and the database. Everything here is DETERMINISTIC — the same pack
 * validates the same way forever, no second model call, no judgement. That is the whole point: the
 * model is allowed to be creative about content and not trusted at all about correctness.
 *
 * The one rule that matters most: **a distractor must not be a correct answer.** The model
 * regularly proposes a "wrong" sentence that our own typed normalisation (case, punctuation,
 * contractions, optional leading article) folds onto the target — "I'd like to withdraw" against
 * "I would like to withdraw" is not an error, it is the same answer. Writing that row would build a
 * trainer whose right answer is marked wrong, so it is scrap. Checking it with the SAME normaliser
 * the grader uses is what makes the check meaningful rather than decorative.
 *
 * Order is load-bearing:
 *   1. settle the variants first — they widen what counts as correct;
 *   2. then judge distractors against {target ∪ variants}, so a variant the same pack proposed can
 *      still kill a distractor from that pack;
 *   3. a collision between the two is not just a dropped row — it means one of the model's two
 *      claims about the same sentence is false, so the TERM goes to a human.
 */
final class EnrichmentValidator
{
    /** A pinned example gets 2–3 options' worth of wrong sentences; more is paid-for noise. */
    public const MAX_DISTRACTORS = 3;

    public function __construct(
        private readonly LexicalNormalizer $normalizer = new LexicalNormalizer(),
        private readonly LanguagePurityCheck $purity = new LanguagePurityCheck(),
    ) {}

    public function validate(EnrichmentCandidate $candidate): EnrichmentVerdict
    {
        $findings = [];

        $accepted = $this->normalizedSet($candidate->acceptedForms);
        $variants = $this->validVariants($candidate, $accepted);

        // The two halves of "correct" are kept apart on purpose. Both kill a distractor, but only a
        // collision with a VARIANT is a contradiction worth a person's time: the same pack claimed
        // one sentence is both right and wrong. A distractor that merely folds onto the target is
        // the ordinary scrap this validator exists to absorb — counted, not escalated.
        $variantSet = [];
        foreach ($variants as $variant) {
            $variantSet[$this->normalizer->normalize($variant->text)] = true;
        }

        [$distractors, $proposed, $rejected, $conflicts] = $this->validDistractors($candidate, $accepted, $variantSet);

        foreach ($conflicts as $sentence) {
            $findings[] = new EnrichmentFinding(
                $candidate->termId,
                FindingKind::VariantConflict,
                null,
                "«{$sentence}» предложено и как верный вариант, и как дистрактор — одно из двух неверно.",
            );
        }

        $ambiguity = $this->ambiguityFinding($candidate, $accepted + $variantSet);
        if ($ambiguity !== null) {
            $findings[] = $ambiguity;
        }

        foreach ($this->languageFindings($candidate) as $finding) {
            $findings[] = $finding;
        }

        return new EnrichmentVerdict(
            termId: $candidate->termId,
            distractors: $distractors,
            variants: $variants,
            findings: $findings,
            proposedDistractors: $proposed,
            rejectedDistractors: $rejected,
        );
    }

    /**
     * Variants that are worth storing: non-empty, not a normalisation-equal restatement of a form
     * the key ALREADY accepts (that row would buy nothing), and distinct from each other.
     *
     * @param  array<string, true>  $accepted
     * @return list<RawVariant>
     */
    private function validVariants(EnrichmentCandidate $candidate, array $accepted): array
    {
        $kept = [];
        $seen = $accepted;
        foreach ($candidate->variants as $variant) {
            $text = trim($variant->text);
            if ($text === '') {
                continue;
            }
            $key = $this->normalizer->normalize($text);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $kept[] = new RawVariant($text, $this->nullIfBlank($variant->note));
        }

        return $kept;
    }

    /**
     * @param  array<string, true>  $accepted  normalised target forms (incl. previously stored variants)
     * @param  array<string, true>  $variantSet  normalised variants proposed by THIS pack
     * @return array{0: list<RawDistractor>, 1: int, 2: int, 3: list<string>}  kept, proposed, rejected, conflicting
     */
    private function validDistractors(EnrichmentCandidate $candidate, array $accepted, array $variantSet): array
    {
        $correct = $accepted + $variantSet;
        $proposed = count($candidate->distractors);

        // No pinned example means nothing to hang a distractor on — every row is scrap, and the
        // count says so rather than the run silently reporting a clean sheet.
        if ($candidate->exampleId === null || $this->nullIfBlank($candidate->exampleSentence) === null) {
            return [[], $proposed, $proposed, []];
        }

        $sentence = (string) $candidate->exampleSentence;
        $kept = [];
        $conflicts = [];
        $seen = [];
        foreach ($candidate->distractors as $raw) {
            $text = trim($raw->sentence);
            $span = trim($raw->errorSpan);
            $key = $this->normalizer->normalize($text);

            if ($text === '' || $key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            // A "wrong" sentence our own grader would accept is not wrong. Escalate only when it was
            // a VARIANT that made it correct — see the note where the two sets are built.
            if (isset($correct[$key])) {
                if (isset($variantSet[$key])) {
                    $conflicts[] = $text;
                }

                continue;
            }
            if (ErrorType::tryFromWire($raw->errorType) === null) {
                continue;
            }
            // The span has to be findable in the sentence it claims to be a fragment of, or the
            // reveal ("this bit is the mistake") has nothing to underline. Case-insensitive so a
            // sentence-initial capital doesn't fail an otherwise good row.
            if ($span === '' || mb_stripos($text, $span) === false) {
                continue;
            }
            if (trim($raw->correction) === '') {
                continue;
            }
            // A distractor identical to the pinned example is not a distractor.
            if ($key === $this->normalizer->normalize($sentence)) {
                continue;
            }

            $kept[] = new RawDistractor($text, strtolower(trim($raw->errorType)), $span, trim($raw->correction));
            if (count($kept) === self::MAX_DISTRACTORS) {
                break;
            }
        }

        return [$kept, $proposed, $proposed - count($kept), $conflicts];
    }

    /**
     * The back-translation check. The model is asked to reconstruct the English from the RUSSIAN
     * prompt alone; if what comes back isn't something we would accept as the answer, then the card
     * asks a question its own prompt doesn't determine — and no variant closed the gap. That is a
     * rewrite candidate, never an automatic edit.
     *
     * @param  array<string, true>  $correct
     */
    private function ambiguityFinding(EnrichmentCandidate $candidate, array $correct): ?EnrichmentFinding
    {
        $back = $this->nullIfBlank($candidate->backTranslation);
        if ($back === null) {
            return new EnrichmentFinding(
                $candidate->termId,
                FindingKind::Ambiguity,
                'translation',
                'Модель не восстановила эталон из русского перевода — перевод не задаёт ответ однозначно.',
            );
        }

        if (isset($correct[$this->normalizer->normalize($back)])) {
            return null;
        }

        return new EnrichmentFinding(
            $candidate->termId,
            FindingKind::Ambiguity,
            'translation',
            "Обратный перевод даёт «{$back}» — ни эталон, ни варианты этого не покрывают; переформулировать.",
        );
    }

    /** @return list<EnrichmentFinding> */
    private function languageFindings(EnrichmentCandidate $candidate): array
    {
        $out = [];

        // Russian fields: Ukrainian-only letters are decisive evidence.
        foreach (['translation' => $candidate->translation, 'example_translation' => $candidate->exampleTranslation] as $field => $value) {
            $value = $this->nullIfBlank($value);
            if ($value === null) {
                continue;
            }
            $ua = $this->purity->ukrainianLetters($value);
            if ($ua !== []) {
                $out[] = new EnrichmentFinding(
                    $candidate->termId,
                    FindingKind::Language,
                    $field,
                    'Украинские буквы в русском поле (' . implode(' ', $ua) . "): «{$value}».",
                );
            }
        }

        // English fields: any non-Latin letter.
        $sentence = $this->nullIfBlank($candidate->exampleSentence);
        if ($sentence !== null) {
            $foreign = $this->purity->nonEnglishLetters($sentence);
            if ($foreign !== []) {
                $out[] = new EnrichmentFinding(
                    $candidate->termId,
                    FindingKind::Language,
                    'example',
                    'Не-латиница в английском поле (' . implode(' ', $foreign) . "): «{$sentence}».",
                );
            }
        }

        // The model's lexis notes — the half a character check cannot see (see LanguagePurityCheck).
        foreach ($candidate->languageNotes as $note) {
            $note = $this->nullIfBlank($note);
            if ($note !== null) {
                $out[] = new EnrichmentFinding($candidate->termId, FindingKind::Language, null, $note);
            }
        }

        return $out;
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

    private function nullIfBlank(?string $value): ?string
    {
        return $value !== null && trim($value) !== '' ? trim($value) : null;
    }
}

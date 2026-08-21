<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

use App\Modules\Generation\Domain\ValueObject\DistractorGate;
use App\Modules\Generation\Domain\ValueObject\EnrichmentCandidate;
use App\Modules\Generation\Domain\ValueObject\EnrichmentFinding;
use App\Modules\Generation\Domain\ValueObject\EnrichmentVerdict;
use App\Modules\Generation\Domain\ValueObject\ErrorType;
use App\Modules\Generation\Domain\ValueObject\FindingKind;
use App\Modules\Generation\Domain\ValueObject\RawDistractor;
use App\Modules\Generation\Domain\ValueObject\RawVariant;
use App\Modules\Shared\Domain\Service\LanguagePurity;
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
 *
 * A distractor's three fields are a claim that can be CHECKED, not just fields to fill: «this is the
 * example with exactly this fragment broken, and that fragment should have read this». Five checks
 * hold it to that claim, all through the same normaliser the grader uses — sentence-equal to the
 * example, sentence-equal to a sibling distractor (stored ones included), a span that equals its own
 * correction, a correction that swallowed the sentence's final punctuation, and a repair that does
 * not reproduce the example. Each of the five was found in live store content, and each of them is a
 * card that teaches the learner something false.
 */
final class EnrichmentValidator
{
    /** A pinned example gets 2–3 options' worth of wrong sentences; more is paid-for noise. */
    public const MAX_DISTRACTORS = 3;

    /**
     * How many whitespace tokens longer than the target a variant may be.
     *
     * This guards the one failure that reaches live grading. The model, shown a term AND its example
     * sentence, reliably answers the wrong question: asked for another way to say "workstation" it
     * returns "Your work station is ready for you." Stored, that sentence becomes an accepted answer
     * for a one-word card — the answer key would accept an utterance of a completely different kind,
     * and the grader would be looser than anyone intended.
     *
     * A token budget is the language-neutral way to say "an alternative FORM, not a different kind of
     * utterance": real alternations differ by a word or two (workstation / work station, this / that),
     * sentences differ by many. It counts whitespace runs, so it makes no assumption about script,
     * alphabet or word order.
     */
    public const MAX_VARIANT_EXTRA_TOKENS = 2;

    /**
     * Phrases with which the model argues its own finding down. Two of the eight language findings on
     * the store run ended this way — «банкомат не является ошибкой», «но это корректно» — so the model
     * filled the field, cost a human a read, and concluded there was nothing there. The prompt now
     * forbids it; this is the deterministic backstop, because a prompt rule is a request and this is a
     * guarantee.
     *
     * Matched against the note's own prose, which is written in the learner's language — Russian for
     * every collection that exists. An unrecognised phrasing keeps the finding, so the list is safe to
     * be incomplete.
     */
    private const SELF_REFUTING = [
        'не является ошибкой',
        'не ошибка',
        'это корректно',
        'но корректно',
        'является корректным',
        'вполне корректно',
        'допустимо',
        'is not an error',
        'is correct',
    ];

    /**
     * The languages an enrichment candidate's fields are written in. Fixed, not passed in, because
     * {@see EnrichmentCandidate} itself declares them: `translation`/`example_translation` are the
     * learner's side, `example` the target side, and every collection in existence is ru→en. Named
     * constants rather than inline literals so the two calls into {@see LanguagePurity} read as the
     * same question the generation barrier asks — one detector, one vocabulary for asking it.
     */
    private const LEARNER_LANG = 'ru';

    private const TARGET_LANG = 'en';

    /** JSON punctuation the model sometimes leaks INSIDE a legal string value — see sanitizeNote(). */
    private const JSON_DEBRIS = ['"},{', '},{', '"}', '{"', '"],', '["'];

    /**
     * Markdown emphasis the model wraps the broken fragment in: «Excuse me, is this seat **take**?».
     *
     * Found on the device, and it is not cosmetic — it GIVES THE CARD AWAY. The marked word is exactly
     * the error, so a learner picks the wrong sentence by looking for asterisks and never reads the
     * grammar. The fragment already has a home in `error_span`; decorating the sentence is redundant
     * and harmful, so the markers are stripped from every field before validation, which also keeps
     * the span findable in its own sentence.
     *
     * Neither English nor Russian prose needs these characters, so removing them cannot damage content.
     */
    private const EMPHASIS_MARKERS = ['**', '__', '*', '_'];

    public function __construct(
        private readonly LexicalNormalizer $normalizer = new LexicalNormalizer(),
        private readonly LanguagePurity $purity = new LanguagePurity(),
        private readonly BackTranslationTolerance $tolerance = new BackTranslationTolerance(),
    ) {}

    /**
     * @param  DistractorGateLog|null  $gates  a notepad, never a decision. Passing one makes the
     *        distractor loop name the check it took for each row and changes nothing else — see
     *        {@see DistractorGateLog} for why the reason is recorded here instead of being derived
     *        by a second implementation of these conditions elsewhere.
     */
    public function validate(EnrichmentCandidate $candidate, ?DistractorGateLog $gates = null): EnrichmentVerdict
    {
        $findings = [];

        $accepted = $this->normalizedSet($candidate->acceptedForms);
        [$variants, $rejectedVariants] = $this->validVariants($candidate, $accepted);

        // The two halves of "correct" are kept apart on purpose. Both kill a distractor, but only a
        // collision with a VARIANT is a contradiction worth a person's time: the same pack claimed
        // one sentence is both right and wrong. A distractor that merely folds onto the target is
        // the ordinary scrap this validator exists to absorb — counted, not escalated.
        $variantSet = [];
        foreach ($variants as $variant) {
            $variantSet[$this->normalizer->normalize($variant->text)] = true;
        }

        [$distractors, $proposed, $rejected, $conflicts] = $this->validDistractors($candidate, $accepted, $variantSet, $gates);
        // Rows the loop never reached, because the example already had its full set of keepers.
        $gates?->fillUnexamined($proposed);

        foreach ($conflicts as $sentence) {
            $findings[] = new EnrichmentFinding(
                $candidate->termId,
                FindingKind::VariantConflict,
                null,
                "«{$sentence}» предложено и как верный вариант, и как дистрактор — одно из двух неверно.",
            );
        }

        $ambiguity = $this->ambiguityFinding(
            $candidate,
            $accepted + $variantSet,
            [...$candidate->acceptedForms, ...array_map(static fn (RawVariant $v): string => $v->text, $variants)],
        );
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
            rejectedVariants: $rejectedVariants,
        );
    }

    /**
     * Variants that are worth storing: non-empty, not a normalisation-equal restatement of a form
     * the key ALREADY accepts (that row would buy nothing), within the token budget (see
     * {@see MAX_VARIANT_EXTRA_TOKENS}), and distinct from each other.
     *
     * @param  array<string, true>  $accepted
     * @return array{0: list<RawVariant>, 1: int}  kept, rejected
     */
    private function validVariants(EnrichmentCandidate $candidate, array $accepted): array
    {
        // The term's own text is the length reference — the first accepted form, by construction.
        $budget = $this->tokenCount($candidate->acceptedForms[0] ?? '') + self::MAX_VARIANT_EXTRA_TOKENS;

        $kept = [];
        $rejected = 0;
        $seen = $accepted;
        foreach ($candidate->variants as $variant) {
            $text = $this->stripEmphasis($variant->text);
            if ($text === '') {
                $rejected++;

                continue;
            }
            $key = $this->normalizer->normalize($text);
            if ($key === '') {
                $rejected++;

                continue;
            }
            // A restatement of something already accepted is not a rejection — it is a no-op, and
            // counting it as scrap would make a well-behaved run look broken.
            if (isset($seen[$key])) {
                continue;
            }
            if ($this->tokenCount($text) > $budget) {
                $rejected++;

                continue;
            }
            $seen[$key] = true;
            $kept[] = new RawVariant($text, $this->sanitizeNote($variant->note));
        }

        return [$kept, $rejected];
    }

    /** Whitespace-run token count — script-agnostic on purpose. */
    private function tokenCount(string $value): int
    {
        $trimmed = trim($value);

        return $trimmed === '' ? 0 : count(preg_split('/\s+/u', $trimmed) ?: [$trimmed]);
    }

    /**
     * @param  array<string, true>  $accepted  normalised target forms (incl. previously stored variants)
     * @param  array<string, true>  $variantSet  normalised variants proposed by THIS pack
     * @param  DistractorGateLog|null  $gates  records WHICH branch each row took; never consulted
     * @return array{0: list<RawDistractor>, 1: int, 2: int, 3: list<string>}  kept, proposed, rejected, conflicting
     */
    private function validDistractors(EnrichmentCandidate $candidate, array $accepted, array $variantSet, ?DistractorGateLog $gates = null): array
    {
        $correct = $accepted + $variantSet;
        $proposed = count($candidate->distractors);

        // No pinned example means nothing to hang a distractor on — every row is scrap, and the
        // count says so rather than the run silently reporting a clean sheet.
        if ($candidate->exampleId === null || $this->nullIfBlank($candidate->exampleSentence) === null) {
            $gates?->fillUnexamined($proposed, DistractorGate::NoExample);

            return [[], $proposed, $proposed, []];
        }

        $sentence = (string) $candidate->exampleSentence;
        $kept = [];
        $conflicts = [];

        // Dedup starts from what is ALREADY on this example, not from an empty set. A pack that only
        // knows its own rows deduplicates only within itself, which is exactly how the top-up run put
        // «I'd like the pasta for go» beside «I’d like the pasta for go» — the same sentence twice,
        // differing in the apostrophe glyph, because the twin lived in the database and not in the pack.
        $seen = $this->normalizedSet($candidate->existingDistractors);

        foreach ($candidate->distractors as $index => $raw) {
            // Emphasis first: the span has to be findable in its own sentence, and stripping both
            // sides keeps that true instead of failing a row the model merely over-decorated.
            $text = $this->stripEmphasis($raw->sentence);
            $span = $this->stripEmphasis($raw->errorSpan);
            $key = $this->normalizer->normalize($text);

            if ($text === '' || $key === '' || isset($seen[$key])) {
                $gates?->record($index, $text === '' || $key === '' ? DistractorGate::EmptySentence : DistractorGate::Duplicate);

                continue;
            }
            $seen[$key] = true;

            // A "wrong" sentence our own grader would accept is not wrong. Escalate only when it was
            // a VARIANT that made it correct — see the note where the two sets are built.
            if (isset($correct[$key])) {
                if (isset($variantSet[$key])) {
                    $conflicts[] = $text;
                }
                $gates?->record($index, isset($variantSet[$key]) ? DistractorGate::VariantConflict : DistractorGate::EqualsAcceptedAnswer);

                continue;
            }
            if (ErrorType::tryFromWire($raw->errorType) === null) {
                $gates?->record($index, DistractorGate::UnknownErrorType);

                continue;
            }
            // The span has to be findable in the sentence it claims to be a fragment of, or the
            // reveal ("this bit is the mistake") has nothing to underline. Case-insensitive so a
            // sentence-initial capital doesn't fail an otherwise good row.
            if ($span === '' || mb_stripos($text, $span) === false) {
                $gates?->record($index, DistractorGate::SpanNotFound);

                continue;
            }
            // The error span must not fall inside an occurrence of the term's OWN accepted answer —
            // marking part of the term's canonical wording as a mistake is not a distractor, it's the
            // correct answer with a false "correction" grafted onto it. «mute the phone» → span «the»,
            // correction «your»: the row repairs cleanly to the pinned example and passes every check
            // in this method, but "mute the phone" is the term's own accepted form and "the" in it is
            // not wrong — the example just phrased the sentence with a possessive instead. Live on the
            // topup-3 store, case #40 (docs/enrich-v1-topup3-full-store.md).
            if ($this->spanIsInsideAcceptedForm($text, $span, $candidate->acceptedForms)) {
                $gates?->record($index, DistractorGate::SpanInsideAcceptedForm);

                continue;
            }
            $correction = $this->stripEmphasis($raw->correction);
            if ($correction === '') {
                $gates?->record($index, DistractorGate::EmptyCorrection);

                continue;
            }
            // A correction that swallowed the sentence's final mark. The card prints it literally —
            // «должно быть: organic?» — and a learner who substitutes it back gets «…organic??».
            // Both live rows on the term «organic» read that way: span «organics», correction
            // «organic?». The circular check below cannot see it, because canonicalize() strips
            // punctuation on both sides and the repair matches the example either way.
            if ($this->carriesSentenceEnd($span, $correction)) {
                $gates?->record($index, DistractorGate::CorrectionCarriesSentenceEnd);

                continue;
            }
            // A distractor identical to the pinned example is not a distractor.
            if ($this->sentenceEquals($text, $sentence)) {
                $gates?->record($index, DistractorGate::EqualsExample);

                continue;
            }
            // A correction that corrects nothing. «has been» → «has been» is the whole feedback the
            // learner gets for picking this option: the card underlines a fragment and then offers the
            // same fragment back as what it should have been. Compared through canonicalize() and NOT
            // normalize(), because the leading article normalize() drops is precisely what an
            // `article` row corrects — «bank account» → «a bank account» is the class working.
            if ($this->normalizer->canonicalize($span) === $this->normalizer->canonicalize($correction)) {
                $gates?->record($index, DistractorGate::NoOpCorrection);

                continue;
            }
            // The circular check: apply the row's OWN repair and see whether it lands on the example.
            //
            // Every field of a distractor claims the same thing — «this sentence is the example with
            // exactly this fragment broken». The claim is checkable: put the correction back where the
            // span is and the example must come out. When it does not, one of the three fields is
            // lying, and the learner is shown a repair that does not repair. «Do you offer online
            // banking at services?» with `at` → `services` yields «…banking services services?», which
            // is how that row announced that its span was never the error.
            //
            // canonicalize(), not normalize(): the repair has to reproduce the example INCLUDING its
            // leading article, or «Delivery must arrive…» would repair to itself and still match
            // «The delivery must arrive…» with the article thrown away on both sides.
            if (! $this->repairsTo($text, $span, $correction, $sentence)) {
                $gates?->record($index, DistractorGate::RepairDoesNotMatchExample);

                continue;
            }

            $kept[] = new RawDistractor($text, strtolower(trim($raw->errorType)), $span, $correction);
            $gates?->record($index, DistractorGate::Kept);
            if (count($kept) === self::MAX_DISTRACTORS) {
                break;
            }
        }

        return [$kept, $proposed, $proposed - count($kept), $conflicts];
    }

    /**
     * Does the correction carry a sentence-ending mark the span it replaces does not have?
     *
     * The span and the correction are the two halves of one substitution: «this fragment should have
     * read that». A fragment inside a sentence does not own the sentence's final «?» — so a
     * correction that ends with one is claiming to replace punctuation it was never given, and the
     * substitution doubles it up.
     *
     * The exception is real: when the span itself ends in that mark («cost?» → «costs?») the
     * correction must carry it too, or the repair would delete the sentence's punctuation.
     */
    private function carriesSentenceEnd(string $span, string $correction): bool
    {
        $last = mb_substr(rtrim($correction), -1);
        if (! in_array($last, ['.', '?', '!', '…'], true)) {
            return false;
        }

        return mb_substr(rtrim($span), -1) !== $last;
    }

    /**
     * The back-translation check. The model is asked to reconstruct the English from the RUSSIAN
     * prompt alone; if what comes back isn't something we would accept as the answer, then the card
     * asks a question its own prompt doesn't determine — and no variant closed the gap. That is a
     * rewrite candidate, never an automatic edit.
     *
     * @param  array<string, true>  $correct  normalised {target ∪ variants}, for the exact match
     * @param  list<string>  $acceptedForms  the same set unnormalised, for the tolerance check
     */
    private function ambiguityFinding(EnrichmentCandidate $candidate, array $correct, array $acceptedForms): ?EnrichmentFinding
    {
        // Never asked → nothing to conclude. The `machinery` shape produces no translations at all,
        // so treating its (necessarily) absent back-translation as a failed one flagged EVERY term
        // it touched: twenty findings on a twenty-term collection, all of them saying the model had
        // not restored a reference it was never asked to restore. A worklist that flags everything
        // is a worklist nobody reads.
        if (! $candidate->backTranslationAsked) {
            return null;
        }

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

        // Not an exact match, but close enough that flagging it would be noise: one function word, or
        // the same word inflected. Suppressing these is what makes the ambiguity rate a number worth
        // reading — see BackTranslationTolerance for why "one token" alone is not the rule.
        if ($this->tolerance->isNearMiss($back, $acceptedForms)) {
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

        // Learner-language fields: letters that only exist in the close relative are decisive
        // evidence of leakage, which is repaired by regenerating — hence UaLeakage, not Language.
        foreach (['translation' => $candidate->translation, 'example_translation' => $candidate->exampleTranslation] as $field => $value) {
            $value = $this->nullIfBlank($value);
            if ($value === null) {
                continue;
            }
            $ua = $this->purity->foreignLetters(self::LEARNER_LANG, $value);
            if ($ua !== []) {
                $out[] = new EnrichmentFinding(
                    $candidate->termId,
                    FindingKind::UaLeakage,
                    $field,
                    'Украинские буквы в русском поле (' . implode(' ', $ua) . "): «{$value}».",
                );
            }
        }

        // English fields: any non-Latin letter.
        $sentence = $this->nullIfBlank($candidate->exampleSentence);
        if ($sentence !== null) {
            $foreign = $this->purity->foreignLetters(self::TARGET_LANG, $sentence);
            if ($foreign !== []) {
                $out[] = new EnrichmentFinding(
                    $candidate->termId,
                    FindingKind::Language,
                    'example',
                    'Не-латиница в английском поле (' . implode(' ', $foreign) . "): «{$sentence}».",
                );
            }
        }

        // The model's lexis notes — the half a character check cannot see (see LanguagePurity):
        // leakage spelled in shared letters, and words that are simply not words. The model names the
        // class; an unrecognised class degrades to the coarse kind rather than being dropped, so a
        // newer prompt can add one without a run losing findings.
        foreach ($candidate->languageNotes as $note) {
            $detail = $this->nullIfBlank($note->detail);
            if ($detail === null || $this->isSelfRefuting($detail)) {
                continue;
            }
            $out[] = new EnrichmentFinding($candidate->termId, $this->noteKind($note->kind), null, $detail);
        }

        return $out;
    }

    /** A note that concludes there is no problem is not a finding — it is a wasted read. */
    private function isSelfRefuting(string $detail): bool
    {
        $lowered = mb_strtolower($detail);
        foreach (self::SELF_REFUTING as $phrase) {
            if (str_contains($lowered, $phrase)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scrub JSON punctuation out of a note. Structured Outputs guarantees the ENVELOPE is valid JSON;
     * it does not stop the model putting `"},{ ` inside a string, which is legal JSON and garbage on
     * screen (seen live on «Синонимы."},{»). The note is a proofreading aid, so scrubbing it is right
     * and dropping the variant over it would be absurd. A note that is only debris becomes null.
     */
    private function sanitizeNote(?string $note): ?string
    {
        $note = $this->nullIfBlank($note);
        if ($note === null) {
            return null;
        }

        // Emphasis too. The sweep that cleaned the sentences went field by field and this field was
        // not on the list — the note is prose a person reads, and «_синоним_» is decoration in it for
        // exactly the same reason it is decoration in a sentence.
        $clean = $this->stripEmphasis(str_replace(self::JSON_DEBRIS, '', $note));
        // Braces/brackets left at the ends are debris. The double quote is NOT in this list on
        // purpose: a note legitimately ends with one («синоним к слову "залог"»), and trimming it
        // would eat the prose to tidy up the model's mess.
        $clean = trim($clean, " \t\n\r\0\x0B{}[],");

        return $this->nullIfBlank($clean);
    }

    private function noteKind(string $wire): FindingKind
    {
        return match (strtolower(trim($wire))) {
            'ua_leakage' => FindingKind::UaLeakage,
            'misspelled_or_nonword' => FindingKind::MisspelledOrNonword,
            default => FindingKind::Language,
        };
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

    /**
     * The circular check as a question anyone can ask: does this row's own repair give back the
     * example? Public because a human editing a span/correction by hand is subject to exactly the same
     * rule as the model, and the review applier checks a proposed fix with it before writing it.
     */
    public function repairsTo(string $sentence, string $span, string $correction, string $example): bool
    {
        $repaired = $this->repair($sentence, $span, $correction);

        return $repaired !== '' && $repaired === $this->normalizer->canonicalize($example);
    }

    /**
     * Sentence equality, tolerant of a bare 's contraction on either side.
     *
     * {@see LexicalNormalizer::expandContractions()} only resolves the curated pronouns — "it's",
     * "that's", "there's" — because an arbitrary "'s" is genuinely ambiguous between "is" and "has".
     * Left unresolved, an arbitrary "'s" (e.g. "How's", "Here's") is not expanded at all: the
     * apostrophe is stripped as punctuation and the "s" survives as its own dangling token, so
     * normalize() never folds it onto the spelled-out form. A distractor that only spells out such a
     * contraction is not a distractor — it's the example — and the risk is one-sided: missing the
     * match writes a card that marks the correct answer wrong. Both readings are cheap to try, and
     * either matching is enough to settle it, so this is deliberately over-eager rather than exact.
     *
     * Scoped to the two callers that ask "is this sentence secretly the example" — the generation-time
     * check above and {@see AuditDistractorsHandler}'s retro-audit of the same question on stored rows.
     * Grading (LexicalNormalizer, used directly by the answer grader) and the circular check
     * ({@see repairsTo}) are untouched.
     */
    public function sentenceEquals(string $a, string $b): bool
    {
        $keyA = $this->normalizer->normalize($a);
        $keyB = $this->normalizer->normalize($b);
        if ($keyA === $keyB) {
            return true;
        }
        foreach ($this->contractionReadings($a) as $reading) {
            if ($this->normalizer->normalize($reading) === $keyB) {
                return true;
            }
        }
        foreach ($this->contractionReadings($b) as $reading) {
            if ($keyA === $this->normalizer->normalize($reading)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Both readings of every bare 's in the text — "is" and "has" — or an empty list when there is
     * none to expand. Matches possessives too ("Tom's"), which is harmless here: a bogus reading like
     * "Tom is" simply matches nothing, so it costs an extra comparison and never a false positive.
     *
     * @return list<string>
     */
    private function contractionReadings(string $text): array
    {
        if (! preg_match("/\b[a-zA-Z]+'s\b/", $text)) {
            return [];
        }

        return [
            (string) preg_replace("/\b([a-zA-Z]+)'s\b/", '$1 is', $text),
            (string) preg_replace("/\b([a-zA-Z]+)'s\b/", '$1 has', $text),
        ];
    }

    /**
     * Substitute the correction for the span and normalise the result — the distractor as the row
     * itself says it should have been written.
     *
     * The FIRST occurrence only, case-insensitively: that is the one the card underlines, so this
     * repairs the fragment the learner is actually shown rather than every look-alike in the string.
     */
    private function repair(string $sentence, string $span, string $correction): string
    {
        $at = $this->spanPosition($sentence, $span);
        if ($at === false) {
            return '';
        }

        $repaired = mb_substr($sentence, 0, $at) . $correction . mb_substr($sentence, $at + mb_strlen($span));

        return $this->normalizer->canonicalize($repaired);
    }

    /**
     * Where the span really sits in its sentence: the first occurrence that is not buried inside a
     * longer word.
     *
     * A raw substring search is wrong for exactly the spans that matter most — the short function
     * words. «on» is inside «resp**on**sible», «in» is inside «**in**tegrate», «a» is inside almost
     * everything. A preposition distractor whose span is «on» would have its repair applied to the
     * middle of another word, come out as «respforsible», and be scrapped as a broken row although
     * nothing was wrong with it. Three live rows died this way before this method existed.
     *
     * Falls back to the raw first occurrence when no standalone one exists, so this only ever makes
     * the search SMARTER — it never makes a span unfindable that was findable before.
     */
    private function spanPosition(string $sentence, string $span): int|false
    {
        $offset = 0;
        while (($at = mb_stripos($sentence, $span, $offset)) !== false) {
            $before = $at > 0 ? mb_substr($sentence, $at - 1, 1) : ' ';
            $after = mb_substr($sentence, $at + mb_strlen($span), 1);
            if (! $this->isWordChar($before) && ! $this->isWordChar($after)) {
                return $at;
            }
            $offset = $at + 1;
        }

        return mb_stripos($sentence, $span);
    }

    private function isWordChar(string $char): bool
    {
        return $char !== '' && preg_match('/[\p{L}\p{N}]/u', $char) === 1;
    }

    /**
     * Does the span [position, position+len) overlap an occurrence of one of the term's accepted
     * answers in this sentence? Case-insensitive; every occurrence of every form is checked, not
     * just the first, because a phrase can legitimately appear more than once in a sentence.
     *
     * @param  list<string>  $acceptedForms
     */
    private function spanIsInsideAcceptedForm(string $sentence, string $span, array $acceptedForms): bool
    {
        $spanAt = $this->spanPosition($sentence, $span);
        if ($spanAt === false) {
            return false;
        }
        $spanEnd = $spanAt + mb_strlen($span);

        foreach ($acceptedForms as $form) {
            $form = trim($form);
            if ($form === '') {
                continue;
            }
            $offset = 0;
            while (($at = mb_stripos($sentence, $form, $offset)) !== false) {
                $end = $at + mb_strlen($form);
                if ($at < $spanEnd && $spanAt < $end) {
                    return true;
                }
                $offset = $at + 1;
            }
        }

        return false;
    }

    /** @see EMPHASIS_MARKERS — the model marks the error, which hands the learner the answer. */
    private function stripEmphasis(string $value): string
    {
        return trim(str_replace(self::EMPHASIS_MARKERS, '', $value));
    }

    private function nullIfBlank(?string $value): ?string
    {
        return $value !== null && trim($value) !== '' ? trim($value) : null;
    }
}

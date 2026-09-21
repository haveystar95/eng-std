<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Assembly\Options;
use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Check\Language\LanguageWords;

/**
 * THE ROLE SAYS ITS OWN LINES (наряд CONV-2, пп. 1 и 4б) — the two guards the server keeps over every reply of the
 * role, because a prompt is a request and not a guarantee.
 *
 * 1. THE LEARNER'S PART IS NOT THE ROLE'S. The key lines of a talk are the lines the learner came to SAY — the tenant's
 *    questions to the agent, the parent's answers to the doctor. On the owner's phone (21.09) the role said them
 *    itself: the agent opened with «Is this flat two rooms?», the gym receptionist asked «Do you have a day pass?», and
 *    the learner was left answering as the agent. A sentence of the reply that shares {@see LEARNER_LINE} of its words
 *    ({@see Options::share()}, the measure «two options mean the same» is judged by) with a line of the learner is that
 *    line — with three exceptions, each of which is the role answering rather than taking the learner's part:
 *    - a question answered by a statement, or a statement turned into a question, is the other side of the line: «Yes,
 *      parking is included.» is the agent's answer to «Is parking included?», not the same line (75 % of the words);
 *    - a sentence that says back what the learner has SAID in this talk is an echo of their move («Your son has a fever
 *      — how long?», «Since this is your first visit, …»), the thing the role is there to do;
 *    - a sentence or a line of fewer than {@see MIN_WORDS} words («Yes, please.», «I see.») is too short to be anybody's
 *      part: sharing it is saying «yes».
 *    A QUESTION is also cut at its commas, so «What monthly memberships do you have in mind, and do you need weekdays
 *    only?» is caught by its first half — the reply of the live gym talk that a whole-sentence reading let through
 *    (43 %). A statement is read whole: «Since this is your first visit, I can explain the rules» is the role speaking,
 *    and its first half is not the member's «Yes, this is my first visit.» (the replay of report §1, call 15).
 *
 * 2. A RESCUE SAYS IT AGAIN IN OTHER WORDS. «Не понял» asks for the same meaning, simpler; both live runs of CLIENT-CONV-1a
 *    got the same line word for word. A rescue reply that shares {@see SAME_WORDS} of its words with the line it rescues
 *    is that line again.
 *
 * 3. THE ROLE DOES NOT SAY BACK WHAT THE LEARNER HAS JUST SAID (наряд BACK-TAILS-2 §9). Guard 1 reads the reply against the
 *    lines the PLAN gives the learner, and lets an echo of the talk through — so on the owner's gym replay the
 *    receptionist answered «Weekdays works for me» with «That works for me on weekdays» in its own first person, and the
 *    words could not tell it from listening. The second reading is against the learner's LAST MOVE as heard, by the rule
 *    the talk ticks its phrases by ({@see PhraseUse::share()}): a sentence of the reply whose key words the move had
 *    already said — {@see ECHO} of them or more, by their bases, in any order; as said, or with the first and second
 *    person swapped («My son has a fever» → «Your son has a fever») — is the move said back. Read as a share of the
 *    SENTENCE, not an overlap of the two: «It started three days ago.» after «His lower back hurts, and three days ago it
 *    started.» is all of it the learner's, however much more the move said (the live run of 22.09 — an overlap of the two
 *    came to 0.5). One exception, the one guard 1 has too: the learner ASKED and the role says a statement — an answer in
 *    the question's words («Can I pay by card» → «Yes, you can pay by card.») is the role answering.
 *
 * What the guards do about it — ask the model once more, with the reason — is the caller's; these only say what is wrong.
 */
final class RoleLines
{
    /** How close to a line of the learner a sentence of the role may come before it IS that line (наряд CONV-2, п. 1). */
    public const LEARNER_LINE = Options::APART;

    /** How close a rescue may stay to the line it rescues before it is the same line again (наряд CONV-2, п. 4б). */
    public const SAME_WORDS = 0.7;

    /** How close a sentence of the role may come to the learner's last move before it is that move said back (§9). */
    public const ECHO = 0.7;

    /** The fewest words a sentence and a line need to be compared at all. */
    public const MIN_WORDS = 3;

    /** The reply said a line of the learner and was asked for again — counted per prompt version (`plan_check_counters`). */
    public const CODE_LEARNER_LINE = 'conversation.learner_line';

    /** …and the answer asked for again said one too: the sentence that says it was cut out, the rest of the answer said. */
    public const CODE_LEARNER_LINE_CUT = 'conversation.learner_line_cut';

    /** …and there was nothing to cut it from (the whole answer is the learner's line): the talk went on with what there was. */
    public const CODE_LEARNER_LINE_KEPT = 'conversation.learner_line_kept';

    /** A rescue said the rescued line again and was asked for in other words. */
    public const CODE_SAME_WORDS = 'conversation.rescue_same_words';

    /** …and the answer asked for again was the same words too, or did not come. */
    public const CODE_SAME_WORDS_KEPT = 'conversation.rescue_same_words_kept';

    /** The reply said the learner's last move back and was asked for again (наряд BACK-TAILS-2 §9). */
    public const CODE_LEARNER_ECHO = 'conversation.learner_echo';

    /** …and the answer asked for again said it back too: the sentence that says it was cut out, the rest said. */
    public const CODE_LEARNER_ECHO_CUT = 'conversation.learner_echo_cut';

    /** …and nothing was left once it was cut out: the role said the pack's neutral line instead. */
    public const CODE_LEARNER_ECHO_NEUTRAL = 'conversation.learner_echo_neutral';

    /** …and there was no neutral line to say (a language whose pack has none): the answer was said as it came. */
    public const CODE_LEARNER_ECHO_KEPT = 'conversation.learner_echo_kept';

    /** The reasons a second try of a move is asked with — `REDO` of the prompt. */
    public const REDO_LEARNER_LINE = 'learner_line';

    public const REDO_SAME_WORDS = 'same_words';

    public const REDO_LEARNER_ECHO = 'learner_echo';

    /**
     * The learner line the reply says as the role's own, or null when every sentence of it is the role's.
     *
     * @param  list<string>  $learnerLines  the learner's lines of the scenes the talk walks
     * @param  list<string>  $said  what the learner has said in this talk, the move being answered included — an echo of it is an answer
     */
    public static function learnerLineIn(string $reply, array $learnerLines, array $said = []): ?string
    {
        foreach (self::units($reply) as [$unit, $question]) {
            if (Words::count($unit) < self::MIN_WORDS || self::echoes($unit, $said)) {
                continue;
            }
            foreach ($learnerLines as $line) {
                if (Words::count($line) < self::MIN_WORDS || self::isQuestion($line) !== $question) {
                    continue;
                }
                if (Options::share($unit, $line) >= self::LEARNER_LINE) {
                    return $line;
                }
            }
        }

        return null;
    }

    /**
     * THE ANSWER WITHOUT THE LEARNER'S PART — the last resort when the answer asked for again says a learner line too
     * (наряд CONV-2, п. 1): the sentences that say one are cut out of the reply and out of its translation, sentence for
     * sentence, and the rest is the role's own. On the owner's rehearsal «Yes, it has a living room and one bedroom. What
     * is the rent?» — the agent asking the tenant's question twice over — became «Yes, it has a living room and one
     * bedroom.». Null when there is nothing to keep (every sentence is the learner's) or the translation does not split
     * the way the reply does: a translation that no longer says what the reply says is worse than the reply as it was.
     *
     * @param  list<string>  $learnerLines
     * @param  list<string>  $said
     * @return array{target: string, native: string}|null
     */
    public static function withoutLearnerLines(string $reply, string $native, array $learnerLines, array $said = []): ?array
    {
        $targets = self::sentences($reply);
        $natives = self::sentences($native);
        if (count($targets) < 2 || count($targets) !== count($natives)) {
            return null;
        }
        $keep = [];
        $keepNative = [];
        foreach ($targets as $i => $sentence) {
            if (self::learnerLineIn($sentence, $learnerLines, $said) === null) {
                $keep[] = $sentence;
                $keepNative[] = $natives[$i];
            }
        }
        if ($keep === [] || count($keep) === count($targets)) {
            return null;
        }

        return ['target' => implode(' ', $keep), 'native' => implode(' ', $keepNative)];
    }

    /** Is this rescue the line it rescues said again ({@see SAME_WORDS})? No line to rescue — nothing to repeat. */
    public static function repeats(string $reply, ?string $rescued): bool
    {
        return $rescued !== null && trim($rescued) !== '' && Options::share($reply, $rescued) >= self::SAME_WORDS;
    }

    /**
     * The sentence of the reply that says the learner's last move back (guard 3), or null. A sentence shorter than
     * {@see MIN_WORDS} words says nothing of anybody's; a statement after a question the learner asked is an answer.
     */
    public static function echoIn(string $reply, string $heard, LanguagePack $pack, PhraseUse $words = new PhraseUse): ?string
    {
        foreach (self::sentences($reply) as $sentence) {
            if (self::isEcho($sentence, $heard, $pack, $words)) {
                return $sentence;
            }
        }

        return null;
    }

    /**
     * THE ANSWER WITHOUT THE ECHO (guard 3, the second try): the sentences that say the move back are cut out of the reply
     * and out of its translation, sentence for sentence, and the rest is the role's own. Null when nothing would be left,
     * or when the translation does not split the way the reply does — then the caller says the pack's neutral line.
     *
     * @return array{target: string, native: string}|null
     */
    public static function withoutEcho(string $reply, string $native, string $heard, LanguagePack $pack, PhraseUse $words = new PhraseUse): ?array
    {
        $targets = self::sentences($reply);
        $natives = self::sentences($native);
        if (count($targets) !== count($natives)) {
            return null;
        }
        $keep = [];
        $keepNative = [];
        foreach ($targets as $i => $sentence) {
            if (! self::isEcho($sentence, $heard, $pack, $words)) {
                $keep[] = $sentence;
                $keepNative[] = $natives[$i];
            }
        }
        if ($keep === [] || count($keep) === count($targets)) {
            return null;
        }

        return ['target' => implode(' ', $keep), 'native' => implode(' ', $keepNative)];
    }

    /** Does this one sentence of the role say the move back — as heard, or with the persons swapped? */
    private static function isEcho(string $sentence, string $heard, LanguagePack $pack, PhraseUse $words): bool
    {
        if (trim($heard) === '' || Words::count($sentence) < self::MIN_WORDS) {
            return false;
        }
        if (! self::isQuestion($sentence) && (new LanguageWords($pack))->isQuestion($heard)) {
            return false;
        }

        return $words->share($sentence, $heard, $pack) >= self::ECHO
            || $words->share($sentence, $heard, $pack, swapPersons: true) >= self::ECHO;
    }

    /**
     * Every sentence of the reply and, when a question has commas, every part of it — each with whether its sentence asks.
     *
     * @return list<array{0: string, 1: bool}>
     */
    private static function units(string $reply): array
    {
        $out = [];
        foreach (self::sentences($reply) as $sentence) {
            $question = self::isQuestion($sentence);
            $out[] = [$sentence, $question];
            $parts = $question ? (preg_split('/[,;:]\s*/u', $sentence, -1, PREG_SPLIT_NO_EMPTY) ?: []) : [];
            if (count($parts) > 1) {
                foreach ($parts as $part) {
                    $out[] = [$part, $question];
                }
            }
        }

        return $out;
    }

    /** @param list<string> $said */
    private static function echoes(string $unit, array $said): bool
    {
        foreach ($said as $line) {
            if (trim($line) !== '' && Options::share($unit, $line) >= self::LEARNER_LINE) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> the sentences of a text, each with its closing mark */
    private static function sentences(string $text): array
    {
        return preg_split('/(?<=[.?!…])\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private static function isQuestion(string $text): bool
    {
        return str_ends_with(rtrim($text, " \t\n\r\"'»”’)]"), '?');
    }
}

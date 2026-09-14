<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Assembly\CardPayloads;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\ValueObject\Speaker;
use App\Modules\Plan\Domain\ValueObject\TermKind;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * EVERYTHING A DAY SAYS OUT LOUD, AND WHAT EACH FILE IS CALLED (DAY-UI-3).
 *
 * Canon: the server voices every line of the dialogue — the partner's AND the learner's — every
 * phrase and every word, in the scene's two voices. A file is named by the unit it voices
 * (`plan_line_audios.line_ref`): `x3` — the partner's line of exchange 3 (the name it had when only
 * the partner was voiced), `x3b` — the learner's line of exchange 3, `p2` — phrase 2, `v5` — word or
 * chunk 5 (the refs `plan_terms` already carry).
 */
final class SpokenLines
{
    public static function partnerRef(int $step): string
    {
        return CardPayloads::exchangeRef($step);
    }

    public static function learnerRef(int $step): string
    {
        return CardPayloads::exchangeRef($step).'b';
    }

    /**
     * The dialogue in its order, both speakers: [ref, speaker, text]. A repeated step (the model's
     * slip, counted by `exchange_shape`) is one file per ref — the first line keeps it.
     *
     * @return list<array{ref: string, speaker: Speaker, text: string}>
     */
    public static function dialogue(Lesson $lesson): array
    {
        $out = [];
        $seen = [];
        foreach ($lesson->exchanges as $exchange) {
            foreach ($exchange->messages as $message) {
                $speaker = $message->isLearner() ? Speaker::Learner : Speaker::Partner;
                $ref = $speaker === Speaker::Partner ? self::partnerRef($exchange->step) : self::learnerRef($exchange->step);
                $text = trim($message->textTarget);
                if ($text === '' || isset($seen[$ref])) {
                    continue;
                }
                $seen[$ref] = true;
                $out[] = ['ref' => $ref, 'speaker' => $speaker, 'text' => $text];
            }
        }

        return $out;
    }

    /**
     * The phrases, or the words and chunks, of a scene in their order: [ref, text].
     *
     * @param  list<PlanTerm>  $terms
     * @return list<array{ref: string, text: string}>
     */
    public static function terms(array $terms, bool $phrases): array
    {
        $out = [];
        foreach ($terms as $term) {
            if (($term->kind() === TermKind::Phrase) !== $phrases || trim($term->textTarget()) === '') {
                continue;
            }
            $out[] = ['ref' => $term->ref(), 'text' => trim($term->textTarget())];
        }

        return $out;
    }

    /**
     * Whose voice a stored ref is: the partner's line is the partner's, everything else — the
     * learner's line, a phrase, a word — is the learner's.
     */
    public static function speakerOf(string $ref): Speaker
    {
        return CardPayloads::stepOfRef($ref) !== null ? Speaker::Partner : Speaker::Learner;
    }

    /**
     * THE CAST OF A SCENE THAT NEVER HAD ONE — a lesson written before voices had genders.
     *
     * The stored gender wins, then the role's gender the lesson named. A scene with neither keeps the
     * default cast — unless the learner's material (phrases, words, the learner's lines) is already on
     * the disk in ONE of the pack's voices: then that voice is the learner's. Paid-for files are used
     * (owner, DAY-UI-3: «51 купленная фраза используется»), and «the learner's voice says their phrases»
     * still holds. The partner's lines are not a reason: the dialogue is always bought whole, in one call,
     * so they come again with it.
     *
     * @param  array<string, int>  $learnerMaterial  voice gender value → rows of learner material already in that voice
     */
    public static function castOf(?VoiceGender $stored, ?VoiceGender $roleGender, array $learnerMaterial, VoiceGender $default): VoiceGender
    {
        if ($stored !== null) {
            return $stored;
        }
        if ($roleGender !== null) {
            return $roleGender;
        }
        $voiced = array_keys(array_filter($learnerMaterial, static fn (int $rows): bool => $rows > 0));
        if (count($voiced) === 1) {
            $learner = VoiceGender::tryFromAny($voiced[0]);
            if ($learner !== null) {
                return $learner->opposite();
            }
        }

        return $default;
    }
}

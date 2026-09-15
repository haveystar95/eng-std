<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Assembly\CardPayloads;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\ValueObject\Speaker;
use App\Modules\Plan\Domain\ValueObject\TermKind;

/**
 * EVERYTHING A DAY SAYS OUT LOUD, AND WHAT EACH FILE IS CALLED (DAY-UI-3, TTS-2).
 *
 * Canon: the server voices every line of the dialogue — the partner's AND the learner's — every phrase, every phrase
 * said with each of its other fillers, and every word, in the scene's two voices. A file is named by the unit it voices
 * (`plan_line_audios.line_ref`): `x3` — the partner's line of exchange 3 (the name it had when only the partner was
 * voiced), `x3b` — the learner's line of exchange 3, `p2` — phrase 2, `p2.f3` — the frame of phrase 2 said with its
 * third filler (the address the lesson's validator already gives a filler), `v5` — word or chunk 5 (the refs
 * `plan_terms` already carry).
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

    public static function fillerRef(string $phraseRef, int $index): string
    {
        return $phraseRef.'.f'.($index + 1);
    }

    /**
     * The dialogue in its order, both speakers: [ref, speaker, text]. A repeated step (the model's
     * slip, counted as `dialogue.count`) is one file per ref — the first line keeps it.
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
     * A phrase said with each of its fillers (TTS-2): the frame with the filler in its slot, as the learner would say
     * it — with the phrase's closing mark when the frame is written without one, the mark the phrase took from its
     * line. The phrase itself already IS its frame said with one filler — that filler is voiced as the phrase
     * (`voicedAs` names the phrase's own ref) and not bought twice; every other filler is its own file. A filler the
     * frame cannot be said with (no slot, a second slot left) has no sound.
     *
     * @return list<array{index: int, ref: string, text: string, voicedAs: string}>
     */
    public static function fillers(PlanTerm $phrase): array
    {
        $frame = $phrase->frame();
        if ($phrase->kind() !== TermKind::Phrase || $frame === null || ! FrameText::hasSlot($frame->frameTarget)) {
            return [];
        }
        $out = [];
        foreach ($frame->fillers() as $index => $filler) {
            $text = FrameText::fill($frame->frameTarget, $filler->target);
            if ($text === '' || FrameText::hasSlot($text)) {
                continue;
            }
            $text = FrameText::withEndMarkOf($text, $phrase->textTarget());
            $ref = self::fillerRef($phrase->ref(), $index);
            $out[] = [
                'index' => $index,
                'ref' => $ref,
                'text' => $text,
                'voicedAs' => $text === trim($phrase->textTarget()) ? $phrase->ref() : $ref,
            ];
        }

        return $out;
    }

    /**
     * Whose voice a stored ref is: the partner's line is the partner's, everything else — the
     * learner's line, a phrase, a filler, a word — is the learner's.
     */
    public static function speakerOf(string $ref): Speaker
    {
        return CardPayloads::stepOfRef($ref) !== null ? Speaker::Partner : Speaker::Learner;
    }
}

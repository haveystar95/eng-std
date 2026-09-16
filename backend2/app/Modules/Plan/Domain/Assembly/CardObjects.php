<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\Lesson\Phrase;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\Service\SpokenLines;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;
use App\Modules\Plan\Domain\ValueObject\Speaker;

/**
 * THE OBJECTS CARDS ARE MADE OF (наряд SESSION-1a, разд. 1): a term, an exchange, a line of it — the partner's, the
 * learner's own, the partner's line a learner line speaks to, a line of the visit as the listening plays it — a
 * frame, its fillers and the phrase as it is said.
 *
 * One exchange is shown by «Фразы», «Диалог», «Слушаю и отвечаю» and «Говорю сам», one frame by a phrase card, under
 * a dialogue line and on a speaking card — so every object is built HERE and only here, and the five card classes
 * call it. A card that drew one of them its own way would drift from the others the first time one of them changed:
 * a line's ref, its sound, its key or a filler's file would read differently on two cards of one day.
 *
 * Nothing here decides whether a card is dealt; a builder returns null only when the lesson has nothing to show
 * (no partner line, no frame). Every sound is an {@see Audio} stub and every picture `{url: null, tone: null}`, both
 * resolved when the card is read.
 */
final class CardObjects
{
    /**
     * A word or a chunk as its cards show it: `{ref: 'v3', text_target, pronunciation_native, text_native,
     * definition_target, image}`.
     *
     * @return array{ref: string, text_target: string, pronunciation_native: string|null, text_native: string, definition_target: string|null, image: array{url: null, tone: null}}
     */
    public static function term(PlanTerm $term): array
    {
        return [
            'ref' => $term->ref(),
            'text_target' => $term->textTarget(),
            'pronunciation_native' => $term->pronunciationNative(),
            'text_native' => $term->textNative(),
            'definition_target' => $term->definitionTarget(),
            'image' => ['url' => null, 'tone' => null],
        ];
    }

    /**
     * The exchange a card is about: `{ref: 'x3', step, kind}`.
     *
     * @return array{ref: string, step: int, kind: string}
     */
    public static function exchange(Exchange $exchange): array
    {
        return ['ref' => SpokenLines::exchangeRef($exchange->step), 'step' => $exchange->step, 'kind' => $exchange->kind->value];
    }

    /**
     * The name of a line of the visit — the name of its file: the partner's line of exchange 3 is `x3`, the
     * learner's `x3b`.
     */
    public static function lineRef(int $step, Message $message): string
    {
        return $message->isLearner() ? SpokenLines::learnerRef($step) : SpokenLines::partnerRef($step);
    }

    /**
     * One line of an exchange as a card plays it: `{ref, text_target, text_native, audio}`, named as its file
     * ({@see lineRef()}), so the sound the reader resolves is the line shown.
     *
     * @return array{ref: string, text_target: string, text_native: string, audio: array{ref: string, voice: 'partner'|'learner', url: null, duration_ms: null}}
     */
    public static function line(Exchange $exchange, Message $message): array
    {
        $ref = self::lineRef($exchange->step, $message);

        return ['ref' => $ref, 'text_target' => $message->textTarget, 'text_native' => $message->textNative, 'audio' => Audio::of($ref)];
    }

    /**
     * The partner's line of an exchange; null for no exchange (the first one's «previous») or an exchange without it.
     *
     * @return array{ref: string, text_target: string, text_native: string, audio: array{ref: string, voice: 'partner'|'learner', url: null, duration_ms: null}}|null
     */
    public static function partnerLine(?Exchange $exchange): ?array
    {
        $partner = $exchange?->partner();

        return $exchange === null || $partner === null ? null : self::line($exchange, $partner);
    }

    /**
     * The learner's own line with what the server knows about it: the frame it stands on, WHICH filler the served
     * lesson found in its text ({@see \App\Modules\Plan\Domain\Lesson\LessonAssembly}) and the key taken from the
     * frame. `frame_ref`, `filler_index` and `key` are null for a line on no frame (a rescue line). Null when the
     * exchange has no learner line.
     *
     * @return array{ref: string, text_target: string, text_native: string, frame_ref: string|null, filler_index: int|null, key: string|null, audio: array{ref: string, voice: 'partner'|'learner', url: null, duration_ms: null}}|null
     */
    public static function ownLine(SceneMaterial $scene, Exchange $exchange): ?array
    {
        $learner = $exchange->learner();
        if ($learner === null) {
            return null;
        }
        $frame = self::frameOf($scene, $learner);
        $ref = self::lineRef($exchange->step, $learner);

        return [
            'ref' => $ref,
            'text_target' => $learner->textTarget,
            'text_native' => $learner->textNative,
            'frame_ref' => $frame?->id,
            'filler_index' => self::indexOfFiller($frame, $learner->filler),
            'key' => $learner->speakingKey,
            'audio' => Audio::of($ref),
        ];
    }

    /**
     * The partner's line the learner's line of an exchange speaks to: in an answer — the partner's line of the same
     * exchange, the question it answers; in an ask — the partner's line of the exchange before it, the context the
     * learner asks in; null for the first exchange or a rescue.
     *
     * @return array{ref: string, text_target: string, text_native: string, audio: array{ref: string, voice: 'partner'|'learner', url: null, duration_ms: null}}|null
     */
    public static function cueLine(SceneMaterial $scene, Exchange $exchange): ?array
    {
        if ($exchange->kind === ExchangeKind::Answer) {
            return self::partnerLine($exchange);
        }
        if ($exchange->kind !== ExchangeKind::Ask) {
            return null;
        }
        $previous = null;
        foreach ($scene->lesson->exchanges as $candidate) {
            if ($candidate->step === $exchange->step) {
                return self::partnerLine($previous);
            }
            $previous = $candidate;
        }

        return null;
    }

    /**
     * A line of the visit as the listening cards play it — who says it and in which exchange besides its texts:
     * `{ref, role, exchange_step, text_target, text_native, audio}`.
     *
     * @return array{ref: string, role: string, exchange_step: int, text_target: string, text_native: string, audio: array{ref: string, voice: 'partner'|'learner', url: null, duration_ms: null}}
     */
    public static function visitLine(int $step, Message $message): array
    {
        $ref = self::lineRef($step, $message);

        return [
            'ref' => $ref,
            'role' => ($message->isLearner() ? Speaker::Learner : Speaker::Partner)->value,
            'exchange_step' => $step,
            'text_target' => $message->textTarget,
            'text_native' => $message->textNative,
            'audio' => Audio::of($ref),
        ];
    }

    /**
     * The frame as every card shows it: `{ref, kind, frame_target, frame_native, frame_pronunciation_native, slot}`,
     * the slot `{hint_native, fillers}` or null. Null for a term that carries no frame (a word, a phrase stored
     * before frames) — there is nothing to show.
     *
     * @return array<string, mixed>|null
     */
    public static function frame(PlanTerm $phrase): ?array
    {
        $frame = $phrase->frame();
        if ($frame === null) {
            return null;
        }

        return [
            'ref' => $phrase->ref(),
            'kind' => $frame->kind->value,
            'frame_target' => $frame->frameTarget,
            'frame_native' => $frame->frameNative,
            'frame_pronunciation_native' => $frame->pronunciationNative,
            'slot' => $frame->slot === null ? null : [
                'hint_native' => $frame->slot->hintNative,
                'fillers' => self::fillers($phrase),
            ],
        ];
    }

    /**
     * The fillers of a frame in the slot's order: `{index, target, native, pronunciation_native, in_dialogue,
     * native_line, audio}`. `native_line` is the whole sentence in the learner's language — the frame's translation
     * with the filler's, ending as the phrase ends; `in_dialogue` is the served mark (what the dialogue says);
     * `audio` is the file the frame said with this filler is voiced as ({@see SpokenLines::fillers()}, `voicedAs`:
     * the filler the phrase itself is said with sounds as the phrase `p1`, every other one as `p1.f2`), null for a
     * filler the frame cannot be said with.
     *
     * @return list<array{index: int, target: string, native: string, pronunciation_native: string, in_dialogue: bool, native_line: string, audio: array{ref: string, voice: 'partner'|'learner', url: null, duration_ms: null}|null}>
     */
    public static function fillers(PlanTerm $phrase): array
    {
        $frame = $phrase->frame();
        if ($frame === null) {
            return [];
        }
        $voiced = [];
        foreach (SpokenLines::fillers($phrase) as $line) {
            $voiced[$line['index']] = $line['voicedAs'];
        }

        $out = [];
        foreach ($frame->fillers() as $index => $filler) {
            $out[] = [
                'index' => $index,
                'target' => $filler->target,
                'native' => $filler->native,
                'pronunciation_native' => $filler->pronunciationNative,
                'in_dialogue' => $filler->inDialogue,
                'native_line' => FrameText::withEndMarkOf(FrameText::fill($frame->frameNative, $filler->native), $phrase->textNative()),
                'audio' => isset($voiced[$index]) ? Audio::of($voiced[$index]) : null,
            ];
        }

        return $out;
    }

    /**
     * The phrase as it is said — the frame with the filler of its first dialogue line, which is what the term's own
     * texts already are: `{filler_index, text_target, text_native, pronunciation_native, audio}`. A frame without a
     * slot is said as itself (`filler_index` null).
     *
     * @return array{filler_index: int|null, text_target: string, text_native: string, pronunciation_native: string|null, audio: array{ref: string, voice: 'partner'|'learner', url: null, duration_ms: null}}
     */
    public static function said(SceneMaterial $scene, PlanTerm $phrase): array
    {
        return [
            'filler_index' => $scene->saidIndex($phrase),
            'text_target' => $phrase->textTarget(),
            'text_native' => $phrase->textNative(),
            'pronunciation_native' => $phrase->pronunciationNative(),
            'audio' => Audio::of($phrase->ref()),
        ];
    }

    /**
     * The frame said with ONE of its fillers, the way a card shows it (SESSION-1d): the same shape as {@see said()} —
     * `{filler_index, text_target, text_native, pronunciation_native, audio}`. The said filler (and a frame without a
     * window, `$index` null) is the phrase itself; any other is the frame with that filler, its translation the
     * filler's `native_line`, its reading the frame's with the filler's, its sound the file the frame is voiced as with
     * it (`p1.f2`). Null when `$index` is no filler of the frame, or the frame cannot be said with it (no sound: a
     * second slot left).
     *
     * @return array{filler_index: int|null, text_target: string, text_native: string, pronunciation_native: string|null, audio: array{ref: string, voice: 'partner'|'learner', url: null, duration_ms: null}}|null
     */
    public static function sentence(SceneMaterial $scene, PlanTerm $phrase, ?int $index): ?array
    {
        $frame = $phrase->frame();
        $said = $scene->saidIndex($phrase);
        if ($index === $said || ($index === null && ($frame === null || $frame->slot === null || ! FrameText::hasSlot($frame->frameTarget)))) {
            return self::said($scene, $phrase);
        }
        $filler = null;
        foreach (self::fillers($phrase) as $candidate) {
            if ($candidate['index'] === $index) {
                $filler = $candidate;
            }
        }
        if ($frame === null || $filler === null || $filler['audio'] === null) {
            return null;
        }
        $reading = FrameText::fill($frame->pronunciationNative, $filler['pronunciation_native']);

        return [
            'filler_index' => $index,
            'text_target' => FrameText::withEndMarkOf(FrameText::fill($frame->frameTarget, $filler['target']), $phrase->textTarget()),
            'text_native' => $filler['native_line'],
            'pronunciation_native' => trim($reading) === '' ? null : $reading,
            'audio' => $filler['audio'],
        ];
    }

    /**
     * Which filler of the frame a learner line is said with — the served `filler` (the server found it in the text)
     * matched among the frame's fillers; null for no frame, no slot or no filler.
     */
    public static function fillerIndexOf(PlanTerm $phrase, ?string $fillerTarget): ?int
    {
        return self::indexOfFiller($phrase->frame(), $fillerTarget);
    }

    private static function indexOfFiller(?Phrase $frame, ?string $fillerTarget): ?int
    {
        $filler = $frame?->filler($fillerTarget);
        if ($frame === null || $filler === null) {
            return null;
        }
        $index = array_search($filler, $frame->fillers(), true);

        return is_int($index) ? $index : null;
    }

    /** The frame a learner line stands on: the scene's phrase term's, else the lesson's own; null for no frame. */
    private static function frameOf(SceneMaterial $scene, Message $learner): ?Phrase
    {
        if ($learner->phraseId === null) {
            return null;
        }

        return $scene->phraseTerm($learner->phraseId)?->frame() ?? $scene->lesson->phrase($learner->phraseId);
    }
}

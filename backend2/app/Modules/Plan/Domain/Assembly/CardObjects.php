<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\Lesson\Phrase;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\Service\SpokenLines;
use App\Modules\Plan\Domain\Service\Words;
use App\Modules\Plan\Domain\ValueObject\Speaker;

/**
 * THE OBJECTS CARDS ARE MADE OF (наряд SESSION-1a, разд. 1): a term, an exchange, a line of it — the partner's, the
 * learner's own, a line of the visit as the listening plays it — a frame, its fillers and the phrase as it is said.
 *
 * There is no «the partner line a learner line speaks to» any more (наряд FIX-2, п. 3): a card shows the lines of ITS
 * OWN exchange, and an exchange where the learner speaks first has no question to show at all.
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
     * the slot `{hint_native, fillers}` or null — the fillers as {@see fillers()} gives them, the ones no card shows
     * left out. Null for a term that carries no frame (a word, a phrase stored before frames) — there is nothing to
     * show.
     *
     * @param  list<int>  $unhidden  the fillers the seam judge hid that this card shows all the same ({@see fillers()})
     * @return array<string, mixed>|null
     */
    public static function frame(SceneMaterial $scene, PlanTerm $phrase, array $unhidden = []): ?array
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
                'fillers' => self::fillers($scene, $phrase, $unhidden),
            ],
        ];
    }

    /**
     * The fillers of a frame in the slot's order, as every card shows them: `{index, target, native,
     * pronunciation_native, in_dialogue, native_line, audio}`. `native_line` is the whole sentence in the learner's
     * language — the MODEL'S OWN translation of the line that says it, when a line of the visit does
     * ({@see SceneMaterial::nativeLineOf()}, наряд BACK-TAILS-1 §2.3), else the frame's translation with the filler's,
     * ending as the phrase ends and STARTING WITH A CAPITAL ({@see FrameText::nativeSentence()}, наряд FIX-2 п. 1 —
     * «моей кошке нужен ветеринар.» went onto a live card lower-cased); `in_dialogue` is the served mark
     * (what the dialogue says); `audio` is the file the frame said with this filler is voiced as
     * ({@see SpokenLines::fillers()}, `voicedAs`: the filler the phrase itself is said with sounds as the phrase `p1`,
     * every other one as `p1.f2`), null for a filler the frame cannot be said with.
     *
     * A filler whose native sentence does not read and which the dialogue does not say ({@see SceneMaterial::hides()},
     * SESSION-1e) is not among them — so no chip, no option, no card of the day shows it; `index` stays its place in
     * the slot, and the others keep theirs. ONE card is the exception, and names the ones it shows all the same
     * (`$unhidden`, наряд FIX-3 §3): «Скажи целиком» says a window of two values or more with two rounds or more, and a
     * frame whose other values the judge all hid takes one of them to make the second round («How heavy should ___ be?» on
     * the owner's gym day: two of three values hidden, one round). Such a filler comes with its VALUE as its `native_line`
     * («Гантель») — the sentence the judge said does not read is still shown nowhere.
     *
     * @param  list<int>  $unhidden  the hidden fillers this card shows all the same
     * @return list<array{index: int, target: string, native: string, pronunciation_native: string, in_dialogue: bool, native_line: string, audio: array{ref: string, voice: 'partner'|'learner', url: null, duration_ms: null}|null}>
     */
    public static function fillers(SceneMaterial $scene, PlanTerm $phrase, array $unhidden = []): array
    {
        $frame = $phrase->frame();
        if ($frame === null) {
            return [];
        }
        $voiced = [];
        foreach (SpokenLines::fillers($phrase, $scene->target->sentenceEnds()) as $line) {
            $voiced[$line['index']] = $line['voicedAs'];
        }

        $out = [];
        foreach ($frame->fillers() as $index => $filler) {
            $hidden = $scene->hides($phrase->ref(), $index);
            if ($hidden && ! in_array($index, $unhidden, true)) {
                continue;
            }
            $out[] = [
                'index' => $index,
                'target' => $filler->target,
                'native' => $filler->native,
                'pronunciation_native' => $filler->pronunciationNative,
                'in_dialogue' => $filler->inDialogue,
                'native_line' => FrameText::capitalized($hidden
                    ? $filler->native
                    : ($scene->nativeLineOf($phrase->ref(), $index)
                        ?? FrameText::nativeSentence($frame->frameNative, $filler->native, $phrase->textNative(), $scene->native->sentenceEnds()))),
                'audio' => isset($voiced[$index]) ? Audio::of($voiced[$index]) : null,
            ];
        }

        return $out;
    }

    /**
     * The phrase as it is said — the frame with the filler of its first dialogue line, which is what the term's own
     * texts already are: `{filler_index, text_target, text_native, pronunciation_native, audio}`. A frame without a
     * slot is said as itself (`filler_index` null). Its translation is the MODEL'S OWN for the line that says it
     * (наряд BACK-TAILS-1 §2.3) — the term's stored `text_native` is the native pattern glued to the native filler,
     * and the line is a sentence somebody wrote.
     *
     * @return array{filler_index: int|null, text_target: string, text_native: string, pronunciation_native: string|null, audio: array{ref: string, voice: 'partner'|'learner', url: null, duration_ms: null}}
     */
    public static function said(SceneMaterial $scene, PlanTerm $phrase): array
    {
        $index = $scene->saidIndex($phrase);

        return [
            'filler_index' => $index,
            'text_target' => $phrase->textTarget(),
            'text_native' => FrameText::capitalized(($index === null ? null : $scene->nativeLineOf($phrase->ref(), $index)) ?? $phrase->textNative()),
            'pronunciation_native' => $phrase->pronunciationNative(),
            'audio' => Audio::of($phrase->ref()),
        ];
    }

    /**
     * «В РАЗГОВОРЕ» — WHERE THE DAY SAYS THE PHRASE (кадр 32-1, наряд CONV-2, п. 12): the first learner line of the visit
     * that stands on the frame, the partner's line of the same exchange, and where the phrase itself stands in the
     * learner's line, in characters — the same reading a word gets in the window (`usage`, кадр 23-0e). The client
     * matched texts against the day's dialogue to draw the block, and a phrase its match missed had no block at all
     * (CLIENT-CONV-1a, §5 п. 5).
     *
     * `offset`/`length` are null when the phrase is not in the line word for word (a line said with another filler);
     * the whole value is null when no line of the visit stands on the frame.
     *
     * @return array{exchange: array{ref: string, step: int, kind: string}, line: array{ref: string, text_target: string, text_native: string, audio: array{ref: string, voice: 'partner'|'learner', url: null, duration_ms: null}}, offset: int|null, length: int|null, partner_line: array{ref: string, text_target: string, text_native: string, audio: array{ref: string, voice: 'partner'|'learner', url: null, duration_ms: null}}|null}|null
     */
    public static function usage(SceneMaterial $scene, PlanTerm $phrase): ?array
    {
        $first = $scene->lesson->linesOf($phrase->ref())[0] ?? null;
        if ($first === null) {
            return null;
        }
        $span = Words::spanOfTerm($phrase->textTarget(), $first['message']->textTarget);

        return [
            'exchange' => self::exchange($first['exchange']),
            'line' => self::line($first['exchange'], $first['message']),
            'offset' => $span[0] ?? null,
            'length' => $span[1] ?? null,
            'partner_line' => self::partnerLine($first['exchange']),
        ];
    }

    /**
     * The frame said with ONE of its fillers, the way a card shows it (SESSION-1d): the same shape as {@see said()} —
     * `{filler_index, text_target, text_native, pronunciation_native, audio}`. The said filler (and a frame without a
     * window, `$index` null) is the phrase itself; any other is the frame with that filler, its translation the
     * filler's `native_line`, its reading the frame's with the filler's, its sound the file the frame is voiced as with
     * it (`p1.f2`). Null when `$index` is no filler of the frame, one no card shows ({@see fillers()}), or the frame
     * cannot be said with it (no sound: a second slot left).
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
        foreach (self::fillers($scene, $phrase) as $candidate) {
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
            'text_target' => FrameText::withEndMarkOf(FrameText::fill($frame->frameTarget, $filler['target'], $scene->target->sentenceEnds()), $phrase->textTarget()),
            'text_native' => $filler['native_line'],
            'pronunciation_native' => trim($reading) === '' ? null : $reading,
            'audio' => $filler['audio'],
        ];
    }

    /**
     * A frame as a whole phrase to answer with (`phrase_combine`, SESSION-1e): `{index, text_target, text_native,
     * audio}` — the frame said with the filler at `$index` ({@see sentence()}); no such filler, or one it cannot be
     * said with — the phrase as the dialogue says it ({@see said()}), `index` its filler.
     *
     * @return array{index: int|null, text_target: string, text_native: string, audio: array{ref: string, voice: 'partner'|'learner', url: null, duration_ms: null}}
     */
    public static function whole(SceneMaterial $scene, PlanTerm $phrase, ?int $index): array
    {
        $sentence = ($index === null ? null : self::sentence($scene, $phrase, $index)) ?? self::said($scene, $phrase);

        return [
            'index' => $sentence['filler_index'],
            'text_target' => $sentence['text_target'],
            'text_native' => $sentence['text_native'],
            'audio' => $sentence['audio'],
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

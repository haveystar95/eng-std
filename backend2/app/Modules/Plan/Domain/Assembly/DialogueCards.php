<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Service\SpokenLines;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use App\Modules\Shared\Domain\ValueObject\SpeechMode;

/**
 * THE CARDS OF «ДИАЛОГ» (наряд SESSION-1a, разд. 1; SPEC §4).
 *
 * Four kinds, each about one exchange (unit `exchange`, ref `x3`): `dialogue_partner` — what did the partner mean;
 * `dialogue_answer` and `dialogue_ask` — say your own line, with data for every mode the client may pick (chips,
 * the line as a hint, the frame blind), so the day does not depend on the «Без подсказок» switch; `dialogue_rescue` —
 * «не понял», the partner repeats slower.
 *
 * The exchange, its lines, the learner's own line and the frame under a voice card are {@see CardObjects}' — the same
 * objects «Фразы», «Слушаю и отвечаю» and «Говорю сам» show, so a line's ref, its sound and its keys never differ
 * between the stages. A card whose material is missing (no partner, no right option, no frame) is not dealt — null,
 * never a half-filled payload.
 */
final class DialogueCards
{
    public const SLOW_RATE = 0.75;

    /**
     * `dialogue_partner` (33-1): what did the partner mean — the exchange's own check ({@see check()}), asked of an
     * `answer` exchange, where the partner speaks first. No partner line, no right option or nothing to choose between
     * — no card.
     */
    public static function partner(SceneMaterial $scene, Exchange $exchange): ?CardDraft
    {
        $partnerLine = CardObjects::partnerLine($exchange);
        $check = self::check($scene, $exchange);
        if ($partnerLine === null || $check === null) {
            return null;
        }

        return self::draft(CardKind::DialoguePartner, $exchange, [
            'scene_id' => $scene->sceneId->value,
            'exchange' => CardObjects::exchange($exchange),
            'partner_line' => $partnerLine,
            ...$check,
        ]);
    }

    /** `dialogue_answer` (33-2 / 33-3 / 33-4): the partner speaks, the learner answers with their own line. */
    public static function answer(SceneMaterial $scene, Exchange $exchange): ?CardDraft
    {
        return self::spoken(CardKind::DialogueAnswer, $scene, $exchange);
    }

    /**
     * `dialogue_ask` (33-5): the learner speaks first, the partner's answer sounds after — AND the exchange's check is
     * asked on the same card (наряд BACK-TAILS-1 §1.5): the answer sounds with its text closed, the learner says what
     * they understood of it, and the text opens with the right option marked. One screen, one card — the ask exchange
     * no longer deals a `dialogue_partner` of its own.
     *
     * The check is the same object, key for key, as `dialogue_partner`'s — `question_native`, `options`, `correct`,
     * built by the same rule and the same seed ({@see check()}) — so the client draws one thing in two places. An
     * exchange whose check cannot be dealt (no right option, nothing to choose between) still deals the voice card,
     * without those three keys: the learner's own line is what this card is for.
     */
    public static function ask(SceneMaterial $scene, Exchange $exchange): ?CardDraft
    {
        return self::spoken(CardKind::DialogueAsk, $scene, $exchange, self::check($scene, $exchange) ?? []);
    }

    /**
     * THE CHECK OF ONE EXCHANGE as a card asks it: the question in the learner's own language and the options of the
     * exchange's OWN check, and nothing else (наряд FIX-3 §5) — the lesson wrote three, the card has three; wrote four,
     * four. A fourth borrowed from the farthest exchange's check was a right answer to another question: on the owner's
     * gym day «К ушам» stood under «О чём спрашивает тренер?», and a comprehension check whose odd option is odd by its
     * form tests nothing.
     *
     * One place for both cards that ask it ({@see partner()}, {@see ask()}), one seed per exchange — a day dealt twice
     * puts the options in the same order. Null when the exchange has no right option or nothing to choose between.
     *
     * @return array{question_native: string, options: list<array<string, mixed>>, correct: string}|null
     */
    private static function check(SceneMaterial $scene, Exchange $exchange): ?array
    {
        $right = $exchange->check->correctOption();
        if ($right === null) {
            return null;
        }

        $candidates = [];
        foreach ($exchange->check->options as $index => $option) {
            if ($index !== $exchange->check->correctOptionIndex) {
                $candidates[] = ['text' => $option->textNative];
            }
        }
        $chosen = Options::choose(
            $scene->seed("x{$exchange->step}:partner"), ['text' => $right->textNative], $candidates,
            count($exchange->check->options), Options::TEXT, Options::APART,
        );
        if (count($chosen['options']) < Options::MIN) {
            return null;
        }

        return [
            'question_native' => $exchange->check->textNative,
            'options' => $chosen['options'],
            'correct' => $chosen['correct'],
        ];
    }

    /**
     * `dialogue_rescue` (33-6): the line the learner did not catch (the partner's line of the exchange before), the
     * rescue line they say, and the partner saying it again slower. The rescue line stands on the screen, so it
     * carries its expected text and the `repeat` mode — but the frame has no microphone, and the client walks it
     * through.
     */
    public static function rescue(SceneMaterial $scene, Exchange $exchange, ?Exchange $previous): ?CardDraft
    {
        $learner = $exchange->learner();
        $partnerRepeat = CardObjects::partnerLine($exchange);
        if ($learner === null || $partnerRepeat === null) {
            return null;
        }

        return self::draft(CardKind::DialogueRescue, $exchange, [
            'scene_id' => $scene->sceneId->value,
            'exchange' => CardObjects::exchange($exchange),
            'asked_line' => CardObjects::partnerLine($previous),
            'rescue_line' => CardObjects::line($exchange, $learner),
            'partner_repeat' => $partnerRepeat,
            'slow_rate' => self::SLOW_RATE,
            'expected_text' => $learner->textTarget,
            'speech_mode' => SpeechMode::Repeat->value,
        ]);
    }

    /**
     * The voice card of an answer or an ask. The learner says their own sentence, so it is passed in the `free` mode
     * ({@see SpeechMode::Free}): the key is the frame's own words and the window is anyone's. `modes` holds every way
     * in: the fillers as chips (a frame without a slot has none), the line itself as the hint, the frame with its
     * empty window blind. A learner line on no frame of the scene deals no voice card.
     *
     * @param  array<string, mixed>  $extra  keys the kind adds after its own — the check of an `ask` (§1.5)
     */
    private static function spoken(CardKind $kind, SceneMaterial $scene, Exchange $exchange, array $extra = []): ?CardDraft
    {
        $partnerLine = CardObjects::partnerLine($exchange);
        $ownLine = CardObjects::ownLine($scene, $exchange);
        $phrase = $scene->phraseTerm($exchange->learner()?->phraseId);
        $frame = $phrase === null ? null : CardObjects::frame($scene, $phrase);
        $pattern = $phrase?->frame();
        if ($partnerLine === null || $ownLine === null || $phrase === null || $frame === null || $pattern === null) {
            return null;
        }

        return self::draft($kind, $exchange, [
            'scene_id' => $scene->sceneId->value,
            'exchange' => CardObjects::exchange($exchange),
            'partner_line' => $partnerLine,
            'own_line' => $ownLine,
            'frame' => $frame,
            'modes' => [
                'chips' => $pattern->slot === null ? [] : CardObjects::fillers($scene, $phrase),
                'voice_hint' => $ownLine['text_target'],
                'voice_blind' => $pattern->frameTarget,
            ],
            'speech_mode' => SpeechMode::Free->value,
            ...$extra,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private static function draft(CardKind $kind, Exchange $exchange, array $payload): CardDraft
    {
        return new CardDraft($kind, UnitKind::Exchange, SpokenLines::exchangeRef($exchange->step), $payload);
    }
}

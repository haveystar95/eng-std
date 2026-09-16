<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Service\FrameParts;
use App\Modules\Plan\Domain\Service\SpeechCoverage;
use App\Modules\Plan\Domain\Service\SpokenLines;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\UnitKind;

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
     * `dialogue_partner` (33-1): what did the partner mean — the three options of the exchange's own check, and a
     * fourth that is surely wrong here because it is right elsewhere: the right option of the check of the exchange
     * FARTHEST from this one by step (between two as far — the lower step), skipping one that reads like any of the
     * three, case and spaces aside — the next farthest then. No partner line, no right option or nothing to choose
     * between — no card.
     */
    public static function partner(SceneMaterial $scene, Exchange $exchange): ?CardDraft
    {
        $partnerLine = CardObjects::partnerLine($exchange);
        $right = $exchange->check->correctOption();
        if ($partnerLine === null || $right === null) {
            return null;
        }

        $taken = [];
        $candidates = [];
        foreach ($exchange->check->options as $index => $option) {
            $taken[self::key($option->textNative)] = true;
            if ($index !== $exchange->check->correctOptionIndex) {
                $candidates[] = ['text' => $option->textNative];
            }
        }
        $fourth = self::farthestRightOption($scene, $exchange, $taken);
        if ($fourth !== null) {
            $candidates[] = ['text' => $fourth];
        }
        $chosen = Options::choose($scene->seed("x{$exchange->step}:partner"), ['text' => $right->textNative], $candidates, 4);
        if (count($chosen['options']) < Options::MIN) {
            return null;
        }

        return self::draft(CardKind::DialoguePartner, $exchange, [
            'scene_id' => $scene->sceneId->value,
            'exchange' => CardObjects::exchange($exchange),
            'partner_line' => $partnerLine,
            'question_native' => $exchange->check->textNative,
            'options' => $chosen['options'],
            'correct' => $chosen['correct'],
        ]);
    }

    /** `dialogue_answer` (33-2 / 33-3 / 33-4): the partner speaks, the learner answers with their own line. */
    public static function answer(SceneMaterial $scene, Exchange $exchange): ?CardDraft
    {
        return self::spoken(CardKind::DialogueAnswer, $scene, $exchange);
    }

    /** `dialogue_ask` (33-5): the learner speaks first, the partner's answer sounds after — the same data as an answer. */
    public static function ask(SceneMaterial $scene, Exchange $exchange): ?CardDraft
    {
        return self::spoken(CardKind::DialogueAsk, $scene, $exchange);
    }

    /**
     * `dialogue_rescue` (33-6): the line the learner did not catch (the partner's line of the exchange before), the
     * rescue line they say, and the partner saying it again slower. The rescue line is passed by coverage, so it
     * carries its expected text and share — but the frame has no microphone, and the client walks it through.
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
            'coverage_min' => (new SpeechCoverage)->minFor($learner->textTarget, $scene->target),
        ]);
    }

    /**
     * The voice card of an answer or an ask. The client passes it by the coverage of the frame's own words — the
     * window is anyone's — so `coverage_min` is measured on {@see FrameParts::part()}, and `modes` holds every way in:
     * the fillers as chips (a frame without a slot has none), the line itself as the hint, the frame with its empty
     * window blind. A learner line on no frame of the scene deals no voice card.
     */
    private static function spoken(CardKind $kind, SceneMaterial $scene, Exchange $exchange): ?CardDraft
    {
        $partnerLine = CardObjects::partnerLine($exchange);
        $ownLine = CardObjects::ownLine($scene, $exchange);
        $phrase = $scene->phraseTerm($exchange->learner()?->phraseId);
        $frame = $phrase === null ? null : CardObjects::frame($phrase);
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
                'chips' => $pattern->slot === null ? [] : CardObjects::fillers($phrase),
                'voice_hint' => $ownLine['text_target'],
                'voice_blind' => $pattern->frameTarget,
            ],
            'coverage_min' => (new SpeechCoverage)->minFor(FrameParts::part($pattern->frameTarget), $scene->target),
        ]);
    }

    /**
     * The right option of the farthest other exchange's check that reads like none of `$taken`.
     *
     * @param  array<string, true>  $taken
     */
    private static function farthestRightOption(SceneMaterial $scene, Exchange $exchange, array $taken): ?string
    {
        $others = array_values(array_filter(
            $scene->lesson->exchanges,
            static fn (Exchange $other): bool => $other->step !== $exchange->step,
        ));
        usort($others, static fn (Exchange $a, Exchange $b): int => [abs($b->step - $exchange->step), $a->step] <=> [abs($a->step - $exchange->step), $b->step]);

        foreach ($others as $other) {
            $text = trim((string) $other->check->correctOption()?->textNative);
            if ($text !== '' && ! isset($taken[self::key($text)])) {
                return $text;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $payload */
    private static function draft(CardKind $kind, Exchange $exchange, array $payload): CardDraft
    {
        return new CardDraft($kind, UnitKind::Exchange, SpokenLines::exchangeRef($exchange->step), $payload);
    }

    private static function key(string $text): string
    {
        return mb_strtolower(trim($text));
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\Service\FrameParts;
use App\Modules\Plan\Domain\Service\SpeechCoverage;

/**
 * THE PAYLOADS OF «ГОВОРЮ САМ» (наряд SESSION-1a, разд. 1; SPEC §4, 26–28) — what `speak_answer`, `speak_echo` and
 * `speak_retell` carry, key for key. Which exchange and which line get a card is {@see SpeakStage}'s business; this
 * class only writes down what the card needs, so a returned exchange, the review and the rehearsal deal exactly the
 * card the scene day deals.
 *
 * The pieces are not drawn here: the exchange, its lines, the learner's own line, the partner line it speaks to and
 * the frame are {@see CardObjects}' — the same objects every stage shows, so a line's ref, its sound and its keys never
 * differ between the dialogue and the speaking of one exchange. Every payload starts with `scene_id` and holds no id
 * and no address: sounds are {@see Audio} stubs, resolved when the day is read.
 */
final class SpeakCards
{
    /** How long `speak_echo` waits after the partner's line before the learner repeats it (кадр 35-3). */
    public const PAUSE_MS = 3000;

    /**
     * `speak_answer` (кадр 35-2): the learner says their own line of the exchange from its meaning, the frame in reach
     * as a hint, the slot judged by meaning (`judge: true`). The line the learner answers is the exchange's partner
     * line — for an `ask`, where the learner speaks first, the partner line of the PREVIOUS exchange (D-22,
     * {@see CardObjects::cueLine()}): the context the question is asked in, and what the judge reads as PARTNER_LINE;
     * null for the first exchange. The coverage is of the frame's own words — the window is the judge's.
     *
     * Null when the phrase carries no frame or the exchange no learner line: there is nothing to say.
     *
     * @return array<string, mixed>|null
     */
    public static function answer(SceneMaterial $scene, Exchange $exchange, PlanTerm $phrase): ?array
    {
        $pattern = $phrase->frame();
        $frame = CardObjects::frame($phrase);
        $ownLine = CardObjects::ownLine($scene, $exchange);
        if ($pattern === null || $frame === null || $ownLine === null) {
            return null;
        }

        return [
            'scene_id' => $scene->sceneId->value,
            'exchange' => CardObjects::exchange($exchange),
            'partner_line' => CardObjects::cueLine($scene, $exchange),
            'own_line' => $ownLine,
            'task_native' => $ownLine['text_native'],
            'frame' => $frame,
            'key' => $ownLine['key'],
            'coverage_min' => (new SpeechCoverage)->minFor(FrameParts::part($pattern->frameTarget), $scene->target),
            'hint' => $pattern->frameTarget,
            'judge' => true,
        ];
    }

    /**
     * `speak_echo` (кадр 35-3): the partner's line is heard, its text hidden, and said back after a pause — passed by
     * most of its words, whatever its length.
     *
     * @return array<string, mixed>
     */
    public static function echoLine(SceneMaterial $scene, Exchange $exchange, Message $partner): array
    {
        return [
            'scene_id' => $scene->sceneId->value,
            'exchange' => CardObjects::exchange($exchange),
            'partner_line' => CardObjects::line($exchange, $partner),
            'expected_text' => $partner->textTarget,
            'coverage_min' => SpeechCoverage::MOST,
            'pause_ms' => self::PAUSE_MS,
        ];
    }

    /**
     * `speak_retell` (кадр 35-4): the partner's line is heard and retold in the learner's own language — judged by
     * meaning; the text is revealed after the verdict.
     *
     * @return array<string, mixed>
     */
    public static function retell(SceneMaterial $scene, Exchange $exchange, Message $partner): array
    {
        return [
            'scene_id' => $scene->sceneId->value,
            'exchange' => CardObjects::exchange($exchange),
            'partner_line' => CardObjects::line($exchange, $partner),
            'reveal' => ['text_target' => $partner->textTarget, 'text_native' => $partner->textNative],
            'judge' => true,
        ];
    }
}

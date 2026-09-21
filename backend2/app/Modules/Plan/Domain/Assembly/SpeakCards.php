<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;
use App\Modules\Shared\Domain\ValueObject\SpeechMode;

/**
 * THE PAYLOADS OF «ГОВОРЮ САМ» (наряд SESSION-1a, разд. 1; SPEC §4, 26–28; наряд BACK-TAILS-1 §1.1) — what
 * `speak_answer`, `speak_echo` and `speak_retell` carry, key for key. Which exchange and which line get a card is {@see SpeakStage}'s business; this
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
    /** How long `speak_echo` waits after the line before the learner repeats it (кадр 35-3). */
    public const PAUSE_MS = 3000;

    /**
     * `speak_answer` (кадр 35-2): the learner says their own line of the exchange from its meaning, the frame in reach
     * as a hint, the slot judged by meaning (`judge: true`).
     *
     * EVERYTHING ON THE CARD IS ONE EXCHANGE — the one `payload.exchange` names (наряд FIX-2, п. 3): the question is
     * the partner line OF THAT EXCHANGE, the expected line is its learner line, the hint is the frame that line
     * stands on, and the judge reads the same partner line as `PARTNER_LINE`.
     *
     * An `ask` exchange has NO question: the learner speaks first, and its partner line is the ANSWER to what they
     * are about to say. `partner_line` is null there, and the card says «Спроси сам» with the learner's own line in
     * their language as the intent. D-22 — the partner line of the PREVIOUS exchange as the invitation — is
     * cancelled (решение архитектора 20.09): on the owner's live day it put «What is your dog's name?» over «Do you
     * have anything ___?», showed one question on two cards, and handed the judge a third exchange's context.
     *
     * The key is the frame's own words — the window is the judge's — so the mode is `free`.
     *
     * Null when the phrase carries no frame or the exchange no learner line: there is nothing to say.
     *
     * @return array<string, mixed>|null
     */
    public static function answer(SceneMaterial $scene, Exchange $exchange, PlanTerm $phrase): ?array
    {
        $pattern = $phrase->frame();
        $frame = CardObjects::frame($scene, $phrase);
        $ownLine = CardObjects::ownLine($scene, $exchange);
        if ($pattern === null || $frame === null || $ownLine === null) {
            return null;
        }

        return [
            'scene_id' => $scene->sceneId->value,
            'exchange' => CardObjects::exchange($exchange),
            'partner_line' => $exchange->kind === ExchangeKind::Ask ? null : CardObjects::partnerLine($exchange),
            'own_line' => $ownLine,
            'task_native' => $ownLine['text_native'],
            'frame' => $frame,
            'key' => $ownLine['key'],
            'speech_mode' => SpeechMode::Free->value,
            'hint' => $pattern->frameTarget,
            'judge' => true,
        ];
    }

    /**
     * `speak_echo` — «Повтори через паузу» (кадр 35-3): the learner's OWN line of the exchange is heard, its text
     * hidden, and said back after a pause (наряд CONV-2, п. 6). It opens with the attempt, and the line the learner
     * repeats is the line they were given — `repeat`.
     *
     * It used to echo the PARTNER's line: on the owner's gym day (21.09) the stage of «Говорю сам» asked him to say
     * «Please bring a towel, use clean shoes, and return the locker key after training» — the receptionist's words,
     * which nobody would ever say in his place. «Говорю сам» is the learner's part and nothing else.
     *
     * `partner_line` carries THE SAME LINE for one reason: the client build on the phone (1.0.0 (17)) reads the line
     * it plays under that key and would skip a card without it — and a skipped card is a stage that never closes. It
     * goes as soon as the client reads `own_line` (ROADMAP, наряд CONV-2).
     *
     * Null when the exchange has no learner line: there is nothing to echo.
     *
     * @return array<string, mixed>|null
     */
    public static function echoLine(SceneMaterial $scene, Exchange $exchange): ?array
    {
        $ownLine = CardObjects::ownLine($scene, $exchange);
        if ($ownLine === null) {
            return null;
        }

        return [
            'scene_id' => $scene->sceneId->value,
            'exchange' => CardObjects::exchange($exchange),
            'own_line' => $ownLine,
            'partner_line' => [
                'ref' => $ownLine['ref'],
                'text_target' => $ownLine['text_target'],
                'text_native' => $ownLine['text_native'],
                'audio' => $ownLine['audio'],
            ],
            'expected_text' => $ownLine['text_target'],
            'speech_mode' => SpeechMode::Repeat->value,
            'pause_ms' => self::PAUSE_MS,
        ];
    }

    /**
     * `speak_retell` — «Повтори свою реплику» (кадр 35-4, наряд BACK-TAILS-1 §1.1): the learner's OWN line of the
     * exchange sounds, its target text closed, its translation left on the screen as the sense of it, and the learner
     * says it back. The client passes it in the `repeat` mode over the WHOLE line — there is no frame here and no
     * window, so nothing is counted apart — and the target text opens after the attempt; the server judges nothing.
     *
     * Null when the exchange has no learner line: there is nothing to repeat.
     *
     * @return array<string, mixed>|null
     */
    public static function retell(SceneMaterial $scene, Exchange $exchange): ?array
    {
        $ownLine = CardObjects::ownLine($scene, $exchange);
        if ($ownLine === null) {
            return null;
        }

        return [
            'scene_id' => $scene->sceneId->value,
            'exchange' => CardObjects::exchange($exchange),
            'own_line' => $ownLine,
            'expected_text' => $ownLine['text_target'],
            'speech_mode' => SpeechMode::Repeat->value,
        ];
    }
}

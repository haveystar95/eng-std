<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * THE SUMMARY OF THE TALK (кадр 37-12): «Сказал сам 6 реплик», «Фразы дня в разговоре · 3 из 5»,
 * «Понял все вопросы · переспросил 2 раза», and the phrases that did not sound — with the day they
 * come back on, or, in the rehearsal, with nothing: there is no tomorrow before the event. The phrases
 * are the talk's TARGETS (наряд CONV-2, п. 10), the list the learner was shown on the way in; a replay
 * returns nothing either — the day's result is the talk that walked the stage (п. 2). Since наряд FIX-4: what the learner
 * said beyond the targets («ещё вспомнил», `extraSaid`) and whether a limit ended the talk (`endedByLimit` — a flag
 * beside `endedReason`, whose values the phone's build (20) reads and which do not change).
 */
final readonly class ConversationSummaryView
{
    /**
     * @param  list<array{scene_id: string, ref: string, frame_target: string, frame_native: string, example_target: string|null, example_native: string|null, said: bool, value_target: string|null, line_native: string, state: string}>  $phrases
     *   the talk's targets as the talk left them — the same shape as `targets[]` (наряд FIX-3 §6)
     * @param  list<array{scene_id: string, ref: string, frame_target: string, frame_native: string, example_target: string|null, example_native: string|null, said: bool, value_target: string|null, line_native: string}>  $extraSaid
     */
    public function __construct(
        public int $saidCount,
        public int $phrasesUsed,
        public int $phrasesTotal,
        public array $phrases,
        public bool $understoodAll,
        public int $notUnderstood,
        public int $rescues,
        public ?string $endedReason,
        public ?int $minutes,
        public bool $returnsTomorrow,
        public array $extraSaid = [],
        public bool $endedByLimit = false,
    ) {}
}

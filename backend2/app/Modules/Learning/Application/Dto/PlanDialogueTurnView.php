<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/** One turn of a scene's conversation on the wire — see {@see PlanDialogueView}. */
final readonly class PlanDialogueTurnView
{
    public function __construct(
        /** `role` — the other person speaks; `you` — the learner's move. */
        public string $turn,
        public string $termId,
        /** The line itself, on the language being learned. */
        public string $text,
        /** Its translation — what «Показать текст» never shows and the cheat sheet does. */
        public ?string $translation,
        /** `hear` | `say` | `ask` — which shelf the card stands on, and therefore its ladder. */
        public ?string $shelf,
        /**
         * АДРЕС ГОТОВОЙ ОЗВУЧКИ этой реплики, или null — «серверного файла нет» (наряд TTS-1).
         *
         * Null означает ровно одно: играй системным голосом, как играл всегда. Он же стоит при
         * выключенной трубе, у чужой полки и у языка без голоса в пакете — и это сознательно одно
         * значение на все три случая: клиенту нечего делать по-разному, а различать их — работа
         * лога, а не экрана.
         *
         * Id строки, а не URL: схему и хост знает Presentation ({@see
         * \App\Modules\Learning\Presentation\Http\LineAudioUrl}).
         */
        public ?string $audioId = null,
        /**
         * СТРОГОСТЬ ЭТОГО ХОДА — `choose` | `assemble` | `say`, и только у хода `you`
         * ({@see \App\Modules\Learning\Domain\ValueObject\PlanTurnLevel}).
         *
         * Едет на ЦЕПОЧКЕ, а не только на задаче, потому что цепочка приходит целой, а задач
         * меньше: экран рисует ленту разговора вперёд, и ход, до которого лестница сегодня не
         * дошла, всё равно должен выглядеть тем, чем он станет. Null у реплики собеседника —
         * её не говорят.
         */
        public ?string $level = null,
    ) {}
}

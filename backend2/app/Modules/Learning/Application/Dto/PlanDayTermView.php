<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * One word or phrase of a plan day, with the stage it stands on — the day screen's register
 * (макет «Фаза 4», кадр 1c · 02).
 *
 * The STAGE is the point of this DTO and the reason the day endpoint cannot just hand the client a
 * collection id and let it read its own mirror: a stage is not stored anywhere. It is a pure
 * function of the review log, computed on every read ({@see
 * \App\Modules\Learning\Domain\Service\PlanStageLadder}), and the device has no way to arrive at it
 * — the log it mirrors is answers, not the plan's ladder over them.
 */
final readonly class PlanDayTermView
{
    public function __construct(
        public string $termId,
        public string $text,
        public ?string $translation,
        /** `word | phrase | idiom | phrasal_verb` — what the expression IS, lexically. */
        public string $type,
        /**
         * `line | word | chunk` — what it DOES in this day, or null on a term that never came from
         * a plan day of v0.2 or later.
         *
         * The day screen sets a spoken LINE differently from a substitution, and until v0.2 it had
         * to guess that from `type` («anything that is not one word is a line»). The guess starts
         * lying the moment a connector appears: «deal with» is two words and a substitution.
         */
        public ?string $kind,
        /**
         * `learner` | `role`, and null on anything that is not a plan line.
         *
         * WHOSE line it is. A `role` line is what the interlocutor says, and the day screen has to
         * mark it: the live run listed «Hello. What seems to be the problem with your child?» among
         * the learner's own phrases with nothing to distinguish it, so the register read as «here
         * are eleven sentences you are learning to say» and one of them was the doctor's (Д-8).
         */
        public ?string $speaker,
        /** `a` | `b` | `c`. */
        public string $stage,
        public bool $stageComplete,
        public bool $finished,
        /**
         * The day that INTRODUCED this term. Equal to the day being read for its own words, smaller
         * for one carried in from an earlier day — which is what «B · со дня 1» is drawn from.
         */
        public int $fromDayIndex,
        /** `hear` | `say` | `ask` | `words` | `chunks` | `numbers` | `rescue` — the day's shelves. */
        public ?string $shelf = null,
        /** `speak` | `understand` — what the card is ever asked of (канон §3). */
        public ?string $tier = null,
        /**
         * АДРЕС ГОТОВОЙ ОЗВУЧКИ (наряд TTS-1), или null — «серверного файла нет».
         *
         * Шпаргалка сцен читает вслух с этого же экрана (канон §13), и играть там она обязана ТОТ
         * ЖЕ файл, что и разговор: два голоса на одну реплику — это две разные реплики для уха.
         */
        public ?string $audioId = null,
        /**
         * ЧТО С ЭТОЙ СТРОКОЙ БУДЕТ ДЕЛАТЬ ЧЕЛОВЕК — код упражнения, которым экран дня подписывает
         * секцию словами (наряд DAY-FIX-2, Ч.4.2): `meet` · `recognize` · `hear` · `choose` ·
         * `assemble` · `say`, или null — сегодня строка ничего не должна.
         *
         * Считает сервер по стойке и уровню хода — тому же правилу, что раздаёт карточку
         * ({@see \App\Modules\Learning\Domain\ValueObject\PlanTurnLevel::forTurn()}); экран только
         * переводит код в слово.
         */
        public ?string $nextStep = null,
        /**
         * ОТМЕТКА У СТРОКИ, если день шёл: `passed` — знакомство закрыто; `said_self` — реплика
         * прозвучала голосом человека в прогоне; null — пусто (Ч.4.3). Не цифры.
         */
        public ?string $mark = null,
    ) {}

    public const STEP_MEET = 'meet';

    public const STEP_RECOGNIZE = 'recognize';

    public const STEP_HEAR = 'hear';

    public const STEP_CHOOSE = 'choose';

    public const STEP_ASSEMBLE = 'assemble';

    public const STEP_SAY = 'say';

    public const MARK_PASSED = 'passed';

    public const MARK_SAID_SELF = 'said_self';

    /**
     * @param  string|null  $audioUrl  адрес файла озвучки, если он есть.
     *
     * Адрес приходит ПАРАМЕТРОМ, а не собирается здесь: URL знает схему и хост, а это Presentation
     * ({@see \App\Modules\Learning\Presentation\Http\LineAudioUrl}), и Application туда не ходит.
     *
     * @return array<string, mixed>
     */
    public function toArray(?string $audioUrl = null): array
    {
        return [
            'id' => $this->termId,
            'text' => $this->text,
            'translation' => $this->translation,
            'type' => $this->type,
            'kind' => $this->kind,
            'speaker' => $this->speaker,
            'stage' => $this->stage,
            'stage_complete' => $this->stageComplete,
            'finished' => $this->finished,
            'from_day_index' => $this->fromDayIndex,
            'shelf' => $this->shelf,
            'tier' => $this->tier,
            'audio_url' => $audioUrl,
            'next_step' => $this->nextStep,
            'mark' => $this->mark,
        ];
    }
}

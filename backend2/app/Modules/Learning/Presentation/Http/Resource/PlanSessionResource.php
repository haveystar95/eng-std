<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Resource;

use App\Modules\Learning\Application\Dto\PlanDialogueTurnView;
use App\Modules\Learning\Application\Dto\PlanLineAudioView;
use App\Modules\Learning\Application\Dto\PlanDialogueView;
use App\Modules\Learning\Application\Dto\PlanSessionTaskView;
use App\Modules\Learning\Application\Dto\PlanSessionView;
use App\Modules\Learning\Application\Dto\SessionView;
use App\Modules\Learning\Presentation\Http\LineAudioUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A plan session on the wire.
 *
 * `tasks` and not `cards`, and the difference is the point: each entry carries the app's ordinary
 * card VERBATIM under `card`, with the plan's own envelope around it. A client that already knows
 * how to play a card plays these; the envelope is what lets it also say «слово из дня 1, ступень B,
 * 3 из 4» and «эта карточка стала мягче».
 *
 * The card body is rendered by {@see SessionResource}, so the two payloads cannot drift: a field
 * added to the study card appears here the same day.
 */
final class PlanSessionResource extends JsonResource
{
    /** @param PlanSessionView $resource */
    public function __construct(PlanSessionView $resource)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var PlanSessionView $view */
        $view = $this->resource;

        return [
            'session_id' => $view->sessionId,
            'plan_id' => $view->planId,
            'day_index' => $view->dayIndex,
            // FALSE means this day was opened out of turn: an ordinary soft run over its material,
            // which schedules nothing and closes no stage.
            'strict' => $view->strict,
            'focus_day_index' => $view->focusDayIndex,
            // The level's six, as this session ran on them — including the ones no trainer reads
            // yet, which every task names for itself under `knobs_ignored`.
            'knobs' => $view->knobs,
            // WHERE THE SEAM FALLS. `tasks` is ordered day-first, so the first `day_task_count`
            // entries are this plan's own material and the rest are the top-up from the learner's
            // ordinary queue. A client counts «день пройден» out of THIS number — counting out of
            // `tasks` is how a day of fourteen was announced as twenty-one.
            'day_task_count' => $view->dayTaskCount,
            // ПРИСЕСТЫ: the task counts of each sitting, in order, adding up to `count(tasks)`.
            // The learner's minutes are the length of one sitting, not a limit on the day, and the
            // breaks fall only on section boundaries. A client that ignores this plays the day as
            // one long session — which is what it did before.
            'sittings' => $view->sittings,
            // СЛОВО О ДНЕ и его минуты — те же, что на пейлоаде плана (наряд DAY-FIX-2, Ч.3). Шапка
            // присеста читает их отсюда; локального счётчика «осталось N» у неё нет.
            'day_state' => $view->dayState->value,
            'minutes_left' => $view->minutesLeft,
            // СЕКУНДЫ ПРОГОНА СЦЕНЫ — «сразу», «Пропустить», сторож и цена хода в минутах дня
            // (наряд SCENE-RUN). На проводе, а не в коде экрана: это продуктовые суждения о том,
            // сколько человек думает, и они обязаны двигаться без выката приложения.
            'scene_run' => $view->sceneRun,
            // THE CONVERSATIONS THIS SITTING PLAYS — one per scene it reaches, whole, in the order
            // the scene is spoken. The turns outnumber the tasks on purpose: the screen plays the
            // conversation from its first line and hands the learner a move only where a task with
            // the same `term_id` exists.
            'dialogues' => array_map(static fn (PlanDialogueView $dialogue): array => [
                'day_index' => $dialogue->dayIndex,
                'scene_title' => $dialogue->sceneTitle,
                'scene_intro' => $dialogue->sceneIntro,
                // ДОЗРЕЛА ЛИ СЦЕНА ДО ПРОГОНА (наряд SCENE-RUN, Ч.2.8). В обычный день у сцены,
                // чей прогон собрали, это всегда true; на последнем дне гоняются и недозревшие, и
                // итог их помечает вместо того, чтобы делать вид, что они были как остальные.
                'run_ready' => $dialogue->runReady,
                'turns' => array_map(static fn (PlanDialogueTurnView $turn): array => [
                    'turn' => $turn->turn,
                    'term_id' => $turn->termId,
                    'text' => $turn->text,
                    'translation' => $turn->translation,
                    'shelf' => $turn->shelf,
                    // ОЗВУЧКА РЕПЛИКИ (наряд TTS-1, Ч.1.3). Null = «серверного файла нет», и клиент
                    // читает строку системным голосом, как читал всегда.
                    'audio_url' => LineAudioUrl::for($turn->audioId),
                    // СТРОГОСТЬ СВОЕГО ХОДА — `choose` | `assemble` | `say`, null у реплики
                    // собеседника (наряд SCENE-RUN, Ч.1). Едет на цепочке, а не только на задаче:
                    // ходов больше, чем задач, и лента рисуется вперёд.
                    'level' => $turn->level,
                    // ТИП ОБМЕНА (P2 v0.6): `answer` — спросили, ты ответил; `ask` — пригласили
                    // спросить, ты спросил. Null на цепочке, написанной до пар.
                    'pair' => $turn->pairKind,
                ], $dialogue->turns),
            ], $view->dialogues),
            // ВСЯ ОЗВУЧКА ЭТОЙ ПОСАДКИ одним списком — то, что телефон качает на входе в день,
            // включая реплики второго присеста и спасателей, которых сегодня нет ни на одной
            // карточке. Пустой список — законный ответ («озвучки нет»), не ошибка.
            'line_audio' => array_map(static fn (PlanLineAudioView $row): array => [
                'term_id' => $row->termId,
                'text' => $row->text,
                'url' => LineAudioUrl::for($row->audioId),
            ], $view->lineAudio),
            'tasks' => array_map(static fn (PlanSessionTaskView $task): array => [
                'stage' => $task->stage,
                // `warmup` | `day` | `review` — which seam this task sits under. Said once here so
                // every client does not re-derive it (and get it wrong).
                'section' => $task->section,
                // …and what the learner is DOING, as a code they see a caption for: `warmup`,
                // `words`, `dialogue_intro`, `dialogue`, `numbers`, `rehearsal`, `review`, `day`.
                // `section` is arithmetic (which side of the seam), this is the sentence over the
                // card — and `shelf` cannot stand in for it, because the introduction of a reply and
                // the conversation it becomes are both `say`.
                'section_code' => $task->sectionCode,
                // «Отпуск в Италии» — where a REVIEW card came from, so the learner is not handed
                // a word out of nowhere in the middle of a plan's lesson. Null on the day's own
                // cards, which need no explanation.
                'origin' => $task->origin,
                'ordinal' => $task->ordinal,
                'of_steps' => $task->ofSteps,
                'from_day_index' => $task->fromDayIndex,
                'softened' => $task->softened,
                'source' => $task->source,
                'speaking_form' => $task->speakingForm,
                // Where a cloze card cuts its gap: the day's own frame when there is one, the
                // card's example otherwise. Null on every other trainer.
                'cloze_source' => $task->clozeSource,
                // WHOSE line it is, and WHAT the card is in its day. The first marks the
                // interlocutor's turn (Д-8); the second is what the summary counts by, instead of
                // counting words in the text and calling a connector a phrase (Д-5).
                'speaker' => $task->speaker,
                'kind' => $task->kind,
                // WHICH SHELF this card is from, and which ladder it climbs. The client draws the
                // seam captions off `shelf` («Разогрев», «Тебе скажут», «Ты ответишь», «Ты
                // спросишь», «Слова и связки») and reads `tier` to know that a `understand` card is
                // never something to say back. Additive: a client that ignores both plays the
                // session exactly as it does today.
                'shelf' => $task->shelf,
                'tier' => $task->tier,
                // THE POSITION a situational card puts the learner in, and whether they say the
                // line they tapped afterwards. Null / false on every other trainer.
                'situation' => $task->situation,
                'speaks_after_choice' => $task->speaksAfterChoice,
                // СТРОГОСТЬ ЭТОГО ХОДА. Отдельно от `exercise_mode`: тренажёр один и тот же на всех
                // уровнях, а рисуется он вариантами, блоками или микрофоном. Null у всего, что не
                // является ходом человека в диалоге.
                'turn_level' => $task->turnLevel,
                'knobs_applied' => $task->knobsApplied,
                'knobs_ignored' => $task->knobsIgnored,
                'card' => self::card($task),
            ], $view->tasks),
        ];
    }

    /** @return array<string, mixed> */
    private static function card(PlanSessionTaskView $task): array
    {
        $rendered = (new SessionResource(new SessionView('', [$task->card])))->toArray(request());

        /** @var list<array<string, mixed>> $cards */
        $cards = $rendered['cards'];

        return $cards[0];
    }
}

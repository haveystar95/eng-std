<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Model;

use App\Modules\Plan\Application\Dto\ConversationAgentRequest;
use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Dto\NativeSeamJudgeRequest;
use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Application\Dto\SlotJudgeRequest;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\LessonRoles;
use App\Modules\Plan\Domain\Service\FrameText;
use Closure;

/**
 * Deterministic answers without a network — `PLAN_MODEL_DRIVER=fake` and the whole test suite.
 *
 * The default answers are a valid plan of exactly SCENES_COUNT scenes and a clean lesson — every rule
 * the lesson validator counts honoured — so a day can be dealt from them; the seam judge reads every native
 * sentence as fine; the slot judge accepts every attempt, taking what was heard for the slot. A goal containing
 * «unclear» comes back `unclear`, the way the prompt answers a non-situation. A test that wants a BROKEN answer hands
 * in its own closure for any call — or a closure that throws, for a model that does not answer.
 */
final class FakePlanModel implements PlanModelPort
{
    public const MODEL = 'fake-plan-model';

    /** How many times each call was made — the assertion behind «one retry, not two». */
    public int $planCalls = 0;

    public int $lessonCalls = 0;

    public int $repairCalls = 0;

    public int $judgeCalls = 0;

    public int $slotJudgeCalls = 0;

    public int $conversationCalls = 0;

    /** @var list<SlotJudgeRequest> */
    public array $slotJudgeRequests = [];

    /** @var list<ConversationAgentRequest> */
    public array $conversationRequests = [];

    /** @var list<LessonCardRepairRequest> */
    public array $repairRequests = [];

    /** @var list<NativeSeamJudgeRequest> */
    public array $judgeRequests = [];

    /** @var list<PlanRequest> */
    public array $planRequests = [];

    /** @var list<LessonRequest> */
    public array $lessonRequests = [];

    /**
     * @param  (Closure(PlanRequest, int): array<string, mixed>)|null  $plan  attempt number is the second argument
     * @param  (Closure(LessonRequest, int): array<string, mixed>)|null  $lesson
     * @param  (Closure(LessonCardRepairRequest, int): array<string, mixed>)|null  $repair  the default returns the card as written
     * @param  (Closure(NativeSeamJudgeRequest, int): array<string, mixed>)|null  $judge  the default reads every sentence as fine
     * @param  (Closure(SlotJudgeRequest, int): array<string, mixed>)|null  $slotJudge  the default accepts; a closure that throws is a silent model
     * @param  (Closure(ConversationAgentRequest, int): array<string, mixed>)|null  $conversation  the default plays the role by the book; a closure that throws is a silent agent
     */
    public function __construct(
        private readonly ?Closure $plan = null,
        private readonly ?Closure $lesson = null,
        private readonly ?Closure $repair = null,
        private readonly string $planVersion = 'plan-builder-v2',
        private readonly string $lessonVersion = 'lesson_day.v4.7',
        private readonly ?Closure $judge = null,
        private readonly ?Closure $slotJudge = null,
        private readonly ?Closure $conversation = null,
    ) {}

    public function buildPlan(PlanRequest $request): ModelReply
    {
        $this->planCalls++;
        $this->planRequests[] = $request;
        $payload = $this->plan !== null ? ($this->plan)($request, $this->planCalls) : self::planPayload($request);

        return new ModelReply($payload, $this->planVersion, self::MODEL, 1200, 800, '0.000000', 5, '');
    }

    public function buildLesson(LessonRequest $request): ModelReply
    {
        $this->lessonCalls++;
        $this->lessonRequests[] = $request;
        $payload = $this->lesson !== null ? ($this->lesson)($request, $this->lessonCalls) : self::lessonPayload($request);

        return new ModelReply($payload, $this->lessonVersion, self::MODEL, 3000, 2500, '0.000000', 7, '');
    }

    public function repairLessonCard(LessonCardRepairRequest $request): ModelReply
    {
        $this->repairCalls++;
        $this->repairRequests[] = $request;
        $payload = $this->repair !== null ? ($this->repair)($request, $this->repairCalls) : ['card' => $request->card];

        return new ModelReply($payload, 'lesson_card_repair.v1.3', self::MODEL, 900, 300, '0.000000', 3, '');
    }

    public function judgeNativeSeams(NativeSeamJudgeRequest $request): ModelReply
    {
        $this->judgeCalls++;
        $this->judgeRequests[] = $request;
        $payload = $this->judge !== null
            ? ($this->judge)($request, $this->judgeCalls)
            : ['verdicts' => array_map(static fn (string $id): array => ['id' => $id, 'reads' => true], $request->ids())];

        return new ModelReply($payload, 'lesson_seam_judge.v1.1', self::MODEL, 400, 120, '0.000000', 2, '');
    }

    public function judgeSlot(SlotJudgeRequest $request): ModelReply
    {
        $this->slotJudgeCalls++;
        $this->slotJudgeRequests[] = $request;
        $payload = $this->slotJudge !== null
            ? ($this->slotJudge)($request, $this->slotJudgeCalls)
            : [
                'accepted' => true,
                'slot_value' => $request->heard,
                'reason_native' => null,
            ];

        return new ModelReply($payload, 'slot_judge.v3', self::MODEL, 350, 40, '0.000000', 1, '');
    }

    /**
     * The role, played deterministically (наряд CONV-1): it asks, walks one checkpoint per move,
     * hears every phrase the learner said, says a rescue in other words (наряд CONV-2, п. 4б), and says
     * goodbye when the turns run out — enough for the whole suite to walk a talk end to end without a
     * network.
     */
    public function conversationTurn(ConversationAgentRequest $request): ModelReply
    {
        $this->conversationCalls++;
        $this->conversationRequests[] = $request;
        $payload = $this->conversation !== null
            ? ($this->conversation)($request, $this->conversationCalls)
            : self::conversationPayload($request);

        return new ModelReply($payload, 'conversation_agent.v2', self::MODEL, 900, 90, '0.000000', 2, '');
    }

    /** @return array<string, mixed> */
    public static function conversationPayload(ConversationAgentRequest $request): array
    {
        $ending = $request->turnsLeft <= 0;
        $checkpoint = $request->turn === 'rescue' ? null : $request->currentCheckpoint;

        return [
            'reply_target' => match (true) {
                $ending => 'Take care. See you next week.',
                $request->turn === 'rescue' => 'What is wrong today?',
                default => 'And what brings you in today?',
            },
            'reply_native' => match (true) {
                $ending => 'Берегите себя. До встречи на следующей неделе.',
                $request->turn === 'rescue' => 'Что сегодня не так?',
                default => 'Что вас беспокоит?',
            },
            'understood' => $request->turn === 'said' ? true : null,
            'phrases_used' => [],
            'off_topic' => false,
            'checkpoint_done' => $ending ? $checkpoint : null,
            'next_hint_native' => $ending ? null : 'скажи, что болит',
            'end' => $ending ? 'natural' : 'no',
        ];
    }

    public function planPromptVersion(): string
    {
        return $this->planVersion;
    }

    public function repairPromptVersion(): string
    {
        return 'lesson_card_repair.v1.3';
    }

    public function judgePromptVersion(): string
    {
        return 'lesson_seam_judge.v1.1';
    }

    public function slotJudgePromptVersion(): string
    {
        return 'slot_judge.v3';
    }

    public function conversationPromptVersion(): string
    {
        return 'conversation_agent.v2';
    }

    public function lessonPromptVersion(): string
    {
        return $this->lessonVersion;
    }

    /** The roles of the fake's plan and of its clean lesson — the plan's parent, the doctor of the visit. */
    public static function roles(): LessonRoles
    {
        return new LessonRoles('Parent', 'Родитель', 'Doctor', 'Врач');
    }

    /** @return array<string, mixed> */
    public static function planPayload(PlanRequest $request): array
    {
        if (mb_stripos($request->goal, 'unclear') !== false || mb_stripos($request->goal, 'подтянуть') !== false) {
            return ['status' => 'unclear', 'unclear_reason' => 'The goal names no concrete situation.', 'plan' => null, 'scenes' => []];
        }

        $titles = [
            ['Запись к врачу', 'Booking', 'спросить время и страховку', 'Receptionist', 'Регистратор'],
            ['Приём у врача', 'Consultation', 'описать боль, понять назначения', 'Doctor', 'Врач'],
            ['Аптека', 'Pharmacy', 'понять дозировку и время приёма', 'Pharmacist', 'Фармацевт'],
            ['Анализы', 'Tests', 'понять, какие анализы сдать', 'Nurse', 'Медсестра'],
            ['Повторный приём', 'Follow-up', 'рассказать, что изменилось', 'Doctor', 'Врач'],
            ['Оплата', 'Payment', 'спросить цену и оплатить', 'Cashier', 'Кассир'],
            ['Справка', 'Certificate', 'попросить справку для школы', 'Receptionist', 'Регистратор'],
        ];
        $offset = count($request->existingScenes);
        $scenes = [];
        for ($i = 0; $i < $request->scenesCount; $i++) {
            $row = $titles[($offset + $i) % count($titles)];
            $order = $offset + $i + 1;
            $scenes[] = [
                'order' => $order,
                'kind' => $i < 3 ? 'situation' : 'variant',
                'priority' => $i === 1 || $request->scenesCount === 1 ? 1 : ($i === 0 ? 2 : $i + 1),
                'title_native' => $row[0].($offset > 0 ? ' '.$order : ''),
                'title_target' => $row[1],
                'teaches_native' => $row[2],
                'goals_native' => ['описать, где болит', 'ответить на вопросы', 'понять назначения'],
                'learner_role_target' => 'Parent',
                'learner_role_native' => 'Родитель',
                'partner_role_target' => $row[3],
                'partner_role_native' => $row[4],
                'topic_description' => "Situation: {$row[1]} at a local clinic with a child who has back pain, first visit. "
                    ."Learner: Parent. Partner: {$row[3]}. "
                    .'Learner must be able to: describe the pain, answer questions, understand instructions. '
                    .'Partner will: ask about symptoms, give instructions. '
                    .'Not in this scene: booking, payment, buying medicine.',
                'image_prompt' => 'reception desk of a small clinic, warm light',
            ];
        }

        return [
            'status' => 'ok',
            'unclear_reason' => null,
            'plan' => [
                'title_native' => 'Приём у врача',
                'title_target' => 'Doctor visit',
                'event_native' => 'Приём',
                'until_phrase_native' => 'До приёма',
                'overdue_native' => 'Приём был вчера',
                'cover_image_prompt' => 'clinic corridor with doors, daylight',
                'learner_role_target' => 'Parent',
                'learner_role_native' => 'Родитель',
            ],
            'scenes' => $scenes,
        ];
    }

    /**
     * THE CLEAN LESSON (`lesson_day.v4.7`): a doctor's visit with a child's back pain, written to break
     * no rule the validator counts — the fixture every plan test deals its days from, and the baseline a
     * test breaks one rule of.
     *
     * Eight exchanges: five answers, one rescue after the long partner line of exchange 5, two asks that
     * say the SAME frame with two different fillers (`p6`, «Do we need ___?», exchanges 7 and 8). Written to v4.5: v4.6 asks
     * a frame not to stand in two exchanges in a row, so the validator counts one warning here, `frame.adjacent_repeat` at
     * `x8` — kept, because every test of the day's dealing reads this order.
     * Six frames for seven answer/ask exchanges, one of them without a slot; eight vocabulary items, six of them in the
     * learner's frames or fillers; three listening questions, the first asking the learner's own value.
     * The right answers stand at varied places, as a model would put them before the server shuffles.
     *
     * Other counts are served by cycling the same material — such a lesson is dealt fine but is no
     * longer clean.
     *
     * A LATER DAY OF THE STORY (EARLIER_DAYS not empty, наряд GEN-3) keeps what it learned: the same visit, told with every
     * word and every frame of the day marked by the day's number ({@see laterDay()}) — no word or frame of an earlier day
     * comes back, as the prompt asks, and the day breaks what the first breaks and nothing more.
     *
     * @return array<string, mixed>
     */
    public static function lessonPayload(LessonRequest $request): array
    {
        $payload = self::firstDayPayload($request);
        $day = count($request->earlierDays->days) + 1;

        return $day === 1 ? $payload : self::laterDay($payload, $day);
    }

    /** @return array<string, mixed> */
    private static function firstDayPayload(LessonRequest $request): array
    {
        $doctor = ['role_target' => 'Doctor', 'role_native' => 'Врач'];
        $parent = ['role_target' => 'Parent', 'role_native' => 'Родитель'];
        $a = static fn (string $text, string $native): array => ['speaker' => 'A', ...$doctor, 'text_target' => $text, 'text_native' => $native];
        $b = static fn (?string $phrase, ?string $filler, string $text, string $native, string $reading, string $key, array $variants): array => [
            'speaker' => 'B', ...$parent, 'phrase_id' => $phrase, 'filler' => $filler, 'text_target' => $text, 'text_native' => $native,
            'pronunciation_native' => $reading, 'speaking_key' => $key, 'simplified_variants' => $variants,
        ];
        $check = static fn (string $q, string $qNative, array $options, int $correct, string $why): array => [
            'text_target' => $q,
            'text_native' => $qNative,
            'options' => array_map(static fn (array $o): array => ['text_target' => $o[0], 'text_native' => $o[1]], $options),
            'correct_option_index' => $correct,
            'explanation_native' => $why,
        ];

        $exchanges = [
            ['answer', 'A', [
                $a('Where does it hurt: his upper back or his lower back?', 'Где болит: вверху спины или в пояснице?'),
                $b('p1', 'lower back', 'It hurts in his lower back.', 'У него болит поясница.', 'ит хёртс ин хиз лоуэр бэк', 'It hurts', ['His lower back hurts.']),
            ], $check('Which two places does the doctor ask about?', 'О каких двух местах спрашивает врач?', [
                ['The top or the bottom of the back', 'Верх или низ спины'], ['The neck or the head', 'Шея или голова'], ['The knees or the feet', 'Колени или ступни'],
            ], 0, 'Врач спрашивает, болит вверху спины или в пояснице.')],
            ['answer', 'A', [
                $a('Did it start today, or earlier this week?', 'Началось сегодня или раньше на этой неделе?'),
                $b('p2', 'three days ago', 'It started three days ago.', 'Началось три дня назад.', 'ит стартид сри дэйз эгоу', 'It started', ['Three days ago.']),
            ], $check('Which two times does the doctor mention?', 'Какие два варианта времени называет врач?', [
                ['Last month or last year', 'В прошлом месяце или году'], ['Now or a few days before', 'Сегодня или на днях'], ['At night or in the morning', 'Ночью или утром'],
            ], 1, 'Врач спрашивает про сегодня или начало недели.')],
            ['answer', 'A', [
                $a('Is the pain sharp, or more of a dull ache?', 'Боль острая или скорее ноющая?'),
                $b('p3', 'sharp', 'The pain is sharp when he bends.', 'Боль острая, когда он наклоняется.', 'зэ пэйн из шарп уэн хи бэндз', 'when he bends', ['It is sharp when he bends.']),
            ], $check('What does the doctor want to know about the pain?', 'Что врач хочет узнать о боли?', [
                ['How long it lasts', 'Сколько она длится'], ['Where it started', 'Где она началась'], ['What kind of pain it is', 'Какая это боль'],
            ], 2, 'Врач спрашивает, острая боль или ноющая.')],
            ['answer', 'A', [
                $a('Does he have a fever?', 'У него есть температура?'),
                $b('p4', null, 'No, he doesn\'t have a fever.', 'Нет, температуры нет.', 'ноу хи дазнт хэв э фивер', 'have a fever', ['No fever.']),
            ], $check('What symptom does the doctor ask about?', 'О каком симптоме спрашивает врач?', [
                ['A high temperature', 'Высокая температура'], ['A cough', 'Кашель'], ['A rash', 'Сыпь'],
            ], 0, 'Врач спрашивает про температуру.')],
            ['answer', 'A', [
                $a('It looks like a muscle strain, so he should rest and use a heating pad.', 'Похоже на растяжение мышцы, так что ему нужен покой и грелка.'),
                $b('p5', 'at home', 'Okay, he will rest at home.', 'Хорошо, он будет отдыхать дома.', 'оукей хи уил рэст эт хоум', 'will rest', ['He will rest.']),
            ], $check('What does the doctor think the problem is?', 'Что, по мнению врача, случилось?', [
                ['A broken bone', 'Перелом'], ['A pulled muscle', 'Растянутая мышца'], ['A bad cold', 'Простуда'],
            ], 1, 'Врач говорит, что это растяжение мышцы.')],
            ['rescue', 'B', [
                $b(null, null, 'Sorry, could you say that more slowly?', 'Простите, можно помедленнее?', 'сори куд ю сэй зэт мор слоули', 'more slowly', ['More slowly, please?']),
                $a('He should rest and use a heating pad.', 'Ему нужен покой и грелка.'),
            ], $check('What should they use at home?', 'Что нужно использовать дома?', [
                ['Ice on the neck', 'Лёд на шею'], ['A cream for the knees', 'Мазь для коленей'], ['Something warm on the back', 'Что-то тёплое на спину'],
            ], 2, 'Врач советует грелку.')],
            ['ask', 'B', [
                $b('p6', 'an X-ray', 'Do we need an X-ray?', 'Нам нужно сделать рентген?', 'ду уи нид эн экс-рэй', 'Do we need', ['Is an X-ray needed?']),
                $a('No, an X-ray is not needed for a muscle strain.', 'Нет, при растяжении мышцы рентген не нужен.'),
            ], $check('Why is an X-ray not needed?', 'Почему рентген не нужен?', [
                ['The bone is broken', 'Сломана кость'], ['It is only a pulled muscle', 'Это просто растяжение мышцы'], ['The clinic is closed', 'Клиника закрыта'],
            ], 1, 'При растяжении мышцы рентген не нужен.')],
            ['ask', 'B', [
                $b('p6', 'a follow-up appointment', 'Do we need a follow-up appointment?', 'Нам нужно прийти на повторный приём?', 'ду уи нид э фоллоу-ап эпойнтмент', 'Do we need', ['Should we come back?']),
                $a('Only if it still hurts after one week.', 'Только если через неделю ещё будет болеть.'),
            ], $check('When should they come back?', 'Когда нужно прийти снова?', [
                ['Tomorrow morning', 'Завтра утром'], ['In a year', 'Через год'], ['If the pain does not stop in seven days', 'Если боль не пройдёт через семь дней'],
            ], 2, 'Прийти снова, если через неделю ещё болит.')],
        ];

        $dialogue = [];
        for ($i = 0; $i < $request->dialogueCount; $i++) {
            [$kind, $initiator, $messages, $question] = $exchanges[$i % count($exchanges)];
            $dialogue[] = ['step' => $i + 1, 'kind' => $kind, 'initiator' => $initiator, 'messages' => $messages, 'check' => $question];
        }

        $filler = static fn (string $target, string $native, string $reading, bool $said): array => [
            'target' => $target, 'native' => $native, 'pronunciation_native' => $reading, 'in_dialogue' => $said,
        ];
        $phrases = [
            ['id' => 'p1', 'kind' => 'answer', 'frame_target' => 'It hurts in his ___.', 'frame_native' => 'У него болит ___.', 'pronunciation_native' => 'ит хёртс ин хиз ___', 'slot' => [
                'hint_native' => 'где болит', 'fillers' => [$filler('lower back', 'поясница', 'лоуэр бэк', true), $filler('neck', 'шея', 'нэк', false), $filler('shoulder', 'плечо', 'шоулдер', false)],
            ]],
            ['id' => 'p2', 'kind' => 'answer', 'frame_target' => 'It started ___.', 'frame_native' => 'Началось ___.', 'pronunciation_native' => 'ит стартид ___', 'slot' => [
                'hint_native' => 'когда', 'fillers' => [$filler('three days ago', 'три дня назад', 'сри дэйз эгоу', true), $filler('last night', 'вчера вечером', 'ласт найт', false), $filler('this morning', 'сегодня утром', 'зис морнинг', false)],
            ]],
            ['id' => 'p3', 'kind' => 'answer', 'frame_target' => 'The pain is ___ when he bends.', 'frame_native' => 'Боль ___, когда он наклоняется.', 'pronunciation_native' => 'зэ пэйн из ___ уэн хи бэндз', 'slot' => [
                'hint_native' => 'какая боль', 'fillers' => [$filler('sharp', 'острая', 'шарп', true), $filler('dull', 'ноющая', 'дал', false), $filler('constant', 'постоянная', 'констант', false)],
            ]],
            ['id' => 'p4', 'kind' => 'answer', 'frame_target' => 'He doesn\'t have a fever.', 'frame_native' => 'Температуры у него нет.', 'pronunciation_native' => 'хи дазнт хэв э фивер', 'slot' => null],
            ['id' => 'p5', 'kind' => 'answer', 'frame_target' => 'He will rest ___.', 'frame_native' => 'Он будет отдыхать ___.', 'pronunciation_native' => 'хи уил рэст ___', 'slot' => [
                'hint_native' => 'где или сколько', 'fillers' => [$filler('at home', 'дома', 'эт хоум', true), $filler('for two days', 'два дня', 'фор ту дэйз', false), $filler('after school', 'после школы', 'афтер скул', false)],
            ]],
            ['id' => 'p6', 'kind' => 'ask', 'frame_target' => 'Do we need ___?', 'frame_native' => 'Нам нужно ___?', 'pronunciation_native' => 'ду уи нид ___', 'slot' => [
                'hint_native' => 'что сделать', 'fillers' => [
                    $filler('an X-ray', 'сделать рентген', 'эн экс-рэй', true),
                    $filler('a follow-up appointment', 'прийти на повторный приём', 'э фоллоу-ап эпойнтмент', true),
                    $filler('a sick note', 'взять справку для школы', 'э сик ноут', false),
                ],
            ]],
        ];

        $vocabulary = [
            ['lower back', 'поясница', 'лоуэр бэк', 'the part of the back above the hips', 'chunk', 'a parent pressing a hand on a child\'s lower back', ['p1', 'A1']],
            ['sharp', 'острая', 'шарп', 'sudden and strong, like a cut', 'word', null, ['p3', 'A3']],
            ['fever', 'температура', 'фивер', 'a body temperature higher than normal', 'word', 'a digital thermometer showing a high temperature', ['p4', 'A4']],
            ['muscle strain', 'растяжение мышцы', 'масл стрэйн', 'an injury to a muscle from stretching it too far', 'chunk', 'a physiotherapist touching a child\'s back muscle', ['A5', 'A7']],
            ['heating pad', 'грелка', 'хитинг пэд', 'a warm pad you put on a painful place', 'chunk', 'an electric heating pad on a sofa', ['A5', 'A6']],
            ['X-ray', 'рентген', 'экс-рэй', 'a picture of the inside of the body', 'word', 'a doctor looking at a spine x-ray on a light box', ['p6', 'A7']],
            ['follow-up appointment', 'повторный приём', 'фоллоу-ап эпойнтмент', 'a second visit to check how you are', 'chunk', 'a calendar page with a clinic visit marked', ['p6']],
            ['sick note', 'справка', 'сик ноут', 'a note from a doctor saying someone was ill', 'chunk', 'a doctor\'s note lying on a school desk', ['p6']],
        ];
        $items = [];
        for ($i = 0; $i < $request->vocabularyCount; $i++) {
            $row = $vocabulary[$i % count($vocabulary)];
            $items[] = [
                'id' => 'v'.($i + 1),
                'term_target' => $i < count($vocabulary) ? $row[0] : $row[0].' '.($i + 1),
                'translation_native' => $row[1],
                'pronunciation_native' => $row[2],
                'definition_target' => $row[3],
                'kind' => $row[4],
                'image_prompt' => $row[5],
                'used_in' => $row[6],
            ];
        }

        return [
            'topic' => [
                'title_target' => 'At the doctor\'s with a child',
                'title_native' => $request->topic,
                'description_target' => 'Describing a child\'s back pain to a doctor and understanding the advice.',
                'description_native' => 'Рассказать врачу о боли в спине у ребёнка и понять советы.',
            ],
            'learner_role' => $parent,
            'role_gender' => 'female',
            'dialogue' => $dialogue,
            'phrases' => $phrases,
            'listening' => ['questions' => [
                ['text_native' => 'Что болит у ребёнка?', 'options_native' => ['Шея', 'Поясница', 'Плечо'], 'correct_option_index' => 1, 'explanation_native' => 'Родитель говорит, что болит поясница.'],
                ['text_native' => 'Что врач советует делать дома?', 'options_native' => ['Отдыхать и греть спину', 'Лечь в больницу', 'Много бегать'], 'correct_option_index' => 0, 'explanation_native' => 'Врач советует покой и грелку.'],
                ['text_native' => 'Когда нужно прийти снова?', 'options_native' => ['Завтра', 'Через месяц', 'Если через неделю ещё болит'], 'correct_option_index' => 2, 'explanation_native' => 'Прийти снова, если через неделю ещё болит.'],
            ]],
            'vocabulary' => $items,
        ];
    }

    /**
     * The same lesson as day `$day` of the story: every word of the day gets «-{day}» wherever the target text says it
     * (frames, fillers, lines, variants, the word itself), and every frame gets «-{day}» on its first word — in both
     * languages — and so does every line that stands on it, after its glue. No word is added: the day breaks what the first
     * day breaks and nothing more.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function laterDay(array $payload, int $day): array
    {
        /** @var list<array<string, mixed>> $vocabulary */
        $vocabulary = $payload['vocabulary'];
        $terms = array_map(static fn (array $item): string => (string) $item['term_target'], $vocabulary);
        usort($terms, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        $marked = static function (string $text) use ($terms, $day): string {
            foreach ($terms as $term) {
                $text = (string) preg_replace('/(?<![\p{L}\p{N}\'’-])('.preg_quote($term, '/').')(?![\p{L}\p{N}\'’-])/iu', '$1-'.$day, $text);
            }

            return $text;
        };
        $firstWord = static fn (string $text): string => (string) preg_replace('/^([\p{L}\p{N}\'’]+)/u', '$1-'.$day, $text, 1);

        foreach ($payload['vocabulary'] as $i => $item) {
            $payload['vocabulary'][$i]['term_target'] = $item['term_target'].'-'.$day;
        }
        foreach ($payload['phrases'] as $i => $phrase) {
            $payload['phrases'][$i]['frame_target'] = $marked((string) $phrase['frame_target']);
            foreach ($phrase['slot']['fillers'] ?? [] as $f => $filler) {
                $payload['phrases'][$i]['slot']['fillers'][$f]['target'] = $marked((string) $filler['target']);
            }
        }
        foreach ($payload['dialogue'] as $x => $exchange) {
            foreach ($exchange['messages'] as $m => $message) {
                $payload['dialogue'][$x]['messages'][$m]['text_target'] = $marked((string) $message['text_target']);
                if ($message['speaker'] === 'B') {
                    $payload['dialogue'][$x]['messages'][$m]['filler'] = $message['filler'] === null ? null : $marked((string) $message['filler']);
                    $payload['dialogue'][$x]['messages'][$m]['simplified_variants'] = array_map($marked, $message['simplified_variants']);
                }
            }
        }

        // The lines are read against their frames before the frames change: what a line says after its glue gets the mark.
        $lesson = (new LessonParser)->parse($payload);
        foreach ($payload['dialogue'] as $x => $exchange) {
            foreach ($exchange['messages'] as $m => $message) {
                $phrase = $message['speaker'] === 'B' && $message['phrase_id'] !== null ? $lesson->phrase((string) $message['phrase_id']) : null;
                if ($phrase === null) {
                    continue;
                }
                $text = (string) $message['text_target'];
                $glue = FrameText::line($phrase, $text)['glue'];
                $payload['dialogue'][$x]['messages'][$m]['text_target'] = $glue.$firstWord(mb_substr($text, mb_strlen($glue)));
            }
        }
        foreach ($payload['phrases'] as $i => $phrase) {
            $payload['phrases'][$i]['frame_target'] = $firstWord((string) $phrase['frame_target']);
            $payload['phrases'][$i]['frame_native'] = $firstWord((string) $phrase['frame_native']);
        }

        return $payload;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Model;

use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Dto\NativeSeamJudgeRequest;
use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Application\Port\PlanModelPort;
use Closure;

/**
 * Deterministic answers without a network — `PLAN_MODEL_DRIVER=fake` and the whole test suite.
 *
 * The default answers are a valid plan of exactly SCENES_COUNT scenes and a clean lesson — every rule
 * the lesson validator counts honoured — so a day can be dealt from them; the seam judge reads every native
 * sentence as fine. A goal containing «unclear» comes back `unclear`, the way the prompt answers a
 * non-situation. A test that wants a BROKEN answer hands in its own closure for any call.
 */
final class FakePlanModel implements PlanModelPort
{
    public const MODEL = 'fake-plan-model';

    /** How many times each call was made — the assertion behind «one retry, not two». */
    public int $planCalls = 0;

    public int $lessonCalls = 0;

    public int $repairCalls = 0;

    public int $judgeCalls = 0;

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
     */
    public function __construct(
        private readonly ?Closure $plan = null,
        private readonly ?Closure $lesson = null,
        private readonly ?Closure $repair = null,
        private readonly string $planVersion = 'plan-builder-v2',
        private readonly string $lessonVersion = 'lesson_day.v4.5',
        private readonly ?Closure $judge = null,
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

        return new ModelReply($payload, 'lesson_card_repair.v1.1', self::MODEL, 900, 300, '0.000000', 3, '');
    }

    public function judgeNativeSeams(NativeSeamJudgeRequest $request): ModelReply
    {
        $this->judgeCalls++;
        $this->judgeRequests[] = $request;
        $payload = $this->judge !== null
            ? ($this->judge)($request, $this->judgeCalls)
            : ['verdicts' => array_map(static fn (string $id): array => ['id' => $id, 'reads' => true], $request->ids())];

        return new ModelReply($payload, 'lesson_seam_judge.v1', self::MODEL, 400, 120, '0.000000', 2, '');
    }

    public function planPromptVersion(): string
    {
        return $this->planVersion;
    }

    public function repairPromptVersion(): string
    {
        return 'lesson_card_repair.v1.1';
    }

    public function judgePromptVersion(): string
    {
        return 'lesson_seam_judge.v1';
    }

    public function lessonPromptVersion(): string
    {
        return $this->lessonVersion;
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
     * THE CLEAN LESSON (`lesson_day.v4.5`): a doctor's visit with a child's back pain, written to break
     * no rule the validator counts — the fixture every plan test deals its days from, and the baseline a
     * test breaks one rule of.
     *
     * Eight exchanges: five answers, one rescue after the long partner line of exchange 5, two asks that
     * say the SAME frame with two different fillers (`p6`, «Do we need ___?»). Six frames for seven
     * answer/ask exchanges, one of them without a slot; eight vocabulary items, six of them in the
     * learner's frames or fillers; three listening questions, the first asking the learner's own value.
     * The right answers stand at varied places, as a model would put them before the server shuffles.
     *
     * Other counts are served by cycling the same material — such a lesson is dealt fine but is no
     * longer clean.
     *
     * @return array<string, mixed>
     */
    public static function lessonPayload(LessonRequest $request): array
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
}

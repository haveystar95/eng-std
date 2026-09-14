<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Model;

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Application\Port\PlanModelPort;
use Closure;

/**
 * Deterministic answers without a network — `PLAN_MODEL_DRIVER=fake` and the whole test suite.
 *
 * The default answers are a valid plan of exactly SCENES_COUNT scenes and a valid lesson of the
 * requested counts, every rule of the prompts honoured, so a day can be dealt from them. A goal
 * containing «unclear» comes back `unclear`, the way the prompt answers a non-situation. A test
 * that wants a BROKEN answer hands in its own closure for either call.
 */
final class FakePlanModel implements PlanModelPort
{
    public const MODEL = 'fake-plan-model';

    /** How many times each call was made — the assertion behind «one retry, not two». */
    public int $planCalls = 0;

    public int $lessonCalls = 0;

    /** @var list<PlanRequest> */
    public array $planRequests = [];

    /** @var list<LessonRequest> */
    public array $lessonRequests = [];

    /**
     * @param  (Closure(PlanRequest, int): array<string, mixed>)|null  $plan  attempt number is the second argument
     * @param  (Closure(LessonRequest, int): array<string, mixed>)|null  $lesson
     */
    public function __construct(
        private readonly ?Closure $plan = null,
        private readonly ?Closure $lesson = null,
        private readonly string $planVersion = 'plan-builder-v2',
        private readonly string $lessonVersion = 'lesson-v4',
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

    public function planPromptVersion(): string
    {
        return $this->planVersion;
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
     * A lesson of the requested counts. Eight exchanges of a doctor's visit; the learner's lines
     * are short, their keys are substrings, the phrases are their cores and the vocabulary is
     * named inside the messages that carry its ids.
     *
     * @return array<string, mixed>
     */
    public static function lessonPayload(LessonRequest $request): array
    {
        $vocabulary = [
            ['v1', 'lower back', 'поясница', 'лоуэр бэк', 'the part of the back above the hips', 'chunk', 'close-up of a person\'s lower back, hand pressed against it'],
            ['v2', 'fever', 'температура', 'фивер', 'a body temperature higher than normal', 'word', 'digital thermometer showing a high temperature'],
            ['v3', 'sharp', 'острая', 'шарп', 'sudden and strong, like a cut', 'word', null],
            ['v4', 'prescription', 'рецепт', 'прескрипшн', 'a doctor\'s written order for medicine', 'word', 'a prescription form on a doctor\'s desk'],
            ['v5', 'painkiller', 'обезболивающее', 'пэйнкилер', 'a medicine that reduces pain', 'word', 'a box of painkiller tablets on a table'],
            ['v6', 'stretch', 'растяжка', 'стретч', 'to extend a muscle gently', 'word', 'a person stretching on a mat'],
            ['v7', 'numbness', 'онемение', 'намнесс', 'a loss of feeling in a part of the body', 'word', null],
            ['v8', 'appointment', 'приём', 'эпойнтмент', 'an arranged time to see a doctor', 'word', 'a calendar page with a marked day'],
            ['v9', 'symptom', 'симптом', 'симптом', 'a sign that shows an illness', 'word', null],
            ['v10', 'heating pad', 'грелка', 'хитинг пэд', 'a warm pad put on a painful place', 'chunk', 'electric heating pad lying on a sofa'],
        ];
        $exchanges = [
            ['A', 'Where exactly does it hurt: the upper back or the lower back?', 'Где именно болит: верх спины или поясница?', 'It hurts in his lower back.', 'Болит в пояснице.', 'lower back', ['His lower back hurts.'], ['p1'], [], ['v1'],
                'Which part does the doctor ask about?', 'О какой части спины спрашивает врач?', ['The upper or lower back', 'The neck', 'The knees'], 0, 'Врач спрашивает про верх спины или поясницу.'],
            ['A', 'Did it start today, or earlier this week?', 'Началось сегодня или раньше на этой неделе?', 'It started three days ago.', 'Началось три дня назад.', 'three days ago', ['Three days ago.'], ['p2'], [], [],
                'When does the doctor ask it started?', 'Когда, по словам врача, это могло начаться?', ['Today or earlier this week', 'Last month', 'A year ago'], 0, 'Врач спрашивает про сегодня или начало недели.'],
            ['A', 'Is the pain sharp, or more of a dull ache?', 'Боль острая или скорее тупая?', 'It is sharp when he bends.', 'Острая, когда он наклоняется.', 'sharp when he bends', ['Sharp when he bends.'], ['p3'], [], ['v3']],
            ['A', 'Does he have a fever or any numbness in his legs?', 'У него есть температура или онемение в ногах?', 'No, he has no fever.', 'Нет, температуры нет.', 'no fever', ['No fever.'], ['p4'], ['v2'], ['v2']],
            ['A', 'This looks like a muscle strain, not a disc problem.', 'Похоже на растяжение мышцы, а не проблему с диском.', 'That is a relief, thank you.', 'Это облегчение, спасибо.', 'a relief', ['Good to hear.'], ['p5'], [], []],
            ['A', 'Give him a painkiller twice a day after meals.', 'Давайте ему обезболивающее два раза в день после еды.', 'Twice a day after meals, understood.', 'Два раза в день после еды, понятно.', 'after meals', ['After meals, okay.'], ['p6'], [], ['v5']],
            ['B', 'Should he avoid sports for now?', 'Ему пока избегать спорта?', 'Yes, no sports for two weeks.', 'Да, никакого спорта две недели.', 'avoid sports', ['No sports for now?'], ['p7'], [], []],
            ['B', 'Do we need a follow-up appointment?', 'Нам нужен повторный приём?', 'Come back if it is not better in a week.', 'Приходите, если через неделю не станет лучше.', 'follow-up appointment', ['Do we come back?'], ['p8'], [], ['v8']],
        ];
        $questionDefaults = ['What does the doctor say?', 'Что говорит врач?', ['Muscle strain', 'A disc problem', 'A broken bone'], 0, 'Врач говорит про растяжение мышцы.'];

        $dialogue = [];
        for ($i = 0; $i < $request->dialogueCount; $i++) {
            $row = $exchanges[$i % count($exchanges)];
            $step = $i + 1;
            $suffix = $i >= count($exchanges) ? ' '.$step : '';
            $partner = ['speaker' => 'A', 'role_target' => 'Doctor', 'role_native' => 'Врач', 'text_target' => $row[1].$suffix, 'text_native' => $row[2], 'phrase_ids' => [], 'vocabulary_ids' => $row[9]];
            $learner = ['speaker' => 'B', 'role_target' => 'Parent', 'role_native' => 'Родитель', 'text_target' => $row[3], 'text_native' => $row[4], 'pronunciation_native' => 'ит хёртс', 'speaking_key' => $row[5], 'simplified_variants' => $row[6], 'phrase_ids' => $i < $request->phrasesCount ? $row[7] : [], 'vocabulary_ids' => $row[8]];
            if ($row[0] === 'B') {
                // The learner opens: their line first, the partner's answer second.
                $learner = ['speaker' => 'B', 'role_target' => 'Parent', 'role_native' => 'Родитель', 'text_target' => $row[1], 'text_native' => $row[2], 'pronunciation_native' => 'шуд хи', 'speaking_key' => $row[5], 'simplified_variants' => $row[6], 'phrase_ids' => $i < $request->phrasesCount ? $row[7] : [], 'vocabulary_ids' => $row[9]];
                $partner = ['speaker' => 'A', 'role_target' => 'Doctor', 'role_native' => 'Врач', 'text_target' => $row[3].$suffix, 'text_native' => $row[4], 'phrase_ids' => [], 'vocabulary_ids' => $row[8]];
                $messages = [$learner, $partner];
            } else {
                $messages = [$partner, $learner];
            }
            $q = isset($row[10]) ? array_slice($row, 10) : $questionDefaults;
            $dialogue[] = [
                'step' => $step,
                'initiator' => $row[0],
                'messages' => $messages,
                'question' => [
                    'text_target' => $q[0],
                    'text_native' => $q[1],
                    'options' => array_map(static fn (string $t): array => ['text_target' => $t, 'text_native' => $t], $q[2]),
                    'correct_option_index' => $q[3],
                    'explanation_native' => $q[4],
                ],
            ];
        }

        $phrases = [];
        for ($i = 0; $i < $request->phrasesCount; $i++) {
            $row = $exchanges[$i % count($exchanges)];
            $text = $row[0] === 'B' ? $row[1] : $row[3];
            $phrases[] = [
                'id' => 'p'.($i + 1),
                'text_target' => $i < count($exchanges) ? self::core($text) : $text.' '.($i + 1),
                'text_native' => $row[0] === 'B' ? $row[2] : $row[4],
                'pronunciation_native' => 'фраза '.($i + 1),
            ];
        }

        $items = [];
        for ($i = 0; $i < $request->vocabularyCount; $i++) {
            $row = $vocabulary[$i % count($vocabulary)];
            $items[] = [
                'id' => 'v'.($i + 1),
                'term_target' => $i < count($vocabulary) ? $row[1] : $row[1].' '.($i + 1),
                'translation_native' => $row[2],
                'pronunciation_native' => $row[3],
                'definition_target' => $row[4],
                'kind' => $row[5],
                'image_prompt' => $row[6],
            ];
        }

        return [
            'topic' => [
                'title_target' => $request->topic,
                'title_native' => $request->topic,
                'description_target' => 'Describing back pain to a doctor and understanding the instructions.',
                'description_native' => 'Описать боль в спине врачу и понять назначения.',
            ],
            'learner_role' => ['role_target' => 'Parent', 'role_native' => 'Родитель'],
            'role_gender' => 'male',
            'dialogue' => $dialogue,
            'phrases' => $phrases,
            'vocabulary' => $items,
        ];
    }

    /** «Yes, no sports for two weeks.» → «No sports for two weeks.» — the prompt's own glue rule. */
    private static function core(string $message): string
    {
        $stripped = (string) preg_replace('/^(Yes|No|Okay|Well|So),\s*/i', '', $message);

        return $stripped === $message ? $message : mb_strtoupper(mb_substr($stripped, 0, 1)).mb_substr($stripped, 1);
    }
}

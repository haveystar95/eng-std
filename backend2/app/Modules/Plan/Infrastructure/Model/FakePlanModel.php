<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Model;

use App\Modules\Plan\Application\Dto\ConversationAgentRequest;
use App\Modules\Plan\Application\Dto\DialogueRequest;
use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Dto\NativeSeamJudgeRequest;
use App\Modules\Plan\Application\Dto\PlanLineRepairRequest;
use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Application\Dto\SlotJudgeRequest;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Application\Service\LessonRequests;
use App\Modules\Plan\Domain\Blueprint\SurvivalSet;
use App\Modules\Plan\Domain\Check\Dialogue\LearnerLine;
use App\Modules\Plan\Domain\Exception\ModelAnswerOffSchema;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Lesson\LessonCard;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\LessonRoles;
use App\Modules\Plan\Domain\Lesson\Skeleton;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use Closure;

/**
 * Deterministic answers without a network — `PLAN_MODEL_DRIVER=fake` and the whole test suite.
 *
 * The default answers are a valid plan of exactly SCENES_COUNT scenes, each with its survival set, and a clean day in two
 * stages — a skeleton and a dialogue that break no rule of their checks and assemble into THE FIXTURE LESSON
 * ({@see lessonPayload()}): the day every plan test deals from. The two stages are read off that lesson ({@see
 * skeletonPayload()}, {@see dialoguePayload()}), so the fixture has one source. The seam judge reads every native sentence as
 * fine and finds no reply naming a filler; a repair gives the card back as it was; a line repair cuts the line to its limit; the slot judge accepts every attempt,
 * taking what was heard for the slot. A goal containing «unclear» comes back `unclear`, the way the prompt answers a
 * non-situation. A test that wants a BROKEN answer hands in its own closure for any call — or a closure that throws, for a
 * model that does not answer.
 */
final class FakePlanModel implements PlanModelPort
{
    public const MODEL = 'fake-plan-model';

    /** The version of every prompt, as the files in `current/` name them. */
    public const PLAN_VERSION = 'plan-builder-v2.1';

    public const SKELETON_VERSION = 'lesson_skeleton.v1.1';

    public const DIALOGUE_VERSION = 'lesson_dialogue.v1.1';

    public const REPAIR_VERSION = 'lesson_card_repair.v1.5';

    public const JUDGE_VERSION = 'lesson_seam_judge.v1.3';

    /** How many times each call was made — the assertion behind «one repeat, not two». */
    public int $planCalls = 0;

    public int $planLineCalls = 0;

    public int $skeletonCalls = 0;

    public int $dialogueCalls = 0;

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

    /** @var list<PlanLineRepairRequest> */
    public array $planLineRequests = [];

    /** @var list<LessonRequest> */
    public array $skeletonRequests = [];

    /** @var list<DialogueRequest> */
    public array $dialogueRequests = [];

    /**
     * @param  (Closure(PlanRequest, int): array<string, mixed>)|null  $plan  attempt number is the second argument
     * @param  (Closure(LessonRequest, int): array<string, mixed>)|null  $skeleton
     * @param  (Closure(DialogueRequest, int): array<string, mixed>)|null  $dialogue
     * @param  (Closure(LessonCardRepairRequest, int): array<string, mixed>)|null  $repair  the default returns the card as written
     * @param  (Closure(NativeSeamJudgeRequest, int): array<string, mixed>)|null  $judge  the default reads every sentence as fine
     * @param  (Closure(SlotJudgeRequest, int): array<string, mixed>)|null  $slotJudge  the default accepts; a closure that throws is a silent model
     * @param  (Closure(ConversationAgentRequest, int): array<string, mixed>)|null  $conversation  the default plays the role by the book; a closure that throws is a silent agent
     * @param  (Closure(PlanLineRepairRequest, int): array<string, mixed>)|null  $planLine  the default cuts the line to its limit
     * @param  (Closure(LessonRequest, int): array<string, mixed>)|null  $lesson  a day written as ONE lesson (the shape a scene stores),
     *                                                                            split into its two stages by {@see stagesOf()} — for a test
     *                                                                            that reads a day, not one that builds it; the number is the
     *                                                                            build's (its skeleton call's), the same for both halves;
     *                                                                            `$skeleton` and `$dialogue` go first
     */
    public function __construct(
        private readonly ?Closure $plan = null,
        private readonly ?Closure $skeleton = null,
        private readonly ?Closure $dialogue = null,
        private readonly ?Closure $repair = null,
        private readonly ?Closure $judge = null,
        private readonly ?Closure $slotJudge = null,
        private readonly ?Closure $conversation = null,
        private readonly ?Closure $planLine = null,
        private readonly ?Closure $lesson = null,
    ) {}

    public function buildPlan(PlanRequest $request): ModelReply
    {
        $this->planCalls++;
        $this->planRequests[] = $request;
        $payload = $this->plan !== null ? ($this->plan)($request, $this->planCalls) : self::planPayload($request);

        return new ModelReply($payload, self::PLAN_VERSION, self::MODEL, 1200, 800, '0.000000', 5, '');
    }

    public function repairPlanLine(PlanLineRepairRequest $request): ModelReply
    {
        $this->planLineCalls++;
        $this->planLineRequests[] = $request;
        $payload = $this->planLine !== null ? ($this->planLine)($request, $this->planLineCalls) : ['line' => mb_substr($request->line, 0, $request->limit)];

        return new ModelReply($payload, 'plan_line_repair.v1', self::MODEL, 200, 20, '0.000000', 1, '');
    }

    public function buildSkeleton(LessonRequest $request): ModelReply
    {
        $this->skeletonCalls++;
        $this->skeletonRequests[] = $request;
        $payload = match (true) {
            $this->skeleton !== null => ($this->skeleton)($request, $this->skeletonCalls),
            $this->lesson !== null => self::stagesOf(($this->lesson)($request, $this->skeletonCalls), $request)['skeleton'],
            default => self::skeletonPayload($request),
        };

        return new ModelReply($payload, self::SKELETON_VERSION, self::MODEL, 2000, 1500, '0.000000', 4, '');
    }

    public function buildDialogue(DialogueRequest $request): ModelReply
    {
        $this->dialogueCalls++;
        $this->dialogueRequests[] = $request;
        $payload = match (true) {
            $this->dialogue !== null => ($this->dialogue)($request, $this->dialogueCalls),
            // Both halves of one build see the same call number: the day's, counted by its skeletons.
            $this->lesson !== null => self::spoken(self::stagesOf(($this->lesson)($request->lesson, $this->skeletonCalls), $request->lesson), $request->skeleton),
            default => self::dialoguePayload($request),
        };

        return new ModelReply($payload, self::DIALOGUE_VERSION, self::MODEL, 3000, 2000, '0.000000', 5, '');
    }

    public function repairLessonCard(LessonCardRepairRequest $request): ModelReply
    {
        $this->repairCalls++;
        $this->repairRequests[] = $request;
        $payload = $this->repair !== null ? ($this->repair)($request, $this->repairCalls) : ['card' => $request->card];

        return new ModelReply($payload, self::REPAIR_VERSION, self::MODEL, 900, 300, '0.000000', 3, '');
    }

    public function judgeNativeSeams(NativeSeamJudgeRequest $request): ModelReply
    {
        $this->judgeCalls++;
        $this->judgeRequests[] = $request;
        $payload = $this->judge !== null
            ? ($this->judge)($request, $this->judgeCalls)
            : [
                'verdicts' => array_map(static fn (string $id): array => ['id' => $id, 'reads' => true], $request->ids()),
                'replies' => array_map(static fn (string $id): array => ['id' => $id, 'names_a_value' => false], $request->replyIds()),
            ];

        return new ModelReply($payload, self::JUDGE_VERSION, self::MODEL, 400, 120, '0.000000', 2, '');
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
     * The role, played deterministically (наряд CONV-1): it asks, opens the door it is led to, says a rescue in other words
     * (наряд CONV-2, п. 4б), greets as the person of its scene, says goodbye when the server closes its scene or the turns
     * run out (наряд FIX-4 §4) — enough for the whole suite to walk a talk end to end without a network. Each reply of a
     * talk is a line of its own (the n-th call's), so the guard against a line said twice never fires on the fake.
     */
    public function conversationTurn(ConversationAgentRequest $request): ModelReply
    {
        $this->conversationCalls++;
        $this->conversationRequests[] = $request;
        $payload = $this->conversation !== null
            ? ($this->conversation)($request, $this->conversationCalls)
            : self::conversationPayload($request, $this->conversationCalls);

        return new ModelReply($payload, 'conversation_agent.v3.4', self::MODEL, 900, 90, '0.000000', 2, '', callId: sprintf('01J8FAKEM0DE1CA11%09d', $this->conversationCalls));
    }

    /** @var list<array{0: string, 1: string}> the fake role's lines, one per move, none of them said twice in a talk */
    private const ROLE_LINES = [
        ['And what brings you in today?', 'Что вас беспокоит?'],
        ['I see. How long has this been going on?', 'Понятно. Как давно это продолжается?'],
        ['Thank you. Is there anything else I should know?', 'Спасибо. Есть ещё что-то, что мне нужно знать?'],
        ['All right. Does anything make it better or worse?', 'Хорошо. Что-нибудь облегчает или ухудшает это?'],
        ['Good. Have you taken any medicine for it?', 'Хорошо. Вы принимали какое-нибудь лекарство?'],
        ['Okay. Let me write that down for the doctor.', 'Хорошо. Я запишу это для врача.'],
        ['Noted. Do you have any questions for me?', 'Записала. У вас есть вопросы ко мне?'],
        ['Fine. Please take a seat over there.', 'Хорошо. Присядьте, пожалуйста, вон там.'],
        ['Right. Tell me how he is sleeping.', 'Ясно. Расскажите, как он спит.'],
        ['Understood. The doctor will call you soon.', 'Поняла. Врач скоро вас позовёт.'],
        ['One moment, please. I am checking the schedule.', 'Минутку, пожалуйста. Я смотрю расписание.'],
    ];

    /**
     * The fake role's answer to one request — `$call` is the call's number in the test (a test's own closure may pass its
     * own): the n-th line of the role, so no line is said twice in a talk; without it, by the lines of the scene so far.
     *
     * @return array<string, mixed>
     */
    public static function conversationPayload(ConversationAgentRequest $request, ?int $call = null): array
    {
        $ending = $request->turnsLeft <= 0;
        $greeting = $request->turn === 'start';
        $said = $call ?? count(array_filter($request->history, static fn (array $h): bool => $h['speaker'] === 'you'));
        $line = self::ROLE_LINES[$said % count(self::ROLE_LINES)];
        $role = trim($request->roleTarget) === '' ? 'person' : mb_strtolower($request->roleTarget);

        return [
            'reply_target' => match (true) {
                $request->sceneEnd && ! $ending => "Thank you, that is all here. The {$role} says goodbye.",
                $ending => 'Take care. See you next week.',
                $request->turn === 'rescue' => 'What is wrong today?',
                // The next scene's role meets the learner as somebody new — in other words than the first one's.
                $greeting && $request->earlier !== [] => "Good day, I am the {$role} you will see next. ".$line[0],
                $greeting => "Hello, I am your {$role} today. ".$line[0],
                default => $line[0],
            },
            'reply_native' => match (true) {
                $request->sceneEnd && ! $ending => 'Спасибо, здесь всё. До свидания.',
                $ending => 'Берегите себя. До встречи на следующей неделе.',
                $request->turn === 'rescue' => 'Что сегодня не так?',
                $greeting && $request->earlier !== [] => 'Добрый день. '.$line[1],
                $greeting => 'Здравствуйте. '.$line[1],
                default => $line[1],
            },
            'understood' => $request->turn === 'said' ? true : null,
            'off_topic' => false,
            'opens' => $ending || $request->sceneEnd ? null : $request->leadTo,
            'end' => $ending ? 'natural' : 'no',
        ];
    }

    public function planPromptVersion(): string
    {
        return self::PLAN_VERSION;
    }

    public function skeletonPromptVersion(): string
    {
        return self::SKELETON_VERSION;
    }

    public function dialoguePromptVersion(): string
    {
        return self::DIALOGUE_VERSION;
    }

    public function lessonPromptVersion(): string
    {
        return self::SKELETON_VERSION.'+'.self::DIALOGUE_VERSION;
    }

    public function repairPromptVersion(): string
    {
        return self::REPAIR_VERSION;
    }

    public function judgePromptVersion(): string
    {
        return self::JUDGE_VERSION;
    }

    public function slotJudgePromptVersion(): string
    {
        return 'slot_judge.v3';
    }

    public function conversationPromptVersion(): string
    {
        return 'conversation_agent.v3.4';
    }

    /** The roles of the fake's plan and of its clean lesson — the plan's parent, the doctor of the visit. */
    public static function roles(): LessonRoles
    {
        return new LessonRoles('Parent', 'Родитель', 'Doctor', 'Врач');
    }

    /**
     * THE SURVIVAL SET OF EVERY SCENE OF THE FAKE'S PLAN (`plan-builder-v2.1`) — the doctor's visit the fixture lesson is: one
     * item a frame, the two asks of `p6` two items of one pattern.
     */
    public static function survival(): SurvivalSet
    {
        return SurvivalSet::fromModel(
            [
                'say where it hurts — slot: the part of the body',
                'say when it started — slot: the time',
                'say what the pain is like — slot: the kind of pain',
                'answer whether he has a fever — slot: none',
                'confirm how he will rest — slot: the place or time',
                'ask whether a test is needed — slot: the test',
                'ask whether another visit is needed — slot: the visit',
            ],
            [
                'asks where exactly it hurts',
                'asks when it started',
                'asks what the pain is like and whether there is a fever',
                'says the likely cause and gives instructions',
                'says what is needed next',
            ],
        );
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
        $survival = self::survival();
        $offset = count($request->existingScenes);
        $scenes = [];
        for ($i = 0; $i < $request->scenesCount; $i++) {
            $row = $titles[($offset + $i) % count($titles)];
            $order = $offset + $i + 1;
            $scenes[] = [
                'order' => $order,
                'kind' => $i < 3 ? 'situation' : 'variant',
                'priority' => $offset > 0 ? $order : ($i === 1 || $request->scenesCount === 1 ? 1 : ($i === 0 ? 2 : $i + 1)),
                'must_say' => $survival->sayLines(),
                'must_understand' => $survival->mustUnderstand,
                'title_native' => $row[0].($offset > 0 ? ' '.$order : ''),
                'title_target' => $row[1],
                'teaches_native' => $row[2],
                'goals_native' => ['описать, где болит', 'ответить на вопросы', 'понять назначения'],
                'learner_role_target' => 'Parent',
                'learner_role_native' => 'Родитель',
                'partner_role_target' => $row[3],
                'partner_role_native' => $row[4],
                'topic_description' => "Situation: {$row[1]} at a local clinic with a child who has back pain, first visit; the parent wants to understand what to do next.\n"
                    ."Learner: Parent. Partner: {$row[3]}.\n"
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
     * THE LEARNER'S OWN WORDS OF THE FIXTURE DAY (наряд GEN-4c) — the goal of a parent who names what the day's fillers say:
     * the sharp pain (p3), the X-ray, the follow-up visit and the note for school (p6). The day's words of those fillers
     * («sharp», «X-ray», «follow-up appointment», «sick note») are words of the learner's details, not placeholder words
     * (`vocab.from_placeholder`): the clean day stays clean for this learner — for a learner who said none of it, those four
     * words are placeholders.
     */
    public const LEARNER_GOAL = 'Иду к врачу с ребёнком: у сына острая боль в спине. Хочу понять, нужно ли сделать рентген, прийти на повторный приём и взять справку для школы.';

    /**
     * A day's inputs as the fixture reads them — the doctor's visit of the fake's plan, day `count($earlier) + 1` of the story,
     * for the learner of {@see LEARNER_GOAL}.
     */
    public static function lessonRequest(string $topic = 'Приём у врача', EarlierDays $earlier = new EarlierDays, string $sceneId = ''): LessonRequest
    {
        return new LessonRequest(
            topic: $topic,
            topicDescription: LessonRequests::topicDescription('Situation: Consultation at a local clinic with a child who has back pain.', self::LEARNER_GOAL),
            survival: self::survival(),
            targetLanguage: 'English',
            nativeLanguage: 'Russian',
            level: PlanLevel::Beginner,
            learnerGender: null,
            vocabularyMin: 8,
            vocabularyMax: 12,
            roles: self::roles(),
            earlierDays: $earlier,
            targetLangCode: 'en',
            nativeLangCode: 'ru',
            sceneId: $sceneId,
        );
    }

    /**
     * THE SKELETON OF THE FIXTURE DAY, read off its lesson ({@see lessonPayload()}, {@see stagesOf()}).
     *
     * @return array<string, mixed>
     */
    public static function skeletonPayload(LessonRequest $request): array
    {
        return self::stagesOf(self::lessonPayload($request), $request)['skeleton'];
    }

    /**
     * THE DIALOGUE OF THE FIXTURE DAY, read off its lesson ({@see lessonPayload()}, {@see stagesOf()}) and said over the
     * skeleton the request carries ({@see spoken()}).
     *
     * @return array<string, mixed>
     */
    public static function dialoguePayload(DialogueRequest $request): array
    {
        return self::spoken(self::stagesOf(self::lessonPayload($request->lesson), $request->lesson), $request->skeleton);
    }

    /**
     * A DIALOGUE SAID OVER THE SKELETON IT IS GIVEN — as a model writes it from the skeleton the request carries: a frame or a
     * partner line a repair of the skeleton changed is said in its new words wherever the lesson said it
     * ({@see LessonCard::replace()}); the rest as the lesson wrote it. A lesson a test broke past reading goes as it is.
     *
     * @param  array{skeleton: array<string, mixed>, dialogue: array<string, mixed>}  $stages
     * @return array<string, mixed>
     */
    private static function spoken(array $stages, Skeleton $given): array
    {
        $parser = new LessonParser;
        try {
            $written = $parser->skeleton($stages['skeleton']);
            $said = $parser->dialogue($stages['dialogue']);
        } catch (ModelAnswerOffSchema) {
            return $stages['dialogue'];
        }
        foreach ($given->frames as $frame) {
            $was = $written->frame($frame->id());
            $card = LessonCard::at($frame->id());
            if ($was !== null && $card !== null && $said !== null && $was->toArray() !== $frame->toArray()) {
                $said = $card->replace($written, $said, $frame)[1];
            }
        }
        foreach ($given->partnerLines as $line) {
            $was = $written->partnerLine($line->id);
            $card = LessonCard::at($line->id);
            if ($was !== null && $card !== null && $said !== null && [$was->textTarget, $was->textNative] !== [$line->textTarget, $line->textNative]) {
                $said = $card->replace($written, $said, $line)[1];
            }
        }

        return $said?->toArray() ?? $stages['dialogue'];
    }

    /**
     * A DAY WRITTEN AS ONE LESSON, SPLIT INTO THE TWO STAGES A MODEL WRITES — what {@see \App\Modules\Plan\Domain\Lesson\LessonAssembler} puts
     * back together:
     *
     *  - the SKELETON: the lesson's topic, role and frames; each frame serves the next items of `must_say`, one for every
     *    exchange that stands on it (`p6` said twice serves two); every exchange but the rescue gives a partner line (`a1`…,
     *    A's message), which delivers an item of `must_understand` — spread over the lines in order, the first line the
     *    first item, the last the last — and pairs with the item its exchange's learner line says; the words, `used_in`
     *    naming a partner line by its own id where the lesson names the message (`A5` → the line exchange 5 carries);
     *  - the DIALOGUE: every exchange with the line it carries and the item it delivers (none for the rescue), and the
     *    listening as it is.
     *
     * No meaning, no check: a lesson a test breaks comes out as broken stages.
     *
     * @param  array<string, mixed>  $lesson
     * @return array{skeleton: array<string, mixed>, dialogue: array<string, mixed>}
     */
    public static function stagesOf(array $lesson, LessonRequest $request): array
    {
        /** @var list<array<string, mixed>> $exchanges */
        $exchanges = $lesson['dialogue'];
        $carrying = array_values(array_filter($exchanges, static fn (array $e): bool => $e['kind'] !== 'rescue' && self::said($e, 'A') !== null));
        $items = max(1, count($request->survival->mustUnderstand));

        $says = [];
        $next = 1;
        foreach ($exchanges as $e) {
            $frame = self::said($e, 'B')['phrase_id'] ?? null;
            if ($e['kind'] !== 'rescue' && is_string($frame)) {
                $says[$e['step']] = [$frame, $next++];
            }
        }

        $lines = [];
        $ids = [];
        $dialogue = [];
        foreach ($exchanges as $e) {
            $index = array_search($e, $carrying, true);
            $id = $index === false ? null : 'a'.($index + 1);
            $item = $index === false ? null : (int) ceil(($index + 1) * $items / count($carrying));
            if ($id !== null) {
                $partner = self::said($e, 'A') ?? [];
                $ids['A'.$e['step']] = $id;
                $lines[] = [
                    'id' => $id,
                    'must_understand' => $item,
                    'kind' => str_ends_with(trim((string) ($partner['text_target'] ?? '')), '?') ? 'question' : 'statement',
                    'pairs_with' => isset($says[$e['step']]) ? [$says[$e['step']][1]] : [],
                    'text_target' => $partner['text_target'] ?? '',
                    'text_native' => $partner['text_native'] ?? '',
                ];
            }
            $dialogue[] = [
                'step' => $e['step'],
                'kind' => $e['kind'],
                'initiator' => $e['initiator'],
                'must_understand' => $item,
                'partner_line' => $id,
                'messages' => $e['messages'],
                'check' => $e['check'],
            ];
        }

        $serves = [];
        foreach ($says as [$frame, $number]) {
            $serves[$frame][] = $number;
        }

        return [
            'skeleton' => [
                'topic' => $lesson['topic'],
                'learner_role' => $lesson['learner_role'],
                'role_gender' => $lesson['role_gender'],
                'phrases' => array_map(static fn (array $p): array => [
                    'id' => $p['id'], 'kind' => $p['kind'], 'must_say' => $serves[$p['id']] ?? [],
                    'frame_target' => $p['frame_target'], 'frame_native' => $p['frame_native'],
                    'pronunciation_native' => $p['pronunciation_native'], 'slot' => $p['slot'],
                ], $lesson['phrases']),
                'partner_lines' => $lines,
                'vocabulary' => array_map(static fn (array $v): array => [
                    ...$v,
                    'used_in' => array_values(array_filter(array_map(
                        static fn (string $ref): ?string => str_starts_with($ref, 'A') ? ($ids[$ref] ?? null) : $ref,
                        $v['used_in'],
                    ))),
                ], $lesson['vocabulary']),
            ],
            'dialogue' => ['dialogue' => $dialogue, 'listening' => $lesson['listening']],
        ];
    }

    /**
     * The message of `$speaker` in an exchange of a lesson, or null.
     *
     * @param  array<string, mixed>  $exchange
     * @return array<string, mixed>|null
     */
    private static function said(array $exchange, string $speaker): ?array
    {
        foreach ($exchange['messages'] as $message) {
            if ($message['speaker'] === $speaker) {
                return $message;
            }
        }

        return null;
    }

    /**
     * THE FIXTURE LESSON (`lesson_day` shape — what a scene stores and every reader deals from): a doctor's visit with a
     * child's back pain, what the fake's two stages assemble into. The fixture every plan test deals its days from, and the
     * baseline a test breaks one rule of.
     *
     * Eight exchanges: five answers, one rescue after the long partner line of exchange 5, two asks that say the SAME frame
     * with two different fillers (`p6`, «Do we need ___?», exchanges 7 and 8 — two items of the survival set of one pattern).
     * Six frames for seven answer/ask exchanges, one of them without a slot; eight vocabulary items; three listening
     * questions, the first asking the learner's own value. The right answers stand at varied places, as a model would put
     * them before the server shuffles. No partner line names a filler of the learner's (наряд GEN-4).
     *
     * A LATER DAY OF THE STORY (EARLIER_DAYS not empty, наряд GEN-3) keeps what it learned: the same visit, told with every
     * word and every frame of the day marked by the day's number ({@see laterDay()}) — no word or frame of an earlier day
     * comes back.
     *
     * @return array<string, mixed>
     */
    public static function lessonPayload(LessonRequest $request): array
    {
        $payload = self::firstDayPayload($request->topic);
        $day = count($request->earlierDays->days) + 1;

        return $day === 1 ? $payload : self::laterDay($payload, $day);
    }

    /** @return array<string, mixed> */
    private static function firstDayPayload(string $topic): array
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
                $a('Where does it hurt: in his upper back or lower down?', 'Где болит: вверху спины или ниже?'),
                $b('p1', 'lower back', 'It hurts in his lower back.', 'У него болит поясница.', 'ит хёртс ин хиз лоуэр бэк', 'It hurts', ['His lower back hurts.']),
            ], $check('Which two places does the doctor ask about?', 'О каких двух местах спрашивает врач?', [
                ['The top or the bottom of the back', 'Верх или низ спины'], ['The neck or the head', 'Шея или голова'], ['The knees or the feet', 'Колени или ступни'],
            ], 0, 'Врач спрашивает, болит вверху спины или ниже.')],
            ['answer', 'A', [
                $a('Did it start today, or earlier this week?', 'Началось сегодня или раньше на этой неделе?'),
                $b('p2', 'three days ago', 'It started three days ago.', 'Началось три дня назад.', 'ит стартид сри дэйз эгоу', 'It started', ['Three days ago.']),
            ], $check('Which two times does the doctor mention?', 'Какие два варианта времени называет врач?', [
                ['Last month or last year', 'В прошлом месяце или году'], ['Now or a few days before', 'Недавно, в последние дни'], ['At night or in the morning', 'Ночью или утром'],
            ], 1, 'Врач спрашивает про сегодня или начало недели.')],
            ['answer', 'A', [
                $a('Is the pain sudden, or more like a slow ache?', 'Боль резкая или скорее тянущая?'),
                $b('p3', 'sharp', 'The pain is sharp when he bends.', 'Боль острая, когда он наклоняется.', 'зэ пэйн из шарп уэн хи бэндз', 'when he bends', ['It is sharp when he bends.']),
            ], $check('What does the doctor want to know about the pain?', 'Что врач хочет узнать о боли?', [
                ['How long it lasts', 'Сколько она длится'], ['Where it started', 'Где она началась'], ['What kind of pain it is', 'Какая это боль'],
            ], 2, 'Врач спрашивает, резкая боль или тянущая.')],
            ['answer', 'A', [
                $a('Does he have a fever?', 'У него есть температура?'),
                $b('p4', null, 'No, he doesn\'t have a fever.', 'Нет, температуры у него нет.', 'ноу хи дазнт хэв э фивер', 'have a fever', ['No fever.']),
            ], $check('What symptom does the doctor ask about?', 'О каком симптоме спрашивает врач?', [
                ['A high temperature', 'Высокая температура'], ['A bad cough', 'Сильный кашель'], ['A skin rash', 'Сыпь на коже'],
            ], 0, 'Врач спрашивает про температуру.')],
            ['answer', 'A', [
                $a('It looks like a muscle strain, so he should rest and use a heating pad.', 'Похоже на растяжение мышцы, так что ему нужен покой и грелка.'),
                $b('p5', 'at home', 'Okay, he will rest at home.', 'Хорошо, он будет отдыхать дома.', 'оукей хи уил рэст эт хоум', 'will rest', ['He will rest.']),
            ], $check('What does the doctor think the problem is?', 'Что, по мнению врача, случилось?', [
                ['A broken bone', 'Перелом кости'], ['A pulled muscle', 'Растянутая мышца'], ['A bad cold', 'Сильная простуда'],
            ], 1, 'Врач говорит, что это растяжение мышцы.')],
            ['rescue', 'B', [
                $b(null, null, 'Sorry, could you say that more slowly?', 'Простите, можно помедленнее?', 'сори куд ю сэй зэт мор слоули', 'more slowly', ['More slowly, please?']),
                $a('He should rest and use a heating pad.', 'Ему нужен покой и грелка.'),
            ], $check('What should they use at home?', 'Что нужно использовать дома?', [
                ['Ice on the neck and shoulders', 'Лёд на шею и плечи'], ['A cream for the knees', 'Мазь для коленей'], ['Something warm on the back', 'Что-то тёплое на спину'],
            ], 2, 'Врач советует грелку.')],
            ['ask', 'B', [
                $b('p6', 'an X-ray', 'Do we need an X-ray?', 'Нам нужно сделать рентген?', 'ду уи нид эн экс-рэй', 'Do we need', ['Is an X-ray needed?']),
                $a('No, you do not need that for a muscle strain.', 'Нет, при растяжении мышцы это не нужно.'),
            ], $check('Why is an X-ray not needed?', 'Почему рентген не нужен?', [
                ['The bone is broken', 'Сломана кость'], ['It is only a pulled muscle', 'Это просто растяжение мышцы'], ['The clinic is closed', 'Клиника закрыта'],
            ], 1, 'При растяжении мышцы рентген не нужен.')],
            ['ask', 'B', [
                $b('p6', 'a follow-up appointment', 'Do we need a follow-up appointment?', 'Нам нужно прийти на повторный приём?', 'ду уи нид э фоллоу-ап эпойнтмент', 'Do we need', ['Should we come back?']),
                $a('No, only if it still hurts after one week.', 'Нет, только если через неделю ещё будет болеть.'),
            ], $check('When should they come back?', 'Когда нужно прийти снова?', [
                ['Tomorrow morning before lunch', 'Завтра утром до обеда'], ['In a year for a check-up', 'Через год на осмотр'], ['If the pain does not stop in seven days', 'Если боль не пройдёт через семь дней'],
            ], 2, 'Прийти снова, если через неделю ещё болит.')],
        ];

        $dialogue = [];
        foreach ($exchanges as $i => [$kind, $initiator, $messages, $question]) {
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
            ['lower back', 'поясница', 'лоуэр бэк', 'the part of the back above the hips', 'chunk', 'a parent pressing a hand on a child\'s lower back', ['p1']],
            ['sharp', 'острая', 'шарп', 'sudden and strong, like a cut', 'word', null, ['p3']],
            ['fever', 'температура', 'фивер', 'a body temperature higher than normal', 'word', 'a digital thermometer showing a high temperature', ['p4', 'A4']],
            ['muscle strain', 'растяжение мышцы', 'масл стрэйн', 'an injury to a muscle from stretching it too far', 'chunk', 'a physiotherapist touching a child\'s back muscle', ['A5', 'A7']],
            ['heating pad', 'грелка', 'хитинг пэд', 'a warm pad you put on a painful place', 'chunk', 'an electric heating pad on a sofa', ['A5']],
            ['X-ray', 'рентген', 'экс-рэй', 'a picture of the inside of the body', 'word', 'a doctor looking at a spine x-ray on a light box', ['p6']],
            ['follow-up appointment', 'повторный приём', 'фоллоу-ап эпойнтмент', 'a second visit to check how you are', 'chunk', 'a calendar page with a clinic visit marked', ['p6']],
            ['sick note', 'справка', 'сик ноут', 'a note from a doctor saying someone was ill', 'chunk', 'a doctor\'s note lying on a school desk', ['p6']],
        ];
        $items = [];
        foreach ($vocabulary as $i => $row) {
            $items[] = [
                'id' => 'v'.($i + 1),
                'term_target' => $row[0],
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
                'title_native' => $topic,
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
     * (frames, fillers, lines, variants, keys, the word itself), and every frame gets «-{day}» on its first word — in both
     * languages — and so does every line that stands on it, after its glue, in both languages. No word is added: the day
     * breaks what the first day breaks and nothing more.
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
                    $payload['dialogue'][$x]['messages'][$m]['speaking_key'] = $message['speaking_key'] === null ? null : $marked((string) $message['speaking_key']);
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
                // A key that opens the frame opens it marked too — a key is words of the line, as the line says them.
                $key = (string) ($payload['dialogue'][$x]['messages'][$m]['speaking_key'] ?? '');
                if ($key !== '' && mb_stripos(mb_substr($text, mb_strlen($glue)), $key) === 0) {
                    $payload['dialogue'][$x]['messages'][$m]['speaking_key'] = $firstWord($key);
                }
                // The native line is the native frame with its filler after a glue of its own: the frame's first word gets the mark.
                $native = (string) $message['text_native'];
                $core = LearnerLine::core($phrase, $phrase->filler($message['filler']), 'native');
                $glueNative = $core !== null && LearnerLine::says($native, $core)
                    ? mb_substr($native, 0, mb_strlen(FrameText::withoutEndMark($native)) - mb_strlen(FrameText::withoutEndMark($core)))
                    : '';
                $payload['dialogue'][$x]['messages'][$m]['text_native'] = $glueNative.$firstWord(mb_substr($native, mb_strlen($glueNative)));
            }
        }
        foreach ($payload['phrases'] as $i => $phrase) {
            $payload['phrases'][$i]['frame_target'] = $firstWord((string) $phrase['frame_target']);
            $payload['phrases'][$i]['frame_native'] = $firstWord((string) $phrase['frame_native']);
        }

        return $payload;
    }
}

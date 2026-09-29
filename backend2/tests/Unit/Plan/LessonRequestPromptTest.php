<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\ModelAnswer;
use App\Modules\Generation\Application\Dto\RenderedPrompt;
use App\Modules\Generation\Application\Port\ContentModelCatalog;
use App\Modules\Generation\Application\Port\ContentModelPort;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Plan\Application\Dto\DialogueRequest;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\NativeSeamJudgeRequest;
use App\Modules\Plan\Application\Dto\PlanLineRepairRequest;
use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Application\Service\LessonCardRepairer;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Lesson\LessonCard;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\LessonRoles;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Infrastructure\Model\ContentModelPlanBuilder;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Plan\Infrastructure\Model\PlanModelChoice;
use App\Modules\Plan\Infrastructure\Prompt\PlanPromptFiles;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * WHAT THE DAY'S STAGES AND THE REPAIR OF A CARD SEND (`lesson_skeleton.v1`, `lesson_dialogue.v1`,
 * `lesson_card_repair.v1.5`; наряд GEN-4): each stage's input in the form of its prompt's own TEST INPUT, byte for byte; a
 * stage asked again with what failed it; a repair's card with the skeleton, the dialogue and the neighbours it may read,
 * and the short story; a request built for the vendor's prompt cache — the rules and the schema first and the same between
 * two days, everything that varies after them; and every purpose on its own model.
 */

function lrpPrompts(): PlanPromptFiles
{
    return new PlanPromptFiles;
}

/** The TEST INPUT section of a stage's prompt file, as written — the form the server's input is held to. */
function lrpTestInput(string $prompt): string
{
    $raw = (string) file_get_contents(PlanPromptFiles::path($prompt));
    $marker = "\n---\n\nTEST INPUT\n\n";
    $rest = substr($raw, (int) strpos($raw, $marker) + strlen($marker));
    $end = strpos($rest, "\n---\n");

    return trim($end === false ? $rest : substr($rest, 0, $end));
}

/** The day the TEST INPUT of the skeleton orders — its topic, brief and learner's words read off the file itself. */
function lrpCanonRequest(): LessonRequest
{
    preg_match('/^TOPIC_DESCRIPTION: (.*?)\n\nSURVIVAL_SET:/sm', lrpTestInput('skeleton'), $brief);

    return new LessonRequest(
        topic: 'Опыт работы',
        topicDescription: $brief[1],
        survival: dayCanonSurvival(),
        targetLanguage: 'Romanian',
        nativeLanguage: 'Russian',
        level: PlanLevel::Beginner,
        learnerGender: VoiceGender::Male,
        vocabularyMin: 8,
        vocabularyMax: 12,
        roles: new LessonRoles('Candidat', 'Кандидат', 'Intervievator', 'Интервьюер'),
        earlierDays: new EarlierDays,
        targetLangCode: 'ro',
        nativeLangCode: 'ru',
    );
}

/** A catalogue whose only model writes down what it was asked for and sent, and answers `{}`. */
final class LrpCapturingCatalog implements ContentModelCatalog
{
    /** @var list<array{prompt: RenderedPrompt, user: string, schema: array<string, mixed>}> */
    public array $sent = [];

    /** @var list<array{model: string|null, journal: string|null, effort: string|null, timeout: int|null}> */
    public array $asked = [];

    public function availability(): array
    {
        return [];
    }

    public function available(): array
    {
        return [];
    }

    public function get(ProviderId $provider, ?string $model = null, ?string $purpose = null, ?int $timeoutSeconds = null, ?int $retries = null, ?string $journalPurpose = null, ?string $reasoningEffort = null): ContentModelPort
    {
        $this->asked[] = ['model' => $model, 'journal' => $journalPurpose, 'effort' => $reasoningEffort, 'timeout' => $timeoutSeconds];
        $catalog = $this;

        return new class($catalog) implements ContentModelPort
        {
            public function __construct(private LrpCapturingCatalog $catalog) {}

            public function provider(): ProviderId
            {
                return ProviderId::OpenAi;
            }

            public function model(): string
            {
                return 'gpt-5.4';
            }

            public function complete(RenderedPrompt $prompt, string $userMessage, array $schema): ModelAnswer
            {
                $this->catalog->sent[] = ['prompt' => $prompt, 'user' => $userMessage, 'schema' => $schema];

                return new ModelAnswer([], 'gpt-5.4', 1, 7000, 4000, '0.000000', '', 6912);
            }
        };
    }
}

/** @param array<string, PlanModelChoice> $choices */
function lrpBuilder(LrpCapturingCatalog $catalog, array $choices = []): ContentModelPlanBuilder
{
    return new ContentModelPlanBuilder($catalog, lrpPrompts(), ProviderId::OpenAi, $choices, 180, 180);
}

// Наряд GEN-4, 3.1: «вход скелета по форме TEST INPUT (TOPIC, TOPIC_DESCRIPTION 3 строки + About the learner…, SURVIVAL_SET
// нумерованными списками, …, роли «Target / Native», VOCABULARY_COUNT диапазоном «8–12», EARLIER_DAYS)». Catches an input
// that drifts from the form the prompt was written and tried with — a label, an order, a list's numbering, a separator.
it('writes the skeleton\'s input exactly as its prompt\'s TEST INPUT', function () {
    expect(lrpPrompts()->skeletonUser(lrpCanonRequest()))->toBe(lrpTestInput('skeleton'));
});

// Наряд GEN-4, 3.5: «вход диалога … + DIALOGUE_COUNT». Catches a dialogue asked in another form than its TEST INPUT's, a
// skeleton sent other than as accepted, and DIALOGUE_COUNT counted otherwise than partner lines + frames paired with none + 1.
it('writes the dialogue\'s input as its prompt\'s TEST INPUT, the skeleton as it stands', function () {
    $ours = lrpPrompts()->dialogueUser(new DialogueRequest(lrpCanonRequest(), dayCanonSkeleton()));
    [$oursHead, $oursSkeleton] = explode("SKELETON:\n", $ours, 2);
    [$theirsHead, $theirsSkeleton] = explode("SKELETON:\n", lrpTestInput('dialogue'), 2);

    $theirs = json_decode($theirsSkeleton, true, flags: JSON_THROW_ON_ERROR);
    // The canon of the tests is the file's skeleton with the one warning of its example taken out: «post» is not in a6.
    $theirs['vocabulary'][1]['used_in'] = ['p2', 'p6', 'a2'];

    expect($oursHead)->toBe($theirsHead)
        ->and($oursHead)->toContain("DIALOGUE_COUNT: 8\n")
        // The file writes a filler on one line, the server pretty-prints it: the same JSON, spaced otherwise.
        ->and(json_decode($oursSkeleton, true, flags: JSON_THROW_ON_ERROR))->toBe($theirs);
});

// Наряд GEN-4, 3: «повторный вызов ступени только по фатальной находке». Catches a stage asked again blind — the same answer
// bought twice — and the reason sent inside the rules, where it would spoil the cache.
it('asks a stage again with what failed it, after its input', function () {
    $again = lrpCanonRequest()->withViolations(['frame.must_say · p2: must_say [9] is out of the list 1…7']);

    expect(lrpPrompts()->skeletonUser($again))->toBe(lrpTestInput('skeleton')."\n\nPREVIOUS_ATTEMPT_REJECTED_FOR:\n- frame.must_say · p2: must_say [9] is out of the list 1…7")
        ->and(lrpPrompts()->dialogueUser((new DialogueRequest(lrpCanonRequest(), dayCanonSkeleton()))->withViolations(['partner.changed · A3: x'])))
        ->toEndWith("\n\nPREVIOUS_ATTEMPT_REJECTED_FOR:\n- partner.changed · A3: x")
        ->and(lrpPrompts()->skeletonSystem())->not->toContain('PREVIOUS_ATTEMPT_REJECTED_FOR:');
});

// Наряд GEN-3, §2: «EARLIER_DAYS — на первый день none; формат ровно как в разделе INPUTS промта». Catches a day 1 sent an
// empty story instead of «none», a story whose frames lose their native side, and a day told without its partner's gender.
it('writes the story so far as the stages read it, and «none» on the first day', function () {
    $second = new EarlierDays([
        planEarlierDay(1, null, 'Agent', VoiceGender::Female, 'Call to the agent'),
        planEarlierDay(2, null, 'Landlord', VoiceGender::Male, 'Second call'),
    ]);
    $request = new LessonRequest('Просмотр', 'Situation: Просмотр.', FakePlanModel::survival(), 'English', 'Russian', PlanLevel::Intermediate, VoiceGender::Male, 8, 12, new LessonRoles('Tenant', 'Арендатор', 'Agent', 'Агент'), $second);
    $user = lrpPrompts()->skeletonUser($request);

    expect(lrpPrompts()->skeletonUser(lrpCanonRequest()))->toEndWith("EARLIER_DAYS:\nnone")
        ->and($user)->toContain("LEARNER_ROLE: Tenant / Арендатор\n\nPARTNER_ROLE: Agent / Агент\n")
        ->and($user)->toContain("EARLIER_DAYS:\nDay 1 — Call to the agent (partner: Agent, female)\nA: Where does it hurt: in his upper back or lower down?\nB: It hurts in his lower back.\n")
        ->and($user)->toContain("Frames: It hurts in his ___. = У него болит ___. | It started ___. = Началось ___. | ")
        ->and($user)->toContain("Words: lower back | sharp | fever | muscle strain | heating pad | X-ray | follow-up appointment | sick note\n\nDay 2 — Second call (partner: Landlord, male)\n")
        ->and(lrpPrompts()->dialogueUser(new DialogueRequest($request, dayCanonSkeleton())))->toContain("EARLIER_DAYS:\nDay 1 — Call to the agent (partner: Agent, female)\n");
});

// Наряд GEN-3, §6: «неизменная часть (текст промта) шла первой и была байт в байт одинаковой между вызовами, а всё переменное —
// после неё». Catches a day's input written into the rules or the schema (the vendor's cache lost on every day), and a stage's
// rules sent with the TEST INPUT the file keeps for trying it.
it('sends two days the same rules and the same schema of each stage, and every input of the day only after them', function () {
    $catalog = new LrpCapturingCatalog;
    $builder = lrpBuilder($catalog);
    $other = new LessonRequest('Просмотр квартиры', 'Situation: a viewing.', FakePlanModel::survival(), 'English', 'Russian', PlanLevel::Beginner, null, 8, 12, new LessonRoles('Tenant', 'Арендатор', 'Landlord', 'Арендодатель'), new EarlierDays([planEarlierDay(1)]));
    $builder->buildSkeleton(lrpCanonRequest());
    $builder->buildSkeleton($other);
    $builder->buildDialogue(new DialogueRequest(lrpCanonRequest(), dayCanonSkeleton()));
    $builder->buildDialogue(new DialogueRequest($other, dayCanonSkeleton()));
    [$one, $two, $three, $four] = $catalog->sent;

    expect($two['prompt']->text)->toBe($one['prompt']->text)
        ->and($two['prompt']->sha256)->toBe($one['prompt']->sha256)
        ->and(json_encode($two['schema']))->toBe(json_encode($one['schema']))
        ->and($four['prompt']->text)->toBe($three['prompt']->text)
        ->and(json_encode($four['schema']))->toBe(json_encode($three['schema']))
        ->and([$one['prompt']->version, $three['prompt']->version])->toBe(['lesson_skeleton.v1', 'lesson_dialogue.v1'])
        ->and($one['prompt']->text)->not->toContain('TEST INPUT')
        ->and($three['prompt']->text)->not->toContain('TEST INPUT')
        ->and($one['prompt']->text)->not->toContain('Опыт работы')
        ->and($two['prompt']->text)->not->toContain('Landlord')
        ->and($two['user'])->toStartWith('TOPIC: Просмотр квартиры')
        ->and($four['user'])->toStartWith('TOPIC_DESCRIPTION: Situation: a viewing.')
        ->and($four['user'])->toContain("EARLIER_DAYS:\nDay 1 — Consultation (partner: Doctor, female)");
});

// `lesson_card_repair.v1.5`: «ADDRESS, FINDINGS, the card, SKELETON, DIALOGUE (a card of the dialogue), NEIGHBOURS (an
// exchange), EARLIER_DAYS»; наряд GEN-3 §5: the short story, Frames and Words only. Catches a repair of an exchange that cannot
// see the lines it must not move a fact out of, a skeleton card sent a dialogue that does not exist yet, a repair of any card
// that does not know what was learned, the whole earlier dialogue sent to a repair, and a schema that names the card or the day.
it('sends a repair its card, its stage, the neighbours of an exchange and the short story', function () {
    $earlier = new EarlierDays([planEarlierDay(1, null, 'Agent', VoiceGender::Female, 'Call to the agent')]);
    $request = FakePlanModel::lessonRequest('x', $earlier);
    [$skeleton] = planFixtureDay($request);
    $parser = new LessonParser;
    $dialogue = $parser->dialogue(FakePlanModel::dialoguePayload(new DialogueRequest($request, $skeleton)));
    $fake = new FakePlanModel;
    $repairer = new LessonCardRepairer($fake, $parser);
    $addresses = ['x3', 'x8', 'x1', 'p2', 'p5', 'v4', 'a3', 'x4.check', 'L2'];
    foreach ($addresses as $address) {
        $card = LessonCard::at($address) ?? throw new LogicException($address);
        $repairer->repair($skeleton, $card->ofSkeleton() ? null : $dialogue, $card, [new LessonViolation('some.code', $address, 'what is broken')], $request);
    }
    $catalog = new LrpCapturingCatalog;
    $builder = lrpBuilder($catalog);
    foreach ($fake->repairRequests as $sent) {
        $builder->repairLessonCard($sent);
    }
    $by = array_combine($addresses, $catalog->sent);

    expect($by['x3']['user'])->toContain("ADDRESS: x3\nCARD KIND: exchange\n\nFINDINGS (code · what is broken):\n- some.code · x3: what is broken\n")
        ->and($by['x3']['user'])->toContain("\nDIALOGUE (accepted; for context, do not return it):\n")
        ->and($by['x3']['user'])->toContain("NEIGHBOURS (the exchange before and the exchange after the card, as they lie in the dialogue; for reading only):\nbefore: {\"step\":2,")
        ->and($by['x3']['user'])->toContain("\nafter: {\"step\":4,")
        ->and($by['x8']['user'])->toContain("\nafter: none")
        ->and($by['x1']['user'])->toContain("\nbefore: none")
        ->and($by['x4.check']['user'])->not->toContain('NEIGHBOURS')
        ->and($by['x4.check']['user'])->toContain('DIALOGUE (accepted')
        // A card of the skeleton is repaired before the dialogue is written: none is sent.
        ->and($by['p2']['user'])->not->toContain('DIALOGUE (accepted')
        ->and($by['p2']['user'])->not->toContain('NEIGHBOURS')
        ->and($by['a3']['user'])->toContain("ADDRESS: a3\nCARD KIND: partner_line\n")
        ->and($by['v4']['user'])->toContain('SKELETON (accepted; for context, do not return it):')
        ->and($by['p2']['user'])->toEndWith("EARLIER_DAYS:\nDay 1\nFrames: It hurts in his ___. = У него болит ___. | It started ___. = Началось ___. | The pain is ___ when he bends. = Боль ___, когда он наклоняется. | He doesn't have a fever. = Температуры у него нет. | He will rest ___. = Он будет отдыхать ___. | Do we need ___? = Нам нужно ___?\nWords: lower back | sharp | fever | muscle strain | heating pad | X-ray | follow-up appointment | sick note")
        ->and($by['v4']['user'])->not->toContain('B: It hurts in his lower back.')
        // The rules and the schema are the kind's, the same whatever the card: the vendor keeps them in its cache.
        ->and($by['x3']['prompt']->text)->toBe($by['x8']['prompt']->text)
        ->and(json_encode($by['x3']['schema']))->toBe(json_encode($by['x1']['schema']))
        ->and(json_encode($by['p2']['schema']))->toBe(json_encode($by['p5']['schema']))
        ->and($by['p2']['prompt']->text)->toBe($by['p5']['prompt']->text)
        ->and($by['p2']['prompt']->version)->toBe('lesson_card_repair.v1.5');
});

// Наряд GEN-4, §1: «починка цитирует разделы по виду карточки: frame/term/partner_line — FRAMES, FILLERS, PARTNER LINES,
// VOCABULARY, PRONUNCIATION_NATIVE, TEXT QUALITY скелета; exchange/check/listening — EXCHANGES, LEARNER MESSAGES, PARTNER
// MESSAGES, CHECK PER EXCHANGE, LISTENING, TEXT QUALITY диалога». Catches a heading written with a typo (it would quote
// nothing), a card repaired with the other stage's rules, and a rule retold instead of quoted.
it('quotes to a repair the sections of the stage its card is of, word for word', function () {
    expect(PlanPromptFiles::REPAIR_SECTIONS)->toBe([
        'skeleton' => ['FRAMES', 'FILLERS', 'PARTNER LINES', 'VOCABULARY', 'PRONUNCIATION_NATIVE', 'TEXT QUALITY'],
        'dialogue' => ['EXCHANGES', 'LEARNER MESSAGES', 'PARTNER MESSAGES', 'CHECK PER EXCHANGE', 'LISTENING', 'TEXT QUALITY'],
    ]);
    foreach (PlanPromptFiles::REPAIR_SECTIONS as $stage => $headings) {
        foreach ($headings as $heading) {
            expect(lrpPrompts()->section($stage, $heading))->toStartWith($heading);
        }
    }
    foreach ([...LessonCard::SKELETON_KINDS, ...LessonCard::DIALOGUE_KINDS] as $kind) {
        $stage = in_array($kind, LessonCard::SKELETON_KINDS, true) ? 'skeleton' : 'dialogue';
        $other = $stage === 'skeleton' ? 'dialogue' : 'skeleton';
        $system = lrpPrompts()->repairSystem($kind);
        foreach (PlanPromptFiles::REPAIR_SECTIONS[$stage] as $heading) {
            expect($system)->toContain(lrpPrompts()->section($stage, $heading));
        }
        expect($system)->not->toContain('{{rules}}')
            ->and($system)->not->toContain(lrpPrompts()->section($other, PlanPromptFiles::REPAIR_SECTIONS[$other][0]));
    }
    expect([lrpPrompts()->planVersion(), lrpPrompts()->skeletonVersion(), lrpPrompts()->dialogueVersion(), lrpPrompts()->repairVersion(), lrpPrompts()->planLineVersion()])
        ->toBe(['plan-builder-v2.1', 'lesson_skeleton.v1', 'lesson_dialogue.v1', 'lesson_card_repair.v1.5', 'plan_line_repair.v1']);
});

// Наряд GEN-4, §4: «модель и reasoning_effort per purpose: plan, plan_line_repair, skeleton, dialogue, repair, seam_judge,
// slot_judge; журнал пишет model и purpose каждого вызова». Catches a stage asked on another purpose's model, an effort lost
// on the way to the vendor, a purpose the journal cannot tell apart, and a purpose the config does not name failing.
it('asks every purpose on its own model and reasoning effort, and names the purpose to the journal', function () {
    $catalog = new LrpCapturingCatalog;
    $builder = lrpBuilder($catalog, [
        ContentModelPlanBuilder::SKELETON => new PlanModelChoice('gpt-5.6-luna', 'high'),
        ContentModelPlanBuilder::DIALOGUE => new PlanModelChoice('gpt-5.6-luna'),
        ContentModelPlanBuilder::REPAIR => new PlanModelChoice('gpt-5.6-luna', 'low'),
        ContentModelPlanBuilder::SEAM_JUDGE => new PlanModelChoice('gpt-5.4-mini'),
        ContentModelPlanBuilder::PLAN_LINE_REPAIR => new PlanModelChoice('gpt-5.6-luna'),
    ]);
    $request = FakePlanModel::lessonRequest();
    [$skeleton] = planFixtureDay($request);
    $builder->buildPlan(new PlanRequest('Иду к врачу', 'English', 'Russian', PlanLevel::Beginner, 3));
    $builder->repairPlanLine(new PlanLineRepairRequest('title_native', 'Russian', 24, 'Очень длинное название плана'));
    $builder->buildSkeleton($request);
    $builder->judgeNativeSeams(new NativeSeamJudgeRequest('Russian', [['id' => 'p1.f1', 'pattern' => 'У него болит ___.', 'value' => 'поясница', 'sentence' => 'У него болит поясница.']]));
    $builder->buildDialogue(new DialogueRequest($request, $skeleton));
    $builder->repairLessonCard(...(static function () use ($skeleton, $request): array {
        $fake = new FakePlanModel;
        (new LessonCardRepairer($fake, new LessonParser))->repair($skeleton, null, LessonCard::at('p1') ?? throw new LogicException, [], $request);

        return $fake->repairRequests;
    })());

    expect(array_map(static fn (array $a): string => "{$a['journal']}:{$a['model']}:".($a['effort'] ?? '—'), $catalog->asked))->toBe([
        'plan:gpt-5.4:—',
        'plan_line_repair:gpt-5.6-luna:—',
        'skeleton:gpt-5.6-luna:high',
        'seam_judge:gpt-5.4-mini:—',
        'dialogue:gpt-5.6-luna:—',
        'repair:gpt-5.6-luna:low',
    ])
        ->and(array_column($catalog->asked, 'timeout'))->toBe([180, 30, 180, 180, 180, 180]);
});

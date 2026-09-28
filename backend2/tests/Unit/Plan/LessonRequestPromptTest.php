<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\ModelAnswer;
use App\Modules\Generation\Application\Dto\RenderedPrompt;
use App\Modules\Generation\Application\Port\ContentModelCatalog;
use App\Modules\Generation\Application\Port\ContentModelPort;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\LessonCard;
use App\Modules\Plan\Domain\Lesson\LessonCardContext;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\LessonRoles;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Infrastructure\Model\ContentModelPlanBuilder;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Plan\Infrastructure\Prompt\PlanPromptFiles;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * WHAT THE LESSON AND THE REPAIR OF A CARD SEND (`lesson_day`, `lesson_card_repair`; наряд GEN-3): the new inputs
 * of a day — the roles, the story so far — in the prompt's own format; a repair's NEIGHBOURS and the short story; and a
 * request built for the vendor's prompt cache — the rules and the schema first and byte for byte the same between two days,
 * everything that varies after them.
 */

function lrpPrompts(): PlanPromptFiles
{
    return new PlanPromptFiles;
}

function lrpRequest(string $topic, LessonRoles $roles, EarlierDays $earlier): LessonRequest
{
    return new LessonRequest($topic, "Situation: {$topic}.", 'English', 'Russian', PlanLevel::Intermediate, VoiceGender::Male, 8, 8, $roles, $earlier, [], 'en', 'ru');
}

/** A catalogue whose only model writes down what it was sent and answers `{}`. */
final class LrpCapturingCatalog implements ContentModelCatalog
{
    /** @var list<array{prompt: RenderedPrompt, user: string, schema: array<string, mixed>}> */
    public array $sent = [];

    public function availability(): array
    {
        return [];
    }

    public function available(): array
    {
        return [];
    }

    public function get(ProviderId $provider, ?string $model = null, ?string $purpose = null, ?int $timeoutSeconds = null, ?int $retries = null, ?string $journalPurpose = null): ContentModelPort
    {
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

function lrpBuilder(LrpCapturingCatalog $catalog): ContentModelPlanBuilder
{
    return new ContentModelPlanBuilder($catalog, lrpPrompts(), ProviderId::OpenAi, 'gpt-5.4', 'gpt-5.4', 180, 180, 'gpt-5.4', 'gpt-5.4-mini');
}

// Наряд GEN-3, §2: «LEARNER_ROLE — «{target} / {native}» из плана; PARTNER_ROLE — из сцены; EARLIER_DAYS — на первый день none;
// формат ровно как в разделе INPUTS промта». Catches a role sent in one language, a day 1 sent an empty story instead of
// «none», a story whose frames lose their native side, and a day told without its partner's gender.
it('writes the roles and the story so far as the day prompt reads them, and «none» on the first day', function () {
    $roles = new LessonRoles('Tenant', 'Арендатор', 'Agent', 'Агент');
    $first = lrpPrompts()->lessonUser(lrpRequest('Звонок агенту', $roles, new EarlierDays));
    $second = lrpPrompts()->lessonUser(lrpRequest('Просмотр', $roles, new EarlierDays([
        planEarlierDay(1, null, 'Agent', VoiceGender::Female, 'Call to the agent'),
        planEarlierDay(2, null, 'Landlord', VoiceGender::Male, 'Second call'),
    ])));

    expect(array_values(array_map(static fn (string $line): string => explode(':', $line, 2)[0], preg_grep('/^[A-Z_]+:/', explode("\n", $first)) ?: [])))
        ->toBe(['TOPIC', 'TOPIC_DESCRIPTION', 'TARGET_LANGUAGE', 'NATIVE_LANGUAGE', 'LEVEL', 'LEARNER_GENDER', 'LEARNER_ROLE', 'PARTNER_ROLE', 'VOCABULARY_COUNT', 'DIALOGUE_COUNT', 'EARLIER_DAYS'])
        ->and($first)->toContain("LEARNER_ROLE: Tenant / Арендатор\n\nPARTNER_ROLE: Agent / Агент\n")
        ->and($first)->toEndWith("EARLIER_DAYS:\nnone")
        ->and($second)->toContain("EARLIER_DAYS:\nDay 1 — Call to the agent (partner: Agent, female)\nA: Where does it hurt: his upper back or his lower back?\nB: It hurts in his lower back.\n")
        ->and($second)->toContain("Frames: It hurts in his ___. = У него болит ___. | It started ___. = Началось ___. | ")
        ->and($second)->toContain("Words: lower back | sharp | fever | muscle strain | heating pad | X-ray | follow-up appointment | sick note\n\nDay 2 — Second call (partner: Landlord, male)\n");
});

// Наряд GEN-3, §6: «неизменная часть (текст промта) шла первой и была байт в байт одинаковой между вызовами, а всё переменное
// (тема, роли, EARLIER_DAYS) — после неё; ничего переменного (дата, id) до правил». Catches a day's input written into the
// rules or the schema (the vendor's cache of the 7K-token prompt lost on every day), and an input missing from the data.
it('sends two days the same rules and the same schema, and every input of the day only after them', function () {
    $catalog = new LrpCapturingCatalog;
    $builder = lrpBuilder($catalog);
    $builder->buildLesson(lrpRequest('Звонок агенту', new LessonRoles('Tenant', 'Арендатор', 'Agent', 'Агент'), new EarlierDays));
    $builder->buildLesson(lrpRequest('Просмотр квартиры', new LessonRoles('Tenant', 'Арендатор', 'Landlord', 'Арендодатель'), new EarlierDays([planEarlierDay(1)])));
    [$one, $two] = $catalog->sent;

    expect($two['prompt']->text)->toBe($one['prompt']->text)
        ->and($two['prompt']->sha256)->toBe($one['prompt']->sha256)
        ->and(json_encode($two['schema']))->toBe(json_encode($one['schema']))
        ->and($one['prompt']->text)->not->toContain('Звонок агенту')
        ->and($two['prompt']->text)->not->toContain('Просмотр квартиры')
        ->and($two['prompt']->text)->not->toContain('Landlord')
        ->and($two['prompt']->text)->not->toContain('It hurts in his lower back.')
        ->and($two['user'])->toStartWith('TOPIC: Просмотр квартиры')
        ->and($two['user'])->toContain('PARTNER_ROLE: Landlord / Арендодатель')
        ->and($two['user'])->toContain("EARLIER_DAYS:\nDay 1 — Consultation (partner: Doctor, female)");
});

// Наряд GEN-3, §5 and §6: «для вида exchange в вызов уходят NEIGHBOURS: обмен до и обмен после (или none); для всех видов уходит
// EARLIER_DAYS в короткой форме: только Frames (оба языка) и Words». Catches a repair of an exchange that cannot see the
// lines it must not move a fact out of, a repair of any card that does not know the frames and words already learned,
// the whole earlier dialogue sent to a repair, and a schema that names the card or the day — keeping the kind's rules out
// of the vendor's cache.
it('sends a repair its neighbours, the short story and a schema that names no card', function () {
    $answer = (new LessonParser)->parse(lvRepairPayload());
    $read = LessonAssembly::said($answer, lessonPacks()->for('en'));
    $earlier = new EarlierDays([planEarlierDay(1, null, 'Agent', VoiceGender::Female, 'Call to the agent')]);
    $request = static fn (string $address) => new LessonCardRepairRequest(
        $address, LessonCard::at($address)?->kind ?? '', LessonCard::at($address)?->of($read) ?? [],
        LessonCardContext::of($read, LessonCard::at($address) ?? throw new RuntimeException),
        [['code' => 'exchange.repeats', 'detail' => 'x']],
        LessonCard::at($address)?->kind === LessonCard::EXCHANGE ? LessonCardContext::neighbours($read, LessonCard::at($address)) : null,
        $earlier, 'English', 'Russian', PlanLevel::Intermediate, null, 8, 8,
    );
    $catalog = new LrpCapturingCatalog;
    $builder = lrpBuilder($catalog);
    foreach (['x3', 'x8', 'x1', 'p2', 'v4', 'p5'] as $address) {
        $builder->repairLessonCard($request($address));
    }
    [$x3, $x8, $x1, $p2, $v4, $p5] = $catalog->sent;

    expect($x3['user'])->toContain("NEIGHBOURS (the exchange before and the exchange after the card, as they lie in the lesson; for reading only):\nbefore: {\"step\":2,")
        ->and($x3['user'])->toContain("\nafter: {\"step\":4,")
        ->and($x8['user'])->toContain("\nafter: none")
        ->and($x1['user'])->toContain("\nbefore: none")
        // The exchanges beside the card go as NEIGHBOURS, not twice.
        ->and(substr_count($x3['user'], 'Did it start today, or earlier this week?'))->toBe(1)
        ->and($p2['user'])->not->toContain('NEIGHBOURS')
        ->and($v4['user'])->not->toContain('NEIGHBOURS')
        ->and($p2['user'])->toEndWith("EARLIER_DAYS:\nDay 1\nFrames: It hurts in his ___. = У него болит ___. | It started ___. = Началось ___. | The pain is ___ when he bends. = Боль ___, когда он наклоняется. | He doesn't have a fever. = Температуры у него нет. | He will rest ___. = Он будет отдыхать ___. | Do we need ___? = Нам нужно ___?\nWords: lower back | sharp | fever | muscle strain | heating pad | X-ray | follow-up appointment | sick note")
        ->and($v4['user'])->toContain("EARLIER_DAYS:\nDay 1\nFrames:")
        ->and($v4['user'])->not->toContain('B: It hurts in his lower back.')
        ->and($v4['user'])->toContain('"partner_lines"')
        ->and($x3['prompt']->text)->toBe($x8['prompt']->text)
        ->and(json_encode($x3['schema']))->toBe(json_encode($x8['schema']))
        ->and(json_encode($x3['schema']))->toBe(json_encode($x1['schema']))
        ->and(json_encode($p2['schema']))->toBe(json_encode($p5['schema']))
        ->and($p2['prompt']->text)->toBe($p5['prompt']->text)
        ->and($x3['prompt']->text)->toContain(lrpPrompts()->lessonSection('THE STORY SO FAR'))
        ->and($v4['prompt']->text)->toContain(lrpPrompts()->lessonSection('VOCABULARY'))
        ->and($v4['schema']['properties']['card']['properties']['id']['enum'])->toBe(['v1', 'v2', 'v3', 'v4', 'v5', 'v6', 'v7', 'v8']);
});

// The quoted rules of a repair are the lesson prompt's own sections, found by their headings — a heading that is not in the
// prompt quotes nothing and would go unnoticed. Catches a heading written with a typo, or one v4.6 renamed.
it('finds every section a repair of each kind quotes in the lesson prompt it quotes from', function () {
    foreach (PlanPromptFiles::REPAIR_SECTIONS as $headings) {
        foreach ($headings as $heading) {
            expect(lrpPrompts()->lessonSection($heading))->toStartWith($heading);
        }
    }
    expect(lrpPrompts()->lessonVersion())->toBe('lesson_day.v4.10')
        ->and(lrpPrompts()->repairVersion())->toBe('lesson_card_repair.v1.4');
});

/** The clean lesson told with p6 apart, so no exchange carries a warning of its own — the payload repairs are asked of. */
function lvRepairPayload(): array
{
    return FakePlanModel::lessonPayload(new LessonRequest('x', 'x', 'English', 'Russian', PlanLevel::Beginner, null, 8, 8, FakePlanModel::roles(), new EarlierDays));
}

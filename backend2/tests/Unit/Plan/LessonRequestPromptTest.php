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
 * WHAT THE LESSON AND THE REPAIR OF A CARD SEND (`lesson_day.v4.7`, `lesson_card_repair.v1.3`; наряд GEN-3): the new inputs
 * of a day — the roles, the story so far — in the prompt's own format; a repair's NEIGHBOURS and the short story; and a
 * request built for the vendor's prompt cache — the rules and the schema first and byte for byte the same between two days,
 * everything that varies after them.
 */

function lrpPrompts(): PlanPromptFiles
{
    return new PlanPromptFiles(dirname(__DIR__, 3).'/app/Modules/Plan/Infrastructure/Prompt');
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

// Canon (наряд LANG-1 §8, DECISIONS п. 157): v4.8 was v4.7 with ONE clause of FINAL INTERNAL VALIDATION replaced — a reading
// in the letters of NATIVE_LANGUAGE's own alphabet, where v4.7 said «Cyrillic only when NATIVE_LANGUAGE is Russian». Наряд
// LANG-1b §5: v4.9 was v4.8 with two lines replaced — LEARNER_GENDER (INPUTS) and role_gender (ROLE GENDER) shape the lines in
// both languages — and ONE sentence added to TEXT QUALITY: the partner's formal address in TARGET_LANGUAGE, the learner
// never assuming the partner's gender. Наряд LANG-1b §10: v4.10 is v4.9 with the definition's language said in VOCABULARY
// (one line replaced), the check's text_target in TARGET_LANGUAGE (one bullet added to CHECK PER EXCHANGE) and an ask exchange
// as a real question, never the learner's own skill turned into one (one paragraph added to EXCHANGE KINDS, after a blank
// line). v4.8 and v4.9 are gone; the rollback v4.7 stays — so v4.10 is read against v4.7: four lines replaced, four added.
// Catches a further edit slipped into the frozen text, a clause lost or written elsewhere, and a change to a section a repair
// quotes other than these: every other rule of P2R stays v4.7's byte for byte.
it('writes v4.10 as v4.7 with the reading clause of v4.8, the gender and the address of v4.9 and the three rules of LANG-1b §10 — and nothing else', function () {
    $dir = dirname(__DIR__, 3).'/app/Modules/Plan/Infrastructure/Prompt';
    $old = explode("\n", (string) file_get_contents("{$dir}/lesson_day.v4.7.md"));
    $new = explode("\n", (string) file_get_contents("{$dir}/lesson_day.v4.10.md"));
    $address = "- In TARGET_LANGUAGE the partner addresses the learner formally (vous / Sie / usted / Lei / pan, pani / dumneavoastră) unless the scene is clearly casual; the learner's own lines never assume the partner's gender.";
    $check = '- text_target of the question and of every option — in TARGET_LANGUAGE.';
    $ask = 'In an ask exchange the learner asks a real question a person in this scene would ask the partner (schedule, duties, pay, documents, next steps) — never their own skill or fact turned into a question; skills are answer exchanges.';
    $at = array_search($address, $new, true);
    $atCheck = array_search($check, $new, true);
    $atAsk = array_search($ask, $new, true);
    expect($at)->toBeInt()
        ->and($new[$at - 1])->toBe("- A's native lines follow role_gender.")
        ->and($atCheck)->toBeInt()
        ->and($new[$atCheck - 1])->toStartWith('- Exactly 3 options, text_target and text_native for the question and each option')
        ->and($atAsk)->toBeInt()
        ->and($new[$atAsk - 1])->toBe('')
        ->and($new[$atAsk - 2])->toStartWith('Right: exchange 5:')
        ->and($new[$atAsk + 2])->toBe('Requirements across the lesson:');
    // The added lines out — the paragraph with the blank line before it.
    $without = array_values(array_filter(
        $new,
        static fn (string $line, int $i): bool => ! in_array($line, [$address, $check, $ask], true) && $i !== $atAsk - 1,
        ARRAY_FILTER_USE_BOTH,
    ));
    $changed = array_keys(array_diff_assoc($without, $old));
    $replaced = [
        '; Cyrillic only when NATIVE_LANGUAGE is Russian.' => "; only the letters of NATIVE_LANGUAGE's own alphabet (Cyrillic for Russian, Ukrainian and Belarusian — each with its own letters; Latin for the others).",
        "Affects only NATIVE_LANGUAGE grammar of the learner's lines (see TEXT QUALITY)." => "LEARNER_GENDER shapes the learner's lines in NATIVE_LANGUAGE and, where TARGET_LANGUAGE marks gender in agreement (adjectives, participles, profession nouns), in TARGET_LANGUAGE too; unknown → gender-neutral phrasing in both languages.",
        'It never changes any TARGET_LANGUAGE text.' => "role_gender shapes A's lines the same way in both languages.",
        'definition_target in TARGET_LANGUAGE.' => 'definition_target — a short definition in TARGET_LANGUAGE, never in English unless TARGET_LANGUAGE is English.',
    ];

    expect(count($new))->toBe(count($old) + 4)
        ->and($changed)->toHaveCount(4);
    foreach ($changed as $line) {
        expect(str_replace(array_values($replaced), array_keys($replaced), $without[$line]))->toBe($old[$line]);
    }
    expect(lrpPrompts()->lessonSection('FINAL INTERNAL VALIDATION'))->toContain(array_values($replaced)[0])
        ->and(lrpPrompts()->lessonSection('ROLE GENDER'))->toContain(array_values($replaced)[2])
        ->and(lrpPrompts()->lessonSection('TEXT QUALITY'))->toContain($address)
        // The example of PRONUNCIATION_NATIVE, which P2R quotes, is v4.7's: «(for Russian: Cyrillic)».
        ->and(lrpPrompts()->lessonSection('PRONUNCIATION_NATIVE'))->toContain('(for Russian: Cyrillic)')
        // Each §10 rule reaches the repairs that quote its section: the word, the check, the exchange and the line.
        ->and(lrpPrompts()->repairSystem('line'))->toContain($address)->toContain($ask)
        ->and(lrpPrompts()->repairSystem('term'))->toContain(array_values($replaced)[3])
        ->and(lrpPrompts()->repairSystem('check'))->toContain($check)
        ->and(lrpPrompts()->repairSystem('exchange'))->toContain($ask)->toContain($check);
    $v47 = implode("\n", $old);
    foreach (PlanPromptFiles::REPAIR_SECTIONS as $headings) {
        foreach ($headings as $heading) {
            $section = str_replace(["\n".$address, "\n".$check, "\n\n".$ask], '', lrpPrompts()->lessonSection($heading));
            expect($v47)->toContain(str_replace(array_values($replaced), array_keys($replaced), $section));
        }
    }
});

/** The clean lesson told with p6 apart, so no exchange carries a warning of its own — the payload repairs are asked of. */
function lvRepairPayload(): array
{
    return FakePlanModel::lessonPayload(new LessonRequest('x', 'x', 'English', 'Russian', PlanLevel::Beginner, null, 8, 8, FakePlanModel::roles(), new EarlierDays));
}

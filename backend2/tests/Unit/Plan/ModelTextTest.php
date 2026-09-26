<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\ModelAnswer;
use App\Modules\Generation\Application\Dto\RenderedPrompt;
use App\Modules\Generation\Application\Port\ContentModelCatalog;
use App\Modules\Generation\Application\Port\ContentModelPort;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Domain\Service\ModelText;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Infrastructure\Model\ContentModelPlanBuilder;
use App\Modules\Plan\Infrastructure\Prompt\PlanPromptFiles;
use App\Modules\Shared\Domain\Service\TextNormalizer;

/**
 * THE MODEL'S TEXT WITHOUT THE CHARACTERS THAT PRINT NOTHING (наряд LANG-1b §6): «на приёме любого текста модели вырезать
 * U+00AD, U+200B–U+200F, U+2060, U+FEFF, U+2028/2029 и прочие форматирующие». The first row is the live one: day 1 of the
 * owner's plan «Собеседование» (26.09) was titled «Опы\u{0004}т и навыки» — U+0004, a CONTROL character the list of the order
 * does not name, and the one the phone drew as an empty box.
 */

// CATCHES a character that prints nothing kept in a stored text — the soft hyphen, the zero-width space and its kin, a
// bidi mark, the byte order mark, a control character inside a word — and a line separator cut so that two words run
// together.
it('cuts every format and control character out of a text, and turns a line separator into a space', function (string $dirty, string $clean) {
    expect((new TextNormalizer)->visible($dirty))->toBe($clean);
})->with([
    'the live day: U+0004 inside a word' => ["Опы\u{0004}т и навыки", 'Опыт и навыки'],
    'a soft hyphen' => ["Собе\u{00AD}седование", 'Собеседование'],
    'a zero-width space, non-joiner and joiner' => ["Ap\u{200B}point\u{200C}ment\u{200D}", 'Appointment'],
    'left-to-right and right-to-left marks' => ["\u{200E}Termin\u{200F}", 'Termin'],
    'a bidi embedding and an isolate' => ["\u{202A}Rezeption\u{202C} \u{2066}Arzt\u{2069}", 'Rezeption Arzt'],
    'a word joiner and a byte order mark' => ["\u{FEFF}Wie\u{2060} bitte?", 'Wie bitte?'],
    'the Arabic letter mark' => ["Oficina\u{061C}", 'Oficina'],
    'the line and paragraph separators' => ["Добрый день.\u{2028}Чем могу помочь?\u{2029}", 'Добрый день. Чем могу помочь? '],
    'a control character that is no line break' => ["Wie\u{0007} bitte\u{007F}?\u{0085}", 'Wie bitte?'],
]);

// CATCHES a letter or a space of the text taken for an invisible character: the French no-break space before «?», the
// stress mark of a reading, the Ukrainian apostrophe, a tab and the line breaks of a brief, an accented letter.
it('keeps what is a letter or a space of the text', function (string $text) {
    expect((new TextNormalizer)->visible($text))->toBe($text);
})->with([
    'the French no-break space' => ["Pardon\u{00A0}?"],
    'a narrow no-break space' => ["Il est 9\u{202F}h."],
    'a combining stress mark' => ["до лека\u{0301}жа"],
    'the Ukrainian apostrophe' => ["п\u{02BC}ять"],
    'a tab and line breaks' => ["Situation: a clinic.\nGoal:\tan appointment.\r\n"],
    'accented letters' => ['Wie geht’s? ¿Qué tal? Ça va. Știu.'],
]);

// CATCHES a string of an answer left dirty because it is deep in the payload — a line of the dialogue, a filler — or a key
// or a number of the payload changed.
it('reads every string of an answer, however deep, and nothing else', function () {
    $payload = ['topic' => ['title_native' => "Опы\u{0004}т"], 'dialogue' => [['step' => 1, 'messages' => [['text_native' => "Здрав\u{00AD}ствуйте."]]]],
        'phrases' => [['slot' => ['fillers' => [['native' => "три\u{200B}года", 'in_dialogue' => true]]]]], 'role_gender' => 'female'];

    expect(ModelText::visible($payload))->toBe(['topic' => ['title_native' => 'Опыт'], 'dialogue' => [['step' => 1, 'messages' => [['text_native' => 'Здравствуйте.']]]],
        'phrases' => [['slot' => ['fillers' => [['native' => 'тригода', 'in_dialogue' => true]]]]], 'role_gender' => 'female']);
});

/** A catalogue whose only model answers what it is given, as the vendor's JSON would decode. */
final class MtAnsweringCatalog implements ContentModelCatalog
{
    /** @param array<string, mixed> $answer */
    public function __construct(private array $answer) {}

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
        $answer = $this->answer;

        return new class($answer) implements ContentModelPort
        {
            /** @param array<string, mixed> $answer */
            public function __construct(private array $answer) {}

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
                return new ModelAnswer($this->answer, 'gpt-5.4', 1, 100, 100, '0.000000', '', 0);
            }
        };
    }
}

// Наряд LANG-1b §6: «на приёме» — the one place every answer of the plan's model comes in. CATCHES a model text that reaches
// the plan with its invisible characters because the adapter hands the vendor's payload on as it came.
it('hands the plan an answer of the model without its invisible characters', function () {
    $builder = new ContentModelPlanBuilder(
        new MtAnsweringCatalog(['status' => 'ok', 'plan' => ['title_native' => "Собесе\u{00AD}дование", 'scenes' => [['title_native' => "Опы\u{0004}т и навыки"]]]]),
        new PlanPromptFiles(dirname(__DIR__, 3).'/app/Modules/Plan/Infrastructure/Prompt'),
        ProviderId::OpenAi, 'gpt-5.4', 'gpt-5.4', 180, 180, 'gpt-5.4', 'gpt-5.4-mini',
    );

    $reply = $builder->buildPlan(new PlanRequest('Собеседование в пятницу', 'English', 'Russian', PlanLevel::Beginner, 2));

    expect($reply->payload['plan']['title_native'])->toBe('Собеседование')
        ->and($reply->payload['plan']['scenes'][0]['title_native'])->toBe('Опыт и навыки');
});

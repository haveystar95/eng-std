<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Vocabulary\Application\Command\ImportTerm;
use App\Modules\Vocabulary\Application\Command\ImportTermHandler;
use App\Modules\Vocabulary\Application\Dto\ExampleInput;
use App\Modules\Vocabulary\Application\Dto\TranslationInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function importStamped(string $text, ?string $version, ?string $model, string $translation = 'перевод', ?string $example = 'A sentence with it.'): string
{
    return app(ImportTermHandler::class)(new ImportTerm(
        lang: new LanguageCode('en'),
        text: $text,
        type: 'phrase',
        pos: null,
        source: 'ai',
        translations: [new TranslationInput(new LanguageCode('ru'), $translation, isPrimary: true)],
        examples: $example !== null
            ? [new ExampleInput($example, 'Предложение с этим.', new LanguageCode('ru'))]
            : [],
        promptVersion: $version,
        generationModel: $model,
    ))->value;
}

it('stamps the prompt version and model on the term, its translation and its example', function () {
    $id = importStamped('tell us about it', 'v10', 'gpt-4o');

    expect(DB::table('terms')->where('id', $id)->first(['prompt_version', 'generation_model']))
        ->prompt_version->toBe('v10')
        ->generation_model->toBe('gpt-4o');

    expect(DB::table('term_translations')->where('term_id', $id)->first(['prompt_version', 'generation_model']))
        ->prompt_version->toBe('v10')
        ->generation_model->toBe('gpt-4o');

    expect(DB::table('term_examples')->where('term_id', $id)->first(['prompt_version', 'generation_model']))
        ->prompt_version->toBe('v10')
        ->generation_model->toBe('gpt-4o');
});

/**
 * The reason provenance sits on the ROW and not on the request: a term is global and deduplicated,
 * so the line a learner reads may have been written by a later prompt than the term it hangs off.
 * A sweep that fixed "every v9 term" while a v10 translation hid inside one would miss it.
 */
it('keeps the older stamp on a deduplicated term while the line a newer call adds carries its own', function () {
    $first = importStamped('open a bank account', 'v9', 'gpt-4o', 'открыть счёт в банке');
    $second = importStamped('Open a Bank Account', 'v10', 'grok-4', 'открыть банковский счёт', example: null);

    expect($second)->toBe($first); // same term — dedup by normalized text

    expect(DB::table('terms')->where('id', $first)->value('prompt_version'))->toBe('v9');

    $byVersion = DB::table('term_translations')->where('term_id', $first)
        ->pluck('prompt_version', 'text')->all();

    expect($byVersion)->toEqualCanonicalizing([
        'открыть счёт в банке' => 'v9',
        'открыть банковский счёт' => 'v10',
    ]);
});

/**
 * NULL is the signal that a writer produced content without saying where it came from. It only
 * works as a signal if nothing invents a plausible value — no column DEFAULT, no "unknown" string
 * filled in by the handler, and the backfill sentinel `legacy` reserved for rows that predate the
 * column entirely.
 */
it('writes no stamp at all when the caller does not know one', function () {
    $id = importStamped('a curated line', null, null);

    expect(DB::table('terms')->where('id', $id)->value('prompt_version'))->toBeNull()
        ->and(DB::table('term_translations')->where('term_id', $id)->value('prompt_version'))->toBeNull()
        ->and(DB::table('term_examples')->where('term_id', $id)->value('prompt_version'))->toBeNull();
});

it('records the prompt version even when the model that answered is unknown', function () {
    $id = importStamped('a versioned line', 'v10', null);

    expect(DB::table('terms')->where('id', $id)->first(['prompt_version', 'generation_model']))
        ->prompt_version->toBe('v10')
        ->generation_model->toBeNull();
});

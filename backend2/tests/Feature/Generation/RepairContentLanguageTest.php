<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Command\RepairContentLanguage;
use App\Modules\Generation\Application\Command\RepairContentLanguageHandler;
use App\Modules\Generation\Application\Port\TranslationRepairPort;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Doubles\ScriptedTranslationRepairer;

uses(RefreshDatabase::class);

const REPAIR_TERM = '01J0000000000000000000TERM';
const REPAIR_COLLECTION = '01J0000000000000000000DECK';
const REPAIR_EXAMPLE = '01J0000000000000000000EXMP';

/**
 * The live shape from docs/ua-audit.md: an English idiom in a ru→en collection, carrying a correct
 * Russian translation, a Ukrainian one beside it, and an example whose Russian side is Ukrainian.
 */
function seedPoisonedTerm(bool $withRussianTranslation = true): void
{
    DB::table('collections')->insert([
        'id' => REPAIR_COLLECTION,
        'type' => 'system',
        'title' => 'Деловые созвоны',
        'source_lang' => 'ru',
        'target_lang' => 'en',
        'visibility' => 'public',
        'source' => 'curated',
        'items_count' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('terms')->insert([
        'id' => REPAIR_TERM,
        'lang' => 'en',
        'text' => 'on the same page',
        'normalized_text' => 'on the same page',
        'type' => 'idiom',
        'source' => 'ai',
        'created_at' => now(),
        'updated_at' => now()->subDay(),
    ]);
    DB::table('collection_items')->insert([
        'id' => '01J'.str_repeat('0', 19).'CARD',
        'collection_id' => REPAIR_COLLECTION,
        'term_id' => REPAIR_TERM,
        'position' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    if ($withRussianTranslation) {
        DB::table('term_translations')->insert([
            'id' => '01J'.str_repeat('0', 19).'TRRU',
            'term_id' => REPAIR_TERM,
            'lang' => 'ru',
            'text' => 'быть на одной волне',
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    DB::table('term_translations')->insert([
        'id' => '01J'.str_repeat('0', 19).'TRUA',
        'term_id' => REPAIR_TERM,
        'lang' => 'uk',
        'text' => 'на одній хвилі, розуміти одне одного',
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('term_examples')->insert([
        'id' => REPAIR_EXAMPLE,
        'term_id' => REPAIR_TERM,
        'sentence' => 'It is important for the team to be on the same page.',
        'sentence_translation' => 'Важливо, щоб команда розуміла одне одного.',
        'source' => 'ai',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function repairHandler(TranslationRepairPort $repairer): RepairContentLanguageHandler
{
    app()->instance(TranslationRepairPort::class, $repairer);

    return app(RepairContentLanguageHandler::class);
}

it('lists the offences without touching anything on a dry run', function () {
    seedPoisonedTerm();
    $repairer = new ScriptedTranslationRepairer([]);

    $report = repairHandler($repairer)(new RepairContentLanguage(new LanguageCode('ru')));

    expect($report)->toHaveCount(2)
        ->and(array_map(static fn ($r): string => $r->action, $report))->toBe(['planned', 'planned'])
        ->and($repairer->calls)->toBe(0)
        ->and(DB::table('term_translations')->where('term_id', REPAIR_TERM)->count())->toBe(2);
});

/**
 * The two repairs, side by side, on one term — which is exactly how the live data is shaped.
 */
it('deletes the surplus foreign translation and re-translates the example', function () {
    seedPoisonedTerm();
    $before = DB::table('terms')->where('id', REPAIR_TERM)->value('updated_at');
    $repairer = new ScriptedTranslationRepairer(['Важно, чтобы команда понимала друг друга.']);

    $report = repairHandler($repairer)(new RepairContentLanguage(new LanguageCode('ru'), apply: true));

    expect(array_map(static fn ($r): string => $r->action, $report))->toBe(['dropped', 'retranslated'])
        // One model call: dropping a surplus row is free.
        ->and($repairer->calls)->toBe(1);

    $translations = DB::table('term_translations')->where('term_id', REPAIR_TERM)->pluck('text')->all();
    expect($translations)->toBe(['быть на одной волне']);

    $example = DB::table('term_examples')->where('id', REPAIR_EXAMPLE)->first();
    // The double appends « (пример)» to an example translation, so this asserts the EXAMPLE field
    // was written from the example answer and not from the term one.
    expect($example?->sentence_translation)->toBe('Важно, чтобы команда понимала друг друга. (пример)')
        // The English sentence is untouched, so anything built from it (distractors, the pinned
        // card the learner is looking at) still refers to text that exists.
        ->and($example?->sentence)->toBe('It is important for the team to be on the same page.');

    // The lesson of 46f9076: a repair the delta sync cannot see is a repair the phone never gets.
    expect(DB::table('terms')->where('id', REPAIR_TERM)->value('updated_at'))->not->toBe($before);
});

it('re-translates instead of deleting when the foreign row is the only translation', function () {
    seedPoisonedTerm(withRussianTranslation: false);
    $repairer = new ScriptedTranslationRepairer(['быть на одной волне', 'Важно понимать друг друга.']);

    $report = repairHandler($repairer)(new RepairContentLanguage(new LanguageCode('ru'), apply: true));

    expect(array_map(static fn ($r): string => $r->action, $report))->toBe(['retranslated', 'retranslated'])
        ->and(DB::table('term_translations')->where('term_id', REPAIR_TERM)->pluck('text')->all())
        ->toBe(['быть на одной волне']);
});

it('reports a row it could not fix instead of writing the model’s second bad answer', function () {
    seedPoisonedTerm();
    // Both answers still Ukrainian: the example translation must stay as it was, not be overwritten
    // with something equally wrong.
    $repairer = new ScriptedTranslationRepairer(['Важливо розуміти одне одного', 'Знову українською']);

    $report = repairHandler($repairer)(new RepairContentLanguage(new LanguageCode('ru'), apply: true));

    $example = array_values(array_filter($report, static fn ($r): bool => $r->field === 'example_translation'))[0];
    expect($example->action)->toBe('unfixable')
        ->and($example->after)->toBeNull()
        ->and(DB::table('term_examples')->where('id', REPAIR_EXAMPLE)->value('sentence_translation'))
        ->toBe('Важливо, щоб команда розуміла одне одного.');
});

it('ignores content nobody can reach', function () {
    seedPoisonedTerm();
    DB::table('collection_items')->where('term_id', REPAIR_TERM)->update(['deleted_at' => now()]);

    expect(repairHandler(new ScriptedTranslationRepairer([]))(new RepairContentLanguage(new LanguageCode('ru'))))
        ->toBe([]);
});

/**
 * docs/ua-audit.md: 118 of 140 poisoned terms are orphans, deliberately outside the default
 * reachable sweep. --orphans is the complement scope that reaches them for the one-off cleanup.
 */
it('finds content nobody can reach when swept as orphans, and reachable sweep still ignores it', function () {
    seedPoisonedTerm();
    DB::table('collection_items')->where('term_id', REPAIR_TERM)->update(['deleted_at' => now()]);

    $reachable = repairHandler(new ScriptedTranslationRepairer([]))(new RepairContentLanguage(new LanguageCode('ru')));
    $orphans = repairHandler(new ScriptedTranslationRepairer([]))(new RepairContentLanguage(new LanguageCode('ru'), orphans: true));

    expect($reachable)->toBe([])
        ->and($orphans)->toHaveCount(2)
        ->and(array_map(static fn ($r): string => $r->action, $orphans))->toBe(['planned', 'planned']);
});

it('excludes a term that sits in even one live collection from the orphan sweep', function () {
    seedPoisonedTerm();

    $orphans = repairHandler(new ScriptedTranslationRepairer([]))(new RepairContentLanguage(new LanguageCode('ru'), orphans: true));

    expect($orphans)->toBe([]);
});

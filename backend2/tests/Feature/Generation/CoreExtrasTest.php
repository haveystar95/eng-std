<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Port\PromptSource;
use App\Modules\Generation\Application\Service\ContentContract;
use App\Modules\Generation\Domain\Service\EnrichmentValidator;
use App\Modules\Generation\Domain\ValueObject\PromptShape;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * SYN-1d — the two gates the core's per-pair products pass, and the switches over them.
 *
 * The pilot numbers behind these rules are in `docs/syn-1-findings.md` §8: the transliteration is
 * judged by a question a machine can answer (which alphabet is this written in) and passed at 100%;
 * the synonym is judged by one it cannot (is this the same thing or a kind of it) and did not.
 */
it('accepts a hint written in the support language\'s own alphabet', function () {
    $v = new EnrichmentValidator();

    expect($v->transliterationFor('ru', 'комо эстас'))->toBe('комо эстас')
        ->and($v->transliterationFor('ru', 'чек-ин'))->toBe('чек-ин')
        ->and($v->transliterationFor('en', 'komo estas'))->toBe('komo estas');
});

it('refuses a hint carrying the alphabet the learner cannot read', function () {
    $v = new EnrichmentValidator();

    // One Latin word in a Russian hint defeats the field for the only reader it exists for — so
    // this is stricter than the majority rule the translation barrier uses («пароль от Wi-Fi»).
    expect($v->transliterationFor('ru', 'комо estás'))->toBeNull()
        ->and($v->transliterationFor('ru', 'como estas'))->toBeNull()
        ->and($v->transliterationFor('en', 'комо эстас'))->toBeNull();
});

it('refuses a hint that is an annotation rather than a reading', function () {
    $v = new EnrichmentValidator();

    expect($v->transliterationFor('ru', 'комо эстас (исп.)'))->toBeNull()
        ->and($v->transliterationFor('ru', 'ко́мо эста́с [2]'))->toBeNull()
        ->and($v->transliterationFor('ru', ''))->toBeNull()
        ->and($v->transliterationFor('ru', null))->toBeNull();
});

it('has no opinion about a language whose script it was never taught', function () {
    // Silence is a pass everywhere else in LanguagePurity, and an invented alphabet would be worse
    // than none: a support language the catalogue names but this check does not know still gets a
    // hint rather than losing the field entirely.
    expect((new EnrichmentValidator())->transliterationFor('xx', 'whatever this is'))->toBe('whatever this is');
});

it('refuses a synonym that is a KIND of the term — the rule three prompts could not teach', function () {
    $v = new EnrichmentValidator();

    // Measured on the SYN-1a pilot rows: kills 4 of 5 bad on v14.1 and 3 of 3 on v14.2 — and costs
    // 4 genuine ones in the same set. See the findings doc; the trade is deliberate.
    [$kept] = $v->synonymsFor(['bank account'], ['savings account']);
    expect($kept)->toBe([]);

    [$kept] = $v->synonymsFor(['credit card'], ['charge card']);
    expect($kept)->toBe([]);

    // A single word cannot share a head with anything, and that is where most real synonyms live.
    [$kept] = $v->synonymsFor(['purpose'], ['goal']);
    expect($kept)->toBe(['goal']);
});

it('ships the reading hint ON and the synonym OFF — the pilot decided each separately', function () {
    expect(config('services.generation.write_transliteration'))->toBeTrue()
        ->and(config('services.generation.write_synonyms'))->toBeFalse();
});

it('gives the other readings a switch of their OWN, so the worse number stops deciding for the better', function () {
    // They shared the synonym flag until SYN-1e measured them apart: 67% clean synonyms against 29%
    // clean other readings with 29% wrong (`build` → «строить» on a noun card). One flag meant the
    // synonyms could never be switched on without the field that failed its threshold riding along.
    expect(config('services.generation.write_other_translations'))->toBeFalse()
        ->and(config('services.generation.write_synonyms'))->toBeFalse();

    // Two keys, not one read twice — the whole point of the split.
    config(['services.generation.write_synonyms' => true]);
    expect(config('services.generation.write_other_translations'))->toBeFalse();
});

it('declares the v15 extras by version NUMBER, so v9 is not asked for fields its prompt never mentions', function () {
    $contract = app(ContentContract::class);
    $extras = ['synonyms', 'other_translations', 'transliteration'];

    $props = fn (string $v): array => array_keys(
        $contract->schema(PromptShape::Full, $v)['properties']['items']['items']['properties'],
    );

    // The bug this pins: written as a string compare, «v15» <= «v9» is TRUE — «1» sorts before «9» —
    // so every core version from v2 to v9 had three properties declared for it. Strict Structured
    // Outputs makes each declared property REQUIRED, so those versions were forced to emit fields
    // their prompt never explains. v10 and up compared correctly by accident, which is why nothing
    // looked wrong from the outside.
    expect($props('v9'))->not->toContain('synonyms')
        ->and($props('v9'))->not->toContain('transliteration')
        ->and($props('v11.1'))->not->toContain('synonyms');

    // At the floor and past it the fields are there — including a dotted version above it, which is
    // the case an exact match would have dropped.
    foreach ($extras as $field) {
        expect($props('v15'))->toContain($field)
            ->and($props('v15.1'))->toContain($field)
            ->and($props('v16'))->toContain($field);
    }
});

it('asks the core for a DESCRIPTION only from v15.2, where the section explaining it lives', function () {
    $contract = app(ContentContract::class);
    $props = fn (string $v): array => array_keys(
        $contract->schema(PromptShape::Terms, $v)['properties']['items']['items']['properties'],
    );

    // The floor is its own, two versions above the other extras: strict Structured Outputs makes a
    // declared property required, and v15/v15.1 are frozen texts that never mention this field.
    expect($props('v15'))->not->toContain('description')
        ->and($props('v15.1'))->not->toContain('description')
        ->and($props('v15.2'))->toContain('description')
        ->and($props('v16'))->toContain('description');
});

it('gives the description its own section in v15.2 and lists it with the fields', function () {
    $rendered = fn (string $v): string => app(PromptSource::class)->render($v, PromptShape::Terms, [
        'source_lang' => 'Russian', 'target_lang' => 'English', 'levels' => 'A2, B1', 'size' => '10',
    ])->text;

    expect($rendered('v15.2'))
        ->toContain('## `description` — what it means, in English, without naming it')
        // Announced where the field list is read, in the order the sections come — the v14 lesson.
        ->and($rendered('v15.2'))->toContain('`description` — what `text` MEANS')
        // The rule that makes the product usable at all, and the one the deterministic gate enforces.
        ->and($rendered('v15.2'))->toContain('It must NOT contain `text`, or any form of it')
        // v15.1 is untouched: it is what the selectivity pilot measured.
        ->and($rendered('v15.1'))->not->toContain('## `description`');
});

it('runs production on the version that asks for a description', function () {
    expect(config('services.generation.core_prompt_version'))->toBe('v15.2');
});

describe('the ENRICH shape — what a future showcase re-generation would be handed', function () {
    it('asks for the description and the reading, exactly as the collection shape does', function () {
        $contract = app(ContentContract::class);
        $props = array_keys(
            $contract->schema(PromptShape::Enrich, 'v15.2')['properties']['items']['items']['properties'],
        );

        expect($props)->toContain('description')
            ->and($props)->toContain('transliteration')
            ->and($props)->toContain('synonyms');

        $rendered = app(PromptSource::class)->render('v15.2', PromptShape::Enrich, [
            'source_lang' => 'Russian', 'target_lang' => 'English', 'levels' => 'A2, B1', 'size' => '10',
        ])->text;

        expect($rendered)->toContain('## `description` — what it means, in English, without naming it')
            ->and($rendered)->toContain('## `transliteration`');
    });

    it('READS BACK neither of them — the shape asks and the parser drops (READ-2 finding)', function () {
        // Not a pin on desirable behaviour: a pin on the gap, so it cannot be discovered twice.
        //
        // `ContentContract::items()` is the reader for every ENRICH-shape caller — the showcase
        // regeneration and the translation audit — and it builds a CandidateItem, which has no field
        // for a description, a reading or an other-reading. So the enrich path has been BUYING a
        // transliteration on every v15/v15.1 call since the extras shipped and dropping it on the
        // floor, and a description would go the same way. The collection shape does not share this
        // reader (ContentModelCollectionGenerator has its own, and it does read them).
        //
        // Consequence for the owner's question: re-generating the showcase on v15.2 would NOT give
        // old terms their descriptions or readings. Making it do so is a change to this parser, to
        // CandidateItem and to what the showcase handler writes — deliberately not done here, since
        // nothing changes until somebody pays for that run.
        $item = app(ContentContract::class)->items([
            'items' => [[
                'text' => 'invoice',
                'translation' => 'счёт',
                'description' => 'A paper that says how much money you have to pay.',
                'transliteration' => 'инвойс',
                'other_translations' => ['накладная'],
                'synonyms' => ['bill'],
            ]],
        ])[0];

        expect($item->text)->toBe('invoice')
            ->and($item->translation)->toBe('счёт')
            // Parsed and used elsewhere…
            ->and($item->synonyms)->toBe(['bill'])
            // …while these two have nowhere to land at all.
            ->and(property_exists($item, 'description'))->toBeFalse()
            ->and(property_exists($item, 'transliteration'))->toBeFalse();
    });
});

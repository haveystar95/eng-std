<?php

declare(strict_types=1);

use App\Modules\Generation\Domain\ValueObject\PromptShape;
use App\Modules\Generation\Infrastructure\Prompt\PromptLibrary;

function renderPrompt(string $version, PromptShape $shape): string
{
    return (new PromptLibrary())->render($version, $shape, [
        'source_lang' => 'Russian',
        'target_lang' => 'English',
        'levels' => 'A2, B1',
        'size' => '12',
    ])->text;
}

function v10(PromptShape $shape): string
{
    return renderPrompt('v10', $shape);
}

function v11(PromptShape $shape): string
{
    return renderPrompt('v11', $shape);
}

/**
 * The shapes a VERSION declares — not every case of the enum. v11 added `mechanics`, which v10 has
 * no sections for, so a test that swept the enum would be asserting things about a prompt that does
 * not exist.
 *
 * @return list<PromptShape>
 */
function shapesOf(string $version): array
{
    return (new PromptLibrary())->shapesFor($version);
}

/**
 * The reason v10 is composed rather than copied: the rule the last two content sweeps were about
 * must be BYTE-IDENTICAL in all three shapes. A drift here is a bake-off that measures a prompt
 * difference and reports it as a model difference.
 */
it('gives all three shapes the same isomorphism rule, both waves, word for word', function () {
    $section = trim((string) file_get_contents(
        __DIR__ . '/../../../app/Modules/Generation/Infrastructure/Prompt/v10/40-translation-key.md'
    ));
    $rendered = strtr($section, [
        '{{source_lang}}' => 'Russian', '{{target_lang}}' => 'English',
    ]);

    foreach (shapesOf('v10') as $shape) {
        expect(v10($shape))->toContain($rendered);
    }
});

it('carries both waves of the rule — nothing lost and nothing added', function () {
    $terms = v10(PromptShape::Terms);

    expect($terms)
        ->toContain('Wave 1 — nothing may be LOST')
        ->toContain('Tell us about a challenge you faced')
        // Wave 2 is what v9 never had: the mirror defect, worked through on the row that found it.
        ->toContain('Wave 2 — nothing may be ADDED')
        ->toContain('Я **хорошо** лажу со своей командой')
        ->toContain('Nothing added.')
        ->toContain('Accuracy beats fluency');
});

it('makes the example mandatory, which v9 only made non-repetitive', function () {
    expect(v10(PromptShape::Terms))
        ->toContain('Never return an empty `example`')
        ->toContain('cannot be practised there')
        ->toContain('The example exists and teaches');
});

it('asks for options only in the shapes that have them', function () {
    expect(v10(PromptShape::Enrich))->toContain('3 WRONG')
        ->and(v10(PromptShape::Full))->toContain('3 WRONG')
        ->and(v10(PromptShape::Terms))->not->toContain('3 WRONG');

    // …and the self-check gains its options step exactly where the options do.
    expect(v10(PromptShape::Enrich))->toContain('The options are wrong.')
        ->and(v10(PromptShape::Terms))->not->toContain('The options are wrong.');
});

it('tells the enrich shape to copy the given terms and the topic shapes to choose their own', function () {
    expect(v10(PromptShape::Enrich))
        ->toContain('copied verbatim')
        ->toContain('exactly one item per given term')
        ->not->toContain('BALANCED MIX');

    foreach ([PromptShape::Terms, PromptShape::Full] as $shape) {
        expect(v10($shape))->toContain('BALANCED MIX')->not->toContain('GIVEN TERMS block');
    }
});

it('substitutes every placeholder, leaving none in the sent text', function () {
    foreach (['v10', 'v11'] as $version) {
        foreach (shapesOf($version) as $shape) {
            expect(renderPrompt($version, $shape))->not->toContain('{{');
        }
    }
});

it('digests the rendered text, so an edited section without a version bump is visible', function () {
    $library = new PromptLibrary();
    $args = ['source_lang' => 'Russian', 'target_lang' => 'English', 'levels' => 'A2', 'size' => '5'];

    $a = $library->render('v10', PromptShape::Terms, $args);
    $b = $library->render('v10', PromptShape::Terms, $args);
    $other = $library->render('v10', PromptShape::Full, $args);

    expect($a->sha256)->toBe($b->sha256)          // same bytes → same digest
        ->and($a->sha256)->not->toBe($other->sha256) // a different shape is a different prompt
        ->and($a->shortSha())->toHaveLength(12);
});

it('still renders the frozen single-file versions, and refuses to invent shapes for them', function () {
    $library = new PromptLibrary();

    expect($library->render('v9', PromptShape::Terms, ['source_lang' => 'Russian'])->text)
        ->toContain('Keep every part of the term');

    expect(fn () => $library->render('v9', PromptShape::Enrich, []))
        ->toThrow(InvalidArgumentException::class, 'frozen single-file version');
});

it('knows which versions and shapes exist', function () {
    $library = new PromptLibrary();

    expect($library->versions())->toContain('v9')->toContain('v10')->toContain('v11')
        ->and($library->shapesFor('v10'))
        ->toBe([PromptShape::Terms, PromptShape::Enrich, PromptShape::Full])
        // v11 adds the mechanics shape; v10 must not silently answer for it.
        ->and($library->shapesFor('v11'))
        ->toBe([PromptShape::Terms, PromptShape::Mechanics, PromptShape::Enrich, PromptShape::Full])
        ->and($library->shapesFor('v9'))->toBe([PromptShape::Terms]);

    expect(fn () => $library->render('v10', PromptShape::Mechanics, []))
        ->toThrow(InvalidArgumentException::class, "has no 'mechanics' shape");
});

/**
 * v11's headline rule. «back up» → «подниматься обратно из-за засора» is true about the world and
 * useless as a card: nobody reads it and writes `back up` back.
 */
it('v11 forbids a translation that defines the term instead of naming it', function () {
    $terms = v11(PromptShape::Terms);

    expect($terms)
        ->toContain('A DEFINITION instead of a key')
        ->toContain('подниматься обратно из-за засора')
        // The rule names the source language by NAME, not by placeholder — the same lesson v9 learned.
        ->toContain('shortest Russian expression a native would use')
        // …and the same question in the self-check, phrased as something to actually ask.
        ->toContain('A key, not a definition.')
        ->toContain('could someone who does not know this term');
});

it('v11 keeps the collection shape to a core — one example, no options', function () {
    $terms = v11(PromptShape::Terms);

    expect($terms)
        ->toContain('ONE short, natural English sentence')
        ->toContain('a second is paid for and discarded')
        // Machinery is a separate, cheaper call now.
        ->not->toContain('3 WRONG')
        ->not->toContain('`forms`');
});

it('v11 mechanics is forbidden to touch the core and asks only for machinery', function () {
    $mechanics = v11(PromptShape::Mechanics);

    expect($mechanics)
        ->toContain('Do not rewrite the core.')
        ->toContain('Do not invent a new example.')
        ->toContain('3 WRONG')
        ->toContain('other spellings of the term that are also RIGHT')
        // None of the core-writing rules ride along: they would be instructions to do the one
        // thing this shape must not do, and they are most of the prompt's length.
        ->not->toContain('A DEFINITION instead of a key')
        ->not->toContain('must EXPAND the term')
        ->not->toContain('BALANCED MIX');
});

it('v11 is materially shorter than v10 on the collection shape', function () {
    // The core shape stopped carrying the options rules and the duplicated key restatement, so the
    // call that runs on every generation got cheaper. Asserted as a number so a future edit that
    // quietly re-inflates it has to argue with a test.
    expect(str_word_count(v11(PromptShape::Terms)))
        ->toBeLessThan((int) (str_word_count(v10(PromptShape::Terms)) * 0.95));
});

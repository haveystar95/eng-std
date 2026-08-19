<?php

declare(strict_types=1);

use App\Modules\Generation\Domain\ValueObject\PromptShape;
use App\Modules\Generation\Infrastructure\Prompt\PromptLibrary;

function v10(PromptShape $shape): string
{
    return (new PromptLibrary())->render('v10', $shape, [
        'source_lang' => 'Russian',
        'target_lang' => 'English',
        'levels' => 'A2, B1',
        'size' => '12',
    ])->text;
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

    foreach (PromptShape::cases() as $shape) {
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
    foreach (PromptShape::cases() as $shape) {
        expect(v10($shape))->not->toContain('{{');
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

    expect($library->versions())->toContain('v9')->toContain('v10')
        ->and($library->shapesFor('v10'))->toBe(PromptShape::cases())
        ->and($library->shapesFor('v9'))->toBe([PromptShape::Terms]);
});

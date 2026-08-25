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

/**
 * v11.1's one rule. The definition check beside it cannot reach these: «испытавший облегчение» is
 * short, carries no explanatory connective and is perfectly accurate about `relieved` — and no
 * learner writes `relieved` back from it, because nobody says it.
 */
it('v11.1 forbids a participial calque and asks for what a person actually says', function () {
    $terms = renderPrompt('v11.1', PromptShape::Terms);

    expect($terms)
        ->toContain('A CALQUE instead of what a person actually says')
        ->toContain('испытавший облегчение')
        ->toContain('«с облегчением»')
        // The rule is a TEST a writer can apply, not an adjective list to memorise.
        ->toContain('say your translation out loud')
        ->toContain('sounds like a case report rather than like a person')
        // …and the self-check asks it again where the reading actually happens.
        ->toContain('A person would say this.');

    // The rule rides with the key, so every shape that writes a key carries it — and no shape that
    // was forbidden to write one does.
    expect(renderPrompt('v11.1', PromptShape::Enrich))->toContain('A CALQUE instead of what a person actually says')
        ->and(renderPrompt('v11.1', PromptShape::Full))->toContain('A CALQUE instead of what a person actually says')
        ->and(renderPrompt('v11.1', PromptShape::Mechanics))->not->toContain('A CALQUE instead of');
});

it('leaves v11 frozen — 39 live terms record it as their passport', function () {
    expect(v11(PromptShape::Terms))
        ->not->toContain('A CALQUE instead of')
        ->not->toContain('испытавший облегчение');

    // Same shapes, so a rollback of the config value is a rollback and not a crash.
    expect(shapesOf('v11.1'))->toBe(shapesOf('v11'));
});

/**
 * v15.1's whole point. v15 asked for «0–3» synonyms and explained how to choose a good one, but
 * never said that NONE is the ordinary answer — so the model filled the slot: 67% clean, 26%
 * arguable, 7% wrong on the pilot (docs/syn-1-findings.md §8), against a threshold of 85/5.
 */
it('v15.1 makes an empty synonym list the default and adds the register test', function () {
    foreach (shapesOf('v15.1') as $shape) {
        $text = renderPrompt('v15.1', $shape);

        expect($text)
            // The default, in the words a model can act on rather than as a permitted range.
            ->toContain('an empty list is the normal answer')
            ->toContain('If you hesitate over a candidate, do not')
            // The third test — what the arguable quarter failed: `amazed` for «удивлённый».
            ->toContain('Test 3 — same strength, same colour')
            ->toContain('«поражённый»')
            ->toContain('«завистливый»')
            // Hyponyms and hyperonyms stay banned; the deterministic half of that rule lives in
            // EnrichmentValidator::synonymsFor() and is untouched.
            ->toContain('a TYPE of the')
            ->toContain('a BROADER word')
            // A multi-word term is told outright what its expected answer is.
            ->toContain('unless the WHOLE expression')
            ->toContain('the expected answer for one is empty')
            // …and the other readings are narrowed from «ambiguous» to a different MEANING of the
            // card's own part of speech — the reading that produced «опрокинутый» for `upset`.
            ->toContain('a genuinely DIFFERENT meaning, or nothing')
            ->toContain('another PART OF SPEECH than the card');
    }
});

it('v15.1 does not touch the transliteration section — 49/49 is not repaired', function () {
    $v15 = trim((string) file_get_contents(
        __DIR__ . '/../../../app/Modules/Generation/Infrastructure/Prompt/v15/21-extras.md'
    ));
    $section = substr($v15, (int) strpos($v15, '## `transliteration`'));

    expect(renderPrompt('v15.1', PromptShape::Terms))
        ->toContain(strtr($section, ['{{source_lang}}' => 'Russian', '{{target_lang}}' => 'English']));
});

it('leaves v15 frozen — its pilot is what the threshold decision rests on', function () {
    expect(renderPrompt('v15', PromptShape::Terms))
        ->not->toContain('Test 3 — same strength, same colour')
        ->not->toContain('an empty list is the normal answer');

    // Same shapes, so a rollback of the config value is a rollback and not a crash.
    expect(shapesOf('v15.1'))->toBe(shapesOf('v15'));
});

it('v11 is materially shorter than v10 on the collection shape', function () {
    // The core shape stopped carrying the options rules and the duplicated key restatement, so the
    // call that runs on every generation got cheaper. Asserted as a number so a future edit that
    // quietly re-inflates it has to argue with a test.
    expect(str_word_count(v11(PromptShape::Terms)))
        ->toBeLessThan((int) (str_word_count(v10(PromptShape::Terms)) * 0.95));
});

<?php

declare(strict_types=1);

use App\Modules\Generation\Infrastructure\Prompt\PracticeDialogInstructions;

it('substitutes the lesson into the prompt template', function () {
    $template = "TOPIC: {{topic}}\nLEVEL: {{level}}\nSPEAK: {{target_language}}\n"
        . "NATIVE: {{native_language}}\nWORDS:\n{{target_words}}";

    $rendered = (new PracticeDialogInstructions())->render($template, [
        'topic' => 'At the bank',
        'level' => 'B1',
        'target' => 'en',
        'native' => 'ru',
        'target_words' => [
            ['term_id' => 't1', 'text' => 'withdraw cash', 'forms' => ['withdraw cash']],
            ['term_id' => 't2', 'text' => 'account balance', 'forms' => ['account balance']],
        ],
    ]);

    expect($rendered)
        ->toContain('TOPIC: At the bank')
        ->toContain('LEVEL: B1')
        ->toContain('SPEAK: English')   // language code resolved to a name
        ->toContain('NATIVE: Russian')
        ->toContain('- withdraw cash')
        ->toContain('- account balance');
});

it('splits multi-word phrases (agent says) from single words (learner produces)', function () {
    $template = "PHRASES:\n{{agent_phrases}}\n---\nWORDS:\n{{elicit_words}}";

    $rendered = (new PracticeDialogInstructions())->render($template, [
        'topic' => 'At the bank', 'level' => 'B1', 'target' => 'en', 'native' => 'ru',
        'target_words' => [
            ['term_id' => 't1', 'text' => 'withdraw cash', 'forms' => ['withdraw cash']],
            ['term_id' => 't2', 'text' => 'account', 'forms' => ['account']],
            ['term_id' => 't3', 'text' => 'how would you like it', 'forms' => ['how would you like it']],
        ],
    ]);

    [$phrases, $words] = explode('---', $rendered);
    expect($phrases)->toContain('- withdraw cash')->toContain('- how would you like it')->not->toContain('- account')
        ->and($words)->toContain('- account')->not->toContain('- withdraw cash');
});

it('renders a placeholder when there are no target words', function () {
    $rendered = (new PracticeDialogInstructions())->render('{{target_words}}', [
        'topic' => 'x', 'level' => 'A1', 'target' => 'en', 'native' => 'ru', 'target_words' => [],
    ]);

    expect($rendered)->toBe('- (none)');
});

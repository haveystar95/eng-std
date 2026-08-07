<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Service\DialogCoverage;
use App\Modules\Generation\Domain\ValueObject\TranscriptLine;
use App\Modules\Generation\Domain\ValueObject\TranscriptRole;
use App\Modules\Shared\Domain\Service\LexicalNormalizer;

function coverageLine(string $role, string $text): TranscriptLine
{
    return new TranscriptLine(TranscriptRole::from($role), $text, 0);
}

function coverage(): DialogCoverage
{
    return new DialogCoverage(new LexicalNormalizer());
}

it('marks a target word used when a form appears in a user line, case-insensitively', function () {
    $target = [['term_id' => 't1', 'text' => 'withdraw cash', 'forms' => ['withdraw cash']]];
    $lines = [
        coverageLine('assistant', 'How can I help you today?'),
        coverageLine('user', 'I would like to Withdraw Cash, please.'),
    ];

    $result = coverage()->evaluate($target, $lines);

    expect($result[0]->used)->toBeTrue();
});

it('does not count the word when only the assistant said it', function () {
    $target = [['term_id' => 't1', 'text' => 'withdraw cash', 'forms' => ['withdraw cash']]];
    $lines = [
        coverageLine('assistant', 'Would you like to withdraw cash?'),
        coverageLine('user', 'No thank you.'),
    ];

    expect(coverage()->evaluate($target, $lines)[0]->used)->toBeFalse();
});

it('is word-boundary aware — "cash" does not match inside "cashier"', function () {
    $target = [['term_id' => 't1', 'text' => 'cash', 'forms' => ['cash']]];
    $lines = [coverageLine('user', 'I spoke to the cashier at the desk.')];

    expect(coverage()->evaluate($target, $lines)[0]->used)->toBeFalse();
});

it('matches through the shared normaliser (contraction expansion)', function () {
    $target = [['term_id' => 't1', 'text' => 'I would like', 'forms' => ['I would like']]];
    $lines = [coverageLine('user', "I'd like to open an account.")];

    expect(coverage()->evaluate($target, $lines)[0]->used)->toBeTrue();
});

it('matches any accepted form, not only the display text', function () {
    $target = [['term_id' => 't1', 'text' => 'colour', 'forms' => ['colour', 'color']]];
    $lines = [coverageLine('user', 'What color is it?')];

    expect(coverage()->evaluate($target, $lines)[0]->used)->toBeTrue();
});

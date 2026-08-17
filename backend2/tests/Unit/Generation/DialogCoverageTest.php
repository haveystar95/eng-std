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

it('counts a single-word target only when the LEARNER produced it', function () {
    $target = [['term_id' => 't1', 'text' => 'account', 'forms' => ['account']]];

    // Learner said it → covered.
    expect(coverage()->evaluate($target, [
        coverageLine('assistant', 'What would you like to do?'),
        coverageLine('user', 'I want to open an account.'),
    ])[0]->used)->toBeTrue();

    // Only the assistant said it → NOT covered (the learner must produce single words).
    expect(coverage()->evaluate($target, [
        coverageLine('assistant', 'Would you like to open an account?'),
        coverageLine('user', 'Yes please.'),
    ])[0]->used)->toBeFalse();
});

it('counts a multi-word phrase when ANY speaker said it', function () {
    $target = [['term_id' => 't1', 'text' => 'how would you like it', 'forms' => ['how would you like it']]];

    // The agent poses the phrase → covered (the learner only has to understand it).
    expect(coverage()->evaluate($target, [
        coverageLine('assistant', 'How would you like it, in tens or twenties?'),
        coverageLine('user', 'In twenties, please.'),
    ])[0]->used)->toBeTrue();
});

it('is word-boundary aware — "cash" does not match inside "cashier"', function () {
    $target = [['term_id' => 't1', 'text' => 'cash', 'forms' => ['cash']]];
    $lines = [coverageLine('user', 'I spoke to the cashier at the desk.')];

    expect(coverage()->evaluate($target, $lines)[0]->used)->toBeFalse();
});

it('matches a multi-word target through the shared normaliser (contraction expansion)', function () {
    $target = [['term_id' => 't1', 'text' => 'I would like', 'forms' => ['I would like']]];
    $lines = [coverageLine('user', "I'd like to open an account.")];

    expect(coverage()->evaluate($target, $lines)[0]->used)->toBeTrue();
});

it('matches any accepted form, not only the display text', function () {
    $target = [['term_id' => 't1', 'text' => 'colour', 'forms' => ['colour', 'color']]];
    $lines = [coverageLine('user', 'What color is it?')];

    expect(coverage()->evaluate($target, $lines)[0]->used)->toBeTrue();
});

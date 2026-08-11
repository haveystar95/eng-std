<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Query\TermAnswerKeyReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Offline grading is only correct if the device holds the same accepted set the server does. These
 * tests pin both ends of that: the server's answer key folds the variants in, and `/sync` carries
 * them to the client. A regression on either side surfaces as «Не то» on an answer the scheduler
 * counts as right — the exact failure the invariant "the client is never stricter than the server"
 * exists to prevent.
 */
function seedEnrichmentFor(string $termId): void
{
    DB::table('term_examples')->insert([
        'id' => str_pad('01VAREX', 26, '0'),
        'term_id' => $termId,
        'sentence' => 'Excuse me, this is my seat.',
        'sentence_translation' => 'Простите, это моё место.',
        'source' => 'curated',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('term_accepted_variants')->insert([
        'id' => str_pad('01VARV1', 26, '0'),
        'term_id' => $termId,
        'text' => 'that is my seat',
        'note' => 'this/that — оба верны',
        'generator_version' => 'enrich-v1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('example_distractors')->insert([
        'id' => str_pad('01VARD1', 26, '0'),
        'example_id' => str_pad('01VAREX', 26, '0'),
        'sentence' => 'Excuse me, this is my place.',
        'error_type' => 'false_friend',
        'error_span' => 'place',
        'correction' => 'seat',
        'generator_version' => 'enrich-v1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('folds accepted variants into the server answer key', function () {
    [$user] = learner();
    [, $termId] = seedCollectionWith($user, 'this is my seat', 'это моё место');
    seedEnrichmentFor($termId);

    $key = app(TermAnswerKeyReader::class)->byIds([TermId::fromString($termId)])[$termId];

    expect($key->accepted)->toBe(['this is my seat', 'that is my seat']);
});

it('carries accepted variants and example distractors to the client through /sync', function () {
    [$user, $token] = learner();
    [, $termId] = seedCollectionWith($user, 'this is my seat', 'это моё место');
    seedEnrichmentFor($termId);

    $delta = sync($this, $token);
    $term = collect($delta['changes']['terms'])->firstWhere('id', $termId);

    expect($term)->not->toBeNull()
        ->and($term['accepted_variants'])->toBe(['that is my seat'])
        ->and($term['example_distractors'])->toHaveCount(1)
        ->and($term['example_distractors'][0]['error_type'])->toBe('false_friend')
        ->and($term['example_distractors'][0]['error_span'])->toBe('place')
        ->and($term['example_distractors'][0]['correction'])->toBe('seat');
});

it('sends empty lists, not null, for a term with no enrichment', function () {
    [$user, $token] = learner();
    [, $termId] = seedCollectionWith($user, 'plain term', 'простой термин');

    $delta = sync($this, $token);
    $term = collect($delta['changes']['terms'])->firstWhere('id', $termId);

    expect($term['accepted_variants'])->toBe([])
        ->and($term['example_distractors'])->toBe([]);
});

it('does not leak another term\'s distractors when several terms sync together', function () {
    [$user, $token] = learner();
    [, $termId] = seedCollectionWith($user, 'this is my seat', 'это моё место');
    seedEnrichmentFor($termId);
    $otherId = seedWordFor($user, 'window seat', 'место у окна');

    $delta = sync($this, $token);
    $other = collect($delta['changes']['terms'])->firstWhere('id', $otherId);

    expect($other['example_distractors'])->toBe([])
        ->and($other['accepted_variants'])->toBe([]);
});

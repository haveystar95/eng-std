<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Command\BuildTermEnrichmentsHandler;
use App\Modules\Generation\Application\Service\ExampleReplacement;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Audit A1. A replaced example used to delete its row, and `example_distractors.example_id` is
 * `cascadeOnDelete` — so every distractor the станок had been paid for died with a write whose only
 * job was to fix a sentence. The row is updated in place now, and what hangs off it is judged rather
 * than destroyed: a distractor survives while it is still a one-place break OF the sentence beside it.
 */
function seedExampleWithDistractors(string $sentence): array
{
    [$user] = learner();
    [, $termId] = seedCollectionWith($user, 'withdraw cash', 'снять наличные');

    $exampleId = Ulid::generate();
    seedExample([
        'id' => $exampleId, 'term_id' => $termId, 'sentence' => $sentence,
        'translation' => 'Мне нужно снять наличные.', 'source' => 'ai',
    ]);

    return [$termId, $exampleId];
}

function seedDistractor(string $exampleId, string $sentence, string $span, string $correction): void
{
    DB::table('example_distractors')->insert([
        'id' => Ulid::generate(), 'example_id' => $exampleId, 'sentence' => $sentence,
        'error_type' => 'article', 'error_span' => $span, 'correction' => $correction,
        'generator_version' => 'enrich-v1', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('keeps the distractors that still describe the example when only its wording is touched up', function () {
    [$termId, $exampleId] = seedExampleWithDistractors('I need to withdraw cash from the account.');
    seedDistractor($exampleId, 'I need to withdraw cash from a account.', 'a account', 'the account');

    // The repair fixes the sentence's punctuation — the distractor is still exactly one place away.
    app(ExampleReplacement::class)->apply(
        TermId::fromString($termId),
        'I need to withdraw cash from the account.',
        'Мне нужно снять наличные со счёта.',
        'ru',
        'ex-regen.v2',
        'gpt-4o-mini',
    );

    expect(DB::table('example_distractors')->where('example_id', $exampleId)->count())
        ->toBe(1, 'the distractor still repairs into the example and was thrown away anyway');

    // Same row, same id: nothing cascaded, so nothing else hanging off the example can have.
    expect(DB::table('term_examples')->where('term_id', $termId)->pluck('id')->all())
        ->toBe([$exampleId]);
});

it('drops the distractors the new sentence has orphaned, and re-opens the term for the станок', function () {
    [$termId, $exampleId] = seedExampleWithDistractors('I need to withdraw cash from the account.');
    seedDistractor($exampleId, 'I need to withdraw cash from a account.', 'a account', 'the account');
    DB::table('term_enrichment_versions')->insert([
        'term_id' => $termId, 'generator_version' => BuildTermEnrichmentsHandler::VERSION, 'created_at' => now(),
    ]);

    app(ExampleReplacement::class)->apply(
        TermId::fromString($termId),
        'She withdrew cash before the shops closed.',
        'Она сняла наличные до закрытия магазинов.',
        'ru',
        'ex-regen.v2',
        'gpt-4o-mini',
    );

    expect(DB::table('example_distractors')->where('example_id', $exampleId)->count())
        ->toBe(0, 'a wrong option built from a sentence the card no longer shows is a lie');

    // Un-marked, so the next pass builds a fresh set instead of leaving the card with none for good.
    expect(DB::table('term_enrichment_versions')->where('term_id', $termId)->count())->toBe(0);
});

it('leaves the term marked when nothing was orphaned', function () {
    [$termId, $exampleId] = seedExampleWithDistractors('I need to withdraw cash from the account.');
    seedDistractor($exampleId, 'I need to withdraw cash from a account.', 'a account', 'the account');
    DB::table('term_enrichment_versions')->insert([
        'term_id' => $termId, 'generator_version' => BuildTermEnrichmentsHandler::VERSION, 'created_at' => now(),
    ]);

    app(ExampleReplacement::class)->apply(
        TermId::fromString($termId),
        'I need to withdraw cash from the account.',
        'Мне нужно снять наличные со счёта.',
        'ru',
        'ex-regen.v2',
        'gpt-4o-mini',
    );

    expect(DB::table('term_enrichment_versions')->where('term_id', $termId)->count())
        ->toBe(1, 'a no-op replacement must not re-send a finished term to the model');
});

/**
 * Audit A3: the example writer stamped `source = 'user'` on a sentence a model had just written, and
 * recorded neither the prompt version nor the model. NULL provenance is the app's own alarm for
 * «a writer produced content without saying where it came from» — a writer that HAS the facts and
 * drops them is the false negative that alarm cannot survive.
 */
it('records who wrote a replaced example: the source, the prompt version and the model', function () {
    [$termId] = seedExampleWithDistractors('An old sentence.');

    app(ExampleReplacement::class)->apply(
        TermId::fromString($termId),
        'She withdrew cash before the shops closed.',
        'Она сняла наличные до закрытия магазинов.',
        'ru',
        'ex-regen.v2',
        'gpt-4o-mini',
    );

    expect(DB::table('term_examples')->where('term_id', $termId)->first(['source', 'prompt_version', 'generation_model']))
        ->source->toBe('ai')
        ->prompt_version->toBe('ex-regen.v2')
        ->generation_model->toBe('gpt-4o-mini');
});

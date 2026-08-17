<?php

declare(strict_types=1);

use App\Modules\Collections\Application\Command\CreateCustomCollection;
use App\Modules\Collections\Application\Command\CreateCustomCollectionHandler;
use App\Modules\Generation\Application\Command\EnrichTerm;
use App\Modules\Generation\Application\Command\EnrichTermHandler;
use App\Modules\Generation\Application\Port\ImageSearchPort;
use App\Modules\Generation\Application\Port\TermEnricherPort;
use App\Modules\Generation\Infrastructure\Adapter\FakePexelsImageSearch;
use App\Modules\Generation\Infrastructure\Adapter\FakeTermEnricher;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Deterministic, no network: fake enricher + a "found" image. Queue is sync (phpunit.xml),
    // so the dispatched EnrichTermJob runs inline within the request.
    $this->app->instance(TermEnricherPort::class, new FakeTermEnricher());
    $this->app->instance(ImageSearchPort::class, new FakePexelsImageSearch(FakePexelsImageSearch::FOUND));
});

function enrichUser(): array
{
    $user = User::factory()->create();

    return [$user, $user->createToken('device')->plainTextToken];
}

function enrichCollection(User $owner): string
{
    return app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        ownerId: UserId::fromString($owner->id),
        title: 'Mine',
        sourceLang: new LanguageCode('ru'),
        targetLang: new LanguageCode('en'),
    ))->value;
}

it('adds a word without a translation and enriches it end-to-end', function () {
    [$user, $token] = enrichUser();
    $collectionId = enrichCollection($user);

    $termId = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/collections/{$collectionId}/items", ['text' => 'overwhelm'])
        ->assertStatus(201)
        ->json('data.items.0.term_id');

    // Translation, transcription, example and a photo were filled in from the LLM + Pexels.
    expect(DB::table('term_translations')->where('term_id', $termId)->where('lang', 'ru')->count())->toBe(1)
        ->and(DB::table('terms')->where('id', $termId)->value('ipa'))->not->toBeNull()
        ->and(DB::table('term_examples')->where('term_id', $termId)->count())->toBe(1)
        ->and(DB::table('terms')->where('id', $termId)->value('image_url'))->not->toBeNull();

    // The spend was recorded (tokens + model; cost is null only because the fake model isn't priced).
    $spend = DB::table('term_enrichments')->where('term_id', $termId)->first();
    expect($spend)->not->toBeNull()
        ->and($spend->model)->toBe('fake')
        ->and((int) $spend->tokens_in)->toBe(40);
});

it('does not enrich a word that was added with a translation', function () {
    [$user, $token] = enrichUser();
    $collectionId = enrichCollection($user);

    $termId = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/collections/{$collectionId}/items", ['text' => 'apple', 'translation' => 'яблоко'])
        ->assertStatus(201)
        ->json('data.items.0.term_id');

    expect(DB::table('term_enrichments')->where('term_id', $termId)->count())->toBe(0);
});

it('is idempotent — re-running enrichment does not re-spend on the model', function () {
    [$user, $token] = enrichUser();
    $collectionId = enrichCollection($user);

    $termId = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/collections/{$collectionId}/items", ['text' => 'overwhelm'])
        ->json('data.items.0.term_id');

    // Run the whole enrichment again — the term now has a translation, so the LLM step is skipped.
    app(EnrichTermHandler::class)(new EnrichTerm(TermId::fromString($termId), new LanguageCode('ru')));

    expect(DB::table('term_enrichments')->where('term_id', $termId)->count())->toBe(1)          // no second spend
        ->and(DB::table('term_translations')->where('term_id', $termId)->count())->toBe(1);     // no duplicate translation
});

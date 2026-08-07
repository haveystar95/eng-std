<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\User;
use Database\Seeders\StoreContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('seeds the curated store catalogue and serves it through the store endpoint', function () {
    $this->seed(StoreContentSeeder::class);

    $curated = DB::table('collections')
        ->where('source', 'curated')->where('type', 'system')->where('visibility', 'public');
    expect($curated->count())->toBe(17)
        ->and((clone $curated)->where('is_premium', true)->count())->toBe(4)
        ->and((clone $curated)->whereNull('image_url')->count())->toBe(0) // every deck has a cover
        ->and(DB::table('terms')->count())->toBeGreaterThan(300)
        ->and(DB::table('term_translations')->count())->toBeGreaterThan(300);

    $user = User::factory()->create();
    $token = $user->createToken('t')->plainTextToken;

    $data = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/store/collections?source_lang=ru&target_lang=en&limit=50')
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(17)
        ->and($data[0]['image_url'])->not->toBeNull()
        ->and($data[0]['level'])->not->toBeNull(); // level derives from the seeded term CEFR
});

it('is idempotent — re-seeding duplicates no collections, terms or items', function () {
    $this->seed(StoreContentSeeder::class);
    $collections = DB::table('collections')->count();
    $terms = DB::table('terms')->count();
    $items = DB::table('collection_items')->count();

    $this->seed(StoreContentSeeder::class);

    expect(DB::table('collections')->count())->toBe($collections)
        ->and(DB::table('terms')->count())->toBe($terms)
        ->and(DB::table('collection_items')->count())->toBe($items);
});

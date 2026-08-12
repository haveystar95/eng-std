<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\Profile;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * A term with two examples (so pinning is observable), distractors on the pinned one, accepted
 * variants, and an enrichment finding.
 *
 * @return array{0: string, 1: string, 2: string, 3: string}  [adminToken, termId, pinnedExampleId, secondExampleId]
 */
function termDetailFixture(): array
{
    $user = User::factory()->create(['email' => 'terms@wt.test']);
    Profile::create(['user_id' => $user->id, 'daily_goal' => 5, 'tier' => 'free']);
    [, $termId] = adminSeedTerm($user, 'Banking', 'account', 'счёт');

    // The pinned example is the lowest id, so generate ids in order deliberately.
    $pinned = Ulid::generate();
    $second = Ulid::generate();
    DB::table('term_examples')->where('term_id', $termId)->delete();
    DB::table('term_examples')->insert([
        ['id' => $pinned, 'term_id' => $termId, 'sentence' => 'I opened a bank account.',
            'sentence_translation' => 'Я открыл счёт в банке.', 'source' => 'ai',
            'created_at' => now(), 'updated_at' => now()],
        ['id' => $second, 'term_id' => $termId, 'sentence' => 'The account is empty.',
            'sentence_translation' => 'Счёт пуст.', 'source' => 'ai',
            'created_at' => now(), 'updated_at' => now()],
    ]);

    DB::table('example_distractors')->insert([
        'id' => Ulid::generate(), 'example_id' => $pinned,
        'sentence' => 'I opened a bank account on the bank.',
        'error_type' => 'preposition', 'error_span' => 'on the bank', 'correction' => 'at the bank',
        'generator_version' => 'enrich-v1', 'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('term_accepted_variants')->insert([
        'id' => Ulid::generate(), 'term_id' => $termId, 'text' => 'bank account',
        'note' => 'full form', 'generator_version' => 'enrich-v1',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('enrichment_findings')->insert([
        'id' => Ulid::generate(), 'term_id' => $termId, 'kind' => 'ambiguity', 'field' => 'translation',
        'detail' => 'счёт also means "bill"', 'generator_version' => 'enrich-v1', 'created_at' => now(),
    ]);

    DB::table('term_enrichment_versions')->insert([
        'term_id' => $termId, 'generator_version' => 'enrich-v1', 'created_at' => now(),
    ]);

    DB::table('terms')->where('id', $termId)->update(['image_url' => 'https://img.test/a.jpg', 'cefr' => 'A2']);

    [, $adminToken] = adminActor();

    return [$adminToken, $termId, $pinned, $second];
}

it('returns a term with every relation, the pinned example flagged first', function () {
    [$adminToken, $termId, $pinned] = termDetailFixture();

    $body = test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson("/admin/api/terms/{$termId}")
        ->assertOk()
        ->assertJsonPath('id', $termId)
        ->assertJsonPath('image_url', 'https://img.test/a.jpg')
        ->assertJsonPath('cefr', 'A2')
        ->assertJsonPath('enrichment_version', 'enrich-v1')
        ->json();

    expect($body['examples'])->toHaveCount(2)
        ->and($body['examples'][0]['id'])->toBe($pinned)
        ->and($body['examples'][0]['is_pinned'])->toBeTrue()
        ->and($body['examples'][1]['is_pinned'])->toBeFalse()
        // Distractors hang off the pinned example only — that is where the trainer reads them.
        ->and($body['examples'][0]['distractors'])->toHaveCount(1)
        ->and($body['examples'][0]['distractors'][0]['error_span'])->toBe('on the bank')
        ->and($body['examples'][0]['distractors'][0]['error_type'])->toBe('preposition')
        ->and($body['examples'][0]['distractors'][0]['correction'])->toBe('at the bank')
        ->and($body['examples'][1]['distractors'])->toBeEmpty()
        ->and($body['accepted_variants'][0]['text'])->toBe('bank account')
        ->and($body['findings'][0]['kind'])->toBe('ambiguity')
        ->and($body['findings'][0]['detail'])->toContain('bill')
        ->and($body['translations'][0]['text'])->toBe('счёт')
        ->and($body['collections'][0]['title'])->toBe('Banking');
});

it('renders a term that the enrichment run never touched, with empty arrays', function () {
    $user = User::factory()->create();
    Profile::create(['user_id' => $user->id, 'daily_goal' => 5, 'tier' => 'free']);
    [, $termId] = adminSeedTerm($user, 'Plain', 'apple', 'яблоко');
    [, $adminToken] = adminActor();

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson("/admin/api/terms/{$termId}")
        ->assertOk()
        ->assertJsonPath('accepted_variants', [])
        ->assertJsonPath('findings', [])
        ->assertJsonPath('enrichment_version', null)
        // A term added by hand carries no example at all — the page must survive that too.
        ->assertJsonPath('examples', []);
});

it('404s an unknown term', function () {
    [, $adminToken] = adminActor();

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/admin/api/terms/' . Ulid::generate())
        ->assertNotFound();
});

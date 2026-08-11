<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\Profile;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Walks a listing to the end using only `limit` + `next_cursor`, the way the panel's infinite
 * scroll does, and returns every id it saw.
 *
 * @return list<string>
 */
function walkCursor(string $url, string $token, int $limit = 2): array
{
    $ids = [];
    $cursor = null;
    $guard = 0;

    do {
        $query = $url . (str_contains($url, '?') ? '&' : '?') . 'limit=' . $limit
            . ($cursor !== null ? '&cursor=' . $cursor : '');

        $body = test()->withHeader('Authorization', "Bearer {$token}")
            ->getJson($query)
            ->assertOk()
            ->json();

        foreach ($body['data'] as $row) {
            $ids[] = $row['id'];
        }
        $cursor = $body['meta']['next_cursor'];
    } while ($cursor !== null && ++$guard < 20);

    return $ids;
}

it('pages users by cursor, newest first, without repeating or skipping a row', function () {
    [, $adminToken] = adminActor();
    foreach (range(1, 5) as $i) {
        $user = User::factory()->create(['email' => "u{$i}@wt.test"]);
        Profile::create(['user_id' => $user->id, 'daily_goal' => 5, 'tier' => 'free']);
    }

    $walked = walkCursor('/admin/api/users', $adminToken);

    // Five seeded users, and the admin actor is not one of them (separate `admins` table).
    expect($walked)->toHaveCount(5)
        ->and(array_unique($walked))->toHaveCount(5)
        // id DESC = newest first, and ULIDs sort by creation.
        ->and($walked)->toBe(array_reverse(collect($walked)->sort()->values()->all()));
});

it('keeps offset pagination working for a caller that never sends limit/cursor', function () {
    [, $adminToken] = adminActor();
    foreach (range(1, 3) as $i) {
        $user = User::factory()->create(['email' => "old{$i}@wt.test"]);
        Profile::create(['user_id' => $user->id, 'daily_goal' => 5, 'tier' => 'free']);
    }

    $page = test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/admin/api/users?page=2&per_page=2')
        ->assertOk()
        ->json();

    expect($page['meta']['total'])->toBe(3)
        ->and($page['meta']['page'])->toBe(2)
        ->and($page['meta']['per_page'])->toBe(2)
        ->and($page['data'])->toHaveCount(1)
        // Offset mode never hands out a cursor — that is how the client knows which mode it is in.
        ->and($page['meta']['next_cursor'])->toBeNull();
});

it('pages terms, collections and logs by cursor too', function () {
    $user = User::factory()->create();
    Profile::create(['user_id' => $user->id, 'daily_goal' => 5, 'tier' => 'free']);
    foreach (['apple', 'pear', 'plum'] as $i => $word) {
        adminSeedTerm($user, "Deck {$i}", $word, 'x');
    }
    foreach (range(1, 3) as $i) {
        DB::table('api_request_logs')->insert([
            'id' => Ulid::generate(), 'direction' => 'inbound', 'method' => 'GET',
            'path' => "/api/v1/thing{$i}", 'status' => 200, 'occurred_at' => now(),
        ]);
    }
    [, $adminToken] = adminActor();

    expect(walkCursor('/admin/api/terms', $adminToken))->toHaveCount(3)
        ->and(walkCursor('/admin/api/collections', $adminToken))->toHaveCount(3)
        // Scoped by path: the walk's own admin calls are themselves logged as they run.
        ->and(walkCursor('/admin/api/logs?path=thing', $adminToken))->toHaveCount(3);
});

it('caps the page size so a client cannot ask for the whole table at once', function () {
    [, $adminToken] = adminActor();

    test()->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/admin/api/users?limit=5000')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);
});

<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * THE CLIENT NAMES THE ROLES (DECISIONS п. 147 — the cure that decision itself names).
 *
 * A search request carries a DIRECTION, «what I typed» → «what I want back», and a direction is not
 * a pair of roles. `de → en` is either German studied with English support or English studied with
 * German support; the query string alone cannot tell them apart, and until now the server broke the
 * tie with the learner's profile — a rule that holds exactly while somebody studies one language.
 *
 * `taught_side` lets the pill say which half it is, because the learner set it. The tie-break is
 * kept for every request that stays silent: an older build, a screen with no pill.
 *
 * The observable in these tests is `reversed`, which the server derives from the SUPPORT language —
 * so it flips exactly when the roles do, with the direction held still.
 */
function instantSide(object $ctx, string $token, string $query, string $source, string $target, ?string $taughtSide = null): array
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/search/instant?' . http_build_query(array_filter([
            'q' => $query,
            'source' => $source,
            'target' => $target,
            'taught_side' => $taughtSide,
        ], static fn (?string $v): bool => $v !== null)))
        ->assertOk()
        ->json('data');
}

it('reads the roles from taught_side when both halves are teachable', function () {
    fakeTranslator();
    [, $token] = learner();

    // Same direction, twice, with only the stated role differing.
    $germanStudied = instantSide($this, $token, 'Rechnung', 'de', 'en', 'source');
    $englishStudied = instantSide($this, $token, 'Rechnung', 'de', 'en', 'target');

    // «reversed» = the query was typed in the SUPPORT language. German studied → it was not;
    // English studied → the German query is the support side and it was.
    expect($germanStudied['reversed'])->toBeFalse()
        ->and($englishStudied['reversed'])->toBeTrue();
});

it('falls back to the tie-break when the client says nothing', function () {
    // Nothing about the old behaviour changes for a build that does not know the field: the profile
    // decides (here `en`, the factory default), so `en` is the taught side of `de → en`.
    fakeTranslator();
    [, $token] = learner();

    expect(instantSide($this, $token, 'Rechnung', 'de', 'en')['reversed'])->toBeTrue();
});

it('ignores an unparseable side on the debounced GET rather than erroring at a typing learner', function () {
    fakeTranslator();
    [, $token] = learner();

    // Same answer as the silent request above — a typo in a query string must not put a 422 on a
    // screen somebody is still typing into.
    expect(instantSide($this, $token, 'Rechnung', 'de', 'en', 'nonsense')['reversed'])->toBeTrue();
});

it('has no say when only one side is teachable — the pair already names the taught language', function () {
    fakeTranslator();
    [, $token] = learner();

    // `ru` is not a language this deployment teaches, so `en` is the term side whatever is claimed.
    expect(instantSide($this, $token, 'случай', 'ru', 'en', 'target')['reversed'])->toBeTrue()
        ->and(instantSide($this, $token, 'occasion', 'en', 'ru', 'source')['reversed'])->toBeFalse();
});

it('refuses a taught_side naming a language this deployment does not teach', function () {
    [, $token] = learner();

    // `ru` is a support language only. Claiming it as the taught side would have the card written
    // in the wrong language invisibly, so the paid endpoint says so instead of guessing.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/lookup', [
            'query' => 'случай',
            'source' => 'ru',
            'target' => 'en',
            'taught_side' => 'source',
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'taught_side_not_taught')
        ->assertJsonPath('meta.taught_side', 'source')
        ->assertJsonPath('meta.lang', 'ru');
});

it('refuses a taught_side outside the two words it knows, at validation', function () {
    [, $token] = learner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/lookup', [
            'query' => 'occasion',
            'source' => 'en',
            'target' => 'ru',
            'taught_side' => 'left',
        ])
        ->assertStatus(422);
});

<?php

declare(strict_types=1);

use App\Modules\Collections\Application\Command\CreateCustomCollection;
use App\Modules\Collections\Application\Command\CreateCustomCollectionHandler;
use App\Modules\Learning\Application\Command\EnrollTerm;
use App\Modules\Learning\Application\Command\EnrollTermHandler;
use App\Modules\Learning\Domain\Exception\ReferenceLanguageTerm;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * REFERENCE LANGUAGES (DECISIONS пп. 84, 136): zh and ja are phrasebooks in v1 — a term, a
 * translation and an audio, with no trainer behind any of it. This pins the three consequences the
 * app depends on: the pool refuses such a word at BOTH doors, the collection is announced as a
 * phrasebook on `/sync`, and progress does not count words that can never be studied.
 *
 * «Reference» is never stored: it is derived from the language carrying no trainers
 * ({@see \App\Modules\Shared\Domain\Service\LanguageRoles::isReference()}), so the day a Chinese
 * grader exists these tests describe a different world by themselves.
 */
function refDeck(App\Modules\Identity\Infrastructure\Eloquent\User $user, string $studied, string $title): string
{
    return app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        UserId::fromString($user->id), $title, new LanguageCode('ru'), new LanguageCode($studied),
    ))->value;
}

it('refuses to enrol a reference-language term, and names the language', function () {
    [$user, $token] = learner();
    $chinese = refDeck($user, 'zh', '中文');
    $term = addWordTo($chinese, $user->id, '苹果', 'яблоко', enroll: false);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/v1/pool/terms/{$term}")
        ->assertStatus(422)
        ->assertJsonPath('code', 'reference_language_term')
        ->assertJsonPath('meta.lang', 'zh')
        ->assertJsonPath('meta.term_id', $term);

    // Nothing was written on the way out — not even a paused row.
    $this->assertDatabaseMissing('user_term_progress', ['user_id' => $user->id, 'term_id' => $term]);
});

it('refuses at the shared Application door every enrolment path uses', function () {
    // «Учить это слово», the triage swipe and the save-from-search all reach the pool through this
    // one handler, which is why the gate lives in it and not in three callers.
    [$user] = learner();
    $japanese = refDeck($user, 'ja', '日本語');
    $term = addWordTo($japanese, $user->id, 'りんご', 'яблоко', enroll: false);

    expect(fn () => app(EnrollTermHandler::class)(new EnrollTerm(
        UserId::fromString($user->id), TermId::fromString($term),
    )))->toThrow(ReferenceLanguageTerm::class);
});

it('still enrols a taught-language term — the gate is about the language, not about being strict', function () {
    [$user, $token] = learner();
    $term = seedWordFor($user, 'apple', 'яблоко', enroll: false);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/v1/pool/terms/{$term}")
        ->assertOk()
        ->assertJsonPath('data.enrolled', true)
        ->assertJsonPath('data.changed', true);
});

it('rejects a reference-language swipe as its own bucket and writes nothing, without failing the batch', function () {
    [$user, $token] = learner();
    $chinese = refDeck($user, 'zh', '中文');
    $chineseTerm = addWordTo($chinese, $user->id, '苹果', 'яблоко', enroll: false);
    $englishTerm = seedWordFor($user, 'apple', 'яблоко', enroll: false);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/triage/batch', ['triages' => [
            [
                'id' => strtoupper(substr(str_repeat('0', 26) . '1', -26)),
                'term_id' => $chineseTerm,
                'verdict' => 'unknown',
                'decided_at' => '2026-08-24T10:00:00Z',
                'client_seq' => 1,
            ],
            [
                'id' => strtoupper(substr(str_repeat('0', 26) . '2', -26)),
                'term_id' => $englishTerm,
                'verdict' => 'unknown',
                'decided_at' => '2026-08-24T10:00:01Z',
                'client_seq' => 2,
            ],
        ]])
        ->assertOk()
        // The impossible word costs the possible one nothing (DECISIONS п. 101).
        ->assertJsonPath('data.rejected', 1)
        ->assertJsonPath('data.accepted', 1)
        ->assertJsonPath('data.unknown', 0);

    $this->assertDatabaseMissing('term_triages', ['term_id' => $chineseTerm]);
    $this->assertDatabaseMissing('user_term_progress', ['user_id' => $user->id, 'term_id' => $chineseTerm]);
    // …and the English one really did land in the pool.
    $this->assertDatabaseHas('term_triages', ['term_id' => $englishTerm, 'verdict' => 'unknown']);
});

it('announces a phrasebook collection on /sync and an ordinary one as ordinary', function () {
    [$user, $token] = learner();
    $chinese = refDeck($user, 'zh', '中文');
    addWordTo($chinese, $user->id, '苹果', 'яблоко', enroll: false);
    [$english] = seedCollectionWith($user, 'apple', 'яблоко', enroll: false);

    $byId = [];
    foreach (sync($this, $token)['changes']['collections'] as $row) {
        $byId[$row['id']] = $row;
    }

    expect($byId[$chinese]['is_reference'])->toBeTrue()
        ->and($byId[$english]['is_reference'])->toBeFalse();
});

it('keeps reference words out of progress — no phantom «0 of 40» to work through', function () {
    [$user, $token] = learner();
    $chinese = refDeck($user, 'zh', '中文');
    addWordTo($chinese, $user->id, '苹果', 'яблоко', enroll: false);
    [$english] = seedCollectionWith($user, 'apple', 'яблоко', enroll: false);

    $body = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/study/progress')
        ->assertOk()
        ->json();

    $collectionIds = array_column($body['data'], 'collection_id');
    expect($collectionIds)->toContain($english)
        ->and($collectionIds)->not->toContain($chinese);

    $langs = array_column($body['by_language'], 'lang');
    expect($langs)->toBe(['en']);
});

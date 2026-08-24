<?php

declare(strict_types=1);

use App\Modules\Admin\Application\Service\ContentTopUp;
use App\Modules\Identity\Infrastructure\Eloquent\Profile;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** Pin a sentence (and optionally its translation) as the term's ONLY — therefore pinned — example. */
function contentHealthExample(string $termId, string $sentence, ?string $translation): string
{
    $exampleId = Ulid::generate();
    DB::table('term_examples')->where('term_id', $termId)->delete();
    seedExample([
        'id' => $exampleId, 'term_id' => $termId, 'sentence' => $sentence,
        'translation' => $translation, 'source' => 'ai',
    ]);

    return $exampleId;
}

/** @param  list<array{0: string, 1: string}>  $rows  [sentence, error_span] */
function contentHealthDistractors(string $exampleId, array $rows): void
{
    foreach ($rows as [$sentence, $span]) {
        DB::table('example_distractors')->insert([
            'id' => Ulid::generate(), 'example_id' => $exampleId, 'sentence' => $sentence,
            'error_type' => 'preposition', 'error_span' => $span, 'correction' => 'at the bank',
            'generator_version' => 'mech-v1', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

/**
 * One collection with three deliberately different terms:
 *
 *   stocked   — example + translation + 3 distinct spans + a variant  → nothing to do
 *   thin      — example + translation, 3 distractor ROWS but one span → догон (мало дистракторов + нет вариантов)
 *   bare      — no example at all                                     → перегенерация, не станок
 *
 * @return array{0: string, 1: string, 2: array{stocked: string, thin: string, bare: string}}
 */
function contentHealthFixture(): array
{
    $user = User::factory()->create(['email' => 'content@wt.test']);
    Profile::create(['user_id' => $user->id, 'daily_goal' => 5, 'tier' => 'free']);

    [$collectionId, $stocked] = adminSeedTerm($user, 'Banking', 'account', 'счёт');
    $thin = addWordTo($collectionId, $user->id, 'invoice', 'счёт-фактура');
    $bare = addWordTo($collectionId, $user->id, 'ledger', 'бухгалтерская книга');

    $stockedExample = contentHealthExample($stocked, 'I opened a bank account yesterday.', 'Вчера я открыл счёт в банке.');
    contentHealthDistractors($stockedExample, [
        ['I opened a bank account on yesterday.', 'on yesterday'],
        ['I opened the bank account yesterday.', 'the bank'],
        ['I open a bank account yesterday.', 'I open'],
    ]);
    DB::table('term_accepted_variants')->insert([
        'id' => Ulid::generate(), 'term_id' => $stocked, 'text' => 'bank account', 'note' => null,
        'generator_version' => 'mech-v1', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('term_enrichment_versions')->insert([
        'term_id' => $stocked, 'generator_version' => 'mech-v1', 'created_at' => now(),
    ]);

    // Three rows, ONE span: the card could deal a single wrong option, so this term is thin however
    // full the table looks. This is the whole reason the report counts spans and not rows.
    $thinExample = contentHealthExample($thin, 'Could you send me the invoice today?', 'Пришлите мне счёт сегодня?');
    contentHealthDistractors($thinExample, [
        ['Could you send me invoice today?', 'me invoice'],
        ['Could you send me  invoice today?', 'ME INVOICE'],
        ['Could you send me an invoice today?', ' me invoice '],
    ]);

    DB::table('term_examples')->where('term_id', $bare)->delete();

    DB::table('enrichment_suppressions')->insert([
        ['id' => Ulid::generate(), 'term_id' => $thin, 'sentence' => 'could you send me a invoice today',
            'source' => 'review', 'created_at' => now()],
        ['id' => Ulid::generate(), 'term_id' => $thin, 'sentence' => 'could you sent me the invoice today',
            'source' => 'audit', 'created_at' => now()],
    ]);

    return [$collectionId, adminActor()[1], ['stocked' => $stocked, 'thin' => $thin, 'bare' => $bare]];
}

it('counts the dictionary once and slices it by collection kind', function () {
    [$collectionId, $token] = contentHealthFixture();

    $body = test()->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/admin/api/content-health/summary')
        ->assertOk()
        ->json();

    $all = $body['scopes']['all'];
    expect($all['terms'])->toBe(3)
        ->and($all['without_example'])->toBe(1)
        // Only the stocked term has three distinct spans AND a translated example.
        ->and($all['pick_correct_ready'])->toBe(1)
        ->and($all['with_distractors'])->toBe(2)
        ->and($all['with_variants'])->toBe(1)
        // The bare term has no example, so it is NOT a догон case — that would bill for a run the
        // станок cannot do anything with.
        ->and($all['needs_enrichment'])->toBe(1)
        ->and($all['estimated_topup_usd'])->toBe(round(ContentTopUp::COST_PER_TERM_USD, 4));

    // A custom collection: the user slice sees all three, the system slice none.
    expect($body['scopes']['user']['terms'])->toBe(3)
        ->and($body['scopes']['system']['terms'])->toBe(0);

    $collection = collect($body['collections'])->firstWhere('id', $collectionId);
    expect($collection['terms'])->toBe(3)
        ->and($collection['needs_enrichment'])->toBe(1)
        ->and($collection['without_example'])->toBe(1);

    expect($body['suppressions']['total'])->toBe(2)
        ->and(collect($body['suppressions']['by_source'])->pluck('count', 'label')->all())
        ->toBe(['audit' => 1, 'review' => 1])
        ->and($body['generation_rejections']['total'])->toBe(0)
        ->and($body['min_distractors'])->toBe(ContentTopUp::MIN_DISTRACTORS)
        ->and($body['current_generator_version'])->not->toBe('');
});

it('counts terms never touched by the станок in their own version bucket', function () {
    [, $token] = contentHealthFixture();

    $versions = test()->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/admin/api/content-health/summary')
        ->assertOk()
        ->json('scopes.all.enrichment_versions');

    expect(collect($versions)->firstWhere('version', null)['terms'])->toBe(2)
        ->and(collect($versions)->firstWhere('version', 'mech-v1')['terms'])->toBe(1);
});

it('lists a collection most under-stocked first, counting spans and not rows', function () {
    [$collectionId, $token, $ids] = contentHealthFixture();

    $body = test()->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/admin/api/content-health/collections/{$collectionId}")
        ->assertOk()
        ->assertJsonPath('collection_id', $collectionId)
        ->json();

    expect(array_column($body['terms'], 'term_id'))
        ->toBe([$ids['bare'], $ids['thin'], $ids['stocked']]);

    $thin = collect($body['terms'])->firstWhere('term_id', $ids['thin']);
    expect($thin['raw_distractors'])->toBe(3)
        ->and($thin['usable_distractors'])->toBe(1)
        ->and($thin['pick_correct_ready'])->toBeFalse()
        ->and($thin['needs_enrichment'])->toBeTrue()
        ->and($thin['needs_enrichment_reasons'])->toBe(['few_distractors']);

    $bare = collect($body['terms'])->firstWhere('term_id', $ids['bare']);
    expect($bare['missing_example'])->toBeTrue()
        ->and($bare['needs_enrichment'])->toBeFalse()
        ->and($bare['needs_enrichment_reasons'])->toBe([]);

    $stocked = collect($body['terms'])->firstWhere('term_id', $ids['stocked']);
    expect($stocked['usable_distractors'])->toBe(3)
        ->and($stocked['pick_correct_ready'])->toBeTrue()
        ->and($stocked['needs_enrichment'])->toBeFalse();

    expect($body['topup_command'])
        ->toContain("--collection={$collectionId}")
        ->toContain('--topup=' . ContentTopUp::MIN_DISTRACTORS);
});

it('404s an unknown collection', function () {
    [, $token] = contentHealthFixture();

    test()->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/admin/api/content-health/collections/' . Ulid::generate())
        ->assertNotFound();
});

it('refuses an anonymous reader', function () {
    contentHealthFixture();

    test()->getJson('/admin/api/content-health/summary')->assertUnauthorized();
});

// ── The term passport ───────────────────────────────────────────────────────────────────────────

it('simulates every trainer against one term’s own content', function () {
    [, $token, $ids] = contentHealthFixture();

    $body = test()->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/admin/api/content-health/terms/{$ids['thin']}")
        ->assertOk()
        ->json();

    $byMode = collect($body['simulation'])->keyBy('mode');
    // Derived from the enum: the simulator must answer for EVERY trainer this build knows, which
    // is the actual claim — a hard-coded count only ever restates the enum's length a version late.
    expect($byMode)->toHaveCount(count(\App\Modules\Learning\Domain\ValueObject\ExerciseMode::cases()))
        // The four that ask for the term itself (or for nothing) fit every term.
        ->and($byMode['typing']['status'])->toBe('ok')
        ->and($byMode['listening']['status'])->toBe('ok')
        ->and($byMode['speaking']['status'])->toBe('ok')
        ->and($byMode['intro']['status'])->toBe('ok')
        // Its options are OTHER words, so the term's content decides nothing here.
        ->and($byMode['multiple_choice']['status'])->toBe('pool_dependent')
        ->and($byMode['multiple_choice']['reason'])->toBe('options_from_pool')
        // Three rows, one span → one usable option → the card cannot be dealt.
        ->and($byMode['pick_correct']['status'])->toBe('blocked')
        ->and($byMode['pick_correct']['reason'])->toBe('too_few_distractors')
        ->and($byMode['pick_correct']['explanation'])->toContain('годных дистракторов 1');

    // A machine reason exactly when the card cannot be built, a human one always.
    foreach ($body['simulation'] as $row) {
        expect($row['explanation'])->not->toBe('');
        expect($row['reason'] === null)->toBe($row['status'] === 'ok', "reason/status disagree for {$row['mode']}");
    }
});

it('reports the single-word gap that the word_bank card actually hits', function () {
    [, $token, $ids] = contentHealthFixture();

    // 'invoice' is one word: nothing to assemble from chips. Same verdict the live gate gives.
    $byMode = collect(
        test()->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/admin/api/content-health/terms/{$ids['thin']}")
            ->json('simulation'),
    )->keyBy('mode');

    expect($byMode['word_bank']['status'])->toBe('blocked')
        ->and($byMode['word_bank']['reason'])->toBe('single_word');
});

it('marks which distractors a card would deal, and keeps suppressions in their own list', function () {
    [, $token, $ids] = contentHealthFixture();

    $body = test()->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/admin/api/content-health/terms/{$ids['thin']}")
        ->assertOk()
        ->json();

    expect($body['distractors'])->toHaveCount(3)
        ->and(array_column($body['distractors'], 'usable'))->toBe([true, false, false])
        ->and($body['usable_distractors'])->toBe(1)
        ->and($body['error_type_note'])->toContain('error_type');

    // Suppressions outlive the rows they were about — never merged into `distractors`.
    expect($body['suppressed'])->toHaveCount(2)
        ->and(collect($body['suppressed'])->pluck('source')->sort()->values()->all())->toBe(['audit', 'review']);

    expect($body['needs_enrichment'])->toBeTrue()
        ->and($body['needs_enrichment_reasons'])->toBe(['few_distractors'])
        ->and($body['missing_example'])->toBeFalse();
});

it('keeps «нет примера» apart from «нужен станок»', function () {
    [, $token, $ids] = contentHealthFixture();

    $body = test()->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/admin/api/content-health/terms/{$ids['bare']}")
        ->assertOk()
        ->json();

    expect($body['example'])->toBeNull()
        ->and($body['missing_example'])->toBeTrue()
        // The станок writes AGAINST an example; with none there is nothing to pay it for.
        ->and($body['needs_enrichment'])->toBeFalse()
        ->and($body['needs_enrichment_reasons'])->toBe([]);

    $byMode = collect($body['simulation'])->keyBy('mode');
    foreach (['cloze', 'scramble', 'dictation', 'pick_correct'] as $mode) {
        expect($byMode[$mode]['reason'])->toBe('no_example');
    }
});

it('hands over a догон command, and the version-skip lesson only when it applies', function () {
    [$collectionId, $token, $ids] = contentHealthFixture();

    $thin = test()->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/admin/api/content-health/terms/{$ids['thin']}")
        ->assertOk()
        ->json();

    expect($thin['topup_command'])
        ->toContain("--collection={$collectionId}")
        ->toContain('--topup=' . ContentTopUp::MIN_DISTRACTORS)
        // Never marked at all → nothing to warn about.
        ->and($thin['topup_hint'])->toBeNull();

    // Mark it at the CURRENT version: a plain run would now skip it, which is exactly the trap.
    $current = app(ContentTopUp::class)->currentVersion();
    DB::table('term_enrichment_versions')->insert([
        'term_id' => $ids['thin'], 'generator_version' => $current, 'created_at' => now(),
    ]);

    $again = test()->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/admin/api/content-health/terms/{$ids['thin']}")
        ->assertOk()
        ->json();

    expect($again['topup_hint'])->toContain($current)
        ->and($again['topup_hint'])->toContain('--generator')
        ->and($again['current_generator_version'])->toBe($current)
        ->and(collect($again['enrichment_versions'])->pluck('version')->all())->toContain($current);
});

it('404s an unknown term passport', function () {
    [, $token] = contentHealthFixture();

    test()->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/admin/api/content-health/terms/' . Ulid::generate())
        ->assertNotFound();
});

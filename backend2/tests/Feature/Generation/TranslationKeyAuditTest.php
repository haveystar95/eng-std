<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * QA-17, the audit half: find the translations that stopped pointing at their own term, write them
 * out for a human, and change NOTHING.
 */
function seedKey(string $term, string $translation, ?string $collectionTitle = null, string $lang = 'ru'): string
{
    $termId = Ulid::generate();
    DB::table('terms')->insert([
        'id' => $termId, 'lang' => 'en', 'text' => $term, 'normalized_text' => mb_strtolower($term),
        'type' => 'phrase', 'source' => 'ai', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('term_translations')->insert([
        'id' => Ulid::generate(), 'term_id' => $termId, 'lang' => $lang, 'text' => $translation,
        'is_primary' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    if ($collectionTitle !== null) {
        $collectionId = Ulid::generate();
        DB::table('collections')->insert([
            'id' => $collectionId, 'type' => 'system', 'title' => $collectionTitle, 'source_lang' => 'ru',
            'target_lang' => 'en', 'visibility' => 'public', 'source' => 'ai',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('collection_items')->insert([
            'id' => Ulid::generate(), 'collection_id' => $collectionId, 'term_id' => $termId,
            'position' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $termId;
}

function runKeyAudit(object $ctx, string $path, string $sourceLang = 'ru'): string
{
    @unlink($path);
    $ctx->artisan('vocab:audit-translation-keys', ['--out' => $path, '--source-lang' => $sourceLang])->assertSuccessful();

    return (string) file_get_contents($path);
}

it('exports the candidates with term, translation, collection and the group they tripped', function () {
    seedKey('Tell us about a challenge you faced', 'Расскажите о вызове, с которым вы столкнулись', 'Собеседование');
    seedKey('withdraw cash', 'снять наличные', 'В банке');

    $body = runKeyAudit($this, storage_path('app/keys-audit-test.md'));

    expect($body)->toContain('Tell us about a challenge you faced')
        ->and($body)->toContain('Расскажите о вызове, с которым вы столкнулись')
        ->and($body)->toContain('Собеседование')
        ->and($body)->toContain('`us/me`')
        // The clean pair is not a candidate and must not be in the file — this is a work list, not
        // a dump of the table.
        ->and($body)->not->toContain('withdraw cash');

    @unlink(storage_path('app/keys-audit-test.md'));
});

it('dates itself by the export rule — the snapshot and HEAD, first line', function () {
    seedKey('Tell us about your experience', 'Расскажите о своём опыте', 'Собеседование');

    $path = storage_path('app/keys-audit-header-test.md');
    $body = runKeyAudit($this, $path);
    $first = explode("\n", $body)[0];

    expect($first)->toStartWith('<!-- snapshot: ')
        ->and($first)->toContain(' · head: ')
        ->and($first)->toMatch('/snapshot: \d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+\-]\d{2}:\d{2}/')
        // …and readable in the rendered document, for the person who actually proof-reads it.
        ->and($body)->toContain('Снимок: **')
        ->and($body)->toContain('HEAD: `');

    @unlink($path);
});

it('changes nothing — it is a reading list, not a repair', function () {
    $termId = seedKey('Tell us about a challenge you faced', 'Расскажите о вызове', 'Собеседование');
    $before = DB::table('term_translations')->where('term_id', $termId)->value('text');

    runKeyAudit($this, storage_path('app/keys-audit-noop-test.md'));

    expect(DB::table('term_translations')->where('term_id', $termId)->value('text'))->toBe($before);

    @unlink(storage_path('app/keys-audit-noop-test.md'));
});

it('audits Ukrainian translations with Ukrainian counterparts, not Russian ones', function () {
    // «Розкажіть про виклик» drops «нам» — a violation in either language, since us/me's forms
    // overlap. «Розкажіть нам про наш досвід» carries everything Ukrainian-specific (наш) — it
    // must NOT be flagged just because it lacks the Russian word «вы».
    seedKey('Tell us about a challenge you faced', 'Розкажіть про виклик', 'Співбесіда', 'uk');
    seedKey('Tell us about our experience', 'Розкажіть нам про наш досвід', 'Співбесіда', 'uk');

    $body = runKeyAudit($this, storage_path('app/keys-audit-uk-test.md'), 'uk');

    expect($body)->toContain('Tell us about a challenge you faced')
        ->and($body)->toContain('`us/me`')
        ->and($body)->not->toContain('Tell us about our experience');

    @unlink(storage_path('app/keys-audit-uk-test.md'));
});

it('says so plainly when there is nothing to proof-read', function () {
    seedKey('withdraw cash', 'снять наличные', 'В банке');

    $body = runKeyAudit($this, storage_path('app/keys-audit-empty-test.md'));

    expect($body)->toContain('_Нечего вычитывать._');

    @unlink(storage_path('app/keys-audit-empty-test.md'));
});

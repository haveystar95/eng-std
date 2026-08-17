<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The export must date itself, on its first line.
 *
 * This is the store5 lesson written as a test. That review was produced against an export taken at
 * 16:10 and read after a fix that landed at 17:32; its section E reported three rows as outstanding
 * that were already clean. Nothing in the file said when it was taken, so nobody could have known.
 * The timestamp dates the DATA and the revision dates the CODE that shaped it, and both belong where
 * a reader's eye — or a script — hits them first.
 */
it('writes the snapshot timestamp and HEAD on the first line of the export', function () {
    $collectionId = Ulid::generate();
    DB::table('collections')->insert([
        'id' => $collectionId,
        'type' => 'system',
        'title' => 'Тест',
        'source_lang' => 'ru',
        'target_lang' => 'en',
        'visibility' => 'public',
        'source' => 'ai',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $path = storage_path('app/export-header-test.md');
    @unlink($path);

    $this->artisan('enrich:backfill', [
        '--collection' => [$collectionId],
        '--limit' => 0,
        '--fake' => true,
        '--out' => $path,
    ])->assertSuccessful();

    expect(is_file($path))->toBeTrue();
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    $first = $lines[0] ?? '';

    // Machine-readable, and literally first: a reader diffing two exports can tell which is newer
    // without parsing prose.
    expect($first)->toStartWith('<!-- snapshot: ')
        ->and($first)->toContain(' · head: ')
        // An ISO-8601 instant, not a locale-formatted date — this gets compared to commit times.
        ->and($first)->toMatch('/snapshot: \d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+\-]\d{2}:\d{2}/');

    // And visible in the rendered document, because the person it protects reads the markdown.
    $body = implode("\n", $lines);
    expect($body)->toContain('Снимок: **')
        ->and($body)->toContain('HEAD: `');

    @unlink($path);
});

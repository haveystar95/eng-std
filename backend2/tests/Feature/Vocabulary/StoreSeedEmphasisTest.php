<?php

declare(strict_types=1);

/**
 * Markdown emphasis must not exist in the store seed.
 *
 * Why a test over a JSON file and not over the database: the emphasis sweep of 46f9076 was a one-off
 * run against a live database, and a one-off leaves nothing behind that can be re-checked. The seed
 * is the same content in git — it is what `migrate:fresh` puts back before the app cuts over — so it
 * is the copy that CAN be guarded, and guarding it means the markers cannot come back through the
 * one path that survives a rebuild.
 *
 * Only prose fields are read. URLs legitimately contain underscores, and asserting over them would
 * make the guard fail for a reason that has nothing to do with what it is protecting.
 */
it('carries no markdown emphasis in any prose field of the store seed', function () {
    $path = base_path('database/seeders/data/store_content.json');
    expect(is_file($path))->toBeTrue();

    /** @var list<array<string, mixed>> $collections */
    $collections = json_decode((string) file_get_contents($path), true);
    expect($collections)->toBeArray()->not->toBeEmpty();

    $offenders = [];
    $check = static function (mixed $value, string $where) use (&$offenders): void {
        if (is_string($value) && preg_match('/(\*\*|__|\*|_)/', $value) === 1) {
            $offenders[] = "{$where}: «{$value}»";
        }
    };

    foreach ($collections as $collection) {
        $slug = is_string($collection['slug'] ?? null) ? $collection['slug'] : '?';
        $check($collection['title'] ?? null, "{$slug} · title");
        $check($collection['description'] ?? null, "{$slug} · description");

        foreach ((array) ($collection['items'] ?? []) as $item) {
            $term = (array) ($item['term'] ?? []);
            $text = is_string($term['text'] ?? null) ? $term['text'] : '?';
            $check($term['text'] ?? null, "{$slug} · term");

            foreach ((array) ($item['translations'] ?? []) as $translation) {
                $check($translation['text'] ?? null, "{$slug} · «{$text}» translation");
            }
            foreach ((array) ($item['examples'] ?? []) as $example) {
                $check($example['sentence'] ?? null, "{$slug} · «{$text}» example");
                $check($example['sentence_translation'] ?? null, "{$slug} · «{$text}» example translation");
            }
        }
    }

    expect($offenders)->toBe([]);
});

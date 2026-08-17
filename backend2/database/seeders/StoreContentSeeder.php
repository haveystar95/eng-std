<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The curated store catalogue: system/public/curated collections for the ru→en pair, with their
 * terms, translations, examples and Pexels imagery. A faithful snapshot loaded from
 * `database/seeders/data/store_content.json` — raw inserts preserve the exact curated data
 * (cover/term images, hand-edited translations, CEFR) that re-running the AI enrichment pipeline
 * would not reproduce.
 *
 * Idempotent and dedup-safe: collections upsert by id; each term is resolved by the GLOBAL dedup
 * key (lang, normalized_text, pos) and inserted only when absent, so re-seeding — or seeding onto
 * a database that already holds some of these terms — never duplicates a term nor breaks an item's
 * foreign key. Regenerate the fixture from the live store, then re-run this seeder.
 */
final class StoreContentSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/store_content.json');
        if (! is_file($path)) {
            $this->command?->warn('store_content.json not found — nothing to seed.');

            return;
        }

        $raw = (string) file_get_contents($path);
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $collections = is_array($decoded) ? $decoded : [];

        $now = now();
        DB::transaction(function () use ($collections, $now): void {
            foreach ($collections as $collection) {
                if (is_array($collection)) {
                    $this->seedCollection($collection, $now);
                }
            }
        });

        $this->command?->info('Seeded ' . count($collections) . ' store collections.');
    }

    /** @param array<string, mixed> $c */
    private function seedCollection(array $c, mixed $now): void
    {
        DB::table('collections')->updateOrInsert(
            ['id' => $c['id']],
            [
                'owner_id' => null,
                'type' => $c['type'],
                'slug' => $c['slug'] ?? null,
                'title' => $c['title'],
                'description' => $c['description'] ?? null,
                'topic' => $c['topic'] ?? null,
                'source_lang' => $c['source_lang'],
                'target_lang' => $c['target_lang'],
                'visibility' => $c['visibility'],
                'source' => $c['source'],
                'items_count' => $c['items_count'],
                'is_premium' => $c['is_premium'],
                'image_url' => $c['image_url'] ?? null,
                'image_author' => $c['image_author'] ?? null,
                'image_author_url' => $c['image_author_url'] ?? null,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $items = is_array($c['items'] ?? null) ? $c['items'] : [];
        foreach ($items as $item) {
            if (! is_array($item) || ! is_array($item['term'] ?? null)) {
                continue;
            }
            $termId = $this->resolveTerm(
                $item['term'],
                is_array($item['translations'] ?? null) ? $item['translations'] : [],
                is_array($item['examples'] ?? null) ? $item['examples'] : [],
                $now,
            );

            DB::table('collection_items')->updateOrInsert(
                ['collection_id' => $c['id'], 'term_id' => $termId],
                [
                    'id' => Ulid::generate(),
                    'position' => $item['position'] ?? 0,
                    'note' => $item['note'] ?? null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    /**
     * Return the id of the term, reusing an existing one that matches the global dedup key so a
     * shared term is never duplicated; otherwise insert the term with its translations + examples.
     *
     * @param  array<string, mixed>  $t
     * @param  list<mixed>  $translations
     * @param  list<mixed>  $examples
     */
    private function resolveTerm(array $t, array $translations, array $examples, mixed $now): string
    {
        $pos = $t['pos'] ?? null;
        $existing = DB::table('terms')
            ->where('lang', $t['lang'])
            ->where('normalized_text', $t['normalized_text'])
            ->when(
                $pos === null,
                fn ($q) => $q->whereNull('pos'),
                fn ($q) => $q->where('pos', $pos),
            )
            ->value('id');

        if ($existing !== null) {
            return (string) $existing;
        }

        $termId = (string) $t['id'];
        DB::table('terms')->insert([
            'id' => $termId,
            'lang' => $t['lang'],
            'text' => $t['text'],
            'normalized_text' => $t['normalized_text'],
            'type' => $t['type'],
            'pos' => $pos,
            'ipa' => $t['ipa'] ?? null,
            'audio_url' => $t['audio_url'] ?? null,
            'frequency_rank' => $t['frequency_rank'] ?? null,
            'source' => $t['source'],
            'cefr' => $t['cefr'] ?? null,
            'image_url' => $t['image_url'] ?? null,
            'image_author' => $t['image_author'] ?? null,
            'image_author_url' => $t['image_author_url'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($translations as $tr) {
            if (! is_array($tr)) {
                continue;
            }
            DB::table('term_translations')->insertOrIgnore([
                'id' => Ulid::generate(),
                'term_id' => $termId,
                'lang' => $tr['lang'],
                'text' => $tr['text'],
                'is_primary' => $tr['is_primary'] ?? false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($examples as $ex) {
            if (! is_array($ex)) {
                continue;
            }
            DB::table('term_examples')->insert([
                'id' => Ulid::generate(),
                'term_id' => $termId,
                'sentence' => $ex['sentence'],
                'sentence_translation' => $ex['sentence_translation'] ?? null,
                'source' => $ex['source'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $termId;
    }
}

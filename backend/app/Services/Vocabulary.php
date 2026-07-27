<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\ReviewState;
use App\Models\User;
use App\Models\Word;

/**
 * Adds words with deduplication: one shared Word per (user, normalized term),
 * linked to any number of collections, with a single review state per word.
 */
class Vocabulary
{
    /**
     * @param array{term:string,translation:string,transcription?:?string,example?:?string,cefr_level?:?string} $data
     */
    public function addToCollection(User $user, Collection $collection, array $data): Word
    {
        $term = trim($data['term']);
        $key = Word::keyFor($term);

        $word = Word::firstOrCreate(
            ['user_id' => $user->id, 'term_key' => $key],
            [
                'term' => $term,
                'translation' => $data['translation'],
                'transcription' => $data['transcription'] ?? null,
                'example' => $data['example'] ?? null,
                'cefr_level' => $data['cefr_level'] ?? null,
            ],
        );

        // Link to the collection (no-op if already linked).
        $collection->words()->syncWithoutDetaching([$word->id]);

        // One review state per word, shared across collections.
        ReviewState::firstOrCreate(
            ['user_id' => $user->id, 'word_id' => $word->id],
            ['state' => 'new', 'due_at' => now()],
        );

        return $word;
    }
}

<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\ReviewState;
use App\Models\User;

/** Aggregates a user's learning progress. */
class StatsBuilder
{
    /** "Learned" ~ recall for a few days; "mastered" ~ three weeks. */
    private const LEARNED_STABILITY = 4;
    private const MASTERED_STABILITY = 21;

    public function forUser(User $user): array
    {
        $states = ReviewState::where('user_id', $user->id)->get();
        $now = now();

        $isLearned = fn ($s) => $s->stability >= self::LEARNED_STABILITY;
        $isDue = fn ($s) => $s->due_at === null || $s->due_at <= $now;

        $collections = Collection::withCount('words')
            ->where('user_id', $user->id)
            ->get()
            ->map(function (Collection $c) use ($states, $isLearned, $isDue) {
                $sub = $states->whereIn('word_id', $c->words()->pluck('words.id'));

                return [
                    'id' => $c->id,
                    'title' => $c->title,
                    'source' => $c->source,
                    'total' => $c->words_count,
                    'learned' => $sub->filter($isLearned)->count(),
                    'due' => $sub->filter($isDue)->count(),
                ];
            });

        return [
            'total_words' => $states->count(),
            'learned' => $states->filter($isLearned)->count(),
            'mastered' => $states->where('stability', '>=', self::MASTERED_STABILITY)->count(),
            'due_today' => $states->filter($isDue)->count(),
            'reviews_total' => $states->sum('reps'),
            'collections' => $collections,
        ];
    }
}

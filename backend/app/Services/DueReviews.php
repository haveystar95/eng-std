<?php

namespace App\Services;

use App\Models\ReviewState;
use App\Models\User;
use Illuminate\Support\Collection;

/** Builds the set of cards for a training session. */
class DueReviews
{
    /**
     * @return Collection<int, ReviewState>
     */
    public function forUser(User $user, ?int $collectionId = null, bool $shuffle = false, int $limit = 40): Collection
    {
        $limit = max(1, min($limit, 200));

        $query = ReviewState::with('word')->where('user_id', $user->id);

        if ($collectionId) {
            // Practice a specific collection: all its words.
            $query->whereHas('word.collections', fn ($q) => $q->where('collections.id', $collectionId));
        } else {
            // Mixed session: only cards that are due now.
            $query->where(fn ($q) => $q->whereNull('due_at')->orWhere('due_at', '<=', now()));
        }

        if ($shuffle) {
            $query->inRandomOrder();
        } else {
            $query->orderByRaw('due_at IS NULL DESC')->orderBy('due_at');
        }

        return $query->limit($limit)->get();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'term_key', 'term', 'translation', 'transcription', 'example', 'cefr_level'])]
class Word extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class);
    }

    public function reviewStates(): HasMany
    {
        return $this->hasMany(ReviewState::class);
    }

    /** Normalized dedup key for a raw term. */
    public static function keyFor(string $term): string
    {
        return mb_strtolower(trim($term));
    }
}

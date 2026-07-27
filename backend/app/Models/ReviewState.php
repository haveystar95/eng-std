<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'word_id', 'stability', 'difficulty', 'reps', 'lapses',
    'state', 'last_reviewed_at', 'due_at', 'last_rating',
])]
class ReviewState extends Model
{
    protected function casts(): array
    {
        return [
            'stability' => 'float',
            'difficulty' => 'float',
            'last_reviewed_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function word(): BelongsTo
    {
        return $this->belongsTo(Word::class);
    }
}

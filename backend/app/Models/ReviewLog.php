<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id', 'word_id', 'rating', 'stability_after',
    'difficulty_after', 'elapsed_days', 'reviewed_at',
])]
class ReviewLog extends Model
{
    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }
}

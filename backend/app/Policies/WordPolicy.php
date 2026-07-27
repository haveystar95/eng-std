<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Word;

class WordPolicy
{
    public function update(User $user, Word $word): bool
    {
        return $word->user_id === $user->id;
    }

    public function delete(User $user, Word $word): bool
    {
        return $word->user_id === $user->id;
    }

    public function review(User $user, Word $word): bool
    {
        return $word->user_id === $user->id;
    }
}

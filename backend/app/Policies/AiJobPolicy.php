<?php

namespace App\Policies;

use App\Models\AiJob;
use App\Models\User;

class AiJobPolicy
{
    public function view(User $user, AiJob $job): bool
    {
        return $job->user_id === $user->id;
    }
}

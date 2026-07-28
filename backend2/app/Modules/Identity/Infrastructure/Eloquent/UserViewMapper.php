<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Eloquent;

use App\Modules\Identity\Application\Dto\ProfileView;
use App\Modules\Identity\Application\Dto\UserView;

/** Builds the client-facing {@see UserView} from the Eloquent user, keeping models out of Application. */
final class UserViewMapper
{
    public function toView(User $user): UserView
    {
        // Load via the relation explicitly (the model declares a same-named property).
        $profile = $user->getRelationValue('profile');

        return new UserView(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            avatar: $user->avatar,
            profile: $profile instanceof Profile ? new ProfileView(
                nativeLanguage: $profile->native_language,
                targetLanguage: $profile->target_language,
                cefrLevel: $profile->cefr_level,
                dailyGoal: $profile->daily_goal,
            ) : null,
        );
    }
}

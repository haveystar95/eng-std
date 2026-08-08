<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Eloquent;

use App\Modules\Identity\Application\Dto\ProfileInput;
use App\Modules\Identity\Application\Dto\UserView;
use App\Modules\Identity\Application\Port\ProfileUpdater;
use App\Modules\Shared\Domain\ValueObject\UserId;

final class EloquentProfileUpdater implements ProfileUpdater
{
    public function __construct(private readonly UserViewMapper $mapper) {}

    public function update(UserId $id, ProfileInput $input): UserView
    {
        $user = User::query()->findOrFail($id->value);

        $changes = array_filter([
            'native_language' => $input->nativeLanguage,
            'target_language' => $input->targetLanguage,
            'cefr_level' => $input->cefrLevel,
            'daily_goal' => $input->dailyGoal,
        ], static fn (mixed $value): bool => $value !== null);

        $profile = $user->profile()->firstOrCreate([]);
        $profile->fill($changes);
        // Stamp onboarding completion once — never overwrite an earlier onboarding (device-batch F1).
        if ($input->onboarded === true && $profile->onboarded_at === null) {
            $profile->onboarded_at = now();
        }
        $profile->save();

        return $this->mapper->toView($user->refresh());
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Controller;

use App\Modules\Identity\Application\Dto\ProfileInput;
use App\Modules\Identity\Application\Port\ProfileUpdater;
use App\Modules\Identity\Presentation\Http\Request\UpdateProfileRequest;
use App\Modules\Identity\Presentation\Http\Resource\UserResource;
use App\Modules\Shared\Domain\ValueObject\UserId;

final class ProfileController
{
    public function __construct(private readonly ProfileUpdater $profiles) {}

    public function update(UpdateProfileRequest $request): UserResource
    {
        $data = $request->validated();

        $view = $this->profiles->update(
            UserId::fromString((string) $request->user()?->getAuthIdentifier()),
            new ProfileInput(
                nativeLanguage: isset($data['native_language']) ? (string) $data['native_language'] : null,
                targetLanguage: isset($data['target_language']) ? (string) $data['target_language'] : null,
                cefrLevel: isset($data['cefr_level']) ? (string) $data['cefr_level'] : null,
                dailyGoal: isset($data['daily_goal']) ? (int) $data['daily_goal'] : null,
                timezone: isset($data['timezone']) ? (string) $data['timezone'] : null,
                onboarded: isset($data['onboarded']) ? (bool) $data['onboarded'] : null,
            ),
        );

        return UserResource::make($view);
    }
}

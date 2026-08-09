<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Controller;

use App\Modules\Admin\Application\Command\ChangeUserTier;
use App\Modules\Admin\Application\Command\ChangeUserTierHandler;
use App\Modules\Admin\Presentation\Http\Request\ChangeTierRequest;
use App\Modules\Shared\Domain\ValueObject\SubscriptionTier;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** The one v1 admin mutation: set a user's subscription tier (audited). */
final class TierController
{
    public function __construct(private readonly ChangeUserTierHandler $changeTier) {}

    public function update(ChangeTierRequest $request, string $id): JsonResponse
    {
        $userId = $this->userId($id);
        $tier = SubscriptionTier::from($request->string('tier')->toString());
        $adminId = (string) $request->user()?->getAuthIdentifier();

        $applied = ($this->changeTier)(new ChangeUserTier($adminId, $userId, $tier));
        abort_unless($applied, Response::HTTP_NOT_FOUND);

        return response()->json(['id' => $userId->value, 'tier' => $tier->value]);
    }

    private function userId(string $id): UserId
    {
        try {
            return UserId::fromString($id);
        } catch (InvalidArgumentException $e) {
            throw new NotFoundHttpException(previous: $e);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Controller;

use App\Modules\Admin\Application\Command\ChangeModeSettingsRow;
use App\Modules\Admin\Application\Command\ChangeModeSettingsRowHandler;
use App\Modules\Admin\Application\Command\ClearModeSettingsOverride;
use App\Modules\Admin\Application\Command\ClearModeSettingsOverrideHandler;
use App\Modules\Admin\Application\Query\GetModeSettingsMatrix;
use App\Modules\Admin\Application\Query\GetModeSettingsMatrixHandler;
use App\Modules\Admin\Presentation\Http\Request\ChangeModeSettingsRowRequest;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The «Матрица режимов» screen: one row per trainer, carrying on/off, rotation position and the
 * acquisition-ladder threshold together — the full `learning_mode_settings` row, unlike
 * `ExerciseModeController`, which edits the toggles and the admission threshold as two separate
 * writes. Same URL shape as that controller: the scope is in the URL, never a body field, so an
 * override can never land on the global default by a missing parameter.
 */
final class ModeSettingsController
{
    public function __construct(
        private readonly GetModeSettingsMatrixHandler $read,
        private readonly ChangeModeSettingsRowHandler $change,
        private readonly ClearModeSettingsOverrideHandler $clear,
    ) {}

    /** The product default: every row, all tagged `global` — there is nothing to inherit here. */
    public function index(): JsonResponse
    {
        return response()->json(['rows' => ($this->read)(new GetModeSettingsMatrix())]);
    }

    public function update(ChangeModeSettingsRowRequest $request): JsonResponse
    {
        $this->write($request, null);

        return $this->index();
    }

    /** This user's matrix: global rows, overlaid by their own where they have one. */
    public function showForUser(string $id): JsonResponse
    {
        return response()->json(['rows' => ($this->read)(new GetModeSettingsMatrix($this->userId($id)))]);
    }

    public function updateForUser(ChangeModeSettingsRowRequest $request, string $id): JsonResponse
    {
        $this->write($request, $this->userId($id));

        return $this->showForUser($id);
    }

    /** Drop this user's override for one mode — the row's «Сбросить» button. */
    public function resetForUser(Request $request, string $id, string $mode): JsonResponse
    {
        try {
            ($this->clear)(new ClearModeSettingsOverride($this->adminId($request), $this->userId($id), $mode));
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return $this->showForUser($id);
    }

    private function write(ChangeModeSettingsRowRequest $request, ?UserId $userId): void
    {
        try {
            ($this->change)(new ChangeModeSettingsRow(
                adminId: $this->adminId($request),
                userId: $userId,
                mode: (string) $request->validated('mode'),
                enabled: (bool) $request->validated('enabled'),
                position: (int) $request->validated('position'),
                minAcquisition: (string) $request->validated('min_acquisition'),
                minLearningStep: $request->validated('min_learning_step') !== null ? (int) $request->validated('min_learning_step') : null,
                minSuccessfulReviews: $request->validated('min_successful_reviews') !== null ? (int) $request->validated('min_successful_reviews') : null,
                optionsPolicy: (string) ($request->validated('options_policy') ?? 'standard'),
            ));
        } catch (InvalidArgumentException $e) {
            // The ladder's own rules live in Learning, so they surface here as a 422 rather than
            // being restated in the FormRequest.
            abort(422, $e->getMessage());
        }
    }

    private function adminId(Request $request): string
    {
        return (string) $request->user()?->getAuthIdentifier();
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

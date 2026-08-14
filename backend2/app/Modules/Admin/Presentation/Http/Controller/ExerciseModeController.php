<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Controller;

use App\Modules\Admin\Application\Command\ChangeExerciseModes;
use App\Modules\Admin\Application\Command\ChangeExerciseModesHandler;
use App\Modules\Admin\Application\Command\ChangeModeAdmission;
use App\Modules\Admin\Application\Command\ChangeModeAdmissionHandler;
use App\Modules\Admin\Presentation\Http\Request\ChangeModeAdmissionRequest;
use App\Modules\Admin\Application\Dto\AdminExerciseModes;
use App\Modules\Admin\Application\Query\GetExerciseModes;
use App\Modules\Admin\Application\Query\GetExerciseModesHandler;
use App\Modules\Admin\Presentation\Http\Request\ChangeExerciseModesRequest;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The «Тренажёры» screen: the product default, and a per-user override.
 *
 * Four routes rather than one with a scope field — the URL says which scope is being written, so
 * an override can never be sent to the global default by a missing parameter.
 *
 * Responses are bare objects, no `data` envelope: that is the admin API's convention (only the
 * paginated lists wrap, and they wrap for their `meta`). The app-facing /api/v1 wraps everything;
 * these are two different contracts and the panel's client assumes this one.
 */
final class ExerciseModeController
{
    public function __construct(
        private readonly GetExerciseModesHandler $read,
        private readonly ChangeExerciseModesHandler $change,
        private readonly ChangeModeAdmissionHandler $changeAdmission,
    ) {}

    /** The product default + every mode this build can deal. */
    public function index(): JsonResponse
    {
        return response()->json($this->payload(($this->read)(new GetExerciseModes())));
    }

    public function update(ChangeExerciseModesRequest $request): JsonResponse
    {
        $modes = $request->modes();
        // Global has nothing to inherit from, so "no modes" is a client error, not "reset".
        abort_if($modes === null || $modes === [], 422, 'The global default needs at least one mode.');

        ($this->change)(new ChangeExerciseModes($this->adminId($request), null, $modes));

        return $this->index();
    }

    /** What one user trains with: their override (or null = inherits) and the effective set. */
    public function showForUser(string $id): JsonResponse
    {
        return response()->json($this->payload(($this->read)(new GetExerciseModes($this->userId($id)))));
    }

    /** `modes: null` (or []) drops the override and puts the user back on the global default. */
    public function updateForUser(ChangeExerciseModesRequest $request, string $id): JsonResponse
    {
        $userId = $this->userId($id);
        $modes = $request->modes();

        ($this->change)(new ChangeExerciseModes(
            $this->adminId($request),
            $userId,
            $modes === [] ? null : $modes,
        ));

        return $this->showForUser($id);
    }

    /**
     * Move one trainer's place on the acquisition ladder, product-wide. One mode per call: a
     * whole-matrix write would silently revert a threshold someone else had just moved.
     */
    public function updateAdmission(ChangeModeAdmissionRequest $request): JsonResponse
    {
        $this->writeAdmission($request, null);

        return $this->index();
    }

    /** The same, for one user — their per-mode override of the product default. */
    public function updateAdmissionForUser(ChangeModeAdmissionRequest $request, string $id): JsonResponse
    {
        $this->writeAdmission($request, $this->userId($id));

        return $this->showForUser($id);
    }

    private function writeAdmission(ChangeModeAdmissionRequest $request, ?UserId $userId): void
    {
        try {
            ($this->changeAdmission)(new ChangeModeAdmission(
                adminId: $this->adminId($request),
                userId: $userId,
                mode: (string) $request->validated('mode'),
                minAcquisition: (string) $request->validated('min_acquisition'),
                minLearningStep: $request->validated('min_learning_step') !== null ? (int) $request->validated('min_learning_step') : null,
                minReps: $request->validated('min_reps') !== null ? (int) $request->validated('min_reps') : null,
                optionsPolicy: (string) ($request->validated('options_policy') ?? 'standard'),
            ));
        } catch (InvalidArgumentException $e) {
            // The ladder's own rules (a reps threshold only means something once graduated) live in
            // Learning, so they surface here as a 422 rather than being restated in the FormRequest.
            abort(422, $e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function payload(AdminExerciseModes $view): array
    {
        return [
            'available' => $view->available,
            'global' => $view->global,
            'override' => $view->override,
            'effective' => $view->effective,
            'no_grade' => $view->noGrade,
            // The acquisition-ladder matrix for this scope. Its own screen is a separate task; the
            // API is here so the panel has something to build against and so the matrix is
            // inspectable the moment it starts affecting what learners are dealt.
            'admission' => $view->admission,
            'inherits' => $view->override === null,
        ];
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

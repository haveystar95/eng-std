<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Controller;

use App\Modules\Admin\Application\Command\ChangeExerciseModes;
use App\Modules\Admin\Application\Command\ChangeExerciseModesHandler;
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
 */
final class ExerciseModeController
{
    public function __construct(
        private readonly GetExerciseModesHandler $read,
        private readonly ChangeExerciseModesHandler $change,
    ) {}

    /** The product default + every mode this build can deal. */
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->payload(($this->read)(new GetExerciseModes()))]);
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
        return response()->json(['data' => $this->payload(($this->read)(new GetExerciseModes($this->userId($id))))]);
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

    /** @return array<string, mixed> */
    private function payload(AdminExerciseModes $view): array
    {
        return [
            'available' => $view->available,
            'global' => $view->global,
            'override' => $view->override,
            'effective' => $view->effective,
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

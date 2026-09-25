<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Resource;

use App\Modules\Identity\Application\Dto\UserView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read UserView $resource */
final class UserResource extends JsonResource
{
    /** @var array<string, mixed>|null Pre-formatted generation-quota block, attached by the controller. */
    private ?array $generation = null;

    /** @var array{plan: string, expires_at: string|null, source: string|null}|null The learner's access, attached by `me()`. */
    private ?array $access = null;

    /**
     * Attach the caller's generation allowance so the client can grey the create button before
     * submit. Kept as a plain array so this resource doesn't depend on the Generation module.
     *
     * @param  array<string, mixed>|null  $generation
     */
    public function withGeneration(?array $generation): self
    {
        $this->generation = $generation;

        return $this;
    }

    /**
     * Attach the learner's access (наряд ACC-1 §2): `plan` free | premium, `expires_at`, `source`. Only `GET /auth/me`
     * carries it — sign-in answers stay as they were.
     *
     * @param  array{plan: string, expires_at: string|null, source: string|null}  $access
     */
    public function withAccess(array $access): self
    {
        $this->access = $access;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'avatar' => $this->resource->avatar,
            'profile' => $this->resource->profile !== null
                ? ProfileResource::make($this->resource->profile)->resolve()
                : null,
            'generation' => $this->generation,
            // The paid plan (наряд ACC-1 §2) — what the paywall of the plan reads; present on `GET /auth/me`.
            ...($this->access === null ? [] : ['access' => $this->access]),
            // QA-ИНСТРУМЕНТЫ ЭТОЙ СЕССИИ (наряд SCENE-RUN, Ч.2.9). Дверь одна на всё: аккаунт
            // помечен `is_qa` И среда не production при включённом флаге. В production поле всегда
            // `false`, поэтому сборка, попавшая туда, инструментов не покажет, даже если её об этом
            // попросить.
            'qa_tools' => $this->resource->qaTools,
        ];
    }
}

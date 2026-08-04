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
        ];
    }
}

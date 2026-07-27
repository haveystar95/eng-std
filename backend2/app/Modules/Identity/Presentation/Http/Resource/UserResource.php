<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Resource;

use App\Modules\Identity\Application\Dto\UserView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read UserView $resource */
final class UserResource extends JsonResource
{
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
        ];
    }
}

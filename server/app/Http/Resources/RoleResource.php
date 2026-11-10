<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'active' => $this->active,
            'is_system' => $this->is_system,
            'created_at' => $this->created_at?->toISOString(),
            'permissions_count' => $this->whenCounted('permissions'),
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->map(fn ($p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
            ])),
        ];
    }
}

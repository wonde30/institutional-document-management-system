<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationUnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'unit_type_id' => $this->unit_type_id,
            'unit_type' => $this->whenLoaded('unitType', fn () => [
                'id' => $this->unitType->id,
                'code' => $this->unitType->code,
                'name' => $this->unitType->name,
            ]),
            'code' => $this->code,
            'name' => $this->name,
            'active' => $this->active,
            'created_at' => $this->created_at?->toISOString(),
            'children' => OrganizationUnitResource::collection($this->whenLoaded('children')),
        ];
    }
}

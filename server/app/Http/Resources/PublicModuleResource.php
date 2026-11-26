<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicModuleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => (string) ($this['key'] ?? ''),
            'title' => (string) ($this['title'] ?? ''),
            'description' => (string) ($this['description'] ?? ''),
            'icon' => (string) ($this['icon'] ?? 'FileText'),
            'route' => (string) ($this['route'] ?? '/'),
            'order' => (int) ($this['order'] ?? 0),
            'highlights' => (array) ($this['highlights'] ?? []),
        ];
    }
}

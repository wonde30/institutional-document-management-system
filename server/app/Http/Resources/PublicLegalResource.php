<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicLegalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => (string) ($this['type'] ?? ''),
            'title' => (string) ($this['title'] ?? ''),
            'subtitle' => (string) ($this['subtitle'] ?? ''),
            'effective_date' => (string) ($this['effective_date'] ?? ''),
            'last_updated' => (string) ($this['last_updated'] ?? ''),
            'statutory_basis' => (string) ($this['statutory_basis'] ?? ''),
            'sections' => (array) ($this['sections'] ?? []),
        ];
    }
}

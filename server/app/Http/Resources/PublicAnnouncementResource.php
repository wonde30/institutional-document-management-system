<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicAnnouncementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) ($this['id'] ?? ''),
            'title' => (string) ($this['title'] ?? ''),
            'summary' => (string) ($this['summary'] ?? ''),
            'category' => (string) ($this['category'] ?? 'General'),
            'published_at' => (string) ($this['published_at'] ?? ''),
            'badge' => (string) ($this['badge'] ?? ''),
            'pinned' => (bool) ($this['pinned'] ?? false),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicLandingSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'published_documents' => (int) ($this['published_documents'] ?? 0),
            'active_offices' => (int) ($this['active_offices'] ?? 0),
            'correspondence_routed' => (int) ($this['correspondence_routed'] ?? 0),
            'accreditation_evidence_items' => (int) ($this['accreditation_evidence_items'] ?? 0),
            'metrics_updated_at' => $this['metrics_updated_at'] ?? now()->toIso8601String(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicAccreditationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'framework' => [
                'name' => (string) ($this['framework']['name'] ?? 'Higher Education Relevance and Quality Agency (HERQA)'),
                'code' => (string) ($this['framework']['code'] ?? 'HERQA'),
            ],
            'cycle' => [
                'name' => (string) ($this['cycle']['name'] ?? 'HERQA Institutional Quality Audit Cycle (2026-2027)'),
                'starts_on' => (string) ($this['cycle']['starts_on'] ?? '2026-01-01'),
                'ends_on' => (string) ($this['cycle']['ends_on'] ?? '2027-12-31'),
            ],
            'summary' => [
                'total_standards' => (int) ($this['summary']['total_standards'] ?? 10),
                'verified_standards' => (int) ($this['summary']['verified_standards'] ?? 0),
                'in_review_standards' => (int) ($this['summary']['in_review_standards'] ?? 0),
                'total_evidence_items' => (int) ($this['summary']['total_evidence_items'] ?? 0),
                'coverage_percentage' => (float) ($this['summary']['coverage_percentage'] ?? 0.0),
            ],
            'standards' => (array) ($this['standards'] ?? []),
        ];
    }
}

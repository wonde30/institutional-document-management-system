<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'title' => $this->title,
            'summary' => $this->summary,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'code' => $this->category->code,
                'name' => $this->category->name,
            ]),
            'confidentiality_id' => $this->confidentiality_id,
            'confidentiality' => $this->whenLoaded('confidentiality', fn () => [
                'id' => $this->confidentiality->id,
                'code' => $this->confidentiality->code,
                'name' => $this->confidentiality->name,
            ]),
            'owner_unit_id' => $this->owner_unit_id,
            'owner_unit' => $this->whenLoaded('ownerUnit', fn () => [
                'id' => $this->ownerUnit->id,
                'code' => $this->ownerUnit->code,
                'name' => $this->ownerUnit->name,
            ]),
            'custodian_id' => $this->custodian_id,
            'custodian' => $this->whenLoaded('custodian', fn () => [
                'id' => $this->custodian->id,
                'name' => $this->custodian->name,
                'email' => $this->custodian->email,
            ]),
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'document_date' => $this->document_date?->toDateString(),
            'registered_at' => $this->registered_at?->toISOString(),
            'lifecycle' => $this->lifecycle,
            'lock_version' => $this->lock_version,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'versions_count' => $this->whenCounted('versions'),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')),
            'versions' => $this->whenLoaded('versions', function () {
                return $this->versions->map(function ($ver) {
                    return [
                        'id' => $ver->id,
                        'document_id' => $ver->document_id,
                        'version_number' => $ver->version_number,
                        'created_by' => $ver->created_by,
                        'creator' => $ver->createdBy ? [
                            'id' => $ver->createdBy->id,
                            'name' => $ver->createdBy->name,
                        ] : null,
                        'change_note' => $ver->change_note,
                        'created_at' => $ver->created_at?->toISOString(),
                        'version_files' => $ver->versionFiles ? $ver->versionFiles->map(function ($vf) {
                            return [
                                'file_object_id' => $vf->file_object_id,
                                'purpose' => $vf->purpose,
                                'file_object' => $vf->fileObject ? [
                                    'id' => $vf->fileObject->id,
                                    'original_filename' => $vf->fileObject->original_filename,
                                    'detected_mime' => $vf->fileObject->detected_mime,
                                    'byte_size' => $vf->fileObject->byte_size,
                                    'sha256' => $vf->fileObject->sha256,
                                    'storage_status' => $vf->fileObject->storage_status,
                                ] : null,
                            ];
                        }) : [],
                    ];
                });
            }),
            'correspondence' => $this->whenLoaded('correspondence', function () {
                if (! $this->correspondence) {
                    return null;
                }
                return new CorrespondenceResource($this->correspondence);
            }),
            'movements' => $this->whenLoaded('movements', function () {
                return $this->movements->map(function ($m) {
                    return [
                        'id' => $m->id,
                        'document_id' => $m->document_id,
                        'from_unit_id' => $m->from_unit_id,
                        'from_unit' => $m->fromUnit ? [
                            'id' => $m->fromUnit->id,
                            'name' => $m->fromUnit->name,
                            'code' => $m->fromUnit->code,
                        ] : null,
                        'to_unit_id' => $m->to_unit_id,
                        'to_unit' => $m->toUnit ? [
                            'id' => $m->toUnit->id,
                            'name' => $m->toUnit->name,
                            'code' => $m->toUnit->code,
                        ] : null,
                        'sent_at' => $m->sent_at?->toISOString(),
                        'sent_by' => $m->sent_by,
                        'sent_by_user' => $m->sentBy ? [
                            'id' => $m->sentBy->id,
                            'name' => $m->sentBy->name,
                        ] : null,
                        'dispatch_method' => $m->dispatch_method,
                        'instructions' => $m->instructions,
                        'receipt' => $m->receipt ? [
                            'id' => $m->receipt->id,
                            'acknowledged_by' => $m->receipt->acknowledged_by,
                            'acknowledged_by_user' => $m->receipt->acknowledgedBy ? [
                                'id' => $m->receipt->acknowledgedBy->id,
                                'name' => $m->receipt->acknowledgedBy->name,
                            ] : null,
                            'acknowledged_at' => $m->receipt->acknowledged_at?->toISOString(),
                            'status' => $m->receipt->status,
                            'remarks' => $m->receipt->remarks,
                        ] : null,
                    ];
                });
            }),
            'assignments' => $this->whenLoaded('assignments', function () {
                return $this->assignments->map(function ($a) {
                    return [
                        'id' => $a->id,
                        'document_id' => $a->document_id,
                        'assigned_unit_id' => $a->assigned_unit_id,
                        'assigned_unit' => $a->assignedUnit ? [
                            'id' => $a->assignedUnit->id,
                            'name' => $a->assignedUnit->name,
                            'code' => $a->assignedUnit->code,
                        ] : null,
                        'assigned_user_id' => $a->assigned_user_id,
                        'assigned_user' => $a->assignedUser ? [
                            'id' => $a->assignedUser->id,
                            'name' => $a->assignedUser->name,
                        ] : null,
                        'assigned_at' => $a->assigned_at?->toISOString(),
                        'due_at' => $a->due_at?->toISOString(),
                        'instructions' => $a->instructions,
                    ];
                });
            }),
            'physical_originals' => $this->whenLoaded('physicalOriginals', function () {
                return $this->physicalOriginals->map(function ($orig) {
                    return [
                        'id' => $orig->id,
                        'document_id' => $orig->document_id,
                        'physical_reference' => $orig->physical_reference,
                        'retention_requirement' => $orig->retention_requirement,
                        'created_at' => $orig->created_at?->toISOString(),
                        'custody_events' => $orig->custodyEvents ? $orig->custodyEvents->map(fn ($ce) => [
                            'id' => $ce->id,
                            'action' => $ce->action,
                            'occurred_at' => $ce->occurred_at?->toISOString(),
                            'reason' => $ce->reason,
                            'location' => $ce->location ? [
                                'id' => $ce->location->id,
                                'code' => $ce->location->code,
                                'name' => $ce->location->name,
                            ] : null,
                            'custodian' => $ce->custodian ? [
                                'id' => $ce->custodian->id,
                                'name' => $ce->custodian->name,
                            ] : null,
                        ]) : [],
                    ];
                });
            }),
        ];
    }
}

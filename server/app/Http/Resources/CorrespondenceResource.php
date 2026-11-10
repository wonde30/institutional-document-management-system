<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CorrespondenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_id' => $this->document_id,
            'document' => $this->whenLoaded('document', fn () => [
                'id' => $this->document->id,
                'reference_number' => $this->document->reference_number,
                'title' => $this->document->title,
                'summary' => $this->document->summary,
                'lifecycle' => $this->document->lifecycle,
            ]),
            'type_id' => $this->type_id,
            'type' => $this->whenLoaded('type', fn () => [
                'id' => $this->type->id,
                'code' => $this->type->code,
                'name' => $this->type->name,
            ]),
            'priority_id' => $this->priority_id,
            'priority' => $this->whenLoaded('priority', fn () => [
                'id' => $this->priority->id,
                'code' => $this->priority->code,
                'name' => $this->priority->name,
            ]),
            'purpose_code' => $this->purpose_code,
            'external_reference' => $this->external_reference,
            'correspondence_date' => $this->correspondence_date?->toDateString(),
            'first_receiving_unit_id' => $this->first_receiving_unit_id,
            'first_receiving_unit' => $this->whenLoaded('firstReceivingUnit', fn () => [
                'id' => $this->firstReceivingUnit->id,
                'code' => $this->firstReceivingUnit->code,
                'name' => $this->firstReceivingUnit->name,
            ]),
            'received_at' => $this->received_at?->toISOString(),
            'requested_due_at' => $this->requested_due_at?->toISOString(),
            'registered_by' => $this->registered_by,
            'registrar' => $this->whenLoaded('registrar', fn () => [
                'id' => $this->registrar->id,
                'name' => $this->registrar->name,
            ]),
            'registered_at' => $this->registered_at?->toISOString(),
            'participants' => $this->whenLoaded('participants'),
            'movements' => $this->whenLoaded('movements', fn () => $this->movements->map(fn ($m) => [
                'id' => $m->id,
                'document_id' => $m->document_id,
                'from_unit_id' => $m->from_unit_id,
                'from_unit' => $m->fromUnit ? [
                    'id' => $m->fromUnit->id,
                    'code' => $m->fromUnit->code,
                    'name' => $m->fromUnit->name,
                ] : null,
                'to_unit_id' => $m->to_unit_id,
                'to_unit' => $m->toUnit ? [
                    'id' => $m->toUnit->id,
                    'code' => $m->toUnit->code,
                    'name' => $m->toUnit->name,
                ] : null,
                'sent_by' => $m->sent_by,
                'sent_by_user' => $m->sentBy ? [
                    'id' => $m->sentBy->id,
                    'name' => $m->sentBy->name,
                ] : null,
                'sent_at' => $m->sent_at?->toISOString(),
                'instruction' => $m->instruction,
                'due_at' => $m->due_at?->toISOString(),
                'receipt' => $m->receipt ? [
                    'id' => $m->receipt->id,
                    'movement_id' => $m->receipt->movement_id,
                    'acknowledged_by' => $m->receipt->acknowledged_by,
                    'acknowledged_at' => $m->receipt->acknowledged_at?->toISOString(),
                    'acknowledged_by_user' => $m->receipt->acknowledgedBy ? [
                        'id' => $m->receipt->acknowledgedBy->id,
                        'name' => $m->receipt->acknowledgedBy->name,
                    ] : null,
                ] : null,
            ])),
            'assignments' => $this->whenLoaded('assignments', fn () => $this->assignments->map(fn ($a) => [
                'id' => $a->id,
                'document_id' => $a->document_id,
                'assigned_user_id' => $a->assigned_user_id,
                'assigned_user' => $a->assignedUser ? [
                    'id' => $a->assignedUser->id,
                    'name' => $a->assignedUser->name,
                    'email' => $a->assignedUser->email,
                ] : null,
                'assigned_unit_id' => $a->assigned_unit_id,
                'assigned_unit' => $a->assignedUnit ? [
                    'id' => $a->assignedUnit->id,
                    'code' => $a->assignedUnit->code,
                    'name' => $a->assignedUnit->name,
                ] : null,
                'assigned_by' => $a->assigned_by,
                'assigned_at' => $a->assigned_at?->toISOString(),
                'due_at' => $a->due_at?->toISOString(),
                'instruction' => $a->instruction,
            ])),
        ];
    }
}

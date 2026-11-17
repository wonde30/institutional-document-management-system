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
            'participants' => $this->whenLoaded('participants', fn () => $this->participants->map(fn ($p) => [
                'id' => $p->id,
                'participation_type' => $p->participation_type,
                'unit' => $p->party?->unit ? [
                    'id' => $p->party->unit->id,
                    'name' => $p->party->unit->name,
                    'code' => $p->party->unit->code,
                ] : null,
                'user' => $p->party?->user ? [
                    'id' => $p->party->user->id,
                    'name' => $p->party->user->name,
                ] : null,
                'external_party' => $p->party?->externalParty ? [
                    'id' => $p->party->externalParty->id,
                    'name' => $p->party->externalParty->name,
                ] : null,
            ])),
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
            'dispatches' => $this->whenLoaded('dispatches', fn () => $this->dispatches->map(fn ($d) => [
                'id' => $d->id,
                'participant_id' => $d->participant_id,
                'document_version_id' => $d->document_version_id,
                'method_id' => $d->method_id,
                'method' => $d->method ? [
                    'id' => $d->method->id,
                    'code' => $d->method->code,
                    'name' => $d->method->name,
                ] : null,
                'dispatched_by' => $d->dispatched_by,
                'dispatched_by_user' => $d->dispatchedBy ? [
                    'id' => $d->dispatchedBy->id,
                    'name' => $d->dispatchedBy->name,
                ] : null,
                'dispatched_at' => $d->dispatched_at?->toISOString(),
                'tracking_reference' => $d->tracking_reference,
                'evidence_file_id' => $d->evidence_file_id,
                'has_delivery_evidence' => ! empty($d->evidence_file_id),
                'evidence_file' => $d->evidenceFile ? [
                    'id' => $d->evidenceFile->id,
                    'original_filename' => $d->evidenceFile->original_filename,
                    'byte_size' => $d->evidenceFile->byte_size,
                    'sha256' => $d->evidenceFile->sha256,
                ] : null,
            ])),
            'lifecycle' => $this->document?->lifecycle,
            'is_closed' => in_array($this->document?->lifecycle, ['retired', 'closed']),
            'is_outgoing' => ($this->type?->code === 'outgoing') || ($this->relationLoaded('dispatches') && $this->dispatches->isNotEmpty()),
            'can_close' => (function () {
                if (in_array($this->document?->lifecycle, ['retired', 'closed'])) {
                    return false;
                }
                $isOutgoing = ($this->type?->code === 'outgoing') || ($this->relationLoaded('dispatches') && $this->dispatches->isNotEmpty());
                if ($isOutgoing) {
                    if (! $this->relationLoaded('dispatches') || $this->dispatches->isEmpty()) {
                        return false;
                    }
                    foreach ($this->dispatches as $d) {
                        if (empty($d->evidence_file_id)) {
                            return false;
                        }
                    }
                }
                return true;
            })(),
            'closure_blockers' => (function () {
                $blockers = [];
                if (in_array($this->document?->lifecycle, ['retired', 'closed'])) {
                    $blockers[] = 'This correspondence is already formally closed.';
                    return $blockers;
                }
                $isOutgoing = ($this->type?->code === 'outgoing') || ($this->relationLoaded('dispatches') && $this->dispatches->isNotEmpty());
                if ($isOutgoing) {
                    if (! $this->relationLoaded('dispatches') || $this->dispatches->isEmpty()) {
                        $blockers[] = 'Outgoing correspondence requires dispatch before formal closure.';
                    } else {
                        foreach ($this->dispatches as $d) {
                            if (empty($d->evidence_file_id)) {
                                $blockers[] = "Dispatch #{$d->id} is awaiting recorded proof of delivery.";
                            }
                        }
                    }
                }
                return $blockers;
            })(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Collection;

class DocumentTimelineService
{
    /**
     * Build a synthesized chronological timeline of all events for a given document.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTimeline(Document $document): array
    {
        $events = collect();

        // 1. Document Registration
        $events->push([
            'id' => "doc-reg-{$document->id}",
            'event_type' => 'document_registered',
            'title' => 'Document Registered in Repository',
            'description' => "Initial registration with reference {$document->reference_number}",
            'actor' => $document->creator ? ['id' => $document->creator->id, 'name' => $document->creator->name] : null,
            'timestamp' => $document->registered_at ? $document->registered_at->toISOString() : null,
            'metadata' => [
                'reference_number' => $document->reference_number,
                'category' => $document->category?->name,
                'confidentiality' => $document->confidentiality?->name,
            ],
        ]);

        // 2. Version Creations
        foreach ($document->versions as $ver) {
            if ($ver->version_number > 1) {
                $events->push([
                    'id' => "doc-ver-{$ver->id}",
                    'event_type' => 'version_created',
                    'title' => "Document Version {$ver->version_number} Created",
                    'description' => $ver->change_note ?? "Version {$ver->version_number} appended ({$ver->ingestion_method})",
                    'actor' => $ver->createdBy ? ['id' => $ver->createdBy->id, 'name' => $ver->createdBy->name] : null,
                    'timestamp' => $ver->created_at ? $ver->created_at->toISOString() : null,
                    'metadata' => [
                        'version_number' => $ver->version_number,
                        'ingestion_method' => $ver->ingestion_method,
                        'file_count' => $ver->versionFiles->count(),
                    ],
                ]);
            }
        }

        // 3. Physical Originals & Custody Events
        foreach ($document->physicalOriginals as $orig) {
            foreach ($orig->custodyEvents as $custody) {
                $events->push([
                    'id' => "custody-{$custody->id}",
                    'event_type' => 'physical_custody',
                    'title' => 'Physical Paper Custody: '.ucfirst($custody->action),
                    'description' => $custody->reason ?? "Physical custody action '{$custody->action}' recorded",
                    'actor' => $custody->actor ? ['id' => $custody->actor->id, 'name' => $custody->actor->name] : null,
                    'timestamp' => $custody->occurred_at ? $custody->occurred_at->toISOString() : null,
                    'metadata' => [
                        'action' => $custody->action,
                        'physical_reference' => $orig->physical_reference,
                        'custodian' => $custody->custodian?->name,
                    ],
                ]);
            }
        }

        // 4. Document Movements & Acknowledgment Receipts
        foreach ($document->movements as $m) {
            $fromName = $m->fromUnit?->name ?? 'Originating Office';
            $toName = $m->toUnit?->name ?? 'Destination Office';

            $events->push([
                'id' => "movement-{$m->id}",
                'event_type' => 'movement_dispatched',
                'title' => "Transit Dispatched: {$fromName} \u{2192} {$toName}",
                'description' => $m->instruction ?? 'Document forwarded between administrative offices',
                'actor' => $m->sentBy ? ['id' => $m->sentBy->id, 'name' => $m->sentBy->name] : null,
                'timestamp' => $m->sent_at ? $m->sent_at->toISOString() : null,
                'metadata' => [
                    'from_unit' => $fromName,
                    'to_unit' => $toName,
                    'due_at' => $m->due_at?->toISOString(),
                ],
            ]);

            if ($m->receipt) {
                $events->push([
                    'id' => "movement-rcpt-{$m->receipt->id}",
                    'event_type' => 'movement_acknowledged',
                    'title' => "Custody Receipt Acknowledged: {$toName}",
                    'description' => "Physical custody confirmed by recipient officer",
                    'actor' => $m->receipt->acknowledgedBy ? ['id' => $m->receipt->acknowledgedBy->id, 'name' => $m->receipt->acknowledgedBy->name] : null,
                    'timestamp' => $m->receipt->acknowledged_at ? $m->receipt->acknowledged_at->toISOString() : null,
                    'metadata' => [
                        'movement_id' => $m->id,
                        'to_unit' => $toName,
                    ],
                ]);
            }
        }

        // 5. Operational Assignments & Responses
        foreach ($document->assignments as $a) {
            $assigneeName = $a->assignedUser?->name ?? $a->assignedUnit?->name ?? 'Assigned Officer';

            $events->push([
                'id' => "assignment-{$a->id}",
                'event_type' => 'directive_assigned',
                'title' => "Directive Assigned: {$assigneeName}",
                'description' => $a->instruction,
                'actor' => $a->assignedBy ? ['id' => $a->assignedBy->id, 'name' => $a->assignedBy->name] : null,
                'timestamp' => $a->assigned_at ? $a->assigned_at->toISOString() : null,
                'metadata' => [
                    'assigned_to' => $assigneeName,
                    'status' => $a->status,
                    'due_at' => $a->due_at?->toISOString(),
                ],
            ]);

            // Assignment Events
            foreach ($a->events as $ev) {
                if ($ev->action !== 'assigned') {
                    $events->push([
                        'id' => "assignment-ev-{$ev->id}",
                        'event_type' => 'assignment_event',
                        'title' => 'Directive Status: '.ucwords(str_replace('_', ' ', $ev->action)),
                        'description' => $ev->comment ?? "Action '{$ev->action}' recorded",
                        'actor' => $ev->actor ? ['id' => $ev->actor->id, 'name' => $ev->actor->name] : null,
                        'timestamp' => $ev->occurred_at ? $ev->occurred_at->toISOString() : null,
                        'metadata' => [
                            'action' => $ev->action,
                            'target_user' => $ev->targetUser?->name,
                        ],
                    ]);
                }
            }

            // Linked Responses
            foreach ($a->responses as $resp) {
                $respDoc = $resp->responseDocument;
                $events->push([
                    'id' => "assignment-resp-{$a->id}-{$resp->response_document_id}",
                    'event_type' => 'assignment_response_linked',
                    'title' => 'Fulfilling Response Linked: #'.($respDoc?->reference_number ?? $resp->response_document_id),
                    'description' => "Response linked with role '{$resp->relationship_role}' (fulfilling: ".($resp->is_fulfilling ? 'Yes' : 'No').")",
                    'actor' => $resp->linkedBy ? ['id' => $resp->linkedBy->id, 'name' => $resp->linkedBy->name] : null,
                    'timestamp' => $resp->linked_at ? $resp->linked_at->toISOString() : null,
                    'metadata' => [
                        'response_document_id' => $resp->response_document_id,
                        'relationship_role' => $resp->relationship_role,
                        'is_fulfilling' => (bool) $resp->is_fulfilling,
                    ],
                ]);
            }
        }

        // 6. Workflow Instances & Decisions
        foreach ($document->versions as $ver) {
            foreach ($ver->workflowInstances as $wi) {
                foreach ($wi->events as $we) {
                    $events->push([
                        'id' => "wf-event-{$we->id}",
                        'event_type' => 'workflow_transition',
                        'title' => 'Workflow Event: '.ucwords(str_replace('_', ' ', $we->event_kind)),
                        'description' => $we->reason ?? ($we->transition ? "Transitioned via '{$we->transition->action_code}'" : "Workflow {$we->event_kind}"),
                        'actor' => $we->actor ? ['id' => $we->actor->id, 'name' => $we->actor->name] : null,
                        'timestamp' => $we->occurred_at ? $we->occurred_at->toISOString() : null,
                        'metadata' => [
                            'instance_id' => $wi->id,
                            'event_kind' => $we->event_kind,
                            'transition_id' => $we->transition_id,
                        ],
                    ]);
                }
            }
        }

        // Sort chronologically ascending
        return $events->filter(fn ($item) => ! empty($item['timestamp']))
            ->sortBy('timestamp')
            ->values()
            ->all();
    }
}

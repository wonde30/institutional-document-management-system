<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AssignmentEvent;
use App\Models\AssignmentParticipant;
use App\Models\AssignmentResponse;
use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\DocumentAssignment;
use App\Models\DocumentRelation;
use App\Models\DocumentRelationType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AssignmentService
{
    /**
     * Create an operational assignment for a document.
     *
     * @param array<string, mixed> $data
     */
    public function createAssignment(Document $document, array $data, User $actor): DocumentAssignment
    {
        $now = now()->format('Y-m-d H:i:s.u');

        return DB::transaction(function () use ($document, $data, $actor, $now) {
            $assignment = DocumentAssignment::create([
                'document_id' => $document->id,
                'assigned_user_id' => $data['assigned_user_id'] ?? null,
                'assigned_unit_id' => $data['assigned_unit_id'] ?? null,
                'assigned_by' => $actor->id,
                'assigned_at' => $now,
                'due_at' => $data['due_at'] ?? null,
                'instruction' => $data['instruction'],
            ]);

            // Automatically add primary assigned user as lead participant if assigned
            if (! empty($data['assigned_user_id'])) {
                AssignmentParticipant::create([
                    'assignment_id' => $assignment->id,
                    'user_id' => $data['assigned_user_id'],
                    'role_code' => 'lead',
                    'status' => 'pending',
                    'assigned_at' => $now,
                ]);
            }

            // Add any additional participants passed in request
            if (! empty($data['participants']) && is_array($data['participants'])) {
                foreach ($data['participants'] as $p) {
                    if ((int) $p['user_id'] !== (int) ($data['assigned_user_id'] ?? 0)) {
                        AssignmentParticipant::create([
                            'assignment_id' => $assignment->id,
                            'user_id' => $p['user_id'],
                            'role_code' => $p['role_code'] ?? 'contributor',
                            'status' => 'pending',
                            'assigned_at' => $now,
                        ]);
                    }
                }
            }

            // Record initial assignment event
            AssignmentEvent::create([
                'assignment_id' => $assignment->id,
                'sequence_number' => 1,
                'actor_id' => $actor->id,
                'target_user_id' => $data['assigned_user_id'] ?? null,
                'action' => 'assigned',
                'occurred_at' => $now,
                'comment' => $data['instruction'],
            ]);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'document.assigned',
                'target_type' => 'document_assignment',
                'target_id' => (string) $assignment->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => [
                    'document_id' => $document->id,
                    'assigned_to' => $data['assigned_user_id'] ?? null,
                    'assigned_unit' => $data['assigned_unit_id'] ?? null,
                ],
            ]);

            try {
                app(\App\Services\EmailNotificationService::class)->notifyAssignment($assignment, 'assigned');
            } catch (\Throwable $e) {
                // Log and continue without interrupting assignment transaction
            }

            return $assignment;
        });
    }

    /**
     * Add a participant to an existing assignment.
     */
    public function addParticipant(
        DocumentAssignment $assignment,
        int $userId,
        string $roleCode = 'contributor',
        ?User $actor = null
    ): AssignmentParticipant {
        if (! in_array($roleCode, ['lead', 'writer', 'contributor', 'secretary'], true)) {
            throw new InvalidArgumentException("Invalid participant role code: {$roleCode}");
        }

        $now = now()->format('Y-m-d H:i:s.u');

        return DB::transaction(function () use ($assignment, $userId, $roleCode, $actor, $now) {
            $participant = AssignmentParticipant::updateOrCreate(
                ['assignment_id' => $assignment->id, 'user_id' => $userId],
                ['role_code' => $roleCode, 'status' => 'pending', 'assigned_at' => $now]
            );

            if ($actor) {
                $seq = (DB::table('assignment_events')->where('assignment_id', $assignment->id)->max('sequence_number') ?? 0) + 1;
                AssignmentEvent::create([
                    'assignment_id' => $assignment->id,
                    'sequence_number' => $seq,
                    'actor_id' => $actor->id,
                    'target_user_id' => $userId,
                    'action' => 'delegated',
                    'occurred_at' => $now,
                    'comment' => "Added participant with role '{$roleCode}'",
                ]);
            }

            return $participant;
        });
    }

    /**
     * Remove a participant from an assignment.
     */
    public function removeParticipant(DocumentAssignment $assignment, int $userId): void
    {
        AssignmentParticipant::where('assignment_id', $assignment->id)
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * Record a lifecycle or delegation event on an assignment.
     *
     * @param array<string, mixed> $data
     */
    public function recordEvent(DocumentAssignment $assignment, array $data, User $actor): AssignmentEvent
    {
        $now = now()->format('Y-m-d H:i:s.u');
        $action = $data['action'];

        return DB::transaction(function () use ($assignment, $data, $actor, $now, $action) {
            $seq = (DB::table('assignment_events')->where('assignment_id', $assignment->id)->max('sequence_number') ?? 0) + 1;
            $event = AssignmentEvent::create([
                'assignment_id' => $assignment->id,
                'sequence_number' => $seq,
                'actor_id' => $actor->id,
                'target_user_id' => $data['target_user_id'] ?? null,
                'action' => $action,
                'occurred_at' => $now,
                'comment' => $data['comment'] ?? null,
            ]);

            return $event;
        });
    }

    /**
     * Link a response document that answers/fulfills the assignment.
     *
     * @param array<string, mixed> $data
     */
    public function linkResponse(
        DocumentAssignment $assignment,
        Document $responseDoc,
        array $data,
        User $actor
    ): AssignmentResponse {
        $now = now()->format('Y-m-d H:i:s.u');
        $relationshipRole = $data['relationship_role'] ?? 'full_reply';
        $isFulfilling = (bool) ($data['is_fulfilling'] ?? 1);

        return DB::transaction(function () use ($assignment, $responseDoc, $relationshipRole, $isFulfilling, $actor, $now) {
            // 1. Create or update assignment_responses entry
            $response = AssignmentResponse::updateOrCreate(
                [
                    'assignment_id' => $assignment->id,
                    'response_document_id' => $responseDoc->id,
                ],
                [
                    'relationship_role' => $relationshipRole,
                    'is_fulfilling' => $isFulfilling ? 1 : 0,
                    'linked_by' => $actor->id,
                    'linked_at' => $now,
                ]
            );

            // 2. Establish canonical responds_to relationship in document_relations
            $relType = DocumentRelationType::where('code', 'responds_to')->first();
            if ($relType) {
                DocumentRelation::firstOrCreate([
                    'source_id' => $responseDoc->id,
                    'target_id' => $assignment->document_id,
                    'relation_type_id' => $relType->id,
                ]);
            }

            // 3. If fulfilling full_reply, auto-complete the assignment
            if ($isFulfilling && $relationshipRole === 'full_reply') {
                $seq = (DB::table('assignment_events')->where('assignment_id', $assignment->id)->max('sequence_number') ?? 0) + 1;
                AssignmentEvent::create([
                    'assignment_id' => $assignment->id,
                    'sequence_number' => $seq,
                    'actor_id' => $actor->id,
                    'target_user_id' => null,
                    'action' => 'completed',
                    'occurred_at' => $now,
                    'comment' => "Auto-completed via linked full reply document #{$responseDoc->reference_number}",
                ]);
            }

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'assignment.response_linked',
                'target_type' => 'document_assignment',
                'target_id' => (string) $assignment->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => [
                    'response_document_id' => $responseDoc->id,
                    'relationship_role' => $relationshipRole,
                    'is_fulfilling' => $isFulfilling,
                ],
            ]);

            return $response;
        });
    }
}

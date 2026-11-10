<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AccreditationCycle;
use App\Models\AccreditationEvidence;
use App\Models\AccreditationFramework;
use App\Models\AccreditationFrameworkVersion;
use App\Models\AccreditationReview;
use App\Models\AccreditationStandard;
use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccreditationController extends Controller
{
    /**
     * List all accreditation frameworks and active versions.
     */
    public function frameworks(): JsonResponse
    {
        $frameworks = AccreditationFramework::with('versions')->where('active', true)->get();

        return response()->json([
            'success' => true,
            'data' => $frameworks,
        ]);
    }

    /**
     * List accreditation standards, optionally filtered by framework version or cycle.
     */
    public function standards(Request $request): JsonResponse
    {
        $query = AccreditationStandard::with(['frameworkVersion', 'parent']);

        if ($request->filled('cycle_id')) {
            $cycle = AccreditationCycle::findOrFail($request->input('cycle_id'));
            $query->where('framework_version_id', $cycle->framework_version_id);
        } elseif ($request->filled('framework_version_id')) {
            $query->where('framework_version_id', $request->input('framework_version_id'));
        }

        $standards = $query->orderBy('code')->get();

        return response()->json([
            'success' => true,
            'data' => $standards,
        ]);
    }

    /**
     * List accreditation cycles with assigned institutional units and framework versions.
     */
    public function cycles(Request $request): JsonResponse
    {
        $query = AccreditationCycle::with(['unit', 'frameworkVersion.framework'])
            ->withCount('evidence');

        if ($request->filled('unit_id')) {
            $query->where('unit_id', $request->input('unit_id'));
        }

        if ($request->filled('active')) {
            $isActive = filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN);
            if ($isActive) {
                $query->where(function ($q) {
                    $q->whereNull('ends_on')->orWhere('ends_on', '>=', now()->toDateString());
                });
            } else {
                $query->where('ends_on', '<', now()->toDateString());
            }
        }

        $cycles = $query->orderByDesc('id')->get();

        return response()->json([
            'success' => true,
            'data' => $cycles,
        ]);
    }

    /**
     * Show single accreditation cycle details.
     */
    public function showCycle(int $id): JsonResponse
    {
        $cycle = AccreditationCycle::with(['unit', 'frameworkVersion.framework', 'frameworkVersion.standards'])
            ->withCount('evidence')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $cycle,
        ]);
    }

    /**
     * Create an institutional accreditation cycle.
     */
    public function storeCycle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'framework_version_id' => 'required|exists:accreditation_framework_versions,id',
            'unit_id' => 'required|exists:organization_units,id',
            'name' => 'required|string|max:200',
            'starts_on' => 'required|date',
            'ends_on' => 'nullable|date|after_or_equal:starts_on',
        ]);

        // Uniqueness check for (framework_version_id, unit_id, name)
        $exists = AccreditationCycle::where('framework_version_id', $validated['framework_version_id'])
            ->where('unit_id', $validated['unit_id'])
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'An accreditation cycle with this identical title, framework version, and institutional unit already exists.',
            ], 422);
        }

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');

        $cycle = DB::transaction(function () use ($validated, $actor, $now) {
            $record = AccreditationCycle::create($validated);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'accreditation.cycle_created',
                'target_type' => 'accreditation_cycle',
                'target_id' => (string) $record->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => [
                    'name' => $record->name,
                    'unit_id' => $record->unit_id,
                    'starts_on' => $record->starts_on?->toDateString(),
                    'ends_on' => $record->ends_on?->toDateString(),
                ],
            ]);

            return $record;
        });

        return response()->json([
            'success' => true,
            'message' => 'Institutional accreditation cycle initialized successfully.',
            'data' => $cycle->load(['unit', 'frameworkVersion.framework']),
        ], 201);
    }

    /**
     * Update an accreditation cycle.
     */
    public function updateCycle(Request $request, int $id): JsonResponse
    {
        $cycle = AccreditationCycle::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:200',
            'starts_on' => 'sometimes|required|date',
            'ends_on' => 'nullable|date|after_or_equal:starts_on',
        ]);

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');

        DB::transaction(function () use ($cycle, $validated, $actor, $now) {
            $cycle->update($validated);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'accreditation.cycle_updated',
                'target_type' => 'accreditation_cycle',
                'target_id' => (string) $cycle->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => $validated,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Accreditation cycle updated successfully.',
            'data' => $cycle->fresh()->load(['unit', 'frameworkVersion.framework']),
        ]);
    }

    /**
     * List accreditation evidence with filtering and eager loading.
     */
    public function evidence(Request $request): JsonResponse
    {
        $query = AccreditationEvidence::with([
            'cycle.unit',
            'standard',
            'documentVersion.document.ownerUnit',
            'submittedBy',
            'reviews.reviewer',
            'latestReview.reviewer',
        ]);

        if ($request->filled('cycle_id')) {
            $query->where('cycle_id', $request->input('cycle_id'));
        }

        if ($request->filled('standard_id')) {
            $query->where('standard_id', $request->input('standard_id'));
        }

        if ($request->filled('unit_id')) {
            $unitId = $request->input('unit_id');
            $query->whereHas('cycle', fn ($cq) => $cq->where('unit_id', $unitId));
        }

        if ($request->filled('decision')) {
            $decision = $request->input('decision');
            if ($decision === 'pending') {
                $query->whereDoesntHave('reviews');
            } else {
                $query->whereHas('latestReview', fn ($rq) => $rq->where('decision', $decision));
            }
        }

        $evidence = $query->orderByDesc('id')->get();

        return response()->json([
            'success' => true,
            'data' => $evidence,
        ]);
    }

    /**
     * Show single evidence record with full review trail and document metadata.
     */
    public function showEvidence(int $id): JsonResponse
    {
        $record = AccreditationEvidence::with([
            'cycle.unit',
            'standard',
            'documentVersion.document.ownerUnit',
            'documentVersion.files.fileObject',
            'submittedBy',
            'reviews.reviewer',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $record,
        ]);
    }

    /**
     * Submit an exact document version as accreditation evidence.
     */
    public function storeEvidence(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cycle_id' => 'required|exists:accreditation_cycles,id',
            'standard_id' => 'required|exists:accreditation_standards,id',
            'document_version_id' => 'required|exists:document_versions,id',
            'note' => 'nullable|string|max:1000',
        ]);

        $cycle = AccreditationCycle::findOrFail($validated['cycle_id']);

        // Guard: Prevent submissions to closed accreditation cycles
        if ($cycle->ends_on && $cycle->ends_on->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Accreditation submission rejected: The selected accreditation cycle was closed on ' . $cycle->ends_on->toDateString() . '. Closed cycles cannot receive new evidence.',
            ], 422);
        }

        // Guard: Enforce standard belongs to cycle's framework version
        $standard = AccreditationStandard::findOrFail($validated['standard_id']);
        if ($standard->framework_version_id !== $cycle->framework_version_id) {
            return response()->json([
                'success' => false,
                'message' => 'Accreditation standard mismatch: The chosen standard does not belong to the regulatory framework version assigned to this cycle.',
            ], 422);
        }

        // Idempotency check: uniqueness constraint (cycle_id, standard_id, document_version_id)
        $alreadySubmitted = AccreditationEvidence::where('cycle_id', $validated['cycle_id'])
            ->where('standard_id', $validated['standard_id'])
            ->where('document_version_id', $validated['document_version_id'])
            ->exists();

        if ($alreadySubmitted) {
            return response()->json([
                'success' => false,
                'message' => 'Duplicate submission rejected: This exact document version has already been submitted as evidence for this standard within the current cycle.',
            ], 422);
        }

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');

        $evidence = DB::transaction(function () use ($validated, $actor, $now, $cycle) {
            $record = AccreditationEvidence::create([
                'cycle_id' => $validated['cycle_id'],
                'standard_id' => $validated['standard_id'],
                'document_version_id' => $validated['document_version_id'],
                'submitted_by' => $actor->id,
                'submitted_at' => $now,
                'note' => $validated['note'] ?? null,
            ]);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'accreditation.evidence_submitted',
                'target_type' => 'accreditation_evidence',
                'target_id' => (string) $record->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => [
                    'cycle_id' => $record->cycle_id,
                    'cycle_name' => $cycle->name,
                    'standard_id' => $record->standard_id,
                    'version_id' => $record->document_version_id,
                    'submitted_by' => $actor->id,
                ],
            ]);

            return $record;
        });

        return response()->json([
            'success' => true,
            'message' => 'Accreditation evidence mapped to HERQA quality standard successfully.',
            'data' => $evidence->load([
                'cycle.unit',
                'standard',
                'documentVersion.document',
                'submittedBy',
            ]),
        ], 201);
    }

    /**
     * Record an audit review decision on submitted accreditation evidence.
     */
    public function reviewEvidence(Request $request, int $id): JsonResponse
    {
        $evidence = AccreditationEvidence::with('cycle')->findOrFail($id);

        if ($evidence->cycle->ends_on && $evidence->cycle->ends_on->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Review rejected: Accreditation cycle is formally closed. Historical reviews cannot be altered.',
            ], 422);
        }

        $validated = $request->validate([
            'decision' => 'required|string|in:accepted,rejected,needs_revision',
            'comment' => 'nullable|string|max:2000',
        ]);

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');

        $review = DB::transaction(function () use ($evidence, $validated, $actor, $now) {
            $rev = AccreditationReview::create([
                'evidence_id' => $evidence->id,
                'reviewer_id' => $actor->id,
                'decision' => $validated['decision'],
                'comment' => $validated['comment'] ?? null,
                'reviewed_at' => $now,
            ]);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'accreditation.evidence_reviewed',
                'target_type' => 'accreditation_review',
                'target_id' => (string) $rev->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => [
                    'evidence_id' => $evidence->id,
                    'decision' => $rev->decision,
                    'reviewer_id' => $actor->id,
                ],
            ]);

            return $rev;
        });

        return response()->json([
            'success' => true,
            'message' => 'Accreditation evidence review decision recorded successfully.',
            'data' => $review->load(['reviewer', 'evidence.standard']),
        ], 201);
    }

    /**
     * Calculate genuine evidence completeness and status analytics for an accreditation cycle.
     */
    public function summary(Request $request, int $cycleId): JsonResponse
    {
        $cycle = AccreditationCycle::with(['unit', 'frameworkVersion.framework'])->findOrFail($cycleId);

        // All standards under this framework version
        $standards = AccreditationStandard::where('framework_version_id', $cycle->framework_version_id)
            ->orderBy('code')
            ->get();

        $totalStandards = $standards->count();

        // All evidence for this cycle with reviews
        $evidence = AccreditationEvidence::where('cycle_id', $cycleId)
            ->with(['latestReview', 'standard', 'documentVersion.document'])
            ->get();

        $standardStatus = [];
        $acceptedCount = 0;
        $rejectedCount = 0;
        $needsRevisionCount = 0;
        $pendingCount = 0;

        foreach ($standards as $std) {
            $evList = $evidence->where('standard_id', $std->id);
            $hasEvidence = $evList->isNotEmpty();

            $status = 'missing';
            if ($hasEvidence) {
                // Determine cumulative status based on latest reviews
                $latestReviews = $evList->map(fn ($e) => $e->latestReview)->filter();

                if ($latestReviews->isEmpty()) {
                    $status = 'pending_review';
                    $pendingCount++;
                } elseif ($latestReviews->contains('decision', 'accepted')) {
                    $status = 'accepted';
                    $acceptedCount++;
                } elseif ($latestReviews->contains('decision', 'needs_revision')) {
                    $status = 'needs_revision';
                    $needsRevisionCount++;
                } else {
                    $status = 'rejected';
                    $rejectedCount++;
                }
            }

            $standardStatus[] = [
                'standard_id' => $std->id,
                'code' => $std->code,
                'title' => $std->title,
                'status' => $status,
                'evidence_count' => $evList->count(),
                'evidence_ids' => $evList->pluck('id')->values()->all(),
            ];
        }

        $coveredStandards = $acceptedCount + $rejectedCount + $needsRevisionCount + $pendingCount;
        $submissionRate = $totalStandards > 0 ? round(($coveredStandards / $totalStandards) * 100, 1) : 0;
        $acceptanceRate = $totalStandards > 0 ? round(($acceptedCount / $totalStandards) * 100, 1) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'cycle' => [
                    'id' => $cycle->id,
                    'name' => $cycle->name,
                    'unit' => $cycle->unit?->name,
                    'framework' => $cycle->frameworkVersion?->framework?->name,
                    'version' => $cycle->frameworkVersion?->version_label,
                    'starts_on' => $cycle->starts_on?->toDateString(),
                    'ends_on' => $cycle->ends_on?->toDateString(),
                    'is_closed' => $cycle->ends_on && $cycle->ends_on->isPast(),
                ],
                'metrics' => [
                    'total_standards' => $totalStandards,
                    'covered_standards' => $coveredStandards,
                    'missing_standards' => $totalStandards - $coveredStandards,
                    'accepted_standards' => $acceptedCount,
                    'pending_review' => $pendingCount,
                    'needs_revision' => $needsRevisionCount,
                    'rejected' => $rejectedCount,
                    'submission_rate_percent' => $submissionRate,
                    'acceptance_rate_percent' => $acceptanceRate,
                    'total_evidence_records' => $evidence->count(),
                ],
                'standards_breakdown' => $standardStatus,
            ],
        ]);
    }

    /**
     * Export accreditation cycle evidence dossier.
     */
    public function export(Request $request, int $cycleId): JsonResponse
    {
        $cycle = AccreditationCycle::with(['unit', 'frameworkVersion.framework'])->findOrFail($cycleId);

        $evidence = AccreditationEvidence::where('cycle_id', $cycleId)
            ->with([
                'standard',
                'documentVersion.document.ownerUnit',
                'documentVersion.files.fileObject',
                'submittedBy',
                'reviews.reviewer',
            ])
            ->get();

        $dossier = [
            'institution' => 'Wollo University',
            'cycle' => [
                'id' => $cycle->id,
                'name' => $cycle->name,
                'responsible_unit' => $cycle->unit?->name,
                'framework' => $cycle->frameworkVersion?->framework?->name,
                'framework_code' => $cycle->frameworkVersion?->framework?->code,
                'version' => $cycle->frameworkVersion?->version_label,
                'period' => [
                    'starts_on' => $cycle->starts_on?->toDateString(),
                    'ends_on' => $cycle->ends_on?->toDateString(),
                ],
            ],
            'export_timestamp' => now()->toISOString(),
            'total_evidence_items' => $evidence->count(),
            'items' => $evidence->map(function ($e) {
                return [
                    'evidence_id' => $e->id,
                    'standard_code' => $e->standard?->code,
                    'standard_title' => $e->standard?->title,
                    'document_reference' => $e->documentVersion?->document?->reference_number,
                    'document_title' => $e->documentVersion?->document?->title,
                    'version_number' => $e->documentVersion?->version_number,
                    'owner_unit' => $e->documentVersion?->document?->ownerUnit?->name,
                    'submitted_by' => $e->submittedBy?->name,
                    'submitted_at' => $e->submitted_at?->toISOString(),
                    'note' => $e->note,
                    'files' => $e->documentVersion?->files->map(fn ($f) => [
                        'filename' => $f->fileObject?->original_filename,
                        'sha256' => $f->fileObject?->sha256,
                        'byte_size' => $f->fileObject?->byte_size,
                    ]),
                    'review_history' => $e->reviews->map(fn ($r) => [
                        'decision' => $r->decision,
                        'comment' => $r->comment,
                        'reviewer' => $r->reviewer?->name,
                        'reviewed_at' => $r->reviewed_at?->toISOString(),
                    ]),
                ];
            }),
        ];

        return response()->json([
            'success' => true,
            'data' => $dossier,
        ]);
    }
}

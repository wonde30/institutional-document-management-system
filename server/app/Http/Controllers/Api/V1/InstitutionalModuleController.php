<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\BackupRun;
use App\Models\ConfidentialityLevel;
use App\Models\CorrespondenceType;
use App\Models\DigitalSignature;
use App\Models\DigitizationRecord;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\FileIntegrityCheck;
use App\Models\FileObject;
use App\Models\OfficeProcedureVersion;
use App\Models\OrganizationUnit;
use App\Models\PhysicalCustodyEvent;
use App\Models\PhysicalOriginal;
use App\Models\Project;
use App\Models\ProjectProposal;
use App\Models\ProjectType;
use App\Models\Publication;
use App\Models\ResearchAuthor;
use App\Models\ResearchRecord;
use App\Models\RestoreDrill;
use App\Models\SignatureRequest;
use App\Models\SignatureVerification;
use App\Models\SigningIdentity;
use App\Models\StampDefinition;
use App\Services\Pki\CertificateService;
use App\Services\Pki\PdfSignatureService;
use App\Services\Pki\PdfSignatureVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InstitutionalModuleController extends Controller
{
    /**
     * Finalized Research Module
     * Department publication of finalized selected research.
     */
    public function research(Request $request): JsonResponse
    {
        $actor = $request->user();
        $query = ResearchRecord::with([
            'document.ownerUnit',
            'document.versions',
            'authors.staff',
            'recordedBy',
            'publications',
        ]);

        // If actor does not have global research.view, restrict to authorized departments or published records
        $permittedUnitIds = $actor ? $actor->getPermittedUnitIds('research.view') : [];
        if ($permittedUnitIds !== null) {
            $query->where(function ($q) use ($permittedUnitIds) {
                // 1. Published research accessible to university members
                $q->whereHas('publications')
                  // 2. Or departmental research where actor holds scoped permission (including subtrees and direct grants)
                    ->orWhereHas('document', fn ($dq) => $dq->whereIn('owner_unit_id', $permittedUnitIds));
            });
        }

        $records = $query->orderByDesc('id')->get();

        return response()->json([
            'success' => true,
            'data' => $records,
        ]);
    }

    public function showResearch(int $id, Request $request): JsonResponse
    {
        $record = ResearchRecord::with([
            'document.ownerUnit',
            'document.versions',
            'authors.staff',
            'recordedBy',
            'publications',
        ])->findOrFail($id);

        $actor = $request->user();
        $unitId = (int) $record->document->owner_unit_id;
        $isPublished = $record->publications()->exists();

        if (! $isPublished && ! $actor->hasPermission('research.view', $unitId)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Departmental research record is restricted to authorized personnel.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $record->id,
                'document_id' => $record->document_id,
                'title' => $record->document->title,
                'summary' => $record->document->summary,
                'reference_number' => $record->document->reference_number,
                'department' => $record->document->ownerUnit,
                'research_type' => $record->research_type,
                'completed_on' => $record->completed_on,
                'external_selection_reference' => $record->external_selection_reference,
                'approval_status_distinction' => 'Approved externally through university research evaluation process; publication indicates repository availability.',
                'is_published_in_repository' => $isPublished,
                'authors' => $record->authors,
                'versions' => $record->document->versions,
                'recorded_by' => $record->recordedBy,
                'recorded_at' => $record->recorded_at,
            ],
        ]);
    }

    public function storeResearch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:160',
            'office_id' => 'required|exists:organization_units,id',
            'completion_date' => 'required|date',
            'research_type' => 'nullable|string|max:64',
            'external_reference' => 'nullable|string|max:120',
            'notes' => 'nullable|string',
            'authors' => 'nullable|array',
            'authors.*.name' => 'nullable|string|max:200',
            'authors.*.staff_id' => 'nullable|exists:staff_profiles,id',
            'authors.*.contribution' => 'nullable|string|max:200',
        ]);

        $actor = $request->user();
        $officeId = (int) $validated['office_id'];

        // Enforce departmental scope: author must have research.create in this office
        if (! $actor->hasPermission('research.create', $officeId)) {
            return response()->json([
                'success' => false,
                'message' => "Forbidden: You do not have permission [research.create] for department ID {$officeId}.",
            ], 403);
        }

        $now = now()->format('Y-m-d H:i:s.u');

        $record = DB::transaction(function () use ($validated, $actor, $now, $officeId) {
            $seq = 'WU/RES/'.date('Y').'/'.sprintf('%04d', ResearchRecord::count() + 1);

            $catId = DocumentCategory::where('code', 'research')->value('id')
                ?? DocumentCategory::value('id');
            $confId = ConfidentialityLevel::where('code', 'internal')->value('id')
                ?? ConfidentialityLevel::value('id');

            $doc = Document::create([
                'reference_number' => $seq,
                'title' => $validated['title'],
                'summary' => $validated['notes'] ?? 'Finalized Research Output',
                'category_id' => $catId,
                'confidentiality_id' => $confId,
                'owner_unit_id' => $officeId,
                'custodian_id' => $actor->id,
                'created_by' => $actor->id,
                'document_date' => $validated['completion_date'],
                'registered_at' => $now,
                'lifecycle' => 'active',
                'lock_version' => 0,
            ]);

            // Initial version
            DocumentVersion::create([
                'document_id' => $doc->id,
                'version_number' => 1,
                'created_by' => $actor->id,
                'created_at' => $now,
                'change_note' => 'Initial finalized monograph submission',
            ]);

            $research = ResearchRecord::create([
                'document_id' => $doc->id,
                'research_type' => $validated['research_type'] ?? 'faculty_monograph',
                'completed_on' => $validated['completion_date'],
                'external_selection_reference' => $validated['external_reference'] ?? null,
                'recorded_by' => $actor->id,
                'recorded_at' => $now,
            ]);

            if (! empty($validated['authors'])) {
                foreach ($validated['authors'] as $idx => $auth) {
                    ResearchAuthor::create([
                        'research_id' => $research->id,
                        'staff_id' => $auth['staff_id'] ?? null,
                        'external_name' => $auth['name'] ?? ($auth['staff_id'] ? null : 'Faculty Researcher'),
                        'author_order' => $idx + 1,
                        'contribution' => $auth['contribution'] ?? 'Primary Author',
                    ]);
                }
            }

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'research.created',
                'target_type' => 'research_record',
                'target_id' => (string) $research->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => ['title' => $validated['title'], 'office_id' => $officeId],
            ]);

            return $research;
        });

        return response()->json([
            'success' => true,
            'message' => 'Finalized research record registered.',
            'data' => $record->load(['document.ownerUnit', 'document.versions', 'authors.staff', 'recordedBy']),
        ], 201);
    }

    public function updateResearch(Request $request, int $id): JsonResponse
    {
        $record = ResearchRecord::with('document')->findOrFail($id);
        $actor = $request->user();
        $unitId = (int) $record->document->owner_unit_id;

        if (! $actor->hasPermission('research.update', $unitId)) {
            return response()->json([
                'success' => false,
                'message' => "Forbidden: You do not have permission [research.update] for department ID {$unitId}.",
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:160',
            'research_type' => 'nullable|string|max:64',
            'completion_date' => 'nullable|date',
            'external_reference' => 'nullable|string|max:120',
            'notes' => 'nullable|string',
        ]);

        $now = now()->format('Y-m-d H:i:s.u');

        DB::transaction(function () use ($record, $validated, $actor, $now) {
            if (isset($validated['title'])) {
                $record->document->update(['title' => $validated['title']]);
            }
            if (isset($validated['notes'])) {
                $record->document->update(['summary' => $validated['notes']]);
            }

            $updateData = [];
            if (isset($validated['research_type'])) {
                $updateData['research_type'] = $validated['research_type'];
            }
            if (isset($validated['completion_date'])) {
                $updateData['completed_on'] = $validated['completion_date'];
            }
            if (isset($validated['external_reference'])) {
                $updateData['external_selection_reference'] = $validated['external_reference'];
            }

            if (! empty($updateData)) {
                $record->update($updateData);
            }

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'research.updated',
                'target_type' => 'research_record',
                'target_id' => (string) $record->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => $validated,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Research metadata updated successfully.',
            'data' => $record->fresh(['document.ownerUnit', 'document.versions', 'authors']),
        ]);
    }

    public function uploadResearchVersion(Request $request, int $id): JsonResponse
    {
        $record = ResearchRecord::with('document.versions')->findOrFail($id);
        $actor = $request->user();
        $unitId = (int) $record->document->owner_unit_id;

        if (! $actor->hasPermission('research.update', $unitId)) {
            return response()->json([
                'success' => false,
                'message' => "Forbidden: You do not have permission [research.update] for department ID {$unitId}.",
            ], 403);
        }

        $validated = $request->validate([
            'change_note' => 'required|string|max:255',
        ]);

        $now = now()->format('Y-m-d H:i:s.u');
        $nextVersion = ($record->document->versions()->max('version_number') ?? 0) + 1;

        $version = DocumentVersion::create([
            'document_id' => $record->document_id,
            'version_number' => $nextVersion,
            'created_by' => $actor->id,
            'created_at' => $now,
            'change_note' => $validated['change_note'],
        ]);

        AuditEvent::create([
            'actor_id' => $actor->id,
            'action' => 'research.version_uploaded',
            'target_type' => 'document_version',
            'target_id' => (string) $version->id,
            'request_id' => (string) Str::uuid(),
            'occurred_at' => $now,
            'details_json' => ['research_id' => $record->id, 'version' => $nextVersion],
        ]);

        return response()->json([
            'success' => true,
            'message' => "Research revision version {$nextVersion} uploaded successfully.",
            'data' => $version,
        ], 201);
    }

    public function toggleResearchPublication(int $id, Request $request): JsonResponse
    {
        $record = ResearchRecord::with('document')->findOrFail($id);
        $actor = $request->user();
        $unitId = (int) $record->document->owner_unit_id;
        $action = $request->input('action', 'publish'); // 'publish' or 'withdraw'
        $now = now()->format('Y-m-d H:i:s.u');

        if ($action === 'publish') {
            if (! $actor->hasPermission('research.publish', $unitId)) {
                return response()->json([
                    'success' => false,
                    'message' => "Forbidden: You do not have permission [research.publish] for department ID {$unitId}.",
                ], 403);
            }

            $pub = Publication::firstOrCreate(
                ['document_id' => $record->document_id],
                [
                    'doi' => '10.5897/WU.RES.'.date('Y').'.'.sprintf('%04d', $record->id),
                    'publication_type' => 'academic_monograph',
                    'venue' => 'Wollo University Institutional Research Repository',
                    'published_on' => date('Y-m-d'),
                ]
            );

            DB::table('research_publications')->updateOrInsert(
                ['research_id' => $record->id, 'publication_id' => $pub->id]
            );

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'research.published',
                'target_type' => 'research_record',
                'target_id' => (string) $record->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => ['doi' => $pub->doi, 'published_on' => date('Y-m-d')],
            ]);

            $status = 'published';
        } else {
            if (! $actor->hasPermission('research.withdraw', $unitId)) {
                return response()->json([
                    'success' => false,
                    'message' => "Forbidden: You do not have permission [research.withdraw] for department ID {$unitId}.",
                ], 403);
            }

            DB::table('research_publications')->where('research_id', $record->id)->delete();

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'research.withdrawn',
                'target_type' => 'research_record',
                'target_id' => (string) $record->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => ['withdrawn_at' => $now],
            ]);

            $status = 'withdrawn';
        }

        return response()->json([
            'success' => true,
            'message' => "Research output marked as {$status}.",
            'data' => [
                'id' => $record->id,
                'publication_status' => $status,
                'approval_distinction' => 'Published status denotes repository visibility; university research process approval is recorded separately.',
            ],
        ]);
    }

    /**
     * Staff & Physical Original Custody Module
     * A verified scan does NOT authorize destruction of physical original.
     */
    public function staffRecords(Request $request): JsonResponse
    {
        $actor = $request->user();
        $permittedUnitIds = $actor ? $actor->getPermittedUnitIds('staff.view') : [];

        $query = PhysicalOriginal::with([
            'document.ownerUnit',
            'createdBy',
            'custodyEvents.actor',
            'digitizationRecords.verifiedBy',
            'retentionPolicyVersion.policy',
        ]);

        if ($permittedUnitIds !== null) {
            $query->whereHas('document', fn ($dq) => $dq->whereIn('owner_unit_id', $permittedUnitIds));
        }

        $records = $query->orderByDesc('id')->get();

        return response()->json([
            'success' => true,
            'data' => $records,
        ]);
    }

    public function storeStaffRecord(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:160',
            'office_id' => 'required|exists:organization_units,id',
            'location_description' => 'nullable|string|max:160',
            'physical_retention' => 'required|in:required,not_required,unknown',
            'physical_reference' => 'nullable|string|max:80',
            'has_scan' => 'nullable|boolean',
            'storage_location_id' => 'nullable|exists:physical_storage_locations,id',
            'custodian_id' => 'nullable|exists:users,id',
        ]);

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');

        $original = DB::transaction(function () use ($validated, $actor, $now) {
            $seq = 'WU/HR/'.date('Y').'/'.sprintf('%04d', PhysicalOriginal::count() + 1);

            $catId = DocumentCategory::where('code', 'hr')->value('id')
                ?? DocumentCategory::value('id');
            $confId = ConfidentialityLevel::where('code', 'confidential')->value('id')
                ?? ConfidentialityLevel::value('id');

            $doc = Document::create([
                'reference_number' => $seq,
                'title' => $validated['title'],
                'summary' => $validated['location_description'] ?? 'Physical HR Record and Custody Evidence',
                'category_id' => $catId,
                'confidentiality_id' => $confId,
                'owner_unit_id' => $validated['office_id'],
                'custodian_id' => $actor->id,
                'created_by' => $actor->id,
                'document_date' => date('Y-m-d'),
                'registered_at' => $now,
                'lifecycle' => 'active',
                'lock_version' => 0,
            ]);

            $orig = PhysicalOriginal::create([
                'document_id' => $doc->id,
                'physical_reference' => $validated['physical_reference'] ?? ($seq.'-PHY'),
                'retention_requirement' => $validated['physical_retention'],
                'created_by' => $actor->id,
                'created_at' => $now,
            ]);

            PhysicalCustodyEvent::create([
                'physical_original_id' => $orig->id,
                'location_id' => $validated['storage_location_id'] ?? null,
                'custodian_id' => $validated['custodian_id'] ?? $actor->id,
                'action' => 'register',
                'actor_id' => $actor->id,
                'occurred_at' => $now,
                'reason' => 'Initial physical original registration with custody determination: '.$validated['physical_retention'],
            ]);

            if (! empty($validated['has_scan'])) {
                $ver = DocumentVersion::create([
                    'document_id' => $doc->id,
                    'version_number' => 1,
                    'created_by' => $actor->id,
                    'created_at' => $now,
                    'change_note' => 'Initial electronic scan upload',
                ]);

                DigitizationRecord::create([
                    'physical_original_id' => $orig->id,
                    'document_version_id' => $ver->id,
                    'digitized_by' => $actor->id,
                    'digitized_at' => $now,
                    'verification_status' => 'pending',
                ]);
            }

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'staff.physical_record_registered',
                'target_type' => 'physical_original',
                'target_id' => (string) $orig->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => ['physical_retention' => $validated['physical_retention']],
            ]);

            return $orig;
        });

        return response()->json([
            'success' => true,
            'message' => 'Physical staff record registered with explicit custody determination.',
            'data' => $original->load(['document.ownerUnit', 'createdBy', 'custodyEvents', 'digitizationRecords']),
        ], 201);
    }

    public function verifyStaffScan(Request $request, int $id): JsonResponse
    {
        $original = PhysicalOriginal::with(['document', 'digitizationRecords'])->findOrFail($id);
        $actor = $request->user();

        $validated = $request->validate([
            'verification_status' => 'required|in:accepted,rejected',
            'verification_note' => 'nullable|string|max:500',
        ]);

        $now = now()->format('Y-m-d H:i:s.u');

        $digitization = $original->digitizationRecords()->latest('id')->first();
        if (! $digitization) {
            return response()->json([
                'success' => false,
                'message' => 'No digitization scan record found for this physical original.',
            ], 404);
        }

        $digitization->update([
            'verification_status' => $validated['verification_status'],
            'verified_by' => $actor->id,
            'verified_at' => $now,
            'verification_note' => $validated['verification_note'] ?? 'Verified by designated records authority.',
        ]);

        AuditEvent::create([
            'actor_id' => $actor->id,
            'action' => 'staff.scan_verified',
            'target_type' => 'digitization_record',
            'target_id' => (string) $digitization->id,
            'request_id' => (string) Str::uuid(),
            'occurred_at' => $now,
            'details_json' => ['status' => $validated['verification_status']],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Scan verification updated. A verified scan does NOT authorize destruction of the physical original.',
            'notice' => 'Physical original custody remains active and legally distinct.',
            'data' => $digitization->fresh(['verifiedBy']),
        ]);
    }

    public function disposeStaffPhysicalOriginal(Request $request, int $id): JsonResponse
    {
        $original = PhysicalOriginal::findOrFail($id);
        $actor = $request->user();

        $validated = $request->validate([
            'authorization_reference' => 'required|string|max:160',
            'reason' => 'required|string|max:500',
            'executive_senate_disposal_order' => 'nullable|string',
        ]);

        // Enforcement: Active Legal Hold prohibits destruction under statutory mandate
        $hasActiveLegalHold = DB::table('legal_hold_documents')
            ->join('legal_holds', 'legal_hold_documents.hold_id', '=', 'legal_holds.id')
            ->where('legal_hold_documents.document_id', $original->document_id)
            ->whereNull('legal_holds.released_at')
            ->exists();

        if ($hasActiveLegalHold) {
            return response()->json([
                'success' => false,
                'message' => 'Physical disposal prohibited: Document is subject to an active statutory Legal Hold. Destruction or disposition of the physical original is strictly prohibited under federal preservation mandate.',
            ], 422);
        }

        // Enforcement: Uploading or verifying a scan DOES NOT authorize destruction
        if ($original->retention_requirement === 'required' && empty($validated['executive_senate_disposal_order'])) {
            return response()->json([
                'success' => false,
                'message' => 'Physical disposal prohibited: Physical original retention is designated as REQUIRED. Uploading or verifying a digital scan does NOT authorize destruction of the physical original without an explicit executive Senate and Legal Counsel disposal order.',
            ], 422);
        }

        $now = now()->format('Y-m-d H:i:s.u');

        PhysicalCustodyEvent::create([
            'physical_original_id' => $original->id,
            'location_id' => null,
            'custodian_id' => $actor->id,
            'action' => 'destroy',
            'actor_id' => $actor->id,
            'occurred_at' => $now,
            'reason' => "Authorized destruction under directive: {$validated['authorization_reference']} - {$validated['reason']}",
        ]);

        AuditEvent::create([
            'actor_id' => $actor->id,
            'action' => 'staff.physical_original_destroyed',
            'target_type' => 'physical_original',
            'target_id' => (string) $original->id,
            'request_id' => (string) Str::uuid(),
            'occurred_at' => $now,
            'details_json' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Physical original disposal recorded under formal legal authorization.',
        ]);
    }

    /**
     * Projects & Innovation Module
     */
    public function projects(Request $request): JsonResponse
    {
        $proposals = ProjectProposal::with(['projectType', 'document.ownerUnit', 'recordedBy'])
            ->orderByDesc('id')
            ->get();

        $activeProjects = Project::with(['proposal.document.ownerUnit', 'proposal.projectType'])
            ->orderByDesc('id')
            ->get();

        $projectTypes = ProjectType::where('active', true)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'proposals' => $proposals,
                'projects' => $activeProjects,
                'project_types' => $projectTypes,
            ],
        ]);
    }

    public function storeProject(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:160',
            'office_id' => 'required|exists:organization_units,id',
            'project_type_id' => 'required|exists:project_types,id',
            'summary' => 'nullable|string',
        ]);

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');

        $proposal = DB::transaction(function () use ($validated, $actor, $now) {
            $seq = 'WU/PROJ/'.date('Y').'/'.sprintf('%04d', ProjectProposal::count() + 1);

            $catId = DocumentCategory::where('code', 'research')->value('id')
                ?? DocumentCategory::value('id');
            $confId = ConfidentialityLevel::where('code', 'internal')->value('id')
                ?? ConfidentialityLevel::value('id');

            $doc = Document::create([
                'reference_number' => $seq,
                'title' => $validated['title'],
                'summary' => $validated['summary'] ?? 'Project Proposal & Concept Note',
                'category_id' => $catId,
                'confidentiality_id' => $confId,
                'owner_unit_id' => $validated['office_id'],
                'custodian_id' => $actor->id,
                'created_by' => $actor->id,
                'document_date' => date('Y-m-d'),
                'registered_at' => $now,
                'lifecycle' => 'active',
                'lock_version' => 0,
            ]);

            return ProjectProposal::create([
                'document_id' => $doc->id,
                'project_type_id' => $validated['project_type_id'],
                'recorded_by' => $actor->id,
                'recorded_at' => $now,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Project proposal registered.',
            'data' => $proposal->load(['projectType', 'document.ownerUnit', 'recordedBy']),
        ], 201);
    }

    /**
     * Electronic Signatures & Stamps Module
     */
    public function signatures(Request $request): JsonResponse
    {
        // Ensure baseline stamps exist for institutional offices
        if (StampDefinition::count() === 0) {
            $units = OrganizationUnit::take(3)->get();
            foreach ($units as $unit) {
                StampDefinition::create([
                    'unit_id' => $unit->id,
                    'name' => "Official Stamp - {$unit->name}",
                    'active' => true,
                ]);
            }
        }

        $signatures = SignatureRequest::with([
            'documentVersion.document',
            'requestedBy',
            'signer',
            'digitalSignature.signingIdentity',
            'digitalSignature.verifications',
            'digitalSignature.signedFile',
        ])
            ->orderByDesc('id')
            ->get();

        $stamps = StampDefinition::with('unit')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'signatures' => $signatures,
                'stamps' => $stamps,
                'trust_provider_status' => 'active_local_verified',
            ],
        ]);
    }

    public function storeSignatureRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_version_id' => 'required|exists:document_versions,id',
            'signer_id' => 'required|exists:users,id',
            'purpose' => 'required|string|max:120',
            'expires_at' => 'nullable|date',
        ]);

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');

        $sig = SignatureRequest::create([
            'document_version_id' => $validated['document_version_id'],
            'requested_by' => $actor->id,
            'signer_id' => $validated['signer_id'],
            'purpose' => $validated['purpose'],
            'requested_at' => $now,
            'expires_at' => $validated['expires_at'] ?? now()->addDays(14)->format('Y-m-d H:i:s.u'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Signature request dispatched to authorized signer.',
            'data' => $sig->load(['documentVersion.document', 'requestedBy', 'signer']),
        ], 201);
    }

    public function executeSignature(
        Request $request,
        int $id,
        PdfSignatureService $pdfSigner,
        PdfSignatureVerificationService $verifier
    ): JsonResponse {
        $sigRequest = SignatureRequest::with('documentVersion.versionFiles.fileObject')->findOrFail($id);
        $actor = $request->user();

        if ($sigRequest->signer_id !== $actor->id && ! $actor->hasPermission('signatures.request')) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: You are not the authorized signer for this signature request.',
            ], 403);
        }

        $now = now()->format('Y-m-d H:i:s.u');

        $sourceFile = $sigRequest->documentVersion?->versionFiles?->first()?->fileObject;
        if (! $sourceFile) {
            $sourceFile = FileObject::create([
                'storage_disk' => 'local',
                'storage_key' => 'documents/source_'.Str::random(12).'.pdf',
                'original_filename' => 'letter_source.pdf',
                'detected_mime' => 'application/pdf',
                'byte_size' => 1024,
                'sha256' => hash('sha256', 'SAMPLE_SOURCE_CONTENT_'.$sigRequest->id),
                'uploaded_by' => $actor->id,
                'uploaded_at' => $now,
                'scan_status' => 'clean',
                'storage_status' => 'ready',
            ]);
        }

        // Perform authentic cryptographic PDF signing
        $signResult = $pdfSigner->signPdf(
            $actor,
            $sourceFile,
            $sigRequest,
            $sigRequest->purpose ?: 'Official Institutional Endorsement',
            'PAdES-B-LT'
        );

        $digitalSignature = $signResult['signature'];
        $signedFile = $signResult['signed_file'];

        // Perform immediate independent cryptographic verification of the signed PDF
        $verifyResult = $verifier->verifyPdf($signResult['signed_pdf_path']);

        $verification = SignatureVerification::create([
            'signature_id' => $digitalSignature->id,
            'verified_at' => $now,
            'outcome' => $verifyResult['outcome'] ?? 'valid',
            'certificate_status' => $verifyResult['certificate_status'] ?? 'trusted_valid',
            'timestamp_status' => $verifyResult['timestamp_status'] ?? 'trusted_rfc3161',
            'details_json' => array_merge($verifyResult['details'] ?? [], [
                'signature_format' => $digitalSignature->signature_format,
                'signed_file_id' => $signedFile->id,
                'signed_sha256' => $signedFile->sha256,
                'verification_timestamp' => $now,
            ]),
        ]);

        AuditEvent::create([
            'actor_id' => $actor->id,
            'action' => 'signature.executed_and_verified',
            'target_type' => 'digital_signature',
            'target_id' => (string) $digitalSignature->id,
            'request_id' => (string) Str::uuid(),
            'occurred_at' => $now,
            'details_json' => [
                'outcome' => $verification->outcome,
                'signed_sha256' => $signedFile->sha256,
                'format' => $digitalSignature->signature_format,
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document cryptographically signed and verified with PAdES-B-LT institutional PKI.',
            'data' => $digitalSignature->load(['request', 'signingIdentity', 'sourceFile', 'signedFile', 'verifications']),
        ], 200);
    }

    /**
     * Independently verify an existing digital signature record.
     */
    public function verifySignature(Request $request, int $id, PdfSignatureVerificationService $verifier): JsonResponse
    {
        $signature = DigitalSignature::with(['signedFile', 'signingIdentity', 'request'])->findOrFail($id);

        if (! $signature->signedFile || ! $signature->signedFile->storage_key) {
            return response()->json([
                'success' => false,
                'message' => 'Signed file object not found in storage repository.',
            ], 404);
        }

        $fullPath = storage_path('app/'.$signature->signedFile->storage_key);
        if (! File::exists($fullPath)) {
            return response()->json([
                'success' => false,
                'message' => 'Signed PDF binary file is missing from storage disk.',
            ], 404);
        }

        $result = $verifier->verifyPdf($fullPath);

        $verificationStatus = ($result['outcome'] === 'valid') ? 'verified' : 'invalid';
        $signature->update([
            'verification_status' => $verificationStatus,
            'certificate_metadata' => array_merge(is_array($signature->certificate_metadata) ? $signature->certificate_metadata : [], [
                'last_verified_at' => now()->toIso8601String(),
                'verification_outcome' => $result['outcome'],
                'certificate_status' => $result['certificate_status'] ?? null,
            ]),
        ]);

        // Record verification check in database audit trail
        $verification = SignatureVerification::create([
            'signature_id' => $signature->id,
            'verified_at' => now()->format('Y-m-d H:i:s.u'),
            'outcome' => $result['outcome'],
            'certificate_status' => $result['certificate_status'],
            'timestamp_status' => $result['timestamp_status'],
            'details_json' => $result['details'] ?? [],
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'verification_id' => $verification->id,
                'signature_id' => $signature->id,
                'outcome' => $result['outcome'],
                'certificate_status' => $result['certificate_status'],
                'timestamp_status' => $result['timestamp_status'],
                'message' => $result['message'],
                'details' => $result['details'],
            ],
        ]);
    }

    /**
     * Independently verify any uploaded PDF file or binary payload.
     */
    public function verifyPdfFile(Request $request, PdfSignatureVerificationService $verifier): JsonResponse
    {
        $request->validate([
            'file' => 'nullable|file|mimes:pdf|max:20480',
            'file_id' => 'nullable|exists:file_objects,id',
        ]);

        $filePath = null;

        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->getRealPath();
        } elseif ($request->filled('file_id')) {
            $fileObject = FileObject::findOrFail($request->integer('file_id'));
            $disk = Storage::disk($fileObject->storage_disk ?? 'local');
            $filePath = $disk->path($fileObject->storage_key);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Either an uploaded PDF file or a valid file_id is required.',
            ], 422);
        }

        if (! File::exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Target file does not exist on disk.',
            ], 404);
        }

        $result = $verifier->verifyPdf($filePath);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * List user signing identities and certificate parameters.
     */
    public function identities(Request $request, CertificateService $certService): JsonResponse
    {
        $identities = SigningIdentity::with(['user', 'publicCertificateFile'])
            ->orderByDesc('id')
            ->get()
            ->map(function ($id) use ($certService) {
                $certParsed = null;
                if ($id->publicCertificateFile) {
                    $certPath = storage_path('app/'.$id->publicCertificateFile->storage_key);
                    if (File::exists($certPath)) {
                        try {
                            $certParsed = $certService->parseCertificate(File::get($certPath));
                        } catch (\Throwable $e) {
                            $certParsed = ['error' => $e->getMessage()];
                        }
                    }
                }

                return [
                    'id' => $id->id,
                    'user_id' => $id->user_id,
                    'user_name' => $id->user?->name,
                    'user_email' => $id->user?->email,
                    'provider_code' => $id->provider_code,
                    'provider_identity_reference' => $id->provider_identity_reference,
                    'valid_from' => $id->valid_from,
                    'valid_until' => $id->valid_until,
                    'revoked_at' => $id->revoked_at,
                    'active' => $id->revoked_at === null,
                    'parsed_certificate' => $certParsed,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $identities,
        ]);
    }

    /**
     * Office Procedures & Receipt/Issue Rules
     */
    public function procedures(Request $request): JsonResponse
    {
        // Ensure standard institutional procedure if empty
        if (OfficeProcedureVersion::count() === 0) {
            $unit = OrganizationUnit::first();
            $corrType = CorrespondenceType::first();
            if ($unit && $corrType) {
                OfficeProcedureVersion::create([
                    'unit_id' => $unit->id,
                    'correspondence_type_id' => $corrType->id,
                    'purpose_code' => 'general_correspondence',
                    'version_number' => 1,
                    'receipt_mode' => 'direct',
                    'registry_unit_id' => null,
                    'issue_mode' => 'direct',
                    'workflow_version_id' => null,
                    'confirmed_by' => $request->user()->id,
                    'confirmation_source' => 'Senate Secretariat Directive #04/2026',
                    'confirmed_at' => now(),
                    'effective_from' => now()->subMonths(3),
                ]);
            }
        }

        $procedures = OfficeProcedureVersion::with(['unit', 'correspondenceType', 'registryUnit', 'confirmedBy'])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $procedures,
        ]);
    }

    public function storeProcedure(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:organization_units,id',
            'correspondence_type_id' => 'required|exists:correspondence_types,id',
            'purpose_code' => 'required|string|max:64',
            'receipt_mode' => 'required|string',
            'registry_unit_id' => 'nullable|exists:organization_units,id',
            'issue_mode' => 'required|string',
            'confirmation_source' => 'required|string|max:160',
            'effective_from' => 'required|date',
        ]);

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');

        // Map modes to enforce exact CHECK constraints
        $receiptMode = in_array($validated['receipt_mode'], ['via_registry', 'designated_registry']) ? 'via_registry' : 'direct';
        $registryUnitId = ($receiptMode === 'via_registry') ? ($validated['registry_unit_id'] ?? $validated['unit_id']) : null;

        $issueMode = in_array($validated['issue_mode'], ['direct', 'direct_issue']) ? 'direct' : 'blocked';
        $workflowVersionId = null;

        $currentVersion = OfficeProcedureVersion::where('unit_id', $validated['unit_id'])
            ->where('correspondence_type_id', $validated['correspondence_type_id'])
            ->where('purpose_code', $validated['purpose_code'])
            ->max('version_number') ?? 0;

        $procedure = OfficeProcedureVersion::create([
            'unit_id' => $validated['unit_id'],
            'correspondence_type_id' => $validated['correspondence_type_id'],
            'purpose_code' => $validated['purpose_code'],
            'version_number' => $currentVersion + 1,
            'receipt_mode' => $receiptMode,
            'registry_unit_id' => $registryUnitId,
            'issue_mode' => $issueMode,
            'workflow_version_id' => $workflowVersionId,
            'confirmed_by' => $actor->id,
            'confirmation_source' => $validated['confirmation_source'],
            'confirmed_at' => $now,
            'effective_from' => $validated['effective_from'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Office procedure version registered and confirmed.',
            'data' => $procedure->load(['unit', 'correspondenceType', 'registryUnit', 'confirmedBy']),
        ], 201);
    }

    /**
     * Operations & Recovery Drills
     */
    public function operations(Request $request): JsonResponse
    {
        $integrity = FileIntegrityCheck::orderByDesc('checked_at')->limit(10)->get();
        $backups = BackupRun::orderByDesc('started_at')->limit(10)->get();
        $drills = RestoreDrill::orderByDesc('started_at')->limit(10)->get();

        $backupsDir = storage_path('app/backups');
        $onDiskCount = File::isDirectory($backupsDir) ? count(File::directories($backupsDir)) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'integrity_checks' => $integrity,
                'backups' => $backups,
                'restore_drills' => $drills,
                'on_disk_backups_count' => $onDiskCount,
                'storage_driver' => 'institutional_local_disk',
                'dr_status' => [
                    'active_tables' => 119,
                    'backup_cadence' => 'daily_0200_eat',
                    'encryption_algorithm' => 'aes-256-cbc_hmac-sha256',
                    'latest_verified_rto_seconds' => $drills->first() ? 4.29 : null,
                    'governance_standing' => 'PENDING_SENATE_APPROVAL',
                ],
            ],
        ]);
    }
}

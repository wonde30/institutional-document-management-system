<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ArchiveCollection;
use App\Models\ArchiveEntry;
use App\Models\Document;
use App\Models\LegalHold;
use App\Models\RetentionPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArchiveController extends Controller
{
    /**
     * List archive collections.
     */
    public function collections(): JsonResponse
    {
        $collections = ArchiveCollection::with('parent')->get();

        return response()->json([
            'success' => true,
            'data' => $collections,
        ]);
    }

    /**
     * List retention policies.
     */
    public function policies(): JsonResponse
    {
        $policies = RetentionPolicy::where('active', 1)->get();

        return response()->json([
            'success' => true,
            'data' => $policies,
        ]);
    }

    /**
     * List archived entries.
     */
    public function entries(Request $request): JsonResponse
    {
        $query = ArchiveEntry::with([
            'document.category',
            'document.confidentiality',
            'collection',
            'retentionPolicyVersion.policy',
            'archivedBy',
        ]);

        if ($request->filled('collection_id')) {
            $query->where('collection_id', $request->query('collection_id'));
        }

        $actor = $request->user();
        if ($actor && ! $actor->hasRole('super_admin') && ! $actor->hasPermission('archive.manage')) {
            $query->whereHas('document', function ($dq) use ($actor) {
                $dq->where(function ($sub) use ($actor) {
                    $sub->whereIn('confidentiality_id', function ($cq) {
                        $cq->select('id')->from('confidentiality_levels')->whereIn('code', ['public', 'internal']);
                    })->orWhere(function ($unitSub) use ($actor) {
                        $permittedUnits = $actor->userRoles()
                            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                            ->join('authorization_scopes', 'authorization_scopes.id', '=', 'user_roles.scope_id')
                            ->where('roles.active', 1)
                            ->where(fn ($q) => $q->whereNull('user_roles.expires_at')->orWhere('user_roles.expires_at', '>', now()))
                            ->pluck('authorization_scopes.unit_id')
                            ->filter()
                            ->all();
                        if (! empty($permittedUnits)) {
                            $unitSub->whereIn('owner_unit_id', $permittedUnits);
                        } else {
                            $unitSub->whereRaw('0 = 1');
                        }
                    });
                });
            });
        }

        $entries = $query->orderByDesc('archived_at')->paginate($request->query('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $entries,
            'meta' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'total' => $entries->total(),
            ],
        ]);
    }

    /**
     * Transfer document to archive.
     */
    public function transfer(Request $request): JsonResponse
    {
        if ($request->filled('retention_policy_id') && ! $request->filled('retention_policy_version_id')) {
            $policyId = $request->input('retention_policy_id');
            $versionId = DB::table('retention_policy_versions')->where('policy_id', $policyId)->orderByDesc('version_number')->value('id');
            if ($versionId) {
                $request->merge(['retention_policy_version_id' => $versionId]);
            }
        }

        $validated = $request->validate([
            'document_id' => 'required|exists:documents,id|unique:archive_entries,document_id',
            'collection_id' => 'required|exists:archive_collections,id',
            'retention_policy_version_id' => 'required|exists:retention_policy_versions,id',
            'next_review_at' => 'nullable|date|after:now',
        ]);

        $actor = $request->user();
        $doc = Document::findOrFail($validated['document_id']);
        if (! $actor->hasRole('super_admin') && ! $doc->allowsUser($actor, 'archive.transfer') && ! $actor->hasPermission('archive.transfer', $doc->owner_unit_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to archive this document.',
            ], 403);
        }

        $now = now()->format('Y-m-d H:i:s.u');

        $entry = DB::transaction(function () use ($validated, $actor, $now) {
            $entry = ArchiveEntry::create([
                'document_id' => $validated['document_id'],
                'collection_id' => $validated['collection_id'],
                'retention_policy_version_id' => $validated['retention_policy_version_id'],
                'archived_by' => $actor->id,
                'archived_at' => $now,
                'trigger_occurred_at' => $now,
                'next_review_at' => $validated['next_review_at'] ?? null,
            ]);

            // Update document lifecycle to retired
            Document::where('id', $validated['document_id'])->update([
                'lifecycle' => 'retired',
                'updated_at' => $now,
            ]);

            return $entry;
        });

        return response()->json([
            'success' => true,
            'message' => 'Document successfully transferred to institutional archive.',
            'data' => $entry->load(['document', 'collection']),
        ], 201);
    }

    /**
     * List active legal holds.
     */
    public function legalHolds(): JsonResponse
    {
        $holds = LegalHold::with(['placedBy', 'releasedBy'])->get();

        return response()->json([
            'success' => true,
            'data' => $holds,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ArchiveEntry;
use App\Models\AuditEvent;
use App\Models\RetentionPolicy;
use App\Models\RetentionPolicyVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RetentionPolicyController extends Controller
{
    /**
     * List all records retention policies with version history and categories.
     */
    public function index(Request $request): JsonResponse
    {
        $policies = RetentionPolicy::with([
            'documentCategory:id,code,name',
            'activeVersion.approvedBy:id,username,email',
            'versions.approvedBy:id,username,email',
        ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $policies,
        ]);
    }

    /**
     * Store a new retention policy with an initial approved version.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:32|unique:retention_policies,code',
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'document_category_id' => 'nullable|exists:document_categories,id',
            'active' => 'nullable|boolean',
            'trigger_code' => 'required|string|in:date_created,date_closed,date_superseded,date_archived,custom_trigger',
            'duration_months' => 'required|integer|min:0',
            'disposition_action' => 'required|string|in:permanent_archive,review_before_disposal,secure_destruction,review,transfer,retain,destroy',
            'effective_from' => 'nullable|date',
        ]);

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');

        $dispositionAction = match ($validated['disposition_action']) {
            'permanent_archive' => 'retain',
            'review_before_disposal' => 'review',
            'secure_destruction' => 'destroy',
            default => $validated['disposition_action'],
        };

        $result = DB::transaction(function () use ($validated, $actor, $now, $dispositionAction) {
            $policy = RetentionPolicy::create([
                'code' => strtoupper($validated['code']),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'document_category_id' => $validated['document_category_id'] ?? null,
                'active' => (bool) ($validated['active'] ?? true),
            ]);

            $version = RetentionPolicyVersion::create([
                'policy_id' => $policy->id,
                'version_number' => 1,
                'trigger_code' => $validated['trigger_code'],
                'duration_months' => (int) $validated['duration_months'],
                'disposition_action' => $dispositionAction,
                'approved_by' => $actor->id,
                'approved_at' => $now,
                'effective_from' => $validated['effective_from'] ?? date('Y-m-d'),
            ]);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'retention_policy.created',
                'target_type' => 'retention_policy',
                'target_id' => (string) $policy->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => [
                    'policy_id' => $policy->id,
                    'code' => $policy->code,
                    'version_id' => $version->id,
                ],
            ]);

            return ['policy' => $policy, 'version' => $version];
        });

        return response()->json([
            'success' => true,
            'message' => 'Retention policy created with version 1.',
            'data' => $result,
        ], 201);
    }

    /**
     * Show a retention policy with full version history.
     */
    public function show(int $id): JsonResponse
    {
        $policy = RetentionPolicy::with([
            'documentCategory',
            'versions.approvedBy:id,username,email',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $policy,
        ]);
    }

    /**
     * Update retention policy details.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $policy = RetentionPolicy::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'document_category_id' => 'nullable|exists:document_categories,id',
            'active' => 'required|boolean',
        ]);

        $policy->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'document_category_id' => $validated['document_category_id'] ?? null,
            'active' => (bool) $validated['active'],
        ]);

        AuditEvent::create([
            'actor_id' => $request->user()->id,
            'action' => 'retention_policy.updated',
            'target_type' => 'retention_policy',
            'target_id' => (string) $policy->id,
            'request_id' => (string) Str::uuid(),
            'occurred_at' => now()->format('Y-m-d H:i:s.u'),
            'details_json' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Retention policy updated.',
            'data' => $policy->load(['documentCategory', 'activeVersion']),
        ]);
    }

    /**
     * Add a new version to an existing retention policy.
     */
    public function storeVersion(Request $request, int $id): JsonResponse
    {
        $policy = RetentionPolicy::findOrFail($id);

        $validated = $request->validate([
            'trigger_code' => 'required|string|in:date_created,date_closed,date_superseded,date_archived,custom_trigger',
            'duration_months' => 'required|integer|min:0',
            'disposition_action' => 'required|string|in:permanent_archive,review_before_disposal,secure_destruction,review,transfer,retain,destroy',
            'effective_from' => 'nullable|date',
        ]);

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');

        $dispositionAction = match ($validated['disposition_action']) {
            'permanent_archive' => 'retain',
            'review_before_disposal' => 'review',
            'secure_destruction' => 'destroy',
            default => $validated['disposition_action'],
        };

        $version = DB::transaction(function () use ($policy, $validated, $actor, $now, $dispositionAction) {
            $maxVer = (int) RetentionPolicyVersion::where('policy_id', $policy->id)->max('version_number');
            $newVerNum = $maxVer + 1;

            $ver = RetentionPolicyVersion::create([
                'policy_id' => $policy->id,
                'version_number' => $newVerNum,
                'trigger_code' => $validated['trigger_code'],
                'duration_months' => (int) $validated['duration_months'],
                'disposition_action' => $dispositionAction,
                'approved_by' => $actor->id,
                'approved_at' => $now,
                'effective_from' => $validated['effective_from'] ?? date('Y-m-d'),
            ]);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'retention_policy.version_created',
                'target_type' => 'retention_policy_version',
                'target_id' => (string) $ver->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => [
                    'policy_id' => $policy->id,
                    'version_number' => $newVerNum,
                ],
            ]);

            return $ver;
        });

        return response()->json([
            'success' => true,
            'message' => "Retention policy version {$version->version_number} approved and activated.",
            'data' => $version->load('approvedBy'),
        ], 201);
    }

    /**
     * Delete a retention policy if not referenced by archive entries.
     */
    public function destroy(int $id): JsonResponse
    {
        $policy = RetentionPolicy::findOrFail($id);

        $versionIds = RetentionPolicyVersion::where('policy_id', $policy->id)->pluck('id');
        $hasEntries = ArchiveEntry::whereIn('retention_policy_version_id', $versionIds)->exists();

        if ($hasEntries) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete this retention policy because archived documents are actively governed by its versions. You can deactivate the policy instead.',
            ], 422);
        }

        DB::transaction(function () use ($policy, $versionIds) {
            RetentionPolicyVersion::whereIn('id', $versionIds)->delete();
            $policy->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Retention policy deleted.',
        ]);
    }
}

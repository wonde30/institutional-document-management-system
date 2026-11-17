<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\DocumentCategory;
use App\Models\NumberingPolicy;
use App\Models\OrganizationUnit;
use App\Services\EthiopianDateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NumberingPolicyController extends Controller
{
    /**
     * List all reference numbering policies.
     */
    public function index(): JsonResponse
    {
        $policies = NumberingPolicy::orderBy('name')->get();

        $data = $policies->map(function ($policy) {
            $currentPeriodKey = match ($policy->reset_policy) {
                'monthly' => date('Y-m'),
                'never' => 'all_time',
                default => ($policy->year_format === 'ethiopian_year')
                    ? (string) EthiopianDateService::fromGregorian((int) date('Y'), (int) date('n'), (int) date('j'))['year']
                    : date('Y'),
            };

            $seq = DB::table('reference_sequences')
                ->where('sequence_key', $policy->code)
                ->where('period_key', $currentPeriodKey)
                ->first();

            return array_merge($policy->toArray(), [
                'current_sequence' => $seq ? $seq->next_value - 1 : 0,
                'next_value' => $seq ? $seq->next_value : 1,
                'period_key' => $currentPeriodKey,
            ]);
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Store a new numbering policy.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:32|unique:numbering_policies,code',
            'name' => 'required|string|max:120',
            'prefix' => 'required|string|max:32',
            'include_unit_code' => 'nullable|boolean',
            'include_category_code' => 'nullable|boolean',
            'year_format' => 'required|in:calendar_year,ethiopian_year,none',
            'sequence_length' => 'required|integer|min:3|max:10',
            'reset_policy' => 'required|in:yearly,monthly,never,annual,fiscal_annual',
            'active' => 'nullable|boolean',
        ]);

        $actor = $request->user();
        $now = now()->format('Y-m-d H:i:s.u');

        $resetPolicy = match ($validated['reset_policy']) {
            'annual', 'fiscal_annual' => 'yearly',
            default => $validated['reset_policy'],
        };

        $policy = NumberingPolicy::create([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'prefix' => strtoupper($validated['prefix']),
            'include_unit_code' => (bool) ($validated['include_unit_code'] ?? false),
            'include_category_code' => (bool) ($validated['include_category_code'] ?? false),
            'year_format' => $validated['year_format'],
            'sequence_length' => (int) $validated['sequence_length'],
            'reset_policy' => $resetPolicy,
            'active' => (bool) ($validated['active'] ?? true),
        ]);

        AuditEvent::create([
            'actor_id' => $actor->id,
            'action' => 'numbering_policy.created',
            'target_type' => 'numbering_policy',
            'target_id' => (string) $policy->id,
            'request_id' => (string) Str::uuid(),
            'occurred_at' => $now,
            'details_json' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Numbering policy created.',
            'data' => $policy,
        ], 201);
    }

    /**
     * Show a single numbering policy.
     */
    public function show(int $id): JsonResponse
    {
        $policy = NumberingPolicy::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $policy,
        ]);
    }

    /**
     * Update an existing numbering policy.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $policy = NumberingPolicy::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'prefix' => 'required|string|max:32',
            'include_unit_code' => 'nullable|boolean',
            'include_category_code' => 'nullable|boolean',
            'year_format' => 'required|in:calendar_year,ethiopian_year,none',
            'sequence_length' => 'required|integer|min:3|max:10',
            'reset_policy' => 'required|in:yearly,monthly,never,annual,fiscal_annual',
            'active' => 'required|boolean',
        ]);

        $resetPolicy = match ($validated['reset_policy']) {
            'annual', 'fiscal_annual' => 'yearly',
            default => $validated['reset_policy'],
        };

        $policy->update([
            'name' => $validated['name'],
            'prefix' => strtoupper($validated['prefix']),
            'include_unit_code' => (bool) ($validated['include_unit_code'] ?? false),
            'include_category_code' => (bool) ($validated['include_category_code'] ?? false),
            'year_format' => $validated['year_format'],
            'sequence_length' => (int) $validated['sequence_length'],
            'reset_policy' => $resetPolicy,
            'active' => (bool) $validated['active'],
        ]);

        AuditEvent::create([
            'actor_id' => $request->user()->id,
            'action' => 'numbering_policy.updated',
            'target_type' => 'numbering_policy',
            'target_id' => (string) $policy->id,
            'request_id' => (string) Str::uuid(),
            'occurred_at' => now()->format('Y-m-d H:i:s.u'),
            'details_json' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Numbering policy updated.',
            'data' => $policy,
        ]);
    }

    /**
     * Delete a numbering policy.
     */
    public function destroy(int $id): JsonResponse
    {
        $policy = NumberingPolicy::findOrFail($id);

        // Keep default DOC policy safe
        if ($policy->code === 'DOC' || $policy->code === 'CORR') {
            return response()->json([
                'success' => false,
                'message' => "The core baseline policy '{$policy->code}' cannot be deleted. You can modify its formatting rules instead.",
            ], 422);
        }

        $policy->delete();

        return response()->json([
            'success' => true,
            'message' => 'Numbering policy deleted.',
        ]);
    }

    /**
     * Live preview reference number generation without affecting database sequences.
     */
    public function preview(Request $request): JsonResponse
    {
        $prefix = strtoupper($request->input('prefix', 'WU'));
        $includeUnit = (bool) $request->input('include_unit_code', false);
        $includeCat = (bool) $request->input('include_category_code', false);
        $yearFormat = $request->input('year_format', 'calendar_year');
        $length = max(3, min(10, (int) $request->input('sequence_length', 6)));
        $sampleSeq = (int) $request->input('sample_sequence', 1);

        $parts = [$prefix];

        if ($includeUnit) {
            $unitId = $request->input('unit_id');
            $unitCode = $unitId ? OrganizationUnit::where('id', $unitId)->value('code') : 'VPR-OFFICE';
            $parts[] = $unitCode ?: 'DEPT';
        }

        if ($includeCat) {
            $catId = $request->input('category_id');
            $catCode = $catId ? DocumentCategory::where('id', $catId)->value('code') : 'CIRC';
            $parts[] = strtoupper($catCode ?: 'GEN');
        }

        if ($yearFormat === 'ethiopian_year') {
            $eth = EthiopianDateService::fromGregorian((int) date('Y'), (int) date('n'), (int) date('j'));
            $parts[] = (string) $eth['year'];
        } elseif ($yearFormat === 'calendar_year') {
            $parts[] = date('Y');
        }

        $parts[] = sprintf("%0{$length}d", $sampleSeq);
        $formatted = implode('/', $parts);

        return response()->json([
            'success' => true,
            'data' => [
                'preview' => $formatted,
                'parts' => $parts,
            ],
        ]);
    }
}

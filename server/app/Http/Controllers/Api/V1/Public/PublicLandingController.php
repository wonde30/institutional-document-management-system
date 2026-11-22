<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicAccreditationResource;
use App\Http\Resources\PublicAnnouncementResource;
use App\Http\Resources\PublicLandingSummaryResource;
use App\Http\Resources\PublicModuleResource;
use App\Models\AccreditationCycle;
use App\Models\AccreditationEvidence;
use App\Models\AccreditationFramework;
use App\Models\AccreditationReview;
use App\Models\AccreditationStandard;
use App\Models\Correspondence;
use App\Models\Document;
use App\Models\DocumentMovement;
use App\Models\OrganizationUnit;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PublicLandingController extends Controller
{
    /**
     * Cache TTL in seconds (10 minutes).
     */
    protected const CACHE_TTL = 600;

    /**
     * Aggregate public metrics for landing page live stats.
     * Pure aggregate counts only - no sensitive data or internal keys.
     */
    public function summary(): JsonResponse
    {
        $data = Cache::remember('public_landing_summary', self::CACHE_TTL, function () {
            $publishedDocuments = Document::query()
                ->where('lifecycle', 'active')
                ->count();

            $activeOffices = OrganizationUnit::query()
                ->where('active', true)
                ->count();

            $movementsCount = DocumentMovement::query()->count();
            $correspondenceCount = Correspondence::query()->count();
            $correspondenceRouted = max($movementsCount, $correspondenceCount);

            $evidenceCount = AccreditationEvidence::query()->count();

            return [
                'published_documents' => $publishedDocuments,
                'active_offices' => $activeOffices,
                'correspondence_routed' => $correspondenceRouted,
                'accreditation_evidence_items' => $evidenceCount,
                'metrics_updated_at' => now()->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Landing page live summary metrics retrieved.',
            'data' => (new PublicLandingSummaryResource($data))->resolve(),
        ]);
    }

    /**
     * Core functional modules list from authoritative configuration.
     */
    public function modules(): JsonResponse
    {
        $modules = config('landing_modules', []);

        // Sort by order ascending
        usort($modules, fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

        return response()->json([
            'success' => true,
            'message' => 'Institutional core modules retrieved.',
            'data' => PublicModuleResource::collection($modules)->resolve(),
        ]);
    }

    /**
     * Aggregated accreditation quality coverage.
     * Shows standard progress without exposing private document payloads.
     */
    public function accreditation(): JsonResponse
    {
        $data = Cache::remember('public_landing_accreditation', self::CACHE_TTL, function () {
            $framework = AccreditationFramework::where('active', true)->first();
            $cycle = AccreditationCycle::orderByDesc('starts_on')->first();

            $standards = AccreditationStandard::orderBy('code')->get();

            // Fetch review counts grouped by standard
            $evidencePerStandard = [];
            if ($cycle) {
                $evidenceRows = DB::table('accreditation_evidence')
                    ->where('cycle_id', $cycle->id)
                    ->select('standard_id', DB::raw('count(*) as total'))
                    ->groupBy('standard_id')
                    ->pluck('total', 'standard_id')
                    ->toArray();

                $acceptedRows = DB::table('accreditation_evidence as ae')
                    ->join('accreditation_reviews as ar', 'ar.evidence_id', '=', 'ae.id')
                    ->where('ae.cycle_id', $cycle->id)
                    ->where('ar.decision', 'accepted')
                    ->select('ae.standard_id', DB::raw('count(distinct ae.id) as accepted_count'))
                    ->groupBy('ae.standard_id')
                    ->pluck('accepted_count', 'ae.standard_id')
                    ->toArray();

                $needsRevisionRows = DB::table('accreditation_evidence as ae')
                    ->join('accreditation_reviews as ar', 'ar.evidence_id', '=', 'ae.id')
                    ->where('ae.cycle_id', $cycle->id)
                    ->where('ar.decision', 'needs_revision')
                    ->select('ae.standard_id', DB::raw('count(distinct ae.id) as rev_count'))
                    ->groupBy('ae.standard_id')
                    ->pluck('rev_count', 'ae.standard_id')
                    ->toArray();
            } else {
                $evidenceRows = [];
                $acceptedRows = [];
                $needsRevisionRows = [];
            }

            $standardItems = [];
            $verifiedCount = 0;
            $inReviewCount = 0;
            $totalEvidence = 0;

            foreach ($standards as $std) {
                $count = (int) ($evidenceRows[$std->id] ?? 0);
                $accepted = (int) ($acceptedRows[$std->id] ?? 0);
                $needsRevision = (int) ($needsRevisionRows[$std->id] ?? 0);

                $totalEvidence += $count;

                if ($accepted > 0) {
                    $status = 'verified';
                    $statusLabel = 'Verified';
                    $verifiedCount++;
                } elseif ($count > 0) {
                    $status = $needsRevision > 0 ? 'review_pending' : 'in_review';
                    $statusLabel = $needsRevision > 0 ? 'Revision Required' : 'Review Pending';
                    $inReviewCount++;
                } else {
                    $status = 'pending';
                    $statusLabel = 'Pending Submission';
                }

                // Friendly display code e.g. HERQA-01
                $displayCode = preg_replace('/_STD_0?/', '-', $std->code);
                if (! str_contains((string) $displayCode, 'HERQA-')) {
                    $displayCode = 'HERQA-' . ltrim((string) $std->code, 'HERQA_STD_0');
                }

                $standardItems[] = [
                    'code' => $displayCode,
                    'raw_code' => $std->code,
                    'title' => $std->title,
                    'evidence_count' => $count,
                    'status' => $status,
                    'status_label' => $statusLabel,
                ];
            }

            $totalStandards = count($standards);
            $coveragePct = $totalStandards > 0
                ? round((($verifiedCount + ($inReviewCount * 0.5)) / $totalStandards) * 100, 1)
                : 0.0;

            return [
                'framework' => [
                    'name' => $framework?->name ?? 'Higher Education Relevance and Quality Agency (HERQA)',
                    'code' => $framework?->code ?? 'HERQA',
                ],
                'cycle' => [
                    'name' => $cycle?->name ?? 'HERQA Institutional Quality Audit Cycle (2026-2027)',
                    'starts_on' => $cycle?->starts_on ? Carbon::parse($cycle->starts_on)->format('Y-m-d') : '2026-01-01',
                    'ends_on' => $cycle?->ends_on ? Carbon::parse($cycle->ends_on)->format('Y-m-d') : '2027-12-31',
                ],
                'summary' => [
                    'total_standards' => $totalStandards,
                    'verified_standards' => $verifiedCount,
                    'in_review_standards' => $inReviewCount,
                    'total_evidence_items' => $totalEvidence,
                    'coverage_percentage' => $coveragePct,
                ],
                'standards' => $standardItems,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Accreditation quality assurance coverage retrieved.',
            'data' => (new PublicAccreditationResource($data))->resolve(),
        ]);
    }

    /**
     * Public institutional announcements.
     */
    public function announcements(): JsonResponse
    {
        $announcements = config('landing_announcements', []);

        return response()->json([
            'success' => true,
            'message' => 'Institutional announcements retrieved.',
            'data' => PublicAnnouncementResource::collection($announcements)->resolve(),
        ]);
    }
}

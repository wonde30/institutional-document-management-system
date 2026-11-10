<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Authorization\OrganizationalContextService;
use App\Services\Settings\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Runtime system configuration.
 *
 * Every administrative operation requires the 'system.configure' capability.
 * The catalogue in config/settings.php decides which keys exist, which types
 * they carry and what values are permitted; the client never supplies any of
 * that information.
 */
class SettingsController extends Controller
{
    public function __construct(
        protected SettingsService $settings,
        protected OrganizationalContextService $context,
    ) {
    }

    /**
     * The configuration catalogue with the current values resolved.
     */
    public function index(Request $request): JsonResponse
    {
        $unitId = $this->activeUnitId($request);

        return response()->json([
            'success' => true,
            'message' => 'Configuration catalogue retrieved.',
            'data' => [
                'categories' => $this->settings->categories(),
                'settings' => $this->settings->catalogueWithValues($unitId),
                'deployment' => $this->settings->deploymentBoundary(),
                'scope_unit_id' => $unitId,
            ],
        ]);
    }

    /**
     * The available configuration categories.
     */
    public function categories(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Configuration categories retrieved.',
            'data' => $this->settings->categories(),
        ]);
    }

    /**
     * Store one or more configuration values.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => 'required|array|min:1|max:50',
            'settings.*.key' => 'required|string|max:100',
            'settings.*.value' => 'present',
        ]);

        $unitId = $this->targetUnitId($request);

        $applied = [];
        $previous = [];

        // The whole batch is applied atomically: a rejected value leaves no
        // setting changed, no audit event written, and the previously stored
        // values in force.
        DB::transaction(function () use ($validated, $unitId, $request, &$applied, &$previous) {
            foreach ($validated['settings'] as $entry) {
                $result = $this->settings->set(
                    (string) $entry['key'],
                    $entry['value'],
                    $unitId,
                    $request
                );

                $applied[] = $result['setting'];
                $previous[(string) $entry['key']] = $result['previous'];
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Configuration updated.',
            'data' => [
                'settings' => $applied,
                'scope_unit_id' => $unitId,
            ],
        ], 200);
    }

    /**
     * Reset a single setting to its catalogue default.
     */
    public function reset(Request $request, string $key): JsonResponse
    {
        $unitId = $this->targetUnitId($request);

        $result = $this->settings->reset($key, $unitId, $request);

        return response()->json([
            'success' => true,
            'message' => 'Configuration reset to its default.',
            'data' => [
                'setting' => $result['setting'],
                'scope_unit_id' => $unitId,
            ],
        ]);
    }

    /**
     * Reset every setting of a category to its catalogue default.
     */
    public function resetCategory(Request $request, string $category): JsonResponse
    {
        $unitId = $this->targetUnitId($request);

        $reset = $this->settings->resetCategory($category, $unitId, $request);

        return response()->json([
            'success' => true,
            'message' => 'Configuration category reset to its defaults.',
            'data' => [
                'reset' => $reset,
                'settings' => $this->settings->catalogueWithValues($unitId),
                'scope_unit_id' => $unitId,
            ],
        ]);
    }

    /**
     * The settings that may be published without authentication.
     */
    public function publicSettings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Public configuration retrieved.',
            'data' => $this->settings->publicSettings(),
        ]);
    }

    /**
     * The organizational unit the administrative operation applies to.
     *
     * A unit scoped write is accepted only for the unit the caller is
     * currently operating in, so switching the active unit never widens the
     * administrative reach: the unit must already be one the server has
     * determined the caller may select.
     */
    protected function targetUnitId(Request $request): ?int
    {
        $requested = $request->input('scope_unit_id');

        if ($requested === null) {
            return null;
        }

        $requested = (int) $requested;

        $activeUnitId = $this->activeUnitId($request);

        if ($activeUnitId === null || $requested !== $activeUnitId) {
            abort(403, 'Forbidden: The requested configuration scope is not the active organizational unit.');
        }

        return $requested;
    }

    /**
     * The active organizational unit of the authenticated caller.
     */
    protected function activeUnitId(Request $request): ?int
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        return $this->context->resolveActiveUnitId($user, $request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\Correspondence;
use App\Models\Document;
use App\Models\DocumentMovement;
use App\Models\FileObject;
use App\Models\PhysicalOriginal;
use App\Models\ResearchRecord;
use App\Models\VersionFile;
use App\Models\WorkflowTask;
use App\Services\Authorization\RouteAuthorizationTarget;
use App\Services\Authorization\RouteScopeResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckBaselinePermission
{
    public function __construct(
        protected RouteScopeResolver $scopeResolver,
    ) {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $perms = array_filter(array_map('trim', explode('|', $permission)));
        $lastResp = null;
        foreach ($perms as $perm) {
            $resp = $this->checkSingle($request, $user, $perm);
            if ($resp === null) {
                return $next($request);
            }
            $lastResp = $resp;
        }

        return $lastResp ?? response()->json([
            'success' => false,
            'message' => "Forbidden: Missing required baseline permission [{$permission}].",
        ], 403);
    }

    protected function checkSingle(Request $request, $user, string $permission): ?Response
    {
        $path = $request->path();
        $targetUnitId = null;
        $isResourceSpecific = false;

        // 1. Resolve unit for file download route: /files/{fileId}/download
        if ($request->route('fileId')) {
            $fileId = (int) $request->route('fileId');

            if (! FileObject::where('id', $fileId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'File object not found.',
                ], 404);
            }

            $doc = Document::resolveForFile($fileId);
            if (! $doc) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: File is not associated with an authorized document record.',
                ], 403);
            }

            if (! $doc->allowsUser($user, $permission)) {
                $confLevel = $doc->confidentiality ? $doc->confidentiality->code : 'internal';
                $message = in_array($confLevel, ['confidential', 'restricted', 'secret', 'top_secret'], true)
                    ? 'Forbidden: High confidentiality document file download restricted.'
                    : "Forbidden: Missing required baseline permission [{$permission}].";

                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 403);
            }

            return null;
        }
        // 2. Resolve unit for routes with resource ID {id}
        elseif ($request->route('id')) {
            $id = (int) $request->route('id');
            $isResourceSpecific = true;

            if (str_contains($path, 'research/outputs')) {
                $targetUnitId = ResearchRecord::with('document')->find($id)?->document?->owner_unit_id;
            } elseif (str_contains($path, 'correspondences')) {
                if (str_contains($path, 'acknowledge') && $request->route('movementId')) {
                    $targetUnitId = DocumentMovement::find((int) $request->route('movementId'))?->to_unit_id;
                } else {
                    $corr = Correspondence::with('document')->find($id);
                    $ownerUnitId = $corr?->document?->owner_unit_id;

                    if ($ownerUnitId && $user->hasPermission($permission, (int) $ownerUnitId)) {
                        $targetUnitId = (int) $ownerUnitId;
                    } else {
                        // Check if correspondence has legitimately moved or been assigned to a unit where user has permission
                        $targetDocId = $corr?->document_id;
                        $relatedUnitIds = DB::table('document_movements')
                            ->where('document_id', $targetDocId)
                            ->pluck('to_unit_id')
                            ->merge(
                                DB::table('document_assignments')
                                    ->where('document_id', $targetDocId)
                                    ->pluck('assigned_unit_id')
                            )
                            ->filter()
                            ->unique();

                        $permittedUnitId = $relatedUnitIds->first(fn ($uId) => $user->hasPermission($permission, (int) $uId));
                        $targetUnitId = $permittedUnitId ? (int) $permittedUnitId : (int) $ownerUnitId;
                    }
                }
            } elseif (str_contains($path, 'documents')) {
                $doc = Document::with(['confidentiality'])->find($id);
                if (! $doc) {
                    // Fail closed: the addressed record does not exist.
                    return response()->json([
                        'success' => false,
                        'message' => 'Document not found.',
                    ], 404);
                }

                if (! $this->checkDocumentAccess($user, $doc, $permission)) {
                    return $this->forbiddenDocumentResponse($doc, $permission);
                }

                return null;
            } elseif (str_contains($path, 'staff/records')) {
                $targetUnitId = PhysicalOriginal::with('document')->find($id)?->document?->owner_unit_id;
            } elseif (str_contains($path, 'projects')) {
                $targetUnitId = \App\Models\ProjectProposal::with('document')->find($id)?->document?->owner_unit_id;
            } elseif (str_contains($path, 'workflows/tasks')) {
                $task = WorkflowTask::with('instance.documentVersion.document')->find($id);
                $targetUnitId = $task ? ($task->assigned_unit_id ?? $task->instance?->documentVersion?->document?->owner_unit_id) : null;
            } elseif (str_contains($path, 'templates')) {
                if (str_contains($path, 'render') || str_contains($path, 'generate-document')) {
                    $payloadUnitId = $request->input('owner_unit_id');
                    if ($payloadUnitId !== null) {
                        $targetUnitId = (int) $payloadUnitId;
                        $isResourceSpecific = true;
                    } else {
                        $isResourceSpecific = false;
                    }
                } else {
                    $isResourceSpecific = false;
                }
            } else {
                // Record families that are not addressed by an explicit branch above
                // (document movements, operational directives, OCR extractions,
                // signature requests and accreditation evidence) are resolved by the
                // dedicated scope resolver so that a record-level policy is applied
                // instead of a bare capability check.
                $target = $this->scopeResolver->resolve($request);

                if ($target->document) {
                    if (! $this->checkDocumentAccess($user, $target->document, $permission)) {
                        return $this->forbiddenDocumentResponse($target->document, $permission);
                    }

                    return null;
                }

                if ($target->unitId !== null) {
                    if (! $user->hasPermission($permission, $target->unitId)) {
                        return $this->forbiddenResponse($permission);
                    }

                    return null;
                }

                $isResourceSpecific = $target->resourceSpecific;
            }
        }
        // 3. For resource creation operations (POST without route id)
        elseif ($request->isMethod('post')) {
            $payloadUnitId = $request->input('owner_unit_id')
                ?? $request->input('office_id')
                ?? $request->input('unit_id')
                ?? $request->input('assigned_unit_id')
                ?? $request->input('to_unit_id');

            if ($payloadUnitId !== null) {
                $targetUnitId = (int) $payloadUnitId;
                $isResourceSpecific = true;

                // Parameter tampering defense: If header claims unit X, but payload targets unit Y
                $headerUnit = $request->header('X-Organization-Unit-Id');
                if ($headerUnit !== null && (int) $headerUnit !== $targetUnitId) {
                    if (! $user->hasPermission($permission, $targetUnitId)) {
                        return response()->json([
                            'success' => false,
                            'message' => "Forbidden: Target organization unit mismatch and missing required permission [{$permission}].",
                        ], 403);
                    }
                }
            }
        }
        // 4. For listing / query endpoints
        else {
            $queryUnitId = $request->header('X-Organization-Unit-Id')
                ?? $request->query('owner_unit_id')
                ?? $request->query('unit_id');

            if ($queryUnitId !== null) {
                $targetUnitId = (int) $queryUnitId;
                $isResourceSpecific = true;
            }
        }

        $targetUnitId = $targetUnitId !== null ? (int) $targetUnitId : null;

        // Check permission against the target unit
        if (! $user->hasPermission($permission, $targetUnitId)) {
            // If request had no specific unit constraint, check if user holds this permission in any active scope
            if (! $isResourceSpecific && $targetUnitId === null) {
                $grants = $user->loadActiveGrants();
                $hasActiveGrant = $grants['direct']->contains(fn ($up) => $up->permission && $up->permission->code === $permission && $up->permission->active)
                    || $grants['roles']->contains(fn ($ur) => $ur->role && $ur->role->active && $ur->role->permissions->contains(fn ($p) => $p->code === $permission && $p->active));

                if ($hasActiveGrant) {
                    return null;
                }
            }

            return $this->forbiddenResponse($permission);
        }

        return null;
    }

    /**
     * Determine whether a user is authorized to perform an operation on a document.
     */
    protected function checkDocumentAccess($user, Document $doc, string $permission): bool
    {
        return $doc->allowsUser($user, $permission);
    }

    /**
     * Build a forbidden response for a denied document operation. High
     * confidentiality records deliberately return a generic message so that
     * the existence and classification of the record is not disclosed.
     */
    protected function forbiddenDocumentResponse(Document $doc, string $permission): Response
    {
        $confLevel = $doc->confidentiality ? $doc->confidentiality->code : 'internal';
        $message = in_array($confLevel, ['confidential', 'restricted', 'secret', 'top_secret'], true)
            ? 'Forbidden: High confidentiality document access restricted.'
            : "Forbidden: Missing required baseline permission [{$permission}].";

        return response()->json([
            'success' => false,
            'message' => $message,
        ], 403);
    }

    /**
     * Build a generic forbidden response for a denied capability.
     */
    protected function forbiddenResponse(string $permission): Response
    {
        return response()->json([
            'success' => false,
            'message' => "Forbidden: Missing required baseline permission [{$permission}].",
        ], 403);
    }
}

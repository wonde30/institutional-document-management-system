<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Models\AccreditationEvidence;
use App\Models\Document;
use App\Models\DocumentAssignment;
use App\Models\DocumentMovement;
use App\Models\DocumentTextExtraction;
use App\Models\SignatureRequest;
use Illuminate\Http\Request;

/**
 * Resolves the record that an API request operates on so the authorization
 * middleware can apply a record-level policy instead of a bare capability check.
 *
 * Every route family that addresses an existing record must be resolvable here.
 * A route family that is missing from this resolver silently degrades to a
 * capability-only check, which is why every entry below is covered by a
 * dedicated authorization test.
 */
class RouteScopeResolver
{
    /**
     * Resolve the authorization target for the given request.
     */
    public function resolve(Request $request): RouteAuthorizationTarget
    {
        $path = $request->path();
        $routeId = $request->route('id');

        if ($routeId === null) {
            return RouteAuthorizationTarget::capabilityOnly();
        }

        $id = (int) $routeId;

        // 1. Custody receipts: the movement is owned by the RECEIVING unit, so
        //    acknowledgment authority must be evaluated against that unit
        //    rather than against the originating document.
        if (str_contains($path, 'movements')) {
            $movement = DocumentMovement::find($id);

            if (! $movement) {
                return RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
            }

            if ($movement->to_unit_id) {
                return RouteAuthorizationTarget::forUnit((int) $movement->to_unit_id);
            }

            $document = $movement->document()->with('confidentiality')->first();

            return $document
                ? RouteAuthorizationTarget::forDocument($document)
                : RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
        }

        // 2. Operational directives (assignments): the directive is owned by the
        //    unit that was instructed to carry out the work.
        if (str_contains($path, 'assignments')) {
            $assignment = DocumentAssignment::with('assignedUnit')->find($id);

            if (! $assignment) {
                return RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
            }

            if ($assignment->assigned_unit_id) {
                return RouteAuthorizationTarget::forUnit((int) $assignment->assigned_unit_id);
            }

            $document = $assignment->document()->with('confidentiality')->first();

            return $document
                ? RouteAuthorizationTarget::forDocument($document)
                : RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
        }

        // 3. OCR text extractions: the extraction belongs to a document version.
        if (str_contains($path, 'extractions')) {
            $extraction = DocumentTextExtraction::find($id);

            if (! $extraction) {
                return RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
            }

            $document = $extraction->documentVersion?->document()->with('confidentiality')->first();

            return $document
                ? RouteAuthorizationTarget::forDocument($document)
                : RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
        }

        // 4. Digital signature requests: the request targets a document version.
        if (str_contains($path, 'signatures')) {
            $signature = SignatureRequest::find($id);

            if (! $signature) {
                return RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
            }

            $document = $signature->documentVersion?->document()->with('confidentiality')->first();

            return $document
                ? RouteAuthorizationTarget::forDocument($document)
                : RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
        }

        // 5. Accreditation evidence: the evidence references a document version.
        if (str_contains($path, 'accreditation/evidence')) {
            $evidence = AccreditationEvidence::find($id);

            if (! $evidence) {
                return RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
            }

            $document = $evidence->documentVersion?->document()->with('confidentiality')->first();

            return $document
                ? RouteAuthorizationTarget::forDocument($document)
                : RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
        }

        // A route that addresses a specific record but could not be resolved to a
        // document or an owning unit remains resource specific, so the decision
        // falls back to the global scope capability check rather than to the
        // looser "any active scope" fallback.
        return RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
    }
}

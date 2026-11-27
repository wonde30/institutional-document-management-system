<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Models\AccreditationEvidence;
use App\Models\DigitalSignature;
use App\Models\Document;
use App\Models\DocumentAssignment;
use App\Models\DocumentMovement;
use App\Models\DocumentTextExtraction;
use App\Models\PhysicalOriginal;
use App\Models\ProjectProposal;
use App\Models\ResearchRecord;
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

        // 0. Dataset import and export operations. The operation is owned by
        //    the organizational unit it applies to.
        if (str_contains($path, 'data/imports')) {
            $operation = \App\Models\DataImportOperation::find($id);

            if (! $operation) {
                return RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
            }

            if (! $operation->scope_unit_id) {
                return RouteAuthorizationTarget::capabilityOnly();
            }

            return RouteAuthorizationTarget::forUnit((int) $operation->scope_unit_id);
        }

        if (str_contains($path, 'data/exports')) {
            $operation = \App\Models\DataExportOperation::find($id);

            if (! $operation) {
                return RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
            }

            // An export that was not narrowed to a single organizational unit
            // covers every unit the caller may reach, so it is not bound to one
            // resource scope.
            if (! $operation->scope_unit_id) {
                return RouteAuthorizationTarget::capabilityOnly();
            }

            return RouteAuthorizationTarget::forUnit((int) $operation->scope_unit_id);
        }

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

            return ($document instanceof Document)
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

            return ($document instanceof Document)
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

            return ($document instanceof Document)
                ? RouteAuthorizationTarget::forDocument($document)
                : RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
        }

        // 4. Digital signatures & signature requests:
        //    - signatures/{id}/verify addresses a DigitalSignature ID
        //    - signatures/{id}/execute addresses a SignatureRequest ID
        if (str_contains($path, 'signatures')) {
            if (preg_match('#signatures/\d+/verify$#', $path)) {
                $digitalSignature = DigitalSignature::with('request.documentVersion.document.confidentiality')->find($id);

                if (! $digitalSignature) {
                    return RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
                }

                $document = $digitalSignature->request?->documentVersion?->document;
                if (! ($document instanceof Document) && $digitalSignature->signed_file_id) {
                    $document = Document::resolveForFile((int) $digitalSignature->signed_file_id);
                }

                return ($document instanceof Document)
                    ? RouteAuthorizationTarget::forDocument($document)
                    : RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
            }

            $signature = SignatureRequest::find($id);

            if (! $signature) {
                return RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
            }

            $document = $signature->documentVersion?->document()->with('confidentiality')->first();

            return ($document instanceof Document)
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

            return ($document instanceof Document)
                ? RouteAuthorizationTarget::forDocument($document)
                : RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
        }

        // 5b. Project proposals: the proposal references a document.
        if (str_contains($path, 'projects')) {
            $proposal = ProjectProposal::find($id);

            if (! $proposal) {
                return RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
            }

            $document = $proposal->document()->with('confidentiality')->first();

            return ($document instanceof Document)
                ? RouteAuthorizationTarget::forDocument($document)
                : RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
        }

        // 5c. Research outputs: published non-confidential outputs are readable across units
        //     with research.view; unpublished, high-confidentiality, or mutating requests bind
        //     to the underlying document policy (Document::allowsUser).
        if (str_contains($path, 'research/outputs')) {
            $record = ResearchRecord::with(['document.confidentiality', 'publications'])->find($id);

            if (! $record) {
                return RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
            }

            $document = $record->document;
            if (! ($document instanceof Document)) {
                return RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
            }

            $confCode = $document->confidentiality?->code;
            $isHighConfidentiality = in_array($confCode, ['confidential', 'restricted', 'secret', 'top_secret'], true);

            if ($request->isMethod('get') && $record->publications->isNotEmpty() && ! $isHighConfidentiality) {
                return RouteAuthorizationTarget::capabilityOnly(resourceSpecific: false);
            }

            return RouteAuthorizationTarget::forDocument($document);
        }

        // 5d. Staff physical records: bound to the underlying HR document policy.
        if (str_contains($path, 'staff/records')) {
            $original = PhysicalOriginal::find($id);

            if (! $original) {
                return RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
            }

            $document = $original->document()->with('confidentiality')->first();

            return ($document instanceof Document)
                ? RouteAuthorizationTarget::forDocument($document)
                : RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
        }

        // 5e. Archive retention policies: institutional catalog definitions (not unit-owned).
        if (str_contains($path, 'admin/retention-policies')) {
            return RouteAuthorizationTarget::capabilityOnly(resourceSpecific: false);
        }

        // 6. Archive records: entries, disposition requests, and archive files.
        if (str_contains($path, 'archive/entries')) {
            $entry = \App\Models\ArchiveEntry::find($id);

            if (! $entry) {
                return RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
            }

            $document = $entry->document()->with('confidentiality')->first();

            return ($document instanceof Document)
                ? RouteAuthorizationTarget::forDocument($document)
                : RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
        }

        if (str_contains($path, 'archive/disposition-requests')) {
            $disp = \App\Models\DispositionRequest::with('archiveEntry.document.confidentiality')->find($id);

            if (! $disp || ! $disp->archiveEntry?->document) {
                return RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
            }

            return RouteAuthorizationTarget::forDocument($disp->archiveEntry->document);
        }

        if (str_contains($path, 'archive/legal-holds')) {
            $hold = \App\Models\LegalHold::with('documents.confidentiality')->find($id);
            if ($hold && $hold->documents->isNotEmpty()) {
                $unitCount = $hold->documents->pluck('owner_unit_id')->filter()->unique()->count();
                if ($unitCount === 1) {
                    return RouteAuthorizationTarget::forDocument($hold->documents->first());
                }
            }

            return RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
        }

        if (str_contains($path, 'accreditation/cycles')) {
            $cycle = \App\Models\AccreditationCycle::find($id);
            if ($cycle && $cycle->unit_id !== null) {
                return RouteAuthorizationTarget::forUnit((int) $cycle->unit_id);
            }
            return RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
        }

        if (str_contains($path, 'organization-units')) {
            return RouteAuthorizationTarget::forUnit($id);
        }

        if (str_contains($path, 'archive/files')) {
            $document = Document::resolveForFile($id);

            return ($document instanceof Document)
                ? RouteAuthorizationTarget::forDocument($document)
                : RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
        }

        // 7. Workflow instances: the instance is bound to the document version being routed.
        if (str_contains($path, 'workflows/instances')) {
            $instance = \App\Models\WorkflowInstance::with('documentVersion.document.confidentiality')->find($id);

            if (! $instance) {
                return RouteAuthorizationTarget::capabilityOnly(resourceSpecific: true);
            }

            $document = $instance->documentVersion?->document;

            return ($document instanceof Document)
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

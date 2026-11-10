<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Models\Document;

/**
 * Immutable description of the authorization target behind an API request.
 *
 * The target determines which server-side policy decides the request:
 *
 *  - document   -> the record belongs to a document. The authoritative decision
 *                  is Document::allowsUser(), which applies unit scope,
 *                  confidentiality clearance, discretionary document grants
 *                  and the creator/custodian read-only rule.
 *  - unit       -> the record is owned by an organizational unit (for example a
 *                  custody receipt owned by the receiving unit). The decision is
 *                  User::hasPermission($code, $unitId).
 *  - capability -> the route is not bound to a single record. Only the
 *                  capability permission is evaluated.
 */
final class RouteAuthorizationTarget
{
    public function __construct(
        public readonly ?Document $document = null,
        public readonly ?int $unitId = null,
        public readonly bool $resourceSpecific = false,
    ) {
    }

    /**
     * A target that is bound to a specific document record.
     */
    public static function forDocument(Document $document): self
    {
        return new self(document: $document, resourceSpecific: true);
    }

    /**
     * A target that is owned by an organizational unit.
     */
    public static function forUnit(int $unitId): self
    {
        return new self(unitId: $unitId, resourceSpecific: true);
    }

    /**
     * A target that is not bound to a single record.
     */
    public static function capabilityOnly(bool $resourceSpecific = false): self
    {
        return new self(resourceSpecific: $resourceSpecific);
    }
}

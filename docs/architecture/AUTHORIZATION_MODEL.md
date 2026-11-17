# Authorization Model

**System:** Wollo University Administrative and Document Management System (WU-DMS)
**Scope:** This document is the authoritative specification of the authorization
boundary of the WU-DMS. Where any other artifact disagrees with this document,
the executable code and the automated contract tests are authoritative.

---

## 1. Design Principles

| # | Principle | Implementation |
|---|---|---|
| 1 | One authoritative engine | `App\Http\Middleware\CheckBaselinePermission` is the single server-side authorization gate. No second parallel engine exists. |
| 2 | Server is the only authority | Every protected request is re-evaluated on the server. Client permission state is a user-experience affordance only. |
| 3 | Capability **and** record ownership | Holding a capability is necessary but not sufficient. The record's owning unit or document must also authorize the operation. |
| 4 | Default deny | A request that resolves to no grant is refused with HTTP 403. |
| 5 | Fail closed | A record that cannot be resolved is refused (404 for the document family) rather than passed through. |
| 6 | Single resolver | The capability decision and the published capability list share one scope-matching implementation and cannot drift apart. |

---

## 2. The Six-Stage Evaluation

Every protected API request passes through the following stages in order.

```
1. AUTHENTICATE            auth:sanctum          → 401 when unauthenticated
2. ACCOUNT STATUS          active_account        → 403 when the account is not active
3. AUTHORIZATION VERSION   authz_version         → 401 when the session is stale
4. CAPABILITY RESOLUTION   permission:<code>     → 403 when no grant covers the scope
5. RECORD OWNERSHIP        scope resolver        → 403 when the record's owner denies
6. EXECUTE & AUDIT         controller + audit    → append-only audit event
```

The middleware priority is declared in `server/bootstrap/app.php` so that the
ordering above is guaranteed rather than incidental.

### Stage 1–3 notes

* Authentication is **Sanctum SPA stateful session** with double-submit CSRF
  protection. No bearer token is persisted in browser storage.
* `ValidateAuthorizationVersion` compares the `authz_version` recorded in the
  session against the user record. Any privilege change increments the stored
  version, which terminates every session established before the change.
* `EnsureAccountIsActive` refuses accounts whose `status` is not `active`.

---

## 3. Scope Semantics

An authorization scope has one of three kinds.

| Kind | Applies to | Notes |
|---|---|---|
| `global` | Every organizational unit | The only kind that satisfies a check with no target unit. |
| `unit` | Exactly one organizational unit | Does **not** cover descendants. |
| `subtree` | The anchor unit **and every descendant** | Resolved through `OrganizationUnit::getAncestorIds()`. |

### 3.1 The decisive rule

> A capability check **without** a target unit is satisfied **only** by a
> `global` grant. A `unit` or `subtree` grant never satisfies an unscoped check.

This rule is implemented once, in `User::scopeAppliesToUnit()`, and is used by
both `User::hasPermission()` and `User::getEffectivePermissionCodes()`. The two
therefore cannot disagree.

### 3.2 Resolver API

| Method | Purpose | No target unit | Target unit |
|---|---|---|---|
| `User::hasPermission($code, ?$unitId)` | The authorization decision | global grants only | global + exact unit + subtree anchored on the unit or an ancestor |
| `User::getEffectivePermissionCodes(?$unitId)` | "What may I do here" | global grants only | identical to `hasPermission` |
| `User::getAllPermissionCodesAcrossScopes()` | Administrative grant matrix | union of every scope | union of every scope |

`getAllPermissionCodesAcrossScopes()` is reserved for administrative surfaces.
It **must never** be used to decide whether an operation is permitted.

---

## 4. Record Ownership Resolution

A capability alone does not authorize an operation on an existing record. The
middleware resolves the record and applies the ownership policy.

### 4.1 Document-owned records

When a record belongs to a document, the decision is
`Document::allowsUser($user, $permission)`, which evaluates, in order:

1. the account is active;
2. the super administrator role (`super_admin`) — full access;
3. the owner-unit grant (`hasPermission($code, $document->owner_unit_id)`);
4. **elevated clearance** for high-confidentiality records (`confidential`,
   `restricted`, `secret`, `top_secret`): the caller must additionally hold
   `staff.view_sensitive` in the owner unit, or hold one of the roles
   `department_head`, `document_controller`, `records_officer`;
5. an active discretionary grant in `document_grants` for that user or one of
   their units;
6. the creator / custodian intrinsic rule, which applies **only** to read
   operations (`documents.view`, `documents.download`, `documents.share`).

### 4.2 Unit-owned records

| Record family | Owning scope | Rationale |
|---|---|---|
| `document_movements` (custody receipt) | `to_unit_id` | A custody receipt is given by the **receiving** unit. The originating unit cannot acknowledge its own transfer. |
| `document_assignments` (operational directive) | `assigned_unit_id` | A directive is owned by the unit instructed to carry out the work. |
| `templates` render / generate-document | payload `owner_unit_id` | The generated document is created in the named unit. |

### 4.3 Route family resolution table

| Route family | Resolution | Policy |
|---|---|---|
| `documents/{id}` | document | `allowsUser` |
| `files/{fileId}/download` | document via `Document::resolveForFile` | `allowsUser` |
| `research/outputs/{id}` | owner unit | `hasPermission` |
| `correspondences/{id}` | owner unit, then movement/assignment units | `hasPermission` |
| `correspondences/{id}/movements/{movementId}/acknowledge` | movement `to_unit_id` | `hasPermission` |
| `staff/records/{id}` | owner unit | `hasPermission` |
| `workflows/tasks/{id}` | assigned unit, else document owner unit | `hasPermission` |
| `movements/{id}` | movement `to_unit_id` | `hasPermission` |
| `assignments/{id}` | assignment `assigned_unit_id` | `hasPermission` |
| `extractions/{id}` | document via version | `allowsUser` |
| `signatures/{id}` | document via version | `allowsUser` |
| `accreditation/evidence/{id}` | document via version | `allowsUser` |
| collection endpoints | header / query unit, else any active grant | `hasPermission` |

The resolver lives in `App\Services\Authorization\RouteScopeResolver` and the
resolved target in `App\Services\Authorization\RouteAuthorizationTarget`.

> **Flat aliases must not be weaker than their nested siblings.**
> `POST /extractions/{id}/verify` and
> `POST /documents/{id}/versions/{versionId}/extractions/{extractionId}/verify`
> resolve to the same record and are therefore bound by the same policy. This is
> asserted by
> `AuthorizationBoundaryEnforcementTest::test_flat_extraction_alias_enforces_the_same_boundary_as_the_nested_route`.

---

## 5. Capability Registry

76 capabilities are registered in the `permissions` table by
`BaselineReferenceVocabularySeeder`. They are grouped as follows.

| Domain | Capabilities |
|---|---|
| Document repository | `documents.view`, `documents.create`, `documents.update`, `documents.delete`, `documents.download`, `documents.share`, `documents.upload`, `documents.version` |
| Correspondence | `correspondence.view`, `correspondence.register`, `correspondence.issue_direct`, `correspondence.forward`, `correspondence.assign`, `correspondence.acknowledge`, `correspondence.dispatch`, `correspondence.send` |
| Workflow | `workflow.view`, `workflow.submit`, `workflow.review`, `workflow.approve`, `workflow.reject`, `workflow.reassign`, `workflow.cancel`, `workflow.configure` |
| Archive | `archive.view`, `archive.transfer`, `archive.request_disposal`, `archive.approve_disposal`, `archive.execute_disposal`, `archive.manage_retention`, `archive.restore` |
| Research & projects | `research.view`, `research.create`, `research.update`, `research.publish`, `research.withdraw`, `projects.view`, `projects.submit`, `projects.review`, `projects.approve` |
| Accreditation | `accreditation.view`, `accreditation.submit_evidence`, `accreditation.review_evidence`, `accreditation.manage`, `accreditation.export` |
| Signatures & stamps | `signatures.request`, `signatures.sign`, `signatures.verify`, `stamps.apply`, `stamps.manage` |
| Staff records | `staff.view`, `staff.update`, `staff.view_sensitive` |
| Organization | `organization.view`, `organization.manage` |
| Users | `users.view`, `users.create`, `users.update`, `users.deactivate` |
| Roles & permissions | `roles.view`, `roles.create`, `roles.update`, `roles.delete`, `roles.assign`, `permissions.view`, `permissions.assign` |
| Reporting & audit | `reports.view`, `reports.export`, `audit.view` |
| Platform | `system.configure`, `backups.manage`, `backups.restore` |

### 5.1 Registry integrity

Every capability required by a route must exist in the registry. A capability
that is not registered can never be granted to a role, so the route is
unreachable for every account including the super administrator. This is
enforced by
`NavigationPermissionContractTest::test_every_route_capability_exists_in_the_permission_registry`.

---

## 6. The Active Organizational Unit Context

The client never decides authorization; it decides presentation. The active
organizational unit is the context in which that presentation happens.

### 6.1 Available units

A unit is **available** to a user when either of the following holds:

* the user is an **active member** of it (`organization_memberships` with no
  `ends_at`, or an `ends_at` in the future); or
* the user holds an **active scoped grant** that covers it — a `unit` scope
  covering that exact unit, or a `subtree` scope covering the anchor unit and
  every descendant.

A `global` grant is unconstrained and therefore does **not** widen the
selectable set: a globally authorized administrator is offered the units they
belong to, not the whole institution.

Inactive units are never offered and never accepted as a selection.

The computation is `User::getActiveMembershipUnitIds()` and
`User::getGrantScopedUnitIds()`, both of which delegate the subtree expansion
to the shared `User::expandScopesToUnitIds()`.

### 6.2 Resolving the active unit

The active unit is resolved by the server, in this order:

1. the unit recorded **in the session**, when it is still available;
2. the user's **primary membership**, when it is available;
3. the **single** available unit, when exactly one exists;
4. otherwise **no active unit**.

A recorded unit that is no longer available — for example after the grant that
produced it was revoked — is discarded rather than honoured.

The selection is stored **server side, in the session**
(`OrganizationalContextService::SESSION_KEY`). The client never supplies an
authoritative unit identifier, and no unit identifier is trusted as proof of
authorization.

### 6.3 Switching context

`POST /api/v1/auth/active-unit` with `{ "unit_id": <id> }`:

* the unit is validated against the units the server has determined the caller
  may select; an unknown, inactive or unauthorized unit is **refused with
  HTTP 403** and the previous context is left untouched;
* on success the selection is recorded in the session and the refreshed
  context is returned;
* the attempt is recorded in the audit trail (`auth.active_unit_changed` or
  `auth.active_unit_rejected`).

Switching **never widens a grant**. A `unit` scope covers exactly its own unit
and is not expanded by the selection; only a `subtree` scope covers
descendants. Moving into a descendant of a `unit`-scoped grant therefore
*withdraws* that capability from the published set, which is the correct
behaviour.

### 6.4 Published capability sets

`GET /api/v1/auth/me` publishes:

| Field | Meaning |
|---|---|
| `permissions` | Capabilities calculated **for the active organizational context**. Never the union of every scoped grant. |
| `permissions_global` | Capabilities held in the global scope only. |
| `permissions_all_scopes` | Union across every scope. Reserved for administrative surfaces; never used to decide an operation. |
| `active_unit` | The resolved unit: id, code, name, unit type, parent. |
| `active_unit_id` | The resolved unit identifier, or `null`. |
| `available_units` | The units the user may select. |
| `unit_selection_required` | `true` when the user holds scoped capabilities but no unit is active. |

### 6.5 A user without an active unit

A user who holds scoped capabilities but has neither a membership nor a single
available unit has **no active unit**. In that state:

* `permissions` contains the **globally scoped capabilities only**;
* no scoped capability is published, and therefore no scoped action is
  presented;
* `unit_selection_required` is `true`, and the interface asks for a selection.

This is the direct consequence of the decisive rule in §3.1: a check without a
target unit is satisfied only by a global grant.

### 6.6 The active unit is not a substitute for record authorization

The active unit determines the **interface**. It never replaces the check
performed against the record being accessed.

* A record-scoped request is evaluated against the **owning unit or document
  of that record**, exactly as before (§4).
* A capability published in the active context does **not** authorize the same
  operation on a record in another unit; the request is refused with
  HTTP 403.
* Collection endpoints remain scoped by `User::getPermittedUnitIds()`, which is
  grant driven and unchanged.
* Creation endpoints continue to validate the payload's `owner_unit_id`
  against the caller's permission in that unit.

### 6.7 Frontend publication

The Pinia authentication store is the single client-side authority:

| Member | Source |
|---|---|
| `availableUnits` | `user.available_units` |
| `activeUnit` / `activeUnitId` | `user.active_unit` / `user.active_unit_id` |
| `unitSelectionRequired` | `user.unit_selection_required` |
| `userPermissions` / `hasPermission()` | `user.permissions` — the active context set |

During a switch the capability list is **emptied** so that no capability from
the previous context remains visible. If the switch is refused, the previous
context is restored in full; the interface is never left partially updated.
The router guard awaits an in-flight switch, so a navigation that happens
during a switch never evaluates a stale capability set.

### 6.8 Navigation and routing

| Surface | Declaration | Contract |
|---|---|---|
| API route | `->middleware('permission:<code>')` | Authoritative. |
| Navigation catalog | `NavigationController::getMasterNavigationCatalog()` | Filtered with the **active context** capability set. |
| Front-end route | `meta.permission` (a capability, or a list of alternatives) | Affordance; "any of" semantics, matching the server-side `a\|b` contract. |

`NavigationPermissionContractTest` asserts that these three declarations agree
and that every capability they reference exists in the registry. The test fails
the build when they drift.

---

## 7. Session Lifecycle

| Event | Server action | Client action |
|---|---|---|
| Sign in | Session regenerated, `authz_version` bound to the session | Identity and capability set cached for presentation |
| Privilege change | `authz_version` incremented | Next request receives HTTP 401 |
| Password change | `authz_version` incremented, other sessions terminated | — |
| Password recovery completed | `authz_version` incremented, **all** sessions terminated | Identity cache dropped |
| Sign out | Session invalidated, token rotated, session rows deleted | Identity, capability set and navigation dropped |
| Account deactivated | `active_account` middleware refuses | HTTP 403 surfaced |
| Organizational context switched | Active unit recorded in the session; capability set recalculated | Capability list emptied for the duration of the switch, then replaced |
| Grant revoked | `authz_version` incremented; the recorded unit is discarded when it is no longer available | Next request receives HTTP 401 |

### 7.1 Client session states

The Pinia authentication store publishes `sessionState`:

| State | Meaning | Guard behaviour |
|---|---|---|
| `unknown` | Not confirmed with the server | The router retries the bootstrap on the next navigation |
| `verified` | The server confirmed the session | The cached identity is used |
| `unauthenticated` | The server rejected the session | Protected routes redirect to sign in |

A 401 response clears the in-memory identity, the cached capability set and the
cached navigation. A 403 response never terminates the session.

---

## 8. Response Contract

| Condition | Status | Body |
|---|---|---|
| Unauthenticated | 401 | `{"success": false, "message": "Unauthenticated."}` |
| Authenticated, not authorized | 403 | `{"success": false, "message": "Forbidden: ..."}` |
| Record does not exist | 404 | `{"success": false, "message": "... not found."}` |
| Validation failure | 422 | `{"success": false, "message": "...", "errors": {...}}` |
| Success | 200 / 201 / 202 | `{"success": true, "message": "...", "data": ...}` |

High-confidentiality records deliberately return a generic refusal so that the
existence and classification of the record is not disclosed.

---

## 9. Verification

| Requirement | Test |
|---|---|
| Cross-unit document access is refused | `SecurityAndAccessControlAuditTest` |
| Record families resolve to their owner | `AuthorizationBoundaryEnforcementTest` |
| Flat alias enforces the same boundary | `AuthorizationBoundaryEnforcementTest` |
| Custody receipt belongs to the receiving unit | `AuthorizationBoundaryEnforcementTest` |
| Directive belongs to the assigned unit | `AuthorizationBoundaryEnforcementTest` |
| Resolver and decision agree | `AuthorizationBoundaryEnforcementTest` |
| Published capability set is scoped | `AuthorizationBoundaryEnforcementTest` |
| Navigation is filtered by the scoped set | `AuthorizationBoundaryEnforcementTest` |
| Navigation, router and API agree | `NavigationPermissionContractTest` |
| Every route capability is registered | `NavigationPermissionContractTest` |
| Visible navigation is backed by the published set | `NavigationPermissionContractTest` |
| Global grant applies in every unit | `ActiveOrganizationalUnitTest` |
| Role based unit grant is limited to its scope | `ActiveOrganizationalUnitTest` |
| Direct user unit grant is limited to its scope | `ActiveOrganizationalUnitTest` |
| College subtree grant covers the college and descendants | `ActiveOrganizationalUnitTest` |
| Department grant does not reach a sibling department | `ActiveOrganizationalUnitTest` |
| No active unit means no union of scoped capabilities | `ActiveOrganizationalUnitTest` |
| Unauthorized unit selection is refused | `ActiveOrganizationalUnitTest` |
| Switching recalculates the capability set | `ActiveOrganizationalUnitTest` |
| Revoked permission is no longer advertised | `ActiveOrganizationalUnitTest` |
| Record authorization is unaffected by the active unit | `ActiveOrganizationalUnitTest` |
| Navigation follows the active context | `ActiveOrganizationalUnitTest` |
| Session termination clears the client state | `client/tests/authSessionLifecycle.spec.ts` |
| Route guards admit and refuse correctly | `client/tests/authSessionLifecycle.spec.ts` |
| Account recovery works and is not enumerable | `AccountRecoveryTest` |
| Context publication and switching on the client | `client/tests/activeUnitContext.spec.ts` |
| No stale capability during a switch | `client/tests/activeUnitContext.spec.ts` |
| A refused switch restores the previous context | `client/tests/activeUnitContext.spec.ts` |
| The context switcher is offered only when applicable | `client/tests/activeUnitContext.spec.ts` |

---

## 10. Outstanding Items

| Item | Severity | Status |
|---|---|---|
| `System Settings` and `Data Imports & Exports` are static screens with no backing API | Medium | Open — see `docs/operations/BUILD_STATUS.md` |
| `DocumentAssignment` participant removal is authorized by unit but does not record an audit event | Low | Open |
| The active unit is stored in the session, so it is shared between the browser tabs of one session | Low | Accepted; the alternative is a per-tab preference, which the server would still have to validate |

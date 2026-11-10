# Build Status

**System:** Wollo University Administrative and Document Management System (WU-DMS)
**Verification run:** 2026-11-10
**Baseline commit:** single consolidated root commit on `main`

---

## 1. Verification Scorecard

| Check | Command | Result | Evidence |
|---|---|---|---|
| Backend test suite | `php artisan test` | **PASS** | 308 tests, 308 passed, 2015 assertions, 133.7 s |
| Frontend test suite | `npx vitest run` | **PASS** | 23 files, 129 tests, 129 passed, 4.5 s |
| Frontend type check | `npx vue-tsc -b --noEmit` | **PASS** | exit code 0, no diagnostics |
| Production build | `npx vite build` | **PASS** | exit code 0, `✓ built in 772ms` |
| Dependency advisories | `composer audit` | **PASS** | "No security vulnerability advisories found." |
| Manifest validity | `composer validate --no-check-publish` | **PASS** | "./composer.json is valid" |
| Lockfile synchronisation | `npm ci --dry-run` | **PASS** | "up to date" |

No check was skipped, stubbed or reported from a cached result. Every figure
above is the output of a command executed in this environment against the
working tree as it now stands.

**Database used for verification:** MySQL 8.4 (`wollo_dms_test`), the project's
real test database configuration. SQLite was not substituted at any point.

---

## 2. Phase 2 — Active Organizational Unit Context

### 2.1 Status

**PASS — verified and working.** Open Item #3 from the previous cycle is
closed: the capability set published to the client is now calculated for an
active organizational context instead of being the union of every scoped
grant.

### 2.2 What was implemented

| Layer | Change |
|---|---|
| Server | `App\Services\Authorization\OrganizationalContextService` computes the available units, resolves the active unit and publishes the context. |
| Server | `POST /api/v1/auth/active-unit` switches the context; the unit is validated against the units the caller may select. |
| Server | `GET /api/v1/auth/me` publishes `active_unit`, `active_unit_id`, `available_units`, `unit_selection_required` and the three capability sets. |
| Server | `GET /api/v1/navigation` filters the catalog with the **active context** capability set. |
| Server | `User::getCapabilitySets()` resolves the active, global and all-scope capability sets in a single pass over the grants. |
| Client | `useAuthStore` publishes `availableUnits`, `activeUnit`, `activeUnitId`, `unitSelectionRequired`, `isSwitchingUnit` and `switchUnit()`. |
| Client | The sidebar renders an accessible context switcher and an explicit notice when a selection is required. |
| Client | The router guard awaits an in-flight switch so that a navigation never evaluates a stale capability set. |

### 2.3 Authorization test matrix — actual results

All results below are from `php artisan test --filter=ActiveOrganizationalUnitTest`
(16 tests, 106 assertions, all passing) and the full suite (308 tests).

| Scenario | Expected behaviour | Result |
|---|---|---|
| Global permission | Available in every unit context | PASS |
| Role-based unit grant | Available only in the applicable scope | PASS |
| Direct user unit grant | Available only in the applicable scope | PASS |
| College subtree grant | Applies to the college and valid descendants | PASS |
| Department grant | Does not grant unrelated department access | PASS |
| Sibling department | No inherited access without a valid grant | PASS |
| No active unit | No union of scoped permissions (global only) | PASS |
| Unauthorized unit selection | Rejected by the server (403) | PASS |
| Unit switch | Recalculates the effective capabilities | PASS |
| Revoked permission | No longer advertised | PASS |
| Direct API request | Resource-level authorization still enforced | PASS |
| Cross-unit record read | 403 | PASS |
| Cross-unit record update | 403, record unchanged | PASS |
| Cross-unit record deletion | No deletion endpoint is exposed (405) | PASS |
| Unauthorized creation under another unit | 403 | PASS |
| Descendant resolution | Subtree anchor covers its descendants | PASS |
| Global versus scoped precedence | Both respected; global applies unscoped | PASS |
| Inactive unit | Cannot be selected (403) | PASS |
| Navigation before / during / after switching | Follows the active context | PASS |
| Session expiration (401) | Client session cleared | PASS (frontend suite) |
| Forbidden operation (403) | Session retained | PASS (frontend suite) |

### 2.4 Query cost

The context resolution was measured with the query log enabled against the
staging database for `demo.dean.informatics@wu.edu.et` (1 role grant, 4 direct
grants, 4 available units):

| State | Queries for one context build |
|---|---|
| Before the optimisation (per-grant subtree walk, repeated grant loads) | **116** |
| After: batched subtree expansion, single grant load per context | **19** |

The reduction is a genuine fix, not a cache: the grants are **never** cached
on the model, so a grant that is added or revoked is always observed by the
next decision. An earlier iteration memoised the grants and was **rejected**
because it made a revoked capability remain advertised — the regression was
caught by `test_revoked_permission_is_no_longer_advertised`.

### 2.5 Test inventory added in this phase

| Suite | Tests | Coverage |
|---|---|---|
| `ActiveOrganizationalUnitTest` | 16 | The full matrix in §2.3, against a real university → college → department hierarchy |
| `NavigationPermissionContractTest::test_visible_navigation_is_backed_by_the_published_capability_set` | 1 | Every offered navigation item is backed by a published capability |
| `client/tests/activeUnitContext.spec.ts` | 9 | Context publication, switching, stale-capability withdrawal, refused switch, single-switch enforcement, switcher rendering |

---

## 3. Test Inventory

### 3.1 Backend (PHPUnit / Pest) — 308 tests

| Suite | Purpose |
|---|---|
| `ActiveOrganizationalUnitTest` | Active unit context and scope-aware authorization |
| `AuthorizationBoundaryEnforcementTest` | Record-level boundary enforcement across six record families |
| `NavigationPermissionContractTest` | Four-way agreement between the API contract, the dynamic catalog, the front-end router and the static fallback; registry integrity |
| `AccountRecoveryTest` | Recovery delivery, non-enumeration, session termination, address confirmation |
| `SecurityAndAccessControlAuditTest` | Cross-department access, anti-self-approval, confidentiality |
| `SecurityPenetrationTest` | Session invalidation, account lockout, replay resistance |
| Remaining 22 suites | Workflow, correspondence, repository, archive, PKI, disaster recovery, schema invariants, scheduling, transactions, credentials |

### 3.2 Frontend (Vitest) — 129 tests

| Suite | Purpose |
|---|---|
| `activeUnitContext.spec.ts` | Organizational context publication and switching |
| `authSessionLifecycle.spec.ts` | 401 clears the in-memory session, 403 preserves it, route guards |
| `accountRecovery.spec.ts` | Recovery request reaches the API, failure is surfaced, confirmation consumes the link |
| `DocumentDetailView.spec.ts` | Document detail rendering and action states |
| `AppSidebar.spec.ts` | Collapsible navigation |
| Remaining 18 suites | Component, store and composable behaviour |

---

## 4. Defects Found and Closed

Every item below was discovered by inspection of the running code, reproduced,
and closed with a regression test.

| # | Severity | Defect | Root cause | Closure |
|---|---|---|---|---|
| 1 | **High** | `POST /extractions/{id}/verify` allowed a caller with a global `documents.update` grant to rewrite the verified OCR text of any department's document. The nested sibling route enforced ownership; the flat alias did not. | The middleware resolved record ownership by path substring and had no branch for the extraction family, so the request fell through to a capability-only check. | `RouteScopeResolver` resolves the owning document; `AuthorizationBoundaryEnforcementTest` asserts both routes enforce the same boundary |
| 2 | **High** | `POST /movements/{id}/acknowledge` allowed the **originating** unit to issue a custody receipt for its own outbound transfer. | No branch for the movement family; the sibling correspondence route resolved `to_unit_id` but the direct route did not. | Resolver binds the movement to `to_unit_id`; test asserts the originating unit is refused and the receiving unit succeeds |
| 3 | **High** | `POST /assignments/{id}/participants`, `/events` and `/responses` performed no ownership check; the controller and service contain no authorization logic at all. | The assignment family had no resolution branch. | Resolver binds the directive to `assigned_unit_id`; test asserts refusal and absence of side effects |
| 4 | **High** | `POST /workflows/tasks/{id}/reassign` was unreachable for **every** account, including the super administrator. | The route required `workflows.manage`, which is not in the permission registry and therefore cannot be granted to any role. | Corrected to the registered capability `workflow.reassign`; the contract test now fails the build if any route capability is unregistered |
| 5 | **Medium** | The capability decision and the published capability list used different scope rules, so the client could advertise authority the server would refuse — and a subtree grant was invisible for descendant units. | `hasPermission()` and `getEffectivePermissionCodes()` each implemented their own scope filter. | Both now delegate to one `scopeAppliesToUnit()` implementation; test asserts they agree |
| 6 | **Medium** | The account recovery, password reset and address confirmation screens reported success without contacting the server. `SystemSettingsView` and `DataImportsExportsView` are static screens; the settings screen persists nothing and there is no `system_settings` table. | Placeholder user interface. | Recovery and verification are now real API-backed flows; the settings and import/export screens remain open (§6) |
| 7 | **Medium** | A 401 response cleared `localStorage` but left the in-memory Pinia session intact, so the application shell kept rendering an authenticated workspace for a dead session. | The response interceptor had no access to the store. | `sessionEvents.ts` bridges the client to the store; `authSessionLifecycle.spec.ts` asserts the state is cleared |
| 8 | **Medium** | The session bootstrap was marked complete **before** it ran, so a transient failure stranded the application on an empty session until a manual reload. | The flag was set outside the `try` block. | Bootstrap is retried while the session state is `unknown`; the duplicate flag was removed in favour of the store's `sessionState` |
| 9 | **Medium** | Route metadata declared capabilities that the serving API route did not require (`/staff`, `/projects`, `/signatures`, `/admin/operations`, `/admin/settings`, `/search`), so the client admitted or refused surfaces inconsistently with the server. | Four independent permission declarations with no contract. | All four declarations aligned; `NavigationPermissionContractTest` asserts their agreement |
| 10 | **Medium** | The dynamic navigation catalog omitted the Templates surface entirely, so it disappeared from the menu once the dynamic catalog loaded, while remaining visible in the static fallback. | The catalog was not derived from the API contract. | Templates added; the static fallback and the dynamic catalog are now asserted to agree |
| 11 | **Low** | Validation failures were returned without the `success` discriminator used by every other API response. | The framework's default validation rendering was used. | A validation renderer in `bootstrap/app.php` emits the documented envelope; the change is additive, so existing assertions are unaffected |
| 12 | **Low** | A document that did not exist passed the authorization middleware and was rejected only by the controller. | The middleware returned "allow" for an unresolved record. | The middleware now fails closed with HTTP 404 |
| 13 | **Medium** | Resolving the organizational context executed **116** database queries for a single request, because the subtree walk was repeated once per grant and the grants were loaded once per capability resolution. | `getDescendantIds()` was called per scope, and each capability resolution re-queried the grants. | The subtree expansion is batched across all anchors and the grants are loaded once per context: **19 queries**. Grants are never cached on the model, so a revoked capability is never advertised |

### 4.1 Dependency remediation

| Package | From | To | Reason |
|---|---|---|---|
| `symfony/http-foundation` | 8.1.9 | 8.1.11 | CVE-2026-118514, PKSA-pr6q-2w5s-kzzf, PKSA-nxjs-s6hn-vb6m, PKSA-b2gg-vk1g-6d5q |
| `symfony/http-kernel` | 8.1.9 | 8.1.11 | CVE-2026-123339, GHSA-wg5q-m2ww-f3c9 |
| `symfony/routing` | 8.1.9 | 8.1.11 | CVE-2026-118516 |

`composer audit` now reports **no** advisories.

---

## 5. Architecture Delivered

| Component | Purpose |
|---|---|
| `app/Services/Authorization/RouteScopeResolver.php` | Resolves the record behind a request to its owning document or unit |
| `app/Services/Authorization/RouteAuthorizationTarget.php` | Immutable description of the resolved target and the policy it selects |
| `app/Services/Authorization/OrganizationalContextService.php` | Available units, active unit resolution, context publication |
| `app/Http/Middleware/CheckBaselinePermission.php` | The single authoritative authorization gate |
| `app/Models/User.php` | The single scope-matching implementation (`scopeAppliesToUnit`) and the single-pass capability resolution (`getCapabilitySets`) |
| `bootstrap/app.php` | Middleware priority, validation error envelope |
| `client/src/api/sessionEvents.ts` | Decouples the HTTP client from the authentication store |
| `client/src/stores/auth.ts` | Session state machine and the organizational context |

---

## 6. Open Items

| # | Severity | Item | Impact | Recommended action |
|---|---|---|---|---|
| 1 | **Medium** | `SystemSettingsView` presents an editable settings form that persists nothing and shows a success toast. There is no `system_settings` table and no settings API. | An administrator believes a configuration was applied when nothing changed. | Either implement the settings API and table, or replace the screen with an explicit "not yet configurable" notice. The target catalogue is specified in `docs/CONFIGURATION_CATALOG.md` §3. |
| 2 | **Medium** | `DataImportsExportsView` is a static screen with no import or export operation. | Advertised bulk data operations do not exist. | Implement against the repository service, or remove the surface from navigation. |
| 3 | **Low** | The navigation catalog and the static fallback are maintained as two parallel declarations. | A future divergence is possible. | Mitigated today by `NavigationPermissionContractTest`; a single generated source would remove the duplication entirely. |
| 4 | **Low** | Assignment participant removal is authorized by unit but records no audit event. | The audit trail is incomplete for that operation. | Add an `AuditEvent` write to `AssignmentService::removeParticipant`. |
| 5 | **Low** | No continuous integration workflow is defined. | Regression detection depends on a local run. | Add a GitHub Actions workflow running the four commands in §1. |
| 6 | **Low** | The active unit is stored in the session, so it is shared between the browser tabs of one session. | Switching in one tab changes the context of the other. | Accepted. A per-tab preference would still have to be validated by the server, so it buys little. |
| 7 | **Informational** | `MAIL_MAILER=log` is the default in `.env.example`. | Recovery and confirmation links are written to the log instead of being delivered. | Set a real transport for any deployment where recovery must work. See `docs/CONFIGURATION_CATALOG.md` §4. |

---

## 7. Environment Notes

| Item | Detail |
|---|---|
| PHP | 8.5.0 (Herd Lite) |
| Composer | 2.8.12 |
| Node.js | 24.22.0 |
| npm | 11.19.1 |
| Database | MySQL 8.4, InnoDB, `utf8mb4_0900_as_cs` |
| Test database | `wollo_dms_test` (refreshed per suite) |
| Staging data inspected | 16 organizational units, 30 memberships, 14 authorization scopes, 30 role grants, 40 direct grants |
| Remote | `https://github.com/ATPLC/DMS.git` — **not reachable from this environment** (private repository, no cached credentials, no `gh` CLI). The push must be performed from an authenticated machine. |

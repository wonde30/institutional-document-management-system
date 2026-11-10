# Build Status

**System:** Wollo University Administrative and Document Management System (WU-DMS)
**Verification run:** 2026-11-10
**Baseline commit:** `b817d36` (single consolidated root commit)

---

## 1. Verification Scorecard

| Check | Command | Result | Evidence |
|---|---|---|---|
| Backend test suite | `php artisan test` | **PASS** | 291 tests, 291 passed, 1886 assertions, 119.9 s |
| Frontend test suite | `npx vitest run` | **PASS** | 22 files, 120 tests, 120 passed, 4.8 s |
| Frontend type check | `npx vue-tsc -b --noEmit` | **PASS** | exit code 0, no diagnostics |
| Production build | `npx vite build` | **PASS** | exit code 0, `✓ built in 637ms`, 161.71 kB entry chunk |
| Dependency advisories | `composer audit` | **PASS** | "No security vulnerability advisories found." |
| Manifest validity | `composer validate --no-check-publish` | **PASS** | "./composer.json is valid" |
| Lockfile synchronisation | `npm ci --dry-run` | **PASS** | "up to date in 502ms" |

No check was skipped, stubbed or reported from a cached result. Every figure
above is the output of a command executed in this environment against the
working tree as it now stands.

---

## 2. Test Inventory

### 2.1 Backend (PHPUnit / Pest) — 291 tests

| Suite | Tests | Purpose |
|---|---|---|
| `AuthorizationBoundaryEnforcementTest` | 11 | Record-level boundary enforcement across document, movement, assignment, extraction, signature and accreditation families |
| `NavigationPermissionContractTest` | 4 | Four-way agreement between the API contract, the dynamic navigation catalog, the front-end router and the static fallback catalog; registry integrity |
| `AccountRecoveryTest` | 9 | Recovery delivery, non-enumeration, session termination, address confirmation |
| `SecurityAndAccessControlAuditTest` | 6 | Cross-department access, anti-self-approval, confidentiality |
| `SecurityPenetrationTest` | 7 | Session invalidation, account lockout, replay resistance |
| Remaining 20 suites | 254 | Workflow, correspondence, repository, archive, PKI, disaster recovery, schema invariants, scheduling, transactions |

### 2.2 Frontend (Vitest) — 120 tests

| Suite | Tests | Purpose |
|---|---|---|
| `authSessionLifecycle.spec.ts` | 9 | 401 clears the in-memory session, 403 preserves it, route guards admit and refuse correctly, unauthenticated redirect preserves the target |
| `accountRecovery.spec.ts` | 6 | Recovery request reaches the API, failure is surfaced instead of claiming success, confirmation consumes the link |
| `DocumentDetailView.spec.ts` | 4 | Document detail rendering and action states |
| `AppSidebar.spec.ts` | 2 | Collapsible navigation |
| Remaining 17 suites | 99 | Component, store and composable behaviour |

---

## 3. Defects Found and Closed in This Cycle

Every item below was discovered by inspection of the running code, reproduced,
and closed with a regression test.

| # | Severity | Defect | Root cause | Closure |
|---|---|---|---|---|
| 1 | **High** | `POST /extractions/{id}/verify` allowed a caller with a global `documents.update` grant to rewrite the verified OCR text of any department's document. The nested sibling route enforced ownership; the flat alias did not. | The middleware resolved record ownership by path substring and had no branch for the extraction family, so the request fell through to a capability-only check. | `RouteScopeResolver` resolves the owning document; `AuthorizationBoundaryEnforcementTest` asserts both routes enforce the same boundary |
| 2 | **High** | `POST /movements/{id}/acknowledge` allowed the **originating** unit to issue a custody receipt for its own outbound transfer. | No branch for the movement family; the sibling correspondence route resolved `to_unit_id` but the direct route did not. | Resolver binds the movement to `to_unit_id`; test asserts the originating unit is refused and the receiving unit succeeds |
| 3 | **High** | `POST /assignments/{id}/participants`, `/events` and `/responses` performed no ownership check; the controller and service contain no authorization logic at all. | The assignment family had no resolution branch. | Resolver binds the directive to `assigned_unit_id`; test asserts refusal and absence of side effects |
| 4 | **High** | `POST /workflows/tasks/{id}/reassign` was unreachable for **every** account, including the super administrator. | The route required `workflows.manage`, which is not in the permission registry and therefore cannot be granted to any role. | Corrected to the registered capability `workflow.reassign`; `NavigationPermissionContractTest` now fails the build if any route capability is unregistered |
| 5 | **Medium** | The capability decision and the published capability list used different scope rules, so the client could advertise authority the server would refuse — and a subtree grant was invisible for descendant units. | `hasPermission()` and `getEffectivePermissionCodes()` each implemented their own scope filter. | Both now delegate to one `scopeAppliesToUnit()` implementation; test asserts they agree |
| 6 | **Medium** | The account recovery, password reset and address confirmation screens reported success without contacting the server. `SystemSettingsView` and `DataImportsExportsView` are static screens; the settings screen persists nothing and there is no `system_settings` table. | Placeholder user interface. | Recovery and verification are now real API-backed flows; the settings and import/export screens remain open (§5) |
| 7 | **Medium** | A 401 response cleared `localStorage` but left the in-memory Pinia session intact, so the application shell kept rendering an authenticated workspace for a dead session. | The response interceptor had no access to the store. | `sessionEvents.ts` bridges the client to the store; `authSessionLifecycle.spec.ts` asserts the state is cleared |
| 8 | **Medium** | The session bootstrap was marked complete **before** it ran, so a transient failure stranded the application on an empty session until a manual reload. | The flag was set outside the `try` block. | Bootstrap is retried while the session state is `unknown`; the duplicate flag was removed in favour of the store's `sessionState` |
| 9 | **Medium** | Route metadata declared capabilities that the serving API route did not require (`/staff`, `/projects`, `/signatures`, `/admin/operations`, `/admin/settings`, `/search`), so the client admitted or refused surfaces inconsistently with the server. | Four independent permission declarations with no contract. | All four declarations aligned; `NavigationPermissionContractTest` asserts their agreement |
| 10 | **Medium** | The dynamic navigation catalog omitted the Templates surface entirely, so it disappeared from the menu once the dynamic catalog loaded, while remaining visible in the static fallback. | The catalog was not derived from the API contract. | Templates added; the static fallback and the dynamic catalog are now asserted to agree |
| 11 | **Low** | Validation failures were returned without the `success` discriminator used by every other API response. | The framework's default validation rendering was used. | A validation renderer in `bootstrap/app.php` emits the documented envelope; the change is additive, so existing assertions are unaffected |
| 12 | **Low** | A document that did not exist passed the authorization middleware and was rejected only by the controller. | The middleware returned "allow" for an unresolved record. | The middleware now fails closed with HTTP 404 |

### 3.1 Dependency remediation

| Package | From | To | Reason |
|---|---|---|---|
| `symfony/http-foundation` | 8.1.9 | 8.1.11 | CVE-2026-118514, PKSA-pr6q-2w5s-kzzf, PKSA-nxjs-s6hn-vb6m, PKSA-b2gg-vk1g-6d5q |
| `symfony/http-kernel` | 8.1.9 | 8.1.11 | CVE-2026-123339, GHSA-wg5q-m2ww-f3c9 |
| `symfony/routing` | 8.1.9 | 8.1.11 | CVE-2026-118516 |

`composer audit` now reports **no** advisories.

---

## 4. Architecture Delivered

| Component | Purpose |
|---|---|
| `app/Services/Authorization/RouteScopeResolver.php` | Resolves the record behind a request to its owning document or unit |
| `app/Services/Authorization/RouteAuthorizationTarget.php` | Immutable description of the resolved target and the policy it selects |
| `app/Http/Middleware/CheckBaselinePermission.php` | The single authoritative authorization gate |
| `app/Models/User.php` | The single scope-matching implementation (`scopeAppliesToUnit`) used by both the decision and the published capability set |
| `bootstrap/app.php` | Middleware priority, validation error envelope |
| `client/src/api/sessionEvents.ts` | Decouples the HTTP client from the authentication store; carries session termination and authorization denial |
| `client/src/stores/auth.ts` | Session state machine (`unknown` / `verified` / `unauthenticated`) |

---

## 5. Open Items

| # | Severity | Item | Impact | Recommended action |
|---|---|---|---|---|
| 1 | **Medium** | `SystemSettingsView` presents an editable settings form that persists nothing and shows a success toast. There is no `system_settings` table and no settings API. | An administrator believes a configuration was applied when nothing changed. | Either implement the settings API and table, or replace the screen with an explicit "not yet configurable" notice. The target catalogue is specified in `docs/CONFIGURATION_CATALOG.md` §3. |
| 2 | **Medium** | `DataImportsExportsView` is a static screen with no import or export operation. | Advertised bulk data operations do not exist. | Implement against the repository service, or remove the surface from navigation. |
| 3 | **Medium** | The client capability set is the union across scopes because the interface has no active-unit switcher. | The client may present an action the server will refuse for a record in another unit. | Introduce an active-unit switcher and publish `permissions_scoped`. See `docs/AUTHORIZATION_MODEL.md` §6.2. |
| 4 | **Low** | The navigation catalog and the static fallback are maintained as two parallel declarations. | A future divergence is possible. | Mitigated today by `NavigationPermissionContractTest`; a single generated source would remove the duplication entirely. |
| 5 | **Low** | Assignment participant removal is authorized by unit but records no audit event. | The audit trail is incomplete for that operation. | Add an `AuditEvent` write to `AssignmentService::removeParticipant`. |
| 6 | **Low** | No continuous integration workflow is defined. | Regression detection depends on a local run. | Add a GitHub Actions workflow running the four commands in §1. |
| 7 | **Informational** | `MAIL_MAILER=log` is the default in `.env.example`. | Recovery and confirmation links are written to the log instead of being delivered. | Set a real transport for any deployment where recovery must work. See `docs/CONFIGURATION_CATALOG.md` §4. |

---

## 6. Environment Notes

| Item | Detail |
|---|---|
| PHP | 8.5.0 (Herd Lite) |
| Composer | 2.8.12 |
| Node.js | 24.22.0 |
| npm | 11.19.1 |
| Database | MySQL 8.4, InnoDB, `utf8mb4_0900_as_cs` |
| Test database | `wollo_dms_test` (refreshed per suite) |
| Remote | `https://github.com/ATPLC/DMS.git` — **not reachable from this environment** (private repository, no cached credentials, no `gh` CLI). The push must be performed from an authenticated machine. |

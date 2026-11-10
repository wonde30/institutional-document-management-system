# Initial Full-Stack System Audit & Architectural Baseline

**Project:** Wollo University Administrative and Document Management System (WU-DMS)  
**Repository:** `https://github.com/ATPLC/DMS.git`  
**Evaluation Standard:** Enterprise Production Engineering & Public Institutional Security  
**Audit Date:** November 2, 2026  
**Auditor:** Principal-Level Senior Full-Stack Engineer, Software Architect & Security Lead  

---

## Executive Summary

A comprehensive full-stack architectural discovery and verification audit was conducted on the Wollo University Administrative and Document Management System (WU-DMS). The system is a single-institution, enterprise-grade platform built specifically for Wollo University to manage official records, multi-departmental correspondence, workflow automation, digital archives, and statutory accreditation dossiers.

The application adheres to modern institutional engineering standards with clear separation of concerns, robust optimistic concurrency control, and multi-tiered scoped dynamic Role-Based Access Control (RBAC). Both the Laravel 13 backend and Vue 3 frontend exhibit high test coverage and strict type definitions.

This audit establishes the concrete verified baseline across all nine engineering evaluation vectors, details an authoritative issue register (P0–P3), and outlines prioritized remediation strategies.

---

## 1. Actual System Architecture

```
DMS/
├── client/                     # Frontend Single Page Application (Vue 3, TypeScript 5.8+, Vite 8, Pinia 4, Tailwind CSS 4)
│   ├── public/                 # Static assets, SVG symbols, institutional icons
│   ├── src/
│   │   ├── api/                # Sanctum SPA Axios client & domain API services
│   │   ├── assets/             # Global CSS and institutional typography tokens
│   │   ├── components/         # Reusable UI, data display, navigation, and auth gates
│   │   ├── composables/        # useDarkMode, useDebounce, useToast
│   │   ├── config/             # Navigation taxonomy and icon resolvers
│   │   ├── directives/         # v-permission element-level authorization directive
│   │   ├── layouts/            # AppLayout with sidebar, context bar, and header
│   │   ├── router/             # Vue Router with permission-driven navigation guards
│   │   ├── stores/             # Pinia stores (auth, metadata, notifications)
│   │   ├── types/              # Comprehensive TypeScript domain interfaces (752 lines)
│   │   ├── utils/              # Formatters, file downloaders, badge styling
│   │   └── views/              # 25+ production domain views
│   └── tests/                  # 19 Vitest component & integration test suites
│
├── server/                     # Backend REST API Service (Laravel 13, PHP 8.4+, MySQL 8.4 InnoDB)
│   ├── app/
│   │   ├── Console/Commands/   # Backup, restore, query profiling, DR drills
│   │   ├── Http/Controllers/   # REST API V1 Controllers (Document, Correspondence, RBAC, etc.)
│   │   ├── Http/Middleware/    # ValidateAuthorizationVersion, EnsureAccountIsActive, CheckBaselinePermission
│   │   ├── Http/Resources/     # API Resource transformers (User, Document, Role, OrganizationUnit)
│   │   ├── Models/             # 70+ Eloquent models mapped to relational schema
│   │   ├── Providers/          # AppServiceProvider (Rate limiting, gate overrides, Sanctum mapping)
│   │   └── Services/           # AuditService, PKI cryptographic engine, Backup & DR coordinators
│   ├── config/                 # Laravel configuration (auth, sanctum, session, cors, database)
│   ├── database/
│   │   ├── migrations/         # 14 Domain-Driven Design migration clusters (118 tables mapped)
│   │   └── seeders/            # Baseline taxonomy and reference seeders
│   ├── routes/                 # api.php, web.php, console.php
│   └── tests/                  # 28 PHPUnit / Pest Feature and Unit test suites
│
├── documentation/engineering/  # Authoritative system audits and engineering specifications
├── Project Proposal for WU.pdf # Official University institutional specifications
└── README.md                   # Repository architectural guide
```

---

## 2. Subsystem & Module Inventory

| Subsystem | Core Eloquent Models | Primary Controller(s) | Operational Scope |
| :--- | :--- | :--- | :--- |
| **Identity & Organization** | `OrganizationUnit`, `UnitType`, `User`, `OrganizationMembership`, `PrimaryMembership` | `OrganizationUnitController`, `UserController` | Hierarchical organizational tree (colleges, faculties, departments, administrative directorates). |
| **Scoped Dynamic RBAC** | `Role`, `Permission`, `AuthorizationScope`, `UserRole`, `UserPermission`, `RolePermission` | `RolePermissionController` | Dynamic roles, permissions, and grants operating at `global`, `subtree`, or `unit` scopes. |
| **Document Repository** | `Document`, `DocumentVersion`, `FileObject`, `VersionFile`, `DocumentCategory`, `ConfidentialityLevel`, `ReferenceSequence` | `DocumentController` | Optimistic locking (`lock_version`), canonical reference generation (`WU/DOC/YYYY/NNNNNN`), SHA-256 integrity verification. |
| **Correspondence Registry** | `Correspondence`, `CorrespondenceType`, `Priority`, `DocumentMovement`, `MovementReceipt`, `DocumentAssignment`, `CorrespondenceDispatch` | `CorrespondenceController` | Dual-path registry (incoming external letters, inter-office memoranda, outgoing dispatches) with workflow-scoped visibility. |
| **Workflow State Machine** | `WorkflowDefinition`, `WorkflowVersion`, `WorkflowState`, `WorkflowTransition`, `WorkflowInstance`, `WorkflowTask`, `WorkflowTaskDecision` | `WorkflowController` | Directed state transitions, task assignments, and review decisions. |
| **Digital Archive & Retention** | `ArchiveCollection`, `RetentionPolicy`, `RetentionPolicyVersion`, `ArchiveEntry`, `LegalHold`, `LegalHoldDocument` | `ArchiveController` | Preserves archival records; enforces legal holds and statutory retention rules under Proclamation No. 1152/2019. |
| **HERQA Quality Assurance** | `AccreditationFramework`, `AccreditationStandard`, `AccreditationCycle`, `AccreditationEvidence`, `AccreditationReview` | `AccreditationController` | Accreditation cycles, evidence criteria scoring, and dossier compilation aligned with HERQA standards. |
| **PKI Digital Trust & Signatures** | `SigningIdentity`, `StampDefinition`, `DigitalSignature`, `SignatureRequest`, `SignatureVerification` | `InstitutionalModuleController` | OpenSSL X.509 cryptographic signing, RFC 3161 timestamps, PKCS#7 ByteRange envelope verification. |
| **Specialized Operations** | `ResearchRecord`, `StaffProfile`, `PhysicalOriginal`, `Project`, `OfficeProcedureVersion` | `InstitutionalModuleController` | Finalized research publications, staff HR physical scan verification, projects/innovation, and standard operating procedures. |
| **Security Audit Trail** | `AuditEvent`, `NotificationEvent`, `NotificationDelivery`, `UserNotification` | `AuditLogController`, `NotificationController` | Append-only security audit log recording actor identity, IP, action, target, and payload diffs. |

---

## 3. Frontend / Backend Integration & Contract Consistency

### 3.1 HTTP Client & Transport Security
* **Client Instance:** Configured in [client/src/api/client.ts](../../client/src/api/client.ts).
  * `baseURL: '/api/v1'`
  * `timeout: 20000` (20 seconds)
  * `withCredentials: true` (ensures stateful session cookies and XSRF cookies travel with every cross-origin request)
  * Default headers: `Accept: 'application/json'`, `X-Requested-With: 'XMLHttpRequest'`.
* **CSRF Double-Submit Handshake:**
  * Client automatically invokes `GET /sanctum/csrf-cookie` prior to executing any mutating HTTP request (`POST`, `PUT`, `PATCH`, `DELETE`).
  * Deduplicated with a module-level initialization promise.
  * Automatic retry on HTTP `419 Authentication Timeout / CSRF Mismatch` via single-retry flag (`_retry`).

### 3.2 Response Packaging
* Standard REST envelope:
  ```typescript
  export interface ApiResponse<T> {
    success: boolean
    message?: string
    data: T
    token?: string
    token_type?: string
  }

  export interface PaginatedResponse<T> {
    success: boolean
    data: T[]
    meta: {
      current_page: number
      last_page: number
      per_page: number
      total: number
      from?: number
      to?: number
    }
  }
  ```
* Standard status code mappings:
  * `200 OK`: Successful read or update.
  * `201 Created`: Resource successfully created.
  * `401 Unauthorized`: Missing or invalid session.
  * `403 Forbidden`: Authenticated user lacks required permission or organizational scope.
  * `404 Not Found`: Target record does not exist.
  * `409 Conflict`: Optimistic locking collision (`lock_version` mismatch).
  * `422 Unprocessable Content`: Validation failure or statutory hold violation.

---

## 4. Authentication & Session Security Flow

### 4.1 Lifecycle Verification
1. **CSRF Handshake:** Client requests `/sanctum/csrf-cookie`. Laravel sets encrypted `XSRF-TOKEN` cookie and `laravel_session` cookie.
2. **Credential Submission:** Client submits email and password to `POST /api/v1/auth/login`.
3. **Identity Verification & Suspension Check:** Server verifies bcrypt hash. If account status is not `active`, the attempt is recorded in `audit_events` and blocked with `403 Forbidden`.
4. **Session Binding & Regeneration:**
   * Server executes `Auth::guard('web')->login($user)`.
   * Calls `$request->session()->regenerate()`.
   * Binds current authorization version: `$request->session()->put('authz_version', (int) $user->authz_version)`.
5. **Instant Session Invalidation (Privilege Bumping):**
   * Middleware [ValidateAuthorizationVersion.php](../../server/app/Http/Middleware/ValidateAuthorizationVersion.php) inspects every incoming request.
   * Compares session `authz_version` with database `user->authz_version`.
   * When an administrator reassigns roles, modifies permissions, transfers departments, or changes user status, `$user->increment('authz_version')` is triggered.
   * The very next request from any active session with the old version triggers an immediate session flush, audits the revocation, and returns `401 Unauthorized`.
6. **Logout:** Server logs out guard, invalidates session, regenerates CSRF token, purges user sessions from the `sessions` table, and client purges auth store state.

---

## 5. Dynamic RBAC Architecture

### 5.1 Authorization Scopes
The system implements a 3-dimensional authorization matrix:

```
[User] ──(assigned to)──> [Role] ──(attached to)──> [Permission]
   │                         │
   └─────────────┬───────────┘
                 ▼
      [AuthorizationScope]
        ├── kind: 'global'   (Universal authority across Wollo University)
        ├── kind: 'subtree'  (Authority over target unit + all recursive descendants)
        └── kind: 'unit'     (Authority strictly isolated to a single department/office)
```

### 5.2 Server-Side Permission Resolution
The authoritative resolution algorithm in [User.php](../../server/app/Models/User.php#L168-L215) enforces:
1. **Status Barrier:** Inactive accounts fail all checks immediately.
2. **Direct Grants:** Direct user permissions in active scope (`user_permissions` table) taking precedence.
3. **Role-Inherited Permissions:** Active roles mapped to active permissions in active scope (`user_roles` -> `role_permissions`).
4. **Time-Bound Enforcement:** Grants with `expires_at <= now()` are excluded.
5. **Hierarchy Resolution:** For `subtree` scopes, the target unit's ancestors are calculated using recursive parent-tree traversal; if the user holds a subtree grant on any ancestor, access is granted.

### 5.3 Defense in Depth & IDOR Mitigation
* **Route Middleware:** [CheckBaselinePermission.php](../../server/app/Http/Middleware/CheckBaselinePermission.php) intercepts every route. It extracts the resource ID (document, correspondence, research, workflow task) or creation payload unit, resolves its `owner_unit_id`, and verifies that the caller holds the permission in that exact unit scope.
* **Controller Scoping:** Controllers do not rely solely on middleware; query endpoints call `$user->getPermittedUnitIds($permission)` and inject `WHERE owner_unit_id IN (...)` to prevent data leakage.
* **Payload Tampering Defense:** If a client provides a unit in the `X-Organization-Unit-Id` header that contradicts the body payload unit, the middleware rejects the request with `403 Forbidden`.

---

## 6. Vue Router & Navigation Architecture

### 6.1 Route Groups & Protection
* **Guest Routes (`guestOnly: true`):** `/login`, `/forgot-password`, `/reset-password`. Authenticated users navigating here are redirected to `/dashboard`.
* **Public Routes (`isPublic: true`):** `/`, `/privacy-policy`, `/terms`. Rendered without the sidebar/header layout.
* **Protected Routes (`requiresAuth: true`):** All operational modules. Protected by the global `beforeEach` navigation guard:
  ```typescript
  // client/src/router/index.ts
  if (requiresAuth && !authStore.isAuthenticated) {
    return next({ name: 'login', query: { redirect: to.fullPath } })
  }
  for (const record of to.matched) {
    if (record.meta.permission && !authStore.hasPermission(record.meta.permission)) {
      return next({ name: 'forbidden' })
    }
  }
  ```
* **Dynamic Sidebar Navigation:** [AppSidebar.vue](../../client/src/components/navigation/AppSidebar.vue) filters navigation sections and items using `authStore.hasPermission()`. If a user lacks permission for all items in a category, the category header is omitted from the DOM.

---

## 7. Database Integrity & Performance Findings

* **Schema Size:** 118 application tables + 1 `migrations` tracking table in MySQL 8.4 Community Server.
* **Storage Engine:** 100% InnoDB engine with strict foreign key constraints.
* **Character Collation:** `utf8mb4_0900_as_cs` (case-sensitive and accent-sensitive collation guaranteeing exact reference code matching).
* **Composite Indexes:** Key operational tables have composite indexes aligned with primary filter vectors:
  * `documents`: `(owner_unit_id, registered_at)`, `(lifecycle, category_id)`, `(reference_number)`
  * `correspondences`: `(type_id, registered_at)`, `(external_reference)`
  * `document_movements`: `(to_unit_id, status)`
  * `audit_events`: `(created_at, action)`
* **ACID Transactions:** All multi-table updates (e.g. document intake with file object, version, and reference counter increment) are wrapped in explicit `DB::transaction(...)` blocks with `SELECT ... FOR UPDATE` row locks on sequence counters.

---

## 8. Automated Testing & Verification Baseline

### 8.1 Backend Test Results (PHPUnit / Pest)
* **Command:** `php artisan test`
* **Result:** **163 / 163 tests passed (100%)**
* **Assertions:** 990 assertions
* **Duration:** 69.17 seconds
* **Failures / Errors:** 0
* **Key Test Suites Executed:**
  * `AuthTest`: Session cookie authentication, CSRF validation, password change session sync.
  * `SanctumCsrfProtectionTest`: Cross-origin bar, token mismatch handling, cookie creation.
  * `SecurityPenetrationTest`: 20 hostile penetration scenarios (IDOR, scope escalation, unit spoofing, illegal shredding, session replay).
  * `ConcurrencyAndDeadlockTest`: Optimistic lock collisions, atomic sequence races, duplicate dispatches, deadlock detection.
  * `DatabaseOptimizationTest`: Index coverage verification, filesort elimination.

### 8.2 Frontend Test Results (Vitest)
* **Command:** `npm test`
* **Result:** **99 / 99 tests passed (100%)**
* **Test Files:** 19 passed out of 19
* **Duration:** 5.62 seconds
* **TypeScript Compilation:** `npx vue-tsc -b` passed with **0 errors**.
* **Production Build:** `npm run build` generated production assets in **708 ms**.

---

## 9. Comprehensive Issue Register

| ID | Sev | Component | Defect Description | Impact / Security Risk | Recommended Remediation |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **F-01** | **P1** | Frontend Auth Store | `authStore` initializes user state by parsing `localStorage.getItem('user')` on app load. | **Client-Side State Tampering:** A user could modify their cached roles or permissions in browser devtools. While backend rejects unauthorized API calls, the frontend UI could display unauthorized action buttons until `/auth/me` returns. | Hydrate state strictly from `null`. Let the router guard trigger `fetchUser()` to establish genuine server-validated state. |
| **F-02** | **P2** | Navigation Layout | The `AppLayout.vue` "Institutional View Switcher" dropdown displays all routes including administrative paths to all users. | **Information Disclosure:** Non-admin users see administrative options (e.g. `/admin/users`, `/admin/roles`). Though protected by router guards, it exposes internal URLs. | Filter `screenOptions` by `authStore.hasPermission(...)` matching the route's permission. |
| **F-03** | **P2** | Permission Directive | `v-permission` directive implements `mounted` but lacks an `updated` lifecycle hook. | **Reactive UI Staleness:** If a user's permissions change reactively without a full page reload, elements with `v-permission` do not re-evaluate. | Add the `updated` hook to `vPermission` in `directives/permission.ts` to re-check authorization on VNode updates. |
| **F-04** | **P2** | Router Types | `isPublic` meta property is used in `App.vue` and route definitions, but is missing from `RouteMeta` interface augmentation in `types/index.ts`. | **Type Safety Gap:** `route.meta.isPublic` is typed as `unknown`, bypassing TypeScript strict compile checking. | Add `isPublic?: boolean` to the `RouteMeta` interface in `types/index.ts`. |
| **F-05** | **P2** | Axios Interceptor | The 401 response interceptor in `api/client.ts` uses `window.location.href = '/login?redirect=...'`. | **State Loss & Concurrent Race:** Forces a full browser page reload, destroying SPA state. Concurrent 401 responses can trigger multiple redundant redirects. | Use Vue Router programmatic navigation (`router.push`) and deduplicate redirect execution. |
| **F-06** | **P2** | API Client Types | Several API service methods in `api/client.ts` (`navigationApi.get`, `correspondenceApi.dispatch`, `institutionalApi.*`) return `ApiResponse<any>`. | **Type Safety Loss:** Weakens frontend type checking and intellisense for domain responses. | Replace all `any` returns in `api/client.ts` with explicit domain interfaces from `types/index.ts`. |
| **F-07** | **P3** | Router Guard | `initialAuthChecked` flag in `router/index.ts` is not reset when `authStore.logout()` is called. | **Session Re-check Delay:** If a user logs out and logs in as a different user within the same tab, `fetchUser()` is not re-triggered by the guard on navigation. | Export a reset function or reset `initialAuthChecked = false` upon logout. |
| **F-08** | **P3** | Auth Store | Vestigial `token` ref in `authStore` is always initialized to `null` and never holds a token. | **Code Clutter:** Misleading remnant from token-based auth. | Remove the unused `token` ref from `authStore`. |
| **F-09** | **P3** | Notification Store | Catch blocks in `stores/notifications.ts` are empty `{}` without error reporting. | **Silent Failures:** Network errors while fetching notifications fail silently without toast or retry. | Add error handling with toast notifications. |
| **F-10** | **P3** | App Layout UI | User and notification dropdowns in `AppLayout.vue` do not close on click-outside. | **Minor UX Inconvenience:** Menus stay open until a route changes or the toggle button is clicked again. | Implement click-outside handler using `@vueuse/core` (`onClickOutside`). |

---

## 10. Prioritized Remediation Plan

```
┌────────────────────────────────────────────────────────┐
│ Phase 1: High-Priority Security & State Hardening (P1) │
├────────────────────────────────────────────────────────┤
│ 1. Eliminate localStorage user/permission hydration   │
│    in auth.ts. Always fetch fresh profile from API.    │
└────────────────────────────────────────────────────────┘
                           │
                           ▼
┌────────────────────────────────────────────────────────┐
│ Phase 2: Authorization & Type Safety Refinement (P2)   │
├────────────────────────────────────────────────────────┤
│ 2. Add 'updated' hook to v-permission directive.      │
│ 3. Filter Institutional View Switcher by permissions.  │
│ 4. Augment RouteMeta with 'isPublic?: boolean'.       │
│ 5. Upgrade 401 interceptor to use Vue Router.         │
│ 6. Replace 'any' return types in api/client.ts.        │
└────────────────────────────────────────────────────────┘
                           │
                           ▼
┌────────────────────────────────────────────────────────┐
│ Phase 3: Cleanup & Robustness Improvements (P3)        │
├────────────────────────────────────────────────────────┤
│ 7. Reset initialAuthChecked flag on logout.           │
│ 8. Purge vestigial token references in auth store.    │
│ 9. Add click-outside behavior to AppLayout dropdowns.  │
│ 10. Add defensive error toast to notification store.   │
└────────────────────────────────────────────────────────┘
                           │
                           ▼
┌────────────────────────────────────────────────────────┐
│ Phase 4: Full Regression Testing & Verification        │
├────────────────────────────────────────────────────────┤
│ 11. Execute full backend Pest suite (163 tests).       │
│ 12. Execute full frontend Vitest suite (99 tests).     │
│ 13. Execute TypeScript compilation & production build. │
└────────────────────────────────────────────────────────┘
```

---

## 11. Conclusion & Baseline Status

The DMS application possesses an exceptionally strong architectural foundation:
- Backend security boundaries, optimistic locking, and scoped RBAC are mathematically sound and thoroughly verified by 163 backend tests.
- Database schema matches the 118-table relational model with zero missing tables, zero multi-tenancy leakage, and verified composite indexes.
- Identified defects are focused on frontend client-side state hardening and type safety consistency (none require breaking database or API changes).

Proceeding to Phase Two (Authentication & Session Security) and targeted defect remediation following the established issue register.

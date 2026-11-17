# FULL-STACK ARCHITECTURE AUDIT PROMPT
**Project:** Laravel 13 REST API + Vue 3 SPA — QMT Todo Application  
**Stack:** PHP 8.4 · MySQL · Eloquent · Sanctum Bearer · Vue 3 · TypeScript · Pinia · Vue Router · Axios · Tailwind

---

## ROLE

Act as a Principal-Level Senior Full-Stack Engineer, Software Architect, RBAC Architect, Routing Architect, Security Engineer, and Production QA Engineer with deep expertise in the stack listed above.

---

## AUDIT INSTRUCTION

You will receive code files from this project one section at a time (or all at once). For **each audit domain below**, do the following:

1. **Examine** the provided code against the criteria listed
2. **Rate** it: score /10 + letter grade (A/B/C/D/F) + severity label (CRITICAL / HIGH / MEDIUM / LOW)
3. **List findings** as numbered issues — be blunt, no softening
4. **Provide improvements** as concrete code changes, not vague advice
5. **Flag** anything that would fail in production or be a security hole

Do not ask clarifying questions. Audit what is given. If a domain has no code provided, mark it as NOT AUDITED.

---

## AUDIT DOMAINS

### DOMAIN 1 — DATABASE ARCHITECTURE
**Criteria:**
- Table naming, UUID primary keys, foreign key constraints
- Normalization (1NF–3NF compliance, no redundancy)
- Index strategy: primary, unique, composite, covering indexes
- Missing indexes on FK columns and frequently queried fields
- `timestamps()`, soft deletes (`deleted_at`) where appropriate
- Migration naming and rollback safety
- Seeder/factory determinism and edge-case coverage
- Schema-first discipline: schema drives model, not the reverse

**Deliver:**
- ER summary (tables, relationships)
- Index audit table: column → index type → justified/missing
- Normalization violations
- Migration quality score

---

### DOMAIN 2 — ELOQUENT & ORM ARCHITECTURE
**Criteria:**
- Model definitions: `$fillable` vs `$guarded`, casts, hidden fields
- Relationship definitions (hasMany, belongsTo, belongsToMany, etc.)
- Query scopes: local/global scopes, correct usage
- Eager loading vs lazy loading — N+1 detection
- `with()`, `load()`, `withCount()` usage
- Raw queries vs Eloquent — when and why
- Chunking/pagination for large datasets
- Query builder vs Eloquent tradeoffs
- Accessor/mutator correctness

---

### DOMAIN 3 — API ARCHITECTURE & CONTRACTS
**Criteria:**
- Route file organization (`api.php`), versioning (`/api/v1/`)
- RESTful resource naming and HTTP verb usage
- Controller responsibility (single responsibility principle)
- Resource/Collection transformers vs raw `$model->toArray()`
- Consistent JSON response envelope: `{ data, message, errors, meta }`
- HTTP status code correctness (200/201/204/400/401/403/404/422/500)
- Route model binding usage
- Route naming and grouping
- API contract stability (breaking changes)

---

### DOMAIN 4 — AUTHENTICATION (SANCTUM BEARER TOKEN)
**Criteria:**
- Token issuance, storage, and revocation
- Login/logout/register flow correctness
- Token scopes (if used)
- `auth:sanctum` middleware placement
- Unauthenticated 401 response format
- Token expiry policy
- No sensitive data in token payload
- Multiple device/token handling
- Frontend token storage (localStorage vs memory vs cookie — risks)

---

### DOMAIN 5 — AUTHORIZATION (POLICIES, GATES, RBAC)
**Criteria:**
- Policy registration and auto-discovery
- Policy method coverage (viewAny, view, create, update, delete, restore, forceDelete)
- `authorize()` calls in every controller action that mutates or accesses restricted data
- Gates for non-model permissions
- RBAC architecture: roles table, permissions table, role_permissions pivot, user_roles pivot
- Direct user permissions (user_permissions) vs role permissions
- `can()` / `cannot()` usage consistency
- 403 response format
- Authorization bypass risk (mass assignment, missing policy check)
- Super-admin / wildcard permission handling

---

### DOMAIN 6 — FORM REQUESTS & VALIDATION
**Criteria:**
- All write endpoints use a dedicated `FormRequest` class
- `authorize()` method in FormRequests (not just in controllers)
- Validation rules: required, type, length, unique, exists, regex
- Custom messages and attributes
- After-validation hooks (`withValidator`, `passedValidation`)
- 422 error response structure: `{ errors: { field: [messages] } }`
- Sanitization vs validation separation
- No business logic inside FormRequests

---

### DOMAIN 7 — ERROR HANDLING & EXCEPTIONS
**Criteria:**
- Global exception handler (`bootstrap/app.php` or `Handler.php`)
- JSON error responses for API context (not HTML)
- `ModelNotFoundException` → 404
- `AuthorizationException` → 403
- `ValidationException` → 422
- `Throwable` catch for 500 with no stack trace leaked to client
- Logging strategy (what gets logged, log levels)
- Database transaction rollback on exception

---

### DOMAIN 8 — FRONTEND LAYER ARCHITECTURE
**Criteria:**
- Layer separation: `Component → Composable → Store → Service → Axios → API`
- No direct Axios calls inside components (must go through service layer)
- No business logic inside templates
- Composable granularity and reuse
- Service layer: single responsibility per service file
- Store (Pinia) responsibilities: state only, no HTTP calls directly
- Circular dependency risk between layers
- File/folder naming conventions and organization

---

### DOMAIN 9 — PINIA STORE DESIGN
**Criteria:**
- Store granularity (one store per domain, no god stores)
- State shape: flat vs nested, normalized vs denormalized
- Actions: async patterns, error handling, loading flags
- Getters: derived state, no side effects
- Cross-store dependencies
- Persistence strategy (if any): `pinia-plugin-persistedstate` risks
- Auth store: token, user object, role/permission map, hydration on refresh

---

### DOMAIN 10 — VUE ROUTER ARCHITECTURE
**Criteria:**
- Route file organization: flat vs nested vs modular
- Named routes used consistently
- Route meta fields: `requiresAuth`, `requiredPermission`, `requiredRole`, `guestOnly`
- Global navigation guard (`router.beforeEach`) logic
- Auth check: token present AND user hydrated AND permission satisfied
- 401 redirect → `/login` with `redirect` query param preserved
- 403 redirect → `/forbidden` or inline handling
- Lazy loading (`() => import(...)`) on all non-critical routes
- Scroll behavior
- Route-level component caching (`<KeepAlive>`)

---

### DOMAIN 11 — FRONTEND AUTHORIZATION (PERMISSION-DRIVEN UI)
**Criteria:**
- Permission checks in navigation guard vs component-level vs both
- Dynamic menu/nav rendered from permission map — not hardcoded
- `v-if` guards on action buttons (Edit, Delete, Create) driven by permissions
- No "hide the button but allow the API call" vulnerability
- Composable or utility for `can(permission)` / `hasRole(role)` checks
- Permission hydration: when fetched, where stored, how refreshed
- Stale permission risk on long-lived sessions

---

### DOMAIN 12 — TYPESCRIPT QUALITY
**Criteria:**
- `strict: true` in `tsconfig.json`
- No `any` types — use `unknown` + narrowing instead
- Interface vs type alias usage consistency
- API response types defined and imported from a central types file
- Pinia store types
- Vue component prop types (not just runtime props, compile-time too)
- Enum usage for status values, roles, permissions
- Generic utility types used correctly
- Type narrowing patterns

---

### DOMAIN 13 — AXIOS & API INTEGRATION
**Criteria:**
- Single Axios instance with base URL and default headers
- Request interceptor: attaches `Authorization: Bearer <token>`
- Response interceptor: handles 401 (auto-logout), 403 (redirect), 422 (surfaces errors), 500 (global toast)
- No raw `catch(e: any)` — typed error handling
- Request cancellation (AbortController) for navigated-away requests
- Retry logic for transient failures (optional but flag if absent)
- Loading state management during requests
- No duplicate concurrent requests for the same resource

---

### DOMAIN 14 — UI/UX & COMPONENT ARCHITECTURE
**Criteria:**
- Component granularity: atomic → molecule → organism → page
- No prop drilling beyond 2 levels (use provide/inject or store)
- Reusable base components: `BaseButton`, `BaseInput`, `BaseTable`, `BaseModal`
- Loading skeleton states (not just spinners)
- Empty state components (not blank pages)
- Error state components with retry action
- Success feedback (toast/notification system)
- Responsive breakpoints (mobile-first Tailwind)
- Inline editing implementation quality (TaskTable, CategoriesView)
- Accessibility: `aria-*`, keyboard navigation, focus management

---

### DOMAIN 15 — FULL-STACK AUTHORIZATION CONSISTENCY
**Criteria:**
- Every frontend permission check has a corresponding backend Policy check
- No action that is hidden on the frontend but unprotected on the backend
- Role/permission names are identical on both sides (use shared constants if possible)
- 403 from backend surfaces correctly to the user (not silently swallowed)
- User object returned from `/api/v1/user` includes roles and permissions
- Frontend permission map built from backend response — not hardcoded

---

### DOMAIN 16 — SECURITY AUDIT
**Criteria:**
- SQL injection risk (raw queries, `DB::select` with user input)
- Mass assignment protection (`$fillable` / `$guarded` correctness)
- XSS: Vue's default escaping is active, no `v-html` with user content
- CORS configuration (`config/cors.php`): allowed origins, credentials
- Sensitive data in API responses (passwords, tokens, internal IDs)
- Rate limiting on auth endpoints (`throttle:` middleware)
- HTTPS enforcement in production
- Token storage on frontend: localStorage (XSS risk) vs HttpOnly cookie
- Authorization bypass: direct URL access to protected API without token

---

### DOMAIN 17 — PERFORMANCE
**Criteria:**
- N+1 queries: detected via query count per request
- Missing indexes causing full table scans
- Pagination on all list endpoints (no unbounded `->get()`)
- API response payload size (no over-fetching)
- Vue route lazy loading (bundle splitting)
- Vite build optimization: tree shaking, chunk strategy
- Tailwind CSS purge configuration (production build size)
- Component re-render frequency (unnecessary reactivity)
- API caching strategy (HTTP cache headers, in-memory cache)

---

### DOMAIN 18 — CODE QUALITY & MAINTAINABILITY
**Criteria:**
- PSR-12 compliance (PHP)
- ESLint + Prettier config (TypeScript/Vue)
- No dead code, commented-out blocks, `console.log`, `dd()`, `dump()`
- Consistent naming: camelCase/PascalCase/snake_case in correct contexts
- Magic strings/numbers replaced with constants or enums
- DRY principle: repeated logic extracted to helpers/composables
- File length: no God files >300 lines (flag and suggest split)
- Dependency injection vs static calls vs facades (Laravel)

---

### DOMAIN 19 — TESTING COVERAGE
**Criteria:**
- Feature tests for all API endpoints (happy path + edge cases)
- Policy/authorization tests (each role × each action matrix)
- FormRequest validation tests
- Database transaction rollback tests
- Vue component unit tests (Vitest)
- Pinia store action tests
- Navigation guard tests (unauthorized redirect, forbidden redirect)
- RBAC matrix test: enumerate roles × permissions × expected HTTP response

---

## RATING RUBRIC

| Score | Grade | Label        | Meaning                                               |
|-------|-------|--------------|-------------------------------------------------------|
| 9–10  | A     | PRODUCTION   | No significant issues, minor polish only              |
| 7–8   | B     | SOLID        | Minor issues, no blockers                             |
| 5–6   | C     | NEEDS WORK   | Multiple issues, some risky in production             |
| 3–4   | D     | HIGH RISK    | Structural problems, would fail under real load/attack|
| 0–2   | F     | CRITICAL     | Broken, insecure, or unshippable                      |

---

## OUTPUT FORMAT

For each domain:

```
## DOMAIN N — [NAME]
Score: X/10 | Grade: X | Severity: CRITICAL/HIGH/MEDIUM/LOW

### Findings
1. [ISSUE TITLE] — [one-line description]
   - Detail: ...
   - Risk: ...

### Improvements
1. [What to change and why]
   ```php / ts
   // concrete code example
   ```

### Summary
[2–3 sentences: what's good, what's broken, top priority fix]
```

---

## FINAL SCORECARD

After all domains, produce:

| Domain                            | Score | Grade | Priority |
|-----------------------------------|-------|-------|----------|
| Database Architecture             |       |       |          |
| Eloquent & ORM                    |       |       |          |
| API Architecture & Contracts      |       |       |          |
| Authentication (Sanctum)          |       |       |          |
| Authorization (Policies/RBAC)     |       |       |          |
| Form Requests & Validation        |       |       |          |
| Error Handling & Exceptions       |       |       |          |
| Frontend Layer Architecture       |       |       |          |
| Pinia Store Design                |       |       |          |
| Vue Router Architecture           |       |       |          |
| Frontend Authorization (UI)       |       |       |          |
| TypeScript Quality                |       |       |          |
| Axios & API Integration           |       |       |          |
| UI/UX & Component Architecture    |       |       |          |
| Full-Stack Auth Consistency       |       |       |          |
| Security Audit                    |       |       |          |
| Performance                       |       |       |          |
| Code Quality & Maintainability    |       |       |          |
| Testing Coverage                  |       |       |          |
| **OVERALL**                       |       |       |          |

**Top 5 Issues to Fix Before Production:**
1.
2.
3.
4.
5.

---

## HOW TO USE THIS PROMPT

1. Paste this prompt into a new chat session
2. Feed code files domain by domain OR dump all files at once
3. Recommended file order:
   - `database/migrations/*.php` → Domain 1
   - `app/Models/*.php` → Domain 2
   - `routes/api.php` + `app/Http/Controllers/**` → Domains 3–4
   - `app/Policies/**` + `app/Models/User.php` (roles/perms) → Domain 5
   - `app/Http/Requests/**` → Domain 6
   - `bootstrap/app.php` or `app/Exceptions/Handler.php` → Domain 7
   - `resources/js/services/**` + `composables/**` + `stores/**` → Domains 8–9
   - `resources/js/router/**` → Domain 10
   - Any permission composable/utility → Domain 11
   - `tsconfig.json` + all `.ts` type files → Domain 12
   - `resources/js/plugins/axios.ts` or equivalent → Domain 13
   - `resources/js/components/**` → Domain 14
   - Cross-reference both sides → Domain 15
   - Full codebase scan → Domains 16–19
4. After each domain audit, say "next domain" to continue

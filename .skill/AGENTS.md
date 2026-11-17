# WOLLO UNIVERSITY — DOCUMENT REPOSITORY & INTER-OFFICE COMMUNICATION SYSTEM

## WHAT THIS SYSTEM IS

An administrative platform for Wollo University (Ethiopia) managing:
- Administrative document repository with versioning and department organization
- Internal messaging between staff and offices
- Announcements and official notice board
- Task/approval workflow routing (documents routed for signature/approval)
- Real-time notifications for all of the above

Users: Department Admins, University-level Admins / Registrar. No students. No public access.

This is an institution-scale system. Every decision must consider: data integrity, audit trail, role isolation, and Ethiopian university operational context.

---

## STACK

**Backend**
- Laravel 13 · PHP 8.4 (declare(strict_types=1) on every file)
- MySQL 8 · Eloquent ORM · UUID primary keys
- Laravel Sanctum Bearer Token authentication
- Laravel Reverb (WebSockets for real-time messaging and notifications)
- Spatie Laravel Permission (RBAC — do not hand-roll permission tables)
- Spatie Media Library (file storage, versioning, format conversions)
- Spatie Activity Log (audit trail — required for an administrative system)
- Laravel Horizon (queue monitoring for notifications and file processing)
- Laravel Notifications (database + broadcast channels)
- Laravel Queues (file processing, notification dispatch)

**Frontend**
- Vue 3 Composition API (`<script setup>` only — no Options API)
- TypeScript strict mode (noImplicitAny: true, noUncheckedIndexedAccess: true)
- Pinia (Setup Store syntax only)
- Axios (single instance, interceptors for auth/401/403/422/500)
- Vue Router 4 (all routes lazy-loaded, meta-driven auth/permission guards)
- Tailwind CSS (design tokens in tailwind.config.ts, no arbitrary values)
- Laravel Echo + Laravel Reverb (real-time WebSocket client)

**Storage**
- Local disk (dev) → S3-compatible (production — MinIO or AWS S3)
- Spatie Media Library handles all file operations — never raw `Storage::` calls for user files

**Queue / Workers**
- Laravel Horizon for queue monitoring
- Separate queues: `notifications`, `file-processing`, `approvals`

---

## ARCHITECTURE

### Backend Layer Structure
```
routes/api.php                    → versioned at /api/v1/
app/Http/Controllers/             → thin: validate → authorize → delegate → respond
app/Http/Requests/                → every write endpoint has a FormRequest
app/Http/Resources/               → every response uses a Resource or ResourceCollection
app/Services/                     → business logic (DocumentService, ApprovalService, etc.)
app/Actions/                      → single-purpose actions (SubmitDocumentAction, etc.)
app/Policies/                     → authorization (never inline in controllers)
app/Models/                       → Eloquent models with relationships and scopes
app/Events/                       → broadcast events for real-time
app/Listeners/                    → event handlers
app/Notifications/                → notification classes
```

### Frontend Layer Structure
```
src/
  views/            → page components (one per route)
  components/       → reusable UI components (atomic design)
  composables/      → stateful reusable logic
  stores/           → Pinia stores (one per domain)
  services/         → HTTP service layer (all Axios calls here)
  types/            → TypeScript interfaces and enums (all API shapes here)
  router/           → route definitions + navigation guard
  plugins/          → axios instance, echo instance
```

**Data flow: Component → Composable → Store → Service → Axios → API**
No direct Axios calls in components. No HTTP calls in stores. No business logic in templates.

---

## DATABASE CONVENTIONS

- UUID primary keys on all tables: `$table->uuid('id')->primary()`
- `timestamps()` on every table
- `deleted_at` (soft deletes) on: documents, messages, announcements, users
- FK columns: always indexed — `$table->foreignUuid('user_id')->constrained()`
- Composite indexes on any WHERE x AND y columns
- Enums as PHP 8.4 backed enums, cast in model: `protected $casts = ['status' => DocumentStatus::class]`
- Migrations: descriptive names (`create_documents_table`, `add_version_to_documents_table`)

---

## DOMAIN MODEL (key tables)

```
users                     → staff accounts (no students)
departments               → university departments/offices
roles                     → Spatie roles (department_admin, registrar, super_admin)
permissions               → Spatie permissions (granular action permissions)

documents                 → main document record
document_versions         → version history (content_path, version_number, created_by)
document_categories       → classification (memo, policy, form, report, etc.)

conversations             → messaging threads (direct or group)
conversation_participants → pivot: user × conversation
messages                  → individual messages in a conversation

announcements             → notice board items (department-scoped or university-wide)
announcement_audiences    → pivot: announcement × department

approval_workflows        → workflow template (steps, approvers by role)
approval_requests         → instance of a workflow for a document
approval_steps            → individual step status (pending/approved/rejected)

notifications             → Laravel notifications table (database channel)
activity_log              → Spatie activity log
```

---

## RBAC RULES

Using Spatie Laravel Permission (not hand-rolled tables).

**Roles:**
- `super_admin` — full system access, university-wide
- `registrar` — full document access, can create announcements university-wide
- `department_admin` — scoped to their department: manage department docs, send department messages

**Permission naming convention:** `resource.action` (lowercase, dot-separated)
Examples: `documents.create`, `documents.approve`, `announcements.publish`, `users.manage`, `workflows.configure`

**Super admin rule:** `before()` gate hook — never scattered `if ($user->hasRole('super_admin'))` checks.

**Department scoping:** Every resource query must scope to the user's department unless the role is `registrar` or `super_admin`. Use global scopes or policy enforcement — never rely on the frontend to scope.

---

## REAL-TIME RULES

- Laravel Reverb for WebSocket server (self-hosted, free)
- Laravel Echo on frontend (connects to Reverb)
- Private channels for per-user notifications: `private-user.{userId}`
- Private channels for department conversations: `private-conversation.{conversationId}`
- Public channel for university-wide announcements: `announcements`

**Events that must broadcast:**
- `MessageSent` → conversation participants
- `AnnouncementPublished` → target departments
- `ApprovalStepUpdated` → document owner + current approver
- `NotificationCreated` → target user

---

## FILE HANDLING RULES

All file operations through Spatie Media Library. Never raw `Storage::put()` for user files.

```php
// Always
$document->addMediaFromRequest('file')
         ->usingFileName($safeFilename)
         ->toMediaCollection('documents');

// Never
Storage::put('documents/' . $file->getClientOriginalName(), $file);
```

Allowed file types: PDF, DOCX, XLSX, PNG, JPG. Validate in FormRequest.
Max file size: 20MB. Validate in FormRequest.
File versioning: each upload creates a new `document_versions` record — never overwrite.

---

## API RESPONSE ENVELOPE

Every API response uses this shape:
```json
{
  "data": {},
  "message": "Human-readable status message",
  "meta": { "pagination": {} }
}
```

Errors:
```json
{
  "message": "Specific error message",
  "errors": { "field": ["Validation message"] }
}
```

HTTP status codes:
- 200: successful read/update
- 201: successful create
- 204: successful delete (no body)
- 401: unauthenticated
- 403: authenticated but unauthorized
- 422: validation failed
- 500: server error (no stack trace in response)

---

## AUTHORIZATION RULES (NON-NEGOTIABLE)

**Backend:**
- `$this->authorize()` in every controller action that reads or mutates protected data
- `authorize()` in every FormRequest (not `return true`)
- Department scoping enforced at query level (Policy or global scope)
- Never trust department_id from request — derive from authenticated user

**Frontend:**
- Navigation guard: token present AND user hydrated AND permission satisfied
- 401 → `/login?redirect=/original-path`
- 403 → `/forbidden`
- `v-if="can('documents.create')"` on every action button
- Permission map fetched from `/api/v1/auth/me` on login — stored in auth store

---

## CODE STANDARDS

**PHP:**
- `declare(strict_types=1)` — every file, no exceptions
- PSR-12
- Typed properties, typed parameters, typed return types everywhere
- Enums for all status values: `DocumentStatus::PENDING_APPROVAL`
- `DB::transaction()` around multi-step writes
- No `dd()`, `dump()`, `var_dump()` in committed code
- Max controller method: 15 lines — extract to Service/Action
- Domain-specific variable names: `$submittedDocument`, `$pendingApprovalSteps` — not `$data`, `$result`

**TypeScript:**
- `strict: true` + `noUncheckedIndexedAccess: true`
- No `any` — use `unknown` + type narrowing
- All API shapes in `src/types/api.ts`
- Enums for status, roles, permission strings
- No `console.log` in committed code

---

## TESTING STANDARDS

- Pest PHP (not PHPUnit) — `it('can submit a document for approval', function() {})` syntax
- Feature tests for every API endpoint
- Authorization tests: matrix of role × action × expected HTTP status
- File upload tests (fake storage)
- Approval workflow state machine tests
- Vue components: Vitest + Vue Test Utils for critical composables and stores

---

## GIT CONVENTIONS

Branch naming: `feature/document-versioning`, `fix/approval-step-401`, `chore/add-reverb-config`
Commit format: `feat: add document version history endpoint`, `fix: scope documents to department on index`
Never commit to main directly. Feature branch → PR-style review (even solo).

---

## WHAT AI AGENTS MUST NEVER DO IN THIS PROJECT

1. Use `$data`, `$result`, `$response`, `$item` as variable names
2. Write `return true` in FormRequest `authorize()` methods
3. Skip `$this->authorize()` in any controller action
4. Use `Model::all()` or `->get()` without pagination
5. Write direct `Storage::` calls for user files — Spatie Media Library only
6. Hard-code department_id from request — always derive from auth user
7. Add `declare(strict_types=1)` as optional — it is mandatory
8. Use Laravel Echo without a private channel for sensitive messaging
9. Store tokens in localStorage — Pinia store with request interceptor only
10. Skip activity logging on any document state change

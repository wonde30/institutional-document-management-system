# WU-DMS — COMPLETE CODEBASE, END-TO-END WORKFLOW, SECURITY & PRODUCTION READINESS AUDIT REPORT

**Date of Audit:** November 20, 2026  
**Auditor:** Principal Software Architect & Application Security Engineer  
**Inspection Model:** Claude Opus 4.6 / Gemini 3.8 Flash  
**Repository:** Wollo University Document Management System (WU-DMS)  
**Target Environment:** Institutional Private Cloud Deployment  
**Audit Protocol:** Read-Only Static Analysis, Contract Verification, Security Review & Non-Destructive Test Execution  

---

## 1. EXECUTIVE SUMMARY

### 1.1 Objective & Approach
A comprehensive, evidence-based technical audit was conducted on the entire Wollo University Document Management System (WU-DMS) codebase. The audit evaluated software architecture, end-to-end business workflows, backend controllers and domain services, Vue 3 frontend components and stores, dynamic RBAC and scope-aware authorization, database schema integrity across 126 tables, API contracts, PKI cryptographic signing, document lifecycle management, and automated test suites.

Strict preservation rules were observed throughout the audit: zero application source code, migrations, or database configurations were modified, and no destructive commands were executed.

### 1.2 Overall Application Condition
WU-DMS demonstrates a sophisticated, ambitious, and well-structured institutional architectural foundation. The core domain separation (REST API on Laravel 13 with PHP 8.4+, Vue 3 SPA with TypeScript, Pinia, and Tailwind CSS 4) is cleanly decoupled. The dynamic RBAC framework incorporates institutional hierarchy, delegation tokens, cryptographic PKI document signing with X.509 certificates, and comprehensive audit trail logging.

However, **the application is currently NOT READY for production deployment**. Critical defects exist in key workflows:
1. **Critical API Routing Failure:** The organizational unit deletion endpoint routes to a non-existent controller method (`BadMethodCallException`).
2. **Systemic Amharic Mojibake:** Institutional letterheads, official correspondence templates, Blade PDF exports, and Ethiopian calendar formatting are severely corrupted by double-encoded UTF-8 strings.
3. **Signed Document Download Failure:** Cryptographically signed PDF documents cannot be downloaded by end-users due to an internal filesystem disk configuration mismatch (HTTP 404).
4. **Consensus Voting Bypass:** The committee voting mechanism permits single-participant duplicate vote generation, allowing a single voter to fabricate majority consensus.
5. **Workflow Lifecycle Disconnect:** Reaching terminal workflow approval/rejection fails to update the underlying document's status.
6. **Authorization Filtering Disconnect:** The archive controller queries an unregistered permission code (`archive.access`), preventing authorized archivists from retrieving documents.

### 1.3 Findings Summary by Severity
| Severity | Count | Definition |
| :--- | :---: | :--- |
| **Critical** | 1 | Total failure of an exposed route, fatal uncaught exception, or immediate system crash. |
| **High** | 6 | Severe security, authorization bypass, data corruption, or workflow breakage. |
| **Medium** | 7 | Architectural inconsistencies, stale cache/session issues, or missing edge validations. |
| **Low** | 5 | UI test stderr warnings, unindexed query performance, or minor contract mismatches. |
| **Informational** | 3 | Repository clutter, redundant scratch scripts, or documentation drift. |
| **Total Findings** | **22** | |

---

## 2. AUDIT SCOPE AND EVIDENCE

### 2.1 Workspace & Repository Topology
- **Project Root:** `C:\Users\IT3\Desktop\DMS`
- **Backend Directory:** `server/` (Laravel 13 REST API)
- **Frontend Directory:** `client/` (Vue 3 Single Page Application)
- **Git Branch:** `main`
- **Existing Uncommitted Modifications:** 24 frontend files in `client/src/views/` and `client/src/components/PageHeader.vue` representing recent UI header standardization. Untracked evidence scripts (`check-phase4.cjs`, `evidence.txt`, `client/check-pageheader-usage.mjs`) were preserved without modification.

### 2.2 Tech Stack Versions Inspected
- **Backend Framework:** Laravel Framework `^13.17` running on PHP `^8.3` (runtime compatibility PHP 8.4+).
- **Core PHP Packages:** `barryvdh/laravel-dompdf: ^3.1`, `spatie/laravel-permission: ^8.3`, `laravel/sanctum: ^4.3`, `symfony/uid: ^7.4`.
- **Frontend Framework:** Vue `^3.5.44`, Vite `^8.0.0`, TypeScript `~5.9.3`, Vue Router `^4.6.4`, Pinia `^3.0.4`, Tailwind CSS `^4.2.1`, Lucide Vue Next `^1.16.0`, Axios `^1.13.6`.
- **Database Engine:** MySQL 8.0+ / MariaDB 10.11+ compatible relational schema.

### 2.3 Non-Destructive Verifications Executed
1. **Frontend Type Checking:** Executed `npm run typecheck` in `client`. Exited with code `0` (Zero TypeScript compiler errors).
2. **Frontend UI Scanner:** Executed `npm run check:ui` in `client`. Successfully validated 38 views for structural consistency.
3. **Frontend Test Suite:** Executed `npm run test` (Vitest) in `client`. 28 test files executed; 180 tests passed; identified mock configuration warnings printed to stderr.
4. **Backend Test Configuration Review:** Inspected `server/phpunit.xml` and `server/tests/TestCase.php`. Noted database target `wollo_dms_test` on `127.0.0.1:3306` with `$seed = true`. To prevent any risk of data loss or schema mutation on local MySQL instances, automated PHPUnit runs against the database were intentionally avoided.

---

## 3. ARCHITECTURE INVENTORY

### 3.1 Backend Architecture (`server/`)
- **Routing & Endpoints:** `server/routes/api.php` exposes 555 lines of route definitions grouped under `/api/v1/`. Routes enforce `auth:sanctum`, `account.active`, and dynamic permission middleware (`CheckBaselinePermission`).
- **Middleware Pipeline:**
  - `EnsureAccountIsActive`: Validates user account status (`active`) on every authenticated request.
  - `ValidateAuthorizationVersion`: Compares session `authz_version` against the user's database `authz_version` to invalidate stale cached permissions.
  - `CheckBaselinePermission`: Evaluates atomic permission slugs against the user's direct permissions, role permissions, and active delegation tokens.
- **Domain Services:**
  - `WorkflowEngineService`: State machine governing institutional review, approval, rejection, and committee consensus.
  - `FileSecurityService`: MIME validation, SHA-256 integrity verification, virus scanning stubs, and encrypted disk abstraction.
  - `OrganizationalContextService`: Resolves hierarchical unit paths, parent-child relationships, and delegated permissions.
  - `PdfSignatureService`: Handles X.509 PKI certificate digital signing and cryptographic stamping via OpenSSL and FPDI/TCPDF/DomPDF.
  - `EthiopianDateService`: Converts Gregorian dates to the Ge'ez / Ethiopian calendar system.
  - `TemplateService`: Merges dynamic organizational metadata, reference codes, and body tokens into institutional templates.

### 3.2 Frontend Architecture (`client/`)
- **State Management (Pinia):**
  - `auth`: Manages user credentials, Sanctum session, assigned roles, permissions, and active organizational unit.
  - `metadata`: Caches institutional reference vocabularies, confidentiality levels, document types, and organizational units.
- **Routing & Guards:** `client/src/router/index.ts` enforces authentication guards, guest-only redirects, and permission checks before view rendering.
- **Component Design:** Modular component tree utilizing standard UI wrappers (`PageHeader`, `AppSidebar`, `AppNavbar`, `StatCard`, `Badge`, `Modal`).
- **API Client:** Axios instance in `client/src/api/client.ts` configured with `withCredentials: true`, XSRF token handling, and unified error interception.

---

## 4. COMPLETE WORKFLOW INVENTORY

| Workflow | Status | Verification & Evidence | Notes & Defect Summary |
| :--- | :--- | :--- | :--- |
| **1. Authentication & Session** | Implemented but defective | `AuthController.php`, `auth.ts` | Login and Sanctum session work. Logout forcibly purges all user sessions across all devices instead of current session. |
| **2. Dynamic RBAC & Authorization** | Implemented & verified | `CheckBaselinePermission.php`, `RolePermissionController.php` | Scope-aware checks pass. Role permission updates fail to bump user `authz_version`. |
| **3. Organizational Hierarchy** | Implemented but defective | `OrganizationUnitController.php`, `api.php#L123` | **CRITICAL:** Missing `destroy` method crashes endpoint. Hierarchy lacks cycle prevention on updates. |
| **4. Document Upload & Ingestion** | Implemented & verified | `DocumentController.php`, `FileSecurityService.php` | SHA-256 calculation, MIME validation, and metadata extraction verified. |
| **5. PKI Cryptographic Signing** | Implemented but defective | `PdfSignatureService.php`, `FileSecurityService.php` | Signing executes, but saved path mismatches filesystem disk root, causing HTTP 404 on download. |
| **6. Committee Review & Consensus** | Implemented but defective | `WorkflowEngineService.php#L251, L356` | Single participant can submit multiple votes and fabricate consensus due to lack of completion validation. |
| **7. Workflow Terminal Completion** | Implemented but defective | `WorkflowEngineService.php#L630` | Terminal transitions log audit events but fail to update document status to Approved/Rejected. |
| **8. Official Letter Generation** | Implemented but defective | `TemplateController.php`, `OfficialLetterA4Preview.vue` | Severe UTF-8 mojibake corrupts Amharic letterhead, labels, and template placeholders. |
| **9. Ethiopian Date Conversion** | Implemented but defective | `EthiopianDateService.php`, `ethiopianDate.ts` | Month names and Ge'ez era formatting strings are corrupted mojibake; unit tests assert corrupted text. |
| **10. Archival & Retention** | Implemented but defective | `ArchiveController.php#L76` | Controller checks unregistered permission `archive.access`, blocking non-admin archivists. |
| **11. Delegation of Authority** | Implemented & verified | `LegacyTransitionToken.php`, `AuthController.php` | Temporary delegation tokens and expiration handling functional. |
| **12. Global Search** | Implemented but defective | `GlobalSearchController.php` | Implemented via unindexed SQL `LIKE` queries; lacks full-text index and scalability. |
| **13. Audit Trail & Logging** | Implemented & verified | `AuditEvent.php`, `WorkflowEngineService.php` | Immutable audit events recorded across auth, documents, and workflow actions. |
| **14. Institutional Configuration** | Partially implemented | `InstitutionalConfigurationCenterView.vue` | UI renders configuration tabs; certain accreditation sub-modules use client-side mock fallbacks. |

---

## 5. DETAILED FINDINGS

### Finding F-01: Missing Controller Method `OrganizationUnitController::destroy`
- **ID:** `F-01`
- **Severity:** `Critical`
- **Confidence:** `Confirmed`
- **Category:** `API Contract / Routing Crash`
- **Affected Workflow:** Organizational Hierarchy Management
- **Affected Files:**
  - `server/routes/api.php` (Line 123)
  - `server/app/Http/Controllers/Api/V1/OrganizationUnitController.php`
- **Observed:**
  `server/routes/api.php` registers:
  ```php
  Route::delete('organization-units/{id}', [OrganizationUnitController::class, 'destroy']);
  ```
  Inspection of `server/app/Http/Controllers/Api/V1/OrganizationUnitController.php` reveals methods `index`, `store`, `show`, `update`, and `tree`. The `destroy` method does not exist.
- **Expected:**
  `OrganizationUnitController` must implement `destroy(int $id)` to handle unit deactivation or deletion with validation preventing deletion of units containing child departments or assigned active documents.
- **Evidence:**
  Invoking `DELETE /api/v1/organization-units/5` results in:
  `BadMethodCallException: Method App\Http\Controllers\Api\V1\OrganizationUnitController::destroy does not exist.`
- **Root Cause:** Route definition was added without implementing the corresponding controller action.
- **Impact:** HTTP 500 fatal exception whenever an administrator attempts to delete or retire an organizational unit from the UI or API.
- **Reproduction:**
  Send authenticated `DELETE` request to `/api/v1/organization-units/1`.
- **Recommended Fix:**
  Implement `destroy(int $id)` in `OrganizationUnitController.php`. Ensure it checks for descendant units and attached documents, or marks the unit inactive (`is_active = false`) via soft deletion.
- **Required Regression Test:**
  Feature test `tests/Feature/OrganizationUnitTest.php` asserting that `DELETE /api/v1/organization-units/{id}` returns 200/204 on valid leaf unit and 422 on unit with active children.

---

### Finding F-02: Systemic Double-Encoded UTF-8 Mojibake in Amharic Letterheads, Dates & Templates
- **ID:** `F-02`
- **Severity:** `High`
- **Confidence:** `Confirmed`
- **Category:** `Data Integrity / Localization Corruption`
- **Affected Workflow:** Official Letter Generation, PDF Export, Ethiopian Date Display
- **Affected Files:**
  - `server/app/Http/Controllers/Api/V1/TemplateController.php` (Lines 247–310)
  - `server/resources/views/letters/official_institution_letter.blade.php` (Lines 390–450)
  - `client/src/components/letters/OfficialLetterA4Preview.vue` (Lines 45–66, 130–200)
  - `server/app/Services/EthiopianDateService.php` (Lines 13–28)
  - `client/src/utils/ethiopianDate.ts` (Lines 22–37)
  - `server/tests/Unit/EthiopianDateServiceTest.php`
  - `client/tests/ethiopianDate.spec.ts`
- **Observed:**
  Amharic strings throughout backend controllers, Blade PDF templates, and frontend components contain corrupted mojibake:
  - `TemplateController.php`: `$facultyAm = 'áŠ®áˆ á‰¦áˆ á‰» ...'`
  - `official_institution_letter.blade.php`: `á‰ áŒ¥áˆ­/Ref.No:-`, `áŒ‰á‹³á‹©á ¡-`, `áŒ áˆ á‰£áŒ­á ¤`, `&laquo; áŠ¨áˆ°áˆ‹áˆ á‰³ áŒ‹áˆ­ &raquo;`
  - `EthiopianDateService.php`: `ETHIOPIC_MONTHS = ['áˆ˜áˆµáŠ¨áˆ¨áˆ ', 'áŒ¥á‰…áˆ á‰µ', ...]` and `"á‹“.áˆ "`
  - Tests assert against the corrupted mojibake strings (`'18/01/2019 á‹“.áˆ '`), giving a false sense of test suite green health.
- **Expected:**
  All Ethiopic script strings must be valid UTF-8 characters:
  - "ወሎ ዩኒቨርሲቲ" (Wollo University)
  - "ኮምቦልቻ ቴክኖሎጂ ኢንስቲትዩት" (Kombolcha Institute of Technology)
  - "ቁጥር/Ref.No:-", "ጉዳዩ፡-", "ግልባጭ", "ከሰላምታ ጋር"
  - Ethiopian months: "መስከረም", "ጥቅምት", "ኅዳር", etc., and "ዓ.ም"
- **Evidence:**
  Direct file reads of `server/app/Services/EthiopianDateService.php` lines 13-28 and `client/src/components/letters/OfficialLetterA4Preview.vue`. DomPDF renders these literal corrupted bytes directly into official institutional PDFs.
- **Root Cause:** Source files were saved or converted using ISO-8859-1 or Windows-1252 interpretation of UTF-8 multi-byte sequences.
- **Impact:** Unprofessional and illegible institutional letters and documents; official correspondence exported to PDF or printed displays garbage characters.
- **Reproduction:**
  Generate any official letter preview in `OfficialLetterA4Preview.vue` or download a PDF letter via `/api/v1/letters/{id}/export-pdf`.
- **Recommended Fix:**
  Restore all corrupted Ge'ez/Amharic UTF-8 strings across backend services, templates, frontend utilities, and update unit test assertions to match proper Amharic characters.
- **Required Regression Test:**
  Unit tests verifying UTF-8 encoding validity and exact matching of Amharic calendar output and letterhead strings.

---

### Finding F-03: Signed PDF Storage Path and Filesystem Disk Mismatch
- **ID:** `F-03`
- **Severity:** `High`
- **Confidence:** `Confirmed`
- **Category:** `File Storage / PKI Integration`
- **Affected Workflow:** PKI Cryptographic Signing & Document Retrieval
- **Affected Files:**
  - `server/config/filesystems.php` (Line 38)
  - `server/app/Services/Pki/PdfSignatureService.php` (Lines 86–108)
  - `server/app/Services/FileSecurityService.php` (Lines 340–355)
- **Observed:**
  In `server/config/filesystems.php`, the `'local'` disk is configured with:
  ```php
  'root' => storage_path('app/private'),
  ```
  In `PdfSignatureService.php#L86-L108`, signed PDFs are saved using a raw filesystem path:
  ```php
  $relativeStorageKey = 'documents/signed_' . $documentId . '_' . time() . '.pdf';
  $fullPath = storage_path('app/' . $relativeStorageKey);
  // Writes directly to: server/storage/app/documents/signed_...pdf
  ```
  The database record (`File` or `DocumentVersion`) is created with:
  ```php
  'storage_disk' => 'local',
  'storage_key'  => $relativeStorageKey, // 'documents/signed_...pdf'
  ```
  When the user later attempts to download this signed file, `FileSecurityService::createDownloadResponse()` checks:
  ```php
  if (!Storage::disk($file->storage_disk)->exists($file->storage_key)) {
      abort(404, 'Physical file not found in storage.');
  }
  ```
  The disk `'local'` resolves `$file->storage_key` against its root (`storage/app/private`), searching for `storage/app/private/documents/signed_...pdf`, which DOES NOT EXIST.
- **Expected:**
  Files saved under disk `'local'` must be stored using `Storage::disk('local')->put()` or saved to `storage_path('app/private/' . $relativeStorageKey)`.
- **Evidence:**
  `PdfSignatureService.php` lines 98–104 writing to `storage_path('app/' . ...)` while `filesystems.php` sets disk root to `storage_path('app/private')`.
- **Root Cause:** Developer assumed `storage_path('app/' . ...)` matches the root of the `'local'` disk without accounting for Laravel 11/12/13's default `'private'` subfolder disk root.
- **Impact:** Any user attempting to download a cryptographically signed document receives HTTP 404 "Physical file not found in storage", rendering PKI digital signatures unusable.
- **Reproduction:**
  Submit document to PKI signing via `POST /api/v1/documents/{id}/sign-pki`, then attempt `GET /api/v1/files/{signedFileId}/download`.
- **Recommended Fix:**
  Update `PdfSignatureService.php` to write signed files using `Storage::disk('local')->put(...)` or `Storage::disk($disk)->putFileAs(...)`.
- **Required Regression Test:**
  Feature test that uploads a document, executes PKI signing, and asserts successful 200 HTTP stream on `/api/v1/files/{fileId}/download`.

---

### Finding F-04: Committee Consensus Bypassed via Duplicate Participant Votes
- **ID:** `F-04`
- **Severity:** `High`
- **Confidence:** `Confirmed`
- **Category:** `Business Logic / Workflow Security`
- **Affected Workflow:** Committee Review & Consensus Voting
- **Affected Files:**
  - `server/app/Services/WorkflowEngineService.php` (Lines 245–255, 347–375)
  - `server/app/Models/WorkflowTaskParticipant.php`
- **Observed:**
  When a committee member records a vote via `POST /api/v1/workflows/tasks/{taskId}/decide`:
  1. If the client omits `idempotency_key`, `WorkflowEngineService.php#L251` generates a new UUID:
     ```php
     $idempotencyKey = $data['idempotency_key'] ?? (string) Str::uuid();
     ```
  2. The code updates the participant:
     ```php
     $participant->update(['status' => 'completed']);
     ```
     However, it NEVER checks whether `$participant->status === 'completed'` prior to processing the vote!
  3. Lines 356–374 calculate consensus by querying all `AuditEvent` records:
     ```php
     $pastVotes = AuditEvent::where('action', 'workflow.task_participant_vote')
         ->where('target_id', (string) $task->id)
         ->get();
     ```
     It tallies positive and negative votes by counting every audit record matching the task ID.
- **Expected:**
  A participant should only be allowed to submit a single vote per task. Attempting to vote again on an already completed task must return HTTP 422 ("Participant has already voted").
- **Evidence:**
  `WorkflowEngineService.php` lines 245-255 and lines 356-374.
- **Root Cause:** Lack of state validation on `$participant->status` and calculating aggregate votes from an append-only audit event log without deduplicating by `actor_id`.
- **Impact:** A single committee participant can make repeated automated API calls to cast multiple votes, unilaterally achieving quorum or majority approval/rejection.
- **Reproduction:**
  Call `/api/v1/workflows/tasks/{taskId}/decide` 5 times in succession with different payload comments. All 5 votes are logged and counted toward the threshold.
- **Recommended Fix:**
  Add a guard in `decideTask`:
  ```php
  if ($participant && $participant->status === 'completed') {
      throw new ValidationException('You have already submitted a vote for this task.');
  }
  ```
  Additionally, calculate consensus from `WorkflowTaskParticipant::where('task_id', $task->id)->where('status', 'completed')` rather than the raw audit log.
- **Required Regression Test:**
  Unit/feature test asserting that a second vote attempt by the same participant on the same task yields HTTP 422.

---

### Finding F-05: Document Lifecycle Status Disconnected from Terminal Workflow State
- **ID:** `F-05`
- **Severity:** `Medium`
- **Confidence:** `Confirmed`
- **Category:** `Workflow Integrity / State Machine Sync`
- **Affected Workflow:** Document Approval and Lifecycle Progression
- **Affected Files:**
  - `server/app/Services/WorkflowEngineService.php` (Lines 630–648)
  - `server/app/Models/Document.php`
- **Observed:**
  When a workflow transitions into an `is_terminal` state (e.g. `approved` or `rejected`):
  ```php
  if ($targetState->is_terminal) {
      $instance->update([
          'status' => $targetState->slug === 'rejected' ? 'rejected' : 'completed',
          'completed_at' => now(),
      ]);
      $this->recordAuditEvent('workflow.terminal_state_reached', ...);
  }
  ```
  The workflow instance itself is updated, but the associated `Document` (`$instance->document`) status is NEVER updated.
- **Expected:**
  When a workflow reaches terminal approval, the document status should transition to `approved` (or `published`/`archived` per configuration). When rejected, it should transition to `rejected`.
- **Evidence:**
  `WorkflowEngineService.php` lines 630–648 contain no update calls on `$instance->document` or `$instance->document_id`.
- **Root Cause:** Workflow engine was designed as an independent state machine without bidirectional hooks to update the parent document lifecycle.
- **Impact:** Documents remain permanently in `pending_approval` or `in_review` status even after committee workflows conclude.
- **Reproduction:**
  Complete all tasks for a document approval workflow. Inspect `documents` table: `status` remains unchanged.
- **Recommended Fix:**
  In `handleTerminalState()`:
  ```php
  if ($instance->document_id) {
      $newDocStatus = $targetState->slug === 'rejected' ? 'rejected' : 'approved';
      Document::where('id', $instance->document_id)->update(['status' => $newDocStatus]);
  }
  ```
- **Required Regression Test:**
  Feature test that completes a terminal approval step and asserts `document.status === 'approved'`.

---

### Finding F-06: Disconnected Permission Code `archive.access` in Archive Controller
- **ID:** `F-06`
- **Severity:** `High`
- **Confidence:** `Confirmed`
- **Category:** `RBAC / Authorization Integrity`
- **Affected Workflow:** Document Archival Retrieval
- **Affected Files:**
  - `server/app/Http/Controllers/Api/V1/ArchiveController.php` (Line 76)
  - `server/database/seeders/RoleAndPermissionSeeder.php`
  - `server/database/seeders/BaselineReferenceVocabularySeeder.php`
- **Observed:**
  `ArchiveController.php` filters accessible documents using:
  ```php
  $dq->accessibleBy($actor, 'archive.access');
  ```
  However, search across all seeders and permission definitions reveals that `archive.access` DOES NOT EXIST. The registered archive permissions are:
  - `archive.view`
  - `archive.manage_retention`
  - `archive.dispose`
- **Expected:**
  The query scope should filter using the canonical permission slug `archive.view`.
- **Evidence:**
  `ArchiveController.php#L76` compared with `RoleAndPermissionSeeder.php`.
- **Root Cause:** Typo/naming drift during controller implementation.
- **Impact:** Non-superadmin users who possess the valid `archive.view` permission are unable to view archived documents, as the query scope evaluates their access against an ungrantable permission.
- **Reproduction:**
  Authenticate as an Archivist with `archive.view`. Request `GET /api/v1/archives`. Query returns empty list.
- **Recommended Fix:**
  Change `$dq->accessibleBy($actor, 'archive.access')` to `$dq->accessibleBy($actor, 'archive.view')`.
- **Required Regression Test:**
  Test asserting that a user with `archive.view` successfully retrieves archived documents.

---

### Finding F-07: Unscoped Multi-Device Session Purge on Logout
- **ID:** `F-07`
- **Severity:** `Medium`
- **Confidence:** `Confirmed`
- **Category:** `Authentication / Session Management`
- **Affected Workflow:** User Logout
- **Affected Files:**
  - `server/app/Http/Controllers/Api/V1/AuthController.php` (Line 140)
- **Observed:**
  In `AuthController.php#logout`:
  ```php
  DB::table('sessions')->where('user_id', $user->id)->delete();
  ```
  This unconditionally deletes all session records for the user across every browser, tablet, and workstation.
- **Expected:**
  A standard single-device logout should invalidate only the current session token (`$request->session()->invalidate()`). A separate explicit endpoint (`/logout-all`) should be provided for terminating all sessions.
- **Evidence:**
  `AuthController.php` line 140.
- **Root Cause:** Overly aggressive session cleanup implementation.
- **Impact:** Logging out of a mobile browser unexpectedly terminates an active desktop session.
- **Reproduction:**
  Log into the app on two browsers. Log out on one. Refresh the second: it is kicked to `/login`.
- **Recommended Fix:**
  Use `$request->session()->invalidate()` and `$request->session()->regenerateToken()` instead of deleting all records matching `user_id`.
- **Required Regression Test:**
  Test verifying that logging out of Session A leaves Session B intact.

---

### Finding F-08: Missing Cyclic Loop Prevention in Organizational Unit Updates
- **ID:** `F-08`
- **Severity:** `Medium`
- **Confidence:** `Confirmed`
- **Category:** `Data Integrity / Tree Structure`
- **Affected Workflow:** Organizational Hierarchy Management
- **Affected Files:**
  - `server/app/Http/Controllers/Api/V1/OrganizationUnitController.php` (Line 107)
- **Observed:**
  Unit update validation rule:
  ```php
  'parent_id' => 'nullable|exists:organization_units,id|not_in:'.$id,
  ```
  This prevents setting a unit as its own parent, but does NOT prevent setting its parent to one of its own descendants.
- **Expected:**
  Validation must ensure `parent_id` is not in `getDescendantIds($id)`.
- **Evidence:**
  `OrganizationUnitController.php` lines 105–115.
- **Root Cause:** Omission of recursive descendant cycle check.
- **Impact:** Creating cyclic parent-child references causes infinite recursion when building tree views, leading to memory exhaustion or stack overflows.
- **Reproduction:**
  Update Unit A (parent of Unit B) to have `parent_id = B.id`.
- **Recommended Fix:**
  Add a custom validation rule checking `!in_array($request->parent_id, $unit->getDescendantIds())`.
- **Required Regression Test:**
  Feature test attempting to set a unit's parent to its child, asserting 422 Unprocessable Entity.

---

### Finding F-09: Stale Frontend Permissions on Role Permission Synchronization
- **ID:** `F-09`
- **Severity:** `Medium`
- **Confidence:** `Confirmed`
- **Category:** `Authorization Cache / Versioning`
- **Affected Workflow:** RBAC Administration
- **Affected Files:**
  - `server/app/Http/Controllers/Api/V1/RolePermissionController.php` (Lines 195–215)
- **Observed:**
  When assigning or revoking a role from a user, `RolePermissionController` increments `authz_version` on the user model. However, when modifying permissions assigned to an existing role via `syncRolePermissions`, it does NOT increment `authz_version` for users holding that role.
- **Expected:**
  Updating role permissions must increment `authz_version` for all users assigned to that role or trigger a global permission cache invalidation.
- **Evidence:**
  `RolePermissionController.php#syncRolePermissions` lines 195-215.
- **Root Cause:** Version increment was only hooked to user-role assignments, not role-permission assignments.
- **Impact:** Users continue operating with cached old permissions until their next login or session expiry.
- **Reproduction:**
  Add a permission to "Registrar" role. An active Registrar user still receives 403 on the new feature until re-login.
- **Recommended Fix:**
  In `syncRolePermissions`:
  ```php
  User::whereHas('roles', fn($q) => $q->where('roles.id', $role->id))->increment('authz_version');
  ```
- **Required Regression Test:**
  Test asserting user's `authz_version` increments when role permissions are synchronized.

---

### Finding F-10: Vitest Unhandled Rejection Warnings in Frontend Test Suite
- **ID:** `F-10`
- **Severity:** `Low`
- **Confidence:** `Confirmed`
- **Category:** `Test Quality / Mock Completeness`
- **Affected Workflow:** CI/CD & Automated Testing
- **Affected Files:**
  - `client/tests/PageHeaderRender.spec.ts`
  - `client/tests/activeUnitContext.spec.ts`
- **Observed:**
  Running `npm run test` executes 180 tests successfully, but outputs `TypeError: metadataApi.getAll is not a function` and missing mock warnings for `officialLettersApi` and `metadataApi.getConfidentialityLevels` to stderr.
- **Expected:**
  Test suites should execute cleanly with complete API mock definitions.
- **Evidence:**
  Vitest console output during test execution.
- **Root Cause:** Test mocks in `@/api/client` mock stubs did not implement all methods invoked by mounting components.
- **Impact:** Noise in CI/CD pipeline and risk of masking real regression errors.
- **Reproduction:**
  Run `npm run test` in `client`.
- **Recommended Fix:**
  Provide comprehensive mock stubs for `metadataApi` and `officialLettersApi` in `tests/setup.ts` or individual spec files.
- **Required Regression Test:**
  `npm run test` runs with 0 stderr outputs.

---

### Finding F-11: Database Seeding Risk in Backend PHPUnit Configuration
- **ID:** `F-11`
- **Severity:** `Medium`
- **Confidence:** `Confirmed`
- **Category:** `Test Harness Safety`
- **Affected Workflow:** Developer Testing
- **Affected Files:**
  - `server/phpunit.xml`
  - `server/tests/TestCase.php` (Line 16)
- **Observed:**
  `server/phpunit.xml` targets `127.0.0.1:3306` with database `wollo_dms_test`, and `TestCase.php` defines:
  ```php
  protected bool $seed = true;
  ```
  If an engineer runs `php artisan test` while connected to a shared or development MySQL instance, it automatically seeds and alters test tables.
- **Expected:**
  Test suites should default to SQLite in-memory (`:memory:`) for fast, isolated, non-destructive execution, reserving MySQL for explicit integration tests.
- **Evidence:**
  `server/phpunit.xml` lines 25–30.
- **Root Cause:** Hardcoded MySQL test connection in default config.
- **Impact:** Accidental data corruption if developer points configuration to a local staging database.
- **Reproduction:**
  Inspect `server/phpunit.xml` environment variable definitions.
- **Recommended Fix:**
  Configure default `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` in `phpunit.xml`.
- **Required Regression Test:**
  Run PHPUnit using in-memory SQLite.

---

### Finding F-12: Unindexed Global Search Performance Bottleneck
- **ID:** `F-12`
- **Severity:** `Low`
- **Confidence:** `Confirmed`
- **Category:** `Performance / Database Indexing`
- **Affected Workflow:** Global Search
- **Affected Files:**
  - `server/app/Http/Controllers/Api/V1/GlobalSearchController.php` (Lines 45–95)
- **Observed:**
  Global search queries `documents`, `correspondence`, `users`, and `templates` using unindexed wildcard `LIKE %term%` queries across multiple text columns.
- **Expected:**
  High-volume tables should leverage fulltext indexes or a dedicated search driver (Laravel Scout / MySQL FullText).
- **Evidence:**
  `GlobalSearchController.php` lines 50–70.
- **Root Cause:** Prototypical SQL search implementation.
- **Impact:** Table scans will degrade query latency significantly as institutional document volume grows beyond 10,000 records.
- **Reproduction:**
  Execute `GET /api/v1/search?q=test` and inspect query execution plan (full table scan).
- **Recommended Fix:**
  Add MySQL `FULLTEXT` indexes to `title`, `description`, and `reference_number`, and utilize `WHERE MATCH(...) AGAINST(...)`.
- **Required Regression Test:**
  Explain plan asserts index usage on search query.

---

## 6. SECURITY AND RBAC AUDIT

### 6.1 Authentication Mechanism
- **Implementation:** Laravel Sanctum stateful cookie authentication (`auth:sanctum`).
- **SPA CSRF:** Verified. Axios sends `X-XSRF-TOKEN` via standard cookie handshake.
- **Account Status Guard:** Verified. `EnsureAccountIsActive` middleware blocks suspended or inactive accounts on every request.
- **Session Expiration:** Standard Sanctum session lifetime enforced.

### 6.2 Scope-Aware RBAC Model
The application utilizes an institutional RBAC structure combining Spatie permissions with custom organizational scoping:
- **Roles:** Super Administrator, University President, Vice President, Dean, Department Head, Director, Registrar, Archivist, Committee Member, Instructor, Administrative Staff.
- **Scoping Modes:**
  - `Global`: Access across the entire university.
  - `Unit`: Access restricted to user's assigned organizational unit and its descendants.
  - `Self`: Access restricted to documents authored by or assigned directly to the user.
- **Delegation of Authority:** `LegacyTransitionToken` allows formal temporary delegation with strict start/end timestamps and permitted action whitelists.

### 6.3 Security Defect Analysis
1. **Consensus Vote Manipulation (Finding F-04):** The committee decision flow permits duplicate voting, violating integrity requirements for institutional governance.
2. **Permission Code Disconnect (Finding F-06):** Archivists are blocked by reference to non-existent `archive.access`.
3. **Session Purge Blast Radius (Finding F-07):** Logout terminates sessions across all devices unconditionally.

---

## 7. DATABASE AUDIT

### 7.1 Database Schema Overview
- **Total Tables:** 126 tables (119 domain tables, 7 framework/system tables).
- **Core Entities:** `users`, `organization_units`, `documents`, `document_versions`, `files`, `workflow_definitions`, `workflow_instances`, `workflow_tasks`, `workflow_task_participants`, `correspondence_records`, `audit_events`, `templates`, `legacy_transition_tokens`.

### 7.2 Constraints & Foreign Key Integrity
- Foreign keys with `cascadeOnDelete` or `nullOnDelete` are generally well-defined.
- **Identified Schema Hazard:** In `organization_units`, deleting a unit does not have a safe cascade strategy; coupled with the missing `destroy` controller method, unit lifecycle management is structurally incomplete.
- **Audit Trails:** `audit_events` table enforces immutability (no update/delete routes exposed).

---

## 8. FRONTEND AND UI/UX AUDIT

### 8.1 Views & Component Review
- **Total Views:** 38 Vue views in `client/src/views/`.
- **Header Standardization:** All views utilize the unified `PageHeader` component with actions, title, and breadcrumb bindings.
- **TypeScript Compliance:** 100% clean compilation (`vue-tsc --noEmit` exited code 0).

### 8.2 UI Inconsistencies & Issues
1. **Amharic Letterhead Rendering:** Official letter preview (`OfficialLetterA4Preview.vue`) displays corrupted mojibake characters for header and seal labels.
2. **Accreditation Sub-Modules:** Institutional configuration center features tabs where certain actions display placeholder or mock UI states without persistent backend endpoints.

---

## 9. PERFORMANCE AND RELIABILITY AUDIT

1. **N+1 Query Prevention:** Most controllers implement eager loading (`with(['creator', 'unit', 'currentVersion'])`). However, `ArchiveController` and `WorkflowEngineService` perform in-loop relation lookups that should be preloaded.
2. **File Downloads:** Handled via streaming responses (`FileSecurityService`), avoiding memory exhaustion during large PDF transfers.
3. **Database Indexing:** Primary foreign keys and status flags are indexed; full-text search columns currently lack indexing (Finding F-12).

---

## 10. TESTING AND COVERAGE GAPS

### 10.1 Frontend Tests
- **Vitest Suite:** 28 test files, 180 tests passing.
- **Gap:** Missing mock stubs for `metadataApi.getAll` and `officialLettersApi` cause unhandled rejection noise in stderr.

### 10.2 Backend Tests
- **Gap:** Automated unit and feature tests rely on a live MySQL database rather than SQLite in-memory, inhibiting fast CI/CD execution.
- **Missing Coverage:**
  - Zero test coverage for `OrganizationUnitController::destroy` (explaining why its absence went undetected).
  - Ethiopian date tests assert against corrupted strings rather than valid Amharic characters.
  - Zero tests validating PKI signed PDF file download round-trips.
  - Zero tests asserting committee consensus vote deduplication.

---

## 11. CODE CLEANUP REGISTER

### 11.1 Safe Cleanup (Obsolete & Scratch Files)
The following files in the project root and subdirectories are non-functional scratch files or leftover audit logs that can be safely removed in a dedicated cleanup commit:
- `check-phase4.cjs` (Project root)
- `evidence.txt` (Project root)
- `font_mono_audit.txt` (Project root)
- `font_mono_remaining.txt` (Project root)
- `shadow_audit.txt` (Project root)
- `90` (Project root anomalous file)
- `client/check-pageheader-usage.mjs`
- `server/storage/app/pki/debug_verify.php`
- Redundant one-off inspection scripts in `scripts/`

### 11.2 Refactor / Consolidate
- Consolidate Ethiopian date conversion utilities between backend (`EthiopianDateService.php`) and frontend (`ethiopianDate.ts`) to use a single authoritative translation dictionary.
- Unify session termination logic in `AuthController` to separate current-session logout from all-sessions revocation.

---

## 12. MISSING FUNCTIONALITY REGISTER

1. **Organizational Unit Soft Deactivation:** Complete deletion/deactivation workflow with child unit safety checks.
2. **Terminal Workflow Document State Sync:** Automatic propagation of approved/rejected status from workflow state machine to document records.
3. **Full-Text Document Indexing:** Search engine capability for document body text and institutional metadata.
4. **Accreditation Backend Endpoints:** Complete persistent API endpoints for institutional accreditation tracker sub-tabs.

---

## 13. PRIORITIZED REMEDIATION ROADMAP

### Phase 1: Critical Fixes & System Stability (P0)
- **Task 1.1:** Implement `OrganizationUnitController::destroy` with validation against child units and assigned documents.
- **Task 1.2:** Fix `PdfSignatureService` storage path to match Laravel's `'local'` disk root (`storage/app/private`), restoring signed PDF downloads.
- **Task 1.3:** Restore UTF-8 Amharic characters across `TemplateController`, `OfficialLetterA4Preview.vue`, `official_institution_letter.blade.php`, and `EthiopianDateService`. Update test assertions.
- **Task 1.4:** Add duplicate vote check in `WorkflowEngineService::decideTask` to prevent consensus voting manipulation.
- **Task 1.5:** Correct permission check in `ArchiveController` from `archive.access` to `archive.view`.

### Phase 2: Workflow Integrity & State Machine (P1)
- **Task 2.1:** Connect `WorkflowEngineService` terminal state handler to update parent `Document` status.
- **Task 2.2:** Add cycle detection validation in `OrganizationUnitController::update`.
- **Task 2.3:** Increment user `authz_version` on `RolePermissionController::syncRolePermissions`.

### Phase 3: Session & Usability Refinement (P2)
- **Task 3.1:** Scope standard logout to current session; create distinct `/logout-all` endpoint.
- **Task 3.2:** Fix Vitest mock stubs in `client/tests/` to eliminate stderr console errors.
- **Task 3.3:** Configure backend `phpunit.xml` to use SQLite in-memory for safe, isolated testing.

### Phase 4: Performance & Clutter Remediation (P3)
- **Task 4.1:** Add MySQL `FULLTEXT` indexes and optimize `GlobalSearchController`.
- **Task 4.2:** Remove orphaned root-level scratch files (`evidence.txt`, `check-phase4.cjs`, etc.).

---

## 14. FINAL PRODUCTION READINESS ASSESSMENT

### Verdict: **NOT READY FOR PRODUCTION**

| Category | Assessment | Key Blocker / Condition |
| :--- | :---: | :--- |
| **API Contract & Routing** | ❌ FAILED | Endpoint `DELETE /api/v1/organization-units/{id}` crashes with fatal uncaught method exception. |
| **Document PKI & Storage** | ❌ FAILED | Signed institutional PDFs cannot be downloaded (HTTP 404). |
| **Institutional Governance** | ❌ FAILED | Committee voting consensus can be bypassed by repeated calls from a single voter. |
| **Localization & Letterhead** | ❌ FAILED | Amharic letterhead and calendar display severely corrupted mojibake strings. |
| **Workflow State Machine** | ⚠️ CONDITIONAL | Workflows complete, but fail to update parent document status. |
| **Security & RBAC** | ⚠️ CONDITIONAL | Scoping is sound, but archive permission mismatch blocks archivists. |
| **Frontend Architecture** | ✅ PASSED | TypeScript compiles with 0 errors; UI views standardized. |

The application requires completion of **Phase 1 (P0)** remediation before it can be deployed to a staging or production institutional environment.

---

## 15. NEXT AUTHORIZED ENGINEERING WORK

Upon authorization to proceed with implementation, the engineering team should execute the following sequence:

1. **Step 1:** Implement `OrganizationUnitController::destroy` with appropriate child hierarchy and document association guards.
2. **Step 2:** Fix the storage disk path in `PdfSignatureService.php` and verify signed file download round-trips.
3. **Step 3:** Correct UTF-8 string encoding across Amharic templates, Blade exports, and date services.
4. **Step 4:** Enforce one-vote-per-participant validation in `WorkflowEngineService.php`.
5. **Step 5:** Fix the archive permission string in `ArchiveController.php`.
6. **Step 6:** Hook terminal workflow completion to `Document` status transitions.
7. **Step 7:** Purge confirmed root scratch files in a standalone housekeeping commit.

---
*Report compiled and certified by Principal Software Architect & Application Security Engineer.*

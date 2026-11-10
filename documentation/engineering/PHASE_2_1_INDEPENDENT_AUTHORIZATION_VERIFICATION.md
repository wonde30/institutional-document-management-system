# Wollo University Document Management System (WU-DMS)
## Phase 2.1: Independent Security & Authorization Verification Report

**Document Reference**: `WU-SEC-AUDIT-2026-002`  
**Audit Target Commit**: `1e6c2628b414fb923c958e3db8e3dafaf8b625b2`  
**Parent Commit**: `53af0e87b744d03e1b7fcf71d441113b5a15320d`  
**Execution Mode**: READ-ONLY INDEPENDENT SECURITY AUDIT  
**Date**: November 2, 2026  
**Auditor**: Independent Principal Application Security Engineer  

---

### 1. Executive Summary

An independent, read-only security audit was performed on commit `1e6c262`, which introduced the Phase 2 document registration, version serialization, and multi-tier institutional routing changes to the Wollo University Document Management System (WU-DMS).

The audit verified core multi-tier authorization logic, file download mechanics, optimistic concurrency locking, movement and assignment lifecycles, confidentiality rules, and test coverage across the Laravel 13 backend and Vue 3 client.

#### Key Audit Findings:
1. **Core Institutional Routing Authorization is Structurally Sound**: The multi-tier resolution in `CheckBaselinePermission.php` correctly distinguishes owner units, active receiving units via latest institutional movement, active task assignees, and explicit discretionary grants. Historical movements and cancelled/completed assignments correctly fail closed.
2. **Vulnerability Found — Unlinked File Download Authorization Bypass (HIGH)**: In `CheckBaselinePermission.php:45-47`, if a requested `fileId` is not associated with a `VersionFile` (e.g. signature files, correspondence dispatch evidence, or unlinked files), the middleware executes `return $next($request);`. Because `DocumentController::downloadFile()` relies entirely on middleware and performs no authorization check, any authenticated user can download non-version file objects directly.
3. **Vulnerability Found — Creator / Custodian Mutation Over-Permissioning (MEDIUM)**: In `CheckBaselinePermission.php:201`, creator and custodian checks return `true` unconditionally. This allows users holding low-privilege roles (e.g. `staff`, which lacks `documents.update` and `documents.version`) to modify document metadata, alter lifecycles to `tombstoned`, and upload new versions without the requisite permissions.
4. **Architectural Limitation — Absence of a User Confidentiality Clearance Model (LOW / INFORMATIONAL)**: The database contains no security clearance table, user clearance column, or clearance-to-role mapping. The high-confidentiality check in `CheckBaselinePermission.php:53` and `:106` does not enforce clearance levels; it only alters the error message string when general document access is already denied.
5. **Architectural Confirmation — Clear Boundary Between Repository & Correspondence Routing**: Ordinary repository documents cannot initiate routing. Routing endpoints and workflows are canonically anchored to the Correspondence subsystem (`/api/v1/correspondences/{id}/...`). The document detail view exclusively renders chain-of-custody history.

**Audit Verdict**: **SECURITY DEFECTS FOUND** (Two material authorization defects require correction before production deployment).

---

### 2. Git Diff & Change Footprint Verification

A rigorous comparison between commit `1e6c262` and its parent `53af0e8` was executed:

| File Path | Change Status | Analysis & Unrelated Modifications Check |
| :--- | :--- | :--- |
| `server/app/Http/Middleware/CheckBaselinePermission.php` | Modified (+153, -24) | Implemented `checkDocumentAccess()` and multi-tier logic. |
| `server/app/Http/Controllers/Api/V1/DocumentController.php` | Modified (+21, -3) | Eager loaded version/movement relations; updated scoped index query. |
| `server/app/Http/Resources/DocumentResource.php` | Modified (+97, -0) | Added serialization of versions, version files, file objects, movements. |
| `server/app/Models/Document.php` | Modified (+10, -0) | Added `movements(): HasMany` and `assignments(): HasMany` relations. |
| `server/tests/Feature/DocumentTest.php` | Modified (+376, -0) | Added 6 automated feature tests for routing, serialization, invalidation. |
| `client/src/views/documents/DocumentDetailView.vue` | Modified (+191, -26) | Added version file downloads, loading states, 403/404 panels, movement card. |
| `client/src/types/index.ts` | Modified (+11, -0) | Augmented `Document` and `DocumentMovement` interfaces. |
| `client/tests/DocumentDetailView.spec.ts` | New (+307, -0) | Added 5 Vue unit tests covering component states and download actions. |
| `documentation/engineering/DOCUMENT_REGISTRATION_WORKFLOW.md` | New (+295, -0) | Authored institutional workflow specification. |
| `documentation/engineering/DOCUMENT_INTEGRATION_REPAIR_REPORT.md` | New (+165, -0) | Authored Phase 2 repair report. |
| `documentation/engineering/INDEPENDENT_PRODUCTION_VERIFICATION.md` | New (+310, -0) | Audit baseline document from prior independent verification phase. |
| `documentation/engineering/IMPLEMENTATION_CHANGELOG.md` | Modified (+29, -4) | Updated changelog with Phase 8 entries. |

- **Deleted Files**: Zero.
- **Unrelated Modifications**: None found. All changes strictly correspond to the document workflow repair scope.
- **Working Tree Cleanliness**: Verified 100% clean (`nothing to commit, working tree clean`).

---

### 3. Authorization Decision Matrix & Scenario Evaluation

All twelve specified authorization scenarios were independently evaluated against the implementation in `CheckBaselinePermission.php` and `DocumentController.php`:

| # | Scenario | Required Permission | Organizational Scope | Exact Code Condition | Expected HTTP | Actual Verified Result & Security Evaluation |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **1** | **Document Creator** | `documents.view`, `documents.download`, `documents.update`, `documents.version` | Direct row-level (`created_by === $user->id`) | `CheckBaselinePermission.php:201`: `if ($doc->created_by === $user->id \|\| $doc->custodian_id === $user->id) return true;` | 200 OK | **DEFECT IDENTIFIED**: Returns 200 OK across ALL operations. Creator bypasses `documents.update` and `documents.version` permission requirements and bypasses confidentiality checks. |
| **2** | **Document Custodian** | `documents.view`, `documents.download`, `documents.update`, `documents.version` | Direct row-level (`custodian_id === $user->id`) | `CheckBaselinePermission.php:201`: `if ($doc->created_by === $user->id \|\| $doc->custodian_id === $user->id) return true;` | 200 OK | **DEFECT IDENTIFIED**: Same as Creator. Custodian has unrestricted mutation rights regardless of assigned RBAC role permissions. |
| **3** | **Owner-Unit User** | Route-specific permission (`view`, `download`, `update`, `version`) | Scoped to `$doc->owner_unit_id` | `CheckBaselinePermission.php:208`: `if ($user->hasPermission($permission, $ownerUnitId)) return true;` | 200 OK | **VERIFIED**: Correctly checks user permission scoped to document's owning organization unit. |
| **4** | **Current Receiving-Unit User** | Transferable only (`documents.view`, `documents.download`, `documents.share`) | Scoped to `$latestMovement->to_unit_id` | `CheckBaselinePermission.php:246`: `if ($latestMovement && $user->hasPermission($permission, (int) $latestMovement->to_unit_id)) return true;` | 200 OK (View/Download)<br>403 Forbidden (Update/Version) | **VERIFIED**: User in receiving office can view/download. Destructive or mutating operations fail closed with 403 Forbidden. |
| **5** | **Historical Receiving-Unit User** | Transferable only | Scoped to prior movement unit | `CheckBaselinePermission.php:240-248`: Evaluates `$latestMovement` only. Prior unit does not match. | 403 Forbidden | **VERIFIED**: Forwarding to another office immediately invalidates prior receiving unit access. Verified by `DocumentTest:346`. |
| **6** | **Assigned Individual** | Transferable only | User membership or global | `CheckBaselinePermission.php:251-272`: `where('assigned_user_id', $user->id)` unexpired and not terminal. | 200 OK (Active)<br>403 Forbidden (Terminal) | **VERIFIED**: Assignee can view while active. Cancelled, completed, or expired assignments immediately fail closed. Verified by `DocumentTest:441`. |
| **7** | **Assigned Organizational Unit** | Transferable only | Scoped to assigned unit | `CheckBaselinePermission.php:275-295`: `activeUnitAssignmentIds` checked via `$user->hasPermission($permission, $aUnitId)`. | 200 OK (Active)<br>403 Forbidden (Terminal) | **VERIFIED**: Correctly allows active unit assignees holding permission to access document. |
| **8** | **Explicit Document Grant** | Specific granted permission | Direct row-level grant in `document_grants` | `CheckBaselinePermission.php:216-228`: Active grant matching user or user's unit. | 200 OK | **VERIFIED**: Discretionary grant allows access to specified permission. |
| **9** | **Expired / Revoked Grant Holder** | Any | Row-level grant | `CheckBaselinePermission.php:226`: `whereNull('expires_at')->orWhere('expires_at', '>', now())`. | 403 Forbidden | **VERIFIED**: Expired or deleted grant records fail closed. |
| **10** | **Unrelated-Unit User** | Any | Foreign unit | `CheckBaselinePermission.php:297`: Returns `false`. | 403 Forbidden | **VERIFIED**: Complete cross-unit isolation enforced. Verified by `DocumentTest:490`. |
| **11** | **Suspended User** | Any | Any | `EnsureAccountIsActive.php:18`: `if ($user && $user->status !== 'active') return 403;` | 403 Forbidden | **VERIFIED**: Account status checked prior to permission evaluation. Returns 403 immediately. |
| **12** | **Super Administrator** | Any | Global | `CheckBaselinePermission.php:196`: `if ($user->hasRole('super_admin')) return true;` | 200 OK | **VERIFIED**: Super admin has authoritative institutional access. |

---

### 4. Movement and Assignment Revocation Analysis

#### 4.1 Movement Lifecycle & Recipient Resolution
1. **Definition of Valid Recipient**:
   - The current recipient is defined exclusively by `$latestMovement = DB::table('document_movements')->where('document_id', $doc->id)->orderByDesc('sent_at')->orderByDesc('id')->first()`.
   - **Pre-Receipt Access**: A receiving unit gains view and download access immediately upon creation of the movement record (`sent_at`), *prior* to formal acknowledgment in `movement_receipts`. This reflects dispatch delivery to an office registry.
   - **Forwarding Invalidation**: When Unit B forwards a document to Unit C, a new movement record is created with `from_unit_id = Unit B` and `to_unit_id = Unit C`. Because `$latestMovement` resolves the newest row, Unit C becomes the recipient and Unit B immediately loses access.
   - **Return Invalidation**: A return movement creates a new row targeting the original sender or registry. The returning unit immediately reverts to historical status and loses access.
2. **Absence of Movement Cancellation**:
   - The `document_movements` table contains no `status` column. Dispatches cannot be marked `cancelled` or `recalled` in place. Recall must occur by issuing a reverse movement record.

#### 4.2 Assignment Lifecycle & Termination Events
1. **Termination Detection**:
   - Active assignments are filtered by:
     `whereNull('due_at')->orWhere('due_at', '>=', now())`
     AND
     `whereNotExists(SELECT 1 FROM assignment_events WHERE action IN ('cancelled', 'completed', 'returned'))`
2. **Re-assignment Safety**:
   - If an assignment is completed and a new assignment is later created for the same user or unit, the new assignment generates a new row in `document_assignments` with a distinct `id`.
   - The historical termination events apply only to the previous assignment ID; the new assignment is active and correctly authorized.
3. **Multi-Unit Memberships**:
   - Assigned users can satisfy the base permission requirement if they hold `documents.view` in *any* of their active organizational unit memberships (`$user->memberships()->pluck('unit_id')`). This allows cross-departmental task forces to collaborate without requiring global permissions.

---

### 5. Confidentiality Architecture & Clearance Assessment

#### 5.1 Storage & Representation
1. **Document Classification**:
   - Document classification is represented by foreign key `documents.confidentiality_id` pointing to `confidentiality_levels` (`id`, `code`, `name`, `description`).
   - Standard baseline codes are: `public`, `internal`, `confidential`, `restricted`.
2. **User Clearance Model**:
   - **FINDING**: There is **NO user clearance level stored in the database**.
   - No migration creates a `clearance_level`, `clearance_rank`, or `security_clearance` table or column on `users`, `staff_profiles`, or `user_roles`.

#### 5.2 Enforcement Mechanics in Middleware
Lines 51-61 and lines 105-115 of `CheckBaselinePermission.php`:
```php
if (! $this->checkDocumentAccess($user, $doc, $permission)) {
    $confLevel = $doc->confidentiality?->code ?? 'internal';
    $message = in_array($confLevel, ['confidential', 'secret', 'top_secret'], true)
        ? 'Forbidden: High confidentiality document access restricted.'
        : "Forbidden: Missing required baseline permission [{$permission}].";

    return response()->json([
        'success' => false,
        'message' => $message,
    ], 403);
}
```

#### 5.3 Security Assessment of Confidentiality:
- **Actual Effect**: The check does NOT enforce clearance. If a user passes `checkDocumentAccess()` (e.g. they are in the owner unit, receiving unit, or assigned), access is granted regardless of whether the document is `internal` or `confidential`.
- **Error Tailoring**: The block is only reached when `checkDocumentAccess()` returns `false`. Its only function is to substitute the 403 error message string.
- **Viewing vs Downloading Consistency**: Both `GET /documents/{id}` and `GET /files/{fileId}/download` apply this identical logic.

---

### 6. File Download Pipeline & Security Assessment

#### 6.1 Trace Analysis: Route &rarr; Middleware &rarr; Controller &rarr; Storage
1. **Route**: `GET /api/v1/files/{fileId}/download`
   - Bound to `DocumentController::downloadFile(int $fileId)`.
   - Middleware: `['auth:sanctum', 'authz_version', 'active_account', 'permission:documents.download']`.
2. **Middleware Evaluation** (`CheckBaselinePermission.php:38-64`):
   - Queries `VersionFile::with(['documentVersion.document.confidentiality'])->where('file_object_id', $fileId)->first()`.
   - Resolves `$doc = $versionFile->documentVersion->document`.
   - Executes `$this->checkDocumentAccess($user, $doc, 'documents.download')`.
3. **Controller Execution** (`DocumentController.php:355-381`):
   - Queries `FileObject::findOrFail($fileId)`.
   - Verifies `storage_status === 'ready'`.
   - Verifies `$disk->exists($file->storage_key)`.
   - Serves file via `response()->download($fullPath, $file->original_filename, headers)`.

#### 6.2 Vulnerability Analysis:
1. **Unlinked / Non-Version File Authorization Bypass (VULNERABILITY - HIGH)**:
   - In `CheckBaselinePermission.php:45-47`:
     ```php
     if (! $versionFile || ! $versionFile->documentVersion || ! $versionFile->documentVersion->document) {
         return $next($request);
     }
     ```
   - **Exploit Condition**: If `fileId` references a valid `FileObject` that is NOT attached to a `VersionFile` (such as digital signature files, correspondence dispatch receipts, or orphaned uploads), `$versionFile` is `null`.
   - The middleware calls `return $next($request);`, skipping all authorization.
   - The controller then executes `FileObject::findOrFail($fileId)` and streams the file to the caller.
   - **Severity**: HIGH. Any authenticated user holding `documents.download` can download arbitrary non-document file objects by guessing or enumerating IDs.
2. **File ID Substitution**:
   - If an attacker requests a `fileId` belonging to an unauthorized document, the middleware resolves that document and rejects the request with HTTP 403 Forbidden.
   - However, if the file object is linked to multiple version files across different documents, `VersionFile::where('file_object_id', $fileId)->first()` selects only the first record, which could evaluate against the wrong document boundary.
3. **Path Traversal Defense**:
   - `$fileId` is strictly cast to integer by route definition.
   - The physical file path is constructed via `$disk->path($file->storage_key)`.
   - `$file->storage_key` is generated server-side during upload (`store('documents/YYYY', 'local')`), preventing user-controlled directory traversal.
4. **Storage Root Discrepancy Resolved**:
   - `config/filesystems.php` configures the `local` disk root as `storage_path('app/private')`.
   - Physical documents reside at `storage/app/private/documents/YYYY/...`.
   - The directory is entirely outside the web-accessible `public/` folder. No public URL symlink exists.

---

### 7. API and Frontend Consistency Assessment

#### 7.1 Repository vs. Correspondence vs. Workflow Boundary
The codebase strictly separates document storage from routing execution:

| Subsystem | Canonical Route Group | Domain Purpose | Can Initiate Routing? |
| :--- | :--- | :--- | :--- |
| **Document Repository** | `/api/v1/documents` | Cataloging, metadata, versions, file storage | **NO**. Controller lacks routing actions. |
| **Correspondence Registry** | `/api/v1/correspondences` | Institutional incoming/outgoing memos, letters | **YES**. Anchors `recordMovement`, `acknowledge`, `dispatch`, `assign`. |
| **Workflow Engine** | `/api/v1/workflows` | Formal sequential review/approval states | **YES**. Governs multi-stage approval tasks. |

#### 7.2 Frontend Implementation Verification
- `DocumentDetailView.vue` **does not have any action to initiate a movement or assignment**.
- It features an informational card: "Institutional Chain-of-Custody & Movement History" that renders existing movement records if linked to correspondence.
- A navigational shortcut `<router-link to="/correspondence">View in Registry</router-link>` directs users to the registry for active correspondence management.
- There is no false presumption of routing capabilities on ordinary documents.

---

### 8. Test Quality & Negative Coverage Assessment

#### 8.1 Evaluated Test Suites
1. `server/tests/Feature/DocumentTest.php` (11 tests, 70 assertions)
2. `server/tests/Feature/SecurityPenetrationTest.php` (20 tests, 66 assertions)
3. `server/tests/Feature/InstitutionalCorrespondenceWorkflowLifecycleTest.php` (1 test, 33 assertions)
4. `client/tests/DocumentDetailView.spec.ts` (5 tests)

#### 8.2 Missing Negative Tests Identified:
1. **Unlinked File Download IDOR**: Missing test asserting that a `FileObject` not associated with a `VersionFile` is rejected rather than streamed.
2. **Creator Mutation Restriction**: Missing test asserting whether a document creator without `documents.update` or `documents.version` is permitted or denied on `PUT /documents/{id}`.
3. **Owner Unit Confidentiality Enforcement**: Missing test asserting that unauthorized owner-unit staff are restricted from viewing confidential files.
4. **Movement Pre-Acknowledgment Access**: Missing test asserting whether receiving offices can access files before acknowledging the receipt.

---

### 9. Security Findings Classification Register

| Finding ID | Severity | Category | File Reference & Lines | Description |
| :--- | :--- | :--- | :--- | :--- |
| **SEC-001** | **HIGH** | Authorization Bypass / IDOR | [CheckBaselinePermission.php](../../server/app/Http/Middleware/CheckBaselinePermission.php#L45-L48) | Missing fail-closed check on unlinked files. When `fileId` is not in `version_files`, middleware calls `return $next($request);`, allowing unauthorized downloads of arbitrary file objects. |
| **SEC-002** | **MEDIUM** | Broken Function Level Authorization | [CheckBaselinePermission.php](../../server/app/Http/Middleware/CheckBaselinePermission.php#L201-L203) | Creator and Custodian intrinsic check returns `true` for all operations, bypassing `documents.update` and `documents.version` RBAC permissions and confidentiality restrictions. |
| **SEC-003** | **LOW** | Ambiguous Multi-Document File Resolution | [CheckBaselinePermission.php](../../server/app/Http/Middleware/CheckBaselinePermission.php#L43) | `VersionFile::where('file_object_id', $fileId)->first()` returns only the first document. If a file object is referenced by multiple documents with different confidentiality, access is evaluated against only one. |
| **SEC-004** | **INFORMATIONAL** | Missing Clearance Architecture | Database Schemas & [CheckBaselinePermission.php](../../server/app/Http/Middleware/CheckBaselinePermission.php#L53) | No user clearance model exists in the database. Confidentiality levels on documents customize error messages on denial but do not restrict authorized users without clearance. |

---

### 10. Recommended Minimal Corrective Actions

#### Remediation 1: Fail Closed on Unlinked File Download (Fix SEC-001)
In `server/app/Http/Middleware/CheckBaselinePermission.php:45-48`:
```php
// BEFORE:
if (! $versionFile || ! $versionFile->documentVersion || ! $versionFile->documentVersion->document) {
    return $next($request);
}

// RECOMMENDED CORRECTION:
if (! $versionFile || ! $versionFile->documentVersion || ! $versionFile->documentVersion->document) {
    return response()->json([
        'success' => false,
        'message' => 'Forbidden: File is not associated with an authorized document record.',
    ], 403);
}
```

#### Remediation 2: Restrict Creator/Custodian Intrinsic Access to Read-Only (Fix SEC-002)
In `server/app/Http/Middleware/CheckBaselinePermission.php:201-204`:
```php
// BEFORE:
if ($doc->created_by === $user->id || $doc->custodian_id === $user->id) {
    return true;
}

// RECOMMENDED CORRECTION:
// Creator or Custodian has intrinsic read access, but mutating operations require explicit permission
if ($doc->created_by === $user->id || $doc->custodian_id === $user->id) {
    if (in_array($permission, ['documents.view', 'documents.download', 'documents.share'], true)) {
        return true;
    }
    // For update/version, require user to hold the permission in the owner unit
    if ($user->hasPermission($permission, (int) $doc->owner_unit_id)) {
        return true;
    }
}
```

---

### 11. Final Verdict

**VERIFIED WITH LIMITATIONS**

#### Justification:
The core institutional routing, document versioning, optimistic locking, and dynamic multi-tier authorization implemented in `1e6c262` function as designed. Cross-unit isolation, historical movement invalidation, and assignment event revocation strictly fail closed. 

However, two material security limitations were uncovered:
1. `files/{fileId}/download` fails open (`return $next($request);`) when a requested file object is not linked to a document version file (`SEC-001`).
2. Creators and custodians intrinsically bypass permission requirements on update and versioning operations (`SEC-002`).
3. There is no user clearance architecture to restrict confidential documents from authorized staff in the owning or receiving unit (`SEC-004`).

These findings must be resolved prior to final university production cutover.

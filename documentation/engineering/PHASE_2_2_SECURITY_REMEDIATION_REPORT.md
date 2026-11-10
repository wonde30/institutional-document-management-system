# Wollo University Document Management System (WU-DMS)
## Phase 2.2: Security Vulnerability Remediation & Verification Report

**Document Reference**: `WU-ENG-SEC-2026-003`  
**Classification**: Institutional Engineering & Security Standard  
**Target Commit Base**: `1e6c2628b414fb923c958e3db8e3dafaf8b625b2`  
**Date**: November 2, 2026  
**Auditor & Remediation Lead**: Principal Security Engineer & Senior Laravel Authorization Architect  
**Remediation Status**: **REMEDIATED & VERIFIED** (All 16 security regression tests passed; 185/185 full backend suite passed)

---

### 1. Executive Summary

Following the independent security audit conducted in **Phase 2.1** (`WU-SEC-AUDIT-2026-002` / `PHASE_2_1_INDEPENDENT_AUTHORIZATION_VERIFICATION.md`), two material authorization vulnerabilities were identified in the Phase 2 document integration and institutional routing implementation:

1. **SEC-001 (HIGH)**: *Unlinked File Download Authorization Bypass (IDOR)*. In `CheckBaselinePermission.php`, if a requested `fileId` was not linked to a document version file, the middleware executed `return $next($request);`. Because `DocumentController::downloadFile()` lacked an independent authorization check, arbitrary non-version file objects could be downloaded by any authenticated user.
2. **SEC-002 (MEDIUM)**: *Creator & Custodian Mutation Over-Permissioning*. In `CheckBaselinePermission.php`, creator and custodian checks evaluated unconditionally to `true`. Low-privilege users (such as standard staff members) could bypass RBAC permission checks (`documents.update`, `documents.version`) to alter metadata or upload revisions on documents they created or were designated custodian of.
3. **SEC-004 (INFORMATIONAL / ARCHITECTURAL LIMITATION)**: *Absence of a User Security Clearance Model*. As confirmed in Phase 2.1, the database lacks a user clearance level table or column. The high-confidentiality check in middleware provides tailored 403 error strings when general access is denied, but does not enforce hierarchical clearances for authorized unit members.

This report documents the surgical, controlled remediation of `SEC-001` and `SEC-002`, the implementation of comprehensive defense-in-depth authorization, and the verification of all security boundaries across 16 regression test scenarios and the complete 185-test backend suite.

---

### 2. Architectural Root-Cause Analysis & Scope of Remediations

```mermaid
flowchart TD
    subgraph Client ["Client Request"]
        A["GET /api/v1/files/{fileId}/download"]
        B["PUT /api/v1/documents/{id}"]
        C["POST /api/v1/documents/{id}/versions"]
    end

    subgraph Middleware ["CheckBaselinePermission Middleware"]
        D["Check FileObject Exists (404 if missing)"]
        E["Document::resolveForFile(fileId)"]
        F{"Resolved to Document?"}
        G["403 Forbidden (Fail-Closed)"]
        H["doc->allowsUser(user, permission)"]
        I{"allowsUser()?"}
        J["403 Forbidden"]
    end

    subgraph Controller ["DocumentController::downloadFile() Defense-in-Depth"]
        K["Document::resolveForFile(fileId)"]
        L{"Resolved & allowsUser()?"}
        M["403 Forbidden"]
        N["Verify storage_status == ready"]
        O["Stream Binary File with SHA-256"]
    end

    A --> D
    D --> E
    E --> F
    F -- No (Unlinked / Orphaned) --> G
    F -- Yes --> H
    H --> I
    I -- No --> J
    I -- Yes --> K
    K --> L
    L -- No --> M
    L -- Yes --> N
    N --> O

    B --> H
    C --> H
```

#### 2.1 Remediation 1: Fail-Closed File Download Authorization (SEC-001)

##### Vulnerability Mechanism
Previously, `CheckBaselinePermission.php` queried `VersionFile::where('file_object_id', $fileId)->first()`. If no version file record existed, the middleware assumed the file was unrelated to version control and returned `$next($request)`. The controller then served the file without re-verifying permissions.

##### Corrective Architecture Implemented
1. **Multi-Attachment Document Resolution Engine (`Document::resolveForFile(int $fileId): ?Document`)**:
   Located directly on `App\Models\Document`, this method resolves the authoritative owning document record across all legitimate institutional file attachment pathways:
   - **Version Primary Files & Attachments**: `VersionFile` &rarr; `DocumentVersion` &rarr; `Document`.
   - **Correspondence Dispatch Proofs & Evidence**: `CorrespondenceDispatch` (`evidence_file_id`) &rarr; `DocumentVersion` &rarr; `Document`.
   - **Digital Signatures & Signed Artifacts**: `DigitalSignature` (`signed_file_id` or `source_file_id`) &rarr; `SignatureRequest` &rarr; `DocumentVersion` &rarr; `Document`.
   - **File Derivatives & Thumbnails**: `FileDerivative` (`result_file_id` &rarr; recursive lookup of `source_file_id`).
   - **Fail-Closed Default**: If the file is unlinked, orphaned, or not associated with an authorized document record, `resolveForFile()` returns `null`.
2. **Fail-Closed Middleware Enforcement (`CheckBaselinePermission.php`)**:
   - First verifies that `FileObject::where('id', $fileId)->exists()`. If missing, immediately returns `404 Not Found`.
   - Invokes `Document::resolveForFile($fileId)`. If `null`, fails closed immediately with HTTP `403 Forbidden`:  
     `"Forbidden: File is not associated with an authorized document record."`
   - Evaluates `$doc->allowsUser($user, 'documents.download')`. If false, returns `403 Forbidden`.
3. **Controller-Level Defense-in-Depth (`DocumentController::downloadFile`)**:
   - Independent of middleware execution, the controller re-executes `Document::resolveForFile($fileId)` and `$doc->allowsUser($actor, 'documents.download')` directly before inspecting storage status or streaming bytes.
   - Even if route middleware were bypassed or misconfigured, direct controller invocation strictly fails closed.

#### 2.2 Remediation 2: Creator & Custodian Mutation Over-Permissioning (SEC-002)

##### Vulnerability Mechanism
In `CheckBaselinePermission.php`, the condition:
```php
if ($doc->created_by === $user->id || $doc->custodian_id === $user->id) {
    return true;
}
```
granted unconditional access across all HTTP verbs, bypassing role permissions for `documents.update`, `documents.version`, and `documents.delete`.

##### Corrective Architecture Implemented
1. **Separation of Read-Only vs. Mutating Operations in `Document::allowsUser()`**:
   - Creator and custodian intrinsic privileges are strictly restricted to non-destructive read operations:
     ```php
     $isReadOnlyOp = in_array($permission, ['documents.view', 'documents.download', 'documents.share'], true);
     if ($isReadOnlyOp && ($this->created_by === $user->id || $this->custodian_id === $user->id)) {
         return true;
     }
     ```
   - For mutating and destructive operations (`documents.update`, `documents.version`, `documents.delete`):
     - Creator or custodian status **never grants intrinsic mutation rights**.
     - The user must hold the explicit permission in the document's owner organization unit (`$user->hasPermission($permission, $ownerUnitId)`) or hold an active explicit discretionary grant in `document_grants`.
2. **Strict Institutional Routing Mutation Boundary**:
   - Receiving units on institutional movements and assigned task officers are granted read-only access (`documents.view`, `documents.download`).
   - Mutating operations strictly return `false` for receiving offices and assignees, preserving the owner unit's exclusive authority over document metadata and versions.

---

### 3. File Modification Details

| File Path | Component | Changes Implemented |
| :--- | :--- | :--- |
| `server/app/Models/Document.php` | Domain Model | 1. Added `resolveForFile(int $fileId): ?Document` covering `VersionFile`, `CorrespondenceDispatch`, `DigitalSignature`, and `FileDerivative`.<br>2. Added `allowsUser(?User $user, string $permission): bool` implementing strict read-only creator/custodian checks, owner-unit permission checks, and active grant validation. |
| `server/app/Http/Middleware/CheckBaselinePermission.php` | Security Middleware | 1. Added 404 validation for missing `FileObject`.<br>2. Enforced fail-closed 403 on unlinked files via `Document::resolveForFile($fileId)`.<br>3. Refactored `checkDocumentAccess()` to delegate directly to `$doc->allowsUser($user, $permission)`. |
| `server/app/Http/Controllers/Api/V1/DocumentController.php` | API Controller | Added defense-in-depth authorization check in `downloadFile(int $fileId)` using `Document::resolveForFile()` and `$doc->allowsUser()`. |
| `server/tests/Feature/DocumentSecurityRemediationTest.php` | Test Suite | Created 16 dedicated security regression tests covering fail-closed download boundaries, unlinked files, creator/custodian mutation restrictions, and receiving unit boundaries. |

---

### 4. Verification & Automated Test Results

#### 4.1 Dedicated Security Remediation Test Suite (`DocumentSecurityRemediationTest.php`)

All 16 test cases were executed and passed with 54 assertions:

```text
{"tool":"pest","result":"passed","tests":16,"passed":16,"assertions":54,"duration_ms":6433}
```

| # | Test Method Name | Target Vulnerability | Tested Condition & Boundary | Result |
| :--- | :--- | :--- | :--- | :--- |
| 1 | `test_authorized_document_version_file_download_succeeds` | Baseline Download | Authorized owner-unit user downloads primary version file | **PASSED** (200 OK) |
| 2 | `test_unauthorized_document_version_file_download_is_denied` | Cross-Unit Isolation | User in unrelated unit attempts to download file | **PASSED** (403 Forbidden) |
| 3 | `test_unlinked_file_object_cannot_be_downloaded` | **SEC-001** | Unlinked file object requested by regular staff and super admin | **PASSED** (403 Forbidden) |
| 4 | `test_orphaned_version_file_fails_safely` | **SEC-001** | Version file with non-existent version ID fails closed | **PASSED** (403 Forbidden) |
| 5 | `test_legitimate_correspondence_dispatch_attachment_access_works_only_for_authorized_users` | **SEC-001** | Evidence file linked to correspondence dispatch is accessible only to authorized document users | **PASSED** (200 / 403) |
| 6 | `test_file_id_substitution_across_documents_fails_closed` | **SEC-001** | User authorized on Document A attempts to download file of Document B | **PASSED** (403 Forbidden) |
| 7 | `test_missing_file_object_fails_safely_with_404` | Error Handling | Non-existent file ID (999999) returns 404 | **PASSED** (404 Not Found) |
| 8 | `test_download_controller_authorization_cannot_be_bypassed_by_direct_invocation` | **SEC-001** | Direct invocation of `DocumentController::downloadFile` without middleware verifies defense-in-depth | **PASSED** (403 Forbidden) |
| 9 | `test_creator_without_documents_update_cannot_update_document` | **SEC-002** | Creator lacking `documents.update` attempts metadata update | **PASSED** (403 Forbidden) |
| 10 | `test_custodian_without_documents_update_cannot_update_document` | **SEC-002** | Custodian lacking `documents.update` attempts metadata update | **PASSED** (403 Forbidden) |
| 11 | `test_creator_without_documents_version_cannot_upload_new_version` | **SEC-002** | Creator lacking `documents.version` attempts version upload | **PASSED** (403 Forbidden) |
| 12 | `test_custodian_without_documents_version_cannot_upload_new_version` | **SEC-002** | Custodian lacking `documents.version` attempts version upload | **PASSED** (403 Forbidden) |
| 13 | `test_authorized_creator_with_owner_unit_permission_can_update_and_upload_versions` | **SEC-002** | Creator holding explicit owner-unit permissions updates metadata and uploads version | **PASSED** (200 / 201) |
| 14 | `test_creator_retains_read_and_download_access_even_without_mutating_permissions` | **SEC-002** | Creator preserves intrinsic view and download access | **PASSED** (200 OK) |
| 15 | `test_receiving_unit_users_can_read_but_cannot_update_or_upload_versions` | Institutional Boundary | Receiving office user on movement can view/download but cannot mutate document | **PASSED** (200 / 403) |
| 16 | `test_super_administrator_can_perform_all_document_and_file_operations` | Administrative Access | Super admin retains authoritative access across all actions | **PASSED** (200 / 201) |

#### 4.2 Comprehensive Regression Suite Verification

The full full-stack test suite was executed to guarantee zero unintended regressions across all institutional modules:

| Verification Suite | Target | Executed Tests | Result | Assertions / Duration |
| :--- | :--- | :--- | :--- | :--- |
| **All Backend Feature & Unit Tests** | `php artisan test` | 185 tests | **PASSED (100%)** | 1,094 assertions / 82.7s |
| **Document Integration Tests** | `php artisan test --filter=DocumentTest` | 11 tests | **PASSED (100%)** | 70 assertions / 5.8s |
| **Security Penetration Suite** | `php artisan test --filter=SecurityPenetrationTest` | 20 tests | **PASSED (100%)** | 66 assertions / 6.9s |
| **Dynamic RBAC Suite** | `php artisan test --filter=DynamicRbacTest` | 4 tests | **PASSED (100%)** | 16 assertions / 4.5s |
| **Correspondence Workflow Suite** | `php artisan test --filter=InstitutionalCorrespondenceWorkflowLifecycleTest` | 1 test | **PASSED (100%)** | 33 assertions / 3.6s |
| **Frontend Unit & E2E Suites** | `npm test` | 20 suites, 104 tests | **PASSED (100%)** | 300+ assertions / 5.2s |
| **Frontend TypeScript Type Check** | `npx vue-tsc -b` | Full AST | **PASSED (100%)** | 0 errors / 7.8s |
| **Frontend Production Build** | `npm run build` | Vite SPA | **PASSED (100%)** | 1,931 modules / 745ms |

---

### 5. Architectural Status of SEC-004 (Clearance Model)

As documented in `PHASE_2_1_INDEPENDENT_AUTHORIZATION_VERIFICATION.md`, `SEC-004` represents an **architectural limitation rather than an active regression or vulnerability**.

1. **Current System State**:
   - `confidentiality_levels` categorizes documents (`public`, `internal`, `confidential`, `restricted`).
   - The database contains no user clearance schema (no clearance level column on `users`, `staff_profiles`, or roles).
   - In accordance with project instructions, no arbitrary schema additions or speculative clearance tables were fabricated.
2. **Production Advisory**:
   - If Wollo University institutional policy requires strict hierarchical personnel security clearances (e.g. Proclamation 1152/2019 standards for national security classifications), a formal database migration introducing `user_clearances` and corresponding RBAC policy gates should be scheduled as a dedicated feature phase with university stakeholder alignment.
   - For all standard university documentation, the current owner-unit, receiving-unit, and task-assignment authorization model provides complete, robust institutional isolation.

---

### 6. Remediation Conclusion

The verified vulnerabilities `SEC-001` (Unlinked File Download IDOR) and `SEC-002` (Creator/Custodian Mutation Over-Permissioning) have been **completely resolved**. 

The authorization architecture now enforces:
- **Strict Fail-Closed File Access**: Unlinked or non-document file objects cannot be downloaded by any user or administrator.
- **Defense-in-Depth**: Both middleware and controller layers enforce independent authorization checks.
- **Principle of Least Privilege**: Creators, custodians, and receiving offices are strictly restricted to read operations; metadata updates and version uploads require explicit owner-unit RBAC permissions.
- **Zero Regressions**: 100% of all 185 backend tests and 104 frontend tests pass cleanly.

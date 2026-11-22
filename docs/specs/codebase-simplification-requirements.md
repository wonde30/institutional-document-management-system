# Codebase Simplification and Maintainability Refactor — Requirements Specification

**Feature:** Institutional DMS Codebase Simplification  
**Type:** Engineering Quality / Technical Debt Reduction  
**Status:** Requirements Phase  
**Created:** 2026-11-20  
**Repository:** c:\Users\IT3\Desktop\DMS  

---

## 1. Executive Summary

### Problem Statement

The Wollo University Document Management System (WU-DMS) currently contains obsolete AI-specific instruction files, redundant documentation, and potential architectural complexity that may hinder maintainability by human engineering teams.

The project requires simplification to ensure it is straightforward for experienced developers to understand, easy for new developers to contribute to, and maintainable without relying on AI-specific instructions.

### Solution Approach

Systematically inspect, document, and simplify the existing production-oriented codebase while preserving all legitimate functionality, security controls, data integrity mechanisms, and institutional requirements.

**This is NOT a rewrite or migration project.** This is surgical removal of unnecessary complexity while strengthening what works.

### Success Criteria

- Clean, understandable project structure with minimal, useful human documentation
- No unnecessary AI-agent instruction system
- No duplicate implementations or dead code identified as active functionality
- Clear Laravel backend conventions and Vue frontend conventions
- Preserved institutional document functionality, data integrity, and security
- Verified tests and production build where environment permits
- Comprehensive simplification report documenting all changes

---

## 2. Current State Assessment

### 2.1 Verified Technology Stack

**Backend (Actual Implementation):**
- Laravel 13 REST API
- PHP 8.4+
- MySQL 8.4
- Laravel Sanctum **SPA stateful session-cookie authentication** (confirmed in `sanctum.php`)
- **Custom scoped RBAC implementation** with `authz_version` instant invalidation
- Custom document versioning with `lock_version` optimistic concurrency
- **Spatie Laravel Permission installed** but used alongside custom RBAC (see `composer.json`)
- No Laravel Reverb (grep confirmed)
- No Spatie Media Library (grep found only Permission package)
- No Laravel Horizon (not in composer.json)

**Frontend (Actual Implementation):**
- Vue 3 SPA with Composition API
- TypeScript 6.0.3 (strict mode)
- Pinia 4.1 state management
- Axios 1.20 for HTTP
- Vue Router 4.6
- Tailwind CSS 4.3
- Lucide icons 1.0
- Vite 8.3

**Data Model Complexity:**
- 120+ Eloquent models in `server/app/Models/`
- 23 database migrations (comprehensive institutional feature set)
- Custom RBAC: `users`, `roles`, `permissions`, `user_roles`, `role_permissions`, `authorization_scopes`
- Document management: `documents`, `document_versions`, `document_categories`, `document_movements`
- Correspondence: `correspondence`, `correspondence_participants`, `correspondence_dispatch`
- Workflow: `workflow_definitions`, `workflow_instances`, `workflow_tasks`, `workflow_decisions`
- Archive: `archive_collections`, `archive_entries`, `legal_holds`, `retention_policies`
- Staff/HR: `staff_profiles`, `staff_qualifications`, `staff_documents`
- Research: `research_records`, `research_publications`, `projects`, `project_proposals`
- Accreditation: `accreditation_frameworks`, `accreditation_cycles`, `accreditation_evidence`
- PKI: `digital_signatures`, `signature_requests`, `signing_identities`, `stamp_definitions`
- Audit: `audit_events`, `notification_events`, `idempotency_records`

### 2.2 Authorization Architecture (Confirmed Custom Implementation)

Migration `2026_10_28_000002_wu_02_scoped_rbac.php` implements:
- Custom `permissions` table
- Custom `roles` table
- Custom `role_permissions` pivot table
- Custom `authorization_scopes` table with `kind` enum ('global', 'unit', 'subtree')
- Custom `user_roles` table with scope relationships
- Custom `user_permissions` table for direct grants

**Spatie Permission is installed but NOT actively used for authorization.** The system has a custom scoped RBAC implementation.

### 2.3 Authentication Flow (Confirmed SPA Cookies)

`sanctum.php` configuration confirms:
- Stateful domains configured for localhost development
- Guard: `['web']` (session-based, not token-based)
- SPA cookie authentication pattern

Custom middleware:
- `ApplyRuntimeSettings.php` - applies system configuration
- `CheckBaselinePermission.php` - authorization enforcement
- `EnsureAccountIsActive.php` - account status verification
- `ValidateAuthorizationVersion.php` - instant session invalidation on permission changes

### 2.4 API Layer Structure

**27 Controllers** in `server/app/Http/Controllers/Api/V1/`:
```
AccreditationController, ArchiveController, AssignmentController,
AuditLogController, AuthController, BackupController,
CorrespondenceController, DashboardController, DataTransferController,
DocumentController, DocumentMovementController, GlobalSearchController,
HealthController, InstitutionalModuleController, MetadataController,
NavigationController, NotificationController, NumberingPolicyController,
OcrController, OrganizationUnitController, ReportController,
RetentionPolicyController, RolePermissionController, RoutingPolicyController,
SettingsController, TemplateController, UserController,
WorkflowAdminController, WorkflowController
```

### 2.5 Service Layer (Needs Investigation)

**Services** in `server/app/Services/`:
```
AssignmentService, AuditService, DocumentRepositoryService,
DocumentTimelineService, EmailNotificationService, EthiopianDateService,
FileSecurityService, OcrTextExtractionService, StorageManagerService,
TemplateSanitizer, TemplateService, UniversityAssetService,
WorkflowEngineService

Subdirectories:
- Authorization/
- Backup/
- DataTransfer/
- Pki/
- Settings/
```

**Requires inspection:** Are these services necessary business logic layers or unnecessary forwarding wrappers?

### 2.6 Frontend Architecture

**Stores (4 total):**
- `auth.ts` - authentication and user state
- `metadata.ts` - reference data caching
- `notifications.ts` - notification state
- `theme.ts` - theme preferences

**API Client:**
- `client.ts` - Axios instance with TypeScript types for all endpoints
- `sessionEvents.ts` - 401/403 event handling

**Structure appears minimal and appropriate.**

### 2.7 Documentation Inventory

**Current documentation structure:**
```
docs/
├── architecture/     - 7+ architectural documents
├── audits/          - 2 comprehensive audit reports + AI cleanup reports
├── deployment/      - Production deployment guides
├── development/     - 7+ API contracts, security audits, implementation logs
├── maintenance/     - 7+ verification reports from recent cleanup
├── operations/      - 6+ operational runbooks and traceability matrices
├── requirements/    - 3 role/user inventories
├── PRODUCTION_DEPLOYMENT_CHECKLIST.md
├── README.md
└── TROUBLESHOOTING_GUIDE.md
```

**Root directory reports (verification artifacts):**
```
CHANGE_COUNT_RECONCILIATION.md
CLEANUP_SUMMARY.md
DELETED_FILES_MANIFEST.txt
GITHUB_ACCESS_DIAGNOSTIC.md
WU-DMS_FINAL_RECOVERY_AND_CLOSURE_REPORT.md
```

**Obsolete AI instruction files:**
```
.skill/AGENTS.md  - Prescriptive AI agent instructions (Bearer tokens, Spatie packages)
.skill/STACK.md   - Aspirational architecture (does not match current implementation)
```

### 2.8 Test Coverage

**Backend:** PHPUnit/Pest tests exist (referenced in composer.json scripts)
**Frontend:** Vitest tests exist (referenced in package.json scripts)

**Status unknown** - requires execution to establish baseline.

---

## 3. Functional Preservation Requirements

### 3.1 MUST PRESERVE (Critical Institutional Features)

**Authentication and Session Management:**
- ✅ Sanctum SPA stateful session-cookie authentication
- ✅ CSRF protection (double-submit pattern)
- ✅ `authz_version` instant session invalidation on permission changes
- ✅ Account status validation (`active`, `inactive`, `locked`)
- ✅ Email verification and password reset flows
- ✅ 401 (unauthenticated) and 403 (unauthorized) handling

**Authorization and RBAC:**
- ✅ Custom scoped authorization (`global`, `unit`, `subtree`)
- ✅ Role-to-permission relationships
- ✅ Direct user-to-permission grants
- ✅ Organizational unit context (primary membership)
- ✅ Authorization scope enforcement in queries
- ✅ Permission-driven frontend navigation
- ✅ Secure route guards
- ✅ Server-side authorization (never trust frontend)

**Document Repository:**
- ✅ Document registration with version control
- ✅ `lock_version` optimistic concurrency control
- ✅ SHA-256 file integrity verification
- ✅ Canonical reference serial generation
- ✅ Document categories and confidentiality levels
- ✅ Document relationships and tagging
- ✅ Document movements and custody tracking
- ✅ Full-text search capability

**Correspondence Management:**
- ✅ Incoming letter registry
- ✅ Outgoing letter dispatch
- ✅ Internal memoranda
- ✅ Routing and receipt acknowledgment
- ✅ Priority and dispatch method tracking

**Workflow Engine:**
- ✅ Workflow definition and versioning
- ✅ Workflow instance state machine
- ✅ Task assignment and decision routing
- ✅ Approval, rejection, return-for-revision transitions
- ✅ Workflow participant management

**Archive and Retention:**
- ✅ Archive collections and entries
- ✅ Retention policy enforcement
- ✅ Legal hold management
- ✅ Physical custody tracking
- ✅ Disposition decisions and execution

**Staff and HR Records:**
- ✅ Staff profiles
- ✅ Employment records
- ✅ Qualifications and certificates
- ✅ Staff-related document attachments

**Research and Projects:**
- ✅ Research record registry
- ✅ Publication tracking
- ✅ Project proposals and workflow
- ✅ Project progress reporting

**Accreditation (HERQA):**
- ✅ Accreditation framework definitions
- ✅ Accreditation cycles
- ✅ Evidence collection and scoring
- ✅ Review and assessment tracking

**PKI Digital Signatures:**
- ✅ X.509 certificate management
- ✅ PDF signing with OpenSSL
- ✅ Signature verification
- ✅ Stamp definitions and applications
- ✅ Signing identity management

**Institutional Configuration:**
- ✅ System settings catalog (runtime configuration)
- ✅ Organization unit hierarchy management
- ✅ Numbering policy configuration
- ✅ Routing policy configuration
- ✅ Retention policy configuration
- ✅ Workflow template management

**Operations and Audit:**
- ✅ Comprehensive audit event logging
- ✅ Notification delivery tracking
- ✅ Idempotency record enforcement
- ✅ Backup and restore drill tracking
- ✅ Data import/export operations with validation
- ✅ Health monitoring endpoints

### 3.2 Database Integrity Requirements (STRICT)

**PROHIBITIONS:**
- ❌ Do NOT drop existing tables
- ❌ Do NOT delete database records
- ❌ Do NOT rewrite migration history
- ❌ Do NOT reset or refresh any database
- ❌ Do NOT change production or staging data
- ❌ Do NOT remove document version immutability
- ❌ Do NOT weaken audit history
- ❌ Do NOT remove constraints protecting institutional data
- ❌ Do NOT remove foreign key relationships
- ❌ Do NOT remove unique constraints
- ❌ Do NOT remove check constraints
- ❌ Do NOT remove indexes that support query performance

**If a schema change is genuinely necessary:**
1. Document the proposed change with justification
2. Assess migration risks and rollback strategy
3. Get explicit approval before implementation
4. Create a new migration (never modify existing migrations)

### 3.3 Security Requirements (STRICT)

**PROHIBITIONS:**
- ❌ Do NOT remove authorization checks from controllers
- ❌ Do NOT remove authorization middleware
- ❌ Do NOT remove scope enforcement in queries
- ❌ Do NOT trust department_id or unit_id from request parameters
- ❌ Do NOT expose password hashes or tokens
- ❌ Do NOT remove CSRF validation
- ❌ Do NOT remove rate limiting on authentication endpoints
- ❌ Do NOT remove email verification flows
- ❌ Do NOT remove password complexity requirements
- ❌ Do NOT remove audit event logging
- ❌ Do NOT remove file integrity verification
- ❌ Do NOT remove signature verification

**MUST VERIFY:**
- ✅ Authorization is enforced server-side on every protected operation
- ✅ Organizational scope is enforced at query level
- ✅ File uploads are validated for type and size
- ✅ User input is sanitized and validated
- ✅ SQL injection is prevented (use Eloquent, not raw queries)
- ✅ XSS is prevented (proper output escaping)
- ✅ CSRF is enforced on state-changing operations

---

## 4. Simplification Requirements

### 4.1 Documentation Simplification

**REQUIRED ACTIONS:**

**Remove obsolete AI instruction files:**
- ❌ `.skill/AGENTS.md` - Prescriptive instructions that don't match current implementation
- ❌ `.skill/STACK.md` - Aspirational architecture document

**Keep useful human engineering context:**
- ✅ Extract genuinely useful code standards from `.skill/AGENTS.md` if they reflect actual conventions
- ✅ Integrate useful standards into `README.md` or a concise `CONTRIBUTING.md`

**Consolidate root-level verification reports:**
- Move to `docs/maintenance/archive/` or delete if redundant:
  - `CHANGE_COUNT_RECONCILIATION.md`
  - `CLEANUP_SUMMARY.md`
  - `DELETED_FILES_MANIFEST.txt`
  - `GITHUB_ACCESS_DIAGNOSTIC.md`
  - `WU-DMS_FINAL_RECOVERY_AND_CLOSURE_REPORT.md`

**Review `docs/` subdirectories for consolidation opportunities:**
- ✅ Preserve: Installation guides, API documentation, deployment guides, operational runbooks
- ✅ Consolidate: Repeated architecture descriptions, overlapping audit reports
- ❌ Remove: Obsolete task checklists, temporary troubleshooting notes, AI execution summaries
- ❌ Remove: Documents describing features that don't exist
- ❌ Remove: Documentation that duplicates source code without adding value

**Target documentation structure:**
```
README.md                          - Project overview, stack, getting started
CONTRIBUTING.md                    - Code standards, PR process, testing requirements
docs/
├── INSTALLATION.md                - Detailed setup instructions
├── API.md                         - REST API reference (if maintained)
├── DEPLOYMENT.md                  - Production deployment guide
├── ARCHITECTURE.md                - High-level system architecture
├── AUTHORIZATION.md               - RBAC and security model
└── OPERATIONS.md                  - Operational runbook
```

### 4.2 Backend Code Simplification

**INVESTIGATION REQUIRED:**

**Service Layer Analysis:**
- Inspect each service in `server/app/Services/`
- Identify unnecessary forwarding wrappers
- Identify duplicate business logic
- Determine if service classes provide genuine value or just add indirection

**Model Analysis:**
- Review 120+ models for unused relationships
- Check for models containing unrelated responsibilities
- Identify unused scopes, accessors, mutators

**Controller Analysis:**
- Check for controllers with excessive business logic
- Verify proper use of Form Requests for validation
- Check for duplicate authorization checks
- Identify dead endpoints

**Unused Code Detection:**
- Identify unused providers, middleware, traits, helpers
- Find dead code and unused methods
- Check for unnecessary custom exception hierarchies

**Pattern Assessment:**
- Evaluate if repository pattern is needed (likely not with Eloquent)
- Check for unnecessary interfaces with single implementations
- Identify redundant DTOs and wrapper classes
- Assess complex code that could use standard Laravel features

**CONSTRAINTS:**
- Preserve Laravel established conventions
- Use Form Requests for substantial validation
- Use Policies for authorization (consider if missing)
- Use Eloquent relationships and query scopes
- Use Middleware for request-level concerns
- Use database transactions where data consistency requires them
- Services only where meaningful business operations justify them
- Events and jobs only where genuinely needed

### 4.3 Frontend Code Simplification

**INVESTIGATION REQUIRED:**

**Component Analysis:**
- Identify components with excessive responsibilities
- Find unnecessarily divided components
- Check for duplicate composables
- Identify duplicate API functions
- Find unnecessary Pinia stores
- Check for unnecessary watchers and computed properties
- Identify excessive prop drilling
- Find overcomplicated conditional rendering

**Type System Review:**
- Identify duplicate TypeScript interfaces
- Find unused types and imports
- Check for dead components
- Identify unnecessary wrapper components

**Routing and Navigation:**
- Check for duplicate route definitions
- Review navigation guards for overcomplicated logic
- Verify consistent API error handling

**State Management:**
- Confirm Pinia is used only for genuinely shared state
- Verify local component state is kept local when appropriate
- Check API integration consistency

**CONSTRAINTS:**
- Prefer simple Vue 3 Composition API patterns
- Keep the API integration layer consistent
- Do not sacrifice readability to reduce line counts
- Maintain TypeScript strict mode compliance

### 4.4 Dependency Review

**Current Backend Dependencies:**
```
laravel/framework: ^13.17
laravel/sanctum: ^4.3
spatie/laravel-permission: ^8.3       ← Installed but unused?
barryvdh/laravel-dompdf: ^3.1
league/flysystem-aws-s3-v3: ^3.28
```

**Question:** Is `spatie/laravel-permission` actually used, or is it a remnant from early development?

**REQUIRED VERIFICATION:**
- Grep for `HasRoles` trait usage in User model
- Grep for `hasPermissionTo()` calls
- Grep for Spatie model imports
- If not used, consider removal after confirming custom RBAC handles everything

**Current Frontend Dependencies:**
```
vue: ^3.5.44
pinia: ^4.1.0
vue-router: ^4.6.4
axios: ^1.20.0
tailwindcss: ^4.3.3
lucide-vue-next: ^1.0.0
```

**All appear necessary and minimal.**

### 4.5 Configuration Cleanup

**INVESTIGATION REQUIRED:**
- Review `server/config/` for unnecessary configuration files
- Check for duplicate configuration values
- Identify hardcoded values that should be environment variables
- Review `.env.example` for obsolete keys

---

## 5. Testing and Verification Requirements

### 5.1 Pre-Refactoring Baseline

**REQUIRED BEFORE ANY CODE CHANGES:**

1. **Establish test baseline:**
   ```bash
   cd server
   php artisan test --log-junit=baseline-results.xml
   ```

2. **Run TypeScript checks:**
   ```bash
   cd client
   npx vue-tsc -b
   ```

3. **Run frontend tests:**
   ```bash
   cd client
   npm test
   ```

4. **Test production build:**
   ```bash
   cd client
   npm run build
   ```

5. **Verify Laravel configuration:**
   ```bash
   cd server
   php artisan config:cache
   php artisan route:list
   ```

6. **Document baseline results:**
   - Total tests: X passed, Y failed, Z skipped
   - Known environmental failures (e.g., database connection)
   - Build warnings and errors
   - Configuration issues

### 5.2 Post-Refactoring Verification

**REQUIRED AFTER EACH MEANINGFUL REFACTORING PHASE:**

1. Run backend tests
2. Run frontend tests
3. Run TypeScript checks
4. Run production build
5. Verify authentication flow manually if environment permits
6. Verify role and permission enforcement
7. Check Laravel routes haven't broken
8. Compare results to baseline

**RULES:**
- Do not claim tests passed if they timed out
- Do not claim tests passed if they could not execute
- Use only isolated test database
- Report exact failure reasons if tests cannot run
- Do not execute destructive commands against staging or production

---

## 6. Repository Preparation Requirements

### 6.1 Git Repository State

**Current state:**
- 395 pending changes (320 deletions + 50 modifications + 25 untracked)
- Complete external recovery backup exists and is verified
- GitHub authentication blocked (cannot push yet)
- All existing branches and tags must be preserved

**REQUIRED ACTIONS:**

**Do NOT yet:**
- ❌ Stage or commit changes
- ❌ Push anything
- ❌ Delete `.git` directory
- ❌ Create new branches
- ❌ Modify existing branches or tags

**DO verify and improve:**
- ✅ Review `.gitignore` for completeness
- ✅ Ensure secrets, dependencies, caches, builds, temp files excluded
- ✅ Ensure legitimate source files are NOT excluded
- ✅ Prepare repository for eventual fresh-history migration (separate decision)

**Target new remote (for later):**
```
https://github.com/wonde30/institutional-document-management-system.git
```

### 6.2 Backup Requirements

**VERIFIED EXISTING:**
- Complete backup at `C:\Users\IT3\Desktop\WU-DMS_FINAL_COMPLETE_BACKUP_20261120_203740\`
- Git bundle verified (36.73 MB, complete history)
- All application files verified with SHA-256 checksums

**REQUIRED:**
- ✅ Preserve existing backup untouched
- ✅ Do not overwrite or delete backup
- ✅ Consider additional backup before major refactoring phases

---

## 7. Execution Method Requirements

### 7.1 Phase Structure

**Phase 0: Baseline Establishment**
- Document current test and build baseline
- Execute and record all test results
- Capture configuration snapshots

**Phase 1: Documentation Cleanup**
- Remove obsolete AI instruction files
- Consolidate root-level verification reports
- Review and consolidate `docs/` structure
- Create concise human-focused documentation

**Phase 2: Dependency Analysis**
- Verify Spatie Permission usage or recommend removal
- Review all backend dependencies
- Review all frontend dependencies
- Document findings

**Phase 3: Backend Code Inspection**
- Analyze service layer (forwarding wrappers vs. genuine business logic)
- Review models for complexity and unused code
- Review controllers for proper structure
- Identify unnecessary abstractions
- Document findings with specific recommendations

**Phase 4: Frontend Code Inspection**
- Analyze components for complexity
- Review stores and composables
- Check routing and navigation
- Identify duplicate code
- Document findings with specific recommendations

**Phase 5: Backend Simplification (if approved)**
- Remove unnecessary service wrappers
- Simplify controllers
- Remove dead code
- Consolidate duplicate logic
- **Run regression tests after each change**

**Phase 6: Frontend Simplification (if approved)**
- Simplify components
- Remove duplicate composables
- Clean up routing
- Remove dead code
- **Run build and tests after each change**

**Phase 7: Configuration Cleanup**
- Review and clean configuration files
- Update `.gitignore`
- Clean up environment examples

**Phase 8: Final Verification**
- Run complete test suite
- Run production build
- Review final diff
- Document all changes
- Produce final simplification report

### 7.2 Decision Gates

**STOP AND REQUEST APPROVAL FOR:**
- Removing any service that might contain business logic
- Changing authorization enforcement patterns
- Removing any database-related code
- Removing any audit or security-related code
- Removing any file handling code
- Removing any workflow engine code
- Any change that might impact institutional data integrity
- Any change that might weaken security
- Any change whose impact is uncertain

**DO NOT STOP FOR:**
- Removing obviously dead code
- Consolidating duplicate documentation
- Removing obsolete AI instruction files
- Improving code formatting
- Adding missing type hints
- Fixing obvious bugs
- Improving variable names

### 7.3 Change Documentation

**REQUIRED FOR EACH PHASE:**
- What was changed
- Why it was changed
- What was preserved
- Test results before and after
- Known risks or remaining issues

---

## 8. Final Deliverable Requirements

### 8.1 Simplification Report

**REQUIRED OUTPUT:**
`docs/maintenance/CODEBASE_SIMPLIFICATION_REPORT.md`

**MUST INCLUDE:**
1. Files and documentation removed (with justification)
2. Files consolidated (before/after)
3. Backend changes (specific classes, methods, abstractions removed)
4. Frontend changes (specific components, stores, functions removed)
5. Unnecessary abstractions removed (with examples)
6. Dependencies removed, if any (with verification they were unused)
7. Security and RBAC behavior preserved (how verified)
8. Tests executed and actual results (pass/fail counts, known issues)
9. Known failures and unresolved issues (be honest)
10. Remaining technical debt (specific items)

**DO NOT:**
- Create multiple duplicate reports
- Generate another enormous theoretical audit
- Claim all tests passed if they didn't
- Hide failures or unresolved issues

### 8.2 Success Criteria Summary

**The final project MUST have:**
- ✅ Clean and understandable project structure
- ✅ Minimal, useful human documentation (not AI instructions)
- ✅ No unnecessary AI-agent instruction system
- ✅ No duplicate implementations
- ✅ No dead code identified as active functionality
- ✅ No unnecessary dependencies
- ✅ Clear Laravel backend conventions
- ✅ Clear Vue frontend conventions
- ✅ Consistent RBAC and routing behavior
- ✅ Preserved institutional document functionality
- ✅ Preserved data integrity and security
- ✅ Verified tests and production build (where environment permits)

---

## 9. Open Questions Requiring Investigation

### 9.1 Spatie Permission Package

**Question:** Is `spatie/laravel-permission` actually used in the application?

**Evidence suggesting NO:**
- Custom RBAC tables exist in migration `2026_10_28_000002_wu_02_scoped_rbac.php`
- Custom `Permission`, `Role`, `UserRole`, `RolePermission` models exist
- No `Gate::define()` calls found (Spatie's typical pattern)
- User model doesn't appear to use `HasRoles` trait

**Investigation required:**
- Check if User model imports or uses Spatie traits
- Grep for `hasPermissionTo()`, `hasRole()`, `assignRole()` calls
- Check if Spatie models are used anywhere
- If unused, recommend removal after verification

### 9.2 Service Layer Justification

**Question:** Do the services in `server/app/Services/` provide genuine business logic or are they unnecessary wrappers?

**Investigation required for each service:**
- `AssignmentService` - document assignment operations
- `AuditService` - audit event logging
- `DocumentRepositoryService` - document operations
- `DocumentTimelineService` - timeline generation
- `EmailNotificationService` - email sending
- `EthiopianDateService` - date conversion
- `FileSecurityService` - file integrity and encryption
- `OcrTextExtractionService` - OCR operations
- `StorageManagerService` - file storage abstraction
- `TemplateSanitizer` - template sanitization
- `TemplateService` - template operations
- `UniversityAssetService` - university asset management
- `WorkflowEngineService` - workflow state machine

**Services in subdirectories:**
- `Authorization/` - custom authorization logic
- `Backup/` - backup operations
- `DataTransfer/` - import/export operations
- `Pki/` - digital signature operations
- `Settings/` - system settings management

**For each service, determine:**
- Does it contain genuine business logic?
- Does it orchestrate multiple operations?
- Does it provide meaningful abstraction?
- Or does it just forward calls to Eloquent models?

### 9.3 Policy Classes

**Question:** Why doesn't `server/app/Policies/` directory exist?

**Current authorization appears to use:**
- Custom middleware: `CheckBaselinePermission`, `ValidateAuthorizationVersion`
- Custom authorization service (in `Services/Authorization/`?)
- Direct permission checks in controllers?

**Investigation required:**
- How is authorization actually enforced?
- Should we add Laravel Policies for better structure?
- Or is the current custom system sufficient and consistent?

### 9.4 Frontend API Client Duplication

**Question:** Is there duplicate API logic or is `client.ts` the single source of truth?

**Investigation required:**
- Check if any components make direct axios calls
- Verify all API calls go through `client.ts`
- Check for duplicate type definitions

### 9.5 Test Environment

**Question:** Can the test suite actually run in the current environment?

**Known from previous reports:**
- Tests timed out due to database connection issues
- May require database setup
- May require specific environment configuration

**Investigation required:**
- What is the actual test failure reason?
- What is needed to make tests runnable?
- Are there environmental blockers we can't fix?

---

## 10. Constraints and Boundaries

### 10.1 What This Project Is

- ✅ Simplification of existing working code
- ✅ Removal of unnecessary complexity
- ✅ Consolidation of duplicate implementations
- ✅ Documentation cleanup and consolidation
- ✅ Preparation for maintainability by human teams
- ✅ Verification that security and functionality are preserved

### 10.2 What This Project Is NOT

- ❌ A complete rewrite
- ❌ A migration to new frameworks or packages
- ❌ A feature addition project
- ❌ An architecture redesign
- ❌ A database refactoring
- ❌ A replacement of working implementations with "better" ones
- ❌ A project to make the codebase look more sophisticated
- ❌ A test coverage improvement project (though tests must pass)
- ❌ A performance optimization project

### 10.3 Architectural Decisions (FIXED)

**These decisions are FINAL and not subject to change during this project:**

1. ✅ Preserve Sanctum SPA cookie authentication
2. ✅ Preserve custom scoped RBAC implementation
3. ✅ Do NOT migrate to Bearer Token authentication
4. ✅ Do NOT replace RBAC with Spatie Permission
5. ✅ Do NOT introduce Spatie Media Library, Laravel Reverb, or Laravel Horizon
6. ✅ Do NOT redesign database schema
7. ✅ Do NOT rewrite migrations
8. ✅ Preserve document versioning and concurrency controls
9. ✅ Preserve audit history mechanisms
10. ✅ Preserve workflow engine functionality

---

## 11. Risk Assessment

### 11.1 High-Risk Areas (Extra Caution Required)

**Authorization and RBAC:**
- Custom scoped authorization is security-critical
- Changes here could create privilege escalation vulnerabilities
- Extensive testing required for any changes
- Consider if Laravel Policies should be added (structural improvement)

**Document Versioning and Concurrency:**
- `lock_version` optimistic concurrency is critical for data integrity
- Do not remove without understanding institutional requirements
- Changes could cause data corruption or lost updates

**Workflow State Machine:**
- Complex business logic with state transitions
- Changes could break approval processes
- Institutional processes depend on this

**PKI Digital Signatures:**
- Cryptographic operations with legal implications
- Do not simplify without understanding security requirements
- Signature verification must remain intact

**Audit Event Logging:**
- Required for institutional compliance
- Cannot be weakened or removed
- May be legally required

### 11.2 Medium-Risk Areas (Review Required)

**Service Layer:**
- May contain business logic that's not obvious
- Removing services could break calling code
- Requires careful analysis before removal

**File Storage and Integrity:**
- File handling is security-sensitive
- SHA-256 verification should be preserved
- Storage abstraction may be justified

**Email and Notifications:**
- May have institutional requirements
- Simplification must preserve delivery guarantees

### 11.3 Low-Risk Areas (Safe to Simplify)

**Documentation:**
- Removing obsolete AI instructions is safe
- Consolidating duplicate docs is safe
- Removing verification reports is safe

**Dead Code:**
- Unused imports, commented code, unreachable functions
- Safe to remove after verification

**Code Style:**
- Formatting improvements
- Variable naming
- Comment cleanup

---

## 12. Success Metrics

### 12.1 Quantitative Metrics

**Documentation:**
- Markdown files: Reduce from X to Y (target: <20 useful documents)
- Root-level reports: Reduce from 5 to 0 (move to archive)
- .skill/ files: Reduce from 2 to 0

**Backend:**
- Unused models: Identify and document count
- Unnecessary services: Identify and document count
- Dead code: Lines removed
- Unnecessary dependencies: Packages removed

**Frontend:**
- Duplicate components: Components consolidated
- Dead code: Lines removed
- Unused types: Interfaces removed

**Tests:**
- Test pass rate: Maintain or improve
- Build success: Maintain or improve
- TypeScript errors: Maintain at zero

### 12.2 Qualitative Metrics

**Maintainability:**
- Can a new developer understand the project structure? (subjective assessment)
- Is the documentation useful for human developers? (review for clarity)
- Are architectural decisions clear? (document in ARCHITECTURE.md)

**Code Quality:**
- Are responsibilities clearly separated?
- Is there one clear way to do each thing?
- Are names clear and consistent?
- Is error handling consistent?

**Security:**
- Are authorization checks clear and consistent?
- Are security boundaries well-defined?
- Is audit trail comprehensive?

---

## 13. Next Steps

### 13.1 After Requirements Approval

1. Create `design.md` specification
   - Detailed architecture of current implementation
   - Specific simplification proposals with justifications
   - Before/after examples
   - Risk assessment for each proposal

2. Create `tasks.md` specification
   - Specific actionable tasks
   - Dependencies between tasks
   - Verification steps for each task
   - Estimated effort

3. Begin phased execution with approval gates

### 13.2 Open Questions for User

**Please confirm or clarify:**

1. Should we add Laravel Policy classes for authorization, or keep custom middleware?
2. Is Spatie Permission installed intentionally or is it a remnant?
3. Are there specific services you know should be preserved?
4. Are there specific documentation files you want preserved?
5. What is the priority: backend simplification, frontend simplification, or documentation?
6. Should we attempt to fix test environment issues or just document them?

---

## 14. Approval

**This requirements document must be approved before proceeding to design phase.**

**Approval checklist:**
- [ ] Functional preservation requirements are acceptable
- [ ] Database integrity prohibitions are understood
- [ ] Security prohibitions are understood
- [ ] Execution method and phase structure are acceptable
- [ ] Risk assessment is reviewed
- [ ] Open questions are addressed
- [ ] Success criteria are clear

**Approved by:** _____________  
**Date:** _____________

---

**END OF REQUIREMENTS SPECIFICATION**

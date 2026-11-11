# Proposal Implementation Traceability

**System:** Wollo University Administrative and Document Management System (WU-DMS)

This document evaluates the approved project proposal against the actual
implementation of WU-DMS, using code, schema and test evidence rather than the
presence of a screen or a route.

**Source document.** *Project Proposal on: Web-based Integrated Document
Repository, Archive, and Inter-office Communication Management System for
Wollo University*, prepared by AWT Teams, submission date 11/02/2019 E.C,
Wollo University, KIoT, Ethiopia.

> **Local reference path.** The proposal was removed from the working tree in
> an earlier phase as a tracked binary artefact, and the repository never
> carried a copy under the name `Project Proposal for WU(1).pdf`. For this
> review the document was recovered from the repository history
> (`archive/pre-squash-2026-11-10:Project Proposal for WU.pdf`, blob
> `c115bb3b9e9cccefdac866cf3401ac26c0bddaf1`, 1 167 170 bytes) and read
> directly. The proposal is **not** present in the current working tree, so a
> reviewer without the history tag must supply the document separately.

**Status vocabulary.**

| Status | Meaning |
|---|---|
| Verified | The behaviour is implemented and the evidence demonstrates it works. |
| Partial | Part of the requirement is delivered; a named part is missing. |
| Missing | The requirement is not implemented. |
| UI-only | The screen exists but performs no real operation. |
| Backend-only | The data structure exists but is not exposed through an operation. |
| Requires approval | The behaviour depends on a university policy that does not yet exist. |
| Not tested | No evidence of the behaviour was found. |

---

## 1. Summary

| # | Proposal requirement | Status | Priority |
|---|---|---|---|
| 1 | User authentication and account management | Verified | — |
| 2 | Organizational-unit management | Verified | — |
| 3 | Role and permission management | Verified | — |
| 4 | Document registration and metadata management | Verified | — |
| 5 | Electronic file upload, storage, viewing, controlled download | Verified | — |
| 6 | Document classification, categorization, and tagging | Partial | Medium |
| 7 | Document search, filtering, sorting, and retrieval | Verified | — |
| 8 | Incoming correspondence registration and tracking | Verified | — |
| 9 | Outgoing correspondence registration and tracking | Verified | — |
| 10 | Internal memorandum management | Verified | — |
| 11 | Office-to-office document routing and controlled sharing | Verified | — |
| 12 | Assignment, forwarding, acknowledgment, and status tracking | Verified | — |
| 13 | Configurable document review and approval workflows | Partial | Requires approval |
| 14 | Electronic-signature and stamp infrastructure | Partial | Medium |
| 15 | Digital archive transfer, classification, retention metadata, retrieval | Verified | — |
| 16 | Retention metadata and retrieval | Partial | Requires approval |
| 17 | Notifications for assignments, pending actions, status changes | Verified | — |
| 18 | Management dashboards and administrative reports | Verified | — |
| 19 | Staff profile and related information | Partial | Medium |
| 20 | Research approvals across the department | Partial | High |
| 21 | Project approvals and related innovated ideas | Partial | High |
| 22 | Audit logging of important system operations | Verified | — |
| 23 | Backup, restoration, and system configuration support | Verified | — |
| 24 | System administration and configuration | Verified | — |
| 25 | Deployment documentation | Partial | High |
| 26 | User guidance and training | Partial | Medium |
| 27 | Maintenance and operational handover | Partial | Medium |

**Counts.** 15 verified · 12 partial · 0 missing · 0 untested.
Three of the partial items are blocked on university policy rather than on
engineering.

---

## 2. Detailed evaluation

### 1. User authentication and account management — **Verified**

| Field | Evidence |
|---|---|
| Backend evidence | `AuthController`: `login`, `logout`, `me`, `changePassword`, `forgotPassword`, `resetPassword`, `verifyEmail`, `sendVerificationEmail`. Sanctum SPA stateful sessions with double-submit CSRF. `EnsureAccountIsActive`, `ValidateAuthorizationVersion` middleware. |
| Frontend evidence | `LoginView`, `ForgotPasswordView`, `ResetPasswordView`, `VerifyEmailView`, `AccountProfileView`. |
| Database evidence | `users`, `sessions`, `password_reset_tokens`, `personal_access_tokens`. |
| Test evidence | `AccountRecoveryTest` (9), `SecurityPenetrationTest` (session invalidation, account lockout, replay), `authSessionLifecycle.spec.ts` (9). |
| Remaining gap | None material. |

### 2. Organizational-unit management — **Verified**

| Field | Evidence |
|---|---|
| Backend evidence | `OrganizationUnitController`: `tree`, `index`, `show`, `store`, `update`, `destroy`. `OrganizationUnit::getAncestorIds()` / `getDescendantIds()` drive the scope arithmetic. |
| Frontend evidence | `OrganizationUnitsView`. |
| Database evidence | `organization_units` (parent_id, unit_type_id, code, active), `organization_memberships`, `primary_memberships`. |
| Test evidence | `OrganizationUnitsView` exercised by `RealOperationalAcceptanceTest`; scope behaviour asserted by `AuthorizationResolutionRegressionTest` (8). |
| Remaining gap | None material. |

### 3. Role and permission management — **Verified**

| Field | Evidence |
|---|---|
| Backend evidence | `RolePermissionController`: role CRUD, permission listing, role-permission sync, user-role assignment and revocation, direct user-permission assignment and revocation. 78 registered capabilities. |
| Frontend evidence | `RoleManagementView`, `PermissionManagementView`. |
| Database evidence | `roles`, `permissions`, `role_permissions`, `authorization_scopes` (global / unit / subtree), `user_roles`, `user_permissions`. |
| Test evidence | `DynamicRbacTest`, `NavigationPermissionContractTest` (5), `AuthorizationResolutionRegressionTest` (8). |
| Remaining gap | None material. |

### 4. Document registration and metadata management — **Verified**

| Field | Evidence |
|---|---|
| Backend evidence | `DocumentController::store` with an atomic reference sequence, canonical metadata and an optional binary. `DocumentResource`. |
| Frontend evidence | `DocumentCreateView`, `DocumentDetailView`, `DocumentListView`. |
| Database evidence | `documents`, `document_versions`, `reference_sequences`, `document_categories`, `confidentiality_levels`. |
| Test evidence | `DocumentTest`, `UnifiedRepositoryEndToEndTest`, `DocumentSecurityRemediationTest`, `EndToEndWorkflowIntegrationTest`. |
| Remaining gap | None material. |

### 5. Electronic file upload, storage, viewing, controlled download — **Verified**

| Field | Evidence |
|---|---|
| Backend evidence | `VersionFile` and `FileObject` with SHA-256 integrity. `DocumentController::downloadFile` re-authorizes with `Document::allowsUser($actor, 'documents.download')` before serving bytes from the private disk. `StorageManagerService`. |
| Frontend evidence | `DocumentDetailView` version file panel with checksum display and download. |
| Database evidence | `file_objects` (storage_disk, storage_key, byte_size, sha256, scan_status), `version_files`. |
| Test evidence | `DocumentSecurityRemediationTest` (direct-invocation bypass), `SecurityAndAccessControlAuditTest` (confidentiality restrictions). |
| Remaining gap | None material. |

### 6. Document classification, categorization, and tagging — **Partial**

| Field | Evidence |
|---|---|
| Backend evidence | Classification: `confidentiality_levels` applied at registration with the elevated-clearance rule in `Document::allowsUser`. Categorization: `document_categories` with a seeded controlled vocabulary. |
| Frontend evidence | `DocumentCreateView` selects a category and a classification. |
| Database evidence | `confidentiality_levels`, `document_categories`, `tags`, `document_tags`, and a `Document::tags()` relation. |
| Test evidence | Classification asserted by `AuthorizationBoundaryEnforcementTest` and `DataTransferTest` (export confidentiality restriction). |
| Remaining gap | **Tagging is backend-only.** The `tags` and `document_tags` tables and the Eloquent relation exist, but there is **no route, no request validation and no interface** that reads or writes a tag. A document cannot be tagged by any actor. |

### 7. Document search, filtering, sorting, and retrieval — **Verified**

| Field | Evidence |
|---|---|
| Backend evidence | `GlobalSearchController::search`, `DocumentController::index` with unit, category, classification, lifecycle and date filters, and grant-driven scoping through `User::getPermittedUnitIds()`. |
| Frontend evidence | `GlobalSearchView`, `DocumentListView` with the filter bar. |
| Database evidence | `documents`, `document_versions`, indexed `owner_unit_id`, `category_id`, `lifecycle`, `document_date`. |
| Test evidence | `DatabaseOptimizationTest` (composite index selection), `dms:profile-queries` output in §5. |
| Remaining gap | None material. |

### 8. Incoming correspondence registration and tracking — **Verified**

| Field | Evidence |
|---|---|
| Backend evidence | `CorrespondenceController::store` and the registry listing with type, priority, purpose, external reference, receiving office and required action. |
| Frontend evidence | `CorrespondenceRegistryView` with the incoming, outgoing and memoranda tabs. |
| Database evidence | `correspondences`, `correspondence_participants`, `correspondence_dispatches`, `correspondence_types`. |
| Test evidence | `CorrespondenceTest`, `InstitutionalCorrespondenceWorkflowLifecycleTest`. |
| Remaining gap | None material. |

### 9. Outgoing correspondence registration and tracking — **Verified**

| Field | Evidence |
|---|---|
| Backend evidence | `CorrespondenceController::dispatch` with the dispatch method, tracking reference and the preserved final copy. |
| Frontend evidence | The outgoing tab of `CorrespondenceRegistryView`. |
| Database evidence | `correspondence_dispatches`, `dispatch_methods`, `document_movements`. |
| Test evidence | `DatabaseOptimizationTest` (dispatch history index), `InstitutionalCorrespondenceWorkflowLifecycleTest`. |
| Remaining gap | None material. |

### 10. Internal memorandum management — **Verified**

| Field | Evidence |
|---|---|
| Backend evidence | Memoranda are correspondence with the memorandum purpose code, routed through the same movement and assignment engine as a letter. |
| Frontend evidence | The memoranda tab of `CorrespondenceRegistryView`. |
| Database evidence | `correspondences.purpose_code`. |
| Test evidence | `CorrespondenceTest` (purpose filtering), `DatabaseOptimizationTest`. |
| Remaining gap | The memorandum is a purpose of the correspondence record rather than a separate entity. That satisfies the proposal, which lists internal memoranda under the inter-office communication component. |

### 11. Office-to-office document routing and controlled sharing — **Verified**

| Field | Evidence |
|---|---|
| Backend evidence | `DocumentMovementController` (send, acknowledge), `AssignmentController` (assign, participants, responses), `document_grants` for discretionary sharing, `RouteScopeResolver` binding every record family to its owning unit. |
| Frontend evidence | The routing tab of `CorrespondenceRegistryView`, the assignment panels of `DocumentDetailView`. |
| Database evidence | `document_movements`, `movement_receipts`, `document_assignments`, `assignment_participants`, `assignment_responses`, `document_grants`. |
| Test evidence | `AuthorizationBoundaryEnforcementTest` (11), `RealAuthorizationAcceptanceTest`, `RealOperationalAcceptanceTest`. |
| Remaining gap | None material. |

### 12. Assignment, forwarding, acknowledgment, and status tracking — **Verified**

| Field | Evidence |
|---|---|
| Backend evidence | `AssignmentService` (participants, events, responses), `DocumentMovementController::acknowledge`, `ProcessDueDatesAndEscalationsCommand` for overdue escalation. |
| Frontend evidence | `WorkflowTasksView`, the assignment panel of `DocumentDetailView`. |
| Database evidence | `document_assignments`, `assignment_participants`, `assignment_events`, `assignment_responses`, `document_movements`, `movement_receipts`. |
| Test evidence | `AssignmentParticipantAuditTest` (6), `AssignmentParticipantAuditAtomicityTest` (1), `ProcessDueDatesAndEscalationsTest`, `SchedulerExecutionTest`. |
| Remaining gap | None material. |

### 13. Configurable document review and approval workflows — **Partial / Requires approval**

| Field | Evidence |
|---|---|
| Backend evidence | `WorkflowEngineService` with workflow definitions, states, transitions, instances and tasks, branch and join support, quorum consensus and a parallel branch test. |
| Frontend evidence | `WorkflowTasksView`. |
| Database evidence | `workflow_definitions`, `workflow_states`, `workflow_transitions`, `workflow_instances`, `workflow_tasks`, `workflow_task_participants`, `workflow_transitions_log`. |
| Test evidence | `WorkflowCompletenessAndConsensusTest`, `WorkflowParallelBranchAndJoinTest`, `EndToEndWorkflowIntegrationTest`. |
| Remaining gap | **The engine is configurable in data, but the approval authority for each document category is a university decision.** The proposal states explicitly: *"The actual workflow and approval authority for each document category will be established with the relevant university offices."* No approval matrix has been supplied, so the shipped default transition set is a technical default rather than an approved university procedure. The capability to model any authority exists; the approved authority does not. |

### 14. Electronic-signature and stamp infrastructure — **Partial**

| Field | Evidence |
|---|---|
| Backend evidence | `PdfSignatureService` (X.509 signing, RFC 3161 timestamps, detached PKCS#7 ByteRange), `PdfSignatureVerificationService`, `SignatureRequest`, `DigitalSignature`, `SignatureVerification`, `StampDefinition` with `StampAuthorization`. |
| Frontend evidence | `SignaturesStampsView` (signatures, stamps and trust-provider tabs). |
| Database evidence | `signature_requests`, `digital_signatures`, `signature_verifications`, `stamp_definitions`, `stamp_authorizations`. |
| Test evidence | `PkiCryptographicEngineTest`, `EndToEndWorkflowIntegrationTest`. |
| Remaining gap | **A stamp can be defined and authorised but not yet applied to a document.** The capability `stamps.apply` is registered, yet no route applies a stamp to a document version. The trust provider is a development certificate; the proposal's national PKI integration depends on an external certificate authority. |

### 15. Digital archive transfer, classification, retention metadata, retrieval — **Verified**

| Field | Evidence |
|---|---|
| Backend evidence | `ArchiveController`: `collections`, `policies`, `entries`, `transfer`, `legalHolds`. |
| Frontend evidence | `ArchiveListView`. |
| Database evidence | `archive_collections`, `archive_entries`, `retention_policies`, `retention_policy_versions`, `legal_holds`. |
| Test evidence | `ArchiveTest`, `DatabaseOptimizationTest`. |
| Remaining gap | None material. |

### 16. Retention metadata and retrieval — **Partial / Requires approval**

| Field | Evidence |
|---|---|
| Backend evidence | Retention policy and version metadata, legal holds, a disposal request that requires the `archive.approve_disposal` capability, and the physical-original disposal route. |
| Frontend evidence | `ArchiveListView`. |
| Database evidence | `retention_policies`, `retention_policy_versions`, `legal_holds`, `physical_originals`. |
| Test evidence | `ArchiveTest`, `DepartmentalClarificationsRegressionTest` (an upload does not authorise a disposal). |
| Remaining gap | **The retention periods themselves are not established.** The proposal states that the module *"will support university record-preservation requirements without automatically determining the legal retention period of any document."* The periods must be supplied by the university. Until then the period is a placeholder, not an approved schedule. |

### 17. Notifications for assignments, pending actions, and status changes — **Verified**

| Field | Evidence |
|---|---|
| Backend evidence | `NotificationController` (list, mark read, mark all read), `user_notifications` written by the assignment and escalation paths, `ProcessDueDatesAndEscalationsCommand` for approaching deadlines. |
| Frontend evidence | `NotificationsView`, the notifications store, the bell indicator in the sidebar. |
| Database evidence | `user_notifications`, `notification_events`. |
| Test evidence | `ProcessDueDatesAndEscalationsTest`, `SchedulerExecutionTest`. |
| Remaining gap | None material. |

### 18. Management dashboards and administrative reports — **Verified**

| Field | Evidence |
|---|---|
| Backend evidence | `DashboardController::metrics` (correspondence volumes, pending assignments, overdue items, archived counts), `ReportController::summary`, `AuditLogController`. |
| Frontend evidence | `DashboardView`, `ReportsView`, `AuditLogView`. |
| Database evidence | The operational tables above, with the indexes listed in §5. |
| Test evidence | `DatabaseOptimizationTest` (the dashboard and report query plans). |
| Remaining gap | The dashboards are operational counters. A printable management report pack is not delivered. |

### 19. Staff profile and related information — **Partial**

| Field | Evidence |
|---|---|
| Backend evidence | `InstitutionalModuleController`: `staffRecords`, `storeStaffRecord`, `verifyStaffScan`, `disposeStaffPhysicalOriginal`, with `staff.view`, `staff.update` and `staff.view_sensitive` capabilities. |
| Frontend evidence | `StaffRecordsView`. |
| Database evidence | `staff_profiles`, `staff_documents` (physical originals), `physical_originals`, custody and scan verification. |
| Test evidence | `SecurityAndAccessControlAuditTest` (the sensitive clearance rule). |
| Remaining gap | **Physical custody of staff dossiers is delivered; the personnel information itself is not.** The proposal asks for *"staff profiles, qualifications, certificates, publications, and employment-related documents"*. There is no qualification, certificate, publication or employment-records model. A staff member's academic and employment record cannot be maintained. |

### 20. Research approvals across the department — **Partial**

| Field | Evidence |
|---|---|
| Backend evidence | `InstitutionalModuleController`: `research`, `showResearch`, `storeResearch`, `updateResearch`, `uploadResearchVersion`, `toggleResearchPublication`, with `research.view`, `research.create`, `research.update`, `research.publish` capabilities. |
| Frontend evidence | `FinalizedResearchView`. |
| Database evidence | `research_records` and the document version relation. |
| Test evidence | `DatabaseOptimizationTest`, the capability resolution regression test. |
| Remaining gap | **There is no research approval workflow.** A research output can be created, revised and published, but it cannot be submitted for departmental review, approved or returned. The generic workflow engine of requirement 13 is the natural mechanism; only the research-specific state set and the approving authority are missing. |

### 21. Project approvals and other related innovated ideas — **Partial**

| Field | Evidence |
|---|---|
| Backend evidence | `InstitutionalModuleController`: `projects`, `storeProject`, with `projects.view` and `projects.submit` capabilities. |
| Frontend evidence | `ProjectsInnovationView`. |
| Database evidence | `projects`, `project_types`. |
| Test evidence | The capability resolution regression test. |
| Remaining gap | **There is no project approval workflow.** A project can be submitted, but it cannot be reviewed, approved or rejected. The `projects.approve` capability is registered with no route that consumes it. |

### 22. Audit logging of important system operations — **Verified**

| Field | Evidence |
|---|---|
| Backend evidence | `AuditService::log` with automatic redaction of credentials, tokens and secrets. Events cover authentication, configuration changes, imports and exports, organizational changes and assignment lifecycle. The domain lifecycle tables (`assignment_events`, `document_relation_types`) carry the equivalent per-aggregate history. |
| Frontend evidence | `AuditLogView` with filtering and the security ledger export. |
| Database evidence | `audit_events` (actor, action, target, request id, occurred at, ip, user agent, redacted details). |
| Test evidence | `AssignmentParticipantAuditTest` (6) and `AssignmentParticipantAuditAtomicityTest` (1) prove that a removal and its audit record share one transaction; `SystemSettingsTest` and `DataTransferTest` prove the same for configuration and data operations. |
| Remaining gap | The ledger is append-only by convention. A database level guarantee that prevents update and delete by the application account has not been established. |

### 23. Backup, restoration, and system configuration support — **Verified**

| Field | Evidence |
|---|---|
| Backend evidence | `dms:backup`, `dms:restore`, `dms:dr-drill`, `dms:backup-status`, `dms:backup-cleanup`, `dms:storage-status`, `dms:profile-queries`. The runtime configuration module (catalogue, persistence, audit, reset, deployment boundary). |
| Frontend evidence | `OperationsView` reports the operational and disaster-recovery health. |
| Database evidence | The backup artefacts and the `system_settings` table. |
| Test evidence | `RealOperationalAcceptanceTest` covers the restoration drill; `SystemSettingsTest` (25) and `ImportUploadCleanupTest` (12) cover the configuration and cleanup behaviour. |
| Remaining gap | The scheduled backup itself is a deployment arrangement. The command exists; the cron entry does not. |

### 24. System administration and configuration — **Verified**

| Field | Evidence |
|---|---|
| Backend evidence | The configuration module: `SettingsController`, `SettingsService`, the `system_settings` table, the code-defined catalogue and the audit trail. `ReferenceDataController`, `OfficeProcedures`, role and permission administration, the dynamic navigation catalogue. |
| Frontend evidence | `SystemSettingsView` (categories, typed editors, defaults, reset with confirmation), `ReferenceDataView`, `OfficeProceduresView`, `PermissionManagementView`, `RoleManagementView`. |
| Database evidence | `system_settings` with its uniqueness constraint and three check constraints, `system_settings` deployment projection. |
| Test evidence | `SystemSettingsTest` (25), `NavigationPermissionContractTest` (5), `FrontendApiContractTest`. |
| Remaining gap | None material. |

### 25. Deployment documentation — **Partial**

| Field | Evidence |
|---|---|
| Backend evidence | `.env.example` documents every environment variable; the configuration catalogue documents every runtime setting. |
| Frontend evidence | Not applicable. |
| Database evidence | The migration history is the schema record. |
| Test evidence | Not applicable. |
| Remaining gap | **There is no deployment runbook.** The proposal's phase 7 requires *"handover documents"* and the schedule names *"deployment and system configuration"* as a month 10 to 11 activity. A reader cannot install the system from the repository alone: there is no document covering the host prerequisites, the web server and process manager configuration, the cron entries, the queue worker, the backup schedule, the TLS termination, or the recovery drill. This is the highest-priority documentation gap. |

### 26. User guidance and training — **Partial**

| Field | Evidence |
|---|---|
| Backend evidence | Not applicable. |
| Frontend evidence | `HelpGuideView` presents a module guide inside the application. |
| Database evidence | None. |
| Test evidence | Not applicable. |
| Remaining gap | **The in-application guide is a static array compiled into the component.** It cannot be maintained by an administrator and does not cover the role-specific procedures the proposal anticipates in its training phase. There is no user manual, no quick-start guide for a records officer, and no training material. |

### 27. Maintenance and operational handover — **Partial**

| Field | Evidence |
|---|---|
| Backend evidence | The maintenance commands of requirement 23, the escalation scheduler, the permission and cache invalidation behaviour, the backup retention policy enforcement. |
| Frontend evidence | `OperationsView`. |
| Database evidence | The schema migration history. |
| Test evidence | `SchedulerExecutionTest`, `ProcessDueDatesAndEscalationsTest`, the configuration and cleanup suites. |
| Remaining gap | **The maintenance plan and the handover document do not exist.** The proposal names *"maintenance plans"* in the specific objectives and *"maintenance and technical support"* in the budget. A support model, an escalation responsibility matrix and a handover record are absent. |

---

## 3. Items that require a university decision

These cannot be closed by engineering. They are recorded so that they are not
mistaken for unfinished software.

| # | Decision required | Consequence while unresolved |
|---|---|---|
| A | The approval authority for each document category: who approves, who may return, what the escalation path is. | The shipped workflow transitions are a technical default. The engine can model any authority, but no approved authority exists. |
| B | The confidentiality classification schedule: which levels exist, which may be applied by a unit, and what clearance each role needs. | The four implemented levels and the elevated-clearance rule are a defensible default, not the university's schedule. |
| C | The retention schedule: the period per record category, the review points and the disposal authority. | The retention metadata model is complete; the periods are placeholders. Disposal already requires a separate approval capability. |
| D | Which records may be managed electronically and which still require the original physical document or an additional formal procedure. | The custody rules are implemented generically, but the classification per record type is unknown. |

---

## 4. Defects found during this review

| # | Severity | Defect | Evidence | Closure |
|---|---|---|---|---|
| 1 | **High** | The OCR operations of the document detail screen called four endpoints that do not exist: `POST /ocr/process`, `GET /ocr/{documentId}/extractions`, `POST /ocr/{extractionId}/verify`, `POST /ocr/{extractionId}/retry`. Every OCR action on the document detail screen returned HTTP 404. | `FrontendApiContractTest` compares every call declared by the front end client with the route table. | The client now calls the real, permission-scoped routes under `/documents/{id}/versions/{versionId}/extractions`, and the view passes the version identifier. |
| 2 | **High** | The OCR verification sent `corrected_text`, while the server requires `verified_text`. The request was refused with a validation error even on a correct route. | The same contract test. | The client sends `verified_text`. |
| 3 | **Medium** | The HTTP error envelope was inconsistent. A validation failure carried `success: false`, while an exception raised by a controller did not, so a client could not distinguish a failure without reading the status code. | `bootstrap/app.php` renders the validation envelope only. | The exception renderer now applies the same envelope to every API failure. |
| 4 | **Medium** | The verification remarks collected on the extraction panel were discarded, because the server accepted and stored nothing but the corrected text. | `OcrController::verify` validated `verified_text` alone and `OcrTextExtractionService::verifyExtraction` recorded only the identifiers. | The remarks are an optional field of the verification and are written to the audit trail with the verification itself. `OcrVerificationContractTest` proves that the corrected text, the remarks, the required field, the capability and the unit boundary all behave. |

---

## 5. Performance observations

Measured with `php artisan dms:profile-queries` against the staging database.
Every operational statement uses an index; no statement performs a filesort
on a large table.

| Area | Observation |
|---|---|
| Document listing | `owner_unit_id` and `lifecycle` are served by a composite index; the paging offset is applied on the indexed column. |
| Document retrieval | A single joined primary-key lookup across the category and classification references. |
| Document search | The unit restriction is applied before the `LIKE` predicates, so the index remains usable. |
| Correspondence registry | The type filter and the registration ordering are served by the composite index. |
| Workflow task inbox | The user and unit task filters each use their own index. |
| Context resolution | The active organizational context is resolved with a bounded number of queries; the grants are loaded once per model instance and never cached across a request. |

No permission caching was introduced. The grant load is performed per request,
which is the price of never serving a stale authorization decision.

---

## 6. Reproduction

```bash
cd server
php artisan migrate
php artisan test --filter=AuthorizationResolutionRegressionTest
php artisan test --filter=FrontendApiContractTest
php artisan test --filter=AssignmentParticipantAudit
php artisan test
php artisan dms:profile-queries

cd ../client
npx vue-tsc -b --noEmit
npx vitest run
npx vite build
```

<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AccreditationController;
use App\Http\Controllers\Api\V1\ArchiveController;
use App\Http\Controllers\Api\V1\AssignmentController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CorrespondenceController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\DocumentMovementController;
use App\Http\Controllers\Api\V1\GlobalSearchController;
use App\Http\Controllers\Api\V1\InstitutionalModuleController;
use App\Http\Controllers\Api\V1\MetadataController;
use App\Http\Controllers\Api\V1\NavigationController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OcrController;
use App\Http\Controllers\Api\V1\OrganizationUnitController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RolePermissionController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\TemplateController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WorkflowController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Pure Wollo University DMS API Routes (118-Table Baseline)
| Production-Grade Scoped RBAC & Server-Side Security Boundaries
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // 1. Public Authentication (Rate Limited)
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

        // Account recovery. Both endpoints are unauthenticated and rate
        // limited per normalized account and per source address.
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware('throttle:password-reset');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])
            ->middleware('throttle:password-reset');

        // Address confirmation is proven by the identifier and the address
        // hash carried in the link, so it does not require an active session.
        Route::get('verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail']);
    });

    // Configuration published on the public portal. Only the keys declared in
    // the public catalogue are exposed, and no value is a secret.
    Route::get('settings/public', [SettingsController::class, 'publicSettings']);

    // 2. Protected Endpoints (Sanctum SPA Session + Authz Version + Active Account Boundary)
    Route::middleware(['auth:sanctum', 'authz_version', 'active_account'])->group(function () {

        // Session & Profile
        Route::prefix('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::put('password', [AuthController::class, 'changePassword']);
            Route::post('active-unit', [AuthController::class, 'setActiveUnit']);
            Route::post('email/verification-notification', [AuthController::class, 'sendVerificationEmail']);
        });

        // Reference Vocabularies (Read-only for all active accounts)
        Route::prefix('metadata')->group(function () {
            Route::get('all', [MetadataController::class, 'all']);
            Route::get('categories', [MetadataController::class, 'categories']);
            Route::get('confidentiality-levels', [MetadataController::class, 'confidentialityLevels']);
            Route::get('correspondence-types', [MetadataController::class, 'correspondenceTypes']);
            Route::get('priorities', [MetadataController::class, 'priorities']);
            Route::get('dispatch-methods', [MetadataController::class, 'dispatchMethods']);
        });

        // Notifications
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);

        // Dynamic Role- & Permission-Filtered Navigation Menu
        Route::get('navigation', [NavigationController::class, 'index']);

        // Executive Dashboard & Reports
        Route::get('dashboard/metrics', [DashboardController::class, 'metrics'])
            ->middleware('permission:documents.view');
        Route::get('reports/summary', [ReportController::class, 'summary'])
            ->middleware('permission:reports.view');

        // Organization & Identity
        Route::get('unit-types', [OrganizationUnitController::class, 'unitTypes']);
        Route::get('organization-units/tree', [OrganizationUnitController::class, 'tree'])
            ->middleware('permission:organization.view');
        Route::get('organization-units', [OrganizationUnitController::class, 'index'])
            ->middleware('permission:organization.view|documents.view|correspondence.view|workflow.view');
        Route::get('organization-units/{id}', [OrganizationUnitController::class, 'show'])
            ->middleware('permission:organization.view|documents.view|correspondence.view|workflow.view');
        Route::post('organization-units', [OrganizationUnitController::class, 'store'])
            ->middleware('permission:organization.manage');
        Route::put('organization-units/{id}', [OrganizationUnitController::class, 'update'])
            ->middleware('permission:organization.manage');
        Route::delete('organization-units/{id}', [OrganizationUnitController::class, 'destroy'])
            ->middleware('permission:organization.manage');

        // Users & Unit Memberships
        Route::get('users', [UserController::class, 'index'])
            ->middleware('permission:users.view|staff.view|correspondence.assign|correspondence.register|workflow.review|workflow.view');
        Route::get('users/{id}', [UserController::class, 'show'])
            ->middleware('permission:users.view|staff.view|correspondence.assign|correspondence.register|workflow.review|workflow.view');
        Route::post('users', [UserController::class, 'store'])
            ->middleware('permission:users.create');
        Route::put('users/{id}', [UserController::class, 'update'])
            ->middleware('permission:users.update');
        Route::patch('users/{id}/status', [UserController::class, 'updateStatus'])
            ->middleware('permission:users.deactivate');
        Route::post('users/{id}/memberships', [UserController::class, 'assignMembership'])
            ->middleware('permission:users.update');

        // Scoped RBAC
        Route::get('roles', [RolePermissionController::class, 'roles'])
            ->middleware('permission:roles.view');
        Route::post('roles', [RolePermissionController::class, 'storeRole'])
            ->middleware('permission:roles.create');
        Route::get('roles/{id}', [RolePermissionController::class, 'showRole'])
            ->middleware('permission:roles.view');
        Route::put('roles/{id}', [RolePermissionController::class, 'updateRole'])
            ->middleware('permission:roles.update');
        Route::post('roles/{id}/permissions', [RolePermissionController::class, 'syncRolePermissions'])
            ->middleware('permission:roles.update');
        Route::get('permissions', [RolePermissionController::class, 'permissions'])
            ->middleware('permission:permissions.view');
        Route::get('authorization-scopes', [RolePermissionController::class, 'scopes'])
            ->middleware('permission:roles.view');
        Route::post('user-roles', [RolePermissionController::class, 'assignUserRole'])
            ->middleware('permission:roles.assign');
        Route::delete('user-roles', [RolePermissionController::class, 'revokeUserRole'])
            ->middleware('permission:roles.assign');
        Route::post('user-permissions', [RolePermissionController::class, 'assignUserPermission'])
            ->middleware('permission:permissions.assign');
        Route::delete('user-permissions', [RolePermissionController::class, 'revokeUserPermission'])
            ->middleware('permission:permissions.assign');

        // Document Repository & Central Operations
        Route::get('documents', [DocumentController::class, 'index'])
            ->middleware('permission:documents.view');
        Route::post('documents', [DocumentController::class, 'store'])
            ->middleware('permission:documents.create');
        Route::get('documents/{id}', [DocumentController::class, 'show'])
            ->middleware('permission:documents.view');
        Route::put('documents/{id}', [DocumentController::class, 'update'])
            ->middleware('permission:documents.update');
        Route::post('documents/{id}/versions', [DocumentController::class, 'uploadVersion'])
            ->middleware('permission:documents.version');
        Route::get('files/{fileId}/download', [DocumentController::class, 'downloadFile'])
            ->middleware('permission:documents.download');
        Route::get('documents/{id}/timeline', [DocumentController::class, 'timeline'])
            ->middleware('permission:documents.view');
        Route::post('documents/{id}/digitize', [DocumentController::class, 'digitizePaper'])
            ->middleware('permission:documents.version');
        Route::get('documents/{id}/physical-originals', [DocumentController::class, 'physicalOriginals'])
            ->middleware('permission:documents.view');
        Route::post('documents/{id}/physical-originals', [DocumentController::class, 'storePhysicalOriginal'])
            ->middleware('permission:documents.create');

        // Document Movements & Custody Receipts
        Route::get('documents/{id}/movements', [DocumentMovementController::class, 'index'])
            ->middleware('permission:documents.view');
        Route::post('documents/{id}/movements', [DocumentMovementController::class, 'store'])
            ->middleware('permission:correspondence.forward');
        Route::post('movements/{id}/acknowledge', [DocumentMovementController::class, 'acknowledge'])
            ->middleware('permission:correspondence.acknowledge');

        // Document Operational Directives & Assignments
        Route::get('assignments', [AssignmentController::class, 'index'])
            ->middleware('permission:documents.view');
        Route::get('documents/{id}/assignments', [AssignmentController::class, 'index'])
            ->middleware('permission:documents.view');
        Route::post('documents/{id}/assignments', [AssignmentController::class, 'store'])
            ->middleware('permission:correspondence.assign');
        Route::post('assignments/{id}/participants', [AssignmentController::class, 'addParticipant'])
            ->middleware('permission:correspondence.assign');
        Route::delete('assignments/{id}/participants/{userId}', [AssignmentController::class, 'removeParticipant'])
            ->middleware('permission:correspondence.assign');
        Route::post('assignments/{id}/events', [AssignmentController::class, 'recordEvent'])
            ->middleware('permission:documents.view');
        Route::post('assignments/{id}/responses', [AssignmentController::class, 'linkResponse'])
            ->middleware('permission:documents.create');

        // Institutional Document Templates
        Route::get('templates', [TemplateController::class, 'index'])
            ->middleware('permission:documents.view');
        Route::post('templates', [TemplateController::class, 'store'])
            ->middleware('permission:documents.create');
        Route::get('templates/{id}', [TemplateController::class, 'show'])
            ->middleware('permission:documents.view');
        Route::post('templates/{id}/versions', [TemplateController::class, 'storeVersion'])
            ->middleware('permission:documents.create');
        Route::post('templates/{id}/versions/{versionId}/publish', [TemplateController::class, 'publishVersion'])
            ->middleware('permission:documents.create');
        Route::post('templates/{id}/render', [TemplateController::class, 'render'])
            ->middleware('permission:documents.create');
        Route::post('templates/{id}/generate-document', [TemplateController::class, 'render'])
            ->middleware('permission:documents.create');

        // Bilingual OCR Text Extractions & Processing
        Route::get('ocr/status', [OcrController::class, 'status'])
            ->middleware('permission:documents.view');
        Route::post('documents/{id}/versions/{versionId}/extractions/process', [OcrController::class, 'process'])
            ->middleware('permission:documents.version');
        Route::post('documents/{id}/versions/{versionId}/extractions', [OcrController::class, 'extract'])
            ->middleware('permission:documents.version');
        Route::get('documents/{id}/versions/{versionId}/extractions', [OcrController::class, 'extractions'])
            ->middleware('permission:documents.view');
        Route::post('documents/{id}/versions/{versionId}/extractions/{extractionId}/retry', [OcrController::class, 'retry'])
            ->middleware('permission:documents.version');
        Route::post('documents/{id}/versions/{versionId}/extractions/{extractionId}/verify', [OcrController::class, 'verify'])
            ->middleware('permission:documents.update');
        Route::post('extractions/{id}/verify', [OcrController::class, 'verify'])
            ->middleware('permission:documents.update');

        // Global Repository & Full-Text Search
        Route::get('search/documents', [GlobalSearchController::class, 'search'])
            ->middleware('permission:documents.view');

        // Correspondence Management
        Route::get('correspondences', [CorrespondenceController::class, 'index'])
            ->middleware('permission:correspondence.view');
        Route::post('correspondences', [CorrespondenceController::class, 'store'])
            ->middleware('permission:correspondence.register');
        Route::get('correspondences/{id}', [CorrespondenceController::class, 'show'])
            ->middleware('permission:correspondence.view');
        Route::post('correspondences/{id}/movements', [CorrespondenceController::class, 'recordMovement'])
            ->middleware('permission:correspondence.forward');
        Route::post('correspondences/{id}/movements/{movementId}/acknowledge', [CorrespondenceController::class, 'acknowledgeMovement'])
            ->middleware('permission:correspondence.acknowledge');
        Route::post('correspondences/{id}/assignments', [CorrespondenceController::class, 'assign'])
            ->middleware('permission:correspondence.assign');
        Route::post('correspondences/{id}/linked-response', [CorrespondenceController::class, 'prepareLinkedResponse'])
            ->middleware('permission:correspondence.register');
        Route::post('correspondences/{id}/dispatch', [CorrespondenceController::class, 'dispatch'])
            ->middleware('permission:correspondence.dispatch');

        // Workflow & Routing
        Route::get('workflows/definitions', [WorkflowController::class, 'definitions'])
            ->middleware('permission:workflow.view');
        Route::get('workflows/tasks', [WorkflowController::class, 'tasks'])
            ->middleware('permission:workflow.view');
        Route::post('workflows/instances', [WorkflowController::class, 'startInstance'])
            ->middleware('permission:workflow.review');
        Route::post('workflows/tasks/{id}/decide', [WorkflowController::class, 'decide'])
            ->middleware('permission:workflow.review');
        Route::post('workflows/tasks/{id}/delegate', [WorkflowController::class, 'delegate'])
            ->middleware('permission:workflow.review');
        Route::post('workflows/tasks/{id}/reassign', [WorkflowController::class, 'reassign'])
            ->middleware('permission:workflow.reassign');
        Route::post('workflows/tasks/{id}/participants', [WorkflowController::class, 'addParticipants'])
            ->middleware('permission:workflow.review');

        // Archive & Retention
        Route::get('archive/collections', [ArchiveController::class, 'collections'])
            ->middleware('permission:archive.view');
        Route::get('archive/policies', [ArchiveController::class, 'policies'])
            ->middleware('permission:archive.view');
        Route::get('archive/entries', [ArchiveController::class, 'entries'])
            ->middleware('permission:archive.view');
        Route::post('archive/transfer', [ArchiveController::class, 'transfer'])
            ->middleware('permission:archive.transfer');
        Route::get('archive/legal-holds', [ArchiveController::class, 'legalHolds'])
            ->middleware('permission:archive.view');

        // Security Audit Trail
        Route::get('audit-events', [AuditLogController::class, 'index'])
            ->middleware('permission:audit.view');

        // Specialized Institutional Operations
        Route::get('research/outputs', [InstitutionalModuleController::class, 'research'])
            ->middleware('permission:research.view');
        Route::get('research/outputs/{id}', [InstitutionalModuleController::class, 'showResearch'])
            ->middleware('permission:research.view');
        Route::post('research/outputs', [InstitutionalModuleController::class, 'storeResearch'])
            ->middleware('permission:research.create');
        Route::put('research/outputs/{id}', [InstitutionalModuleController::class, 'updateResearch'])
            ->middleware('permission:research.update');
        Route::post('research/outputs/{id}/versions', [InstitutionalModuleController::class, 'uploadResearchVersion'])
            ->middleware('permission:research.update');
        Route::post('research/outputs/{id}/toggle-publication', [InstitutionalModuleController::class, 'toggleResearchPublication'])
            ->middleware('permission:research.publish');

        Route::get('staff/records', [InstitutionalModuleController::class, 'staffRecords'])
            ->middleware('permission:staff.view');
        Route::post('staff/records', [InstitutionalModuleController::class, 'storeStaffRecord'])
            ->middleware('permission:staff.update');
        Route::post('staff/records/{id}/verify-scan', [InstitutionalModuleController::class, 'verifyStaffScan'])
            ->middleware('permission:staff.update');
        Route::post('staff/records/{id}/dispose-physical', [InstitutionalModuleController::class, 'disposeStaffPhysicalOriginal'])
            ->middleware('permission:archive.approve_disposal');

        // Quality Assurance & HERQA Accreditation
        Route::get('accreditation/frameworks', [AccreditationController::class, 'frameworks'])
            ->middleware('permission:accreditation.view');
        Route::get('accreditation/standards', [AccreditationController::class, 'standards'])
            ->middleware('permission:accreditation.view');
        Route::get('accreditation/cycles', [AccreditationController::class, 'cycles'])
            ->middleware('permission:accreditation.view');
        Route::get('accreditation/cycles/{id}', [AccreditationController::class, 'showCycle'])
            ->middleware('permission:accreditation.view');
        Route::post('accreditation/cycles', [AccreditationController::class, 'storeCycle'])
            ->middleware('permission:accreditation.manage');
        Route::put('accreditation/cycles/{id}', [AccreditationController::class, 'updateCycle'])
            ->middleware('permission:accreditation.manage');
        Route::get('accreditation/cycles/{id}/summary', [AccreditationController::class, 'summary'])
            ->middleware('permission:accreditation.view');
        Route::get('accreditation/cycles/{id}/export', [AccreditationController::class, 'export'])
            ->middleware('permission:accreditation.export');

        Route::get('accreditation/evidence', [AccreditationController::class, 'evidence'])
            ->middleware('permission:accreditation.view');
        Route::get('accreditation/evidence/{id}', [AccreditationController::class, 'showEvidence'])
            ->middleware('permission:accreditation.view');
        Route::post('accreditation/evidence', [AccreditationController::class, 'storeEvidence'])
            ->middleware('permission:accreditation.view');
        Route::post('accreditation/evidence/{id}/reviews', [AccreditationController::class, 'reviewEvidence'])
            ->middleware('permission:accreditation.review_evidence');
        Route::get('projects', [InstitutionalModuleController::class, 'projects'])
            ->middleware('permission:projects.view');
        Route::post('projects', [InstitutionalModuleController::class, 'storeProject'])
            ->middleware('permission:projects.submit');

        Route::get('signatures', [InstitutionalModuleController::class, 'signatures'])
            ->middleware('permission:signatures.request');
        Route::post('signatures', [InstitutionalModuleController::class, 'storeSignatureRequest'])
            ->middleware('permission:signatures.request');
        Route::post('signatures/{id}/execute', [InstitutionalModuleController::class, 'executeSignature'])
            ->middleware('permission:signatures.request');
        Route::post('signatures/{id}/verify', [InstitutionalModuleController::class, 'verifySignature'])
            ->middleware('permission:signatures.request');
        Route::post('signatures/verify-file', [InstitutionalModuleController::class, 'verifyPdfFile'])
            ->middleware('permission:signatures.request');
        Route::get('signatures/identities', [InstitutionalModuleController::class, 'identities'])
            ->middleware('permission:signatures.request');

        Route::get('procedures', [InstitutionalModuleController::class, 'procedures'])
            ->middleware('permission:roles.view');
        Route::post('procedures', [InstitutionalModuleController::class, 'storeProcedure'])
            ->middleware('permission:roles.view');

        Route::get('operations/status', [InstitutionalModuleController::class, 'operations'])
            ->middleware('permission:system.configure');

        // Runtime System Configuration
        Route::prefix('settings')->group(function () {
            Route::get('/', [SettingsController::class, 'index'])
                ->middleware('permission:system.configure');
            Route::get('categories', [SettingsController::class, 'categories'])
                ->middleware('permission:system.configure');
            Route::put('/', [SettingsController::class, 'update'])
                ->middleware('permission:system.configure');
            Route::post('categories/{category}/reset', [SettingsController::class, 'resetCategory'])
                ->middleware('permission:system.configure');
            Route::post('{key}/reset', [SettingsController::class, 'reset'])
                ->middleware('permission:system.configure');
        });
    });
});

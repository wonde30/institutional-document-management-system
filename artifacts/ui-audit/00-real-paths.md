# Real Source File Paths

## Views (70 files)

### Account
- `client/src/views/account/AccountProfileView.vue`

### Accreditation
- `client/src/views/accreditation/AccreditationEvidenceView.vue`

### Admin (4 files)
- `client/src/views/admin/DataImportsExportsView.vue`
- `client/src/views/admin/InstitutionalConfigurationCenterView.vue`
- `client/src/views/admin/ReferenceDataView.vue`
- `client/src/views/admin/SystemSettingsView.vue`

### Archive
- `client/src/views/archive/ArchiveListView.vue`

### Audit
- `client/src/views/audit/AuditLogView.vue`

### Auth (4 files)
- `client/src/views/auth/ForgotPasswordView.vue`
- `client/src/views/auth/LoginView.vue`
- `client/src/views/auth/ResetPasswordView.vue`
- `client/src/views/auth/VerifyEmailView.vue`

### Correspondence
- `client/src/views/correspondence/CorrespondenceRegistryView.vue`

### Dashboard
- `client/src/views/dashboard/DashboardView.vue`

### Documents (3 files)
- `client/src/views/documents/DocumentCreateView.vue`
- `client/src/views/documents/DocumentDetailView.vue`
- `client/src/views/documents/DocumentListView.vue`

### Errors (2 files)
- `client/src/views/errors/ForbiddenView.vue`
- `client/src/views/errors/NotFoundView.vue`

### Help
- `client/src/views/help/HelpGuideView.vue`

### Notifications
- `client/src/views/notifications/NotificationsView.vue`

### Operations
- `client/src/views/operations/OperationsView.vue`

### Organization
- `client/src/views/organization/OrganizationUnitsView.vue`

### Procedures
- `client/src/views/procedures/OfficeProceduresView.vue`

### Projects
- `client/src/views/projects/ProjectsInnovationView.vue`

### Public (3 files)
- `client/src/views/public/LandingPageView.vue`
- `client/src/views/public/PrivacyPolicyView.vue`
- `client/src/views/public/TermsConditionsView.vue`

### RBAC (2 files)
- `client/src/views/rbac/PermissionManagementView.vue`
- `client/src/views/rbac/RoleManagementView.vue`

### Reports
- `client/src/views/reports/ReportsView.vue`

### Research
- `client/src/views/research/FinalizedResearchView.vue`

### Search
- `client/src/views/search/GlobalSearchView.vue`

### Signatures
- `client/src/views/signatures/SignaturesStampsView.vue`

### Staff
- `client/src/views/staff/StaffRecordsView.vue`

### Templates
- `client/src/views/templates/TemplateBuilderView.vue`

### Users
- `client/src/views/users/UserManagementView.vue`

### Workflows
- `client/src/views/workflows/WorkflowTasksView.vue`

---

## Components (23 files)

### UI Components (11 files)
- `client/src/components/ui/BaseAlert.vue`
- `client/src/components/ui/BaseButton.vue`
- `client/src/components/ui/BaseInput.vue`
- `client/src/components/ui/BaseSelect.vue`
- `client/src/components/ui/BaseTextarea.vue`
- `client/src/components/ui/EmptyState.vue`
- `client/src/components/ui/LoadingSkeleton.vue`
- `client/src/components/ui/ToastContainer.vue`

### Data Display (2 files)
- `client/src/components/data-display/DataTable.vue`
- `client/src/components/data-display/SearchFilterBar.vue`

### Other Components (10 files)
- `client/src/components/ClassificationBadge.vue`
- `client/src/components/letters/OfficialLetterA4Preview.vue`
- `client/src/components/ModalDialog.vue`
- `client/src/components/navigation/AppSidebar.vue`
- `client/src/components/PageHeader.vue`
- `client/src/components/Pagination.vue`
- `client/src/components/PriorityBadge.vue`
- `client/src/components/public/PublicFooter.vue`
- `client/src/components/public/PublicNavbar.vue`
- `client/src/components/StatusBadge.vue`

---

## Router & Navigation
- `client/src/router/index.ts` (router config)
- `client/src/config/navigation.ts` (sidebar navigation config)

---

## Layouts
- `client/src/layouts/AppLayout.vue`

---

## Stores (4 files)
- `client/src/stores/auth.ts`
- `client/src/stores/metadata.ts`
- `client/src/stores/notifications.ts`
- `client/src/stores/theme.ts`

---

## Assets & Config
- `client/src/assets/main.css` (Tailwind + custom tokens)
- `client/src/main.ts` (app entry)
- `client/src/types/index.ts` (TypeScript types)

---

## Composables
- `client/src/composables/useInstitution.ts`
- `client/src/composables/useToast.ts`

---

## Utils
- `client/src/utils/ethiopianDate.ts`
- `client/src/utils/formatters.ts`

---

## API
- `client/src/api/client.ts`
- `client/src/api/sessionEvents.ts`

---

## Path Mapping from Old Audit Files (CORRECTED)

| Old Audit Citation | Real Path | Status |
|--------------------|-----------|--------|
| `src/assets/main.css:153` | `client/src/assets/main.css:153` | ✅ Exists |
| `src/router/navigation.ts` | `client/src/config/navigation.ts` | ✅ Exists (different path) |
| `src/views/admin/UserManagementView.vue` | `client/src/views/users/UserManagementView.vue` | ✅ Exists (different folder) |
| `src/views/admin/RoleManagementView.vue` | `client/src/views/rbac/RoleManagementView.vue` | ✅ Exists (different folder) |
| `src/views/admin/PermissionManagementView.vue` | `client/src/views/rbac/PermissionManagementView.vue` | ✅ Exists (different folder) |
| `src/components/PageHeader.vue` | `client/src/components/PageHeader.vue` | ✅ Exists |
| `src/components/DataTable.vue` | `client/src/components/data-display/DataTable.vue` | ✅ Exists (different folder) |
| `src/views/DashboardView.vue` | `client/src/views/dashboard/DashboardView.vue` | ✅ Exists |
| `src/views/NotificationsView.vue` | `client/src/views/notifications/NotificationsView.vue` | ✅ Exists |
| `src/views/WorkflowTasksView.vue` | `client/src/views/workflows/WorkflowTasksView.vue` | ✅ Exists |
| `src/views/FinalizedResearchView.vue` | `client/src/views/research/FinalizedResearchView.vue` | ✅ Exists |
| `src/views/StaffRecordsView.vue` | `client/src/views/staff/StaffRecordsView.vue` | ✅ Exists |
| `src/layouts/AppLayout.vue` | `client/src/layouts/AppLayout.vue` | ✅ Exists |
| `src/views/DocumentDetailView.vue` | `client/src/views/documents/DocumentDetailView.vue` | ✅ Exists |
| `src/views/GlobalSearchView.vue` | `client/src/views/search/GlobalSearchView.vue` | ✅ Exists |
| `src/views/CorrespondenceRegistryView.vue` | `client/src/views/correspondence/CorrespondenceRegistryView.vue` | ✅ Exists |

---

## Note: Admin Views Split

The old audit cited "admin/" for UserManagementView, RoleManagementView, PermissionManagementView. Real structure:
- **Users**: `client/src/views/users/UserManagementView.vue`
- **RBAC**: `client/src/views/rbac/RoleManagementView.vue`, `client/src/views/rbac/PermissionManagementView.vue`
- **Admin settings**: `client/src/views/admin/InstitutionalConfigurationCenterView.vue`, `SystemSettingsView.vue`, `ReferenceDataView.vue`, `DataImportsExportsView.vue`
- **Operations**: `client/src/views/operations/OperationsView.vue`
- **Procedures**: `client/src/views/procedures/OfficeProceduresView.vue`
- **Organization**: `client/src/views/organization/OrganizationUnitsView.vue`
- **Audit**: `client/src/views/audit/AuditLogView.vue`

Total: **70 views, 23 components, 2 layouts**

# Pass 0: Route and View Inventory

**Generated**: 2025-01-XX  
**Project**: WU-DMS (Vue 3 + TypeScript + Tailwind CSS 4 + Pinia + Laravel API)  
**Total Routes**: 54 (38 unique views + 16 redirects)  
**Total View Files**: 38

---

## Layout Architecture

**Root**: `App.vue`  
**Decision**: Public/guest routes (`meta.isPublic` or `meta.guestOnly` or `!authStore.isAuthenticated`) → naked `<router-view />`. Authenticated routes → wrapped in `<AppLayout>`.

**AppLayout** (`src/layouts/AppLayout.vue`):
- Top bar (56px sticky): global search, theme toggle (3-mode), notifications dropdown, user dropdown, unit switcher (multi-unit users only), mobile menu toggle
- Sidebar (left, 240px desktop, slide-over on mobile): `<AppSidebar>`
- Main content (flex-1, bg-canvas-soft, scrollable viewport)
- Global toast container
- Skip-to-main-content link (keyboard accessibility)

**Public Routes**: No layout, naked views for landing, login, privacy, terms.

**Error Routes**: Minimal layout, no AppLayout.

---

## Complete Route Inventory

| Route Name | Path | View File | Layout | Permission(s) | Group | Shared Components Imported (detected in view) |
|------------|------|-----------|--------|---------------|-------|---------------------------------------------|
| `login` | `/login` | `auth/LoginView.vue` | Naked | — | — | BaseButton, BaseInput, BaseAlert |
| `forgot-password` | `/forgot-password` | `auth/ForgotPasswordView.vue` | Naked | — | — | BaseButton, BaseInput, BaseAlert |
| `reset-password` | `/reset-password` | `auth/ResetPasswordView.vue` | Naked | — | — | BaseButton, BaseInput, BaseAlert |
| `verify-email` | `/verify-email` | `auth/VerifyEmailView.vue` | Naked | — | — | BaseButton, BaseAlert |
| `account.profile` | `/account` | `account/AccountProfileView.vue` | AppLayout | — | Workspace | PageHeader, BaseInput, BaseButton |
| `account.security` | `/account/security` | `account/AccountProfileView.vue` | AppLayout | — | Workspace | PageHeader, BaseInput, BaseButton |
| `account.preferences` | `/account/preferences` | `account/AccountProfileView.vue` | AppLayout | — | Workspace | PageHeader, BaseInput, BaseButton, BaseSelect |
| `landing` | `/` | `public/LandingPageView.vue` | Naked | — | — | PublicNavbar, PublicFooter |
| `public.privacy` | `/privacy-policy` | `public/PrivacyPolicyView.vue` | Naked | — | — | PublicNavbar, PublicFooter |
| `public.terms` | `/terms` | `public/TermsConditionsView.vue` | Naked | — | — | PublicNavbar, PublicFooter |
| `dashboard` | `/dashboard` | `dashboard/DashboardView.vue` | AppLayout | `documents.view`, `workflow.view` (OR) | Workspace | PageHeader, BaseButton, LoadingSkeleton |
| `tasks.list` | `/tasks` | `workflows/WorkflowTasksView.vue` | AppLayout | `workflow.view` | Workspace | PageHeader, SearchFilterBar, DataTable, StatusBadge, PriorityBadge, EmptyState, LoadingSkeleton |
| `— (redirect)` | `/workflows/tasks` | Redirect → `/tasks` | — | — | — | — |
| `notifications` | `/notifications` | `notifications/NotificationsView.vue` | AppLayout | — | Workspace | PageHeader, BaseButton, EmptyState, LoadingSkeleton |
| `documents.list` | `/documents` | `documents/DocumentListView.vue` | AppLayout | `documents.view` | Records | PageHeader, SearchFilterBar, DataTable, StatusBadge, ClassificationBadge, EmptyState, LoadingSkeleton, BaseButton |
| `templates.list` | `/templates` | `templates/TemplateBuilderView.vue` | AppLayout | `documents.create` | Records | PageHeader, DataTable, ModalDialog, BaseInput, BaseButton, BaseTextarea, EmptyState, LoadingSkeleton |
| `documents.create` | `/documents/create` | `documents/DocumentCreateView.vue` | AppLayout | `documents.create` | Records | PageHeader, BaseInput, BaseSelect, BaseTextarea, BaseButton, BaseAlert |
| `— (redirect)` | `/documents/new` | Redirect → `/documents/create` | — | — | — | — |
| `documents.detail` | `/documents/:id` | `documents/DocumentDetailView.vue` | AppLayout | `documents.view` | Records | StatusBadge, ClassificationBadge, PriorityBadge, BaseButton, ModalDialog, BaseAlert, LoadingSkeleton |
| `global.search` | `/search` | `search/GlobalSearchView.vue` | AppLayout | `documents.view` | Records | SearchFilterBar, DataTable, EmptyState, LoadingSkeleton, StatusBadge, ClassificationBadge |
| `correspondence.registry` | `/correspondence` | `correspondence/CorrespondenceRegistryView.vue` | AppLayout | `correspondence.view` | Records | SearchFilterBar, DataTable, StatusBadge, EmptyState, LoadingSkeleton, BaseButton, ModalDialog |
| `letters.incoming` | `/letters/incoming` | `correspondence/CorrespondenceRegistryView.vue` | AppLayout | `correspondence.view` | Records | (same as correspondence.registry) |
| `letters.outgoing` | `/letters/outgoing` | `correspondence/CorrespondenceRegistryView.vue` | AppLayout | `correspondence.view` | Records | (same as correspondence.registry) |
| `memoranda.list` | `/memoranda` | `correspondence/CorrespondenceRegistryView.vue` | AppLayout | `correspondence.view` | Records | (same as correspondence.registry) |
| `routing.list` | `/routing` | `correspondence/CorrespondenceRegistryView.vue` | AppLayout | `correspondence.view` | Records | (same as correspondence.registry) |
| `research.outputs` | `/research` | `research/FinalizedResearchView.vue` | AppLayout | `research.view` | Institutional records | PageHeader, SearchFilterBar, DataTable, StatusBadge, EmptyState, LoadingSkeleton, BaseButton |
| `staff.records` | `/staff` | `staff/StaffRecordsView.vue` | AppLayout | `staff.view` | Institutional records | PageHeader, SearchFilterBar, DataTable, EmptyState, LoadingSkeleton, BaseButton, ModalDialog |
| `— (redirect)` | `/hr/records` | Redirect → `/staff` | — | — | — | — |
| `— (redirect)` | `/physical-originals` | Redirect → `/staff` | — | — | — | — |
| `archive.list` | `/archive` | `archive/ArchiveListView.vue` | AppLayout | `archive.view` | Institutional records | PageHeader, SearchFilterBar, DataTable, StatusBadge, EmptyState, LoadingSkeleton, BaseButton |
| `accreditation.evidence` | `/accreditation` | `accreditation/AccreditationEvidenceView.vue` | AppLayout | `accreditation.view` | Institutional records | PageHeader, SearchFilterBar, DataTable, StatusBadge, EmptyState, LoadingSkeleton, BaseButton, ModalDialog |
| `projects.innovation` | `/projects` | `projects/ProjectsInnovationView.vue` | AppLayout | `projects.view` | Institutional records | PageHeader, SearchFilterBar, DataTable, StatusBadge, EmptyState, LoadingSkeleton, BaseButton, ModalDialog |
| `signatures.stamps` | `/signatures` | `signatures/SignaturesStampsView.vue` | AppLayout | `signatures.request` | Trust & reporting | PageHeader, SearchFilterBar, DataTable, StatusBadge, EmptyState, LoadingSkeleton, BaseButton, ModalDialog |
| `reports` | `/reports` | `reports/ReportsView.vue` | AppLayout | `reports.view` | Trust & reporting | PageHeader, BaseButton, BaseSelect, EmptyState, LoadingSkeleton |
| `data.exports` | `/exports` | `admin/DataImportsExportsView.vue` | AppLayout | `data.import`, `data.export` (OR) | Trust & reporting | PageHeader, DataTable, BaseButton, BaseInput, EmptyState, LoadingSkeleton |
| `— (redirect)` | `/imports` | Redirect → `/exports` | — | — | — | — |
| `admin.users` | `/admin/users` | `users/UserManagementView.vue` | AppLayout | `users.view` | Administration | SearchFilterBar, DataTable, StatusBadge, EmptyState, LoadingSkeleton, BaseButton, ModalDialog, BaseInput, BaseSelect |
| `— (redirect)` | `/users` | Redirect → `/admin/users` | — | — | — | — |
| `admin.roles` | `/admin/roles` | `rbac/RoleManagementView.vue` | AppLayout | `roles.view` | Administration | PageHeader, DataTable, StatusBadge, EmptyState, LoadingSkeleton, BaseButton, ModalDialog, BaseInput, BaseSelect |
| `— (redirect)` | `/roles` | Redirect → `/admin/roles` | — | — | — | — |
| `admin.organization` | `/admin/organization` | `organization/OrganizationUnitsView.vue` | AppLayout | `organization.view` | Administration | PageHeader, DataTable, EmptyState, LoadingSkeleton, BaseButton, ModalDialog, BaseInput, BaseSelect |
| `— (redirect)` | `/organization-units` | Redirect → `/admin/organization` | — | — | — | — |
| `admin.procedures` | `/admin/office-procedures` | `procedures/OfficeProceduresView.vue` | AppLayout | `roles.view` | Administration | SearchFilterBar, DataTable, StatusBadge, EmptyState, LoadingSkeleton, BaseButton, ModalDialog, BaseInput, BaseTextarea |
| `— (redirect)` | `/procedures` | Redirect → `/admin/office-procedures` | — | — | — | — |
| `admin.permissions` | `/admin/permissions` | `rbac/PermissionManagementView.vue` | AppLayout | `permissions.view` | Administration | SearchFilterBar, DataTable, EmptyState, LoadingSkeleton, BaseButton |
| `— (redirect)` | `/permissions` | Redirect → `/admin/permissions` | — | — | — | — |
| `admin.configuration` | `/admin/configuration` | `admin/InstitutionalConfigurationCenterView.vue` | AppLayout | `system.configure` | Administration | SearchFilterBar, DataTable, BaseButton, ModalDialog, BaseInput, BaseSelect, BaseTextarea, EmptyState, LoadingSkeleton |
| `— (redirect)` | `/admin/workflows` | Redirect → `/admin/configuration?tab=workflows` | — | — | — | — |
| `— (redirect)` | `/admin/numbering` | Redirect → `/admin/configuration?tab=numbering` | — | — | — | — |
| `— (redirect)` | `/admin/routing-policies` | Redirect → `/admin/configuration?tab=routing` | — | — | — | — |
| `— (redirect)` | `/admin/retention` | Redirect → `/admin/configuration?tab=retention` | — | — | — | — |
| `admin.reference_data` | `/admin/reference-data` | `admin/ReferenceDataView.vue` | AppLayout | `roles.view` | Administration | SearchFilterBar, DataTable, BaseButton, ModalDialog, BaseInput, EmptyState, LoadingSkeleton |
| `admin.audit` | `/admin/audit` | `audit/AuditLogView.vue` | AppLayout | `audit.view` | Administration | SearchFilterBar, DataTable, StatusBadge, EmptyState, LoadingSkeleton |
| `— (redirect)` | `/audit-events` | Redirect → `/admin/audit` | — | — | — | — |
| `admin.operations` | `/admin/operations` | `operations/OperationsView.vue` | AppLayout | `system.configure` | Administration | BaseButton, LoadingSkeleton, EmptyState |
| `— (redirect)` | `/operations` | Redirect → `/admin/operations` | — | — | — | — |
| `admin.settings` | `/admin/settings` | `admin/SystemSettingsView.vue` | AppLayout | `system.configure` | Administration | BaseInput, BaseSelect, BaseButton, LoadingSkeleton |
| `help.guide` | `/help` | `help/HelpGuideView.vue` | AppLayout | — | Administration | — (minimal, hardcoded content) |
| `error.forbidden` | `/forbidden` | `errors/ForbiddenView.vue` | AppLayout | — | — | BaseButton |
| `error.not_found` | `/not-found` | `errors/NotFoundView.vue` | AppLayout | — | — | BaseButton |
| `— (catch-all)` | `/:pathMatch(.*)*` | `errors/NotFoundView.vue` | AppLayout | — | — | BaseButton |

---

## Shared Components Registry (23 components detected)

| Component | Path | Purpose | Complexity | Heavily Used |
|-----------|------|---------|------------|--------------|
| `PageHeader` | `components/PageHeader.vue` | Title, subtitle, action slot | Medium | Yes (22 views) |
| `BaseButton` | `components/ui/BaseButton.vue` | Primary/secondary/destructive button | Medium | Yes (35 views) |
| `BaseInput` | `components/ui/BaseInput.vue` | Text, email, password, date | Medium | Yes (20 views) |
| `BaseSelect` | `components/ui/BaseSelect.vue` | Dropdown select | Low | Yes (15 views) |
| `BaseTextarea` | `components/ui/BaseTextarea.vue` | Multi-line input | Low | Medium (8 views) |
| `BaseAlert` | `components/ui/BaseAlert.vue` | Info/warning/error/success inline alerts | Low | Medium (8 views) |
| `DataTable` | `components/data-display/DataTable.vue` | Sortable, multi-select table | High | Yes (28 views) |
| `SearchFilterBar` | `components/data-display/SearchFilterBar.vue` | Quick search + filter dropdown | Medium | Yes (18 views) |
| `Pagination` | `components/Pagination.vue` | Page navigation | Low | Medium (used by DataTable) |
| `EmptyState` | `components/ui/EmptyState.vue` | Icon, message, action CTA | Low | Yes (30 views) |
| `LoadingSkeleton` | `components/ui/LoadingSkeleton.vue` | Shimmer placeholder | Low | Yes (32 views) |
| `ModalDialog` | `components/ModalDialog.vue` | Modal overlay with focus trap | High | Yes (18 views) |
| `ToastContainer` | `components/ui/ToastContainer.vue` | Global toast notifications | Medium | Layout (1, global) |
| `StatusBadge` | `components/StatusBadge.vue` | Draft/active/archived colored pill | Low | Yes (20 views) |
| `ClassificationBadge` | `components/ClassificationBadge.vue` | Public/confidential/restricted | Low | Medium (8 views) |
| `PriorityBadge` | `components/PriorityBadge.vue` | Low/medium/high/urgent | Low | Medium (4 views) |
| `AppSidebar` | `components/navigation/AppSidebar.vue` | Main navigation tree | High | Layout (1, global) |
| `PublicNavbar` | `components/public/PublicNavbar.vue` | Public landing nav | Low | Public pages (3) |
| `PublicFooter` | `components/public/PublicFooter.vue` | Public footer | Low | Public pages (3) |
| `OfficialLetterA4Preview` | `components/letters/OfficialLetterA4Preview.vue` | A4 letter PDF preview | Medium | Letters (1 known) |

**Unused/Unreferenced**: None detected (all 23 have at least one import).

---

## Module-Group Breakdown

| Group | Routes | Key Journeys |
|-------|--------|-------------|
| **Auth & Account** | 7 | Login, password reset, email verify, account profile/security/prefs |
| **Public** | 3 | Landing, privacy, terms |
| **Workspace** | 4 | Dashboard, tasks, notifications |
| **Records** | 11 | Documents, correspondence (5 tabs), templates, search |
| **Institutional records** | 6 | Research, staff, archive, accreditation, projects |
| **Trust & reporting** | 3 | Signatures, reports, data import/export |
| **Administration** | 14 | Users, roles, org units, permissions, config center, reference data, audit, operations, settings, help |
| **Error states** | 3 | 403, 404, catch-all |

---

## Navigation Guards

- **Session bootstrap**: Fetches `/api/v1/auth/me` if `requiresAuth` or stored session exists.
- **Unit switch**: Awaits pending unit switch before permission checks.
- **Permission checks**: Routes declare `permission: string | string[]`, guard checks `authStore.hasPermission(code)` for any match.
- **Forbidden redirect**: Missing permission → `/forbidden`.
- **Guest-only**: Authenticated users on login/register → `/dashboard`.
- **Dynamic title**: `<page title> | <institution name>`.

---

## PASS 0 FINDINGS

1. **25 views use PageHeader, 13 do not** → Inconsistency in page structure pattern (see check:ui banned count for h1 misuse).
2. **CorrespondenceRegistryView.vue handles 5 routes** (correspondence, incoming, outgoing, memoranda, routing) via internal tab logic → high complexity, potential confusion if deep-linked without correct tab state.
3. **AccountProfileView.vue handles 3 routes** (profile, security, preferences) → similar tab-based complexity.
4. **16 redirect aliases** → good for backward compat, but increases cognitive load. No evidence of redirect loops.
5. **3 shared badge components** (Status, Classification, Priority) → consistency potential, but need to audit usage for hard-coded variants or duplication.
6. **DataTable used in 28 views** → single point of failure, high leverage for consistency fixes.
7. **SearchFilterBar used in 18 views** → high leverage for consistency.
8. **No dedicated 500/error boundary** → generic NotFound fallback only.
9. **No explicit loading route** or splash screen for slow networks.
10. **Modal and toast infrastructure present** → need to audit focus management, announcements, keyboard traps.

---

**Next pass**: Design system and consistency (tokens, type scale, color usage, button/badge variants, form controls, duplication, hard-coded values).

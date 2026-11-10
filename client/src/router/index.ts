import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes: RouteRecordRaw[] = [
  // --- Group A: Authentication & Account ---
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/auth/LoginView.vue'),
    meta: { title: 'Sign In', guestOnly: true },
  },
  {
    path: '/forgot-password',
    name: 'forgot-password',
    component: () => import('@/views/auth/ForgotPasswordView.vue'),
    meta: { title: 'Forgot Password', guestOnly: true },
  },
  {
    path: '/reset-password',
    name: 'reset-password',
    component: () => import('@/views/auth/ResetPasswordView.vue'),
    meta: { title: 'Reset Password', guestOnly: true },
  },
  {
    path: '/verify-email',
    name: 'verify-email',
    component: () => import('@/views/auth/VerifyEmailView.vue'),
    meta: { title: 'Verify Email', requiresAuth: true },
  },
  {
    path: '/account',
    name: 'account.profile',
    component: () => import('@/views/account/AccountProfileView.vue'),
    meta: { title: 'My Account Facts', requiresAuth: true, group: 'Workspace' },
  },
  {
    path: '/account/security',
    name: 'account.security',
    component: () => import('@/views/account/AccountProfileView.vue'),
    meta: { title: 'Account Security & Password', requiresAuth: true, group: 'Workspace' },
  },
  {
    path: '/account/preferences',
    name: 'account.preferences',
    component: () => import('@/views/account/AccountProfileView.vue'),
    meta: { title: 'Workspace Preferences', requiresAuth: true, group: 'Workspace' },
  },

  // --- Group 0: Public Institutional Portal ---
  {
    path: '/',
    name: 'landing',
    component: () => import('@/views/public/LandingPageView.vue'),
    meta: { title: 'Document Management Portal', isPublic: true },
  },
  {
    path: '/privacy-policy',
    name: 'public.privacy',
    component: () => import('@/views/public/PrivacyPolicyView.vue'),
    meta: { title: 'Privacy & Data Protection Policy', isPublic: true },
  },
  {
    path: '/terms',
    name: 'public.terms',
    component: () => import('@/views/public/TermsConditionsView.vue'),
    meta: { title: 'Terms of Use & Administrative Agreement', isPublic: true },
  },

  // --- Group B: Dashboard, Tasks & Notifications ---
  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('@/views/dashboard/DashboardView.vue'),
    meta: {
      title: 'Workspace Dashboard',
      requiresAuth: true,
      permission: ['documents.view', 'workflow.view'],
      group: 'Workspace',
    },
  },
  {
    path: '/tasks',
    name: 'tasks.list',
    component: () => import('@/views/workflows/WorkflowTasksView.vue'),
    meta: { title: 'My Tasks & Decisions', requiresAuth: true, permission: 'workflow.view', group: 'Workspace' },
  },
  {
    path: '/workflows/tasks',
    redirect: '/tasks',
  },
  {
    path: '/notifications',
    name: 'notifications',
    component: () => import('@/views/notifications/NotificationsView.vue'),
    meta: { title: 'Notifications', requiresAuth: true, group: 'Workspace' },
  },

  // --- Group C: Document Repository ---
  {
    path: '/documents',
    name: 'documents.list',
    component: () => import('@/views/documents/DocumentListView.vue'),
    meta: { title: 'Document Repository', requiresAuth: true, permission: 'documents.view', group: 'Records' },
  },
  {
    path: '/templates',
    name: 'templates.list',
    component: () => import('@/views/templates/TemplateBuilderView.vue'),
    meta: { title: 'Document Templates', requiresAuth: true, permission: 'documents.create', group: 'Records' },
  },
  {
    path: '/documents/create',
    name: 'documents.create',
    component: () => import('@/views/documents/DocumentCreateView.vue'),
    meta: { title: 'Register Document', requiresAuth: true, permission: 'documents.create', group: 'Records' },
  },
  {
    path: '/documents/new',
    redirect: '/documents/create',
  },
  {
    path: '/documents/:id',
    name: 'documents.detail',
    component: () => import('@/views/documents/DocumentDetailView.vue'),
    meta: { title: 'Document Details', requiresAuth: true, permission: 'documents.view', group: 'Records' },
  },
  {
    path: '/search',
    name: 'global.search',
    component: () => import('@/views/search/GlobalSearchView.vue'),
    meta: { title: 'Authorized Search', requiresAuth: true, permission: 'documents.view', group: 'Records' },
  },

  // --- Group D: Correspondence Management ---
  {
    path: '/correspondence',
    name: 'correspondence.registry',
    component: () => import('@/views/correspondence/CorrespondenceRegistryView.vue'),
    meta: { title: 'Correspondence Registry', requiresAuth: true, permission: 'correspondence.view', group: 'Records' },
  },
  {
    path: '/letters/incoming',
    name: 'letters.incoming',
    component: () => import('@/views/correspondence/CorrespondenceRegistryView.vue'),
    meta: { title: 'Incoming Letters', requiresAuth: true, permission: 'correspondence.view', group: 'Records' },
  },
  {
    path: '/letters/outgoing',
    name: 'letters.outgoing',
    component: () => import('@/views/correspondence/CorrespondenceRegistryView.vue'),
    meta: { title: 'Outgoing Letters', requiresAuth: true, permission: 'correspondence.view', group: 'Records' },
  },
  {
    path: '/memoranda',
    name: 'memoranda.list',
    component: () => import('@/views/correspondence/CorrespondenceRegistryView.vue'),
    meta: { title: 'Internal Memoranda', requiresAuth: true, permission: 'correspondence.view', group: 'Records' },
  },
  {
    path: '/routing',
    name: 'routing.list',
    component: () => import('@/views/correspondence/CorrespondenceRegistryView.vue'),
    meta: { title: 'Office Routing', requiresAuth: true, permission: 'correspondence.view', group: 'Records' },
  },

  // --- Group E: Finalized Research Repository ---
  {
    path: '/research',
    name: 'research.outputs',
    component: () => import('@/views/research/FinalizedResearchView.vue'),
    meta: { title: 'Finalized Research', requiresAuth: true, permission: 'research.view', group: 'Institutional records' },
  },

  // --- Group F: Staff, HR and Physical Originals ---
  {
    path: '/staff',
    name: 'staff.records',
    component: () => import('@/views/staff/StaffRecordsView.vue'),
    meta: { title: 'Staff Records & Physical Custody', requiresAuth: true, permission: 'staff.view', group: 'Institutional records' },
  },
  {
    path: '/hr/records',
    redirect: '/staff',
  },
  {
    path: '/physical-originals',
    redirect: '/staff',
  },

  // --- Group G: Digital Archive ---
  {
    path: '/archive',
    name: 'archive.list',
    component: () => import('@/views/archive/ArchiveListView.vue'),
    meta: { title: 'Digital Archive Repository', requiresAuth: true, permission: 'archive.view', group: 'Institutional records' },
  },

  // --- Group H: Accreditation & Quality Assurance ---
  {
    path: '/accreditation',
    name: 'accreditation.evidence',
    component: () => import('@/views/accreditation/AccreditationEvidenceView.vue'),
    meta: { title: 'Accreditation Evidence', requiresAuth: true, permission: 'accreditation.view', group: 'Institutional records' },
  },

  // --- Group I: Projects & Innovation ---
  {
    path: '/projects',
    name: 'projects.innovation',
    component: () => import('@/views/projects/ProjectsInnovationView.vue'),
    meta: { title: 'Projects & Innovation', requiresAuth: true, permission: 'projects.view', group: 'Institutional records' },
  },

  // --- Group J: Signatures & Stamps ---
  {
    path: '/signatures',
    name: 'signatures.stamps',
    component: () => import('@/views/signatures/SignaturesStampsView.vue'),
    meta: { title: 'Signatures & Stamps', requiresAuth: true, permission: 'signatures.request', group: 'Trust & reporting' },
  },

  // --- Group K: Reports, Imports & Exports ---
  {
    path: '/reports',
    name: 'reports',
    component: () => import('@/views/reports/ReportsView.vue'),
    meta: { title: 'Operational Reports & Analytics', requiresAuth: true, permission: 'reports.view', group: 'Trust & reporting' },
  },
  {
    path: '/exports',
    name: 'data.exports',
    component: () => import('@/views/admin/DataImportsExportsView.vue'),
    meta: { title: 'Data Imports & Exports', requiresAuth: true, permission: 'roles.view', group: 'Trust & reporting' },
  },
  {
    path: '/imports',
    redirect: '/exports',
  },

  // --- Group L: Administration & Operations ---
  {
    path: '/admin/users',
    name: 'admin.users',
    component: () => import('@/views/users/UserManagementView.vue'),
    meta: { title: 'User Accounts & Staff', requiresAuth: true, permission: 'users.view', group: 'Administration' },
  },
  {
    path: '/users',
    redirect: '/admin/users',
  },
  {
    path: '/admin/roles',
    name: 'admin.roles',
    component: () => import('@/views/rbac/RoleManagementView.vue'),
    meta: { title: 'Scoped Role Management', requiresAuth: true, permission: 'roles.view', group: 'Administration' },
  },
  {
    path: '/roles',
    redirect: '/admin/roles',
  },
  {
    path: '/admin/organization',
    name: 'admin.organization',
    component: () => import('@/views/organization/OrganizationUnitsView.vue'),
    meta: { title: 'Organization Structure', requiresAuth: true, permission: 'organization.view', group: 'Administration' },
  },
  {
    path: '/organization-units',
    redirect: '/admin/organization',
  },
  {
    path: '/admin/office-procedures',
    name: 'admin.procedures',
    component: () => import('@/views/procedures/OfficeProceduresView.vue'),
    meta: { title: 'Office Procedures', requiresAuth: true, permission: 'roles.view', group: 'Administration' },
  },
  {
    path: '/procedures',
    redirect: '/admin/office-procedures',
  },
  {
    path: '/admin/permissions',
    name: 'admin.permissions',
    component: () => import('@/views/rbac/PermissionManagementView.vue'),
    meta: { title: 'Permission Registry', requiresAuth: true, permission: 'permissions.view', group: 'Administration' },
  },
  {
    path: '/permissions',
    redirect: '/admin/permissions',
  },
  {
    path: '/admin/reference-data',
    name: 'admin.reference_data',
    component: () => import('@/views/admin/ReferenceDataView.vue'),
    meta: { title: 'Reference Catalogs', requiresAuth: true, permission: 'roles.view', group: 'Administration' },
  },
  {
    path: '/admin/audit',
    name: 'admin.audit',
    component: () => import('@/views/audit/AuditLogView.vue'),
    meta: { title: 'Security Audit Ledger', requiresAuth: true, permission: 'audit.view', group: 'Administration' },
  },
  {
    path: '/audit-events',
    redirect: '/admin/audit',
  },
  {
    path: '/admin/operations',
    name: 'admin.operations',
    component: () => import('@/views/operations/OperationsView.vue'),
    meta: { title: 'Operations & Health', requiresAuth: true, permission: 'system.configure', group: 'Administration' },
  },
  {
    path: '/operations',
    redirect: '/admin/operations',
  },
  {
    path: '/admin/settings',
    name: 'admin.settings',
    component: () => import('@/views/admin/SystemSettingsView.vue'),
    meta: { title: 'System Settings', requiresAuth: true, permission: 'system.configure', group: 'Administration' },
  },
  {
    path: '/help',
    name: 'help.guide',
    component: () => import('@/views/help/HelpGuideView.vue'),
    meta: { title: 'Help & User Guide', requiresAuth: true, group: 'Administration' },
  },

  // --- Group M: System States & Error Pages ---
  {
    path: '/forbidden',
    name: 'error.forbidden',
    component: () => import('@/views/errors/ForbiddenView.vue'),
    meta: { title: 'Access Forbidden' },
  },
  {
    path: '/not-found',
    name: 'error.not_found',
    component: () => import('@/views/errors/NotFoundView.vue'),
    meta: { title: 'Page Not Found' },
  },
  {
    path: '/:pathMatch(.*)*',
    component: () => import('@/views/errors/NotFoundView.vue'),
    meta: { title: 'Page Not Found' },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() {
    return { top: 0 }
  },
})

// Production-Grade Navigation Guard with Session Bootstrap & Scope Inheritance
router.beforeEach(async (to, _from, next) => {
  const authStore = useAuthStore()

  // Session bootstrap. The store owns the session state, and it is retried
  // while that state remains unknown, so a transient transport failure cannot
  // strand the application on a stale or empty session.
  if (authStore.sessionState === 'unknown') {
    try {
      await authStore.fetchUser()
    } catch {
      // Unauthenticated or unreachable server; proceed to route evaluation.
    }
  }

  // Dynamic window title
  const pageTitle = (to.meta.title as string) || 'Document Repository'
  document.title = `${pageTitle} | Wollo University DMS`

  const requiresAuth = to.matched.some((r) => r.meta.requiresAuth)
  const guestOnly = to.matched.some((r) => r.meta.guestOnly)

  if (requiresAuth && !authStore.isAuthenticated) {
    next({ name: 'login', query: { redirect: to.fullPath } })
    return
  }

  if (guestOnly && authStore.isAuthenticated) {
    next({ name: 'dashboard' })
    return
  }

  // Check required permissions across all matched ancestor routes. A route may
  // declare a single capability or a list of alternatives; the semantics match
  // the server-side "permission:one|two" contract, where holding any one of
  // the declared capabilities satisfies the check.
  for (const record of to.matched) {
    const required = record.meta.permission as string | string[] | undefined
    if (!required) continue

    const candidates = Array.isArray(required) ? required : [required]
    const granted = candidates.some((code) => authStore.hasPermission(code))

    if (!granted) {
      next({ name: 'error.forbidden' })
      return
    }
  }

  next()
})

export default router

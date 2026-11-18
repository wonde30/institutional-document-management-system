import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// Views that use PageHeader
import DashboardView from '@/views/dashboard/DashboardView.vue'
import RoleManagementView from '@/views/rbac/RoleManagementView.vue'
import DataImportsExportsView from '@/views/admin/DataImportsExportsView.vue'
import DocumentListView from '@/views/documents/DocumentListView.vue'
import DocumentCreateView from '@/views/documents/DocumentCreateView.vue'
import TemplateBuilderView from '@/views/templates/TemplateBuilderView.vue'
import WorkflowTasksView from '@/views/workflows/WorkflowTasksView.vue'
import NotificationsView from '@/views/notifications/NotificationsView.vue'
import AccountProfileView from '@/views/account/AccountProfileView.vue'
import FinalizedResearchView from '@/views/research/FinalizedResearchView.vue'
import StaffRecordsView from '@/views/staff/StaffRecordsView.vue'
import ArchiveListView from '@/views/archive/ArchiveListView.vue'
import AccreditationEvidenceView from '@/views/accreditation/AccreditationEvidenceView.vue'
import ProjectsInnovationView from '@/views/projects/ProjectsInnovationView.vue'
import SignaturesStampsView from '@/views/signatures/SignaturesStampsView.vue'
import ReportsView from '@/views/reports/ReportsView.vue'
import OrganizationUnitsView from '@/views/organization/OrganizationUnitsView.vue'

// Mock API clients
vi.mock('@/api/client', () => ({
  workflowsApi: {
    myTasks: vi.fn().mockResolvedValue({ data: { data: [], meta: {} } }),
  },
  workflowApi: {
    tasks: vi.fn().mockResolvedValue({ data: { data: [], meta: {} } }),
  },
  dashboardApi: {
    getMetrics: vi.fn().mockResolvedValue({ data: { data: {} } }),
  },
  rolesApi: {
    list: vi.fn().mockResolvedValue({ data: { data: [] } }),
  },
  permissionsApi: {
    list: vi.fn().mockResolvedValue({ data: { data: [] } }),
  },
  exportsApi: {
    list: vi.fn().mockResolvedValue({ data: { data: [] } }),
  },
  importsApi: {
    list: vi.fn().mockResolvedValue({ data: { data: [] } }),
  },
  documentsApi: {
    list: vi.fn().mockResolvedValue({ data: { data: [], meta: {} } }),
    categories: vi.fn().mockResolvedValue({ data: { data: [] } }),
  },
  correspondenceApi: {
    types: vi.fn().mockResolvedValue({ data: { data: [] } }),
  },
  templatesApi: {
    list: vi.fn().mockResolvedValue({ data: { data: [] } }),
  },
  notificationsApi: {
    list: vi.fn().mockResolvedValue({ data: { data: [], meta: {} } }),
  },
  usersApi: {
    me: vi.fn().mockResolvedValue({ data: { data: { id: 1, name: 'Test User', roles: [] } } }),
    list: vi.fn().mockResolvedValue({ data: { data: [] } }),
  },
  institutionalApi: {
    finalizedResearch: vi.fn().mockResolvedValue({ data: { data: { publications: [] } } }),
    staffRecords: vi.fn().mockResolvedValue({ data: { data: [] } }),
    accreditation: vi.fn().mockResolvedValue({ data: { data: { mappings: [] } } }),
    projects: vi.fn().mockResolvedValue({ data: { data: { proposals: [], projects: [] } } }),
    signatures: vi.fn().mockResolvedValue({ data: { data: { signature_requests: [], stamps: [] } } }),
  },
  archiveApi: {
    list: vi.fn().mockResolvedValue({ data: { data: [], meta: {} } }),
  },
  reportsApi: {
    list: vi.fn().mockResolvedValue({ data: { data: [] } }),
  },
  organizationUnitsApi: {
    list: vi.fn().mockResolvedValue({ data: { data: [] } }),
  },
  metadataApi: {
    documentCategories: vi.fn().mockResolvedValue({ data: { data: [] } }),
    correspondenceTypes: vi.fn().mockResolvedValue({ data: { data: [] } }),
    priorities: vi.fn().mockResolvedValue({ data: { data: [] } }),
    getCategories: vi.fn().mockResolvedValue({ data: { data: [] } }),
  },
}))

// Mock stores
vi.mock('@/stores/auth', () => ({
  useAuthStore: vi.fn(() => ({
    user: { id: 1, name: 'Test User', email: 'test@example.com' },
    isAuthenticated: true,
    hasPermission: vi.fn(() => true),
    activeUnitId: 1,
    primaryUnit: { unit_id: 1, name: 'Test Unit' },
    userRoles: [],
  })),
}))

vi.mock('@/stores/metadata', () => ({
  useMetadataStore: vi.fn(() => ({
    documentCategories: [],
    correspondenceTypes: [],
    priorities: [],
    categories: [], // For DocumentCreateView
    confidentialityLevels: [], // For DocumentCreateView
    loadDocumentCategories: vi.fn(),
    loadCorrespondenceTypes: vi.fn(),
    loadPriorities: vi.fn(),
    loadAll: vi.fn(), // For DocumentListView and DocumentCreateView
  })),
}))

vi.mock('@/stores/notifications', () => ({
  useNotificationStore: vi.fn(() => ({
    unreadCount: 0,
    notifications: [], // For NotificationsView
    fetchNotifications: vi.fn(), // For NotificationsView
  })),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  })),
}))

describe('PageHeader Render Test', () => {
  let router: any
  let consoleWarnSpy: any

  beforeEach(() => {
    router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', component: { template: '<div>Home</div>' } },
        { path: '/dashboard', component: DashboardView },
        { path: '/roles', component: RoleManagementView },
      ],
    })
    consoleWarnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {})
  })

  const testCases = [
    {
      name: 'DashboardView',
      component: DashboardView,
      expectedTitle: 'Administrative workload, pending decisions, and correspondence',
    },
    {
      name: 'RoleManagementView',
      component: RoleManagementView,
      expectedTitle: 'Configure institutional roles, assignable capability bundles, and multi-unit authorization scopes without code changes',
    },
    {
      name: 'DataImportsExportsView',
      component: DataImportsExportsView,
      expectedTitle: 'Run a validated batch import against an authorised organizational unit, or generate a structured export of the records you are permitted to reach.',
    },
    {
      name: 'DocumentListView',
      component: DocumentListView,
      expectedTitle: 'Search, view, and manage institutional records with version tracking and scoped security classifications',
    },
    {
      name: 'DocumentCreateView',
      component: DocumentCreateView,
      expectedTitle: 'Create authentic Wollo University letters matching institutional standards or register external document records.',
    },
    {
      name: 'TemplateBuilderView',
      component: TemplateBuilderView,
      expectedTitle: 'Standardized letterheads, memoranda, directives, and verified document templates.',
    },
    {
      name: 'WorkflowTasksView',
      component: WorkflowTasksView,
      expectedTitle: 'Review, approve, or return institutional documents and correspondence directives undergoing routing policy decisions.',
    },
    {
      name: 'NotificationsView',
      component: NotificationsView,
      expectedTitle: 'Authorized event notifications, document dispatches, and pending workflow assignments.',
    },
    {
      name: 'AccountProfileView',
      component: AccountProfileView,
      expectedTitle: 'Manage your institutional identity, authentication credentials, and display preferences.',
    },
    {
      name: 'FinalizedResearchView',
      component: FinalizedResearchView,
      expectedTitle: 'Departmental publication of research completed and selected through external university processes.',
    },
    {
      name: 'StaffRecordsView',
      component: StaffRecordsView,
      expectedTitle: 'Dual tracking of electronic staff dossiers alongside physical paper original locations for institutional compliance.',
    },
    {
      name: 'ArchiveListView',
      component: ArchiveListView,
      expectedTitle: 'Statutory archival repository, retention schedules, legal holds, and historical preservation for auditable institutional memory.',
    },
    {
      name: 'AccreditationEvidenceView',
      component: AccreditationEvidenceView,
      expectedTitle: 'Map frozen document versions to official national HERQA quality standards with traceable audit reviews.',
    },
    {
      name: 'ProjectsInnovationView',
      component: ProjectsInnovationView,
      expectedTitle: 'Registered project proposals, innovation grants, and active institutional initiatives.',
    },
    {
      name: 'SignaturesStampsView',
      component: SignaturesStampsView,
      expectedTitle: 'Cryptographic signing requests, authorized signers, and departmental office seal registries.',
    },
    {
      name: 'ReportsView',
      component: ReportsView,
      expectedTitle: 'Institutional analytics, operational correspondence audit, document lifecycle diagnostics, and management dashboards.',
    },
    {
      name: 'OrganizationUnitsView',
      component: OrganizationUnitsView,
      expectedTitle: 'Hierarchical structure of colleges, directorates, departments, and academic units across Wollo University campuses.',
    },
  ]

  testCases.forEach(({ name, component, expectedTitle }) => {
    it(`${name} renders PageHeader with correct subtitle text`, async () => {
      const wrapper = mount(component, {
        global: {
          plugins: [router],
          stubs: {
            RouterLink: true,
            Teleport: true,
          },
        },
      })

      // Wait for async setup
      await wrapper.vm.$nextTick()

      // Find the subtitle paragraph (PageHeader subtitle prop renders as <p class="text-sm text-ink-muted">)
      const subtitle = wrapper.find('p.text-sm.text-ink-muted')
      expect(subtitle.exists()).toBe(true)
      expect(subtitle.text()).toBe(expectedTitle)

      // Ensure no console.warn or console.error about "Failed to resolve component" or "Vue warn"
      const resolveWarnings = consoleWarnSpy.mock.calls.filter((call: any[]) =>
        call.some((arg: any) => typeof arg === 'string' && (arg.includes('Failed to resolve component') || arg.includes('Vue warn')))
      )
      expect(resolveWarnings).toHaveLength(0)

      wrapper.unmount()
    })
  })
})

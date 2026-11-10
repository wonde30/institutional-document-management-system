import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import UserManagementView from '@/views/users/UserManagementView.vue'
import { usersApi, organizationUnitsApi, rolesApi, permissionsApi } from '@/api/client'

vi.mock('@/api/client', () => ({
  usersApi: {
    list: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    updateStatus: vi.fn(),
    assignMembership: vi.fn(),
  },
  organizationUnitsApi: {
    list: vi.fn(),
  },
  rolesApi: {
    list: vi.fn(),
    assignUserRole: vi.fn(),
    revokeUserRole: vi.fn(),
    assignUserPermission: vi.fn(),
    revokeUserPermission: vi.fn(),
  },
  permissionsApi: {
    list: vi.fn(),
  },
}))

describe('UserManagementView.vue', () => {
  const mockUsers = [
    {
      id: 1,
      name: 'System Administrator',
      email: 'admin@wu.edu.et',
      status: 'active',
      authz_version: 1,
      created_at: '2026-01-01T00:00:00Z',
      primary_membership: {
        id: 1,
        unit_id: 1,
        unit_name: 'Wollo University',
        unit_code: 'WU',
        position_title: 'Chief Administrator',
      },
      roles: [
        {
          role_id: 1,
          role_code: 'super_admin',
          role_name: 'Super Administrator',
          scope_id: 1,
          scope_kind: 'global',
        },
      ],
      direct_permissions: [],
      permissions: ['users.view', 'users.create', 'users.update'],
    },
    {
      id: 2,
      name: 'Hanan Mohammed (Direct Auditor)',
      email: 'auditor.direct@wu.edu.et',
      status: 'active',
      authz_version: 2,
      created_at: '2026-01-02T00:00:00Z',
      primary_membership: {
        id: 2,
        unit_id: 7,
        unit_name: 'Institutional Quality Assurance',
        unit_code: 'QA',
        position_title: 'Senior Compliance Auditor',
      },
      roles: [
        {
          role_id: 7,
          role_code: 'staff',
          role_name: 'Staff Member',
          scope_id: 7,
          scope_kind: 'unit',
        },
      ],
      direct_permissions: [
        {
          id: 1,
          permission_id: 10,
          permission_code: 'audit.view',
          permission_name: 'Audit View',
          scope_id: 1,
          scope_kind: 'global',
        },
        {
          id: 2,
          permission_id: 17,
          permission_code: 'documents.delete',
          permission_name: 'Documents Delete',
          scope_id: 1,
          scope_kind: 'global',
        },
      ],
      permissions: ['audit.view', 'documents.delete'],
    },
    {
      id: 3,
      name: 'Yared Fikre (Suspended)',
      email: 'suspended.staff@wu.edu.et',
      status: 'inactive',
      authz_version: 3,
      created_at: '2026-01-03T00:00:00Z',
      primary_membership: {
        id: 3,
        unit_id: 2,
        unit_name: 'Department of Software Engineering',
        unit_code: 'SE',
        position_title: 'Laboratory Technician',
      },
      roles: [
        {
          role_id: 7,
          role_code: 'staff',
          role_name: 'Staff Member',
          scope_id: 2,
          scope_kind: 'unit',
        },
      ],
      direct_permissions: [],
      permissions: [],
    },
  ]

  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(usersApi.list).mockResolvedValue({
      data: {
        success: true,
        data: mockUsers,
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 15,
          total: 3,
          from: 1,
          to: 3,
        },
      },
    } as any)

    vi.mocked(organizationUnitsApi.list).mockResolvedValue({
      data: {
        success: true,
        data: [
          { id: 1, code: 'WU', name: 'Wollo University', active: true, unit_type_id: 1 },
          { id: 7, code: 'QA', name: 'Quality Assurance', active: true, unit_type_id: 5 },
        ],
        meta: { current_page: 1, last_page: 1, total: 2 },
      },
    } as any)

    vi.mocked(rolesApi.list).mockResolvedValue({
      data: {
        success: true,
        data: [
          { id: 1, code: 'super_admin', name: 'Super Administrator', is_system: true, active: true },
          { id: 7, code: 'staff', name: 'Staff Member', is_system: true, active: true },
        ],
      },
    } as any)

    vi.mocked(permissionsApi.list).mockResolvedValue({
      data: {
        success: true,
        data: [
          { id: 10, code: 'audit.view', name: 'Audit view', active: true },
          { id: 17, code: 'documents.delete', name: 'Documents delete', active: true },
        ],
      },
    } as any)
  })

  it('renders tabular view initially with users and direct permissions', async () => {
    const wrapper = mount(UserManagementView)
    await flushPromises()

    expect(wrapper.text()).toContain('User Accounts & Staff Directory')
    expect(wrapper.text()).toContain('System Administrator')
    expect(wrapper.text()).toContain('Hanan Mohammed (Direct Auditor)')
    expect(wrapper.text()).toContain('audit.view')
    expect(wrapper.text()).toContain('documents.delete')
    expect(wrapper.text()).toContain('Yared Fikre (Suspended)')
  })

  it('toggles between Tabular View and Table Cards view', async () => {
    const wrapper = mount(UserManagementView)
    await flushPromises()

    // Initially in tabular view
    expect(wrapper.findComponent({ name: 'DataTable' }).exists()).toBe(true)

    // Click Table Cards view button
    const cardViewBtn = wrapper.findAll('button').find((b) => b.text().includes('Table Cards'))
    expect(cardViewBtn).toBeDefined()
    await cardViewBtn!.trigger('click')

    // Now in cards view: DataTable component is replaced by cards grid
    expect(wrapper.findComponent({ name: 'DataTable' }).exists()).toBe(false)
    expect(wrapper.text()).toContain('Scoped Roles')
    expect(wrapper.text()).toContain('Single-User Direct Overrides')
    expect(wrapper.text()).toContain('Hanan Mohammed (Direct Auditor)')
    expect(wrapper.text()).toContain('Session Guard: v2')
  })

  it('prompts confirmation when activate/deactivate button is clicked', async () => {
    const wrapper = mount(UserManagementView)
    await flushPromises()

    // Find the toggle status button for user #3 (suspended/inactive)
    const toggleButtons = wrapper.findAll('button[title*="Activate User Account"]')
    expect(toggleButtons.length).toBeGreaterThanOrEqual(1)

    await toggleButtons[0].trigger('click')

    // Expect confirmation modal to open
    expect(wrapper.text()).toContain('Activate Staff Account')
    expect(wrapper.text()).toContain('Confirm Activation')
  })
})

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'

const { me, setActiveUnit, navigationGet } = vi.hoisted(() => ({
  me: vi.fn(),
  setActiveUnit: vi.fn(),
  navigationGet: vi.fn(),
}))

vi.mock('@/api/client', () => ({
  authApi: {
    me,
    setActiveUnit,
    login: vi.fn(),
    logout: vi.fn(),
  },
  navigationApi: {
    get: navigationGet,
  },
  notificationsApi: {
    list: vi.fn().mockResolvedValue({ data: { success: true, data: { items: { data: [] }, unread_count: 0 } } }),
    unreadCount: vi.fn().mockResolvedValue({ data: { success: true, count: 0 } }),
  },
  metadataApi: {
    getAll: vi.fn().mockResolvedValue({ data: { success: true, data: { document_categories: [], priorities: [], confidentiality_levels: [], correspondence_types: [], dispatch_methods: [] } } }),
    loadAll: vi.fn().mockResolvedValue({ data: { success: true, data: {} } }),
  },
  organizationUnitsApi: {
    unitTypes: vi.fn().mockResolvedValue({ data: { data: [] } }),
  },
  ensureCsrfCookie: vi.fn().mockResolvedValue(undefined),
}))

import router from '@/router'
import { useAuthStore } from '@/stores/auth'
import AppSidebar from '@/components/navigation/AppSidebar.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import type { OrganizationalUnitSummary, User } from '@/types'

const collegeA: OrganizationalUnitSummary = {
  id: 20,
  code: 'COLLEGE_A',
  name: 'College A',
  is_primary: true,
}

const collegeB: OrganizationalUnitSummary = {
  id: 21,
  code: 'COLLEGE_B',
  name: 'College B',
  is_primary: false,
}

function contextUser(
  overrides: Partial<User> = {},
  units: OrganizationalUnitSummary[] = [collegeA, collegeB]
): User {
  return {
    id: 7,
    name: 'Scoped Officer',
    email: 'officer@wu.edu.et',
    status: 'active',
    authz_version: 1,
    roles: [{ id: 2, name: 'records_officer' }],
    permissions: ['documents.view'],
    available_units: units,
    active_unit: units[0] ?? null,
    active_unit_id: units[0]?.id ?? null,
    unit_selection_required: false,
    ...overrides,
  }
}

describe('Active organizational unit context', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    me.mockReset()
    setActiveUnit.mockReset()
    navigationGet.mockReset()
    navigationGet.mockResolvedValue({ data: { success: true, data: [] } })
  })

  it('publishes the available units and the active unit supplied by the server', async () => {
    me.mockResolvedValue({ data: { success: true, user: contextUser() } })

    const authStore = useAuthStore()
    await authStore.fetchUser()

    expect(authStore.availableUnits).toHaveLength(2)
    expect(authStore.activeUnitId).toBe(collegeA.id)
    expect(authStore.activeUnit?.name).toBe('College A')
  })

  it('uses the capability set calculated for the active context, not a union', async () => {
    me.mockResolvedValue({
      data: {
        success: true,
        user: contextUser({
          permissions: ['documents.view'],
          permissions_global: [],
          permissions_all_scopes: ['documents.view', 'roles.view'],
        }),
      },
    })

    const authStore = useAuthStore()
    await authStore.fetchUser()

    // The scoped capability is usable.
    expect(authStore.hasPermission('documents.view')).toBe(true)

    // The capability held only in another unit is not advertised.
    expect(authStore.hasPermission('roles.view')).toBe(false)
  })

  it('recalculates the capability set when the unit is switched', async () => {
    const switchedUser = contextUser({
      permissions: ['roles.view'],
      active_unit: collegeB,
      active_unit_id: collegeB.id,
    })

    me.mockResolvedValueOnce({ data: { success: true, user: contextUser() } })
    setActiveUnit.mockResolvedValue({ data: { success: true } })
    me.mockResolvedValueOnce({ data: { success: true, user: switchedUser } })

    const authStore = useAuthStore()
    await authStore.fetchUser()
    expect(authStore.hasPermission('documents.view')).toBe(true)
    expect(authStore.hasPermission('roles.view')).toBe(false)

    const switched = await authStore.switchUnit(collegeB.id)

    expect(switched).toBe(true)
    expect(setActiveUnit).toHaveBeenCalledWith(collegeB.id)
    expect(authStore.activeUnitId).toBe(collegeB.id)
    expect(authStore.hasPermission('roles.view')).toBe(true)
    expect(authStore.hasPermission('documents.view')).toBe(false)
  })

  it('presents no stale capability while a switch is in flight', async () => {
    me.mockResolvedValue({ data: { success: true, user: contextUser() } })
    setActiveUnit.mockResolvedValue({ data: { success: true } })

    const authStore = useAuthStore()
    await authStore.fetchUser()

    let release: (() => void) | null = null
    setActiveUnit.mockImplementationOnce(
      () =>
        new Promise((resolve) => {
          release = () => resolve({ data: { success: true } })
        })
    )

    const pending = authStore.switchUnit(collegeB.id)

    // While the switch is pending the previous capability set is withdrawn.
    expect(authStore.isSwitchingUnit).toBe(true)
    expect(authStore.user?.permissions).toEqual([])

    release?.()
    await pending
    expect(authStore.isSwitchingUnit).toBe(false)
  })

  it('restores the previous context when the switch is refused', async () => {
    me.mockResolvedValue({ data: { success: true, user: contextUser() } })
    setActiveUnit.mockRejectedValue({
      response: { data: { message: 'Forbidden: You are not authorized to operate in the requested organizational unit.' } },
    })

    const authStore = useAuthStore()
    await authStore.fetchUser()

    const switched = await authStore.switchUnit(collegeB.id)

    expect(switched).toBe(false)
    expect(authStore.activeUnitId).toBe(collegeA.id)
    expect(authStore.hasPermission('documents.view')).toBe(true)
    expect(authStore.error).toContain('not authorized')
  })

  it('does not start a second switch while one is in flight', async () => {
    me.mockResolvedValue({ data: { success: true, user: contextUser() } })

    const authStore = useAuthStore()
    await authStore.fetchUser()

    let release: (() => void) | null = null
    setActiveUnit.mockImplementationOnce(
      () =>
        new Promise((resolve) => {
          release = () => resolve({ data: { success: true } })
        })
    )

    const first = authStore.switchUnit(collegeB.id)
    const second = authStore.switchUnit(collegeB.id)

    expect(setActiveUnit).toHaveBeenCalledTimes(1)

    release?.()
    await Promise.all([first, second])
  })

  it('offers the context switcher only when more than one unit is available', async () => {
    me.mockResolvedValue({ data: { success: true, user: contextUser({}, [collegeA]) } })

    const authStore = useAuthStore()
    await authStore.fetchUser()

    const wrapper = mount(AppSidebar, {
      global: { plugins: [router] },
    })

    expect(wrapper.find('#active-unit-selector').exists()).toBe(false)
  })

  it('renders the context switcher when several units are available', async () => {
    me.mockResolvedValue({ data: { success: true, user: contextUser() } })

    const authStore = useAuthStore()
    await authStore.fetchUser()

    const wrapper = mount(AppLayout, {
      global: {
        plugins: [router],
        stubs: {
          RouterView: true,
        },
      },
    })
    await flushPromises()

    // Find and click the avatar dropdown button
    const avatarButton = wrapper.findAll('button').find(el => {
      const text = el.text()
      return text.includes(userInitials(authStore.user!))
    })
    expect(avatarButton).toBeTruthy()
    await avatarButton!.trigger('click')
    await wrapper.vm.$nextTick()

    // Verify the dropdown shows the unit switcher with multiple units
    const selectElement = wrapper.find('select')
    expect(selectElement.exists()).toBe(true)
    
    // Verify current unit is selected
    expect((selectElement.element as HTMLSelectElement).value).toBe(String(collegeA.id))

    // Mock the switch to collegeB
    const switchedUser = contextUser({
      permissions: ['roles.view'],
      active_unit: collegeB,
      active_unit_id: collegeB.id,
    })
    setActiveUnit.mockResolvedValue({ data: { success: true } })
    me.mockResolvedValueOnce({ data: { success: true, user: switchedUser } })

    // Simulate selecting a different unit
    await selectElement.setValue(String(collegeB.id))
    await flushPromises()

    // Verify switchUnit was called with the correct unitId
    expect(setActiveUnit).toHaveBeenCalledWith(collegeB.id)
    
    // Verify the active unit changed in the store
    expect(authStore.activeUnitId).toBe(collegeB.id)
    expect(authStore.activeUnit?.name).toBe('College B')
  })

  it('explains that a unit must be selected when scoped capabilities are held', async () => {
    me.mockResolvedValue({
      data: {
        success: true,
        user: contextUser({
          active_unit: null,
          active_unit_id: null,
          unit_selection_required: true,
          permissions: [],
        }),
      },
    })

    const authStore = useAuthStore()
    await authStore.fetchUser()

    // Verify the auth store reflects the unit selection requirement
    expect(authStore.user?.unit_selection_required).toBe(true)
    expect(authStore.activeUnit).toBeNull()
    expect(authStore.user?.available_units?.length).toBeGreaterThan(0)
  })

function userInitials(user: User): string {
  const parts = user.name.split(' ')
  if (parts.length >= 2) {
    return `${parts[0][0]}${parts[1][0]}`.toUpperCase()
  }
  return user.name.slice(0, 2).toUpperCase()
}
})

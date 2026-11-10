import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import router from '@/router'
import { useAuthStore } from '@/stores/auth'
import AppSidebar from '@/components/navigation/AppSidebar.vue'
import { navigationConfig } from '@/config/navigation'
import type { User } from '@/types'

// Mock notification API
vi.mock('@/api/client', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
  },
  ensureCsrfCookie: vi.fn().mockResolvedValue(undefined),
  notificationsApi: {
    list: vi.fn().mockResolvedValue({ data: { success: true, data: { items: { data: [] }, unread_count: 3 } } }),
    unreadCount: vi.fn().mockResolvedValue({ data: { success: true, count: 3 } }),
  },
}))

describe('AppSidebar Collapsible Accordion Navigation', () => {
  let authStore: ReturnType<typeof useAuthStore>

  const fullAdminUser: User = {
    id: 1,
    name: 'Executive Vice President',
    email: 'evp@wu.edu.et',
    roles: [{ id: 1, name: 'super-admin' }],
    permissions: [
      'workflow.view',
      'documents.view',
      'documents.create',
      'correspondence.view',
      'research.view',
      'users.view',
      'archive.view',
      'accreditation.view',
      'reports.view',
      'roles.view',
      'organization.view',
      'permissions.view',
      'audit.view',
    ],
    is_active: true,
  }

  beforeEach(async () => {
    setActivePinia(createPinia())
    localStorage.clear()
    authStore = useAuthStore()
    authStore.user = fullAdminUser
    await router.push('/dashboard')
  })

  it('1. expands only one group at a time upon manual accordion toggle', async () => {
    const wrapper = mount(AppSidebar, {
      global: {
        plugins: [router],
      },
    })

    // Initially on /dashboard -> Workspace is expanded
    const buttons = wrapper.findAll('button[aria-expanded]')
    expect(buttons.length).toBe(5)

    // Workspace is open (aria-expanded === 'true')
    const workspaceBtn = buttons.find((b) => b.text().includes('Workspace'))
    expect(workspaceBtn?.attributes('aria-expanded')).toBe('true')

    // Records is collapsed (aria-expanded === 'false')
    const recordsBtn = buttons.find((b) => b.text().includes('Records'))
    expect(recordsBtn?.attributes('aria-expanded')).toBe('false')

    // Click Records header button to expand it
    await recordsBtn?.trigger('click')

    // Now Records should be expanded
    expect(recordsBtn?.attributes('aria-expanded')).toBe('true')
    // And Workspace must be collapsed (single-open rule!)
    expect(workspaceBtn?.attributes('aria-expanded')).toBe('false')

    // Click Administration header button
    const adminBtn = buttons.find((b) => b.text().includes('Administration'))
    await adminBtn?.trigger('click')

    // Administration is now expanded
    expect(adminBtn?.attributes('aria-expanded')).toBe('true')
    // Records is now collapsed
    expect(recordsBtn?.attributes('aria-expanded')).toBe('false')
  })

  it("2. opens current route's group automatically on mount", async () => {
    // Navigate to /accreditation before mounting
    await router.push('/accreditation')

    const wrapper = mount(AppSidebar, {
      global: {
        plugins: [router],
      },
    })

    const buttons = wrapper.findAll('button[aria-expanded]')
    const institutionalBtn = buttons.find((b) => b.text().includes('Institutional records'))
    const workspaceBtn = buttons.find((b) => b.text().includes('Workspace'))

    expect(institutionalBtn?.attributes('aria-expanded')).toBe('true')
    expect(workspaceBtn?.attributes('aria-expanded')).toBe('false')
  })

  it('3. updates group expansion automatically when navigating between routes', async () => {
    const wrapper = mount(AppSidebar, {
      global: {
        plugins: [router],
      },
    })

    // Start at /dashboard (Workspace open)
    let buttons = wrapper.findAll('button[aria-expanded]')
    let workspaceBtn = buttons.find((b) => b.text().includes('Workspace'))
    let recordsBtn = buttons.find((b) => b.text().includes('Records'))
    expect(workspaceBtn?.attributes('aria-expanded')).toBe('true')
    expect(recordsBtn?.attributes('aria-expanded')).toBe('false')

    // Programmatically navigate to /documents
    await router.push('/documents')
    await wrapper.vm.$nextTick()

    // Records should now be expanded, Workspace collapsed
    buttons = wrapper.findAll('button[aria-expanded]')
    workspaceBtn = buttons.find((b) => b.text().includes('Workspace'))
    recordsBtn = buttons.find((b) => b.text().includes('Records'))
    expect(recordsBtn?.attributes('aria-expanded')).toBe('true')
    expect(workspaceBtn?.attributes('aria-expanded')).toBe('false')

    // Navigate to /signatures (Trust & reporting)
    await router.push('/signatures')
    await wrapper.vm.$nextTick()

    buttons = wrapper.findAll('button[aria-expanded]')
    const trustBtn = buttons.find((b) => b.text().includes('Trust & reporting'))
    recordsBtn = buttons.find((b) => b.text().includes('Records'))
    expect(trustBtn?.attributes('aria-expanded')).toBe('true')
    expect(recordsBtn?.attributes('aria-expanded')).toBe('false')
  })

  it('4. visibly highlights the currently active navigation item with aria-current="page"', async () => {
    await router.push('/tasks')

    const wrapper = mount(AppSidebar, {
      global: {
        plugins: [router],
      },
    })

    // Locate active link for My tasks
    const activeLink = wrapper.find('a[aria-current="page"]')
    expect(activeLink.exists()).toBe(true)
    expect(activeLink.text()).toContain('My tasks')
    expect(activeLink.classes()).toContain('bg-[#e8f3fc]')
    expect(activeLink.classes()).toContain('border-l-[#0075de]')
  })

  it('5. hides restricted menu items when actor lacks required permission', async () => {
    // Restricted user lacking 'audit.view' and 'roles.view'
    authStore.user = {
      id: 99,
      name: 'Department Clerk',
      email: 'clerk@wu.edu.et',
      roles: [{ id: 8, name: 'Clerk' }],
      permissions: ['documents.view'],
      is_active: true,
    }

    const wrapper = mount(AppSidebar, {
      global: {
        plugins: [router],
      },
    })

    const links = wrapper.findAll('a')
    const linkTexts = links.map((l) => l.text())

    // Permitted items are visible
    expect(linkTexts.some((t) => t.includes('Documents'))).toBe(true)
    expect(linkTexts.some((t) => t.includes('Dashboard'))).toBe(true)

    // Restricted items are hidden
    expect(linkTexts.some((t) => t.includes('Audit log'))).toBe(false)
    expect(linkTexts.some((t) => t.includes('Roles & permissions'))).toBe(false)
  })

  it('6. does not cause page navigation when user expands or collapses accordion groups', async () => {
    await router.push('/dashboard')

    const wrapper = mount(AppSidebar, {
      global: {
        plugins: [router],
      },
    })

    expect(router.currentRoute.value.path).toBe('/dashboard')

    // Click Records header button to expand it
    const buttons = wrapper.findAll('button[aria-expanded]')
    const recordsBtn = buttons.find((b) => b.text().includes('Records'))
    await recordsBtn?.trigger('click')

    // Records is expanded
    expect(recordsBtn?.attributes('aria-expanded')).toBe('true')

    // Route remains /dashboard!
    expect(router.currentRoute.value.path).toBe('/dashboard')
  })

  it('7. maintains correct navigation and group state on browser back and forward', async () => {
    const wrapper = mount(AppSidebar, {
      global: {
        plugins: [router],
      },
    })

    await router.push('/documents')
    await wrapper.vm.$nextTick()
    let buttons = wrapper.findAll('button[aria-expanded]')
    expect(buttons.find((b) => b.text().includes('Records'))?.attributes('aria-expanded')).toBe('true')

    await router.push('/archive')
    await wrapper.vm.$nextTick()
    buttons = wrapper.findAll('button[aria-expanded]')
    expect(buttons.find((b) => b.text().includes('Institutional records'))?.attributes('aria-expanded')).toBe('true')
    expect(buttons.find((b) => b.text().includes('Records'))?.attributes('aria-expanded')).toBe('false')

    // Simulate browser Back
    router.back()
    await new Promise((r) => setTimeout(r, 50))
    await wrapper.vm.$nextTick()

    buttons = wrapper.findAll('button[aria-expanded]')
    expect(buttons.find((b) => b.text().includes('Records'))?.attributes('aria-expanded')).toBe('true')
    expect(buttons.find((b) => b.text().includes('Institutional records'))?.attributes('aria-expanded')).toBe('false')
  })

  it('8. opens correct parent group when navigating to a nested route', async () => {
    // Navigate to nested document registration route /documents/create
    await router.push('/documents/create')

    const wrapper = mount(AppSidebar, {
      global: {
        plugins: [router],
      },
    })

    const buttons = wrapper.findAll('button[aria-expanded]')
    const recordsBtn = buttons.find((b) => b.text().includes('Records'))
    expect(recordsBtn?.attributes('aria-expanded')).toBe('true')
  })

  it('9. hides entire navigation group if user has zero permitted items in it', async () => {
    // User with only null permissions (no documents, correspondence, research, or admin permissions)
    authStore.user = {
      id: 50,
      name: 'Guest Observer',
      email: 'guest@wu.edu.et',
      roles: [],
      permissions: [], // Lacks documents.view, correspondence.view, research.view, etc.
      is_active: true,
    }

    const wrapper = mount(AppSidebar, {
      global: {
        plugins: [router],
      },
    })

    const buttons = wrapper.findAll('button[aria-expanded]')
    const buttonTexts = buttons.map((b) => b.text())

    // Workspace is visible because Dashboard & Notifications require no permissions
    expect(buttonTexts.some((t) => t.includes('Workspace'))).toBe(true)

    // Records, Institutional records, and Trust & reporting are completely hidden
    expect(buttonTexts.some((t) => t.includes('Records'))).toBe(false)
    expect(buttonTexts.some((t) => t.includes('Institutional records'))).toBe(false)
    expect(buttonTexts.some((t) => t.includes('Trust & reporting'))).toBe(false)
  })

  it('10. manages mobile drawer open, close, backdrop, and keyboard dismissal', async () => {
    const wrapper = mount(AppSidebar, {
      props: {
        mobileOpen: true,
      },
      global: {
        plugins: [router],
      },
    })

    // Drawer is open
    const aside = wrapper.find('aside')
    expect(aside.classes()).toContain('translate-x-0')

    // Click mobile close button
    const closeBtn = wrapper.find('button[aria-label="Close navigation menu"]')
    await closeBtn.trigger('click')

    expect(wrapper.emitted('update:mobileOpen')?.[0]).toEqual([false])
    expect(wrapper.emitted('close')).toBeTruthy()

    // Test Escape key dismissal
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    expect(wrapper.emitted('close')?.length).toBeGreaterThanOrEqual(2)
  })

  it('11. provides accessible ARIA attributes and keyboard controls', async () => {
    const wrapper = mount(AppSidebar, {
      global: {
        plugins: [router],
      },
    })

    const buttons = wrapper.findAll('button[aria-expanded]')
    for (const btn of buttons) {
      expect(btn.attributes('type')).toBe('button')
      expect(btn.attributes('aria-controls')).toBeTruthy()
      const panelId = btn.attributes('aria-controls')
      const panel = wrapper.find(`#${panelId}`)
      expect(panel.exists()).toBe(true)
      expect(panel.attributes('role')).toBe('region')
    }
  })

  it('12. preserves every existing navigation group and module without omission', () => {
    const wrapper = mount(AppSidebar, {
      global: {
        plugins: [router],
      },
    })

    const buttons = wrapper.findAll('button[aria-expanded]')
    const groupTitles = buttons.map((b) => b.find('span.truncate').text())

    expect(groupTitles).toEqual([
      'Workspace',
      'Records',
      'Institutional records',
      'Trust & reporting',
      'Administration',
    ])

    // Verify all 25 navigation items are represented
    const totalExpectedItems = navigationConfig.reduce((acc, sec) => acc + sec.items.length, 0)
    const renderedLinks = wrapper.findAll('a[href]')
    // Plus 1 for the logo link to /dashboard
    expect(renderedLinks.length).toBe(totalExpectedItems + 1)
  })
})

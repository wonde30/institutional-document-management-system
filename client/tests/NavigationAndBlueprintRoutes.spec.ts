import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { navigationConfig } from '@/config/navigation'
import router from '@/router'
import { useAuthStore } from '@/stores/auth'

describe('Wollo University UI Blueprint Navigation & Router Coverage', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
  })

  it('exposes all 5 blueprint navigation groups', () => {
    const titles = navigationConfig.map((s) => s.title)
    expect(titles).toEqual([
      'Workspace',
      'Records',
      'Institutional records',
      'Trust & reporting',
      'Administration',
    ])
  })

  it('includes all specified blueprint navigation items in each group', () => {
    const workspaceItems = navigationConfig[0].items.map((i) => i.name)
    expect(workspaceItems).toContain('Dashboard')
    expect(workspaceItems).toContain('My tasks')
    expect(workspaceItems).toContain('Notifications')

    const recordsItems = navigationConfig[1].items.map((i) => i.name)
    expect(recordsItems).toContain('Documents')
    expect(recordsItems).toContain('Incoming letters')
    expect(recordsItems).toContain('Outgoing letters')
    expect(recordsItems).toContain('Internal memoranda')
    expect(recordsItems).toContain('Office routing')

    const institutionalItems = navigationConfig[2].items.map((i) => i.name)
    expect(institutionalItems).toContain('Finalized research')
    expect(institutionalItems).toContain('Staff records')
    expect(institutionalItems).toContain('Digital archive')
    expect(institutionalItems).toContain('Accreditation evidence')
    expect(institutionalItems).toContain('Projects & innovation')

    const trustItems = navigationConfig[3].items.map((i) => i.name)
    expect(trustItems).toContain('Signatures & stamps')
    expect(trustItems).toContain('Reports')
    expect(trustItems).toContain('Imports & exports')

    const adminItems = navigationConfig[4].items.map((i) => i.name)
    expect(adminItems).toContain('Users')
    expect(adminItems).toContain('Roles & permissions')
    expect(adminItems).toContain('Organization')
    expect(adminItems).toContain('Workflows & office procedures')
    expect(adminItems).toContain('Reference data')
    expect(adminItems).toContain('Audit log')
    expect(adminItems).toContain('Operations')
    expect(adminItems).toContain('Settings')
    expect(adminItems).toContain('Help & guide')
  })

  it('registers all atomic routes from Blueprint Screen Inventory (Groups A through M)', () => {
    const routePaths = router.getRoutes().map((r) => r.path)

    // Group A: Auth & Account
    expect(routePaths).toContain('/login')
    expect(routePaths).toContain('/forgot-password')
    expect(routePaths).toContain('/reset-password')
    expect(routePaths).toContain('/verify-email')
    expect(routePaths).toContain('/account')
    expect(routePaths).toContain('/account/security')
    expect(routePaths).toContain('/account/preferences')

    // Group B: Dashboard & Tasks
    expect(routePaths).toContain('/dashboard')
    expect(routePaths).toContain('/tasks')
    expect(routePaths).toContain('/notifications')

    // Group C: Documents & Search
    expect(routePaths).toContain('/documents')
    expect(routePaths).toContain('/documents/create')
    expect(routePaths).toContain('/documents/:id')
    expect(routePaths).toContain('/search')

    // Group D: Correspondence
    expect(routePaths).toContain('/correspondence')
    expect(routePaths).toContain('/letters/incoming')
    expect(routePaths).toContain('/letters/outgoing')
    expect(routePaths).toContain('/memoranda')
    expect(routePaths).toContain('/routing')

    // Group E: Research
    expect(routePaths).toContain('/research')

    // Group F: Staff & HR
    expect(routePaths).toContain('/staff')

    // Group G: Archive
    expect(routePaths).toContain('/archive')

    // Group H: Accreditation
    expect(routePaths).toContain('/accreditation')

    // Group I: Projects
    expect(routePaths).toContain('/projects')

    // Group J: Signatures & Stamps
    expect(routePaths).toContain('/signatures')

    // Group K: Reports & Exports
    expect(routePaths).toContain('/reports')
    expect(routePaths).toContain('/exports')

    // Group L: Administration
    expect(routePaths).toContain('/admin/users')
    expect(routePaths).toContain('/admin/roles')
    expect(routePaths).toContain('/admin/organization')
    expect(routePaths).toContain('/admin/office-procedures')
    expect(routePaths).toContain('/admin/reference-data')
    expect(routePaths).toContain('/admin/audit')
    expect(routePaths).toContain('/admin/operations')
    expect(routePaths).toContain('/admin/settings')
    expect(routePaths).toContain('/help')

    // Group M: Errors & Fallbacks
    expect(routePaths).toContain('/forbidden')
    expect(routePaths).toContain('/not-found')
  })

  it('protects secure routes and redirects unauthorized access to /forbidden', async () => {
    const auth = useAuthStore()

    // Authenticated normal staff without admin roles
    auth.user = {
      id: 5,
      name: 'Lecturer User',
      email: 'lecturer@wu.edu.et',
      roles: [{ id: 8, name: 'Lecturer' }],
      permissions: ['documents.view', 'research.view'],
      status: 'active',
      authz_version: 1,
    }
    auth.token = 'valid-token'

    // Try navigating to admin users (requires 'users.view')
    await router.push('/admin/users')
    expect(router.currentRoute.value.path).toBe('/forbidden')

    // Try navigating to authorized route
    await router.push('/documents')
    expect(router.currentRoute.value.path).toBe('/documents')
  })
})

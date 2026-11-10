import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import router from '@/router'
import { navigationConfig } from '@/config/navigation'
import type { User } from '@/types'

describe('Institutional Workflow & Navigation Guard Integration', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
  })

  it('redirects unauthenticated user directly accessing protected route to login', async () => {
    const auth = useAuthStore()
    auth.user = null
    auth.token = null

    await router.push('/tasks')
    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/tasks')
  })

  it('redirects authenticated user lacking permission to forbidden view on direct URL access', async () => {
    const auth = useAuthStore()
    const restrictedUser: User = {
      id: 20,
      name: 'Unprivileged Staff',
      email: 'staff.unprivileged@wu.edu.et',
      roles: [{ id: 7, name: 'Staff' }],
      permissions: ['documents.view'], // Lacks 'system.configure' or 'audit.view'
      is_active: true,
    }

    auth.user = restrictedUser
    auth.token = 'active-session-token'

    // Attempt direct URL access to /admin/audit (requires audit.view)
    await router.push('/admin/audit')
    expect(router.currentRoute.value.name).toBe('error.forbidden')
  })

  it('permits authorized user with required permission to access protected route directly', async () => {
    const auth = useAuthStore()
    const auditorUser: User = {
      id: 21,
      name: 'Institutional Auditor',
      email: 'auditor@wu.edu.et',
      roles: [{ id: 2, name: 'Auditor' }],
      permissions: ['audit.view', 'documents.view'],
      is_active: true,
    }

    auth.user = auditorUser
    auth.token = 'active-session-token'

    await router.push('/admin/audit')
    expect(router.currentRoute.value.name).toBe('admin.audit')
  })

  it('immediately blocks access when direct permission is revoked', async () => {
    const auth = useAuthStore()
    const officer: User = {
      id: 22,
      name: 'Temporary Records Officer',
      email: 'temp.officer@wu.edu.et',
      roles: [{ id: 5, name: 'Records Officer' }],
      permissions: ['correspondence.view', 'correspondence.register'],
      is_active: true,
    }

    auth.user = officer
    auth.token = 'active-session-token'

    // Permitted initially
    expect(auth.hasPermission('correspondence.register')).toBe(true)

    // Permission revoked dynamically by administrator
    officer.permissions = ['correspondence.view']
    auth.user = { ...officer }

    // Fails closed immediately
    expect(auth.hasPermission('correspondence.register')).toBe(false)
  })

  it('filters navigation items dynamically based on permission grants', () => {
    const auth = useAuthStore()
    auth.user = {
      id: 30,
      name: 'Scoped Registry Officer',
      email: 'registry.scoped@wu.edu.et',
      roles: [{ id: 5, name: 'Records Officer' }],
      permissions: [
        'correspondence.view',
        'correspondence.register',
        'workflow.view',
      ],
      is_active: true,
    }
    auth.token = 'token'

    // Inspect Records navigation section
    const recordsGroup = navigationConfig.find((g) => g.title === 'Records')
    expect(recordsGroup).toBeDefined()

    // Test permission evaluation for each nav item
    const authorizedItems = recordsGroup!.items.filter(
      (item) => !item.permission || auth.hasPermission(item.permission)
    )

    // Incoming letters requires correspondence.view
    expect(authorizedItems.some((i) => i.name === 'Incoming letters')).toBe(true)
    // Outgoing letters requires correspondence.view
    expect(authorizedItems.some((i) => i.name === 'Outgoing letters')).toBe(true)
  })
})

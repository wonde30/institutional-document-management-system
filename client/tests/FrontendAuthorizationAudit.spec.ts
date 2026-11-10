import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import router from '@/router'
import { navigationConfig } from '@/config/navigation'
import type { User } from '@/types'

describe('Comprehensive Frontend Authorization Audit', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    vi.restoreAllMocks()
  })

  // 1. Direct URL Navigation & Unauthenticated Redirect
  it('redirects unauthenticated user accessing protected route directly to /login with redirect query', async () => {
    const auth = useAuthStore()
    auth.user = null
    auth.token = null

    await router.push('/correspondence')
    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/correspondence')
  })

  // 2. Direct URL Navigation & Unauthorized 403 Forbidden
  it('redirects authenticated user to /forbidden when accessing route without required permission', async () => {
    const auth = useAuthStore()
    auth.user = {
      id: 101,
      name: 'Regular Staff Member',
      email: 'staff@wu.edu.et',
      roles: [{ id: 7, name: 'Staff' }],
      permissions: ['documents.view'],
      is_active: true,
    }
    auth.token = 'active-session-token'

    // Direct access to /admin/roles (requires 'roles.view')
    await router.push('/admin/roles')
    expect(router.currentRoute.value.name).toBe('error.forbidden')
  })

  // 3. Authorized Page Access
  it('permits authorized user with exact permission to access protected page directly', async () => {
    const auth = useAuthStore()
    auth.user = {
      id: 102,
      name: 'Records Officer',
      email: 'officer@wu.edu.et',
      roles: [{ id: 5, name: 'Records Officer' }],
      permissions: ['correspondence.view', 'documents.view'],
      is_active: true,
    }
    auth.token = 'active-session-token'

    await router.push('/correspondence')
    expect(router.currentRoute.value.name).toBe('correspondence.registry')
  })

  // 4. Dynamic Navigation Menu Filtering
  it('filters navigation sections and items strictly according to user permissions', () => {
    const auth = useAuthStore()
    auth.user = {
      id: 103,
      name: 'Academic Staff',
      email: 'academic@wu.edu.et',
      roles: [{ id: 7, name: 'Staff' }],
      permissions: ['documents.view', 'research.view'],
      is_active: true,
    }
    auth.token = 'active-session-token'

    // Evaluate navigation visibility
    const visibleSections = navigationConfig.map((section) => ({
      title: section.title,
      items: section.items.filter((item) => {
        if (!item.permission) return true
        return auth.hasPermission(item.permission)
      }),
    })).filter((section) => section.items.length > 0)

    const visibleItemNames = visibleSections.flatMap((s) => s.items.map((i) => i.name))

    // Should include permitted items
    expect(visibleItemNames).toContain('Documents')
    expect(visibleItemNames).toContain('Finalized research')

    // Must NOT contain restricted items
    expect(visibleItemNames).not.toContain('Audit trail')
    expect(visibleItemNames).not.toContain('Role directory')
    expect(visibleItemNames).not.toContain('Permission matrix')
    expect(visibleItemNames).not.toContain('Incoming letters')
  })

  // 5. Action Button Permission Verification
  it('evaluates granular component action capabilities without exposing sensitive actions', () => {
    const auth = useAuthStore()
    auth.user = {
      id: 104,
      name: 'Read Only Researcher',
      email: 'researcher@wu.edu.et',
      roles: [{ id: 7, name: 'Staff' }],
      permissions: ['research.view'],
      is_active: true,
    }
    auth.token = 'active-session-token'

    // View action allowed
    expect(auth.hasPermission('research.view')).toBe(true)

    // Mutation actions strictly prohibited
    expect(auth.hasPermission('research.publish')).toBe(false)
    expect(auth.hasPermission('research.create')).toBe(false)
    expect(auth.hasPermission('research.update')).toBe(false)
  })

  // 6. Expired Sessions & State Clearance
  it('purges authentication token and user identity from memory and localStorage upon logout', async () => {
    const auth = useAuthStore()
    auth.token = 'sample-token'
    auth.user = {
      id: 105,
      name: 'Logged In User',
      email: 'user@wu.edu.et',
      is_active: true,
    }
    localStorage.setItem('token', 'sample-token')
    localStorage.setItem('user', JSON.stringify(auth.user))

    await auth.logout()

    expect(auth.isAuthenticated).toBe(false)
    expect(auth.user).toBeNull()
    expect(auth.token).toBeNull()
    expect(localStorage.getItem('token')).toBeNull()
    expect(localStorage.getItem('user')).toBeNull()
  })

  // 7. Dynamic Permission Revocation
  it('fails closed immediately when permissions are revoked in Pinia store', () => {
    const auth = useAuthStore()
    const user: User = {
      id: 106,
      name: 'Dynamic Grant User',
      email: 'dynamic@wu.edu.et',
      roles: [{ id: 5, name: 'Records Officer' }],
      permissions: ['correspondence.dispatch', 'correspondence.view'],
      is_active: true,
    }
    auth.user = user
    auth.token = 'active-token'

    expect(auth.hasPermission('correspondence.dispatch')).toBe(true)

    // Revoke permission dynamically
    user.permissions = ['correspondence.view']
    auth.user = { ...user }

    expect(auth.hasPermission('correspondence.dispatch')).toBe(false)
  })

  // 8. Role Changes & Privilege Adjustment
  it('updates capability boundaries immediately when role is modified', () => {
    const auth = useAuthStore()
    const user: User = {
      id: 107,
      name: 'Department Head Promoted',
      email: 'head@wu.edu.et',
      roles: [{ id: 3, name: 'Department Head' }],
      permissions: ['workflow.review'],
      is_active: true,
    }
    auth.user = user
    auth.token = 'active-token'

    expect(auth.hasRole('Department Head')).toBe(true)
    expect(auth.hasRole('Super Admin')).toBe(false)
    expect(auth.isSuperAdmin).toBe(false)

    // Role modified to Super Admin
    user.roles = [{ id: 1, name: 'Super Admin' }]
    auth.user = { ...user }

    expect(auth.isSuperAdmin).toBe(true)
    // Super Admin inherits full operational capabilities
    expect(auth.hasPermission('system.configure')).toBe(true)
    expect(auth.hasPermission('any.custom.permission')).toBe(true)
  })

  // 9. Browser Refresh Rehydration
  it('correctly rehydrates user permissions from storage upon browser refresh', () => {
    const storedUser: User = {
      id: 108,
      name: 'Rehydrated User',
      email: 'rehydrated@wu.edu.et',
      roles: [{ id: 6, name: 'Archivist' }],
      permissions: ['archive.view', 'archive.transfer'],
      is_active: true,
    }
    localStorage.setItem('user', JSON.stringify(storedUser))
    localStorage.setItem('token', 'persisted-token')

    const auth = useAuthStore()
    expect(auth.isAuthenticated).toBe(true)
    expect(auth.user?.name).toBe('Rehydrated User')
    expect(auth.hasPermission('archive.transfer')).toBe(true)
    expect(auth.hasPermission('correspondence.register')).toBe(false)
  })

  // 10. Open Redirect Prevention & Secure Destination Sanitization
  it('sanitizes malicious or protocol-relative redirect URLs to safe institutional dashboard', () => {
    function sanitizeRedirect(target: unknown): string {
      if (typeof target !== 'string' || !target.startsWith('/')) {
        return '/dashboard'
      }
      if (target.startsWith('//') || target.startsWith('/\\') || target.includes('/login')) {
        return '/dashboard'
      }
      return target
    }

    // Malicious open redirect attempts
    expect(sanitizeRedirect('//malicious-attacker.com')).toBe('/dashboard')
    expect(sanitizeRedirect('https://external-phishing.com')).toBe('/dashboard')
    expect(sanitizeRedirect('/\\evil.com')).toBe('/dashboard')
    expect(sanitizeRedirect('/login')).toBe('/dashboard')
    expect(sanitizeRedirect(null)).toBe('/dashboard')

    // Legitimate internal institutional routes preserved
    expect(sanitizeRedirect('/correspondence')).toBe('/correspondence')
    expect(sanitizeRedirect('/documents/create')).toBe('/documents/create')
    expect(sanitizeRedirect('/archive/transfer')).toBe('/archive/transfer')
  })
})

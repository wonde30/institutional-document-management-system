import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import type { User } from '@/types'

describe('Pinia Auth Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
  })

  it('initializes with unauthenticated state', () => {
    const auth = useAuthStore()
    expect(auth.isAuthenticated).toBe(false)
    expect(auth.user).toBeNull()
    expect(auth.token).toBeNull()
  })

  it('determines roles and super admin bypass correctly', () => {
    const auth = useAuthStore()

    const normalUser: User = {
      id: 2,
      name: 'Archivist User',
      email: 'archivist@wu.edu.et',
      roles: [{ id: 6, name: 'Archivist' }],
      permissions: ['archive.view', 'archive.manage'],
      department_id: 1,
      clearance_level: 3,
      is_active: true,
      created_at: '2026-10-24T00:00:00Z',
    }

    auth.user = normalUser
    auth.token = 'sample-mock-token'

    expect(auth.isAuthenticated).toBe(true)
    expect(auth.hasRole('Archivist')).toBe(true)
    expect(auth.hasRole('Super Admin')).toBe(false)
    expect(auth.hasAnyRole(['Admin', 'Archivist'])).toBe(true)
    expect(auth.hasPermission('archive.manage')).toBe(true)
    expect(auth.hasPermission('system.settings')).toBe(false)

    // Super Admin user
    const superAdmin: User = {
      id: 1,
      name: 'Super Admin',
      email: 'admin@wu.edu.et',
      roles: [{ id: 1, name: 'Super Admin' }],
      permissions: [],
      department_id: 1,
      clearance_level: 4,
      is_active: true,
      created_at: '2026-10-24T00:00:00Z',
    }

    auth.user = superAdmin
    expect(auth.hasRole('RandomRole')).toBe(true) // Super admin bypass
    expect(auth.hasPermission('any.random.perm')).toBe(true) // Super admin bypass
  })

  it('clears state on logout', async () => {
    const auth = useAuthStore()
    auth.token = 'test-token'
    auth.user = {
      id: 1,
      name: 'Test',
      email: 'test@wu.edu.et',
      is_active: true,
      created_at: '2026-10-24',
    }

    await auth.logout()

    expect(auth.token).toBeNull()
    expect(auth.user).toBeNull()
    expect(auth.isAuthenticated).toBe(false)
    expect(localStorage.getItem('token')).toBeNull()
  })
})

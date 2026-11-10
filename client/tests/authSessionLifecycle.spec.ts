import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import router from '@/router'
import { useAuthStore } from '@/stores/auth'
import { emitForbidden, emitUnauthorized } from '@/api/sessionEvents'
import { apiClient } from '@/api/client'
import type { User } from '@/types'

vi.mock('@/api/client', async () => {
  const actual = await vi.importActual<typeof import('@/api/client')>('@/api/client')
  return {
    ...actual,
    authApi: {
      me: vi.fn().mockRejectedValue({ response: { status: 401 } }),
      login: vi.fn(),
      logout: vi.fn(),
    },
    navigationApi: {
      get: vi.fn().mockResolvedValue({ data: { success: true, data: [] } }),
    },
  }
})

const institutionalUser: User = {
  id: 42,
  name: 'Records Officer',
  email: 'officer@wu.edu.et',
  roles: [{ id: 2, name: 'records_officer' }],
  permissions: ['documents.view', 'documents.create', 'workflow.view'],
  is_active: true,
}

describe('Authentication session lifecycle', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
  })

  it('clears the in-memory session when the server reports it as terminated', () => {
    const authStore = useAuthStore()
    authStore.user = institutionalUser
    localStorage.setItem('user', JSON.stringify(institutionalUser))

    expect(authStore.isAuthenticated).toBe(true)

    emitUnauthorized()

    expect(authStore.isAuthenticated).toBe(false)
    expect(authStore.user).toBeNull()
    expect(authStore.sessionState).toBe('unauthenticated')
    expect(localStorage.getItem('user')).toBeNull()
  })

  it('keeps the session intact when the server reports an authorization denial', () => {
    const authStore = useAuthStore()
    authStore.user = institutionalUser

    emitForbidden('Forbidden: Missing required baseline permission [documents.update].')

    expect(authStore.isAuthenticated).toBe(true)
    expect(authStore.user).not.toBeNull()
    expect(authStore.sessionState).not.toBe('unauthenticated')
  })

  it('translates a 401 response into a session termination signal', async () => {
    const authStore = useAuthStore()
    authStore.user = institutionalUser

    const handlers = (apiClient.interceptors.response as unknown as {
      handlers: Array<{ rejected?: (error: unknown) => Promise<unknown> }>
    }).handlers

    const rejected = handlers[0]?.rejected
    expect(rejected).toBeTypeOf('function')

    // The interceptor re-rejects after handling the failure, so the rejection
    // is expected and swallowed here.
    await Promise.resolve(
      rejected?.({
        config: { url: '/documents' },
        response: { status: 401, data: { message: 'Unauthenticated.' } },
      })
    ).catch(() => undefined)

    expect(authStore.isAuthenticated).toBe(false)
  })

  it('surfaces a 403 response without terminating the session', async () => {
    const authStore = useAuthStore()
    authStore.user = institutionalUser

    const handlers = (apiClient.interceptors.response as unknown as {
      handlers: Array<{ rejected?: (error: unknown) => Promise<unknown> }>
    }).handlers

    const rejected = handlers[0]?.rejected
    await Promise.resolve(
      rejected?.({
        config: { url: '/documents/1' },
        response: { status: 403, data: { message: 'Forbidden.' } },
      })
    ).catch(() => undefined)

    expect(authStore.isAuthenticated).toBe(true)
  })
})

describe('Permission driven route guards', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
  })

  it('admits a route whose capability the user holds', async () => {
    const authStore = useAuthStore()
    authStore.user = institutionalUser
    authStore.sessionState = 'verified'

    await router.push('/documents')
    await router.isReady()

    expect(router.currentRoute.value.name).toBe('documents.list')
  })

  it('refuses a route whose capability the user does not hold', async () => {
    const authStore = useAuthStore()
    authStore.user = institutionalUser
    authStore.sessionState = 'verified'

    await router.push('/admin/users')
    await router.isReady()

    expect(router.currentRoute.value.name).toBe('error.forbidden')
  })

  it('refuses a route when the capability is held only in a different scope', async () => {
    const authStore = useAuthStore()
    authStore.user = {
      ...institutionalUser,
      permissions: ['documents.view'],
    }
    authStore.sessionState = 'verified'

    await router.push('/admin/roles')
    await router.isReady()

    expect(router.currentRoute.value.name).toBe('error.forbidden')
  })

  it('admits a route that declares alternative capabilities when one is held', async () => {
    const authStore = useAuthStore()
    authStore.user = institutionalUser
    authStore.sessionState = 'verified'

    await router.push('/dashboard')
    await router.isReady()

    expect(router.currentRoute.value.name).toBe('dashboard')
  })

  it('sends an unauthenticated visitor to the sign in route', async () => {
    const authStore = useAuthStore()
    authStore.sessionState = 'verified'

    await router.push('/documents')
    await router.isReady()

    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/documents')
  })
})

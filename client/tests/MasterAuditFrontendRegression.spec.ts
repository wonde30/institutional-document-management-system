import { describe, it, expect, beforeEach, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { gregorianToEthiopian, parseAndConvertEthiopianToGregorian } from '@/utils/ethiopianDate'
import router from '@/router'

vi.mock('@/api/client', async () => {
  const actual = await vi.importActual<typeof import('@/api/client')>('@/api/client')
  return {
    ...actual,
    authApi: {
      ...actual.authApi,
      setActiveUnit: vi.fn(),
      me: vi.fn(),
      login: vi.fn(),
      logout: vi.fn(),
    },
    navigationApi: {
      get: vi.fn().mockResolvedValue({ data: { success: true, data: [] } }),
    },
  }
})

describe('Master Audit Frontend Remediation Regressions', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('recovers cleanly from corrupted localStorage user JSON without crashing store bootstrap', () => {
    localStorage.setItem('user', '{broken-json')
    const auth = useAuthStore()
    expect(auth.user).toBeNull()
    expect(localStorage.getItem('user')).toBeNull()
  })

  it('restores previous user context and returns false if fetchUser fails during switchUnit', async () => {
    const { authApi } = await import('@/api/client')
    localStorage.setItem(
      'user',
      JSON.stringify({
        id: 5,
        name: 'Officer',
        email: 'officer@wu.edu.et',
        roles: ['staff'],
        permissions: ['documents.view'],
        active_unit_id: 1,
      })
    )
    const auth = useAuthStore()
    vi.mocked(authApi.setActiveUnit).mockResolvedValue({ data: { success: true } } as any)
    vi.mocked(authApi.me).mockRejectedValue(new Error('500 Server Error'))

    const ok = await auth.switchUnit(2)
    expect(ok).toBe(false)
    expect(auth.user?.permissions).toEqual(['documents.view'])
    expect(auth.activeUnitId).toBe(1)
  })

  it('allows unauthenticated navigation to /verify-email', () => {
    const verifyRoute = router.getRoutes().find((r) => r.path === '/verify-email')
    expect(verifyRoute).toBeDefined()
    expect(verifyRoute?.meta?.isPublic).toBe(true)
    expect(verifyRoute?.meta?.requiresAuth).toBeUndefined()
  })

  it('handles ISO timestamps and YYYY-MM-DD Ethiopic strings in ethiopianDate utilities', () => {
    const eth = gregorianToEthiopian('2026-09-28T10:00:00Z')
    expect(eth.formatted).toBe('18/01/2019 ዓ.ም')
    expect(parseAndConvertEthiopianToGregorian('2019-01-18')).toBe('2026-09-28')
  })
})

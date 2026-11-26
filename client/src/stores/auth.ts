import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi, navigationApi } from '@/api/client'
import { onUnauthorized } from '@/api/sessionEvents'
import type { OrganizationalUnitSummary, User } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  // Pure cookie-based SPA session authentication: No tokens stored in localStorage or browser headers
  const token = ref<string | null>(null)
  let initialUser: User | null = null
  try {
    const rawUser = localStorage.getItem('user')
    initialUser = rawUser ? (JSON.parse(rawUser) as User) : null
  } catch {
    localStorage.removeItem('user')
    initialUser = null
  }
  const user = ref<User | null>(initialUser)
  const dynamicNavigation = ref<any[] | null>(null)
  const isLoading = ref<boolean>(false)
  const error = ref<string | null>(null)

  /**
   * Session verification state.
   *
   * 'unknown'        - the session has not been confirmed with the server yet.
   * 'verified'       - the server confirmed the session.
   * 'unauthenticated'- the server rejected the session.
   *
   * While the state is 'unknown' the router retries the bootstrap, so a
   * transient transport failure cannot permanently strand the application on a
   * stale or empty session.
   */
  const sessionState = ref<'unknown' | 'verified' | 'unauthenticated'>('unknown')

  const isAuthenticated = computed(() => !!user.value)

  const userRoles = computed<string[]>(() => {
    if (!user.value?.roles) return []
    return user.value.roles
      .map((r: Record<string, unknown>) => {
        const code = r.role_code || r.code || r.name
        return typeof code === 'string' ? code : ''
      })
      .filter(Boolean)
  })

  const isSuperAdmin = computed(() => {
    return userRoles.value.some(
      (r) =>
        r.toLowerCase() === 'super_admin' ||
        r.toLowerCase() === 'super admin' ||
        r.toLowerCase() === 'super-admin'
    )
  })

  const userPermissions = computed<string[]>(() => {
    return user.value?.permissions || []
  })

  const primaryUnit = computed(() => {
    return user.value?.primary_membership || null
  })

  function hasRole(roleCodeOrName: string): boolean {
    if (isSuperAdmin.value) return true
    const target = roleCodeOrName.toLowerCase()
    return userRoles.value.some((r) => r.toLowerCase() === target)
  }

  function hasAnyRole(roles: string[]): boolean {
    if (isSuperAdmin.value) return true
    const targets = roles.map((r) => r.toLowerCase())
    return userRoles.value.some((r) => targets.includes(r.toLowerCase()))
  }

  /**
   * Whether the account holds a capability.
   *
   * A list of capabilities is satisfied when **any** of them is held, which is
   * the same "any of" semantics the route guard and the navigation filter use.
   */
  function hasPermission(permissionCode: string | string[]): boolean {
    if (isSuperAdmin.value) return true

    if (Array.isArray(permissionCode)) {
      return permissionCode.some((code) => hasPermission(code))
    }

    const target = permissionCode.toLowerCase().trim()
    return userPermissions.value.some((p) => p.toLowerCase().trim() === target)
  }

  async function login(credentials: { email: string; password: string }) {
    isLoading.value = true
    error.value = null
    try {
      const response = await authApi.login(credentials)
      const data = response.data as unknown as { user?: User; data?: User }
      const receivedUser = data.user || data.data || (data as unknown as User)

      token.value = null
      user.value = receivedUser
      sessionState.value = 'verified'

      // Maintain user identity in state; clear any legacy token artifacts from localStorage
      localStorage.removeItem('token')
      localStorage.setItem('user', JSON.stringify(receivedUser))
      fetchNavigation().catch(() => {})
      return true
    } catch (err: unknown) {
      const apiErr = err as { response?: { data?: { message?: string } } }
      error.value =
        apiErr.response?.data?.message ||
        'Authentication failed. Please verify your institutional email and password.'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function logout() {
    try {
      await authApi.logout()
    } catch {
      // Ignore network errors on logout
    } finally {
      clearSession()
    }
  }

  async function fetchNavigation() {
    if (!isAuthenticated.value) {
      dynamicNavigation.value = null
      return null
    }
    try {
      const res = await navigationApi.get()
      if (res.data?.success && Array.isArray(res.data.data)) {
        dynamicNavigation.value = res.data.data
        return dynamicNavigation.value
      }
    } catch {
      // Graceful fallback
    }
    return null
  }

  /**
   * The organizational units the server has determined the user may select.
   */
  const availableUnits = computed<OrganizationalUnitSummary[]>(() => user.value?.available_units || [])

  /**
   * The organizational unit the user is currently operating in.
   */
  const activeUnit = computed<OrganizationalUnitSummary | null>(() => user.value?.active_unit || null)

  const activeUnitId = computed<number | null>(() => user.value?.active_unit_id ?? null)

  /**
   * True when the user holds scoped capabilities but no unit has been selected
   * yet, so the interface must ask for a selection.
   */
  const unitSelectionRequired = computed<boolean>(() => !!user.value?.unit_selection_required)

  /**
   * True while the active organizational unit is being switched.
   */
  const isSwitchingUnit = ref<boolean>(false)

  let unitSwitchPromise: Promise<boolean> | null = null

  /**
   * Switch the active organizational unit.
   *
   * The server validates the requested unit and recalculates the capability
   * set for the new context. The capability list is emptied for the duration
   * of the switch so that no stale capability is presented, and the previous
   * state is restored when the switch is refused.
   */
  function switchUnit(unitId: number): Promise<boolean> {
    if (unitSwitchPromise) return unitSwitchPromise

    const previousUser = user.value

    isSwitchingUnit.value = true

    unitSwitchPromise = (async () => {
      try {
        // Fail closed for the duration of the switch: a capability that
        // belonged to the previous context must not remain visible.
        if (user.value) {
          user.value = { ...user.value, permissions: [] }
        }

        await authApi.setActiveUnit(unitId)

        // Re-read the canonical context and the navigation for it.
        const refreshed = await fetchUser()
        if (!refreshed) {
          user.value = previousUser
          return false
        }

        return true
      } catch (err: unknown) {
        const apiErr = err as { response?: { data?: { message?: string } } }
        error.value =
          apiErr.response?.data?.message ||
          'The organizational context could not be changed.'
        // Restore the previous context in full: a refused switch must not
        // leave the interface in a partially updated state.
        user.value = previousUser
        await fetchNavigation().catch(() => {})
        return false
      } finally {
        isSwitchingUnit.value = false
        unitSwitchPromise = null
      }
    })()

    return unitSwitchPromise
  }

  /**
   * Await an in-flight context switch, if any.
   */
  async function awaitPendingUnitSwitch(): Promise<void> {
    if (unitSwitchPromise) {
      await unitSwitchPromise
    }
  }

  let fetchUserPromise: Promise<User | null> | null = null

  /**
   * Clear every trace of the authenticated session from client state.
   *
   * The server is the only authority on session validity. When it reports
   * that a session is no longer valid the client must drop the cached
   * identity, the cached capability list and the cached navigation, otherwise
   * the application shell keeps rendering an authenticated workspace for a
   * session that the server will refuse.
   */
  function clearSession(): void {
    user.value = null
    token.value = null
    dynamicNavigation.value = null
    sessionState.value = 'unauthenticated'
    localStorage.removeItem('token')
    localStorage.removeItem('user')
  }

  // A session termination reported by the API client invalidates the store.
  onUnauthorized(() => {
    clearSession()
  })

  async function fetchUser(): Promise<User | null> {
    if (fetchUserPromise) return fetchUserPromise

    fetchUserPromise = (async () => {
      try {
        const response = await authApi.me()
        const resData = response.data as unknown as { user?: User; data?: User }
        const userData = resData.user || resData.data || (resData as unknown as User)
        user.value = userData
        sessionState.value = 'verified'
        localStorage.setItem('user', JSON.stringify(userData))
        fetchNavigation().catch(() => {})
        return user.value
      } catch (err: unknown) {
        const apiErr = err as { response?: { status?: number } }
        const status = apiErr.response?.status

        // 401/403 mean the server rejected the session: drop the cached
        // identity so the shell cannot keep presenting stale authority.
        if (status === 401 || status === 403) {
          clearSession()
          return null
        }

        // A transport or server fault leaves the session state unverified. The
        // cached identity and capability list are retained so a transient fault
        // does not strand the workspace, and the session state stays unknown so
        // the router retries the bootstrap. The server remains the only
        // authority: every request is re-authorized regardless of this cache.
        sessionState.value = 'unknown'
        return null      } finally {
        fetchUserPromise = null
      }
    })()

    return fetchUserPromise
  }

  return {
    token,
    user,
    dynamicNavigation,
    isLoading,
    error,
    sessionState,
    isAuthenticated,
    isSuperAdmin,
    userRoles,
    userPermissions,
    primaryUnit,
    availableUnits,
    activeUnit,
    activeUnitId,
    unitSelectionRequired,
    isSwitchingUnit,
    hasRole,
    hasAnyRole,
    hasPermission,
    login,
    logout,
    fetchUser,
    fetchNavigation,
    switchUnit,
    awaitPendingUnitSwitch,
  }
})

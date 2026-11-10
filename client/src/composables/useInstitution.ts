import { ref } from 'vue'
import { settingsApi } from '@/api/client'

/**
 * The institutional identity published by the server.
 *
 * The value comes from the runtime configuration catalogue, so an
 * administrator can change the institutional display name without a
 * deployment. It is fetched once and shared, following the same module level
 * pattern as the toast composable.
 */
const displayName = ref('Wollo University')
let loaded = false
let pending: Promise<void> | null = null

export function useInstitution() {
  /**
   * Load the public institutional identity.
   *
   * A failure is not fatal: the interface keeps the default name so the
   * portal remains usable.
   */
  function load(): Promise<void> {
    if (loaded) return Promise.resolve()
    if (pending) return pending

    pending = settingsApi
      .publicSettings()
      .then((response) => {
        const name = response.data?.data?.['institution.display_name']
        if (typeof name === 'string' && name.trim() !== '') {
          displayName.value = name
        }
        loaded = true
      })
      .catch(() => {
        // Keep the default name; the portal remains usable.
      })
      .finally(() => {
        pending = null
      })

    return pending
  }

  return {
    displayName,
    load,
  }
}

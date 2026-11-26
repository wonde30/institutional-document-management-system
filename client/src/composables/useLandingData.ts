import { ref, computed } from 'vue'
import { landingApi } from '@/api/landing'
import type {
  LandingSummary,
  LandingModule,
  LandingAccreditation,
  LandingAnnouncement,
} from '@/types'

// Module-level cache so returning to landing doesn't flicker
const cachedSummary = ref<LandingSummary | null>(null)
const cachedModules = ref<LandingModule[]>([])
const cachedAccreditation = ref<LandingAccreditation | null>(null)
const cachedAnnouncements = ref<LandingAnnouncement[]>([])

export function useLandingData() {
  const summary = ref<LandingSummary | null>(cachedSummary.value)
  const modules = ref<LandingModule[]>(cachedModules.value)
  const accreditation = ref<LandingAccreditation | null>(cachedAccreditation.value)
  const announcements = ref<LandingAnnouncement[]>(cachedAnnouncements.value)

  const isLoading = ref<boolean>(false)
  const isSummaryLoading = ref<boolean>(false)
  const isModulesLoading = ref<boolean>(false)
  const isAccreditationLoading = ref<boolean>(false)
  const isAnnouncementsLoading = ref<boolean>(false)

  const error = ref<string | null>(null)
  const retryCount = ref<number>(0)

  const isError = computed(() => !!error.value && !summary.value)
  const isEmpty = computed(() => {
    if (isLoading.value) return false
    return (
      !summary.value ||
      (summary.value.published_documents === 0 &&
        summary.value.active_offices === 0 &&
        summary.value.correspondence_routed === 0)
    )
  })

  async function fetchSummary() {
    isSummaryLoading.value = true
    try {
      const res = await landingApi.getSummary()
      summary.value = res.data.data
      cachedSummary.value = res.data.data
    } catch (err: unknown) {
      console.warn('Failed to load landing summary:', err)
      throw err
    } finally {
      isSummaryLoading.value = false
    }
  }

  async function fetchModules() {
    isModulesLoading.value = true
    try {
      const res = await landingApi.getModules()
      modules.value = res.data.data
      cachedModules.value = res.data.data
    } catch (err: unknown) {
      console.warn('Failed to load landing modules:', err)
      throw err
    } finally {
      isModulesLoading.value = false
    }
  }

  async function fetchAccreditation() {
    isAccreditationLoading.value = true
    try {
      const res = await landingApi.getAccreditation()
      accreditation.value = res.data.data
      cachedAccreditation.value = res.data.data
    } catch (err: unknown) {
      console.warn('Failed to load landing accreditation:', err)
      throw err
    } finally {
      isAccreditationLoading.value = false
    }
  }

  async function fetchAnnouncements() {
    isAnnouncementsLoading.value = true
    try {
      const res = await landingApi.getAnnouncements()
      announcements.value = res.data.data
      cachedAnnouncements.value = res.data.data
    } catch (err: unknown) {
      console.warn('Failed to load landing announcements:', err)
      // Announcements are non-critical, don't fail entire view
    } finally {
      isAnnouncementsLoading.value = false
    }
  }

  async function fetchAll(isRetry = false) {
    if (isRetry) {
      retryCount.value++
    }
    isLoading.value = true
    error.value = null

    const results = await Promise.allSettled([
      fetchSummary(),
      fetchModules(),
      fetchAccreditation(),
      fetchAnnouncements(),
    ])

    // If core metrics or modules failed and we have no cached data, flag error
    const summaryFailed = results[0].status === 'rejected'
    const modulesFailed = results[1].status === 'rejected'
    if ((summaryFailed || modulesFailed) && (!summary.value || modules.value.length === 0)) {
      error.value =
        'Unable to connect to the institutional repository server. Please verify your connection and try again.'
    }

    isLoading.value = false
  }

  return {
    summary,
    modules,
    accreditation,
    announcements,
    isLoading,
    isSummaryLoading,
    isModulesLoading,
    isAccreditationLoading,
    isAnnouncementsLoading,
    error,
    isError,
    isEmpty,
    retryCount,
    fetchAll,
    fetchSummary,
    fetchModules,
    fetchAccreditation,
    fetchAnnouncements,
  }
}

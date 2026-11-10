import { defineStore } from 'pinia'
import { ref } from 'vue'
import { metadataApi, organizationUnitsApi } from '@/api/client'
import type {
  DocumentCategory,
  ConfidentialityLevel,
  CorrespondenceType,
  Priority,
  DispatchMethod,
  UnitType,
} from '@/types'

export const useMetadataStore = defineStore('metadata', () => {
  const categories = ref<DocumentCategory[]>([])
  const confidentialityLevels = ref<ConfidentialityLevel[]>([])
  const correspondenceTypes = ref<CorrespondenceType[]>([])
  const priorities = ref<Priority[]>([])
  const dispatchMethods = ref<DispatchMethod[]>([])
  const unitTypes = ref<UnitType[]>([])
  const isLoaded = ref<boolean>(false)
  const isLoading = ref<boolean>(false)

  async function loadAll() {
    if (isLoaded.value || isLoading.value) return
    isLoading.value = true
    try {
      const [metaRes, unitTypesRes] = await Promise.all([
        metadataApi.getAll(),
        organizationUnitsApi.unitTypes().catch(() => ({ data: { data: [] } })),
      ])

      const data = metaRes.data.data
      categories.value = data.document_categories || []
      confidentialityLevels.value = data.confidentiality_levels || []
      correspondenceTypes.value = data.correspondence_types || []
      priorities.value = data.priorities || []
      dispatchMethods.value = data.dispatch_methods || []
      unitTypes.value = unitTypesRes.data.data || []
      isLoaded.value = true
    } catch (err) {
      console.error('Failed to load reference metadata:', err)
    } finally {
      isLoading.value = false
    }
  }

  return {
    categories,
    confidentialityLevels,
    correspondenceTypes,
    priorities,
    dispatchMethods,
    unitTypes,
    isLoaded,
    isLoading,
    loadAll,
  }
})

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { documentsApi, organizationUnitsApi } from '@/api/client'
import { useMetadataStore } from '@/stores/metadata'
import type { Document, OrganizationUnit, PaginationMeta } from '@/types'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import PageHeader from '@/components/PageHeader.vue'
import DataTable, { type TableColumn } from '@/components/data-display/DataTable.vue'
import SearchFilterBar from '@/components/data-display/SearchFilterBar.vue'
import Pagination from '@/components/Pagination.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import ClassificationBadge from '@/components/ClassificationBadge.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { formatDate } from '@/utils/formatters'
import { Plus, Eye, FileText, Filter } from 'lucide-vue-next'

const router = useRouter()
const authStore = useAuthStore()
const metaStore = useMetadataStore()
const toast = useToast()

const documents = ref<Document[]>([])
const units = ref<OrganizationUnit[]>([])
const isLoading = ref(false)
const showFilters = ref(false)

const pagination = ref<PaginationMeta>({
 current_page: 1,
 last_page: 1,
 total: 0,
})

// Filter models
const searchQuery = ref('')
const selectedCategory = ref('')
const selectedConfidentiality = ref('')
const selectedUnit = ref('')
const selectedLifecycle = ref('')
const sortBy = ref('registered_at')
const sortOrder = ref<'asc' | 'desc'>('desc')

const columns: TableColumn[] = [
 { key: 'reference_number', label: 'Document Reference & Code', sortable: true },
 { key: 'title', label: 'Title & Summary', sortable: true },
 { key: 'category', label: 'Document Category' },
 { key: 'confidentiality', label: 'Confidentiality Level' },
 { key: 'owner_unit', label: 'Custodian Department' },
 { key: 'lifecycle', label: 'Lifecycle State', align: 'center', sortable: true },
 { key: 'registered_at', label: 'Date Registered', sortable: true },
]

const activeFiltersCount = computed(() => {
 let count = 0
 if (selectedCategory.value) count++
 if (selectedConfidentiality.value) count++
 if (selectedUnit.value) count++
 if (selectedLifecycle.value) count++
 return count
})

async function loadUnits() {
 try {
 const res = await organizationUnitsApi.list({ per_page: 100 })
 units.value = (res.data as any).data || []
 } catch (err) {
 console.error('Failed to load organization units', err)
 }
}

async function loadDocuments(page = 1) {
 isLoading.value = true
 try {
 const params: Record<string, unknown> = {
 page,
 search: searchQuery.value || undefined,
 category_id: selectedCategory.value || undefined,
 confidentiality_id: selectedConfidentiality.value || undefined,
 owner_unit_id: selectedUnit.value || undefined,
 lifecycle: selectedLifecycle.value || undefined,
 sort_by: sortBy.value,
 sort_order: sortOrder.value,
 }

 const res = await documentsApi.list(params)
 documents.value = (res.data as any).data || []
 if (res.data.meta) {
 pagination.value = res.data.meta
 }
 } catch (err: any) {
 toast.error(err.response?.data?.message || 'Failed to retrieve document repository.')
 } finally {
 isLoading.value = false
 }
}

function handleSort(columnKey: string) {
 if (sortBy.value === columnKey) {
 sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
 } else {
 sortBy.value = columnKey
 sortOrder.value = 'asc'
 }
 loadDocuments(1)
}

function resetFilters() {
 searchQuery.value = ''
 selectedCategory.value = ''
 selectedConfidentiality.value = ''
 selectedUnit.value = ''
 selectedLifecycle.value = ''
 loadDocuments(1)
}

onMounted(async () => {
 await Promise.all([metaStore.loadAll(), loadUnits()])
 loadDocuments(1)
})
</script>

<template>
 <div class="space-y-5">
  <PageHeader
    title="Document Repository"
    subtitle="Search, view, and manage institutional records with version tracking and scoped security classifications"
  >
    <template #actions>
      <BaseButton
        v-if="authStore.hasPermission('documents.create')"
        variant="primary"
        size="sm"
        @click="router.push('/documents/create')"
      >
        <Plus class="w-3.5 h-3.5 mr-1" />
        Register document
      </BaseButton>
    </template>
  </PageHeader>

 <!-- Search & Filter Bar -->
 <div class="bg-surface border border-line rounded-lg p-3 sm:p-4 space-y-3">
 <div class="flex flex-col sm:flex-row items-center gap-3">
 <div class="flex-1 w-full">
 <SearchFilterBar
 v-model="searchQuery"
 placeholder="Search documents by reference number, title, or summary..."
 @search="loadDocuments(1)"
 />
 </div>

 <BaseButton
 variant="outline"
 size="sm"
 class="w-full sm:w-auto shrink-0"
 :class="activeFiltersCount > 0 ? 'border-primary text-primary' : ''"
 @click="showFilters = !showFilters"
 >
 <Filter class="w-3.5 h-3.5 mr-1" />
 <span>Filters</span>
 <span
 v-if="activeFiltersCount > 0"
 class="ml-1 px-1.5 py-0.2 rounded-full bg-primary text-white text-xs flex items-center justify-center font-bold"
 >
 {{ activeFiltersCount }}
 </span>
 </BaseButton>
 </div>

 <!-- Collapsible Filters Drawer -->
 <div v-show="showFilters" class="pt-4 border-t border-hairline-subtle grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
 <BaseSelect
 v-model="selectedCategory"
 label="Category"
 :options="metaStore.categories.map((c) => ({ label: c.name, value: c.id }))"
 placeholder="All Categories"
 @update:model-value="loadDocuments(1)"
 />

 <BaseSelect
 v-model="selectedConfidentiality"
 label="Classification"
 :options="metaStore.confidentialityLevels.map((c) => ({ label: c.name, value: c.id }))"
 placeholder="All Classifications"
 @update:model-value="loadDocuments(1)"
 />

 <BaseSelect
 v-model="selectedUnit"
 label="Accountable Unit"
 :options="units.map((u) => ({ label: `${u.name} (${u.code})`, value: u.id }))"
 placeholder="All Units"
 @update:model-value="loadDocuments(1)"
 />

 <BaseSelect
 v-model="selectedLifecycle"
 label="Lifecycle"
 :options="[
 { label: 'Active', value: 'active' },
 { label: 'Retired (Archived)', value: 'retired' },
 { label: 'Tombstoned', value: 'tombstoned' },
 ]"
 placeholder="All Lifecycle States"
 @update:model-value="loadDocuments(1)"
 />

 <div class="sm:col-span-2 lg:col-span-4 flex justify-end gap-2 pt-2">
 <BaseButton variant="ghost" size="sm" @click="resetFilters">Reset Filters</BaseButton>
 </div>
 </div>
 </div>

 <!-- Data Table -->
 <div class="overflow-x-auto">
  <DataTable
 :columns="columns"
 :items="documents"
 :loading="isLoading"
 :sort-by="sortBy"
 :sort-order="sortOrder"
 empty-title="No documents found"
 empty-description="No institutional records match the selected criteria."
 @sort="handleSort"
 @row-click="(row) => router.push(`/documents/${row.id}`)"
 >
 <template #cell-reference_number="{ value }">
 <span class="font-mono text-xs font-semibold text-primary">
 {{ value }}
 </span>
 </template>

 <template #cell-title="{ item }">
 <div class="max-w-md lg:max-w-xl">
 <div class="text-xs font-semibold text-ink truncate">{{ item.title }}</div>
 <div v-if="item.summary" class="text-xs text-ink-muted truncate mt-0.5">{{ item.summary }}</div>
 </div>
 </template>

 <template #cell-category="{ item }">
 <span class="text-xs text-ink-secondary font-medium">
 {{ item.category?.name || 'Uncategorized' }}
 </span>
 </template>

 <template #cell-confidentiality="{ item }">
 <ClassificationBadge :classification="item.confidentiality" />
 </template>

 <template #cell-owner_unit="{ item }">
 <span class="text-xs text-ink-muted truncate max-w-[180px] lg:max-w-xs inline-block" :title="item.owner_unit?.name">
 {{ item.owner_unit?.name || 'Wollo University' }}
 </span>
 </template>

 <template #cell-lifecycle="{ item }">
 <StatusBadge :status="item.lifecycle" />
 </template>

 <template #cell-registered_at="{ value }">
 <span class="text-xs text-ink-muted">
 {{ formatDate(value) }}
 </span>
 </template>

 <template #actions="{ item }">
 <div class="flex items-center justify-end gap-1.5" @click.stop>
 <BaseButton
 variant="ghost"
 size="sm"
 class="h-8 w-8 p-0"
 title="View Details"
 aria-label="View document details"
 @click="router.push(`/documents/${item.id}`)"
 >
 <Eye class="w-4 h-4 text-ink-muted" />
 </BaseButton>
 </div>
 </template>
 </DataTable>
 </div>

 <!-- Pagination -->
 <Pagination
 v-if="pagination.total > 0"
 :meta="pagination"
 @page-change="loadDocuments"
 />
 </div>
</template>

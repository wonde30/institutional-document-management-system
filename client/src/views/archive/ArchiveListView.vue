<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { archiveApi } from '@/api/client'
import type { ArchiveEntry, ArchiveCollection, RetentionPolicy, LegalHold, PaginationMeta } from '@/types'
import DataTable, { type TableColumn } from '@/components/data-display/DataTable.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import LoadingSkeleton from '@/components/ui/LoadingSkeleton.vue'
import ClassificationBadge from '@/components/ClassificationBadge.vue'
import { useToast } from '@/composables/useToast'
import {
 Archive,
 FolderArchive,
 Scale,
 Calendar,
 Clock,
 ShieldCheck,
 FileText,
 RefreshCw,
 Search,
 ExternalLink,
 ShieldAlert,
 Layers,
} from 'lucide-vue-next'

const router = useRouter()
const toast = useToast()

const activeTab = ref<'entries' | 'collections' | 'holds'>('entries')
const isLoading = ref(true)

// Data
const entries = ref<ArchiveEntry[]>([])
const collections = ref<ArchiveCollection[]>([])
const policies = ref<RetentionPolicy[]>([])
const legalHolds = ref<LegalHold[]>([])

const pagination = ref<PaginationMeta>({
 current_page: 1,
 last_page: 1,
 per_page: 25,
 total: 0,
})

const filterCollection = ref<number | ''>('')

const tableColumns: TableColumn[] = [
 { key: 'document', label: 'Archived Document & Category', sortable: true },
 { key: 'collection', label: 'Institutional Collection' },
 { key: 'retention', label: 'Retention Policy' },
 { key: 'archived_at', label: 'Archived Date' },
 { key: 'review_at', label: 'Next Review Date' },
 { key: 'archived_by', label: 'Archivist' },
 { key: 'actions', label: '', align: 'right' },
]

async function loadData(page = 1) {
 isLoading.value = true
 try {
 const [entriesRes, collectionsRes, policiesRes, holdsRes] = await Promise.all([
 archiveApi.entries({
 page,
 per_page: pagination.value.per_page,
 collection_id: filterCollection.value || undefined,
 }),
 archiveApi.collections(),
 archiveApi.policies(),
 archiveApi.legalHolds(),
 ])

 const entriesData = entriesRes.data.data
 entries.value = Array.isArray(entriesData) ? entriesData : (entriesData as any).data || []
 const meta = (entriesRes.data as any).meta
 if (meta) {
 pagination.value = {
 current_page: meta.current_page,
 last_page: meta.last_page,
 per_page: pagination.value.per_page,
 total: meta.total,
 }
 }

 collections.value = collectionsRes.data.data
 policies.value = policiesRes.data.data
 legalHolds.value = holdsRes.data.data
 } catch (err: any) {
 toast.error('Archive Error', err.response?.data?.message || 'Failed to load archive repository.')
 } finally {
 isLoading.value = false
 }
}

function viewDocument(documentId: number) {
 router.push(`/documents/${documentId}`)
}

onMounted(() => {
 loadData(1)
})
</script>

<template>
 <div class="space-y-6">
 <!-- Header -->
 <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
 <div>
 <div class="notion-eyebrow text-primary mb-1">
 INSTITUTIONAL RECORDS / DIGITAL ARCHIVE
 </div>
 <h1 class="text-2xl font-semibold tracking-tight text-ink-secondary flex items-center gap-2.5">
 <Archive class="w-6 h-6 text-primary" />
 Digital Archive & Long-Term Preservation
 </h1>
 <p class="text-xs sm:text-sm text-ink-muted mt-0.5">
 Statutory archival repository, retention schedules, legal holds, and historical record curation.
 </p>
 </div>

 <div class="flex items-center gap-2.5">
 <BaseButton variant="outline" size="sm" :loading="isLoading" @click="loadData(1)">
 <RefreshCw class="w-4 h-4 mr-1.5" />
 Refresh
 </BaseButton>
 </div>
 </div>

 <!-- Stat Cards -->
 <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
 <div class="bg-surface p-4 rounded-lg border border-hairline shadow-xs flex items-center gap-4">
 <div class="p-3 bg-[#fff4e6] dark:bg-[#382412] text-[#dd5b00] dark:text-[#ff9243] rounded-md">
 <FolderArchive class="w-6 h-6" />
 </div>
 <div>
 <div class="text-2xl font-semibold text-ink-secondary">{{ pagination.total }}</div>
 <div class="text-xs text-ink-muted font-medium">Archived Document Records</div>
 </div>
 </div>

 <div class="bg-surface p-4 rounded-lg border border-hairline shadow-xs flex items-center gap-4">
 <div class="p-3 bg-primary-subtle text-primary rounded-md">
 <Layers class="w-6 h-6" />
 </div>
 <div>
 <div class="text-2xl font-semibold text-ink-secondary">{{ collections.length }}</div>
 <div class="text-xs text-ink-muted font-medium">Archival Collections</div>
 </div>
 </div>

 <div class="bg-surface p-4 rounded-lg border border-hairline shadow-xs flex items-center gap-4">
 <div class="p-3 bg-[#fdebee] dark:bg-[#381619] text-[#e03e3e] dark:text-[#ff7373] rounded-md">
 <Scale class="w-6 h-6" />
 </div>
 <div>
 <div class="text-2xl font-semibold text-ink-secondary">{{ legalHolds.length }}</div>
 <div class="text-xs text-ink-muted font-medium">Active Legal Holds</div>
 </div>
 </div>
 </div>

 <!-- Tab Buttons -->
 <div class="flex items-center gap-2 border-b border-hairline pb-2">
 <button
 type="button"
 class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors flex items-center gap-1.5"
 :class="activeTab === 'entries' ? 'bg-primary-subtle text-primary font-semibold border border-primary/20' : 'text-ink-muted hover:bg-canvas-soft'"
 @click="activeTab = 'entries'"
 >
 <FolderArchive class="w-4 h-4" />
 Archived Entries ({{ pagination.total }})
 </button>

 <button
 type="button"
 class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors flex items-center gap-1.5"
 :class="activeTab === 'collections' ? 'bg-primary-subtle text-primary font-semibold border border-primary/20' : 'text-ink-muted hover:bg-canvas-soft'"
 @click="activeTab = 'collections'"
 >
 <Layers class="w-4 h-4" />
 Collections Catalog ({{ collections.length }})
 </button>

 <button
 type="button"
 class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors flex items-center gap-1.5"
 :class="activeTab === 'holds' ? 'bg-primary-subtle text-primary font-semibold border border-primary/20' : 'text-ink-muted hover:bg-canvas-soft'"
 @click="activeTab = 'holds'"
 >
 <Scale class="w-4 h-4" />
 Legal Holds Ledger ({{ legalHolds.length }})
 </button>
 </div>

 <!-- Tab 1: Archived Entries -->
 <div v-if="activeTab === 'entries'" class="space-y-4">
 <div class="bg-surface rounded-lg border border-hairline p-4 shadow-xs flex items-center justify-between">
 <div class="flex items-center gap-3">
 <label class="text-xs font-semibold text-ink-secondary">Filter Collection:</label>
 <select
 v-model="filterCollection"
 class="text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-3 py-1.5 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 @change="loadData(1)"
 >
 <option value="">All Collections</option>
 <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }} ({{ c.code }})</option>
 </select>
 </div>
 </div>

 <DataTable
 :columns="tableColumns"
 :items="entries"
 :loading="isLoading"
 :total="pagination.total"
 :page="pagination.current_page"
 :per-page="pagination.per_page"
 empty-title="No archived documents found"
 empty-description="Documents transferred to the archive will be recorded in this repository."
 @page-change="loadData"
 >
 <template #cell-document="{ item }">
 <div class="space-y-0.5">
 <div class="font-mono text-xs font-bold text-primary">
 {{ item.document?.sequence_number || `Document #${item.document_id}` }}
 </div>
 <div class="font-semibold text-xs text-ink-secondary">
 {{ item.document?.title || 'Document Record' }}
 </div>
 <div class="text-[10px] text-ink-muted flex items-center gap-1.5 mt-0.5">
 <span>{{ item.document?.category?.name || 'Category' }}</span>
 <span>•</span>
 <ClassificationBadge :classification="item.document?.confidentiality" />
 </div>
 </div>
 </template>

 <template #cell-collection="{ item }">
 <span class="px-2 py-0.5 rounded text-xs font-medium bg-[#fff4e6] dark:bg-[#382412] text-[#dd5b00] dark:text-[#ff9243] border border-[#dd5b00]/20">
 {{ item.collection?.name || `Collection #${item.collection_id}` }}
 </span>
 </template>

 <template #cell-retention="{ item }">
 <div class="text-xs">
 <div class="font-semibold text-ink-secondary">
 {{ item.retention_policy_version?.policy?.name || 'Statutory Schedule' }}
 </div>
 <div class="text-[10px] text-ink-muted">
 Version #{{ item.retention_policy_version_id }}
 </div>
 </div>
 </template>

 <template #cell-archived_at="{ value }">
 <span class="text-xs text-ink-muted">
 {{ value ? new Date(value).toLocaleDateString() : '—' }}
 </span>
 </template>

 <template #cell-review_at="{ value }">
 <span
 class="text-xs font-medium"
 :class="value && new Date(value) <= new Date() ? 'text-[#e03e3e] font-bold' : 'text-ink-muted'"
 >
 {{ value ? new Date(value).toLocaleDateString() : 'Permanent' }}
 </span>
 </template>

 <template #cell-archived_by="{ item }">
 <span class="text-xs text-ink-secondary font-medium">
 {{ item.archived_by_user?.name || `Archivist #${item.archived_by}` }}
 </span>
 </template>

 <template #cell-actions="{ item }">
 <BaseButton variant="outline" size="sm" @click="viewDocument(item.document_id)">
 <ExternalLink class="w-3.5 h-3.5 mr-1" />
 Inspect
 </BaseButton>
 </template>
 </DataTable>
 </div>

 <!-- Tab 2: Collections Catalog -->
 <div v-else-if="activeTab === 'collections'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
 <div
 v-for="c in collections"
 :key="c.id"
 class="bg-surface rounded-lg border border-hairline p-5 shadow-xs"
 >
 <div class="flex items-start justify-between gap-2 mb-2">
 <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-[#fff4e6] dark:bg-[#382412] text-[#dd5b00] dark:text-[#ff9243] border border-[#dd5b00]/20 uppercase">
 {{ c.code }}
 </span>
 <span v-if="c.parent" class="text-[10px] text-ink-muted">
 Sub of {{ c.parent.name }}
 </span>
 </div>

 <h3 class="text-sm font-semibold text-ink-secondary mt-1">
 {{ c.name }}
 </h3>
 <p class="text-xs text-ink-muted mt-1 line-clamp-3">
 {{ c.description || 'Statutory university archival record series.' }}
 </p>
 </div>
 </div>

 <!-- Tab 3: Legal Holds Ledger -->
 <div v-else-if="activeTab === 'holds'" class="bg-surface rounded-lg border border-hairline p-5 shadow-xs space-y-4">
 <div v-if="legalHolds.length === 0" class="py-12 text-center text-ink-muted text-sm">
 No active legal holds in effect. All retention policies operate under standard schedules.
 </div>

 <div v-else class="space-y-3">
 <div
 v-for="hold in legalHolds"
 :key="hold.id"
 class="p-4 rounded-lg border border-rose-200 dark:border-rose-900/60 bg-rose-50/30 dark:bg-rose-950/20 flex items-start justify-between gap-4"
 >
 <div class="flex items-start gap-3">
 <ShieldAlert class="w-5 h-5 text-rose-600 dark:text-rose-400 mt-0.5" />
 <div>
 <div class="flex items-center gap-2">
 <span class="font-mono text-xs font-bold text-rose-700 dark:text-rose-300">
 {{ hold.code }}
 </span>
 <span
 class="text-[10px] font-bold uppercase px-2 py-0.5 rounded"
 :class="hold.released_at ? 'bg-surface-muted text-ink-muted' : 'bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-200'"
 >
 {{ hold.released_at ? 'Released' : 'Active Hold' }}
 </span>
 </div>
 <p class="text-xs text-ink-secondary mt-1 font-medium">
 {{ hold.reason }}
 </p>
 <div class="text-[11px] text-ink-muted mt-1 flex items-center gap-3">
 <span>Placed: {{ new Date(hold.placed_at).toLocaleDateString() }}</span>
 <span v-if="hold.released_at">Released: {{ new Date(hold.released_at).toLocaleDateString() }}</span>
 </div>
 </div>
 </div>
 </div>
 </div>
 </div>
 </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { archiveApi } from '@/api/client'
import type { ArchiveEntry, ArchiveCollection, RetentionPolicy, LegalHold, PaginationMeta } from '@/types'
import DataTable, { type TableColumn } from '@/components/data-display/DataTable.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseAlert from '@/components/ui/BaseAlert.vue'
import ModalDialog from '@/components/ModalDialog.vue'
import PageHeader from '@/components/PageHeader.vue'
import ClassificationBadge from '@/components/ClassificationBadge.vue'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import {
  Archive,
  FolderArchive,
  Scale,
  RefreshCw,
  ExternalLink,
  ShieldAlert,
  Layers,
  Plus,
  Trash2,
  Lock,
  Unlock,
  AlertTriangle,
  CheckCircle2,
  FileWarning,
  RotateCcw
} from 'lucide-vue-next'

const router = useRouter()
const toast = useToast()
const authStore = useAuthStore()

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
const selectedQueue = ref<'' | 'upcoming_review' | 'expired_review' | 'held'>('')

// -----------------------------------------------------------------------------
// Retention Review State & Modals
// -----------------------------------------------------------------------------
const showReviewModal = ref(false)
const activeReviewEntry = ref<ArchiveEntry | null>(null)
const isSubmittingReview = ref(false)
const reviewForm = ref({
  review_action: 'extend' as 'extend' | 'retain_permanent' | 'request_disposal',
  months_extended: 12,
  notes: '',
})

function openReviewModal(entry: ArchiveEntry) {
  activeReviewEntry.value = entry
  reviewForm.value = {
    review_action: 'extend',
    months_extended: 12,
    notes: '',
  }
  showReviewModal.value = true
}

async function submitReview() {
  if (!activeReviewEntry.value) return
  if (!reviewForm.value.notes.trim() || reviewForm.value.notes.trim().length < 5) {
    toast.error('Substantive review notes (minimum 5 characters) are required.')
    return
  }

  isSubmittingReview.value = true
  try {
    await archiveApi.review(activeReviewEntry.value.id, {
      review_action: reviewForm.value.review_action,
      months_extended: reviewForm.value.review_action === 'extend' ? Number(reviewForm.value.months_extended) : undefined,
      notes: reviewForm.value.notes.trim(),
    })
    toast.success(`Retention review recorded with action "${reviewForm.value.review_action}".`)
    showReviewModal.value = false
    await loadData(pagination.value.current_page)
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to record retention review.')
  } finally {
    isSubmittingReview.value = false
  }
}

const tableColumns: TableColumn[] = [
  { key: 'document', label: 'Archived Document & Category', sortable: true },
  { key: 'collection', label: 'Institutional Collection' },
  { key: 'retention', label: 'Retention Policy' },
  { key: 'archived_at', label: 'Archived Date' },
  { key: 'review_at', label: 'Next Review Date' },
  { key: 'archived_by', label: 'Archivist' },
  { key: 'actions', label: '', align: 'right' },
]

// -----------------------------------------------------------------------------
// Legal Hold State & Modals
// -----------------------------------------------------------------------------
const showLegalHoldModal = ref(false)
const isSubmittingHold = ref(false)
const holdForm = ref({
  code: '',
  reason: '',
  document_id: '' as number | '',
})

function openCreateHoldModal() {
  holdForm.value = {
    code: `HOLD-WU-${new Date().getFullYear()}-${String(Math.floor(Math.random() * 900) + 100)}`,
    reason: '',
    document_id: '',
  }
  showLegalHoldModal.value = true
}

async function submitCreateLegalHold() {
  if (!holdForm.value.code.trim() || !holdForm.value.reason.trim()) {
    toast.error('Code and reason are required for statutory legal holds.')
    return
  }

  isSubmittingHold.value = true
  try {
    const payload: { code: string; reason: string; document_ids?: number[] } = {
      code: holdForm.value.code.trim(),
      reason: holdForm.value.reason.trim(),
    }
    if (holdForm.value.document_id) {
      payload.document_ids = [Number(holdForm.value.document_id)]
    }

    await archiveApi.createLegalHold(payload)
    toast.success(`Legal hold ${payload.code} placed successfully.`)
    showLegalHoldModal.value = false
    await loadData(pagination.value.current_page)
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to place legal hold.')
  } finally {
    isSubmittingHold.value = false
  }
}

async function handleReleaseHold(hold: LegalHold) {
  if (!confirm(`Are you sure you want to release legal hold "${hold.code}"? Retention schedules and disposal eligibility will be reinstated for attached documents.`)) {
    return
  }

  try {
    await archiveApi.releaseLegalHold(hold.id)
    toast.success(`Legal hold ${hold.code} released successfully.`)
    await loadData(pagination.value.current_page)
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to release legal hold.')
  }
}

// -----------------------------------------------------------------------------
// Archival Collection State & Modals
// -----------------------------------------------------------------------------
const showCollectionModal = ref(false)
const isSubmittingCollection = ref(false)
const collectionForm = ref({
  code: '',
  name: '',
  description: '',
  parent_id: '' as number | '',
})

function openCreateCollectionModal() {
  collectionForm.value = {
    code: '',
    name: '',
    description: '',
    parent_id: '',
  }
  showCollectionModal.value = true
}

async function submitCreateCollection() {
  if (!collectionForm.value.code.trim() || !collectionForm.value.name.trim()) {
    toast.error('Code and Name are required.')
    return
  }

  isSubmittingCollection.value = true
  try {
    await archiveApi.createCollection({
      code: collectionForm.value.code.trim().toUpperCase(),
      name: collectionForm.value.name.trim(),
      description: collectionForm.value.description.trim() || undefined,
      parent_id: collectionForm.value.parent_id ? Number(collectionForm.value.parent_id) : undefined,
    })
    toast.success(`Collection ${collectionForm.value.name} created successfully.`)
    showCollectionModal.value = false
    await loadData(pagination.value.current_page)
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to create collection.')
  } finally {
    isSubmittingCollection.value = false
  }
}

// -----------------------------------------------------------------------------
// Disposal Eligibility & Execution State & Modals
// -----------------------------------------------------------------------------
const showDisposalModal = ref(false)
const activeDisposalEntry = ref<ArchiveEntry | null>(null)
const isLoadingEligibility = ref(false)
const isExecutingDisposal = ref(false)
const eligibilityInfo = ref<{
  entry_id: number
  document_id: number
  reference_number: string
  title: string
  lifecycle: string
  eligible: boolean
  reasons: string[]
  active_holds: Array<{ id: number; code: string; reason: string; placed_at: string }>
  next_review_at: string | null
} | null>(null)

const disposalForm = ref({
  requested_action: 'destroy_digital',
  justification: '',
  external_receipt: '',
})

async function openDisposalModal(entry: ArchiveEntry) {
  activeDisposalEntry.value = entry
  eligibilityInfo.value = null
  disposalForm.value = {
    requested_action: 'destroy_digital',
    justification: '',
    external_receipt: '',
  }
  showDisposalModal.value = true
  isLoadingEligibility.value = true

  try {
    const res = await archiveApi.disposalEligibility(entry.id)
    eligibilityInfo.value = res.data.data
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to evaluate disposal eligibility.')
  } finally {
    isLoadingEligibility.value = false
  }
}

async function submitDisposal() {
  if (!activeDisposalEntry.value) return
  if (!disposalForm.value.justification.trim() || disposalForm.value.justification.trim().length < 5) {
    toast.error('A substantive statutory justification (minimum 5 characters) is required.')
    return
  }

  isExecutingDisposal.value = true
  try {
    await archiveApi.dispose(activeDisposalEntry.value.id, {
      requested_action: disposalForm.value.requested_action,
      justification: disposalForm.value.justification.trim(),
      external_receipt: disposalForm.value.requested_action === 'destroy_physical' ? disposalForm.value.external_receipt.trim() : undefined,
    } as any)
    toast.success('Disposal executed successfully. Document status updated to tombstoned.')
    showDisposalModal.value = false
    await loadData(pagination.value.current_page)
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Disposal execution failed.')
  } finally {
    isExecutingDisposal.value = false
  }
}

const isRestoring = ref(false)

async function handleRestore(entry: ArchiveEntry) {
  if (!confirm(`Are you sure you want to retrieve and restore "${entry.document?.title || 'this document'}" from the archive to active operational status?`)) {
    return
  }
  isRestoring.value = true
  try {
    await archiveApi.restore(entry.id)
    toast.success('Document successfully retrieved and restored to active operational status.')
    await loadData(pagination.value.current_page)
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to restore document from archive.')
  } finally {
    isRestoring.value = false
  }
}

async function loadData(page = 1) {
  isLoading.value = true
  try {
    const [entriesRes, collectionsRes, policiesRes, holdsRes] = await Promise.all([
      archiveApi.entries({
        page,
        per_page: pagination.value.per_page,
        collection_id: filterCollection.value || undefined,
        queue: selectedQueue.value || undefined,
      }),
      archiveApi.collections(),
      archiveApi.policies(),
      archiveApi.legalHolds(),
    ])

    entries.value = entriesRes.data.data || []
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
  <div class="space-y-5">
    <!-- Header -->
    
        <PageHeader title="Digital Archive &amp; Long-Term Preservation" subtitle="Statutory archival repository, retention schedules, legal holds, and historical record curation.">

         <template #actions>

        <BaseButton
          v-if="activeTab === 'holds'"
          variant="primary"
          size="sm"
          class="bg-rose-600 hover:bg-rose-700 text-white"
          @click="openCreateHoldModal"
        >
          <Lock class="w-4 h-4 mr-1.5" />
          Place Legal Hold
        </BaseButton>

        <BaseButton
          v-else-if="activeTab === 'collections'"
          variant="primary"
          size="sm"
          @click="openCreateCollectionModal"
        >
          <Plus class="w-4 h-4 mr-1.5" />
          New Collection
        </BaseButton>

        <BaseButton variant="outline" size="sm" :loading="isLoading" @click="loadData(1)">
          <RefreshCw class="w-4 h-4 mr-1.5" />
          Refresh
        </BaseButton>
      

         </template>

        </PageHeader>

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="bg-surface p-3.5 rounded-lg border border-hairline flex items-center gap-3">
        <div class="p-3 bg-[#fff4e6] dark:bg-[#382412] text-[#dd5b00] dark:text-[#ff9243] rounded-md">
          <FolderArchive class="w-5 h-5" />
        </div>
        <div>
          <div class="text-xl font-semibold tabular-nums text-ink">{{ pagination.total }}</div>
          <div class="text-[13px] text-ink-muted">Archived Document Records</div>
        </div>
      </div>

      <div class="bg-surface p-3.5 rounded-lg border border-hairline flex items-center gap-3">
        <div class="p-3 bg-primary-subtle text-primary rounded-md">
          <Layers class="w-5 h-5" />
        </div>
        <div>
          <div class="text-xl font-semibold tabular-nums text-ink">{{ collections.length }}</div>
          <div class="text-[13px] text-ink-muted">Archival Collections</div>
        </div>
      </div>

      <div class="bg-surface p-3.5 rounded-lg border border-hairline flex items-center gap-3">
        <div class="p-3 bg-[#fdebee] dark:bg-[#381619] text-[#e03e3e] dark:text-[#ff7373] rounded-md">
          <Scale class="w-5 h-5" />
        </div>
        <div>
          <div class="text-xl font-semibold tabular-nums text-ink">{{ legalHolds.filter(h => !h.released_at).length }}</div>
          <div class="text-[13px] text-ink-muted">Active Legal Holds</div>
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
      <div class="bg-surface rounded-lg border border-hairline p-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="px-2.5 py-1 rounded text-xs font-medium transition cursor-pointer"
            :class="selectedQueue === '' ? 'bg-primary text-white font-semibold' : 'bg-canvas-soft text-ink-muted hover:text-ink'"
            @click="selectedQueue = ''; loadData(1)"
          >
            All Archive
          </button>
          <button
            type="button"
            class="px-2.5 py-1 rounded text-xs font-medium transition cursor-pointer"
            :class="selectedQueue === 'upcoming_review' ? 'bg-primary text-white font-semibold' : 'bg-canvas-soft text-ink-muted hover:text-ink'"
            @click="selectedQueue = 'upcoming_review'; loadData(1)"
          >
            Upcoming Review (30d)
          </button>
          <button
            type="button"
            class="px-2.5 py-1 rounded text-xs font-medium transition cursor-pointer"
            :class="selectedQueue === 'expired_review' ? 'bg-rose-600 text-white font-semibold' : 'bg-canvas-soft text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/20'"
            @click="selectedQueue = 'expired_review'; loadData(1)"
          >
            Overdue Review
          </button>
          <button
            type="button"
            class="px-2.5 py-1 rounded text-xs font-medium transition cursor-pointer"
            :class="selectedQueue === 'held' ? 'bg-amber-600 text-white font-semibold' : 'bg-canvas-soft text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/20'"
            @click="selectedQueue = 'held'; loadData(1)"
          >
            Protected by Legal Hold
          </button>
        </div>

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
          <div class="space-y-0.5 max-w-xs sm:max-w-sm md:max-w-md">
            <div class="font-mono text-xs font-bold text-primary flex items-center gap-2">
              <span class="truncate">{{ item.document?.reference_number || `Document #${item.document_id}` }}</span>
              <span
                v-if="item.document?.lifecycle === 'tombstoned'"
                class="px-1.5 py-0.2 rounded text-xs font-bold bg-zinc-200 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 shrink-0"
              >
                TOMBSTONED
              </span>
            </div>
            <div class="font-semibold text-xs text-ink-secondary truncate" :title="item.document?.title">
              {{ item.document?.title || 'Document Record' }}
            </div>
            <div class="text-xs text-ink-muted flex items-center gap-1.5 mt-0.5">
              <span class="truncate max-w-[140px]">{{ item.document?.category?.name || 'Category' }}</span>
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
            <div class="text-xs text-ink-muted">
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
          <div class="flex items-center justify-end gap-1.5 flex-wrap sm:flex-nowrap">
            <BaseButton
              v-if="item.document?.lifecycle !== 'tombstoned' && (authStore.hasPermission('archive.manage_retention') || authStore.hasPermission('archive.transfer'))"
              variant="outline"
              size="sm"
              class="text-primary hover:bg-primary-subtle"
              title="Conduct Statutory Retention Review"
              @click="openReviewModal(item)"
            >
              <RotateCcw class="w-3.5 h-3.5 mr-1" />
              Review
            </BaseButton>

            <BaseButton
              v-if="item.document?.lifecycle !== 'tombstoned'"
              variant="outline"
              size="sm"
              class="text-amber-600 hover:text-amber-700 hover:bg-amber-50 dark:hover:bg-amber-950/20"
              title="Disposal Eligibility & Execution"
              @click="openDisposalModal(item)"
            >
              <Trash2 class="w-3.5 h-3.5 mr-1" />
              Disposal
            </BaseButton>

            <BaseButton
              v-if="item.document?.lifecycle === 'retired' && authStore.hasPermission('archive.restore')"
              variant="outline"
              size="sm"
              class="text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-950/20"
              title="Retrieve / Restore Document to Active Service"
              :loading="isRestoring"
              @click="handleRestore(item)"
            >
              <RotateCcw class="w-3.5 h-3.5 mr-1" />
              Restore
            </BaseButton>

            <BaseButton variant="outline" size="sm" @click="viewDocument(item.document_id)">
              <ExternalLink class="w-3.5 h-3.5 mr-1" />
              Inspect
            </BaseButton>
          </div>
        </template>
      </DataTable>
    </div>

    <!-- Tab 2: Collections Catalog -->
    <div v-else-if="activeTab === 'collections'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        v-for="c in collections"
        :key="c.id"
        class="bg-surface rounded-lg border border-hairline p-5"
      >
        <div class="flex items-start justify-between gap-2 mb-2">
          <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-[#fff4e6] dark:bg-[#382412] text-[#dd5b00] dark:text-[#ff9243] border border-[#dd5b00]/20">
            {{ c.code }}
          </span>
          <span v-if="c.parent" class="text-xs text-ink-muted">
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
    <div v-else-if="activeTab === 'holds'" class="bg-surface rounded-lg border border-hairline p-5 space-y-4">
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
                  class="text-xs font-medium px-2 py-0.5 rounded"
                  :class="hold.released_at ? 'bg-surface-muted text-ink-muted' : 'bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-200'"
                >
                  {{ hold.released_at ? 'Released' : 'Active Hold' }}
                </span>
              </div>
              <p class="text-xs text-ink-secondary mt-1 font-medium">
                {{ hold.reason }}
              </p>
              <div class="text-xs text-ink-muted mt-1 flex flex-wrap items-center gap-3">
                <span>Placed: {{ new Date(hold.placed_at).toLocaleDateString() }}</span>
                <span v-if="hold.placed_by_user">By: {{ hold.placed_by_user.name }}</span>
                <span v-if="hold.released_at" class="text-emerald-600 dark:text-emerald-400 font-semibold">
                  Released: {{ new Date(hold.released_at).toLocaleDateString() }}
                </span>
              </div>
            </div>
          </div>

          <div v-if="!hold.released_at" class="shrink-0">
            <BaseButton
              variant="outline"
              size="sm"
              class="text-rose-700 hover:text-rose-800 border-rose-300 hover:bg-rose-100 dark:border-rose-800"
              @click="handleReleaseHold(hold)"
            >
              <Unlock class="w-3.5 h-3.5 mr-1" />
              Release Hold
            </BaseButton>
          </div>
        </div>
      </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL 1: PLACE LEGAL HOLD -->
    <!-- ===================================================================== -->
    <ModalDialog
      :show="showLegalHoldModal"
      title="Place Statutory Legal Hold"
      @close="showLegalHoldModal = false"
    >
      <form @submit.prevent="submitCreateLegalHold" class="space-y-4">
        <BaseAlert variant="danger">
          <strong>Mandatory Preservation:</strong> Placing a legal hold halts all automated and requested disposition, destruction, or purging for protected documents until formally released.
        </BaseAlert>

        <div>
          <label class="block text-xs font-medium text-ink-secondary mb-1">
            Hold Tracking Code *
          </label>
          <BaseInput v-model="holdForm.code" placeholder="e.g., HOLD-AUDIT-2026-001" required />
        </div>

        <div>
          <label class="block text-xs font-medium text-ink-secondary mb-1">
            Statutory Reason / Subpoena Justification *
          </label>
          <BaseTextarea
            v-model="holdForm.reason"
            rows="3"
            placeholder="Official legal, regulatory, or audit reason necessitating preservation..."
            required
          />
        </div>

        <div>
          <label class="block text-xs font-medium text-ink-secondary mb-1">
            Target Archived Document (Optional)
          </label>
          <BaseSelect
            v-model="holdForm.document_id"
            label=""
            :options="[
              { label: 'General / All unassigned items', value: '' },
              ...entries.map(e => ({
                label: `${e.document?.reference_number || 'Doc #' + e.document_id} — ${e.document?.title || ''}`,
                value: e.document_id
              }))
            ]"
          />
        </div>

        <div class="flex items-center justify-end gap-3 pt-3">
          <BaseButton variant="outline" type="button" @click="showLegalHoldModal = false">Cancel</BaseButton>
          <BaseButton
            variant="primary"
            type="submit"
            :loading="isSubmittingHold"
            class="bg-rose-600 hover:bg-rose-700 text-white"
          >
            <Lock class="w-4 h-4 mr-1.5" />
            Enforce Legal Hold
          </BaseButton>
        </div>
      </form>
    </ModalDialog>

    <!-- ===================================================================== -->
    <!-- MODAL 2: CREATE ARCHIVAL COLLECTION -->
    <!-- ===================================================================== -->
    <ModalDialog
      :show="showCollectionModal"
      title="Create Archival Collection"
      @close="showCollectionModal = false"
    >
      <form @submit.prevent="submitCreateCollection" class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-ink-secondary mb-1">
              Collection Code *
            </label>
            <BaseInput v-model="collectionForm.code" placeholder="e.g., ENG_DEEDS" required />
          </div>
          <div>
            <label class="block text-xs font-medium text-ink-secondary mb-1">
              Parent Series (Optional)
            </label>
            <BaseSelect
              v-model="collectionForm.parent_id"
              label=""
              :options="[
                { label: 'Top-Level Master Series', value: '' },
                ...collections.map(c => ({ label: `${c.name} (${c.code})`, value: c.id }))
              ]"
            />
          </div>
        </div>

        <div>
          <label class="block text-xs font-medium text-ink-secondary mb-1">
            Collection Name *
          </label>
          <BaseInput v-model="collectionForm.name" placeholder="e.g., Engineering & Infrastructure Deeds" required />
        </div>

        <div>
          <label class="block text-xs font-medium text-ink-secondary mb-1">
            Description
          </label>
          <BaseTextarea
            v-model="collectionForm.description"
            rows="3"
            placeholder="Scope, custodial provenance, and historical significance..."
          />
        </div>

        <div class="flex items-center justify-end gap-3 pt-3">
          <BaseButton variant="outline" type="button" @click="showCollectionModal = false">Cancel</BaseButton>
          <BaseButton variant="primary" type="submit" :loading="isSubmittingCollection">
            <Plus class="w-4 h-4 mr-1.5" />
            Create Collection
          </BaseButton>
        </div>
      </form>
    </ModalDialog>

    <!-- ===================================================================== -->
    <!-- MODAL 3: DISPOSAL ELIGIBILITY & EXECUTION -->
    <!-- ===================================================================== -->
    <ModalDialog
      :show="showDisposalModal"
      title="Statutory Records Disposition &amp; Review"
      @close="showDisposalModal = false"
    >
      <div v-if="isLoadingEligibility" class="py-8 text-center text-sm text-ink-muted">
        Evaluating statutory retention policies and active legal holds...
      </div>

      <div v-else-if="eligibilityInfo" class="space-y-5">
        <!-- Target Document Summary -->
        <div class="p-3 rounded-lg bg-surface-hover border border-hairline text-xs space-y-1">
          <div class="font-bold text-ink flex items-center justify-between">
            <span>{{ eligibilityInfo.reference_number }}</span>
            <span class="font-mono text-ink-muted">Document ID #{{ eligibilityInfo.document_id }}</span>
          </div>
          <div class="text-ink-secondary">{{ eligibilityInfo.title }}</div>
        </div>

        <!-- Strict Warning / Legal Hold Block -->
        <div
          v-if="eligibilityInfo.active_holds.length > 0"
          class="p-4 rounded-lg bg-rose-50 dark:bg-rose-950/40 border-2 border-rose-500 text-rose-900 dark:text-rose-200 space-y-2"
        >
          <div class="flex items-center gap-2 font-bold text-xs   text-rose-700 dark:text-rose-300">
            <FileWarning class="w-5 h-5 text-rose-600 shrink-0" />
            <span>DISPOSITION STRICTLY BLOCKED: ACTIVE LEGAL HOLD</span>
          </div>
          <p class="text-xs">
            This document is protected under <strong>{{ eligibilityInfo.active_holds.length }} active legal hold(s)</strong>.
            Under statutory institutional compliance, no records destruction or disposal is permitted while a hold remains in effect.
          </p>
          <div class="space-y-1 pt-1">
            <div
              v-for="h in eligibilityInfo.active_holds"
              :key="h.id"
              class="p-2 rounded bg-white/60 dark:bg-black/30 font-mono text-xs"
            >
              <strong>{{ h.code }}:</strong> {{ h.reason }}
            </div>
          </div>
        </div>

        <!-- Eligibility Status Card -->
        <div
          v-else-if="eligibilityInfo.eligible"
          class="p-3.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-300 dark:border-emerald-800 text-xs flex items-center gap-2.5 text-emerald-800 dark:text-emerald-200"
        >
          <CheckCircle2 class="w-5 h-5 text-emerald-600 shrink-0" />
          <div>
            <div class="font-bold">Eligible for Statutory Disposition</div>
            <div class="text-xs text-emerald-700 dark:text-emerald-300">
              Retention period completed with zero active legal holds.
            </div>
          </div>
        </div>

        <div
          v-else
          class="p-3.5 rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-300 dark:border-amber-800 text-xs space-y-1 text-amber-800 dark:text-amber-200"
        >
          <div class="font-bold flex items-center gap-2">
            <AlertTriangle class="w-4 h-4 text-amber-600 shrink-0" />
            <span>Not Yet Eligible for Standard Disposal</span>
          </div>
          <ul class="list-disc list-inside text-xs space-y-0.5 pt-1 text-amber-700 dark:text-amber-300">
            <li v-for="(reason, idx) in eligibilityInfo.reasons" :key="idx">{{ reason }}</li>
          </ul>
        </div>

        <!-- Disposal Form (Only enabled if NO active legal holds) -->
        <form
          v-if="eligibilityInfo.active_holds.length === 0"
          @submit.prevent="submitDisposal"
          class="space-y-4 pt-2 border-t border-hairline"
        >
          <div>
            <label class="block text-xs font-medium text-ink-secondary mb-1">
              Disposition Action *
            </label>
            <BaseSelect
              v-model="disposalForm.requested_action"
              label=""
              :options="[
                { label: 'Digital Purge & Tombstone (destroy_digital)', value: 'destroy_digital' },
                { label: 'Physical Destruction & Tombstone (destroy_physical)', value: 'destroy_physical' },
                { label: 'External Archive Transfer (transfer)', value: 'transfer' },
              ]"
              required
            />
          </div>

          <div v-if="disposalForm.requested_action === 'destroy_physical'">
            <label class="block text-xs font-medium text-ink-secondary mb-1">
              Physical Destruction Certificate / Receipt Reference *
            </label>
            <BaseInput
              v-model="disposalForm.external_receipt"
              placeholder="e.g. CERT-WU-DISP-2026-XXXX or Witnessed Burn Record #"
              required
            />
          </div>

          <div>
            <label class="block text-xs font-medium text-ink-secondary mb-1">
              Statutory Justification &amp; Authority Note *
            </label>
            <BaseTextarea
              v-model="disposalForm.justification"
              rows="3"
              placeholder="Provide the formal archival justification, minuted committee approval, or regulatory reference..."
              required
            />
          </div>

          <div class="flex items-center justify-end gap-3 pt-2">
            <BaseButton variant="outline" type="button" @click="showDisposalModal = false">Cancel</BaseButton>
            <BaseButton
              variant="primary"
              type="submit"
              :loading="isExecutingDisposal"
              class="bg-amber-600 hover:bg-amber-700 text-white"
            >
              <Trash2 class="w-4 h-4 mr-1.5" />
              Execute Disposition
            </BaseButton>
          </div>
        </form>

        <div v-else class="flex justify-end pt-2">
          <BaseButton variant="outline" @click="showDisposalModal = false">Close</BaseButton>
        </div>
      </div>
    </ModalDialog>

    <!-- Retention Review Modal -->
    <ModalDialog
      :show="showReviewModal"
      title="Conduct Statutory Retention Review"
      max-width="md"
      @close="showReviewModal = false"
    >
      <form v-if="activeReviewEntry" @submit.prevent="submitReview" class="space-y-4">
        <div class="p-3 rounded-lg bg-surface-hover border border-hairline text-xs space-y-1">
          <div class="font-bold text-ink">
            {{ activeReviewEntry.document?.title || 'Archived Document' }}
          </div>
          <div class="font-mono text-ink-muted">
            {{ activeReviewEntry.document?.reference_number }} · Collection: {{ activeReviewEntry.collection?.name || 'Archive' }}
          </div>
          <div class="text-xs text-ink-secondary">
            Current Review Target: {{ activeReviewEntry.next_review_at ? new Date(activeReviewEntry.next_review_at).toLocaleDateString() : 'Permanent' }}
          </div>
        </div>

        <div>
          <label class="block text-xs font-medium text-ink-secondary mb-1">
            Review Decision *
          </label>
          <BaseSelect
            v-model="reviewForm.review_action"
            label=""
            :options="[
              { label: 'Extend Retention Period', value: 'extend' },
              { label: 'Designate Permanent Preservation', value: 'retain_permanent' },
              { label: 'Submit Disposition Request for Disposal', value: 'request_disposal' },
            ]"
            required
          />
        </div>

        <div v-if="reviewForm.review_action === 'extend'">
          <label class="block text-xs font-medium text-ink-secondary mb-1">
            Months to Extend *
          </label>
          <BaseInput
            v-model="reviewForm.months_extended"
            type="number"
            min="1"
            max="600"
            required
          />
        </div>

        <div>
          <label class="block text-xs font-medium text-ink-secondary mb-1">
            Review Justification &amp; Minutes *
          </label>
          <BaseTextarea
            v-model="reviewForm.notes"
            rows="3"
            placeholder="Document the review committee findings, regulatory mandate, or preservation justification..."
            required
          />
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
          <BaseButton variant="outline" type="button" @click="showReviewModal = false">Cancel</BaseButton>
          <BaseButton
            variant="primary"
            type="submit"
            :loading="isSubmittingReview"
          >
            Record Review
          </BaseButton>
        </div>
      </form>
    </ModalDialog>
  </div>
</template>


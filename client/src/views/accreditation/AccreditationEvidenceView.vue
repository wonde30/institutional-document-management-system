<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { institutionalApi, documentsApi } from '@/api/client'
import { downloadBlob } from '@/utils/formatters'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import ModalDialog from '@/components/ModalDialog.vue'
import LoadingSkeleton from '@/components/ui/LoadingSkeleton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import PageHeader from '@/components/PageHeader.vue'
import BaseAlert from '@/components/ui/BaseAlert.vue'
import type {
 AccreditationCycle,
 AccreditationStandard,
 AccreditationEvidence,
 AccreditationCycleSummary,
} from '@/types'
import {
 ClipboardCheck,
 Plus,
 RefreshCw,
 Search,
 Info,
 CheckCircle2,
 AlertCircle,
 FileText,
 Download,
 Award,
 Layers,
 HelpCircle
} from 'lucide-vue-next'

const authStore = useAuthStore()
const toast = useToast()

const loading = ref(true)
const error = ref<string | null>(null)
const loadingSummary = ref(false)
const isSubmitting = ref(false)

const cycles = ref<AccreditationCycle[]>([])
const selectedCycleId = ref<number | null>(null)
const standards = ref<AccreditationStandard[]>([])
const evidenceList = ref<AccreditationEvidence[]>([])
const summary = ref<AccreditationCycleSummary | null>(null)
const repoDocuments = ref<any[]>([])
const loadingDocs = ref(false)

const searchQuery = ref('')
const filterStandard = ref<number | ''>('')
const filterStatus = ref('')
const showAttachModal = ref(false)
const showReviewModal = ref(false)
const selectedEvidenceForReview = ref<AccreditationEvidence | null>(null)

const attachForm = ref({
 cycle_id: 0,
 standard_id: 0,
 document_version_id: '' as unknown as number,
 note: '',
})

const reviewForm = ref({
 evidence_id: 0,
 decision: 'accepted' as 'accepted' | 'rejected' | 'needs_revision',
 comment: '',
})

async function loadCycles() {
 try {
 const res = await institutionalApi.accreditationCycles()
 if (res.data.success && res.data.data) {
 cycles.value = res.data.data
 if (cycles.value.length > 0 && !selectedCycleId.value) {
 selectedCycleId.value = cycles.value[0].id
 }
 }
 } catch (err: any) {
 toast.error(err.response?.data?.message || 'Failed to load cycles')
   error.value = err.response?.data?.message || 'Failed to load data'
 }
}

async function loadStandards() {
 if (!selectedCycleId.value) {
 standards.value = []
 return
 }
 try {
 const res = await institutionalApi.accreditationStandards({ cycle_id: selectedCycleId.value })
 if (res.data.success && res.data.data) {
 standards.value = res.data.data
 }
 } catch {
 // Graceful fallback
 }
}

async function loadSummary() {
 if (!selectedCycleId.value) {
 summary.value = null
 return
 }
 loadingSummary.value = true
 try {
 const res = await institutionalApi.accreditationCycleSummary(selectedCycleId.value)
 if (res.data.success && res.data.data) {
 summary.value = res.data.data
 }
 } catch {
 // Non-fatal
 } finally {
 loadingSummary.value = false
 }
}

async function loadEvidence() {
 if (!selectedCycleId.value) {
 loading.value = false
 evidenceList.value = []
 return
 }
 loading.value = true
 try {
 const res = await institutionalApi.accreditationEvidence({ cycle_id: selectedCycleId.value })
 if (res.data.success && res.data.data) {
 evidenceList.value = res.data.data
 }
 } catch (err: any) {
 toast.error('Error', err.response?.data?.message || 'Failed to load accreditation evidence.')
 } finally {
 loading.value = false
 }
}

async function refreshAll() {
 error.value = null
 loading.value = true
 try {
 await loadCycles()
 if (selectedCycleId.value) {
 await Promise.all([loadStandards(), loadSummary(), loadEvidence()])
 } else {
 standards.value = []
 summary.value = null
 evidenceList.value = []
 }
 } finally {
 loading.value = false
 }
}

watch(selectedCycleId, async (newVal) => {
 if (newVal) {
 attachForm.value.cycle_id = newVal
 await Promise.all([loadStandards(), loadSummary(), loadEvidence()])
 }
})

const filteredList = computed(() => {
 return evidenceList.value.filter((item) => {
 const matchesSearch =
 !searchQuery.value ||
 item.standard?.code?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
 item.standard?.title?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
 item.document_version?.document?.title?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
 item.document_version?.document?.reference_number?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
 item.note?.toLowerCase().includes(searchQuery.value.toLowerCase())

 const matchesStandard =
 !filterStandard.value || item.standard_id === Number(filterStandard.value)

 let itemDecision = item.latest_review ? item.latest_review.decision : 'pending'
 const matchesStatus = !filterStatus.value || itemDecision === filterStatus.value

 return matchesSearch && matchesStandard && matchesStatus
 })
})

async function loadRepoDocuments() {
  if (repoDocuments.value.length > 0) return
  loadingDocs.value = true
  try {
    const res = await documentsApi.list({ per_page: 50 })
    repoDocuments.value = (res.data as any).data || []
  } catch {
    // Non-fatal
  } finally {
    loadingDocs.value = false
  }
}

function resolveDocumentVersionId(doc: any): number | null {
  const id = doc?.current_version?.id ?? doc?.latest_version?.id ?? doc?.versions?.[0]?.id ?? null
  return typeof id === 'number' && id > 0 ? id : null
}

function openAttachModal() {
  loadRepoDocuments()
 if (!selectedCycleId.value) {
 toast.error('Cycle Required', 'Please select or create an active accreditation cycle first.')
 return
 }
 attachForm.value = {
 cycle_id: selectedCycleId.value,
 standard_id: standards.value.length > 0 ? standards.value[0].id : 0,
 document_version_id: '' as unknown as number,
 note: '',
 }
 showAttachModal.value = true
}

async function handleAttachSubmit() {
 if (!attachForm.value.standard_id) {
 toast.error('Validation Error', 'Please select an accreditation standard.')
 return
 }
 const versionId = Number(attachForm.value.document_version_id)
 if (!Number.isInteger(versionId) || versionId <= 0) {
 toast.error('Validation Error', 'Please select a document with an active Document Version ID or enter a valid Document Version ID.')
 return
 }

 isSubmitting.value = true
 try {
 const res = await institutionalApi.submitAccreditationEvidence({
 cycle_id: attachForm.value.cycle_id,
 standard_id: attachForm.value.standard_id,
 document_version_id: Number(attachForm.value.document_version_id),
 note: attachForm.value.note || undefined,
 })

 if (res.data.success) {
 toast.success('Attached', 'Document version mapped to accreditation standard successfully.')
 showAttachModal.value = false
 await Promise.all([loadSummary(), loadEvidence()])
 }
 } catch (err: any) {
 toast.error('Submission Failed', err.response?.data?.message || 'Failed to submit evidence.')
 } finally {
 isSubmitting.value = false
 }
}

function openReviewModal(item: AccreditationEvidence) {
 selectedEvidenceForReview.value = item
 reviewForm.value = {
 evidence_id: item.id,
 decision: 'accepted',
 comment: '',
 }
 showReviewModal.value = true
}

async function handleReviewSubmit() {
 if (!selectedEvidenceForReview.value) return

 isSubmitting.value = true
 try {
 const res = await institutionalApi.reviewAccreditationEvidence(
 reviewForm.value.evidence_id,
 {
 decision: reviewForm.value.decision,
 comment: reviewForm.value.comment || undefined,
 }
 )

 if (res.data.success) {
 toast.success('Review Recorded', 'Audit review decision recorded successfully.')
 showReviewModal.value = false
 await Promise.all([loadSummary(), loadEvidence()])
 }
 } catch (err: any) {
 toast.error('Review Error', err.response?.data?.message || 'Failed to record review.')
 } finally {
 isSubmitting.value = false
 }
}

async function handleExportDossier() {
 if (!selectedCycleId.value) return
 try {
 const res = await institutionalApi.exportAccreditationDossier(selectedCycleId.value)
 if (res.data.success && res.data.data) {
  const blob = new Blob([JSON.stringify(res.data.data, null, 2)], { type: 'application/json' })
  downloadBlob(blob, `WU_HERQA_Dossier_Cycle_${selectedCycleId.value}.json`)
  toast.success('Export Ready', 'Institutional compliance dossier downloaded successfully.')
 }
 } catch (err: any) {
 toast.error('Export Error', err.response?.data?.message || 'Failed to export dossier.')
 }
}

onMounted(() => {
 refreshAll()
})
</script>

<template>
 <div class="space-y-6">
 <PageHeader title="Accreditation & Quality Assurance" subtitle="Map frozen document versions to official national HERQA quality standards with traceable audit reviews.">
   <template #actions>
    <select
     v-model="selectedCycleId"
     class="text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-3 py-1.5 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
    >
     <option v-for="c in cycles" :key="c.id" :value="c.id">
      {{ c.name }} ({{ c.starts_on }} - {{ c.ends_on || 'Present' }})
     </option>
    </select>
    <BaseButton variant="outline" size="sm" :loading="loading" @click="refreshAll">
     <RefreshCw class="w-4 h-4 mr-1.5" />
     Refresh
    </BaseButton>
    <BaseButton
     v-if="authStore.hasPermission('accreditation.export')"
     variant="outline"
     size="sm"
     @click="handleExportDossier"
    >
     <Download class="w-4 h-4 mr-1.5" />
     Export Dossier
    </BaseButton>
    <BaseButton
     v-if="authStore.hasPermission('accreditation.submit_evidence')"
     variant="primary"
     size="sm"
     @click="openAttachModal"
    >
     <Plus class="w-4 h-4 mr-1.5" />
     Attach evidence
    </BaseButton>
   </template>
  </PageHeader>

   <BaseAlert v-if="error" variant="danger">
    {{ error }}
    <template #actions>
     <BaseButton size="sm" variant="outline" @click="refreshAll">
      <RefreshCw class="w-4 h-4 mr-1.5" />
      Retry
     </BaseButton>
    </template>
   </BaseAlert>

 <!-- Panel & Toolbar -->
 <section class="bg-surface rounded-lg border border-hairline overflow-hidden">
 <div class="p-3 sm:p-4 border-b border-hairline flex flex-col sm:flex-row items-center justify-between gap-3">
 <div class="relative w-full sm:w-80">
 <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
 <input
 v-model="searchQuery"
 type="search"
 placeholder="Search by code, title, reference, or note..."
 class="w-full pl-9 pr-3 py-1.5 text-xs rounded-md border border-hairline bg-surface text-ink-secondary focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 />
 </div>

 <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
 <!-- Standard Filter -->
 <select
 v-model="filterStandard"
 class="text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-3 py-1.5 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 >
 <option value="">All Standards</option>
 <option v-for="s in standards" :key="s.id" :value="s.id">
 {{ s.code }} - {{ s.title.slice(0, 30) }}...
 </option>
 </select>

 <!-- Review Status Filter -->
 <select
 v-model="filterStatus"
 class="text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-3 py-1.5 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 >
 <option value="">All Review Statuses</option>
 <option value="accepted">Accepted</option>
 <option value="needs_revision">Needs Revision</option>
 <option value="pending">Pending Review</option>
 <option value="rejected">Rejected</option>
 </select>
 </div>
 </div>

 <!-- Loading Skeleton -->
 <LoadingSkeleton v-if="loading" :count="4" class="my-4 p-4" />

 <!-- Table Directory -->
 <div v-else class="overflow-x-auto">
 <table class="w-full text-left text-xs border-collapse">
 <thead class="bg-canvas-soft text-ink-muted font-semibold border-b border-hairline">
 <tr>
 <th class="py-3 px-4">Standard Criterion</th>
 <th class="py-3 px-4">Document Reference & Title</th>
 <th class="py-3 px-4">Exact Revision</th>
 <th class="py-3 px-4">Responsible Unit</th>
 <th class="py-3 px-4">Audit Evaluation</th>
 <th class="py-3 px-4 text-right">Actions</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-hairline">
 <tr
 v-for="item in filteredList"
 :key="item.id"
 class="hover:bg-surface-hover transition-colors"
 >
 <td class="py-3 px-4">
 <div class="font-semibold text-ink-secondary">
 {{ item.standard?.code }}
 </div>
 <div class="text-xs text-ink-muted line-clamp-1">
 {{ item.standard?.title }}
 </div>
 </td>

 <td class="py-3 px-4">
 <div class="font-medium text-ink-secondary">
 {{ item.document_version?.document?.title || 'Document Version #' + item.document_version_id }}
 </div>
 <small class="block text-xs text-primary font-mono mt-0.5">
 Ref: {{ item.document_version?.document?.reference_number || 'N/A' }}
 </small>
 </td>

 <td class="py-3 px-4 font-mono text-ink-secondary">
 v{{ item.document_version?.version_number || 1 }}
 </td>

 <td class="py-3 px-4 text-ink-muted">
 {{ item.document_version?.document?.owner_unit?.name || item.cycle?.unit?.name || 'Informatics Department' }}
 </td>

 <td class="py-3 px-4">
 <div v-if="item.latest_review">
 <span
 class="px-2 py-0.5 rounded text-xs font-semibold"
 :class="{
 'bg-[#e8f7ec] text-[#1aae39] dark:bg-[#183321] dark:text-[#42cf63] border border-[#1aae39]/20': item.latest_review.decision === 'accepted',
 'bg-[#fff4e6] text-[#dd5b00] dark:bg-[#382412] dark:text-[#ff9243] border border-[#dd5b00]/20': item.latest_review.decision === 'needs_revision',
 'bg-[#fdebee] text-[#e03e3e] dark:bg-[#381619] dark:text-[#ff7373] border border-[#e03e3e]/20': item.latest_review.decision === 'rejected',
 }"
 >
 {{ item.latest_review.decision.replace('_', ' ') }}
 </span>
 <p v-if="item.latest_review.comment" class="text-xs text-ink-muted mt-1 italic line-clamp-1">
 "{{ item.latest_review.comment }}"
 </p>
 </div>
 <div v-else>
 <span class="px-2 py-0.5 rounded text-xs font-medium bg-surface-muted text-ink-muted">
 PENDING REVIEW
 </span>
 </div>
 </td>

 <td class="py-3 px-4 text-right">
 <BaseButton
 v-if="authStore.hasPermission('accreditation.review_evidence')"
 variant="outline"
 size="sm"
 @click="openReviewModal(item)"
 >
 Evaluate
 </BaseButton>
 </td>
 </tr>

 <tr v-if="filteredList.length === 0">
 <td colspan="6" class="py-8 text-center text-xs text-ink-muted">
 No accreditation evidence records match the selected filters.
 </td>
 </tr>
 </tbody>
 </table>
 </div>

 <div class="p-3 border-t border-hairline text-xs text-ink-muted flex justify-between">
 <span>{{ filteredList.length }} verified evidence mappings</span>
 <span>Standard Framework: HERQA 10 Standards</span>
 </div>
 </section>

 <!-- Attach Evidence Modal -->
 <ModalDialog
 :open="showAttachModal"
 title="Attach Accreditation Evidence to HERQA Standard"
 max-width="md"
 @close="showAttachModal = false"
 >
 <div class="space-y-4">
 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 HERQA Quality Standard *
 </label>
 <select
 v-model="attachForm.standard_id"
 class="w-full text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 >
 <option v-for="s in standards" :key="s.id" :value="s.id">
 {{ s.code }} - {{ s.title }}
 </option>
 </select>
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Select Document from Repository
 </label>
 <select
 class="w-full text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 mb-2"
 @change="(e: Event) => { const v = (e.target as HTMLSelectElement).value; attachForm.document_version_id = v ? Number(v) : ('' as unknown as number) }"
 >
 <option value="">-- Choose from repository documents or enter version ID below --</option>
 <option
 v-for="doc in repoDocuments"
 :key="doc.id"
 :value="resolveDocumentVersionId(doc) ?? ''"
 :disabled="!resolveDocumentVersionId(doc)"
 >
 {{ doc.reference_number }} — {{ doc.title }} ({{ resolveDocumentVersionId(doc) ? `Ver ID: ${resolveDocumentVersionId(doc)}` : 'No version available' }})
 </option>
 </select>
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Document Version ID *
 </label>
 <BaseInput
 v-model="attachForm.document_version_id"
 type="number"
 placeholder="e.g. 1"
 />
 <p class="text-xs text-ink-muted mt-1">
 Selected or specified numerical ID of the finalized document version in the institutional repository.
 </p>
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Evidence Context & Compliance Notes
 </label>
 <BaseTextarea
 v-model="attachForm.note"
 rows="3"
 placeholder="Describe how this exact document version satisfies the HERQA benchmark criteria..."
 />
 </div>
 </div>

 <template #footer>
 <div class="flex items-center justify-end gap-2">
 <BaseButton variant="outline" size="sm" @click="showAttachModal = false">
 Cancel
 </BaseButton>
 <BaseButton
 variant="primary"
 size="sm"
 :loading="isSubmitting"
 @click="handleAttachSubmit"
 >
 Attach Evidence
 </BaseButton>
 </div>
 </template>
 </ModalDialog>

 <!-- Review Evidence Modal -->
 <ModalDialog
 :open="showReviewModal"
 title="Audit Evaluation of Accreditation Evidence"
 max-width="md"
 @close="showReviewModal = false"
 >
 <div v-if="selectedEvidenceForReview" class="space-y-4">
 <div class="p-3 bg-canvas-soft rounded-md border border-hairline text-xs">
 <div class="font-medium text-ink-secondary">
 {{ selectedEvidenceForReview.standard?.code }}: {{ selectedEvidenceForReview.standard?.title }}
 </div>
 <div class="text-ink-muted mt-1">
 Document: {{ selectedEvidenceForReview.document_version?.document?.title || 'Version #' + selectedEvidenceForReview.document_version_id }}
 </div>
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Audit Decision *
 </label>
 <div class="grid grid-cols-3 gap-2">
 <label
 class="flex items-center justify-center p-2 rounded-md border text-xs font-medium cursor-pointer transition-colors"
 :class="reviewForm.decision === 'accepted' ? 'border-[#1aae39] bg-[#e8f7ec] text-[#1aae39] dark:bg-[#183321] dark:text-[#42cf63]' : 'border-hairline bg-surface text-ink-muted'"
 >
 <input v-model="reviewForm.decision" type="radio" value="accepted" class="sr-only" />
 Accepted
 </label>

 <label
 class="flex items-center justify-center p-2 rounded-md border text-xs font-medium cursor-pointer transition-colors"
 :class="reviewForm.decision === 'needs_revision' ? 'border-[#dd5b00] bg-[#fff4e6] text-[#dd5b00] dark:bg-[#382412] dark:text-[#ff9243]' : 'border-hairline bg-surface text-ink-muted'"
 >
 <input v-model="reviewForm.decision" type="radio" value="needs_revision" class="sr-only" />
 Needs Revision
 </label>

 <label
 class="flex items-center justify-center p-2 rounded-md border text-xs font-medium cursor-pointer transition-colors"
 :class="reviewForm.decision === 'rejected' ? 'border-[#e03e3e] bg-[#fdebee] text-[#e03e3e] dark:bg-[#381619] dark:text-[#ff7373]' : 'border-hairline bg-surface text-ink-muted'"
 >
 <input v-model="reviewForm.decision" type="radio" value="rejected" class="sr-only" />
 Rejected
 </label>
 </div>
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Auditor Findings & Review Comments
 </label>
 <BaseTextarea
 v-model="reviewForm.comment"
 rows="3"
 placeholder="Record audit verification notes or specify required amendments..."
 />
 </div>
 </div>

 <template #footer>
 <div class="flex items-center justify-end gap-2">
 <BaseButton variant="outline" size="sm" @click="showReviewModal = false">
 Cancel
 </BaseButton>
 <BaseButton
 variant="primary"
 size="sm"
 :loading="isSubmitting"
 @click="handleReviewSubmit"
 >
 Record Audit Review
 </BaseButton>
 </div>
 </template>
 </ModalDialog>
 </div>
</template>


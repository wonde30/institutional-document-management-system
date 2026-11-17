<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { institutionalApi, organizationUnitsApi } from '@/api/client'
import type { OrganizationUnit, ResearchRecord } from '@/types'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import ModalDialog from '@/components/ModalDialog.vue'
import LoadingSkeleton from '@/components/ui/LoadingSkeleton.vue'
import {
 BookOpen,
 Plus,
 RefreshCw,
 Search,
 Info,
 CheckCircle2,
 Globe,
 Lock,
 Layers,
 FileText,
 AlertCircle,
 Eye,
 Upload,
} from 'lucide-vue-next'

const authStore = useAuthStore()
const toast = useToast()
const loading = ref(true)
const isSubmitting = ref(false)
const units = ref<OrganizationUnit[]>([])

const researchList = ref<ResearchRecord[]>([])

/**
 * Whether a research output is published in the repository.
 *
 * The repository publishes a record by linking it to a publication, so the
 * publication state is derived from that link rather than from a separate
 * status column.
 */
function isPublished(record: ResearchRecord): boolean {
 return Array.isArray(record.publications) && record.publications.length > 0
}

const searchQuery = ref('')
const filterStatus = ref('')
const showModal = ref(false)
const showDetailModal = ref(false)
const showVersionModal = ref(false)
const selectedResearch = ref<ResearchRecord | null>(null)

// Create Form (F-RESEARCH)
const form = ref({
 title: '',
 office_id: '' as number | '',
 completion_date: new Date().toISOString().split('T')[0],
 external_reference: '',
 authors: '',
 notes: '',
})

// Revision Form
const versionForm = ref({
 change_note: '',
})
const isSubmittingVersion = ref(false)

async function loadData() {
 loading.value = true
 try {
 const [resRes, unitsRes] = await Promise.all([
 institutionalApi.research(),
 organizationUnitsApi.list({ per_page: 100 }),
 ])

 researchList.value = resRes.data.data || []
 units.value = unitsRes.data.data || []
 if (!form.value.office_id && units.value.length > 0) {
  form.value.office_id = authStore.activeUnitId || authStore.primaryUnit?.unit_id || units.value[0].id
 }
 } catch (err: any) {
 toast.error(err.response?.data?.message || 'Failed to retrieve research outputs.')
 } finally {
 loading.value = false
 }
}

const filteredList = computed(() => {
 return researchList.value.filter((item) => {
 const title = item.document?.title || ''
 const ref = item.document?.reference_number || ''
 const matchesSearch =
 !searchQuery.value ||
 title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
 ref.toLowerCase().includes(searchQuery.value.toLowerCase())

 const matchesStatus =
 !filterStatus.value ||
 (filterStatus.value === 'published' ? isPublished(item) : !isPublished(item))
 return matchesSearch && matchesStatus
 })
})

function inspectResearch(item: ResearchRecord) {
 selectedResearch.value = item
 showDetailModal.value = true
}

function openVersionModal(item: ResearchRecord) {
 selectedResearch.value = item
 versionForm.value = {
 change_note: '',
 }
 showVersionModal.value = true
}

async function submitRevision() {
 if (!selectedResearch.value) return
 if (!versionForm.value.change_note.trim()) {
 toast.error('Change note is required.')
 return
 }
 isSubmittingVersion.value = true
 try {
 await institutionalApi.uploadResearchVersion(selectedResearch.value.id, {
 change_note: versionForm.value.change_note.trim(),
 })
 toast.success('New revision uploaded successfully.')
 showVersionModal.value = false
 await loadData()
 } catch (err: any) {
 toast.error(err.response?.data?.message || 'Failed to upload revision.')
 } finally {
 isSubmittingVersion.value = false
 }
}

async function togglePublication(item: ResearchRecord) {
 const newAction = isPublished(item) ? 'withdraw' : 'publish'
 try {
 await institutionalApi.togglePublication(item.id, newAction)
 toast.success(`Research output ${newAction === 'publish' ? 'published to repository' : 'withdrawn'}.`)
 await loadData()
 } catch (err: any) {
 toast.error(err.response?.data?.message || 'Action denied by departmental scope.')
 }
}

async function handleCreate() {
 if (!form.value.title.trim()) {
 toast.error('Research title is required.')
 return
 }

 isSubmitting.value = true
 try {
 await institutionalApi.storeResearch({
 title: form.value.title.trim(),
 office_id: Number(form.value.office_id),
 completion_date: form.value.completion_date,
 external_reference: form.value.external_reference || null,
 notes: form.value.notes || null,
 })
 toast.success('Finalized research output added to repository catalog.')
 showModal.value = false
 form.value.title = ''
 form.value.external_reference = ''
 await loadData()
 } catch (err: any) {
 toast.error(err.response?.data?.message || 'Failed to record finalized research.')
 } finally {
 isSubmitting.value = false
 }
}

onMounted(() => {
 loadData()
})
</script>

<template>
 <div class="space-y-6">
 <!-- Header -->
 <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 border-b border-hairline pb-4">
 <div>
 <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-ink flex items-center gap-2.5">
 <BookOpen class="w-5 h-5 sm:w-6 sm:h-6 text-primary shrink-0" />
 <span>Finalized Faculty Research Repository</span>
 </h1>
 <p class="text-xs text-ink-muted mt-0.5">
 Departmental publication of research completed and selected through external university processes.
 </p>
 </div>

 <div class="flex items-center gap-2.5">
 <BaseButton variant="outline" size="sm" :loading="loading" @click="loadData">
 <RefreshCw class="w-4 h-4 mr-1.5" />
 Refresh
 </BaseButton>

 <BaseButton
 v-if="authStore.hasPermission('research.create') || authStore.hasPermission('documents.create')"
 variant="primary"
 size="sm"
 @click="showModal = true"
 >
 <Plus class="w-4 h-4 mr-1.5" />
 Register Finalized Research
 </BaseButton>
 </div>
 </div>

 <!-- Institutional Policy Notice -->
 <div class="p-3.5 rounded-lg bg-canvas-soft border border-hairline text-xs text-ink-secondary flex items-start gap-2.5">
 <Info class="w-4 h-4 text-primary shrink-0 mt-0.5" />
 <p class="leading-relaxed">
 <strong>Institutional Boundary:</strong> Proposal submission, reviewer scoring, screening, and research approvals occur strictly outside this module. Authorized departments record finalized monographs and manage repository publication under department-scoped authority (<code class="font-mono text-xs">research.publish</code>).
 </p>
 </div>

 <!-- Panel & Toolbar -->
 <section class="bg-surface rounded-lg border border-hairline overflow-hidden">
 <div class="p-3 sm:p-4 border-b border-hairline flex flex-col sm:flex-row items-center justify-between gap-3">
 <div class="relative w-full sm:w-80">
 <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
 <input
 v-model="searchQuery"
 type="search"
 placeholder="Search by title or reference number..."
 class="w-full pl-9 pr-3 py-1.5 text-xs rounded-md border border-hairline bg-surface text-ink-secondary focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 />
 </div>

 <div class="flex items-center gap-2 w-full sm:w-auto">
 <select
 v-model="filterStatus"
 class="text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-3 py-1.5 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 >
 <option value="">All publication states</option>
 <option value="published">Published in Repository</option>
 <option value="ready_to_publish">Ready to Publish</option>
 </select>
 </div>
 </div>

 <!-- Loading Skeleton -->
 <LoadingSkeleton v-if="loading" :count="4" class="p-4" />

 <!-- Table Directory -->
 <div v-else class="overflow-x-auto">
 <table class="w-full text-left text-xs border-collapse">
 <thead class="bg-canvas-soft text-ink-muted font-semibold border-b border-hairline">
 <tr>
 <th class="py-3 px-4">Research / Reference</th>
 <th class="py-3 px-4">Owning Department</th>
 <th class="py-3 px-4">Completion Date</th>
 <th class="py-3 px-4">Repository Status</th>
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
 <div class="font-medium text-ink-secondary">
 {{ item.document?.title }}
 </div>
 <small class="block text-xs text-primary font-mono mt-0.5">
 {{ item.document?.reference_number }}
 <span v-if="item.external_selection_reference" class="text-ink-muted">· Selection Ref: {{ item.external_selection_reference }}</span>
 </small>
 </td>
 <td class="py-3 px-4 text-ink-muted">
 {{ item.document?.ownerUnit?.name || '—' }}
 </td>
 <td class="py-3 px-4 text-ink-muted whitespace-nowrap">
 {{ item.completed_on ? new Date(item.completed_on).toLocaleDateString() : '—' }}
 </td>
 <td class="py-3 px-4">
 <span
 class="px-2 py-0.5 rounded text-xs font-semibold"
 :class="{
 'bg-[#e8f7ec] text-[#1aae39] dark:bg-[#183321] dark:text-[#42cf63]': isPublished(item),
 'bg-[#fff4e6] text-[#dd5b00] dark:bg-[#382412] dark:text-[#ff9243]': !isPublished(item),
 }"
 >
 {{ isPublished(item) ? 'Published' : 'Ready to publish' }}
 </span>
 </td>
 <td class="py-3 px-4 text-right">
 <div class="flex items-center justify-end gap-2">
 <button
 type="button"
 class="text-xs font-medium text-ink-muted hover:text-ink-secondary "
 @click="inspectResearch(item)"
 title="Inspect details and distinction"
 >
 Details
 </button>

 <button
 type="button"
 class="text-xs font-medium text-primary hover:underline"
 @click="openVersionModal(item)"
 title="Upload revision"
 >
 Add Revision
 </button>

 <button
 type="button"
 class="text-xs font-medium"
 :class="isPublished(item) ? 'text-amber-700 hover:underline' : 'text-primary hover:underline'"
 @click="togglePublication(item)"
 >
 {{ isPublished(item) ? 'Withdraw' : 'Publish' }}
 </button>
 </div>
 </td>
 </tr>

 <tr v-if="filteredList.length === 0">
 <td colspan="5" class="py-8 text-center text-xs text-ink-muted">
 No finalized research records match these filters within your authorized scope.
 </td>
 </tr>
 </tbody>
 </table>
 </div>

 <div class="p-3 border-t border-hairline text-xs text-ink-muted flex justify-between">
 <span>{{ filteredList.length }} research outputs</span>
 <span>Scoped Department Access</span>
 </div>
 </section>

 <!-- F-RESEARCH Modal -->
 <ModalDialog
 :open="showModal"
 title="Register Finalized Research Monograph"
 max-width="lg"
 @close="showModal = false"
 >
 <div class="space-y-4 text-xs">
 <div>
 <label class="block font-semibold text-ink-secondary mb-1">
 Finalized Research Title *
 </label>
 <BaseInput
 v-model="form.title"
 placeholder="e.g. Distributed Optical Sensor Networks for Agricultural Monitoring"
 maxlength="200"
 />
 </div>

 <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
 <div>
 <label class="block font-semibold text-ink-secondary mb-1">
 Responsible Department *
 </label>
 <select
 v-model="form.office_id"
 class="w-full text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 >
 <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
 </select>
 </div>

 <div>
 <label class="block font-semibold text-ink-secondary mb-1">
 Completion Date *
 </label>
 <input
 v-model="form.completion_date"
 type="date"
 class="w-full text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 />
 </div>
 </div>

 <div>
 <label class="block font-semibold text-ink-secondary mb-1">
 External University Approval / Selection Reference
 </label>
 <BaseInput
 v-model="form.external_reference"
 placeholder="e.g. WU/RC/MIN/2026/04"
 />
 </div>

 <div class="p-3 rounded-md bg-canvas-soft border border-hairline text-ink-muted">
 Note: Title screening, evaluation scoring, and research selection forms are intentionally excluded. Only completed research approved by the external research committee is registered.
 </div>
 </div>

 <template #footer>
 <div class="flex items-center justify-end gap-2">
 <BaseButton variant="outline" size="sm" @click="showModal = false">
 Cancel
 </BaseButton>
 <BaseButton
 variant="primary"
 size="sm"
 :loading="isSubmitting"
 @click="handleCreate"
 >
 Register Record
 </BaseButton>
 </div>
 </template>
 </ModalDialog>

 <!-- Research Detail & Status Distinction Modal -->
 <ModalDialog
 :open="showDetailModal"
 title="Research Monograph Facts & Distinction"
 max-width="lg"
 @close="showDetailModal = false"
 >
 <div v-if="selectedResearch" class="space-y-4 text-xs">
 <div>
 <span class="block text-ink-muted font-mono text-xs">
 {{ selectedResearch.document?.reference_number }}
 </span>
 <h2 class="text-sm font-semibold text-ink-secondary mt-0.5">
 {{ selectedResearch.document?.title }}
 </h2>
 </div>

 <!-- Approval vs Publication Distinction Card -->
 <div class="p-3.5 rounded-lg bg-canvas-soft border border-hairline space-y-2">
 <div class="font-semibold text-ink-secondary flex items-center gap-2">
 <CheckCircle2 class="w-4 h-4 text-primary" />
 Status Distinction Architecture
 </div>
 <div class="grid grid-cols-2 gap-3 pt-1">
 <div class="p-2.5 rounded-md bg-surface border border-hairline">
 <span class="block text-xs  font-bold text-ink-muted">1. University Evaluation</span>
 <span class="font-semibold text-emerald-600 dark:text-emerald-400 mt-0.5 block">Approved Externally</span>
 <span class="text-xs text-ink-muted block mt-0.5">
 Ref: {{ selectedResearch.external_selection_reference || '—' }}
 </span>
 </div>

 <div class="p-2.5 rounded-md bg-surface border border-hairline">
 <span class="block text-xs  font-bold text-ink-muted">2. Repository State</span>
 <span class="font-semibold capitalize text-primary mt-0.5 block">
 {{ isPublished(selectedResearch) ? 'Published' : 'Ready to publish' }}
 </span>
 <span class="text-xs text-ink-muted block mt-0.5">
 Scoped to {{ selectedResearch.document?.ownerUnit?.name || '—' }}
 </span>
 </div>
 </div>
 </div>

 <div class="grid grid-cols-2 gap-4 text-xs">
 <div>
 <span class="block text-ink-muted">Owning Department</span>
 <span class="font-medium text-ink-secondary">{{ selectedResearch.document?.ownerUnit?.name || '—' }}</span>
 </div>
 <div>
 <span class="block text-ink-muted">Completion Date</span>
 <span class="font-medium text-ink-secondary">{{ selectedResearch.completed_on || '—' }}</span>
 </div>
 </div>
 </div>

 <template #footer>
 <div class="flex justify-end">
 <BaseButton variant="outline" size="sm" @click="showDetailModal = false">Close</BaseButton>
 </div>
 </template>
 </ModalDialog>

 <!-- Revision Upload Modal -->
 <ModalDialog
 :open="showVersionModal"
 title="Upload Research Monograph Revision"
 @close="showVersionModal = false"
 >
 <form @submit.prevent="submitRevision" class="space-y-4 text-xs">
 <div>
 <label class="block font-semibold text-ink-secondary mb-1">
 Revision Change Note *
 </label>
 <BaseInput
 v-model="versionForm.change_note"
 placeholder="e.g. Revised methodology section per external peer-review findings"
 required
 />
 </div>

 <div class="pt-2 flex justify-end gap-2">
 <BaseButton variant="outline" size="sm" type="button" @click="showVersionModal = false">Cancel</BaseButton>
 <BaseButton
 variant="primary"
 size="sm"
 type="submit"
 :loading="isSubmittingVersion"
 >
 Upload Revision
 </BaseButton>
 </div>
 </form>
 </ModalDialog>
 </div>
</template>

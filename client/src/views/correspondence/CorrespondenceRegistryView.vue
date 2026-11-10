<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { correspondenceApi, organizationUnitsApi, usersApi } from '@/api/client'
import { useMetadataStore } from '@/stores/metadata'
import type { Correspondence, OrganizationUnit, User, PaginationMeta } from '@/types'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import DataTable, { type TableColumn } from '@/components/data-display/DataTable.vue'
import SearchFilterBar from '@/components/data-display/SearchFilterBar.vue'
import Pagination from '@/components/Pagination.vue'
import PriorityBadge from '@/components/PriorityBadge.vue'
import ModalDialog from '@/components/ModalDialog.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import { formatDate } from '@/utils/formatters'
import {
 Mail,
 Plus,
 Send,
 UserCheck,
 Building2,
 Inbox,
 TrendingUp,
 FileCheck2,
 CheckCircle2,
 AlertCircle,
 Reply,
 CheckCheck,
} from 'lucide-vue-next'

const route = useRoute()
const authStore = useAuthStore()
const metaStore = useMetadataStore()
const toast = useToast()

const correspondences = ref<Correspondence[]>([])
const units = ref<OrganizationUnit[]>([])
const users = ref<User[]>([])
const isLoading = ref(false)
const selectedTab = ref<'all' | 'incoming' | 'outgoing' | 'internal_memo'>('all')

const pagination = ref<PaginationMeta>({
 current_page: 1,
 last_page: 1,
 total: 0,
})

const searchQuery = ref('')

// Modals
const showCreateModal = ref(false)
const showMovementModal = ref(false)
const showAssignmentModal = ref(false)
const showDispatchModal = ref(false)
const activeCorrespondence = ref<Correspondence | null>(null)

// Dispatch Form Model
const dispatchForm = ref({
 dispatch_method_id: '' as number | '',
 tracking_number: '',
})
const isSubmittingDispatch = ref(false)

// Linked Response Form Model
const showLinkedResponseModal = ref(false)
const linkedResponseForm = ref({
 title: '',
 summary: '',
 category_id: '' as number | '',
 confidentiality_id: '' as number | '',
 owner_unit_id: '' as number | '',
 priority_id: '' as number | '',
 purpose_code: 'official_response',
 correspondence_date: new Date().toISOString().split('T')[0],
 external_reference: '',
})
const isSubmittingLinkedResponse = ref(false)

// Create Form Model
const newCorr = ref({
 title: '',
 summary: '',
 category_id: '' as number | '',
 confidentiality_id: '' as number | '',
 owner_unit_id: '' as number | '',
 type_id: '' as number | '',
 priority_id: '' as number | '',
 purpose_code: 'official_communication',
 external_reference: '',
 correspondence_date: new Date().toISOString().split('T')[0],
 first_receiving_unit_id: '' as number | '',
 sender_unit_id: '' as number | '',
 to_unit_id: '' as number | '',
})
const isSubmittingCreate = ref(false)

// Movement Form Model
const movementForm = ref({
 from_unit_id: '' as number | '',
 to_unit_id: '' as number | '',
 instruction: '',
 due_at: '',
})
const isSubmittingMovement = ref(false)

// Assignment Form Model
const assignmentForm = ref({
 assigned_user_id: '' as number | '',
 assigned_unit_id: '' as number | '',
 instruction: '',
 due_at: '',
})
const isSubmittingAssignment = ref(false)

const columns: TableColumn[] = [
 { key: 'reference', label: 'Reference #' },
 { key: 'title', label: 'Title / Subject' },
 { key: 'type', label: 'Type' },
 { key: 'priority', label: 'Priority' },
 { key: 'unit', label: 'First Receipt Unit' },
 { key: 'date', label: 'Letter Date' },
]

async function loadCatalogs() {
 try {
 const [uRes, usrRes] = await Promise.all([
 organizationUnitsApi.list({ per_page: 100 }),
 usersApi.list({ per_page: 100 }),
 ])
 units.value = (uRes.data as any).data || []
 users.value = (usrRes.data as any).data || []

 if (units.value.length > 0) {
  const userUnitId = authStore.primaryUnit?.unit_id || units.value.find(u => u.code === 'REG')?.id || units.value[0]?.id || 2
  newCorr.value.owner_unit_id = userUnitId
  newCorr.value.first_receiving_unit_id = userUnitId
 }
 if (metaStore.categories.length > 0 && !newCorr.value.category_id) {
  newCorr.value.category_id = metaStore.categories[0].id
 }
 if (metaStore.confidentialityLevels.length > 0 && !newCorr.value.confidentiality_id) {
  newCorr.value.confidentiality_id = metaStore.confidentialityLevels[0].id
 }
 if (metaStore.correspondenceTypes.length > 0 && !newCorr.value.type_id) {
  newCorr.value.type_id = metaStore.correspondenceTypes[0].id
 }
 if (metaStore.priorities.length > 0 && !newCorr.value.priority_id) {
  newCorr.value.priority_id = metaStore.priorities[0].id
 }
 } catch (err) {
 console.error('Failed to load catalogs', err)
 }
}

async function loadCorrespondences(page = 1) {
 isLoading.value = true
 try {
 const params: Record<string, unknown> = {
 page,
 search: searchQuery.value || undefined,
 }

 if (selectedTab.value !== 'all') {
 const type = metaStore.correspondenceTypes.find((t) => t.code === selectedTab.value)
 if (type) {
 params.type_id = type.id
 }
 }

 const res = await correspondenceApi.list(params)
 correspondences.value = (res.data as any).data || []
 if (res.data.meta) {
 pagination.value = res.data.meta
 }
 } catch (err: any) {
 toast.error(err.response?.data?.message || 'Failed to load correspondence registry.')
 } finally {
 isLoading.value = false
 }
}

function openCreateModal() {
 const userUnitId = authStore.primaryUnit?.unit_id || units.value.find(u => u.code === 'REG')?.id || units.value[0]?.id || 2
 newCorr.value = {
  title: '',
  summary: '',
  category_id: metaStore.categories[0]?.id || 1,
  confidentiality_id: metaStore.confidentialityLevels[0]?.id || 1,
  owner_unit_id: userUnitId,
  type_id: metaStore.correspondenceTypes[0]?.id || 1,
  priority_id: metaStore.priorities[0]?.id || 1,
  purpose_code: 'official_communication',
  external_reference: '',
  correspondence_date: new Date().toISOString().split('T')[0],
  first_receiving_unit_id: userUnitId,
  sender_unit_id: '' as number | '',
  to_unit_id: '' as number | '',
 }
 showCreateModal.value = true
}

function openMovementModal(item: Correspondence) {
 activeCorrespondence.value = item
 movementForm.value = {
 from_unit_id: item.first_receiving_unit_id || units.value[0]?.id || '',
 to_unit_id: '',
 instruction: '',
 due_at: '',
 }
 showMovementModal.value = true
}

function openAssignmentModal(item: Correspondence) {
 activeCorrespondence.value = item
 assignmentForm.value = {
 assigned_user_id: '',
 assigned_unit_id: item.first_receiving_unit_id || units.value[0]?.id || '',
 instruction: '',
 due_at: '',
 }
 showAssignmentModal.value = true
}

async function submitCreateCorrespondence() {
 if (!newCorr.value.title.trim()) {
 toast.error('Title is required.')
 return
 }
 isSubmittingCreate.value = true
 try {
 const ownerId = newCorr.value.owner_unit_id
 ? Number(newCorr.value.owner_unit_id)
 : (newCorr.value.first_receiving_unit_id ? Number(newCorr.value.first_receiving_unit_id) : (units.value[0]?.id || 1))

 const payload = {
 ...newCorr.value,
 category_id: Number(newCorr.value.category_id),
 confidentiality_id: Number(newCorr.value.confidentiality_id),
 owner_unit_id: ownerId,
 type_id: Number(newCorr.value.type_id),
 priority_id: Number(newCorr.value.priority_id),
 first_receiving_unit_id: newCorr.value.first_receiving_unit_id ? Number(newCorr.value.first_receiving_unit_id) : null,
 sender_unit_id: newCorr.value.sender_unit_id ? Number(newCorr.value.sender_unit_id) : null,
 to_unit_id: newCorr.value.to_unit_id ? Number(newCorr.value.to_unit_id) : null,
 }

 await correspondenceApi.create(payload)
 toast.success('Correspondence logged successfully.')
 showCreateModal.value = false
 loadCorrespondences(1)
 } catch (err: any) {
 toast.error(err.response?.data?.message || 'Failed to log correspondence.')
 } finally {
 isSubmittingCreate.value = false
 }
}

async function submitMovement() {
 if (!activeCorrespondence.value) return
 if (!movementForm.value.to_unit_id) {
 toast.error('Please select destination unit.')
 return
 }

 isSubmittingMovement.value = true
 try {
 await correspondenceApi.recordMovement(activeCorrespondence.value.id, {
 from_unit_id: Number(movementForm.value.from_unit_id),
 to_unit_id: Number(movementForm.value.to_unit_id),
 instruction: movementForm.value.instruction || undefined,
 due_at: movementForm.value.due_at || undefined,
 })
 toast.success('Document movement recorded.')
 showMovementModal.value = false
 await loadCorrespondences(pagination.value.current_page)
 } catch (err: any) {
 toast.error(err.response?.data?.message || 'Failed to record movement.')
 } finally {
 isSubmittingMovement.value = false
 }
}

async function submitAssignment() {
 if (!activeCorrespondence.value) return
 if (!assignmentForm.value.assigned_user_id) {
 toast.error('Please select assigned staff member.')
 return
 }

 isSubmittingAssignment.value = true
 try {
 await correspondenceApi.assign(activeCorrespondence.value.id, {
 assigned_user_id: Number(assignmentForm.value.assigned_user_id),
 assigned_unit_id: Number(assignmentForm.value.assigned_unit_id),
 instruction: assignmentForm.value.instruction,
 due_at: assignmentForm.value.due_at || undefined,
 })
 toast.success('Correspondence assigned.')
 showAssignmentModal.value = false
 await loadCorrespondences(pagination.value.current_page)
 } catch (err: any) {
 toast.error(err.response?.data?.message || 'Failed to assign correspondence.')
 } finally {
 isSubmittingAssignment.value = false
 }
}

function getPendingMovementForUser(item: Correspondence) {
 if (!item.movements || !item.movements.length) return null
 const userUnitId = authStore.primaryUnit?.unit_id
 const isSuper = authStore.isSuperAdmin
 return item.movements.find((m: any) => {
 const isTarget = isSuper || (userUnitId && m.to_unit_id === userUnitId)
 const notAcked = !m.receipt
 return isTarget && notAcked
 })
}

async function handleAcknowledgeMovement(item: Correspondence) {
 const pendingMov = getPendingMovementForUser(item)
 if (!pendingMov) return
 await acknowledgeMovement(item.id, pendingMov.id)
}

function openDispatchModal(item: Correspondence) {
 activeCorrespondence.value = item
 dispatchForm.value = {
 dispatch_method_id: metaStore.dispatchMethods[0]?.id || '',
 tracking_number: '',
 }
 showDispatchModal.value = true
}

async function submitDispatch() {
 if (!activeCorrespondence.value) return
 if (!dispatchForm.value.dispatch_method_id) {
 toast.error('Please select a dispatch method.')
 return
 }
 isSubmittingDispatch.value = true
 try {
 await correspondenceApi.dispatch(activeCorrespondence.value.id, {
 method_id: Number(dispatchForm.value.dispatch_method_id),
 dispatch_method_id: Number(dispatchForm.value.dispatch_method_id),
 document_version_id: activeCorrespondence.value.document?.latest_version?.id || activeCorrespondence.value.document?.versions?.[0]?.id,
 tracking_reference: dispatchForm.value.tracking_number || undefined,
 tracking_number: dispatchForm.value.tracking_number || undefined,
 })
 toast.success('Official correspondence dispatched successfully.')
 showDispatchModal.value = false
 loadCorrespondences(pagination.value.current_page)
 } catch (err: any) {
 toast.error(err.response?.data?.message || 'Dispatch rejected by office procedure.')
 } finally {
 isSubmittingDispatch.value = false
 }
}

function openLinkedResponseModal(item: Correspondence) {
 activeCorrespondence.value = item
 linkedResponseForm.value = {
 title: `Re: ${item.document?.title || 'Incoming Correspondence'}`,
 summary: `Official response to incoming letter ${item.document?.reference_number || item.external_reference || ''}`,
 category_id: item.document?.category_id || metaStore.categories[0]?.id || '',
 confidentiality_id: item.document?.confidentiality_id || metaStore.confidentialityLevels[0]?.id || '',
 owner_unit_id: item.first_receiving_unit_id || item.document?.owner_unit_id || '',
 priority_id: item.priority_id || metaStore.priorities[0]?.id || '',
 purpose_code: 'official_response',
 correspondence_date: new Date().toISOString().split('T')[0],
 external_reference: '',
 }
 showLinkedResponseModal.value = true
}

async function submitLinkedResponse() {
 if (!activeCorrespondence.value) return
 isSubmittingLinkedResponse.value = true
 try {
 await correspondenceApi.prepareLinkedResponse(activeCorrespondence.value.id, {
 ...linkedResponseForm.value,
 category_id: Number(linkedResponseForm.value.category_id),
 confidentiality_id: Number(linkedResponseForm.value.confidentiality_id),
 owner_unit_id: Number(linkedResponseForm.value.owner_unit_id),
 priority_id: Number(linkedResponseForm.value.priority_id),
 })
 toast.success('Linked outgoing response correspondence prepared.')
 showLinkedResponseModal.value = false
 loadCorrespondences(pagination.value.current_page)
 } catch (err: any) {
 toast.error(err.response?.data?.message || 'Failed to prepare linked response.')
 } finally {
 isSubmittingLinkedResponse.value = false
 }
}

async function acknowledgeMovement(corrId: number, movementId: number) {
 try {
 await correspondenceApi.acknowledgeMovement(corrId, movementId)
 toast.success('Document movement acknowledged successfully.')
 await loadCorrespondences(pagination.value.current_page)
 } catch (err: any) {
 toast.error(err.response?.data?.message || 'Failed to acknowledge movement.')
 }
}

function syncTabFromRoute() {
 if (route.path.includes('/incoming')) selectedTab.value = 'incoming'
 else if (route.path.includes('/outgoing')) selectedTab.value = 'outgoing'
 else if (route.path.includes('/memoranda')) selectedTab.value = 'internal_memo'
 else if (route.path.includes('/routing')) selectedTab.value = 'all'
}

watch(() => route.path, () => {
 syncTabFromRoute()
 loadCorrespondences(1)
})

onMounted(async () => {
 syncTabFromRoute()
 await Promise.all([metaStore.loadAll(), loadCatalogs()])
 if (metaStore.categories.length > 0) newCorr.value.category_id = metaStore.categories[0].id
 if (metaStore.confidentialityLevels.length > 0) newCorr.value.confidentiality_id = metaStore.confidentialityLevels[0].id
 if (metaStore.correspondenceTypes.length > 0) newCorr.value.type_id = metaStore.correspondenceTypes[0].id
 if (metaStore.priorities.length > 0) newCorr.value.priority_id = metaStore.priorities[0].id
 loadCorrespondences(1)
})
</script>

<template>
 <div class="space-y-6">
 <!-- Header -->
 <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-hairline pb-5">
 <div>
 <div class="notion-eyebrow text-primary mb-1">
 RECORDS & ARCHIVES / CORRESPONDENCE REGISTRY
 </div>
 <h1 class="notion-heading-2 text-ink flex items-center gap-2.5">
 <Mail class="w-6 h-6 text-primary" />
 <span>Correspondence & Inter-Office Registry</span>
 </h1>
 <p class="mt-1 notion-body-sm text-ink-muted">
 Dual-path incoming letter logging, version-bound outgoing dispatch, and inter-office memoranda tracking.
 </p>
 </div>

 <div class="flex items-center gap-2.5">
 <BaseButton
 v-if="authStore.hasPermission('correspondence.register')"
 variant="primary"
 size="sm"
 class="bg-primary hover:bg-primary-hover text-white shadow-xs"
 @click="openCreateModal"
 >
 <Plus class="w-4 h-4 mr-1.5" />
 <span>Log Correspondence</span>
 </BaseButton>
 </div>
 </div>

 <!-- Type Tabs Bar -->
 <div class="flex items-center gap-2 border-b border-hairline pb-px text-xs">
 <button
 v-for="tab in [
 { key: 'all', label: 'All Records' },
 { key: 'incoming', label: 'Incoming Letters' },
 { key: 'outgoing', label: 'Outgoing Dispatches' },
 { key: 'internal_memo', label: 'Internal Memoranda' },
 ]"
 :key="tab.key"
 @click="selectedTab = tab.key as any; loadCorrespondences(1)"
 class="px-4 py-2 font-semibold transition-all border-b-2 cursor-pointer"
 :class="selectedTab === tab.key
 ? 'border-primary text-primary'
 : 'border-transparent text-ink-muted hover:text-ink dark:text-ink-faint dark:hover:text-white'"
 >
 {{ tab.label }}
 </button>
 </div>

 <!-- Search Bar -->
 <div class="bg-surface border border-hairline rounded-xl p-4 shadow-xs">
 <SearchFilterBar
 v-model="searchQuery"
 placeholder="Search correspondence by reference, subject, or external sender..."
 @search="loadCorrespondences(1)"
 />
 </div>

 <!-- Data Table -->
 <DataTable
 :columns="columns"
 :items="correspondences"
 :loading="isLoading"
 empty-title="No correspondence records found"
 empty-description="No records match your active query or selected category filter."
 >
 <template #cell-reference="{ item }">
 <div class="space-y-0.5">
 <div class="font-mono text-xs font-semibold text-primary">
 {{ item.document?.reference_number }}
 </div>
 <div v-if="item.external_reference" class="text-[10px] text-ink-muted font-mono">
 Ext: {{ item.external_reference }}
 </div>
 </div>
 </template>

 <template #cell-title="{ item }">
 <div class="text-xs font-semibold text-ink-secondary max-w-sm truncate">
 {{ item.document?.title }}
 </div>
 </template>

 <template #cell-type="{ item }">
 <span class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wider bg-canvas-soft text-ink-muted">
 {{ item.type?.name }}
 </span>
 </template>

 <template #cell-priority="{ item }">
 <PriorityBadge :priority="item.priority?.code || item.priority?.name" />
 </template>

 <template #cell-unit="{ item }">
 <span class="text-xs text-ink-muted truncate max-w-[140px] inline-block">
 {{ item.first_receiving_unit?.name || 'Wollo University' }}
 </span>
 </template>

 <template #cell-date="{ item }">
 <span class="text-xs text-ink-muted">
 {{ formatDate(item.correspondence_date) }}
 </span>
 </template>

 <template #actions="{ item }">
 <div class="flex items-center justify-end gap-1.5" @click.stop>
 <!-- Acknowledge Movement Button -->
 <BaseButton
 v-if="getPendingMovementForUser(item)"
 variant="outline"
 size="sm"
 class="h-7 text-[11px] px-2 text-emerald-700 dark:text-emerald-300 border-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100"
 title="Acknowledge receipt of forwarded letter"
 @click="handleAcknowledgeMovement(item)"
 >
 <CheckCheck class="w-3.5 h-3.5 mr-1" /> Acknowledge
 </BaseButton>

 <!-- Outgoing Dispatch Button -->
 <BaseButton
 v-if="selectedTab === 'outgoing' || item.type?.code === 'outgoing'"
 variant="outline"
 size="sm"
 class="h-7 text-[11px] px-2 text-primary border-primary/30"
 title="Dispatch official outward letter"
 @click="openDispatchModal(item)"
 >
 <Send class="w-3.5 h-3.5 mr-1" /> Dispatch
 </BaseButton>

 <!-- Forward / Movement Button -->
 <BaseButton
 variant="outline"
 size="sm"
 class="h-7 text-[11px] px-2"
 title="Forward to another unit"
 @click="openMovementModal(item)"
 >
 <TrendingUp class="w-3.5 h-3.5 mr-1" /> Forward
 </BaseButton>

 <!-- Direct Assignment Button -->
 <BaseButton
 variant="outline"
 size="sm"
 class="h-7 text-[11px] px-2"
 title="Assign task directive"
 @click="openAssignmentModal(item)"
 >
 <UserCheck class="w-3.5 h-3.5 mr-1" /> Assign
 </BaseButton>

 <!-- Linked Outgoing Response Button -->
 <BaseButton
 v-if="item.type?.code === 'incoming' && authStore.hasPermission('correspondence.register')"
 variant="outline"
 size="sm"
 class="h-7 text-[11px] px-2 text-primary border-primary/30"
 title="Prepare linked outgoing response"
 @click="openLinkedResponseModal(item)"
 >
 <Reply class="w-3.5 h-3.5 mr-1" /> Reply
 </BaseButton>
 </div>
 </template>
 </DataTable>

 <!-- Pagination -->
 <Pagination
 v-if="pagination.total > 0"
 :current-page="pagination.current_page"
 :last-page="pagination.last_page"
 :total-items="pagination.total"
 :items-per-page="15"
 @page-change="loadCorrespondences"
 />

 <!-- Log Correspondence Modal -->
 <ModalDialog
 :is-open="showCreateModal"
 title="Log New Institutional Correspondence"
 @close="showCreateModal = false"
 >
 <form @submit.prevent="submitCreateCorrespondence" class="space-y-4">
 <div>
 <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
 Subject / Title *
 </label>
 <BaseInput v-model="newCorr.title" placeholder="e.g. Budget transfer notice from Ministry of Education" required />
 </div>

 <div class="grid grid-cols-2 gap-3">
 <BaseSelect
 v-model="newCorr.type_id"
 label="Correspondence Type *"
 :options="metaStore.correspondenceTypes.map((t) => ({ label: t.name, value: t.id }))"
 required
 />

 <BaseSelect
 v-model="newCorr.priority_id"
 label="Priority SLA *"
 :options="metaStore.priorities.map((p) => ({ label: p.name, value: p.id }))"
 required
 />
 </div>

 <div class="grid grid-cols-2 gap-3">
 <BaseSelect
 v-model="newCorr.category_id"
 label="Category *"
 :options="metaStore.categories.map((c) => ({ label: c.name, value: c.id }))"
 required
 />

 <BaseSelect
 v-model="newCorr.confidentiality_id"
 label="Classification *"
 :options="metaStore.confidentialityLevels.map((c) => ({ label: c.name, value: c.id }))"
 required
 />
 </div>

 <div class="grid grid-cols-2 gap-3">
 <BaseSelect
 v-model="newCorr.first_receiving_unit_id"
 label="First Receiving Unit *"
 :options="units.map((u) => ({ label: `${u.name} (${u.code})`, value: u.id }))"
 required
 />

 <div>
 <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
 Letter Date *
 </label>
 <BaseInput v-model="newCorr.correspondence_date" type="date" required />
 </div>
 </div>

 <div>
 <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
 External Reference Number
 </label>
 <BaseInput v-model="newCorr.external_reference" placeholder="e.g. MOE/FIN/2026/099" />
 </div>

 <div class="flex justify-end gap-2 pt-2">
 <BaseButton variant="ghost" size="sm" type="button" @click="showCreateModal = false">Cancel</BaseButton>
 <BaseButton variant="primary" size="sm" type="submit" :loading="isSubmittingCreate" @click="submitCreateCorrespondence">Commit Correspondence</BaseButton>
 </div>
 </form>
 </ModalDialog>

 <!-- Movement Forwarding Modal -->
 <ModalDialog
 :is-open="showMovementModal"
 title="Forward Document to Another Unit"
 @close="showMovementModal = false"
 >
 <form @submit.prevent="submitMovement" class="space-y-4">
 <BaseSelect
 v-model="movementForm.from_unit_id"
 label="From Unit *"
 :options="units.map((u) => ({ label: u.name, value: u.id }))"
 required
 />

 <BaseSelect
 v-model="movementForm.to_unit_id"
 label="Forward To Unit *"
 :options="units.map((u) => ({ label: u.name, value: u.id }))"
 required
 />

 <div>
 <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
 Handling Directive / Instruction
 </label>
 <BaseTextarea v-model="movementForm.instruction" rows="2" placeholder="e.g. For necessary departmental review and response..." />
 </div>

 <div class="flex justify-end gap-2 pt-2">
 <BaseButton variant="ghost" size="sm" type="button" @click="showMovementModal = false">Cancel</BaseButton>
 <BaseButton variant="primary" size="sm" type="submit" :loading="isSubmittingMovement" @click="submitMovement">Confirm Forwarding</BaseButton>
 </div>
 </form>
 </ModalDialog>

 <!-- Directive Assignment Modal -->
 <ModalDialog
 :is-open="showAssignmentModal"
 title="Assign Correspondence Directive"
 @close="showAssignmentModal = false"
 >
 <form @submit.prevent="submitAssignment" class="space-y-4">
 <BaseSelect
 v-model="assignmentForm.assigned_user_id"
 label="Assigned Officer *"
 :options="users.map((u) => ({ label: `${u.name} (${u.email})`, value: u.id }))"
 required
 />

 <BaseSelect
 v-model="assignmentForm.assigned_unit_id"
 label="Department / Unit *"
 :options="units.map((u) => ({ label: u.name, value: u.id }))"
 required
 />

 <div>
 <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
 Assignment Directive *
 </label>
 <BaseTextarea v-model="assignmentForm.instruction" rows="3" placeholder="Specific action required by the officer..." required />
 </div>

 <div class="flex justify-end gap-2 pt-2">
 <BaseButton variant="ghost" size="sm" type="button" @click="showAssignmentModal = false">Cancel</BaseButton>
 <BaseButton variant="primary" size="sm" type="submit" :loading="isSubmittingAssignment" @click="submitAssignment">Assign Directive</BaseButton>
 </div>
 </form>
 </ModalDialog>

 <!-- Outgoing Dispatch Modal -->
 <ModalDialog
 :is-open="showDispatchModal"
 title="Official Outgoing Dispatch"
 @close="showDispatchModal = false"
 >
 <form @submit.prevent="submitDispatch" class="space-y-4 text-xs">
 <div class="p-3.5 rounded-xl bg-primary-subtle border border-primary/20 space-y-1.5">
 <div class="font-semibold text-primary flex items-center gap-1.5">
 <CheckCircle2 class="w-4 h-4 shrink-0" />
 <span>Policy-Driven Outgoing Authority</span>
 </div>
 <p class="text-ink text-[11px] leading-relaxed">
 Dispatch is governed by originating office procedure. If procedure mandates workflow review, dispatch is bound strictly to the approved document version. Unapproved or revised drafts are blocked with HTTP 422.
 </p>
 </div>

 <div>
 <label class="block font-semibold text-ink-secondary mb-1">
 Dispatch Method / Delivery Channel *
 </label>
 <BaseSelect
 v-model="dispatchForm.dispatch_method_id"
 :options="metaStore.dispatchMethods.map((m) => ({ label: m.name, value: m.id }))"
 required
 />
 </div>

 <div>
 <label class="block font-semibold text-ink-secondary mb-1">
 Courier / Postal Tracking Reference
 </label>
 <BaseInput
 v-model="dispatchForm.tracking_number"
 placeholder="e.g. POST-ET-2026-9914 or Hand-delivery receipt #"
 />
 </div>

 <div class="pt-2 flex justify-end gap-2">
 <BaseButton variant="outline" size="sm" type="button" @click="showDispatchModal = false">Cancel</BaseButton>
 <BaseButton
 variant="primary"
 size="sm"
 class="bg-primary hover:bg-primary-hover text-white shadow-xs"
 type="submit"
 :loading="isSubmittingDispatch"
 >
 Authorize & Dispatch
 </BaseButton>
 </div>
 </form>
 </ModalDialog>

 <!-- Prepare Linked Outgoing Response Modal -->
 <ModalDialog
 :is-open="showLinkedResponseModal"
 title="Prepare Linked Outgoing Response"
 @close="showLinkedResponseModal = false"
 >
 <form @submit.prevent="submitLinkedResponse" class="space-y-4 text-xs">
 <div class="p-2.5 rounded-lg bg-surface-muted  border border-hairline text-ink-muted">
 Replying to: <span class="font-bold text-ink">{{ activeCorrespondence?.document?.title }}</span>
 ({{ activeCorrespondence?.document?.reference_number || 'Pending Ref' }})
 </div>

 <div>
 <label class="block font-semibold text-ink-secondary mb-1">
 Response Title / Subject *
 </label>
 <BaseInput v-model="linkedResponseForm.title" required />
 </div>

 <div>
 <label class="block font-semibold text-ink-secondary mb-1">
 Summary / Action Taken
 </label>
 <BaseTextarea v-model="linkedResponseForm.summary" rows="2" />
 </div>

 <div class="grid grid-cols-2 gap-3">
 <BaseSelect
 v-model="linkedResponseForm.owner_unit_id"
 label="Originating Office *"
 :options="units.map((u) => ({ label: `${u.name} (${u.code})`, value: u.id }))"
 required
 />

 <BaseSelect
 v-model="linkedResponseForm.priority_id"
 label="Priority SLA *"
 :options="metaStore.priorities.map((p) => ({ label: p.name, value: p.id }))"
 required
 />
 </div>

 <div class="grid grid-cols-2 gap-3">
 <BaseSelect
 v-model="linkedResponseForm.category_id"
 label="Category *"
 :options="metaStore.categories.map((c) => ({ label: c.name, value: c.id }))"
 required
 />

 <BaseSelect
 v-model="linkedResponseForm.confidentiality_id"
 label="Classification *"
 :options="metaStore.confidentialityLevels.map((c) => ({ label: c.name, value: c.id }))"
 required
 />
 </div>

 <div class="grid grid-cols-2 gap-3">
 <div>
 <label class="block font-semibold text-ink-secondary mb-1">
 Letter Date *
 </label>
 <BaseInput v-model="linkedResponseForm.correspondence_date" type="date" required />
 </div>

 <div>
 <label class="block font-semibold text-ink-secondary mb-1">
 External Reference
 </label>
 <BaseInput v-model="linkedResponseForm.external_reference" placeholder="e.g. WU/CS/RESP/2026/01" />
 </div>
 </div>

 <div class="pt-2 flex justify-end gap-2">
 <BaseButton variant="outline" size="sm" type="button" @click="showLinkedResponseModal = false">Cancel</BaseButton>
 <BaseButton
 variant="primary"
 size="sm"
 class="bg-primary hover:bg-primary-hover text-white shadow-xs"
 type="submit"
 :loading="isSubmittingLinkedResponse"
 >
 Create Linked Response Draft
 </BaseButton>
 </div>
 </form>
 </ModalDialog>
 </div>
</template>

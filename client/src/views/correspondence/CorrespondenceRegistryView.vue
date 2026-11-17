<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
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
 Eye,
 FileText,
 Upload,
} from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
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
 sender_type: 'external' as 'internal' | 'external',
 sender_unit_id: '' as number | '',
 sender_external_name: '',
 to_type: 'internal' as 'internal' | 'external',
 to_unit_id: '' as number | '',
 to_external_name: '',
 related_document_id: '' as number | '',
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

// File attachment for registration
const newCorrFile = ref<File | null>(null)
const newCorrFileName = ref('')

function handleCorrFileChange(event: Event) {
 const target = event.target as HTMLInputElement
 if (target.files && target.files[0]) {
 newCorrFile.value = target.files[0]
 newCorrFileName.value = target.files[0].name
 }
}

// Quick View / Inspect Modal State
const inspectingCorr = ref<Correspondence | null>(null)
const showInspectModal = ref(false)

function openInspectModal(item: Correspondence) {
 inspectingCorr.value = item
 showInspectModal.value = true
}

function getSenderName(corr: Correspondence): string {
 const senderPart = corr.participants?.find(p => p.participation_type === 'sender')
 if (senderPart?.unit?.name) return senderPart.unit.name
 if (senderPart?.user?.name) return senderPart.user.name
 if (senderPart?.external_party?.name) return senderPart.external_party.name
 if (corr.type?.code === 'outgoing') return corr.document?.owner_unit?.name || 'Wollo University'
 return corr.external_reference ? `Ext: ${corr.external_reference}` : 'External Sender'
}

function getRecipientName(corr: Correspondence): string {
 const toPart = corr.participants?.find(p => p.participation_type === 'to')
 if (toPart?.unit?.name) return toPart.unit.name
 if (toPart?.user?.name) return toPart.user.name
 if (toPart?.external_party?.name) return toPart.external_party.name
 if (corr.first_receiving_unit?.name) return corr.first_receiving_unit.name
 return 'Designated Office'
}

function getRoutingStatus(corr: Correspondence): { text: string; class: string } {
  if (corr.is_closed || corr.document?.lifecycle === 'retired' || corr.lifecycle === 'closed') {
    return {
      text: 'Closed',
      class: 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700',
    }
  }

  if (corr.dispatches && corr.dispatches.length > 0) {
    const allDelivered = corr.dispatches.every(d => d.has_delivery_evidence || !!d.evidence_file_id)
    if (allDelivered) {
      return {
        text: 'Delivered (Proof Verified)',
        class: 'bg-[#e8f7ec] dark:bg-[#14301d] text-[#1aae39] dark:text-[#34d399] border-[#1aae39]/30',
      }
    }
    const disp = corr.dispatches[0]
    return {
      text: 'Dispatched (Pending Proof)',
      class: 'bg-[#fff4e6] dark:bg-[#382412] text-[#dd5b00] dark:text-[#ff9243] border-[#dd5b00]/30',
    }
  }

  if (corr.type?.code === 'outgoing' || corr.is_outgoing) {
    return {
      text: 'Pending Dispatch',
      class: 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-300/40',
    }
  }
 if (corr.movements && corr.movements.length > 0) {
 const latest = corr.movements[corr.movements.length - 1]
 if (latest.receipt?.acknowledged_at) {
 return { text: `Received: ${latest.to_unit?.name || 'Office'}`, class: 'bg-[#e8f7ec] dark:bg-[#14301d] text-[#1aae39] dark:text-[#34d399] border-[#1aae39]/20' }
 }
 return { text: `In transit to ${latest.to_unit?.name || 'Office'}`, class: 'bg-[#fff4e6] dark:bg-[#382412] text-[#dd5b00] dark:text-[#ff9243] border-[#dd5b00]/20' }
 }
 if (corr.assignments && corr.assignments.length > 0) {
 const assignedUser = corr.assignments[0].assigned_user?.name || 'Officer'
 return { text: `Assigned: ${assignedUser}`, class: 'bg-primary-subtle text-primary border-primary/20' }
 }
 if (corr.received_at) {
 return { text: 'Logged & Received', class: 'bg-primary-subtle text-primary border-primary/20' }
 }
 return { text: 'Registered', class: 'bg-surface-muted text-ink-muted border-hairline' }
}

// Assignment Form Model
const assignmentForm = ref({
 assigned_user_id: '' as number | '',
 assigned_unit_id: '' as number | '',
 instruction: '',
 due_at: '',
})
const isSubmittingAssignment = ref(false)

const sortBy = ref('registered_at')
const sortOrder = ref<'asc' | 'desc'>('desc')

const columns: TableColumn[] = [
 { key: 'reference', label: 'Reference #', sortable: true },
 { key: 'title', label: 'Title / Subject' },
 { key: 'sender_recipient', label: 'Sender & Recipient' },
 { key: 'type', label: 'Type' },
 { key: 'priority', label: 'Priority' },
 { key: 'status', label: 'Routing & Receipt' },
 { key: 'date', label: 'Letter Date', sortable: true },
]

/**
 * The unit the correspondence is registered against by default.
 *
 * The active organizational unit is used when the server published one. The
 * unit is never invented: when no unit can be determined the field stays
 * empty and the operator chooses it.
 */
function defaultOwnerUnitId(): number | '' {
 const activeUnitId = authStore.activeUnitId
 if (activeUnitId !== null) return activeUnitId
 const primaryUnitId = authStore.primaryUnit?.unit_id
 if (primaryUnitId) return primaryUnitId
 return units.value[0]?.id ?? ''
}

async function loadCatalogs() {
 // The two catalogues are loaded independently. A caller may reach the
 // registry without the capability to list accounts, and a failure of one
 // catalogue must not empty the other.
 const [unitsResult, usersResult] = await Promise.allSettled([
 organizationUnitsApi.list({ per_page: 100 }),
 usersApi.list({ per_page: 100 }),
 ])

 if (unitsResult.status === 'fulfilled') {
 units.value = unitsResult.value.data.data || []
 } else {
 console.error('Failed to load the organizational unit catalogue', unitsResult.reason)
 }

 if (usersResult.status === 'fulfilled') {
 users.value = usersResult.value.data.data || []
 } else {
 console.error('Failed to load the account directory', usersResult.reason)
 }

 const ownerUnitId = defaultOwnerUnitId()
 if (ownerUnitId !== '') {
 newCorr.value.owner_unit_id = ownerUnitId
 newCorr.value.first_receiving_unit_id = ownerUnitId
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
}

async function loadCorrespondences(page = 1) {
 isLoading.value = true
 try {
 const params: Record<string, unknown> = {
 page,
 search: searchQuery.value || undefined,
 sort_by: sortBy.value,
 sort_order: sortOrder.value,
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

function handleSort(columnKey: string) {
 const backendKey = columnKey === 'date' ? 'correspondence_date' : columnKey === 'reference' ? 'external_reference' : 'registered_at'
 if (sortBy.value === backendKey) {
 sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
 } else {
 sortBy.value = backendKey
 sortOrder.value = 'asc'
 }
 loadCorrespondences(1)
}

function openCreateModal() {
 const ownerUnitId = defaultOwnerUnitId()
 newCorrFile.value = null
 newCorrFileName.value = ''
 newCorr.value = {
 title: '',
 summary: '',
 category_id: metaStore.categories[0]?.id ?? '',
 confidentiality_id: metaStore.confidentialityLevels[0]?.id ?? '',
 owner_unit_id: ownerUnitId,
 type_id: metaStore.correspondenceTypes[0]?.id ?? '',
 priority_id: metaStore.priorities[0]?.id ?? '',
 purpose_code: 'official_communication',
 external_reference: '',
 correspondence_date: new Date().toISOString().split('T')[0],
 first_receiving_unit_id: ownerUnitId,
 sender_type: 'external',
 sender_unit_id: '' as number | '',
 sender_external_name: '',
 to_type: 'internal',
 to_unit_id: ownerUnitId,
 to_external_name: '',
 related_document_id: '' as number | '',
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
 : newCorr.value.first_receiving_unit_id
 ? Number(newCorr.value.first_receiving_unit_id)
 : null

 if (!ownerId) {
 toast.error('Select the accountable unit for this correspondence.')
 return
 }

 const formData = new FormData()
 formData.append('title', newCorr.value.title.trim())
 if (newCorr.value.summary) formData.append('summary', newCorr.value.summary.trim())
 formData.append('category_id', String(newCorr.value.category_id))
 formData.append('confidentiality_id', String(newCorr.value.confidentiality_id))
 formData.append('owner_unit_id', String(ownerId))
 formData.append('type_id', String(newCorr.value.type_id))
 formData.append('priority_id', String(newCorr.value.priority_id))
 formData.append('purpose_code', newCorr.value.purpose_code)
 formData.append('correspondence_date', newCorr.value.correspondence_date)
 if (newCorr.value.external_reference) formData.append('external_reference', newCorr.value.external_reference.trim())
 if (newCorr.value.first_receiving_unit_id) formData.append('first_receiving_unit_id', String(newCorr.value.first_receiving_unit_id))
 if (newCorr.value.sender_type === 'internal' && newCorr.value.sender_unit_id) {
 formData.append('sender_unit_id', String(newCorr.value.sender_unit_id))
 } else if (newCorr.value.sender_type === 'external' && newCorr.value.sender_external_name) {
 formData.append('sender_external_name', newCorr.value.sender_external_name.trim())
 }
 if (newCorr.value.to_type === 'internal' && newCorr.value.to_unit_id) {
 formData.append('to_unit_id', String(newCorr.value.to_unit_id))
 } else if (newCorr.value.to_type === 'external' && newCorr.value.to_external_name) {
 formData.append('to_external_name', newCorr.value.to_external_name.trim())
 }
 if (newCorr.value.related_document_id) {
 formData.append('related_document_id', String(newCorr.value.related_document_id))
 }
 if (newCorrFile.value) {
 formData.append('file', newCorrFile.value)
 }

 await correspondenceApi.create(formData)
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
 const userUnitId = authStore.activeUnitId || authStore.primaryUnit?.unit_id
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
 owner_unit_id: item.first_receiving_unit_id || item.document?.owner_unit_id || authStore.activeUnitId || authStore.primaryUnit?.unit_id || '',
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

const showCloseModal = ref(false)
const closeReason = ref('')
const isSubmittingClose = ref(false)

function openCloseModal(item: Correspondence) {
  activeCorrespondence.value = item
  closeReason.value = ''
  showCloseModal.value = true
}

async function submitCloseCorrespondence() {
  if (!activeCorrespondence.value) return
  isSubmittingClose.value = true
  try {
    await correspondenceApi.close(activeCorrespondence.value.id, {
      reason: closeReason.value.trim() || undefined,
    })
    toast.success('Correspondence item formally closed.')
    showCloseModal.value = false
    await loadCorrespondences(pagination.value.current_page)
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to close correspondence.')
  } finally {
    isSubmittingClose.value = false
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
 <div class="space-y-5">
 <!-- Header -->
 <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
 <div>
 <h1 class="text-xl sm:text-2xl font-semibold tracking-tight text-ink flex items-center gap-2.5">
 <Mail class="w-5 h-5 sm:w-6 sm:h-6 text-primary" />
 <span>Correspondence & Inter-Office Registry</span>
 </h1>
 <p class="mt-1 text-sm text-ink-muted">
 Dual-path incoming letter logging, version-bound outgoing dispatch, and inter-office memoranda tracking
 </p>
 </div>

 <div class="flex items-center gap-2.5 shrink-0">
 <BaseButton
 v-if="authStore.hasPermission('correspondence.register')"
 variant="primary"
 size="sm"
 @click="openCreateModal"
 >
 <Plus class="w-3.5 h-3.5 mr-1" />
 Log correspondence
 </BaseButton>
 </div>
 </div>

 <!-- Type Tabs Bar -->
 <div class="flex items-center gap-2 border-b border-hairline pb-px text-xs" role="tablist" aria-label="Correspondence types">
 <button
 type="button"
 role="tab"
 :aria-selected="selectedTab === tab.key"
 v-for="tab in [
 { key: 'all', label: 'All records' },
 { key: 'incoming', label: 'Incoming letters' },
 { key: 'outgoing', label: 'Outgoing dispatches' },
 { key: 'internal_memo', label: 'Internal memoranda' },
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
 <div class="bg-surface border border-line rounded-lg p-3 sm:p-4">
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
 :sort-by="sortBy === 'correspondence_date' ? 'date' : sortBy === 'external_reference' ? 'reference' : sortBy"
 :sort-order="sortOrder"
 empty-title="No correspondence records found"
 empty-description="No records match your active query or selected category filter."
 @sort="handleSort"
 @row-click="(row) => row.document_id && router.push(`/documents/${row.document_id}`)"
 >
 <template #cell-reference="{ item }">
 <div class="space-y-0.5">
 <div class="font-mono text-xs font-semibold text-primary">
 {{ item.document?.reference_number }}
 </div>
 <div v-if="item.external_reference" class="text-xs text-ink-muted font-mono">
 Ext: {{ item.external_reference }}
 </div>
 </div>
 </template>

 <template #cell-title="{ item }">
 <div class="text-xs font-semibold text-ink-secondary max-w-sm truncate">
 {{ item.document?.title }}
 </div>
 </template>

 <template #cell-sender_recipient="{ item }">
 <div class="space-y-0.5 text-xs">
 <div class="font-medium text-ink truncate max-w-[180px] sm:max-w-[240px] lg:max-w-xs" :title="getSenderName(item)">
 <span class="text-xs font-semibold text-ink-muted mr-1">From:</span>{{ getSenderName(item) }}
 </div>
 <div class="text-xs text-ink-muted truncate max-w-[180px] sm:max-w-[240px] lg:max-w-xs" :title="getRecipientName(item)">
 <span class="text-xs font-semibold text-ink-muted mr-1">To:</span>{{ getRecipientName(item) }}
 </div>
 </div>
 </template>

 <template #cell-type="{ item }">
 <span class="px-2 py-0.5 rounded text-xs font-semibold   bg-canvas-soft text-ink-muted">
 {{ item.type?.name }}
 </span>
 </template>

 <template #cell-priority="{ item }">
 <PriorityBadge :priority="item.priority?.code || item.priority?.name" />
 </template>

 <template #cell-status="{ item }">
 <span
 class="px-2 py-0.5 rounded text-xs font-semibold   border inline-block"
 :class="getRoutingStatus(item).class"
 >
 {{ getRoutingStatus(item).text }}
 </span>
 </template>

 <template #cell-unit="{ item }">
 <span class="text-xs text-ink-muted truncate max-w-[160px] sm:max-w-[220px] lg:max-w-xs inline-block" :title="item.first_receiving_unit?.name">
 {{ item.first_receiving_unit?.name || 'Wollo University' }}
 </span>
 </template>

 <template #cell-date="{ item }">
 <span class="text-xs text-ink-muted">
 {{ formatDate(item.correspondence_date) }}
 </span>
 </template>

 <template #actions="{ item }">
 <div class="flex items-center justify-end gap-1.5 flex-wrap" @click.stop>
 <!-- Acknowledge Movement Button -->
 <BaseButton
 v-if="getPendingMovementForUser(item)"
 variant="outline"
 size="sm"
 class="h-7 text-xs px-2 bg-[#e8f7ec] dark:bg-[#14301d] text-[#1aae39] dark:text-[#34d399] border-[#1aae39]/30 hover:bg-[#d6f0dc] dark:hover:bg-[#1b3d27]"
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
 class="h-7 text-xs px-2 text-primary border-primary/30"
 title="Dispatch official outward letter"
 @click="openDispatchModal(item)"
 >
 <Send class="w-3.5 h-3.5 mr-1" /> Dispatch
 </BaseButton>

 <!-- Forward / Movement Button -->
 <BaseButton
 variant="outline"
 size="sm"
 class="h-7 text-xs px-2"
 title="Forward to another unit"
 @click="openMovementModal(item)"
 >
 <TrendingUp class="w-3.5 h-3.5 mr-1" /> Forward
 </BaseButton>

 <!-- Direct Assignment Button -->
 <BaseButton
 variant="outline"
 size="sm"
 class="h-7 text-xs px-2"
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
 class="h-7 text-xs px-2 text-primary border-primary/30"
 title="Prepare linked outgoing response"
 @click="openLinkedResponseModal(item)"
 >
 <Reply class="w-3.5 h-3.5 mr-1" /> Reply
 </BaseButton>

 <!-- Close Correspondence Button -->
 <BaseButton
 variant="outline"
 size="sm"
 class="h-7 text-xs px-2 text-rose-600 border-rose-300 hover:bg-rose-50 dark:hover:bg-rose-950/20"
 title="Formally close correspondence item"
 @click="openCloseModal(item)"
 >
 <CheckCircle2 class="w-3.5 h-3.5 mr-1" /> Close
 </BaseButton>

 <!-- View Details Button -->
 <BaseButton
 variant="ghost"
 size="sm"
 class="h-7 text-xs px-2 text-ink-muted hover:text-ink-secondary"
 title="Inspect correspondence trail and directives"
 @click="openInspectModal(item)"
 >
 <Eye class="w-3.5 h-3.5 mr-1" /> View
 </BaseButton>
 </div>
 </template>
 </DataTable>

 <!-- Pagination -->
 <Pagination
 v-if="pagination.total > 0"
 :meta="pagination"
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
 <label class="block text-xs font-semibold   text-ink-secondary mb-1">
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
 <div>
 <label class="block text-xs font-semibold   text-ink-secondary mb-1">
 Originating Sender
 </label>
 <div class="flex items-center gap-2 mb-1.5 text-xs">
 <label class="inline-flex items-center gap-1 cursor-pointer">
 <input type="radio" value="external" v-model="newCorr.sender_type" class="text-primary" />
 <span>External Org</span>
 </label>
 <label class="inline-flex items-center gap-1 cursor-pointer">
 <input type="radio" value="internal" v-model="newCorr.sender_type" class="text-primary" />
 <span>Internal Unit</span>
 </label>
 </div>
 <BaseInput
 v-if="newCorr.sender_type === 'external'"
 v-model="newCorr.sender_external_name"
 placeholder="e.g. Ministry of Education"
 />
 <BaseSelect
 v-else
 v-model="newCorr.sender_unit_id"
 :options="units.map((u) => ({ label: u.name, value: u.id }))"
 />
 </div>

 <div>
 <label class="block text-xs font-semibold   text-ink-secondary mb-1">
 Destination Recipient
 </label>
 <div class="flex items-center gap-2 mb-1.5 text-xs">
 <label class="inline-flex items-center gap-1 cursor-pointer">
 <input type="radio" value="internal" v-model="newCorr.to_type" class="text-primary" />
 <span>Internal Unit</span>
 </label>
 <label class="inline-flex items-center gap-1 cursor-pointer">
 <input type="radio" value="external" v-model="newCorr.to_type" class="text-primary" />
 <span>External Org</span>
 </label>
 </div>
 <BaseSelect
 v-if="newCorr.to_type === 'internal'"
 v-model="newCorr.to_unit_id"
 :options="units.map((u) => ({ label: u.name, value: u.id }))"
 />
 <BaseInput
 v-else
 v-model="newCorr.to_external_name"
 placeholder="e.g. Commercial Bank of Ethiopia"
 />
 </div>
 </div>

 <div class="grid grid-cols-2 gap-3">
 <BaseSelect
 v-model="newCorr.first_receiving_unit_id"
 label="First Receiving Unit *"
 :options="units.map((u) => ({ label: `${u.name} (${u.code})`, value: u.id }))"
 required
 />

 <div>
 <label class="block text-xs font-semibold   text-ink-secondary mb-1">
 Letter Date *
 </label>
 <BaseInput v-model="newCorr.correspondence_date" type="date" required />
 </div>
 </div>

 <div>
 <label class="block text-xs font-semibold   text-ink-secondary mb-1">
 External Reference Number
 </label>
 <BaseInput v-model="newCorr.external_reference" placeholder="e.g. MOE/FIN/2026/099" />
 </div>

 <div>
 <label class="block text-xs font-semibold   text-ink-secondary mb-1">
 Digital File / Scanned Copy (PDF, DOCX)
 </label>
 <input
 type="file"
 accept=".pdf,.docx,.doc,.tiff,.png,.jpg"
 class="w-full text-xs text-ink-secondary file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary-subtle file:text-primary dark:file:bg-[#172c42] dark:file:text-[#62aef0] hover:file:bg-[#d4e8fa] cursor-pointer"
 @change="handleCorrFileChange"
 />
 <div v-if="newCorrFileName" class="text-xs text-ink-muted mt-1 font-mono">
 Selected: {{ newCorrFileName }}
 </div>
 </div>

 <div class="flex justify-end gap-2 pt-2">
 <BaseButton variant="ghost" size="sm" type="button" @click="showCreateModal = false">Cancel</BaseButton>
 <BaseButton variant="primary" size="sm" type="submit" :loading="isSubmittingCreate">Commit Correspondence</BaseButton>
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
 <label class="block text-xs font-semibold   text-ink-secondary mb-1">
 Handling Directive / Instruction
 </label>
 <BaseTextarea v-model="movementForm.instruction" rows="2" placeholder="e.g. For necessary departmental review and response..." />
 </div>

 <div class="flex justify-end gap-2 pt-2">
 <BaseButton variant="ghost" size="sm" type="button" @click="showMovementModal = false">Cancel</BaseButton>
 <BaseButton variant="primary" size="sm" type="submit" :loading="isSubmittingMovement">Confirm Forwarding</BaseButton>
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
 <label class="block text-xs font-semibold   text-ink-secondary mb-1">
 Assignment Directive *
 </label>
 <BaseTextarea v-model="assignmentForm.instruction" rows="3" placeholder="Specific action required by the officer..." required />
 </div>

 <div class="flex justify-end gap-2 pt-2">
 <BaseButton variant="ghost" size="sm" type="button" @click="showAssignmentModal = false">Cancel</BaseButton>
 <BaseButton variant="primary" size="sm" type="submit" :loading="isSubmittingAssignment">Assign Directive</BaseButton>
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
 <p class="text-ink text-xs leading-relaxed">
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
 class="bg-primary hover:bg-primary-hover text-white"
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
 class="bg-primary hover:bg-primary-hover text-white"
 type="submit"
 :loading="isSubmittingLinkedResponse"
 >
 Create Linked Response Draft
 </BaseButton>
 </div>
 </form>
 </ModalDialog>

 <!-- Quick Inspection Modal -->
 <ModalDialog
 :is-open="showInspectModal"
 title="Institutional Correspondence Record"
 @close="showInspectModal = false"
 >
 <div v-if="inspectingCorr" class="space-y-4">
 <div class="p-3.5 bg-surface-hover rounded-xl border border-hairline space-y-2">
 <div class="flex items-center justify-between">
 <div class="font-mono text-xs font-bold text-primary">
 {{ inspectingCorr.document?.reference_number }}
 </div>
 <span
 class="px-2 py-0.5 rounded text-xs font-semibold   border"
 :class="getRoutingStatus(inspectingCorr).class"
 >
 {{ getRoutingStatus(inspectingCorr).text }}
 </span>
 </div>
 <div class="text-sm font-semibold text-ink">
 {{ inspectingCorr.document?.title }}
 </div>
 <p v-if="inspectingCorr.document?.summary" class="text-xs text-ink-muted">
 {{ inspectingCorr.document.summary }}
 </p>
 </div>

 <div class="grid grid-cols-2 gap-3 text-xs border-y border-hairline py-3">
 <div>
 <span class="text-ink-muted block text-xs font-semibold">Originating Sender</span>
 <span class="font-medium text-ink">{{ getSenderName(inspectingCorr) }}</span>
 </div>
 <div>
 <span class="text-ink-muted block text-xs font-semibold">First Receiving Office</span>
 <span class="font-medium text-ink">{{ getRecipientName(inspectingCorr) }}</span>
 </div>
 <div>
 <span class="text-ink-muted block text-xs font-semibold">Type & Priority</span>
 <span class="font-medium text-ink">{{ inspectingCorr.type?.name }} &bull; {{ inspectingCorr.priority?.name }}</span>
 </div>
 <div>
 <span class="text-ink-muted block text-xs font-semibold">External Reference</span>
 <span class="font-mono text-ink">{{ inspectingCorr.external_reference || 'None (Internal)' }}</span>
 </div>
 </div>

 <!-- Movements History -->
 <div>
 <div class="text-xs font-semibold   text-ink-muted mb-2">
 Routing & Movements History ({{ inspectingCorr.movements?.length || 0 }})
 </div>
 <div v-if="inspectingCorr.movements && inspectingCorr.movements.length > 0" class="space-y-2">
 <div
 v-for="m in inspectingCorr.movements"
 :key="m.id"
 class="p-2.5 rounded-lg bg-surface border border-hairline text-xs space-y-1"
 >
 <div class="flex items-center justify-between font-medium">
 <span>{{ m.from_unit?.name || 'Unit' }} &rarr; {{ m.to_unit?.name || 'Unit' }}</span>
 <span class="text-xs font-mono text-ink-muted">{{ formatDate(m.sent_at) }}</span>
 </div>
 <div v-if="m.instruction" class="text-xs text-ink-secondary italic">
 Instruction: {{ m.instruction }}
 </div>
 <div class="text-xs text-ink-muted flex items-center gap-1.5">
 <span v-if="m.receipt?.acknowledged_at" class="text-emerald-600 font-semibold flex items-center gap-1">
 <CheckCheck class="w-3 h-3" /> Acknowledged by {{ m.receipt.acknowledged_by_user?.name || 'Officer' }}
 </span>
 <span v-else class="text-amber-600 font-medium">
 Pending physical receipt confirmation
 </span>
 </div>
 </div>
 </div>
 <div v-else class="text-xs text-ink-muted italic py-2">
 No physical movements recorded for this letter.
 </div>
 </div>

 <!-- Directives & Assignments -->
 <div v-if="inspectingCorr.assignments && inspectingCorr.assignments.length > 0">
 <div class="text-xs font-semibold   text-ink-muted mb-2">
 Operational Directives & Assignments ({{ inspectingCorr.assignments.length }})
 </div>
 <div class="space-y-2">
 <div
 v-for="a in inspectingCorr.assignments"
 :key="a.id"
 class="p-2.5 rounded-lg bg-surface border border-hairline text-xs space-y-1"
 >
 <div class="flex items-center justify-between font-medium">
 <span class="text-primary font-semibold">Assigned to: {{ a.assigned_user?.name || 'Officer' }} ({{ a.assigned_unit?.name || 'Unit' }})</span>
 <span v-if="a.due_at" class="text-xs text-amber-600 font-mono">Due: {{ formatDate(a.due_at) }}</span>
 </div>
 <div class="text-xs text-ink-secondary">
 {{ a.instruction }}
 </div>
 <div class="text-xs text-ink-muted font-mono">
 Assigned {{ formatDate(a.assigned_at) }}
 </div>
 </div>
 </div>
 </div>

 <!-- Outgoing Dispatches -->
 <div v-if="inspectingCorr.dispatches && inspectingCorr.dispatches.length > 0">
 <div class="text-xs font-semibold   text-ink-muted mb-2">
 Official Dispatches & Transmittals ({{ inspectingCorr.dispatches.length }})
 </div>
 <div class="space-y-2">
 <div
 v-for="d in inspectingCorr.dispatches"
 :key="d.id"
 class="p-2.5 rounded-lg bg-emerald-50 dark:bg-[#14301d] border border-emerald-200 dark:border-emerald-800 text-xs space-y-1"
 >
 <div class="flex items-center justify-between font-medium text-emerald-800 dark:text-emerald-300">
 <span>Method: {{ d.method?.name || 'Official Dispatch' }}</span>
 <span class="text-xs font-mono">{{ formatDate(d.dispatched_at) }}</span>
 </div>
 <div v-if="d.tracking_reference" class="text-xs font-mono text-emerald-700 dark:text-emerald-400">
 Tracking Ref: {{ d.tracking_reference }}
 </div>
 <div class="text-xs text-ink-muted">
 Dispatched by {{ d.dispatched_by_user?.name || 'Authorized Officer' }}
 </div>
 </div>
 </div>
 </div>

 <div class="flex items-center justify-between pt-2 border-t border-hairline">
 <BaseButton
 v-if="inspectingCorr.document_id"
 variant="primary"
 size="sm"
 @click="router.push(`/documents/${inspectingCorr.document_id}`)"
 >
 <FileText class="w-3.5 h-3.5 mr-1.5" /> Open Full Document File
 </BaseButton>
 <BaseButton variant="ghost" size="sm" @click="showInspectModal = false">Close</BaseButton>
 </div>
 </div>
 </ModalDialog>

 <!-- Formal Closure Modal -->
 <ModalDialog
   :show="showCloseModal"
   title="Formally Close Correspondence"
   max-width="md"
   @close="showCloseModal = false"
 >
   <form v-if="activeCorrespondence" @submit.prevent="submitCloseCorrespondence" class="space-y-4">
     <div class="p-3 rounded-lg bg-surface-hover border border-hairline text-xs space-y-1">
       <div class="font-bold text-ink">
         {{ activeCorrespondence.document?.title || 'Correspondence Record' }}
       </div>
       <div class="font-mono text-ink-muted">
         {{ activeCorrespondence.document?.reference_number || activeCorrespondence.external_reference }}
       </div>
     </div>

     <!-- Closure Prerequisites Warning Banner -->
     <div
       v-if="activeCorrespondence.closure_blockers && activeCorrespondence.closure_blockers.length > 0"
       class="p-3 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-700 text-xs text-amber-900 dark:text-amber-200 space-y-1.5"
     >
       <div class="font-semibold flex items-center gap-1.5">
         <AlertCircle class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0" />
         <span>Closure Prerequisites Unfulfilled</span>
       </div>
       <p class="text-xs text-amber-800 dark:text-amber-300">
         This outgoing correspondence cannot be formally closed until required dispatches are delivered and verified:
       </p>
       <ul class="list-disc list-inside space-y-0.5 pl-1 text-xs font-mono">
         <li v-for="(blocker, idx) in activeCorrespondence.closure_blockers" :key="idx">
           {{ blocker }}
         </li>
       </ul>
       <div class="text-xs font-medium text-amber-700 dark:text-amber-400 pt-1 border-t border-amber-200 dark:border-amber-800">
         Rule: Dispatch != Delivery. Record signed proof-of-delivery or verified transmission first.
       </div>
     </div>

     <div>
       <label class="block text-xs font-semibold   text-ink-secondary mb-1">
         Formal Closure Remarks &amp; Resolution Note
       </label>
       <BaseTextarea
         v-model="closeReason"
         rows="3"
         placeholder="Document the resolution, directive completion, or filing rationale..."
       />
     </div>

     <div class="flex items-center justify-end gap-3 pt-2">
       <BaseButton variant="outline" type="button" @click="showCloseModal = false">Cancel</BaseButton>
       <BaseButton
         variant="primary"
         type="submit"
         :loading="isSubmittingClose"
         :disabled="activeCorrespondence.can_close === false || (activeCorrespondence.closure_blockers && activeCorrespondence.closure_blockers.length > 0)"
         class="bg-rose-600 hover:bg-rose-700 text-white disabled:opacity-50 disabled:cursor-not-allowed"
       >
         Confirm Closure
       </BaseButton>
     </div>
   </form>
 </ModalDialog>
 </div>
</template>

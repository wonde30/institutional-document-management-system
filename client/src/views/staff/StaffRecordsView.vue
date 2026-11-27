<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { institutionalApi, organizationUnitsApi } from '@/api/client'
import type { OrganizationUnit } from '@/types'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import ModalDialog from '@/components/ModalDialog.vue'
import LoadingSkeleton from '@/components/ui/LoadingSkeleton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import PageHeader from '@/components/PageHeader.vue'
import BaseAlert from '@/components/ui/BaseAlert.vue'
import {
 Users,
 Plus,
 RefreshCw,
 Search,
 Trash2,
 CheckCircle2,
 AlertTriangle
} from 'lucide-vue-next'

const authStore = useAuthStore()
const toast = useToast()
const loading = ref(true)
const isSubmitting = ref(false)
const units = ref<OrganizationUnit[]>([])

interface StaffRecordItem {
 id: number
 document_id: number
 document?: {
 id: number
 reference_number: string
 title: string
 }
 physical_reference: string
 retention_requirement: 'required' | 'not_required' | 'unknown'
 custody_events?: Array<{
 id: number
 action: string
 reason?: string | null
 occurred_at: string
 location?: { id: number; code: string; name: string } | null
 }>
 digitization_records?: Array<{
 id: number
 verification_status: string
 verified_at?: string | null
 verification_note?: string | null
 }>
}

const recordsList = ref<StaffRecordItem[]>([])

const searchQuery = ref('')
const filterRetention = ref('')
const showModal = ref(false)
const showDisposalModal = ref(false)
const showVerifyModal = ref(false)
const selectedRecord = ref<StaffRecordItem | null>(null)
const disposalOrderRef = ref('')
const disposalReason = ref('')
const senateDisposalOrder = ref('')
const isSubmittingDisposal = ref(false)
const verifyStatus = ref<'accepted' | 'rejected'>('accepted')
const verifyNote = ref('')
const isSubmittingVerify = ref(false)

const form = ref({
 title: '',
 office_id: '' as number | '',
 location_description: 'Human Resources Registry Room 12 / Cabinet 4',
 physical_retention: 'required' as 'required' | 'not_required' | 'unknown',
 physical_reference: '',
})

async function loadData() {
 loading.value = true
 try {
 const [staffRes, unitsRes] = await Promise.all([
 institutionalApi.staffRecords(),
 organizationUnitsApi.list({ per_page: 100 }),
 ])

 recordsList.value = staffRes.data.data || []
 units.value = unitsRes.data.data || []
 if (!form.value.office_id && units.value.length > 0) {
  form.value.office_id = authStore.activeUnitId || authStore.primaryUnit?.unit_id || units.value[0].id
 }
 } catch (err: any) {
 toast.error(err.response?.data?.message || 'Failed to retrieve staff records.')
 } finally {
 loading.value = false
 }
}

const filteredList = computed(() => {
 return recordsList.value.filter((item) => {
 const title = item.document?.title || ''
 const ref = item.document?.reference_number || ''
 const itemCode = item.physical_reference || ''
 const matchesSearch =
 !searchQuery.value ||
 title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
 ref.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
 itemCode.toLowerCase().includes(searchQuery.value.toLowerCase())

 const matchesRetention = !filterRetention.value || item.retention_requirement === filterRetention.value
 return matchesSearch && matchesRetention
 })
})

function latestScan(item: StaffRecordItem) {
 const records = item.digitization_records ?? []
 if (records.length === 0) return null
 return records.reduce((latest, record) => (record.id > latest.id ? record : latest))
}

function isDisposed(item: StaffRecordItem): boolean {
 return item.custody_events?.some((event) => event.action === 'destroy') ?? false
}

function latestLocation(item: StaffRecordItem): string {
 const withLocation = item.custody_events?.find((event) => event.location)
 return withLocation?.location?.name || 'Not recorded'
}

function openVerifyModal(item: StaffRecordItem) {
 selectedRecord.value = item
 verifyStatus.value = 'accepted'
 verifyNote.value = ''
 showVerifyModal.value = true
}

async function handleVerifyScan() {
 if (!selectedRecord.value) return
 isSubmittingVerify.value = true
 try {
 await institutionalApi.verifyStaffScan(selectedRecord.value.id, {
 verification_status: verifyStatus.value,
 verification_note: verifyNote.value.trim() || undefined,
 })
 toast.success('Scan verification recorded. A verified scan does NOT authorize physical destruction.')
 showVerifyModal.value = false
 await loadData()
 } catch (err: any) {
 toast.error(err.response?.data?.message || 'Verification update failed.')
 } finally {
 isSubmittingVerify.value = false
 }
}

function openDisposalModal(item: StaffRecordItem) {
 selectedRecord.value = item
 disposalOrderRef.value = ''
 disposalReason.value = ''
 senateDisposalOrder.value = ''
 showDisposalModal.value = true
}

async function handleExecuteDisposal() {
 if (!selectedRecord.value) return
 if (!disposalOrderRef.value.trim()) {
 toast.error('Legal Counsel or Senate authorization order reference is required.')
 return
 }
 if (!disposalReason.value.trim()) {
 toast.error('A disposal reason is required for the audit record.')
 return
 }
 if (
 selectedRecord.value.retention_requirement === 'required' &&
 !senateDisposalOrder.value.trim()
 ) {
 toast.error('An executive Senate/Legal Counsel disposal order is required for retention-required records.')
 return
 }
 isSubmittingDisposal.value = true
 try {
 await institutionalApi.disposeStaffPhysical(selectedRecord.value.id, {
 authorization_reference: disposalOrderRef.value.trim(),
 reason: disposalReason.value.trim(),
 executive_senate_disposal_order: senateDisposalOrder.value.trim() || undefined,
 })
 toast.success('Physical original disposal logged and executed under official authorization.')
 showDisposalModal.value = false
 await loadData()
 } catch (err: any) {
 toast.error(err.response?.data?.message || 'Physical disposal rejected.')
 } finally {
 isSubmittingDisposal.value = false
 }
}

async function handleCreate() {
 if (!form.value.title.trim()) {
 toast.error('Document title is required.')
 return
 }

 isSubmitting.value = true
 try {
 await institutionalApi.storeStaffRecord({
 title: form.value.title.trim(),
 office_id: Number(form.value.office_id),
 physical_retention: form.value.physical_retention,
 physical_reference: form.value.physical_reference || undefined,
 location_description: form.value.location_description,
 })
 toast.success('Staff electronic record & physical inventory entry committed.')
 showModal.value = false
 form.value.title = ''
 form.value.physical_reference = ''
 await loadData()
 } catch (err: any) {
 toast.error(err.response?.data?.message || 'Failed to record staff inventory item.')
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
 
 <PageHeader title="Staff Records & Physical Original Custody" subtitle="Dual tracking of electronic staff dossiers alongside physical paper original location, custodian, and retention rules.">

  <template #actions>

 <BaseButton variant="outline" size="sm" :loading="loading" @click="loadData">
 <RefreshCw class="w-4 h-4 mr-1.5" />
 Refresh
 </BaseButton>

 <BaseButton
 v-if="authStore.hasPermission('staff.update') || authStore.hasPermission('system.configure')"
 variant="primary"
 size="sm"
 @click="showModal = true"
 >
 <Plus class="w-4 h-4 mr-1.5" />
 Register Original Record
 </BaseButton>
 

  </template>

 </PageHeader>

 <!-- Institutional Non-Destruction Invariant -->
 <div class="p-3.5 rounded-lg bg-[#fff4e6] dark:bg-[#382412] border border-[#dd5b00]/30 text-xs text-[#dd5b00] dark:text-[#ff9243] flex items-start gap-2.5">
 <AlertTriangle class="w-4 h-4 shrink-0 mt-0.5" />
 <p class="leading-relaxed">
 <strong>Institutional Retention Invariant:</strong> A verified electronic scan <strong>never authorizes destruction</strong> of any physical original. Scanned copies are not labeled legally equivalent without confirmed statutory grounds. Physical disposal is a distinct authorized action requiring official Senate/Legal Counsel order.
 </p>
 </div>

 <!-- Physical Originals Table -->
 <section class="bg-surface rounded-lg border border-hairline overflow-hidden">
 <div class="p-3 sm:p-4 border-b border-hairline flex flex-col sm:flex-row items-center justify-between gap-3">
 <div class="relative w-full sm:w-80">
 <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
 <input
 v-model="searchQuery"
 type="search"
 placeholder="Search by personnel title or physical code..."
 class="w-full pl-9 pr-3 py-1.5 text-xs rounded-md border border-hairline bg-surface text-ink-secondary focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 />
 </div>

 <div class="flex items-center gap-2 w-full sm:w-auto">
 <select
 v-model="filterRetention"
 class="text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-3 py-1.5 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 >
 <option value="">All retention rules</option>
 <option value="required">Physical retention required</option>
 <option value="not_required">Physical retention not required</option>
 <option value="unknown">Retention not yet determined (Unknown)</option>
 </select>
 </div>
 </div>

 <LoadingSkeleton v-if="loading" :count="4" class="p-4" />

 <div v-else class="overflow-x-auto">
 <table class="w-full text-left text-xs border-collapse">
 <thead class="bg-canvas-soft text-ink-muted font-semibold border-b border-hairline">
 <tr>
 <th class="py-3 px-4">Staff Record / Physical Code</th>
 <th class="py-3 px-4">Storage Location</th>
 <th class="py-3 px-4">Physical Retention Status</th>
 <th class="py-3 px-4">Scan Verification</th>
 <th class="py-3 px-4 text-right">Authorized Actions</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-hairline">
 <tr
 v-for="item in filteredList"
 :key="item.id"
 class="hover:bg-surface-hover transition-colors"
 >
 <td class="py-3 px-4 max-w-[200px] sm:max-w-xs md:max-w-md">
 <div class="font-medium text-ink-secondary truncate" :title="item.document?.title">
 {{ item.document?.title }}
 </div>
 <small class="block text-xs text-primary font-mono mt-0.5 truncate">
 Physical code: {{ item.physical_reference }}
 </small>
 </td>
 <td class="py-3 px-4 text-ink-muted">
 {{ latestLocation(item) }}
 </td>
 <td class="py-3 px-4">
 <span
 class="px-2 py-0.5 rounded text-xs font-semibold"
 :class="{
 'bg-[#fff4e6] text-[#dd5b00] dark:bg-[#382412] dark:text-[#ff9243]': item.retention_requirement === 'required',
 'bg-[#e8f7ec] text-[#1aae39] dark:bg-[#183321] dark:text-[#42cf63]': item.retention_requirement === 'not_required',
 'bg-surface-muted text-ink-muted ': item.retention_requirement === 'unknown',
 }"
 >
 {{ item.retention_requirement.replace('_', ' ') }}
 </span>
 <span v-if="isDisposed(item)" class="ml-1.5 px-1.5 py-0.5 rounded text-xs bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 font-bold">
 Disposed
 </span>
 </td>
 <td class="py-3 px-4">
 <span
 v-if="latestScan(item)"
 class="inline-flex items-center gap-1 text-xs font-medium"
 :class="latestScan(item)?.verification_status === 'accepted'
 ? 'text-emerald-600 dark:text-emerald-400'
 : latestScan(item)?.verification_status === 'rejected'
 ? 'text-rose-600 dark:text-rose-400'
 : 'text-amber-600 dark:text-amber-400'"
 >
 <CheckCircle2 class="w-3.5 h-3.5" />
 {{ latestScan(item)?.verification_status }}
 </span>
 <span v-else class="text-xs text-ink-muted">No scan on file</span>
 </td>
 <td class="py-3 px-4 text-right">
 <div class="flex items-center justify-end gap-2">
 <button
 v-if="latestScan(item) && (authStore.hasPermission('staff.update') || authStore.hasPermission('system.configure'))"
 type="button"
 class="text-xs font-medium text-primary hover:underline"
 @click="openVerifyModal(item)"
 title="Record scan verification"
 >
 Verify Scan
 </button>

 <button
 v-if="!isDisposed(item) && (authStore.hasPermission('archive.approve_disposal') || authStore.hasPermission('system.configure'))"
 type="button"
 class="text-xs font-semibold text-rose-600 dark:text-rose-400 hover:underline inline-flex items-center gap-1"
 @click="openDisposalModal(item)"
 title="Request authorized physical disposal"
 >
 <Trash2 class="w-3 h-3" /> Dispose Physical
 </button>
 </div>
 </td>
 </tr>

 <tr v-if="filteredList.length === 0">
 <td colspan="5" class="py-8 text-center text-xs text-ink-muted">
 No physical original records match these criteria within your authorized scope.
 </td>
 </tr>
 </tbody>
 </table>
 </div>
 </section>

 <!-- F-STAFF / Physical Modal -->
 <ModalDialog
 :open="showModal"
 title="Register Physical Original & Dossier Scan"
 max-width="lg"
 @close="showModal = false"
 >
 <div class="space-y-4 text-xs">
 <div>
 <label class="block font-semibold text-ink-secondary mb-1">
 Personnel Record Title *
 </label>
 <BaseInput
 v-model="form.title"
 placeholder="e.g. Appointment Letter & Degree Authentications · Dr. Abebe Bekele"
 />
 </div>

 <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
 <div>
 <label class="block font-semibold text-ink-secondary mb-1">
 Custody Office *
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
 Physical Retention Rule *
 </label>
 <select
 v-model="form.physical_retention"
 class="w-full text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 >
 <option value="required">Physical retention required (Permanent/Statutory)</option>
 <option value="unknown">Retention not yet determined (Unknown)</option>
 <option value="not_required">Physical retention not required</option>
 </select>
 </div>
 </div>

 <div>
 <label class="block font-semibold text-ink-secondary mb-1">
 Physical Reference Code
 </label>
 <BaseInput
 v-model="form.physical_reference"
 placeholder="e.g. HR-PHY-2026-0041"
 />
 </div>

 <div>
 <label class="block font-semibold text-ink-secondary mb-1">
 Custody Location Note
 </label>
 <BaseInput
 v-model="form.location_description"
 placeholder="e.g. Cabinet A / Shelf 2 / Folder 41"
 />
 </div>

 <div class="p-3 rounded-md bg-canvas-soft border border-hairline text-ink-muted">
 Digital scans are attached through the document record's digitization action. Registering a physical original here records custody and retention only.
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

 <!-- Scan Verification Modal -->
 <ModalDialog
 :open="showVerifyModal"
 title="Record Scan Verification"
 @close="showVerifyModal = false"
 >
 <form @submit.prevent="handleVerifyScan" class="space-y-4 text-xs">
 <p class="text-ink-muted leading-relaxed">
 Confirm whether the digitized scan is a faithful reproduction of the physical original. Verification does not authorize disposal of the physical record.
 </p>

 <div>
 <label class="block font-semibold text-ink-secondary mb-1">
 Verification Decision *
 </label>
 <select
 v-model="verifyStatus"
 class="w-full text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 >
 <option value="accepted">Accepted — scan is legible and complete</option>
 <option value="rejected">Rejected — rescan required</option>
 </select>
 </div>

 <div>
 <label class="block font-semibold text-ink-secondary mb-1">
 Verification Note
 </label>
 <BaseInput
 v-model="verifyNote"
 placeholder="Optional note recorded in the audit ledger"
 />
 </div>

 <div class="pt-2 flex justify-end gap-2">
 <BaseButton variant="outline" size="sm" type="button" @click="showVerifyModal = false">Cancel</BaseButton>
 <BaseButton
 variant="primary"
 size="sm"
 type="submit"
 :loading="isSubmittingVerify"
 >
 Save Verification
 </BaseButton>
 </div>
 </form>
 </ModalDialog>

 <!-- Physical Disposal Modal -->
 <ModalDialog
 :open="showDisposalModal"
 title="Authorize Physical Record Disposal"
 @close="showDisposalModal = false"
 >
 <form @submit.prevent="handleExecuteDisposal" class="space-y-4 text-xs">
 <div class="p-3.5 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-800 dark:text-rose-200 space-y-1">
 <div class="font-bold flex items-center gap-1.5">
 <AlertTriangle class="w-4 h-4 shrink-0" />
 Statutory Physical Disposal Barrier
 </div>
 <p class="text-xs leading-relaxed">
 Physical shredding of paper records marked "retention required" is barred without official Senate Executive or Legal Counsel order. An independent verification certificate will be recorded in the audit ledger.
 </p>
 </div>

 <div>
 <label class="block font-semibold text-ink-secondary mb-1">
 Legal / Senate Disposal Order Reference *
 </label>
 <BaseInput
 v-model="disposalOrderRef"
 placeholder="e.g. WU/SENATE/DISP/2026/012"
 required
 />
 </div>

 <div>
 <label class="block font-semibold text-ink-secondary mb-1">
 Disposal Reason *
 </label>
 <BaseInput
 v-model="disposalReason"
 placeholder="Reason recorded in the custody and audit ledger"
 required
 />
 </div>

 <div v-if="selectedRecord?.retention_requirement === 'required'">
 <label class="block font-semibold text-ink-secondary mb-1">
 Executive Senate / Legal Counsel Disposal Order *
 </label>
 <BaseInput
 v-model="senateDisposalOrder"
 placeholder="Order identifier or official directive reference"
 required
 />
 <p class="text-xs text-ink-muted mt-1">
 This record requires physical retention. Disposal is only permitted with an explicit executive disposal order.
 </p>
 </div>

 <div class="pt-2 flex justify-end gap-2">
 <BaseButton variant="outline" size="sm" type="button" @click="showDisposalModal = false">Cancel</BaseButton>
 <BaseButton
 variant="primary"
 size="sm"
 class="!bg-rose-700 hover:!bg-rose-800 text-white"
 type="submit"
 :loading="isSubmittingDisposal"
 >
 Confirm Authorized Disposal
 </BaseButton>
 </div>
 </form>
 </ModalDialog>
 </div>
</template>

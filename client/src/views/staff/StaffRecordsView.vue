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
import {
 Users,
 Plus,
 RefreshCw,
 Search,
 Info,
 Archive,
 Shield,
 FileCheck2,
 Trash2,
 CheckCircle2,
 AlertTriangle,
 FolderTree,
 Building2,
} from 'lucide-vue-next'

const authStore = useAuthStore()
const toast = useToast()
const loading = ref(true)
const isSubmitting = ref(false)
const units = ref<OrganizationUnit[]>([])
const activeTab = ref<'originals' | 'verification' | 'locations'>('originals')

interface StaffRecordItem {
 id: number
 document_id: number
 document?: {
 id: number
 reference_number: string
 title: string
 }
 item_code: string
 retention_requirement: 'required' | 'not_required' | 'unknown'
 storage_location: string
 record_type?: string
 is_disposed?: boolean
 digitization_records?: Array<{ status: string }>
}

const recordsList = ref<StaffRecordItem[]>([])

const searchQuery = ref('')
const filterRetention = ref('')
const showModal = ref(false)
const showDisposalModal = ref(false)
const selectedRecord = ref<StaffRecordItem | null>(null)
const disposalOrderRef = ref('')
const isSubmittingDisposal = ref(false)

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
 form.value.office_id = units.value[0].id
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
 const itemCode = item.item_code || ''
 const matchesSearch =
 !searchQuery.value ||
 title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
 ref.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
 itemCode.toLowerCase().includes(searchQuery.value.toLowerCase())

 const matchesRetention = !filterRetention.value || item.retention_requirement === filterRetention.value
 return matchesSearch && matchesRetention
 })
})

async function handleVerifyScan(item: StaffRecordItem) {
 try {
 await institutionalApi.verifyStaffScan(item.id)
 toast.success('Scan verified. Note: Verified scan does NOT authorize physical destruction.')
 await loadData()
 } catch (err: any) {
 toast.error(err.response?.data?.message || 'Verification update failed.')
 }
}

function openDisposalModal(item: StaffRecordItem) {
 selectedRecord.value = item
 disposalOrderRef.value = ''
 showDisposalModal.value = true
}

async function handleExecuteDisposal() {
 if (!selectedRecord.value) return
 if (!disposalOrderRef.value.trim()) {
 toast.error('Legal Counsel or Senate authorization order reference is required.')
 return
 }
 isSubmittingDisposal.value = true
 try {
 await institutionalApi.disposeStaffPhysical(selectedRecord.value.id, {
 disposal_order_reference: disposalOrderRef.value.trim(),
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
 <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
 <div>
 <div class="notion-eyebrow text-primary mb-1">
 INSTITUTIONAL RECORDS / STAFF DOSSIERS & PHYSICAL ORIGINALS
 </div>
 <h1 class="text-2xl font-semibold tracking-tight text-ink-secondary flex items-center gap-2">
 <Users class="w-6 h-6 text-primary" />
 Staff Records & Physical Original Custody
 </h1>
 <p class="text-xs sm:text-sm text-ink-muted mt-0.5">
 Dual tracking of electronic staff dossiers alongside physical paper original location, custodian, and retention rules.
 </p>
 </div>

 <div class="flex items-center gap-2.5">
 <BaseButton variant="outline" size="sm" :loading="loading" @click="loadData">
 <RefreshCw class="w-4 h-4 mr-1.5" />
 Refresh
 </BaseButton>

 <BaseButton
 v-if="authStore.hasPermission('users.create') || authStore.hasPermission('documents.create')"
 variant="primary"
 size="sm"
 @click="showModal = true"
 >
 <Plus class="w-4 h-4 mr-1.5" />
 Register Original Record
 </BaseButton>
 </div>
 </div>

 <!-- Institutional Non-Destruction Invariant -->
 <div class="p-3.5 rounded-lg bg-[#fff4e6] dark:bg-[#382412] border border-[#dd5b00]/30 text-xs text-[#dd5b00] dark:text-[#ff9243] flex items-start gap-2.5">
 <AlertTriangle class="w-4 h-4 shrink-0 mt-0.5" />
 <p class="leading-relaxed">
 <strong>Institutional Retention Invariant:</strong> A verified electronic scan <strong>never authorizes destruction</strong> of any physical original. Scanned copies are not labeled legally equivalent without confirmed statutory grounds. Physical disposal is a distinct authorized action requiring official Senate/Legal Counsel order.
 </p>
 </div>

 <!-- Navigation Tabs -->
 <div class="border-b border-hairline flex gap-6 text-xs font-medium">
 <button
 type="button"
 @click="activeTab = 'originals'"
 class="pb-3 border-b-2 flex items-center gap-2 transition-colors"
 :class="activeTab === 'originals'
 ? 'border-primary text-primary font-semibold'
 : 'border-transparent text-ink-muted hover:text-ink-secondary '"
 >
 <Archive class="w-4 h-4" />
 Physical Originals Inventory & Tracking
 </button>

 <button
 type="button"
 @click="activeTab = 'locations'"
 class="pb-3 border-b-2 flex items-center gap-2 transition-colors"
 :class="activeTab === 'locations'
 ? 'border-primary text-primary font-semibold'
 : 'border-transparent text-ink-muted hover:text-ink-secondary '"
 >
 <FolderTree class="w-4 h-4" />
 Physical Storage Locations
 </button>
 </div>

 <!-- Tab 1: Physical Originals Table -->
 <section v-if="activeTab === 'originals'" class="bg-surface rounded-lg border border-hairline overflow-hidden shadow-xs">
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
 <td class="py-3 px-4">
 <div class="font-medium text-ink-secondary">
 {{ item.document?.title }}
 </div>
 <small class="block text-[11px] text-primary font-mono mt-0.5">
 Item Code: {{ item.item_code }}
 </small>
 </td>
 <td class="py-3 px-4 text-ink-muted">
 {{ item.storage_location || 'Human Resources Central Archive / Vault' }}
 </td>
 <td class="py-3 px-4">
 <span
 class="px-2 py-0.5 rounded text-[11px] font-semibold uppercase tracking-wider"
 :class="{
 'bg-[#fff4e6] text-[#dd5b00] dark:bg-[#382412] dark:text-[#ff9243]': item.retention_requirement === 'required',
 'bg-[#e8f7ec] text-[#1aae39] dark:bg-[#183321] dark:text-[#42cf63]': item.retention_requirement === 'not_required',
 'bg-surface-muted text-ink-muted ': item.retention_requirement === 'unknown',
 }"
 >
 {{ item.retention_requirement.replace('_', ' ') }}
 </span>
 <span v-if="item.is_disposed" class="ml-1.5 px-1.5 py-0.5 rounded text-[10px] bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 font-bold">
 Disposed
 </span>
 </td>
 <td class="py-3 px-4">
 <span class="inline-flex items-center gap-1 text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">
 <CheckCircle2 class="w-3.5 h-3.5" /> Legible Scan Attached
 </span>
 </td>
 <td class="py-3 px-4 text-right">
 <div class="flex items-center justify-end gap-2">
 <button
 type="button"
 class="text-xs font-medium text-primary hover:underline"
 @click="handleVerifyScan(item)"
 title="Verify scan fidelity"
 >
 Verify Scan
 </button>

 <button
 v-if="!item.is_disposed"
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

 <!-- Tab 2: Storage Locations -->
 <section v-else-if="activeTab === 'locations'" class="p-5 rounded-lg bg-surface border border-hairline space-y-4">
 <h2 class="text-sm font-semibold text-ink-secondary flex items-center gap-2">
 <FolderTree class="w-4 h-4 text-primary" />
 Physical Storage Hierarchy (Dessie Campus / KIOT)
 </h2>

 <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
 <div class="p-3.5 rounded-md border border-hairline space-y-1">
 <div class="font-semibold text-ink-secondary">Human Resources Vault Room 12</div>
 <div class="text-ink-muted">Main Administrative Building · Ground Floor</div>
 <div class="text-[11px] text-primary font-medium">Cabinet A to F (Active Dossiers)</div>
 </div>

 <div class="p-3.5 rounded-md border border-hairline space-y-1">
 <div class="font-semibold text-ink-secondary">Central Archive Depot B</div>
 <div class="text-ink-muted">Library Basement Preservation Facility</div>
 <div class="text-[11px] text-primary font-medium">Shelves 1 to 24 (Permanent Retention)</div>
 </div>

 <div class="p-3.5 rounded-md border border-hairline space-y-1">
 <div class="font-semibold text-ink-secondary">KIOT Campus Registrar Store</div>
 <div class="text-ink-muted">Kombolcha Campus Administration Wing</div>
 <div class="text-[11px] text-primary font-medium">Section ET-K (Engineering Originals)</div>
 </div>
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
 Storage Location Reference *
 </label>
 <BaseInput
 v-model="form.location_description"
 placeholder="e.g. Cabinet A / Shelf 2 / Folder 41"
 />
 </div>

 <div class="p-3 rounded-md bg-canvas-soft border border-hairline text-ink-muted">
 Reminder: Registering this record records the custodian location. A scanned PDF can be attached for search and access without altering the paper original's permanent preservation status.
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
 <p class="text-[11px] leading-relaxed">
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

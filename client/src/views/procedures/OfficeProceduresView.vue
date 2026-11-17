<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { institutionalApi, organizationUnitsApi, metadataApi } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import BaseButton from '@/components/ui/BaseButton.vue'
import {
 GitBranch,
 Plus,
 Search,
 CheckCircle2,
 Clock,
 ShieldAlert,
 Building2,
 FileCheck,
 X,
} from 'lucide-vue-next'

const toast = useToast()
const authStore = useAuthStore()
const loading = ref(true)
const procedures = ref<any[]>([])
const units = ref<any[]>([])
const correspondenceTypes = ref<any[]>([])

const searchQuery = ref('')
const selectedUnit = ref('')
const isModalOpen = ref(false)
const submitting = ref(false)
const errorMessage = ref('')

const form = ref({
 unit_id: '',
 correspondence_type_id: '',
 purpose_code: 'general_correspondence',
 receipt_mode: 'direct_office',
 registry_unit_id: '',
 issue_mode: 'workflow_review',
 confirmation_source: '',
 effective_from: new Date().toISOString().split('T')[0],
})

async function fetchData() {
 loading.value = true
 errorMessage.value = ''
 try {
 const [procRes, orgRes, metaRes] = await Promise.all([
 institutionalApi.procedures(),
 organizationUnitsApi.list({ per_page: 50 }),
 metadataApi.getCorrespondenceTypes(),
 ])

 if (procRes.data.success) {
 procedures.value = procRes.data.data || []
 }

 if (orgRes.data.data) {
 units.value = orgRes.data.data
 }

 if (metaRes.data.data) {
 correspondenceTypes.value = metaRes.data.data
 }
 } catch (err: any) {
 errorMessage.value = 'Failed to load office procedures registry.'
 } finally {
 loading.value = false
 }
}

onMounted(() => {
 fetchData()
})

const filteredProcedures = computed(() => {
 return procedures.value.filter((p) => {
 const matchesSearch =
 !searchQuery.value ||
 p.unit?.name?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
 p.purpose_code?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
 p.confirmation_source?.toLowerCase().includes(searchQuery.value.toLowerCase())

 const matchesUnit = !selectedUnit.value || p.unit_id === Number(selectedUnit.value)

 return matchesSearch && matchesUnit
 })
})

function openCreateModal() {
 const preferredUnit = authStore.activeUnitId ? String(authStore.activeUnitId) : (authStore.primaryUnit?.unit_id ? String(authStore.primaryUnit.unit_id) : (units.value[0]?.id ? String(units.value[0].id) : ''))
 form.value = {
 unit_id: preferredUnit,
 correspondence_type_id: correspondenceTypes.value[0]?.id ? String(correspondenceTypes.value[0].id) : '1',
 purpose_code: 'general_correspondence',
 receipt_mode: 'direct_office',
 registry_unit_id: '',
 issue_mode: 'workflow_review',
 confirmation_source: 'Academic Council Resolution / Executive Directive',
 effective_from: new Date().toISOString().split('T')[0],
 }
 isModalOpen.value = true
}

async function submitProcedure() {
 if (!form.value.unit_id || !form.value.correspondence_type_id || !form.value.confirmation_source) {
 toast.error('Validation Error', 'Please fill in all required fields.')
 return
 }

 submitting.value = true
 try {
 const res = await institutionalApi.storeProcedure({
 unit_id: Number(form.value.unit_id),
 correspondence_type_id: Number(form.value.correspondence_type_id),
 purpose_code: form.value.purpose_code,
 receipt_mode: form.value.receipt_mode,
 registry_unit_id: form.value.receipt_mode === 'designated_registry' && form.value.registry_unit_id ? Number(form.value.registry_unit_id) : null,
 issue_mode: form.value.issue_mode,
 confirmation_source: form.value.confirmation_source,
 effective_from: form.value.effective_from,
 })

 if (res.data.success) {
 toast.success('Procedure Saved', 'Office procedure directive confirmed and stored.')
 isModalOpen.value = false
 await fetchData()
 }
 } catch (err: any) {
 toast.error('Registration Failed', err.response?.data?.message || err.message || 'Failed to register procedure.')
 } finally {
 submitting.value = false
 }
}
</script>

<template>
 <div class="space-y-6">
 <!-- Header -->
 <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-hairline pb-4">
 <div>
 <h1 class="text-xl sm:text-2xl font-bold text-ink tracking-tight flex items-center gap-2.5">
 <GitBranch class="w-5 h-5 sm:w-6 sm:h-6 text-primary shrink-0" />
 <span>Office Procedure Directives</span>
 </h1>
 <p class="text-xs text-ink-muted mt-0.5">
 Confirmed receipt and issue routing policies governing institutional correspondence channels.
 </p>
 </div>

 <div class="flex items-center gap-2">
 <BaseButton
 variant="primary"
 size="sm"
 @click="openCreateModal"
 >
 <Plus class="w-4 h-4 mr-1.5" />
 <span>Add Confirmed Procedure</span>
 </BaseButton>
 </div>
 </div>

 <!-- Institutional Policy Notice -->
 <div class="wu-notice">
 <div class="flex items-start gap-3">
 <ShieldAlert class="w-5 h-5 text-amber-700 dark:text-amber-300 shrink-0 mt-0.5" />
 <div class="text-xs text-amber-900 dark:text-amber-100">
 <strong class="font-semibold block mb-0.5">Policy Confirmation Invariant</strong>
 A preview or draft entry cannot confirm an actual university correspondence policy. Outgoing letters are strictly held until an active, confirmed procedure version authorizes direct issue or departmental review. Unconfirmed issue is blocked server-side.
 </div>
 </div>
 </div>

 <!-- Metric Cards -->
 <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
 <div class="bg-surface border border-hairline rounded-xl p-4 shadow-xs">
 <div class="flex items-center justify-between">
 <span class="text-xs font-semibold text-ink-muted">Confirmed Active Procedures</span>
 <GitBranch class="w-4 h-4 text-primary" />
 </div>
 <div class="text-2xl font-bold text-ink-secondary mt-2">
 {{ procedures.length }}
 </div>
 <div class="text-xs text-ink-muted mt-0.5">Enforced routing configurations</div>
 </div>

 <div class="bg-surface border border-hairline rounded-xl p-4 shadow-xs">
 <div class="flex items-center justify-between">
 <span class="text-xs font-semibold text-ink-muted">Designated Registry Enforced</span>
 <Building2 class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
 </div>
 <div class="text-2xl font-bold text-ink-secondary mt-2">
 {{ procedures.filter(p => p.receipt_mode === 'designated_registry').length }}
 </div>
 <div class="text-xs text-ink-muted mt-0.5">Central registry receipt gate</div>
 </div>

 <div class="bg-surface border border-hairline rounded-xl p-4 shadow-xs">
 <div class="flex items-center justify-between">
 <span class="text-xs font-semibold text-ink-muted">Direct Issue Authorities</span>
 <FileCheck class="w-4 h-4 text-primary" />
 </div>
 <div class="text-2xl font-bold text-ink-secondary mt-2">
 {{ procedures.filter(p => p.issue_mode === 'direct_issue').length }}
 </div>
 <div class="text-xs text-ink-muted mt-0.5">Authorized executive bypass rules</div>
 </div>
 </div>

 <!-- Filter Bar -->
 <div class="flex flex-col sm:flex-row items-center gap-3">
 <div class="relative flex-1 w-full">
 <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
 <input
 v-model="searchQuery"
 type="text"
 placeholder="Search by office, purpose, or confirmation source..."
 class="w-full pl-9 pr-3 py-1.5 bg-surface border border-hairline rounded-lg text-xs text-ink-secondary focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"
 />
 </div>

 <select
 v-model="selectedUnit"
 class="w-full sm:w-56 py-1.5 px-3 bg-surface border border-hairline rounded-lg text-xs text-ink-secondary focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"
 >
 <option value="">All Institutional Offices</option>
 <option v-for="u in units" :key="u.id" :value="u.id">
 {{ u.name }}
 </option>
 </select>
 </div>

 <!-- Procedures Table -->
 <div class="bg-surface border border-hairline rounded-xl overflow-hidden shadow-xs">
 <div v-if="loading" class="p-8 text-center text-xs text-ink-muted">
 Loading office procedures ledger...
 </div>

 <div v-else class="overflow-x-auto">
 <table class="w-full text-left text-xs text-ink-secondary">
 <thead class="bg-canvas-soft border-b border-hairline text-xs font-semibold text-ink-muted uppercase tracking-wider">
 <tr>
 <th class="py-3 px-4">Procedure Version</th>
 <th class="py-3 px-4">Office & Type</th>
 <th class="py-3 px-4">Receipt Path</th>
 <th class="py-3 px-4">Issue Path</th>
 <th class="py-3 px-4">Confirmation Source</th>
 <th class="py-3 px-4 text-right">Effectivity</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-hairline">
 <tr
 v-for="proc in filteredProcedures"
 :key="proc.id"
 class="hover:bg-surface-hover transition"
 >
 <td class="py-3 px-4 font-mono font-medium text-primary">
 OP-{{ String(proc.unit_id).padStart(2, '0') }}-v{{ proc.version_number }}
 </td>
 <td class="py-3 px-4">
 <div class="font-semibold">{{ proc.unit?.name || 'Department' }}</div>
 <div class="text-xs text-ink-muted">
 {{ proc.correspondence_type?.name || 'Correspondence' }} · {{ proc.purpose_code }}
 </div>
 </td>
 <td class="py-3 px-4">
 <span
 class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium"
 :class="proc.receipt_mode === 'designated_registry' ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-200' : 'bg-canvas-soft text-ink-secondary'"
 >
 {{ proc.receipt_mode === 'designated_registry' ? 'Designated Registry' : 'Direct Office' }}
 </span>
 </td>
 <td class="py-3 px-4">
 <span
 class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium"
 :class="proc.issue_mode === 'direct_issue' ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300' : 'bg-primary-subtle text-primary'"
 >
 {{ proc.issue_mode === 'direct_issue' ? 'Direct Issue' : 'Workflow Review Required' }}
 </span>
 </td>
 <td class="py-3 px-4">
 <div class="text-ink-secondary font-medium">
 {{ proc.confirmation_source || 'University Senate Directive' }}
 </div>
 <div class="text-xs text-ink-muted">
 Confirmed by {{ proc.confirmed_by?.name || 'Authorized Officer' }}
 </div>
 </td>
 <td class="py-3 px-4 text-right">
 <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300">
 <CheckCircle2 class="w-3 h-3" />
 Active
 </span>
 </td>
 </tr>

 <tr v-if="filteredProcedures.length === 0">
 <td colspan="6" class="py-8 text-center text-xs text-ink-muted">
 No office procedures match the criteria.
 </td>
 </tr>
 </tbody>
 </table>
 </div>
 </div>

 <!-- Modal: Add Confirmed Procedure (F-PROCEDURE) -->
 <div
 v-if="isModalOpen"
 class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50 backdrop-blur-xs"
 @click.self="isModalOpen = false"
 >
 <div class="bg-surface border border-hairline rounded-xl max-w-lg w-full p-6 shadow-xl">
 <div class="flex items-center justify-between pb-3 border-b border-hairline">
 <div>
 <h2 class="text-sm font-bold text-ink-secondary">
 Add Confirmed Office Procedure (F-PROCEDURE)
 </h2>
 <p class="text-xs text-ink-muted">
 Enforce verified receipt and issue paths for departmental correspondence
 </p>
 </div>
 <button
 type="button"
 class="text-ink-muted hover:text-ink-secondary p-1 cursor-pointer"
 @click="isModalOpen = false"
 >
 <X class="w-4 h-4" />
 </button>
 </div>

 <form class="space-y-4 mt-4" @submit.prevent="submitProcedure">
 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Institutional Office *
 </label>
 <select
 v-model="form.unit_id"
 required
 class="w-full px-3 py-1.5 bg-surface border border-hairline rounded-lg text-xs text-ink-secondary focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"
 >
 <option value="" disabled>Select Office</option>
 <option v-for="u in units" :key="u.id" :value="u.id">
 {{ u.name }}
 </option>
 </select>
 </div>

 <div class="grid grid-cols-2 gap-3">
 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Correspondence Type *
 </label>
 <select
 v-model="form.correspondence_type_id"
 required
 class="w-full px-3 py-1.5 bg-surface border border-hairline rounded-lg text-xs text-ink-secondary focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"
 >
 <option v-for="t in correspondenceTypes" :key="t.id" :value="t.id">
 {{ t.name }}
 </option>
 </select>
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Purpose Code *
 </label>
 <input
 v-model="form.purpose_code"
 type="text"
 required
 placeholder="e.g. academic_affairs"
 class="w-full px-3 py-1.5 bg-surface border border-hairline rounded-lg text-xs text-ink-secondary focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"
 />
 </div>
 </div>

 <div class="grid grid-cols-2 gap-3">
 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Receipt Mode *
 </label>
 <select
 v-model="form.receipt_mode"
 required
 class="w-full px-3 py-1.5 bg-surface border border-hairline rounded-lg text-xs text-ink-secondary focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"
 >
 <option value="direct_office">Direct Office Receipt</option>
 <option value="designated_registry">Designated Registry</option>
 </select>
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Issue Mode *
 </label>
 <select
 v-model="form.issue_mode"
 required
 class="w-full px-3 py-1.5 bg-surface border border-hairline rounded-lg text-xs text-ink-secondary focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"
 >
 <option value="workflow_review">Workflow Review Required</option>
 <option value="direct_issue">Direct Issue Authorized</option>
 </select>
 </div>
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Confirmation Source *
 </label>
 <input
 v-model="form.confirmation_source"
 type="text"
 required
 placeholder="e.g. University Senate Council Directive #04/2026"
 class="w-full px-3 py-1.5 bg-surface border border-hairline rounded-lg text-xs text-ink-secondary focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"
 />
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Effective Date *
 </label>
 <input
 v-model="form.effective_from"
 type="date"
 required
 class="w-full px-3 py-1.5 bg-surface border border-hairline rounded-lg text-xs text-ink-secondary focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"
 />
 </div>

 <div class="flex items-center justify-end gap-2 pt-3 border-t border-hairline">
 <BaseButton
 variant="outline"
 size="sm"
 @click="isModalOpen = false"
 >
 Cancel
 </BaseButton>
 <BaseButton
 variant="primary"
 size="sm"
 :loading="submitting"
 type="submit"
 >
 Save Confirmed Procedure
 </BaseButton>
 </div>
 </form>
 </div>
 </div>
 </div>
</template>

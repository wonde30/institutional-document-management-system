<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { institutionalApi, organizationUnitsApi } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import LoadingSkeleton from '@/components/ui/LoadingSkeleton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import type { ProjectProposal, Project, ProjectType, OrganizationUnit } from '@/types'
import {
 FolderKanban,
 Plus,
 Search,
 Filter,
 AlertCircle,
 Clock,
 CheckCircle2,
 Building2,
 FileText,
 X,
 ShieldAlert,
} from 'lucide-vue-next'

const authStore = useAuthStore()
const toast = useToast()

// State
const loading = ref(true)
const activeTab = ref<'proposals' | 'projects'>('proposals')
const proposals = ref<ProjectProposal[]>([])
const activeProjects = ref<Project[]>([])
const projectTypes = ref<ProjectType[]>([])
const units = ref<OrganizationUnit[]>([])

const searchQuery = ref('')
const selectedType = ref('')
const isModalOpen = ref(false)
const submitting = ref(false)
const errorMessage = ref('')

// New Proposal Form (F-PROJECT)
const form = ref({
 title: '',
 office_id: '',
 project_type_id: '',
 summary: '',
})

async function fetchData() {
 loading.value = true
 errorMessage.value = ''
 try {
 const [projRes, orgRes] = await Promise.all([
 institutionalApi.projects(),
 organizationUnitsApi.list({ per_page: 50 }),
 ])

 if (projRes.data.success) {
 proposals.value = projRes.data.data.proposals || []
 activeProjects.value = projRes.data.data.projects || []
 projectTypes.value = projRes.data.data.project_types || [
 { id: 1, name: 'Academic Research Project' },
 { id: 2, name: 'Community Service Project' },
 { id: 3, name: 'Campus Infrastructure' },
 ]
 }

 if (orgRes.data.data) {
 units.value = orgRes.data.data
 }
 } catch (err: any) {
 errorMessage.value = 'Failed to load projects ledger. Please check network connectivity.'
 } finally {
 loading.value = false
 }
}

onMounted(() => {
 fetchData()
})

const filteredProposals = computed(() => {
 return proposals.value.filter((p) => {
 const matchesSearch =
 !searchQuery.value ||
 p.document?.title?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
 p.document?.reference_number?.toLowerCase().includes(searchQuery.value.toLowerCase())

 const matchesType =
 !selectedType.value || p.project_type_id === Number(selectedType.value)

 return matchesSearch && matchesType
 })
})

const filteredProjects = computed(() => {
 return activeProjects.value.filter((p) => {
 return (
 !searchQuery.value ||
 p.code?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
 p.proposal?.document?.title?.toLowerCase().includes(searchQuery.value.toLowerCase())
 )
 })
})

function openCreateModal() {
 form.value = {
 title: '',
 office_id: authStore.activeUnitId ? String(authStore.activeUnitId) : (authStore.primaryUnit?.unit_id ? String(authStore.primaryUnit.unit_id) : (units.value[0]?.id ? String(units.value[0].id) : '')),
 project_type_id: projectTypes.value[0]?.id ? String(projectTypes.value[0].id) : '1',
 summary: '',
 }
 isModalOpen.value = true
}

async function submitProposal() {
 if (!form.value.title || !form.value.office_id || !form.value.project_type_id) {
 toast.error('Validation Error', 'Please fill in all required fields.')
 return
 }

 submitting.value = true
 try {
 const res = await institutionalApi.storeProject({
 title: form.value.title,
 office_id: Number(form.value.office_id),
 project_type_id: Number(form.value.project_type_id),
 summary: form.value.summary,
 })

 if (res.data.success) {
 toast.success('Proposal Recorded', 'Project proposal registered successfully in institutional catalog.')
 isModalOpen.value = false
 await fetchData()
 }
 } catch (err: any) {
 toast.error('Submission Failed', err.response?.data?.message || err.message || 'Failed to submit proposal.')
 } finally {
 submitting.value = false
 }
}
</script>

<template>
 <div class="space-y-6">
 <!-- Header Row -->
 <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-hairline pb-4">
 <div>
 <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-ink flex items-center gap-2.5">
 <FolderKanban class="w-5 h-5 sm:w-6 sm:h-6 text-primary shrink-0" />
 <span>Projects & Innovation</span>
 </h1>
 <p class="text-xs text-ink-muted mt-0.5">
 Registered project proposals, innovation grants, and active institutional initiatives.
 </p>
 </div>

 <div class="flex items-center gap-2">
 <button
 type="button"
 class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-md bg-primary hover:bg-primary-hover text-white text-xs font-medium transition-colors shadow-xs"
 @click="openCreateModal"
 >
 <Plus class="w-4 h-4" />
 <span>Record Project Proposal</span>
 </button>
 </div>
 </div>

 <!-- Institutional Policy Notice (Mandatory from UI Blueprint) -->
 <div class="p-3.5 rounded-lg bg-canvas-soft border border-hairline text-xs text-ink-secondary">
 <div class="flex items-start gap-3">
 <ShieldAlert class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
 <div class="text-xs text-ink-secondary">
 <strong class="font-semibold block mb-0.5 text-ink-secondary">Institutional Approval Boundary Notice</strong>
 The project approval chain requires explicit departmental and committee confirmation. No project approval or funding authorization is assumed from finalized research publication rules. Unsupported committee/quorum workflows display “Procedure configuration required”; they do not simulate approval.
 </div>
 </div>
 </div>

 <!-- Metrics Cards -->
 <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
 <div class="bg-surface border border-hairline rounded-lg p-4 shadow-xs">
 <div class="flex items-center justify-between">
 <span class="text-xs font-semibold text-ink-muted">Project Proposals</span>
 <FolderKanban class="w-4 h-4 text-primary" />
 </div>
 <div class="text-2xl font-semibold text-ink-secondary mt-2">
 {{ proposals.length }}
 </div>
 <div class="text-[11px] text-ink-muted mt-0.5">Concept notes & grant submissions</div>
 </div>

 <div class="bg-surface border border-hairline rounded-lg p-4 shadow-xs">
 <div class="flex items-center justify-between">
 <span class="text-xs font-semibold text-ink-muted">Active Operational Projects</span>
 <CheckCircle2 class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
 </div>
 <div class="text-2xl font-semibold text-ink-secondary mt-2">
 {{ activeProjects.length }}
 </div>
 <div class="text-[11px] text-ink-muted mt-0.5">Approved and running initiatives</div>
 </div>

 <div class="bg-surface border border-hairline rounded-lg p-4 shadow-xs">
 <div class="flex items-center justify-between">
 <span class="text-xs font-semibold text-ink-muted">Approval Procedure State</span>
 <Clock class="w-4 h-4 text-amber-600 dark:text-amber-400" />
 </div>
 <div class="text-base font-semibold text-ink-secondary mt-2">
 Institutional Procedure Required
 </div>
 <div class="text-[11px] text-ink-muted mt-0.5">Direct approval without confirmed policy is blocked</div>
 </div>
 </div>

 <!-- Tab Bar -->
 <div class="flex items-center gap-4 border-b border-hairline">
 <button
 type="button"
 class="pb-2.5 text-xs font-medium border-b-2 transition-colors"
 :class="[
 activeTab === 'proposals'
 ? 'border-primary text-primary font-semibold'
 : 'border-transparent text-ink-muted hover:text-ink-secondary'
 ]"
 @click="activeTab = 'proposals'"
 >
 Project Proposals ({{ proposals.length }})
 </button>

 <button
 type="button"
 class="pb-2.5 text-xs font-medium border-b-2 transition-colors"
 :class="[
 activeTab === 'projects'
 ? 'border-primary text-primary font-semibold'
 : 'border-transparent text-ink-muted hover:text-ink-secondary'
 ]"
 @click="activeTab = 'projects'"
 >
 Approved Projects ({{ activeProjects.length }})
 </button>
 </div>

 <!-- Search & Filter Bar -->
 <div class="flex flex-col sm:flex-row items-center gap-3">
 <div class="relative flex-1 w-full">
 <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
 <input
 v-model="searchQuery"
 type="text"
 placeholder="Search by title, reference code..."
 class="w-full pl-9 pr-3 py-1.5 bg-surface border border-hairline rounded-md text-xs text-ink-secondary focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 />
 </div>

 <select
 v-if="activeTab === 'proposals'"
 v-model="selectedType"
 class="w-full sm:w-56 py-1.5 px-3 bg-surface border border-hairline rounded-md text-xs text-ink-secondary focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 >
 <option value="">All Project Types</option>
 <option v-for="t in projectTypes" :key="t.id" :value="t.id">
 {{ t.name }}
 </option>
 </select>
 </div>

 <!-- Content Table -->
 <div class="bg-surface border border-hairline rounded-lg overflow-hidden shadow-xs">
 <!-- Loading Skeleton -->
 <div v-if="loading" class="p-6">
 <LoadingSkeleton type="table" :rows="5" />
 </div>

 <!-- Proposals Table -->
 <div v-else-if="activeTab === 'proposals'" class="overflow-x-auto">
 <table class="w-full text-left text-xs text-ink-secondary">
 <thead class="bg-canvas-soft border-b border-hairline text-[11px] font-semibold text-ink-muted uppercase tracking-wider">
 <tr>
 <th class="py-3 px-4">Proposal Reference</th>
 <th class="py-3 px-4">Title & Concept</th>
 <th class="py-3 px-4">Type</th>
 <th class="py-3 px-4">Lead Office</th>
 <th class="py-3 px-4">Recorded By</th>
 <th class="py-3 px-4 text-right">Workflow Status</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-hairline">
 <tr
 v-for="p in filteredProposals"
 :key="p.id"
 class="hover:bg-surface-hover transition-colors"
 >
 <td class="py-3 px-4 font-mono font-medium text-primary">
 {{ p.document?.reference_number || '—' }}
 </td>
 <td class="py-3 px-4">
 <div class="font-medium text-ink-secondary">
 {{ p.document?.title || 'Untitled Proposal' }}
 </div>
 <div class="text-[11px] text-ink-muted truncate max-w-md">
 {{ p.document?.summary || 'No concept notes registered' }}
 </div>
 </td>
 <td class="py-3 px-4">
 <span class="px-2 py-0.5 rounded text-[11px] bg-surface-muted text-ink-muted">
 {{ p.projectType?.name || p.project_type?.name || 'Academic Research' }}
 </span>
 </td>
 <td class="py-3 px-4 text-ink-muted">
 {{ p.document?.owner_unit?.name || 'Lead Office' }}
 </td>
 <td class="py-3 px-4 text-ink-muted">
 {{ (typeof p.recorded_by === 'object' ? p.recorded_by?.name : p.recordedBy?.name) || 'Faculty Member' }}
 </td>
 <td class="py-3 px-4 text-right">
 <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-semibold bg-[#fff4e6] dark:bg-[#382412] text-[#dd5b00] dark:text-[#ff9243] border border-[#dd5b00]/20">
 <Clock class="w-3 h-3" />
 Institutional Procedure Required
 </span>
 </td>
 </tr>

 <tr v-if="filteredProposals.length === 0">
 <td colspan="6" class="p-8">
 <EmptyState
 title="No proposals found"
 description="No project proposals match the selected filters. Click 'Submit proposal' to register a new project."
 />
 </td>
 </tr>
 </tbody>
 </table>
 </div>

 <!-- Approved Projects Table -->
 <div v-else class="overflow-x-auto">
 <table class="w-full text-left text-xs text-ink-secondary">
 <thead class="bg-canvas-soft border-b border-hairline text-[11px] font-semibold text-ink-muted uppercase tracking-wider">
 <tr>
 <th class="py-3 px-4">Project Code</th>
 <th class="py-3 px-4">Approved Title</th>
 <th class="py-3 px-4">Originating Office</th>
 <th class="py-3 px-4">Operational Period</th>
 <th class="py-3 px-4 text-right">Status</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-hairline">
 <tr
 v-for="p in filteredProjects"
 :key="p.id"
 class="hover:bg-surface-hover transition-colors"
 >
 <td class="py-3 px-4 font-mono font-medium text-primary">
 {{ p.code }}
 </td>
 <td class="py-3 px-4 font-medium text-ink-secondary">
 {{ p.proposal?.document?.title || 'Operational Project' }}
 </td>
 <td class="py-3 px-4 text-ink-muted">
 {{ p.proposal?.document?.owner_unit?.name || 'Department' }}
 </td>
 <td class="py-3 px-4 text-ink-muted">
 {{ p.starts_on || 'Current' }} → {{ p.ends_on || 'Indefinite' }}
 </td>
 <td class="py-3 px-4 text-right">
 <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-[#e8f7ec] text-[#1aae39] dark:bg-[#183321] dark:text-[#42cf63] border border-[#1aae39]/20">
 <CheckCircle2 class="w-3 h-3" />
 Active
 </span>
 </td>
 </tr>

 <tr v-if="filteredProjects.length === 0">
 <td colspan="5" class="p-8">
 <EmptyState
 title="No operational projects"
 description="No operational projects are currently confirmed. Direct project creation requires formal committee procedure approval."
 />
 </td>
 </tr>
 </tbody>
 </table>
 </div>
 </div>

 <!-- Modal: Record Project Proposal (F-PROJECT) -->
 <div
 v-if="isModalOpen"
 class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50 backdrop-blur-xs"
 @click.self="isModalOpen = false"
 >
 <div class="bg-surface border border-hairline rounded-lg max-w-lg w-full p-6 shadow-xl">
 <div class="flex items-center justify-between pb-3 border-b border-hairline">
 <div>
 <h2 class="text-sm font-semibold text-ink-secondary">
 Record Project Proposal (F-PROJECT)
 </h2>
 <p class="text-[11px] text-ink-muted">
 Submit concept note for departmental procedure screening
 </p>
 </div>
 <button
 type="button"
 class="text-ink-muted hover:text-ink-secondary p-1"
 @click="isModalOpen = false"
 >
 <X class="w-4 h-4" />
 </button>
 </div>

 <form class="space-y-4 mt-4" @submit.prevent="submitProposal">
 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Proposal Title *
 </label>
 <input
 v-model="form.title"
 type="text"
 required
 placeholder="e.g. Community-Based Renewable Energy Microgrid in South Wollo"
 class="w-full px-3 py-1.5 bg-surface border border-hairline rounded-md text-xs text-ink-secondary focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 />
 </div>

 <div class="grid grid-cols-2 gap-3">
 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Originating Office *
 </label>
 <select
 v-model="form.office_id"
 required
 class="w-full px-3 py-1.5 bg-surface border border-hairline rounded-md text-xs text-ink-secondary focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 >
 <option value="" disabled>Select Office</option>
 <option v-for="u in units" :key="u.id" :value="u.id">
 {{ u.name }}
 </option>
 </select>
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Project Type *
 </label>
 <select
 v-model="form.project_type_id"
 required
 class="w-full px-3 py-1.5 bg-surface border border-hairline rounded-md text-xs text-ink-secondary focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 >
 <option v-for="t in projectTypes" :key="t.id" :value="t.id">
 {{ t.name }}
 </option>
 </select>
 </div>
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Summary & Objectives
 </label>
 <textarea
 v-model="form.summary"
 rows="3"
 placeholder="Outline project objectives, target community impact, and methodology..."
 class="w-full px-3 py-1.5 bg-surface border border-hairline rounded-md text-xs text-ink-secondary focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 />
 </div>

 <div class="p-3 rounded-md bg-[#fff4e6] dark:bg-[#382412] border border-[#dd5b00]/30 text-[11px] text-[#dd5b00] dark:text-[#ff9243]">
 <strong>Rule invariant:</strong> Approval requires formal departmental confirmation. Direct operational status cannot be self-selected.
 </div>

 <div class="flex items-center justify-end gap-2 pt-3 border-t border-hairline">
 <button
 type="button"
 class="px-3 py-1.5 rounded-md border border-hairline text-xs font-medium text-ink-secondary hover:bg-canvas-soft"
 @click="isModalOpen = false"
 >
 Cancel
 </button>
 <button
 type="submit"
 :disabled="submitting"
 class="px-4 py-1.5 rounded-md bg-primary hover:bg-primary-hover text-white text-xs font-semibold transition disabled:opacity-50"
 >
 {{ submitting ? 'Recording...' : 'Register Proposal' }}
 </button>
 </div>
 </form>
 </div>
 </div>
 </div>
</template>

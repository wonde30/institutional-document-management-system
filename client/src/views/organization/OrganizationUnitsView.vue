<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { organizationUnitsApi } from '@/api/client'
import type { OrganizationUnit, UnitType } from '@/types'
import { useToast } from '@/composables/useToast'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import ModalDialog from '@/components/ModalDialog.vue'
import LoadingSkeleton from '@/components/ui/LoadingSkeleton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import {
 Building2,
 GitFork,
 Plus,
 RefreshCw,
 FolderTree,
 Table as TableIcon,
 Search,
 CheckCircle2,
 XCircle,
 Edit2,
 ChevronRight,
 ChevronDown,
} from 'lucide-vue-next'

const toast = useToast()

const activeTab = ref<'tree' | 'table'>('tree')
const isLoading = ref(true)
const isSubmitting = ref(false)
const searchQuery = ref('')
const selectedTypeFilter = ref<number | ''>('')

const treeData = ref<OrganizationUnit[]>([])
const flatUnits = ref<OrganizationUnit[]>([])
const unitTypes = ref<UnitType[]>([])

// Modal state
const isModalOpen = ref(false)
const isEditing = ref(false)
const currentUnitId = ref<number | null>(null)
const form = ref({
 code: '',
 name: '',
 unit_type_id: '' as number | '',
 parent_id: '' as number | '' | null,
 active: true,
})

// Expanded nodes in tree
const expandedNodeIds = ref<Set<number>>(new Set())

function toggleExpand(id: number) {
 if (expandedNodeIds.value.has(id)) {
 expandedNodeIds.value.delete(id)
 } else {
 expandedNodeIds.value.add(id)
 }
}

async function loadData() {
 isLoading.value = true
 try {
 const [treeRes, typesRes, listRes] = await Promise.all([
 organizationUnitsApi.tree(),
 organizationUnitsApi.unitTypes(),
 organizationUnitsApi.list({ per_page: 500 }),
 ])

 treeData.value = treeRes.data.data
 unitTypes.value = typesRes.data.data
 flatUnits.value = listRes.data.data

 // Auto-expand root level nodes
 treeData.value.forEach((unit) => expandedNodeIds.value.add(unit.id))
 } catch (error: any) {
 toast.error('Load Error', error.response?.data?.message || 'Failed to load organization units.')
 } finally {
 isLoading.value = false
 }
}

const filteredFlatUnits = computed(() => {
 return flatUnits.value.filter((unit) => {
 const matchesSearch =
 !searchQuery.value ||
 unit.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
 unit.code.toLowerCase().includes(searchQuery.value.toLowerCase())
 const matchesType =
 selectedTypeFilter.value === '' || unit.unit_type_id === selectedTypeFilter.value
 return matchesSearch && matchesType
 })
})

function openCreateModal(parentId: number | null = null) {
 isEditing.value = false
 currentUnitId.value = null
 form.value = {
 code: '',
 name: '',
 unit_type_id: unitTypes.value[0]?.id || '',
 parent_id: parentId,
 active: true,
 }
 isModalOpen.value = true
}

function openEditModal(unit: OrganizationUnit) {
 isEditing.value = true
 currentUnitId.value = unit.id
 form.value = {
 code: unit.code,
 name: unit.name,
 unit_type_id: unit.unit_type_id,
 parent_id: unit.parent_id || '',
 active: unit.active,
 }
 isModalOpen.value = true
}

async function handleSubmit() {
 if (!form.value.name || !form.value.code || !form.value.unit_type_id) {
 toast.error('Validation Error', 'Unit code, name, and type are required.')
 return
 }

 isSubmitting.value = true
 try {
 const payload = {
 code: form.value.code.toUpperCase().trim(),
 name: form.value.name.trim(),
 unit_type_id: Number(form.value.unit_type_id),
 parent_id: form.value.parent_id ? Number(form.value.parent_id) : null,
 active: form.value.active,
 }

 if (isEditing.value && currentUnitId.value) {
 await organizationUnitsApi.update(currentUnitId.value, payload)
 toast.success('Updated', `Organization unit "${payload.name}" updated successfully.`)
 } else {
 await organizationUnitsApi.create(payload)
 toast.success('Created', `Organization unit "${payload.name}" created successfully.`)
 }

 isModalOpen.value = false
 await loadData()
 } catch (error: any) {
 toast.error('Operation Failed', error.response?.data?.message || 'Could not save unit details.')
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
 <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-hairline pb-4">
 <div>
 <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-ink flex items-center gap-2.5">
 <Building2 class="w-5 h-5 sm:w-6 sm:h-6 text-primary shrink-0" />
 <span>Institutional Organization Structure</span>
 </h1>
 <p class="text-xs text-ink-muted mt-0.5">
 Hierarchical structure of colleges, directorates, departments, and academic units across Wollo University campuses.
 </p>
 </div>

 <div class="flex items-center gap-2.5">
 <BaseButton variant="outline" size="sm" :loading="isLoading" @click="loadData">
 <RefreshCw class="w-4 h-4 mr-1.5" />
 Refresh
 </BaseButton>

 <BaseButton variant="primary" size="sm" @click="openCreateModal(null)">
 <Plus class="w-4 h-4 mr-1.5" />
 Add Top-Level Unit
 </BaseButton>
 </div>
 </div>

 <!-- Stats Overview -->
 <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
 <div class="bg-surface p-4 rounded-xl border border-hairline shadow-xs flex items-center gap-4">
 <div class="p-3 bg-primary-subtle text-primary rounded-lg">
 <Building2 class="w-6 h-6" />
 </div>
 <div>
 <div class="text-2xl font-bold text-ink-secondary">{{ flatUnits.length }}</div>
 <div class="text-xs text-ink-muted font-medium">Total Institutional Units</div>
 </div>
 </div>

 <div class="bg-surface p-4 rounded-xl border border-hairline shadow-xs flex items-center gap-4">
 <div class="p-3 bg-primary-subtle text-primary rounded-lg">
 <GitFork class="w-6 h-6" />
 </div>
 <div>
 <div class="text-2xl font-bold text-ink-secondary">{{ unitTypes.length }}</div>
 <div class="text-xs text-ink-muted font-medium">Unit Classification Types</div>
 </div>
 </div>

 <div class="bg-surface p-4 rounded-xl border border-hairline shadow-xs flex items-center gap-4">
 <div class="p-3 bg-[#e8f7ec] dark:bg-[#14301d] text-[#1aae39] dark:text-[#34d399] rounded-lg">
 <CheckCircle2 class="w-6 h-6" />
 </div>
 <div>
 <div class="text-2xl font-bold text-ink-secondary">
 {{ flatUnits.filter(u => u.active).length }}
 </div>
 <div class="text-xs text-ink-muted font-medium">Active Operational Units</div>
 </div>
 </div>
 </div>

 <!-- View Modes & Controls -->
 <div class="bg-surface rounded-xl border border-hairline shadow-xs p-4">
 <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-4 pb-4 border-b border-hairline">
 <div class="flex items-center gap-2 bg-canvas-soft p-1 rounded-lg">
 <button
 type="button"
 class="px-3 py-1.5 rounded-md text-xs font-semibold transition-all flex items-center gap-1.5 cursor-pointer"
 :class="activeTab === 'tree' ? 'bg-surface text-primary shadow-xs' : 'text-ink-muted'"
 @click="activeTab = 'tree'"
 >
 <FolderTree class="w-3.5 h-3.5" />
 Tree Hierarchy
 </button>
 <button
 type="button"
 class="px-3 py-1.5 rounded-md text-xs font-semibold transition-all flex items-center gap-1.5 cursor-pointer"
 :class="activeTab === 'table' ? 'bg-surface text-primary shadow-xs' : 'text-ink-muted'"
 @click="activeTab = 'table'"
 >
 <TableIcon class="w-3.5 h-3.5" />
 Tabular Directory
 </button>
 </div>

 <div v-if="activeTab === 'table'" class="flex items-center gap-3 w-full sm:w-auto">
 <div class="relative flex-1 sm:w-64">
 <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
 <input
 v-model="searchQuery"
 type="text"
 placeholder="Search by code or name..."
 class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border border-hairline bg-surface text-ink-secondary focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"
 />
 </div>

 <select
 v-model="selectedTypeFilter"
 class="text-xs rounded-lg border border-hairline bg-surface text-ink-secondary px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"
 >
 <option value="">All Unit Types</option>
 <option v-for="t in unitTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
 </select>
 </div>
 </div>

 <!-- Loading State -->
 <LoadingSkeleton v-if="isLoading" :count="6" class="my-4" />

 <!-- Tree View Tab -->
 <div v-else-if="activeTab === 'tree'" class="space-y-2">
 <div v-if="treeData.length === 0" class="py-8 text-center text-sm text-ink-muted">
 No organizational units configured yet. Click "Add Top-Level Unit" to start.
 </div>

 <div v-else class="space-y-2">
 <!-- Recursive Tree Root Level -->
 <div
 v-for="unit in treeData"
 :key="unit.id"
 class="border border-hairline rounded-lg p-3 bg-canvas-soft "
 >
 <div class="flex items-center justify-between gap-3">
 <div class="flex items-center gap-2.5">
 <button
 v-if="unit.children && unit.children.length > 0"
 type="button"
 class="p-1 rounded hover:bg-surface-hover text-ink-muted cursor-pointer"
 @click="toggleExpand(unit.id)"
 >
 <ChevronDown v-if="expandedNodeIds.has(unit.id)" class="w-4 h-4" />
 <ChevronRight v-else class="w-4 h-4" />
 </button>
 <div v-else class="w-6" />

 <div class="flex items-center gap-2">
 <span class="font-mono text-xs px-2 py-0.5 rounded bg-primary-subtle text-primary font-bold">
 {{ unit.code }}
 </span>
 <span class="text-sm font-semibold text-ink-secondary">
 {{ unit.name }}
 </span>
 <span class="text-xs px-2 py-0.5 rounded-md bg-canvas-soft text-ink-secondary">
 {{ unit.unit_type?.name || 'Unit' }}
 </span>
 </div>
 </div>

 <div class="flex items-center gap-2">
 <span
 class="text-[11px] px-2 py-0.5 rounded font-medium"
 :class="unit.active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300'"
 >
 {{ unit.active ? 'Active' : 'Inactive' }}
 </span>

 <button
 type="button"
 class="p-1 text-ink-muted hover:text-primary rounded transition cursor-pointer"
 title="Add Sub-unit"
 @click="openCreateModal(unit.id)"
 >
 <Plus class="w-4 h-4" />
 </button>
 <button
 type="button"
 class="p-1 text-ink-muted hover:text-primary rounded transition cursor-pointer"
 title="Edit Unit"
 @click="openEditModal(unit)"
 >
 <Edit2 class="w-4 h-4" />
 </button>
 </div>
 </div>

 <!-- Sub-units (Level 1) -->
 <div
 v-if="unit.children && unit.children.length > 0 && expandedNodeIds.has(unit.id)"
 class="ml-8 mt-3 pl-4 border-l-2 border-hairline space-y-2"
 >
 <div
 v-for="child in unit.children"
 :key="child.id"
 class="border border-hairline rounded-lg p-2.5 bg-surface"
 >
 <div class="flex items-center justify-between gap-3">
 <div class="flex items-center gap-2.5">
 <button
 v-if="child.children && child.children.length > 0"
 type="button"
 class="p-1 rounded hover:bg-surface-hover text-ink-muted cursor-pointer"
 @click="toggleExpand(child.id)"
 >
 <ChevronDown v-if="expandedNodeIds.has(child.id)" class="w-3.5 h-3.5" />
 <ChevronRight v-else class="w-3.5 h-3.5" />
 </button>
 <div v-else class="w-5" />

 <div class="flex items-center gap-2">
 <span class="font-mono text-xs px-2 py-0.5 rounded bg-primary-subtle text-primary font-bold">
 {{ child.code }}
 </span>
 <span class="text-xs font-semibold text-ink-secondary">
 {{ child.name }}
 </span>
 <span class="text-[11px] px-1.5 py-0.2 rounded bg-canvas-soft text-ink-secondary">
 {{ child.unit_type?.name }}
 </span>
 </div>
 </div>

 <div class="flex items-center gap-1.5">
 <button
 type="button"
 class="p-1 text-ink-muted hover:text-primary rounded transition cursor-pointer"
 title="Add Child Sub-unit"
 @click="openCreateModal(child.id)"
 >
 <Plus class="w-3.5 h-3.5" />
 </button>
 <button
 type="button"
 class="p-1 text-ink-muted hover:text-primary rounded transition cursor-pointer"
 title="Edit Unit"
 @click="openEditModal(child)"
 >
 <Edit2 class="w-3.5 h-3.5" />
 </button>
 </div>
 </div>

 <!-- Sub-sub units (Level 2) -->
 <div
 v-if="child.children && child.children.length > 0 && expandedNodeIds.has(child.id)"
 class="ml-6 mt-2 pl-3 border-l-2 border-primary/30 dark:border-primary/40 space-y-1.5"
 >
 <div
 v-for="subChild in child.children"
 :key="subChild.id"
 class="flex items-center justify-between p-2 rounded bg-canvas-soft text-xs"
 >
 <div class="flex items-center gap-2">
 <span class="font-mono font-bold text-ink-muted">{{ subChild.code }}</span>
 <span class="text-ink-secondary font-medium">{{ subChild.name }}</span>
 </div>
 <div class="flex items-center gap-2">
 <button
 type="button"
 class="p-1 text-ink-muted hover:text-primary rounded transition cursor-pointer"
 title="Edit Unit"
 @click="openEditModal(subChild)"
 >
 <Edit2 class="w-3.5 h-3.5" />
 </button>
 </div>
 </div>
 </div>
 </div>
 </div>
 </div>
 </div>
 </div>

 <!-- Tabular Directory Tab -->
 <div v-else-if="activeTab === 'table'" class="overflow-x-auto">
 <table class="w-full text-left text-xs">
 <thead class="bg-canvas-soft text-ink-muted uppercase tracking-wider font-semibold border-b border-hairline">
 <tr>
 <th class="px-4 py-3">Code</th>
 <th class="px-4 py-3">Unit Name</th>
 <th class="px-4 py-3">Unit Type</th>
 <th class="px-4 py-3">Parent Unit</th>
 <th class="px-4 py-3">Status</th>
 <th class="px-4 py-3 text-right">Actions</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-hairline">
 <tr
 v-for="unit in filteredFlatUnits"
 :key="unit.id"
 class="hover:bg-surface-hover transition"
 >
 <td class="px-4 py-3 font-mono font-bold text-primary">
 {{ unit.code }}
 </td>
 <td class="px-4 py-3 font-medium text-ink-secondary">
 {{ unit.name }}
 </td>
 <td class="px-4 py-3 text-ink-muted">
 {{ unit.unit_type?.name || 'N/A' }}
 </td>
 <td class="px-4 py-3 text-ink-muted">
 {{ unit.parent?.name || 'Top-level' }}
 </td>
 <td class="px-4 py-3">
 <span
 class="px-2 py-0.5 rounded text-[11px] font-semibold"
 :class="unit.active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300'"
 >
 {{ unit.active ? 'Active' : 'Inactive' }}
 </span>
 </td>
 <td class="px-4 py-3 text-right">
 <button
 type="button"
 class="p-1 text-ink-muted hover:text-primary rounded transition cursor-pointer"
 @click="openEditModal(unit)"
 >
 <Edit2 class="w-4 h-4 inline" />
 </button>
 </td>
 </tr>
 </tbody>
 </table>

 <div v-if="filteredFlatUnits.length === 0" class="py-8 text-center text-ink-muted text-xs">
 No units matched your filter criteria.
 </div>
 </div>
 </div>

 <!-- Create / Edit Modal -->
 <ModalDialog
 :open="isModalOpen"
 :title="isEditing ? 'Modify Organizational Unit' : 'Create Organizational Unit'"
 max-width="md"
 @close="isModalOpen = false"
 >
 <div class="space-y-4">
 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Unit Code (Unique Identifier) *
 </label>
 <BaseInput
 v-model="form.code"
 placeholder="e.g. CNCS, CS-DEPARTMENT, REGISTRAR"
 class="uppercase"
 :disabled="isEditing"
 />
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Official Unit Name *
 </label>
 <BaseInput
 v-model="form.name"
 placeholder="e.g. College of Natural and Computational Sciences"
 />
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Unit Classification Type *
 </label>
 <select
 v-model="form.unit_type_id"
 class="w-full text-xs rounded-lg border border-hairline bg-surface text-ink-secondary px-3 py-2 focus:ring-1 focus:ring-primary focus:border-primary"
 >
 <option v-for="t in unitTypes" :key="t.id" :value="t.id">{{ t.name }} ({{ t.code }})</option>
 </select>
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Parent Organization Unit
 </label>
 <select
 v-model="form.parent_id"
 class="w-full text-xs rounded-lg border border-hairline bg-surface text-ink-secondary px-3 py-2 focus:ring-1 focus:ring-primary focus:border-primary"
 >
 <option :value="null">None (Top-Level Institutional Office)</option>
 <option
 v-for="u in flatUnits.filter(u => u.id !== currentUnitId)"
 :key="u.id"
 :value="u.id"
 >
 {{ u.name }} ({{ u.code }})
 </option>
 </select>
 </div>

 <div class="flex items-center gap-2 pt-2">
 <input
 id="unit-active-checkbox"
 v-model="form.active"
 type="checkbox"
 class="w-4 h-4 text-primary rounded border-hairline focus:ring-primary"
 />
 <label for="unit-active-checkbox" class="text-xs font-medium text-ink-secondary">
 Active Operational Status
 </label>
 </div>
 </div>

 <template #footer>
 <div class="flex items-center justify-end gap-2">
 <BaseButton variant="outline" size="sm" @click="isModalOpen = false">
 Cancel
 </BaseButton>
 <BaseButton variant="primary" size="sm" :loading="isSubmitting" @click="handleSubmit">
 {{ isEditing ? 'Save Changes' : 'Create Unit' }}
 </BaseButton>
 </div>
 </template>
 </ModalDialog>
 </div>
</template>

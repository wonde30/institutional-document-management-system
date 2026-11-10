<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { rolesApi } from '@/api/client'
import type { RoleItem, PermissionItem } from '@/types'
import { useToast } from '@/composables/useToast'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import ModalDialog from '@/components/ModalDialog.vue'
import LoadingSkeleton from '@/components/ui/LoadingSkeleton.vue'
import {
 Shield,
 ShieldCheck,
 Plus,
 RefreshCw,
 Search,
 CheckCircle2,
 Lock,
 Edit2,
 Check,
 Layers,
} from 'lucide-vue-next'

const toast = useToast()
const loading = ref(true)
const isSubmitting = ref(false)
const roles = ref<RoleItem[]>([])
const allPermissions = ref<PermissionItem[]>([])
const searchQuery = ref('')

// Modal state
const isModalOpen = ref(false)
const isEditing = ref(false)
const selectedRoleId = ref<number | null>(null)

const roleForm = ref({
 code: '',
 name: '',
 description: '',
 active: true,
 permission_ids: [] as number[],
})

// Group permissions by their prefix/module (e.g. 'documents.view' -> 'documents')
const groupedPermissions = computed(() => {
 const groups: Record<string, PermissionItem[]> = {}
 allPermissions.value.forEach((perm) => {
 const parts = perm.code.split('.')
 const moduleName = parts.length > 1 ? parts[0] : 'general'
 if (!groups[moduleName]) {
 groups[moduleName] = []
 }
 groups[moduleName].push(perm)
 })
 return groups
})

const filteredRoles = computed(() => {
 if (!searchQuery.value.trim()) return roles.value
 const q = searchQuery.value.toLowerCase()
 return roles.value.filter(
 (r) => r.name.toLowerCase().includes(q) || r.code.toLowerCase().includes(q)
 )
})

async function loadData() {
 loading.value = true
 try {
 const [rolesRes, permsRes] = await Promise.all([
 rolesApi.list(),
 rolesApi.permissions(),
 ])
 roles.value = rolesRes.data.data
 allPermissions.value = permsRes.data.data
 } catch (err: any) {
 toast.error('Load Error', err.response?.data?.message || 'Failed to retrieve roles & permissions.')
 } finally {
 loading.value = false
 }
}

function openCreateModal() {
 isEditing.value = false
 selectedRoleId.value = null
 roleForm.value = {
 code: '',
 name: '',
 description: '',
 active: true,
 permission_ids: [],
 }
 isModalOpen.value = true
}

async function openEditModal(role: RoleItem) {
 isEditing.value = true
 selectedRoleId.value = role.id

 try {
 // Fetch full role with permissions
 const res = await rolesApi.get(role.id)
 const fullRole = res.data.data
 roleForm.value = {
 code: fullRole.code,
 name: fullRole.name,
 description: fullRole.description || '',
 active: fullRole.active,
 permission_ids: (fullRole.permissions || []).map((p) => p.id),
 }
 isModalOpen.value = true
 } catch (err: any) {
 toast.error('Load Error', 'Failed to fetch detailed role permissions.')
 }
}

function togglePermission(id: number) {
 const index = roleForm.value.permission_ids.indexOf(id)
 if (index > -1) {
 roleForm.value.permission_ids.splice(index, 1)
 } else {
 roleForm.value.permission_ids.push(id)
 }
}

function toggleModule(moduleName: string) {
 const modulePerms = groupedPermissions.value[moduleName] || []
 const allSelected = modulePerms.every((p) => roleForm.value.permission_ids.includes(p.id))

 if (allSelected) {
 const idsToRemove = new Set(modulePerms.map((p) => p.id))
 roleForm.value.permission_ids = roleForm.value.permission_ids.filter((id) => !idsToRemove.has(id))
 } else {
 const combined = new Set([...roleForm.value.permission_ids, ...modulePerms.map((p) => p.id)])
 roleForm.value.permission_ids = Array.from(combined)
 }
}

function isModuleSelected(moduleName: string): boolean {
 const modulePerms = groupedPermissions.value[moduleName] || []
 if (modulePerms.length === 0) return false
 return modulePerms.every((p) => roleForm.value.permission_ids.includes(p.id))
}

async function handleSaveRole() {
 if (!roleForm.value.name.trim()) {
 toast.error('Validation Error', 'Role name cannot be empty.')
 return
 }
 if (!isEditing.value && !roleForm.value.code.trim()) {
 toast.error('Validation Error', 'Role code cannot be empty.')
 return
 }

 isSubmitting.value = true
 try {
 if (isEditing.value && selectedRoleId.value) {
 // Update role attributes
 await rolesApi.update(selectedRoleId.value, {
 name: roleForm.value.name.trim(),
 description: roleForm.value.description.trim() || undefined,
 active: roleForm.value.active,
 })
 // Synchronize permissions
 await rolesApi.syncPermissions(selectedRoleId.value, roleForm.value.permission_ids)
 toast.success('Updated', `Role "${roleForm.value.name}" updated successfully.`)
 } else {
 await rolesApi.create({
 code: roleForm.value.code.toLowerCase().trim(),
 name: roleForm.value.name.trim(),
 description: roleForm.value.description.trim() || undefined,
 active: roleForm.value.active,
 permission_ids: roleForm.value.permission_ids,
 })
 toast.success('Created', `Role "${roleForm.value.name}" created successfully.`)
 }

 isModalOpen.value = false
 await loadData()
 } catch (err: any) {
 toast.error('Save Failed', err.response?.data?.message || 'Failed to save role configuration.')
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
 <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
 <div>
 <div class="notion-eyebrow text-primary mb-1">
 ADMINISTRATION / ROLES & PERMISSIONS
 </div>
 <h1 class="text-2xl font-semibold tracking-tight text-ink-secondary flex items-center gap-2.5">
 <Shield class="w-6 h-6 text-primary" />
 Scoped Role Management
 </h1>
 <p class="text-xs sm:text-sm text-ink-muted mt-0.5">
 Define institutional roles, permission capability bundles, and authorization boundaries.
 </p>
 </div>

 <div class="flex items-center gap-2.5">
 <BaseButton variant="outline" size="sm" :loading="loading" @click="loadData">
 <RefreshCw class="w-4 h-4 mr-1.5" />
 Refresh
 </BaseButton>

 <BaseButton variant="primary" size="sm" @click="openCreateModal">
 <Plus class="w-4 h-4 mr-1.5" />
 Create Custom Role
 </BaseButton>
 </div>
 </div>

 <!-- Search Bar -->
 <div class="bg-surface rounded-lg border border-hairline p-4 shadow-xs flex items-center justify-between">
 <div class="relative w-full sm:w-80">
 <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
 <input
 v-model="searchQuery"
 type="text"
 placeholder="Filter roles by name or code..."
 class="w-full pl-9 pr-3 py-1.5 text-xs rounded-md border border-hairline bg-surface text-ink-secondary focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 />
 </div>

 <div class="text-xs text-ink-muted font-medium">
 Showing {{ filteredRoles.length }} of {{ roles.length }} institutional roles
 </div>
 </div>

 <!-- Loading Skeleton -->
 <LoadingSkeleton v-if="loading" :count="4" class="my-4" />

 <!-- Roles Grid -->
 <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
 <div
 v-for="role in filteredRoles"
 :key="role.id"
 class="bg-surface rounded-lg border border-hairline p-5 shadow-xs hover:border-primary/40 transition-colors flex flex-col justify-between"
 >
 <div>
 <div class="flex items-start justify-between gap-3 mb-2">
 <div>
 <span class="font-mono text-[11px] font-semibold px-2 py-0.5 rounded bg-primary-subtle text-primary border border-primary/20 uppercase">
 {{ role.code }}
 </span>
 <h3 class="text-base font-semibold text-ink-secondary mt-1.5">
 {{ role.name }}
 </h3>
 </div>

 <span
 class="text-[10px] font-semibold px-2 py-0.5 rounded"
 :class="role.is_system ? 'bg-[#fff4e6] text-[#dd5b00] dark:bg-[#382412] dark:text-[#ff9243] border border-[#dd5b00]/20' : 'bg-surface-muted text-ink-muted '"
 >
 {{ role.is_system ? 'System Role' : 'Custom Role' }}
 </span>
 </div>

 <p class="text-xs text-ink-muted line-clamp-2 mb-4">
 {{ role.description || 'No institutional description provided.' }}
 </p>
 </div>

 <div class="border-t border-hairline pt-3 flex items-center justify-between">
 <div class="flex items-center gap-1.5 text-xs text-ink-muted">
 <ShieldCheck class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
 <span class="font-semibold text-ink-secondary">{{ role.permissions_count ?? (role.permissions?.length || 0) }}</span> permissions
 </div>

 <BaseButton variant="outline" size="sm" @click="openEditModal(role)">
 <Edit2 class="w-3.5 h-3.5 mr-1" />
 Configure
 </BaseButton>
 </div>
 </div>
 </div>

 <!-- Create / Edit Modal -->
 <ModalDialog
 :open="isModalOpen"
 :title="isEditing ? `Configure Role: ${roleForm.name}` : 'Create Institutional Role'"
 max-width="2xl"
 @close="isModalOpen = false"
 >
 <div class="space-y-4 max-h-[70vh] overflow-y-auto pr-1">
 <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
 <div>
 <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
 Role Code *
 </label>
 <BaseInput
 v-model="roleForm.code"
 placeholder="e.g. academic_dean"
 class="lowercase"
 :disabled="isEditing"
 />
 </div>

 <div>
 <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
 Display Name *
 </label>
 <BaseInput v-model="roleForm.name" placeholder="e.g. Academic Dean" />
 </div>
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Description
 </label>
 <BaseInput
 v-model="roleForm.description"
 placeholder="Institutional remit, responsibilities, and authority boundaries..."
 />
 </div>

 <div class="flex items-center gap-2">
 <input
 id="role-active-check"
 v-model="roleForm.active"
 type="checkbox"
 class="w-4 h-4 text-primary rounded border-hairline focus:ring-primary"
 />
 <label for="role-active-check" class="text-xs font-medium text-ink-secondary">
 Active Role Status
 </label>
 </div>

 <!-- Permissions Matrix Checklist -->
 <div class="border-t border-hairline pt-4">
 <div class="flex items-center justify-between mb-3">
 <h4 class="text-xs font-bold text-ink-secondary uppercase tracking-wider flex items-center gap-1.5">
 <Layers class="w-4 h-4 text-primary" />
 Permissions Matrix ({{ roleForm.permission_ids.length }} assigned)
 </h4>
 </div>

 <div class="space-y-4">
 <div
 v-for="(perms, moduleName) in groupedPermissions"
 :key="moduleName"
 class="border border-hairline rounded-lg p-3 bg-canvas-soft "
 >
 <div class="flex items-center justify-between mb-2">
 <span class="text-xs font-bold text-ink-secondary uppercase tracking-wider">
 {{ moduleName }}
 </span>
 <button
 type="button"
 class="text-[11px] font-semibold text-primary hover:underline cursor-pointer"
 @click="toggleModule(String(moduleName))"
 >
 {{ isModuleSelected(String(moduleName)) ? 'Deselect All' : 'Select All' }}
 </button>
 </div>

 <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
 <div
 v-for="perm in perms"
 :key="perm.id"
 class="flex items-start gap-2 p-1.5 rounded hover:bg-surface-hover transition cursor-pointer"
 @click="togglePermission(perm.id)"
 >
 <input
 type="checkbox"
 :checked="roleForm.permission_ids.includes(perm.id)"
 class="w-3.5 h-3.5 mt-0.5 text-primary rounded border-hairline focus:ring-primary"
 @click.stop="togglePermission(perm.id)"
 />
 <div>
 <div class="font-mono text-xs font-medium text-ink-secondary">
 {{ perm.code }}
 </div>
 <div class="text-[10px] text-ink-muted line-clamp-1">
 {{ perm.name }}
 </div>
 </div>
 </div>
 </div>
 </div>
 </div>
 </div>
 </div>

 <template #footer>
 <div class="flex items-center justify-end gap-2">
 <BaseButton variant="outline" size="sm" @click="isModalOpen = false">
 Cancel
 </BaseButton>
 <BaseButton variant="primary" size="sm" :loading="isSubmitting" @click="handleSaveRole">
 {{ isEditing ? 'Save Role Changes' : 'Create Role' }}
 </BaseButton>
 </div>
 </template>
 </ModalDialog>
 </div>
</template>

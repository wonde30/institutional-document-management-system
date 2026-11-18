<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { usersApi, organizationUnitsApi, rolesApi, permissionsApi } from '@/api/client'
import type {
 User,
 OrganizationUnit,
 RoleItem,
 PermissionItem,
 PaginationMeta,
 UserDirectPermission,
 PermissionLineageData,
 PermissionLineageItem,
} from '@/types'
import ModalDialog from '@/components/ModalDialog.vue'
import DataTable, { type TableColumn } from '@/components/data-display/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import { useToast } from '@/composables/useToast'
import { formatStatus } from '@/utils/formatters'
import {
 Users,
 UserPlus,
 Edit2,
 Shield,
 ShieldCheck,
 Building2,
 Mail,
 Lock,
 Unlock,
 Search,
 RefreshCw,
 CheckCircle2,
 AlertCircle,
 Plus,
 KeyRound,
 LayoutGrid,
 List,
 UserCheck,
 UserX,
 Trash2,
 Calendar,
 ArrowRight,
 Clock,
 Info,
 Check,
} from 'lucide-vue-next'

const toast = useToast()
const loading = ref(true)
const users = ref<User[]>([])
const units = ref<OrganizationUnit[]>([])
const availableRoles = ref<RoleItem[]>([])
const availablePermissions = ref<PermissionItem[]>([])

// View mode: 'table' (dense institutional table) | 'cards' (responsive institutional cards)
const viewMode = ref<'table' | 'cards'>('table')

const pagination = ref<PaginationMeta>({
 current_page: 1,
 last_page: 1,
 per_page: 15,
 total: 0,
})

const searchQuery = ref('')
const filterStatus = ref('')
const filterUnit = ref<number | ''>('')
const filterRole = ref('')
const perPageOptions = [10, 15, 25, 50]

// Create / Edit User Modal
const showCreateEditModal = ref(false)
const isEditing = ref(false)
const selectedUser = ref<User | null>(null)
const isSubmitting = ref(false)

const userForm = ref({
 name: '',
 email: '',
 password: '',
 status: 'active' as 'active' | 'inactive' | 'locked',
 unit_id: '' as number | '',
 position_title: '',
})

// Scoped Role Assignment Modal
const showRoleModal = ref(false)
const roleForm = ref({
 user_id: 0,
 role_id: '' as number | '',
 scope_kind: 'global' as 'global' | 'unit' | 'subtree',
 unit_id: '' as number | '' | null,
 expires_at: '',
})

// Direct Single-User Permission Modal
const showPermissionModal = ref(false)
const permissionForm = ref({
 user_id: 0,
 permission_id: '' as number | '',
 scope_kind: 'global' as 'global' | 'unit' | 'subtree',
 unit_id: '' as number | '' | null,
 expires_at: '',
})

// Unit Membership Modal
const showMembershipModal = ref(false)
const membershipForm = ref({
 user_id: 0,
 unit_id: '' as number | '',
 position_title: '',
 is_primary: true,
})

// Account Activate / Deactivate Confirmation Modal
const showStatusConfirmModal = ref(false)
const targetStatusUser = ref<User | null>(null)
const pendingStatus = ref<'active' | 'inactive'>('inactive')

// Effective Permissions & Access Lineage Modal
const showLineageModal = ref(false)
const loadingLineage = ref(false)
const lineageData = ref<PermissionLineageData | null>(null)
const lineageUnitFilter = ref<number | ''>('')
const lineageSearch = ref('')
const lineageModuleFilter = ref('')
const lineageStatusFilter = ref<'all' | 'effective' | 'non_effective'>('all')

async function fetchLineage(userId: number, unitId?: number | null) {
 loadingLineage.value = true
 try {
  const res = await usersApi.getEffectivePermissions(userId, unitId ?? undefined)
  lineageData.value = res.data.data
 } catch (err: any) {
  toast.error('Lineage Error', err.response?.data?.message || 'Failed to load permission lineage.')
 } finally {
  loadingLineage.value = false
 }
}

async function openLineageModal(user: User) {
 selectedUser.value = user
 lineageUnitFilter.value = user.primary_membership?.unit_id || ''
 lineageSearch.value = ''
 lineageModuleFilter.value = ''
 lineageStatusFilter.value = 'all'
 showLineageModal.value = true
 await fetchLineage(user.id, lineageUnitFilter.value ? Number(lineageUnitFilter.value) : null)
}

async function handleLineageUnitChange() {
 if (!selectedUser.value) return
 await fetchLineage(selectedUser.value.id, lineageUnitFilter.value ? Number(lineageUnitFilter.value) : null)
}

const filteredLineage = computed(() => {
 if (!lineageData.value?.lineage) return []
 return lineageData.value.lineage.filter((item) => {
  if (lineageSearch.value) {
   const q = lineageSearch.value.toLowerCase()
   const match =
    item.permission_code.toLowerCase().includes(q) ||
    item.permission_name.toLowerCase().includes(q) ||
    item.source_name.toLowerCase().includes(q)
   if (!match) return false
  }
  if (lineageModuleFilter.value && item.module !== lineageModuleFilter.value) {
   return false
  }
  if (lineageStatusFilter.value === 'effective' && !item.is_effective) {
   return false
  }
  if (lineageStatusFilter.value === 'non_effective' && item.is_effective) {
   return false
  }
  return true
 })
})

const distinctLineageModules = computed(() => {
 if (!lineageData.value?.lineage) return []
 const set = new Set<string>()
 lineageData.value.lineage.forEach((item) => set.add(item.module))
 return Array.from(set).sort()
})

const tableColumns: TableColumn[] = [
 { key: 'name', label: 'User & Email', sortable: true },
 { key: 'unit', label: 'Primary Unit & Position' },
 { key: 'roles', label: 'Scoped Role Assignments' },
 { key: 'direct_permissions', label: 'Single-User Direct Overrides' },
 { key: 'status', label: 'Account Status' },
 { key: 'created_at', label: 'Registered' },
 { key: 'actions', label: 'Actions', align: 'right' },
]

async function loadData(page = 1) {
 loading.value = true
 try {
 const [userRes, unitRes, roleRes, permRes] = await Promise.all([
 usersApi.list({
 page,
 per_page: pagination.value.per_page,
 search: searchQuery.value || undefined,
 status: filterStatus.value || undefined,
 unit_id: filterUnit.value || undefined,
 role: filterRole.value || undefined,
 }),
 organizationUnitsApi.list({ per_page: 500 }),
 rolesApi.list(),
 permissionsApi.list(),
 ])

 users.value = userRes.data.data
 const meta = (userRes.data as any).meta
 if (meta) {
 pagination.value = {
 current_page: meta.current_page,
 last_page: meta.last_page,
 per_page: meta.per_page || pagination.value.per_page,
 total: meta.total,
 from: meta.from,
 to: meta.to,
 }
 }
 units.value = unitRes.data.data
 availableRoles.value = roleRes.data.data
 availablePermissions.value = permRes.data.data
 } catch (err: any) {
 toast.error('Roster Error', err.response?.data?.message || 'Failed to load user accounts.')
 } finally {
 loading.value = false
 }
}

function handlePerPageChange(newPerPage: number) {
 pagination.value.per_page = newPerPage
 loadData(1)
}

function openCreateModal() {
 isEditing.value = false
 selectedUser.value = null
 userForm.value = {
 name: '',
 email: '',
 password: '',
 status: 'active',
 unit_id: units.value[0]?.id || '',
 position_title: '',
 }
 showCreateEditModal.value = true
}

function openEditModal(user: User) {
 isEditing.value = true
 selectedUser.value = user
 userForm.value = {
 name: user.name,
 email: user.email,
 password: '',
 status: user.status,
 unit_id: user.primary_membership?.unit_id || '',
 position_title: user.primary_membership?.position_title || '',
 }
 showCreateEditModal.value = true
}

function openAssignRoleModal(user: User) {
 selectedUser.value = user
 roleForm.value = {
 user_id: user.id,
 role_id: availableRoles.value[0]?.id || '',
 scope_kind: 'global',
 unit_id: null,
 expires_at: '',
 }
 showRoleModal.value = true
}

function openDirectPermissionModal(user: User) {
 selectedUser.value = user
 permissionForm.value = {
 user_id: user.id,
 permission_id: availablePermissions.value[0]?.id || '',
 scope_kind: 'global',
 unit_id: null,
 expires_at: '',
 }
 showPermissionModal.value = true
}

function openMembershipModal(user: User) {
 selectedUser.value = user
 membershipForm.value = {
 user_id: user.id,
 unit_id: units.value[0]?.id || '',
 position_title: '',
 is_primary: true,
 }
 showMembershipModal.value = true
}

function promptToggleStatus(user: User) {
 targetStatusUser.value = user
 pendingStatus.value = user.status === 'active' ? 'inactive' : 'active'
 showStatusConfirmModal.value = true
}

async function handleConfirmToggleStatus() {
 if (!targetStatusUser.value) return
 isSubmitting.value = true
 try {
 await usersApi.updateStatus(targetStatusUser.value.id, pendingStatus.value)
 toast.success(
 pendingStatus.value === 'active' ? 'Account Activated' : 'Account Deactivated',
 `Staff member "${targetStatusUser.value.name}" account status updated to ${pendingStatus.value}.`
 )
 showStatusConfirmModal.value = false
 await loadData(pagination.value.current_page)
 } catch (err: any) {
 toast.error('Status Change Error', err.response?.data?.message || 'Could not update account status.')
 } finally {
 isSubmitting.value = false
 }
}

async function handleSaveUser() {
 if (!userForm.value.name || !userForm.value.email) {
 toast.error('Validation Error', 'Name and institutional email are required.')
 return
 }

 if (!isEditing.value && (!userForm.value.password || userForm.value.password.length < 8)) {
 toast.error('Validation Error', 'Password must be at least 8 characters.')
 return
 }

 isSubmitting.value = true
 try {
 if (isEditing.value && selectedUser.value) {
 const payload: Record<string, unknown> = {
 name: userForm.value.name.trim(),
 email: userForm.value.email.trim(),
 status: userForm.value.status,
 }
 if (userForm.value.password) {
 payload.password = userForm.value.password
 }
 await usersApi.update(selectedUser.value.id, payload)
 toast.success('Updated', `User profile for "${payload.name}" updated successfully.`)
 } else {
 await usersApi.create({
 name: userForm.value.name.trim(),
 email: userForm.value.email.trim(),
 password: userForm.value.password,
 status: userForm.value.status,
 unit_id: userForm.value.unit_id ? Number(userForm.value.unit_id) : undefined,
 position_title: userForm.value.position_title || undefined,
 })
 toast.success('Created', `Staff account registered successfully.`)
 }

 showCreateEditModal.value = false
 await loadData(pagination.value.current_page)
 } catch (err: any) {
 toast.error('Save Failed', err.response?.data?.message || 'Could not save user account.')
 } finally {
 isSubmitting.value = false
 }
}

async function handleAssignRole() {
 if (!roleForm.value.role_id) {
 toast.error('Validation Error', 'Please select a role to assign.')
 return
 }

 isSubmitting.value = true
 try {
 await rolesApi.assignUserRole({
 user_id: roleForm.value.user_id,
 role_id: Number(roleForm.value.role_id),
 scope_kind: roleForm.value.scope_kind,
 unit_id: roleForm.value.unit_id ? Number(roleForm.value.unit_id) : null,
 expires_at: roleForm.value.expires_at || null,
 })
 toast.success('Role Granted', 'Role privileges granted successfully in designated scope.')
 showRoleModal.value = false
 await loadData(pagination.value.current_page)
 } catch (err: any) {
 toast.error('Assignment Failed', err.response?.data?.message || 'Could not assign role.')
 } finally {
 isSubmitting.value = false
 }
}

async function handleAssignDirectPermission() {
 if (!permissionForm.value.permission_id) {
 toast.error('Validation Error', 'Please select a permission to grant.')
 return
 }

 isSubmitting.value = true
 try {
 await rolesApi.assignUserPermission({
 user_id: permissionForm.value.user_id,
 permission_id: Number(permissionForm.value.permission_id),
 scope_kind: permissionForm.value.scope_kind,
 unit_id: permissionForm.value.unit_id ? Number(permissionForm.value.unit_id) : null,
 expires_at: permissionForm.value.expires_at || null,
 })
 toast.success('Direct Permission Granted', 'Single-user direct permission assigned successfully.')
 showPermissionModal.value = false
 await loadData(pagination.value.current_page)
 } catch (err: any) {
 toast.error('Assignment Failed', err.response?.data?.message || 'Could not grant direct permission.')
 } finally {
 isSubmitting.value = false
 }
}

async function handleRevokeRole(userOrRole: any, roleOrNone?: any) {
  const targetUser = roleOrNone ? userOrRole : selectedUser.value
  const targetRole = roleOrNone ? roleOrNone : userOrRole
  if (!targetUser) return

  isSubmitting.value = true
  try {
    await rolesApi.revokeUserRole({
      user_id: targetUser.id,
      role_id: targetRole.role_id,
      scope_id: targetRole.scope_id,
    })
    toast.success('Role Revoked', `Role "${targetRole.role_name || targetRole.role_code}" revoked successfully.`)
    await loadData(pagination.value.current_page)
    if (showLineageModal.value && selectedUser.value?.id === targetUser.id) {
      await fetchLineage(targetUser.id, lineageUnitFilter.value ? Number(lineageUnitFilter.value) : null)
    }
  } catch (err: any) {
    toast.error('Revocation Error', err.response?.data?.message || 'Could not revoke role.')
  } finally {
    isSubmitting.value = false
  }
}

async function handleRevokeDirectPermission(userOrPerm: any, permOrNone?: any) {
  const targetUser = permOrNone ? userOrPerm : selectedUser.value
  const targetPerm = permOrNone ? permOrNone : userOrPerm
  if (!targetUser) return

  isSubmitting.value = true
  try {
    await rolesApi.revokeUserPermission({
      user_id: targetUser.id,
      permission_id: targetPerm.permission_id,
      scope_id: targetPerm.scope_id,
    })
    toast.success('Permission Revoked', `Direct permission "${targetPerm.permission_code || ''}" revoked.`)
    await loadData(pagination.value.current_page)
    if (showLineageModal.value && selectedUser.value?.id === targetUser.id) {
      await fetchLineage(targetUser.id, lineageUnitFilter.value ? Number(lineageUnitFilter.value) : null)
    }
  } catch (err: any) {
    toast.error('Revocation Error', err.response?.data?.message || 'Could not revoke permission.')
  } finally {
    isSubmitting.value = false
  }
}

async function handleAssignMembership() {
 if (!membershipForm.value.unit_id) {
 toast.error('Validation Error', 'Please select an organization unit.')
 return
 }

 isSubmitting.value = true
 try {
 await usersApi.assignMembership(membershipForm.value.user_id, {
 unit_id: Number(membershipForm.value.unit_id),
 position_title: membershipForm.value.position_title || undefined,
 is_primary: membershipForm.value.is_primary,
 })
 toast.success('Membership Recorded', 'Unit affiliation assigned successfully.')
 showMembershipModal.value = false
 await loadData(pagination.value.current_page)
 } catch (err: any) {
 toast.error('Assignment Failed', err.response?.data?.message || 'Could not assign membership.')
 } finally {
 isSubmitting.value = false
 }
}

onMounted(() => {
 loadData(1)
})
</script>

<template>
 <div class="space-y-6">
 <!-- Header -->
 <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
 <div>
 <h1 class="text-xl sm:text-2xl font-semibold tracking-tight text-ink flex items-center gap-2.5">
 <Users class="w-5 h-5 sm:w-6 sm:h-6 text-primary shrink-0" />
 <span>User Accounts & Staff Directory</span>
 </h1>
 <p class="text-sm text-ink-muted mt-1">
 Dynamic database-driven RBAC roster, scoped roles, single-user direct permission overrides, and lifecycle status
 </p>
 </div>

 <div class="flex items-center gap-2.5 flex-wrap shrink-0">
 <!-- View Mode Switcher -->
 <div class="inline-flex rounded-md border border-hairline p-0.5 bg-surface-muted">
 <button
 type="button"
 class="px-2.5 py-1 text-xs font-medium rounded flex items-center gap-1.5 transition-colors"
 :class="viewMode === 'table' ? 'bg-surface text-primary font-semibold' : 'text-ink-muted hover:text-ink-secondary'"
 @click="viewMode = 'table'"
 >
 <List class="w-3.5 h-3.5" />
 Tabular view
 </button>
 <button
 type="button"
 class="px-2.5 py-1 text-xs font-medium rounded flex items-center gap-1.5 transition-colors"
 :class="viewMode === 'cards' ? 'bg-surface text-primary font-semibold' : 'text-ink-muted hover:text-ink-secondary'"
 @click="viewMode = 'cards'"
 >
 <LayoutGrid class="w-3.5 h-3.5" />
 Table cards
 </button>
 </div>

 <BaseButton variant="outline" size="sm" :loading="loading" @click="loadData(1)">
 <RefreshCw class="w-4 h-4 mr-1.5" />
 Refresh
 </BaseButton>

 <BaseButton variant="primary" size="sm" @click="openCreateModal">
 <UserPlus class="w-4 h-4 mr-1.5" />
 Register staff member
 </BaseButton>
 </div>
 </div>

 <!-- Filters & Search Bar -->
 <div class="bg-surface rounded-lg border border-line p-4 flex flex-col md:flex-row items-center justify-between gap-4">
 <div class="relative w-full md:w-72">
 <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
 <input
 v-model="searchQuery"
 type="text"
 placeholder="Search by name or email..."
 class="w-full pl-9 pr-3 py-1.5 text-xs rounded-md border border-hairline bg-surface text-ink-secondary focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 @keyup.enter="loadData(1)"
 />
 </div>

 <div class="flex items-center gap-2.5 w-full md:w-auto flex-wrap">
 <!-- Status Filter -->
 <select
 v-model="filterStatus"
 class="text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-3 py-1.5 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 @change="loadData(1)"
 >
 <option value="">All Account Statuses</option>
 <option value="active">Active Accounts</option>
 <option value="inactive">Inactive / Suspended</option>
 <option value="locked">Security Locked</option>
 </select>

 <!-- Role Filter -->
 <select
 v-model="filterRole"
 class="text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-3 py-1.5 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 @change="loadData(1)"
 >
 <option value="">All Institutional Roles</option>
 <option v-for="r in availableRoles" :key="r.id" :value="r.code">
 {{ r.name }}
 </option>
 </select>

 <!-- Organization Unit Filter -->
 <select
 v-model="filterUnit"
 class="text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-3 py-1.5 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 max-w-xs"
 @change="loadData(1)"
 >
 <option value="">All Organization Units</option>
 <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }} ({{ u.code }})</option>
 </select>

 <!-- Items Per Page Dropdown -->
 <div class="flex items-center gap-1.5 text-xs text-ink-muted">
 <span class="hidden sm:inline">Per page:</span>
 <select
 :value="pagination.per_page"
 class="text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-2 py-1.5 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 @change="handlePerPageChange(Number(($event.target as HTMLSelectElement).value))"
 >
 <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
 </select>
 </div>
 </div>
 </div>

 <!-- 1. TABULAR VIEW -->
 <div v-if="viewMode === 'table'">
 <DataTable
 :columns="tableColumns"
 :items="users"
 :loading="loading"
 :total="pagination.total"
 :page="pagination.current_page"
 :per-page="pagination.per_page"
 empty-title="No user accounts found"
 empty-description="No staff profiles match the specified filters."
 @page-change="loadData"
 >
 <template #cell-name="{ item }">
 <div class="flex items-center gap-3">
 <div
 class="w-8 h-8 rounded-md flex items-center justify-center font-semibold text-xs uppercase"
 :class="{
 'bg-[#e8f7ec] text-[#1aae39] dark:bg-[#183321] dark:text-[#42cf63]': item.status === 'active',
 'bg-surface-muted text-ink-muted ': item.status === 'inactive',
 'bg-[#fdebee] text-[#e03e3e] dark:bg-[#381619] dark:text-[#ff7373]': item.status === 'locked',
 }"
 >
 {{ item.name.slice(0, 2) }}
 </div>
 <div>
 <div class="font-medium text-ink-secondary text-xs flex items-center gap-1.5">
 {{ item.name }}
 <span class="text-xs text-ink-muted font-normal tabular-nums">v{{ item.authz_version }}</span>
 </div>
 <div class="text-xs text-ink-muted flex items-center gap-1">
 <Mail class="w-3 h-3 text-ink-muted" />
 {{ item.email }}
 </div>
 </div>
 </div>
 </template>

 <template #cell-unit="{ item }">
 <div v-if="item.primary_membership" class="space-y-0.5">
 <div class="text-xs font-medium text-ink-secondary flex items-center gap-1.5">
 <Building2 class="w-3.5 h-3.5 text-ink-muted" />
 {{ item.primary_membership.unit_name || item.primary_membership.unit_code }}
 </div>
 <div v-if="item.primary_membership.position_title" class="text-xs text-ink-muted">
 {{ item.primary_membership.position_title }}
 </div>
 </div>
 <span v-else class="text-xs text-ink-muted italic">No primary unit set</span>
 </template>

 <template #cell-roles="{ item }">
 <div class="flex flex-wrap gap-1">
 <span
 v-for="(role, idx) in item.roles || []"
 :key="idx"
 class="text-xs font-semibold px-2 py-0.5 rounded bg-surface-muted text-ink-secondary border border-hairline inline-flex items-center gap-1 group"
 >
 <span>{{ role.role_name || role.role_code }}</span>
 <span v-if="role.scope_kind" class="text-xs text-primary font-medium  ml-0.5">({{ role.scope_kind }})</span>
 <button type="button" class="opacity-0 group-hover:opacity-100 text-rose-500 hover:text-rose-700 ml-0.5 transition-opacity cursor-pointer font-bold leading-none" title="Revoke role assignment" @click.stop="handleRevokeRole(item, role)">&times;</button>
 </span>
 <span v-if="!item.roles || item.roles.length === 0" class="text-xs text-ink-muted">
 None assigned
 </span>
 </div>
 </template>

 <template #cell-direct_permissions="{ item }">
 <div class="flex flex-wrap items-center gap-1">
 <span
 v-for="(dp, idx) in item.direct_permissions || []"
 :key="idx"
 class="text-xs font-semibold px-2 py-0.5 rounded bg-primary-subtle text-primary border border-primary/20 inline-flex items-center gap-1 group"
 :title="`Direct Single-User Permission in ${dp.scope_kind} scope`"
 >
 <span>{{ dp.permission_code }}</span>
 <button type="button" class="opacity-0 group-hover:opacity-100 text-rose-500 hover:text-rose-700 ml-0.5 transition-opacity cursor-pointer font-bold leading-none" title="Revoke direct permission override" @click.stop="handleRevokeDirectPermission(item, dp)">&times;</button>
 </span>
 <span v-if="!item.direct_permissions || item.direct_permissions.length === 0" class="text-xs text-ink-muted">
 None
 </span>
 </div>
 </template>

 <template #cell-status="{ item }">
 <span
 class="px-2 py-0.5 rounded text-xs font-semibold   inline-flex items-center gap-1"
 :class="{
 'bg-[#e8f7ec] text-[#1aae39] dark:bg-[#183321] dark:text-[#42cf63] border border-[#1aae39]/20': item.status === 'active',
 'bg-surface-muted text-ink-muted border border-hairline': item.status === 'inactive',
 'bg-[#fdebee] text-[#e03e3e] dark:bg-[#381619] dark:text-[#ff7373] border border-[#e03e3e]/20': item.status === 'locked',
 }"
 >
 <span class="w-1.5 h-1.5 rounded-full" :class="{ 'bg-[#1aae39]': item.status === 'active', 'bg-ink-muted': item.status === 'inactive', 'bg-[#e03e3e]': item.status === 'locked' }" />
 {{ formatStatus(item.status) }}
 </span>
 </template>

 <template #cell-created_at="{ value }">
 <span class="text-xs text-ink-muted">
 {{ value ? new Date(value).toLocaleDateString() : '—' }}
 </span>
 </template>

 <template #cell-actions="{ item }">
 <div class="flex items-center justify-end gap-1.5">
 <!-- Quick Activate / Deactivate Toggle Button -->
 <button
 type="button"
 class="p-1 rounded transition-colors"
 :class="item.status === 'active' ? 'text-rose-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950' : 'text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50 dark:hover:bg-emerald-950'"
 :title="item.status === 'active' ? 'Deactivate User Account' : 'Activate User Account'"
 @click="promptToggleStatus(item)"
 >
 <UserX v-if="item.status === 'active'" class="w-4 h-4" />
 <UserCheck v-else class="w-4 h-4" />
 </button>

 <!-- Assign Scoped Role Button -->
 <button
 type="button"
 class="p-1 text-ink-muted hover:text-primary hover:bg-canvas-soft rounded transition-colors"
 title="Assign Scoped Role"
 @click="openAssignRoleModal(item)"
 >
 <Shield class="w-4 h-4" />
 </button>

 <!-- Assign Direct Permission Button -->
 <button
 type="button"
 class="p-1 text-ink-muted hover:text-amber-600 hover:bg-canvas-soft rounded transition-colors"
 title="Grant Single-User Direct Permission"
 @click="openDirectPermissionModal(item)"
 >
 <KeyRound class="w-4 h-4" />
 </button>

 <!-- Assign Unit Membership Button -->
 <button
 type="button"
 class="p-1 text-ink-muted hover:text-primary hover:bg-canvas-soft rounded transition-colors"
 title="Assign Unit Membership"
 @click="openMembershipModal(item)"
 >
 <Building2 class="w-4 h-4" />
 </button>

 <!-- Inspect Effective Access Lineage Button -->
 <button
 type="button"
 class="p-1 text-ink-muted hover:text-emerald-600 hover:bg-canvas-soft rounded transition-colors"
 title="Inspect Effective Permissions & Lineage"
 @click="openLineageModal(item)"
 >
 <ShieldCheck class="w-4 h-4 text-emerald-600" />
 </button>

 <!-- Edit User Button -->
 <button
 type="button"
 class="p-1 text-ink-muted hover:text-primary hover:bg-canvas-soft rounded transition-colors"
 title="Edit Staff Profile"
 @click="openEditModal(item)"
 >
 <Edit2 class="w-4 h-4" />
 </button>
 </div>
 </template>
 </DataTable>
 </div>

 <!-- 2. TABLE CARDS VIEW -->
 <div v-else class="space-y-4">
 <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
 <div v-for="n in 6" :key="n" class="bg-surface rounded-lg border border-hairline p-5 animate-pulse space-y-3">
 <div class="flex items-center gap-3">
 <div class="w-10 h-10 rounded-md bg-surface-muted" />
 <div class="space-y-1.5 flex-1">
 <div class="h-4 bg-surface-muted rounded w-3/4" />
 <div class="h-3 bg-surface-muted rounded w-1/2" />
 </div>
 </div>
 <div class="h-10 bg-canvas-soft/60 rounded" />
 <div class="h-6 bg-canvas-soft/60 rounded w-2/3" />
 </div>
 </div>

 <div v-else-if="users.length === 0" class="bg-surface rounded-lg border border-hairline p-12 text-center">
 <Users class="w-12 h-12 text-ink-muted mx-auto mb-3" />
 <h3 class="text-sm font-semibold text-ink-secondary">No user accounts found</h3>
 <p class="text-xs text-ink-muted mt-1">No staff members match the currently applied filters.</p>
 </div>

 <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
 <div
 v-for="user in users"
 :key="user.id"
 class="bg-surface rounded-lg border border-hairline p-5 hover:border-primary/40 transition-colors flex flex-col justify-between"
 >
 <div class="space-y-4">
 <!-- Card Header: Avatar, Name, Email, Status Pill -->
 <div class="flex items-start justify-between gap-3">
 <div class="flex items-center gap-3">
 <div
 class="w-10 h-10 rounded-md flex items-center justify-center font-bold text-sm  shrink-0"
 :class="{
 'bg-[#e8f7ec] text-[#1aae39] dark:bg-[#183321] dark:text-[#42cf63]': user.status === 'active',
 'bg-surface-muted text-ink-muted ': user.status === 'inactive',
 'bg-[#fdebee] text-[#e03e3e] dark:bg-[#381619] dark:text-[#ff7373]': user.status === 'locked',
 }"
 >
 {{ user.name.slice(0, 2) }}
 </div>
 <div>
 <h3 class="font-semibold text-sm text-ink-secondary leading-tight">
 {{ user.name }}
 </h3>
 <div class="text-xs text-ink-muted flex items-center gap-1 mt-0.5">
 <Mail class="w-3 h-3 text-ink-muted" />
 {{ user.email }}
 </div>
 </div>
 </div>

 <!-- Status Badge -->
 <span
 class="px-2 py-0.5 rounded text-xs font-semibold   inline-flex items-center gap-1 shrink-0"
 :class="{
 'bg-[#e8f7ec] text-[#1aae39] dark:bg-[#183321] dark:text-[#42cf63] border border-[#1aae39]/20': user.status === 'active',
 'bg-surface-muted text-ink-muted border border-hairline': user.status === 'inactive',
 'bg-[#fdebee] text-[#e03e3e] dark:bg-[#381619] dark:text-[#ff7373] border border-[#e03e3e]/20': user.status === 'locked',
 }"
 >
 <span class="w-1.5 h-1.5 rounded-full" :class="{ 'bg-[#1aae39]': user.status === 'active', 'bg-ink-muted': user.status === 'inactive', 'bg-[#e03e3e]': user.status === 'locked' }" />
 {{ formatStatus(user.status) }}
 </span>
 </div>

 <!-- Primary Organization Unit & Job Position -->
 <div class="bg-canvas-soft rounded-md p-2.5 text-xs">
 <div class="flex items-center gap-1.5 font-medium text-ink-secondary">
 <Building2 class="w-3.5 h-3.5 text-primary" />
 <span>{{ user.primary_membership?.unit_name || user.primary_membership?.unit_code || 'No Primary Unit' }}</span>
 </div>
 <div v-if="user.primary_membership?.position_title" class="text-xs text-ink-muted mt-0.5 pl-5">
 {{ user.primary_membership.position_title }}
 </div>
 </div>

 <!-- Assigned Roles -->
 <div class="space-y-1">
 <div class="text-xs font-semibold text-ink-muted   flex items-center gap-1">
 <Shield class="w-3 h-3 text-ink-muted" />
 Scoped Roles
 </div>
 <div class="flex flex-wrap gap-1">
 <span
 v-for="(r, idx) in user.roles || []"
 :key="idx"
 class="text-xs font-semibold px-2 py-0.5 rounded bg-surface-muted text-ink-secondary border border-hairline"
 >
 {{ r.role_name || r.role_code }}
 <span v-if="r.scope_kind" class="text-xs text-primary font-medium  ml-0.5">({{ r.scope_kind }})</span>
 </span>
 <span v-if="!user.roles || user.roles.length === 0" class="text-xs text-ink-muted italic">
 No roles assigned
 </span>
 </div>
 </div>

 <!-- Direct Single-User Permissions -->
 <div class="space-y-1">
 <div class="text-xs font-semibold text-ink-muted   flex items-center gap-1">
 <KeyRound class="w-3 h-3 text-amber-500" />
 Single-User Direct Overrides
 </div>
 <div class="flex flex-wrap gap-1">
 <span
 v-for="(dp, idx) in user.direct_permissions || []"
 :key="idx"
 class="text-xs font-semibold px-2 py-0.5 rounded bg-[#fff4e6] dark:bg-[#382412] text-[#dd5b00] dark:text-[#ff9243] border border-[#dd5b00]/20"
 >
 {{ dp.permission_code }}
 </span>
 <span v-if="!user.direct_permissions || user.direct_permissions.length === 0" class="text-xs text-ink-muted italic">
 No individual overrides
 </span>
 </div>
 </div>

 <!-- Registration Date & Authz Version -->
 <div class="flex items-center justify-between text-xs text-ink-muted pt-1 border-t border-hairline">
 <span class="flex items-center gap-1">
 <Calendar class="w-3 h-3" />
 {{ user.created_at ? new Date(user.created_at).toLocaleDateString() : 'N/A' }}
 </span>
 <span class="text-xs tabular-nums">Session Guard: v{{ user.authz_version }}</span>
 </div>
 </div>

 <!-- Card Actions Footer -->
 <div class="pt-4 mt-4 border-t border-hairline flex items-center justify-between">
 <!-- Activate / Deactivate Toggle Button -->
 <button
 type="button"
 class="px-2.5 py-1 text-xs font-semibold rounded-md flex items-center gap-1.5 transition-colors cursor-pointer"
 :class="user.status === 'active' ? 'bg-[#fdebee] text-[#e03e3e] hover:bg-rose-100 dark:bg-[#381619] dark:text-[#ff7373]' : 'bg-[#e8f7ec] text-[#1aae39] hover:bg-emerald-100 dark:bg-[#183321] dark:text-[#42cf63]'"
 @click="promptToggleStatus(user)"
 >
 <UserX v-if="user.status === 'active'" class="w-3.5 h-3.5" />
 <UserCheck v-else class="w-3.5 h-3.5" />
 {{ user.status === 'active' ? 'Deactivate' : 'Activate' }}
 </button>

 <!-- Action Toolbar -->
 <div class="flex items-center gap-1">
 <button
 type="button"
 class="p-1.5 text-ink-muted hover:text-primary hover:bg-canvas-soft rounded-md transition-colors"
 title="Assign Scoped Role"
 @click="openAssignRoleModal(user)"
 >
 <Shield class="w-4 h-4" />
 </button>

 <button
 type="button"
 class="p-1.5 text-ink-muted hover:text-amber-600 hover:bg-canvas-soft rounded-md transition-colors"
 title="Single-User Permissions"
 @click="openDirectPermissionModal(user)"
 >
 <KeyRound class="w-4 h-4" />
 </button>

 <button
 type="button"
 class="p-1.5 text-ink-muted hover:text-primary hover:bg-canvas-soft rounded-md transition-colors"
 title="Assign Unit"
 @click="openMembershipModal(user)"
 >
 <Building2 class="w-4 h-4" />
 </button>

 <button
 type="button"
 class="p-1.5 text-ink-muted hover:text-emerald-600 hover:bg-canvas-soft rounded-md transition-colors"
 title="Inspect Effective Permissions & Lineage"
 @click="openLineageModal(user)"
 >
 <ShieldCheck class="w-4 h-4 text-emerald-600" />
 </button>

 <button
 type="button"
 class="p-1.5 text-ink-muted hover:text-primary hover:bg-canvas-soft rounded-md transition-colors"
 title="Edit Profile"
 @click="openEditModal(user)"
 >
 <Edit2 class="w-4 h-4" />
 </button>
 </div>
 </div>
 </div>
 </div>

 <!-- Pagination for Table Cards View -->
 <Pagination
 :meta="pagination"
 :current-page="pagination.current_page"
 :last-page="pagination.last_page"
 :total="pagination.total"
 :per-page="pagination.per_page"
 @page-change="loadData"
 />
 </div>

 <!-- Create / Edit Staff Profile Modal -->
 <ModalDialog
 :open="showCreateEditModal"
 :title="isEditing ? 'Modify Staff Profile' : 'Register New Staff Member'"
 max-width="md"
 @close="showCreateEditModal = false"
 >
 <div class="space-y-4">
 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Full Staff Name *
 </label>
 <BaseInput v-model="userForm.name" placeholder="e.g. Dr. Abebe Bikila" />
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Institutional Email *
 </label>
 <BaseInput v-model="userForm.email" type="email" placeholder="e.g. abebe.b@wu.edu.et" />
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 {{ isEditing ? 'Change Password (leave blank to keep current)' : 'Account Password *' }}
 </label>
 <BaseInput v-model="userForm.password" type="password" placeholder="Min. 8 characters" />
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Account Status *
 </label>
 <select
 v-model="userForm.status"
 class="w-full text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 >
 <option value="active">Active (Access Allowed)</option>
 <option value="inactive">Inactive (Suspended)</option>
 <option value="locked">Locked (Security Lockout)</option>
 </select>
 </div>

 <div v-if="!isEditing" class="border-t border-hairline pt-3 space-y-3">
 <div class="text-xs font-semibold text-ink-secondary">
 Initial Primary Organizational Unit (Optional)
 </div>
 <div>
 <label class="block text-xs text-ink-muted mb-1">Department / Office</label>
 <select
 v-model="userForm.unit_id"
 class="w-full text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 >
 <option :value="''">None (Assign later)</option>
 <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }} ({{ u.code }})</option>
 </select>
 </div>

 <div>
 <label class="block text-xs text-ink-muted mb-1">Position / Job Title</label>
 <BaseInput v-model="userForm.position_title" placeholder="e.g. Dean, Senior Lecturer, Registrar Officer" />
 </div>
 </div>
 </div>

 <template #footer>
 <div class="flex items-center justify-end gap-2">
 <BaseButton variant="outline" size="sm" @click="showCreateEditModal = false">
 Cancel
 </BaseButton>
 <BaseButton variant="primary" size="sm" :loading="isSubmitting" @click="handleSaveUser">
 {{ isEditing ? 'Save Changes' : 'Create Account' }}
 </BaseButton>
 </div>
 </template>
 </ModalDialog>

 <!-- Assign Scoped Role Modal -->
 <ModalDialog
 :open="showRoleModal"
 :title="`Assign Scoped Role: ${selectedUser?.name || 'Staff'}`"
 max-width="md"
 @close="showRoleModal = false"
 >
 <div class="space-y-4">
 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Select Role *
 </label>
 <select
 v-model="roleForm.role_id"
 class="w-full text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 >
 <option v-for="r in availableRoles" :key="r.id" :value="r.id">
 {{ r.name }} ({{ r.code }})
 </option>
 </select>
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Authorization Scope Kind *
 </label>
 <select
 v-model="roleForm.scope_kind"
 class="w-full text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 >
 <option value="global">Global (Full University-Wide Scope)</option>
 <option value="unit">Unit (Restricted to Specific Unit Only)</option>
 <option value="subtree">Subtree (Unit and all Child Departments)</option>
 </select>
 </div>

 <div v-if="roleForm.scope_kind !== 'global'">
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Target Organization Unit *
 </label>
 <select
 v-model="roleForm.unit_id"
 class="w-full text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 >
 <option :value="null">Select Target Unit...</option>
 <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }} ({{ u.code }})</option>
 </select>
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Expiration Date (Optional)
 </label>
 <input
 v-model="roleForm.expires_at"
 type="datetime-local"
 class="w-full text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 />
 </div>
 </div>

 <template #footer>
 <div class="flex items-center justify-end gap-2">
 <BaseButton variant="outline" size="sm" @click="showRoleModal = false">
 Cancel
 </BaseButton>
 <BaseButton variant="primary" size="sm" :loading="isSubmitting" @click="handleAssignRole">
 Grant Scoped Role
 </BaseButton>
 </div>
 </template>
 </ModalDialog>

 <!-- Direct Single-User Permission Modal -->
 <ModalDialog
 :open="showPermissionModal"
 :title="`Single-User Direct Permissions: ${selectedUser?.name || 'Staff'}`"
 max-width="lg"
 @close="showPermissionModal = false"
 >
 <div class="space-y-5">
 <!-- Currently Assigned Direct Permissions -->
 <div>
 <div class="text-xs font-semibold text-ink-secondary mb-2">
 Active Direct Single-User Permissions
 </div>
 <div v-if="!selectedUser?.direct_permissions || selectedUser.direct_permissions.length === 0" class="text-xs text-ink-muted italic bg-canvas-soft p-3 rounded-md">
 No direct single-user permissions currently assigned to this account. All permissions derive from assigned roles.
 </div>
 <div v-else class="space-y-1.5 max-h-48 overflow-y-auto">
 <div
 v-for="dp in selectedUser.direct_permissions"
 :key="dp.id"
 class="flex items-center justify-between p-2 rounded-md border border-hairline bg-surface text-xs"
 >
 <div>
 <span class="font-semibold text-primary">{{ dp.permission_code }}</span>
 <span class="text-ink-muted ml-1.5">({{ dp.permission_name || 'Permission' }})</span>
 <span class="text-xs  font-semibold text-ink-muted ml-2">[{{ dp.scope_kind }}]</span>
 </div>
 <button
 type="button"
 class="text-rose-500 hover:text-rose-700 p-1 rounded"
 title="Revoke Permission"
 @click="handleRevokeDirectPermission(dp)"
 >
 <Trash2 class="w-3.5 h-3.5" />
 </button>
 </div>
 </div>
 </div>

 <!-- Grant New Direct Permission Form -->
 <div class="border-t border-hairline pt-4 space-y-3">
 <div class="text-xs font-semibold text-ink-secondary">
 Grant Additional Direct Permission Override
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Select Permission *
 </label>
 <select
 v-model="permissionForm.permission_id"
 class="w-full text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 >
 <option v-for="p in availablePermissions" :key="p.id" :value="p.id">
 {{ p.code }} — {{ p.name }}
 </option>
 </select>
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Scope Kind *
 </label>
 <select
 v-model="permissionForm.scope_kind"
 class="w-full text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 >
 <option value="global">Global (Full University Scope)</option>
 <option value="unit">Unit (Restricted to Unit Only)</option>
 <option value="subtree">Subtree (Unit & Sub-departments)</option>
 </select>
 </div>

 <div v-if="permissionForm.scope_kind !== 'global'">
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Target Organization Unit *
 </label>
 <select
 v-model="permissionForm.unit_id"
 class="w-full text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 >
 <option :value="null">Select Target Unit...</option>
 <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }} ({{ u.code }})</option>
 </select>
 </div>
 </div>
 </div>

 <template #footer>
 <div class="flex items-center justify-end gap-2">
 <BaseButton variant="outline" size="sm" @click="showPermissionModal = false">
 Close
 </BaseButton>
 <BaseButton variant="primary" size="sm" :loading="isSubmitting" @click="handleAssignDirectPermission">
 Grant Direct Permission
 </BaseButton>
 </div>
 </template>
 </ModalDialog>

 <!-- Assign Membership Modal -->
 <ModalDialog
 :open="showMembershipModal"
 :title="`Assign Unit Membership: ${selectedUser?.name || 'Staff'}`"
 max-width="md"
 @close="showMembershipModal = false"
 >
 <div class="space-y-4">
 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Organization Unit *
 </label>
 <select
 v-model="membershipForm.unit_id"
 class="w-full text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 >
 <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }} ({{ u.code }})</option>
 </select>
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Position / Job Title
 </label>
 <BaseInput v-model="membershipForm.position_title" placeholder="e.g. Department Head, Lecturer" />
 </div>

 <div class="flex items-center gap-2 pt-2">
 <input
 id="make-primary"
 v-model="membershipForm.is_primary"
 type="checkbox"
 class="w-4 h-4 text-primary rounded border-hairline focus:ring-primary"
 />
 <label for="make-primary" class="text-xs font-medium text-ink-secondary">
 Set as Primary Organizational Membership
 </label>
 </div>
 </div>

 <template #footer>
 <div class="flex items-center justify-end gap-2">
 <BaseButton variant="outline" size="sm" @click="showMembershipModal = false">
 Cancel
 </BaseButton>
 <BaseButton variant="primary" size="sm" :loading="isSubmitting" @click="handleAssignMembership">
 Assign Membership
 </BaseButton>
 </div>
 </template>
 </ModalDialog>

 <!-- Account Status Confirmation Modal -->
 <ModalDialog
 :open="showStatusConfirmModal"
 :title="pendingStatus === 'active' ? 'Activate Staff Account' : 'Deactivate Staff Account'"
 max-width="sm"
 @close="showStatusConfirmModal = false"
 >
 <div class="space-y-3">
 <div class="flex items-center gap-3">
 <div
 class="w-10 h-10 rounded-md flex items-center justify-center shrink-0"
 :class="pendingStatus === 'active' ? 'bg-[#e8f7ec] text-[#1aae39]' : 'bg-[#fdebee] text-[#e03e3e]'"
 >
 <UserCheck v-if="pendingStatus === 'active'" class="w-5 h-5" />
 <UserX v-else class="w-5 h-5" />
 </div>
 <div>
 <div class="text-sm font-semibold text-ink-secondary">
 {{ targetStatusUser?.name }}
 </div>
 <div class="text-xs text-ink-muted">
 {{ targetStatusUser?.email }}
 </div>
 </div>
 </div>

 <p class="text-xs text-ink-muted">
 <span v-if="pendingStatus === 'active'">
 Are you sure you want to <strong>activate</strong> this account? The user will be permitted to authenticate and access their authorized modules.
 </span>
 <span v-else>
 Are you sure you want to <strong>deactivate</strong> this account? Active sessions will be invalidated immediately and login access will be denied.
 </span>
 </p>
 </div>

 <template #footer>
 <div class="flex items-center justify-end gap-2">
 <BaseButton variant="outline" size="sm" @click="showStatusConfirmModal = false">
 Cancel
 </BaseButton>
 <BaseButton
 variant="primary"
 size="sm"
 :class="pendingStatus === 'active' ? '!bg-emerald-600 hover:!bg-emerald-700 text-white' : '!bg-rose-600 hover:!bg-rose-700 text-white'"
 :loading="isSubmitting"
 @click="handleConfirmToggleStatus"
 >
 Confirm {{ pendingStatus === 'active' ? 'Activation' : 'Deactivation' }}
 </BaseButton>
 </div>
 </template>
 </ModalDialog>

 <!-- Inspect Access & Effective Permissions Lineage Modal -->
 <ModalDialog
 :open="showLineageModal"
 :title="`Effective Permissions & Access Lineage: ${selectedUser?.name || 'Staff'}`"
 size="xl"
 @close="showLineageModal = false"
 >
 <div class="space-y-4 max-h-[75vh] overflow-y-auto pr-1">
 <!-- Target Evaluation Scope & Context Bar -->
 <div class="p-3 bg-surface-muted border border-hairline rounded-lg flex flex-col sm:flex-row sm:items-center justify-between gap-3">
 <div class="space-y-0.5">
 <div class="text-xs font-semibold text-ink-secondary flex items-center gap-1.5">
 <Building2 class="w-4 h-4 text-primary" />
 Evaluation Scope Context
 </div>
 <p class="text-xs text-ink-muted">
 Inspect effective permissions for this staff member either globally or within an organizational unit.
 </p>
 </div>
 <div class="flex items-center gap-2">
 <label class="text-xs font-medium text-ink-secondary whitespace-nowrap">Target Unit:</label>
 <select
 v-model="lineageUnitFilter"
 class="text-xs rounded border border-hairline bg-surface text-ink-secondary px-2.5 py-1.5 focus:outline-none focus:border-primary"
 @change="handleLineageUnitChange"
 >
 <option value="">Global Evaluation (Unrestricted / All Units)</option>
 <option v-for="u in units" :key="u.id" :value="u.id">
 {{ u.name }} ({{ u.code }})
 </option>
 </select>
 </div>
 </div>

 <!-- Warnings Banner -->
 <div
 v-if="lineageData && lineageData.warnings && lineageData.warnings.length > 0"
 class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-lg space-y-1.5"
 >
 <div class="text-xs font-semibold text-amber-800 dark:text-amber-200 flex items-center gap-1.5">
 <AlertCircle class="w-4 h-4 text-amber-600 dark:text-amber-400" />
 Access Configuration Warnings ({{ lineageData.warnings.length }})
 </div>
 <ul class="text-xs text-amber-700 dark:text-amber-300 space-y-0.5 list-disc list-inside">
 <li v-for="(warn, wIdx) in lineageData.warnings" :key="wIdx">
 {{ warn }}
 </li>
 </ul>
 </div>

 <!-- Summary KPI Row -->
 <div v-if="lineageData" class="grid grid-cols-2 sm:grid-cols-4 gap-3">
 <div class="p-3 bg-surface border border-hairline rounded-lg">
 <div class="text-xs  font-semibold text-ink-muted">Effective Capabilities</div>
 <div class="text-lg font-bold text-emerald-600 mt-0.5">{{ lineageData.effective_count }}</div>
 <div class="text-xs text-ink-muted">in {{ lineageData.target_unit_name || 'Global Scope' }}</div>
 </div>
 <div class="p-3 bg-surface border border-hairline rounded-lg">
 <div class="text-xs  font-semibold text-ink-muted">Assigned Roles</div>
 <div class="text-lg font-bold text-ink-secondary mt-0.5">{{ selectedUser?.roles?.length || 0 }}</div>
 <div class="text-xs text-ink-muted">catalog roles assigned</div>
 </div>
 <div class="p-3 bg-surface border border-hairline rounded-lg">
 <div class="text-xs  font-semibold text-ink-muted">Direct Overrides</div>
 <div class="text-lg font-bold text-amber-600 mt-0.5">{{ selectedUser?.direct_permissions?.length || 0 }}</div>
 <div class="text-xs text-ink-muted">single-user grants</div>
 </div>
 <div class="p-3 bg-surface border border-hairline rounded-lg">
 <div class="text-xs  font-semibold text-ink-muted">Scopes Held</div>
 <div class="text-lg font-bold text-primary mt-0.5">{{ lineageData.scopes_held?.length || 0 }}</div>
 <div class="text-xs text-ink-muted">distinct scopes</div>
 </div>
 </div>

 <!-- Filters & Search Toolbar -->
 <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pt-1">
 <div class="relative flex-1 max-w-sm">
 <Search class="w-3.5 h-3.5 absolute left-2.5 top-2.5 text-ink-muted" />
 <input
 v-model="lineageSearch"
 type="text"
 placeholder="Filter permissions or source role..."
 class="w-full text-xs pl-8 pr-3 py-1.5 rounded border border-hairline bg-surface text-ink-secondary focus:outline-none focus:border-primary"
 />
 </div>

 <div class="flex items-center gap-2">
 <!-- Module Filter -->
 <select
 v-model="lineageModuleFilter"
 class="text-xs rounded border border-hairline bg-surface text-ink-secondary px-2.5 py-1.5 focus:outline-none focus:border-primary"
 >
 <option value="">All Modules</option>
 <option v-for="m in distinctLineageModules" :key="m" :value="m">
 {{ m }}
 </option>
 </select>

 <!-- Status Filter -->
 <select
 v-model="lineageStatusFilter"
 class="text-xs rounded border border-hairline bg-surface text-ink-secondary px-2.5 py-1.5 focus:outline-none focus:border-primary"
 >
 <option value="all">All Grants ({{ lineageData?.lineage?.length || 0 }})</option>
 <option value="effective">Effective Only</option>
 <option value="non_effective">Inactive / Expired / Out of Scope</option>
 </select>
 </div>
 </div>

 <!-- Loading State -->
 <div v-if="loadingLineage" class="py-12 text-center text-xs text-ink-muted">
 <RefreshCw class="w-5 h-5 mx-auto animate-spin text-primary mb-2" />
 Calculating effective permissions and scope lineage...
 </div>

 <!-- Empty State -->
 <div v-else-if="filteredLineage.length === 0" class="py-8 text-center text-xs text-ink-muted border border-dashed border-hairline rounded-lg">
 No permission records match the selected scope and filters.
 </div>

 <!-- Lineage Table -->
 <div v-else class="border border-hairline rounded-lg overflow-hidden">
 <table class="w-full text-left text-xs">
 <thead class="bg-surface-muted text-ink-muted   text-xs font-semibold border-b border-hairline">
 <tr>
 <th class="px-3 py-2">Permission & Description</th>
 <th class="px-3 py-2">Lineage Path (User &rarr; Source &rarr; Scope)</th>
 <th class="px-3 py-2">Scope / Unit</th>
 <th class="px-3 py-2">Validity</th>
 <th class="px-3 py-2 text-right">Effective Status</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-hairline">
 <tr
 v-for="(item, idx) in filteredLineage"
 :key="idx"
 class="hover:bg-surface-hover/50 transition-colors"
 :class="{ 'opacity-60 bg-surface-muted/30': !item.is_effective }"
 >
 <!-- Permission & Module -->
 <td class="px-3 py-2.5 align-top">
 <div class="font-mono text-xs font-medium text-ink-secondary flex items-center gap-1.5">
 <span class="text-xs  px-1 py-0.2 rounded bg-surface-muted text-ink-muted border border-hairline font-sans font-semibold">
 {{ item.module }}
 </span>
 {{ item.permission_code }}
 </div>
 <div class="text-xs text-ink-muted mt-0.5 line-clamp-1">
 {{ item.permission_name }}
 </div>
 </td>

 <!-- Lineage Path (Visual User -> Source -> Permission -> Scope) -->
 <td class="px-3 py-2.5 align-top">
 <div class="flex items-center flex-wrap gap-1 text-xs">
 <span class="font-medium text-ink-secondary">{{ selectedUser?.name?.split(' ')[0] || 'User' }}</span>
 <ArrowRight class="w-3 h-3 text-ink-muted shrink-0" />
 <span
 class="px-1.5 py-0.5 rounded text-xs font-semibold"
 :class="item.source_type === 'direct' ? 'bg-[#fff4e6] text-[#dd5b00] dark:bg-[#382412] dark:text-[#ff9243]' : 'bg-surface-muted text-ink-secondary border border-hairline'"
 >
 {{ item.source_name }}
 </span>
 <ArrowRight class="w-3 h-3 text-ink-muted shrink-0" />
 <span class="font-mono text-xs text-ink-secondary">{{ item.permission_code }}</span>
 </div>
 </td>

 <!-- Scope -->
 <td class="px-3 py-2.5 align-top">
 <div class="text-xs font-medium text-ink-secondary flex items-center gap-1">
 <span
 class="uppercase text-xs font-semibold px-1.5 py-0.5 rounded"
 :class="{
 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300': item.scope_kind === 'global',
 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300': item.scope_kind === 'unit',
 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300': item.scope_kind === 'subtree',
 }"
 >
 {{ item.scope_kind }}
 </span>
 <span class="text-ink-muted truncate max-w-[180px] sm:max-w-[260px] lg:max-w-xs" :title="item.scope_unit_name || ''">
 {{ item.scope_unit_name || 'All Units' }}
 </span>
 </div>
 </td>

 <!-- Validity / Expiration -->
 <td class="px-3 py-2.5 align-top whitespace-nowrap">
 <div v-if="item.expires_at" class="text-xs" :class="item.is_expired ? 'text-rose-600 font-semibold' : 'text-ink-muted'">
 <div class="flex items-center gap-1">
 <Clock class="w-3 h-3" />
 {{ new Date(item.expires_at).toLocaleDateString() }}
 </div>
 <div v-if="item.is_expired" class="text-xs text-rose-500 font-medium">Expired</div>
 </div>
 <span v-else class="text-xs text-ink-muted">Indefinite</span>
 </td>

 <!-- Status Badge -->
 <td class="px-3 py-2.5 align-top text-right whitespace-nowrap">
 <span
 class="px-2 py-0.5 rounded text-xs font-semibold   inline-flex items-center gap-1"
 :class="{
 'bg-[#e8f7ec] text-[#1aae39] dark:bg-[#183321] dark:text-[#42cf63] border border-[#1aae39]/20': item.is_effective,
 'bg-[#fdebee] text-[#e03e3e] dark:bg-[#381619] dark:text-[#ff7373] border border-[#e03e3e]/20': item.status_label === 'Expired',
 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300 border border-amber-300': item.status_label === 'Out of Scope',
 'bg-surface-muted text-ink-muted border border-hairline': item.status_label !== 'Expired' && item.status_label !== 'Out of Scope' && !item.is_effective,
 }"
 >
 <Check v-if="item.is_effective" class="w-3 h-3 text-[#1aae39]" />
 <span v-else class="w-1.5 h-1.5 rounded-full bg-current" />
 {{ item.status_label }}
 </span>
 </td>
 </tr>
 </tbody>
 </table>
 </div>
 </div>

 <template #footer>
 <div class="flex items-center justify-between">
 <div class="text-xs text-ink-muted flex items-center gap-1">
 <Info class="w-3.5 h-3.5" />
 Dynamic lineage is evaluated instantly in real-time; permissions apply without re-login.
 </div>
 <BaseButton variant="outline" size="sm" @click="showLineageModal = false">
 Close
 </BaseButton>
 </div>
 </template>
 </ModalDialog>
 </div>
</template>

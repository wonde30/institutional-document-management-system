<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { rolesApi } from '@/api/client'
import type { RoleItem, PermissionItem, RoleAssignedUser } from '@/types'
import { useToast } from '@/composables/useToast'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import PageHeader from '@/components/PageHeader.vue'
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
  Users,
  UserCheck,
  AlertCircle,
  Building2,
  Clock,
  Globe,
  ArrowRight
} from 'lucide-vue-next'

const toast = useToast()
const loading = ref(true)
const isSubmitting = ref(false)
const roles = ref<RoleItem[]>([])
const allPermissions = ref<PermissionItem[]>([])
const searchQuery = ref('')
const statusFilter = ref<'all' | 'active' | 'inactive'>('all')
const typeFilter = ref<'all' | 'system' | 'custom'>('all')

// Modal state: Create/Edit
const isModalOpen = ref(false)
const isEditing = ref(false)
const selectedRoleId = ref<number | null>(null)
const rolePermSearch = ref('')

const roleForm = ref({
  code: '',
  name: '',
  description: '',
  active: true,
  permission_ids: [] as number[],
})

// Modal state: View Assigned Users
const showUsersModal = ref(false)
const loadingUsers = ref(false)
const selectedRoleForUsers = ref<RoleItem | null>(null)
const roleUsersList = ref<RoleAssignedUser[]>([])

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

// Filtered permissions inside modal matrix
const modalFilteredGroupedPermissions = computed(() => {
  const q = rolePermSearch.value.toLowerCase().trim()
  if (!q) return groupedPermissions.value

  const filtered: Record<string, PermissionItem[]> = {}
  for (const [mod, perms] of Object.entries(groupedPermissions.value)) {
    const matched = perms.filter(
      (p) => p.code.toLowerCase().includes(q) || p.name.toLowerCase().includes(q)
    )
    if (matched.length > 0) {
      filtered[mod] = matched
    }
  }
  return filtered
})

const filteredRoles = computed(() => {
  return roles.value.filter((r) => {
    // Search query
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase()
      const match = r.name.toLowerCase().includes(q) || r.code.toLowerCase().includes(q)
      if (!match) return false
    }

    // Status filter
    if (statusFilter.value === 'active' && !r.active) return false
    if (statusFilter.value === 'inactive' && r.active) return false

    // Type filter
    if (typeFilter.value === 'system' && !r.is_system) return false
    if (typeFilter.value === 'custom' && r.is_system) return false

    return true
  })
})

async function loadData() {
  loading.value = true
  try {
    const [rolesRes, permsRes] = await Promise.all([
      rolesApi.list(),
      rolesApi.permissions({ all: true }),
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
  rolePermSearch.value = ''
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
  rolePermSearch.value = ''

  try {
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

async function openRoleUsersModal(role: RoleItem) {
  selectedRoleForUsers.value = role
  showUsersModal.value = true
  loadingUsers.value = true
  roleUsersList.value = []

  try {
    const res = await rolesApi.getUsers(role.id)
    roleUsersList.value = res.data.data.users
  } catch (err: any) {
    toast.error('Users Error', err.response?.data?.message || 'Failed to load assigned users.')
  } finally {
    loadingUsers.value = false
  }
}

async function toggleRoleActive(role: RoleItem) {
  if (role.code === 'super_admin') {
    toast.error('Protected Role', 'The Super Administrator role cannot be deactivated.')
    return
  }

  const newStatus = !role.active
  try {
    await rolesApi.update(role.id, { active: newStatus })
    role.active = newStatus
    toast.success('Status Updated', `Role "${role.name}" is now ${newStatus ? 'active' : 'inactive'}.`)
  } catch (err: any) {
    toast.error('Update Failed', err.response?.data?.message || 'Could not update role status.')
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
      await rolesApi.update(selectedRoleId.value, {
        name: roleForm.value.name.trim(),
        description: roleForm.value.description.trim() || undefined,
        active: roleForm.value.active,
      })
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
    <PageHeader
      title="Scoped Role Management"
      subtitle="Configure institutional roles, assignable capability bundles, and multi-unit authorization scopes without code changes"
    >
      <template #actions>
        <BaseButton variant="outline" size="sm" :loading="loading" @click="loadData">
          <RefreshCw class="w-4 h-4 mr-1.5" />
          Refresh
        </BaseButton>

        <BaseButton variant="primary" size="sm" @click="openCreateModal">
          <Plus class="w-4 h-4 mr-1.5" />
          Create custom role
        </BaseButton>
      </template>
    </PageHeader>

    <!-- Search & Filter Bar -->
    <div class="bg-surface rounded-lg border border-line p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div class="flex flex-wrap items-center gap-2.5 flex-1">
        <div class="relative w-full sm:w-72">
          <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Filter roles by name or code..."
            class="w-full pl-9 pr-3 py-1.5 text-xs rounded-md border border-hairline bg-surface text-ink-secondary focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
          />
        </div>

        <!-- Status Filter -->
        <select
          v-model="statusFilter"
          class="text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-2.5 py-1.5 focus:outline-none focus:border-primary"
        >
          <option value="all">All Statuses</option>
          <option value="active">Active Only</option>
          <option value="inactive">Inactive Only</option>
        </select>

        <!-- Role Type Filter -->
        <select
          v-model="typeFilter"
          class="text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-2.5 py-1.5 focus:outline-none focus:border-primary"
        >
          <option value="all">All Types</option>
          <option value="system">System Roles</option>
          <option value="custom">Custom Roles</option>
        </select>
      </div>

      <div class="text-xs text-ink-muted font-medium whitespace-nowrap">
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
        class="bg-surface rounded-lg border border-hairline p-5 hover:border-primary/40 transition-colors flex flex-col justify-between"
        :class="{ 'opacity-70 bg-surface-muted/40': !role.active }"
      >
        <div>
          <div class="flex items-start justify-between gap-3 mb-2">
            <div>
              <div class="flex items-center gap-1.5">
                <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded bg-primary-subtle text-primary border border-primary/20 uppercase">
                  {{ role.code }}
                </span>
                <span
                  class="text-xs font-semibold px-2 py-0.5 rounded"
                  :class="role.active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-surface-muted text-ink-muted'"
                >
                  {{ role.active ? 'Active' : 'Inactive' }}
                </span>
              </div>
              <h3 class="text-base font-semibold text-ink-secondary mt-1.5">
                {{ role.name }}
              </h3>
            </div>

            <span
              class="text-xs font-semibold px-2 py-0.5 rounded shrink-0"
              :class="role.is_system ? 'bg-[#fff4e6] text-[#dd5b00] dark:bg-[#382412] dark:text-[#ff9243] border border-[#dd5b00]/20' : 'bg-surface-muted text-ink-muted'"
            >
              {{ role.is_system ? 'System' : 'Custom' }}
            </span>
          </div>

          <p class="text-xs text-ink-muted line-clamp-2 mb-4">
            {{ role.description || 'No institutional description provided.' }}
          </p>
        </div>

        <div class="border-t border-hairline pt-3 space-y-2.5">
          <div class="flex items-center justify-between text-xs text-ink-muted">
            <div class="flex items-center gap-1.5">
              <ShieldCheck class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
              <span class="font-semibold text-ink-secondary">{{ role.permissions_count ?? (role.permissions?.length || 0) }}</span> permissions
            </div>

            <button
              type="button"
              class="flex items-center gap-1 text-xs text-primary hover:underline font-medium cursor-pointer"
              @click="openRoleUsersModal(role)"
            >
              <Users class="w-3.5 h-3.5" />
              <span>Assigned Users</span>
            </button>
          </div>

          <div class="flex items-center justify-between gap-2 pt-1 border-t border-hairline">
            <button
              v-if="role.code !== 'super_admin'"
              type="button"
              class="text-xs font-semibold transition-colors cursor-pointer"
              :class="role.active ? 'text-amber-600 hover:text-amber-800' : 'text-emerald-600 hover:text-emerald-800'"
              @click="toggleRoleActive(role)"
            >
              {{ role.active ? 'Deactivate' : 'Activate' }}
            </button>
            <span v-else class="text-xs text-ink-muted italic">Protected core role</span>

            <BaseButton variant="outline" size="sm" @click="openEditModal(role)">
              <Edit2 class="w-3.5 h-3.5 mr-1" />
              Configure
            </BaseButton>
          </div>
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
            <label class="block text-xs font-semibold text-ink-secondary mb-1">
              Role Code *
            </label>
            <BaseInput
              v-model="roleForm.code"
              placeholder="e.g. department_records_officer"
              class="lowercase"
              :disabled="isEditing"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-ink-secondary mb-1">
              Display Name *
            </label>
            <BaseInput v-model="roleForm.name" placeholder="e.g. Department Records Officer" />
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
            :disabled="isEditing && roleForm.code === 'super_admin'"
          />
          <label for="role-active-check" class="text-xs font-medium text-ink-secondary">
            Active Role Status (assignable to staff in organizational scopes)
          </label>
        </div>

        <!-- Permissions Matrix Checklist with Search -->
        <div class="border-t border-hairline pt-4">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
            <h4 class="text-xs font-bold text-ink-secondary   flex items-center gap-1.5">
              <Layers class="w-4 h-4 text-primary" />
              Permissions Matrix ({{ roleForm.permission_ids.length }} assigned)
            </h4>

            <div class="relative w-full sm:w-56">
              <Search class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-ink-muted" />
              <input
                v-model="rolePermSearch"
                type="text"
                placeholder="Search capabilities..."
                class="w-full pl-8 pr-2.5 py-1 text-xs rounded border border-hairline bg-surface text-ink-secondary focus:outline-none focus:border-primary"
              />
            </div>
          </div>

          <div class="space-y-4">
            <div
              v-for="(perms, moduleName) in modalFilteredGroupedPermissions"
              :key="moduleName"
              class="border border-hairline rounded-lg p-3 bg-canvas-soft"
            >
              <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-ink-secondary">
                  {{ moduleName }}
                </span>
                <button
                  type="button"
                  class="text-xs font-semibold text-primary hover:underline cursor-pointer"
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
                    <div class="font-mono text-xs font-medium text-ink-secondary flex items-center gap-1">
                      {{ perm.code }}
                      <span
                        v-if="perm.risk_classification === 'critical'"
                        class="text-xs px-1 rounded bg-rose-100 text-rose-700 font-sans font-bold"
                      >
                        critical
                      </span>
                    </div>
                    <div class="text-xs text-ink-muted line-clamp-1">
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

    <!-- Assigned Users in Scope Modal -->
    <ModalDialog
      :open="showUsersModal"
      :title="`Users Holding Role: ${selectedRoleForUsers?.name || ''}`"
      size="xl"
      @close="showUsersModal = false"
    >
      <div class="space-y-4 max-h-[70vh] overflow-y-auto pr-1">
        <div class="p-3 bg-surface-muted rounded-lg border border-hairline flex items-center justify-between text-xs">
          <div>
            <span class="font-semibold text-ink-secondary">{{ selectedRoleForUsers?.name }}</span>
            <span class="font-mono text-ink-muted ml-1.5">({{ selectedRoleForUsers?.code }})</span>
          </div>
          <div class="text-ink-muted">
            <span class="font-bold text-ink-secondary">{{ roleUsersList.length }}</span> assigned user(s)
          </div>
        </div>

        <div v-if="loadingUsers" class="py-8 text-center text-xs text-ink-muted">
          <RefreshCw class="w-4 h-4 mx-auto animate-spin text-primary mb-1.5" />
          Loading assigned users...
        </div>

        <div v-else-if="roleUsersList.length === 0" class="py-8 text-center text-xs text-ink-muted border border-dashed border-hairline rounded-lg">
          No users are currently assigned to this role.
        </div>

        <div v-else class="border border-hairline rounded-lg overflow-x-auto">
          <table class="w-full text-left text-xs min-w-[500px]">
            <thead class="bg-surface-muted text-ink-muted   text-xs font-semibold border-b border-hairline">
              <tr>
                <th class="px-3 py-2">Staff Member</th>
                <th class="px-3 py-2">Assigned Scope</th>
                <th class="px-3 py-2">Granted By</th>
                <th class="px-3 py-2">Validity</th>
                <th class="px-3 py-2 text-right">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
              <tr v-for="user in roleUsersList" :key="user.user_id" class="hover:bg-surface-hover/50">
                <td class="px-3 py-2.5">
                  <div class="font-medium text-ink-secondary">{{ user.name }}</div>
                  <div class="text-xs text-ink-muted">{{ user.email }}</div>
                </td>
                <td class="px-3 py-2.5">
                  <div class="flex items-center gap-1 font-medium text-ink-secondary">
                    <span
                      class="uppercase text-xs font-bold px-1.5 py-0.5 rounded"
                      :class="{
                        'bg-purple-100 text-purple-700': user.scope_kind === 'global',
                        'bg-blue-100 text-blue-700': user.scope_kind === 'unit',
                        'bg-emerald-100 text-emerald-700': user.scope_kind === 'subtree',
                      }"
                    >
                      {{ user.scope_kind }}
                    </span>
                    <span>{{ user.scope_unit_name || 'All Units (Global)' }}</span>
                  </div>
                </td>
                <td class="px-3 py-2.5 text-ink-muted">
                  {{ user.granted_by_name || 'System' }}
                </td>
                <td class="px-3 py-2.5 whitespace-nowrap">
                  <span v-if="user.expires_at" :class="user.is_expired ? 'text-rose-600 font-semibold' : 'text-ink-muted'">
                    {{ new Date(user.expires_at).toLocaleDateString() }}
                    <span v-if="user.is_expired" class="text-xs text-rose-500 block">Expired</span>
                  </span>
                  <span v-else class="text-ink-muted">Indefinite</span>
                </td>
                <td class="px-3 py-2.5 text-right">
                  <span
                    class="px-2 py-0.5 rounded text-xs font-semibold   inline-flex items-center gap-1"
                    :class="user.status === 'active' && !user.is_expired ? 'bg-[#e8f7ec] text-[#1aae39]' : 'bg-surface-muted text-ink-muted'"
                  >
                    {{ user.status === 'active' && !user.is_expired ? 'Active' : 'Inactive' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <template #footer>
        <div class="flex items-center justify-end">
          <BaseButton variant="outline" size="sm" @click="showUsersModal = false">
            Close
          </BaseButton>
        </div>
      </template>
    </ModalDialog>
  </div>
</template>

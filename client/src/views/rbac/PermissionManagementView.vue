<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { permissionsApi } from '@/api/client'
import type { PermissionItem } from '@/types'
import { useToast } from '@/composables/useToast'
import BaseButton from '@/components/ui/BaseButton.vue'
import LoadingSkeleton from '@/components/ui/LoadingSkeleton.vue'
import ModalDialog from '@/components/ModalDialog.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import {
  Key,
  Shield,
  Search,
  RefreshCw,
  CheckCircle2,
  AlertTriangle,
  Layers,
  FileText,
  Mail,
  GitBranch,
  Archive,
  Building2,
  Users,
  Eye,
  BarChart3,
  Lock,
  Unlock,
  Edit2,
  Info,
} from 'lucide-vue-next'

const toast = useToast()
const loading = ref(true)
const isSubmitting = ref(false)
const permissions = ref<PermissionItem[]>([])
const searchQuery = ref('')
const selectedModule = ref<string>('all')
const selectedRisk = ref<string>('all')
const selectedAction = ref<string>('all')
const selectedStatus = ref<string>('all')

// Edit Permission Modal
const isEditModalOpen = ref(false)
const editingPerm = ref<PermissionItem | null>(null)
const editForm = ref({
  active: true,
  description: '',
})

const moduleIcons: Record<string, any> = {
  documents: FileText,
  correspondence: Mail,
  workflow: GitBranch,
  archive: Archive,
  organization: Building2,
  users: Users,
  roles: Shield,
  permissions: Key,
  audit: Eye,
  reports: BarChart3,
  system: Lock,
  signatures: Edit2,
}

const groupedPermissions = computed(() => {
  const groups: Record<string, PermissionItem[]> = {}
  permissions.value.forEach((perm) => {
    const mod = perm.module || perm.code.split('.')[0] || 'general'
    if (!groups[mod]) {
      groups[mod] = []
    }
    groups[mod].push(perm)
  })
  return groups
})

const availableModules = computed(() => {
  return Object.keys(groupedPermissions.value).sort()
})

const stats = computed(() => {
  const total = permissions.value.length
  const assignable = permissions.value.filter((p) => p.assignability_status === 'assignable').length
  const protectedCount = permissions.value.filter((p) => p.is_protected || p.assignability_status === 'protected').length
  const deactivated = permissions.value.filter((p) => !p.active).length
  return { total, assignable, protectedCount, deactivated }
})

const filteredGroups = computed(() => {
  const q = searchQuery.value.toLowerCase().trim()
  const result: Record<string, PermissionItem[]> = {}

  for (const [mod, perms] of Object.entries(groupedPermissions.value)) {
    if (selectedModule.value !== 'all' && selectedModule.value !== mod) {
      continue
    }

    const matched = perms.filter((p) => {
      // Search
      if (q) {
        const matchesQuery =
          p.code.toLowerCase().includes(q) ||
          p.name.toLowerCase().includes(q) ||
          (p.description && p.description.toLowerCase().includes(q))
        if (!matchesQuery) return false
      }

      // Risk filter
      if (selectedRisk.value !== 'all' && p.risk_classification !== selectedRisk.value) {
        return false
      }

      // Action type filter
      if (selectedAction.value !== 'all' && p.action_type !== selectedAction.value) {
        return false
      }

      // Status filter
      if (selectedStatus.value === 'assignable' && p.assignability_status !== 'assignable') return false
      if (selectedStatus.value === 'protected' && !p.is_protected) return false
      if (selectedStatus.value === 'deactivated' && p.active) return false

      return true
    })

    if (matched.length > 0) {
      result[mod] = matched
    }
  }

  return result
})

const totalFilteredCount = computed(() => {
  return Object.values(filteredGroups.value).reduce((sum, list) => sum + list.length, 0)
})

async function loadPermissions() {
  loading.value = true
  try {
    const res = await permissionsApi.list({ all: true })
    permissions.value = res.data.data
  } catch (err: any) {
    toast.error('Load Error', err.response?.data?.message || 'Failed to load permission catalog.')
  } finally {
    loading.value = false
  }
}

function openEditModal(perm: PermissionItem) {
  editingPerm.value = perm
  editForm.value = {
    active: perm.active,
    description: perm.description || '',
  }
  isEditModalOpen.value = true
}

async function handleSavePermission() {
  if (!editingPerm.value) return
  isSubmitting.value = true

  try {
    const res = await permissionsApi.update(editingPerm.value.id, {
      active: editForm.value.active,
      description: editForm.value.description.trim() || undefined,
    })
    toast.success('Updated', `Capability "${editingPerm.value.code}" updated successfully.`)
    isEditModalOpen.value = false
    await loadPermissions()
  } catch (err: any) {
    toast.error('Update Failed', err.response?.data?.message || 'Failed to update capability.')
  } finally {
    isSubmitting.value = false
  }
}

async function togglePermissionStatus(perm: PermissionItem) {
  if (perm.is_protected) {
    toast.error('Protected Capability', `Protected capability "${perm.code}" cannot be deactivated.`)
    return
  }

  const newStatus = !perm.active
  try {
    await permissionsApi.update(perm.id, { active: newStatus })
    perm.active = newStatus
    toast.success('Status Changed', `Capability "${perm.code}" assignment availability updated.`)
    await loadPermissions()
  } catch (err: any) {
    toast.error('Status Change Failed', err.response?.data?.message || 'Could not update capability status.')
  }
}

onMounted(() => {
  loadPermissions()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-semibold tracking-tight text-ink flex items-center gap-2.5">
          <Key class="w-5 h-5 sm:w-6 sm:h-6 text-primary shrink-0" />
          <span>Institutional Permission Registry</span>
        </h1>
        <p class="text-sm text-ink-muted mt-1">
          Catalog of atomic security capabilities enforcing server-side authorization boundaries, risk classification, and scope applicability
        </p>
      </div>

      <div class="flex items-center gap-2.5 shrink-0">
        <BaseButton variant="outline" size="sm" :loading="loading" @click="loadPermissions">
          <RefreshCw class="w-4 h-4 mr-1.5" />
          Refresh
        </BaseButton>
      </div>
    </div>

    <!-- Stats Row -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
      <div class="bg-surface p-4 rounded-lg border border-line">
        <div class="text-xs font-semibold text-ink-muted">Total capabilities</div>
        <div class="text-2xl font-semibold text-ink-secondary mt-1 tabular-nums">{{ stats.total }}</div>
        <div class="text-xs text-ink-muted mt-0.5">{{ availableModules.length }} functional domains</div>
      </div>

      <div class="bg-surface p-4 rounded-lg border border-line">
        <div class="text-xs  font-bold text-ink-muted">Assignable</div>
        <div class="text-2xl font-bold text-emerald-600 mt-1">{{ stats.assignable }}</div>
        <div class="text-xs text-ink-muted mt-0.5">roles & unit grants</div>
      </div>

      <div class="bg-surface p-4 rounded-lg border border-line">
        <div class="text-xs  font-semibold text-ink-muted">Protected / Admin</div>
        <div class="text-2xl font-bold text-primary mt-1">{{ stats.protectedCount }}</div>
        <div class="text-xs text-ink-muted mt-0.5">requires super_admin</div>
      </div>

      <div class="bg-surface p-4 rounded-lg border border-line">
        <div class="text-xs  font-semibold text-ink-muted">Deactivated</div>
        <div class="text-2xl font-bold text-ink-muted mt-1">{{ stats.deactivated }}</div>
        <div class="text-xs text-ink-muted mt-0.5">blocked from assignment</div>
      </div>
    </div>

    <!-- Filter Toolbar -->
    <div class="bg-surface rounded-lg border border-line p-4 space-y-3">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="relative w-full sm:w-80">
          <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Filter permissions by code or description..."
            class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border border-hairline bg-surface text-ink-secondary focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"
          />
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <!-- Risk Filter -->
          <select
            v-model="selectedRisk"
            class="text-xs rounded-lg border border-hairline bg-surface text-ink-secondary px-2.5 py-1.5 focus:outline-none focus:border-primary"
          >
            <option value="all">All Risk Levels</option>
            <option value="critical">Critical Risk</option>
            <option value="high">High Risk</option>
            <option value="standard">Standard Risk</option>
            <option value="low">Low Risk</option>
          </select>

          <!-- Action Type Filter -->
          <select
            v-model="selectedAction"
            class="text-xs rounded-lg border border-hairline bg-surface text-ink-secondary px-2.5 py-1.5 focus:outline-none focus:border-primary"
          >
            <option value="all">All Action Types</option>
            <option value="read">Read / View</option>
            <option value="create">Create / Register</option>
            <option value="update">Update / Modify</option>
            <option value="delete">Delete / Purge</option>
            <option value="admin">Administration</option>
            <option value="approval">Review / Approve</option>
            <option value="signing">Signing / Seal</option>
            <option value="custody">Custody Transfer</option>
          </select>

          <!-- Status Filter -->
          <select
            v-model="selectedStatus"
            class="text-xs rounded-lg border border-hairline bg-surface text-ink-secondary px-2.5 py-1.5 focus:outline-none focus:border-primary"
          >
            <option value="all">All Availability</option>
            <option value="assignable">Assignable</option>
            <option value="protected">Protected Super Admin</option>
            <option value="deactivated">Deactivated</option>
          </select>
        </div>
      </div>

      <!-- Domain / Module Tabs -->
      <div class="flex items-center gap-1.5 overflow-x-auto pb-1 pt-1 border-t border-hairline">
        <button
          type="button"
          class="px-3 py-1 rounded-md text-xs font-semibold whitespace-nowrap transition cursor-pointer"
          :class="selectedModule === 'all' ? 'bg-primary text-white' : 'bg-surface-muted text-ink-muted hover:bg-surface-hover'"
          @click="selectedModule = 'all'"
        >
          All Domains
        </button>
        <button
          v-for="mod in availableModules"
          :key="mod"
          type="button"
          class="px-2.5 py-1 rounded-md text-xs font-semibold whitespace-nowrap   transition cursor-pointer"
          :class="selectedModule === mod ? 'bg-primary text-white' : 'bg-surface-muted text-ink-muted hover:bg-surface-hover'"
          @click="selectedModule = mod"
        >
          {{ mod }} ({{ groupedPermissions[mod]?.length || 0 }})
        </button>
      </div>
    </div>

    <!-- Loading Skeleton -->
    <LoadingSkeleton v-if="loading" :count="4" class="my-4" />

    <!-- Grouped Permission Catalog -->
    <div v-else class="space-y-6">
      <div
        v-for="(perms, mod) in filteredGroups"
        :key="mod"
        class="bg-surface rounded-lg border border-line p-5 space-y-4"
      >
        <div class="flex items-center justify-between pb-3 border-b border-hairline">
          <div class="flex items-center gap-2.5">
            <div class="p-2 rounded-lg bg-primary-subtle text-primary">
              <component :is="moduleIcons[mod] || Shield" class="w-5 h-5" />
            </div>
            <div>
              <h2 class="text-base font-semibold text-ink-secondary tabular-nums">
                {{ mod }} Domain
              </h2>
              <div class="text-xs text-ink-muted">
                {{ perms.length }} atomic capabilities
              </div>
            </div>
          </div>

          <span class="text-xs font-semibold px-2.5 py-0.5 rounded-md bg-canvas-soft text-ink-secondary">
            Enforced
          </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
          <div
            v-for="perm in perms"
            :key="perm.id"
            class="p-3.5 rounded-lg border border-hairline bg-canvas-soft hover:border-primary/40 transition flex flex-col justify-between"
            :class="{ 'opacity-60 bg-surface-muted/30': !perm.active }"
          >
            <div>
              <div class="flex items-start justify-between gap-2 mb-1.5">
                <span class="font-mono text-xs font-bold text-primary">
                  {{ perm.code }}
                </span>
                <span
                  class="w-2 h-2 rounded-full mt-1 shrink-0"
                  :class="perm.active ? 'bg-emerald-500' : 'bg-ink-muted'"
                  :title="perm.active ? 'Active & Enforced' : 'Deactivated'"
                />
              </div>

              <div class="text-xs font-semibold text-ink-secondary">
                {{ perm.name }}
              </div>

              <p class="text-xs text-ink-muted mt-1 line-clamp-2">
                {{ perm.description || 'System-enforced RBAC capability.' }}
              </p>
            </div>

            <!-- Tags & Badges Row -->
            <div class="pt-3 mt-3 border-t border-hairline space-y-2">
              <div class="flex flex-wrap items-center gap-1.5">
                <!-- Action Type Badge -->
                <span class="text-xs font-bold px-2 py-0.5 rounded bg-surface-muted text-ink-secondary border border-hairline">
                  {{ perm.action_type || 'action' }}
                </span>

                <!-- Risk Badge -->
                <span
                  class="text-xs font-bold px-2 py-0.5 rounded"
                  :class="{
                    'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300': perm.risk_classification === 'critical',
                    'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300': perm.risk_classification === 'high',
                    'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300': perm.risk_classification === 'standard',
                    'bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-300': perm.risk_classification === 'low',
                  }"
                >
                  {{ perm.risk_classification }} risk
                </span>

                <!-- Scope Tag -->
                <span class="text-xs px-2 py-0.5 rounded bg-purple-50 text-purple-700 dark:bg-purple-950 dark:text-purple-300 font-medium">
                  {{ perm.scope_applicability === 'global_only' ? 'Global Only' : 'Unit / Subtree' }}
                </span>
              </div>

              <!-- Assignability / Action Controls -->
              <div class="flex items-center justify-between text-xs pt-1">
                <span
                  v-if="perm.is_protected"
                  class="text-xs text-primary font-semibold flex items-center gap-1"
                >
                  <Lock class="w-3 h-3" /> Protected Core
                </span>
                <span
                  v-else-if="perm.active"
                  class="text-xs text-emerald-600 font-medium flex items-center gap-1"
                >
                  <CheckCircle2 class="w-3 h-3" /> Assignable
                </span>
                <span
                  v-else
                  class="text-xs text-rose-500 font-medium"
                >
                  Deactivated
                </span>

                <div class="flex items-center gap-1.5">
                  <button
                    v-if="!perm.is_protected"
                    type="button"
                    class="text-xs font-semibold hover:underline cursor-pointer"
                    :class="perm.active ? 'text-amber-600' : 'text-emerald-600'"
                    @click="togglePermissionStatus(perm)"
                  >
                    {{ perm.active ? 'Disable' : 'Enable' }}
                  </button>
                  <button
                    type="button"
                    class="text-ink-muted hover:text-primary p-0.5 rounded transition cursor-pointer"
                    title="Configure Capability Details"
                    @click="openEditModal(perm)"
                  >
                    <Edit2 class="w-3 h-3" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="totalFilteredCount === 0" class="py-12 text-center text-ink-muted text-sm border border-dashed border-hairline rounded-xl">
        No permissions matched the selected filters.
      </div>
    </div>

    <!-- Edit Permission Modal -->
    <ModalDialog
      :open="isEditModalOpen"
      :title="`Configure Capability: ${editingPerm?.code || ''}`"
      max-width="md"
      @close="isEditModalOpen = false"
    >
      <div class="space-y-4">
        <div>
          <label class="block text-xs font-semibold text-ink-secondary mb-1">
            Permission Identifier (Stable Technical Key)
          </label>
          <div class="font-mono text-xs p-2 rounded bg-surface-muted text-ink-secondary border border-hairline">
            {{ editingPerm?.code }}
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-ink-secondary mb-1">
            Display Name
          </label>
          <div class="text-xs p-2 rounded bg-surface-muted text-ink-secondary border border-hairline">
            {{ editingPerm?.name }}
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-ink-secondary mb-1">
            Institutional Remit & Scope Description
          </label>
          <textarea
            v-model="editForm.description"
            rows="3"
            class="w-full text-xs rounded border border-hairline bg-surface text-ink-secondary p-2.5 focus:outline-none focus:border-primary"
            placeholder="Document what institutional authority this capability grants..."
          />
        </div>

        <div v-if="editingPerm?.is_protected" class="p-2.5 rounded bg-primary-subtle text-primary text-xs flex items-center gap-2">
          <Info class="w-4 h-4 shrink-0" />
          <span>This is a core system-protected capability. It cannot be deactivated or removed.</span>
        </div>

        <div v-else class="flex items-center gap-2 pt-1">
          <input
            id="perm-active-check"
            v-model="editForm.active"
            type="checkbox"
            class="w-4 h-4 text-primary rounded border-hairline focus:ring-primary"
          />
          <label for="perm-active-check" class="text-xs font-medium text-ink-secondary">
            Assignable Status (Available for role bundling and direct staff assignments)
          </label>
        </div>
      </div>

      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <BaseButton variant="outline" size="sm" @click="isEditModalOpen = false">
            Cancel
          </BaseButton>
          <BaseButton variant="primary" size="sm" :loading="isSubmitting" @click="handleSavePermission">
            Save Changes
          </BaseButton>
        </div>
      </template>
    </ModalDialog>
  </div>
</template>

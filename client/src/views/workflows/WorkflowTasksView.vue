<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { workflowApi, usersApi } from '@/api/client'
import type { WorkflowTask, PaginationMeta, User } from '@/types'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import DataTable, { type TableColumn } from '@/components/data-display/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import ModalDialog from '@/components/ModalDialog.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import { formatDate } from '@/utils/formatters'
import {
 CheckSquare,
 CheckCircle2,
 XCircle,
 RotateCcw,
 RefreshCw,
 Clock,
 AlertCircle,
 UserCheck,
} from 'lucide-vue-next'

const authStore = useAuthStore()
const toast = useToast()

const tasks = ref<WorkflowTask[]>([])
const users = ref<User[]>([])
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)
const scopeFilter = ref<'mine' | 'office'>('mine')
let loadRequestId = 0

const pagination = ref<PaginationMeta>({
 current_page: 1,
 last_page: 1,
 total: 0,
})

// Decision Modal State
const showDecisionModal = ref(false)
const selectedTask = ref<WorkflowTask | null>(null)
const decisionType = ref<'approve' | 'reject' | 'return'>('approve')
const decisionReason = ref('')
const isSubmittingDecision = ref(false)

// Delegation Modal State
const showDelegateModal = ref(false)
const targetUserId = ref<number | ''>('')
const delegationReason = ref('')
const isSubmittingDelegation = ref(false)

const decisionModalTitle = computed(() => {
 if (decisionType.value === 'approve') return 'Approve Workflow Task'
 if (decisionType.value === 'reject') return 'Reject Workflow Task'
 return 'Return Workflow Task for Revision'
})

const decisionTypeLabel = computed(() => {
 if (decisionType.value === 'approve') return 'Approve'
 if (decisionType.value === 'reject') return 'Reject'
 return 'Return for Revision'
})

const columns: TableColumn[] = [
 { key: 'id', label: 'Task Reference' },
 { key: 'instance', label: 'Workflow Subject & Document Reference' },
 { key: 'state', label: 'Workflow Stage' },
 { key: 'assigned_user', label: 'Assigned Officer or Office' },
 { key: 'due_at', label: 'Target Completion Date' },
 { key: 'created_at', label: 'Date Assigned' },
]

async function loadTasks(page = 1) {
 const reqId = ++loadRequestId
 isLoading.value = true
 errorMessage.value = null
 try {
 const res = await workflowApi.tasks({
 scope: scopeFilter.value as any,
 page,
 } as any)
 if (reqId !== loadRequestId) return

 const payload = res.data
 let taskList: WorkflowTask[] = []
 if (Array.isArray(payload.data)) {
 taskList = payload.data
 } else if (payload.data && Array.isArray((payload.data as any).data)) {
 taskList = (payload.data as any).data
 } else if (Array.isArray(payload)) {
 taskList = payload
 }
 tasks.value = taskList

 if (payload.meta) {
 pagination.value = payload.meta
 }
 } catch (err: any) {
 if (reqId !== loadRequestId) return
 errorMessage.value = err.response?.data?.message || 'Failed to load workflow tasks.'
 toast.error('Failed to load workflow tasks.')
 } finally {
 if (reqId === loadRequestId) {
 isLoading.value = false
 }
 }
}

function handleTabChange(newScope: 'mine' | 'office') {
 if (scopeFilter.value === newScope) return
 scopeFilter.value = newScope
 loadTasks(1)
}

function openDecision(task: WorkflowTask, type: 'approve' | 'reject' | 'return') {
 selectedTask.value = task
 decisionType.value = type
 decisionReason.value = ''
 showDecisionModal.value = true
}

async function submitDecision() {
 if (!selectedTask.value) return
 isSubmittingDecision.value = true
 try {
 const res = await workflowApi.decide(selectedTask.value.id, {
 decision: decisionType.value,
 reason: decisionReason.value.trim() || undefined,
 })
 const msg = (res.data as any)?.message || `Workflow task ${decisionType.value} recorded successfully.`
 toast.success(msg)
 showDecisionModal.value = false
 await loadTasks(pagination.value.current_page)
 } catch (err: any) {
 toast.error(err.response?.data?.message || 'Failed to record decision.')
 } finally {
 isSubmittingDecision.value = false
 }
}

function openDelegate(task: WorkflowTask) {
 selectedTask.value = task
 targetUserId.value = ''
 delegationReason.value = ''
 showDelegateModal.value = true
}

async function submitDelegation() {
 if (!selectedTask.value) return
 if (!targetUserId.value) {
  toast.error('Please select an authorized delegate.')
  return
 }
 isSubmittingDelegation.value = true
 try {
  await workflowApi.delegate(selectedTask.value.id, {
   target_user_id: Number(targetUserId.value),
   reason: delegationReason.value.trim() || undefined,
  })
  toast.success('Task successfully delegated to officer.')
  showDelegateModal.value = false
  await loadTasks(pagination.value.current_page)
 } catch (err: any) {
  toast.error(err.response?.data?.message || 'Failed to delegate task.')
 } finally {
  isSubmittingDelegation.value = false
 }
}

async function loadUsers() {
 try {
  const res = await usersApi.list({ per_page: 100 })
  users.value = (res.data as any).data || []
 } catch (err) {
  console.error('Failed to load users for delegation', err)
 }
}

onMounted(() => {
 loadTasks(1)
 loadUsers()
})
</script>

<template>
 <div class="space-y-6">
 <!-- Header -->
 <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-hairline pb-5">
 <div>
 <div class="notion-eyebrow text-primary mb-1">
 WORKSPACE / MY ASSIGNED TASKS
 </div>
 <h1 class="text-2xl font-semibold tracking-tight text-ink-secondary flex items-center gap-2.5">
 <CheckSquare class="w-6 h-6 text-primary" />
 <span>Workflow Tasks & Decision Approvals</span>
 </h1>
 <p class="mt-1 text-xs text-ink-muted">
 Review, approve, or return institutional documents and correspondence directives undergoing routing policy decisions.
 </p>
 </div>

 <div class="flex items-center gap-2.5">
 <div class="flex rounded-md bg-surface-muted border border-hairline p-1 text-xs font-medium">
 <button
 class="px-3 py-1 rounded transition-colors"
 :class="scopeFilter === 'mine' ? 'bg-surface text-primary shadow-xs font-semibold' : 'text-ink-muted hover:text-ink-secondary'"
 @click="handleTabChange('mine')"
 >
 Assigned to Me
 </button>
 <button
 class="px-3 py-1 rounded transition-colors"
 :class="scopeFilter === 'office' ? 'bg-surface text-primary shadow-xs font-semibold' : 'text-ink-muted hover:text-ink-secondary'"
 @click="handleTabChange('office')"
 >
 My Office Tasks
 </button>
 </div>

 <BaseButton variant="outline" size="sm" :loading="isLoading" @click="loadTasks(1)">
 <RefreshCw class="w-4 h-4 mr-1.5" />
 Refresh
 </BaseButton>
 </div>
 </div>

 <!-- Error Banner -->
 <div
 v-if="errorMessage"
 class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-800 dark:text-rose-200 flex items-center justify-between text-xs"
 >
 <div class="flex items-center gap-2">
 <AlertCircle class="w-4 h-4 shrink-0 text-rose-600" />
 <span>{{ errorMessage }}</span>
 </div>
 <BaseButton size="sm" variant="outline" class="border-rose-300" @click="loadTasks(1)">
 Try Again
 </BaseButton>
 </div>

 <!-- Data Table -->
 <DataTable
 :columns="columns"
 :items="tasks"
 :loading="isLoading"
 empty-title="No pending tasks in queue"
 empty-description="You are all caught up! No workflow decisions are currently awaiting action within your scope."
 >
 <template #cell-id="{ value }">
 <span class="font-mono text-xs font-semibold text-ink-secondary">
 TASK #{{ value }}
 </span>
 </template>

 <template #cell-instance="{ item }">
 <div class="font-medium text-ink-secondary">
 {{ item.instance?.document_version?.document?.title || (item.instance ? `Workflow Instance #${item.instance_id}` : 'Official Record') }}
 </div>
 <div class="text-[11px] text-ink-muted font-mono mt-0.5">
 Workflow Instance #{{ item.instance_id }} · Reference: {{ item.instance?.document_version?.document?.reference_number || 'Pending' }} (Revision {{ item.instance?.document_version?.version_number || 1 }})
 </div>
 </template>

 <template #cell-state="{ item }">
 <div class="flex items-center gap-1.5 flex-wrap">
 <span class="px-2.5 py-0.5 rounded text-[11px] font-semibold uppercase tracking-wider bg-primary-subtle text-primary border border-primary/20">
 {{ item.state?.name || `State #${item.state_id}` }}
 </span>
 <span v-if="item.branch_key" class="px-2 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
 {{ item.branch_key.replace('_', ' ') }}
 </span>
 </div>
 </template>

 <template #cell-assigned_user="{ item }">
 <span class="text-xs font-medium text-ink-secondary">
 {{ item.assigned_user?.name || item.assigned_unit?.name || (item.assigned_user_id ? `User #${item.assigned_user_id}` : 'Unassigned') }}
 </span>
 </template>

 <template #cell-due_at="{ value }">
 <span v-if="value" class="text-xs text-amber-600 dark:text-amber-400 flex items-center gap-1 font-medium">
 <Clock class="w-3.5 h-3.5" /> {{ formatDate(value) }}
 </span>
 <span v-else class="text-xs text-ink-muted">None</span>
 </template>

 <template #cell-created_at="{ value }">
 <span class="text-xs text-ink-muted">
 {{ formatDate(value) }}
 </span>
 </template>

 <template #actions="{ item }">
 <div class="flex items-center justify-end gap-1.5" @click.stop>
 <BaseButton
 size="sm"
 variant="primary"
 class="h-7 text-xs px-2.5 bg-emerald-600 hover:bg-emerald-700 text-white"
 @click="openDecision(item, 'approve')"
 >
 <CheckCircle2 class="w-3.5 h-3.5 mr-1" /> Approve
 </BaseButton>

 <BaseButton
 size="sm"
 variant="outline"
 class="h-7 text-xs px-2.5 border-rose-300 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950"
 @click="openDecision(item, 'reject')"
 >
 <XCircle class="w-3.5 h-3.5 mr-1" /> Reject
 </BaseButton>

 <BaseButton
 size="sm"
 variant="ghost"
 class="h-7 text-xs px-2 text-ink-muted hover:text-ink-secondary "
 @click="openDecision(item, 'return')"
 >
 <RotateCcw class="w-3.5 h-3.5 mr-1" /> Return
 </BaseButton>

 <BaseButton
 size="sm"
 variant="outline"
 class="h-7 text-xs px-2 text-primary hover:bg-primary-subtle"
 @click="openDelegate(item)"
 >
 <UserCheck class="w-3.5 h-3.5 mr-1" /> Delegate
 </BaseButton>
 </div>
 </template>
 </DataTable>

 <!-- Pagination -->
 <Pagination
 v-if="pagination.total > 0"
 :current-page="pagination.current_page"
 :last-page="pagination.last_page"
 :total-items="pagination.total"
 :items-per-page="15"
 @page-change="loadTasks"
 />

 <!-- Task Decision Modal -->
 <ModalDialog
 :is-open="showDecisionModal"
 :title="decisionModalTitle"
 @close="showDecisionModal = false"
 >
 <form @submit.prevent="submitDecision" class="space-y-4">
 <p class="text-xs text-ink-muted">
 You are executing decision <strong class="uppercase text-primary">{{ decisionTypeLabel }}</strong> on Task #{{ selectedTask?.id }}.
 </p>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Reason & Administrative Justification
 </label>
 <BaseTextarea
 v-model="decisionReason"
 rows="3"
 placeholder="Provide context or instructions for this institutional decision..."
 />
 </div>

 <div class="flex justify-end gap-2 pt-2">
 <BaseButton variant="ghost" size="sm" type="button" @click="showDecisionModal = false">Cancel</BaseButton>
 <BaseButton
 variant="primary"
 size="sm"
 type="submit"
 :loading="isSubmittingDecision"
 :class="decisionType === 'reject' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-emerald-600 hover:bg-emerald-700'"
 >
 Confirm Decision
 </BaseButton>
 </div>
 </form>
 </ModalDialog>

 <!-- Delegation Modal -->
 <ModalDialog
 :is-open="showDelegateModal"
 title="Delegate Workflow Task"
 @close="showDelegateModal = false"
 >
 <form @submit.prevent="submitDelegation" class="space-y-4">
 <p class="text-xs text-ink-muted">
 Transfer operational execution of Task #{{ selectedTask?.id }} to an authorized colleague with an immutable delegation audit record.
 </p>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Select Delegate Officer *
 </label>
 <BaseSelect
 v-model="targetUserId"
 :options="users.map(u => ({ value: u.id, label: `${u.name} (${u.email})` }))"
 required
 />
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Delegation Directive / Reason
 </label>
 <BaseTextarea
 v-model="delegationReason"
 rows="3"
 placeholder="State the institutional reason for delegating this decision..."
 />
 </div>

 <div class="flex justify-end gap-2 pt-2">
 <BaseButton variant="ghost" size="sm" type="button" @click="showDelegateModal = false">Cancel</BaseButton>
 <BaseButton
 variant="primary"
 size="sm"
 type="submit"
 :loading="isSubmittingDelegation"
 class="bg-primary hover:bg-primary-hover text-white"
 >
 Confirm Delegation
 </BaseButton>
 </div>
 </form>
 </ModalDialog>
 </div>
</template>

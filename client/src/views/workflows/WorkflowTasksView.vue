<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { workflowApi, usersApi } from '@/api/client'
import type { WorkflowTask, PaginationMeta, User } from '@/types'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import PageHeader from '@/components/PageHeader.vue'
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
 UserPlus,
 ExternalLink
} from 'lucide-vue-next'

const router = useRouter()
const authStore = useAuthStore()
const toast = useToast()

const tasks = ref<WorkflowTask[]>([])
const users = ref<User[]>([])
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)
const scopeFilter = ref<'mine' | 'office'>('mine')
const statusFilter = ref<'open' | 'closed'>('open')
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

// Reassignment Modal State
const showReassignModal = ref(false)
const reassignTargetUserId = ref<number | ''>('')
const reassignReason = ref('')
const isSubmittingReassign = ref(false)

function isOverdue(dueAt: string | null | undefined, closedAt: string | null | undefined): boolean {
  if (!dueAt || closedAt) return false
  return new Date(dueAt).getTime() < Date.now()
}

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

const columns = computed<TableColumn[]>(() => {
 const cols: TableColumn[] = [
 { key: 'id', label: 'Task Reference' },
 { key: 'instance', label: 'Workflow Subject & Document Reference' },
 { key: 'state', label: 'Workflow Stage' },
 { key: 'assigned_user', label: 'Assigned Officer or Office' },
 ]
 if (statusFilter.value === 'closed') {
 cols.push({ key: 'decision', label: 'Recorded Decision' })
 cols.push({ key: 'closed_at', label: 'Decided Date' })
 } else {
 cols.push({ key: 'due_at', label: 'Target Completion Date' })
 cols.push({ key: 'created_at', label: 'Date Assigned' })
 }
 return cols
})

async function loadTasks(page = 1) {
 const reqId = ++loadRequestId
 isLoading.value = true
 errorMessage.value = null
 try {
 const res = await workflowApi.tasks({
 scope: scopeFilter.value as any,
 status: statusFilter.value,
 page,
 } as any)
 if (reqId !== loadRequestId) return

 const payload = res.data
 tasks.value = payload.data || []

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

function handleStatusChange(newStatus: 'open' | 'closed') {
 if (statusFilter.value === newStatus) return
 statusFilter.value = newStatus
 loadTasks(1)
}

function openReassign(task: WorkflowTask) {
 selectedTask.value = task
 reassignTargetUserId.value = ''
 reassignReason.value = ''
 showReassignModal.value = true
}

async function submitReassign() {
 if (!selectedTask.value) return
 if (!reassignTargetUserId.value) {
 toast.error('Please select an authorized user for reassignment.')
 return
 }
 isSubmittingReassign.value = true
 try {
 await workflowApi.reassign(selectedTask.value.id, {
 target_user_id: Number(reassignTargetUserId.value),
 reason: reassignReason.value.trim() || undefined,
 })
 toast.success('Workflow task reassigned successfully.')
 showReassignModal.value = false
 await loadTasks(pagination.value.current_page)
 } catch (err: any) {
 toast.error(err.response?.data?.message || 'Failed to reassign task.')
 } finally {
 isSubmittingReassign.value = false
 }
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
  <PageHeader
    title="Workflow Tasks & Decision Approvals"
    subtitle="Review, approve, or return institutional documents and correspondence directives undergoing routing policy decisions."
  >
    <template #actions>
      <div class="flex rounded-md bg-surface-muted border border-hairline p-1 text-xs font-medium" role="group" aria-label="Task status filters">
        <button
          type="button"
          class="px-3 py-1 rounded transition-colors cursor-pointer"
          :class="statusFilter === 'open' ? 'bg-surface text-primary font-semibold' : 'text-ink-muted hover:text-ink-secondary'"
          @click="handleStatusChange('open')"
        >
          Active Tasks
        </button>
        <button
          type="button"
          class="px-3 py-1 rounded transition-colors cursor-pointer"
          :class="statusFilter === 'closed' ? 'bg-surface text-primary font-semibold' : 'text-ink-muted hover:text-ink-secondary'"
          @click="handleStatusChange('closed')"
        >
          Decision History
        </button>
      </div>

      <div class="flex rounded-md bg-surface-muted border border-hairline p-1 text-xs font-medium" role="group" aria-label="Task assignment scope">
        <button
          type="button"
          class="px-3 py-1 rounded transition-colors cursor-pointer"
          :class="scopeFilter === 'mine' ? 'bg-surface text-primary font-semibold' : 'text-ink-muted hover:text-ink-secondary'"
          @click="handleTabChange('mine')"
        >
          Assigned to Me
        </button>
        <button
          type="button"
          class="px-3 py-1 rounded transition-colors cursor-pointer"
          :class="scopeFilter === 'office' ? 'bg-surface text-primary font-semibold' : 'text-ink-muted hover:text-ink-secondary'"
          @click="handleTabChange('office')"
        >
          My Office Tasks
        </button>
      </div>

      <BaseButton variant="outline" size="sm" :loading="isLoading" @click="loadTasks(1)">
        <RefreshCw class="w-4 h-4 mr-1.5" />
        Refresh
      </BaseButton>
    </template>
  </PageHeader>

 <!-- Error Banner -->
 <div
 v-if="errorMessage"
 class="p-4 rounded-lg bg-[#fde8e8] dark:bg-[#371717] border border-[#f87171]/40 text-[#e03e3e] dark:text-[#f87171] flex items-center justify-between text-xs"
 >
 <div class="flex items-center gap-2">
 <AlertCircle class="w-4 h-4 shrink-0" />
 <span>{{ errorMessage }}</span>
 </div>
 <BaseButton size="sm" variant="outline" @click="loadTasks(1)">
 Try Again
 </BaseButton>
 </div>

 <!-- Data Table -->
 <div class="overflow-x-auto">
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
 <div class="space-y-0.5">
 <router-link
 v-if="(item.instance?.document_version || item.instance?.documentVersion)?.document?.id"
 :to="`/documents/${(item.instance?.document_version || item.instance?.documentVersion)?.document?.id}`"
 class="font-medium text-xs text-primary hover:underline flex items-center gap-1"
 @click.stop
 >
 <span>{{ (item.instance?.document_version || item.instance?.documentVersion)?.document?.title || (item.instance ? `Workflow Instance #${item.instance_id}` : 'Official Record') }}</span>
 <ExternalLink class="w-3 h-3 inline shrink-0" />
 </router-link>
 <div v-else class="font-medium text-xs text-ink-secondary">
 {{ (item.instance?.document_version || item.instance?.documentVersion)?.document?.title || (item.instance ? `Workflow Instance #${item.instance_id}` : 'Official Record') }}
 </div>
 <div class="text-xs text-ink-muted font-mono mt-0.5">
 Workflow Instance #{{ item.instance_id }} · Reference: {{ (item.instance?.document_version || item.instance?.documentVersion)?.document?.reference_number || 'Pending' }} (Revision {{ (item.instance?.document_version || item.instance?.documentVersion)?.version_number || 1 }})
 </div>
 </div>
 </template>

 <template #cell-state="{ item }">
 <div class="flex items-center gap-1.5 flex-wrap">
 <span class="px-2.5 py-0.5 rounded text-xs font-semibold   bg-primary-subtle text-primary border border-primary/20">
 {{ item.state?.name || `State #${item.state_id}` }}
 </span>
 <span v-if="item.branch_key" class="px-2 py-0.5 rounded text-xs font-medium   bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
 {{ item.branch_key.replace('_', ' ') }}
 </span>
 </div>
 </template>

 <template #cell-assigned_user="{ item }">
 <span class="text-xs font-medium text-ink-secondary">
 {{ item.assigned_user?.name || item.assigned_unit?.name || (item.assigned_user_id ? `User #${item.assigned_user_id}` : 'Unassigned') }}
 </span>
 </template>

 <template #cell-decision="{ item }">
 <div v-if="item.decision" class="space-y-0.5 text-xs">
 <span
 class="px-2 py-0.5 rounded text-xs font-bold"
 :class="item.decision.decision === 'approve' ? 'bg-[#e8f7ec] dark:bg-[#14301d] text-[#1aae39] dark:text-[#34d399] border border-[#1aae39]/20' : item.decision.decision === 'reject' ? 'bg-[#fde8e8] dark:bg-[#371717] text-[#e03e3e] dark:text-[#f87171] border border-[#f87171]/20' : 'bg-[#fff4e6] dark:bg-[#382412] text-[#dd5b00] dark:text-[#ff9243] border border-[#dd5b00]/20'"
 >
 {{ item.decision.decision }}
 </span>
 <div v-if="item.decision.decided_by" class="text-xs text-ink-muted">
 By {{ item.decision.decided_by.name }}
 </div>
 <div v-if="item.decision.reason" class="text-xs text-ink-secondary italic truncate max-w-xs font-mono">
 "{{ item.decision.reason }}"
 </div>
 </div>
 <span v-else class="text-xs text-ink-muted">&mdash;</span>
 </template>

 <template #cell-closed_at="{ value }">
 <span class="text-xs text-ink-muted">
 {{ formatDate(value) }}
 </span>
 </template>

 <template #cell-due_at="{ item, value }">
 <div v-if="value" class="space-y-1">
 <div
 class="text-xs flex items-center gap-1 font-medium"
 :class="isOverdue(value, item.closed_at) ? 'text-rose-600 dark:text-rose-400 font-semibold' : 'text-amber-600 dark:text-amber-400'"
 >
 <Clock class="w-3.5 h-3.5 shrink-0" />
 <span>{{ formatDate(value) }}</span>
 </div>
 <span
 v-if="isOverdue(value, item.closed_at)"
 class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-bold   bg-rose-100 dark:bg-rose-950/80 text-rose-700 dark:text-rose-300 border border-rose-300 dark:border-rose-800"
 >
 Overdue
 </span>
 </div>
 <span v-else class="text-xs text-ink-muted">None</span>
 </template>

 <template #cell-created_at="{ value }">
 <span class="text-xs text-ink-muted">
 {{ formatDate(value) }}
 </span>
 </template>

 <template #actions="{ item }">
 <div v-if="!item.closed_at" class="flex items-center justify-end gap-1.5" @click.stop>
 <BaseButton
 size="sm"
 variant="success"
 class="h-7 text-xs px-2.5"
 @click="openDecision(item, 'approve')"
 >
 <CheckCircle2 class="w-3.5 h-3.5 mr-1" /> Approve
 </BaseButton>

 <BaseButton
 size="sm"
 variant="danger"
 class="h-7 text-xs px-2.5"
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

 <BaseButton
 v-if="authStore.hasPermission('workflow.configure') || authStore.hasPermission('workflow.reassign') || authStore.hasPermission('system.configure')"
 size="sm"
 variant="outline"
 class="h-7 text-xs px-2 text-ink-secondary hover:bg-surface-hover"
 @click="openReassign(item)"
 >
 <UserPlus class="w-3.5 h-3.5 mr-1" /> Reassign
 </BaseButton>
 </div>
 <div v-else class="text-xs text-ink-muted italic text-right px-2">
 Finalized
 </div>
 </template>
 </DataTable>
 </div>

 <!-- Pagination -->
 <Pagination
 v-if="pagination.total > 0"
 :meta="pagination"
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
 You are executing decision <strong class="text-primary">{{ decisionTypeLabel }}</strong> on Task #{{ selectedTask?.id }}.
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
 :variant="decisionType === 'reject' ? 'danger' : decisionType === 'approve' ? 'success' : 'warning'"
 size="sm"
 type="submit"
 :loading="isSubmittingDecision"
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

 <!-- Reassignment Modal -->
 <ModalDialog
 :is-open="showReassignModal"
 title="Reassign Workflow Task Directive"
 @close="showReassignModal = false"
 >
 <form @submit.prevent="submitReassign" class="space-y-4">
 <p class="text-xs text-ink-muted">
 Reassign Task #{{ selectedTask?.id }} to another institutional officer. All decision authority and audit trails will record the reassignment.
 </p>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Target Officer / Staff Member *
 </label>
 <BaseSelect
 v-model="reassignTargetUserId"
 :options="users.map(u => ({ value: u.id, label: `${u.name} (${u.email})` }))"
 required
 />
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Administrative Reassignment Reason
 </label>
 <BaseTextarea
 v-model="reassignReason"
 rows="3"
 placeholder="Reason for reassigning this workflow review task..."
 />
 </div>

 <div class="flex justify-end gap-2 pt-2">
 <BaseButton variant="ghost" size="sm" type="button" @click="showReassignModal = false">Cancel</BaseButton>
 <BaseButton variant="primary" size="sm" type="submit" :loading="isSubmittingReassign">
 Confirm Reassignment
 </BaseButton>
 </div>
 </form>
 </ModalDialog>
 </div>
</template>

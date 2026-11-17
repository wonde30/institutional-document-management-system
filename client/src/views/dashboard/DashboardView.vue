<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { dashboardApi, workflowApi } from '@/api/client'
import type { DashboardMetrics, WorkflowTask } from '@/types'
import { useAuthStore } from '@/stores/auth'
import BaseButton from '@/components/ui/BaseButton.vue'
import LoadingSkeleton from '@/components/ui/LoadingSkeleton.vue'
import {
  FileText,
  MailPlus,
  Send,
  BookOpen,
  ArrowRight,
  RefreshCw,
  Plus,
  CheckCircle2,
} from 'lucide-vue-next'

const router = useRouter()
const authStore = useAuthStore()

const metrics = ref<DashboardMetrics | null>(null)
const tasks = ref<WorkflowTask[]>([])
const isLoading = ref(true)
const errorMessage = ref('')

async function loadData() {
  isLoading.value = true
  errorMessage.value = ''

  // Both data endpoints are independent and guarded by capabilities.
  const [metricsResult, tasksResult] = await Promise.allSettled([
    dashboardApi.getMetrics(),
    authStore.hasPermission('workflow.view')
      ? workflowApi.tasks({ scope: 'mine' })
      : Promise.resolve(null),
  ])

  if (metricsResult.status === 'fulfilled' && metricsResult.value) {
    metrics.value = metricsResult.value.data.data
  } else {
    metrics.value = null
  }

  if (tasksResult.status === 'fulfilled' && tasksResult.value) {
    tasks.value = tasksResult.value.data.data || []
  } else {
    tasks.value = []
  }

  const failure =
    metricsResult.status === 'rejected' ? metricsResult.reason : tasksResult.status === 'rejected' ? tasksResult.reason : null

  if (failure) {
    const apiErr = failure as { response?: { data?: { message?: string } } }
    errorMessage.value = apiErr.response?.data?.message || 'Failed to load workspace data.'
  }

  isLoading.value = false
}

onMounted(() => {
  loadData()
})

watch(() => authStore.activeUnitId, () => {
  loadData()
})
</script>

<template>
  <div class="space-y-5">
    <!-- Page Header: title + subtitle + actions -->
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
      <div class="max-w-2xl">
        <h1 class="text-2xl font-bold tracking-tight text-ink text-balance">Workspace</h1>
        <p class="text-sm text-ink-muted mt-1">Administrative workload, pending decisions, and correspondence</p>
      </div>
      <div class="flex items-center gap-2 shrink-0">
        <BaseButton variant="secondary" size="sm" :loading="isLoading" @click="loadData" title="Refresh dashboard data" class="hidden lg:flex">
          <RefreshCw class="w-3.5 h-3.5" />
          <span class="ml-1.5">Refresh</span>
        </BaseButton>
        <BaseButton variant="secondary" size="sm" :loading="isLoading" @click="loadData" title="Refresh dashboard data" class="lg:hidden" aria-label="Refresh dashboard">
          <RefreshCw class="w-3.5 h-3.5" />
        </BaseButton>
        <BaseButton
          v-if="authStore.hasPermission('documents.create')"
          variant="primary"
          size="sm"
          class="whitespace-nowrap text-balance"
          @click="router.push('/documents/create')"
        >
          <Plus class="w-3.5 h-3.5 mr-1" />
          Register document
        </BaseButton>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="errorMessage" class="p-3.5 rounded-lg bg-[#fde8e8] dark:bg-[#371717] border border-[#f87171]/40 text-[#e03e3e] dark:text-[#f87171] text-xs flex items-center justify-between">
      <span>{{ errorMessage }}</span>
      <BaseButton size="sm" variant="outline" @click="loadData">Retry</BaseButton>
    </div>

    <!-- Loading Skeleton -->
    <LoadingSkeleton v-if="isLoading" :count="4" class="my-4" />

    <template v-else>
      <!-- Core Operational KPI Grid: Compact, readable, informative -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div class="p-4 rounded-lg bg-surface border border-hairline">
          <div class="flex items-baseline gap-2">
            <div class="text-3xl font-semibold tabular-nums text-ink">{{ tasks.length }}</div>
            <span
              v-if="(metrics?.overview?.overdue_tasks ?? 0) > 0"
              class="bg-[#fde8e8] text-[#e03e3e] dark:bg-[#371717] dark:text-[#f87171] text-[11px] font-medium px-1.5 py-0.5 rounded"
            >
              {{ metrics?.overview?.overdue_tasks }} Overdue
            </span>
          </div>
          <div class="text-[13px] text-ink-muted mt-1">Open tasks</div>
        </div>

        <div class="p-4 rounded-lg bg-surface border border-hairline">
          <div class="text-3xl font-semibold tabular-nums text-ink">{{ metrics?.overview?.documents_requiring_action ?? 0 }}</div>
          <div class="text-[13px] text-ink-muted mt-1">Action required</div>
        </div>

        <div class="p-4 rounded-lg bg-surface border border-hairline">
          <div class="text-3xl font-semibold tabular-nums text-ink">{{ metrics?.overview?.incoming_letters ?? 0 }}</div>
          <div class="text-[13px] text-ink-muted mt-1">Incoming letters</div>
        </div>

        <div class="p-4 rounded-lg bg-surface border border-hairline">
          <div class="text-3xl font-semibold tabular-nums text-ink">{{ metrics?.overview?.outgoing_awaiting_dispatch ?? 0 }}</div>
          <div class="text-[13px] text-ink-muted mt-1">Awaiting dispatch</div>
        </div>
      </div>

      <!-- Office overview card -->
      <div class="rounded-lg bg-surface border border-hairline">
        <div class="px-4 py-2.5 border-b border-hairline">
          <h2 class="text-sm font-semibold text-ink">Office overview</h2>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-hairline">
          <router-link to="/tasks" class="p-4 hover:bg-surface-hover transition group">
            <div class="text-xl font-semibold tabular-nums text-ink group-hover:text-primary">{{ metrics?.overview?.my_office_pending_tasks ?? 0 }}</div>
            <div class="text-[13px] text-ink-muted mt-0.5">Office pending tasks</div>
          </router-link>
          <router-link to="/correspondence" class="p-4 hover:bg-surface-hover transition group">
            <div class="text-xl font-semibold tabular-nums text-ink group-hover:text-primary">{{ metrics?.overview?.unreceived_dispatches ?? 0 }}</div>
            <div class="text-[13px] text-ink-muted mt-0.5">Unreceived dispatches</div>
          </router-link>
          <router-link to="/archive" class="p-4 hover:bg-surface-hover transition group">
            <div class="text-xl font-semibold tabular-nums text-ink group-hover:text-primary">{{ metrics?.overview?.records_awaiting_retention_review ?? 0 }}</div>
            <div class="text-[13px] text-ink-muted mt-0.5">Retention reviews due</div>
          </router-link>
          <router-link to="/archive" class="p-4 hover:bg-surface-hover transition group">
            <div class="text-xl font-semibold tabular-nums text-ink group-hover:text-primary">{{ metrics?.overview?.pending_disposition_requests ?? 0 }}</div>
            <div class="text-[13px] text-ink-muted mt-0.5">Pending disposals</div>
          </router-link>
        </div>
      </div>

      <!-- Two-Column Grid: Work Queues vs Quick Actions & Dispatches -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <!-- Left: Needs your attention & Decision logs -->
        <div class="lg:col-span-7 space-y-4">
          <!-- Tasks Needing Attention -->
          <section class="bg-surface rounded-lg border border-hairline overflow-hidden">
            <div class="px-4 py-3 border-b border-hairline flex items-center justify-between">
              <div>
                <h2 class="text-sm font-semibold text-ink">Pending decisions</h2>
              </div>

              <router-link
                to="/tasks"
                class="text-xs text-primary font-medium hover:underline flex items-center gap-1"
              >
                View all tasks <ArrowRight class="w-3.5 h-3.5" />
              </router-link>
            </div>

            <!-- Task Items -->
            <div class="divide-y divide-hairline-subtle">
              <div
                v-for="(t, idx) in tasks.slice(0, 4)"
                :key="t.id || idx"
                class="p-3.5 hover:bg-surface-hover transition"
              >
                <div class="flex items-center justify-between gap-2 mb-1">
                  <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-[#fff4e6] text-[#dd5b00] dark:bg-[#3b220c] dark:text-[#f59e0b]">
                    {{ t.state?.name || 'Open task' }}
                  </span>
                  <span class="text-[10px] text-ink-faint font-mono">TASK #{{ t.id }}</span>
                </div>

                <h3 class="text-xs font-semibold text-ink">
                  {{ (t.instance?.documentVersion || t.instance?.document_version)?.document?.title || 'Workflow task' }}
                </h3>
                <small class="block text-[11px] text-ink-muted mt-0.5 mb-2 font-mono">
                  {{ (t.instance?.documentVersion || t.instance?.document_version)?.document?.reference_number || 'No linked document reference' }}
                </small>

                <button
                  type="button"
                  class="text-xs font-medium text-primary hover:underline flex items-center gap-1 cursor-pointer"
                  @click="router.push('/tasks')"
                >
                  Process task <ArrowRight class="w-3.5 h-3.5" />
                </button>
              </div>

              <!-- Empty state -->
              <div v-if="tasks.length === 0" class="py-8 text-center">
                <CheckCircle2 class="w-6 h-6 text-ink-faint mx-auto mb-2" aria-hidden="true" />
                <p class="text-sm text-ink-muted">No pending tasks.</p>
              </div>
            </div>
          </section>

          <!-- Documents Requiring Action -->
          <section v-if="(metrics?.documents_requiring_action || []).length > 0" class="bg-surface rounded-lg border border-hairline p-4">
            <div class="flex items-center justify-between mb-2.5 pb-2 border-b border-hairline">
              <h2 class="text-sm font-semibold text-ink">Documents requiring action</h2>
              <router-link to="/documents" class="text-xs text-primary hover:underline">
                View all
              </router-link>
            </div>

            <div class="divide-y divide-hairline-subtle text-xs">
              <div
                v-for="doc in metrics?.documents_requiring_action"
                :key="doc.id"
                class="py-2 flex items-center justify-between gap-3 hover:bg-surface-hover px-1 rounded transition cursor-pointer"
                @click="router.push(`/documents/${doc.id}`)"
              >
                <div class="min-w-0 flex-1">
                  <div class="font-medium text-ink truncate">{{ doc.title }}</div>
                  <div class="text-[10px] text-ink-faint font-mono">{{ doc.reference_number }} · {{ doc.owner_unit?.name || 'Unit' }}</div>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                  Action Pending
                </span>
              </div>
            </div>
          </section>

          <!-- Recently Completed Decisions -->
          <section v-if="(metrics?.recent_decisions || []).length > 0" class="bg-surface rounded-lg border border-hairline p-4">
            <div class="flex items-center justify-between mb-2.5 pb-2 border-b border-hairline">
              <h2 class="text-sm font-semibold text-ink">Recently completed</h2>
              <router-link to="/tasks" class="text-xs text-primary hover:underline">
                Decision history
              </router-link>
            </div>

            <div class="divide-y divide-hairline-subtle text-xs">
              <div
                v-for="dec in metrics?.recent_decisions"
                :key="dec.id"
                class="py-2 flex items-start justify-between gap-3 px-1"
              >
                <div class="min-w-0 flex-1">
                  <div class="font-medium text-ink truncate">
                    {{ (dec.task?.instance?.document_version || (dec.task?.instance as any)?.documentVersion)?.document?.title || `Task #${dec.task?.id}` }}
                  </div>
                  <div class="text-[10px] text-ink-muted">
                    Decided by <span class="font-semibold text-ink-secondary">{{ dec.decided_by?.name || 'Officer' }}</span> · {{ new Date(dec.decided_at).toLocaleDateString() }}
                  </div>
                  <div v-if="dec.reason" class="text-[10px] text-ink-faint italic truncate mt-0.5 font-mono">
                    "{{ dec.reason }}"
                  </div>
                </div>
                <span
                  class="px-2 py-0.5 rounded text-[11px] font-semibold shrink-0"
                  :class="dec.decision === 'approve' ? 'bg-[#e8f7ec] text-[#1aae39] dark:bg-[#14301d] dark:text-[#34d399]' : dec.decision === 'reject' ? 'bg-[#fde8e8] text-[#e03e3e] dark:bg-[#371717] dark:text-[#f87171]' : 'bg-[#fff4e6] text-[#dd5b00] dark:bg-[#382412] dark:text-[#ff9243]'"
                >
                  {{ dec.decision }}
                </span>
              </div>
            </div>
          </section>

          <!-- Recent Document Register Summary -->
          <section class="bg-surface rounded-lg border border-hairline p-4">
            <div class="flex items-center justify-between mb-2.5 pb-2 border-b border-hairline">
              <h2 class="text-sm font-semibold text-ink">
                Recent document activity
              </h2>
              <router-link to="/documents" class="text-xs text-primary hover:underline">
                View repository
              </router-link>
            </div>

            <div class="divide-y divide-hairline-subtle text-xs">
              <div
                v-for="doc in (metrics?.recent_documents || []).slice(0, 3)"
                :key="doc.id"
                class="py-2 flex items-center justify-between gap-3 hover:bg-surface-hover px-1 rounded transition cursor-pointer"
                @click="router.push(`/documents/${doc.id}`)"
              >
                <div class="min-w-0 flex-1">
                  <div class="font-medium text-ink truncate">{{ doc.title }}</div>
                  <div class="text-[10px] text-ink-faint font-mono">{{ doc.reference_number }}</div>
                </div>
                <span class="px-2 py-0.5 rounded-md text-[11px] font-medium bg-[#e8f7ec] text-[#1aae39] dark:bg-[#14301d] dark:text-[#34d399]">
                  {{ doc.lifecycle }}
                </span>
              </div>
            </div>
          </section>
        </div>

        <!-- Right: Quick actions & Dispatches -->
        <div class="lg:col-span-5 space-y-4">
          <!-- Quick actions: capability guarded -->
          <section class="bg-surface rounded-lg border border-hairline p-4">
            <h2 class="text-sm font-semibold text-ink mb-2.5 pb-2 border-b border-hairline">Quick actions</h2>
            <div class="divide-y divide-hairline-subtle">
              <button
                v-if="authStore.hasPermission('correspondence.register')"
                type="button"
                class="w-full text-xs font-medium px-2 py-2.5 text-ink-secondary hover:text-ink hover:bg-canvas-soft rounded transition flex items-center gap-2.5 text-left cursor-pointer"
                @click="router.push('/letters/incoming')"
              >
                <MailPlus class="w-4 h-4 text-primary shrink-0" />
                <span>Log an incoming letter</span>
              </button>

              <button
                v-if="authStore.hasPermission('correspondence.register')"
                type="button"
                class="w-full text-xs font-medium px-2 py-2.5 text-ink-secondary hover:text-ink hover:bg-canvas-soft rounded transition flex items-center gap-2.5 text-left cursor-pointer"
                @click="router.push('/letters/outgoing')"
              >
                <Send class="w-4 h-4 text-primary shrink-0" />
                <span>Prepare an outgoing letter</span>
              </button>

              <button
                v-if="authStore.hasPermission('research.create')"
                type="button"
                class="w-full text-xs font-medium px-2 py-2.5 text-ink-secondary hover:text-ink hover:bg-canvas-soft rounded transition flex items-center gap-2.5 text-left cursor-pointer"
                @click="router.push('/research')"
              >
                <BookOpen class="w-4 h-4 text-primary shrink-0" />
                <span>Post finalized research record</span>
              </button>
            </div>
          </section>

          <!-- Outgoing Letters Awaiting Dispatch (if any) -->
          <section v-if="(metrics?.outgoing_awaiting_dispatch || []).length > 0" class="bg-surface rounded-lg border border-hairline p-4">
            <div class="flex items-center justify-between mb-2.5 pb-2 border-b border-hairline">
              <h2 class="text-sm font-semibold text-ink flex items-center gap-1.5">
                <Send class="w-3.5 h-3.5 text-primary" />
                <span>Awaiting dispatch ({{ metrics?.outgoing_awaiting_dispatch?.length }})</span>
              </h2>
              <router-link to="/correspondence" class="text-xs text-primary hover:underline">
                Dispatch
              </router-link>
            </div>

            <div class="divide-y divide-hairline-subtle text-xs">
              <router-link
                v-for="corr in metrics?.outgoing_awaiting_dispatch"
                :key="corr.id"
                to="/correspondence"
                class="py-2 flex items-center justify-between gap-3 hover:bg-surface-hover px-1 rounded transition"
              >
                <div class="min-w-0 flex-1">
                  <div class="font-medium text-ink truncate">{{ corr.document?.title || corr.external_reference || 'Outgoing Letter' }}</div>
                  <div class="text-[11px] text-ink-faint font-mono">{{ corr.document?.reference_number || corr.external_reference }}</div>
                </div>
                <span class="px-2 py-0.5 rounded text-[11px] font-medium bg-[#e8f3fc] text-[#0075de] dark:bg-[#172c42] dark:text-[#62aef0] shrink-0">
                  Pending dispatch
                </span>
              </router-link>
            </div>
          </section>

          <!-- Recent correspondence -->
          <section class="bg-surface rounded-lg border border-hairline p-4">
            <div class="flex items-center justify-between mb-2.5 pb-2 border-b border-hairline">
              <h2 class="text-sm font-semibold text-ink">
                Recent correspondence
              </h2>
              <router-link to="/correspondence" class="text-xs text-primary hover:underline">
                View registry
              </router-link>
            </div>

            <div v-if="(metrics?.recent_correspondences || []).length > 0" class="divide-y divide-hairline-subtle text-xs">
              <router-link
                v-for="corr in (metrics?.recent_correspondences || []).slice(0, 4)"
                :key="corr.id"
                to="/correspondence"
                class="py-2 flex items-center justify-between gap-3 hover:bg-surface-hover px-1 rounded transition"
              >
                <div class="min-w-0 flex-1">
                  <div class="font-medium text-ink truncate">{{ corr.document?.title || corr.external_reference || 'Correspondence record' }}</div>
                  <div class="text-[11px] text-ink-faint font-mono">{{ corr.document?.reference_number || corr.external_reference || '' }}</div>
                </div>
                <span class="text-xs text-ink-faint shrink-0 font-mono">{{ corr.correspondence_date }}</span>
              </router-link>
            </div>

            <div v-else class="py-5 text-center text-xs text-ink-faint">
              No correspondence recorded in current scope.
            </div>
          </section>
        </div>
      </div>
    </template>
  </div>
</template>

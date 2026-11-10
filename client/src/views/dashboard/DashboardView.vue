<script setup lang="ts">
import { ref, onMounted } from 'vue'
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
 ShieldCheck,
 AlertCircle,
 Clock,
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
 try {
 const [metricsRes, tasksRes] = await Promise.all([
 dashboardApi.getMetrics(),
 workflowApi.tasks({ scope: 'mine' }),
 ])

 metrics.value = metricsRes.data.data
 const tasksData = tasksRes.data.data
 tasks.value = Array.isArray(tasksData) ? tasksData : (tasksData as any).data || []
 } catch (err: any) {
 errorMessage.value = err.response?.data?.message || 'Failed to load workspace data.'
 } finally {
 isLoading.value = false
 }
}

onMounted(() => {
 loadData()
})
</script>

<template>
 <div class="space-y-6">
 <!-- Eyebrow & Page Header (Notion Tight Typography) -->
 <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
 <div>
 <div class="notion-eyebrow text-primary mb-1.5">
 {{ authStore.primaryUnit?.unit_name || 'INFORMATION TECHNOLOGY DIRECTORATE' }} / WORKSPACE
 </div>
 <h1 class="notion-heading-2 text-ink">
 Your work, in one place
 </h1>
 <p class="notion-body-sm text-ink-muted mt-1">
 Keep documents moving. Know what needs your attention.
 </p>
 </div>

 <div class="flex items-center gap-2.5">
 <BaseButton variant="outline" size="sm" :loading="isLoading" @click="loadData">
 <RefreshCw class="w-4 h-4 mr-1.5" />
 Refresh
 </BaseButton>

 <BaseButton
 variant="primary"
 size="sm"
 class="bg-primary hover:bg-primary-hover text-white shadow-xs"
 @click="router.push('/documents/create')"
 >
 <Plus class="w-4 h-4 mr-1.5" />
 Register Document
 </BaseButton>
 </div>
 </div>

 <!-- Error State -->
 <div v-if="errorMessage" class="p-4 rounded-lg bg-[#fde8e8] dark:bg-[#371717] border border-[#f87171]/40 text-[#e03e3e] dark:text-[#f87171] text-xs flex items-center justify-between">
 <span>{{ errorMessage }}</span>
 <BaseButton size="sm" variant="outline" @click="loadData">Retry</BaseButton>
 </div>

 <!-- Loading Skeleton -->
 <LoadingSkeleton v-if="isLoading" :count="4" class="my-4" />

 <template v-else>
 <!-- 3 Primary Calm Metric Cards (feature-card per DESIGN.md: white surface, hairline border, 12px radius) -->
 <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 sm:gap-4">
 <!-- My open tasks -->
 <div class="p-5 rounded-xl bg-surface border border-hairline shadow-xs hover:border-hairline transition-colors">
 <small class="notion-caption text-ink-muted block">My open tasks</small>
 <strong class="block text-3xl font-bold tracking-tight text-ink mt-1 font-mono">
 {{ tasks.length || metrics?.overview?.pending_assignments || 0 }}
 </strong>
 <span class="text-[11px] text-ink-faint mt-0.5 block">Across your assigned office</span>
 </div>

 <!-- Need acknowledgment -->
 <div class="p-5 rounded-xl bg-surface border border-hairline shadow-xs hover:border-hairline transition-colors">
 <small class="notion-caption text-ink-muted block">Need acknowledgment</small>
 <strong class="block text-3xl font-bold tracking-tight text-ink mt-1 font-mono">
 {{ metrics?.overview?.incoming_letters || 0 }}
 </strong>
 <span class="text-[11px] text-ink-faint mt-0.5 block">Office receipt to confirm</span>
 </div>

 <!-- Due today / urgent -->
 <div class="p-5 rounded-xl bg-surface border border-hairline shadow-xs hover:border-hairline transition-colors">
 <small class="notion-caption text-ink-muted block">Due today</small>
 <strong class="block text-3xl font-bold tracking-tight text-[#dd5b00] dark:text-[#f59e0b] mt-1 font-mono">
 {{ metrics?.overview?.pending_assignments || 0 }}
 </strong>
 <span class="text-[11px] text-ink-faint mt-0.5 block">Review the requested action</span>
 </div>
 </div>

 <!-- Two-Column Grid (Needs Attention vs Quick Actions & Reminders) -->
 <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
 <!-- Left: Needs your attention -->
 <div class="lg:col-span-7 space-y-4">
 <section class="bg-surface rounded-xl border border-hairline overflow-hidden shadow-xs">
 <div class="px-5 py-4 border-b border-hairline flex items-center justify-between">
 <div>
 <h2 class="text-sm font-semibold text-ink">Needs your attention</h2>
 <p class="text-xs text-ink-muted">Your next permitted actions</p>
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
 class="p-4 sm:px-5 hover:bg-surface-hover transition"
 >
 <div class="flex items-center justify-between gap-2 mb-1.5">
 <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-[#fff4e6] text-[#dd5b00] dark:bg-[#3b220c] dark:text-[#f59e0b]">
 {{ t.state?.name || 'Needs acknowledgment' }}
 </span>
 <span class="text-[11px] text-ink-faint font-mono">TASK #{{ t.id }}</span>
 </div>

 <h3 class="text-xs font-semibold text-ink">
 {{ (t as any).instance?.document?.title || `Review requested on Instance #${t.instance_id}` }}
 </h3>
 <small class="block text-[11px] text-ink-muted mt-0.5 mb-2">
 {{ (t as any).instance?.document?.reference_number || 'WU/CORR/2026/000001' }} · Confirm office receipt
 </small>

 <button
 type="button"
 class="text-xs font-medium text-primary hover:underline flex items-center gap-1 cursor-pointer"
 @click="router.push('/tasks')"
 >
 Open task <ArrowRight class="w-3.5 h-3.5" />
 </button>
 </div>

 <!-- Clean Institutional Empty State when tasks queue is clear -->
 <div v-if="tasks.length === 0" class="p-8 text-center space-y-2">
 <div class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-[#e8f7ec] dark:bg-[#14301d] text-[#1aae39] dark:text-[#34d399]">
 <CheckCircle2 class="w-5 h-5" />
 </div>
 <div class="text-xs font-semibold text-ink">
 All clear — no pending tasks
 </div>
 <p class="text-[11px] text-ink-muted max-w-sm mx-auto">
 There are currently no document approvals or correspondence assignments requiring your immediate attention.
 </p>
 </div>
 </div>
 </section>

 <!-- Recent Document Register Summary -->
 <section class="bg-surface rounded-xl border border-hairline p-5 shadow-xs">
 <div class="flex items-center justify-between mb-3 pb-2 border-b border-hairline">
 <h2 class="text-xs font-semibold text-ink uppercase tracking-wider">
 Recent Document Activity
 </h2>
 <router-link to="/documents" class="text-xs text-primary hover:underline">
 View repository
 </router-link>
 </div>

 <div class="divide-y divide-hairline-subtle text-xs">
 <div
 v-for="doc in (metrics?.recent_documents || []).slice(0, 3)"
 :key="doc.id"
 class="py-2.5 flex items-center justify-between gap-3 hover:bg-surface-hover px-1 rounded transition cursor-pointer"
 @click="router.push(`/documents/${doc.id}`)"
 >
 <div class="min-w-0 flex-1">
 <div class="font-medium text-ink truncate">{{ doc.title }}</div>
 <div class="text-[10px] text-ink-faint font-mono">{{ doc.reference_number }}</div>
 </div>
 <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-[#e8f7ec] text-[#1aae39] dark:bg-[#14301d] dark:text-[#34d399]">
 {{ doc.lifecycle }}
 </span>
 </div>
 </div>
 </section>
 </div>

 <!-- Right: Quick actions & Workspace reminders -->
 <div class="lg:col-span-5 space-y-4">
 <!-- Quick actions (utility buttons per DESIGN.md: 8px radius, white surface, hairline border) -->
 <section class="bg-surface rounded-xl border border-hairline p-5 shadow-xs">
 <h2 class="text-sm font-semibold text-ink mb-3">Quick Actions</h2>
 <div class="space-y-2">
 <button
 type="button"
 class="w-full text-xs font-medium px-3.5 py-2.5 rounded-lg border border-hairline bg-surface text-ink-secondary hover:bg-canvas-soft flex items-center gap-2.5 transition text-left cursor-pointer shadow-xs"
 @click="router.push('/correspondence')"
 >
 <MailPlus class="w-4 h-4 text-primary" />
 <span>Register Incoming Letter</span>
 </button>

 <button
 type="button"
 class="w-full text-xs font-medium px-3.5 py-2.5 rounded-lg border border-hairline bg-surface text-ink-secondary hover:bg-canvas-soft flex items-center gap-2.5 transition text-left cursor-pointer shadow-xs"
 @click="router.push('/correspondence')"
 >
 <Send class="w-4 h-4 text-primary" />
 <span>Prepare Outgoing Letter</span>
 </button>

 <button
 type="button"
 class="w-full text-xs font-medium px-3.5 py-2.5 rounded-lg border border-hairline bg-surface text-ink-secondary hover:bg-canvas-soft flex items-center gap-2.5 transition text-left cursor-pointer shadow-xs"
 @click="router.push('/research')"
 >
 <BookOpen class="w-4 h-4 text-primary" />
 <span>Post Finalized Research</span>
 </button>
 </div>
 </section>

 <!-- Workspace reminders (quiet paper card) -->
 <section class="bg-surface rounded-xl border border-hairline p-5 shadow-xs space-y-3">
 <div>
 <h2 class="text-sm font-semibold text-ink">Workspace Reminders</h2>
 <h3 class="text-xs font-medium text-ink-secondary mt-2">Physical Originals Preservation</h3>
 <p class="text-xs text-ink-muted leading-relaxed mt-1">
 Human Resources dossier scans and physical retention are tracked separately. A digital scan never authorizes destruction of institutional originals.
 </p>
 </div>

 <div class="border-t border-hairline pt-3 space-y-2 text-xs">
 <div class="flex items-center justify-between gap-2">
 <span class="text-ink-muted">Office Issuing Procedure</span>
 <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-[#fff4e6] text-[#dd5b00] dark:bg-[#3b220c] dark:text-[#f59e0b]">
 Confirm Before Issue
 </span>
 </div>

 <div class="flex items-center justify-between gap-2">
 <span class="text-ink-muted">Research Repository</span>
 <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-[#e8f7ec] text-[#1aae39] dark:bg-[#14301d] dark:text-[#34d399]">
 Finalized Work Only
 </span>
 </div>
 </div>
 </section>
 </div>
 </div>
 </template>
 </div>
</template>

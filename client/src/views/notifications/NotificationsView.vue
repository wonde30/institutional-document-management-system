<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useNotificationStore } from '@/stores/notifications'
import BaseButton from '@/components/ui/BaseButton.vue'
import {
 Bell,
 CheckCheck,
 Clock,
 Mail,
 FileText,
 AlertCircle,
 ShieldCheck,
 Archive,
 ArrowRight,
} from 'lucide-vue-next'

const router = useRouter()
const notifStore = useNotificationStore()
const filterMode = ref<'all' | 'unread'>('all')

function formatTypeCode(code?: string): string {
 if (!code) return 'Institutional Security Notice'
 const map: Record<string, string> = {
 'auth.login': 'User Authentication Sign In',
 'auth.logout': 'User Authentication Sign Out',
 'document.created': 'Official Document Registered',
 'document.updated': 'Document Metadata Updated',
 'document.assigned': 'Operational Directive Assigned',
 'document.version_uploaded': 'New Document Revision Uploaded',
 'correspondence.incoming_created': 'Incoming Letter Registered',
 'correspondence.outgoing_dispatched': 'Outgoing Letter Dispatched',
 'correspondence.received': 'Correspondence Received in Office',
 'correspondence.forwarded': 'Correspondence Forwarded to Unit',
 'archive.retention_review_due': 'Archive Retention Review Due',
 'workflow.task_assigned': 'Workflow Decision Assigned',
 'workflow.task_completed': 'Workflow Decision Completed',
 'workflow.approved': 'Workflow Stage Approved',
 'workflow.rejected': 'Workflow Stage Rejected',
 'workflow.returned': 'Workflow Returned for Revision',
 'workflow.task_delegated': 'Workflow Task Delegated to You',
 'workflow.task_reassigned': 'Workflow Task Reassigned to You',
 }
 if (map[code]) return map[code]
 return code
 .split('.')
 .map((seg) => seg.replace(/_/g, ' '))
 .map((seg) => seg.charAt(0).toUpperCase() + seg.slice(1))
 .join(' · ')
}

onMounted(() => {
 notifStore.fetchNotifications()
})

const displayedNotifications = computed(() => {
 if (filterMode.value === 'unread') {
 return notifStore.notifications.filter((n) => !n.read_at)
 }
 return notifStore.notifications
})

async function markAsRead(id: number) {
 await notifStore.markAsRead(id)
}

async function markAllRead() {
 await notifStore.markAllAsRead()
}

async function handleNavigate(notif: any) {
 if (!notif.read_at) {
 await markAsRead(notif.id)
 }
 if (notif.target_url) {
 router.push(notif.target_url)
 } else if (notif.event?.document?.id && !notif.event.document.is_restricted) {
 router.push(`/documents/${notif.event.document.id}`)
 } else if (notif.event?.type_code?.startsWith('correspondence.')) {
 router.push('/correspondence')
 } else if (notif.event?.type_code?.startsWith('workflow.')) {
 router.push('/tasks')
 } else if (notif.event?.type_code?.startsWith('archive.')) {
 router.push('/archive')
 }
}
</script>

<template>
 <div class="space-y-6 max-w-4xl">
 <!-- Header -->
 <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-hairline pb-4">
 <div>
 <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-ink flex items-center gap-2.5">
 <Bell class="w-5 h-5 sm:w-6 sm:h-6 text-primary shrink-0" />
 <span>Notifications & Institutional Activity</span>
 </h1>
 <p class="text-xs text-ink-muted mt-0.5">
 Authorized event notifications, document dispatches, and pending workflow assignments.
 </p>
 </div>

 <div class="flex items-center gap-2">
 <BaseButton
 v-if="notifStore.unreadCount > 0"
 variant="outline"
 size="sm"
 @click="markAllRead"
 >
 <CheckCheck class="w-4 h-4 mr-1.5 text-primary" />
 <span>Mark All as Read</span>
 </BaseButton>
 </div>
 </div>

 <!-- Filter Tabs -->
 <div class="flex items-center gap-4 border-b border-hairline">
 <button
 type="button"
 class="pb-2.5 text-xs font-semibold border-b-2 transition-colors cursor-pointer"
 :class="[
 filterMode === 'all'
 ? 'border-primary text-primary'
 : 'border-transparent text-ink-muted hover:text-ink-secondary'
 ]"
 @click="filterMode = 'all'"
 >
 All Events ({{ notifStore.notifications.length }})
 </button>

 <button
 type="button"
 class="pb-2.5 text-xs font-semibold border-b-2 transition-colors cursor-pointer"
 :class="[
 filterMode === 'unread'
 ? 'border-primary text-primary'
 : 'border-transparent text-ink-muted hover:text-ink-secondary'
 ]"
 @click="filterMode = 'unread'"
 >
 Unread ({{ notifStore.unreadCount }})
 </button>
 </div>

 <!-- Notifications List -->
 <div class="bg-surface border border-hairline rounded-xl overflow-hidden shadow-xs divide-y divide-hairline">
 <div
 v-for="notif in displayedNotifications"
 :key="notif.id"
 class="p-4 flex items-start justify-between gap-4 transition hover:bg-surface-hover cursor-pointer"
 :class="{ 'bg-[#ebf5fe]/30 dark:bg-[#1c385c]/20': !notif.read_at }"
 @click="handleNavigate(notif)"
 >
 <div class="flex items-start gap-3">
 <div
 class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 mt-0.5"
 :class="[
 !notif.read_at
 ? 'bg-primary-subtle text-primary'
 : 'bg-canvas-soft text-ink-muted'
 ]"
 >
 <FileText v-if="notif.event?.type_code?.startsWith('document.')" class="w-4 h-4" />
 <Mail v-else-if="notif.event?.type_code?.startsWith('correspondence.')" class="w-4 h-4" />
 <CheckCheck v-else-if="notif.event?.type_code?.startsWith('workflow.')" class="w-4 h-4" />
 <Archive v-else-if="notif.event?.type_code?.startsWith('archive.')" class="w-4 h-4" />
 <ShieldCheck v-else-if="notif.event?.type_code?.startsWith('auth.')" class="w-4 h-4" />
 <Bell v-else class="w-4 h-4" />
 </div>

 <div class="space-y-1">
 <div class="flex items-center gap-2">
 <span class="text-xs font-bold text-ink-secondary">
 {{ formatTypeCode(notif.event?.type_code) }}
 </span>
 <span
 v-if="!notif.read_at"
 class="px-1.5 py-0.2 rounded text-[10px] font-semibold bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300"
 >
 Unread
 </span>
 </div>

 <div v-if="notif.event?.document" class="text-xs font-medium text-ink">
 <span>{{ notif.event.document.title }}</span>
 <span class="text-[11px] text-ink-muted font-mono ml-1.5">({{ notif.event.document.reference_number }})</span>
 </div>
 <p v-else class="text-xs text-ink-muted">
 Event generated by institutional workflow engine. Access is subject to scoped RBAC permissions.
 </p>

 <div class="text-[11px] text-ink-muted flex items-center gap-2 pt-0.5">
 <span v-if="notif.event?.actor" class="text-ink-secondary font-medium">By {{ notif.event.actor.name }} ·</span>
 <span class="flex items-center gap-1"><Clock class="w-3 h-3" /> {{ notif.created_at ? new Date(notif.created_at).toLocaleString() : 'Just now' }}</span>
 </div>
 </div>
 </div>

 <div class="flex items-center gap-2 shrink-0" @click.stop>
 <button
 type="button"
 class="px-2.5 py-1 rounded text-[11px] font-medium bg-primary text-white hover:bg-primary-hover flex items-center gap-1 transition cursor-pointer"
 @click="handleNavigate(notif)"
 >
 <span>Open</span>
 <ArrowRight class="w-3 h-3" />
 </button>
 <button
 v-if="!notif.read_at"
 type="button"
 class="px-2.5 py-1 rounded text-[11px] font-medium bg-primary-subtle text-primary hover:bg-primary/10 transition cursor-pointer"
 @click="markAsRead(notif.id)"
 >
 Mark Read
 </button>
 </div>
 </div>

 <div
 v-if="displayedNotifications.length === 0"
 class="p-12 text-center text-xs text-ink-muted"
 >
 <Bell class="w-8 h-8 mx-auto text-ink-muted opacity-60 mb-2" />
 <div class="font-semibold text-ink-secondary">No notifications to display</div>
 <div class="mt-0.5">You are completely up to date with institutional assignments and alerts.</div>
 </div>
 </div>
 </div>
</template>

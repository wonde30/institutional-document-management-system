<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { auditApi } from '@/api/client'
import type { AuditEvent, PaginationMeta } from '@/types'
import DataTable, { type TableColumn } from '@/components/data-display/DataTable.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ModalDialog from '@/components/ModalDialog.vue'
import { useToast } from '@/composables/useToast'
import {
 ShieldCheck,
 Search,
 Filter,
 Eye,
 FileJson,
 Globe,
 Terminal,
 RefreshCw,
 User as UserIcon,
} from 'lucide-vue-next'

const toast = useToast()
const loading = ref(true)
const events = ref<AuditEvent[]>([])
const pagination = ref<PaginationMeta>({
 current_page: 1,
 last_page: 1,
 per_page: 50,
 total: 0,
})

const filterAction = ref('')
const filterTargetType = ref('')
const selectedEvent = ref<AuditEvent | null>(null)
const showDetailModal = ref(false)

const tableColumns: TableColumn[] = [
 { key: 'occurred_at', label: 'Timestamp (UTC)', sortable: true },
 { key: 'actor', label: 'Responsible Actor' },
 { key: 'action', label: 'Security Action' },
 { key: 'target', label: 'Target Entity' },
 { key: 'ip_address', label: 'Origin IP' },
 { key: 'actions', label: '', align: 'right' },
]

async function loadEvents(page = 1) {
 loading.value = true
 try {
 const res = await auditApi.list({
 page,
 per_page: pagination.value.per_page,
 action: filterAction.value || undefined,
 target_type: filterTargetType.value || undefined,
 })
 events.value = res.data.data || []
 const meta = (res.data as any).meta
 if (meta) {
 pagination.value = {
 current_page: meta.current_page,
 last_page: meta.last_page,
 per_page: pagination.value.per_page,
 total: meta.total,
 }
 }
 } catch (err: any) {
 toast.error('Audit Error', err.response?.data?.message || 'Failed to retrieve audit ledger.')
 } finally {
 loading.value = false
 }
}

function openDetailModal(event: AuditEvent) {
 selectedEvent.value = event
 showDetailModal.value = true
}

function formatJson(val: unknown): string {
 if (!val) return 'None'
 try {
 return JSON.stringify(val, null, 2)
 } catch {
 return String(val)
 }
}

onMounted(() => {
 loadEvents(1)
})
</script>

<template>
 <div class="space-y-6">
 <!-- Page Header -->
 <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-hairline pb-4">
 <div>
 <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-ink flex items-center gap-2.5">
 <ShieldCheck class="w-5 h-5 sm:w-6 sm:h-6 text-primary shrink-0" />
 <span>Security Audit Ledger & Immutable Trails</span>
 </h1>
 <p class="text-xs text-ink-muted mt-0.5">
 Cryptographically referenced, immutable administrative event log tracking system actions across Wollo University DMS.
 </p>
 </div>

 <div class="flex items-center gap-2.5">
 <BaseButton variant="outline" size="sm" :loading="loading" @click="loadEvents(1)">
 <RefreshCw class="w-4 h-4 mr-1.5" />
 Refresh
 </BaseButton>
 </div>
 </div>

 <!-- Filter Bar -->
 <div class="bg-surface rounded-xl border border-hairline p-4 flex flex-col sm:flex-row items-center justify-between gap-4">
 <div class="flex items-center gap-2 text-xs font-bold text-ink-secondary  tracking-wider">
 <Filter class="w-4 h-4 text-primary" />
 Filter Security Events
 </div>

 <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full sm:w-auto">
 <input
 v-model="filterAction"
 type="text"
 placeholder="Filter by action (e.g. auth.login)..."
 class="w-full sm:w-auto text-xs rounded-lg border border-hairline bg-surface text-ink-secondary px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"
 @keyup.enter="loadEvents(1)"
 />

 <input
 v-model="filterTargetType"
 type="text"
 placeholder="Filter by target (e.g. document)..."
 class="w-full sm:w-auto text-xs rounded-lg border border-hairline bg-surface text-ink-secondary px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"
 @keyup.enter="loadEvents(1)"
 />

 <BaseButton variant="primary" size="sm" @click="loadEvents(1)">
 Apply
 </BaseButton>
 </div>
 </div>

 <!-- Audit Events Table -->
 <DataTable
 :columns="tableColumns"
 :items="events"
 :loading="loading"
 :total="pagination.total"
 :page="pagination.current_page"
 :per-page="pagination.per_page"
 empty-title="No audit events recorded"
 empty-description="System events and user security interactions will appear in this ledger."
 @page-change="loadEvents"
 >
 <template #cell-occurred_at="{ value }">
 <span class="font-mono text-xs text-ink-muted">
 {{ value ? new Date(value).toISOString().replace('T', ' ').slice(0, 19) : '—' }}
 </span>
 </template>

 <template #cell-actor="{ item }">
 <div v-if="item.actor" class="flex items-center gap-2">
 <div class="w-6 h-6 rounded-md bg-canvas-soft flex items-center justify-center text-xs font-bold text-ink-secondary">
 {{ item.actor.name.slice(0, 2) }}
 </div>
 <div>
 <div class="text-xs font-semibold text-ink-secondary">{{ item.actor.name }}</div>
 <div class="text-xs text-ink-muted">{{ item.actor.email }}</div>
 </div>
 </div>
 <span v-else class="text-xs text-ink-muted font-mono">System Actor</span>
 </template>

 <template #cell-action="{ value }">
 <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-primary-subtle text-primary border border-primary/30">
 {{ value }}
 </span>
 </template>

 <template #cell-target="{ item }">
 <div class="text-xs">
 <span class="font-semibold text-ink-secondary text-xs">{{ item.target_type }}</span>
 <span class="font-mono text-ink-muted ml-1">#{{ item.target_id }}</span>
 </div>
 </template>

 <template #cell-ip_address="{ value }">
 <span class="font-mono text-xs text-ink-muted">
 {{ value || '127.0.0.1' }}
 </span>
 </template>

 <template #cell-actions="{ item }">
 <BaseButton variant="outline" size="sm" @click="openDetailModal(item)">
 <Eye class="w-3.5 h-3.5 mr-1" />
 Inspect
 </BaseButton>
 </template>
 </DataTable>

 <!-- Event Detail Modal -->
 <ModalDialog
 :open="showDetailModal"
 :title="`Audit Event Details #${selectedEvent?.id}`"
 max-width="lg"
 @close="showDetailModal = false"
 >
 <div v-if="selectedEvent" class="space-y-4">
 <div class="grid grid-cols-2 gap-3 text-xs">
 <div class="p-3 rounded-lg bg-canvas-soft border border-hairline">
 <span class="text-ink-muted block mb-1 font-medium">Action</span>
 <span class="font-mono font-bold text-primary">{{ selectedEvent.action }}</span>
 </div>

 <div class="p-3 rounded-lg bg-canvas-soft border border-hairline">
 <span class="text-ink-muted block mb-1 font-medium">Target Entity</span>
 <span class="font-mono font-bold text-ink-secondary">
 {{ selectedEvent.target_type }} #{{ selectedEvent.target_id }}
 </span>
 </div>

 <div class="p-3 rounded-lg bg-canvas-soft border border-hairline">
 <span class="text-ink-muted block mb-1 font-medium">Actor</span>
 <span class="font-medium text-ink-secondary">
 {{ selectedEvent.actor?.name || 'System / Unauthenticated' }}
 </span>
 </div>

 <div class="p-3 rounded-lg bg-canvas-soft border border-hairline">
 <span class="text-ink-muted block mb-1 font-medium">Timestamp</span>
 <span class="font-mono text-ink-secondary">{{ selectedEvent.occurred_at }}</span>
 </div>

 <div class="p-3 rounded-lg bg-canvas-soft border border-hairline">
 <span class="text-ink-muted block mb-1 font-medium">IP Address</span>
 <span class="font-mono text-ink-secondary">{{ selectedEvent.ip_address || '—' }}</span>
 </div>

 <div class="p-3 rounded-lg bg-canvas-soft border border-hairline">
 <span class="text-ink-muted block mb-1 font-medium">Request UUID</span>
 <span class="font-mono text-ink-secondary text-xs break-all">
 {{ selectedEvent.request_id || '—' }}
 </span>
 </div>
 </div>

 <div>
 <span class="text-xs font-bold text-ink-secondary block mb-1.5 flex items-center gap-1.5">
 <Terminal class="w-3.5 h-3.5 text-primary" />
 Payload Details (JSON)
 </span>
 <pre class="p-3 rounded-lg bg-[#191919] text-emerald-400 font-mono text-xs overflow-x-auto max-h-60 border border-[#2e2e2e]">{{ formatJson(selectedEvent.details_json) }}</pre>
 </div>
 </div>

 <template #footer>
 <div class="flex justify-end">
 <BaseButton variant="outline" size="sm" @click="showDetailModal = false">
 Close
 </BaseButton>
 </div>
 </template>
 </ModalDialog>
 </div>
</template>


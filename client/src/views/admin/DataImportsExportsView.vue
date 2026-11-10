<script setup lang="ts">
import { ref } from 'vue'
import { useToast } from '@/composables/useToast'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import ModalDialog from '@/components/ModalDialog.vue'
import {
 ArrowUpDown,
 Download,
 Upload,
 FileSpreadsheet,
 CheckCircle2,
 Clock,
 AlertCircle,
 RefreshCw,
 Plus,
} from 'lucide-vue-next'

const toast = useToast()
const activeTab = ref<'exports' | 'imports'>('exports')
const isLoading = ref(false)
const showImportModal = ref(false)

const exportsList = ref([
 {
 id: 1,
 title: 'Correspondence Volume Summary Q1',
 format: 'CSV / Excel',
 requested_at: '2026-10-29 14:30',
 status: 'completed',
 record_count: 142,
 file_size: '48 KB',
 },
 {
 id: 2,
 title: 'Archive Preservation Manifest (INST_PERM)',
 format: 'CSV',
 requested_at: '2026-10-30 08:15',
 status: 'completed',
 record_count: 58,
 file_size: '22 KB',
 },
])

const importsList = ref([
 {
 id: 1,
 source: 'Historical Registry Digitization Batch A',
 module: 'Correspondence',
 total_rows: 85,
 imported_rows: 85,
 rejected_rows: 0,
 status: 'completed',
 executed_at: '2026-10-25 11:20',
 },
])

function triggerExport(format: string) {
 toast.success(`Export job queued in ${format} format.`)
}

function handleStartImport() {
 toast.success('Batch import validated and queued for background ingestion.')
 showImportModal.value = false
}
</script>

<template>
 <div class="space-y-6">
 <!-- Header -->
 <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
 <div>
 <div class="notion-eyebrow text-primary mb-1">
 TRUST & REPORTING / DATA OPERATIONS
 </div>
 <h1 class="text-2xl font-semibold tracking-tight text-ink-secondary">
 Data Imports & Structured Exports
 </h1>
 <p class="text-xs sm:text-sm text-ink-muted mt-0.5">
 Execute authorized CSV/Excel dataset exports and validated batch metadata imports.
 </p>
 </div>

 <div class="flex items-center gap-2.5">
 <BaseButton
 v-if="activeTab === 'exports'"
 variant="primary"
 size="sm"
 @click="triggerExport('CSV')"
 >
 <Download class="w-4 h-4 mr-1.5" />
 Request Export
 </BaseButton>

 <BaseButton
 v-else
 variant="primary"
 size="sm"
 @click="showImportModal = true"
 >
 <Plus class="w-4 h-4 mr-1.5" />
 New Batch Import
 </BaseButton>
 </div>
 </div>

 <!-- Tabs -->
 <div class="border-b border-hairline flex gap-6 text-xs font-medium">
 <button
 type="button"
 @click="activeTab = 'exports'"
 class="pb-3 border-b-2 flex items-center gap-2 transition-colors"
 :class="activeTab === 'exports'
 ? 'border-primary text-primary font-semibold'
 : 'border-transparent text-ink-muted hover:text-ink-secondary '"
 >
 <Download class="w-4 h-4" />
 Authorized Data Exports
 </button>

 <button
 type="button"
 @click="activeTab = 'imports'"
 class="pb-3 border-b-2 flex items-center gap-2 transition-colors"
 :class="activeTab === 'imports'
 ? 'border-primary text-primary font-semibold'
 : 'border-transparent text-ink-muted hover:text-ink-secondary '"
 >
 <Upload class="w-4 h-4" />
 Batch Data Ingestion Runs
 </button>
 </div>

 <!-- Exports Table -->
 <div v-if="activeTab === 'exports'" class="bg-surface border border-hairline rounded-lg shadow-xs overflow-hidden">
 <table class="w-full text-xs text-left">
 <thead class="bg-canvas-soft border-b border-hairline text-ink-muted font-semibold uppercase tracking-wider">
 <tr>
 <th class="p-3.5">Export Job</th>
 <th class="p-3.5">Format</th>
 <th class="p-3.5">Requested At</th>
 <th class="p-3.5">Records</th>
 <th class="p-3.5">File Size</th>
 <th class="p-3.5 text-right">Action</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-hairline text-ink-secondary">
 <tr v-for="exp in exportsList" :key="exp.id" class="hover:bg-surface-hover transition-colors">
 <td class="p-3.5 font-medium flex items-center gap-2">
 <FileSpreadsheet class="w-4 h-4 text-primary" />
 {{ exp.title }}
 </td>
 <td class="p-3.5 font-mono">{{ exp.format }}</td>
 <td class="p-3.5 text-ink-muted">{{ exp.requested_at }}</td>
 <td class="p-3.5">{{ exp.record_count }} rows</td>
 <td class="p-3.5 font-mono text-[11px]">{{ exp.file_size }}</td>
 <td class="p-3.5 text-right">
 <button
 type="button"
 @click="toast.success('Downloading archive artifact...')"
 class="px-2.5 py-1 text-xs font-medium text-primary hover:underline inline-flex items-center gap-1"
 >
 <Download class="w-3.5 h-3.5" />
 Download
 </button>
 </td>
 </tr>
 </tbody>
 </table>
 </div>

 <!-- Imports Table -->
 <div v-else class="bg-surface border border-hairline rounded-lg shadow-xs overflow-hidden">
 <table class="w-full text-xs text-left">
 <thead class="bg-canvas-soft border-b border-hairline text-ink-muted font-semibold uppercase tracking-wider">
 <tr>
 <th class="p-3.5">Batch Source</th>
 <th class="p-3.5">Target Module</th>
 <th class="p-3.5">Executed At</th>
 <th class="p-3.5">Ingested / Rejected</th>
 <th class="p-3.5">Status</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-hairline text-ink-secondary">
 <tr v-for="imp in importsList" :key="imp.id" class="hover:bg-surface-hover transition-colors">
 <td class="p-3.5 font-medium">{{ imp.source }}</td>
 <td class="p-3.5">{{ imp.module }}</td>
 <td class="p-3.5 text-ink-muted">{{ imp.executed_at }}</td>
 <td class="p-3.5">
 <span class="text-emerald-600 font-semibold">{{ imp.imported_rows }} imported</span>
 <span v-if="imp.rejected_rows > 0" class="text-rose-600 ml-2">({{ imp.rejected_rows }} rejected)</span>
 </td>
 <td class="p-3.5">
 <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-[#e8f7ec] text-[#1aae39] dark:bg-[#183321] dark:text-[#42cf63] border border-[#1aae39]/20">
 Completed
 </span>
 </td>
 </tr>
 </tbody>
 </table>
 </div>

 <!-- New Import Modal -->
 <ModalDialog :open="showImportModal" title="Validated Batch Data Ingestion" @close="showImportModal = false">
 <div class="space-y-4 text-xs">
 <p class="text-ink-muted leading-relaxed">
 Upload a structured CSV or XLSX template. Ingestion maps rows to canonical database entities and validates foreign keys prior to persistence. Arbitrary table insertion is barred.
 </p>

 <div class="space-y-1.5">
 <label class="block font-semibold text-ink-secondary">Target Module</label>
 <BaseSelect
 :model-value="'correspondence'"
 :options="[
 { value: 'correspondence', label: 'Historical Inbound/Outbound Correspondence' },
 { value: 'documents', label: 'Archival Institutional Documents' },
 { value: 'staff_inventory', label: 'Physical Original Dossier Inventory' },
 ]"
 />
 </div>

 <div class="space-y-1.5">
 <label class="block font-semibold text-ink-secondary">Template File (CSV / XLSX)</label>
 <input
 type="file"
 accept=".csv,.xlsx"
 class="block w-full text-xs text-ink-muted file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-primary-subtle file:text-primary hover:file:bg-primary/20 dark:file:bg-[#172c42] dark:file:text-[#62aef0]"
 />
 </div>

 <div class="pt-2 flex justify-end gap-2">
 <BaseButton variant="outline" size="sm" @click="showImportModal = false">Cancel</BaseButton>
 <BaseButton variant="primary" size="sm" @click="handleStartImport">
 Validate & Ingest
 </BaseButton>
 </div>
 </div>
 </ModalDialog>
 </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { reportsApi } from '@/api/client'
import type { ReportsSummary } from '@/types'
import { downloadBlob } from '@/utils/formatters'
import BaseButton from '@/components/ui/BaseButton.vue'
import LoadingSkeleton from '@/components/ui/LoadingSkeleton.vue'
import { useToast } from '@/composables/useToast'
import {
 BarChart3,
 Download,
 FileSpreadsheet,
 FileText,
 Filter,
 RotateCcw,
 Shield,
 Layers,
 Send,
 Mail,
 Archive,
 RefreshCw,
 Building2,
} from 'lucide-vue-next'

const toast = useToast()
const loading = ref(true)
const exportingCsv = ref(false)
const summary = ref<ReportsSummary | null>(null)

// Date filters
const dateFrom = ref('')
const dateTo = ref('')

async function loadData() {
 loading.value = true
 try {
 const res = await reportsApi.getSummary({
 from_date: dateFrom.value || undefined,
 to_date: dateTo.value || undefined,
 })
 summary.value = res.data.data
 } catch (err: any) {
 toast.error('Report Error', err.response?.data?.message || 'Failed to load report analytics.')
 } finally {
 loading.value = false
 }
}

function handleResetFilters() {
 dateFrom.value = ''
 dateTo.value = ''
 loadData()
}

function exportCsvReport() {
 if (!summary.value) return
 exportingCsv.value = true

 try {
 const lines: string[] = []
 lines.push('WOLLO UNIVERSITY ADMINISTRATIVE & DOCUMENT MANAGEMENT SYSTEM')
 lines.push(`OPERATIONAL METRICS REPORT (${summary.value.period.from} to ${summary.value.period.to})`)
 lines.push('')
 lines.push('OVERVIEW TOTALS')
 lines.push(`Total Documents,${summary.value.totals.documents}`)
 lines.push(`Total Correspondence,${summary.value.totals.correspondence}`)
 lines.push(`Inter-Unit Movements,${summary.value.totals.movements}`)
 lines.push(`Archived Records,${summary.value.totals.archive_entries}`)
 lines.push('')

 lines.push('DOCUMENTS BY CATEGORY')
 lines.push('Category,Volume')
 summary.value.documents_by_category.forEach((item) => {
 lines.push(`"${item.category}",${item.count}`)
 })
 lines.push('')

 lines.push('DOCUMENTS BY CONFIDENTIALITY LEVEL')
 lines.push('Confidentiality,Volume')
 summary.value.documents_by_confidentiality.forEach((item) => {
 lines.push(`"${item.confidentiality}",${item.count}`)
 })
 lines.push('')

 lines.push('CORRESPONDENCE BY TYPE')
 lines.push('Type,Volume')
 summary.value.correspondence_by_type.forEach((item) => {
 lines.push(`"${item.type}",${item.count}`)
 })
 lines.push('')

 lines.push('TOP INTER-UNIT MOVEMENT DESTINATIONS')
 lines.push('Receiving Unit,Dispatched Count')
 summary.value.top_movement_destinations.forEach((item) => {
 lines.push(`"${item.unit}",${item.count}`)
 })

 downloadBlob(
  new Blob([lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' }),
  `WU_DMS_Report_${new Date().toISOString().slice(0, 10)}.csv`
 )

 toast.success('Report Exported', 'CSV summary report generated and downloaded successfully.')
 } catch {
 toast.error('Export Error', 'Failed to generate spreadsheet file.')
 } finally {
 exportingCsv.value = false
 }
}

onMounted(() => {
 loadData()
})
</script>

<template>
 <div class="space-y-6">
 <!-- Header -->
 <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-hairline pb-4">
 <div>
 <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-ink flex items-center gap-2.5">
 <BarChart3 class="w-5 h-5 sm:w-6 sm:h-6 text-primary shrink-0" />
 <span>Executive Reports & Statistical Summaries</span>
 </h1>
 <p class="text-xs text-ink-muted mt-0.5">
 Institutional analytics, operational correspondence audit, document lifecycle distribution, and movement trends.
 </p>
 </div>

 <div class="flex items-center gap-2.5">
 <BaseButton variant="outline" size="sm" :loading="loading" @click="loadData">
 <RefreshCw class="w-4 h-4 mr-1.5" />
 Refresh
 </BaseButton>

 <BaseButton
 variant="secondary"
 size="sm"
 :loading="exportingCsv"
 :disabled="!summary"
 @click="exportCsvReport"
 >
 <FileSpreadsheet class="w-4 h-4 mr-1.5 text-emerald-600" />
 Export CSV Spreadsheet
 </BaseButton>
 </div>
 </div>

 <!-- Date Filter Bar -->
 <div class="bg-surface rounded-lg border border-hairline p-4 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
 <div class="flex items-center gap-2 text-xs font-semibold text-ink-secondary">
 <Filter class="w-4 h-4 text-primary" />
 Reporting Period
 </div>

 <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
 <div class="flex items-center gap-2 text-xs">
 <label class="text-ink-muted">From:</label>
 <input
 v-model="dateFrom"
 type="date"
 class="text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-2.5 py-1.5 focus:border-primary focus:ring-1 focus:ring-primary/20"
 />
 </div>

 <div class="flex items-center gap-2 text-xs">
 <label class="text-ink-muted">To:</label>
 <input
 v-model="dateTo"
 type="date"
 class="text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-2.5 py-1.5 focus:border-primary focus:ring-1 focus:ring-primary/20"
 />
 </div>

 <BaseButton variant="primary" size="sm" @click="loadData">
 Apply Filter
 </BaseButton>

 <BaseButton variant="outline" size="sm" @click="handleResetFilters">
 <RotateCcw class="w-3.5 h-3.5 mr-1" />
 Reset
 </BaseButton>
 </div>
 </div>

 <!-- Loading Skeleton -->
 <LoadingSkeleton v-if="loading" :count="4" class="my-4" />

 <!-- Summary Content -->
 <div v-else-if="summary" class="space-y-6">
 <!-- Totals Overview Cards -->
 <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
 <div class="bg-surface p-4 rounded-lg border border-hairline flex items-center gap-4">
 <div class="p-3 bg-primary-subtle text-primary rounded-md">
 <FileText class="w-6 h-6" />
 </div>
 <div>
 <div class="text-2xl font-semibold text-ink-secondary">{{ summary.totals.documents }}</div>
 <div class="text-xs text-ink-muted font-medium">Total Registered Documents</div>
 </div>
 </div>

 <div class="bg-surface p-4 rounded-lg border border-hairline flex items-center gap-4">
 <div class="p-3 bg-primary-subtle text-primary rounded-md">
 <Mail class="w-6 h-6" />
 </div>
 <div>
 <div class="text-2xl font-semibold text-ink-secondary">{{ summary.totals.correspondence }}</div>
 <div class="text-xs text-ink-muted font-medium">Total Correspondence</div>
 </div>
 </div>

 <div class="bg-surface p-4 rounded-lg border border-hairline flex items-center gap-4">
 <div class="p-3 bg-[#e8f7ec] dark:bg-[#183321] text-[#1aae39] dark:text-[#42cf63] rounded-md">
 <Send class="w-6 h-6" />
 </div>
 <div>
 <div class="text-2xl font-semibold text-ink-secondary">{{ summary.totals.movements }}</div>
 <div class="text-xs text-ink-muted font-medium">Inter-Unit Movements</div>
 </div>
 </div>

 <div class="bg-surface p-4 rounded-lg border border-hairline flex items-center gap-4">
 <div class="p-3 bg-[#fff4e6] dark:bg-[#382412] text-[#dd5b00] dark:text-[#ff9243] rounded-md">
 <Archive class="w-6 h-6" />
 </div>
 <div>
 <div class="text-2xl font-semibold text-ink-secondary">{{ summary.totals.archive_entries }}</div>
 <div class="text-xs text-ink-muted font-medium">Archived Records</div>
 </div>
 </div>
 </div>

 <!-- Analytical Breakdowns Grid -->
 <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
 <!-- Breakdown: Document Categories -->
 <div class="bg-surface rounded-lg border border-hairline p-5 space-y-4">
 <div class="flex items-center justify-between pb-3 border-b border-hairline">
 <h3 class="text-sm font-semibold text-ink-secondary flex items-center gap-2">
 <Layers class="w-4 h-4 text-primary" />
 Document Volume by Category
 </h3>
 <span class="text-xs text-ink-muted font-medium">
 {{ summary.documents_by_category.length }} categories
 </span>
 </div>

 <div v-if="summary.documents_by_category.length === 0" class="py-8 text-center text-xs text-ink-muted">
 No document category records in selected date range.
 </div>

 <div v-else class="space-y-3">
 <div
 v-for="cat in summary.documents_by_category"
 :key="cat.category"
 class="space-y-1"
 >
 <div class="flex items-center justify-between text-xs gap-2">
 <span class="font-medium text-ink-secondary truncate max-w-[200px] sm:max-w-xs" :title="cat.category">{{ cat.category }}</span>
 <span class="font-mono font-semibold text-primary shrink-0">{{ cat.count }}</span>
 </div>
 <div class="w-full bg-surface-muted h-2 rounded-md overflow-hidden">
 <div
 class="bg-primary h-2 rounded-md"
 :style="{ width: `${Math.min(100, (cat.count / (summary.totals.documents || 1)) * 100)}%` }"
 />
 </div>
 </div>
 </div>
 </div>

 <!-- Breakdown: Confidentiality Levels -->
 <div class="bg-surface rounded-lg border border-hairline p-5 space-y-4">
 <div class="flex items-center justify-between pb-3 border-b border-hairline">
 <h3 class="text-sm font-semibold text-ink-secondary flex items-center gap-2">
 <Shield class="w-4 h-4 text-primary" />
 Confidentiality Classification Distribution
 </h3>
 <span class="text-xs text-ink-muted font-medium">
 {{ summary.documents_by_confidentiality.length }} tiers
 </span>
 </div>

 <div v-if="summary.documents_by_confidentiality.length === 0" class="py-8 text-center text-xs text-ink-muted">
 No confidentiality distribution records in selected date range.
 </div>

 <div v-else class="space-y-3">
 <div
 v-for="conf in summary.documents_by_confidentiality"
 :key="conf.confidentiality"
 class="space-y-1"
 >
 <div class="flex items-center justify-between text-xs">
 <span class="font-medium text-ink-secondary uppercase">{{ conf.confidentiality }}</span>
 <span class="font-mono font-semibold text-primary">{{ conf.count }}</span>
 </div>
 <div class="w-full bg-surface-muted h-2 rounded-md overflow-hidden">
 <div
 class="bg-primary h-2 rounded-md"
 :style="{ width: `${Math.min(100, (conf.count / (summary.totals.documents || 1)) * 100)}%` }"
 />
 </div>
 </div>
 </div>
 </div>

 <!-- Breakdown: Correspondence Types -->
 <div class="bg-surface rounded-lg border border-hairline p-5 space-y-4">
 <div class="flex items-center justify-between pb-3 border-b border-hairline">
 <h3 class="text-sm font-semibold text-ink-secondary flex items-center gap-2">
 <Mail class="w-4 h-4 text-emerald-500" />
 Correspondence Classification
 </h3>
 <span class="text-xs text-ink-muted font-medium">
 {{ summary.correspondence_by_type.length }} types
 </span>
 </div>

 <div v-if="summary.correspondence_by_type.length === 0" class="py-8 text-center text-xs text-ink-muted">
 No correspondence volume records in selected date range.
 </div>

 <div v-else class="space-y-3">
 <div
 v-for="ctype in summary.correspondence_by_type"
 :key="ctype.type"
 class="space-y-1"
 >
 <div class="flex items-center justify-between text-xs">
 <span class="font-medium text-ink-secondary">{{ ctype.type }}</span>
 <span class="font-mono font-semibold text-emerald-600 dark:text-emerald-400">{{ ctype.count }}</span>
 </div>
 <div class="w-full bg-surface-muted h-2 rounded-md overflow-hidden">
 <div
 class="bg-emerald-600 h-2 rounded-md"
 :style="{ width: `${Math.min(100, (ctype.count / (summary.totals.correspondence || 1)) * 100)}%` }"
 />
 </div>
 </div>
 </div>
 </div>

 <!-- Breakdown: Movement Destinations -->
 <div class="bg-surface rounded-lg border border-hairline p-5 space-y-4">
 <div class="flex items-center justify-between pb-3 border-b border-hairline">
 <h3 class="text-sm font-semibold text-ink-secondary flex items-center gap-2">
 <Building2 class="w-4 h-4 text-amber-500" />
 Top Inter-Unit Movement Destinations
 </h3>
 <span class="text-xs text-ink-muted font-medium">
 Top units by incoming dispatches
 </span>
 </div>

 <div v-if="summary.top_movement_destinations.length === 0" class="py-8 text-center text-xs text-ink-muted">
 No inter-unit movement dispatches recorded in selected date range.
 </div>

 <div v-else class="space-y-3">
 <div
 v-for="dest in summary.top_movement_destinations"
 :key="dest.unit"
 class="space-y-1"
 >
 <div class="flex items-center justify-between text-xs gap-2">
 <span class="font-medium text-ink-secondary truncate max-w-[200px] sm:max-w-xs" :title="dest.unit">{{ dest.unit }}</span>
 <span class="font-mono font-semibold text-amber-600 dark:text-amber-400 shrink-0">{{ dest.count }} dispatches</span>
 </div>
 <div class="w-full bg-surface-muted h-2 rounded-md overflow-hidden">
 <div
 class="bg-amber-600 h-2 rounded-md"
 :style="{ width: `${Math.min(100, (dest.count / (summary.totals.movements || 1)) * 100)}%` }"
 />
 </div>
 </div>
 </div>
 </div>
 </div>
 </div>
 </div>
</template>

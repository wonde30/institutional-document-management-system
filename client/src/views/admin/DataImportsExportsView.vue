<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import ModalDialog from '@/components/ModalDialog.vue'
import LoadingSkeleton from '@/components/ui/LoadingSkeleton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import BaseAlert from '@/components/ui/BaseAlert.vue'
import { dataTransferApi } from '@/api/client'
import { formatStatus } from '@/utils/formatters'
import type {
  DataDataset,
  DataExportOperation,
  DataImportOperation,
} from '@/types'
import {
  ArrowUpDown,
  Download,
  Upload,
  FileSpreadsheet,
  CheckCircle2,
  AlertCircle,
  RefreshCw,
  Plus,
  X,
} from 'lucide-vue-next'

const toast = useToast()
const authStore = useAuthStore()

const activeTab = ref<'imports' | 'exports'>('imports')

const isLoading = ref(true)
const loadError = ref<string | null>(null)
const datasets = ref<DataDataset[]>([])
const maxImportKilobytes = ref(0)

const importHistory = ref<DataImportOperation[]>([])
const exportHistory = ref<DataExportOperation[]>([])

/* ------------------------------------------------------------------ import */

const importDataset = ref<string>('')
const importFile = ref<File | null>(null)
const isUploading = ref(false)
const uploadPercent = ref(0)
const inspection = ref<DataImportOperation | null>(null)
const isExecuting = ref(false)
const executionResult = ref<DataImportOperation | null>(null)
const importError = ref<string | null>(null)
const pendingDiscard = ref<DataImportOperation | null>(null)

/* ------------------------------------------------------------------ export */

const exportDataset = ref<string>('')
const exportFilters = ref<Record<string, string>>({})
const isGenerating = ref(false)
const exportError = ref<string | null>(null)

const importDatasets = computed(() => datasets.value.filter((d) => d.direction === 'import'))
const exportDatasets = computed(() => datasets.value.filter((d) => d.direction === 'export'))

const selectedImportDataset = computed(() =>
  datasets.value.find((d) => d.key === importDataset.value) ?? null
)
const selectedExportDataset = computed(() =>
  datasets.value.find((d) => d.key === exportDataset.value) ?? null
)

const importOptions = computed(() =>
  importDatasets.value.map((d) => ({ label: d.label, value: d.key }))
)
const exportOptions = computed(() =>
  exportDatasets.value.map((d) => ({ label: d.label, value: d.key }))
)

const exportFilterFields = computed(() => selectedExportDataset.value?.filters ?? [])

async function loadAll() {
  isLoading.value = true
  loadError.value = null

  try {
    const [catalogue, imports, exports] = await Promise.all([
      dataTransferApi.datasets(),
      dataTransferApi.imports(),
      dataTransferApi.exports(),
    ])

    datasets.value = catalogue.data?.data ?? []
    maxImportKilobytes.value = catalogue.data?.meta?.max_import_kilobytes ?? 0
    importHistory.value = imports.data?.data ?? []
    exportHistory.value = exports.data?.data ?? []

    if (!importDataset.value && importDatasets.value.length > 0) {
      importDataset.value = importDatasets.value[0].key
    }
    if (!exportDataset.value && exportDatasets.value.length > 0) {
      exportDataset.value = exportDatasets.value[0].key
    }
  } catch (error: unknown) {
    const apiError = error as { response?: { status?: number; data?: { message?: string } } }
    if (apiError.response?.status === 403) {
      loadError.value = 'You are not authorized to use the data operations module.'
    } else {
      loadError.value =
        apiError.response?.data?.message || 'The data operations could not be loaded.'
    }
  } finally {
    isLoading.value = false
  }
}

function handleFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  importFile.value = input.files?.[0] ?? null
  inspection.value = null
  executionResult.value = null
  importError.value = null
}

async function inspectFile() {
  if (!importFile.value || !importDataset.value || isUploading.value) return

  isUploading.value = true
  uploadPercent.value = 0
  importError.value = null
  inspection.value = null
  executionResult.value = null

  const form = new FormData()
  form.append('dataset', importDataset.value)
  form.append('file', importFile.value)

  if (authStore.activeUnitId) {
    form.append('scope_unit_id', String(authStore.activeUnitId))
  }

  try {
    const response = await dataTransferApi.inspectImport(form, (percent) => {
      uploadPercent.value = percent
    })

    inspection.value = response.data?.data ?? null
    toast.success('The file was inspected. No record has been created yet.')
  } catch (error: unknown) {
    const apiError = error as { response?: { data?: { message?: string } } }
    importError.value =
      apiError.response?.data?.message ||
      'The file could not be inspected. Please try again.'
  } finally {
    isUploading.value = false
    uploadPercent.value = 0
  }
}

async function executeImport() {
  if (!inspection.value || isExecuting.value) return

  isExecuting.value = true
  importError.value = null

  try {
    const response = await dataTransferApi.executeImport(inspection.value.id)

    executionResult.value = response.data?.data ?? null
    inspection.value = null
    importFile.value = null

    const result = executionResult.value
    if (result?.status === 'completed') {
      toast.success(`${result.imported_rows} record(s) were imported.`)
    } else if (result?.status === 'completed_with_errors') {
      toast.warning(
        `${result.imported_rows} record(s) were imported and ${result.failed_rows} failed.`
      )
    } else {
      toast.error(result?.failure_reason || 'The import failed. No record was created.')
    }

    await refreshHistory()
  } catch (error: unknown) {
    const apiError = error as { response?: { data?: { message?: string } } }
    importError.value =
      apiError.response?.data?.message || 'The import could not be executed.'
  } finally {
    isExecuting.value = false
  }
}

async function executeImportFor(operation: DataImportOperation) {
  if (isExecuting.value) return

  isExecuting.value = true
  importError.value = null

  try {
    const response = await dataTransferApi.executeImport(operation.id)

    executionResult.value = response.data?.data ?? null

    const result = executionResult.value
    if (result?.status === 'completed') {
      toast.success(`${result.imported_rows} record(s) were imported.`)
    } else if (result?.status === 'completed_with_errors') {
      toast.warning(
        `${result.imported_rows} record(s) were imported and ${result.failed_rows} failed.`
      )
    } else {
      toast.error(result?.failure_reason || 'The import failed. No record was created.')
    }

    await refreshHistory()
  } catch (error: unknown) {
    const apiError = error as { response?: { data?: { message?: string } } }
    importError.value =
      apiError.response?.data?.message || 'The import could not be executed.'
  } finally {
    isExecuting.value = false
  }
}

async function discardImport() {
  const operation = pendingDiscard.value
  if (!operation) return

  try {
    await dataTransferApi.discardImport(operation.id)
    toast.success('The import operation was discarded.')
    await refreshHistory()
  } catch (error: unknown) {
    const apiError = error as { response?: { data?: { message?: string } } }
    toast.error(apiError.response?.data?.message || 'The operation could not be discarded.')
  } finally {
    pendingDiscard.value = null
  }
}

async function refreshHistory() {
  try {
    const [imports, exports] = await Promise.all([
      dataTransferApi.imports(),
      dataTransferApi.exports(),
    ])
    importHistory.value = imports.data?.data ?? []
    exportHistory.value = exports.data?.data ?? []
  } catch {
    // The history is a convenience; the operation itself has already reported.
  }
}

/* ------------------------------------------------------------------ export */

async function generateExport() {
  if (!exportDataset.value || isGenerating.value) return

  isGenerating.value = true
  exportError.value = null

  try {
    const response = await dataTransferApi.generateExport({
      dataset: exportDataset.value,
      filters: exportFilters.value,
    })

    const operation = response.data?.data

    if (operation?.status === 'completed') {
      toast.success(`The export file was generated with ${operation.record_count} record(s).`)
      await refreshHistory()
    } else {
      exportError.value = operation?.failure_reason || 'The export could not be generated.'
    }
  } catch (error: unknown) {
    const apiError = error as { response?: { data?: { message?: string } } }
    exportError.value =
      apiError.response?.data?.message || 'The export could not be generated.'
  } finally {
    isGenerating.value = false
  }
}

function downloadExport(operation: DataExportOperation) {
  if (!operation.retrievable) return
  window.open(dataTransferApi.exportDownloadUrl(operation.id), '_blank')
}

function downloadErrorReport(operation: DataImportOperation) {
  window.open(dataTransferApi.importErrorsUrl(operation.id), '_blank')
}

function statusClass(status: string): string {
  if (status === 'completed') {
    return 'bg-[#e8f7ec] text-[#1aae39] dark:bg-[#183321] dark:text-[#42cf63] border border-[#1aae39]/20'
  }
  if (status === 'failed' || status === 'completed_with_errors') {
    return 'bg-[#fdecec] text-[#e03e3e] dark:bg-[#3a1414] dark:text-[#f87171] border border-[#e03e3e]/20'
  }
  if (status === 'discarded') {
    return 'bg-canvas-soft text-ink-muted border border-hairline'
  }
  return 'bg-[#fdf3e3] text-[#b8791f] dark:bg-[#2e2312] dark:text-[#e0a03a] border border-[#e0a03a]/25'
}

function formatBytes(bytes: number): string {
  if (!bytes) return '0 KB'
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

onMounted(loadAll)
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 border-b border-hairline pb-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-ink flex items-center gap-2.5">
          <ArrowUpDown class="w-5 h-5 sm:w-6 sm:h-6 text-primary shrink-0" />
          <span>Data Imports &amp; Structured Exports</span>
        </h1>
        <p class="text-xs text-ink-muted mt-0.5">
          Run a validated batch import against an authorised organizational unit, or generate a
          structured export of the records you are permitted to reach.
        </p>
      </div>

      <BaseButton variant="outline" size="sm" :disabled="isLoading" @click="loadAll">
        <RefreshCw class="w-3.5 h-3.5 mr-1.5" />
        Refresh
      </BaseButton>
    </div>

    <!-- Loading -->
    <LoadingSkeleton v-if="isLoading" :rows="6" type="card" />

    <!-- Load failure -->
    <BaseAlert v-else-if="loadError" variant="danger">
      <div class="flex items-center justify-between gap-3">
        <span>{{ loadError }}</span>
        <BaseButton variant="outline" class="shrink-0" @click="loadAll">Retry</BaseButton>
      </div>
    </BaseAlert>

    <template v-else>
      <!-- Scope notice -->
      <div class="p-3.5 rounded-lg bg-canvas-soft border border-hairline text-xs text-ink-muted flex items-start gap-2.5">
        <FileSpreadsheet class="w-4 h-4 shrink-0 mt-0.5 text-primary" />
        <span>
          An import always applies to the active organizational unit
          (<strong>{{ authStore.activeUnit?.name || 'none selected' }}</strong>). An export covers
          exactly the records you are permitted to reach; a filter narrows that set and never
          broadens it.
        </span>
      </div>

      <!-- Tabs -->
      <div class="border-b border-hairline flex gap-6 text-xs font-medium">
        <button
          type="button"
          @click="activeTab = 'imports'"
          class="pb-3 border-b-2 flex items-center gap-2 transition-colors"
          :class="activeTab === 'imports'
            ? 'border-primary text-primary font-semibold'
            : 'border-transparent text-ink-muted hover:text-ink-secondary '"
        >
          <Upload class="w-4 h-4" />
          Batch Data Ingestion
        </button>
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
      </div>

      <!-- ============================ IMPORT ============================ -->
      <div v-if="activeTab === 'imports'" class="space-y-6">
        <div class="p-5 rounded-xl bg-surface border border-hairline space-y-4">
          <h2 class="text-sm font-semibold text-ink-secondary flex items-center gap-2">
            <Upload class="w-4 h-4 text-primary" />
            New Batch Import
          </h2>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-ink-secondary" for="import-dataset">
                Target dataset
              </label>
              <BaseSelect
                id="import-dataset"
                :model-value="importDataset"
                :options="importOptions"
                :disabled="isUploading || isExecuting"
                @update:model-value="(value: string) => { importDataset = value; inspection = null; executionResult = null }"
              />
              <p v-if="selectedImportDataset" class="text-xs text-ink-muted">
                {{ selectedImportDataset.description }}
              </p>
              <p v-if="selectedImportDataset?.required_headers?.length" class="text-xs text-ink-muted">
                Required columns: {{ selectedImportDataset.required_headers.join(', ') }}
              </p>
            </div>

            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-ink-secondary" for="import-file">
                Dataset file (CSV)
              </label>
              <input
                id="import-file"
                type="file"
                accept=".csv,text/csv"
                :disabled="isUploading || isExecuting"
                class="block w-full text-xs text-ink-muted file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-primary-subtle file:text-primary hover:file:bg-primary/20 dark:file:bg-[#172c42] dark:file:text-[#62aef0]"
                @change="handleFileChange"
              />
              <p class="text-xs text-ink-muted">
                Maximum file size {{ Math.round(maxImportKilobytes / 1024) }} MB.
              </p>
            </div>
          </div>

          <!-- Upload progress -->
          <div v-if="isUploading" class="space-y-1.5">
            <div class="flex items-center justify-between text-xs text-ink-muted">
              <span>Uploading the dataset file…</span>
              <span>{{ uploadPercent }}%</span>
            </div>
            <div class="h-1.5 w-full rounded-full bg-canvas-soft overflow-hidden">
              <div
                class="h-full bg-primary transition-all"
                :style="{ width: `${uploadPercent}%` }"
              ></div>
            </div>
          </div>

          <div v-if="importError" class="p-3 rounded-lg bg-[#fdecec] dark:bg-[#3a1414] border border-[#e03e3e]/20 flex items-start gap-2" role="alert">
            <AlertCircle class="w-4 h-4 shrink-0 mt-0.5 text-[#e03e3e] dark:text-[#f87171]" />
            <p class="text-xs text-ink leading-relaxed">{{ importError }}</p>
          </div>

          <div class="flex justify-end gap-2">
            <BaseButton
              variant="primary"
              :loading="isUploading"
              :disabled="!importFile || isExecuting"
              @click="inspectFile"
            >
              <Upload class="w-4 h-4 mr-1.5" />
              Inspect file
            </BaseButton>
          </div>
        </div>

        <!-- Validation preview -->
        <div v-if="inspection" class="p-5 rounded-xl bg-surface border border-hairline space-y-4">
          <div class="flex items-start justify-between gap-3">
            <h2 class="text-sm font-semibold text-ink-secondary">Validation preview</h2>
            <span class="text-xs font-medium px-1.5 py-0.5 rounded">
              Nothing has been created yet
            </span>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="p-3 rounded-lg bg-canvas-soft/60 border border-hairline">
              <div class="text-[12px] text-ink-muted">Total records</div>
              <div class="text-lg font-semibold text-ink">{{ inspection.total_rows }}</div>
            </div>
            <div class="p-3 rounded-lg bg-canvas-soft/60 border border-hairline">
              <div class="text-[12px] text-ink-muted">Valid</div>
              <div class="text-lg font-semibold text-[#1aae39] dark:text-[#42cf63]">{{ inspection.valid_rows }}</div>
            </div>
            <div class="p-3 rounded-lg bg-canvas-soft/60 border border-hairline">
              <div class="text-[12px] text-ink-muted">Invalid</div>
              <div class="text-lg font-semibold text-[#e03e3e] dark:text-[#f87171]">{{ inspection.invalid_rows }}</div>
            </div>
            <div class="p-3 rounded-lg bg-canvas-soft/60 border border-hairline">
              <div class="text-[12px] text-ink-muted">Duplicates</div>
              <div class="text-lg font-semibold text-ink">{{ inspection.duplicate_rows }}</div>
            </div>
          </div>

          <!-- Row level errors -->
          <div v-if="inspection.errors?.length" class="space-y-2">
            <h3 class="text-xs font-semibold text-ink-secondary">
              Records that need attention ({{ inspection.errors.length }})
            </h3>
            <div class="max-h-64 overflow-auto border border-hairline rounded-lg">
              <table class="w-full text-xs text-left min-w-[480px]">
                <thead class="bg-canvas-soft border-b border-hairline text-ink-muted font-semibold   sticky top-0">
                  <tr>
                    <th class="p-2.5 w-16">Row</th>
                    <th class="p-2.5 w-40">Column</th>
                    <th class="p-2.5">Reason</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-hairline text-ink-secondary">
                  <tr v-for="(rowError, index) in inspection.errors" :key="index">
                    <td class="p-2.5 font-mono text-xs">{{ rowError.row_number }}</td>
                    <td class="p-2.5 font-mono text-xs">{{ rowError.column_name || '—' }}</td>
                    <td class="p-2.5">{{ rowError.message }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <a
              :href="dataTransferApi.importErrorsUrl(inspection.id)"
              class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
            >
              <Download class="w-3.5 h-3.5" />
              Download the error report
            </a>
          </div>

          <div class="flex justify-end gap-2">
            <BaseButton variant="outline" :disabled="isExecuting" @click="inspection = null">
              <X class="w-3.5 h-3.5 mr-1" />
              Cancel
            </BaseButton>
            <BaseButton
              variant="primary"
              :loading="isExecuting"
              :disabled="!inspection.executable"
              @click="executeImport"
            >
              <CheckCircle2 class="w-4 h-4 mr-1.5" />
              Import {{ inspection.valid_rows }} record(s)
            </BaseButton>
          </div>
        </div>

        <!-- Execution result -->
        <div
          v-if="executionResult"
          class="p-5 rounded-xl border space-y-3"
          :class="executionResult.status === 'completed'
            ? 'bg-[#e8f7ec] dark:bg-[#14301d] border-[#1aae39]/25'
            : 'bg-[#fdf3e3] dark:bg-[#2e2312] border-[#e0a03a]/30'"
        >
          <h2 class="text-sm font-semibold text-ink-secondary flex items-center gap-2">
            <CheckCircle2 class="w-4 h-4 text-primary" />
            Import {{ executionResult.status === 'completed' ? 'completed' : 'completed with errors' }}
          </h2>
          <p class="text-xs text-ink">
            <strong>{{ executionResult.imported_rows }}</strong> record(s) were created,
            <strong>{{ executionResult.failed_rows }}</strong> failed.
          </p>
          <p v-if="executionResult.failure_reason" class="text-xs text-ink">
            {{ executionResult.failure_reason }}
          </p>
          <div class="flex justify-end">
            <BaseButton
              v-if="executionResult.failed_rows > 0"
              variant="outline"
              @click="downloadErrorReport(executionResult)"
            >
              <Download class="w-3.5 h-3.5 mr-1" />
              Download the error report
            </BaseButton>
          </div>
        </div>

        <!-- Import history -->
        <div class="bg-surface border border-hairline rounded-lg overflow-hidden">
          <div class="p-4 border-b border-hairline flex items-center justify-between">
            <h2 class="text-sm font-semibold text-ink-secondary">Import runs</h2>
            <span class="text-xs text-ink-muted">{{ importHistory.length }} operation(s)</span>
          </div>

          <EmptyState
            v-if="importHistory.length === 0"
            title="No import has been run"
            description="An inspected dataset appears here with its validation outcome."
          />

          <div class="overflow-x-auto">
            <table class="w-full text-xs text-left min-w-[560px]">
            <thead class="bg-canvas-soft border-b border-hairline text-ink-muted font-semibold">
              <tr>
                <th class="p-3.5">File</th>
                <th class="p-3.5">Dataset</th>
                <th class="p-3.5">Records</th>
                <th class="p-3.5">Status</th>
                <th class="p-3.5 text-right">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-hairline text-ink-secondary">
              <tr v-for="operation in importHistory" :key="operation.id" class="hover:bg-surface-hover transition-colors">
                <td class="p-3.5 font-medium flex items-center gap-2">
                  <FileSpreadsheet class="w-4 h-4 text-primary" />
                  {{ operation.original_filename }}
                </td>
                <td class="p-3.5 font-mono text-xs">{{ operation.dataset }}</td>
                <td class="p-3.5">
                  <span class="text-ink">{{ operation.imported_rows }} imported</span>
                  <span v-if="operation.failed_rows > 0" class="text-[#e03e3e] ml-1.5">
                    ({{ operation.failed_rows }} failed)
                  </span>
                  <span v-if="operation.total_rows && !operation.imported_rows && operation.status === 'validated'" class="text-ink-muted ml-1.5">
                    ({{ operation.valid_rows }} ready)
                  </span>
                </td>
                <td class="p-3.5">
                  <span
                    class="px-2 py-0.5 rounded text-xs font-semibold border"
                    :class="statusClass(operation.status)"
                  >
                    {{ formatStatus(operation.status) }}
                  </span>
                </td>
                <td class="p-3.5 text-right">
                  <button
                    v-if="operation.executable"
                    type="button"
                    class="px-2.5 py-1 min-h-[32px] inline-flex items-center text-xs font-medium text-primary hover:underline cursor-pointer"
                    @click="executeImportFor(operation)"
                  >
                    Execute
                  </button>
                  <button
                    v-else-if="operation.status === 'validated'"
                    type="button"
                    class="px-2.5 py-1 min-h-[32px] inline-flex items-center text-xs font-medium text-[#e03e3e] hover:underline cursor-pointer"
                    @click="pendingDiscard = operation"
                  >
                    Discard
                  </button>
                  <button
                    v-else-if="operation.failed_rows > 0"
                    type="button"
                    class="px-2.5 py-1 min-h-[32px] inline-flex items-center text-xs font-medium text-primary hover:underline cursor-pointer"
                    @click="downloadErrorReport(operation)"
                  >
                    Error report
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
          </div>
        </div>
      </div>

      <!-- ============================ EXPORT ============================ -->
      <div v-else class="space-y-6">
        <div class="p-5 rounded-xl bg-surface border border-hairline space-y-4">
          <h2 class="text-sm font-semibold text-ink-secondary flex items-center gap-2">
            <Download class="w-4 h-4 text-primary" />
            Generate an export
          </h2>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-ink-secondary" for="export-dataset">
                Dataset
              </label>
              <BaseSelect
                id="export-dataset"
                :model-value="exportDataset"
                :options="exportOptions"
                :disabled="isGenerating"
                @update:model-value="(value: string) => { exportDataset = value; exportFilters = {} }"
              />
              <p v-if="selectedExportDataset" class="text-xs text-ink-muted">
                {{ selectedExportDataset.description }}
              </p>
            </div>

            <div
              v-for="filter in exportFilterFields"
              :key="filter"
              class="space-y-1.5"
            >
              <label class="block text-xs font-semibold text-ink-secondary" :for="`export-filter-${filter}`">
                {{ filter.replace(/_/g, ' ') }}
              </label>
              <BaseInput
                :id="`export-filter-${filter}`"
                :model-value="exportFilters[filter] ?? ''"
                :disabled="isGenerating"
                placeholder="All"
                @update:model-value="(value: string) => { exportFilters = { ...exportFilters, [filter]: value } }"
              />
            </div>
          </div>

          <div v-if="exportError" class="p-3 rounded-lg bg-[#fdecec] dark:bg-[#3a1414] border border-[#e03e3e]/20 flex items-start gap-2" role="alert">
            <AlertCircle class="w-4 h-4 shrink-0 mt-0.5 text-[#e03e3e] dark:text-[#f87171]" />
            <p class="text-xs text-ink leading-relaxed">{{ exportError }}</p>
          </div>

          <div class="flex justify-end">
            <BaseButton variant="primary" :loading="isGenerating" :disabled="!exportDataset" @click="generateExport">
              <ArrowUpDown class="w-4 h-4 mr-1.5" />
              Generate export
            </BaseButton>
          </div>
        </div>

        <!-- Export history -->
        <div class="bg-surface border border-hairline rounded-lg overflow-hidden">
          <div class="p-4 border-b border-hairline flex items-center justify-between">
            <h2 class="text-sm font-semibold text-ink-secondary">Export runs</h2>
            <span class="text-xs text-ink-muted">{{ exportHistory.length }} operation(s)</span>
          </div>

          <EmptyState
            v-if="exportHistory.length === 0"
            title="No export has been generated"
            description="A generated file appears here with its record count and expiry."
          />

          <div class="overflow-x-auto">
            <table class="w-full text-xs text-left min-w-[560px]">
            <thead class="bg-canvas-soft border-b border-hairline text-ink-muted font-semibold">
              <tr>
                <th class="p-3.5">Dataset</th>
                <th class="p-3.5">Records</th>
                <th class="p-3.5">Size</th>
                <th class="p-3.5">Expires</th>
                <th class="p-3.5 text-right">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-hairline text-ink-secondary">
              <tr v-for="operation in exportHistory" :key="operation.id" class="hover:bg-surface-hover transition-colors">
                <td class="p-3.5 font-medium font-mono text-xs">{{ operation.dataset }}</td>
                <td class="p-3.5">{{ operation.record_count }} rows</td>
                <td class="p-3.5 font-mono text-xs">{{ formatBytes(operation.file_size) }}</td>
                <td class="p-3.5 text-ink-muted">
                  {{ operation.expires_at ? new Date(operation.expires_at).toLocaleString() : '—' }}
                </td>
                <td class="p-3.5 text-right">
                  <button
                    v-if="operation.retrievable"
                    type="button"
                    class="px-2.5 py-1 min-h-[32px] text-xs font-medium text-primary hover:underline inline-flex items-center gap-1 cursor-pointer"
                    @click="downloadExport(operation)"
                  >
                    <Download class="w-3.5 h-3.5" />
                    Download
                  </button>
                  <span v-else class="text-xs text-ink-faint italic">Expired</span>
                </td>
              </tr>
            </tbody>
          </table>
          </div>
        </div>
      </div>
    </template>

    <!-- Discard confirmation -->
    <ModalDialog
      :open="pendingDiscard !== null"
      title="Discard this import operation"
      @close="pendingDiscard = null"
    >
      <div class="space-y-3">
        <p class="text-xs text-ink leading-relaxed">
          The uploaded file
          <strong>{{ pendingDiscard?.original_filename }}</strong> will be removed and the
          operation can no longer be executed. No record has been created.
        </p>
        <div class="flex justify-end gap-2 pt-1">
          <BaseButton variant="outline" @click="pendingDiscard = null">Keep it</BaseButton>
          <BaseButton variant="danger" @click="discardImport">
            <X class="w-3.5 h-3.5 mr-1" />
            Discard
          </BaseButton>
        </div>
      </div>
    </ModalDialog>
  </div>
</template>


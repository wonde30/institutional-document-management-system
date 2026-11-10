<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { documentsApi, archiveApi, ocrApi, institutionalApi } from '@/api/client'
import type { Document, ArchiveCollection, RetentionPolicy, TimelineEvent, PhysicalOriginal } from '@/types'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import ModalDialog from '@/components/ModalDialog.vue'
import ClassificationBadge from '@/components/ClassificationBadge.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import LoadingSkeleton from '@/components/ui/LoadingSkeleton.vue'
import { formatDate, formatFileSize, downloadBlob } from '@/utils/formatters'
import {
  Download,
  Upload,
  ArrowLeft,
  FileText,
  Clock,
  Shield,
  Building2,
  User,
  History,
  Archive,
  CheckCircle2,
  Lock,
  Mail,
  ShieldCheck,
  Send,
  CheckCheck,
  AlertCircle,
  ScanLine,
  Workflow,
  FileCheck,
  Key,
  RefreshCw,
  Edit3,
} from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const toast = useToast()
const docId = Number(route.params.id)

const document = ref<Document | null>(null)
const isLoading = ref(true)
const errorMessage = ref<string | null>(null)
const errorCode = ref<number | null>(null)
const downloadingFileId = ref<number | null>(null)

// Version upload modal state
const showVersionModal = ref(false)
const versionFile = ref<File | null>(null)
const versionFileName = ref('')
const changeNote = ref('')
const isUploadingVersion = ref(false)

// Archive transfer modal state
const showArchiveModal = ref(false)
const collections = ref<ArchiveCollection[]>([])
const policies = ref<RetentionPolicy[]>([])
const selectedCollectionId = ref<number | ''>('')
const selectedPolicyVersionId = ref<number | ''>('')
const isArchiving = ref(false)

async function loadDocument() {
 isLoading.value = true
 errorMessage.value = null
 errorCode.value = null
 try {
  const res = await documentsApi.get(docId)
  document.value = res.data.data
  if (document.value?.physical_originals && document.value.physical_originals.length > 0) {
    selectedPhysicalOriginalId.value = document.value.physical_originals[0].id
  }
 } catch (err: any) {
 errorCode.value = err.response?.status || 500
 errorMessage.value = err.response?.data?.message || 'Failed to retrieve document details.'
 } finally {
 isLoading.value = false
 }
}

async function handleDownloadFile(fileId: number, originalName: string) {
 downloadingFileId.value = fileId
 try {
 const res = await documentsApi.downloadFile(fileId)
 downloadBlob(res.data, originalName)
 toast.success(`Download started for ${originalName}`)
 } catch (err: any) {
 if (err.response?.status === 403) {
 toast.error('Access Forbidden (HTTP 403): You lack required security clearance or download permissions.')
 } else if (err.response?.status === 404) {
 toast.error('File not found (HTTP 404): The physical file is missing or not ready.')
 } else {
 toast.error(err.response?.data?.message || 'File download failed.')
 }
 } finally {
 downloadingFileId.value = null
 }
}

function handleVersionFileChange(event: Event) {
 const target = event.target as HTMLInputElement
 if (target.files && target.files[0]) {
 versionFile.value = target.files[0]
 versionFileName.value = versionFile.value.name
 }
}

async function submitNewVersion() {
 if (!versionFile.value) {
 toast.error('Please choose a file to upload for this new version.')
 return
 }
 if (!changeNote.value.trim()) {
 toast.error('Please enter a change note.')
 return
 }

 isUploadingVersion.value = true
 try {
 const formData = new FormData()
 formData.append('file', versionFile.value)
 formData.append('change_note', changeNote.value.trim())
 formData.append('purpose', 'primary')

 await documentsApi.uploadVersion(docId, formData)
 toast.success('New document version committed successfully.')
 showVersionModal.value = false
 versionFile.value = null
 versionFileName.value = ''
 changeNote.value = ''
 await loadDocument()
 } catch (err: any) {
 if (err.response?.status === 409) {
 toast.error('Concurrency Collision (HTTP 409): Document has been revised by another user. Reloading latest document data...')
 await loadDocument()
 } else if (err.response?.status === 422) {
 const errs = err.response.data?.errors
 const msg = errs ? Object.values(errs).flat().join(' ') : (err.response.data?.message || 'Validation error.')
 toast.error(`Validation Failed (HTTP 422): ${msg}`)
 } else {
 toast.error(err.response?.data?.message || 'Failed to commit new version.')
 }
 } finally {
 isUploadingVersion.value = false
 }
}

async function openArchiveModal() {
 try {
 const [cRes, pRes] = await Promise.all([
 archiveApi.collections(),
 archiveApi.policies(),
 ])
 collections.value = cRes.data.data || []
 policies.value = pRes.data.data || []
 if (collections.value.length > 0) {
 selectedCollectionId.value = collections.value[0].id
 }
 if (policies.value.length > 0) {
 selectedPolicyVersionId.value = policies.value[0].id
 }
 showArchiveModal.value = true
 } catch (err) {
 toast.error('Failed to load archival policies.')
 }
}

async function submitArchiveTransfer() {
 if (!selectedCollectionId.value || !selectedPolicyVersionId.value) {
 toast.error('Please select both collection and retention policy.')
 return
 }

 isArchiving.value = true
 try {
 await archiveApi.transfer({
 document_id: docId,
 collection_id: Number(selectedCollectionId.value),
 retention_policy_version_id: Number(selectedPolicyVersionId.value),
 next_review_at: new Date(Date.now() + 10 * 365 * 24 * 3600 * 1000).toISOString().split('T')[0],
 })
 toast.success('Document successfully transferred to institutional archive.')
 showArchiveModal.value = false
 await loadDocument()
 } catch (err: any) {
 toast.error(err.response?.data?.message || 'Archival transfer failed.')
 } finally {
 isArchiving.value = false
 }
}

// Timeline state
const timelineEvents = ref<TimelineEvent[]>([])
const isLoadingTimeline = ref(false)

async function loadTimeline() {
  if (typeof documentsApi.timeline !== 'function') return
  isLoadingTimeline.value = true
  try {
    const res = await documentsApi.timeline(docId)
    timelineEvents.value = res.data.data || []
  } catch (err) {
    console.warn('Failed to load document timeline:', err)
  } finally {
    isLoadingTimeline.value = false
  }
}

// Digitize paper original modal state (Scenario B)
const showDigitizeModal = ref(false)
const digitizeFile = ref<File | null>(null)
const digitizeFileName = ref('')
const selectedPhysicalOriginalId = ref<number | ''>('')
const digitizeVerificationNote = ref('')
const isDigitizing = ref(false)

function handleDigitizeFileChange(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    digitizeFile.value = target.files[0]
    digitizeFileName.value = digitizeFile.value.name
  }
}

async function submitDigitize() {
  if (!digitizeFile.value) {
    toast.error('Please select the scanned document file.')
    return
  }

  isDigitizing.value = true
  try {
    const formData = new FormData()
    formData.append('file', digitizeFile.value)
    if (selectedPhysicalOriginalId.value) {
      formData.append('physical_original_id', String(selectedPhysicalOriginalId.value))
    }
    if (digitizeVerificationNote.value.trim()) {
      formData.append('verification_note', digitizeVerificationNote.value.trim())
    }

    await documentsApi.digitize(docId, formData)
    toast.success('Paper original digitized successfully. Version 2 generated.')
    showDigitizeModal.value = false
    digitizeFile.value = null
    digitizeFileName.value = ''
    digitizeVerificationNote.value = ''
    await Promise.all([loadDocument(), loadTimeline()])
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to digitize paper original.')
  } finally {
    isDigitizing.value = false
  }
}

function getEventBadgeColor(type: string): string {
  switch (type) {
    case 'document_registered':
      return 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300 border-blue-200 dark:border-blue-800'
    case 'version_created':
      return 'bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300 border-purple-200 dark:border-purple-800'
    case 'physical_custody':
      return 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border-amber-200 dark:border-amber-800'
    case 'movement_dispatched':
    case 'movement_acknowledged':
      return 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800'
    case 'directive_assigned':
    case 'assignment_event':
    case 'assignment_response_linked':
      return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
    case 'workflow_transition':
      return 'bg-teal-50 text-teal-700 dark:bg-teal-950/40 dark:text-teal-300 border-teal-200 dark:border-teal-800'
    default:
      return 'bg-slate-50 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700'
  }
}

// OCR Subsystem State
const ocrExtractions = ref<any[]>([])
const isLoadingOcr = ref(false)
const isProcessingOcr = ref(false)
const showOcrVerifyModal = ref(false)
const selectedOcrExtraction = ref<any | null>(null)
const ocrCorrectedText = ref('')
const ocrVerificationNotes = ref('')
const isVerifyingOcr = ref(false)

async function loadOcrExtractions() {
  isLoadingOcr.value = true
  try {
    const res = await ocrApi.extractions(docId)
    ocrExtractions.value = (res.data as any).data || []
  } catch (err) {
    console.warn('Failed to load OCR extractions:', err)
  } finally {
    isLoadingOcr.value = false
  }
}

async function handleProcessOcr(fileObjectId?: number) {
  isProcessingOcr.value = true
  try {
    await ocrApi.process({
      document_id: docId,
      file_object_id: fileObjectId,
      languages: 'eng+amh',
      queue_mode: 'sync',
    })
    toast.success('OCR document extraction completed successfully.')
    await loadOcrExtractions()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to process OCR extraction.')
    await loadOcrExtractions()
  } finally {
    isProcessingOcr.value = false
  }
}

async function handleRetryOcr(extractionId: number) {
  isProcessingOcr.value = true
  try {
    await ocrApi.retry(extractionId)
    toast.success('OCR extraction retry initiated.')
    await loadOcrExtractions()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to retry OCR.')
  } finally {
    isProcessingOcr.value = false
  }
}

function openOcrVerify(extraction: any) {
  selectedOcrExtraction.value = extraction
  ocrCorrectedText.value = extraction.raw_text || extraction.searchable_text || ''
  ocrVerificationNotes.value = ''
  showOcrVerifyModal.value = true
}

async function submitOcrVerify() {
  if (!selectedOcrExtraction.value) return
  if (!ocrCorrectedText.value.trim()) {
    toast.error('Extracted text cannot be blank.')
    return
  }
  isVerifyingOcr.value = true
  try {
    await ocrApi.verify(selectedOcrExtraction.value.id, {
      corrected_text: ocrCorrectedText.value,
      notes: ocrVerificationNotes.value.trim() || undefined,
    })
    toast.success('OCR text verified and marked clean.')
    showOcrVerifyModal.value = false
    await loadOcrExtractions()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to verify OCR text.')
  } finally {
    isVerifyingOcr.value = false
  }
}

// Digital Signatures State
const signatures = ref<any[]>([])
const isLoadingSignatures = ref(false)
const isVerifyingSig = ref<number | null>(null)
const sigResults = ref<Record<number, any>>({})

async function loadSignatures() {
  isLoadingSignatures.value = true
  try {
    const res = await institutionalApi.signatures()
    const allSigs = (res.data as any)?.data?.signatures || []
    signatures.value = allSigs.filter((s: any) => s.document_id === docId || s.document_version?.document_id === docId)
  } catch (err) {
    console.warn('Failed to load signatures:', err)
  } finally {
    isLoadingSignatures.value = false
  }
}

async function handleVerifySig(sigId: number) {
  isVerifyingSig.value = sigId
  try {
    const res = await institutionalApi.verifySignature(sigId)
    sigResults.value[sigId] = (res.data as any).data
    toast.success('Cryptographic X.509 signature verified.')
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Cryptographic verification check failed.')
  } finally {
    isVerifyingSig.value = null
  }
}

onMounted(() => {
  loadDocument()
  loadTimeline()
  loadOcrExtractions()
  loadSignatures()
})
</script>

<template>
 <div class="space-y-6 max-w-5xl mx-auto">
 <!-- Header -->
 <div class="flex items-center justify-between border-b border-slate-200 pb-5 dark:border-slate-800">
 <div class="flex items-center gap-3">
 <BaseButton variant="ghost" size="sm" @click="router.push('/documents')">
 <ArrowLeft class="w-4 h-4" />
 </BaseButton>
 <div>
 <div class="notion-eyebrow text-primary mb-1">
 RECORDS & ARCHIVES / DOCUMENT DETAIL
 </div>
 <div class="flex items-center gap-2">
 <span class="font-mono text-xs font-semibold text-primary bg-primary-subtle px-2.5 py-0.5 rounded-full border border-primary/20">
 {{ document?.reference_number || 'Loading...' }}
 </span>
 <StatusBadge v-if="document" :status="document.lifecycle" />
 </div>
 <h1 class="notion-heading-2 text-ink mt-1">
 {{ document?.title || 'Document Record' }}
 </h1>
 </div>
 </div>

 <div class="flex items-center gap-2">
 <BaseButton
 v-if="document && document.lifecycle === 'active' && authStore.hasPermission('documents.version')"
 variant="outline"
 size="sm"
 @click="showDigitizeModal = true"
 >
 <ScanLine class="w-4 h-4" />
 <span>Digitize Paper Original</span>
 </BaseButton>

 <BaseButton
 v-if="document && document.lifecycle === 'active' && authStore.hasPermission('documents.version')"
 variant="outline"
 size="sm"
 @click="showVersionModal = true"
 >
 <Upload class="w-4 h-4" />
 <span>Upload Revision</span>
 </BaseButton>

 <BaseButton
 v-if="document && document.lifecycle === 'active' && authStore.hasPermission('archive.transfer')"
 variant="secondary"
 size="sm"
 class="bg-primary hover:bg-primary-hover text-white shadow-xs"
 @click="openArchiveModal"
 >
 <Archive class="w-4 h-4" />
 <span>Transfer to Digital Archive</span>
 </BaseButton>
 </div>
 </div>

 <!-- Loading Skeleton -->
 <div v-if="isLoading" class="space-y-6">
 <LoadingSkeleton class="h-44 rounded-2xl" />
 <LoadingSkeleton class="h-64 rounded-2xl" />
 </div>

 <!-- Error State -->
 <div v-else-if="errorMessage" class="p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-center space-y-4 max-w-md mx-auto shadow-sm">
 <div v-if="errorCode === 403" class="w-12 h-12 rounded-full bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center mx-auto">
 <Lock class="w-6 h-6" />
 </div>
 <div v-else-if="errorCode === 404" class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 flex items-center justify-center mx-auto">
 <FileText class="w-6 h-6" />
 </div>
 <div v-else class="w-12 h-12 rounded-full bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center mx-auto">
 <AlertCircle class="w-6 h-6" />
 </div>

 <div class="space-y-1">
 <h3 class="font-bold text-slate-900 dark:text-white text-base">
 {{ errorCode === 403 ? 'Access Restricted' : (errorCode === 404 ? 'Document Not Found' : 'Unable to Load Document') }}
 </h3>
 <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">{{ errorMessage }}</p>
 </div>

 <div class="pt-2 flex justify-center gap-3">
 <BaseButton size="sm" variant="outline" @click="router.push('/documents')">Back to Repository</BaseButton>
 <BaseButton v-if="errorCode !== 403 && errorCode !== 404" size="sm" variant="primary" @click="loadDocument">Retry</BaseButton>
 </div>
 </div>

 <!-- Document Content Grid -->
 <template v-else-if="document">
 <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
 <!-- Main Details (Left 2 cols) -->
 <div class="lg:col-span-2 space-y-6">
 <!-- Metadata Summary -->
 <div class="bg-surface border border-hairline rounded-xl p-6 shadow-xs space-y-4">
 <h2 class="text-xs font-semibold uppercase tracking-wider text-ink-muted border-b border-hairline-subtle pb-2">
 Descriptive Summary
 </h2>
 <p class="text-sm text-ink-secondary leading-relaxed">
 {{ document.summary || 'No descriptive summary provided at registration.' }}
 </p>

 <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 pt-3 border-t border-hairline-subtle text-xs">
 <div>
 <span class="text-ink-muted block font-medium">Category</span>
 <span class="font-bold text-ink">{{ document.category?.name }}</span>
 </div>
 <div>
 <span class="text-ink-muted block font-medium">Classification</span>
 <div class="mt-0.5">
 <ClassificationBadge :classification="document.confidentiality" />
 </div>
 </div>
 <div>
 <span class="text-ink-muted block font-medium">Document Date</span>
 <span class="font-bold text-ink">{{ formatDate(document.document_date) }}</span>
 </div>
 </div>
 </div>

 <!-- Document Versions History -->
 <div class="bg-surface border border-hairline rounded-xl p-6 shadow-xs space-y-4">
 <div class="flex items-center justify-between border-b border-hairline-subtle pb-3">
 <h2 class="text-xs font-semibold uppercase tracking-wider text-ink-muted flex items-center gap-2">
 <History class="w-4 h-4 text-primary" />
 <span>Version History ({{ document.versions?.length || 0 }})</span>
 </h2>
 <span class="text-xs text-ink-faint font-medium">
 Optimistic Concurrency Lock: v{{ document.lock_version }}
 </span>
 </div>

 <div v-if="document.versions && document.versions.length > 0" class="divide-y divide-hairline-subtle">
 <div
 v-for="ver in document.versions"
 :key="ver.id"
 class="py-4 space-y-3"
 >
 <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
 <div class="space-y-1">
 <div class="flex items-center gap-2">
 <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-primary-subtle text-primary">
 v{{ ver.version_number }}.0
 </span>
 <span class="text-xs font-medium text-ink">
 {{ ver.change_note || 'Initial version' }}
 </span>
 </div>
 <div class="text-[11px] text-ink-faint flex items-center gap-2">
 <span>Committed: {{ formatDate(ver.created_at) }}</span>
 <span>&bull;</span>
 <span>By: {{ ver.creator?.name || `Staff #${ver.created_by}` }}</span>
 </div>
 </div>
 </div>

 <!-- Attached Version Files with Size, Checksum and Download -->
 <div v-if="ver.version_files && ver.version_files.length > 0" class="space-y-2 pt-1">
 <div
 v-for="vf in ver.version_files"
 :key="vf.file_object_id"
 class="flex items-center justify-between p-3 rounded-xl bg-surface-hover border border-hairline text-xs"
 >
 <div class="flex items-center gap-3 truncate max-w-[70%]">
 <FileText class="w-4 h-4 text-primary shrink-0" />
 <div class="truncate">
 <span class="font-medium text-ink truncate block">
 {{ vf.file_object?.original_filename || `document_v${ver.version_number}.pdf` }}
 </span>
 <div class="text-[10px] text-ink-faint flex items-center gap-2 mt-0.5 font-mono">
 <span>{{ formatFileSize(vf.file_object?.byte_size) }}</span>
 <span v-if="vf.file_object?.sha256" class="truncate" :title="vf.file_object.sha256">
 &bull; SHA-256: {{ vf.file_object.sha256.substring(0, 12) }}...
 </span>
 </div>
 </div>
 </div>

 <BaseButton
 size="sm"
 variant="outline"
 :loading="downloadingFileId === vf.file_object_id"
 @click="handleDownloadFile(vf.file_object_id, vf.file_object?.original_filename || `document_v${ver.version_number}.pdf`)"
 >
 <Download class="w-3.5 h-3.5 mr-1" />
 <span>Download</span>
 </BaseButton>
 </div>
 </div>
 <div v-else class="text-xs text-slate-400 italic bg-slate-50 dark:bg-slate-800/30 p-2.5 rounded-xl">
 No binary file attached to this revision.
 </div>
 </div>
 </div>
 <div v-else class="text-xs text-slate-400 italic py-6 text-center">
 No digital versions committed to this document repository record.
 </div>
 </div>

      <!-- OCR Document Text Extraction Panel -->
      <div class="bg-surface border border-hairline rounded-xl p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-hairline-subtle pb-3">
          <div class="flex items-center gap-2">
            <ScanLine class="w-4 h-4 text-primary" />
            <h2 class="text-xs font-semibold uppercase tracking-wider text-ink-muted">
              OCR Document Extraction (Amharic & English)
            </h2>
          </div>
          <BaseButton
            size="sm"
            variant="outline"
            :loading="isProcessingOcr"
            @click="handleProcessOcr()"
          >
            <RefreshCw class="w-3.5 h-3.5 mr-1" />
            <span>Process / Retest OCR</span>
          </BaseButton>
        </div>

        <div v-if="isLoadingOcr" class="py-4 text-xs text-ink-muted">
          Loading document text extraction records...
        </div>

        <div v-else-if="ocrExtractions.length > 0" class="space-y-4">
          <div
            v-for="ext in ocrExtractions"
            :key="ext.id"
            class="p-4 rounded-xl border border-hairline bg-surface-hover space-y-3"
          >
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
              <div class="flex items-center gap-2 flex-wrap">
                <span
                  class="px-2 py-0.5 rounded text-[11px] font-semibold uppercase"
                  :class="{
                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200': ext.processing_status === 'completed',
                    'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 animate-pulse border border-blue-200': ext.processing_status === 'processing',
                    'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200': ext.processing_status === 'pending',
                    'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200': ext.processing_status === 'failed',
                  }"
                >
                  OCR {{ ext.processing_status || 'completed' }}
                </span>
                <span class="text-xs text-ink-muted font-mono">
                  Engine: {{ ext.engine_name || 'Tesseract 5.x' }} · Lang: {{ ext.languages || 'eng+amh' }}
                </span>
                <span
                  v-if="ext.human_verified"
                  class="px-2 py-0.5 rounded text-[10px] font-medium bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-300"
                >
                  ✓ Verified by Staff
                </span>
              </div>

              <div class="flex items-center gap-2">
                <BaseButton
                  v-if="ext.processing_status === 'failed'"
                  size="sm"
                  variant="outline"
                  class="text-xs h-7 text-rose-600 border-rose-300"
                  :loading="isProcessingOcr"
                  @click="handleRetryOcr(ext.id)"
                >
                  <RefreshCw class="w-3 h-3 mr-1" /> Retry
                </BaseButton>

                <BaseButton
                  size="sm"
                  variant="outline"
                  class="text-xs h-7"
                  @click="openOcrVerify(ext)"
                >
                  <Edit3 class="w-3 h-3 mr-1" /> Human Verification
                </BaseButton>
              </div>
            </div>

            <!-- Error details if failed -->
            <div v-if="ext.error_details" class="p-2.5 rounded-lg bg-rose-50 dark:bg-rose-950/30 text-rose-700 dark:text-rose-300 text-xs">
              <strong>Error Details:</strong> {{ ext.error_details }}
            </div>

            <!-- Extracted Text Preview -->
            <div class="p-3 rounded-lg bg-surface border border-hairline font-mono text-xs whitespace-pre-wrap max-h-48 overflow-y-auto text-ink-secondary">
              {{ ext.searchable_text || ext.raw_text || 'No text extracted.' }}
            </div>
          </div>
        </div>

        <div v-else class="text-xs text-ink-muted italic py-4 text-center bg-surface-muted rounded-xl">
          No OCR extraction has been performed on this document yet. Click "Process / Retest OCR" above to extract Amharic and English text.
        </div>
      </div>

      <!-- Digital Signatures & Trust Tier Panel -->
      <div v-if="signatures.length > 0" class="bg-surface border border-hairline rounded-xl p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-hairline-subtle pb-3">
          <div class="flex items-center gap-2">
            <Key class="w-4 h-4 text-emerald-600" />
            <h2 class="text-xs font-semibold uppercase tracking-wider text-ink-muted">
              Cryptographic Digital Signatures & Trust Tiers
            </h2>
          </div>
          <span class="text-xs text-ink-faint font-mono">
            Standard: PAdES-B-LT / X.509 PKI
          </span>
        </div>

        <div class="divide-y divide-hairline-subtle">
          <div
            v-for="sig in signatures"
            :key="sig.id"
            class="py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
          >
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <span class="font-medium text-xs text-ink">
                  {{ sig.signer?.name || 'Authorized Institutional Signer' }}
                </span>
                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200">
                  {{ sig.verification_status || 'Verified' }}
                </span>
                <span class="px-2 py-0.5 rounded text-[10px] font-mono text-ink-muted bg-surface-muted">
                  {{ sig.trust_tier || 'Development Certificate' }}
                </span>
              </div>
              <div class="text-[11px] text-ink-muted">
                Signed: {{ formatDate(sig.signed_at || sig.created_at) }} · Format: {{ sig.signature_format || 'PAdES-B-LT' }}
              </div>
            </div>

            <div class="flex items-center gap-2">
              <span v-if="sigResults[sig.id]" class="text-xs font-medium text-emerald-600 flex items-center gap-1">
                <CheckCircle2 class="w-3.5 h-3.5" /> {{ sigResults[sig.id].outcome || 'Valid' }}
              </span>
              <BaseButton
                size="sm"
                variant="outline"
                class="text-xs h-7"
                :loading="isVerifyingSig === sig.id"
                @click="handleVerifySig(sig.id)"
              >
                <ShieldCheck class="w-3.5 h-3.5 mr-1" />
                <span>Verify PKI</span>
              </BaseButton>
            </div>
          </div>
        </div>
      </div>

 <!-- Institutional Movements & Chain-of-Custody (if correspondence or direct movements exist) -->
 <div
 v-if="(document.correspondence?.movements && document.correspondence.movements.length > 0) || (document.movements && document.movements.length > 0)"
 class="bg-surface border border-hairline rounded-xl p-6 shadow-xs space-y-4"
 >
 <div class="flex items-center justify-between border-b border-hairline-subtle pb-3">
 <h2 class="text-xs font-semibold uppercase tracking-wider text-ink-muted flex items-center gap-2">
 <Send class="w-4 h-4 text-primary" />
 <span>Institutional Chain-of-Custody & Movement History</span>
 </h2>
 <router-link
 v-if="document.correspondence"
 to="/correspondence"
 class="text-xs font-semibold text-primary hover:underline flex items-center gap-1"
 >
 <span>View in Registry</span>
 </router-link>
 </div>

 <div class="space-y-3">
 <div
 v-for="m in (document.correspondence?.movements || document.movements || [])"
 :key="m.id"
 class="p-3.5 rounded-xl bg-surface-hover border border-hairline space-y-2 text-xs"
 >
 <div class="flex items-center justify-between">
 <div class="flex items-center gap-2 font-medium">
 <span class="text-ink-muted">{{ m.from_unit?.name || 'Central Registry' }}</span>
 <span class="text-ink-faint">&rarr;</span>
 <span class="font-bold text-ink">{{ m.to_unit?.name }}</span>
 </div>

 <span
 v-if="m.receipt"
 class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-[#e8f7ec] text-[#1aae39] dark:bg-[#14301d] dark:text-[#34d399] border border-[#1aae39]/20 flex items-center gap-1"
 >
 <CheckCheck class="w-3 h-3" />
 <span>Acknowledged on {{ formatDate(m.receipt.acknowledged_at) }}</span>
 </span>
 <span
 v-else
 class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-[#fff4e6] text-[#dd5b00] dark:bg-[#3b220c] dark:text-[#f59e0b] border border-[#dd5b00]/20 flex items-center gap-1"
 >
 <Clock class="w-3 h-3" />
 <span>Pending Office Receipt</span>
 </span>
 </div>

 <p v-if="m.instruction" class="text-ink-secondary italic text-[11px]">
 &ldquo;{{ m.instruction }}&rdquo;
 </p>

 <div class="text-[10px] text-ink-faint flex items-center gap-2 pt-1 border-t border-hairline-subtle">
 <span>Dispatched: {{ formatDate(m.sent_at) }}</span>
 <span v-if="m.sent_by_user">&bull; Sent by: {{ m.sent_by_user.name }}</span>
 <span v-if="m.due_at">&bull; Due: {{ formatDate(m.due_at) }}</span>
 </div>
 </div>
 </div>
 </div>

 <!-- Operational Directives & Collaboration -->
 <div
 v-if="document.assignments && document.assignments.length > 0"
 class="bg-surface border border-hairline rounded-xl p-6 shadow-xs space-y-4"
 >
 <div class="flex items-center justify-between border-b border-hairline-subtle pb-3">
 <h2 class="text-xs font-semibold uppercase tracking-wider text-ink-muted flex items-center gap-2">
 <User class="w-4 h-4 text-primary" />
 <span>Operational Directives & Assignments ({{ document.assignments.length }})</span>
 </h2>
 </div>

 <div class="space-y-3">
 <div
 v-for="a in document.assignments"
 :key="a.id"
 class="p-3.5 rounded-xl bg-surface-hover border border-hairline space-y-2 text-xs"
 >
 <div class="flex items-center justify-between">
 <div class="flex items-center gap-2 font-medium">
 <span class="text-ink-muted">Assigned to:</span>
 <span class="font-bold text-ink">
 {{ a.assigned_user?.name || a.assigned_unit?.name || `Staff #${a.assigned_user_id}` }}
 </span>
 </div>
 <span
 v-if="a.due_at"
 class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-primary-subtle text-primary border border-primary/20"
 >
 Due: {{ formatDate(a.due_at) }}
 </span>
 </div>

 <p class="text-ink-secondary leading-relaxed text-xs">
 {{ a.instruction || (a as any).instructions }}
 </p>

 <div class="text-[10px] text-ink-faint flex items-center gap-2 pt-1 border-t border-hairline-subtle">
 <span>Assigned on: {{ formatDate(a.assigned_at) }}</span>
 <span v-if="a.assigned_by_user">&bull; By: {{ a.assigned_by_user.name }}</span>
 </div>
 </div>
 </div>
 </div>

 <!-- Physical Originals & Archival Preservation (Scenario B) -->
 <div
 v-if="document.physical_originals && document.physical_originals.length > 0"
 class="bg-surface border border-hairline rounded-xl p-6 shadow-xs space-y-4"
 >
 <div class="flex items-center justify-between border-b border-hairline-subtle pb-3">
 <h2 class="text-xs font-semibold uppercase tracking-wider text-ink-muted flex items-center gap-2">
 <ShieldCheck class="w-4 h-4 text-primary" />
 <span>Physical Originals & Archival Storage ({{ document.physical_originals.length }})</span>
 </h2>
 <span class="text-xs text-ink-faint font-mono">
 Institutional Physical Tracked
 </span>
 </div>

 <div class="space-y-4">
 <div
 v-for="orig in document.physical_originals"
 :key="orig.id"
 class="p-4 rounded-xl bg-surface-hover border border-hairline space-y-3 text-xs"
 >
 <div class="flex items-center justify-between flex-wrap gap-2">
 <div class="flex items-center gap-2">
 <span class="font-mono text-xs font-bold text-primary bg-primary-subtle px-2 py-0.5 rounded-lg border border-primary/20">
 {{ orig.physical_reference || `ORIG-${orig.id}` }}
 </span>
 <span class="text-xs text-ink font-medium">
 Retention: {{ orig.retention_requirement.toUpperCase() }}
 </span>
 </div>
 <span class="text-[11px] text-ink-faint">
 Registered: {{ formatDate(orig.created_at) }}
 </span>
 </div>

 <div v-if="orig.custody_events && orig.custody_events.length > 0" class="pt-2 border-t border-hairline-subtle space-y-2">
 <div class="text-[11px] font-semibold text-ink-muted uppercase tracking-wider">
 Custody Chain Log
 </div>
 <div
 v-for="ce in orig.custody_events"
 :key="ce.id"
 class="flex items-center justify-between text-[11px] bg-surface p-2 rounded-lg border border-hairline"
 >
 <div class="flex items-center gap-2">
 <span class="font-semibold text-ink capitalize">{{ ce.action }}</span>
 <span v-if="ce.location" class="text-ink-muted">
 at {{ ce.location.name }} ({{ ce.location.code }})
 </span>
 <span v-if="ce.custodian" class="text-ink-faint">
 &bull; Custodian: {{ ce.custodian.name }}
 </span>
 </div>
 <span class="text-ink-faint font-mono text-[10px]">
 {{ formatDate(ce.occurred_at) }}
 </span>
 </div>
 </div>
 </div>
 </div>
 </div>

 <!-- Unified Institutional Lifecycle Timeline -->
 <div class="bg-surface border border-hairline rounded-xl p-6 shadow-xs space-y-4">
 <div class="flex items-center justify-between border-b border-hairline-subtle pb-3">
 <h2 class="text-xs font-semibold uppercase tracking-wider text-ink-muted flex items-center gap-2">
 <Clock class="w-4 h-4 text-primary" />
 <span>Unified Institutional Lifecycle Timeline ({{ timelineEvents.length }})</span>
 </h2>
 <span class="text-xs text-ink-faint font-medium">Authoritative Chronology</span>
 </div>

 <div v-if="isLoadingTimeline" class="py-4 space-y-3">
 <LoadingSkeleton class="h-12 rounded-xl" />
 <LoadingSkeleton class="h-12 rounded-xl" />
 </div>

 <div
 v-else-if="timelineEvents.length > 0"
 class="relative pl-6 space-y-5 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-hairline"
 >
 <div
 v-for="ev in timelineEvents"
 :key="ev.id"
 class="relative space-y-1.5"
 >
 <!-- Timeline bullet dot -->
 <div
 class="absolute -left-6 top-1 w-4 h-4 rounded-full border-2 border-surface flex items-center justify-center"
 :class="getEventBadgeColor(ev.event_type)"
 >
 <div class="w-1.5 h-1.5 rounded-full bg-current"></div>
 </div>

 <div class="flex items-start justify-between gap-3">
 <div class="space-y-0.5">
 <div class="flex items-center gap-2 flex-wrap">
 <span class="text-xs font-bold text-ink">
 {{ ev.title }}
 </span>
 <span
 class="px-2 py-0.5 rounded-full text-[10px] font-semibold border"
 :class="getEventBadgeColor(ev.event_type)"
 >
 {{ ev.event_type.replace(/_/g, ' ') }}
 </span>
 </div>
 <p v-if="ev.description" class="text-xs text-ink-secondary leading-relaxed">
 {{ ev.description }}
 </p>
 </div>

 <span class="text-[11px] font-mono text-ink-faint shrink-0 whitespace-nowrap">
 {{ formatDate(ev.timestamp) }}
 </span>
 </div>

 <div class="flex items-center gap-3 text-[11px] text-ink-faint flex-wrap">
 <span v-if="ev.actor" class="flex items-center gap-1 font-medium text-ink-secondary">
 <User class="w-3 h-3" />
 {{ ev.actor.name }}
 </span>
 <span v-if="ev.metadata?.action" class="font-mono text-[10px]">
 Action: {{ ev.metadata.action }}
 </span>
 <span v-if="ev.metadata?.version_number" class="font-mono text-[10px]">
 v{{ ev.metadata.version_number }}
 </span>
 <span v-if="ev.metadata?.from_unit" class="text-[10px]">
 {{ ev.metadata.from_unit }} &rarr; {{ ev.metadata.to_unit }}
 </span>
 <span v-if="ev.metadata?.is_fulfilling !== undefined" class="text-[10px]">
 Fulfilling: {{ ev.metadata.is_fulfilling ? 'Yes' : 'No' }}
 </span>
 </div>
 </div>
 </div>

 <div v-else class="text-xs text-slate-400 italic py-6 text-center">
 No lifecycle timeline events recorded for this document yet.
 </div>
 </div>
 </div>

 <!-- Sidebar Attributes (Right 1 col) -->
 <div class="space-y-6">
 <!-- Institutional Ownership Card -->
 <div class="bg-surface border border-hairline rounded-xl p-5 shadow-xs space-y-4">
 <h3 class="text-xs font-semibold uppercase tracking-wider text-ink-muted flex items-center gap-2 border-b border-hairline-subtle pb-2">
 <Building2 class="w-4 h-4 text-primary" />
 <span>Institutional Ownership</span>
 </h3>

 <div class="space-y-3 text-xs">
 <div>
 <span class="text-ink-muted block font-medium">Accountable Unit</span>
 <span class="font-bold text-ink">
 {{ document.owner_unit?.name }} ({{ document.owner_unit?.code }})
 </span>
 </div>

 <div>
 <span class="text-ink-muted block font-medium">Registered By</span>
 <span class="font-bold text-ink">
 {{ document.creator?.name || `Staff #${document.created_by}` }}
 </span>
 </div>

 <div>
 <span class="text-ink-muted block font-medium">Custodian Officer</span>
 <span class="font-bold text-ink">
 {{ document.custodian?.name || 'Unassigned Central Registry' }}
 </span>
 </div>

 <div>
 <span class="text-ink-muted block font-medium">Registration Instant</span>
 <span class="font-mono text-ink-muted">
 {{ new Date(document.registered_at).toLocaleString() }}
 </span>
 </div>
 </div>
 </div>
 </div>
 </div>
 </template>

 <!-- Version Upload Modal -->
 <ModalDialog
 :is-open="showVersionModal"
 title="Upload New Document Version"
 @close="showVersionModal = false"
 >
 <form @submit.prevent="submitNewVersion" class="space-y-4">
 <div>
 <label class="block text-xs font-semibold uppercase tracking-wider text-ink-secondary mb-1">
 File (PDF, DOCX) *
 </label>
 <input
 type="file"
 required
 class="w-full text-xs text-ink-secondary file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary-subtle file:text-primary dark:file:bg-[#172c42] dark:file:text-[#62aef0] hover:file:bg-[#d4e8fa] cursor-pointer"
 @change="handleVersionFileChange"
 />
 </div>

 <div>
 <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
 Change Summary / Version Note *
 </label>
 <BaseTextarea
 v-model="changeNote"
 rows="3"
 placeholder="Describe revisions made in this version..."
 required
 />
 </div>

 <div class="flex justify-end gap-2 pt-2">
 <BaseButton variant="ghost" size="sm" type="button" @click="showVersionModal = false">Cancel</BaseButton>
 <BaseButton variant="primary" size="sm" type="submit" :loading="isUploadingVersion">Upload Version</BaseButton>
 </div>
 </form>
 </ModalDialog>

 <!-- Archive Transfer Modal -->
 <ModalDialog
 :is-open="showArchiveModal"
 title="Transfer Document to Institutional Archive"
 @close="showArchiveModal = false"
 >
 <form @submit.prevent="submitArchiveTransfer" class="space-y-4">
 <p class="text-xs text-slate-500">
 Transferring this record will transition its lifecycle from <strong>active</strong> to <strong>retired</strong> under permanent retention policy governance.
 </p>

 <BaseSelect
 v-model="selectedCollectionId"
 label="Archive Collection *"
 :options="collections.map((c) => ({ label: c.name, value: c.id }))"
 required
 />

 <BaseSelect
 v-model="selectedPolicyVersionId"
 label="Retention Policy Version *"
 :options="policies.map((p) => ({ label: `${p.name} (${p.code})`, value: p.id }))"
 required
 />

 <div class="flex justify-end gap-2 pt-2">
 <BaseButton variant="ghost" size="sm" type="button" @click="showArchiveModal = false">Cancel</BaseButton>
 <BaseButton variant="primary" size="sm" type="submit" :loading="isArchiving">Confirm Archival Transfer</BaseButton>
 </div>
 </form>
 </ModalDialog>

 <!-- Digitize Paper Original Modal (Scenario B) -->
 <ModalDialog
 :is-open="showDigitizeModal"
 title="Digitize Paper Original (Deferred Scanning)"
 @close="showDigitizeModal = false"
 >
 <form @submit.prevent="submitDigitize" class="space-y-4">
 <p class="text-xs text-ink-secondary leading-relaxed">
 Scenario B: Ingest the physical paper scan as an authoritative <strong>Version 2</strong> revision. The initial paper intake metadata (Version 1) remains strictly immutable.
 </p>

 <div v-if="document?.physical_originals && document.physical_originals.length > 0">
 <label class="block text-xs font-semibold uppercase tracking-wider text-ink-secondary mb-1">
 Target Physical Original
 </label>
 <select
 v-model="selectedPhysicalOriginalId"
 class="w-full text-xs rounded-xl border border-hairline bg-surface px-3 py-2 text-ink focus:outline-none focus:ring-2 focus:ring-primary/20"
 >
 <option
 v-for="orig in document.physical_originals"
 :key="orig.id"
 :value="orig.id"
 >
 {{ orig.physical_reference || `Original #${orig.id}` }} (Retention: {{ orig.retention_requirement }})
 </option>
 </select>
 </div>

 <div>
 <label class="block text-xs font-semibold uppercase tracking-wider text-ink-secondary mb-1">
 Scanned Binary Document (PDF, TIFF, PNG) *
 </label>
 <input
 type="file"
 required
 accept=".pdf,.tiff,.tif,.png,.jpg,.jpeg"
 class="w-full text-xs text-ink-secondary file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary-subtle file:text-primary dark:file:bg-[#172c42] dark:file:text-[#62aef0] hover:file:bg-[#d4e8fa] cursor-pointer"
 @change="handleDigitizeFileChange"
 />
 </div>

 <div>
 <label class="block text-xs font-semibold uppercase tracking-wider text-ink-secondary mb-1">
 Digitization & Quality Verification Notes
 </label>
 <BaseTextarea
 v-model="digitizeVerificationNote"
 rows="3"
 placeholder="e.g. Scanned via Archival Canon Scanner at 300 DPI, full color optical reproduction..."
 />
 </div>

 <div class="flex justify-end gap-2 pt-2">
 <BaseButton variant="ghost" size="sm" type="button" @click="showDigitizeModal = false">Cancel</BaseButton>
 <BaseButton variant="primary" size="sm" type="submit" :loading="isDigitizing">
 Commit Digital Scan
 </BaseButton>
 </div>
 </form>
 </ModalDialog>

 <!-- OCR Text Verification Modal -->
 <ModalDialog
   :open="showOcrVerifyModal"
   title="Human Verification & Correction of Extracted Text"
   @close="showOcrVerifyModal = false"
 >
   <div class="space-y-4">
     <p class="text-xs text-ink-muted">
       Review the OCR extracted text below (Amharic & English), make any corrections necessary, and submit to mark as human-verified.
     </p>

     <div>
       <label class="block text-xs font-medium text-ink mb-1">Extracted Text Content</label>
       <BaseTextarea
         v-model="ocrCorrectedText"
         :rows="10"
         placeholder="Enter or correct extracted text..."
         class="font-mono text-xs"
       />
     </div>

     <div>
       <label class="block text-xs font-medium text-ink mb-1">Verification Remarks / Notes</label>
       <BaseInput
         v-model="ocrVerificationNotes"
         placeholder="e.g. Verified against physical stamp and Amharic letter body"
         class="text-xs"
       />
     </div>

     <div class="flex justify-end gap-2 pt-2">
       <BaseButton variant="ghost" size="sm" @click="showOcrVerifyModal = false">
         Cancel
       </BaseButton>
       <BaseButton
         variant="primary"
         size="sm"
         :loading="isVerifyingOcr"
         @click="submitOcrVerify"
       >
         Save Human Verification
       </BaseButton>
     </div>
   </div>
 </ModalDialog>
 </div>
</template>

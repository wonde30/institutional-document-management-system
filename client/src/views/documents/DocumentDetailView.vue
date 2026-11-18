<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { documentsApi, archiveApi, ocrApi, institutionalApi, workflowApi, usersApi } from '@/api/client'
import type {
  Document,
  ArchiveCollection,
  RetentionPolicy,
  TimelineEvent,
  PhysicalOriginal,
  WorkflowDefinition,
  DocumentWorkflowInstanceSummary,
  DocumentWorkflowTaskSummary,
  User as UserAccount,
} from '@/types'
import { useAuthStore } from '@/stores/auth'
import { useMetadataStore } from '@/stores/metadata'
import { useToast } from '@/composables/useToast'
import PageHeader from '@/components/PageHeader.vue'
import ModalDialog from '@/components/ModalDialog.vue'
import ClassificationBadge from '@/components/ClassificationBadge.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import LoadingSkeleton from '@/components/ui/LoadingSkeleton.vue'
import OfficialLetterA4Preview from '@/components/letters/OfficialLetterA4Preview.vue'
import { formatDate, formatFileSize, downloadBlob, formatStatus } from '@/utils/formatters'
import {
  Download,
  Upload,
  ArrowLeft,
  ArrowRight,
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
  Printer,
  FileSignature,
  XCircle,
  RotateCcw,
  CheckSquare,
  GitCompare,
  Link2,
  Plus,
} from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const metaStore = useMetadataStore()
const toast = useToast()
const docId = Number(route.params.id)

const document = ref<Document | null>(null)
const isLoading = ref(true)
const errorMessage = ref<string | null>(null)
const errorCode = ref<number | null>(null)
const downloadingFileId = ref<number | null>(null)

// Edit metadata modal state
const showEditMetadataModal = ref(false)
const editMetadataForm = ref({
  title: '',
  summary: '',
  category_id: '' as number | '',
  confidentiality_id: '' as number | '',
  custodian_id: '' as number | '',
  document_date: '',
  lifecycle: 'active' as 'active' | 'retired',
})
const isSavingMetadata = ref(false)
const userOptions = ref<UserAccount[]>([])

// Submit to workflow modal state
const showSubmitWorkflowModal = ref(false)
const workflowDefinitions = ref<WorkflowDefinition[]>([])
const selectedWorkflowDefId = ref<number | ''>('')
const selectedVersionForWorkflow = ref<number | ''>('')
const isSubmittingWorkflow = ref(false)

// Workflow task decision modal state
const showTaskDecisionModal = ref(false)
const selectedWorkflowTask = ref<DocumentWorkflowTaskSummary | null>(null)
const decisionType = ref<'approve' | 'reject' | 'return'>('approve')
const decisionReason = ref('')
const isSubmittingDecision = ref(false)

const hasFileBackedVersion = computed(() => {
  return document.value?.versions?.some(v => v.version_files && v.version_files.length > 0) ?? false
})

const fileBackedVersions = computed(() => {
  return document.value?.versions?.filter(v => v.version_files && v.version_files.length > 0) ?? []
})

const activeWorkflowInstances = computed<DocumentWorkflowInstanceSummary[]>(() => {
  if (document.value?.workflow_instances && document.value.workflow_instances.length > 0) {
    return document.value.workflow_instances
  }
  const list: DocumentWorkflowInstanceSummary[] = []
  document.value?.versions?.forEach(v => {
    if (v.workflow_instances) {
      list.push(...v.workflow_instances)
    }
  })
  return list
})

async function openEditMetadataModal() {
  if (!document.value) return
  editMetadataForm.value = {
    title: document.value.title || '',
    summary: document.value.summary || '',
    category_id: document.value.category_id || '',
    confidentiality_id: document.value.confidentiality_id || '',
    custodian_id: document.value.custodian_id || '',
    document_date: document.value.document_date ? document.value.document_date.split('T')[0] : '',
    lifecycle: document.value.lifecycle === 'tombstoned' ? 'retired' : document.value.lifecycle,
  }
  if (userOptions.value.length === 0) {
    try {
      const res = await usersApi.list({ per_page: 100 })
      userOptions.value = (res.data as any).data || []
    } catch (e) {
      console.warn('Could not load user directory for custodian assignment', e)
    }
  }
  showEditMetadataModal.value = true
}

async function submitEditMetadata() {
  if (!document.value) return
  if (!editMetadataForm.value.title.trim()) {
    toast.error('Document title is required.')
    return
  }
  isSavingMetadata.value = true
  try {
    const payload: any = {
      title: editMetadataForm.value.title.trim(),
      summary: editMetadataForm.value.summary.trim() || null,
      category_id: Number(editMetadataForm.value.category_id),
      confidentiality_id: Number(editMetadataForm.value.confidentiality_id),
      custodian_id: editMetadataForm.value.custodian_id ? Number(editMetadataForm.value.custodian_id) : null,
      document_date: editMetadataForm.value.document_date,
      lifecycle: editMetadataForm.value.lifecycle,
      lock_version: document.value.lock_version,
    }
    await documentsApi.update(docId, payload)
    toast.success('Document metadata updated successfully.')
    showEditMetadataModal.value = false
    await loadDocument()
  } catch (err: any) {
    if (err.response?.status === 409) {
      toast.error('Concurrency Collision: Metadata was modified by another operator. Reloading latest state...')
      await loadDocument()
    } else {
      toast.error(err.response?.data?.message || 'Failed to update document metadata.')
    }
  } finally {
    isSavingMetadata.value = false
  }
}

async function openSubmitWorkflowModal() {
  if (!document.value) return
  try {
    const res = await workflowApi.definitions()
    workflowDefinitions.value = (res.data.data || []).filter(d => d.active)
    if (workflowDefinitions.value.length > 0) {
      selectedWorkflowDefId.value = workflowDefinitions.value[0].id
    }
    const avail = fileBackedVersions.value
    if (avail.length > 0) {
      selectedVersionForWorkflow.value = avail[avail.length - 1].id
    }
    showSubmitWorkflowModal.value = true
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to load workflow definitions.')
  }
}

async function submitToWorkflow() {
  if (!selectedWorkflowDefId.value || !selectedVersionForWorkflow.value) {
    toast.error('Please select both a workflow policy and a revision with uploaded files.')
    return
  }
  isSubmittingWorkflow.value = true
  try {
    await workflowApi.startInstance({
      workflow_definition_id: Number(selectedWorkflowDefId.value),
      document_version_id: Number(selectedVersionForWorkflow.value),
    })
    toast.success('Document version submitted into institutional approval workflow.')
    showSubmitWorkflowModal.value = false
    await loadDocument()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to submit document to workflow.')
  } finally {
    isSubmittingWorkflow.value = false
  }
}

function openTaskDecision(task: DocumentWorkflowTaskSummary, type: 'approve' | 'reject' | 'return') {
  selectedWorkflowTask.value = task
  decisionType.value = type
  decisionReason.value = ''
  showTaskDecisionModal.value = true
}

async function submitTaskDecision() {
  if (!selectedWorkflowTask.value) return
  if (decisionType.value === 'reject' && !decisionReason.value.trim()) {
    toast.error('Rejection reason is required for institutional audit record.')
    return
  }
  isSubmittingDecision.value = true
  try {
    await workflowApi.decide(selectedWorkflowTask.value.id, {
      decision: decisionType.value,
      reason: decisionReason.value.trim() || undefined,
    })
    toast.success(`Workflow decision '${decisionType.value}' recorded.`)
    showTaskDecisionModal.value = false
    await loadDocument()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to record workflow decision.')
  } finally {
    isSubmittingDecision.value = false
  }
}

// Version Comparison State
const showCompareModal = ref(false)
const compareVersionA = ref<number | ''>('')
const compareVersionB = ref<number | ''>('')
const isComparing = ref(false)
const comparisonResult = ref<any>(null)

function openCompareModal() {
  const vers = document.value?.versions || []
  if (vers.length >= 2) {
    compareVersionA.value = vers[vers.length - 2].id
    compareVersionB.value = vers[vers.length - 1].id
  } else if (vers.length === 1) {
    compareVersionA.value = vers[0].id
    compareVersionB.value = vers[0].id
  }
  comparisonResult.value = null
  showCompareModal.value = true
  if (compareVersionA.value && compareVersionB.value && compareVersionA.value !== compareVersionB.value) {
    runVersionComparison()
  }
}

async function runVersionComparison() {
  if (!compareVersionA.value || !compareVersionB.value || compareVersionA.value === compareVersionB.value) {
    return
  }
  isComparing.value = true
  try {
    const res = await documentsApi.compareVersions(docId, Number(compareVersionA.value), Number(compareVersionB.value))
    comparisonResult.value = res.data.data
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to compare document versions.')
  } finally {
    isComparing.value = false
  }
}

// File Integrity Check State
const verifyingFileId = ref<number | null>(null)
const fileIntegrityResults = ref<Record<number, { outcome: string; message: string; checked_at: string }>>({})

async function verifyFileIntegrity(fileId: number) {
  verifyingFileId.value = fileId
  try {
    const res = await documentsApi.verifyFileIntegrity(docId, fileId)
    fileIntegrityResults.value[fileId] = {
      outcome: res.data.data.outcome,
      message: res.data.message || '',
      checked_at: new Date().toLocaleTimeString(),
    }
    if (res.data.data.outcome === 'match') {
      toast.success('SHA-256 Checksum Verified: Exact match with institutional record.')
    } else {
      toast.error('Integrity Alert: File hash differs from institutional record!')
    }
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'File integrity check failed.')
  } finally {
    verifyingFileId.value = null
  }
}

// Related Documents State
const showAddRelationModal = ref(false)
const relationForm = ref({
  target_document_id: '' as number | '',
  relation_type: 'references',
})
const isSubmittingRelation = ref(false)
const isRemovingRelation = ref<number | null>(null)

async function submitAddRelation() {
  if (!relationForm.value.target_document_id) {
    toast.error('Please enter the target document ID.')
    return
  }
  isSubmittingRelation.value = true
  try {
    await documentsApi.addRelation(docId, {
      target_document_id: Number(relationForm.value.target_document_id),
      relation_type: relationForm.value.relation_type,
    })
    toast.success('Document cross-reference relation established.')
    showAddRelationModal.value = false
    relationForm.value = { target_document_id: '', relation_type: 'references' }
    await loadDocument()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to establish document relation.')
  } finally {
    isSubmittingRelation.value = false
  }
}

async function removeRelation(targetId: number) {
  if (!confirm('Are you sure you want to remove this document relation?')) return
  isRemovingRelation.value = targetId
  try {
    await documentsApi.removeRelation(docId, targetId)
    toast.success('Document relationship removed.')
    await loadDocument()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to remove relation.')
  } finally {
    isRemovingRelation.value = null
  }
}

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
      return 'bg-[#e8f3fc] text-[#0075de] dark:bg-[#172c42] dark:text-[#62aef0]'
    case 'version_created':
      return 'bg-[#f3e8ff] text-[#9333ea] dark:bg-[#2e1065] dark:text-[#c084fc]'
    case 'physical_custody':
      return 'bg-[#fff4e6] text-[#dd5b00] dark:bg-[#3b220c] dark:text-[#f59e0b]'
    case 'movement_dispatched':
    case 'movement_acknowledged':
      return 'bg-[#e0e7ff] text-[#4f46e5] dark:bg-[#1e1b4b] dark:text-[#818cf8]'
    case 'directive_assigned':
    case 'assignment_event':
    case 'assignment_response_linked':
      return 'bg-[#e8f7ec] text-[#1aae39] dark:bg-[#14301d] dark:text-[#34d399]'
    case 'workflow_transition':
      return 'bg-[#ccfbf1] text-[#0d9488] dark:bg-[#134e4a] dark:text-[#2dd4bf]'
    default:
      return 'bg-[var(--wu-line)] text-[var(--wu-muted)]'
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

/**
 * The version the text extraction applies to.
 *
 * The extraction belongs to a document version, so the current version is
 * required by every extraction call.
 */
const currentVersionId = computed<number | null>(() => {
  const versions = document.value?.versions
  if (!versions || versions.length === 0) return null
  return versions[0].id
})

const isTemplateLetter = computed(() => {
  const v = document.value?.versions?.[0]
  return v?.ingestion_method === 'template_generated' || !!v?.template_data
})

const letterData = computed(() => {
  const v = document.value?.versions?.[0]
  return (v?.template_data as any) || {}
})

const primaryPdfFile = computed(() => {
  const v = document.value?.versions?.[0]
  return v?.version_files?.find((vf: any) => vf.purpose === 'primary')?.file_object
})

async function loadOcrExtractions() {
  if (!currentVersionId.value) {
    ocrExtractions.value = []
    return
  }
  isLoadingOcr.value = true
  try {
    const res = await ocrApi.extractions(docId, currentVersionId.value)
    ocrExtractions.value = (res.data as any).data || []
  } catch (err) {
    console.warn('Failed to load OCR extractions:', err)
  } finally {
    isLoadingOcr.value = false
  }
}

async function handleProcessOcr(fileObjectId?: number) {
  if (!currentVersionId.value) {
    toast.error('This document has no version to extract from.')
    return
  }
  isProcessingOcr.value = true
  try {
    await ocrApi.process(docId, currentVersionId.value, {
      file_object_id: fileObjectId,
      languages: 'eng+amh',
      sync: true,
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
  if (!currentVersionId.value) return
  isProcessingOcr.value = true
  try {
    await ocrApi.retry(docId, currentVersionId.value, extractionId, { sync: true })
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
    if (!currentVersionId.value) return
    await ocrApi.verify(docId, currentVersionId.value, selectedOcrExtraction.value.id, {
      verified_text: ocrCorrectedText.value,
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

const activeVersionId = computed(() => document.value?.versions?.[0]?.id)

// Verified signature strictly matching the active document version
const verifiedSignatureRecord = computed(() => {
  const currentVersionId = activeVersionId.value
  if (!currentVersionId) return null

  return signatures.value.find((s: any) => {
    const matchesVersion = s.document_version_id === currentVersionId || s.document_version?.id === currentVersionId
    const sig = s.digital_signature
    if (!matchesVersion || !sig) return false

    // Must be validly verified
    const isValidStatus = sig.verification_status === 'valid' || sig.verification_status === 'verified'
    // Must not be revoked
    const notRevoked = !sig.signing_identity?.revoked_at
    // Must have signed content digest
    const hasSignedHash = !!sig.signed_file?.sha256

    return isValidStatus && notRevoked && hasSignedHash
  })
})

const isDigitallySigned = computed(() => !!verifiedSignatureRecord.value)
const verifiedDigitalSig = computed(() => {
  const s = verifiedSignatureRecord.value
  if (!s || !s.digital_signature) return null
  return {
    signer_name: s.signer?.name || s.digital_signature.signing_identity?.signer_name || 'Authorized Signatory',
    signed_at: s.digital_signature.signed_at,
    provider_transaction_reference: s.digital_signature.signed_file?.sha256,
    verification_status: s.digital_signature.verification_status,
  }
})

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
  metaStore.loadAll()
  loadDocument()
  loadTimeline()
  loadOcrExtractions()
  loadSignatures()
})
</script>

<template>
 <div class="space-y-6 max-w-5xl mx-auto">
 <!-- Header -->
 <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-hairline pb-4">
 <div class="flex items-center gap-3">
 <BaseButton variant="ghost" size="sm" @click="router.push('/documents')">
 <ArrowLeft class="w-4 h-4" />
 </BaseButton>
 <div>
 <div class="flex items-center gap-2">
 <span class="font-mono text-xs font-semibold text-primary bg-primary-subtle px-2.5 py-0.5 rounded-full border border-primary/20">
 {{ document?.reference_number || 'Loading...' }}
 </span>
 <StatusBadge v-if="document" :status="document.lifecycle" />
 </div>
 <h1 class="text-2xl font-semibold tracking-tight text-balance mt-1">
 {{ document?.title || 'Document Record' }}
 </h1>
 </div>
 </div>

 <div class="flex items-center gap-2 flex-wrap">
 <BaseButton
 v-if="document && primaryPdfFile"
 variant="primary"
 size="sm"
 :loading="downloadingFileId === primaryPdfFile.id"
 @click="handleDownloadFile(primaryPdfFile.id, primaryPdfFile.original_filename || `${document.reference_number}.pdf`)"
 >
 <Printer class="w-4 h-4 mr-1.5" />
 <span>Print / Download PDF</span>
 </BaseButton>

 <BaseButton
 v-if="document && document.lifecycle !== 'tombstoned' && (authStore.hasPermission('documents.update') || authStore.hasPermission('system.configure'))"
 variant="outline"
 size="sm"
 @click="openEditMetadataModal"
 >
 <Edit3 class="w-4 h-4 mr-1.5" />
 <span>Edit Metadata</span>
 </BaseButton>

 <BaseButton
 v-if="document && document.lifecycle === 'active' && hasFileBackedVersion && (authStore.hasPermission('workflow.submit') || authStore.hasPermission('documents.update') || authStore.hasPermission('system.configure'))"
 variant="primary"
 size="sm"
 @click="openSubmitWorkflowModal"
 >
 <Workflow class="w-4 h-4 mr-1.5" />
 <span>Submit to Workflow</span>
 </BaseButton>

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
 variant="outline"
 size="sm"
 @click="openArchiveModal"
 >
 <Archive class="w-4 h-4" />
 <span>Transfer to Digital Archive</span>
 </BaseButton>
 </div>
 </div>

 <!-- Loading Skeleton -->
 <div v-if="isLoading" class="space-y-6">
 <LoadingSkeleton class="h-44 rounded-lg" />
 <LoadingSkeleton class="h-64 rounded-lg" />
 </div>

 <!-- Error State -->
 <div v-else-if="errorMessage" class="p-8 rounded-lg bg-surface border border-hairline text-center space-y-4 max-w-md mx-auto">
 <div v-if="errorCode === 403" class="w-12 h-12 rounded-full bg-[#fff4e6] dark:bg-[#3b220c] text-[#dd5b00] dark:text-[#f59e0b] flex items-center justify-center mx-auto">
 <Lock class="w-6 h-6" />
 </div>
 <div v-else-if="errorCode === 404" class="w-12 h-12 rounded-full bg-surface-muted text-ink-muted flex items-center justify-center mx-auto">
 <FileText class="w-6 h-6" />
 </div>
 <div v-else class="w-12 h-12 rounded-full bg-[#fde8e8] dark:bg-[#371717] text-[#e03e3e] dark:text-[#f87171] flex items-center justify-center mx-auto">
 <AlertCircle class="w-6 h-6" />
 </div>

 <div class="space-y-1">
 <h3 class="font-bold text-ink text-base">
 {{ errorCode === 403 ? 'Access Restricted' : (errorCode === 404 ? 'Document Not Found' : 'Unable to Load Document') }}
 </h3>
 <p class="text-xs text-ink-muted leading-relaxed">{{ errorMessage }}</p>
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
 <!-- Official Institutional Letter Presentation Card (When Letter Template Present) -->
 <div v-if="isTemplateLetter" class="bg-surface border border-hairline rounded-xl p-6 space-y-4">
 <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-hairline-subtle pb-3">
 <div class="flex items-center gap-2">
 <FileSignature class="w-4 h-4 text-primary" />
 <h2 class="text-xs font-semibold   text-ink-muted">
 Official Wollo University Letter Presentation
 </h2>
 <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
 A4 Authentic Standard
 </span>
 </div>
 <div class="flex items-center gap-2">
 <BaseButton
 v-if="primaryPdfFile"
 size="sm"
 variant="outline"
 :loading="downloadingFileId === primaryPdfFile.id"
 @click="handleDownloadFile(primaryPdfFile.id, primaryPdfFile.original_filename || `${document.reference_number}.pdf`)"
 >
 <Printer class="w-3.5 h-3.5 mr-1" />
 <span>Print / Download PDF</span>
 </BaseButton>
 </div>
 </div>

 <OfficialLetterA4Preview
 :ref-number="document.reference_number"
 :ethiopian-date="letterData.ethiopian_date || '---'"
 :recipient-lead="letterData.recipient_lead || letterData.recipient_name || ''"
 :recipient-sub="letterData.recipient_sub || letterData.recipient_office || ''"
 :recipient-location="letterData.recipient_location || 'ኮምቦልቻ፤'"
 :subject="letterData.subject || document.title"
 :body="letterData.body || document.summary || ''"
 :cc-list="letterData.cc_list || []"
 :signatory-name="letterData.signatory_name || ''"
 :signatory-title-am="letterData.signatory_title_am || ''"
 :signatory-title-en="letterData.signatory_title_en || ''"
 :is-digitally-signed="isDigitallySigned"
 :digital-signature="verifiedDigitalSig"
 />
 </div>

 <!-- Institutional Workflow & Review Status -->
 <div v-if="activeWorkflowInstances.length > 0" class="bg-surface border border-hairline rounded-xl p-6 space-y-4">
 <div class="flex items-center justify-between border-b border-hairline-subtle pb-3">
 <div class="flex items-center gap-2">
 <Workflow class="w-4 h-4 text-primary" />
 <h2 class="text-xs font-semibold   text-ink-muted">
 Institutional Workflow & Routing
 </h2>
 </div>
 <span class="text-xs font-semibold text-primary">
 {{ activeWorkflowInstances.length }} Pipeline{{ activeWorkflowInstances.length > 1 ? 's' : '' }} Active
 </span>
 </div>

 <div class="space-y-4">
 <div
 v-for="wf in activeWorkflowInstances"
 :key="wf.id"
 class="p-4 rounded-xl bg-surface-hover border border-hairline space-y-3"
 >
 <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-hairline-subtle pb-2.5">
 <div>
 <div class="text-sm font-bold text-ink">
 {{ wf.workflow_definition?.name || 'Administrative Approval Workflow' }}
 </div>
 <div class="text-xs text-ink-muted flex items-center gap-2 mt-0.5">
 <span>Instance #{{ wf.id }}</span>
 <span>&bull;</span>
 <span v-if="wf.version_number">Target: Revision v{{ wf.version_number }}.0</span>
 <span v-if="wf.started_at">&bull; Started: {{ formatDate(wf.started_at) }}</span>
 <span v-if="wf.started_by">&bull; By: {{ wf.started_by.name }}</span>
 </div>
 </div>
 </div>

 <!-- Task stages -->
 <div v-if="wf.tasks && wf.tasks.length > 0" class="space-y-2">
 <div
 v-for="t in wf.tasks"
 :key="t.id"
 class="flex flex-col sm:flex-row sm:items-center sm:justify-between p-3 rounded-lg bg-surface border border-hairline text-xs gap-3"
 >
 <div class="space-y-1">
 <div class="flex items-center gap-2">
 <span
 class="px-2 py-0.5 rounded text-xs font-semibold"
 :class="t.closed_at ? 'bg-surface-muted text-ink-muted border border-hairline' : 'bg-primary-subtle text-primary border border-primary/20'"
 >
 {{ t.state?.name || 'Pending Decision' }}
 </span>
 <span v-if="t.closed_at && t.decision" class="text-xs font-medium" :class="t.decision.decision === 'approve' ? 'text-emerald-600' : 'text-rose-600'">
 &bull; {{ t.decision.decision === 'approve' ? 'Approved' : t.decision.decision === 'reject' ? 'Rejected' : t.decision.decision }}
 <span v-if="t.decision.decided_by">by {{ t.decision.decided_by.name }}</span>
 </span>
 </div>
 <div class="text-xs text-ink-muted flex items-center gap-2">
 <span>Assignee: {{ t.assigned_user?.name || t.assigned_role?.name || t.assigned_unit?.name || 'Unassigned' }}</span>
 <span v-if="t.due_at">&bull; Due: {{ formatDate(t.due_at) }}</span>
 </div>
 <div v-if="t.decision?.reason" class="text-xs text-ink-secondary bg-surface-muted p-2 rounded mt-1 font-mono">
 Note: {{ t.decision.reason }}
 </div>
 </div>

 <!-- Action buttons if task is open and actor is eligible -->
 <div v-if="!t.closed_at && (authStore.user?.id === t.assigned_user_id || authStore.hasPermission('workflow.review') || authStore.hasPermission('workflow.manage') || authStore.hasPermission('system.configure'))" class="flex items-center gap-1.5 shrink-0">
 <BaseButton
 size="sm"
 variant="primary"
 class="h-7 text-xs px-2.5"
 @click="openTaskDecision(t, 'approve')"
 >
 <CheckCircle2 class="w-3.5 h-3.5 mr-1" /> Approve
 </BaseButton>
 <BaseButton
 size="sm"
 variant="danger"
 class="h-7 text-xs px-2.5"
 @click="openTaskDecision(t, 'reject')"
 >
 <XCircle class="w-3.5 h-3.5 mr-1" /> Reject
 </BaseButton>
 <BaseButton
 size="sm"
 variant="ghost"
 class="h-7 text-xs px-2"
 @click="openTaskDecision(t, 'return')"
 >
 <RotateCcw class="w-3.5 h-3.5 mr-1" /> Return
 </BaseButton>
 </div>
 </div>
 </div>
 </div>
 </div>
 </div>

 <!-- Metadata Summary -->
 <div class="bg-surface border border-hairline rounded-xl p-6 space-y-4">
 <h2 class="text-xs font-semibold   text-ink-muted border-b border-hairline-subtle pb-2">
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
 <div class="bg-surface border border-hairline rounded-xl p-6 space-y-4">
 <div class="flex items-center justify-between border-b border-hairline-subtle pb-3">
 <h2 class="text-xs font-semibold   text-ink-muted flex items-center gap-2">
 <History class="w-4 h-4 text-primary" />
 <span>Version History ({{ document.versions?.length || 0 }})</span>
 </h2>
 <div class="flex items-center gap-2">
 <BaseButton
 v-if="document.versions && document.versions.length >= 2"
 variant="outline"
 size="sm"
 class="h-7 text-xs px-2.5"
 @click="openCompareModal"
 >
 <GitCompare class="w-3.5 h-3.5 mr-1 text-primary" /> Compare Versions
 </BaseButton>
 <span class="text-xs text-ink-faint font-medium">
 Optimistic Concurrency Lock: v{{ document.lock_version }}
 </span>
 </div>
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
 <div class="text-xs text-ink-faint flex items-center gap-2">
 <span>Committed: {{ formatDate(ver.created_at) }}</span>
 <span>&bull;</span>
 <span>By: {{ ver.creator?.name || `Staff #${ver.created_by}` }}</span>
 </div>
 </div>
 </div>

 <!-- Attached Version Files with Size, Checksum, Integrity and Download -->
 <div v-if="ver.version_files && ver.version_files.length > 0" class="space-y-2 pt-1">
 <div
 v-for="vf in ver.version_files"
 :key="vf.file_object_id"
 class="flex items-center justify-between p-3 rounded-xl bg-surface-hover border border-hairline text-xs"
 >
 <div class="flex items-center gap-3 truncate max-w-[65%]">
 <FileText class="w-4 h-4 text-primary shrink-0" />
 <div class="truncate">
 <span class="font-medium text-ink truncate block">
 {{ vf.file_object?.original_filename || `document_v${ver.version_number}.pdf` }}
 </span>
 <div class="text-xs text-ink-faint flex items-center gap-2 mt-0.5 font-mono">
 <span>{{ formatFileSize(vf.file_object?.byte_size) }}</span>
 <span v-if="vf.file_object?.sha256" class="truncate" :title="vf.file_object.sha256">
 &bull; SHA-256: {{ vf.file_object.sha256.substring(0, 12) }}...
 </span>
 </div>
 </div>
 </div>

 <div class="flex items-center gap-1.5 shrink-0">
 <BaseButton
 size="sm"
 variant="ghost"
 class="h-7 text-xs px-2 text-ink-muted hover:text-ink"
 :loading="verifyingFileId === vf.file_object_id"
 title="Verify cryptographic SHA-256 integrity against storage"
 @click="verifyFileIntegrity(vf.file_object_id)"
 >
 <ShieldCheck class="w-3.5 h-3.5 mr-1 text-emerald-600" />
 <span v-if="fileIntegrityResults[vf.file_object_id]?.outcome === 'match'" class="text-emerald-600 font-semibold">Verified</span>
 <span v-else>Verify</span>
 </BaseButton>

 <BaseButton
 size="sm"
 variant="outline"
 class="h-7 text-xs px-2.5"
 :loading="downloadingFileId === vf.file_object_id"
 @click="handleDownloadFile(vf.file_object_id, vf.file_object?.original_filename || `document_v${ver.version_number}.pdf`)"
 >
 <Download class="w-3.5 h-3.5 mr-1" />
 <span>Download</span>
 </BaseButton>
 </div>
 </div>
 </div>
 <div v-else class="text-xs text-ink-faint italic bg-surface-muted p-2.5 rounded-xl">
 No binary file attached to this revision.
 </div>
 </div>
 </div>
 <div v-else class="text-xs text-ink-faint italic py-6 text-center">
 No digital versions committed to this document repository record.
 </div>
 </div>

 <!-- Related Documents & Cross-References -->
 <div class="bg-surface border border-hairline rounded-xl p-6 space-y-4">
 <div class="flex items-center justify-between border-b border-hairline-subtle pb-3">
 <h2 class="text-xs font-semibold   text-ink-muted flex items-center gap-2">
 <Link2 class="w-4 h-4 text-primary" />
 <span>Cross-Referenced &amp; Related Documents</span>
 </h2>
 <BaseButton
 v-if="authStore.hasPermission('documents.update')"
 variant="outline"
 size="sm"
 class="h-7 text-xs px-2"
 @click="showAddRelationModal = true"
 >
 <Plus class="w-3.5 h-3.5 mr-1" /> Link Document
 </BaseButton>
 </div>

 <div v-if="(document.outgoing_relations && document.outgoing_relations.length > 0) || (document.incoming_relations && document.incoming_relations.length > 0)" class="space-y-3">
 <!-- Outgoing Relations -->
 <div v-if="document.outgoing_relations && document.outgoing_relations.length > 0" class="space-y-2">
 <div class="text-xs font-bold   text-ink-muted">Referenced by this document</div>
 <div v-for="rel in document.outgoing_relations" :key="rel.target_id" class="flex items-center justify-between p-3 rounded-xl bg-surface-hover border border-hairline text-xs">
 <div class="flex items-center gap-2.5">
 <span class="px-2 py-0.5 rounded text-xs font-bold  bg-primary-subtle text-primary">
 {{ rel.relation_type?.name || 'References' }}
 </span>
 <span class="font-mono text-xs font-semibold text-primary">{{ rel.target_document?.reference_number }}</span>
 <span class="text-ink font-medium">{{ rel.target_document?.title }}</span>
 </div>
 <div class="flex items-center gap-1.5">
 <BaseButton variant="ghost" size="sm" class="h-6 text-xs px-2" @click="router.push(`/documents/${rel.target_id}`)">
 Open
 </BaseButton>
 <BaseButton v-if="authStore.hasPermission('documents.update')" variant="ghost" size="sm" class="h-6 text-xs px-1.5 text-rose-500" @click="removeRelation(rel.target_id)">
 Unlink
 </BaseButton>
 </div>
 </div>
 </div>

 <!-- Incoming Relations -->
 <div v-if="document.incoming_relations && document.incoming_relations.length > 0" class="space-y-2">
 <div class="text-xs font-bold   text-ink-muted">Referenced by other documents</div>
 <div v-for="rel in document.incoming_relations" :key="rel.source_id" class="flex items-center justify-between p-3 rounded-xl bg-surface-hover border border-hairline text-xs">
 <div class="flex items-center gap-2.5">
 <span class="px-2 py-0.5 rounded text-xs font-bold  bg-zinc-200 dark:bg-zinc-800 text-ink-secondary">
 {{ rel.relation_type?.name || 'Referenced By' }}
 </span>
 <span class="font-mono text-xs font-semibold text-primary">{{ rel.source_document?.reference_number }}</span>
 <span class="text-ink font-medium">{{ rel.source_document?.title }}</span>
 </div>
 <BaseButton variant="ghost" size="sm" class="h-6 text-xs px-2" @click="router.push(`/documents/${rel.source_id}`)">
 Open
 </BaseButton>
 </div>
 </div>
 </div>

 <div v-else class="text-xs text-ink-faint italic py-4 text-center">
 No formal cross-references or document relationships established.
 </div>
 </div>

      <!-- OCR Document Text Extraction Panel -->
      <div class="bg-surface border border-hairline rounded-xl p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-hairline-subtle pb-3">
          <div class="flex items-center gap-2">
            <ScanLine class="w-4 h-4 text-primary" />
            <h2 class="text-xs font-semibold   text-ink-muted">
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
                  class="px-2 py-0.5 rounded text-xs font-semibold"
                  :class="{
                    'bg-[#e8f7ec] text-[#1aae39] dark:bg-[#14301d] dark:text-[#34d399]': ext.processing_status === 'completed',
                    'bg-[#e8f3fc] text-[#0075de] dark:bg-[#172c42] dark:text-[#62aef0] animate-pulse': ext.processing_status === 'processing',
                    'bg-[#fff4e6] text-[#dd5b00] dark:bg-[#3b220c] dark:text-[#f59e0b]': ext.processing_status === 'pending',
                    'bg-[#fde8e8] text-[#e03e3e] dark:bg-[#371717] dark:text-[#f87171]': ext.processing_status === 'failed',
                  }"
                >
                  OCR {{ ext.processing_status || 'completed' }}
                </span>
                <span class="text-xs text-ink-muted font-mono">
                  Engine: {{ ext.engine_name || 'Tesseract 5.x' }} · Lang: {{ ext.languages || 'eng+amh' }}
                </span>
                <span
                  v-if="ext.human_verified"
                  class="px-2 py-0.5 rounded text-xs font-medium bg-[#e8f7ec] text-[#1aae39] dark:bg-[#14301d] dark:text-[#34d399]"
                >
                  ✓ Verified by Staff
                </span>
              </div>

              <div class="flex items-center gap-2">
                <BaseButton
                  v-if="ext.processing_status === 'failed'"
                  size="sm"
                  variant="danger"
                  class="text-xs h-7"
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
            <div v-if="ext.error_details" class="p-2.5 rounded-lg bg-[#fde8e8] dark:bg-[#371717] text-[#e03e3e] dark:text-[#f87171] text-xs">
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
      <div v-if="signatures.length > 0" class="bg-surface border border-hairline rounded-xl p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-hairline-subtle pb-3">
          <div class="flex items-center gap-2">
            <Key class="w-4 h-4 text-[#1aae39] dark:text-[#34d399]" />
            <h2 class="text-xs font-semibold   text-ink-muted">
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
                <span class="px-2 py-0.5 rounded text-xs font-semibold bg-[#e8f7ec] text-[#1aae39] dark:bg-[#14301d] dark:text-[#34d399]">
                  {{ sig.verification_status || 'Verified' }}
                </span>
                <span class="px-2 py-0.5 rounded text-xs font-mono text-ink-muted bg-surface-muted">
                  {{ sig.trust_tier || 'Development Certificate' }}
                </span>
              </div>
              <div class="text-xs text-ink-muted">
                Signed: {{ formatDate(sig.signed_at || sig.created_at) }} · Format: {{ sig.signature_format || 'PAdES-B-LT' }}
              </div>
            </div>

            <div class="flex items-center gap-2">
              <span v-if="sigResults[sig.id]" class="text-xs font-medium text-[#1aae39] dark:text-[#34d399] flex items-center gap-1">
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
 class="bg-surface border border-hairline rounded-xl p-6 space-y-4"
 >
 <div class="flex items-center justify-between border-b border-hairline-subtle pb-3">
 <h2 class="text-xs font-semibold   text-ink-muted flex items-center gap-2">
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
 class="px-2 py-0.5 rounded-full text-xs font-semibold bg-[#e8f7ec] text-[#1aae39] dark:bg-[#14301d] dark:text-[#34d399] border border-[#1aae39]/20 flex items-center gap-1"
 >
 <CheckCheck class="w-3 h-3" />
 <span>Acknowledged on {{ formatDate(m.receipt.acknowledged_at) }}</span>
 </span>
 <span
 v-else
 class="px-2 py-0.5 rounded-full text-xs font-semibold bg-[#fff4e6] text-[#dd5b00] dark:bg-[#3b220c] dark:text-[#f59e0b] border border-[#dd5b00]/20 flex items-center gap-1"
 >
 <Clock class="w-3 h-3" />
 <span>Pending Office Receipt</span>
 </span>
 </div>

 <p v-if="m.instruction" class="text-ink-secondary italic text-xs">
 &ldquo;{{ m.instruction }}&rdquo;
 </p>

 <div class="text-xs text-ink-faint flex items-center gap-2 pt-1 border-t border-hairline-subtle">
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
 class="bg-surface border border-hairline rounded-xl p-6 space-y-4"
 >
 <div class="flex items-center justify-between border-b border-hairline-subtle pb-3">
 <h2 class="text-xs font-semibold   text-ink-muted flex items-center gap-2">
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
 class="px-2 py-0.5 rounded-full text-xs font-semibold bg-primary-subtle text-primary border border-primary/20"
 >
 Due: {{ formatDate(a.due_at) }}
 </span>
 </div>

 <p class="text-ink-secondary leading-relaxed text-xs">
 {{ a.instruction || (a as any).instructions }}
 </p>

 <div class="text-xs text-ink-faint flex items-center gap-2 pt-1 border-t border-hairline-subtle">
 <span>Assigned on: {{ formatDate(a.assigned_at) }}</span>
 <span v-if="a.assigned_by_user">&bull; By: {{ a.assigned_by_user.name }}</span>
 </div>
 </div>
 </div>
 </div>

 <!-- Physical Originals & Archival Preservation (Scenario B) -->
 <div
 v-if="document.physical_originals && document.physical_originals.length > 0"
 class="bg-surface border border-hairline rounded-xl p-6 space-y-4"
 >
 <div class="flex items-center justify-between border-b border-hairline-subtle pb-3">
 <h2 class="text-xs font-semibold   text-ink-muted flex items-center gap-2">
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
 Retention: {{ formatStatus(orig.retention_requirement) }}
 </span>
 </div>
 <span class="text-xs text-ink-faint">
 Registered: {{ formatDate(orig.created_at) }}
 </span>
 </div>

 <div v-if="orig.custody_events && orig.custody_events.length > 0" class="pt-2 border-t border-hairline-subtle space-y-2">
 <div class="text-xs font-semibold text-ink-muted">
 Custody Chain Log
 </div>
 <div
 v-for="ce in orig.custody_events"
 :key="ce.id"
 class="flex items-center justify-between text-xs bg-surface p-2 rounded-lg border border-hairline"
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
 <span class="text-ink-faint font-mono text-xs">
 {{ formatDate(ce.occurred_at) }}
 </span>
 </div>
 </div>
 </div>
 </div>
 </div>

 <!-- Institutional Archive Record (if transferred to archive) -->
 <div
 v-if="document.archive_entry || document.lifecycle === 'retired'"
 class="bg-surface border border-hairline rounded-xl p-6 space-y-4"
 >
 <div class="flex items-center justify-between border-b border-hairline-subtle pb-3">
 <div class="flex items-center gap-2">
 <Archive class="w-4 h-4 text-amber-600" />
 <h2 class="text-xs font-semibold   text-ink-muted">
 Archival Record & Custody Transfer
 </h2>
 </div>
 <router-link
 to="/archive"
 class="text-xs font-semibold text-primary hover:underline flex items-center gap-1"
 >
 <span>View in Archive Registry</span>
 <ArrowRight class="w-3.5 h-3.5" />
 </router-link>
 </div>

 <div class="p-4 rounded-xl bg-surface-hover border border-hairline space-y-2 text-xs">
 <div class="flex items-center justify-between">
 <div class="space-y-0.5">
 <div class="font-bold text-ink flex items-center gap-2">
 <span>Reference: {{ document.archive_entry?.archive_reference || document.reference_number }}</span>
 <span class="px-2 py-0.5 rounded text-xs font-bold   bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200">
 {{ document.lifecycle }}
 </span>
 </div>
 <div class="text-xs text-ink-muted">
 Collection: {{ document.archive_entry?.collection?.name || 'General University Records Collection' }}
 </div>
 </div>
 <span v-if="document.archive_entry?.archived_at" class="text-xs text-ink-faint">
 Archived: {{ formatDate(document.archive_entry.archived_at) }}
 </span>
 </div>
 </div>
 </div>

 <!-- Unified Institutional Lifecycle Timeline -->
 <div class="bg-surface border border-hairline rounded-xl p-6 space-y-4">
 <div class="flex items-center justify-between border-b border-hairline-subtle pb-3">
 <h2 class="text-xs font-semibold   text-ink-muted flex items-center gap-2">
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
 class="px-2 py-0.5 rounded-full text-xs font-semibold border"
 :class="getEventBadgeColor(ev.event_type)"
 >
 {{ ev.event_type.replace(/_/g, ' ') }}
 </span>
 </div>
 <p v-if="ev.description" class="text-xs text-ink-secondary leading-relaxed">
 {{ ev.description }}
 </p>
 </div>

 <span class="text-xs font-mono text-ink-faint shrink-0 whitespace-nowrap">
 {{ formatDate(ev.timestamp) }}
 </span>
 </div>

 <div class="flex items-center gap-3 text-xs text-ink-faint flex-wrap">
 <span v-if="ev.actor" class="flex items-center gap-1 font-medium text-ink-secondary">
 <User class="w-3 h-3" />
 {{ ev.actor.name }}
 </span>
 <span v-if="ev.metadata?.action" class="font-mono text-xs">
 Action: {{ ev.metadata.action }}
 </span>
 <span v-if="ev.metadata?.version_number" class="font-mono text-xs">
 v{{ ev.metadata.version_number }}
 </span>
 <span v-if="ev.metadata?.from_unit" class="text-xs">
 {{ ev.metadata.from_unit }} &rarr; {{ ev.metadata.to_unit }}
 </span>
 <span v-if="ev.metadata?.is_fulfilling !== undefined" class="text-xs">
 Fulfilling: {{ ev.metadata.is_fulfilling ? 'Yes' : 'No' }}
 </span>
 </div>
 </div>
 </div>

 <div v-else class="text-xs text-ink-faint italic py-6 text-center">
 No lifecycle timeline events recorded for this document yet.
 </div>
 </div>
 </div>

 <!-- Sidebar Attributes (Right 1 col) -->
 <div class="space-y-6">
 <!-- Institutional Ownership Card -->
 <div class="bg-surface border border-hairline rounded-xl p-5 space-y-4">
 <h3 class="text-xs font-semibold   text-ink-muted flex items-center gap-2 border-b border-hairline-subtle pb-2">
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
 <label class="block text-xs font-semibold   text-ink-secondary mb-1">
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
 <label class="block text-xs font-semibold   text-ink-secondary mb-1">
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
 <p class="text-xs text-ink-muted">
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
 <label class="block text-xs font-semibold   text-ink-secondary mb-1">
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
 <label class="block text-xs font-semibold   text-ink-secondary mb-1">
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
 <label class="block text-xs font-semibold   text-ink-secondary mb-1">
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

  <!-- Edit Metadata Modal -->
  <ModalDialog
    :is-open="showEditMetadataModal"
    title="Edit Document Metadata"
    @close="showEditMetadataModal = false"
  >
    <form @submit.prevent="submitEditMetadata" class="space-y-4">
      <BaseInput
        v-model="editMetadataForm.title"
        label="Document Title *"
        required
        placeholder="Enter official document title"
      />

      <BaseTextarea
        v-model="editMetadataForm.summary"
        label="Summary & Purpose"
        rows="3"
        placeholder="Brief summary of document subject matter..."
      />

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold   text-ink-secondary mb-1">
            Category *
          </label>
          <select
            v-model="editMetadataForm.category_id"
            class="w-full text-xs rounded-lg border border-hairline bg-surface text-ink px-3 py-2 focus:outline-none focus:border-primary"
            required
          >
            <option v-for="c in metaStore.categories" :key="c.id" :value="c.id">
              {{ c.name }}
            </option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-semibold   text-ink-secondary mb-1">
            Security Classification *
          </label>
          <select
            v-model="editMetadataForm.confidentiality_id"
            class="w-full text-xs rounded-lg border border-hairline bg-surface text-ink px-3 py-2 focus:outline-none focus:border-primary"
            required
          >
            <option v-for="cl in metaStore.confidentialityLevels" :key="cl.id" :value="cl.id">
              {{ cl.name }}
            </option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <BaseInput
          v-model="editMetadataForm.document_date"
          type="date"
          label="Document Date *"
          required
        />

        <div>
          <label class="block text-xs font-semibold   text-ink-secondary mb-1">
            Custodian Officer
          </label>
          <select
            v-model="editMetadataForm.custodian_id"
            class="w-full text-xs rounded-lg border border-hairline bg-surface text-ink px-3 py-2 focus:outline-none focus:border-primary"
          >
            <option value="">Unassigned</option>
            <option v-for="u in userOptions" :key="u.id" :value="u.id">
              {{ u.name }} ({{ u.email }})
            </option>
          </select>
        </div>
      </div>

      <div>
        <label class="block text-xs font-semibold   text-ink-secondary mb-1">
          Lifecycle State
        </label>
        <select
          v-model="editMetadataForm.lifecycle"
          class="w-full text-xs rounded-lg border border-hairline bg-surface text-ink px-3 py-2 focus:outline-none focus:border-primary"
        >
          <option value="active">Active</option>
          <option value="retired">Retired / Superseded</option>
        </select>
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <BaseButton variant="ghost" size="sm" type="button" @click="showEditMetadataModal = false">
          Cancel
        </BaseButton>
        <BaseButton variant="primary" size="sm" type="submit" :loading="isSavingMetadata">
          Save Metadata
        </BaseButton>
      </div>
    </form>
  </ModalDialog>

  <!-- Submit to Workflow Modal -->
  <ModalDialog
    :is-open="showSubmitWorkflowModal"
    title="Submit Document to Approval Workflow"
    @close="showSubmitWorkflowModal = false"
  >
    <form @submit.prevent="submitToWorkflow" class="space-y-4">
      <p class="text-xs text-ink-muted">
        Select an approved institutional workflow definition to route this document revision through review, endorsement, and administrative decisions.
      </p>

      <div>
        <label class="block text-xs font-semibold   text-ink-secondary mb-1">
          Workflow Definition *
        </label>
        <select
          v-model="selectedWorkflowDefId"
          class="w-full text-xs rounded-lg border border-hairline bg-surface text-ink px-3 py-2 focus:outline-none focus:border-primary"
          required
        >
          <option v-for="wf in workflowDefinitions" :key="wf.id" :value="wf.id">
            {{ wf.name }} ({{ wf.code }})
          </option>
        </select>
      </div>

      <div>
        <label class="block text-xs font-semibold   text-ink-secondary mb-1">
          Target Document Revision *
        </label>
        <select
          v-model="selectedVersionForWorkflow"
          class="w-full text-xs rounded-lg border border-hairline bg-surface text-ink px-3 py-2 focus:outline-none focus:border-primary"
          required
        >
          <option v-for="ver in fileBackedVersions" :key="ver.id" :value="ver.id">
            Revision v{{ ver.version_number }}.0 &mdash; {{ ver.change_note || 'Registered version' }}
          </option>
        </select>
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <BaseButton variant="ghost" size="sm" type="button" @click="showSubmitWorkflowModal = false">
          Cancel
        </BaseButton>
        <BaseButton variant="primary" size="sm" type="submit" :loading="isSubmittingWorkflow">
          Submit to Workflow
        </BaseButton>
      </div>
    </form>
  </ModalDialog>

  <!-- Quick Task Decision Modal -->
  <ModalDialog
    :is-open="showTaskDecisionModal"
    :title="`Execute Decision: ${formatStatus(decisionType)}`"
    @close="showTaskDecisionModal = false"
  >
    <form @submit.prevent="submitTaskDecision" class="space-y-4">
      <p class="text-xs text-ink-muted">
        You are recording a formal administrative decision on Task #{{ selectedWorkflowTask?.id }} (State: {{ selectedWorkflowTask?.state?.name }}).
      </p>

      <div>
        <label class="block text-xs font-semibold   text-ink-secondary mb-1">
          Administrative Justification / Reason {{ decisionType === 'reject' ? '*' : '(Optional)' }}
        </label>
        <BaseTextarea
          v-model="decisionReason"
          rows="4"
          :placeholder="decisionType === 'reject' ? 'Required: Provide clear institutional rejection grounds...' : 'Optional administrative notes or directive remarks...'"
        />
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <BaseButton variant="ghost" size="sm" type="button" @click="showTaskDecisionModal = false">
          Cancel
        </BaseButton>
        <BaseButton
          :variant="decisionType === 'approve' ? 'primary' : decisionType === 'reject' ? 'danger' : 'outline'"
          size="sm"
          type="submit"
          :loading="isSubmittingDecision"
        >
          Confirm {{ formatStatus(decisionType) }}
        </BaseButton>
      </div>
    </form>
  </ModalDialog>

  <!-- Version Comparison Modal -->
  <ModalDialog
    :is-open="showCompareModal"
    title="Compare Document Revisions (Cryptographic & Content Diff)"
    @close="showCompareModal = false"
  >
    <div class="space-y-4 text-xs">
      <div class="grid grid-cols-2 gap-3 p-3 rounded-xl bg-surface-hover border border-hairline">
        <BaseSelect
          v-model="compareVersionA"
          label="Baseline Revision (Version A)"
          :options="(document?.versions || []).map(v => ({ label: `v${v.version_number}.0 — ${v.change_note || 'Committed'} (${formatDate(v.created_at)})`, value: v.id }))"
          @change="runVersionComparison"
        />

        <BaseSelect
          v-model="compareVersionB"
          label="Target Revision (Version B)"
          :options="(document?.versions || []).map(v => ({ label: `v${v.version_number}.0 — ${v.change_note || 'Committed'} (${formatDate(v.created_at)})`, value: v.id }))"
          @change="runVersionComparison"
        />
      </div>

      <div v-if="isComparing" class="py-8 text-center text-ink-muted">
        <RefreshCw class="w-6 h-6 animate-spin mx-auto mb-2 text-primary" />
        Evaluating revision differences and file checksums...
      </div>

      <div v-else-if="comparisonResult" class="space-y-3">
        <!-- Result Banner -->
        <div
          class="p-3 rounded-xl border flex items-center justify-between"
          :class="comparisonResult.comparison.content_identical
            ? 'bg-emerald-50 dark:bg-emerald-950/20 border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300'
            : 'bg-amber-50 dark:bg-amber-950/20 border-amber-300 dark:border-amber-800 text-amber-800 dark:text-amber-300'"
        >
          <div class="flex items-center gap-2">
            <span class="font-bold text-sm">
              {{ comparisonResult.comparison.content_identical ? 'Content Identical (Zero Binary Drift)' : 'Material Revision Detected' }}
            </span>
          </div>
          <span class="px-2 py-0.5 rounded text-xs font-mono font-semibold   bg-white/60 dark:bg-black/30">
            Delta: {{ comparisonResult.comparison.version_delta > 0 ? '+' : '' }}{{ comparisonResult.comparison.version_delta }} version(s)
          </span>
        </div>

        <!-- Side by Side Details -->
        <div class="grid grid-cols-2 gap-3">
          <!-- Version A Card -->
          <div class="p-3 rounded-xl bg-surface border border-hairline space-y-2">
            <div class="font-bold text-ink flex items-center justify-between">
              <span>Version {{ comparisonResult.version_a.version_number }}.0</span>
              <span class="text-xs text-ink-faint tabular-nums">{{ formatDate(comparisonResult.version_a.created_at) }}</span>
            </div>
            <div class="text-xs text-ink-secondary">
              Note: {{ comparisonResult.version_a.change_note || 'None' }}
            </div>
            <div class="text-xs text-ink-muted">
              Committed by: {{ comparisonResult.version_a.creator?.name || 'Staff' }}
            </div>
            <div class="border-t border-hairline pt-2 space-y-1">
              <span class="text-xs font-bold  text-ink-muted">Files ({{ comparisonResult.version_a.files.length }})</span>
              <div v-for="f in comparisonResult.version_a.files" :key="f.id" class="text-xs truncate">{{ f.filename }} ({{ formatFileSize(f.byte_size) }})
              </div>
            </div>
          </div>

          <!-- Version B Card -->
          <div class="p-3 rounded-xl bg-surface border border-hairline space-y-2">
            <div class="font-bold text-ink flex items-center justify-between">
              <span>Version {{ comparisonResult.version_b.version_number }}.0</span>
              <span class="text-xs text-ink-faint tabular-nums">{{ formatDate(comparisonResult.version_b.created_at) }}</span>
            </div>
            <div class="text-xs text-ink-secondary">
              Note: {{ comparisonResult.version_b.change_note || 'None' }}
            </div>
            <div class="text-xs text-ink-muted">
              Committed by: {{ comparisonResult.version_b.creator?.name || 'Staff' }}
            </div>
            <div class="border-t border-hairline pt-2 space-y-1">
              <span class="text-xs font-bold  text-ink-muted">Files ({{ comparisonResult.version_b.files.length }})</span>
              <div v-for="f in comparisonResult.version_b.files" :key="f.id" class="text-xs truncate">{{ f.filename }} ({{ formatFileSize(f.byte_size) }})
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="flex justify-end pt-2">
        <BaseButton variant="ghost" size="sm" @click="showCompareModal = false">Close</BaseButton>
      </div>
    </div>
  </ModalDialog>

  <!-- Link Document Relationship Modal -->
  <ModalDialog
    :is-open="showAddRelationModal"
    title="Establish Cross-Reference / Document Relationship"
    @close="showAddRelationModal = false"
  >
    <form @submit.prevent="submitAddRelation" class="space-y-4 text-xs">
      <div>
        <label class="block font-semibold   text-ink-secondary mb-1">
          Target Document ID / Record # *
        </label>
        <BaseInput
          v-model="relationForm.target_document_id"
          type="number"
          placeholder="e.g. 42"
          required
        />
      </div>

      <div>
        <label class="block font-semibold   text-ink-secondary mb-1">
          Relationship Type *
        </label>
        <BaseSelect
          v-model="relationForm.relation_type"
          :options="[
            { label: 'References (Cites or mentions target document)', value: 'references' },
            { label: 'Supersedes (Formally replaces older document)', value: 'supersedes' },
            { label: 'Supplements (Adds supporting addendum or annex)', value: 'supplements' },
            { label: 'Responds To (Formal institutional reply)', value: 'responds_to' },
          ]"
          required
        />
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <BaseButton variant="ghost" size="sm" type="button" @click="showAddRelationModal = false">Cancel</BaseButton>
        <BaseButton variant="primary" size="sm" type="submit" :loading="isSubmittingRelation">Establish Link</BaseButton>
      </div>
    </form>
  </ModalDialog>
 </div>
</template>

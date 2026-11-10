<script setup lang="ts">
import { ref, onMounted, watchEffect } from 'vue'
import { useRouter } from 'vue-router'
import { documentsApi, organizationUnitsApi } from '@/api/client'
import { useMetadataStore } from '@/stores/metadata'
import { useAuthStore } from '@/stores/auth'
import type { OrganizationUnit } from '@/types'
import { useToast } from '@/composables/useToast'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseAlert from '@/components/ui/BaseAlert.vue'
import { ArrowLeft, UploadCloud, FileText, CheckCircle2 } from 'lucide-vue-next'

const router = useRouter()
const metaStore = useMetadataStore()
const authStore = useAuthStore()
const toast = useToast()

const title = ref('')
const summary = ref('')
const categoryId = ref<number | ''>('')
const confidentialityId = ref<number | ''>('')
const ownerUnitId = ref<number | ''>('')
const documentDate = ref(new Date().toISOString().split('T')[0])
const changeNote = ref('Initial registered version')
const file = ref<File | null>(null)
const fileName = ref('')
const fileSize = ref('')

const units = ref<OrganizationUnit[]>([])
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)

async function loadUnits() {
 try {
 const res = await organizationUnitsApi.list({ per_page: 100 })
 units.value = (res.data as any).data || []
 const preferred = authStore.primaryUnit?.unit_id || units.value[0]?.id || ''
 if (preferred) {
 ownerUnitId.value = preferred
 }
 } catch (err) {
 console.error('Failed to load units', err)
 }
}

function handleFileChange(event: Event) {
 const target = event.target as HTMLInputElement
 if (target.files && target.files[0]) {
 file.value = target.files[0]
 fileName.value = file.value.name
 fileSize.value = (file.value.size / (1024 * 1024)).toFixed(2) + ' MB'
 }
}

async function handleSubmit() {
 if (!title.value.trim()) {
 errorMessage.value = 'Please provide a document title.'
 return
 }
 if (!categoryId.value) {
 errorMessage.value = 'Please select a document category.'
 return
 }
 if (!confidentialityId.value) {
 errorMessage.value = 'Please select a security classification.'
 return
 }
 if (!ownerUnitId.value) {
 errorMessage.value = 'Please select the accountable organizational unit.'
 return
 }

 isLoading.value = true
 errorMessage.value = null

 try {
 const formData = new FormData()
 formData.append('title', title.value.trim())
 if (summary.value.trim()) {
 formData.append('summary', summary.value.trim())
 }
 formData.append('category_id', String(categoryId.value))
 formData.append('confidentiality_id', String(confidentialityId.value))
 formData.append('owner_unit_id', String(ownerUnitId.value))
 formData.append('document_date', documentDate.value)
 formData.append('change_note', changeNote.value.trim())

 if (file.value) {
 formData.append('file', file.value)
 }

 const res = await documentsApi.create(formData)
 const doc = res.data.data
 toast.success(`Document registered successfully with reference ${doc.reference_number}.`)
 router.push(`/documents/${doc.id}`)
 } catch (err: any) {
 errorMessage.value = err.response?.data?.message || 'Failed to register document.'
 } finally {
 isLoading.value = false
 }
}

onMounted(async () => {
 await Promise.all([metaStore.loadAll(), loadUnits()])
})

watchEffect(() => {
 if (!categoryId.value && metaStore.categories.length > 0) {
  categoryId.value = metaStore.categories[0].id
 }
 if (!confidentialityId.value && metaStore.confidentialityLevels.length > 0) {
  confidentialityId.value = metaStore.confidentialityLevels[0].id
 }
 if (!ownerUnitId.value && units.value.length > 0) {
  const preferred = authStore.primaryUnit?.unit_id || units.value[0].id
  ownerUnitId.value = preferred
 }
})
</script>

<template>
 <div class="max-w-4xl mx-auto space-y-6">
 <!-- Header -->
 <div class="flex items-center gap-4">
 <BaseButton variant="ghost" size="sm" @click="router.back()">
 <ArrowLeft class="w-4 h-4" />
 </BaseButton>
 <div>
 <div class="notion-eyebrow text-primary mb-1.5">
 RECORDS & ARCHIVES / REGISTER DOCUMENT
 </div>
 <h1 class="notion-heading-2 text-ink flex items-center gap-2">
 <span>Register New Institutional Record</span>
 </h1>
 <p class="notion-body-sm text-ink-muted mt-0.5">
 Submit official university records into the repository with automated canonical reference number generation.
 </p>
 </div>
 </div>

 <!-- Error Alert -->
 <BaseAlert v-if="errorMessage" variant="danger" dismissible @dismiss="errorMessage = null">
 {{ errorMessage }}
 </BaseAlert>

 <!-- Registration Form -->
 <form @submit.prevent="handleSubmit" class="space-y-6">
 <!-- Section 1: Metadata Card -->
 <div class="bg-surface border border-hairline rounded-xl p-6 shadow-xs space-y-5">
 <h2 class="text-sm font-semibold text-ink uppercase tracking-wider border-b border-hairline-subtle pb-3 flex items-center gap-2">
 <FileText class="w-4 h-4 text-primary" />
 <span>Record Identification & Classification</span>
 </h2>

 <div class="space-y-4">
 <div>
 <label class="block text-xs font-semibold uppercase tracking-wider text-ink-secondary mb-1">
 Document Title *
 </label>
 <BaseInput
 v-model="title"
 placeholder="e.g., Wollo University Senate Guideline on Graduate Research 2026"
 required
 />
 </div>

 <div>
 <label class="block text-xs font-semibold uppercase tracking-wider text-ink-secondary mb-1">
 Descriptive Summary
 </label>
 <BaseTextarea
 v-model="summary"
 rows="3"
 placeholder="Brief summary of institutional record contents..."
 />
 </div>

 <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
 <BaseSelect
 v-model="categoryId"
 label="Document Category *"
 :options="metaStore.categories.map((c) => ({ label: c.name, value: c.id }))"
 required
 />

 <BaseSelect
 v-model="confidentialityId"
 label="Confidentiality Classification *"
 :options="metaStore.confidentialityLevels.map((c) => ({ label: c.name, value: c.id }))"
 required
 />
 </div>

 <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
 <BaseSelect
 v-model="ownerUnitId"
 label="Accountable Office / Unit *"
 :options="units.map((u) => ({ label: `${u.name} (${u.code})`, value: u.id }))"
 required
 />

 <div>
 <label class="block text-xs font-semibold uppercase tracking-wider text-ink-secondary mb-1">
 Document Date *
 </label>
 <BaseInput
 v-model="documentDate"
 type="date"
 required
 />
 </div>
 </div>
 </div>
 </div>

 <!-- Section 2: File Upload Card -->
 <div class="bg-surface border border-hairline rounded-xl p-6 shadow-xs space-y-4">
 <h2 class="text-sm font-semibold text-ink uppercase tracking-wider border-b border-hairline-subtle pb-3 flex items-center gap-2">
 <UploadCloud class="w-4 h-4 text-primary" />
 <span>Primary Digital Asset (Version 1)</span>
 </h2>

 <!-- Dropzone Area -->
 <div class="border-2 border-dashed border-hairline hover:border-primary dark:hover:border-[#62aef0] rounded-xl p-8 text-center transition-all bg-surface-hover relative">
 <input
 type="file"
 id="file-upload"
 class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
 @change="handleFileChange"
 />

 <div v-if="!file" class="space-y-2 pointer-events-none">
 <UploadCloud class="w-10 h-10 text-ink-faint mx-auto" />
 <div class="text-sm font-semibold text-ink-secondary">
 Drag and drop document file here, or click to browse
 </div>
 <div class="text-xs text-ink-faint">
 Supported formats: PDF, DOCX, XLSX, Images (maximum 100MB). Cryptographic SHA-256 integrity hash computed automatically.
 </div>
 </div>

 <div v-else class="flex items-center justify-center gap-3 pointer-events-none">
 <CheckCircle2 class="w-8 h-8 text-[#1aae39] shrink-0" />
 <div class="text-left">
 <div class="text-sm font-semibold text-ink truncate max-w-xs">{{ fileName }}</div>
 <div class="text-xs text-ink-faint">{{ fileSize }}</div>
 </div>
 </div>
 </div>

 <div>
 <label class="block text-xs font-semibold uppercase tracking-wider text-ink-secondary mb-1">
 Initial Version Note
 </label>
 <BaseInput
 v-model="changeNote"
 placeholder="e.g., Initial registered baseline version"
 />
 </div>
 </div>

 <!-- Submit Actions -->
 <div class="flex items-center justify-end gap-3 pt-2">
 <BaseButton variant="outline" type="button" @click="router.back()">Cancel</BaseButton>
 <BaseButton variant="primary" type="submit" :loading="isLoading" class="bg-primary hover:bg-primary-hover text-white px-6 py-2.5 shadow-xs">
 Complete Registration
 </BaseButton>
 </div>
 </form>
 </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watchEffect, watch } from 'vue'
import { useRouter } from 'vue-router'
import { documentsApi, organizationUnitsApi, officialLettersApi, templatesApi } from '@/api/client'
import { useMetadataStore } from '@/stores/metadata'
import { useAuthStore } from '@/stores/auth'
import type { OrganizationUnit } from '@/types'
import { useToast } from '@/composables/useToast'
import { gregorianToEthiopian } from '@/utils/ethiopianDate'
import PageHeader from '@/components/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseAlert from '@/components/ui/BaseAlert.vue'
import OfficialLetterA4Preview from '@/components/letters/OfficialLetterA4Preview.vue'
import {
  ArrowLeft,
  UploadCloud,
  FileText,
  CheckCircle2,
  FileSignature,
  Eye,
  Plus,
  X,
  Building2,
  Stamp,
  Calendar,
  Sparkles,
  Layers
} from 'lucide-vue-next'

const router = useRouter()
const metaStore = useMetadataStore()
const authStore = useAuthStore()
const toast = useToast()

// Mode: 'letter' (Official University Letter), 'template' (Institutional Template), or 'upload' (File/Scan Intake)
const creationMode = ref<'letter' | 'template' | 'upload'>('letter')

// Shared Metadata
const units = ref<OrganizationUnit[]>([])
const isLoading = ref(false)
const isPreviewingPdf = ref(false)
const errorMessage = ref<string | null>(null)

// -----------------------------------------------------------------------------
// Official Letter Generator Form State
// -----------------------------------------------------------------------------
const letterSubject = ref('')
const letterBody = ref('')
const recipientLead = ref('')
const recipientSub = ref('')
const recipientLocation = ref('ኮምቦልቻ፤')

const letterOwnerUnitId = ref<number | ''>('')
const letterConfidentialityId = ref<number | ''>('')
const letterCategoryId = ref<number | ''>('')
const letterDate = ref(new Date().toISOString().split('T')[0])

const signatoryName = ref('Tibebu Legesse Molla')
const signatoryTitleAm = ref('የኢንፎርሜሽን ቴክኖሎጂ ትምህርት ክፍል ኃላፊ')
const signatoryTitleEn = ref('Head Department of Information Technology')

const submitForApproval = ref(true)

// CC List State
const ccList = ref<string[]>([])
const newCcInput = ref('')

const commonOffices = [
  'ለምክትል ሳይንቲፊክ ዳይሬክተር ጽ/ቤት',
  'ለተማሪዎች ጉዳይ ዳይሬክተር ጽ/ቤት',
  'ለሬጅስትራር ጽ/ቤት',
  'ለኢንፎርማቲክስ ኮሌጅ',
  'ለኢንፎርማቲክስ ኮሌጅ ት/ት ጥራት አስተባባሪ ጽ/ቤት',
  'ለኢንፎርማቲክስ ኮሌጅ ምርምር፤ ማህበረሰብ አገልግሎትና ድህረ-ምረቃ ጽ/ቤት',
  'ለርቀትና ተከታታይ ጽ/ቤት',
  'ለኢንፎርሜሽን ቴክኖሎጂ ት/ት ክፍል',
]

// Computed Ethiopian Date
const ethiopianDateStr = computed(() => {
  if (!letterDate.value) return ''
  try {
    return gregorianToEthiopian(letterDate.value).formatted
  } catch {
    return ''
  }
})

// Unit hierarchy names
const uniAm = ref('ወሎ ዩኒቨርሲቲ')
const uniEn = ref('WOLLO UNIVERSITY')
const facultyAm = ref('ኮምቦልቻ ቴክኖሎጂ ኢንስቲትዩት')
const facultyEn = ref('KOMBOLCHA INSTITUTE OF TECHNOLOGY')
const deptAm = ref('ኢንፎርሜሽን ቴክኖሎጂ ትምህርት ክፍል')
const deptEn = ref('Information Technology Department')
const locationAm = ref('ኮምቦልቻ')
const locationEn = ref('Kombolcha')

function addCcItem(item?: string) {
  const toAdd = (item || newCcInput.value).trim()
  if (toAdd && !ccList.value.includes(toAdd)) {
    ccList.value.push(toAdd)
    if (!item) newCcInput.value = ''
  }
}

function removeCcItem(index: number) {
  ccList.value.splice(index, 1)
}

async function handleGenerateOfficialLetter() {
  if (!letterSubject.value.trim()) {
    errorMessage.value = 'Please enter a letter subject (ጉዳዩ).'
    return
  }
  if (!letterBody.value.trim()) {
    errorMessage.value = 'Please provide letter body content.'
    return
  }
  if (!letterOwnerUnitId.value) {
    errorMessage.value = 'Please select the accountable organizational unit.'
    return
  }

  isLoading.value = true
  errorMessage.value = null

  try {
    const payload = {
      subject: letterSubject.value.trim(),
      body: letterBody.value.trim(),
      recipient_lead: recipientLead.value.trim(),
      recipient_sub: recipientSub.value.trim(),
      recipient_location: recipientLocation.value.trim(),
      cc_list: ccList.value,
      signatory_name: signatoryName.value.trim(),
      signatory_title_am: signatoryTitleAm.value.trim(),
      signatory_title_en: signatoryTitleEn.value.trim(),
      document_date: letterDate.value,
      ethiopian_date: ethiopianDateStr.value,
      owner_unit_id: Number(letterOwnerUnitId.value),
      category_id: letterCategoryId.value ? Number(letterCategoryId.value) : undefined,
      confidentiality_id: letterConfidentialityId.value ? Number(letterConfidentialityId.value) : undefined,
      submit_for_approval: submitForApproval.value,
    }

    const res = await officialLettersApi.generate(payload)
    const doc = (res.data as any).data
    toast.success(`Official letter generated successfully with reference ${doc.reference_number}!`)
    router.push(`/documents/${doc.id}`)
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to generate official letter.'
  } finally {
    isLoading.value = false
  }
}

async function handleDownloadLivePreviewPdf() {
  isPreviewingPdf.value = true
  try {
    const res = await officialLettersApi.preview({
      subject: letterSubject.value.trim() || 'Untitled Letter',
      body: letterBody.value.trim() || 'Official university letter preview',
      recipient_lead: recipientLead.value.trim(),
      recipient_sub: recipientSub.value.trim(),
      recipient_location: recipientLocation.value.trim(),
      cc_list: ccList.value,
      signatory_name: signatoryName.value.trim(),
      signatory_title_am: signatoryTitleAm.value.trim(),
      signatory_title_en: signatoryTitleEn.value.trim(),
      document_date: letterDate.value,
      ethiopian_date: ethiopianDateStr.value,
    })

    const base64 = res.data.data.pdf_base64
    const byteCharacters = atob(base64)
    const byteNumbers = new Array(byteCharacters.length)
    for (let i = 0; i < byteCharacters.length; i++) {
      byteNumbers[i] = byteCharacters.charCodeAt(i)
    }
    const byteArray = new Uint8Array(byteNumbers)
    const blob = new Blob([byteArray], { type: 'application/pdf' })
    const url = URL.createObjectURL(blob)
    window.open(url, '_blank')
    toast.success('Live print-ready A4 PDF opened in preview window.')
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to generate live preview PDF.')
  } finally {
    isPreviewingPdf.value = false
  }
}

// -----------------------------------------------------------------------------
// Digital / Physical Scan Upload Form State
// -----------------------------------------------------------------------------
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

function handleFileChange(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    file.value = target.files[0]
    fileName.value = file.value.name
    fileSize.value = (file.value.size / (1024 * 1024)).toFixed(2) + ' MB'
  }
}

async function handleUploadSubmit() {
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

async function loadUnitsAndConfig() {
  try {
    const res = await organizationUnitsApi.list({ per_page: 100 })
    units.value = (res.data as any).data || []

    const preferred = authStore.activeUnitId || authStore.primaryUnit?.unit_id || units.value[0]?.id || ''
    if (preferred) {
      letterOwnerUnitId.value = preferred
      ownerUnitId.value = preferred
    }

    // Load official letters baseline config
    const configRes = await officialLettersApi.baselineConfig(preferred ? Number(preferred) : undefined)
    const cfg = configRes.data.data
    if (cfg) {
      uniAm.value = cfg.uni_am || uniAm.value
      uniEn.value = cfg.uni_en || uniEn.value
      facultyAm.value = cfg.faculty_am || facultyAm.value
      facultyEn.value = cfg.faculty_en || facultyEn.value
      deptAm.value = cfg.dept_am || deptAm.value
      deptEn.value = cfg.dept_en || deptEn.value
    }
  } catch (err) {
    console.error('Failed to load initial data:', err)
  }
}

// -----------------------------------------------------------------------------
// Institutional Template Intake Form State & Methods
// -----------------------------------------------------------------------------
const templatesList = ref<any[]>([])
const selectedTemplateId = ref<number | ''>('')
const templateOwnerUnitId = ref<number | ''>('')
const templateCategoryId = ref<number | ''>('')
const templateConfidentialityId = ref<number | ''>('')
const templateDate = ref(new Date().toISOString().split('T')[0])
const templateSubmitForApproval = ref(true)

const templateFields = ref<Record<string, string>>({
  recipient: '',
  subject: '',
  body: '',
  reference_date: new Date().toISOString().split('T')[0],
})

const selectedTemplate = computed(() => {
  return templatesList.value.find((t) => t.id === Number(selectedTemplateId.value)) || null
})

const templateLivePreviewHtml = computed(() => {
  if (!selectedTemplate.value) return ''
  const raw = selectedTemplate.value.published_version?.body_template_html || ''
  if (!raw) return '<div class="p-6 text-center text-xs text-ink-muted">No published content available for this template.</div>'

  let rendered = raw
  for (const [key, val] of Object.entries(templateFields.value)) {
    const escaped = (val || `[${key}]`).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    rendered = rendered.replace(
      new RegExp(`\\{\\{\\s*${key}\\s*\\}\\}`, 'g'),
      escaped
    )
  }
  return rendered
})

async function loadTemplatesList() {
  try {
    const res = await templatesApi.list({ per_page: 50 })
    templatesList.value = (res.data as any).data || []
    if (templatesList.value.length > 0 && !selectedTemplateId.value) {
      selectedTemplateId.value = templatesList.value[0].id
    }
  } catch (err) {
    console.error('Failed to load templates list', err)
  }
}

watch(selectedTemplateId, (newId) => {
  if (newId && selectedTemplate.value) {
    if (selectedTemplate.value.category_id) {
      templateCategoryId.value = selectedTemplate.value.category_id
    }
    if (selectedTemplate.value.owner_unit_id) {
      templateOwnerUnitId.value = selectedTemplate.value.owner_unit_id
    }
  }
})

async function handleGenerateFromTemplate() {
  if (!selectedTemplate.value) {
    errorMessage.value = 'Please select an institutional document template.'
    return
  }
  if (!templateFields.value.subject?.trim()) {
    errorMessage.value = 'Please enter a document subject / title.'
    return
  }
  if (!templateFields.value.recipient?.trim()) {
    errorMessage.value = 'Please specify the addressed recipient.'
    return
  }
  if (!templateFields.value.body?.trim()) {
    errorMessage.value = 'Please provide the document body content.'
    return
  }
  if (!templateOwnerUnitId.value) {
    errorMessage.value = 'Please select the accountable organizational unit.'
    return
  }

  isLoading.value = true
  errorMessage.value = null

  try {
    const payload = {
      template_data: {
        recipient: templateFields.value.recipient.trim(),
        subject: templateFields.value.subject.trim(),
        body: templateFields.value.body.trim(),
        reference_date: templateDate.value,
      },
      title: templateFields.value.subject.trim(),
      summary: `Generated from template "${selectedTemplate.value.name}" (${selectedTemplate.value.code})`,
      category_id: Number(templateCategoryId.value || metaStore.categories[0]?.id || 1),
      confidentiality_id: Number(templateConfidentialityId.value || metaStore.confidentialityLevels[0]?.id || 1),
      owner_unit_id: Number(templateOwnerUnitId.value),
      document_date: templateDate.value,
      submit_for_approval: templateSubmitForApproval.value,
    }

    const res = await templatesApi.generateDocument(selectedTemplate.value.id, payload)
    const doc = (res.data as any).data
    toast.success(`Document registered successfully from template with reference #${doc.reference_number || doc.id}!`)
    router.push(`/documents/${doc.id}`)
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to generate document from template.'
  } finally {
    isLoading.value = false
  }
}

onMounted(async () => {
  await Promise.all([metaStore.loadAll(), loadUnitsAndConfig(), loadTemplatesList()])
})

watchEffect(() => {
  if (!categoryId.value && metaStore.categories.length > 0) {
    categoryId.value = metaStore.categories[0].id
    letterCategoryId.value = metaStore.categories[0].id
    templateCategoryId.value = metaStore.categories[0].id
  }
  if (!confidentialityId.value && metaStore.confidentialityLevels.length > 0) {
    confidentialityId.value = metaStore.confidentialityLevels[0].id
    letterConfidentialityId.value = metaStore.confidentialityLevels[0].id
    templateConfidentialityId.value = metaStore.confidentialityLevels[0].id
  }
  if (!ownerUnitId.value && units.value.length > 0) {
    const preferred = authStore.activeUnitId || authStore.primaryUnit?.unit_id || units.value[0].id
    ownerUnitId.value = preferred
    letterOwnerUnitId.value = preferred
    templateOwnerUnitId.value = preferred
  }
})
</script>

<template>
  <div class="max-w-7xl mx-auto space-y-6">
    <!-- Header with Breadcrumb Back -->
    <div class="flex items-center gap-4 border-b border-hairline pb-4">
      <BaseButton variant="ghost" size="sm" @click="router.back()">
        <ArrowLeft class="w-4 h-4" />
      </BaseButton>
      <div class="flex-1">
        <PageHeader
          title="Register New Institutional Record"
          subtitle="Create authentic Wollo University letters matching institutional standards or register external document records."
        />
      </div>
    </div>

    <!-- Creation Mode Selector Pill -->
    <div class="flex justify-center">
      <div class="inline-flex rounded-xl p-1 bg-surface-hover border border-hairline">
        <button
          type="button"
          class="flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-semibold transition-all"
          :class="creationMode === 'letter' ? 'bg-primary text-white' : 'text-ink-secondary hover:text-ink'"
          @click="creationMode = 'letter'"
        >
          <FileSignature class="w-4 h-4" />
          <span>Official University Letter</span>
        </button>
        <button
          type="button"
          class="flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-semibold transition-all"
          :class="creationMode === 'template' ? 'bg-primary text-white' : 'text-ink-secondary hover:text-ink'"
          @click="creationMode = 'template'"
        >
          <FileText class="w-4 h-4" />
          <span>From Institutional Template</span>
        </button>
        <button
          type="button"
          class="flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-semibold transition-all"
          :class="creationMode === 'upload' ? 'bg-primary text-white' : 'text-ink-secondary hover:text-ink'"
          @click="creationMode === 'upload'"
        >
          <UploadCloud class="w-4 h-4" />
          <span>Physical / Digital Scan Intake</span>
        </button>
      </div>
    </div>

    <!-- Error Alert -->
    <BaseAlert v-if="errorMessage" variant="danger" dismissible @dismiss="errorMessage = null">
      {{ errorMessage }}
    </BaseAlert>

    <!-- ===================================================================== -->
    <!-- MODE 1: OFFICIAL UNIVERSITY LETTER GENERATOR -->
    <!-- ===================================================================== -->
    <div v-if="creationMode === 'letter'" class="space-y-6">
      <!-- Institutional Drafting Guideline Header -->
      <div class="bg-surface border border-hairline rounded-xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs text-ink-secondary">
        <div class="flex items-center gap-2 font-medium text-ink">
          <Building2 class="w-4 h-4 text-primary shrink-0" />
          <span>Wollo University Official Letter Drafter</span>
        </div>
        <div class="text-xs text-ink-muted">
          Generates authentic A4 institutional letters with Nyala Ethiopic typography, automatic Ethiopian calendar conversion, and server-authoritative audit tracking.
        </div>
      </div>

      <!-- Main Layout: 2 Columns (Form Left, Live A4 Preview Right) -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        <!-- Left: Form Controls (7 cols on lg) -->
        <div class="lg:col-span-7 space-y-6">
          <form @submit.prevent="handleGenerateOfficialLetter" class="space-y-6">
            <!-- 1. Administrative Context & Date -->
            <div class="bg-surface border border-hairline rounded-xl p-5 space-y-4">
              <h2 class="text-xs font-semibold   text-ink-muted border-b border-hairline-subtle pb-2 flex items-center gap-2">
                <Building2 class="w-4 h-4 text-primary" />
                <span>Issuing Administrative Context</span>
              </h2>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <BaseSelect
                  v-model="letterOwnerUnitId"
                  label="Issuing Department / Office *"
                  :options="units.map((u) => ({ label: `${u.name} (${u.code})`, value: u.id }))"
                  required
                />
                <BaseSelect
                  v-model="letterConfidentialityId"
                  label="Classification Level *"
                  :options="metaStore.confidentialityLevels.map((c) => ({ label: c.name, value: c.id }))"
                  required
                />
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                <div>
                  <label class="block text-xs font-semibold   text-ink-secondary mb-1">
                    Gregorian Date *
                  </label>
                  <BaseInput v-model="letterDate" type="date" required />
                </div>
                <div>
                  <label class="block text-xs font-semibold   text-ink-secondary mb-1">
                    Ethiopian Calendar (ቀን)
                  </label>
                  <div class="h-9 px-3 py-1.5 rounded-xl bg-surface-hover border border-hairline text-ink font-mono text-xs flex items-center justify-between">
                    <span>{{ ethiopianDateStr || 'Auto-computed' }}</span>
                    <span class="text-xs text-primary bg-primary-subtle px-1.5 py-0.5 rounded font-semibold">Ethiopic</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- 2. Recipient Information Block -->
            <div class="bg-surface border border-hairline rounded-xl p-5 space-y-4">
              <h2 class="text-xs font-semibold   text-ink-muted border-b border-hairline-subtle pb-2 flex items-center gap-2">
                <FileText class="w-4 h-4 text-primary" />
                <span>Recipient Details (ለ...)</span>
              </h2>

              <div class="space-y-3">
                <div>
                  <label class="block text-xs font-semibold   text-ink-secondary mb-1">
                    Addressed Recipient / Lead Title *
                  </label>
                  <BaseInput
                    v-model="recipientLead"
                    placeholder="e.g. ለመ/ር መላክ ተሾመ or ለኢንፎርማቲክስ ኮሌጅ"
                    required
                  />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-semibold   text-ink-secondary mb-1">
                      Recipient Office / Sub-Unit (Optional)
                    </label>
                    <BaseInput
                      v-model="recipientSub"
                      placeholder="e.g. ኢንፎርሜሽን ቴክኖሎጂ ት/ት ክፍል"
                    />
                  </div>
                  <div>
                    <label class="block text-xs font-semibold   text-ink-secondary mb-1">
                      Campus / Location *
                    </label>
                    <BaseInput
                      v-model="recipientLocation"
                      placeholder="e.g. ኮምቦልቻ፤"
                      required
                    />
                  </div>
                </div>
              </div>
            </div>

            <!-- 3. Subject & Letter Content -->
            <div class="bg-surface border border-hairline rounded-xl p-5 space-y-4">
              <h2 class="text-xs font-semibold   text-ink-muted border-b border-hairline-subtle pb-2 flex items-center gap-2">
                <FileText class="w-4 h-4 text-primary" />
                <span>Letter Subject & Content</span>
              </h2>

              <div>
                <label class="block text-xs font-semibold   text-ink-secondary mb-1">
                  Subject / ጉዳዩ * (Centered, bold, underlined on letter)
                </label>
                <BaseInput
                  v-model="letterSubject"
                  placeholder="e.g. ውክልና መስጠትን ይመለከታል"
                  required
                />
              </div>

              <div>
                <label class="block text-xs font-semibold   text-ink-secondary mb-1">
                  Letter Body * (Paragraphs formatted in Nyala institutional type)
                </label>
                <BaseTextarea
                  v-model="letterBody"
                  rows="7"
                  placeholder="Type letter text in Amharic or English. Paragraph breaks are preserved with authentic indentations..."
                  required
                />
              </div>
            </div>

            <!-- 4. Copy-Distribution (CC / ግልባጭ፤) -->
            <div class="bg-surface border border-hairline rounded-xl p-5 space-y-4">
              <div class="flex items-center justify-between border-b border-hairline-subtle pb-2">
                <h2 class="text-xs font-semibold   text-ink-muted flex items-center gap-2">
                  <FileText class="w-4 h-4 text-primary" />
                  <span>Copy-Distribution / ግልባጭ፤ ({{ ccList.length }})</span>
                </h2>
                <span class="text-xs text-ink-faint">Appears on lower-left of letter</span>
              </div>

              <!-- Quick Add Common Offices -->
              <div>
                <label class="block text-xs font-semibold text-ink-faint mb-1.5">
                  Quick-Add Common Offices:
                </label>
                <div class="flex flex-wrap gap-1.5">
                  <button
                    v-for="office in commonOffices"
                    :key="office"
                    type="button"
                    class="px-2 py-1 rounded-md text-xs bg-surface-hover hover:bg-primary-subtle hover:text-primary border border-hairline transition-all text-left truncate max-w-[280px] sm:max-w-xs"
                    @click="addCcItem(office)"
                  >
                    + {{ office }}
                  </button>
                </div>
              </div>

              <!-- Active CC List -->
              <div class="space-y-1.5 pt-1">
                <div
                  v-for="(cc, idx) in ccList"
                  :key="idx"
                  class="flex items-center justify-between p-2 rounded-lg bg-surface-hover border border-hairline text-xs"
                >
                  <span class="truncate text-ink font-medium">&#10146; {{ cc }}</span>
                  <button
                    type="button"
                    class="text-ink-muted hover:text-red-500 p-1 cursor-pointer transition-colors"
                    title="Remove recipient"
                    aria-label="Remove recipient from CC list"
                    @click="removeCcItem(idx)"
                  >
                    <X class="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>

              <!-- Custom CC Input -->
              <div class="flex gap-2 pt-1">
                <BaseInput
                  v-model="newCcInput"
                  placeholder="Add custom office (e.g. ለሰው ኃይል አስተዳደር ዳይሬክቶሬት)..."
                  class="grow"
                  @keyup.enter.prevent="addCcItem()"
                />
                <BaseButton size="sm" variant="outline" type="button" @click="addCcItem()">
                  <Plus class="w-3.5 h-3.5 mr-1" /> Add
                </BaseButton>
              </div>
            </div>

            <!-- 5. Signatory & Institutional Stamp Options -->
            <div class="bg-surface border border-hairline rounded-xl p-5 space-y-4">
              <h2 class="text-xs font-semibold   text-ink-muted border-b border-hairline-subtle pb-2 flex items-center gap-2">
                <Stamp class="w-4 h-4 text-primary" />
                <span>Authorized Signatory & Seal</span>
              </h2>

              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                  <label class="block text-xs font-semibold   text-ink-secondary mb-1">
                    Signatory Full Name *
                  </label>
                  <BaseInput v-model="signatoryName" required />
                </div>
                <div>
                  <label class="block text-xs font-semibold   text-ink-secondary mb-1">
                    Title (Amharic) *
                  </label>
                  <BaseInput v-model="signatoryTitleAm" required />
                </div>
                <div>
                  <label class="block text-xs font-semibold   text-ink-secondary mb-1">
                    Title (English) *
                  </label>
                  <BaseInput v-model="signatoryTitleEn" required />
                </div>
              </div>

              <div class="pt-2 border-t border-hairline-subtle text-xs">
                <label class="flex items-center gap-2.5 cursor-pointer">
                  <input
                    type="checkbox"
                    v-model="submitForApproval"
                    class="rounded border-hairline text-primary focus:ring-primary w-4 h-4"
                  />
                  <span class="font-medium text-ink">Submit to Standard Review &amp; Approval Workflow (standard_doc_approval)</span>
                </label>
              </div>
            </div>

            <!-- Submit Action Toolbar -->
            <div class="flex items-center justify-between gap-3 pt-3">
              <BaseButton
                variant="outline"
                type="button"
                :loading="isPreviewingPdf"
                @click="handleDownloadLivePreviewPdf"
              >
                <Eye class="w-4 h-4 mr-1.5" />
                <span>Open Live PDF Preview</span>
              </BaseButton>

              <div class="flex items-center gap-3">
                <BaseButton variant="outline" type="button" @click="router.back()">Cancel</BaseButton>
                <BaseButton
                  variant="primary"
                  type="submit"
                  :loading="isLoading"
                  class="bg-primary hover:bg-primary-hover text-white px-6 py-2.5"
                >
                  <FileSignature class="w-4 h-4 mr-1.5" />
                  <span>Generate & Register Letter</span>
                </BaseButton>
              </div>
            </div>
          </form>
        </div>

        <!-- Right: Live A4 Visual Preview (5 cols on lg) -->
        <div class="lg:col-span-5 sticky top-20 space-y-3">
          <div class="flex items-center justify-between px-1">
            <span class="text-xs font-semibold text-ink   flex items-center gap-1.5">
              <Eye class="w-3.5 h-3.5 text-primary" />
              <span>Real-Time A4 Document Preview</span>
            </span>
            <span class="text-xs text-ink-faint">A4 Portrait (210×297mm)</span>
          </div>

          <OfficialLetterA4Preview
            :ref-number="'WU/DOC/PREVIEW'"
            :ethiopian-date="ethiopianDateStr || '---'"
            :recipient-lead="recipientLead"
            :recipient-sub="recipientSub"
            :recipient-location="recipientLocation"
            :subject="letterSubject"
            :body="letterBody"
            :cc-list="ccList"
            :signatory-name="signatoryName"
            :signatory-title-am="signatoryTitleAm"
            :signatory-title-en="signatoryTitleEn"
            :uni-am="uniAm"
            :faculty-am="facultyAm"
            :dept-am="deptAm"
            :location-am="locationAm"
            :uni-en="uniEn"
            :faculty-en="facultyEn"
            :dept-en="deptEn"
            :location-en="locationEn"
          />
        </div>
      </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODE 2: INSTITUTIONAL TEMPLATE INTAKE -->
    <!-- ===================================================================== -->
    <div v-else-if="creationMode === 'template'" class="space-y-6">
      <div class="bg-surface border border-hairline rounded-xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs text-ink-secondary">
        <div class="flex items-center gap-2 font-medium text-ink">
          <Layers class="w-4 h-4 text-primary shrink-0" />
          <span>Wollo University Institutional Template Intake</span>
        </div>
        <div class="text-xs text-ink-muted">
          Generates authentic institutional documents using pre-approved university templates with dynamic field mapping and live layout rendering.
        </div>
      </div>

      <!-- Main Layout: 2 Columns (Form Left, Live Template HTML Preview Right) -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        <!-- Left: Form Controls (7 cols on lg) -->
        <div class="lg:col-span-7 space-y-6">
          <form @submit.prevent="handleGenerateFromTemplate" class="space-y-6">
            <!-- 1. Template Selection & Details -->
            <div class="bg-surface border border-hairline rounded-xl p-5 space-y-4">
              <h2 class="text-xs font-semibold   text-ink-muted border-b border-hairline-subtle pb-2 flex items-center gap-2">
                <FileText class="w-4 h-4 text-primary" />
                <span>Select Institutional Template</span>
              </h2>

              <div>
                <BaseSelect
                  v-model="selectedTemplateId"
                  label="Approved Document Template *"
                  :options="templatesList.map((t) => ({ label: `${t.name} (${t.code})`, value: t.id }))"
                  required
                />
              </div>

              <div v-if="selectedTemplate" class="p-3 bg-surface-hover border border-hairline rounded-lg text-xs space-y-1.5">
                <div class="flex items-center justify-between">
                  <span class="font-mono font-bold text-primary">{{ selectedTemplate.code }}</span>
                  <span class="px-2 py-0.5 rounded text-xs font-bold bg-primary-subtle text-primary">
                    v{{ selectedTemplate.published_version?.version_number || 1 }} Published
                  </span>
                </div>
                <div class="font-semibold text-ink">{{ selectedTemplate.name }}</div>
                <div v-if="selectedTemplate.description" class="text-ink-muted text-xs">
                  {{ selectedTemplate.description }}
                </div>
              </div>
            </div>

            <!-- 2. Administrative Context -->
            <div class="bg-surface border border-hairline rounded-xl p-5 space-y-4">
              <h2 class="text-xs font-semibold   text-ink-muted border-b border-hairline-subtle pb-2 flex items-center gap-2">
                <Building2 class="w-4 h-4 text-primary" />
                <span>Accountability & Security Classification</span>
              </h2>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <BaseSelect
                  v-model="templateOwnerUnitId"
                  label="Accountable Office / Department *"
                  :options="units.map((u) => ({ label: `${u.name} (${u.code})`, value: u.id }))"
                  required
                />
                <BaseSelect
                  v-model="templateConfidentialityId"
                  label="Classification Level *"
                  :options="metaStore.confidentialityLevels.map((c) => ({ label: c.name, value: c.id }))"
                  required
                />
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <BaseSelect
                  v-model="templateCategoryId"
                  label="Document Category *"
                  :options="metaStore.categories.map((c) => ({ label: c.name, value: c.id }))"
                  required
                />
                <div>
                  <label class="block text-xs font-semibold   text-ink-secondary mb-1">
                    Document Date *
                  </label>
                  <BaseInput v-model="templateDate" type="date" required />
                </div>
              </div>
            </div>

            <!-- 3. Template Fields -->
            <div class="bg-surface border border-hairline rounded-xl p-5 space-y-4">
              <h2 class="text-xs font-semibold   text-ink-muted border-b border-hairline-subtle pb-2 flex items-center gap-2">
                <Sparkles class="w-4 h-4 text-primary" />
                <span>Document Content & Field Mapping</span>
              </h2>

              <div>
                <label class="block text-xs font-semibold   text-ink-secondary mb-1">
                  Addressed Recipient / Department (recipient) *
                </label>
                <BaseInput
                  v-model="templateFields.recipient"
                  placeholder="e.g., Office of Academic Vice President or College of Informatics"
                  required
                />
              </div>

              <div>
                <label class="block text-xs font-semibold   text-ink-secondary mb-1">
                  Document Subject / Title (subject) *
                </label>
                <BaseInput
                  v-model="templateFields.subject"
                  placeholder="e.g., Quarterly Operational Curriculum Review Report"
                  required
                />
              </div>

              <div>
                <label class="block text-xs font-semibold   text-ink-secondary mb-1">
                  Document Body / Institutional Text (body) *
                </label>
                <BaseTextarea
                  v-model="templateFields.body"
                  rows="7"
                  placeholder="Enter substantive document text or operational directives..."
                  required
                />
              </div>

              <div class="pt-2 border-t border-hairline-subtle text-xs">
                <label class="flex items-center gap-2.5 cursor-pointer">
                  <input
                    type="checkbox"
                    v-model="templateSubmitForApproval"
                    class="rounded border-hairline text-primary focus:ring-primary w-4 h-4"
                  />
                  <span class="font-medium text-ink">Submit to Standard Review &amp; Approval Workflow on Generation</span>
                </label>
              </div>
            </div>

            <!-- Submit Action Toolbar -->
            <div class="flex items-center justify-end gap-3 pt-3">
              <BaseButton variant="outline" type="button" @click="router.back()">Cancel</BaseButton>
              <BaseButton
                variant="primary"
                type="submit"
                :loading="isLoading"
                class="bg-primary hover:bg-primary-hover text-white px-6 py-2.5"
              >
                <FileSignature class="w-4 h-4 mr-1.5" />
                <span>Generate Document from Template</span>
              </BaseButton>
            </div>
          </form>
        </div>

        <!-- Right: Live Template HTML Preview (5 cols on lg) -->
        <div class="lg:col-span-5 sticky top-20 space-y-3">
          <div class="flex items-center justify-between px-1">
            <span class="text-xs font-semibold text-ink   flex items-center gap-1.5">
              <Eye class="w-3.5 h-3.5 text-primary" />
              <span>Live Generated Document Layout</span>
            </span>
            <span class="text-xs text-ink-faint">Standard Template A4</span>
          </div>

          <div class="bg-surface rounded-xl border border-hairline overflow-hidden min-h-[500px]">
            <div class="border-b border-hairline bg-surface-hover px-4 py-2.5 flex items-center justify-between text-xs text-ink-secondary">
              <span class="font-semibold">{{ selectedTemplate?.name || 'Template Document' }}</span>
              <span class="text-xs text-ink-muted">A4 Paper Simulation</span>
            </div>
            <div class="p-6 bg-white dark:bg-zinc-900 text-black dark:text-zinc-100 min-h-[460px]">
              <div v-html="templateLivePreviewHtml"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODE 3: PHYSICAL / DIGITAL SCAN UPLOAD -->
    <!-- ===================================================================== -->
    <div v-else class="max-w-4xl mx-auto space-y-6">
      <form @submit.prevent="handleUploadSubmit" class="space-y-6">
        <!-- Section 1: Metadata Card -->
        <div class="bg-surface border border-hairline rounded-xl p-6 space-y-5">
          <h2 class="text-sm font-semibold text-ink   border-b border-hairline-subtle pb-3 flex items-center gap-2">
            <FileText class="w-4 h-4 text-primary" />
            <span>Record Identification & Classification</span>
          </h2>

          <div class="space-y-4">
            <div>
              <label class="block text-xs font-semibold   text-ink-secondary mb-1">
                Document Title *
              </label>
              <BaseInput
                v-model="title"
                placeholder="e.g., Wollo University Senate Guideline on Graduate Research 2026"
                required
              />
            </div>

            <div>
              <label class="block text-xs font-semibold   text-ink-secondary mb-1">
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
                <label class="block text-xs font-semibold   text-ink-secondary mb-1">
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
        <div class="bg-surface border border-hairline rounded-xl p-6 space-y-4">
          <h2 class="text-sm font-semibold text-ink   border-b border-hairline-subtle pb-3 flex items-center gap-2">
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
            <label class="block text-xs font-semibold   text-ink-secondary mb-1">
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
          <BaseButton
            variant="primary"
            type="submit"
            :loading="isLoading"
            class="bg-primary hover:bg-primary-hover text-white px-6 py-2.5"
          >
            Complete Registration
          </BaseButton>
        </div>
      </form>
    </div>
  </div>
</template>

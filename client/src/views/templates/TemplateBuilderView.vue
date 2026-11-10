<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { templatesApi, organizationUnitsApi, metadataApi } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import ModalDialog from '@/components/ModalDialog.vue'
import DataTable, { type TableColumn } from '@/components/data-display/DataTable.vue'
import SearchFilterBar from '@/components/data-display/SearchFilterBar.vue'
import Pagination from '@/components/Pagination.vue'
import {
  FileText,
  Plus,
  Eye,
  CheckCircle2,
  FilePlus2,
  Pencil,
  Send,
  Building2,
  Shield,
  Layers,
  Sparkles,
} from 'lucide-vue-next'

const router = useRouter()
const authStore = useAuthStore()
const toast = useToast()

const templates = ref<any[]>([])
const units = ref<any[]>([])
const categories = ref<any[]>([])
const confidentialityLevels = ref<any[]>([])
const isLoading = ref(false)
const searchQuery = ref('')

const pagination = ref({
  current_page: 1,
  last_page: 1,
  total: 0,
})

// Modals
const showCreateModal = ref(false)
const showVersionModal = ref(false)
const showGenerateModal = ref(false)
const showPreviewModal = ref(false)
const activeTemplate = ref<any>(null)

// Create Template Model
const createForm = ref({
  name: '',
  code: '',
  category_id: '' as number | '',
  active: true,
})
const isSubmittingCreate = ref(false)

// Version Model
const versionForm = ref({
  template_content: '',
  schema_definition: '{\n  "recipient": "string",\n  "subject": "string",\n  "body": "string",\n  "reference_date": "string"\n}',
  change_note: 'Updated template layout and institutional headers',
  publish_now: true,
})
const isSubmittingVersion = ref(false)

// Generate Document Model
const generateForm = ref({
  recipient: 'Academic Vice President Office',
  subject: 'Departmental Curriculum Review and Laboratory Allocation',
  body: 'We hereby submit the comprehensive review of department course specifications and request authorization for upgraded laboratory equipment.',
  reference_date: new Date().toISOString().split('T')[0],
  category_id: '' as number | '',
  confidentiality_id: '' as number | '',
  owner_unit_id: '' as number | '',
  change_note: 'Generated from approved institutional template',
})
const isGenerating = ref(false)
const previewHtml = ref('')

const columns: TableColumn[] = [
  { key: 'code', label: 'Template Code' },
  { key: 'name', label: 'Template Name' },
  { key: 'category', label: 'Category' },
  { key: 'version', label: 'Published Version' },
  { key: 'status', label: 'Status' },
]

async function loadCatalogs() {
  try {
    const [orgRes, metaRes] = await Promise.all([
      organizationUnitsApi.list({ per_page: 100 }),
      metadataApi.getCategories(),
    ])
    units.value = (orgRes.data as any).data || []
    categories.value = (metaRes.data as any).data || []

    const confRes = await metadataApi.getConfidentialityLevels()
    confidentialityLevels.value = (confRes.data as any).data || []

    if (units.value.length > 0) {
      generateForm.value.owner_unit_id = authStore.primaryUnit?.unit_id || units.value[0].id
    }
    if (categories.value.length > 0) {
      createForm.value.category_id = categories.value[0].id
      generateForm.value.category_id = categories.value[0].id
    }
    if (confidentialityLevels.value.length > 0) {
      generateForm.value.confidentiality_id = confidentialityLevels.value[0].id
    }
  } catch (err) {
    console.error('Failed to load catalogs', err)
  }
}

async function loadTemplates(page = 1) {
  isLoading.value = true
  try {
    const res = await templatesApi.list({
      page,
      search: searchQuery.value || undefined,
    })
    templates.value = (res.data as any).data || []
    if (res.data.meta) {
      pagination.value = res.data.meta
    }
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to load templates.')
  } finally {
    isLoading.value = false
  }
}

function openCreateModal() {
  createForm.value = {
    name: '',
    code: 'WU-TMP-' + Math.floor(100 + Math.random() * 900),
    category_id: categories.value[0]?.id || 1,
    active: true,
  }
  showCreateModal.value = true
}

async function submitCreateTemplate() {
  if (!createForm.value.name.trim() || !createForm.value.code.trim()) {
    toast.error('Name and Code are required.')
    return
  }

  isSubmittingCreate.value = true
  try {
    await templatesApi.create({
      name: createForm.value.name.trim(),
      code: createForm.value.code.trim(),
      category_id: Number(createForm.value.category_id),
      active: createForm.value.active ? 1 : 0,
    })
    toast.success('Template created successfully.')
    showCreateModal.value = false
    await loadTemplates(1)
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to create template.')
  } finally {
    isSubmittingCreate.value = false
  }
}

function openVersionModal(tpl: any) {
  activeTemplate.value = tpl
  versionForm.value = {
    template_content: tpl.published_version?.template_content || 
      '<div style="font-family: Arial, sans-serif; padding: 24px;">\n  <h2 style="color: #003366; text-align: center;">WOLLO UNIVERSITY</h2>\n  <h4 style="text-align: center; border-bottom: 2px solid #003366; padding-bottom: 8px;">OFFICIAL ADMINISTRATIVE DIRECTIVE</h4>\n  <p><strong>To:</strong> {{recipient}}</p>\n  <p><strong>Date:</strong> {{reference_date}}</p>\n  <p><strong>Subject:</strong> {{subject}}</p>\n  <hr style="border: 0; border-top: 1px solid #ccc; margin: 16px 0;" />\n  <div style="line-height: 1.6;">{{body}}</div>\n  <br><br>\n  <p><strong>Approved by:</strong> Dean / Authorized Officer</p>\n</div>',
    schema_definition: '{\n  "recipient": "string",\n  "subject": "string",\n  "body": "string",\n  "reference_date": "string"\n}',
    change_note: 'Updated institutional layout version',
    publish_now: true,
  }
  showVersionModal.value = true
}

async function submitNewVersion() {
  if (!activeTemplate.value) return

  let parsedSchema: Record<string, unknown> = {}
  try {
    parsedSchema = JSON.parse(versionForm.value.schema_definition)
  } catch {
    toast.error('Schema definition must be valid JSON.')
    return
  }

  isSubmittingVersion.value = true
  try {
    const res = await templatesApi.createVersion(activeTemplate.value.id, {
      body_template_html: versionForm.value.template_content,
      template_content: versionForm.value.template_content,
      schema_definition: parsedSchema,
      change_note: versionForm.value.change_note,
    })

    const newVer = res.data.data
    if (versionForm.value.publish_now && newVer?.id) {
      await templatesApi.publishVersion(activeTemplate.value.id, newVer.id)
    }

    toast.success('Template version created and published successfully.')
    showVersionModal.value = false
    await loadTemplates(pagination.value.current_page)
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to save template version.')
  } finally {
    isSubmittingVersion.value = false
  }
}

function openGenerateModal(tpl: any) {
  activeTemplate.value = tpl
  generateForm.value = {
    recipient: 'Dean, College of Informatics',
    subject: 'Laboratory Procurement and Staff Allocation Directive',
    body: 'Please proceed with formal procurement evaluation for updated computing equipment and staff directives as approved by Council.',
    reference_date: new Date().toISOString().split('T')[0],
    category_id: tpl.category_id || categories.value[0]?.id || '',
    confidentiality_id: confidentialityLevels.value[0]?.id || '',
    owner_unit_id: authStore.primaryUnit?.unit_id || units.value[0]?.id || '',
    change_note: `Generated from template ${tpl.name}`,
  }
  showGenerateModal.value = true
}

async function submitGenerateDocument() {
  if (!activeTemplate.value) return
  if (!generateForm.value.owner_unit_id) {
    toast.error('Accountable unit is required.')
    return
  }

  isGenerating.value = true
  try {
    const payload = {
      template_data: {
        recipient: generateForm.value.recipient,
        subject: generateForm.value.subject,
        body: generateForm.value.body,
        reference_date: generateForm.value.reference_date,
      },
      title: generateForm.value.subject,
      summary: `Official document generated from template: ${activeTemplate.value.name}`,
      category_id: Number(generateForm.value.category_id),
      confidentiality_id: Number(generateForm.value.confidentiality_id),
      owner_unit_id: Number(generateForm.value.owner_unit_id),
      document_date: generateForm.value.reference_date,
      change_note: generateForm.value.change_note,
    }

    const res = await templatesApi.generateDocument(activeTemplate.value.id, payload)
    const doc = res.data.data
    toast.success(`Official document generated with reference #${doc.reference_number}!`)
    showGenerateModal.value = false
    router.push(`/documents/${doc.id}`)
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to generate document from template.')
  } finally {
    isGenerating.value = false
  }
}

async function previewTemplate(tpl: any) {
  activeTemplate.value = tpl
  try {
    const res = await templatesApi.render(tpl.id, {
      template_data: {
        recipient: '[RECIPIENT NAME / UNIT]',
        subject: '[OFFICIAL DOCUMENT SUBJECT]',
        body: '<p>[This is where the structured institutional letter or directive content will appear in the finalized PDF.]</p>',
        reference_date: new Date().toLocaleDateString(),
      },
    })
    previewHtml.value = res.data.data?.rendered_html || ''
    showPreviewModal.value = true
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to render preview.')
  }
}

onMounted(() => {
  loadCatalogs()
  loadTemplates(1)
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
          <FileText class="w-6 h-6 text-primary-600 dark:text-primary-400" />
          Institutional Document Templates
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
          Standardized letterheads, memoranda, directives, and verified document templates
        </p>
      </div>
      <div>
        <BaseButton variant="primary" size="sm" @click="openCreateModal">
          <Plus class="w-4 h-4 mr-1.5" />
          New Template
        </BaseButton>
      </div>
    </div>

    <!-- Search & Filter -->
    <SearchFilterBar
      v-model:search="searchQuery"
      search-placeholder="Search templates by code or title..."
      @search="loadTemplates(1)"
      @reset="searchQuery = ''; loadTemplates(1)"
    />

    <!-- Templates Data Table -->
    <DataTable
      :columns="columns"
      :items="templates"
      :is-loading="isLoading"
      empty-title="No Document Templates Found"
      empty-message="Create your first standardized institutional letter or memorandum template."
    >
      <template #code="{ item }">
        <span class="font-mono text-xs font-semibold text-primary-700 dark:text-primary-300 bg-primary-50 dark:bg-primary-950/50 px-2 py-0.5 rounded border border-primary-200 dark:border-primary-800">
          {{ item.code }}
        </span>
      </template>

      <template #name="{ item }">
        <div>
          <div class="font-medium text-slate-900 dark:text-white">{{ item.name }}</div>
          <div class="text-xs text-slate-500 dark:text-slate-400">
            {{ item.published_version ? `Version ${item.published_version.version_number}` : 'Draft / No published version' }}
          </div>
        </div>
      </template>

      <template #category="{ item }">
        <span class="text-xs text-slate-600 dark:text-slate-300">
          {{ item.category?.name || 'General' }}
        </span>
      </template>

      <template #version="{ item }">
        <span v-if="item.published_version" class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 dark:text-emerald-400">
          <CheckCircle2 class="w-3.5 h-3.5" />
          v{{ item.published_version.version_number }}
        </span>
        <span v-else class="text-xs text-amber-600 dark:text-amber-400">
          Unpublished
        </span>
      </template>

      <template #status="{ item }">
        <span :class="[
          'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium',
          item.active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
        ]">
          {{ item.active ? 'Active' : 'Inactive' }}
        </span>
      </template>

      <template #actions="{ item }">
        <div class="flex items-center gap-2 justify-end">
          <BaseButton
            v-if="item.published_version || item.versions?.some((v: any) => v.status === 'published')"
            variant="primary"
            size="sm"
            @click="openGenerateModal(item)"
          >
            <Sparkles class="w-3.5 h-3.5 mr-1" />
            Generate
          </BaseButton>

          <BaseButton
            variant="secondary"
            size="sm"
            @click="openVersionModal(item)"
          >
            <Pencil class="w-3.5 h-3.5 mr-1" />
            Edit
          </BaseButton>

          <BaseButton
            variant="ghost"
            size="sm"
            @click="previewTemplate(item)"
          >
            <Eye class="w-3.5 h-3.5" />
          </BaseButton>
        </div>
      </template>
    </DataTable>

    <!-- Pagination -->
    <Pagination
      v-if="pagination.total > 0"
      :current-page="pagination.current_page"
      :last-page="pagination.last_page"
      :total-items="pagination.total"
      @page-change="loadTemplates"
    />

    <!-- Modal: Create Template -->
    <ModalDialog
      :is-open="showCreateModal"
      title="Create Institutional Template"
      @close="showCreateModal = false"
    >
      <form @submit.prevent="submitCreateTemplate" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
            Template Name *
          </label>
          <BaseInput
            v-model="createForm.name"
            placeholder="e.g., Dean Official Approval Memorandum"
            required
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
            Template Unique Code *
          </label>
          <BaseInput
            v-model="createForm.code"
            placeholder="e.g., WU-TMP-DEAN-MEMO"
            required
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
            Document Category *
          </label>
          <BaseSelect
            v-model="createForm.category_id"
            :options="categories.map(c => ({ value: c.id, label: c.name }))"
            required
          />
        </div>

        <div class="flex items-center gap-2 pt-2">
          <input
            id="template-active"
            type="checkbox"
            v-model="createForm.active"
            class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500"
          />
          <label for="template-active" class="text-sm text-slate-700 dark:text-slate-300">
            Active for institutional generation
          </label>
        </div>

        <div class="flex justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
          <BaseButton variant="ghost" type="button" @click="showCreateModal = false">
            Cancel
          </BaseButton>
          <BaseButton variant="primary" type="submit" :loading="isSubmittingCreate" @click="submitCreateTemplate">
            Create Template
          </BaseButton>
        </div>
      </form>
    </ModalDialog>

    <!-- Modal: New Template Version / Edit Layout -->
    <ModalDialog
      :is-open="showVersionModal"
      title="Template Layout & Schema Version"
      size="lg"
      @close="showVersionModal = false"
    >
      <form @submit.prevent="submitNewVersion" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
            HTML Template Content (with dynamic placeholders) *
          </label>
          <BaseTextarea
            v-model="versionForm.template_content"
            rows="10"
            class="font-mono text-xs"
            placeholder="<div>... {{recipient}} ... {{subject}} ...</div>"
            required
          />
          <p class="text-xs text-slate-500 mt-1">
            Use double curly braces for variables. Script tags and executable event handlers are stripped automatically by the server-side sanitizer.
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
            JSON Schema Definition *
          </label>
          <BaseTextarea
            v-model="versionForm.schema_definition"
            rows="4"
            class="font-mono text-xs"
            required
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
            Version Change Note *
          </label>
          <BaseInput
            v-model="versionForm.change_note"
            placeholder="e.g., Added official university seal and revised signature block"
            required
          />
        </div>

        <div class="flex items-center gap-2">
          <input
            id="publish-version"
            type="checkbox"
            v-model="versionForm.publish_now"
            class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500"
          />
          <label for="publish-version" class="text-sm text-slate-700 dark:text-slate-300">
            Publish immediately as the active version
          </label>
        </div>

        <div class="flex justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
          <BaseButton variant="ghost" type="button" @click="showVersionModal = false">
            Cancel
          </BaseButton>
          <BaseButton variant="primary" type="submit" :loading="isSubmittingVersion" @click="submitNewVersion">
            Save Version
          </BaseButton>
        </div>
      </form>
    </ModalDialog>

    <!-- Modal: Generate Document from Template -->
    <ModalDialog
      :is-open="showGenerateModal"
      :title="`Generate Document: ${activeTemplate?.name || ''}`"
      size="lg"
      @close="showGenerateModal = false"
    >
      <form @submit.prevent="submitGenerateDocument" class="space-y-4">
        <div class="bg-primary-50 dark:bg-primary-950/40 p-3 rounded-lg border border-primary-200 dark:border-primary-800 text-xs text-primary-800 dark:text-primary-300">
          Generating a document will render the approved template, produce a sealed PDF, and register an immutable <strong>Version 1</strong> document in the repository with <code>ingestion_method = template_generated</code>.
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
              Accountable Unit *
            </label>
            <BaseSelect
              v-model="generateForm.owner_unit_id"
              :options="units.map(u => ({ value: u.id, label: u.name }))"
              required
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
              Security Classification *
            </label>
            <BaseSelect
              v-model="generateForm.confidentiality_id"
              :options="confidentialityLevels.map(c => ({ value: c.id, label: c.name }))"
              required
            />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
            Recipient / Destination *
          </label>
          <BaseInput
            v-model="generateForm.recipient"
            placeholder="e.g., Dean, College of Informatics"
            required
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
            Subject / Directive Title *
          </label>
          <BaseInput
            v-model="generateForm.subject"
            placeholder="e.g., Department Procurement Directive"
            required
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
            Letter Body / Content *
          </label>
          <BaseTextarea
            v-model="generateForm.body"
            rows="5"
            placeholder="Provide official directive or response text..."
            required
          />
        </div>

        <div class="flex justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
          <BaseButton variant="ghost" type="button" @click="showGenerateModal = false">
            Cancel
          </BaseButton>
          <BaseButton variant="primary" type="submit" :loading="isGenerating" @click="submitGenerateDocument">
            <Sparkles class="w-4 h-4 mr-1.5" />
            Generate & Register Document
          </BaseButton>
        </div>
      </form>
    </ModalDialog>

    <!-- Modal: Preview Template -->
    <ModalDialog
      :is-open="showPreviewModal"
      title="Template Rendered Preview"
      size="lg"
      @close="showPreviewModal = false"
    >
      <div class="bg-white p-6 rounded border border-slate-300 shadow-inner overflow-auto max-h-[60vh]">
        <div v-html="previewHtml"></div>
      </div>
      <div class="flex justify-end pt-4 border-t border-slate-200 dark:border-slate-800 mt-4">
        <BaseButton variant="secondary" @click="showPreviewModal = false">
          Close Preview
        </BaseButton>
      </div>
    </ModalDialog>
  </div>
</template>

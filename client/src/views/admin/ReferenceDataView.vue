<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { metadataApi } from '@/api/client'
import { useToast } from '@/composables/useToast'
import LoadingSkeleton from '@/components/ui/LoadingSkeleton.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import ModalDialog from '@/components/ModalDialog.vue'
import {
  Database,
  ShieldCheck,
  Tag,
  Flag,
  Send,
  RefreshCw,
  Plus,
  Edit2,
  CheckCircle,
  XCircle,
  AlertCircle,
} from 'lucide-vue-next'

const toast = useToast()

const loading = ref(true)
const saving = ref(false)
const showInactive = ref(true)
const activeTab = ref<'categories' | 'confidentiality' | 'types' | 'priorities' | 'dispatch'>('categories')

const categories = ref<any[]>([])
const confidentiality = ref<any[]>([])
const types = ref<any[]>([])
const priorities = ref<any[]>([])
const dispatchMethods = ref<any[]>([])

// Modal state
const isModalOpen = ref(false)
const editingItem = ref<any | null>(null)
const formCode = ref('')
const formName = ref('')
const formDescription = ref('')
const formActive = ref(true)
const formError = ref<string | null>(null)

const tabConfig = {
  categories: {
    title: 'Document Category',
    typeKey: 'categories',
    codePlaceholder: 'e.g., academic_transcript, audit_report',
  },
  confidentiality: {
    title: 'Confidentiality Level',
    typeKey: 'confidentiality-levels',
    codePlaceholder: 'e.g., highly_restricted, presidential_secret',
  },
  types: {
    title: 'Correspondence Type',
    typeKey: 'correspondence-types',
    codePlaceholder: 'e.g., inter_university, external_directive',
  },
  priorities: {
    title: 'Priority Level',
    typeKey: 'priorities',
    codePlaceholder: 'e.g., emergency, express_dispatch',
  },
  dispatch: {
    title: 'Dispatch Channel',
    typeKey: 'dispatch-methods',
    codePlaceholder: 'e.g., registered_post, courier_express',
  },
}

const currentConfig = computed(() => tabConfig[activeTab.value])

async function loadData() {
  loading.value = true
  try {
    const res = await metadataApi.getAll({ include_inactive: showInactive.value })
    const d = res.data.data
    categories.value = d.document_categories || []
    confidentiality.value = d.confidentiality_levels || []
    types.value = d.correspondence_types || []
    priorities.value = d.priorities || []
    dispatchMethods.value = d.dispatch_methods || []
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to load reference vocabularies.')
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  editingItem.value = null
  formCode.value = ''
  formName.value = ''
  formDescription.value = ''
  formActive.value = true
  formError.value = null
  isModalOpen.value = true
}

function openEditModal(item: any) {
  editingItem.value = item
  formCode.value = item.code
  formName.value = item.name
  formDescription.value = item.description || ''
  formActive.value = Boolean(item.active)
  formError.value = null
  isModalOpen.value = true
}

async function handleSaveItem() {
  if (!formName.value.trim()) {
    formError.value = 'Display name is required.'
    return
  }

  if (!editingItem.value && !formCode.value.trim()) {
    formError.value = 'Technical code is required.'
    return
  }

  saving.value = true
  formError.value = null

  const typeKey = currentConfig.value.typeKey

  try {
    if (editingItem.value) {
      await metadataApi.updateItem(typeKey, editingItem.value.id, {
        name: formName.value.trim(),
        description: formDescription.value.trim() || undefined,
        active: formActive.value,
      })
      toast.success(`${currentConfig.value.title} updated successfully.`)
    } else {
      await metadataApi.createItem(typeKey, {
        code: formCode.value.trim().toLowerCase(),
        name: formName.value.trim(),
        description: formDescription.value.trim() || undefined,
        active: formActive.value,
      })
      toast.success(`${currentConfig.value.title} created successfully.`)
    }

    isModalOpen.value = false
    await loadData()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'An error occurred while saving.'
  } finally {
    saving.value = false
  }
}

async function handleToggleStatus(item: any) {
  const typeKey = currentConfig.value.typeKey
  const nextStatus = !item.active
  try {
    await metadataApi.updateItem(typeKey, item.id, {
      active: nextStatus,
    })
    item.active = nextStatus
    toast.success(`${item.name} is now ${nextStatus ? 'active' : 'deactivated'}.`)
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Failed to update item status.')
  }
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 border-b border-hairline pb-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-ink flex items-center gap-2.5">
          <Database class="w-5 h-5 sm:w-6 sm:h-6 text-primary shrink-0" />
          <span>Institutional Vocabulary & Reference Catalogs</span>
        </h1>
        <p class="text-xs text-ink-muted mt-0.5">
          Standardized system taxonomy: categories, confidentiality classifications, priorities, and dispatch channels.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <label class="flex items-center gap-1.5 text-xs text-ink-muted mr-2 cursor-pointer select-none">
          <input
            type="checkbox"
            v-model="showInactive"
            @change="loadData"
            class="rounded border-hairline text-primary focus:ring-primary w-3.5 h-3.5"
          />
          Show Deactivated
        </label>

        <BaseButton variant="outline" size="sm" :loading="loading" @click="loadData">
          <RefreshCw class="w-3.5 h-3.5 mr-1.5" />
          Refresh
        </BaseButton>

        <BaseButton variant="primary" size="sm" @click="openCreateModal">
          <Plus class="w-4 h-4 mr-1.5" />
          Add {{ currentConfig.title }}
        </BaseButton>
      </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-hairline flex gap-4 text-xs font-semibold overflow-x-auto">
      <button
        type="button"
        @click="activeTab = 'categories'"
        class="pb-3 border-b-2 flex items-center gap-1.5 whitespace-nowrap transition-colors cursor-pointer"
        :class="activeTab === 'categories'
          ? 'border-primary text-primary'
          : 'border-transparent text-ink-muted hover:text-ink-secondary'"
      >
        <Tag class="w-3.5 h-3.5" />
        Categories ({{ categories.length }})
      </button>

      <button
        type="button"
        @click="activeTab = 'confidentiality'"
        class="pb-3 border-b-2 flex items-center gap-1.5 whitespace-nowrap transition-colors cursor-pointer"
        :class="activeTab === 'confidentiality'
          ? 'border-primary text-primary'
          : 'border-transparent text-ink-muted hover:text-ink-secondary'"
      >
        <ShieldCheck class="w-3.5 h-3.5" />
        Confidentiality Levels ({{ confidentiality.length }})
      </button>

      <button
        type="button"
        @click="activeTab = 'types'"
        class="pb-3 border-b-2 flex items-center gap-1.5 whitespace-nowrap transition-colors cursor-pointer"
        :class="activeTab === 'types'
          ? 'border-primary text-primary'
          : 'border-transparent text-ink-muted hover:text-ink-secondary'"
      >
        <Database class="w-3.5 h-3.5" />
        Correspondence Types ({{ types.length }})
      </button>

      <button
        type="button"
        @click="activeTab = 'priorities'"
        class="pb-3 border-b-2 flex items-center gap-1.5 whitespace-nowrap transition-colors cursor-pointer"
        :class="activeTab === 'priorities'
          ? 'border-primary text-primary'
          : 'border-transparent text-ink-muted hover:text-ink-secondary'"
      >
        <Flag class="w-3.5 h-3.5" />
        Priorities ({{ priorities.length }})
      </button>

      <button
        type="button"
        @click="activeTab = 'dispatch'"
        class="pb-3 border-b-2 flex items-center gap-1.5 whitespace-nowrap transition-colors cursor-pointer"
        :class="activeTab === 'dispatch'
          ? 'border-primary text-primary'
          : 'border-transparent text-ink-muted hover:text-ink-secondary'"
      >
        <Send class="w-3.5 h-3.5" />
        Dispatch Channels ({{ dispatchMethods.length }})
      </button>
    </div>

    <LoadingSkeleton v-if="loading" :count="4" />

    <!-- Table of Catalog Data -->
    <div v-else class="bg-surface border border-hairline rounded-xl overflow-hidden">
      <!-- Categories -->
      <table v-if="activeTab === 'categories'" class="w-full text-xs text-left">
        <thead class="bg-canvas-soft border-b border-hairline text-ink-muted font-semibold">
          <tr>
            <th class="p-3.5">Code</th>
            <th class="p-3.5">Category Name</th>
            <th class="p-3.5">Description</th>
            <th class="p-3.5">Status</th>
            <th class="p-3.5 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-hairline text-ink-secondary">
          <tr v-for="cat in categories" :key="cat.id" class="hover:bg-surface-hover transition">
            <td class="p-3.5 font-mono font-bold text-primary">{{ cat.code }}</td>
            <td class="p-3.5 font-medium">{{ cat.name }}</td>
            <td class="p-3.5 text-ink-muted">{{ cat.description || '—' }}</td>
            <td class="p-3.5">
              <span
                class="px-2 py-0.5 rounded text-xs font-semibold"
                :class="cat.active ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300' : 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300'"
              >
                {{ cat.active ? 'Active' : 'Deactivated' }}
              </span>
            </td>
            <td class="p-3.5 text-right">
              <div class="inline-flex items-center gap-1">
                <button
                  type="button"
                  @click="openEditModal(cat)"
                  class="p-1 rounded text-ink-muted hover:text-primary hover:bg-canvas-soft transition"
                  title="Edit Category"
                >
                  <Edit2 class="w-3.5 h-3.5" />
                </button>
                <button
                  type="button"
                  @click="handleToggleStatus(cat)"
                  class="px-2 py-0.5 rounded text-xs font-medium border border-hairline hover:bg-canvas-soft transition"
                >
                  {{ cat.active ? 'Deactivate' : 'Activate' }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Confidentiality -->
      <table v-else-if="activeTab === 'confidentiality'" class="w-full text-xs text-left">
        <thead class="bg-canvas-soft border-b border-hairline text-ink-muted font-semibold">
          <tr>
            <th class="p-3.5">Rank</th>
            <th class="p-3.5">Code</th>
            <th class="p-3.5">Classification Label</th>
            <th class="p-3.5">Description</th>
            <th class="p-3.5">Status</th>
            <th class="p-3.5 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-hairline text-ink-secondary">
          <tr v-for="c in confidentiality" :key="c.id" class="hover:bg-surface-hover transition">
            <td class="p-3.5 tabular-nums">{{ c.rank_order || c.id }}</td>
            <td class="p-3.5 font-mono font-bold text-primary">{{ c.code }}</td>
            <td class="p-3.5 font-medium">{{ c.name }}</td>
            <td class="p-3.5 text-ink-muted">{{ c.description || '—' }}</td>
            <td class="p-3.5">
              <span
                class="px-2 py-0.5 rounded text-xs font-semibold"
                :class="c.active ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300' : 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300'"
              >
                {{ c.active ? 'Active' : 'Deactivated' }}
              </span>
            </td>
            <td class="p-3.5 text-right">
              <div class="inline-flex items-center gap-1">
                <button
                  type="button"
                  @click="openEditModal(c)"
                  class="p-1 rounded text-ink-muted hover:text-primary hover:bg-canvas-soft transition"
                  title="Edit Classification"
                >
                  <Edit2 class="w-3.5 h-3.5" />
                </button>
                <button
                  type="button"
                  @click="handleToggleStatus(c)"
                  class="px-2 py-0.5 rounded text-xs font-medium border border-hairline hover:bg-canvas-soft transition"
                >
                  {{ c.active ? 'Deactivate' : 'Activate' }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Correspondence Types -->
      <table v-else-if="activeTab === 'types'" class="w-full text-xs text-left">
        <thead class="bg-canvas-soft border-b border-hairline text-ink-muted font-semibold">
          <tr>
            <th class="p-3.5">Code</th>
            <th class="p-3.5">Type Name</th>
            <th class="p-3.5">Description</th>
            <th class="p-3.5">Status</th>
            <th class="p-3.5 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-hairline text-ink-secondary">
          <tr v-for="t in types" :key="t.id" class="hover:bg-surface-hover transition">
            <td class="p-3.5 font-mono font-bold text-primary">{{ t.code }}</td>
            <td class="p-3.5 font-medium">{{ t.name }}</td>
            <td class="p-3.5 text-ink-muted">{{ t.description || '—' }}</td>
            <td class="p-3.5">
              <span
                class="px-2 py-0.5 rounded text-xs font-semibold"
                :class="t.active ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300' : 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300'"
              >
                {{ t.active ? 'Active' : 'Deactivated' }}
              </span>
            </td>
            <td class="p-3.5 text-right">
              <div class="inline-flex items-center gap-1">
                <button
                  type="button"
                  @click="openEditModal(t)"
                  class="p-1 rounded text-ink-muted hover:text-primary hover:bg-canvas-soft transition"
                  title="Edit Type"
                >
                  <Edit2 class="w-3.5 h-3.5" />
                </button>
                <button
                  type="button"
                  @click="handleToggleStatus(t)"
                  class="px-2 py-0.5 rounded text-xs font-medium border border-hairline hover:bg-canvas-soft transition"
                >
                  {{ t.active ? 'Deactivate' : 'Activate' }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Priorities -->
      <table v-else-if="activeTab === 'priorities'" class="w-full text-xs text-left">
        <thead class="bg-canvas-soft border-b border-hairline text-ink-muted font-semibold">
          <tr>
            <th class="p-3.5">Code</th>
            <th class="p-3.5">Priority Label</th>
            <th class="p-3.5">Description</th>
            <th class="p-3.5">Status</th>
            <th class="p-3.5 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-hairline text-ink-secondary">
          <tr v-for="p in priorities" :key="p.id" class="hover:bg-surface-hover transition">
            <td class="p-3.5 font-mono font-bold text-primary">{{ p.code }}</td>
            <td class="p-3.5 font-medium">{{ p.name }}</td>
            <td class="p-3.5 text-ink-muted">{{ p.description || '—' }}</td>
            <td class="p-3.5">
              <span
                class="px-2 py-0.5 rounded text-xs font-semibold"
                :class="p.active ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300' : 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300'"
              >
                {{ p.active ? 'Active' : 'Deactivated' }}
              </span>
            </td>
            <td class="p-3.5 text-right">
              <div class="inline-flex items-center gap-1">
                <button
                  type="button"
                  @click="openEditModal(p)"
                  class="p-1 rounded text-ink-muted hover:text-primary hover:bg-canvas-soft transition"
                  title="Edit Priority"
                >
                  <Edit2 class="w-3.5 h-3.5" />
                </button>
                <button
                  type="button"
                  @click="handleToggleStatus(p)"
                  class="px-2 py-0.5 rounded text-xs font-medium border border-hairline hover:bg-canvas-soft transition"
                >
                  {{ p.active ? 'Deactivate' : 'Activate' }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Dispatch Methods -->
      <table v-else-if="activeTab === 'dispatch'" class="w-full text-xs text-left">
        <thead class="bg-canvas-soft border-b border-hairline text-ink-muted font-semibold">
          <tr>
            <th class="p-3.5">Code</th>
            <th class="p-3.5">Method Name</th>
            <th class="p-3.5">Description</th>
            <th class="p-3.5">Status</th>
            <th class="p-3.5 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-hairline text-ink-secondary">
          <tr v-for="d in dispatchMethods" :key="d.id" class="hover:bg-surface-hover transition">
            <td class="p-3.5 font-mono font-bold text-primary">{{ d.code }}</td>
            <td class="p-3.5 font-medium">{{ d.name }}</td>
            <td class="p-3.5 text-ink-muted">{{ d.description || '—' }}</td>
            <td class="p-3.5">
              <span
                class="px-2 py-0.5 rounded text-xs font-semibold"
                :class="d.active ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300' : 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300'"
              >
                {{ d.active ? 'Active' : 'Deactivated' }}
              </span>
            </td>
            <td class="p-3.5 text-right">
              <div class="inline-flex items-center gap-1">
                <button
                  type="button"
                  @click="openEditModal(d)"
                  class="p-1 rounded text-ink-muted hover:text-primary hover:bg-canvas-soft transition"
                  title="Edit Dispatch Method"
                >
                  <Edit2 class="w-3.5 h-3.5" />
                </button>
                <button
                  type="button"
                  @click="handleToggleStatus(d)"
                  class="px-2 py-0.5 rounded text-xs font-medium border border-hairline hover:bg-canvas-soft transition"
                >
                  {{ d.active ? 'Deactivate' : 'Activate' }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create / Edit Vocabulary Modal -->
    <ModalDialog
      :open="isModalOpen"
      :title="editingItem ? `Edit ${currentConfig.title}` : `Create New ${currentConfig.title}`"
      @close="isModalOpen = false"
    >
      <form @submit.prevent="handleSaveItem" class="space-y-4">
        <div v-if="formError" class="p-3 text-xs bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-900 rounded-lg text-rose-700 dark:text-rose-300 flex items-start gap-2">
          <AlertCircle class="w-4 h-4 mt-0.5 shrink-0" />
          <span>{{ formError }}</span>
        </div>

        <div>
          <label class="block text-xs font-semibold text-ink-secondary mb-1">Technical Code</label>
          <BaseInput
            v-model="formCode"
            :placeholder="currentConfig.codePlaceholder"
            :disabled="Boolean(editingItem)"
            class="font-mono text-xs"
            required
          />
          <p v-if="!editingItem" class="text-xs text-ink-muted mt-1">
            Unique machine identifier. Alphanumeric, lowercase, underscores. Immutable once created.
          </p>
          <p v-else class="text-xs text-ink-muted mt-1">
            Technical codes are immutable to maintain historical workflow and document references.
          </p>
        </div>

        <div>
          <label class="block text-xs font-semibold text-ink-secondary mb-1">Display Label</label>
          <BaseInput
            v-model="formName"
            placeholder="Official institutional label"
            class="text-xs"
            required
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-ink-secondary mb-1">Description</label>
          <textarea
            v-model="formDescription"
            rows="2"
            placeholder="Operational notes, applicability, or institutional standard"
            class="w-full text-xs p-2 rounded-lg border border-hairline bg-surface text-ink-secondary focus:outline-none focus:ring-1 focus:ring-primary"
          ></textarea>
        </div>

        <div class="flex items-center gap-2 pt-2">
          <input
            id="modal-active"
            type="checkbox"
            v-model="formActive"
            class="rounded border-hairline text-primary focus:ring-primary w-4 h-4"
          />
          <label for="modal-active" class="text-xs font-medium text-ink-secondary cursor-pointer">
            Active for institutional selection
          </label>
        </div>

        <div class="flex justify-end gap-2 pt-4 border-t border-hairline">
          <BaseButton variant="outline" size="sm" type="button" @click="isModalOpen = false">
            Cancel
          </BaseButton>
          <BaseButton variant="primary" size="sm" type="submit" :loading="saving">
            {{ editingItem ? 'Save Changes' : 'Create Item' }}
          </BaseButton>
        </div>
      </form>
    </ModalDialog>
  </div>
</template>

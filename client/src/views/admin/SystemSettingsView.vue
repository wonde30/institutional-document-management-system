<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useToast } from '@/composables/useToast'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import ModalDialog from '@/components/ModalDialog.vue'
import LoadingSkeleton from '@/components/ui/LoadingSkeleton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import BaseAlert from '@/components/ui/BaseAlert.vue'
import { settingsApi } from '@/api/client'
import type { SettingDefinition, SettingsPayload } from '@/types'
import { Settings, ShieldCheck, Database, Lock, Save, AlertCircle, RotateCcw, X, CheckCircle2 } from 'lucide-vue-next'

const toast = useToast()

const isLoading = ref(true)
const isSaving = ref(false)
const loadError = ref<string | null>(null)
const payload = ref<SettingsPayload | null>(null)
const activeCategory = ref<string | null>(null)
const draft = ref<Record<string, string | number | boolean>>({})
const fieldErrors = ref<Record<string, string>>({})
const saveError = ref<string | null>(null)
const pendingReset = ref<{ kind: 'setting' | 'category'; key: string; label: string } | null>(null)

const categories = computed(() => payload.value?.categories ?? {})

const categoryList = computed(() => Object.entries(categories.value).map(([id, category]) => ({ id, ...category })))

const settings = computed<SettingDefinition[]>(() => payload.value?.settings ?? [])

const visibleSettings = computed(() =>
  activeCategory.value === null
    ? settings.value
    : settings.value.filter((setting) => setting.category === activeCategory.value)
)

const deployment = computed(() => payload.value?.deployment ?? [])

const editableSettings = computed(() => visibleSettings.value.filter((setting) => setting.editable))

const dirtyKeys = computed(() =>
  editableSettings.value
    .filter((setting) => !sameValue(draft.value[setting.key], setting.value))
    .map((setting) => setting.key)
)

const hasUnsavedChanges = computed(() => dirtyKeys.value.length > 0)

const scopeUnitId = computed(() => payload.value?.scope_unit_id ?? null)

function sameValue(a: unknown, b: unknown): boolean {
  return String(a ?? '') === String(b ?? '')
}

function initialDraft(source: SettingDefinition[]): Record<string, string | number | boolean> {
  const values: Record<string, string | number | boolean> = {}
  source.forEach((setting) => {
    values[setting.key] = setting.value
  })
  return values
}

async function loadSettings() {
  isLoading.value = true
  loadError.value = null

  try {
    const response = await settingsApi.index()
    const data = response.data?.data

    if (!data) {
      throw new Error('The configuration payload was empty.')
    }

    payload.value = data
    draft.value = initialDraft(data.settings)

    if (activeCategory.value === null && categoryList.value.length > 0) {
      activeCategory.value = categoryList.value[0].id
    }
  } catch (error: unknown) {
    const apiError = error as { response?: { data?: { message?: string } } }
    loadError.value =
      apiError.response?.data?.message ||
      'The configuration could not be loaded. Please try again shortly.'
  } finally {
    isLoading.value = false
  }
}

function selectCategory(id: string) {
  activeCategory.value = id
}

function cancelChanges() {
  if (!payload.value) return
  draft.value = initialDraft(payload.value.settings)
  fieldErrors.value = {}
}

async function saveChanges() {
  if (!hasUnsavedChanges.value || isSaving.value) return

  isSaving.value = true
  fieldErrors.value = {}
  saveError.value = null

  const changes = dirtyKeys.value.map((key) => ({ key, value: draft.value[key] }))

  try {
    const response = await settingsApi.update(
      changes,
      scopeUnitId.value
    )

    const applied = response.data?.data?.settings

    if (payload.value && applied) {
      applied.forEach((setting) => {
        const index = payload.value!.settings.findIndex((item) => item.key === setting.key)
        if (index !== -1) {
          payload.value!.settings[index] = setting
        }
      })
      draft.value = initialDraft(payload.value.settings)
    }

    toast.success('Configuration saved and synchronized.')
  } catch (error: unknown) {
    const apiError = error as {
      response?: { data?: { message?: string; errors?: Record<string, string[] | string> } }
    }

    const errors = apiError.response?.data?.errors ?? {}
    const mapped: Record<string, string> = {}

    Object.entries(errors).forEach(([field, messages]) => {
      // The server reports a failure against the configuration key.
      const key = field.replace(/^settings\.\d+\./, '')
      mapped[key] = Array.isArray(messages) ? messages[0] : messages
    })

    fieldErrors.value = mapped

    const message =
      apiError.response?.data?.message || 'The configuration could not be saved. No value was changed.'

    saveError.value = message
    toast.error(message)
  } finally {
    isSaving.value = false
  }
}

function requestReset(kind: 'setting' | 'category', key: string, label: string) {
  pendingReset.value = { kind, key, label }
}

async function confirmReset() {
  const request = pendingReset.value
  if (!request || isSaving.value) return

  isSaving.value = true

  try {
    if (request.kind === 'setting') {
      await settingsApi.reset(request.key, scopeUnitId.value)
    } else {
      await settingsApi.resetCategory(request.key, scopeUnitId.value)
    }

    await loadSettings()
    toast.success(
      request.kind === 'setting'
        ? `"${request.label}" was reset to its default.`
        : `The "${request.label}" category was reset to its defaults.`
    )
  } catch (error: unknown) {
    const apiError = error as { response?: { data?: { message?: string } } }
    toast.error(apiError.response?.data?.message || 'The reset could not be completed.')
  } finally {
    isSaving.value = false
    pendingReset.value = null
  }
}

function inputType(setting: SettingDefinition): string {
  if (setting.type === 'integer') return 'number'
  if (setting.type === 'boolean') return 'checkbox'
  return 'text'
}

function selectOptions(setting: SettingDefinition) {
  return (setting.options ?? []).map((option) => ({ label: option, value: option }))
}

/** The current draft value rendered by a select control. */
function selectValue(setting: SettingDefinition): string {
  const value = draft.value[setting.key]
  return value === undefined || value === null ? '' : String(value)
}

/** The current draft value rendered by a text or number control. */
function textValue(setting: SettingDefinition): string | number {
  const value = draft.value[setting.key]
  if (typeof value === 'number') return value
  if (typeof value === 'boolean') return value ? 'true' : 'false'
  return value ?? ''
}

onMounted(loadSettings)
</script>

<template>
  <div class="space-y-6 max-w-4xl">
    <div>
      <div class="text-[11px] font-bold tracking-widest text-primary uppercase mb-1">
        ADMINISTRATION / SYSTEM CONFIGURATION
      </div>
      <h1 class="text-2xl font-bold tracking-tight text-ink-secondary">
        Institutional Settings &amp; Environment Boundaries
      </h1>
      <p class="text-xs sm:text-sm text-ink-muted mt-0.5">
        Runtime configuration is stored by the server and audited on every change. Deployment
        configuration remains managed by the environment and is shown for reference only.
      </p>
    </div>

    <!-- Loading -->
    <LoadingSkeleton v-if="isLoading" :rows="6" type="card" />

    <!-- Load failure -->
    <BaseAlert v-else-if="loadError" variant="danger">
      <div class="flex items-center justify-between gap-3">
        <span>{{ loadError }}</span>
        <BaseButton variant="outline" class="shrink-0" @click="loadSettings">Retry</BaseButton>
      </div>
    </BaseAlert>

    <template v-else-if="payload">
      <!-- Category navigation -->
      <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="Configuration categories">
        <button
          v-for="category in categoryList"
          :key="category.id"
          type="button"
          role="tab"
          :aria-selected="activeCategory === category.id"
          class="px-3 py-1.5 rounded-[6px] text-[11px] font-semibold border transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-primary"
          :class="
            activeCategory === category.id
              ? 'bg-primary text-white border-primary'
              : 'bg-surface text-ink-secondary border-hairline hover:bg-canvas-soft'
          "
          @click="selectCategory(category.id)"
        >
          {{ category.label }}
        </button>
      </div>

      <div
        v-if="activeCategory && categories[activeCategory]"
        class="text-[11px] text-ink-muted"
      >
        {{ categories[activeCategory].description }}
      </div>

      <!-- Settings of the active category -->
      <div class="p-5 rounded-xl bg-surface border border-hairline shadow-xs space-y-4">
        <div class="flex items-start justify-between gap-3">
          <h2 class="text-sm font-semibold text-ink-secondary flex items-center gap-2">
            <Settings class="w-4 h-4 text-primary" />
            {{ activeCategory ? categories[activeCategory]?.label : 'All Settings' }}
          </h2>
          <BaseButton
            v-if="activeCategory"
            variant="outline"
            class="text-[11px]"
            :disabled="isSaving"
            @click="requestReset('category', activeCategory, categories[activeCategory]?.label ?? activeCategory)"
          >
            <RotateCcw class="w-3.5 h-3.5 mr-1" />
            Reset category
          </BaseButton>
        </div>

        <!-- Empty state -->
        <EmptyState
          v-if="visibleSettings.length === 0"
          title="No settings in this category"
          description="This category does not currently declare any configuration."
        />

        <div v-else class="space-y-4">
          <div
            v-for="setting in visibleSettings"
            :key="setting.key"
            class="p-4 rounded-lg border border-hairline bg-canvas-soft/40 space-y-2"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <label class="block text-xs font-semibold text-ink-secondary">
                  {{ setting.label }}
                </label>
                <p class="text-[11px] text-ink-muted mt-0.5">{{ setting.description }}</p>
              </div>
              <span
                class="shrink-0 text-[10px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded"
                :class="
                  setting.editable
                    ? 'bg-[#e8f7ec] dark:bg-[#14301d] text-[#1aae39] dark:text-[#34d399]'
                    : 'bg-canvas-soft text-ink-muted border border-hairline'
                "
              >
                {{ setting.editable ? 'Editable' : 'Fixed' }}
              </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-start">
              <div class="space-y-1.5">
                <BaseSelect
                  v-if="setting.type === 'select'"
                  :model-value="selectValue(setting)"
                  :options="selectOptions(setting)"
                  :disabled="!setting.editable || isSaving"
                  :error="fieldErrors[setting.key]"
                  @update:model-value="(value: string) => (draft[setting.key] = value)"
                />
                <BaseInput
                  v-else-if="setting.type === 'boolean'"
                  :model-value="textValue(setting) === 'true' ? 'true' : 'false'"
                  :disabled="!setting.editable || isSaving"
                  :error="fieldErrors[setting.key]"
                  @update:model-value="(value: string) => (draft[setting.key] = value === 'true')"
                />
                <BaseInput
                  v-else
                  :model-value="textValue(setting)"
                  :type="inputType(setting)"
                  :disabled="!setting.editable || isSaving"
                  :error="fieldErrors[setting.key]"
                  @update:model-value="(value: string | number) => (draft[setting.key] = value)"
                />

                <p v-if="fieldErrors[setting.key]" class="text-[11px] text-[#e03e3e] dark:text-[#f87171]">
                  {{ fieldErrors[setting.key] }}
                </p>
              </div>

              <div class="text-[11px] text-ink-muted space-y-1">
                <p>
                  <span class="font-semibold text-ink-secondary">Default:</span>
                  {{ setting.default }}
                </p>
                <p v-if="setting.scope === 'global_or_unit'">
                  <span class="font-semibold text-ink-secondary">Scope:</span>
                  applies to the active organizational unit
                </p>
                <p v-if="setting.updated_at">
                  <span class="font-semibold text-ink-secondary">Last changed:</span>
                  {{ setting.updated_at }}
                  <span v-if="setting.updated_by"> by {{ setting.updated_by }}</span>
                </p>
                <p v-else class="text-ink-faint">Not yet overridden; the default is in force.</p>
              </div>
            </div>

            <div v-if="setting.editable" class="flex justify-end">
              <BaseButton
                variant="outline"
                class="text-[11px]"
                :disabled="isSaving"
                @click="requestReset('setting', setting.key, setting.label)"
              >
                <RotateCcw class="w-3.5 h-3.5 mr-1" />
                Reset to default
              </BaseButton>
            </div>
          </div>
        </div>
      </div>

      <!-- Deployment boundary -->
      <div class="p-5 rounded-xl bg-surface border border-hairline shadow-xs space-y-4">
        <h2 class="text-sm font-semibold text-ink-secondary flex items-center gap-2">
          <Lock class="w-4 h-4 text-primary" />
          Deployment Boundary
        </h2>
        <p class="text-[11px] text-ink-muted">
          These values are managed by the deployment environment. They are shown for reference and
          cannot be edited here.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2">
          <div
            v-for="entry in deployment"
            :key="entry.key"
            class="flex items-baseline justify-between gap-3 py-1 border-b border-hairline/60"
          >
            <span class="text-[11px] text-ink-secondary">{{ entry.label }}</span>
            <span
              class="text-[11px] font-mono text-right"
              :class="entry.sensitive ? 'text-ink-faint italic' : 'text-ink'"
            >
              {{ entry.sensitive ? 'managed externally' : entry.value || '—' }}
            </span>
          </div>
        </div>

        <div class="p-3.5 rounded-lg bg-canvas-soft border border-hairline text-xs text-ink-muted flex items-start gap-2.5">
          <ShieldCheck class="w-4 h-4 shrink-0 mt-0.5 text-primary" />
          <span>
            Database credentials, encryption keys (<code>APP_KEY</code>), signing certificates and
            storage credentials are strictly managed by environment configuration and are never
            exposed to the browser interface.
          </span>
        </div>
      </div>

      <!-- Save bar -->
      <div class="space-y-3">
        <div
          v-if="saveError"
          class="p-3 rounded-lg bg-[#fdecec] dark:bg-[#3a1414] border border-[#e03e3e]/20 flex items-start gap-2"
          role="alert"
        >
          <AlertCircle class="w-4 h-4 shrink-0 mt-0.5 text-[#e03e3e] dark:text-[#f87171]" />
          <p class="text-xs text-ink leading-relaxed">{{ saveError }}</p>
        </div>

        <div class="flex items-center justify-between gap-3">
          <p class="text-[11px] text-ink-muted" role="status" aria-live="polite">
            <template v-if="isSaving">Saving configuration…</template>
            <template v-else-if="hasUnsavedChanges">
              {{ dirtyKeys.length }} unsaved change{{ dirtyKeys.length === 1 ? '' : 's' }}
            </template>
            <template v-else>All changes saved</template>
          </p>

          <div class="flex gap-2">
            <BaseButton
              variant="outline"
              :disabled="!hasUnsavedChanges || isSaving"
              @click="cancelChanges"
            >
              <X class="w-3.5 h-3.5 mr-1" />
              Cancel
            </BaseButton>
            <BaseButton
              variant="primary"
              :loading="isSaving"
              :disabled="!hasUnsavedChanges || isSaving"
              @click="saveChanges"
            >
              <Save class="w-4 h-4 mr-1.5" />
              Save Configuration
            </BaseButton>
          </div>
        </div>
      </div>
    </template>

    <!-- Reset confirmation -->
    <ModalDialog
      :open="pendingReset !== null"
      :title="pendingReset?.kind === 'category' ? 'Reset category to defaults' : 'Reset setting to default'"
      @close="pendingReset = null"
    >
      <div class="space-y-3">
        <p class="text-xs text-ink leading-relaxed">
          <template v-if="pendingReset?.kind === 'category'">
            Every setting in the
            <strong>{{ pendingReset?.label }}</strong>
            category will be returned to its catalogue default. Values that are currently in force
            will be removed.
          </template>
          <template v-else>
            <strong>{{ pendingReset?.label }}</strong> will be returned to its catalogue default. The
            value currently in force will be removed.
          </template>
        </p>

        <p class="text-[11px] text-ink-muted">
          The change is recorded in the security audit trail.
        </p>

        <div class="flex justify-end gap-2 pt-1">
          <BaseButton variant="outline" :disabled="isSaving" @click="pendingReset = null">
            Keep current value
          </BaseButton>
          <BaseButton variant="primary" :loading="isSaving" @click="confirmReset">
            <CheckCircle2 class="w-3.5 h-3.5 mr-1" />
            Confirm reset
          </BaseButton>
        </div>
      </div>
    </ModalDialog>
  </div>
</template>

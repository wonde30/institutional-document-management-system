<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    modelValue?: string | number | null
    label?: string
    placeholder?: string
    options?: Array<{ label: string; value: string | number }>
    error?: string
    hint?: string
    required?: boolean
    disabled?: boolean
    id?: string
  }>(),
  {
    required: false,
    disabled: false,
  }
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
}>()

const fieldId = computed(() => props.id || `select-${Math.random().toString(36).slice(2, 8)}`)

function handleChange(event: Event) {
  const target = event.target as HTMLSelectElement
  emit('update:modelValue', target.value)
}
</script>

<template>
  <div class="w-full">
    <label
      v-if="label"
      :for="fieldId"
      class="block text-xs font-semibold text-ink-secondary mb-1.5"
    >
      {{ label }}
      <span v-if="required" class="text-[#e03e3e] ml-0.5" aria-hidden="true">*</span>
    </label>
    <div class="relative">
      <select
        :id="fieldId"
        :value="modelValue ?? ''"
        :required="required"
        :disabled="disabled"
        :aria-invalid="!!error"
        :aria-describedby="(error || hint) ? fieldId + '-hint' : undefined"
        @change="handleChange"
        class="w-full px-3.5 py-2 h-9 text-sm text-ink bg-surface border rounded-md transition-colors duration-150 focus:outline-hidden focus:ring-2 disabled:bg-canvas-soft disabled:text-ink-faint disabled:cursor-not-allowed appearance-none pr-8 cursor-pointer"
        :class="[
          error
            ? 'border-[#e03e3e] dark:border-[#f87171] focus:border-[#e03e3e] dark:focus:border-[#f87171] focus:ring-[#e03e3e]/20 dark:focus:ring-[#f87171]/20'
            : 'border-hairline focus:border-primary dark:focus:border-[#2b8bee] focus:ring-primary/20 dark:focus:ring-[#2b8bee]/30'
        ]"
      >
        <option v-if="placeholder" value="" disabled :selected="!modelValue">
          {{ placeholder }}
        </option>
        <template v-if="options && options.length > 0">
          <option
            v-for="opt in options"
            :key="opt.value"
            :value="opt.value"
          >
            {{ opt.label }}
          </option>
        </template>
        <slot></slot>
      </select>
      <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2.5 text-ink-faint" aria-hidden="true">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </div>
    </div>
    <p
      v-if="error"
      :id="fieldId + '-hint'"
      class="mt-1 text-xs text-[#e03e3e] dark:text-[#f87171] font-medium"
      role="alert"
    >
      {{ error }}
    </p>
    <p v-else-if="hint" :id="fieldId + '-hint'" class="mt-1 text-xs text-ink-muted">
      {{ hint }}
    </p>
  </div>
</template>

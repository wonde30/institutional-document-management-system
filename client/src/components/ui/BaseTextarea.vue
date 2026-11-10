<script setup lang="ts">
const props = withDefaults(
 defineProps<{
 modelValue?: string
 id?: string
 label?: string
 placeholder?: string
 rows?: number | string
 required?: boolean
 disabled?: boolean
 error?: string
 hint?: string
 }>(),
 {
 modelValue: '',
 rows: 3,
 required: false,
 disabled: false,
 }
)

const emit = defineEmits<{
 (e: 'update:modelValue', value: string): void
}>()

function onInput(event: Event) {
 const target = event.target as HTMLTextAreaElement
 emit('update:modelValue', target.value)
}
</script>

<template>
 <div class="w-full">
 <label
 v-if="label"
 :for="id"
 class="block text-xs font-semibold uppercase tracking-wider text-ink-secondary mb-1.5"
 >
 {{ label }}
 <span v-if="required" class="text-[#e03e3e] ml-0.5">*</span>
 </label>

 <textarea
 :id="id"
 :value="modelValue"
 :placeholder="placeholder"
 :rows="rows"
 :required="required"
 :disabled="disabled"
 @input="onInput"
 class="w-full rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint transition focus:outline-hidden disabled:bg-canvas-soft disabled:text-ink-faint disabled:cursor-not-allowed "
 :class="[
 error
 ? 'border-[#e03e3e] dark:border-[#f87171] focus:border-[#e03e3e] dark:focus:border-[#f87171] focus:ring-2 focus:ring-[#e03e3e]/20 dark:focus:ring-[#f87171]/20'
 : 'border-hairline focus:border-primary dark:focus:border-[#2b8bee] focus:ring-2 focus:ring-primary/20 dark:focus:ring-[#2b8bee]/30',
 ]"
 ></textarea>

 <p v-if="error" class="mt-1 text-xs text-[#e03e3e] dark:text-[#f87171] font-medium">
 {{ error }}
 </p>
 <p v-else-if="hint" class="mt-1 text-xs text-ink-muted">
 {{ hint }}
 </p>
 </div>
</template>

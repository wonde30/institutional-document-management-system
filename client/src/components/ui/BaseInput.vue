<script setup lang="ts">
withDefaults(
 defineProps<{
 modelValue?: string | number | null
 type?: string
 label?: string
 placeholder?: string
 error?: string
 hint?: string
 required?: boolean
 disabled?: boolean
 id?: string
 }>(),
 {
 type: 'text',
 required: false,
 disabled: false,
 }
)

const emit = defineEmits<{
 (e: 'update:modelValue', value: string): void
}>()

function handleInput(event: Event) {
 const target = event.target as HTMLInputElement
 emit('update:modelValue', target.value)
}
</script>

<template>
 <div class="w-full">
 <label
 v-if="label"
 :for="id"
 class="block text-xs font-semibold text-ink-secondary uppercase tracking-wider mb-1.5"
 >
 {{ label }}
 <span v-if="required" class="text-[#e03e3e] ml-0.5">*</span>
 </label>
 <div class="relative flex items-center">
 <div v-if="$slots.prefix" class="absolute left-3 flex items-center pointer-events-none text-ink-faint">
 <slot name="prefix" />
 </div>
 <input
 :id="id"
 :type="type"
 :value="modelValue ?? ''"
 :placeholder="placeholder"
 :required="required"
 :disabled="disabled"
 @input="handleInput"
 class="w-full px-3.5 py-2 text-sm text-ink bg-surface border rounded-lg transition-colors duration-150 placeholder:text-ink-faint focus:outline-hidden focus:ring-2 disabled:bg-canvas-soft disabled:text-ink-faint disabled:cursor-not-allowed"
 :class="[
 $slots.prefix ? 'pl-9' : '',
 $slots.suffix ? 'pr-9' : '',
 error
 ? 'border-[#e03e3e] dark:border-[#f87171] focus:border-[#e03e3e] dark:focus:border-[#f87171] focus:ring-[#e03e3e]/20 dark:focus:ring-[#f87171]/20'
 : 'border-hairline focus:border-primary dark:focus:border-[#2b8bee] focus:ring-primary/20 dark:focus:ring-[#2b8bee]/30'
 ]"
 />
 <div v-if="$slots.suffix" class="absolute right-3 flex items-center pointer-events-none text-ink-faint">
 <slot name="suffix" />
 </div>
 </div>
 <p v-if="error" class="mt-1 text-xs text-[#e03e3e] dark:text-[#f87171] font-medium">
 {{ error }}
 </p>
 <p v-else-if="hint" class="mt-1 text-xs text-ink-muted">
 {{ hint }}
 </p>
 </div>
</template>

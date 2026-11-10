<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue'

const props = withDefaults(
 defineProps<{
 isOpen?: boolean
 show?: boolean
 open?: boolean
 modelValue?: boolean
 title: string
 size?: 'sm' | 'md' | 'lg' | 'xl'
 maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | string
 }>(),
 {
 isOpen: undefined,
 show: undefined,
 open: undefined,
 modelValue: undefined,
 size: 'md',
 }
)

const emit = defineEmits<{
 (e: 'close'): void
 (e: 'update:modelValue', value: boolean): void
}>()

const active = computed(() => props.open ?? props.show ?? props.isOpen ?? props.modelValue ?? false)

function handleKeyDown(event: KeyboardEvent) {
 if (event.key === 'Escape' && active.value) {
 emit('close')
 emit('update:modelValue', false)
 }
}

const sizeClasses = computed(() => {
 const s = props.maxWidth || props.size || 'md'
 switch (s) {
 case 'sm':
 return 'max-w-sm'
 case 'lg':
 return 'max-w-2xl'
 case 'xl':
 return 'max-w-4xl'
 case 'md':
 default:
 return 'max-w-lg'
 }
})

onMounted(() => window.addEventListener('keydown', handleKeyDown))
onUnmounted(() => window.removeEventListener('keydown', handleKeyDown))
</script>

<template>
 <div
 v-if="active"
 class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs transition-opacity modal-overlay"
 @click.self="emit('close')"
 >
 <div
 class="w-full bg-surface rounded-xl notion-shadow-elevated border border-hairline overflow-hidden transform transition-all modal-dialog"
 :class="sizeClasses"
 role="dialog"
 aria-modal="true"
 >
 <!-- Modal Header -->
 <div class="px-5 py-4 border-b border-hairline flex items-center justify-between modal-header">
 <h3 class="text-base font-semibold text-ink card-title">
 {{ title }}
 </h3>
 <button
 type="button"
 class="p-1 text-ink-faint hover:text-ink rounded-lg hover:bg-canvas-soft transition-colors cursor-pointer btn-close"
 @click="emit('close')"
 aria-label="Close"
 >
 <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
 </svg>
 </button>
 </div>

 <!-- Modal Body -->
 <div class="p-5 max-h-[calc(85vh-120px)] overflow-y-auto modal-body text-ink-secondary">
 <slot name="body">
 <slot></slot>
 </slot>
 </div>

 <!-- Modal Footer -->
 <div
 v-if="$slots.footer"
 class="px-5 py-3.5 bg-canvas-soft border-t border-hairline flex items-center justify-end gap-2.5 modal-footer"
 >
 <slot name="footer"></slot>
 </div>
 </div>
 </div>
</template>

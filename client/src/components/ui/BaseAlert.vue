<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
 defineProps<{
 variant?: 'success' | 'danger' | 'warning' | 'info'
 dismissible?: boolean
 }>(),
 {
 variant: 'info',
 dismissible: true,
 }
)

const emit = defineEmits<{
 (e: 'dismiss'): void
}>()

const variantClasses = computed(() => {
 switch (props.variant) {
 case 'success':
 return 'bg-emerald-50 text-emerald-800 border-emerald-200'
 case 'danger':
 return 'bg-red-50 text-red-800 border-red-200'
 case 'warning':
 return 'bg-amber-50 text-amber-800 border-amber-200'
 case 'info':
 default:
 return 'bg-blue-50 text-blue-800 border-blue-200'
 }
})
</script>

<template>
 <div
 class="flex items-start justify-between p-4 rounded-lg border text-sm font-medium transition-all"
 :class="variantClasses"
 role="alert"
 >
 <div class="flex items-start gap-2.5">
 <div class="grow">
 <slot></slot>
 </div>
 </div>
 <button
 v-if="dismissible"
 type="button"
 @click="emit('dismiss')"
 class="text-current opacity-60 hover:opacity-100 p-0.5 rounded-md transition-opacity cursor-pointer ml-3"
 aria-label="Close alert"
 >
 <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
 </svg>
 </button>
 </div>
</template>

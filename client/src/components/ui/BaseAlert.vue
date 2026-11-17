<script setup lang="ts">
import { computed } from 'vue'
import { X, CheckCircle2, AlertCircle, AlertTriangle, Info } from 'lucide-vue-next'

const props = withDefaults(
 defineProps<{
 variant?: 'success' | 'danger' | 'warning' | 'info'
 dismissible?: boolean
 title?: string
 showIcon?: boolean
 }>(),
 {
 variant: 'info',
 dismissible: true,
 showIcon: true,
 }
)

const emit = defineEmits<{
 (e: 'dismiss'): void
}>()

const variantClasses = computed(() => {
 switch (props.variant) {
 case 'success':
 return 'bg-[#e8f7ec] dark:bg-[#14301d] text-[#1aae39] dark:text-[#34d399] border-[#1aae39]/30'
 case 'danger':
 return 'bg-[#fde8e8] dark:bg-[#371717] text-[#e03e3e] dark:text-[#f87171] border-[#e03e3e]/30'
 case 'warning':
 return 'bg-[#fff4e6] dark:bg-[#3b220c] text-[#dd5b00] dark:text-[#f59e0b] border-[#dd5b00]/30'
 case 'info':
 default:
 return 'bg-[#e8f3fc] dark:bg-[#172c42] text-[#0075de] dark:text-[#62aef0] border-[#0075de]/30'
 }
})

const iconComponent = computed(() => {
 switch (props.variant) {
 case 'success':
 return CheckCircle2
 case 'danger':
 return AlertCircle
 case 'warning':
 return AlertTriangle
 case 'info':
 default:
 return Info
 }
})
</script>

<template>
 <div
 class="flex items-start justify-between p-3.5 rounded-lg border text-xs leading-relaxed font-medium transition-all shadow-xs"
 :class="variantClasses"
 role="alert"
 >
 <div class="flex items-start gap-2.5 grow">
 <component
 :is="iconComponent"
 v-if="showIcon"
 class="w-4 h-4 shrink-0 mt-0.5"
 />
 <div class="grow">
 <div v-if="title" class="font-semibold text-xs mb-0.5 uppercase tracking-wider">
 {{ title }}
 </div>
 <div>
 <slot></slot>
 </div>
 </div>
 </div>
 <button
 v-if="dismissible"
 type="button"
 @click="emit('dismiss')"
 class="text-current opacity-60 hover:opacity-100 p-0.5 rounded-md transition-opacity cursor-pointer ml-3 shrink-0"
 aria-label="Close alert"
 >
 <X class="w-4 h-4" />
 </button>
 </div>
</template>

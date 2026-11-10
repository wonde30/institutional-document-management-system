<script setup lang="ts">
import { computed } from 'vue'
import { getPriorityBadgeClass } from '@/utils/formatters'

const props = defineProps<{
 priority?: string
}>()

const badgeClass = computed(() => getPriorityBadgeClass(props.priority))

const tailwindClasses = computed(() => {
 switch (props.priority?.toLowerCase()) {
 case 'urgent':
 return 'bg-red-100 text-red-800 border border-red-300 dark:bg-red-950/50 dark:text-red-300 dark:border-red-800 font-semibold'
 case 'high':
 return 'bg-amber-100 text-amber-800 border border-amber-300 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800'
 case 'normal':
 return 'bg-primary-subtle text-primary border border-primary/20 dark:bg-[#172c42] dark:text-[#62aef0] dark:border-[#2b8bee]/30'
 case 'low':
 default:
 return 'bg-slate-100 text-slate-600 border border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700'
 }
})
</script>

<template>
 <span
 :class="[
 'inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium tracking-wide uppercase',
 tailwindClasses,
 'badge',
 badgeClass
 ]"
 >
 {{ priority || 'normal' }}
 </span>
</template>

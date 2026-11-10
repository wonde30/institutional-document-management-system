<script setup lang="ts">
import { computed } from 'vue'
import { getClassificationBadge } from '@/utils/formatters'
import type { SecurityClassification } from '@/types'

const props = defineProps<{
 level?: number
 name?: string
 classification?: SecurityClassification | { name?: string; level?: number; code?: string }
}>()

const badge = computed(() => {
 const name = props.classification?.name || props.name
 const level = props.classification?.level ?? props.level

 if (name) {
 switch (name.toLowerCase()) {
 case 'restricted': return { label: 'Restricted', className: 'badge-restricted' }
 case 'confidential': return { label: 'Confidential', className: 'badge-confidential' }
 case 'internal': return { label: 'Internal', className: 'badge-internal' }
 case 'public': return { label: 'Public', className: 'badge-public' }
 }
 }

 if (level !== undefined) {
 return getClassificationBadge(level)
 }

 if (props.classification === undefined && props.level === undefined && props.name === undefined) {
 return { label: 'Unclassified', className: 'badge-secondary' }
 }

 return { label: name || 'Public', className: 'badge-public' }
})

const tailwindClasses = computed(() => {
 switch (badge.value.className) {
 case 'badge-restricted':
 return 'bg-red-50 text-red-700 border border-red-200 dark:bg-red-950/40 dark:text-red-300 dark:border-red-900/60'
 case 'badge-confidential':
 return 'bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900/60'
 case 'badge-internal':
 return 'bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-900/60'
 case 'badge-public':
 return 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900/60'
 default:
 return 'bg-slate-100 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700'
 }
})
</script>

<template>
 <span
 :class="[
 'inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold tracking-wide',
 tailwindClasses,
 'badge',
 badge.className
 ]"
 >
 {{ badge.label }}
 </span>
</template>

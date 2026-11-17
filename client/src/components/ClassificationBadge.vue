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

const tokenClasses = computed(() => {
 switch (badge.value.className) {
 case 'badge-restricted':
 return 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/10 dark:bg-red-950 dark:text-red-300 dark:ring-red-500/20'
 case 'badge-confidential':
 return 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/10 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-500/20'
 case 'badge-internal':
 return 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/10 dark:bg-blue-950 dark:text-blue-300 dark:ring-blue-500/20'
 case 'badge-public':
 return 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/10 dark:bg-green-950 dark:text-green-300 dark:ring-green-500/20'
 default:
 return 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/10 dark:bg-gray-900 dark:text-gray-300 dark:ring-gray-500/20'
 }
})
</script>

<template>
 <span
 :class="[
 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium',
 tokenClasses,
 'badge',
 badge.className
 ]"
 >
 {{ badge.label }}
 </span>
</template>

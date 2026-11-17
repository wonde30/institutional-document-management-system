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
 return 'bg-[#fde8e8] text-[#e03e3e] dark:bg-[#371717] dark:text-[#f87171]'
 case 'badge-confidential':
 return 'bg-[#fff4e6] text-[#dd5b00] dark:bg-[#3b220c] dark:text-[#f59e0b]'
 case 'badge-internal':
 return 'bg-[#e8f3fc] text-[#0075de] dark:bg-[#172c42] dark:text-[#62aef0]'
 case 'badge-public':
 return 'bg-[#e8f7ec] text-[#1aae39] dark:bg-[#14301d] dark:text-[#34d399]'
 default:
 return 'bg-[var(--wu-line)] text-[var(--wu-muted)]'
 }
})
</script>

<template>
 <span
 :class="[
 'inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium',
 tokenClasses,
 'badge',
 badge.className
 ]"
 >
 {{ badge.label }}
 </span>
</template>

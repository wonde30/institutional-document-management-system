<script setup lang="ts">
import { computed } from 'vue'
import { getPriorityBadgeClass } from '@/utils/formatters'

const props = defineProps<{
 priority?: string
}>()

const badgeClass = computed(() => getPriorityBadgeClass(props.priority))

const tokenClasses = computed(() => {
 switch (props.priority?.toLowerCase()) {
 case 'urgent':
 return 'bg-[#fde8e8] text-[#e03e3e] dark:bg-[#371717] dark:text-[#f87171] font-semibold'
 case 'high':
 return 'bg-[#fff4e6] text-[#dd5b00] dark:bg-[#3b220c] dark:text-[#f59e0b]'
 case 'normal':
 return 'bg-[#e8f3fc] text-[#0075de] dark:bg-[#172c42] dark:text-[#62aef0]'
 case 'low':
 default:
 return 'bg-[var(--wu-line)] text-[var(--wu-muted)]'
 }
})
</script>

<template>
 <span
 :class="[
 'inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold tracking-wider uppercase',
 tokenClasses,
 'badge',
 badgeClass
 ]"
 >
 {{ priority || 'normal' }}
 </span>
</template>

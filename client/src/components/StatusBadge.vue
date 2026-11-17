<script setup lang="ts">
import { computed } from 'vue'
import { getStatusBadgeClass, formatStatus } from '@/utils/formatters'

const props = defineProps<{
 status?: string
}>()

const formattedStatus = computed(() => formatStatus(props.status))

const badgeClass = computed(() => getStatusBadgeClass(props.status))

const tokenClasses = computed(() => {
 switch (props.status?.toLowerCase()) {
 case 'approved':
 case 'completed':
 case 'dispatched':
 case 'executed':
 case 'acknowledged':
 case 'accepted':
 return 'bg-[#e8f7ec] text-[#1aae39] dark:bg-[#14301d] dark:text-[#34d399]'
 case 'pending_review':
 case 'under_review':
 case 'assigned':
 case 'in_progress':
 case 'pending':
 case 'needs_revision':
 return 'bg-[#fff4e6] text-[#dd5b00] dark:bg-[#3b220c] dark:text-[#f59e0b]'
 case 'rejected':
 case 'overdue':
 case 'cancelled':
 return 'bg-[#fde8e8] text-[#e03e3e] dark:bg-[#371717] dark:text-[#f87171]'
 case 'archived':
 case 'disposed':
 return 'bg-[var(--wu-line)] text-[var(--wu-muted)]'
 case 'draft':
 case 'received':
 case 'sent':
 default:
 return 'bg-[#e8f3fc] text-[#0075de] dark:bg-[#172c42] dark:text-[#62aef0]'
 }
})
</script>

<template>
 <span
 :class="[
 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium',
 tokenClasses,
 'badge',
 badgeClass
 ]"
 >
 {{ formattedStatus }}
 </span>
</template>

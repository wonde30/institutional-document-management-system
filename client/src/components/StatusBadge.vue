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
 return 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/10 dark:bg-green-950 dark:text-green-300 dark:ring-green-500/20'
 case 'pending_review':
 case 'under_review':
 case 'assigned':
 case 'in_progress':
 case 'pending':
 case 'needs_revision':
 return 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/10 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-500/20'
 case 'rejected':
 case 'overdue':
 case 'cancelled':
 return 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/10 dark:bg-red-950 dark:text-red-300 dark:ring-red-500/20'
 case 'archived':
 case 'disposed':
 return 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/10 dark:bg-gray-900 dark:text-gray-300 dark:ring-gray-500/20'
 case 'draft':
 case 'received':
 case 'sent':
 default:
 return 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/10 dark:bg-blue-950 dark:text-blue-300 dark:ring-blue-500/20'
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

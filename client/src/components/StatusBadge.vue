<script setup lang="ts">
import { computed } from 'vue'
import { getStatusBadgeClass } from '@/utils/formatters'

const props = defineProps<{
 status?: string
}>()

const formattedStatus = computed(() => {
 if (!props.status) return 'UNKNOWN'
 return props.status.replace(/_/g, ' ')
})

const badgeClass = computed(() => getStatusBadgeClass(props.status))

const tailwindClasses = computed(() => {
 switch (props.status?.toLowerCase()) {
 case 'approved':
 case 'completed':
 case 'dispatched':
 case 'executed':
 case 'acknowledged':
 return 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900/60'
 case 'pending_review':
 case 'under_review':
 case 'assigned':
 case 'in_progress':
 case 'pending':
 return 'bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900/60'
 case 'rejected':
 case 'overdue':
 case 'cancelled':
 return 'bg-red-50 text-red-700 border border-red-200 dark:bg-red-950/40 dark:text-red-300 dark:border-red-900/60'
 case 'archived':
 case 'disposed':
 return 'bg-slate-100 text-slate-700 border border-slate-300 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700'
 case 'draft':
 case 'received':
 case 'sent':
 default:
 return 'bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-900/60'
 }
})
</script>

<template>
 <span
 :class="[
 'inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium tracking-wide transition-colors',
 tailwindClasses,
 'badge',
 badgeClass
 ]"
 >
 {{ formattedStatus }}
 </span>
</template>

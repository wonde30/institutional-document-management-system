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
 return 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/10 dark:bg-red-950 dark:text-red-300 dark:ring-red-500/20 font-semibold'
 case 'high':
 return 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/10 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-500/20'
 case 'normal':
 return 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/10 dark:bg-blue-950 dark:text-blue-300 dark:ring-blue-500/20'
 case 'low':
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
      badgeClass
    ]"
  >
    {{ priority || 'normal' }}
  </span>
</template>

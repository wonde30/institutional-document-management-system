<script setup lang="ts">
import { computed, type Component } from 'vue'

const props = withDefaults(
 defineProps<{
 title: string
 value: string | number
 subtitle?: string
 badgeText?: string
 badgeVariant?: 'success' | 'warning' | 'danger' | 'info'
 icon?: Component | Function | object
 }>(),
 {
 badgeVariant: 'info',
 }
)

const badgeClasses = computed(() => {
 switch (props.badgeVariant) {
 case 'success':
 return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800'
 case 'warning':
 return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800'
 case 'danger':
 return 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800'
 case 'info':
 default:
 return 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800'
 }
})
</script>

<template>
 <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs transition hover:border-blue-400 dark:bg-slate-900 dark:border-slate-800 dark:hover:border-blue-600">
 <div class="flex items-center justify-between">
 <span class="text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">
 {{ title }}
 </span>
 <span
 v-if="badgeText"
 class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium border"
 :class="badgeClasses"
 >
 {{ badgeText }}
 </span>
 <div v-else-if="icon" class="p-2 rounded-xl bg-slate-50 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
 <component :is="icon" class="w-4 h-4" />
 </div>
 </div>
 <div class="mt-2.5 flex items-baseline gap-2">
 <span class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
 {{ value }}
 </span>
 </div>
 <p v-if="subtitle" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
 {{ subtitle }}
 </p>
 </div>
</template>

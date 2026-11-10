<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
 defineProps<{
 variant?: 'primary' | 'secondary' | 'danger' | 'warning' | 'success' | 'outline' | 'ghost'
 size?: 'sm' | 'md' | 'lg'
 type?: 'button' | 'submit' | 'reset'
 disabled?: boolean
 loading?: boolean
 }>(),
 {
 variant: 'primary',
 size: 'md',
 type: 'button',
 disabled: false,
 loading: false,
 }
)

const variantClasses = computed(() => {
 switch (props.variant) {
 case 'primary':
 return 'bg-blue-700 hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-700 text-white shadow-xs focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2'
 case 'secondary':
 return 'bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 shadow-xs focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 dark:border-slate-700'
 case 'danger':
 return 'bg-rose-600 hover:bg-rose-700 text-white shadow-xs focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2'
 case 'warning':
 return 'bg-amber-600 hover:bg-amber-700 text-white shadow-xs focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2'
 case 'success':
 return 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2'
 case 'outline':
 return 'bg-transparent border border-blue-700 text-blue-700 hover:bg-blue-50 focus-visible:ring-2 focus-visible:ring-blue-600 dark:border-blue-400 dark:text-blue-400 dark:hover:bg-blue-950/40'
 case 'ghost':
 return 'bg-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'
 default:
 return 'bg-blue-700 text-white'
 }
})

const sizeClasses = computed(() => {
 switch (props.size) {
 case 'sm':
 return 'px-2.5 py-1.5 text-xs font-medium rounded-md gap-1.5'
 case 'lg':
 return 'px-5 py-2.5 text-base font-semibold rounded-lg gap-2.5'
 case 'md':
 default:
 return 'px-3.5 py-2 text-sm font-medium rounded-lg gap-2'
 }
})
</script>

<template>
 <button
 :type="type"
 :disabled="disabled || loading"
 class="inline-flex items-center justify-center font-medium transition-all duration-150 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed select-none active:scale-[0.98]"
 :class="[variantClasses, sizeClasses]"
 :style="variant === 'primary' ? { backgroundColor: 'var(--color-brand-primary, var(--wu-primary))', borderColor: 'var(--color-brand-primary, var(--wu-primary))' } : {}"
 >
 <svg
 v-if="loading"
 class="animate-spin -ml-0.5 h-4 w-4 text-current"
 fill="none"
 viewBox="0 0 24 24"
 >
 <circle
 class="opacity-25"
 cx="12"
 cy="12"
 r="10"
 stroke="currentColor"
 stroke-width="4"
 ></circle>
 <path
 class="opacity-75"
 fill="currentColor"
 d="M4 12a8 8 0 018-8v8H4z"
 ></path>
 </svg>
 <slot></slot>
 </button>
</template>

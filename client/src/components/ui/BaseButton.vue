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
      return 'bg-primary hover:bg-primary-hover text-on-primary focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:ring-offset-2'
    case 'secondary':
      return 'bg-surface hover:bg-surface-hover text-ink-secondary border border-hairline focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:ring-offset-2'
    case 'danger':
      return 'bg-[var(--wu-danger)] hover:bg-[#c53030] text-white focus-visible:ring-2 focus-visible:ring-[var(--wu-danger)]/30 focus-visible:ring-offset-2'
    case 'warning':
      return 'bg-[#fff4e6] dark:bg-[#3b220c] text-[#793400] dark:text-[#f59e0b] border border-[#dd5b00]/40 hover:bg-[#ffe8cc] dark:hover:bg-[#4a2c10] focus-visible:ring-2 focus-visible:ring-[#dd5b00]/30 focus-visible:ring-offset-2'
    case 'success':
      return 'bg-[var(--wu-success)] hover:bg-[#158f2e] text-white focus-visible:ring-2 focus-visible:ring-[var(--wu-success)]/30 focus-visible:ring-offset-2'
    case 'outline':
      return 'bg-surface border border-hairline text-ink-secondary hover:bg-surface-hover hover:text-ink focus-visible:ring-2 focus-visible:ring-primary/30'
    case 'ghost':
      return 'bg-transparent text-ink-secondary hover:bg-canvas-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-primary/30'
    default:
      return 'bg-primary hover:bg-primary-hover text-on-primary'
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
      return 'px-3.5 py-2 text-sm font-medium rounded-md gap-2'
  }
})
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    class="inline-flex items-center justify-center font-medium transition-all duration-150 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed select-none active:scale-[0.98]"
    :class="[variantClasses, sizeClasses]"
  >
    <svg
      v-if="loading"
      class="animate-spin -ml-0.5 h-4 w-4 text-current"
      fill="none"
      viewBox="0 0 24 24"
      aria-hidden="true"
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

<script setup lang="ts">
import { computed } from 'vue'
import type { PaginationMeta } from '@/types'

const props = defineProps<{
  meta?: PaginationMeta
  currentPage?: number
  lastPage?: number
  total?: number
  perPage?: number
  from?: number | null
  to?: number | null
}>()

const emit = defineEmits<{
  (e: 'page-change', page: number): void
}>()

const current = computed(() => props.meta?.current_page ?? props.currentPage ?? 1)
const itemsPerPage = computed(() => props.meta?.per_page ?? props.perPage ?? 15)
const totalItems = computed(() => props.meta?.total ?? props.total ?? 0)

const last = computed(() => {
  if (props.meta?.last_page) return props.meta.last_page
  if (props.lastPage) return props.lastPage
  return Math.max(1, Math.ceil(totalItems.value / (itemsPerPage.value || 1)))
})

const fromItem = computed(() => {
  if (props.meta?.from !== undefined && props.meta?.from !== null) return props.meta.from
  if (props.from !== undefined && props.from !== null) return props.from
  if (totalItems.value === 0) return 0
  return (current.value - 1) * itemsPerPage.value + 1
})

const toItem = computed(() => {
  if (props.meta?.to !== undefined && props.meta?.to !== null) return props.meta.to
  if (props.to !== undefined && props.to !== null) return props.to
  if (totalItems.value === 0) return 0
  return Math.min(totalItems.value, current.value * itemsPerPage.value)
})

/** Compute visible page number sequence with ellipsis for large ranges */
const pageNumbers = computed<(number | '...')[]>(() => {
  const total = last.value
  if (total <= 1) return []
  if (total <= 7) {
    return Array.from({ length: total }, (_, i) => i + 1)
  }
  const c = current.value
  const pages: (number | '...')[] = [1]
  if (c > 3) pages.push('...')
  for (let p = Math.max(2, c - 1); p <= Math.min(total - 1, c + 1); p++) {
    pages.push(p)
  }
  if (c < total - 2) pages.push('...')
  pages.push(total)
  return pages
})
</script>

<template>
  <nav
    v-if="totalItems > 0 && last > 0"
    class="flex items-center justify-between px-4 py-3 bg-surface border-t border-hairline text-xs text-ink-muted select-none"
    aria-label="Pagination"
  >
    <!-- Item count summary -->
    <div class="hidden sm:block text-xs text-ink-muted">
      Showing
      <strong class="font-semibold text-ink">{{ fromItem }}</strong>–<strong class="font-semibold text-ink">{{ toItem }}</strong>
      of
      <strong class="font-semibold text-ink">{{ totalItems }}</strong>
    </div>

    <!-- Page controls -->
    <div v-if="last > 1" class="flex items-center gap-1 ml-auto">
      <button
        type="button"
        class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-md bg-surface border border-hairline text-ink-secondary hover:bg-canvas-soft disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
        :disabled="current <= 1"
        aria-label="Previous page"
        @click="emit('page-change', current - 1)"
      >
        ‹ Prev
      </button>

      <template v-for="page in pageNumbers" :key="String(page) + '-' + (page === '...' ? Math.random() : '')">
        <span
          v-if="page === '...'"
          class="px-1.5 py-1.5 text-xs text-ink-faint select-none"
          aria-hidden="true"
        >…</span>
        <button
          v-else
          type="button"
          class="inline-flex items-center justify-center w-8 h-8 text-xs font-medium rounded-md border transition-colors cursor-pointer"
          :class="page === current
            ? 'bg-primary border-primary text-white font-semibold'
            : 'bg-surface border-hairline text-ink-secondary hover:bg-canvas-soft'"
          :aria-label="`Page ${page}`"
          :aria-current="page === current ? 'page' : undefined"
          @click="page !== current ? emit('page-change', Number(page)) : undefined"
        >
          {{ page }}
        </button>
      </template>

      <button
        type="button"
        class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-md bg-surface border border-hairline text-ink-secondary hover:bg-canvas-soft disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
        :disabled="current >= last"
        aria-label="Next page"
        @click="emit('page-change', current + 1)"
      >
        Next ›
      </button>
    </div>
  </nav>
</template>

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
</script>

<template>
 <div
 v-if="totalItems > 0"
 class="flex items-center justify-between px-4 py-3 bg-surface border-t border-hairline text-xs sm:text-sm text-ink-muted select-none pagination-container"
 >
 <div class="pagination-info">
 Showing <strong class="font-semibold text-ink">{{ fromItem }}</strong> to
 <strong class="font-semibold text-ink">{{ toItem }}</strong> of
 <strong class="font-semibold text-ink">{{ totalItems }}</strong> entries
 </div>
 <div v-if="last > 1" class="flex items-center gap-2 pagination-actions">
 <button
 class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md bg-surface border border-hairline text-ink-secondary hover:bg-canvas-soft disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer btn btn-secondary btn-sm shadow-xs"
 :disabled="current <= 1"
 aria-label="Previous Page"
 @click="emit('page-change', current - 1)"
 >
 Previous
 </button>

 <span class="px-2 text-xs font-semibold text-ink pagination-current">
 Page {{ current }} of {{ last }}
 </span>

 <button
 class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md bg-surface border border-hairline text-ink-secondary hover:bg-canvas-soft disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer btn btn-secondary btn-sm shadow-xs"
 :disabled="current >= last"
 aria-label="Next Page"
 @click="emit('page-change', current + 1)"
 >
 Next
 </button>
 </div>
 </div>
</template>

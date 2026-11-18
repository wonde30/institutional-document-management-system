<script setup lang="ts">
import { computed, useSlots } from 'vue'
import { ArrowUpDown, ArrowUp, ArrowDown } from 'lucide-vue-next'
import LoadingSkeleton from '@/components/ui/LoadingSkeleton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Pagination from '@/components/Pagination.vue'

export interface TableColumn {
 key: string
 label: string
 sortable?: boolean
 align?: 'left' | 'center' | 'right'
 class?: string
 headerClass?: string
}

const props = withDefaults(
 defineProps<{
 columns: TableColumn[]
 items: any[]
 loading?: boolean
 sortBy?: string
 sortOrder?: 'asc' | 'desc'
 emptyTitle?: string
 emptyDescription?: string
 selectable?: boolean
 selectedIds?: (string | number)[]
 idKey?: string
 // Built-in pagination support
 total?: number
 page?: number
 perPage?: number
 meta?: any
 }>(),
 {
 loading: false,
 sortBy: '',
 sortOrder: 'desc',
 emptyTitle: 'No records found',
 emptyDescription: 'There are no records matching your active filters.',
 selectable: false,
 selectedIds: () => [],
 idKey: 'id',
 total: 0,
 page: 1,
 perPage: 15,
 }
)

const emit = defineEmits<{
 (e: 'sort', columnKey: string): void
 (e: 'update:selectedIds', ids: (string | number)[]): void
 (e: 'rowClick', item: any): void
 (e: 'page-change', page: number): void
}>()

const slots = useSlots()

const safeItems = computed<any[]>(() => (Array.isArray(props.items) ? props.items : []))

const hasActionsInColumns = computed(() => props.columns.some((c) => c.key === 'actions'))

const totalColspan = computed(() => {
 return props.columns.length + (props.selectable ? 1 : 0) + (slots.actions && !hasActionsInColumns.value ? 1 : 0)
})

const allSelected = computed(() => {
 if (!safeItems.value.length) return false
 return safeItems.value.every((item) => item && props.selectedIds.includes(item[props.idKey]))
})

function toggleSelectAll(event: Event) {
 const checked = (event.target as HTMLInputElement).checked
 if (checked) {
 const allIds = safeItems.value
 .filter((item) => item && typeof item === 'object')
 .map((item) => item[props.idKey])
 const combined = Array.from(new Set([...props.selectedIds, ...allIds]))
 emit('update:selectedIds', combined)
 } else {
 const currentItemIds = safeItems.value
 .filter((item) => item && typeof item === 'object')
 .map((item) => item[props.idKey])
 emit('update:selectedIds', props.selectedIds.filter((id) => !currentItemIds.includes(id)))
 }
}

function toggleSelectItem(item: any) {
 if (!item || typeof item !== 'object') return
 const id = item[props.idKey]
 if (props.selectedIds.includes(id)) {
 emit('update:selectedIds', props.selectedIds.filter((i) => i !== id))
 } else {
 emit('update:selectedIds', [...props.selectedIds, id])
 }
}

function handleSort(col: TableColumn) {
 if (col.sortable) {
 emit('sort', col.key)
 }
}
</script>

<template>
 <div class="w-full space-y-3">
 <!-- Table Container with responsive horizontal scroll (Notion-inspired hairline & flat level 0) -->
 <div class="overflow-x-auto rounded-lg border border-line bg-surface">
 <table class="w-full text-left text-xs border-collapse">
 <!-- Table Header -->
 <thead class="bg-canvas-soft border-b border-hairline text-xs font-medium text-ink-muted select-none" role="rowgroup">
 <tr>
 <!-- Select all checkbox -->
 <th v-if="selectable" scope="col" class="w-10 px-3.5 py-2.5 text-center">
 <input
 type="checkbox"
 :checked="allSelected"
 aria-label="Select all rows"
 @change="toggleSelectAll"
 class="rounded-[3px] border-hairline text-primary focus:ring-primary cursor-pointer"
 />
 </th>

 <!-- Columns -->
 <th
 v-for="col in columns"
 :key="col.key"
 scope="col"
 class="px-3.5 py-2.5 text-xs font-medium text-ink-muted transition"
 :class="[
 col.sortable ? 'cursor-pointer hover:text-ink' : '',
 col.align === 'center' ? 'text-center' : col.align === 'right' ? 'text-right' : 'text-left',
 col.headerClass || '',
 ]"
 @click="handleSort(col)"
 >
 <div
 class="inline-flex items-center gap-1.5"
 :class="{
 'justify-center': col.align === 'center',
 'justify-end': col.align === 'right',
 }"
 >
 <span>{{ col.label }}</span>
 <span v-if="col.sortable" class="text-ink-faint shrink-0">
 <ArrowUp v-if="sortBy === col.key && sortOrder === 'asc'" class="w-3.5 h-3.5 text-primary" />
 <ArrowDown v-else-if="sortBy === col.key && sortOrder === 'desc'" class="w-3.5 h-3.5 text-primary" />
 <ArrowUpDown v-else class="w-3.5 h-3.5 hover:text-ink-secondary" />
 </span>
 </div>
 </th>

 <!-- Trailing Actions header (only when not explicitly in columns) -->
 <th v-if="slots.actions && !hasActionsInColumns" scope="col" class="px-3.5 py-2.5 text-right w-24 text-xs font-medium text-ink-muted">
 Actions
 </th>
 </tr>
 </thead>

 <!-- Body -->
 <tbody class="divide-y divide-hairline-subtle" role="rowgroup">
 <!-- Loading State -->
 <tr v-if="loading">
 <td :colspan="totalColspan" class="p-8">
 <LoadingSkeleton type="table" :rows="5" />
 </td>
 </tr>

 <!-- Empty State -->
 <tr v-else-if="safeItems.length === 0">
 <td :colspan="totalColspan" class="p-8">
 <EmptyState :title="emptyTitle" :description="emptyDescription">
 <template #actions>
 <slot name="empty-actions"></slot>
 </template>
 </EmptyState>
 </td>
 </tr>

 <!-- Data Rows -->
 <tr
 v-else
 v-for="(item, index) in safeItems"
 :key="item && typeof item === 'object' && item[idKey] !== undefined ? item[idKey] : index"
 class="min-h-[44px] hover:bg-canvas-soft transition group cursor-pointer"
 :class="{ 'bg-primary-subtle/50': item && typeof item === 'object' && selectedIds.includes(item[idKey]) }"
 @click="emit('rowClick', item)"
 >
 <!-- Checkbox -->
 <td v-if="selectable" class="w-10 px-3.5 py-2.5 text-center" @click.stop>
 <input
 type="checkbox"
 :checked="selectedIds.includes(item[idKey])"
 aria-label="Select row"
 @change="toggleSelectItem(item)"
 class="rounded-[3px] border-hairline text-primary focus:ring-primary cursor-pointer"
 />
 </td>

 <!-- Cells: notion table typography -->
 <td
 v-for="col in columns"
 :key="col.key"
 class="px-3.5 py-2.5 text-xs leading-snug font-normal text-ink-secondary"
 :class="[
 col.align === 'center' ? 'text-center' : col.align === 'right' ? 'text-right' : 'text-left',
 col.class || '',
 ]"
 @click="col.key === 'actions' ? $event.stopPropagation() : undefined"
 >
 <slot v-if="slots[col.key]" :name="col.key" :item="item" :value="item[col.key]" />
 <slot v-else-if="slots['cell-' + col.key]" :name="'cell-' + col.key" :item="item" :value="item[col.key]" />
 <slot v-else-if="col.key === 'actions' && slots.actions" name="actions" :item="item" />
 <template v-else>
 {{ item[col.key] !== undefined && item[col.key] !== null ? item[col.key] : '—' }}
 </template>
 </td>

 <!-- Trailing Actions slot -->
 <td v-if="slots.actions && !hasActionsInColumns" class="px-3.5 py-2.5 text-right whitespace-nowrap" @click.stop>
 <slot name="actions" :item="item"></slot>
 </td>
 </tr>
 </tbody>
 </table>
 </div>

 <!-- Custom Pagination slot -->
 <div v-if="slots.pagination" class="pt-1">
 <slot name="pagination"></slot>
 </div>

 <!-- Built-in Pagination when total is provided and no custom slot -->
 <Pagination
 v-else-if="total > 0"
 :total="total"
 :current-page="page"
 :per-page="perPage"
 :meta="meta"
 @page-change="emit('page-change', $event)"
 />
 </div>
</template>


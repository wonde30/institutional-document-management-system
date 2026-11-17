<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Search, X, Filter, RotateCcw } from 'lucide-vue-next'

export interface FilterOption {
 label: string
 value: string | number
}

export interface FilterGroup {
 key: string
 label: string
 options: FilterOption[]
}

const props = withDefaults(
 defineProps<{
 modelValue?: string
 placeholder?: string
 searchPlaceholder?: string
 activeFiltersCount?: number
 showFilterButton?: boolean
 loading?: boolean
 filterGroups?: FilterGroup[]
 }>(),
 {
 modelValue: '',
 placeholder: 'Search records by keyword...',
 searchPlaceholder: '',
 activeFiltersCount: 0,
 showFilterButton: false,
 loading: false,
 filterGroups: () => [],
 }
)

const emit = defineEmits<{
 (e: 'update:modelValue', value: string): void
 (e: 'search', value: string): void
 (e: 'clear'): void
 (e: 'toggleFilters'): void
 (e: 'toggle-filters'): void
 (e: 'filter', filters: Record<string, string>): void
}>()

const localQuery = ref(props.modelValue)
const filterValues = ref<Record<string, string>>({})
let timer: ReturnType<typeof setTimeout> | undefined

const effectivePlaceholder = computed(() => props.searchPlaceholder || props.placeholder)

const totalActiveFilters = computed(() => {
 const customFiltersCount = Object.values(filterValues.value).filter(Boolean).length
 return props.activeFiltersCount + customFiltersCount
})

watch(() => props.modelValue, (newVal) => {
 localQuery.value = newVal
})

function onInput(event: Event) {
 const target = event.target as HTMLInputElement
 localQuery.value = target.value
 emit('update:modelValue', target.value)

 if (timer) clearTimeout(timer)
 timer = setTimeout(() => {
 emit('search', localQuery.value)
 }, 350)
}

function handleFilterChange(key: string, value: string) {
 filterValues.value[key] = value
 emit('filter', { ...filterValues.value })
}

function handleClear() {
 localQuery.value = ''
 filterValues.value = {}
 emit('update:modelValue', '')
 emit('search', '')
 emit('filter', {})
 emit('clear')
}

function handleToggleFilters() {
 emit('toggleFilters')
 emit('toggle-filters')
}
</script>

<template>
 <div class="space-y-3 w-full">
 <div class="flex flex-col lg:flex-row items-stretch lg:items-center gap-3">
 <!-- Search Input Container -->
 <div class="relative flex-1 min-w-0">
 <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-ink-faint">
 <Search class="w-4 h-4" />
 </div>

 <input
 type="text"
 :value="localQuery"
 :placeholder="effectivePlaceholder"
 aria-label="Search"
 @input="onInput"
 class="w-full pl-10 pr-10 h-9 px-3 py-1.5 rounded-lg border border-hairline bg-surface text-sm text-ink transition focus:border-primary focus:outline-hidden focus:ring-2 focus:ring-primary/20"
 />

 <button
 v-if="localQuery"
 type="button"
 class="absolute inset-y-0 right-0 pr-3 flex items-center text-ink-faint hover:text-ink transition cursor-pointer"
 aria-label="Clear search"
 @click="handleClear"
 >
 <X class="w-4 h-4" />
 </button>
 </div>

 <!-- Filter Groups (inline dropdowns) if provided -->
 <div v-if="filterGroups && filterGroups.length > 0" class="flex flex-wrap items-center gap-2">
 <div v-for="group in filterGroups" :key="group.key" class="relative">
 <select
 :value="filterValues[group.key] || ''"
 :aria-label="group.label"
 class="px-3 h-9 px-3 py-1.5 rounded-lg border border-hairline bg-surface text-xs font-semibold text-ink-secondary hover:bg-canvas-soft transition focus:ring-2 focus:ring-primary/20 focus:outline-hidden cursor-pointer"
 @change="handleFilterChange(group.key, ($event.target as HTMLSelectElement).value)"
 >
 <option
 v-for="opt in group.options"
 :key="String(opt.value)"
 :value="opt.value"
 >
 {{ opt.label }}
 </option>
 </select>
 </div>
 </div>

 <!-- Actions slot / buttons -->
 <div class="flex items-center gap-2 shrink-0 justify-end">
 <slot name="filters"></slot>

 <button
 v-if="showFilterButton"
 type="button"
 class="inline-flex items-center gap-2 px-3.5 h-9 px-3 py-1.5 rounded-lg border border-hairline bg-surface text-xs font-semibold text-ink-secondary hover:bg-canvas-soft transition cursor-pointer"
 @click="handleToggleFilters"
 >
 <Filter class="w-3.5 h-3.5" />
 <span>Filters</span>
 <span
 v-if="totalActiveFilters > 0"
 class="px-1.5 py-0.2 rounded-full bg-primary text-white text-xs font-bold"
 >
 {{ totalActiveFilters }}
 </span>
 </button>

 <button
 v-if="totalActiveFilters > 0 || localQuery"
 type="button"
 class="inline-flex items-center gap-1.5 px-3 py-2.5 text-xs font-medium text-ink-muted hover:text-rose-600 transition cursor-pointer"
 title="Reset all filters"
 @click="handleClear"
 >
 <RotateCcw class="w-3.5 h-3.5" />
 <span class="hidden md:inline">Reset</span>
 </button>

 <slot name="actions"></slot>
 </div>
 </div>

 <!-- Active filter chips row if provided -->
 <div v-if="$slots.chips" class="flex flex-wrap items-center gap-2 pt-1">
 <slot name="chips"></slot>
 </div>
 </div>
</template>

<script setup lang="ts">
const props = withDefaults(
 defineProps<{
 rows?: number
 type?: 'table' | 'card' | 'list'
 }>(),
 {
 rows: 4,
 type: 'table',
 }
)
</script>

<template>
 <div class="animate-pulse space-y-3 w-full" aria-busy="true" aria-label="Loading content">
 <!-- Table Skeleton -->
 <template v-if="type === 'table'">
 <div class="h-10 bg-surface-muted rounded-lg w-full mb-4"></div>
 <div
 v-for="i in rows"
 :key="i"
 class="h-14 bg-canvas-soft rounded-lg w-full flex items-center px-4 gap-4"
 >
 <div class="h-4 bg-surface-muted rounded-sm w-1/6"></div>
 <div class="h-4 bg-surface-muted rounded-sm w-2/6"></div>
 <div class="h-4 bg-surface-muted rounded-sm w-1/6"></div>
 <div class="h-4 bg-surface-muted rounded-sm w-2/6"></div>
 </div>
 </template>

 <!-- Card Skeleton -->
 <template v-else-if="type === 'card'">
 <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
 <div
 v-for="i in rows"
 :key="i"
 class="h-32 bg-canvas-soft rounded-xl p-5 space-y-3"
 >
 <div class="h-4 bg-surface-muted rounded-sm w-1/3"></div>
 <div class="h-8 bg-surface-muted rounded-sm w-1/2"></div>
 </div>
 </div>
 </template>

 <!-- List Skeleton -->
 <template v-else>
 <div
 v-for="i in rows"
 :key="i"
 class="h-16 bg-canvas-soft rounded-xl p-4 flex items-center justify-between"
 >
 <div class="space-y-2 w-1/2">
 <div class="h-3.5 bg-surface-muted rounded-sm w-3/4"></div>
 <div class="h-2.5 bg-surface-muted rounded-sm w-1/2"></div>
 </div>
 <div class="h-6 bg-surface-muted rounded-full w-20"></div>
 </div>
 </template>
 </div>
</template>

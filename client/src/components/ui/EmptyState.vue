<script setup lang="ts">
import { FolderSearch } from 'lucide-vue-next'

const props = withDefaults(
 defineProps<{
 title?: string
 description?: string
 }>(),
 {
 title: 'No records found',
 description: 'There are currently no items matching your criteria or registered in this category.',
 }
)
</script>

<template>
 <div class="py-12 px-4 text-center rounded-xl border border-dashed border-hairline bg-canvas-soft">
 <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-surface text-primary border border-hairline shadow-xs mb-3.5">
 <slot name="icon">
 <FolderSearch class="h-6 w-6" />
 </slot>
 </div>
 <h3 class="text-sm font-semibold text-ink">
 {{ title }}
 </h3>
 <p class="mt-1 text-xs text-ink-muted max-w-sm mx-auto leading-relaxed">
 {{ description }}
 </p>
 <div v-if="$slots.actions" class="mt-5 flex justify-center gap-3">
 <slot name="actions"></slot>
 </div>
 </div>
</template>

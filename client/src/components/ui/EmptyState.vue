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
  <div class="py-8 text-center">
    <slot name="icon">
      <FolderSearch class="w-6 h-6 text-ink-faint mx-auto mb-2" aria-hidden="true" />
    </slot>
    <p class="text-sm text-ink-muted">{{ title }}</p>
    <p v-if="description" class="text-xs text-ink-faint mt-1 max-w-md mx-auto">{{ description }}</p>
    <div v-if="$slots.actions" class="mt-4 flex justify-center gap-3">
      <slot name="actions"></slot>
    </div>
  </div>
</template>

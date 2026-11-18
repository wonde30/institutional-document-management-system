<script setup lang="ts">
defineSlots<{
  breadcrumb?: () => unknown;
  actions?: () => unknown;
}>();

defineProps<{
  title: string;
  subtitle?: string;
}>();
</script>

<template>
  <div class="mb-6 space-y-3">
    <div v-if="$slots.breadcrumb" class="text-sm text-ink-muted">
      <slot name="breadcrumb" />
    </div>
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="space-y-1">
        <h1 class="text-2xl font-semibold tracking-tight text-balance">
          {{ title }}
        </h1>
        <p v-if="subtitle" class="text-sm text-ink-muted">
          {{ subtitle }}
        </p>
      </div>
      <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
        <slot name="actions" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Component } from 'vue'

defineSlots<{
  breadcrumb?: () => unknown;
  title?: () => unknown;
  actions?: () => unknown;
}>();

defineProps<{
  title?: string;
  subtitle?: string;
  icon?: Component;
}>();
</script>

<template>
  <div class="mb-6 space-y-3">
    <div v-if="$slots.breadcrumb" class="text-sm text-ink-muted">
      <slot name="breadcrumb" />
    </div>
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="flex items-start gap-2.5">
        <div v-if="icon" class="p-2 bg-primary/10 rounded-lg text-primary shrink-0">
          <component :is="icon" class="w-5 h-5" />
        </div>
        <div class="space-y-1">
          <h1 class="text-2xl font-semibold tracking-tight text-balance">
            <slot v-if="$slots.title" name="title" />
            <template v-else>{{ title }}</template>
          </h1>
          <p v-if="subtitle" class="text-sm text-ink-muted">
            {{ subtitle }}
          </p>
        </div>
      </div>
      <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
        <slot name="actions" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import CountUp from '@/components/landing/CountUp.vue'
import type { LandingSummary } from '@/types'
import {
  FileText,
  Building2,
  Send,
  Award,
  AlertCircle,
  RotateCw,
} from 'lucide-vue-next'

const props = defineProps<{
  summary: LandingSummary | null
  isLoading?: boolean
  error?: string | null
}>()

const emit = defineEmits<{
  (e: 'retry'): void
}>()

const stats = computed(() => [
  {
    key: 'documents',
    label: 'Preserved Institutional Records',
    description: 'Centralized policies, curricula, and official decisions',
    value: props.summary?.published_documents ?? 0,
    icon: FileText,
    iconColor: 'text-primary',
    bgColor: 'bg-primary-subtle',
  },
  {
    key: 'offices',
    label: 'Campus Offices & Directorates',
    description: 'Active faculties, academic institutes, and branches',
    value: props.summary?.active_offices ?? 0,
    icon: Building2,
    iconColor: 'text-indigo-500',
    bgColor: 'bg-indigo-500/10',
  },
  {
    key: 'correspondence',
    label: 'Inter-Office Dispatches Routed',
    description: 'Audited movements with formal chains-of-custody',
    value: props.summary?.correspondence_routed ?? 0,
    icon: Send,
    iconColor: 'text-amber-500',
    bgColor: 'bg-amber-500/10',
  },
  {
    key: 'evidence',
    label: 'Accreditation Evidence Dossiers',
    description: 'Frozen versions mapped to national HERQA standards',
    value: props.summary?.accreditation_evidence_items ?? 0,
    icon: Award,
    iconColor: 'text-emerald-500',
    bgColor: 'bg-emerald-500/10',
  },
])
</script>

<template>
  <section class="py-8 bg-canvas-soft border-b border-hairline transition-colors" aria-label="Institutional Live Metrics">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Error Recovery Banner -->
      <div
        v-if="error && !summary"
        class="mb-4 p-4 rounded-lg bg-red-500/10 border border-red-500/20 text-red-600 dark:text-red-400 flex items-center justify-between gap-4"
        role="alert"
      >
        <div class="flex items-center gap-2 text-xs font-medium">
          <AlertCircle class="w-4 h-4 shrink-0" />
          <span>{{ error }}</span>
        </div>
        <button
          type="button"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold bg-red-600 text-white hover:bg-red-700 transition cursor-pointer"
          @click="emit('retry')"
        >
          <RotateCw class="w-3.5 h-3.5" />
          <span>Retry</span>
        </button>
      </div>

      <!-- Stats Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div
          v-for="item in stats"
          :key="item.key"
          class="p-4 sm:p-5 rounded-xl border border-hairline bg-surface hover:border-primary/40 transition-all shadow-xs hover:shadow-sm"
        >
          <div class="flex items-center justify-between mb-3">
            <div :class="['w-9 h-9 rounded-lg flex items-center justify-center shrink-0', item.bgColor]">
              <component :is="item.icon" :class="['w-4 h-4', item.iconColor]" />
            </div>
            <span class="text-[10px] font-mono text-ink-muted uppercase tracking-wider">
              Live Verified
            </span>
          </div>

          <!-- Number with Count-Up or Skeleton -->
          <div class="min-h-[36px] flex items-center">
            <template v-if="isLoading && !summary">
              <div class="h-8 w-24 bg-hairline rounded animate-pulse" />
            </template>
            <template v-else>
              <div class="text-2xl sm:text-3xl font-extrabold text-ink tracking-tight">
                <CountUp :value="item.value" />
              </div>
            </template>
          </div>

          <div class="mt-1">
            <h3 class="text-xs font-bold text-ink-secondary">
              {{ item.label }}
            </h3>
            <p class="text-[11px] text-ink-muted mt-0.5 leading-snug">
              {{ item.description }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

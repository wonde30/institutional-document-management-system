<script setup lang="ts">
import { ref, watch, onUnmounted } from 'vue'
import { useToast } from '@/composables/useToast'
import { CheckCircle2, AlertCircle, AlertTriangle, Info, X } from 'lucide-vue-next'

const { toasts, remove } = useToast()

// Track drain progress per toast id (100 = full, 0 = dismissed)
const progress = ref<Record<string, number>>({})
const intervals: Record<string, ReturnType<typeof setInterval>> = {}

function startProgress(id: string, duration: number) {
  if (!duration || duration <= 0) return
  progress.value[id] = 100
  const tick = 50
  const step = (tick / duration) * 100
  intervals[id] = setInterval(() => {
    const current = progress.value[id] ?? 0
    if (current <= 0) {
      clearInterval(intervals[id])
      delete intervals[id]
      return
    }
    progress.value[id] = Math.max(0, current - step)
  }, tick)
}

function stopProgress(id: string) {
  if (intervals[id]) {
    clearInterval(intervals[id])
    delete intervals[id]
  }
  delete progress.value[id]
}

watch(
  toasts,
  (newToasts, oldToasts) => {
    const newIds = new Set(newToasts.map((t) => t.id))
    // Start progress for newly added toasts
    for (const toast of newToasts) {
      if (intervals[toast.id] === undefined && progress.value[toast.id] === undefined) {
        startProgress(toast.id, toast.duration ?? 4000)
      }
    }
    // Clean up for removed toasts
    if (oldToasts) {
      for (const toast of oldToasts) {
        if (!newIds.has(toast.id)) {
          stopProgress(toast.id)
        }
      }
    }
  },
  { deep: true, immediate: true }
)

onUnmounted(() => {
  for (const id of Object.keys(intervals)) {
    clearInterval(intervals[id])
  }
})

function progressBarColor(type: string): string {
  switch (type) {
    case 'success': return 'bg-[var(--wu-success)]'
    case 'error':   return 'bg-[var(--wu-danger)]'
    case 'warning': return 'bg-[var(--wu-warning-text)]'
    default:        return 'bg-primary'
  }
}
</script>

<template>
  <div
    class="fixed bottom-5 right-5 z-50 flex flex-col gap-2.5 w-full max-w-sm pointer-events-none"
    role="region"
    aria-live="polite"
    aria-atomic="false"
    aria-label="Application notifications"
  >
    <transition-group
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="pointer-events-auto flex flex-col rounded-xl border shadow-lg backdrop-blur-xs bg-surface overflow-hidden"
        :class="{
          'border-[var(--wu-success)]/30': toast.type === 'success',
          'border-[var(--wu-danger)]/30': toast.type === 'error',
          'border-[var(--wu-warning-text)]/30': toast.type === 'warning',
          'border-primary/30': toast.type === 'info',
        }"
        role="alert"
        :aria-atomic="true"
      >
        <div class="flex items-start gap-3 p-4">
          <!-- Status icon -->
          <div class="shrink-0 mt-0.5" aria-hidden="true">
            <CheckCircle2 v-if="toast.type === 'success'" class="w-5 h-5 text-[var(--wu-success)]" />
            <AlertCircle  v-else-if="toast.type === 'error'"   class="w-5 h-5 text-[var(--wu-danger)]" />
            <AlertTriangle v-else-if="toast.type === 'warning'" class="w-5 h-5 text-[var(--wu-warning-text)]" />
            <Info          v-else                               class="w-5 h-5 text-primary" />
          </div>

          <!-- Message content -->
          <div class="flex-1 min-w-0">
            <h4 v-if="toast.title" class="text-xs font-bold uppercase tracking-wider mb-0.5 text-ink">
              {{ toast.title }}
            </h4>
            <p class="text-xs font-medium text-ink-secondary break-words leading-relaxed">
              {{ toast.message }}
            </p>
          </div>

          <!-- Dismiss button -->
          <button
            type="button"
            class="shrink-0 text-ink-faint hover:text-ink transition p-1 rounded-md cursor-pointer"
            aria-label="Dismiss notification"
            @click="remove(toast.id)"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <!-- Auto-dismiss progress bar -->
        <div
          v-if="toast.duration && toast.duration > 0"
          class="h-0.5 w-full bg-hairline"
          aria-hidden="true"
        >
          <div
            class="h-full"
            :class="progressBarColor(toast.type)"
            :style="{
              width: `${progress[toast.id] ?? 100}%`,
              transition: 'width 50ms linear',
            }"
          />
        </div>
      </div>
    </transition-group>
  </div>
</template>

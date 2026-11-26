<script setup lang="ts">
import type { LandingAnnouncement } from '@/types'
import { Megaphone, Calendar, Tag, Pin } from 'lucide-vue-next'

defineProps<{
  announcements: LandingAnnouncement[]
  isLoading?: boolean
}>()

function formatDate(iso: string) {
  if (!iso) return ''
  try {
    const d = new Date(iso)
    return d.toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    })
  } catch {
    return iso
  }
}
</script>

<template>
  <section
    v-if="announcements && announcements.length > 0"
    id="announcements"
    class="py-14 sm:py-20 bg-canvas-soft border-b border-hairline transition-colors"
    aria-labelledby="announcements-heading"
  >
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Section Header -->
      <div class="text-center max-w-2xl mx-auto mb-10 sm:mb-14">
        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-semibold bg-primary-subtle text-primary border border-primary/20 mb-3">
          <Megaphone class="w-3.5 h-3.5" />
          <span>Official Institutional Notices</span>
        </div>
        <h2 id="announcements-heading" class="text-2xl sm:text-3xl font-extrabold tracking-tight text-ink">
          Public Administrative Bulletins
        </h2>
        <p class="text-xs sm:text-sm text-ink-muted mt-3 leading-relaxed">
          Official directives, quality assurance audit cycles, and digital repository announcements.
        </p>
      </div>

      <!-- Announcements Grid -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div
          v-for="ann in announcements"
          :key="ann.id"
          class="p-5 sm:p-6 rounded-xl border border-hairline bg-surface hover:border-primary/40 transition-colors shadow-xs flex flex-col justify-between"
        >
          <div>
            <div class="flex items-center justify-between gap-2 mb-3">
              <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-primary-subtle text-primary border border-primary/20">
                {{ ann.category }}
              </span>
              <span v-if="ann.pinned" class="inline-flex items-center gap-1 text-[10px] font-medium text-amber-600 dark:text-amber-400">
                <Pin class="w-3 h-3" />
                Featured
              </span>
            </div>

            <h3 class="text-sm font-bold text-ink leading-snug">
              {{ ann.title }}
            </h3>

            <p class="text-xs text-ink-muted mt-2.5 leading-relaxed">
              {{ ann.summary }}
            </p>
          </div>

          <div class="mt-4 pt-3 border-t border-hairline flex items-center justify-between text-[11px] text-ink-muted">
            <span class="flex items-center gap-1.5">
              <Calendar class="w-3 h-3 text-primary" />
              {{ formatDate(ann.published_at) }}
            </span>
            <span class="font-medium text-primary">
              {{ ann.badge }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import type { Component } from 'vue'
import type { LandingModule } from '@/types'
import {
  FileText,
  Send,
  Layers,
  Archive,
  Award,
  FileCheck,
  BookOpen,
  ShieldCheck,
  Users,
  CheckCircle2,
  ArrowRight,
  Database,
} from 'lucide-vue-next'

const props = defineProps<{
  modules: LandingModule[]
  isLoading?: boolean
}>()

const iconMap: Record<string, Component> = {
  FileText,
  Send,
  Layers,
  Archive,
  Award,
  FileCheck,
  BookOpen,
  ShieldCheck,
  Users,
  Database,
}

function resolveIcon(iconName: string): Component {
  return iconMap[iconName] || FileText
}
</script>

<template>
  <section
    id="modules"
    class="py-14 sm:py-20 bg-surface border-b border-hairline transition-colors"
    aria-labelledby="modules-heading"
  >
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Section Header -->
      <div class="text-center max-w-3xl mx-auto mb-12 sm:mb-16">
        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-semibold bg-primary-subtle text-primary border border-primary/20 mb-3">
          <Layers class="w-3.5 h-3.5" />
          <span>Institutional Functional Architecture</span>
        </div>
        <h2 id="modules-heading" class="text-2xl sm:text-3xl font-extrabold tracking-tight text-ink">
          Modular Enterprise Document Platform
        </h2>
        <p class="text-xs sm:text-sm text-ink-muted mt-3 max-w-xl mx-auto leading-relaxed">
          Engineered to satisfy the functional requirements of Ethiopian public university governance, from everyday inter-office correspondence to national accreditation audits.
        </p>
      </div>

      <!-- Loading Skeletons -->
      <div v-if="isLoading && modules.length === 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <div
          v-for="i in 6"
          :key="i"
          class="p-5 rounded-xl border border-hairline bg-canvas-soft/50 animate-pulse space-y-3"
        >
          <div class="w-10 h-10 rounded-lg bg-hairline" />
          <div class="h-4 w-32 bg-hairline rounded" />
          <div class="h-12 w-full bg-hairline/60 rounded" />
          <div class="space-y-1.5 pt-2">
            <div class="h-3 w-3/4 bg-hairline/40 rounded" />
            <div class="h-3 w-1/2 bg-hairline/40 rounded" />
          </div>
        </div>
      </div>

      <!-- Modules Grid -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 sm:gap-6">
        <div
          v-for="mod in modules"
          :key="mod.key"
          class="p-5 sm:p-6 rounded-xl border border-hairline bg-surface hover:border-primary/50 transition-all duration-200 shadow-xs hover:shadow-md flex flex-col justify-between group"
        >
          <div>
            <!-- Card Header -->
            <div class="flex items-center gap-3 mb-3.5">
              <div
                class="w-10 h-10 rounded-lg bg-primary-subtle text-primary flex items-center justify-center shrink-0 group-hover:bg-primary group-hover:text-white transition-colors duration-200"
              >
                <component :is="resolveIcon(mod.icon)" class="w-5 h-5" />
              </div>
              <div>
                <h3 class="text-sm sm:text-base font-bold text-ink group-hover:text-primary transition-colors">
                  {{ mod.title }}
                </h3>
                <span class="text-[10px] font-mono font-semibold text-primary/80">
                  WU-DMS &bull; Module #0{{ mod.order }}
                </span>
              </div>
            </div>

            <p class="text-xs text-ink-muted leading-relaxed">
              {{ mod.description }}
            </p>

            <!-- Highlights Checklist -->
            <ul v-if="mod.highlights && mod.highlights.length > 0" class="mt-4 pt-3.5 border-t border-hairline space-y-1.5 text-xs text-ink-secondary">
              <li
                v-for="(hl, hIdx) in mod.highlights"
                :key="hIdx"
                class="flex items-center gap-2 text-[11px]"
              >
                <CheckCircle2 class="w-3.5 h-3.5 text-emerald-500 shrink-0" />
                <span>{{ hl }}</span>
              </li>
            </ul>
          </div>

          <!-- Bottom Meta / Action -->
          <div class="mt-5 pt-3 border-t border-hairline/60 flex items-center justify-between text-xs text-ink-muted">
            <span class="font-mono text-[10px] uppercase">Production Ready</span>
            <router-link
              to="/login"
              class="text-xs font-semibold text-primary hover:text-primary-hover flex items-center gap-1 group-hover:translate-x-0.5 transition-transform"
            >
              <span>Access</span>
              <ArrowRight class="w-3.5 h-3.5" />
            </router-link>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

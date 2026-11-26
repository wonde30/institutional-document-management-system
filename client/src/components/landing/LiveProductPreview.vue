<script setup lang="ts">
import { computed } from 'vue'
import type { LandingSummary, LandingAccreditation } from '@/types'
import {
  FileText,
  Building2,
  Send,
  Award,
  ShieldCheck,
  CheckCircle2,
  Clock,
  ArrowUpRight,
  Activity,
  Layers,
} from 'lucide-vue-next'

const props = defineProps<{
  summary: LandingSummary | null
  accreditation: LandingAccreditation | null
  isLoading?: boolean
}>()

const publishedCount = computed(() => props.summary?.published_documents ?? 0)
const officesCount = computed(() => props.summary?.active_offices ?? 0)
const routedCount = computed(() => props.summary?.correspondence_routed ?? 0)
const evidenceCount = computed(() => props.summary?.accreditation_evidence_items ?? 0)

const coveragePct = computed(() => {
  return props.accreditation?.summary?.coverage_percentage ?? 80.0
})
</script>

<template>
  <div class="relative w-full max-w-lg lg:max-w-none mx-auto select-none">
    <!-- Ambient Glow Backdrop -->
    <div
      class="absolute -inset-1.5 bg-gradient-to-tr from-primary/20 via-primary-hover/10 to-indigo-500/10 rounded-2xl blur-xl opacity-75 dark:opacity-50 pointer-events-none transition-all duration-500"
      aria-hidden="true"
    />

    <!-- Mock Application Card Shell -->
    <div
      class="relative rounded-xl border border-hairline bg-surface/90 dark:bg-[#1a1a1a]/95 backdrop-blur-md shadow-2xl overflow-hidden transition-all duration-300 hover:shadow-primary/5 hover:border-primary/40 group"
    >
      <!-- Institutional Top Bar -->
      <div class="px-4 py-3 border-b border-hairline bg-surface-muted/50 dark:bg-[#141414] flex items-center justify-between">
        <div class="flex items-center gap-2">
          <div class="flex items-center gap-1.5" aria-hidden="true">
            <span class="w-2.5 h-2.5 rounded-full bg-red-400/80 inline-block" />
            <span class="w-2.5 h-2.5 rounded-full bg-amber-400/80 inline-block" />
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400/80 inline-block" />
          </div>
          <span class="text-xs font-mono font-medium text-ink-muted ml-1 truncate">
            wu-dms.wollo.edu.et &bull; v2.6.4
          </span>
        </div>

        <!-- Live Node Indicator -->
        <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-[11px] font-medium text-emerald-600 dark:text-emerald-400">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
          <span>Live Node</span>
        </div>
      </div>

      <!-- Main Preview Content Area -->
      <div class="p-4 sm:p-5 space-y-4">
        <!-- Header Banner within Preview -->
        <div class="flex items-start justify-between gap-3 pb-3 border-b border-hairline">
          <div>
            <div class="flex items-center gap-1.5">
              <span class="text-xs font-bold text-ink">Administrative Directorate Portal</span>
              <span class="px-1.5 py-0.2 rounded text-[10px] font-semibold bg-primary-subtle text-primary">
                Multi-Scope
              </span>
            </div>
            <p class="text-[11px] text-ink-muted mt-0.5">
              Kombolcha Institute of Technology &bull; Central Registry
            </p>
          </div>
          <div class="p-1.5 rounded-md bg-canvas-soft border border-hairline text-ink-muted">
            <ShieldCheck class="w-4 h-4 text-primary" />
          </div>
        </div>

        <!-- Metric Cards 2x2 Grid -->
        <div class="grid grid-cols-2 gap-2.5">
          <!-- Item 1: Documents -->
          <div class="p-2.5 rounded-lg bg-canvas-soft border border-hairline/80 transition-colors hover:border-primary/30">
            <div class="flex items-center justify-between text-ink-muted mb-1">
              <span class="text-[11px] font-medium">Preserved Records</span>
              <FileText class="w-3.5 h-3.5 text-primary" />
            </div>
            <div class="text-lg font-bold text-ink tabular-nums">
              <template v-if="isLoading">
                <div class="h-5 w-12 bg-hairline rounded animate-pulse" />
              </template>
              <template v-else>
                {{ publishedCount }}
              </template>
            </div>
            <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-medium flex items-center gap-0.5 mt-0.5">
              <CheckCircle2 class="w-3 h-3" />
              SHA-256 Verified
            </span>
          </div>

          <!-- Item 2: Active Offices -->
          <div class="p-2.5 rounded-lg bg-canvas-soft border border-hairline/80 transition-colors hover:border-primary/30">
            <div class="flex items-center justify-between text-ink-muted mb-1">
              <span class="text-[11px] font-medium">Campus Directorates</span>
              <Building2 class="w-3.5 h-3.5 text-indigo-500" />
            </div>
            <div class="text-lg font-bold text-ink tabular-nums">
              <template v-if="isLoading">
                <div class="h-5 w-10 bg-hairline rounded animate-pulse" />
              </template>
              <template v-else>
                {{ officesCount }}
              </template>
            </div>
            <span class="text-[10px] text-ink-muted font-medium flex items-center gap-0.5 mt-0.5">
              <Layers class="w-3 h-3 text-primary" />
              Unit Scoped
            </span>
          </div>

          <!-- Item 3: Correspondence -->
          <div class="p-2.5 rounded-lg bg-canvas-soft border border-hairline/80 transition-colors hover:border-primary/30">
            <div class="flex items-center justify-between text-ink-muted mb-1">
              <span class="text-[11px] font-medium">Tracked Dispatches</span>
              <Send class="w-3.5 h-3.5 text-amber-500" />
            </div>
            <div class="text-lg font-bold text-ink tabular-nums">
              <template v-if="isLoading">
                <div class="h-5 w-10 bg-hairline rounded animate-pulse" />
              </template>
              <template v-else>
                {{ routedCount }}
              </template>
            </div>
            <span class="text-[10px] text-amber-600 dark:text-amber-400 font-medium flex items-center gap-0.5 mt-0.5">
              <Clock class="w-3 h-3" />
              Audited SLA
            </span>
          </div>

          <!-- Item 4: Accreditation Evidence -->
          <div class="p-2.5 rounded-lg bg-canvas-soft border border-hairline/80 transition-colors hover:border-primary/30">
            <div class="flex items-center justify-between text-ink-muted mb-1">
              <span class="text-[11px] font-medium">HERQA Standards</span>
              <Award class="w-3.5 h-3.5 text-emerald-500" />
            </div>
            <div class="text-lg font-bold text-ink tabular-nums">
              <template v-if="isLoading">
                <div class="h-5 w-14 bg-hairline rounded animate-pulse" />
              </template>
              <template v-else>
                {{ evidenceCount }} Dossiers
              </template>
            </div>
            <span class="text-[10px] text-primary font-medium flex items-center gap-0.5 mt-0.5">
              {{ coveragePct }}% Coverage
            </span>
          </div>
        </div>

        <!-- Simulated Recent Document Dispatch Stream -->
        <div class="pt-2 border-t border-hairline">
          <div class="flex items-center justify-between mb-2">
            <span class="text-[11px] font-bold text-ink-secondary flex items-center gap-1.5">
              <Activity class="w-3 h-3 text-primary" />
              Recent Institutional Dispatch Chain
            </span>
            <span class="text-[10px] text-ink-muted font-mono">Live Telemetry</span>
          </div>

          <div class="space-y-1.5">
            <!-- Row 1 -->
            <div class="p-2 rounded-md bg-canvas-soft/80 border border-hairline flex items-center justify-between text-[11px]">
              <div class="flex items-center gap-2 min-w-0">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0" />
                <span class="font-mono text-xs text-primary font-semibold shrink-0">WU/DOC/2026/0001</span>
                <span class="text-ink truncate max-w-[140px] sm:max-w-[180px]">Five-Year Strategic Plan</span>
              </div>
              <span class="text-[10px] font-medium text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-1.5 py-0.5 rounded shrink-0">
                Archived
              </span>
            </div>

            <!-- Row 2 -->
            <div class="p-2 rounded-md bg-canvas-soft/80 border border-hairline flex items-center justify-between text-[11px]">
              <div class="flex items-center gap-2 min-w-0">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0" />
                <span class="font-mono text-xs text-primary font-semibold shrink-0">HERQA-01</span>
                <span class="text-ink truncate max-w-[140px] sm:max-w-[180px]">Governance &amp; Autonomy Review</span>
              </div>
              <span class="text-[10px] font-medium text-amber-600 dark:text-amber-400 bg-amber-500/10 px-1.5 py-0.5 rounded shrink-0">
                Review Staged
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Institutional Security Footer Strip -->
      <div class="px-4 py-2.5 bg-surface-muted/40 dark:bg-[#121212] border-t border-hairline flex items-center justify-between text-[11px] text-ink-muted">
        <span class="flex items-center gap-1">
          <ShieldCheck class="w-3.5 h-3.5 text-primary" />
          Strict 3NF &bull; Multi-Unit RBAC
        </span>
        <span class="text-primary font-medium hover:underline inline-flex items-center gap-0.5">
          <span>Enterprise Preview</span>
          <ArrowUpRight class="w-3 h-3" />
        </span>
      </div>
    </div>
  </div>
</template>

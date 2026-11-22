<script setup lang="ts">
import { computed } from 'vue'
import type { LandingAccreditation } from '@/types'
import {
  Award,
  CheckCircle2,
  Clock,
  AlertCircle,
  FileText,
  ShieldCheck,
  ExternalLink,
} from 'lucide-vue-next'

const props = defineProps<{
  accreditation: LandingAccreditation | null
  isLoading?: boolean
}>()

const standards = computed(() => props.accreditation?.standards ?? [])
const cycle = computed(() => props.accreditation?.cycle)
const summary = computed(() => props.accreditation?.summary)

const coveragePct = computed(() => summary.value?.coverage_percentage ?? 0)
const totalEvidence = computed(() => summary.value?.total_evidence_items ?? 0)
const verifiedCount = computed(() => summary.value?.verified_standards ?? 0)
</script>

<template>
  <section
    id="accreditation"
    class="py-14 sm:py-20 bg-surface border-b border-hairline transition-colors"
    aria-labelledby="accreditation-heading"
  >
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">
        <!-- Left Column: Context & Coverage Overview -->
        <div class="lg:col-span-5 space-y-5">
          <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
            <Award class="w-3.5 h-3.5" />
            <span>Academic Quality Assurance</span>
          </div>

          <h2 id="accreditation-heading" class="text-2xl sm:text-3xl font-extrabold tracking-tight text-ink leading-tight">
            National HERQA Accreditation Readiness
          </h2>

          <p class="text-xs sm:text-sm text-ink-muted leading-relaxed">
            Academic program accreditation requires verifiable, tamper-evident documentation across curricula, faculty profiles, research outputs, and Senate decisions. WU-DMS maps frozen document revisions directly against the 10 national standards established by the Higher Education Relevance and Quality Agency (HERQA).
          </p>

          <!-- Coverage Progress Bar Card -->
          <div class="p-4 rounded-xl border border-hairline bg-canvas-soft space-y-3">
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-ink">Institutional Coverage Progress</span>
              <span class="font-mono font-bold text-primary tabular-nums">{{ coveragePct }}%</span>
            </div>
            <!-- Progress Bar -->
            <div class="w-full bg-hairline rounded-full h-2 overflow-hidden" role="progressbar" :aria-valuenow="coveragePct" aria-valuemin="0" aria-valuemax="100">
              <div
                class="bg-primary h-2 rounded-full transition-all duration-700 ease-out"
                :style="{ width: `${Math.min(coveragePct, 100)}%` }"
              />
            </div>
            <div class="flex items-center justify-between text-[11px] text-ink-muted pt-1">
              <span>{{ verifiedCount }} of 10 Standards Verified</span>
              <span>{{ totalEvidence }} Frozen Dossiers</span>
            </div>
          </div>

          <!-- Feature Bullets -->
          <div class="space-y-2.5 text-xs text-ink-secondary pt-2">
            <div class="flex items-start gap-2.5">
              <CheckCircle2 class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" />
              <span><strong>Immutable Version Freeze:</strong> Evidence dossiers cannot be modified after departmental review sign-off.</span>
            </div>
            <div class="flex items-start gap-2.5">
              <CheckCircle2 class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" />
              <span><strong>Audit Cycle Compilations:</strong> 1-click exportable dossiers structured for external HERQA assessment panels.</span>
            </div>
            <div class="flex items-start gap-2.5">
              <CheckCircle2 class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" />
              <span><strong>Formal Peer-Review Trail:</strong> Multi-stakeholder review workflows with accepted, needs-revision, and reject statuses.</span>
            </div>
          </div>
        </div>

        <!-- Right Column: Standards Live Tracker Panel -->
        <div class="lg:col-span-7 bg-canvas-soft rounded-xl border border-hairline p-5 sm:p-6 space-y-4 shadow-xs">
          <!-- Panel Header -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-hairline gap-2">
            <div>
              <h3 class="text-xs font-bold text-ink">
                HERQA Standard Mappings (Active Cycle)
              </h3>
              <p class="text-[11px] text-ink-muted">
                {{ cycle?.name || 'HERQA Institutional Quality Audit Cycle (2026-2027)' }}
              </p>
            </div>
            <span class="text-xs font-mono text-primary bg-primary-subtle px-2.5 py-1 rounded-md font-semibold self-start sm:self-auto">
              2026 Institutional Audit
            </span>
          </div>

          <!-- Loading Skeleton -->
          <div v-if="isLoading && standards.length === 0" class="space-y-2">
            <div v-for="i in 5" :key="i" class="p-3 rounded-lg bg-surface border border-hairline animate-pulse flex justify-between">
              <div class="h-4 w-48 bg-hairline rounded" />
              <div class="h-4 w-16 bg-hairline rounded" />
            </div>
          </div>

          <!-- Standards List -->
          <div v-else class="space-y-2 max-h-[460px] overflow-y-auto scrollbar-thin pr-1">
            <div
              v-for="std in standards"
              :key="std.code"
              class="p-3 rounded-lg bg-surface border border-hairline hover:border-primary/40 transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-2"
            >
              <div class="min-w-0 pr-2">
                <div class="flex items-center gap-2">
                  <span class="font-mono font-bold text-xs text-primary shrink-0">{{ std.code }}</span>
                  <span class="text-xs font-semibold text-ink truncate">{{ std.title }}</span>
                </div>
                <div class="flex items-center gap-3 text-[11px] text-ink-muted mt-1 font-mono">
                  <span>{{ std.evidence_count }} evidence document{{ std.evidence_count === 1 ? '' : 's' }} staged</span>
                </div>
              </div>

              <!-- Status Badge -->
              <span
                v-if="std.status === 'verified'"
                class="px-2.5 py-1 rounded-md text-[11px] font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 shrink-0 self-start sm:self-auto flex items-center gap-1"
              >
                <CheckCircle2 class="w-3 h-3" />
                {{ std.status_label }}
              </span>
              <span
                v-else-if="std.status === 'review_pending' || std.status === 'in_review'"
                class="px-2.5 py-1 rounded-md text-[11px] font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 shrink-0 self-start sm:self-auto flex items-center gap-1"
              >
                <Clock class="w-3 h-3" />
                {{ std.status_label }}
              </span>
              <span
                v-else
                class="px-2.5 py-1 rounded-md text-[11px] font-semibold bg-hairline/60 text-ink-muted shrink-0 self-start sm:self-auto"
              >
                {{ std.status_label }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

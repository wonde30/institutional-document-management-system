<script setup lang="ts">
import {
  FilePlus2,
  Send,
  Archive,
  Award,
  ArrowRight,
  ShieldCheck,
} from 'lucide-vue-next'

const steps = [
  {
    number: '01',
    title: 'Register & Classify',
    subtitle: 'Deterministic Sequencing',
    description:
      'Incoming letters, internal directives, and curricula are ingested with automated reference sequences (WU/DOC/YEAR/SEQ) and cryptographic SHA-256 integrity hashes.',
    icon: FilePlus2,
    badge: 'Metadata Intake',
  },
  {
    number: '02',
    title: 'Route & Acknowledge',
    subtitle: 'Audited Chain of Custody',
    description:
      'Documents are dispatched across colleges, faculties, and directorates with formal routing instructions, SLA response deadlines, and digital receipt acknowledgments.',
    icon: Send,
    badge: 'Movement Ledger',
  },
  {
    number: '03',
    title: 'Preserve & Archive',
    subtitle: 'Statutory Retention',
    description:
      'Inactive records automatically transition to institutional digital archives according to national retention policies, complete with physical storage bay/shelf indexing.',
    icon: Archive,
    badge: 'Long-Term Preservation',
  },
  {
    number: '04',
    title: 'Verify & Accredit',
    subtitle: 'HERQA Quality Assurance',
    description:
      'Immutable document versions are mapped against national HERQA accreditation standards, enabling 1-click audit dossiers for external evaluation teams.',
    icon: Award,
    badge: 'Accreditation Dossiers',
  },
]
</script>

<template>
  <section
    id="how-it-works"
    class="py-14 sm:py-20 bg-canvas-soft border-b border-hairline transition-colors"
    aria-labelledby="how-it-works-heading"
  >
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Section Header -->
      <div class="text-center max-w-2xl mx-auto mb-12 sm:mb-16">
        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-semibold bg-primary-subtle text-primary border border-primary/20 mb-3">
          <ShieldCheck class="w-3.5 h-3.5" />
          <span>Operational Lifecycle</span>
        </div>
        <h2 id="how-it-works-heading" class="text-2xl sm:text-3xl font-extrabold tracking-tight text-ink">
          How Institutional Records Flow
        </h2>
        <p class="text-xs sm:text-sm text-ink-muted mt-3 leading-relaxed">
          From paper intake to long-term digital preservation and national quality assurance compliance.
        </p>
      </div>

      <!-- 4-Step Process Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 relative">
        <div
          v-for="(step, idx) in steps"
          :key="step.number"
          class="relative p-5 sm:p-6 rounded-xl border border-hairline bg-surface flex flex-col justify-between hover:border-primary/40 transition-all shadow-xs hover:shadow-sm group"
        >
          <!-- Step Indicator -->
          <div>
            <div class="flex items-center justify-between mb-4">
              <span class="text-2xl font-black font-mono text-primary/30 group-hover:text-primary transition-colors">
                {{ step.number }}
              </span>
              <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-primary-subtle text-primary">
                {{ step.badge }}
              </span>
            </div>

            <!-- Icon -->
            <div class="w-10 h-10 rounded-lg bg-canvas-soft text-primary flex items-center justify-center mb-3 border border-hairline group-hover:bg-primary group-hover:text-white transition-colors">
              <component :is="step.icon" class="w-5 h-5" />
            </div>

            <h3 class="text-sm font-bold text-ink">
              {{ step.title }}
            </h3>
            <p class="text-[11px] font-medium text-primary mt-0.5">
              {{ step.subtitle }}
            </p>

            <p class="text-xs text-ink-muted mt-2.5 leading-relaxed">
              {{ step.description }}
            </p>
          </div>

          <!-- Bottom Step Link -->
          <div class="mt-5 pt-3 border-t border-hairline flex items-center justify-between text-[11px] text-ink-muted">
            <span class="font-mono">Step {{ idx + 1 }} of 4</span>
            <span v-if="idx < 3" class="hidden lg:block text-ink-muted/60" aria-hidden="true">
              <ArrowRight class="w-4 h-4" />
            </span>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

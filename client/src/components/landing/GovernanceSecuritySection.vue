<script setup lang="ts">
import {
  ShieldCheck,
  Lock,
  Database,
  Archive,
  Layers,
  FileCheck,
} from 'lucide-vue-next'

const features = [
  {
    title: 'Scoped Dynamic RBAC',
    tag: 'Zero-Trust Architecture',
    description:
      'Privileges are partitioned into Global, Unit, or Subtree administrative boundaries. Department heads and deans hold authority strictly bounded to their faculties without cross-departmental exposure.',
    icon: ShieldCheck,
    points: ['Global, Unit & Subtree scoping', 'Contextual active unit switching', 'Inherited hierarchy permissions'],
  },
  {
    title: 'Stateful SPA Cookie Security',
    tag: 'Sanctum Protection',
    description:
      'Encrypted, HTTP-only, SameSite session cookies authenticated by Laravel Sanctum. No bearer tokens or authentication secrets are ever stored in browser localStorage or exposed to XSS vectors.',
    icon: Lock,
    points: ['HttpOnly SameSite cookies', 'Automated CSRF token exchange', 'Zero client token leakage'],
  },
  {
    title: 'Tamper-Proof Audit Trail',
    tag: 'Immutable Ledger',
    description:
      'Every document registration, revision, view, permission grant, dispatch event, and status transition is recorded in an append-only ledger with actor identity, timestamp, and network telemetry.',
    icon: Database,
    points: ['Append-only MySQL integrity', 'Actor, IP & timestamp telemetry', 'Non-repudiation audit trails'],
  },
  {
    title: 'Statutory Retention Schedules',
    tag: 'Proclamation No. 1072/2018',
    description:
      'Compliant with Ethiopian National Archive standards. Automated monitoring of retention periods, legal hold freezes, and authorized disposition workflows protect institutional memory.',
    icon: Archive,
    points: ['Legal hold freezes', 'Physical bay & shelf tracking', 'Authorized disposition logs'],
  },
]
</script>

<template>
  <section
    id="governance"
    class="py-14 sm:py-20 bg-canvas-soft border-b border-hairline transition-colors"
    aria-labelledby="governance-heading"
  >
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Section Header -->
      <div class="text-center max-w-3xl mx-auto mb-12 sm:mb-16">
        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-semibold bg-primary-subtle text-primary border border-primary/20 mb-3">
          <ShieldCheck class="w-3.5 h-3.5" />
          <span>Security &amp; Institutional Governance</span>
        </div>
        <h2 id="governance-heading" class="text-2xl sm:text-3xl font-extrabold tracking-tight text-ink">
          Multi-Scope Information Assurance
        </h2>
        <p class="text-xs sm:text-sm text-ink-muted mt-3 max-w-xl mx-auto leading-relaxed">
          Separation of administrative privileges from ordinary document handling ensures strict confidentiality and compliance across campuses.
        </p>
      </div>

      <!-- Feature Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div
          v-for="item in features"
          :key="item.title"
          class="p-5 sm:p-6 rounded-xl border border-hairline bg-surface hover:border-primary/40 transition-all shadow-xs hover:shadow-sm flex flex-col justify-between"
        >
          <div>
            <div class="w-10 h-10 rounded-lg bg-primary-subtle text-primary flex items-center justify-center mb-4">
              <component :is="item.icon" class="w-5 h-5" />
            </div>

            <div class="flex items-center justify-between mb-1">
              <h3 class="text-sm font-bold text-ink">
                {{ item.title }}
              </h3>
            </div>
            <span class="text-[10px] font-mono text-primary font-semibold block mb-2">
              {{ item.tag }}
            </span>

            <p class="text-xs text-ink-muted leading-relaxed">
              {{ item.description }}
            </p>
          </div>

          <div class="mt-4 pt-3 border-t border-hairline space-y-1 text-[11px] text-ink-secondary">
            <div v-for="(p, pIdx) in item.points" :key="pIdx" class="flex items-center gap-1.5">
              <span class="w-1 h-1 rounded-full bg-primary" />
              <span>{{ p }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

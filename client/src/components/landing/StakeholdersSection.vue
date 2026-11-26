<script setup lang="ts">
import { Building2, Users, GraduationCap, Scale } from 'lucide-vue-next'

const roles = [
  {
    title: 'University Management',
    roleList: 'President, Vice Presidents, Executive Directors',
    description: 'Executive oversight, statutory approvals, institutional dispatches',
  },
  {
    title: 'Academic Leadership',
    roleList: 'Colleges, Institutes (KIoT), Deans, Dept Heads',
    description: 'Curricula revisions, faculty teaching assignments, departmental tasks',
  },
  {
    title: 'Registrar & Records',
    roleList: 'Student Administration & Central Intake',
    description: 'Incoming mail logging, external ministry dispatches, transcript files',
  },
  {
    title: 'Administrative Directorates',
    roleList: 'Human Resources, Finance & Procurement',
    description: 'Staff employment files, payroll memoranda, procurement dossiers',
  },
  {
    title: 'Research & Community',
    roleList: 'Directorate of Research & Publications',
    description: 'Competitive grant reviews, IRB approvals, published monograph archive',
  },
  {
    title: 'Records Officers & Archivists',
    roleList: 'Preservation, Retention, Physical Storage',
    description: 'Digital archiving, physical shelf indexing, retention reviews',
  },
  {
    title: 'Quality Assurance Teams',
    roleList: 'Institutional QA Directorate & Auditors',
    description: 'HERQA national standards mapping, cyclical peer reviews',
  },
  {
    title: 'Information Technology',
    roleList: 'System Administrators & Backup Operators',
    description: 'Role permission mapping, backup drills, audit telemetry verification',
  },
]
</script>

<template>
  <section
    id="stakeholders"
    class="py-14 sm:py-20 bg-surface border-b border-hairline transition-colors"
    aria-labelledby="stakeholders-heading"
  >
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center max-w-2xl mx-auto mb-12 sm:mb-16">
        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-semibold bg-primary-subtle text-primary border border-primary/20 mb-3">
          <Users class="w-3.5 h-3.5" />
          <span>Campus Stakeholders</span>
        </div>
        <h2 id="stakeholders-heading" class="text-2xl sm:text-3xl font-extrabold tracking-tight text-ink">
          Supporting Every Institutional Role
        </h2>
        <p class="text-xs sm:text-sm text-ink-muted mt-3 leading-relaxed">
          Tailored interfaces, deterministic reference workflows, and strict authorization boundaries for all campus actors.
        </p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
        <div
          v-for="role in roles"
          :key="role.title"
          class="p-4 sm:p-5 rounded-xl bg-canvas-soft border border-hairline hover:border-primary/40 transition-colors shadow-xs"
        >
          <h3 class="text-xs font-bold text-ink">
            {{ role.title }}
          </h3>
          <p class="text-[11px] font-medium text-primary mt-0.5">
            {{ role.roleList }}
          </p>
          <p class="text-xs text-ink-muted mt-2 leading-relaxed">
            {{ role.description }}
          </p>
        </div>
      </div>
    </div>
  </section>
</template>

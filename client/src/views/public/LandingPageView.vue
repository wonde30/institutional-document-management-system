<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import PublicNavbar from '@/components/public/PublicNavbar.vue'
import PublicFooter from '@/components/public/PublicFooter.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import {
  FileText,
  Mail,
  Send,
  Archive,
  Award,
  ShieldCheck,
  Building2,
  CheckCircle2,
  ArrowRight,
  Database,
  Lock,
  Layers,
  FileCheck
} from 'lucide-vue-next'

const router = useRouter()
const authStore = useAuthStore()

function handleCtaClick() {
  if (authStore.isAuthenticated) {
    router.push('/dashboard')
  } else {
    router.push('/login')
  }
}
</script>

<template>
  <div class="min-h-screen flex flex-col bg-canvas-soft text-ink-secondary transition-colors selection:bg-primary selection:text-white">
    <PublicNavbar />

    <main class="flex-1">
      <!-- Section 1: Institutional Hero -->
      <section id="overview" class="relative py-10 sm:py-16 lg:py-20 overflow-hidden border-b border-hairline bg-surface">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="max-w-3xl">
            <!-- Institutional Affiliation Tag -->
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-md text-xs font-semibold bg-primary-subtle text-primary border border-primary/20 mb-4 sm:mb-5">
              <Building2 class="w-3.5 h-3.5 shrink-0" />
              <span>Wollo University &bull; Kombolcha Institute of Technology (KIoT)</span>
            </div>

            <h1 class="text-2xl sm:text-4xl lg:text-[40px] font-bold tracking-tight text-ink leading-tight sm:leading-tight">
              Web-Based Integrated Document Repository, Digital Archive &amp; Inter-Office Communication
            </h1>

            <p class="mt-4 text-xs sm:text-sm text-ink-muted leading-relaxed max-w-2xl">
              Designed specifically for Wollo University's academic directorates, colleges, and administrative offices. Centralizing institutional document registration, audited correspondence routing, long-term digital preservation, and verifiable accreditation evidence within a single, secure environment.
            </p>

            <!-- Institutional CTA Row -->
            <div class="mt-6 sm:mt-7 flex flex-wrap items-center gap-3">
              <BaseButton
                variant="primary"
                size="md"
                class="text-xs sm:text-sm font-semibold py-2 px-4 sm:px-5 rounded-lg"
                @click="handleCtaClick"
              >
                <span>{{ authStore.isAuthenticated ? 'Enter Workspace' : 'Sign In to Workspace' }}</span>
                <ArrowRight class="w-4 h-4 ml-1.5" />
              </BaseButton>

              <a
                href="#modules"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-hairline text-xs sm:text-sm font-semibold text-ink-secondary hover:bg-surface-hover hover:text-ink transition-colors"
              >
                <span>Explore Core Modules</span>
              </a>
            </div>

            <!-- Technical Scope Fact Sheet (Tested Proposal Metrics) -->
            <div class="mt-8 pt-6 border-t border-hairline grid grid-cols-2 sm:grid-cols-4 gap-3">
              <div class="p-3 rounded-lg border border-hairline bg-surface">
                <span class="block text-2xl font-bold text-primary tabular-nums">118</span>
                <span class="block text-xs font-semibold text-ink-muted   mt-0.5">Normalized Tables</span>
              </div>
              <div class="p-3 rounded-lg border border-hairline bg-surface">
                <span class="block text-2xl font-bold text-primary">3NF</span>
                <span class="block text-xs font-semibold text-ink-muted   mt-0.5">Relational Baseline</span>
              </div>
              <div class="p-3 rounded-lg border border-hairline bg-surface">
                <span class="block text-2xl font-bold text-primary tabular-nums">100%</span>
                <span class="block text-xs font-semibold text-ink-muted   mt-0.5">Audited Lifecycle</span>
              </div>
              <div class="p-3 rounded-lg border border-hairline bg-surface">
                <span class="block text-2xl font-bold text-primary">Multi-Unit</span>
                <span class="block text-xs font-semibold text-ink-muted   mt-0.5">Scoped RBAC</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Section 2: Statement of the Problem & Institutional Solution -->
      <section id="context" class="py-12 sm:py-16 bg-canvas-soft">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="text-center max-w-2xl mx-auto mb-10 sm:mb-12">
            <h2 class="text-xs font-bold   text-primary">
              Institutional Context
            </h2>
            <h3 class="text-xl sm:text-2xl font-bold text-ink mt-1">
              Transforming Administrative Workflows
            </h3>
            <p class="text-xs sm:text-sm text-ink-muted mt-2 leading-relaxed">
              As an applied university, Wollo University requires dependable digital systems to eliminate paper-based bottlenecks, fragmented office channels, and delayed decision-making.
            </p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-5 sm:gap-6">
            <!-- Card 1 -->
            <div class="bg-surface p-5 rounded-lg border border-hairline space-y-2.5 hover:border-primary/40 transition">
              <div class="w-9 h-9 rounded-md bg-primary-subtle text-primary flex items-center justify-center shrink-0">
                <FileText class="w-4 h-4" />
              </div>
              <h4 class="text-sm font-bold text-ink">
                Unified Document Repository
              </h4>
              <p class="text-xs text-ink-muted leading-relaxed">
                Centralized classification, structured metadata, and SHA-256 cryptographic verification replace scattered paper folders and inconsistent personal file copies across departments.
              </p>
            </div>

            <!-- Card 2 -->
            <div class="bg-surface p-5 rounded-lg border border-hairline space-y-2.5 hover:border-primary/40 transition">
              <div class="w-9 h-9 rounded-md bg-primary-subtle text-primary flex items-center justify-center shrink-0">
                <Mail class="w-4 h-4" />
              </div>
              <h4 class="text-sm font-bold text-ink">
                Transparent Correspondence Tracking
              </h4>
              <p class="text-xs text-ink-muted leading-relaxed">
                Every incoming letter, outgoing dispatch, and internal memorandum is assigned, tracked, acknowledged, and timestamped with complete chain-of-custody visibility.
              </p>
            </div>

            <!-- Card 3 -->
            <div class="bg-surface p-5 rounded-lg border border-hairline space-y-2.5 hover:border-primary/40 transition">
              <div class="w-9 h-9 rounded-md bg-primary-subtle text-primary flex items-center justify-center shrink-0">
                <Archive class="w-4 h-4" />
              </div>
              <h4 class="text-sm font-bold text-ink">
                Archival Preservation &amp; Memory
              </h4>
              <p class="text-xs text-ink-muted leading-relaxed">
                Senate decisions, approved curricula, research outputs, and strategic plans are safely archived with defined retention schedules, protecting long-term institutional memory.
              </p>
            </div>
          </div>
        </div>
      </section>

      <!-- Section 3: Core Functional Modules -->
      <section id="modules" class="py-12 sm:py-16 bg-surface border-t border-b border-hairline">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="text-center max-w-2xl mx-auto mb-10 sm:mb-12">
            <h2 class="text-xs font-bold   text-primary">
              Functional Architecture
            </h2>
            <h3 class="text-xl sm:text-2xl font-bold text-ink mt-1">
              Complete System Modules
            </h3>
            <p class="text-xs sm:text-sm text-ink-muted mt-2">
              Directly aligned with the official project proposal requirements matrix.
            </p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
            <!-- Module 1: Document Repository -->
            <div class="p-4 sm:p-5 rounded-lg border border-hairline bg-surface hover:border-primary/40 transition space-y-2">
              <div class="flex items-center gap-2.5">
                <div class="p-2 rounded-md bg-primary-subtle text-primary shrink-0">
                  <FileText class="w-4 h-4" />
                </div>
                <h4 class="text-sm font-bold text-ink">Document Repository</h4>
              </div>
              <p class="text-xs text-ink-muted leading-relaxed">
                Centralized storage with deterministic reference sequences (<span class="font-mono text-xs text-primary font-semibold">WU/DOC/YEAR/SEQ</span>), categorical tagging, version releases, and secure file handling.
              </p>
            </div>

            <!-- Module 2: Correspondence Management -->
            <div class="p-4 sm:p-5 rounded-lg border border-hairline bg-surface hover:border-primary/40 transition space-y-2">
              <div class="flex items-center gap-2.5">
                <div class="p-2 rounded-md bg-primary-subtle text-primary shrink-0">
                  <Send class="w-4 h-4" />
                </div>
                <h4 class="text-sm font-bold text-ink">Correspondence Registry</h4>
              </div>
              <p class="text-xs text-ink-muted leading-relaxed">
                Incoming letters with external reference capture, priority assignment, required actions, and outgoing dispatch records linked to source correspondence.
              </p>
            </div>

            <!-- Module 3: Inter-Office Routing -->
            <div class="p-4 sm:p-5 rounded-lg border border-hairline bg-surface hover:border-primary/40 transition space-y-2">
              <div class="flex items-center gap-2.5">
                <div class="p-2 rounded-md bg-primary-subtle text-primary shrink-0">
                  <Layers class="w-4 h-4" />
                </div>
                <h4 class="text-sm font-bold text-ink">Inter-Office Movement</h4>
              </div>
              <p class="text-xs text-ink-muted leading-relaxed">
                Controlled office-to-office document forwarding, formal movement acknowledgments, routing instructions, and multi-actor communication history.
              </p>
            </div>

            <!-- Module 4: Digital Archiving -->
            <div class="p-4 sm:p-5 rounded-lg border border-hairline bg-surface hover:border-primary/40 transition space-y-2">
              <div class="flex items-center gap-2.5">
                <div class="p-2 rounded-md bg-primary-subtle text-primary shrink-0">
                  <Archive class="w-4 h-4" />
                </div>
                <h4 class="text-sm font-bold text-ink">Archive &amp; Retention</h4>
              </div>
              <p class="text-xs text-ink-muted leading-relaxed">
                Structured transfer of inactive records, retention schedule monitoring, archival bay/shelf tracking, and authorized record disposal workflows.
              </p>
            </div>

            <!-- Module 5: Accreditation Quality Assurance -->
            <div class="p-4 sm:p-5 rounded-lg border border-hairline bg-surface hover:border-primary/40 transition space-y-2">
              <div class="flex items-center gap-2.5">
                <div class="p-2 rounded-md bg-primary-subtle text-primary shrink-0">
                  <Award class="w-4 h-4" />
                </div>
                <h4 class="text-sm font-bold text-ink">Accreditation Evidence</h4>
              </div>
              <p class="text-xs text-ink-muted leading-relaxed">
                HERQA national quality assurance standard mapping, frozen version evidence dossiers, review acceptance trails, and exportable institutional compilations.
              </p>
            </div>

            <!-- Module 6: Signatures & PKI -->
            <div class="p-4 sm:p-5 rounded-lg border border-hairline bg-surface hover:border-primary/40 transition space-y-2">
              <div class="flex items-center gap-2.5">
                <div class="p-2 rounded-md bg-primary-subtle text-primary shrink-0">
                  <FileCheck class="w-4 h-4" />
                </div>
                <h4 class="text-sm font-bold text-ink">Signatures &amp; Seals</h4>
              </div>
              <p class="text-xs text-ink-muted leading-relaxed">
                Cryptographic RSA key-pair signatures, institutional stamp artwork authorization, non-repudiation audit trails, and formal verification certificates.
              </p>
            </div>
          </div>
        </div>
      </section>

      <!-- Section 4: Accreditation & Quality Assurance Highlight -->
      <section id="accreditation" class="py-12 sm:py-16 bg-canvas-soft">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
            <div class="lg:col-span-6 space-y-4">
              <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-semibold bg-primary-subtle text-primary">
                <Award class="w-3.5 h-3.5" />
                <span>Academic Quality Assurance</span>
              </div>

              <h3 class="text-xl sm:text-2xl font-bold text-ink">
                Prepared for National Accreditation (HERQA) Assessments
              </h3>

              <p class="text-xs sm:text-sm text-ink-muted leading-relaxed">
                Academic program accreditation requires verifiable documentation across curricula, staff qualifications, research outputs, and Senate decisions. WU-DMS maps frozen document revisions directly against the 10 national quality assurance standards established by the Higher Education Relevance and Quality Agency (HERQA).
              </p>

              <div class="space-y-2 text-xs text-ink-secondary">
                <div class="flex items-center gap-2">
                  <CheckCircle2 class="w-4 h-4 text-primary shrink-0" />
                  <span>Immutable version freeze guarantees evidence cannot be altered after review</span>
                </div>
                <div class="flex items-center gap-2">
                  <CheckCircle2 class="w-4 h-4 text-primary shrink-0" />
                  <span>Cycle-based evidence dossiers exportable for accreditation assessment teams</span>
                </div>
                <div class="flex items-center gap-2">
                  <CheckCircle2 class="w-4 h-4 text-primary shrink-0" />
                  <span>Formal peer-review workflows with accepted, needs-revision, and rejection statuses</span>
                </div>
              </div>
            </div>

            <!-- Accreditation Standards Preview Panel -->
            <div class="lg:col-span-6 bg-surface rounded-lg border border-hairline p-5 space-y-3">
              <div class="flex items-center justify-between pb-2.5 border-b border-hairline">
                <h4 class="text-xs font-bold   text-ink">
                  HERQA Standard Mappings (Active Cycle)
                </h4>
                <span class="text-xs font-mono text-primary bg-primary-subtle px-2 py-0.5 rounded font-semibold">
                  2026 Institutional Audit
                </span>
              </div>

              <div class="space-y-2 text-xs">
                <div class="p-2.5 rounded-md bg-canvas-soft flex items-center justify-between border border-hairline">
                  <div>
                    <span class="font-mono font-bold text-primary">HERQA-01</span>
                    <span class="ml-2 font-medium text-ink-secondary">Vision, Mission and Educational Goals</span>
                  </div>
                  <span class="px-2 py-0.5 rounded text-xs font-semibold bg-[#e8f7ec] text-[#1aae39] dark:bg-[#14301d] dark:text-[#34d399]">Verified</span>
                </div>

                <div class="p-2.5 rounded-md bg-canvas-soft flex items-center justify-between border border-hairline">
                  <div>
                    <span class="font-mono font-bold text-primary">HERQA-02</span>
                    <span class="ml-2 font-medium text-ink-secondary">Governance, Leadership and Management</span>
                  </div>
                  <span class="px-2 py-0.5 rounded text-xs font-semibold bg-[#e8f7ec] text-[#1aae39] dark:bg-[#14301d] dark:text-[#34d399]">Verified</span>
                </div>

                <div class="p-2.5 rounded-md bg-canvas-soft flex items-center justify-between border border-hairline">
                  <div>
                    <span class="font-mono font-bold text-primary">HERQA-03</span>
                    <span class="ml-2 font-medium text-ink-secondary">Academic Programs and Curriculum Design</span>
                  </div>
                  <span class="px-2 py-0.5 rounded text-xs font-semibold bg-[#e8f7ec] text-[#1aae39] dark:bg-[#14301d] dark:text-[#34d399]">Verified</span>
                </div>

                <div class="p-2.5 rounded-md bg-canvas-soft flex items-center justify-between border border-hairline">
                  <div>
                    <span class="font-mono font-bold text-primary">HERQA-06</span>
                    <span class="ml-2 font-medium text-ink-secondary">Research, Innovation and Community Service</span>
                  </div>
                  <span class="px-2 py-0.5 rounded text-xs font-semibold bg-[#fff4e6] text-[#dd5b00] dark:bg-[#3b220c] dark:text-[#f59e0b]">Review Pending</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Section 5: Security, RBAC & Institutional Governance -->
      <section id="governance" class="py-12 sm:py-16 bg-surface border-t border-hairline">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="text-center max-w-2xl mx-auto mb-10 sm:mb-12">
            <h2 class="text-xs font-bold   text-primary">
              Information Security
            </h2>
            <h3 class="text-xl sm:text-2xl font-bold text-ink mt-1">
              Multi-Scope Institutional Governance
            </h3>
            <p class="text-xs sm:text-sm text-ink-muted mt-2">
              Separation of administrative privileges from ordinary document-handling permissions ensures strict confidentiality.
            </p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-5 sm:gap-6">
            <div class="p-5 rounded-lg border border-hairline bg-surface space-y-2.5 hover:border-primary/40 transition">
              <div class="w-9 h-9 rounded-md bg-primary-subtle text-primary flex items-center justify-center shrink-0">
                <ShieldCheck class="w-4 h-4" />
              </div>
              <h4 class="text-sm font-bold text-ink">Scoped Dynamic RBAC</h4>
              <p class="text-xs text-ink-muted leading-relaxed">
                Roles and permissions are scoped by <span class="font-semibold text-ink">Global</span>, <span class="font-semibold text-ink">Unit</span>, or <span class="font-semibold text-ink">Subtree</span>. A department head's authority is bounded to their faculty or directorate without cross-office exposure.
              </p>
            </div>

            <div class="p-5 rounded-lg border border-hairline bg-surface space-y-2.5 hover:border-primary/40 transition">
              <div class="w-9 h-9 rounded-md bg-primary-subtle text-primary flex items-center justify-center shrink-0">
                <Lock class="w-4 h-4" />
              </div>
              <h4 class="text-sm font-bold text-ink">Stateful SPA Cookie Security</h4>
              <p class="text-xs text-ink-muted leading-relaxed">
                Encrypted, HTTP-only, SameSite session cookies powered by Laravel Sanctum. No bearer tokens or authentication secrets are ever stored in browser localStorage.
              </p>
            </div>

            <div class="p-5 rounded-lg border border-hairline bg-surface space-y-2.5 hover:border-primary/40 transition">
              <div class="w-9 h-9 rounded-md bg-primary-subtle text-primary flex items-center justify-center shrink-0">
                <Database class="w-4 h-4" />
              </div>
              <h4 class="text-sm font-bold text-ink">Tamper-Proof Audit Ledger</h4>
              <p class="text-xs text-ink-muted leading-relaxed">
                Every document view, revision, status transition, permission assignment, and dispatch event is immutably logged with actor identity, timestamp, and IP address telemetry.
              </p>
            </div>
          </div>
        </div>
      </section>

      <!-- Section 6: University Stakeholder Roles -->
      <section id="stakeholders" class="py-12 sm:py-16 bg-canvas-soft">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="text-center max-w-2xl mx-auto mb-10 sm:mb-12">
            <h2 class="text-xs font-bold   text-primary">
              Participating Offices
            </h2>
            <h3 class="text-xl sm:text-2xl font-bold text-ink mt-1">
              Supporting Every Campus Role
            </h3>
            <p class="text-xs sm:text-sm text-ink-muted mt-2">
              Dedicated interfaces and permissions tailored to each institutional responsibility.
            </p>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 text-xs">
            <div class="p-3.5 rounded-lg bg-surface border border-hairline hover:border-primary/30 transition">
              <div class="font-bold text-ink">University Management</div>
              <div class="text-xs text-ink-muted mt-0.5">President, Vice Presidents, Directors</div>
            </div>

            <div class="p-3.5 rounded-lg bg-surface border border-hairline hover:border-primary/30 transition">
              <div class="font-bold text-ink">Academic Leadership</div>
              <div class="text-xs text-ink-muted mt-0.5">Colleges, Institutes (KIoT), Deans, Heads</div>
            </div>

            <div class="p-3.5 rounded-lg bg-surface border border-hairline hover:border-primary/30 transition">
              <div class="font-bold text-ink">Registrar &amp; Records</div>
              <div class="text-xs text-ink-muted mt-0.5">Student administration &amp; central registry</div>
            </div>

            <div class="p-3.5 rounded-lg bg-surface border border-hairline hover:border-primary/30 transition">
              <div class="font-bold text-ink">Administrative Directorates</div>
              <div class="text-xs text-ink-muted mt-0.5">Human Resources, Finance &amp; Procurement</div>
            </div>

            <div class="p-3.5 rounded-lg bg-surface border border-hairline hover:border-primary/30 transition">
              <div class="font-bold text-ink">Research &amp; Community</div>
              <div class="text-xs text-ink-muted mt-0.5">Directorate of Research &amp; Publication</div>
            </div>

            <div class="p-3.5 rounded-lg bg-surface border border-hairline hover:border-primary/30 transition">
              <div class="font-bold text-ink">Records Officers &amp; Archivists</div>
              <div class="text-xs text-ink-muted mt-0.5">Preservation, retention, physical indexing</div>
            </div>

            <div class="p-3.5 rounded-lg bg-surface border border-hairline hover:border-primary/30 transition">
              <div class="font-bold text-ink">Information Technology</div>
              <div class="text-xs text-ink-muted mt-0.5">System administrators, backup operators</div>
            </div>

            <div class="p-3.5 rounded-lg bg-surface border border-hairline hover:border-primary/30 transition">
              <div class="font-bold text-ink">Academic &amp; Support Staff</div>
              <div class="text-xs text-ink-muted mt-0.5">Departmental tasks &amp; internal memoranda</div>
            </div>
          </div>
        </div>
      </section>

      <!-- Section 7: Final Institutional Call to Action -->
      <section id="access" class="py-12 sm:py-16 bg-surface border-t border-hairline">
        <div class="max-w-3xl mx-auto px-4 text-center space-y-4 sm:space-y-5">
          <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-lg bg-primary text-white flex items-center justify-center font-bold text-base mx-auto">
            WU
          </div>

          <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-ink">
            Authorized Staff Workspace Access
          </h2>

          <p class="text-xs sm:text-sm text-ink-muted max-w-lg mx-auto leading-relaxed">
            Access to WU-DMS is restricted to authorized Wollo University employees. Please sign in with your institutional credentials (<span class="font-mono text-xs text-primary font-semibold">@wu.edu.et</span>) to manage documents, letters, and workflow tasks.
          </p>

          <div class="pt-1 flex justify-center">
            <BaseButton
              variant="primary"
              size="md"
              class="text-xs sm:text-sm font-semibold py-2 px-5 sm:px-6 rounded-lg"
              @click="handleCtaClick"
            >
              <span>{{ authStore.isAuthenticated ? 'Enter Workspace' : 'Sign In with Institutional ID' }}</span>
              <ArrowRight class="w-4 h-4 ml-1.5" />
            </BaseButton>
          </div>
        </div>
      </section>
    </main>

    <PublicFooter />
  </div>
</template>

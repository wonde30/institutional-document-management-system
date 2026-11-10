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
 Users,
 CheckCircle2,
 ArrowRight,
 Database,
 Lock,
 Layers,
 FileCheck,
 Search,
 BookOpen,
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
 <section id="overview" class="relative pt-12 pb-20 sm:pt-20 sm:pb-28 overflow-hidden border-b border-hairline bg-surface">
 <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
 <div class="max-w-3xl">
 <!-- Institutional Affiliation Tag -->
 <div class="inline-flex items-center gap-2 px-3 py-1 rounded-md text-xs font-semibold bg-primary-subtle text-primary border border-primary/20 mb-6">
 <Building2 class="w-3.5 h-3.5 shrink-0" />
 <span>Wollo University &bull; Kombolcha Institute of Technology (KIoT)</span>
 </div>

 <h1 class="text-3xl sm:text-5xl font-bold tracking-tight text-ink-secondary leading-tight sm:leading-tight">
 Web-Based Integrated Document Repository, Digital Archive &amp; Inter-Office Communication
 </h1>

 <p class="mt-5 text-sm sm:text-base text-ink-muted leading-relaxed">
 Designed specifically for Wollo University's academic directorates, colleges, and administrative offices. Centralizing institutional document registration, audited correspondence routing, long-term digital preservation, and verifiable accreditation evidence within a single, secure environment.
 </p>

 <!-- Institutional CTA Row -->
 <div class="mt-8 flex flex-wrap items-center gap-4">
 <BaseButton
 variant="primary"
 size="md"
 class="text-xs sm:text-sm font-semibold py-2.5 px-5 rounded-lg shadow-xs"
 @click="handleCtaClick"
 >
 <span>{{ authStore.isAuthenticated ? 'Enter Workspace' : 'Sign In to Workspace' }}</span>
 <ArrowRight class="w-4 h-4 ml-1.5" />
 </BaseButton>

 <a
 href="#modules"
 class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-hairline text-xs sm:text-sm font-semibold text-ink-secondary hover:bg-surface-hover transition-colors"
 >
 <span>Explore Core Modules</span>
 </a>
 </div>

 <!-- Technical Scope Fact Sheet -->
 <div class="mt-12 pt-8 border-t border-hairline grid grid-cols-2 sm:grid-cols-4 gap-6">
 <div>
 <span class="block text-2xl font-bold font-mono text-primary">118</span>
 <span class="block text-xs font-medium text-ink-muted mt-0.5">Normalized Tables</span>
 </div>
 <div>
 <span class="block text-2xl font-bold font-mono text-primary">3NF</span>
 <span class="block text-xs font-medium text-ink-muted mt-0.5">Relational Baseline</span>
 </div>
 <div>
 <span class="block text-2xl font-bold font-mono text-primary">100%</span>
 <span class="block text-xs font-medium text-ink-muted mt-0.5">Audited Lifecycle</span>
 </div>
 <div>
 <span class="block text-2xl font-bold font-mono text-primary">Multi-Unit</span>
 <span class="block text-xs font-medium text-ink-muted mt-0.5">Scoped RBAC</span>
 </div>
 </div>
 </div>
 </div>
 </section>

 <!-- Section 2: Statement of the Problem & Institutional Solution -->
 <section class="py-16 sm:py-24 bg-canvas-soft">
 <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
 <div class="text-center max-w-2xl mx-auto mb-14">
 <h2 class="text-xs font-bold uppercase tracking-wider text-primary">
 Institutional Context
 </h2>
 <h3 class="text-2xl sm:text-3xl font-bold text-ink-secondary mt-1.5">
 Transforming Administrative Workflows
 </h3>
 <p class="text-xs sm:text-sm text-ink-muted mt-3 leading-relaxed">
 As an applied university, Wollo University requires robust digital systems to eliminate paper-based bottlenecks, fragmented office channels, and delayed decision-making.
 </p>
 </div>

 <div class="grid grid-cols-1 md:grid-cols-3 gap-6 sm:gap-8">
 <!-- Card 1 -->
 <div class="bg-surface p-6 rounded-xl border border-hairline shadow-xs space-y-3">
 <div class="w-10 h-10 rounded-lg bg-primary-subtle text-primary flex items-center justify-center">
 <FileText class="w-5 h-5" />
 </div>
 <h4 class="text-sm font-bold text-ink-secondary">
 Unified Document Repository
 </h4>
 <p class="text-xs text-ink-muted leading-relaxed">
 Centralized classification, structured metadata, and SHA-256 cryptographic verification replace scattered paper folders and inconsistent personal file copies across departments.
 </p>
 </div>

 <!-- Card 2 -->
 <div class="bg-surface p-6 rounded-xl border border-hairline shadow-xs space-y-3">
 <div class="w-10 h-10 rounded-lg bg-primary-subtle text-primary flex items-center justify-center">
 <Mail class="w-5 h-5" />
 </div>
 <h4 class="text-sm font-bold text-ink-secondary">
 Transparent Correspondence Tracking
 </h4>
 <p class="text-xs text-ink-muted leading-relaxed">
 Every incoming letter, outgoing dispatch, and internal memorandum is assigned, tracked, acknowledged, and timestamped with complete chain-of-custody visibility.
 </p>
 </div>

 <!-- Card 3 -->
 <div class="bg-surface p-6 rounded-xl border border-hairline shadow-xs space-y-3">
 <div class="w-10 h-10 rounded-lg bg-primary-subtle text-primary flex items-center justify-center">
 <Archive class="w-5 h-5" />
 </div>
 <h4 class="text-sm font-bold text-ink-secondary">
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
 <section id="modules" class="py-16 sm:py-24 bg-surface border-t border-b border-hairline">
 <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
 <div class="text-center max-w-2xl mx-auto mb-14">
 <h2 class="text-xs font-bold uppercase tracking-wider text-primary">
 Functional Architecture
 </h2>
 <h3 class="text-2xl sm:text-3xl font-bold text-ink-secondary mt-1.5">
 Comprehensive System Modules
 </h3>
 <p class="text-xs sm:text-sm text-ink-muted mt-3">
 Directly aligned with the official project proposal requirements matrix.
 </p>
 </div>

 <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
 <!-- Module 1: Document Repository -->
 <div class="p-5 rounded-xl border border-hairline bg-canvas-soft space-y-2.5">
 <div class="flex items-center gap-2.5">
 <div class="p-2 rounded-lg bg-primary-subtle text-primary">
 <FileText class="w-4 h-4" />
 </div>
 <h4 class="text-sm font-bold text-ink-secondary">Document Repository</h4>
 </div>
 <p class="text-xs text-ink-muted leading-relaxed">
 Centralized storage with deterministic reference sequences (<span class="font-mono text-[11px]">WU/DOC/YEAR/SEQ</span>), categorical tagging, version releases, and secure file handling.
 </p>
 </div>

 <!-- Module 2: Correspondence Management -->
 <div class="p-5 rounded-xl border border-hairline bg-canvas-soft space-y-2.5">
 <div class="flex items-center gap-2.5">
 <div class="p-2 rounded-lg bg-primary-subtle text-primary">
 <Send class="w-4 h-4" />
 </div>
 <h4 class="text-sm font-bold text-ink-secondary">Correspondence Registry</h4>
 </div>
 <p class="text-xs text-ink-muted leading-relaxed">
 Incoming letters with external reference capture, priority assignment, required actions, and outgoing dispatch records linked to source correspondence.
 </p>
 </div>

 <!-- Module 3: Inter-Office Routing -->
 <div class="p-5 rounded-xl border border-hairline bg-canvas-soft space-y-2.5">
 <div class="flex items-center gap-2.5">
 <div class="p-2 rounded-lg bg-primary-subtle text-primary">
 <Layers class="w-4 h-4" />
 </div>
 <h4 class="text-sm font-bold text-ink-secondary">Inter-Office Movement</h4>
 </div>
 <p class="text-xs text-ink-muted leading-relaxed">
 Controlled office-to-office document forwarding, formal movement acknowledgments, routing instructions, and multi-actor communication history.
 </p>
 </div>

 <!-- Module 4: Digital Archiving -->
 <div class="p-5 rounded-xl border border-hairline bg-canvas-soft space-y-2.5">
 <div class="flex items-center gap-2.5">
 <div class="p-2 rounded-lg bg-primary-subtle text-primary">
 <Archive class="w-4 h-4" />
 </div>
 <h4 class="text-sm font-bold text-ink-secondary">Archive &amp; Retention</h4>
 </div>
 <p class="text-xs text-ink-muted leading-relaxed">
 Structured transfer of inactive records, retention schedule monitoring, archival bay/shelf tracking, and authorized record disposal workflows.
 </p>
 </div>

 <!-- Module 5: Accreditation Quality Assurance -->
 <div class="p-5 rounded-xl border border-hairline bg-canvas-soft space-y-2.5">
 <div class="flex items-center gap-2.5">
 <div class="p-2 rounded-lg bg-primary-subtle text-primary">
 <Award class="w-4 h-4" />
 </div>
 <h4 class="text-sm font-bold text-ink-secondary">Accreditation Evidence</h4>
 </div>
 <p class="text-xs text-ink-muted leading-relaxed">
 HERQA national quality assurance standard mapping, frozen version evidence dossiers, review acceptance trails, and exportable institutional compilations.
 </p>
 </div>

 <!-- Module 6: Signatures & PKI -->
 <div class="p-5 rounded-xl border border-hairline bg-canvas-soft space-y-2.5">
 <div class="flex items-center gap-2.5">
 <div class="p-2 rounded-lg bg-primary-subtle text-primary">
 <FileCheck class="w-4 h-4" />
 </div>
 <h4 class="text-sm font-bold text-ink-secondary">Signatures &amp; Seals</h4>
 </div>
 <p class="text-xs text-ink-muted leading-relaxed">
 Cryptographic RSA key-pair signatures, institutional stamp artwork authorization, non-repudiation audit trails, and formal verification certificates.
 </p>
 </div>
 </div>
 </div>
 </section>

 <!-- Section 4: Accreditation & Quality Assurance Highlight -->
 <section id="accreditation" class="py-16 sm:py-24 bg-canvas-soft">
 <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
 <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">
 <div class="lg:col-span-6 space-y-5">
 <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-semibold bg-primary-subtle text-primary">
 <Award class="w-4 h-4" />
 <span>Academic Quality Assurance</span>
 </div>

 <h3 class="text-2xl sm:text-3xl font-bold text-ink-secondary">
 Prepared for National Accreditation (HERQA) Assessments
 </h3>

 <p class="text-xs sm:text-sm text-ink-muted leading-relaxed">
 Academic program accreditation requires verifiable documentation across curricula, staff qualifications, research outputs, and Senate decisions. WU-DMS maps frozen document revisions directly against the 10 national quality assurance standards established by the Higher Education Relevance and Quality Agency (HERQA).
 </p>

 <div class="space-y-2.5 text-xs text-ink-secondary">
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
 <div class="lg:col-span-6 bg-surface rounded-xl border border-hairline p-6 shadow-xs space-y-4">
 <div class="flex items-center justify-between pb-3 border-b border-hairline">
 <h4 class="text-xs font-bold uppercase tracking-wider text-ink-secondary">
 HERQA Standard Mappings (Active Cycle)
 </h4>
 <span class="text-[11px] font-mono text-primary bg-primary-subtle px-2 py-0.5 rounded-md font-semibold">
 2026 Institutional Audit
 </span>
 </div>

 <div class="space-y-2 text-xs">
 <div class="p-2.5 rounded-lg bg-canvas-soft flex items-center justify-between">
 <div>
 <span class="font-mono font-bold text-primary">HERQA-01</span>
 <span class="ml-2 font-medium">Vision, Mission and Educational Goals</span>
 </div>
 <span class="text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">Verified</span>
 </div>

 <div class="p-2.5 rounded-lg bg-canvas-soft flex items-center justify-between">
 <div>
 <span class="font-mono font-bold text-primary">HERQA-02</span>
 <span class="ml-2 font-medium">Governance, Leadership and Management</span>
 </div>
 <span class="text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">Verified</span>
 </div>

 <div class="p-2.5 rounded-lg bg-canvas-soft flex items-center justify-between">
 <div>
 <span class="font-mono font-bold text-primary">HERQA-03</span>
 <span class="ml-2 font-medium">Academic Programs and Curriculum Design</span>
 </div>
 <span class="text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">Verified</span>
 </div>

 <div class="p-2.5 rounded-lg bg-canvas-soft flex items-center justify-between">
 <div>
 <span class="font-mono font-bold text-primary">HERQA-06</span>
 <span class="ml-2 font-medium">Research, Innovation and Community Service</span>
 </div>
 <span class="text-[11px] font-semibold text-amber-700 dark:text-amber-300">Review Pending</span>
 </div>
 </div>
 </div>
 </div>
 </div>
 </section>

 <!-- Section 5: Security, RBAC & Institutional Governance -->
 <section id="governance" class="py-16 sm:py-24 bg-surface border-t border-hairline">
 <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
 <div class="text-center max-w-2xl mx-auto mb-14">
 <h2 class="text-xs font-bold uppercase tracking-wider text-primary">
 Information Security
 </h2>
 <h3 class="text-2xl sm:text-3xl font-bold text-ink-secondary mt-1.5">
 Multi-Scope Institutional Governance
 </h3>
 <p class="text-xs sm:text-sm text-ink-muted mt-3">
 Separation of administrative privileges from ordinary document-handling permissions ensures strict confidentiality.
 </p>
 </div>

 <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
 <div class="p-5 rounded-xl border border-hairline bg-canvas-soft space-y-3">
 <div class="w-9 h-9 rounded-lg bg-primary-subtle text-primary flex items-center justify-center">
 <ShieldCheck class="w-5 h-5" />
 </div>
 <h4 class="text-sm font-bold text-ink-secondary">Scoped Dynamic RBAC</h4>
 <p class="text-xs text-ink-muted leading-relaxed">
 Roles and permissions are scoped by <span class="font-medium text-ink-secondary">Global</span>, <span class="font-medium text-ink-secondary">Unit</span>, or <span class="font-medium text-ink-secondary">Subtree</span>. A department head's authority is bounded to their faculty or directorate without cross-office exposure.
 </p>
 </div>

 <div class="p-5 rounded-xl border border-hairline bg-canvas-soft space-y-3">
 <div class="w-9 h-9 rounded-lg bg-primary-subtle text-primary flex items-center justify-center">
 <Lock class="w-5 h-5" />
 </div>
 <h4 class="text-sm font-bold text-ink-secondary">Stateful SPA Cookie Security</h4>
 <p class="text-xs text-ink-muted leading-relaxed">
 Encrypted, HTTP-only, SameSite session cookies powered by Laravel Sanctum. No bearer tokens or authentication secrets are ever stored in browser localStorage.
 </p>
 </div>

 <div class="p-5 rounded-xl border border-hairline bg-canvas-soft space-y-3">
 <div class="w-9 h-9 rounded-lg bg-primary-subtle text-primary flex items-center justify-center">
 <Database class="w-5 h-5" />
 </div>
 <h4 class="text-sm font-bold text-ink-secondary">Tamper-Proof Audit Ledger</h4>
 <p class="text-xs text-ink-muted leading-relaxed">
 Every document view, revision, status transition, permission assignment, and dispatch event is immutably logged with actor identity, timestamp, and IP address telemetry.
 </p>
 </div>
 </div>
 </div>
 </section>

 <!-- Section 6: University Stakeholder Roles -->
 <section class="py-16 sm:py-24 bg-canvas-soft">
 <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
 <div class="text-center max-w-2xl mx-auto mb-14">
 <h2 class="text-xs font-bold uppercase tracking-wider text-primary">
 Participating Offices
 </h2>
 <h3 class="text-2xl sm:text-3xl font-bold text-ink-secondary mt-1.5">
 Empowering Every Campus Persona
 </h3>
 <p class="text-xs sm:text-sm text-ink-muted mt-3">
 Dedicated interfaces and permissions tailored to each institutional responsibility.
 </p>
 </div>

 <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
 <div class="p-4 rounded-xl bg-surface border border-hairline">
 <div class="font-bold text-ink-secondary">University Management</div>
 <div class="text-[11px] text-ink-muted mt-1">President, Vice Presidents, Directors</div>
 </div>

 <div class="p-4 rounded-xl bg-surface border border-hairline">
 <div class="font-bold text-ink-secondary">Academic Leadership</div>
 <div class="text-[11px] text-ink-muted mt-1">Colleges, Institutes (KIoT), Deans, Heads</div>
 </div>

 <div class="p-4 rounded-xl bg-surface border border-hairline">
 <div class="font-bold text-ink-secondary">Registrar &amp; Records</div>
 <div class="text-[11px] text-ink-muted mt-1">Student administration &amp; central registry</div>
 </div>

 <div class="p-4 rounded-xl bg-surface border border-hairline">
 <div class="font-bold text-ink-secondary">Administrative Directorates</div>
 <div class="text-[11px] text-ink-muted mt-1">Human Resources, Finance &amp; Procurement</div>
 </div>

 <div class="p-4 rounded-xl bg-surface border border-hairline">
 <div class="font-bold text-ink-secondary">Research &amp; Community</div>
 <div class="text-[11px] text-ink-muted mt-1">Directorate of Research &amp; Publication</div>
 </div>

 <div class="p-4 rounded-xl bg-surface border border-hairline">
 <div class="font-bold text-ink-secondary">Records Officers &amp; Archivists</div>
 <div class="text-[11px] text-ink-muted mt-1">Preservation, retention, physical indexing</div>
 </div>

 <div class="p-4 rounded-xl bg-surface border border-hairline">
 <div class="font-bold text-ink-secondary">Information Technology</div>
 <div class="text-[11px] text-ink-muted mt-1">System administrators, backup operators</div>
 </div>

 <div class="p-4 rounded-xl bg-surface border border-hairline">
 <div class="font-bold text-ink-secondary">Academic &amp; Support Staff</div>
 <div class="text-[11px] text-ink-muted mt-1">Departmental tasks &amp; internal memoranda</div>
 </div>
 </div>
 </div>
 </section>

 <!-- Section 7: Final Institutional Call to Action -->
 <section class="py-16 sm:py-20 bg-surface border-t border-hairline">
 <div class="max-w-4xl mx-auto px-4 text-center space-y-6">
 <div class="w-12 h-12 rounded-xl bg-primary text-white flex items-center justify-center font-bold text-lg mx-auto shadow-xs">
 WU
 </div>

 <h2 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink-secondary">
 Authorized Staff Workspace Access
 </h2>

 <p class="text-xs sm:text-sm text-ink-muted max-w-xl mx-auto leading-relaxed">
 Access to WU-DMS is restricted to authorized Wollo University employees. Please sign in with your institutional credentials (<span class="font-mono text-xs">@wu.edu.et</span>) to manage documents, letters, and workflow tasks.
 </p>

 <div class="pt-2 flex justify-center">
 <BaseButton
 variant="primary"
 size="md"
 class="text-xs sm:text-sm font-semibold py-2.5 px-6 rounded-lg shadow-xs"
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

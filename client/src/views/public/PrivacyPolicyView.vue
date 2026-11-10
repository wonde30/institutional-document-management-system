<script setup lang="ts">
import PublicNavbar from '@/components/public/PublicNavbar.vue'
import PublicFooter from '@/components/public/PublicFooter.vue'
import { ShieldCheck, Lock, FileText, Eye, CheckCircle2, Building2 } from 'lucide-vue-next'
</script>

<template>
 <div class="min-h-screen flex flex-col bg-canvas-soft text-ink-secondary transition-colors selection:bg-primary selection:text-white">
 <PublicNavbar />

 <main class="flex-1 py-12 sm:py-16">
 <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
 <!-- Header -->
 <div class="border-b border-hairline pb-8 mb-8 space-y-3">
 <div class="inline-flex items-center gap-2 px-3 py-1 rounded-md text-xs font-semibold bg-primary-subtle text-primary border border-primary/20">
 <ShieldCheck class="w-3.5 h-3.5" />
 <span>Institutional Governance</span>
 </div>

 <h1 class="text-3xl sm:text-4xl font-bold tracking-tight text-ink-secondary">
 Privacy &amp; Data Protection Policy
 </h1>

 <p class="text-xs sm:text-sm text-ink-muted">
 Wollo University Administrative and Document Management System (WU-DMS) &bull; Version 1.2 (Effective 2026)
 </p>
 </div>

 <!-- Policy Content -->
 <div class="space-y-8 text-xs sm:text-sm leading-relaxed text-ink-secondary">
 <!-- Section 1 -->
 <section class="space-y-3">
 <h2 class="text-base sm:text-lg font-bold text-ink-secondary flex items-center gap-2">
 <span class="w-6 h-6 rounded-md bg-primary text-white flex items-center justify-center text-xs font-bold">1</span>
 Institutional Purpose &amp; Scope
 </h2>
 <p class="text-ink-muted">
 This Privacy Policy governs the collection, processing, storage, and access of institutional records within the Web-based Integrated Document Repository, Digital Archive, and Inter-Office Communication Management System (WU-DMS) of Wollo University. It applies across all campuses, including the Main Campus (Dessie), the Kombolcha Institute of Technology (KIoT), colleges, academic departments, research institutes, and administrative directorates.
 </p>
 <p class="text-ink-muted">
 The system operates in compliance with Ethiopian Higher Education Proclamation No. 1152/2019, the Electronic Signature Proclamation No. 1072/2018, and Wollo University internal recordkeeping regulations.
 </p>
 </section>

 <!-- Section 2 -->
 <section class="space-y-3">
 <h2 class="text-base sm:text-lg font-bold text-ink-secondary flex items-center gap-2">
 <span class="w-6 h-6 rounded-md bg-primary text-white flex items-center justify-center text-xs font-bold">2</span>
 Categories of Information Handled
 </h2>
 <div class="p-4 rounded-xl border border-hairline bg-surface space-y-3">
 <div>
 <h3 class="font-bold text-xs uppercase tracking-wider text-primary">
 A. User Account &amp; Identity Data
 </h3>
 <p class="text-ink-muted mt-1 text-xs">
 Full name, institutional email address (<span class="font-mono">@wu.edu.et</span>), assigned organizational unit membership, job position, cryptographic signing key fingerprints, and dynamic role assignments.
 </p>
 </div>

 <div>
 <h3 class="font-bold text-xs uppercase tracking-wider text-primary">
 B. Official Records &amp; Document Metadata
 </h3>
 <p class="text-ink-muted mt-1 text-xs">
 Official correspondence, directives, memoranda, curriculum proposals, Senate minutes, research reports, accreditation evidence, file sizes, MIME types, and SHA-256 cryptographic content hashes.
 </p>
 </div>

 <div>
 <h3 class="font-bold text-xs uppercase tracking-wider text-primary">
 C. Security Audit &amp; Technical Telemetry
 </h3>
 <p class="text-ink-muted mt-1 text-xs">
 Immutable event records capturing user identity, timestamp, originating IP address, HTTP user-agent, action performed (e.g. view, create, approve, dispatch), and unique request UUIDs.
 </p>
 </div>
 </div>
 </section>

 <!-- Section 3 -->
 <section class="space-y-3">
 <h2 class="text-base sm:text-lg font-bold text-ink-secondary flex items-center gap-2">
 <span class="w-6 h-6 rounded-md bg-primary text-white flex items-center justify-center text-xs font-bold">3</span>
 Role-Based Access Boundaries &amp; Scoping
 </h2>
 <p class="text-ink-muted">
 WU-DMS enforces a strict, server-side dynamic Role-Based Access Control (RBAC) architecture. In accordance with Section 1.4.8 of the Project Proposal, administrative privileges are strictly separated from ordinary document-handling permissions:
 </p>
 <ul class="list-disc pl-5 space-y-1.5 text-ink-muted">
 <li><strong>Unit Scope:</strong> Users assigned to a specific department or office may only access records owned by or routed to their unit.</li>
 <li><strong>Subtree Scope:</strong> Deans and Institute Directors may access records within their constituent colleges or directorates.</li>
 <li><strong>Global Scope:</strong> Limited strictly to executive university leadership and designated system auditors.</li>
 <li><strong>Classification Tiers:</strong> Documents tagged as Restricted or Confidential require explicit security authorization regardless of unit assignment.</li>
 </ul>
 </section>

 <!-- Section 4 -->
 <section class="space-y-3">
 <h2 class="text-base sm:text-lg font-bold text-ink-secondary flex items-center gap-2">
 <span class="w-6 h-6 rounded-md bg-primary text-white flex items-center justify-center text-xs font-bold">4</span>
 Stateful Cookies &amp; Browser Security
 </h2>
 <p class="text-ink-muted">
 WU-DMS employs stateful Laravel Sanctum SPA session cookies. The application does not store access tokens or credential secrets in browser <span class="font-mono text-xs">localStorage</span>. All session cookies are configured with:
 </p>
 <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
 <div class="p-3 rounded-lg border border-hairline bg-surface">
 <div class="font-mono font-bold text-xs text-primary">HttpOnly</div>
 <div class="text-[11px] text-ink-muted mt-0.5">Prevents script access</div>
 </div>
 <div class="p-3 rounded-lg border border-hairline bg-surface">
 <div class="font-mono font-bold text-xs text-primary">SameSite=Lax</div>
 <div class="text-[11px] text-ink-muted mt-0.5">Protects against CSRF</div>
 </div>
 <div class="p-3 rounded-lg border border-hairline bg-surface">
 <div class="font-mono font-bold text-xs text-primary">Zero Trackers</div>
 <div class="text-[11px] text-ink-muted mt-0.5">No 3rd party advertising</div>
 </div>
 </div>
 </section>

 <!-- Section 5 -->
 <section class="space-y-3">
 <h2 class="text-base sm:text-lg font-bold text-ink-secondary flex items-center gap-2">
 <span class="w-6 h-6 rounded-md bg-primary text-white flex items-center justify-center text-xs font-bold">5</span>
 Retention, Digital Archiving &amp; Preservation
 </h2>
 <p class="text-ink-muted">
 In compliance with Section 1.4.2 of the Project Proposal, records that conclude their active administrative workflows are preserved in the Digital Archive. The system enforces institutional retention schedules. Digital records remain available for historical continuity, accreditation inspections, and legal defense. No digital record may be deleted or purged without an authorized disposal resolution signed by the University Registrar or Archive Committee.
 </p>
 </section>

 <!-- Section 6 -->
 <section class="space-y-3">
 <h2 class="text-base sm:text-lg font-bold text-ink-secondary flex items-center gap-2">
 <span class="w-6 h-6 rounded-md bg-primary text-white flex items-center justify-center text-xs font-bold">6</span>
 Inquiries &amp; Data Protection Officer Contact
 </h2>
 <p class="text-ink-muted">
 For questions regarding privacy, record classification disputes, or account delegation requests, authorized personnel may contact:
 </p>
 <div class="p-4 rounded-xl border border-hairline bg-surface text-xs space-y-1">
 <p class="font-bold text-ink-secondary">Information Technology Directorate — Systems Division</p>
 <p class="text-ink-muted">Wollo University, Main Campus, Administration Building, 3rd Floor</p>
 <p class="text-ink-muted">Email: <a href="mailto:ict.support@wu.edu.et" class="text-primary hover:underline">ict.support@wu.edu.et</a></p>
 <p class="text-ink-muted">P.O. Box 1145, Dessie, Ethiopia</p>
 </div>
 </section>
 </div>
 </div>
 </main>

 <PublicFooter />
 </div>
</template>

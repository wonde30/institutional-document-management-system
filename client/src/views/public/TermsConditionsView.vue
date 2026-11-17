<script setup lang="ts">
import PublicNavbar from '@/components/public/PublicNavbar.vue'
import PublicFooter from '@/components/public/PublicFooter.vue'
import { FileText, AlertCircle, CheckCircle2, ShieldCheck, Scale, Building2 } from 'lucide-vue-next'
</script>

<template>
 <div class="min-h-screen flex flex-col bg-canvas-soft text-ink-secondary transition-colors selection:bg-primary selection:text-white">
 <PublicNavbar />

 <main class="flex-1 py-12 sm:py-16">
 <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
 <!-- Header -->
 <div class="border-b border-hairline pb-8 mb-8 space-y-3">
 <div class="inline-flex items-center gap-2 px-3 py-1 rounded-md text-xs font-semibold bg-primary-subtle text-primary border border-primary/20">
 <Scale class="w-3.5 h-3.5" />
 <span>Institutional Legal Terms</span>
 </div>

 <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-ink">
 Terms of Use &amp; Administrative Service Agreement
 </h1>

 <p class="text-xs sm:text-sm text-ink-muted">
 Wollo University Administrative and Document Management System (WU-DMS) &bull; Version 1.2 (Effective 2026)
 </p>
 </div>

 <!-- Terms Content -->
 <div class="space-y-8 text-xs sm:text-sm leading-relaxed text-ink-secondary">
 <!-- Section 1 -->
 <section class="space-y-3">
 <h2 class="text-base sm:text-lg font-bold text-ink-secondary flex items-center gap-2">
 <span class="w-6 h-6 rounded-md bg-primary text-white flex items-center justify-center text-xs font-bold">1</span>
 Authorized User Acceptance
 </h2>
 <p class="text-ink-muted">
 By signing in to the Wollo University Administrative and Document Management System (WU-DMS), you certify that you are an active employee, academic staff member, department head, director, or designated administrator of Wollo University. Use of this system constitutes unconditional agreement to adhere to these Terms of Use, the Wollo University Senate Legislation, and relevant Ethiopian administrative and cybersecurity regulations.
 </p>
 </section>

 <!-- Section 2 -->
 <section class="space-y-3">
 <h2 class="text-base sm:text-lg font-bold text-ink-secondary flex items-center gap-2">
 <span class="w-6 h-6 rounded-md bg-primary text-white flex items-center justify-center text-xs font-bold">2</span>
 Account Security &amp; Non-Transferability
 </h2>
 <p class="text-ink-muted">
 Each user account is tied to an individual university identity. In accordance with institutional information security policy:
 </p>
 <ul class="list-disc pl-5 space-y-1.5 text-ink-muted">
 <li>You are personally responsible for all actions, uploads, approvals, and dispatches performed under your authenticated session.</li>
 <li>Account credentials (usernames, passwords, MFA factors, and PKI private keys) are strictly non-transferable and must never be shared with colleagues, assistants, or third parties.</li>
 <li>Temporary delegation of authority must be executed via official system delegation workflows, not through password disclosure.</li>
 <li>Any suspected compromise of credentials must be reported immediately to the Information Technology Directorate.</li>
 </ul>
 </section>

 <!-- Section 3 -->
 <section class="space-y-3">
 <h2 class="text-base sm:text-lg font-bold text-ink-secondary flex items-center gap-2">
 <span class="w-6 h-6 rounded-md bg-primary text-white flex items-center justify-center text-xs font-bold">3</span>
 Official Document Integrity &amp; Digital Signatures
 </h2>
 <p class="text-ink-muted">
 Documents registered, routed, or finalized within WU-DMS represent official university records:
 </p>
 <div class="p-4 rounded-xl border border-hairline bg-surface space-y-2 text-xs">
 <p class="font-bold text-ink-secondary">
 A. Legal Validity of Internal Electronic Signatures
 </p>
 <p class="text-ink-muted">
 In accordance with Electronic Signature Proclamation No. 1072/2018, digital signatures and institutional stamp authorizations applied through WU-DMS carry binding administrative validity within Wollo University governance structures.
 </p>
 <p class="font-bold text-ink-secondary pt-2">
 B. Tamper-Proof Audit &amp; Non-Repudiation
 </p>
 <p class="text-ink-muted">
 Once a document version is signed, approved, or officially released, its content bytes and metadata are frozen in immutable database projections. Any attempt to alter, forge, or delete released documents constitutes academic and administrative misconduct.
 </p>
 </div>
 </section>

 <!-- Section 4 -->
 <section class="space-y-3">
 <h2 class="text-base sm:text-lg font-bold text-ink-secondary flex items-center gap-2">
 <span class="w-6 h-6 rounded-md bg-primary text-white flex items-center justify-center text-xs font-bold">4</span>
 Physical Originals Preservation Rule (Proposal §1.7.4)
 </h2>
 <div class="p-4 rounded-xl border border-amber-200 dark:border-amber-900/60 bg-[#FFF5DE] dark:bg-[#3A3020] text-[#725115] dark:text-[#F4D995] space-y-2 text-xs">
 <div class="flex items-center gap-2 font-bold">
 <AlertCircle class="w-4 h-4 shrink-0" />
 <span>Statutory Notice on Physical Documentation</span>
 </div>
 <p class="leading-relaxed">
 As explicitly established in Section 1.7.4 of the Wollo University Project Proposal, the registration of a digital scan or electronic record within WU-DMS <strong>does not automatically replace or authorize the destruction of original paper records</strong> where original signatures, government watermarks, fiscal stamps, or legal mandates require physical custody. Physical dossiers must be maintained in designated institutional archives alongside digital registry references.
 </p>
 </div>
 </section>

 <!-- Section 5 -->
 <section class="space-y-3">
 <h2 class="text-base sm:text-lg font-bold text-ink-secondary flex items-center gap-2">
 <span class="w-6 h-6 rounded-md bg-primary text-white flex items-center justify-center text-xs font-bold">5</span>
 System Auditability &amp; Regulatory Compliance
 </h2>
 <p class="text-ink-muted">
 All interactions with WU-DMS are subject to continuous, automated audit logging. The university reserves the right to review audit trails, session histories, and IP telemetry during internal compliance investigations, financial audits, or quality assurance reviews conducted by the Ministry of Education or HERQA.
 </p>
 </section>

 <!-- Section 6 -->
 <section class="space-y-3">
 <h2 class="text-base sm:text-lg font-bold text-ink-secondary flex items-center gap-2">
 <span class="w-6 h-6 rounded-md bg-primary text-white flex items-center justify-center text-xs font-bold">6</span>
 Disciplinary Action &amp; Penalties
 </h2>
 <p class="text-ink-muted">
 Violations of these terms, including unauthorized access attempts, disclosure of confidential university records, deliberate disruption of correspondence workflows, or misuse of administrative privileges, shall be subject to disciplinary penalties under the Wollo University Senate Code of Conduct, account termination, and referral to relevant judicial authorities where applicable.
 </p>
 </section>
 </div>
 </div>
 </main>

 <PublicFooter />
 </div>
</template>

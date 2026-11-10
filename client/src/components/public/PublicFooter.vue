<script setup lang="ts">
import { Building2, ShieldCheck, FileText, ExternalLink } from 'lucide-vue-next'
</script>

<template>
 <footer class="bg-[#191919] text-[#f7f6f3] border-t border-[#2e2e2e] transition-colors">
 <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
 <div class="grid grid-cols-1 md:grid-cols-4 gap-8 lg:gap-12">
 <!-- Col 1: Institutional Identity -->
 <div class="space-y-4 md:col-span-2">
 <div class="flex items-center gap-3">
 <div class="w-10 h-10 rounded-lg bg-primary text-white flex items-center justify-center font-bold text-base shadow-xs shrink-0">
 WU
 </div>
 <div>
 <h2 class="text-base font-bold text-white tracking-tight">
 Wollo University
 </h2>
 <p class="text-xs text-[#9b9a97]">
 Kombolcha Institute of Technology (KIoT) &bull; Dessie &amp; Kombolcha Campuses
 </p>
 </div>
 </div>

 <p class="text-xs text-[#9b9a97] leading-relaxed max-w-md">
 Web-based Integrated Document Repository, Digital Archive, and Inter-Office Communication Management System. Providing secure document preservation, audited correspondence routing, and verifiable accreditation compliance for higher education administration.
 </p>

 <div class="text-[11px] text-ink-muted space-y-1">
 <p>Federal Democratic Republic of Ethiopia &bull; Ministry of Education</p>
 <p>Categorized as an Applied University under national higher education reform.</p>
 </div>
 </div>

 <!-- Col 2: Institutional Modules -->
 <div class="space-y-3">
 <h3 class="text-xs font-bold uppercase tracking-wider text-white">
 Institutional Modules
 </h3>
 <ul class="space-y-2 text-xs text-[#9b9a97]">
 <li>
 <a href="/#modules" class="hover:text-[#62aef0] transition-colors">Document Repository</a>
 </li>
 <li>
 <a href="/#modules" class="hover:text-[#62aef0] transition-colors">Correspondence Management</a>
 </li>
 <li>
 <a href="/#modules" class="hover:text-[#62aef0] transition-colors">Inter-Office Routing</a>
 </li>
 <li>
 <a href="/#modules" class="hover:text-[#62aef0] transition-colors">Digital Archive &amp; Retention</a>
 </li>
 <li>
 <a href="/#accreditation" class="hover:text-[#62aef0] transition-colors">Accreditation Evidence (HERQA)</a>
 </li>
 <li>
 <a href="/#governance" class="hover:text-[#62aef0] transition-colors">Scoped Role-Based Access</a>
 </li>
 </ul>
 </div>

 <!-- Col 3: Compliance & Legal -->
 <div class="space-y-3">
 <h3 class="text-xs font-bold uppercase tracking-wider text-white">
 Governance &amp; Policy
 </h3>
 <ul class="space-y-2 text-xs text-[#9b9a97]">
 <li>
 <router-link to="/privacy-policy" class="hover:text-[#62aef0] transition-colors">
 Privacy Policy
 </router-link>
 </li>
 <li>
 <router-link to="/terms" class="hover:text-[#62aef0] transition-colors">
 Terms &amp; Conditions
 </router-link>
 </li>
 <li>
 <router-link to="/login" class="hover:text-[#62aef0] transition-colors">
 Staff Authentication
 </router-link>
 </li>
 <li>
 <a href="mailto:ict.support@wu.edu.et" class="hover:text-[#62aef0] transition-colors flex items-center gap-1">
 <span>ICT Directorate Support</span>
 </a>
 </li>
 </ul>
 </div>
 </div>

 <!-- Bottom Bar -->
 <div class="mt-12 pt-8 border-t border-[#2e2e2e] flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-ink-muted">
 <p>
 &copy; {{ new Date().getFullYear() }} Wollo University. All administrative and operational rights reserved.
 </p>
 <p class="font-mono text-[11px]">
 WU-DMS Architecture &bull; 118 Normalized Tables &bull; Strict Scoped RBAC
 </p>
 </div>
 </div>
 </footer>
</template>

<script setup lang="ts">
import { Building2, ShieldCheck, FileText, ExternalLink } from 'lucide-vue-next'
</script>

<template>
  <footer class="bg-[#141414] text-[#f7f6f3] border-t border-[#2e2e2e] transition-colors">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-8 lg:gap-12">
        <!-- Col 1: Institutional Identity -->
        <div class="space-y-3.5 md:col-span-2">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-primary text-white flex items-center justify-center font-bold text-sm shadow-2xs shrink-0">
              WU
            </div>
            <div>
              <h2 class="text-base font-bold text-white tracking-tight leading-tight">
                Wollo University
              </h2>
              <p class="text-xs text-slate-400 leading-tight">
                Kombolcha Institute of Technology (KIoT) &bull; Dessie &amp; Kombolcha Campuses
              </p>
            </div>
          </div>

          <p class="text-xs text-slate-400 leading-relaxed max-w-md">
            Web-based Integrated Document Repository, Digital Archive, and Inter-Office Communication Management System. Providing secure document preservation, audited correspondence routing, and verifiable accreditation compliance for higher education administration.
          </p>

          <div class="text-xs text-slate-400 space-y-0.5">
            <p>Federal Democratic Republic of Ethiopia &bull; Ministry of Education</p>
            <p>Categorized as an Applied University under national higher education reform.</p>
          </div>
        </div>

        <!-- Col 2: Institutional Modules -->
        <div class="space-y-3">
          <h3 class="text-xs font-bold uppercase text-white">
            Institutional Modules
          </h3>
          <ul class="space-y-2 text-xs text-slate-400">
            <li>
              <a href="/#modules" class="hover:text-primary transition-colors">Document Repository</a>
            </li>
            <li>
              <a href="/#modules" class="hover:text-primary transition-colors">Correspondence Registry</a>
            </li>
            <li>
              <a href="/#modules" class="hover:text-primary transition-colors">Inter-Office Movement</a>
            </li>
            <li>
              <a href="/#modules" class="hover:text-primary transition-colors">Digital Archive &amp; Retention</a>
            </li>
            <li>
              <a href="/#accreditation" class="hover:text-primary transition-colors">Accreditation Evidence (HERQA)</a>
            </li>
            <li>
              <a href="/#governance" class="hover:text-primary transition-colors">Scoped Role-Based Access</a>
            </li>
          </ul>
        </div>

        <!-- Col 3: Compliance & Legal -->
        <div class="space-y-3">
          <h3 class="text-xs font-bold uppercase text-white">
            Governance &amp; Policy
          </h3>
          <ul class="space-y-2 text-xs text-slate-400">
            <li>
              <router-link to="/privacy-policy" class="hover:text-primary transition-colors">
                Privacy &amp; Data Protection
              </router-link>
            </li>
            <li>
              <router-link to="/terms" class="hover:text-primary transition-colors">
                Terms of Use &amp; Service
              </router-link>
            </li>
            <li>
              <router-link to="/login" class="hover:text-primary transition-colors">
                Staff Authentication Portal
              </router-link>
            </li>
            <li>
              <router-link to="/terms" class="hover:text-primary transition-colors">
                Institutional SLA &amp; Support
              </router-link>
            </li>
          </ul>
        </div>
      </div>

      <!-- Bottom Bar -->
      <div class="mt-10 pt-6 border-t border-[#262626] flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-400">
        <p>
          &copy; {{ new Date().getFullYear() }} Wollo University. All administrative and operational rights reserved.
        </p>
        <p class="font-mono text-xs text-slate-400">
          WU-DMS Architecture &bull; 118 Normalized Tables &bull; Strict Scoped RBAC
        </p>
      </div>
    </div>
  </footer>
</template>

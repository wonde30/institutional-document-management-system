<script setup lang="ts">
import { computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { Building2, ShieldCheck, Mail, MapPin, ExternalLink } from 'lucide-vue-next'

const router = useRouter()
const route = useRoute()
const currentYear = computed(() => new Date().getFullYear())

function navigateAnchor(anchorId: string) {
  if (route.path !== '/') {
    router.push({ path: '/', hash: `#${anchorId}` })
  } else {
    document.getElementById(anchorId)?.scrollIntoView({ behavior: 'smooth' })
  }
}
</script>

<template>
  <footer
    class="bg-[#141414] text-[#f7f6f3] border-t border-[#2e2e2e] transition-colors"
    role="contentinfo"
    aria-label="Institutional Footer"
  >
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-8 lg:gap-12">
        <!-- Col 1: Institutional Identity (Spans 2 columns) -->
        <div class="space-y-4 md:col-span-2">
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 rounded-lg bg-primary text-white flex items-center justify-center font-extrabold text-base shrink-0 shadow-sm"
            >
              WU
            </div>
            <div>
              <h2 class="text-base font-bold text-white tracking-tight leading-tight">
                Wollo University &bull; ወሎ ዩኒቨርሲቲ
              </h2>
              <p class="text-xs text-slate-400 leading-tight">
                Kombolcha Institute of Technology (KIoT) &bull; Dessie &amp; Kombolcha Campuses
              </p>
            </div>
          </div>

          <p class="text-xs text-slate-400 leading-relaxed max-w-md">
            Integrated Document Repository, Digital Archive, and Inter-Office Communication Management System. Providing secure document preservation, audited correspondence routing, and verifiable accreditation compliance for higher education administration.
          </p>

          <div class="text-xs text-slate-400 space-y-1 pt-1">
            <p class="flex items-center gap-1.5">
              <MapPin class="w-3.5 h-3.5 text-primary shrink-0" />
              <span>Federal Democratic Republic of Ethiopia &bull; Ministry of Education</span>
            </p>
            <p class="text-[11px] text-slate-500 pl-5">
              Categorized as an Applied University under national higher education reform.
            </p>
          </div>
        </div>

        <!-- Col 2: Institutional Modules -->
        <div class="space-y-3">
          <h3 class="text-xs font-bold text-white uppercase tracking-wider">
            Core Modules
          </h3>
          <ul class="space-y-2 text-xs text-slate-400">
            <li>
              <a
                href="#modules"
                class="hover:text-primary transition-colors cursor-pointer"
                @click.prevent="navigateAnchor('modules')"
              >
                Document Repository
              </a>
            </li>
            <li>
              <a
                href="#modules"
                class="hover:text-primary transition-colors cursor-pointer"
                @click.prevent="navigateAnchor('modules')"
              >
                Correspondence Registry
              </a>
            </li>
            <li>
              <a
                href="#how-it-works"
                class="hover:text-primary transition-colors cursor-pointer"
                @click.prevent="navigateAnchor('how-it-works')"
              >
                Inter-Office Movement
              </a>
            </li>
            <li>
              <a
                href="#modules"
                class="hover:text-primary transition-colors cursor-pointer"
                @click.prevent="navigateAnchor('modules')"
              >
                Digital Archive &amp; Retention
              </a>
            </li>
            <li>
              <a
                href="#accreditation"
                class="hover:text-primary transition-colors cursor-pointer"
                @click.prevent="navigateAnchor('accreditation')"
              >
                Accreditation Evidence (HERQA)
              </a>
            </li>
            <li>
              <a
                href="#governance"
                class="hover:text-primary transition-colors cursor-pointer"
                @click.prevent="navigateAnchor('governance')"
              >
                Scoped Role-Based Access
              </a>
            </li>
          </ul>
        </div>

        <!-- Col 3: Compliance & Legal -->
        <div class="space-y-3">
          <h3 class="text-xs font-bold text-white uppercase tracking-wider">
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

          <div class="pt-2 text-xs text-slate-500">
            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 text-[11px] font-medium">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
              Operational &bull; 99.9% Uptime
            </span>
          </div>
        </div>
      </div>

      <!-- Bottom Bar -->
      <div
        class="mt-12 pt-6 border-t border-[#262626] flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-400"
      >
        <p>
          &copy; {{ currentYear }} Wollo University. All administrative and operational rights reserved.
        </p>
        <p class="text-xs text-slate-500 font-mono">
          WU-DMS Enterprise &bull; Scoped Dynamic RBAC &bull; HERQA Aligned
        </p>
      </div>
    </div>
  </footer>
</template>

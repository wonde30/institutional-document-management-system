<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import BaseButton from '@/components/ui/BaseButton.vue'
import LiveProductPreview from '@/components/landing/LiveProductPreview.vue'
import type { LandingSummary, LandingAccreditation } from '@/types'
import { Building2, ArrowRight, Layers, ShieldCheck, ChevronDown } from 'lucide-vue-next'

const props = defineProps<{
  summary: LandingSummary | null
  accreditation: LandingAccreditation | null
  isLoading?: boolean
}>()

const router = useRouter()
const authStore = useAuthStore()

function handlePrimaryCta() {
  if (authStore.isAuthenticated) {
    router.push('/dashboard')
  } else {
    router.push('/login')
  }
}

function scrollToModules() {
  const el = document.getElementById('modules')
  if (el) {
    el.scrollIntoView({ behavior: 'smooth' })
  }
}
</script>

<template>
  <section
    id="overview"
    class="relative py-12 sm:py-16 lg:py-20 overflow-hidden border-b border-hairline bg-surface transition-colors"
    aria-labelledby="hero-heading"
  >
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-8 items-center">
        <!-- Left Column: Copy & Actions -->
        <div class="lg:col-span-7 space-y-5 sm:space-y-6">
          <!-- Institutional Tag -->
          <div
            class="inline-flex items-center gap-2 px-3 py-1 rounded-md text-xs font-semibold bg-primary-subtle text-primary border border-primary/20 shadow-xs"
          >
            <Building2 class="w-3.5 h-3.5 shrink-0" />
            <span>Wollo University &bull; Kombolcha Institute of Technology (KIoT)</span>
          </div>

          <h1
            id="hero-heading"
            class="text-3xl sm:text-4xl lg:text-[42px] font-extrabold tracking-tight text-ink leading-tight sm:leading-[1.18]"
          >
            Integrated Institutional Repository, Digital Archive &amp; Inter-Office Communication
          </h1>

          <p class="text-xs sm:text-sm text-ink-muted leading-relaxed max-w-2xl">
            Designed specifically for Wollo University's academic directorates, colleges, and administrative offices. Centralizing institutional document registration, audited correspondence routing, long-term digital preservation, and verifiable accreditation evidence within a single, secure environment.
          </p>

          <!-- Call to Actions -->
          <div class="pt-1 flex flex-wrap items-center gap-3">
            <BaseButton
              variant="primary"
              size="md"
              class="text-xs sm:text-sm font-semibold py-2.5 px-5 sm:px-6 rounded-lg shadow-sm hover:shadow transition-all"
              @click="handlePrimaryCta"
            >
              <span>{{ authStore.isAuthenticated ? 'Open Workspace' : 'Sign In to Workspace' }}</span>
              <ArrowRight class="w-4 h-4 ml-1.5" />
            </BaseButton>

            <a
              href="#modules"
              class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-hairline text-xs sm:text-sm font-semibold text-ink-secondary hover:bg-surface-hover hover:text-ink transition-colors cursor-pointer"
              @click.prevent="scrollToModules"
            >
              <Layers class="w-4 h-4 text-primary" />
              <span>Explore Core Modules</span>
              <ChevronDown class="w-3.5 h-3.5 ml-0.5 text-ink-muted" />
            </a>
          </div>

          <!-- Institutional Trust Highlights -->
          <div class="pt-4 border-t border-hairline flex flex-wrap items-center gap-6 text-xs text-ink-muted font-medium">
            <span class="flex items-center gap-1.5">
              <ShieldCheck class="w-4 h-4 text-emerald-500" />
              Multi-Unit Scoped RBAC
            </span>
            <span class="flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-primary" />
              100% Audited Telemetry
            </span>
            <span class="flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500" />
              National HERQA Aligned
            </span>
          </div>
        </div>

        <!-- Right Column: Living Product Preview -->
        <div class="lg:col-span-5 w-full">
          <LiveProductPreview
            :summary="summary"
            :accreditation="accreditation"
            :is-loading="isLoading"
          />
        </div>
      </div>
    </div>
  </section>
</template>

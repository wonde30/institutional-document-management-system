<script setup lang="ts">
import { onMounted, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { useLandingData } from '@/composables/useLandingData'
import PublicNavbar from '@/components/public/PublicNavbar.vue'
import PublicFooter from '@/components/public/PublicFooter.vue'
import HeroSection from '@/components/landing/HeroSection.vue'
import StatsStrip from '@/components/landing/StatsStrip.vue'
import CoreModulesSection from '@/components/landing/CoreModulesSection.vue'
import HowItWorksSection from '@/components/landing/HowItWorksSection.vue'
import AccreditationSection from '@/components/landing/AccreditationSection.vue'
import GovernanceSecuritySection from '@/components/landing/GovernanceSecuritySection.vue'
import StakeholdersSection from '@/components/landing/StakeholdersSection.vue'
import AnnouncementsSection from '@/components/landing/AnnouncementsSection.vue'
import CtaSection from '@/components/landing/CtaSection.vue'

const route = useRoute()
const {
  summary,
  modules,
  accreditation,
  announcements,
  isLoading,
  isSummaryLoading,
  isModulesLoading,
  isAccreditationLoading,
  isAnnouncementsLoading,
  error,
  fetchAll,
} = useLandingData()

onMounted(async () => {
  await fetchAll()

  // Handle hash deep-linking after data render
  if (route.hash) {
    nextTick(() => {
      const targetId = route.hash.replace('#', '')
      const el = document.getElementById(targetId)
      if (el) {
        el.scrollIntoView({ behavior: 'smooth' })
      }
    })
  }
})
</script>

<template>
  <div
    class="min-h-screen flex flex-col bg-canvas text-ink transition-colors selection:bg-primary selection:text-white"
  >
    <!-- Sticky Blur Navbar -->
    <PublicNavbar />

    <!-- Main Content Container with Semantic Sections -->
    <main id="main-content" class="flex-1 focus:outline-hidden" tabindex="-1">
      <!-- 1. Hero with Live Product Preview (id="overview") -->
      <HeroSection
        :summary="summary"
        :accreditation="accreditation"
        :is-loading="isLoading"
      />

      <!-- 2. Live Stats Strip with Count-Up -->
      <StatsStrip
        :summary="summary"
        :is-loading="isSummaryLoading"
        :error="error"
        @retry="fetchAll(true)"
      />

      <!-- 3. Core Functional Modules Grid (id="modules") -->
      <CoreModulesSection
        :modules="modules"
        :is-loading="isModulesLoading"
      />

      <!-- 4. How It Works (Register -> Route -> Archive -> Verify) (id="how-it-works") -->
      <HowItWorksSection />

      <!-- 5. Accreditation & Quality Assurance (id="accreditation") -->
      <AccreditationSection
        :accreditation="accreditation"
        :is-loading="isAccreditationLoading"
      />

      <!-- 6. Information Security & Governance (id="governance") -->
      <GovernanceSecuritySection />

      <!-- 7. University Stakeholders & Campus Roles (id="stakeholders") -->
      <StakeholdersSection />

      <!-- 8. Public Administrative Announcements (id="announcements") -->
      <AnnouncementsSection
        :announcements="announcements"
        :is-loading="isAnnouncementsLoading"
      />

      <!-- 9. Final Institutional Call to Action (id="access") -->
      <CtaSection />
    </main>

    <!-- Institutional Footer -->
    <PublicFooter />
  </div>
</template>

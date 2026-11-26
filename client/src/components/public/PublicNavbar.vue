<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useThemeStore } from '@/stores/theme'
import BaseButton from '@/components/ui/BaseButton.vue'
import {
  ArrowRight,
  Sun,
  Moon,
  Laptop,
  Menu,
  X,
  Lock,
} from 'lucide-vue-next'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const themeStore = useThemeStore()

const mobileMenuOpen = ref(false)
const isScrolled = ref(false)
const activeSection = ref<string>('overview')

// Navigation items definition
const navItems = [
  { id: 'overview', label: 'Overview' },
  { id: 'modules', label: 'Core Modules' },
  { id: 'how-it-works', label: 'How It Works' },
  { id: 'accreditation', label: 'Accreditation' },
  { id: 'governance', label: 'Governance' },
]

// Theme button label & tooltip
const themeTooltip = computed(() => {
  if (themeStore.mode === 'light') return 'Theme: Light (click for Dark)'
  if (themeStore.mode === 'dark') return 'Theme: Dark (click for System)'
  return 'Theme: System OS (click for Light)'
})

const themeAriaLabel = computed(() => {
  return `Toggle theme. Current theme is ${themeStore.mode}.`
})

function handleScroll() {
  if (typeof window === 'undefined') return
  isScrolled.value = window.scrollY > 12
}

let observer: IntersectionObserver | null = null

function setupScrollspy() {
  if (typeof window === 'undefined' || typeof IntersectionObserver === 'undefined') return

  observer?.disconnect()

  observer = new IntersectionObserver(
    (entries) => {
      // Find the first intersecting entry that is visible
      const visible = entries.find((e) => e.isIntersecting)
      if (visible && visible.target.id) {
        activeSection.value = visible.target.id
      }
    },
    {
      rootMargin: '-80px 0px -60% 0px',
      threshold: 0.1,
    }
  )

  const sectionIds = ['overview', 'modules', 'how-it-works', 'accreditation', 'governance']
  for (const id of sectionIds) {
    const el = document.getElementById(id)
    if (el) observer.observe(el)
  }
}

function scrollToSection(sectionId: string) {
  mobileMenuOpen.value = false
  if (route.path !== '/') {
    router.push({ path: '/', hash: `#${sectionId}` }).then(() => {
      nextTick(() => {
        const el = document.getElementById(sectionId)
        el?.scrollIntoView({ behavior: 'smooth' })
        activeSection.value = sectionId
      })
    })
    return
  }

  const el = document.getElementById(sectionId)
  if (el) {
    el.scrollIntoView({ behavior: 'smooth' })
    activeSection.value = sectionId
    // update URL without full reload
    if (typeof history !== 'undefined') {
      history.replaceState(null, '', `#${sectionId}`)
    }
  }
}

function handleKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && mobileMenuOpen.value) {
    mobileMenuOpen.value = false
  }
}

function handleAuthAction() {
  if (authStore.isAuthenticated) {
    router.push('/dashboard')
  } else {
    router.push('/login')
  }
}

onMounted(() => {
  themeStore.init()
  if (typeof window !== 'undefined') {
    window.addEventListener('scroll', handleScroll, { passive: true })
    window.addEventListener('keydown', handleKeydown)
    handleScroll()
    nextTick(() => {
      setupScrollspy()
      // Check initial hash
      if (route.hash) {
        const targetId = route.hash.replace('#', '')
        scrollToSection(targetId)
      }
    })
  }
})

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('scroll', handleScroll)
    window.removeEventListener('keydown', handleKeydown)
  }
  observer?.disconnect()
})
</script>

<template>
  <!-- Skip to Main Content Link for Keyboard / Screen Readers -->
  <a
    href="#overview"
    class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:px-4 focus:py-2 focus:bg-primary focus:text-white focus:rounded-lg focus:shadow-xl focus:text-xs focus:font-bold focus:outline-hidden"
  >
    Skip to main content
  </a>

  <header
    :class="[
      'sticky top-0 z-50 transition-all duration-200 border-b',
      isScrolled
        ? 'bg-surface/95 dark:bg-[#181818]/95 backdrop-blur-md border-hairline shadow-sm'
        : 'bg-surface/90 dark:bg-[#141414]/90 backdrop-blur-xs border-hairline/80 shadow-none'
    ]"
  >
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-14 sm:h-16">
        <!-- University Crest & Title Brand -->
        <router-link to="/" class="flex items-center gap-3 group focus:outline-hidden focus:ring-2 focus:ring-primary rounded-lg p-1">
          <div
            class="w-9 h-9 sm:w-10 sm:h-10 rounded-lg bg-primary text-white flex items-center justify-center font-extrabold text-sm sm:text-base group-hover:bg-primary-hover transition-colors shrink-0 shadow-xs"
          >
            WU
          </div>
          <div class="flex flex-col">
            <span class="text-sm sm:text-base font-bold tracking-tight text-ink group-hover:text-primary transition-colors leading-tight">
              Wollo University
            </span>
            <span class="text-[11px] font-medium text-ink-muted leading-tight">
              Document Management Portal &bull; KIoT
            </span>
          </div>
        </router-link>

        <!-- Desktop Navigation Links with Scrollspy Highlighting -->
        <nav
          class="hidden md:flex items-center gap-1 lg:gap-2 text-xs font-semibold text-ink-muted"
          aria-label="Public Portal Navigation"
        >
          <a
            v-for="item in navItems"
            :key="item.id"
            :href="`#${item.id}`"
            :class="[
              'px-3 py-1.5 rounded-md transition-colors relative cursor-pointer',
              activeSection === item.id
                ? 'text-primary font-bold bg-primary-subtle'
                : 'hover:text-ink hover:bg-surface-hover'
            ]"
            :aria-current="activeSection === item.id ? 'location' : undefined"
            @click.prevent="scrollToSection(item.id)"
          >
            {{ item.label }}
          </a>

          <!-- Static Legal Links -->
          <router-link
            to="/privacy-policy"
            class="px-3 py-1.5 rounded-md transition-colors hover:text-ink hover:bg-surface-hover"
            :class="{ 'text-primary font-bold bg-primary-subtle': route.path === '/privacy-policy' || route.path === '/privacy' }"
          >
            Privacy
          </router-link>

          <router-link
            to="/terms"
            class="px-3 py-1.5 rounded-md transition-colors hover:text-ink hover:bg-surface-hover"
            :class="{ 'text-primary font-bold bg-primary-subtle': route.path === '/terms' }"
          >
            Terms
          </router-link>
        </nav>

        <!-- Right Side Header Actions -->
        <div class="flex items-center gap-2 sm:gap-3">
          <!-- Theme Toggle with Tooltip & Aria Label -->
          <button
            type="button"
            class="p-2 rounded-lg text-ink-muted hover:bg-surface-hover hover:text-ink transition-colors cursor-pointer focus:outline-hidden focus:ring-2 focus:ring-primary/50"
            :title="themeTooltip"
            :aria-label="themeAriaLabel"
            @click="themeStore.toggle"
          >
            <Sun v-if="themeStore.mode === 'light'" class="w-4 h-4 text-amber-500" />
            <Moon v-else-if="themeStore.mode === 'dark'" class="w-4 h-4 text-primary" />
            <Laptop v-else class="w-4 h-4 text-ink-muted" />
          </button>

          <!-- Auth Entry Point -->
          <template v-if="authStore.isAuthenticated">
            <BaseButton
              variant="primary"
              size="sm"
              class="text-xs font-semibold py-1.5 px-3.5 rounded-lg shadow-xs"
              @click="handleAuthAction"
            >
              <span>Open Workspace</span>
              <ArrowRight class="w-3.5 h-3.5 ml-1" />
            </BaseButton>
          </template>

          <template v-else>
            <BaseButton
              variant="outline"
              size="sm"
              class="text-xs font-semibold py-1.5 px-3.5 rounded-lg border-hairline hover:border-primary/40 text-ink"
              @click="handleAuthAction"
            >
              <Lock class="w-3.5 h-3.5 mr-1 text-primary" />
              <span>Sign In</span>
            </BaseButton>
          </template>

          <!-- Mobile Menu Hamburger -->
          <button
            type="button"
            class="md:hidden p-2 rounded-lg text-ink-muted hover:bg-surface-hover hover:text-ink transition-colors cursor-pointer focus:outline-hidden focus:ring-2 focus:ring-primary"
            :aria-expanded="mobileMenuOpen"
            aria-controls="mobile-navigation-menu"
            aria-label="Toggle mobile menu"
            @click="mobileMenuOpen = !mobileMenuOpen"
          >
            <X v-if="mobileMenuOpen" class="w-5 h-5" />
            <Menu v-else class="w-5 h-5" />
          </button>
        </div>
      </div>
    </div>

    <!-- Mobile Drawer Menu with Focus Management -->
    <div
      v-if="mobileMenuOpen"
      id="mobile-navigation-menu"
      class="md:hidden border-t border-hairline bg-surface/98 dark:bg-[#181818]/98 backdrop-blur-md px-4 py-4 space-y-1.5 text-xs font-semibold text-ink shadow-xl animate-in slide-in-from-top-2 duration-150"
      role="dialog"
      aria-modal="true"
      aria-label="Mobile Navigation"
    >
      <a
        v-for="item in navItems"
        :key="item.id"
        :href="`#${item.id}`"
        :class="[
          'block py-2.5 px-3 rounded-lg transition-colors',
          activeSection === item.id ? 'bg-primary-subtle text-primary font-bold' : 'hover:bg-surface-hover text-ink-secondary'
        ]"
        @click.prevent="scrollToSection(item.id)"
      >
        {{ item.label }}
      </a>

      <div class="pt-2 border-t border-hairline space-y-1.5">
        <router-link
          to="/privacy-policy"
          class="block py-2.5 px-3 rounded-lg hover:bg-surface-hover text-ink-secondary transition-colors"
          @click="mobileMenuOpen = false"
        >
          Privacy Policy
        </router-link>

        <router-link
          to="/terms"
          class="block py-2.5 px-3 rounded-lg hover:bg-surface-hover text-ink-secondary transition-colors"
          @click="mobileMenuOpen = false"
        >
          Terms &amp; Conditions
        </router-link>
      </div>

      <div class="pt-3 border-t border-hairline">
        <BaseButton
          variant="primary"
          size="md"
          class="w-full text-xs font-semibold py-2.5 rounded-lg justify-center"
          @click="handleAuthAction"
        >
          <span>{{ authStore.isAuthenticated ? 'Open Workspace' : 'Sign In to Workspace' }}</span>
          <ArrowRight class="w-3.5 h-3.5 ml-1.5" />
        </BaseButton>
      </div>
    </div>
  </header>
</template>

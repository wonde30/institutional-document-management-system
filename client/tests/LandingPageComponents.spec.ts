import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import StatsStrip from '@/components/landing/StatsStrip.vue'
import LiveProductPreview from '@/components/landing/LiveProductPreview.vue'
import PublicNavbar from '@/components/public/PublicNavbar.vue'
import { useAuthStore } from '@/stores/auth'
import { useThemeStore } from '@/stores/theme'
import type { LandingSummary, LandingAccreditation } from '@/types'

describe('Landing Page Modular Components', () => {
  let router: any

  const mockSummary: LandingSummary = {
    published_documents: 42,
    active_offices: 9,
    correspondence_routed: 15,
    accreditation_evidence_items: 8,
    metrics_updated_at: '2026-11-22T20:00:00Z',
  }

  const mockAccreditation: LandingAccreditation = {
    framework: { name: 'Higher Education Relevance and Quality Agency (HERQA)', code: 'HERQA' },
    cycle: {
      name: 'HERQA Institutional Quality Audit Cycle (2026-2027)',
      starts_on: '2026-01-01',
      ends_on: '2027-12-31',
    },
    summary: {
      total_standards: 10,
      verified_standards: 6,
      in_review_standards: 2,
      total_evidence_items: 8,
      coverage_percentage: 70.0,
    },
    standards: [
      {
        code: 'HERQA-01',
        raw_code: 'HERQA_STD_01',
        title: 'Governance, Leadership and Institutional Autonomy',
        evidence_count: 2,
        status: 'verified',
        status_label: 'Verified',
      },
    ],
  }

  beforeEach(() => {
    setActivePinia(createPinia())
    router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', component: { template: '<div>Home</div>' } },
        { path: '/login', component: { template: '<div>Login</div>' } },
        { path: '/dashboard', component: { template: '<div>Dashboard</div>' } },
        { path: '/privacy-policy', component: { template: '<div>Privacy</div>' } },
        { path: '/terms', component: { template: '<div>Terms</div>' } },
      ],
    })
  })

  describe('StatsStrip Component', () => {
    it('renders live summary value cards when data is provided', () => {
      const wrapper = mount(StatsStrip, {
        props: {
          summary: mockSummary,
          isLoading: false,
          error: null,
        },
      })

      expect(wrapper.text()).toContain('Preserved Institutional Records')
      expect(wrapper.text()).toContain('Campus Offices & Directorates')
      expect(wrapper.text()).toContain('Inter-Office Dispatches Routed')
      expect(wrapper.text()).toContain('Accreditation Evidence Dossiers')
    })

    it('shows pulse skeleton loaders when loading without data', () => {
      const wrapper = mount(StatsStrip, {
        props: {
          summary: null,
          isLoading: true,
          error: null,
        },
      })

      const skeletons = wrapper.findAll('.animate-pulse')
      expect(skeletons.length).toBeGreaterThan(0)
    })

    it('displays error recovery banner and emits retry when failed', async () => {
      const wrapper = mount(StatsStrip, {
        props: {
          summary: null,
          isLoading: false,
          error: 'Connection timeout',
        },
      })

      expect(wrapper.text()).toContain('Connection timeout')
      const retryButton = wrapper.find('button')
      expect(retryButton.exists()).toBe(true)

      await retryButton.trigger('click')
      expect(wrapper.emitted('retry')).toBeTruthy()
    })
  })

  describe('LiveProductPreview Component', () => {
    it('renders real mock dashboard with summary metrics rather than a dead image', () => {
      const wrapper = mount(LiveProductPreview, {
        props: {
          summary: mockSummary,
          accreditation: mockAccreditation,
          isLoading: false,
        },
      })

      expect(wrapper.find('img').exists()).toBe(false)
      expect(wrapper.text()).toContain('wu-dms.wollo.edu.et')
      expect(wrapper.text()).toContain('Live Node')
      expect(wrapper.text()).toContain('Administrative Directorate Portal')
      expect(wrapper.text()).toContain('Preserved Records')
      expect(wrapper.text()).toContain('42')
      expect(wrapper.text()).toContain('9')
      expect(wrapper.text()).toContain('15')
      expect(wrapper.text()).toContain('8 Dossiers')
      expect(wrapper.text()).toContain('70% Coverage')
      expect(wrapper.text()).toContain('Recent Institutional Dispatch Chain')
    })
  })

  describe('PublicNavbar Component', () => {
    it('renders institutional branding and navigation anchors', async () => {
      router.push('/')
      await router.isReady()

      const wrapper = mount(PublicNavbar, {
        global: {
          plugins: [router],
        },
      })

      expect(wrapper.text()).toContain('Wollo University')
      expect(wrapper.text()).toContain('Overview')
      expect(wrapper.text()).toContain('Core Modules')
      expect(wrapper.text()).toContain('How It Works')
      expect(wrapper.text()).toContain('Accreditation')
      expect(wrapper.text()).toContain('Governance')
      expect(wrapper.text()).toContain('Privacy')
      expect(wrapper.text()).toContain('Terms')
    })

    it('provides clear tooltip and accessible label for theme toggle', async () => {
      router.push('/')
      await router.isReady()

      const wrapper = mount(PublicNavbar, {
        global: {
          plugins: [router],
        },
      })

      const themeStore = useThemeStore()
      themeStore.setMode('light')
      await wrapper.vm.$nextTick()

      const themeButton = wrapper.find('button[aria-label^="Toggle theme"]')
      expect(themeButton.exists()).toBe(true)
      expect(themeButton.attributes('title')).toBe('Theme: Light (click for Dark)')
      expect(themeButton.attributes('aria-label')).toBe('Toggle theme. Current theme is light.')

      // Toggle to dark
      await themeButton.trigger('click')
      expect(themeStore.mode).toBe('dark')
      await wrapper.vm.$nextTick()
      expect(themeButton.attributes('title')).toBe('Theme: Dark (click for System)')
    })

    it('displays Sign In when guest, and Open Workspace when authenticated', async () => {
      router.push('/')
      await router.isReady()

      const authStore = useAuthStore()
      authStore.user = null

      const wrapper = mount(PublicNavbar, {
        global: {
          plugins: [router],
        },
      })

      // Guest state
      expect(wrapper.text()).toContain('Sign In')
      expect(wrapper.text()).not.toContain('Open Workspace')

      // Authenticated state
      authStore.user = {
        id: 1,
        name: 'Dean Tech',
        email: 'dean@wu.edu.et',
        status: 'active',
        roles: [{ code: 'dean', name: 'Dean' }],
        permissions: ['documents.view'],
      } as any
      await wrapper.vm.$nextTick()

      expect(wrapper.text()).toContain('Open Workspace')
      expect(wrapper.text()).not.toContain('Sign In')
    })
  })
})

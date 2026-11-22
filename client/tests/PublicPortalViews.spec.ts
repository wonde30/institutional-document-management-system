import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import LandingPageView from '@/views/public/LandingPageView.vue'
import PrivacyPolicyView from '@/views/public/PrivacyPolicyView.vue'
import TermsConditionsView from '@/views/public/TermsConditionsView.vue'
import { landingApi } from '@/api/landing'
import type { LandingSummary, LandingModule, LandingAccreditation, LandingAnnouncement } from '@/types'

describe('Public Institutional Portal Views', () => {
  let router: any

  const mockSummary: LandingSummary = {
    published_documents: 42,
    active_offices: 9,
    correspondence_routed: 15,
    accreditation_evidence_items: 8,
    metrics_updated_at: '2026-11-22T20:00:00Z',
  }

  const mockModules: LandingModule[] = [
    {
      key: 'repository',
      title: 'Document Repository',
      description: 'Centralized storage with deterministic reference sequences.',
      icon: 'FileText',
      route: '/documents',
      order: 1,
      highlights: ['Deterministic numbering', 'Categorical metadata'],
    },
    {
      key: 'correspondence',
      title: 'Correspondence Registry',
      description: 'Incoming letters and outgoing dispatch records.',
      icon: 'Send',
      route: '/correspondence',
      order: 2,
      highlights: ['External reference tracking'],
    },
    {
      key: 'routing',
      title: 'Inter-Office Movement',
      description: 'Controlled office forwarding and routing receipts.',
      icon: 'Layers',
      route: '/routing',
      order: 3,
      highlights: ['Inter-unit movement'],
    },
    {
      key: 'archive',
      title: 'Archive & Retention',
      description: 'Structured transfer of inactive records.',
      icon: 'Archive',
      route: '/archive',
      order: 4,
      highlights: ['Statutory retention'],
    },
    {
      key: 'accreditation',
      title: 'Accreditation Evidence',
      description: 'HERQA national quality assurance standard mapping.',
      icon: 'Award',
      route: '/accreditation',
      order: 5,
      highlights: ['10 National standards'],
    },
    {
      key: 'signatures',
      title: 'Signatures & Seals',
      description: 'Cryptographic digital signatures and institutional stamps.',
      icon: 'FileCheck',
      route: '/signatures',
      order: 6,
      highlights: ['Cryptographic signing'],
    },
  ]

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

  const mockAnnouncements: LandingAnnouncement[] = [
    {
      id: 'ann-1',
      title: 'HERQA Institutional Audit Cycle 2026 Launched',
      summary: 'Quality assurance dossiers open for review.',
      category: 'Quality Assurance',
      published_at: '2026-11-15T08:00:00Z',
      badge: 'Active Audit',
      pinned: true,
    },
  ]

  beforeEach(() => {
    setActivePinia(createPinia())
    router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', name: 'landing', component: LandingPageView },
        { path: '/privacy-policy', name: 'public.privacy', component: PrivacyPolicyView },
        { path: '/terms', name: 'public.terms', component: TermsConditionsView },
        { path: '/login', name: 'login', component: { template: '<div>Login View</div>' } },
        { path: '/dashboard', name: 'dashboard', component: { template: '<div>Dashboard View</div>' } },
      ],
    })

    // Setup API mocks
    vi.spyOn(landingApi, 'getSummary').mockResolvedValue({
      data: { success: true, data: mockSummary },
    } as any)

    vi.spyOn(landingApi, 'getModules').mockResolvedValue({
      data: { success: true, data: mockModules },
    } as any)

    vi.spyOn(landingApi, 'getAccreditation').mockResolvedValue({
      data: { success: true, data: mockAccreditation },
    } as any)

    vi.spyOn(landingApi, 'getAnnouncements').mockResolvedValue({
      data: { success: true, data: mockAnnouncements },
    } as any)
  })

  it('renders LandingPageView with genuine institutional branding, live metrics, and core modules', async () => {
    router.push('/')
    await router.isReady()

    const wrapper = mount(LandingPageView, {
      global: {
        plugins: [router],
      },
    })

    // Wait for async fetchAll to settle
    await new Promise((r) => setTimeout(r, 20))
    await wrapper.vm.$nextTick()

    // Verify Title & University affiliation
    expect(wrapper.text()).toContain('Wollo University')
    expect(wrapper.text()).toContain('Kombolcha Institute of Technology')
    expect(wrapper.text()).toContain('Integrated Institutional Repository')

    // Verify User Value Metrics (live API data)
    expect(wrapper.text()).toContain('Preserved Institutional Records')
    expect(wrapper.text()).toContain('Campus Offices & Directorates')
    expect(wrapper.text()).toContain('Inter-Office Dispatches Routed')
    expect(wrapper.text()).toContain('Accreditation Evidence Dossiers')

    // Verify Core Modules from API
    expect(wrapper.text()).toContain('Document Repository')
    expect(wrapper.text()).toContain('Correspondence Registry')
    expect(wrapper.text()).toContain('Inter-Office Movement')
    expect(wrapper.text()).toContain('Archive & Retention')
    expect(wrapper.text()).toContain('Accreditation Evidence')
    expect(wrapper.text()).toContain('Signatures & Seals')

    // Verify How It Works 4-step flow
    expect(wrapper.text()).toContain('Register & Classify')
    expect(wrapper.text()).toContain('Route & Acknowledge')
    expect(wrapper.text()).toContain('Preserve & Archive')
    expect(wrapper.text()).toContain('Verify & Accredit')

    // Verify HERQA Accreditation section
    expect(wrapper.text()).toContain('HERQA-01')
    expect(wrapper.text()).toContain('Higher Education Relevance and Quality Agency')

    // Verify Governance & Stakeholder roles
    expect(wrapper.text()).toContain('Multi-Scope Information Assurance')
    expect(wrapper.text()).toContain('Scoped Dynamic RBAC')
    expect(wrapper.text()).toContain('University Management')
    expect(wrapper.text()).toContain('Academic Leadership')
    expect(wrapper.text()).toContain('Registrar & Records')
  })

  it('renders PrivacyPolicyView with institutional governance details and contact info', async () => {
    router.push('/privacy-policy')
    await router.isReady()

    const wrapper = mount(PrivacyPolicyView, {
      global: {
        plugins: [router],
      },
    })

    expect(wrapper.text()).toContain('Privacy & Data Protection Policy')
    expect(wrapper.text()).toContain('Wollo University Administrative and Document Management System')
    expect(wrapper.text()).toContain('Proclamation No. 1152/2019')
    expect(wrapper.text()).toContain('Role-Based Access Boundaries')
    expect(wrapper.text()).toContain('Stateful Cookies & Browser Security')
    expect(wrapper.text()).toContain('Information Technology Directorate')
    expect(wrapper.text()).toContain('Data Protection Officer')
    expect(wrapper.text()).not.toContain('ict.support@wu.edu.et')
  })

  it('renders TermsConditionsView with statutory preservation rules and signature validity', async () => {
    router.push('/terms')
    await router.isReady()

    const wrapper = mount(TermsConditionsView, {
      global: {
        plugins: [router],
      },
    })

    expect(wrapper.text()).toContain('Terms of Use & Administrative Service Agreement')
    expect(wrapper.text()).toContain('Authorized User Acceptance')
    expect(wrapper.text()).toContain('Account Security & Non-Transferability')
    expect(wrapper.text()).toContain('Proclamation No. 1072/2018')
    expect(wrapper.text()).toContain('Physical Originals Preservation Rule')
    expect(wrapper.text()).toContain('does not automatically replace or authorize the destruction of original paper records')
    expect(wrapper.text()).toContain('Disciplinary Action & Penalties')
  })
})

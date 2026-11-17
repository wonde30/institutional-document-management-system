import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import LandingPageView from '@/views/public/LandingPageView.vue'
import PrivacyPolicyView from '@/views/public/PrivacyPolicyView.vue'
import TermsConditionsView from '@/views/public/TermsConditionsView.vue'

describe('Public Institutional Portal Views', () => {
  let router: any

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
  })

  it('renders LandingPageView with genuine institutional branding and core modules', async () => {
    router.push('/')
    await router.isReady()

    const wrapper = mount(LandingPageView, {
      global: {
        plugins: [router],
      },
    })

    // Verify Title & University affiliation from PDF
    expect(wrapper.text()).toContain('Wollo University')
    expect(wrapper.text()).toContain('Kombolcha Institute of Technology')
    expect(wrapper.text()).toContain('Web-Based Integrated Document Repository')

    // Verify Technical Baseline Facts (Proposal & Normalized DB)
    expect(wrapper.text()).toContain('118')
    expect(wrapper.text()).toContain('Normalized Tables')
    expect(wrapper.text()).toContain('3NF')

    // Verify Core Modules from Proposal §1.4
    expect(wrapper.text()).toContain('Document Repository')
    expect(wrapper.text()).toContain('Correspondence Registry')
    expect(wrapper.text()).toContain('Inter-Office Movement')
    expect(wrapper.text()).toContain('Archive & Retention')
    expect(wrapper.text()).toContain('Accreditation Evidence')
    expect(wrapper.text()).toContain('Signatures & Seals')

    // Verify HERQA Accreditation section
    expect(wrapper.text()).toContain('HERQA-01')
    expect(wrapper.text()).toContain('Higher Education Relevance and Quality Agency')

    // Verify Institutional Roles from Proposal §1.7.2
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
    // The published contact route is described. No address is invented on a
    // page that states the institutional contact details.
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
    // Verification of statutory physical originals rule (Proposal §1.7.4)
    expect(wrapper.text()).toContain('Physical Originals Preservation Rule')
    expect(wrapper.text()).toContain('does not automatically replace or authorize the destruction of original paper records')
    expect(wrapper.text()).toContain('Disciplinary Action & Penalties')
  })
})

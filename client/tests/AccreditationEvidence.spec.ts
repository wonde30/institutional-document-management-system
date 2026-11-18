import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import { useAuthStore } from '@/stores/auth'
import router from '@/router'
import AccreditationEvidenceView from '@/views/accreditation/AccreditationEvidenceView.vue'
import { institutionalApi } from '@/api/client'
import type { User } from '@/types'

describe('Accreditation Evidence View & Navigation Security', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    vi.clearAllMocks()
  })

  it('redirects unauthenticated users attempting to access /accreditation to login', async () => {
    const auth = useAuthStore()
    auth.user = null
    auth.token = null

    await router.push('/accreditation')
    expect(router.currentRoute.value.name).toBe('login')
  })

  it('redirects users lacking accreditation.view permission to 403 forbidden', async () => {
    const auth = useAuthStore()
    const unauthorizedUser: User = {
      id: 55,
      name: 'Dr. No QA Access',
      email: 'noqa@wu.edu.et',
      roles: [{ id: 7, name: 'Staff' }],
      permissions: ['documents.view'], // Lacks 'accreditation.view'
      is_active: true,
    }

    auth.user = unauthorizedUser
    auth.token = 'valid-token'

    await router.push('/accreditation')
    expect(router.currentRoute.value.name).toBe('error.forbidden')
  })

  it('permits authorized QA personnel with accreditation.view to access /accreditation', async () => {
    const auth = useAuthStore()
    const qaOfficer: User = {
      id: 60,
      name: 'QA Director Kassaye',
      email: 'qa.director@wu.edu.et',
      roles: [{ id: 4, name: 'Quality Officer' }],
      permissions: ['accreditation.view', 'accreditation.submit_evidence', 'accreditation.review_evidence'],
      is_active: true,
    }

    auth.user = qaOfficer
    auth.token = 'valid-token'

    await router.push('/accreditation')
    expect(router.currentRoute.value.name).toBe('accreditation.evidence')
  })

  it('renders accreditation cockpit with real API data, metrics and action controls', async () => {
    const auth = useAuthStore()
    auth.user = {
      id: 60,
      name: 'QA Director Kassaye',
      email: 'qa.director@wu.edu.et',
      roles: [{ id: 4, name: 'Quality Officer' }],
      permissions: ['accreditation.view', 'accreditation.submit_evidence', 'accreditation.review_evidence', 'accreditation.export'],
      is_active: true,
    }
    auth.token = 'valid-token'

    // Mock API responses
    vi.spyOn(institutionalApi, 'accreditationCycles').mockResolvedValue({
      data: {
        success: true,
        data: [
          {
            id: 1,
            framework_version_id: 1,
            unit_id: 2,
            name: '2026 Institutional QA Cycle',
            starts_on: '2026-01-01',
            ends_on: '2026-12-31',
          },
        ],
      },
    } as any)

    vi.spyOn(institutionalApi, 'accreditationStandards').mockResolvedValue({
      data: {
        success: true,
        data: [
          {
            id: 10,
            framework_version_id: 1,
            code: 'HERQA_STD_02',
            title: 'Academic Programs and Curricula Relevance',
          },
        ],
      },
    } as any)

    vi.spyOn(institutionalApi, 'accreditationCycleSummary').mockResolvedValue({
      data: {
        success: true,
        data: {
          cycle: {
            id: 1,
            name: '2026 Institutional QA Cycle',
            is_closed: false,
          },
          metrics: {
            total_standards: 10,
            covered_standards: 1,
            missing_standards: 9,
            accepted_standards: 1,
            pending_review: 0,
            needs_revision: 0,
            rejected: 0,
            submission_rate_percent: 10.0,
            acceptance_rate_percent: 10.0,
            total_evidence_records: 1,
          },
          standards_breakdown: [],
        },
      },
    } as any)

    vi.spyOn(institutionalApi, 'accreditationEvidence').mockResolvedValue({
      data: {
        success: true,
        data: [
          {
            id: 101,
            cycle_id: 1,
            standard_id: 10,
            document_version_id: 5,
            submitted_by: 60,
            submitted_at: '2026-10-30T10:00:00Z',
            note: 'Undergraduate curricula dossier',
            standard: {
              id: 10,
              framework_version_id: 1,
              code: 'HERQA_STD_02',
              title: 'Academic Programs and Curricula Relevance',
            },
            document_version: {
              id: 5,
              version_number: 2,
              document: {
                id: 3,
                title: 'Medical School Curriculum Revision',
                reference_number: 'WU/SOM/2026/01',
              },
            },
            latest_review: {
              id: 201,
              evidence_id: 101,
              reviewer_id: 60,
              decision: 'accepted',
              comment: 'Fully compliant with national standard',
              reviewed_at: '2026-10-30T10:30:00Z',
            },
          },
        ],
      },
    } as any)

    const wrapper = mount(AccreditationEvidenceView, {
      global: {
        plugins: [router],
      },
    })

    // Allow async mounted calls to resolve
    await new Promise((resolve) => setTimeout(resolve, 50))
    await wrapper.vm.$nextTick()

    // Assert UI elements
    expect(wrapper.text()).toContain('Standard Framework: HERQA')
    expect(wrapper.text()).toContain('HERQA_STD_02')
    expect(wrapper.text()).toContain('Medical School Curriculum Revision')
    expect(wrapper.text()).toContain('Accepted') // Formatted sentence case via formatStatus
    expect(wrapper.text()).toContain('Evaluate')
  })
})

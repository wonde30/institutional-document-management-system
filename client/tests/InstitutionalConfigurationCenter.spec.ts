import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import InstitutionalConfigurationCenterView from '@/views/admin/InstitutionalConfigurationCenterView.vue'

vi.mock('vue-router', () => ({
  useRoute: () => ({ query: {} }),
  useRouter: () => ({ replace: vi.fn(), push: vi.fn() }),
}))

vi.mock('@/api/client', async (importOriginal) => {
  const actual = (await importOriginal()) as any
  return {
    ...actual,
    workflowAdminApi: {
      list: vi.fn().mockResolvedValue({
        data: {
          success: true,
          data: [
            {
              id: 1,
              code: 'ACADEMIC_PROMOTION',
              name: 'Academic Promotion Approval Workflow',
              description: 'Multi-stage faculty promotion process',
              active: true,
              versions_count: 2,
              active_version: { id: 10, version_number: 1, published_at: '2026-11-15T00:00:00Z', states_count: 3 },
              draft_version: { id: 11, version_number: 2, created_at: '2026-11-15T01:00:00Z' },
            },
          ],
        },
      }),
      get: vi.fn().mockResolvedValue({
        data: {
          success: true,
          data: {
            id: 1,
            code: 'ACADEMIC_PROMOTION',
            name: 'Academic Promotion Approval Workflow',
            description: 'Multi-stage faculty promotion process',
            active: true,
            versions: [
              {
                id: 10,
                version_number: 1,
                published_at: '2026-11-15T00:00:00Z',
                states: [
                  { id: 101, code: 'SUBMITTED', name: 'Dossier Submitted', is_initial: true, is_terminal: false, sla_hours: 48, outgoingTransitions: [] },
                  { id: 102, code: 'SENATE_CONFIRMED', name: 'Senate Confirmed', is_initial: false, is_terminal: true, outgoingTransitions: [] },
                ],
              },
            ],
          },
        },
      }),
      validate: vi.fn().mockResolvedValue({
        data: {
          success: true,
          data: { valid: true, errors: [] },
        },
      }),
      publish: vi.fn().mockResolvedValue({
        data: { success: true },
      }),
    },
    numberingPoliciesApi: {
      list: vi.fn().mockResolvedValue({
        data: {
          success: true,
          data: [
            {
              id: 1,
              code: 'DOC',
              name: 'General University Repository Reference',
              prefix: 'WU',
              include_unit_code: true,
              include_category_code: true,
              year_format: 'ethiopian_year',
              sequence_length: 6,
              reset_policy: 'yearly',
              active: true,
              next_value: 42,
            },
          ],
        },
      }),
      preview: vi.fn().mockResolvedValue({
        data: {
          success: true,
          data: {
            preview: 'WU/VPR/RES/2019/000001',
            parts: ['WU', 'VPR', 'RES', '2019', '000001'],
          },
        },
      }),
    },
    routingPoliciesApi: {
      list: vi.fn().mockResolvedValue({
        data: {
          success: true,
          data: [
            {
              id: 1,
              code: 'VPR_TO_ACAD',
              name: 'Research to Academic Affairs Expedited',
              from_unit_id: null,
              to_unit_id: 2,
              sla_hours: 36,
              active: true,
              toUnit: { id: 2, code: 'ACAD', name: 'Academic Affairs Directorate' },
            },
          ],
        },
      }),
      match: vi.fn().mockResolvedValue({
        data: {
          success: true,
          data: {
            data: {
              id: 1,
              name: 'Research to Academic Affairs Expedited',
              sla_hours: 36,
              to_unit: { id: 2, name: 'Academic Affairs Directorate' },
            },
            has_match: true,
          },
        },
      }),
    },
    retentionPoliciesApi: {
      list: vi.fn().mockResolvedValue({
        data: {
          success: true,
          data: [
            {
              id: 1,
              code: 'FIN_AUDIT_7Y',
              name: 'Financial Audit 7-Year Schedule',
              active: true,
              activeVersion: {
                id: 1,
                version_number: 1,
                duration_months: 84,
                trigger_code: 'date_archived',
                disposition_action: 'retain',
              },
            },
          ],
        },
      }),
    },
    metadataApi: {
      getAll: vi.fn().mockResolvedValue({
        data: {
          success: true,
          data: {
            document_categories: [{ id: 1, code: 'RES', name: 'Research' }],
            correspondence_types: [{ id: 1, code: 'MEMO', name: 'Internal Memo' }],
            priorities: [{ id: 1, code: 'ROUTINE', name: 'Routine' }],
          },
        },
      }),
    },
    organizationUnitsApi: {
      list: vi.fn().mockResolvedValue({
        data: {
          success: true,
          data: [{ id: 1, code: 'VPR', name: 'Vice President Research' }],
        },
      }),
    },
    rolesApi: {
      list: vi.fn().mockResolvedValue({
        data: {
          success: true,
          data: [{ id: 1, code: 'admin', name: 'System Administrator' }],
        },
      }),
      permissions: vi.fn().mockResolvedValue({
        data: {
          success: true,
          data: [{ id: 1, code: 'workflow.review', name: 'Workflow Review' }],
        },
      }),
    },
  }
})

describe('InstitutionalConfigurationCenterView.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders the configuration center header and tabs', async () => {
    const wrapper = mount(InstitutionalConfigurationCenterView, {
      global: {
        stubs: {
          routerLink: true,
          LoadingSkeleton: true,
          ModalDialog: true,
        },
      },
    })

    expect(wrapper.text()).toContain('Institutional Configuration Center')
    expect(wrapper.text()).toContain('Workflows & Approval Stages')
    expect(wrapper.text()).toContain('Reference Numbering')
    expect(wrapper.text()).toContain('Organizational Routing')
    expect(wrapper.text()).toContain('Retention Schedules')
  })

  it('switches between configuration tabs cleanly', async () => {
    const wrapper = mount(InstitutionalConfigurationCenterView, {
      global: {
        stubs: {
          routerLink: true,
          LoadingSkeleton: true,
          ModalDialog: true,
        },
      },
    })

    // Click on Reference Numbering Tab
    const numberingTabBtn = wrapper.findAll('button').find(b => b.text().includes('Reference Numbering'))
    expect(numberingTabBtn).toBeDefined()
    await numberingTabBtn!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Institutional Numbering Policies')
    expect(wrapper.text()).toContain('New Numbering Policy')
  })
})

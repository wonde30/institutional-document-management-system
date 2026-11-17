import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import DocumentDetailView from '@/views/documents/DocumentDetailView.vue'
import { documentsApi, archiveApi } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

const mockRoute = {
  params: { id: '42' },
}
const mockRouter = {
  push: vi.fn(),
}

vi.mock('vue-router', async (importOriginal) => {
  const actual = await importOriginal<any>()
  return {
    ...actual,
    useRoute: () => mockRoute,
    useRouter: () => mockRouter,
    RouterLink: {
      template: '<a><slot /></a>',
    },
  }
})

vi.mock('@/api/client', () => ({
  documentsApi: {
    get: vi.fn(),
    downloadFile: vi.fn(),
    uploadVersion: vi.fn(),
    timeline: vi.fn().mockResolvedValue({ data: { success: true, data: [] } }),
    digitize: vi.fn().mockResolvedValue({ data: { success: true, data: {} } }),
    physicalOriginals: vi.fn().mockResolvedValue({ data: { success: true, data: [] } }),
  },
  archiveApi: {
    getCollections: vi.fn(),
    transferDocument: vi.fn(),
  },
  ocrApi: {
    extractions: vi.fn().mockResolvedValue({ data: { success: true, data: [] } }),
    process: vi.fn().mockResolvedValue({ data: { success: true, data: {} } }),
    verify: vi.fn().mockResolvedValue({ data: { success: true, data: {} } }),
    retry: vi.fn().mockResolvedValue({ data: { success: true, data: {} } }),
    status: vi.fn().mockResolvedValue({ data: { success: true, data: { tesseract_installed: false } } }),
  },
  institutionalApi: {
    signatures: vi.fn().mockResolvedValue({ data: { success: true, data: { signatures: [] } } }),
    verifySignature: vi.fn().mockResolvedValue({ data: { success: true, data: { outcome: 'valid' } } }),
  },
  organizationUnitsApi: {
    unitTypes: vi.fn().mockResolvedValue({ data: { success: true, data: [] } }),
  },
  metadataApi: {
    getAll: vi.fn().mockResolvedValue({
      data: {
        success: true,
        data: {
          document_categories: [],
          confidentiality_levels: [],
          correspondence_types: [],
          priorities: [],
          dispatch_methods: [],
        },
      },
    }),
    getCategories: vi.fn().mockResolvedValue({ data: { success: true, data: [] } }),
    getConfidentialityLevels: vi.fn().mockResolvedValue({ data: { success: true, data: [] } }),
    getCorrespondenceTypes: vi.fn().mockResolvedValue({ data: { success: true, data: [] } }),
    getPriorities: vi.fn().mockResolvedValue({ data: { success: true, data: [] } }),
  },
}))

describe('DocumentDetailView.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    const authStore = useAuthStore()
    authStore.user = {
      id: 1,
      name: 'Dr. Test Administrator',
      email: 'admin@wu.edu.et',
      roles: [{ id: 1, name: 'Admin' }],
      permissions: ['documents.view', 'documents.download', 'documents.version'],
      is_active: true,
    }
  })

  it('renders document metadata, versions, and file objects when loaded', async () => {
    const mockDoc = {
      id: 42,
      reference_number: 'WU/DOC/2026/000042',
      title: 'Institutional Strategic Plan 2026-2030',
      summary: 'Five-year university expansion and digital roadmap.',
      category: { id: 1, name: 'Administrative' },
      confidentiality: { id: 2, code: 'internal', name: 'Internal' },
      owner_unit: { id: 1, name: 'President Office', code: 'PRES' },
      creator: { id: 1, name: 'President Office Secretary' },
      custodian: { id: 2, name: 'Central Registry Archivist' },
      registered_at: '2026-10-29T10:00:00Z',
      document_date: '2026-10-29',
      lifecycle: 'active',
      lock_version: 0,
      versions: [
        {
          id: 101,
          document_id: 42,
          version_number: 1,
          change_note: 'Initial senate draft',
          created_at: '2026-10-29T10:00:00Z',
          version_files: [
            {
              file_object_id: 99,
              purpose: 'primary',
              file_object: {
                id: 99,
                original_filename: 'Strategic_Plan_2026.pdf',
                detected_mime: 'application/pdf',
                byte_size: 1048576, // 1 MB
                sha256: 'abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890',
                storage_status: 'ready',
              },
            },
          ],
        },
      ],
      correspondence: null,
      movements: [],
    }

    vi.mocked(documentsApi.get).mockResolvedValueOnce({
      data: { success: true, data: mockDoc },
    } as any)

    const wrapper = mount(DocumentDetailView, {
      global: {
        stubs: {
          'router-link': true,
          BaseButton: {
            template: '<button :disabled="$attrs.disabled"><slot /></button>',
          },
          StatusBadge: {
            template: '<span class="status-badge"><slot /></span>',
          },
          ClassificationBadge: {
            template: '<span class="classification-badge"><slot /></span>',
          },
          LoadingSkeleton: {
            template: '<div class="skeleton"></div>',
          },
        },
      },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('WU/DOC/2026/000042')
    expect(wrapper.text()).toContain('Institutional Strategic Plan 2026-2030')
    expect(wrapper.text()).toContain('Five-year university expansion and digital roadmap.')
    expect(wrapper.text()).toContain('Strategic_Plan_2026.pdf')
    expect(wrapper.text()).toContain('1 MB')
    expect(wrapper.text()).toContain('Initial senate draft')
  })

  it('triggers authenticated file download when download button is clicked', async () => {
    const mockDoc = {
      id: 42,
      reference_number: 'WU/DOC/2026/000042',
      title: 'Institutional Strategic Plan 2026-2030',
      owner_unit: { id: 1, name: 'President Office', code: 'PRES' },
      registered_at: '2026-10-29T10:00:00Z',
      lifecycle: 'active',
      lock_version: 0,
      versions: [
        {
          id: 101,
          document_id: 42,
          version_number: 1,
          version_files: [
            {
              file_object_id: 99,
              purpose: 'primary',
              file_object: {
                id: 99,
                original_filename: 'Strategic_Plan_2026.pdf',
                detected_mime: 'application/pdf',
                byte_size: 1048576,
                sha256: 'abcdef123456',
                storage_status: 'ready',
              },
            },
          ],
        },
      ],
    }

    vi.mocked(documentsApi.get).mockResolvedValueOnce({
      data: { success: true, data: mockDoc },
    } as any)

    vi.mocked(documentsApi.downloadFile).mockResolvedValueOnce(new Blob(['file-content']) as any)

    // Mock URL methods
    globalThis.URL.createObjectURL = vi.fn().mockReturnValue('blob:http://localhost/test-blob')
    globalThis.URL.revokeObjectURL = vi.fn()

    const wrapper = mount(DocumentDetailView, {
      global: {
        stubs: {
          'router-link': true,
          BaseButton: {
            template: '<button :disabled="$attrs.disabled"><slot /></button>',
          },
          StatusBadge: true,
          ClassificationBadge: true,
          LoadingSkeleton: true,
        },
      },
    })

    await flushPromises()

    const downloadBtn = wrapper.findAll('button').find(b => b.text().includes('Download'))
    expect(downloadBtn).toBeDefined()

    await downloadBtn!.trigger('click')
    await flushPromises()

    expect(documentsApi.downloadFile).toHaveBeenCalledWith(99)
  })

  it('renders institutional chain-of-custody and movement history card when movements are present', async () => {
    const mockDoc = {
      id: 42,
      reference_number: 'WU/DOC/2026/000042',
      title: 'Senate Academic Policy Decree',
      owner_unit: { id: 1, name: 'President Office', code: 'PRES' },
      registered_at: '2026-10-29T10:00:00Z',
      lifecycle: 'active',
      lock_version: 0,
      versions: [],
      correspondence: {
        id: 7,
        movements: [
          {
            id: 201,
            from_unit: { id: 1, name: 'President Office', code: 'PRES' },
            to_unit: { id: 2, name: 'Office of the Academic VP', code: 'AVP' },
            sent_at: '2026-10-29T11:00:00Z',
            instruction: 'For urgent faculty implementation.',
            sent_by_user: { id: 1, name: 'President Secretary' },
            receipt: {
              id: 301,
              acknowledged_at: '2026-10-29T12:00:00Z',
              acknowledged_by_user: { id: 4, name: 'AVP Executive Secretary' },
            },
          },
        ],
      },
    }

    vi.mocked(documentsApi.get).mockResolvedValueOnce({
      data: { success: true, data: mockDoc },
    } as any)

    const wrapper = mount(DocumentDetailView, {
      global: {
        stubs: {
          'router-link': true,
          BaseButton: true,
          StatusBadge: true,
          ClassificationBadge: true,
          LoadingSkeleton: true,
        },
      },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('Institutional Chain-of-Custody & Movement History')
    expect(wrapper.text()).toContain('President Office')
    expect(wrapper.text()).toContain('Office of the Academic VP')
    expect(wrapper.text()).toContain('Acknowledged')
    expect(wrapper.text()).toContain('For urgent faculty implementation.')
  })

  it('renders 403 Forbidden security access panel when user lacks authorization', async () => {
    vi.mocked(documentsApi.get).mockRejectedValueOnce({
      response: {
        status: 403,
        data: {
          message: 'Forbidden: High confidentiality document access restricted.',
        },
      },
    })

    const wrapper = mount(DocumentDetailView, {
      global: {
        stubs: {
          'router-link': true,
          BaseButton: true,
          StatusBadge: true,
          ClassificationBadge: true,
          LoadingSkeleton: true,
        },
      },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('Access Restricted')
    expect(wrapper.text()).toContain('Forbidden: High confidentiality document access restricted.')
  })

  it('renders 404 Not Found panel when document is missing or purged', async () => {
    vi.mocked(documentsApi.get).mockRejectedValueOnce({
      response: {
        status: 404,
        data: {
          message: 'Document record not found in repository.',
        },
      },
    })

    const wrapper = mount(DocumentDetailView, {
      global: {
        stubs: {
          'router-link': true,
          BaseButton: true,
          StatusBadge: true,
          ClassificationBadge: true,
          LoadingSkeleton: true,
        },
      },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('Document Not Found')
    expect(wrapper.text()).toContain('Document record not found in repository.')
  })

  it('renders unified institutional lifecycle timeline and physical original cards', async () => {
    const mockDoc = {
      id: 42,
      reference_number: 'WU/DOC/2026/000042',
      title: 'Institutional Strategic Plan 2026-2030',
      owner_unit: { id: 1, name: 'President Office', code: 'PRES' },
      registered_at: '2026-10-29T10:00:00Z',
      lifecycle: 'active',
      lock_version: 0,
      versions: [],
      physical_originals: [
        {
          id: 88,
          document_id: 42,
          physical_reference: 'WU/ARCH/2026/001',
          retention_requirement: 'required',
          created_at: '2026-10-29T10:05:00Z',
          custody_events: [
            {
              id: 1,
              action: 'register',
              occurred_at: '2026-10-29T10:05:00Z',
              location: { id: 5, code: 'BLD-A-R101', name: 'Central Archive Vault 1' },
              custodian: { id: 2, name: 'Chief Archivist' },
            },
          ],
        },
      ],
      assignments: [
        {
          id: 10,
          document_id: 42,
          assigned_user_id: 5,
          assigned_user: { id: 5, name: 'Abebe Kebede', email: 'abebe@wu.edu.et' },
          assigned_by: 1,
          assigned_by_user: { id: 1, name: 'Dr. Test Administrator' },
          assigned_at: '2026-10-29T11:00:00Z',
          instruction: 'Execute faculty strategic review.',
        },
      ],
    }

    const mockTimeline = [
      {
        id: 'doc-reg-42',
        event_type: 'document_registered',
        title: 'Document Registered in Repository',
        description: 'Initial registration with reference WU/DOC/2026/000042',
        actor: { id: 1, name: 'Dr. Test Administrator' },
        timestamp: '2026-10-29T10:00:00Z',
      },
      {
        id: 'custody-1',
        event_type: 'physical_custody',
        title: 'Physical Paper Custody: Register',
        description: 'Physical intake recorded in Central Archive Vault 1',
        actor: { id: 1, name: 'Dr. Test Administrator' },
        timestamp: '2026-10-29T10:05:00Z',
      },
    ]

    vi.mocked(documentsApi.get).mockResolvedValueOnce({
      data: { success: true, data: mockDoc },
    } as any)

    vi.mocked(documentsApi.timeline).mockResolvedValueOnce({
      data: { success: true, data: mockTimeline },
    } as any)

    const wrapper = mount(DocumentDetailView, {
      global: {
        stubs: {
          'router-link': true,
          BaseButton: true,
          StatusBadge: true,
          ClassificationBadge: true,
          LoadingSkeleton: true,
        },
      },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('Unified Institutional Lifecycle Timeline')
    expect(wrapper.text()).toContain('Physical Originals & Archival Storage')
    expect(wrapper.text()).toContain('WU/ARCH/2026/001')
    expect(wrapper.text()).toContain('Central Archive Vault 1')
    expect(wrapper.text()).toContain('Operational Directives & Assignments')
    expect(wrapper.text()).toContain('Abebe Kebede')
    expect(wrapper.text()).toContain('Execute faculty strategic review.')
    expect(wrapper.text()).toContain('Document Registered in Repository')
    expect(wrapper.text()).toContain('Physical Paper Custody: Register')
  })
})

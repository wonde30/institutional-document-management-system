import { describe, it, expect, beforeEach, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { mount, flushPromises } from '@vue/test-utils'
import { useAuthStore } from '@/stores/auth'
import SignaturesStampsView from '@/views/signatures/SignaturesStampsView.vue'
import AccreditationEvidenceView from '@/views/accreditation/AccreditationEvidenceView.vue'
import StaffRecordsView from '@/views/staff/StaffRecordsView.vue'
import ReferenceDataView from '@/views/admin/ReferenceDataView.vue'
import {
  institutionalApi,
  documentsApi,
  usersApi,
  organizationUnitsApi,
  metadataApi,
} from '@/api/client'

vi.mock('@/api/client', () => ({
  institutionalApi: {
    signatures: vi.fn(),
    signingIdentities: vi.fn(),
    executeSignature: vi.fn(),
    verifySignature: vi.fn(),
    storeSignatureRequest: vi.fn(),
    applyStamp: vi.fn(),
    accreditationCycles: vi.fn(),
    accreditationStandards: vi.fn(),
    accreditationCycleSummary: vi.fn(),
    accreditationEvidence: vi.fn(),
    submitAccreditationEvidence: vi.fn(),
    staffRecords: vi.fn(),
  },
  documentsApi: { list: vi.fn() },
  usersApi: { list: vi.fn() },
  organizationUnitsApi: { list: vi.fn() },
  metadataApi: { getAll: vi.fn() },
}))

describe('Phase 2 Frontend Truthfulness & RBAC Regressions', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('displays genuine invalid/tampered/expired signature verification results without hardcoded pass text', async () => {
    const auth = useAuthStore()
    auth.user = {
      id: 10,
      name: 'Signer Officer',
      email: 'signer@wu.edu.et',
      status: 'active',
      permissions: ['signatures.request'],
      roles: [],
    } as any

    vi.mocked(institutionalApi.signatures).mockResolvedValue({
      data: {
        success: true,
        data: {
          signatures: [
            {
              id: 50,
              document_version_id: 12,
              signer_id: 10,
              requested_by: 1,
              purpose: 'Test Seal',
              document_version: { version_number: 1, document: { title: 'Tampered Contract', reference_number: 'WU/01' } },
              signer: { name: 'Signer Officer', email: 'signer@wu.edu.et' },
              digital_signature: {
                id: 77,
                signature_format: 'PAdES-B-LT',
                signed_at: '2026-11-26T10:00:00Z',
              },
            },
          ],
          stamps: [{ id: 1, unit_id: 1, name: 'IT Stamp', active: true }],
        },
      },
    } as any)
    vi.mocked(documentsApi.list).mockResolvedValue({ data: { data: [] } } as any)
    vi.mocked(usersApi.list).mockResolvedValue({ data: { data: [] } } as any)
    vi.mocked(institutionalApi.signingIdentities).mockResolvedValue({ data: { data: [] } } as any)
    vi.mocked(institutionalApi.verifySignature).mockResolvedValue({
      data: {
        success: true,
        data: {
          verification_id: 1,
          signature_id: 77,
          outcome: 'invalid',
          certificate_status: 'expired',
          timestamp_status: 'not_present',
          message: 'Post-signing modifications detected.',
          details: {
            tamper_detected: true,
            post_signing_modifications: true,
            byte_range_valid: false,
            observed_sha256: 'deadbeef',
          },
        },
      },
    } as any)

    const wrapper = mount(SignaturesStampsView)
    await flushPromises()

    // Stamp Apply button must NOT show for signatures.request-only user lacking stamps.apply/stamps.manage
    const stampsTab = wrapper.findAll('button').find((b) => b.text().includes('Office Stamps'))
    await stampsTab!.trigger('click')
    expect(wrapper.text()).not.toContain('Apply Seal')

    const sigTab = wrapper.findAll('button').find((b) => b.text().includes('Signing Requests'))
    await sigTab!.trigger('click')
    const verifyBtn = wrapper.findAll('button').find((b) => b.text().includes('Verify Cryptography'))
    await verifyBtn!.trigger('click')
    await flushPromises()

    const modalText = wrapper.text()
    expect(modalText).not.toContain('Unbroken (Tamper Passed)')
    expect(modalText).toContain('Invalid')
    expect(modalText).toContain('Tampered (Modified After Signing)')
    expect(modalText).toContain('Expired Certificate')
    expect(modalText).toContain('Not present')
  })

  it('never falls back to doc.id when a repository document has no resolved version ID', async () => {
    const auth = useAuthStore()
    auth.user = {
      id: 10,
      name: 'QA Submitter',
      email: 'qa@wu.edu.et',
      status: 'active',
      permissions: ['accreditation.view', 'accreditation.submit_evidence', 'documents.view'],
      roles: [],
    } as any

    vi.mocked(institutionalApi.accreditationCycles).mockResolvedValue({
      data: { success: true, data: [{ id: 1, name: 'QA 2026', unit_id: 1 }] },
    } as any)
    vi.mocked(institutionalApi.accreditationStandards).mockResolvedValue({
      data: { success: true, data: [{ id: 5, code: 'HERQA_01', title: 'Governance' }] },
    } as any)
    vi.mocked(institutionalApi.accreditationCycleSummary).mockResolvedValue({
      data: { success: true, data: null },
    } as any)
    vi.mocked(institutionalApi.accreditationEvidence).mockResolvedValue({
      data: { success: true, data: [] },
    } as any)
    vi.mocked(documentsApi.list).mockResolvedValue({
      data: {
        data: [
          { id: 999, reference_number: 'WU/UNVER/999', title: 'Unversioned Draft' },
          { id: 1000, reference_number: 'WU/VER/1000', title: 'Valid Versioned Doc', current_version: { id: 4321 } },
        ],
      },
    } as any)

    const wrapper = mount(AccreditationEvidenceView)
    await flushPromises()

    const attachBtn = wrapper.findAll('button').find((b) => b.text().includes('Attach Evidence') || b.text().includes('Attach'))
    await attachBtn!.trigger('click')
    await flushPromises()

    const options = wrapper.findAll('select option')
    const draftOpt = options.find((o) => o.text().includes('WU/UNVER/999'))
    const validOpt = options.find((o) => o.text().includes('WU/VER/1000'))
    expect(draftOpt).toBeDefined()
    expect(draftOpt!.attributes('value')).toBe('')
    expect(draftOpt!.attributes('disabled')).toBeDefined()
    expect(validOpt!.attributes('value')).toBe('4321')
  })

  it('aligns StaffRecordsView and ReferenceDataView action buttons with backend permissions', async () => {
    const auth = useAuthStore()
    auth.user = {
      id: 20,
      name: 'HR Custodian',
      email: 'hr.custodian@wu.edu.et',
      status: 'active',
      permissions: ['staff.view', 'staff.update'],
      roles: [],
    } as any

    vi.mocked(organizationUnitsApi.list).mockResolvedValue({ data: { data: [] } } as any)
    vi.mocked(institutionalApi.staffRecords).mockResolvedValue({
      data: {
        success: true,
        data: [
          {
            id: 1,
            physical_reference: 'PHYS-01',
            retention_requirement: 'required',
            document: { title: 'Personnel File' },
            custody_events: [],
            digitization_records: [{ verification_status: 'pending' }],
          },
        ],
      },
    } as any)

    const staffWrapper = mount(StaffRecordsView)
    await flushPromises()
    expect(staffWrapper.text()).toContain('Register Original Record')
    expect(staffWrapper.text()).toContain('Verify Scan')
    expect(staffWrapper.text()).not.toContain('Dispose Physical')

    vi.mocked(metadataApi.getAll).mockResolvedValue({
      data: {
        success: true,
        data: {
          document_categories: [{ id: 1, code: 'memo', name: 'Memorandum', active: true }],
          confidentiality_levels: [],
          correspondence_types: [],
          priorities: [],
          dispatch_methods: [],
        },
      },
    } as any)

    const refWrapper = mount(ReferenceDataView)
    await flushPromises()
    // Non-system.configure caller must see table data cleanly but zero Add/Edit/Deactivate controls
    expect(refWrapper.text()).toContain('Memorandum')
    expect(refWrapper.findAll('button').some((b) => b.text().trim() === 'Deactivate')).toBe(false)
    expect(refWrapper.findAll('button[title="Edit Category"]').length).toBe(0)
    expect(refWrapper.text()).not.toContain('Add Category')
  })
})

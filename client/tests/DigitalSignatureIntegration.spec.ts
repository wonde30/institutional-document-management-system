import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import { useAuthStore } from '@/stores/auth'
import router from '@/router'
import SignaturesStampsView from '@/views/signatures/SignaturesStampsView.vue'
import { institutionalApi, documentsApi, usersApi } from '@/api/client'
import type { User } from '@/types'

vi.mock('@/api/client', () => ({
  institutionalApi: {
    signatures: vi.fn(),
    signingIdentities: vi.fn(),
    executeSignature: vi.fn(),
    verifySignature: vi.fn(),
    storeSignatureRequest: vi.fn(),
  },
  documentsApi: {
    list: vi.fn(),
  },
  usersApi: {
    list: vi.fn(),
  },
}))

describe('Digital Signature PKI Frontend Integration & Transparency', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    vi.clearAllMocks()

    vi.mocked(documentsApi.list).mockResolvedValue({
      data: { data: [{ id: 101, title: 'Senate Policy Resolution', reference_number: 'WU/POL/2026/01' }] },
    } as any)

    vi.mocked(usersApi.list).mockResolvedValue({
      data: { data: [{ id: 9, name: 'Prof. Dean Worku', email: 'dean.worku@wu.edu.et' }] },
    } as any)

    vi.mocked(institutionalApi.signingIdentities).mockResolvedValue({
      data: {
        success: true,
        data: [
          {
            id: 1,
            user_id: 9,
            user_name: 'Prof. Dean Worku',
            user_email: 'dean.worku@wu.edu.et',
            provider_code: 'wollo_institutional_pki',
            provider_identity_reference: 'WU-PKI-USER-9-ABCD',
            valid_from: '2026-10-01',
            valid_until: '2028-10-01',
            active: true,
          },
        ],
      },
    } as any)
  })

  it('renders the statutory legal trust boundary notice', async () => {
    vi.mocked(institutionalApi.signatures).mockResolvedValue({
      data: {
        success: true,
        data: {
          signatures: [],
          stamps: [],
          trust_provider_status: 'active_local_verified',
        },
      },
    } as any)

    const wrapper = mount(SignaturesStampsView)
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('Legal Trust Boundary & Regulatory Notice')
    expect(wrapper.text()).toContain('A visual stamp image is NOT a cryptographic digital signature')
    expect(wrapper.text()).toContain('Proclamation No. 1072/2018')
  })

  it('displays digital signature execution and verification actions for authorized signers', async () => {
    const auth = useAuthStore()
    const deanUser: User = {
      id: 9,
      name: 'Prof. Dean Worku',
      email: 'dean.worku@wu.edu.et',
      roles: [{ id: 3, name: 'Dean' }],
      permissions: ['signatures.request'],
      is_active: true,
    }
    auth.user = deanUser

    vi.mocked(institutionalApi.signatures).mockResolvedValue({
      data: {
        success: true,
        data: {
          signatures: [
            {
              id: 1,
              signer_id: 9,
              purpose: 'Senate Approval',
              document_version: {
                document: { title: 'Accreditation Monograph', reference_number: 'WU/DOC/01' },
                version_number: 1,
              },
              signer: { name: 'Prof. Dean Worku', email: 'dean.worku@wu.edu.et' },
              digital_signature: null, // Pending execution
            },
            {
              id: 2,
              signer_id: 9,
              purpose: 'Dean Endorsement',
              document_version: {
                document: { title: 'Curriculum Revision', reference_number: 'WU/DOC/02' },
                version_number: 1,
              },
              signer: { name: 'Prof. Dean Worku', email: 'dean.worku@wu.edu.et' },
              digital_signature: {
                id: 50,
                signature_format: 'PAdES-B-LT',
                signed_at: '2026-10-30T15:00:00Z',
              },
            },
          ],
          stamps: [],
          trust_provider_status: 'active_local_verified',
        },
      },
    } as any)

    const wrapper = mount(SignaturesStampsView)
    await wrapper.vm.$nextTick()
    await new Promise((r) => setTimeout(r, 10))

    // Row 1 (Pending): shows Sign Document button
    expect(wrapper.text()).toContain('Sign Document')

    // Row 2 (Signed): shows signed format and Verify Cryptography button
    expect(wrapper.text()).toContain('Signed (PAdES-B-LT)')
    expect(wrapper.text()).toContain('Verify Cryptography')
  })

  it('navigates to Trust Configuration tab and displays user signing identities', async () => {
    vi.mocked(institutionalApi.signatures).mockResolvedValue({
      data: {
        success: true,
        data: {
          signatures: [],
          stamps: [],
          trust_provider_status: 'active_local_verified',
        },
      },
    } as any)

    const wrapper = mount(SignaturesStampsView)
    await wrapper.vm.$nextTick()
    await new Promise((r) => setTimeout(r, 10))

    // Switch to Trust Configuration tab
    const tabs = wrapper.findAll('button')
    const providerTab = tabs.find((t) => t.text().includes('Trust Configuration'))
    expect(providerTab).toBeDefined()
    await providerTab!.trigger('click')

    expect(wrapper.text()).toContain('Wollo University Trust Services Architecture')
    expect(wrapper.text()).toContain('RSA-2048 with SHA-256 (PKCS#7 Detached ByteRange Envelope)')
    expect(wrapper.text()).toContain('TEST PKI — UNACCREDITED INSTITUTIONAL CA')
    expect(wrapper.text()).toContain('WU-PKI-USER-9-ABCD')
  })
})

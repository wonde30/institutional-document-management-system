import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'

const {
  forgotPassword,
  resetPassword,
  verifyEmail,
  sendVerificationEmail,
} = vi.hoisted(() => ({
  forgotPassword: vi.fn(),
  resetPassword: vi.fn(),
  verifyEmail: vi.fn(),
  sendVerificationEmail: vi.fn(),
}))

vi.mock('@/api/client', () => ({
  authApi: {
    forgotPassword,
    resetPassword,
    verifyEmail,
    sendVerificationEmail,
    me: vi.fn().mockRejectedValue({ response: { status: 401 } }),
    login: vi.fn(),
    logout: vi.fn(),
  },
  navigationApi: {
    get: vi.fn().mockResolvedValue({ data: { success: true, data: [] } }),
  },
  ensureCsrfCookie: vi.fn().mockResolvedValue(undefined),
}))

const { routeQuery } = vi.hoisted(() => ({
  routeQuery: {} as Record<string, string>,
}))

vi.mock('vue-router', async () => {
  const actual = await vi.importActual<typeof import('vue-router')>('vue-router')
  return {
    ...actual,
    useRoute: () => ({ query: routeQuery }),
    useRouter: () => ({ push: vi.fn() }),
  }
})

import ForgotPasswordView from '@/views/auth/ForgotPasswordView.vue'
import ResetPasswordView from '@/views/auth/ResetPasswordView.vue'
import VerifyEmailView from '@/views/auth/VerifyEmailView.vue'

describe('Account recovery', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    forgotPassword.mockClear()
    resetPassword.mockClear()
    verifyEmail.mockClear()
    sendVerificationEmail.mockClear()
    forgotPassword.mockResolvedValue({ data: { success: true } })
    resetPassword.mockResolvedValue({ data: { success: true } })
    verifyEmail.mockResolvedValue({ data: { success: true } })
    sendVerificationEmail.mockResolvedValue({ data: { success: true } })
    Object.keys(routeQuery).forEach((key) => delete routeQuery[key])
  })

  it('requests a recovery link from the API instead of simulating one', async () => {
    const wrapper = mount(ForgotPasswordView, {
      global: { stubs: { 'router-link': true } },
    })

    await wrapper.find('input').setValue('officer@wu.edu.et')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(forgotPassword).toHaveBeenCalledWith({ email: 'officer@wu.edu.et' })
    expect(wrapper.text()).toContain('Request Processed')
  })

  it('surfaces a rejected recovery request instead of claiming success', async () => {
    forgotPassword.mockRejectedValueOnce({
      response: { data: { errors: { email: ['The rate limit has been exceeded.'] } } },
    })

    const wrapper = mount(ForgotPasswordView, {
      global: { stubs: { 'router-link': true } },
    })

    await wrapper.find('input').setValue('officer@wu.edu.et')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('The rate limit has been exceeded.')
    expect(wrapper.text()).not.toContain('Request Processed')
  })

  it('completes the recovery with the token carried by the link', async () => {
    routeQuery.token = 'reset-token-value'
    routeQuery.email = 'officer@wu.edu.et'

    const wrapper = mount(ResetPasswordView, {
      global: { stubs: { 'router-link': true } },
    })

    const inputs = wrapper.findAll('input')
    await inputs[0].setValue('ReplacementPassword123!')
    await inputs[1].setValue('ReplacementPassword123!')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(resetPassword).toHaveBeenCalledWith({
      token: 'reset-token-value',
      email: 'officer@wu.edu.et',
      password: 'ReplacementPassword123!',
      password_confirmation: 'ReplacementPassword123!',
    })
    expect(wrapper.text()).toContain('Password Updated')
  })

  it('refuses a recovery that does not match the confirmation', async () => {
    routeQuery.token = 'reset-token-value'
    routeQuery.email = 'officer@wu.edu.et'

    const wrapper = mount(ResetPasswordView, {
      global: { stubs: { 'router-link': true } },
    })

    const inputs = wrapper.findAll('input')
    await inputs[0].setValue('ReplacementPassword123!')
    await inputs[1].setValue('DifferentPassword123!')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(resetPassword).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Passwords do not match.')
  })

  it('confirms the institutional address when the view is opened from the link', async () => {
    routeQuery.id = '42'
    routeQuery.hash = 'a-hash-value'

    const wrapper = mount(VerifyEmailView)
    await flushPromises()

    expect(verifyEmail).toHaveBeenCalledWith('42', 'a-hash-value')
    expect(wrapper.text()).toContain('Address Confirmed')
  })

  it('does not attempt a confirmation when the view is opened without a link', async () => {
    mount(VerifyEmailView)
    await flushPromises()

    expect(verifyEmail).not.toHaveBeenCalled()
  })
})

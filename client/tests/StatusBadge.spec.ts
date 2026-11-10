import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import StatusBadge from '@/components/StatusBadge.vue'

describe('StatusBadge Component', () => {
  it('renders correct text and badge class for approved status', () => {
    const wrapper = mount(StatusBadge, {
      props: { status: 'approved' },
    })
    expect(wrapper.text()).toBe('approved')
    expect(wrapper.classes()).toContain('badge-success')
  })

  it('renders correct badge class for rejected status', () => {
    const wrapper = mount(StatusBadge, {
      props: { status: 'rejected' },
    })
    expect(wrapper.text()).toBe('rejected')
    expect(wrapper.classes()).toContain('badge-danger')
  })

  it('renders correct badge class for pending status', () => {
    const wrapper = mount(StatusBadge, {
      props: { status: 'pending' },
    })
    expect(wrapper.text()).toBe('pending')
    expect(wrapper.classes()).toContain('badge-warning')
  })

  it('renders fallback info badge and replaces underscores for unknown status', () => {
    const wrapper = mount(StatusBadge, {
      props: { status: 'custom_state' },
    })
    expect(wrapper.text()).toBe('custom state')
    expect(wrapper.classes()).toContain('badge-info')
  })
})

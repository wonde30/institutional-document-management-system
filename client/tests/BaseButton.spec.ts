import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import BaseButton from '@/components/ui/BaseButton.vue'

describe('BaseButton.vue', () => {
  it('renders default button with slot text', () => {
    const wrapper = mount(BaseButton, {
      slots: {
        default: 'Submit Document',
      },
    })
    expect(wrapper.text()).toContain('Submit Document')
    // Primary variant uses design-system token bg-primary (not a raw Tailwind colour)
    expect(wrapper.classes()).toContain('bg-primary')
  })

  it('renders danger variant styling', () => {
    const wrapper = mount(BaseButton, {
      props: {
        variant: 'danger',
      },
      slots: {
        default: 'Delete Record',
      },
    })
    // Danger variant references the CSS variable --wu-danger
    expect(wrapper.classes().join(' ')).toContain('bg-[var(--wu-danger)]')
  })

  it('renders warning variant with accessible contrast (light amber surface)', () => {
    const wrapper = mount(BaseButton, {
      props: {
        variant: 'warning',
      },
      slots: {
        default: 'Warning Action',
      },
    })
    // Warning uses an accessible amber tinted surface (not white-text-on-orange)
    const classes = wrapper.classes().join(' ')
    expect(classes).toContain('bg-[#fff4e6]')
    expect(classes).toContain('text-[#793400]')
  })

  it('renders loading spinner and disables button when loading', () => {
    const wrapper = mount(BaseButton, {
      props: {
        loading: true,
      },
      slots: {
        default: 'Saving',
      },
    })
    expect(wrapper.attributes('disabled')).toBeDefined()
    expect(wrapper.find('svg.animate-spin').exists()).toBe(true)
  })

  it('disables button when disabled prop is true', () => {
    const wrapper = mount(BaseButton, {
      props: {
        disabled: true,
      },
      slots: {
        default: 'Inactive',
      },
    })
    expect(wrapper.attributes('disabled')).toBeDefined()
  })
})

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
    expect(wrapper.classes()).toContain('bg-blue-700')
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
    expect(wrapper.classes()).toContain('bg-rose-600')
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

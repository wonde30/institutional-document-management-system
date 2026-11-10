import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import PriorityBadge from '@/components/PriorityBadge.vue'

describe('PriorityBadge Component', () => {
  it('renders urgent priority with badge-urgent class', () => {
    const wrapper = mount(PriorityBadge, {
      props: { priority: 'urgent' },
    })
    expect(wrapper.text()).toBe('urgent')
    expect(wrapper.classes()).toContain('badge-urgent')
  })

  it('renders high priority with badge-high class', () => {
    const wrapper = mount(PriorityBadge, {
      props: { priority: 'high' },
    })
    expect(wrapper.text()).toBe('high')
    expect(wrapper.classes()).toContain('badge-high')
  })

  it('renders normal priority with badge-normal class', () => {
    const wrapper = mount(PriorityBadge, {
      props: { priority: 'normal' },
    })
    expect(wrapper.text()).toBe('normal')
    expect(wrapper.classes()).toContain('badge-normal')
  })

  it('renders low priority with badge-low class', () => {
    const wrapper = mount(PriorityBadge, {
      props: { priority: 'low' },
    })
    expect(wrapper.text()).toBe('low')
    expect(wrapper.classes()).toContain('badge-low')
  })
})

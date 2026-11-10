import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ClassificationBadge from '@/components/ClassificationBadge.vue'

describe('ClassificationBadge Component', () => {
  it('renders Restricted classification with badge-restricted class', () => {
    const wrapper = mount(ClassificationBadge, {
      props: {
        classification: {
          id: 4,
          name: 'Restricted',
          code: 'RESTRICTED',
          level: 4,
          color: '#ef4444',
          description: 'Executive Only',
          is_active: true,
        },
      },
    })
    expect(wrapper.text()).toBe('Restricted')
    expect(wrapper.classes()).toContain('badge-restricted')
  })

  it('renders Confidential classification with badge-confidential class', () => {
    const wrapper = mount(ClassificationBadge, {
      props: {
        classification: {
          id: 3,
          name: 'Confidential',
          code: 'CONFIDENTIAL',
          level: 3,
          color: '#f59e0b',
          description: 'Internal Management',
          is_active: true,
        },
      },
    })
    expect(wrapper.text()).toBe('Confidential')
    expect(wrapper.classes()).toContain('badge-confidential')
  })

  it('renders Public classification with badge-public class', () => {
    const wrapper = mount(ClassificationBadge, {
      props: {
        classification: {
          id: 1,
          name: 'Public',
          code: 'PUBLIC',
          level: 1,
          color: '#10b981',
          description: 'Open to Public',
          is_active: true,
        },
      },
    })
    expect(wrapper.text()).toBe('Public')
    expect(wrapper.classes()).toContain('badge-public')
  })

  it('handles null/undefined classification gracefully as unclassified', () => {
    const wrapper = mount(ClassificationBadge, {
      props: {
        classification: undefined,
      },
    })
    expect(wrapper.text()).toBe('Unclassified')
    expect(wrapper.classes()).toContain('badge-secondary')
  })
})

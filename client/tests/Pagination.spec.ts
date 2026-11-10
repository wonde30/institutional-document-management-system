import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import Pagination from '@/components/Pagination.vue'

describe('Pagination Component', () => {
  it('renders pagination info and buttons from meta prop', () => {
    const wrapper = mount(Pagination, {
      props: {
        meta: {
          current_page: 2,
          last_page: 5,
          per_page: 10,
          total: 50,
          from: 11,
          to: 20,
        },
      },
    })

    expect(wrapper.text()).toContain('Showing 11 to 20 of 50 entries')
    expect(wrapper.text()).toContain('Page 2 of 5')

    const buttons = wrapper.findAll('button')
    expect(buttons).toHaveLength(2)
    expect(buttons[0].attributes('disabled')).toBeUndefined()
    expect(buttons[1].attributes('disabled')).toBeUndefined()
  })

  it('emits page-change when next or previous clicked', async () => {
    const wrapper = mount(Pagination, {
      props: {
        meta: {
          current_page: 2,
          last_page: 5,
          per_page: 10,
          total: 50,
          from: 11,
          to: 20,
        },
      },
    })

    const buttons = wrapper.findAll('button')
    await buttons[0].trigger('click')
    expect(wrapper.emitted('page-change')?.[0]).toEqual([1])

    await buttons[1].trigger('click')
    expect(wrapper.emitted('page-change')?.[1]).toEqual([3])
  })

  it('disables previous button on first page', () => {
    const wrapper = mount(Pagination, {
      props: {
        meta: {
          current_page: 1,
          last_page: 3,
          per_page: 10,
          total: 30,
          from: 1,
          to: 10,
        },
      },
    })

    const buttons = wrapper.findAll('button')
    expect(buttons[0].attributes('disabled')).toBeDefined()
  })
})

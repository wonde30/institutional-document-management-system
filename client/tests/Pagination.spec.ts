import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import Pagination from '@/components/Pagination.vue'

describe('Pagination Component', () => {
  it('renders pagination info and page number buttons from meta prop', () => {
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

    // Updated format: "Showing 11–20 of 50"
    expect(wrapper.text()).toContain('11')
    expect(wrapper.text()).toContain('20')
    expect(wrapper.text()).toContain('50')

    // Should now have Prev + 5 page number buttons + Next = 7 buttons
    const buttons = wrapper.findAll('button')
    expect(buttons.length).toBeGreaterThanOrEqual(7) // Prev + pages 1-5 + Next

    // Find Prev and Next by aria-label
    const prevBtn = wrapper.find('button[aria-label="Previous page"]')
    const nextBtn = wrapper.find('button[aria-label="Next page"]')
    expect(prevBtn.exists()).toBe(true)
    expect(nextBtn.exists()).toBe(true)
    // On page 2 of 5, both should be enabled
    expect(prevBtn.attributes('disabled')).toBeUndefined()
    expect(nextBtn.attributes('disabled')).toBeUndefined()
  })

  it('emits page-change when previous and next are clicked', async () => {
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

    const prevBtn = wrapper.find('button[aria-label="Previous page"]')
    const nextBtn = wrapper.find('button[aria-label="Next page"]')

    await prevBtn.trigger('click')
    expect(wrapper.emitted('page-change')?.[0]).toEqual([1])

    await nextBtn.trigger('click')
    expect(wrapper.emitted('page-change')?.[1]).toEqual([3])
  })

  it('emits page-change when a page number button is clicked', async () => {
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

    // Find page 4 button
    const page4Btn = wrapper.find('button[aria-label="Page 4"]')
    expect(page4Btn.exists()).toBe(true)
    await page4Btn.trigger('click')
    expect(wrapper.emitted('page-change')?.[0]).toEqual([4])
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

    const prevBtn = wrapper.find('button[aria-label="Previous page"]')
    expect(prevBtn.attributes('disabled')).toBeDefined()
  })

  it('disables next button on last page', () => {
    const wrapper = mount(Pagination, {
      props: {
        meta: {
          current_page: 3,
          last_page: 3,
          per_page: 10,
          total: 30,
          from: 21,
          to: 30,
        },
      },
    })

    const nextBtn = wrapper.find('button[aria-label="Next page"]')
    expect(nextBtn.attributes('disabled')).toBeDefined()
  })
})

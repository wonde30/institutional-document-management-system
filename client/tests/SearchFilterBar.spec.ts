import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import SearchFilterBar from '@/components/data-display/SearchFilterBar.vue'

describe('SearchFilterBar.vue', () => {
  it('renders search input with custom placeholder and searchPlaceholder', () => {
    const wrapper = mount(SearchFilterBar, {
      props: {
        searchPlaceholder: 'Search document archives...',
      },
    })

    const input = wrapper.find('input[type="text"]')
    expect(input.exists()).toBe(true)
    expect(input.attributes('placeholder')).toBe('Search document archives...')
  })

  it('renders filter slots and clear button when query exists', async () => {
    const wrapper = mount(SearchFilterBar, {
      props: {
        modelValue: 'Curriculum',
        activeFiltersCount: 1,
      },
      slots: {
        filters: '<select><option value="approved">Approved</option></select>',
      },
    })

    expect(wrapper.find('select').exists()).toBe(true)
    expect(wrapper.text()).toContain('Reset')

    const clearBtn = wrapper.find('button[aria-label="Clear search"]')
    expect(clearBtn.exists()).toBe(true)
    await clearBtn.trigger('click')

    expect(wrapper.emitted('clear')).toBeTruthy()
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([''])
  })

  it('renders filter-groups and emits filter event upon selection change', async () => {
    const filterGroups = [
      {
        key: 'status',
        label: 'Status',
        options: [
          { label: 'All Statuses', value: '' },
          { label: 'Active', value: 'active' },
          { label: 'Archived', value: 'archived' },
        ],
      },
    ]

    const wrapper = mount(SearchFilterBar, {
      props: {
        filterGroups,
      },
    })

    const select = wrapper.find('select')
    expect(select.exists()).toBe(true)
    await select.setValue('active')

    expect(wrapper.emitted('filter')).toBeTruthy()
    expect(wrapper.emitted('filter')?.[0]).toEqual([{ status: 'active' }])
  })
})

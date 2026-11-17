import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import DataTable from '@/components/data-display/DataTable.vue'

describe('DataTable.vue', () => {
  const columns = [
    { key: 'id', label: 'ID', sortable: true },
    { key: 'title', label: 'Title', sortable: true },
    { key: 'category', label: 'Category' },
  ]

  const items = [
    { id: 1, title: 'Senate Resolution 01', category: 'Council' },
    { id: 2, title: 'Curriculum Review Plan', category: 'Academic' },
  ]

  it('renders columns and data rows correctly', () => {
    const wrapper = mount(DataTable, {
      props: {
        columns,
        items,
        total: 2,
      },
    })

    expect(wrapper.text()).toContain('Senate Resolution 01')
    expect(wrapper.text()).toContain('Curriculum Review Plan')
    expect(wrapper.text()).toContain('Council')
    expect(wrapper.text()).toContain('Academic')
  })

  it('renders empty state when items array is empty', () => {
    const wrapper = mount(DataTable, {
      props: {
        columns,
        items: [],
        total: 0,
        emptyTitle: 'No Records Recorded',
        emptyDescription: 'Zero records matched criteria.',
      },
    })

    expect(wrapper.text()).toContain('No Records Recorded')
    expect(wrapper.text()).toContain('Zero records matched criteria.')
  })

  it('emits sort event when sortable header is clicked', async () => {
    const wrapper = mount(DataTable, {
      props: {
        columns,
        items,
        total: 2,
        sortBy: 'title',
        sortOrder: 'asc',
      },
    })

    const titleHeader = wrapper.findAll('th').find((th) => th.text().includes('Title'))
    expect(titleHeader).toBeDefined()
    await titleHeader?.trigger('click')

    expect(wrapper.emitted('sort')).toBeTruthy()
    expect(wrapper.emitted('sort')?.[0]).toEqual(['title'])
  })

  it('renders cell- prefixed scoped slots seamlessly', () => {
    const wrapper = mount(DataTable, {
      props: {
        columns,
        items,
        total: 2,
      },
      slots: {
        'cell-title': '<template #cell-title="{ item }"><span class="custom-cell">{{ item.title }} - Custom</span></template>',
      },
    })

    expect(wrapper.find('.custom-cell').exists()).toBe(true)
    expect(wrapper.text()).toContain('Senate Resolution 01 - Custom')
  })

  it('does not render duplicate actions header when actions column is defined in columns prop', () => {
    const colsWithActions = [
      ...columns,
      { key: 'actions', label: 'Actions' },
    ]

    const wrapper = mount(DataTable, {
      props: {
        columns: colsWithActions,
        items,
        total: 2,
      },
      slots: {
        actions: '<button class="act-btn">Edit</button>',
      },
    })

    const thHeaders = wrapper.findAll('th').filter((th) => th.text().trim() === 'Actions')
    expect(thHeaders.length).toBe(1)
  })

  it('renders built-in pagination and emits page-change event', async () => {
    const wrapper = mount(DataTable, {
      props: {
        columns,
        items,
        total: 50,
        page: 1,
        perPage: 10,
      },
    })

    // Updated format: "Showing 1–10 of 50" (em-dash separator)
    const text = wrapper.text()
    expect(text).toContain('50')
    expect(text).toContain('10')

    // aria-label uses lowercase 'page' now
    const nextBtn = wrapper.find('button[aria-label="Next page"]')
    expect(nextBtn.exists()).toBe(true)
    await nextBtn.trigger('click')

    expect(wrapper.emitted('page-change')).toBeTruthy()
    expect(wrapper.emitted('page-change')?.[0]).toEqual([2])
  })
})

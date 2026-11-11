import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'

const {
  datasets,
  imports,
  exports,
  inspectImport,
  executeImport,
  discardImport,
  generateExport,
} = vi.hoisted(() => ({
  datasets: vi.fn(),
  imports: vi.fn(),
  exports: vi.fn(),
  inspectImport: vi.fn(),
  executeImport: vi.fn(),
  discardImport: vi.fn(),
  generateExport: vi.fn(),
}))

vi.mock('@/api/client', () => ({
  dataTransferApi: {
    datasets,
    imports,
    exports,
    inspectImport,
    executeImport,
    discardImport,
    generateExport,
    exportDownloadUrl: (id: number) => `/api/v1/data/exports/${id}/download`,
    importErrorsUrl: (id: number) => `/api/v1/data/imports/${id}/errors`,
  },
  ensureCsrfCookie: vi.fn().mockResolvedValue(undefined),
}))

import DataImportsExportsView from '@/views/admin/DataImportsExportsView.vue'
import { useAuthStore } from '@/stores/auth'
import type { DataDataset } from '@/types'

const catalogue: DataDataset[] = [
  {
    key: 'organization_units',
    label: 'Organizational Units',
    description: 'Creates organizational units beneath the active organizational unit.',
    direction: 'import',
    format: 'csv',
    permission: 'organization.manage',
    scope: 'active_unit',
    fields: ['code', 'name', 'unit_type_code'],
    required_headers: ['code', 'name', 'unit_type_code'],
    filters: [],
    max_rows: 2000,
  },
  {
    key: 'document_catalog',
    label: 'Document Catalogue',
    description: 'Document metadata.',
    direction: 'export',
    format: 'csv',
    permission: 'documents.view',
    scope: 'permitted_units',
    fields: ['reference_number', 'title'],
    required_headers: [],
    filters: ['lifecycle', 'date_from'],
    max_rows: 10000,
  },
]

function inspectionPayload(overrides: Record<string, unknown> = {}) {
  return {
    id: 7,
    dataset: 'organization_units',
    status: 'validated',
    format: 'csv',
    original_filename: 'dataset.csv',
    scope_unit_id: 1,
    scope_unit: 'Wollo University',
    total_rows: 3,
    valid_rows: 2,
    invalid_rows: 1,
    duplicate_rows: 0,
    imported_rows: 0,
    failed_rows: 0,
    failure_reason: null,
    started_at: null,
    completed_at: null,
    created_at: '2026-11-11T09:00:00Z',
    executable: true,
    errors: [
      {
        row_number: 3,
        column_name: 'code',
        code: 'required',
        message: 'The code column is required.',
      },
    ],
    ...overrides,
  }
}

describe('Data imports and exports screen', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    datasets.mockReset()
    imports.mockReset()
    exports.mockReset()
    inspectImport.mockReset()
    executeImport.mockReset()
    discardImport.mockReset()
    generateExport.mockReset()

    datasets.mockResolvedValue({
      data: {
        success: true,
        data: catalogue,
        meta: { max_import_kilobytes: 20480 },
      },
    })
    imports.mockResolvedValue({ data: { success: true, data: [] } })
    exports.mockResolvedValue({ data: { success: true, data: [] } })
  })

  async function mountView() {
    const wrapper = mount(DataImportsExportsView, {
      global: { stubs: { RouterLink: true } },
    })
    await flushPromises()
    return wrapper
  }

  it('loads the supported dataset catalogue and the operation history', async () => {
    const wrapper = await mountView()

    expect(datasets).toHaveBeenCalledTimes(1)
    expect(imports).toHaveBeenCalledTimes(1)
    expect(exports).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('Batch Data Ingestion')
  })

  it('shows the required columns of the selected import dataset', async () => {
    const wrapper = await mountView()

    expect(wrapper.text()).toContain('Required columns: code, name, unit_type_code')
  })

  it('inspects the uploaded file and presents the validation preview', async () => {
    inspectImport.mockResolvedValue({
      data: { success: true, data: inspectionPayload() },
    })

    const wrapper = await mountView()

    const file = new File(['code,name,unit_type_code\n'], 'dataset.csv', { type: 'text/csv' })
    const input = wrapper.find('#import-file')
    Object.defineProperty(input.element, 'files', { value: [file] })
    await input.trigger('change')
    await flushPromises()

    const inspectButton = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Inspect file'))
    await inspectButton!.trigger('click')
    await flushPromises()

    expect(inspectImport).toHaveBeenCalledTimes(1)

    // The preview shows the counts and the row level failure.
    expect(wrapper.text()).toContain('Validation preview')
    expect(wrapper.text()).toContain('Total records')
    expect(wrapper.text()).toContain('The code column is required.')
  })

  it('surfaces an upload failure without claiming a preview', async () => {
    inspectImport.mockRejectedValue({
      response: { data: { message: 'Only CSV dataset files are supported.' } },
    })

    const wrapper = await mountView()

    const file = new File(['x'], 'dataset.xlsx', { type: 'application/vnd.ms-excel' })
    const input = wrapper.find('#import-file')
    Object.defineProperty(input.element, 'files', { value: [file] })
    await input.trigger('change')
    await flushPromises()

    const inspectButton = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Inspect file'))
    await inspectButton!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Only CSV dataset files are supported.')
    expect(wrapper.text()).not.toContain('Validation preview')
  })

  it('executes the import only after the preview was accepted', async () => {
    inspectImport.mockResolvedValue({
      data: { success: true, data: inspectionPayload() },
    })
    executeImport.mockResolvedValue({
      data: { success: true, data: inspectionPayload({ status: 'completed', imported_rows: 2 }) },
    })

    const wrapper = await mountView()

    const file = new File(['code,name,unit_type_code\n'], 'dataset.csv', { type: 'text/csv' })
    const input = wrapper.find('#import-file')
    Object.defineProperty(input.element, 'files', { value: [file] })
    await input.trigger('change')
    await flushPromises()

    const inspectButton = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Inspect file'))
    await inspectButton!.trigger('click')
    await flushPromises()

    expect(executeImport).not.toHaveBeenCalled()

    const executeButton = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Import 2 record(s)'))
    await executeButton!.trigger('click')
    await flushPromises()

    expect(executeImport).toHaveBeenCalledWith(7)
    expect(wrapper.text()).toContain('Import completed')
  })

  it('generates an export with the applied filters', async () => {
    generateExport.mockResolvedValue({
      data: {
        success: true,
        data: {
          id: 3,
          dataset: 'document_catalog',
          status: 'completed',
          format: 'csv',
          filters: { lifecycle: 'active' },
          scope_unit_id: null,
          scope_unit: null,
          record_count: 12,
          file_size: 2048,
          failure_reason: null,
          started_at: null,
          completed_at: null,
          expires_at: '2026-11-12T09:00:00Z',
          created_at: '2026-11-11T09:00:00Z',
          retrievable: true,
        },
      },
    })

    const wrapper = await mountView()

    const exportTab = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Authorized Data Exports'))
    await exportTab!.trigger('click')
    await flushPromises()

    const generateButton = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Generate export'))
    await generateButton!.trigger('click')
    await flushPromises()

    expect(generateExport).toHaveBeenCalledTimes(1)
    expect(generateExport.mock.calls[0][0]).toMatchObject({ dataset: 'document_catalog' })
  })

  it('reports a failure to load the module', async () => {
    datasets.mockRejectedValueOnce({
      response: { status: 500, data: { message: 'The data operations could not be loaded.' } },
    })

    const wrapper = await mountView()

    expect(wrapper.text()).toContain('The data operations could not be loaded.')
    expect(wrapper.text()).toContain('Retry')
  })

  it('reports a forbidden module without offering the workflow', async () => {
    datasets.mockRejectedValueOnce({
      response: { status: 403, data: { message: 'You are not authorized to use the data operations module.' } },
    })

    const wrapper = await mountView()

    expect(wrapper.text()).toContain('You are not authorized to use the data operations module.')
    expect(wrapper.text()).not.toContain('Inspect file')
  })

  it('shows an empty state when no operation has been run', async () => {
    const wrapper = await mountView()

    expect(wrapper.text()).toContain('No import has been run')
  })
})

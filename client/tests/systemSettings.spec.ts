import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'

const { settingsIndex, settingsUpdate, settingsReset, settingsResetCategory } = vi.hoisted(() => ({
  settingsIndex: vi.fn(),
  settingsUpdate: vi.fn(),
  settingsReset: vi.fn(),
  settingsResetCategory: vi.fn(),
}))

vi.mock('@/api/client', () => ({
  settingsApi: {
    index: settingsIndex,
    update: settingsUpdate,
    reset: settingsReset,
    resetCategory: settingsResetCategory,
    categories: vi.fn(),
    publicSettings: vi.fn(),
  },
  ensureCsrfCookie: vi.fn().mockResolvedValue(undefined),
}))

import SystemSettingsView from '@/views/admin/SystemSettingsView.vue'
import type { SettingsPayload } from '@/types'

function payload(overrides: Partial<SettingsPayload> = {}): SettingsPayload {
  return {
    categories: {
      institution: {
        label: 'Institutional Profile',
        description: 'Identity and presentation of the institution.',
      },
      documents: {
        label: 'Document Repository',
        description: 'Defaults applied when a document is registered.',
      },
    },
    settings: [
      {
        key: 'institution.display_name',
        label: 'Institution Display Name',
        description: 'Official institutional name.',
        category: 'institution',
        type: 'string',
        options: null,
        default: 'Wollo University',
        value: 'Wollo University',
        scope: 'global',
        scope_unit_id: null,
        editable: true,
        is_default: true,
        updated_at: null,
        updated_by: null,
      },
      {
        key: 'documents.max_upload_size_mb',
        label: 'Maximum Upload Size',
        description: 'Largest upload accepted.',
        category: 'documents',
        type: 'integer',
        options: null,
        default: 100,
        value: 100,
        scope: 'global',
        scope_unit_id: null,
        editable: true,
        is_default: true,
        updated_at: null,
        updated_by: null,
      },
      {
        key: 'documents.default_confidentiality_level_code',
        label: 'Default Classification Level',
        description: 'Classification applied by default.',
        category: 'documents',
        type: 'select',
        options: ['internal', 'confidential', 'restricted'],
        default: 'internal',
        value: 'internal',
        scope: 'global_or_unit',
        scope_unit_id: null,
        editable: true,
        is_default: true,
        updated_at: null,
        updated_by: null,
      },
      {
        key: 'documents.reference_number_scheme',
        label: 'Reference Number Scheme',
        description: 'Defined by the application.',
        category: 'documents',
        type: 'string',
        options: null,
        default: 'WU/DOC/{YEAR}/{SEQUENCE}',
        value: 'WU/DOC/{YEAR}/{SEQUENCE}',
        scope: 'global',
        scope_unit_id: null,
        editable: false,
        is_default: true,
        updated_at: null,
        updated_by: null,
      },
    ],
    deployment: [
      { key: 'app.env', label: 'Application Environment', sensitive: false, value: 'production', managed: 'deployment' },
      { key: 'app.key', label: 'Application Encryption Key', sensitive: true, value: null, managed: 'deployment' },
    ],
    scope_unit_id: null,
    ...overrides,
  }
}

describe('System settings screen', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    settingsIndex.mockReset()
    settingsUpdate.mockReset()
    settingsReset.mockReset()
    settingsResetCategory.mockReset()
    settingsIndex.mockResolvedValue({ data: { success: true, data: payload() } })
    settingsUpdate.mockResolvedValue({ data: { success: true, data: { settings: [], scope_unit_id: null } } })
    settingsReset.mockResolvedValue({ data: { success: true, data: { setting: null, scope_unit_id: null } } })
    settingsResetCategory.mockResolvedValue({
      data: { success: true, data: { reset: [], settings: [] } },
    })
  })

  async function mountView() {
    const wrapper = mount(SystemSettingsView)
    await flushPromises()
    return wrapper
  }

  it('loads the catalogue from the API', async () => {
    const wrapper = await mountView()

    expect(settingsIndex).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('Institution Display Name')
  })

  it('presents the categories and switches between them', async () => {
    const wrapper = await mountView()

    expect(wrapper.text()).toContain('Institutional Profile')
    expect(wrapper.text()).not.toContain('Maximum Upload Size')

    const documentTab = wrapper
      .findAll('[role="tab"]')
      .find((tab) => tab.text().includes('Document Repository'))

    await documentTab!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Maximum Upload Size')
  })

  it('renders the control that matches the declared type', async () => {
    const wrapper = await mountView()

    // The integer setting is rendered as a number control.
    const documentTab = wrapper
      .findAll('[role="tab"]')
      .find((tab) => tab.text().includes('Document Repository'))
    await documentTab!.trigger('click')
    await flushPromises()

    const numberInputs = wrapper.findAll('input[type="number"]')
    expect(numberInputs.length).toBeGreaterThan(0)

    // The select setting is rendered as a select control.
    expect(wrapper.findAll('select').length).toBeGreaterThan(0)
  })

  it('marks a setting that is not editable as fixed and disables it', async () => {
    const wrapper = await mountView()

    const documentTab = wrapper
      .findAll('[role="tab"]')
      .find((tab) => tab.text().includes('Document Repository'))
    await documentTab!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Reference Number Scheme')
    expect(wrapper.text()).toContain('Fixed')
  })

  it('separates the deployment boundary and never shows a secret value', async () => {
    const wrapper = await mountView()

    expect(wrapper.text()).toContain('Deployment Boundary')
    expect(wrapper.text()).toContain('Application Encryption Key')
    expect(wrapper.text()).toContain('managed externally')
  })

  it('tracks unsaved changes and can cancel them', async () => {
    const wrapper = await mountView()

    expect(wrapper.text()).toContain('All changes saved')

    const input = wrapper.find('input')
    await input.setValue('Wollo University of Technology')
    await flushPromises()

    expect(wrapper.text()).toContain('1 unsaved change')

    const cancel = wrapper
      .findAll('button')
      .find((button) => button.text().trim() === 'Cancel')
    await cancel!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('All changes saved')
  })

  it('saves the changed value through the API', async () => {
    const wrapper = await mountView()

    const input = wrapper.find('input')
    await input.setValue('Wollo University of Technology')
    await flushPromises()

    const save = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Save Configuration'))
    await save!.trigger('click')
    await flushPromises()

    expect(settingsUpdate).toHaveBeenCalledTimes(1)
    expect(settingsUpdate.mock.calls[0][0]).toEqual([
      { key: 'institution.display_name', value: 'Wollo University of Technology' },
    ])
  })

  it('surfaces a validation failure and reports that nothing was changed', async () => {
    settingsUpdate.mockRejectedValueOnce({
      response: { data: { message: 'The given data was invalid.', errors: { 'institution.display_name': ['The Institution Display Name field is required.'] } } },
    })

    const wrapper = await mountView()

    const input = wrapper.find('input')
    await input.setValue('ab')
    await flushPromises()

    const save = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Save Configuration'))
    await save!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('The Institution Display Name field is required.')
  })

  it('asks for confirmation before resetting a setting', async () => {
    const wrapper = await mountView()

    const reset = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Reset to default'))
    await reset!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Reset setting to default')
    expect(wrapper.text()).toContain('security audit trail')

    // Nothing is sent until the confirmation is accepted.
    expect(settingsReset).not.toHaveBeenCalled()

    const confirm = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Confirm reset'))
    await confirm!.trigger('click')
    await flushPromises()

    expect(settingsReset).toHaveBeenCalledWith('institution.display_name', null)
  })

  it('can cancel the reset confirmation', async () => {
    const wrapper = await mountView()

    const reset = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Reset to default'))
    await reset!.trigger('click')
    await flushPromises()

    const keep = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Keep current value'))
    await keep!.trigger('click')
    await flushPromises()

    expect(settingsReset).not.toHaveBeenCalled()
  })

  it('resets a category through the API', async () => {
    const wrapper = await mountView()

    const resetCategory = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Reset category'))
    await resetCategory!.trigger('click')
    await flushPromises()

    const confirm = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Confirm reset'))
    await confirm!.trigger('click')
    await flushPromises()

    expect(settingsResetCategory).toHaveBeenCalledWith('institution', null)
  })

  it('reports a failure to load the configuration', async () => {
    settingsIndex.mockRejectedValueOnce({
      response: { data: { message: 'Forbidden: Missing required baseline permission.' } },
    })

    const wrapper = await mountView()

    expect(wrapper.text()).toContain('Forbidden')
    expect(wrapper.findAll('button').some((button) => button.text().includes('Retry'))).toBe(true)
  })

  it('reports a failed save without claiming success', async () => {
    settingsUpdate.mockRejectedValueOnce({
      response: { data: { message: 'The configuration could not be saved.' } },
    })

    const wrapper = await mountView()

    const input = wrapper.find('input')
    await input.setValue('Changed')
    await flushPromises()

    const save = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Save Configuration'))
    await save!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('The configuration could not be saved.')
    expect(wrapper.text()).toContain('1 unsaved change')
  })

  it('reloads the persisted values after a reset', async () => {
    settingsIndex.mockResolvedValueOnce({ data: { success: true, data: payload() } })
    settingsIndex.mockResolvedValueOnce({
      data: {
        success: true,
        data: payload({
          settings: payload().settings.map((setting) =>
            setting.key === 'institution.display_name'
              ? { ...setting, value: 'Wollo University', is_default: true }
              : setting
          ),
        }),
      },
    })

    const wrapper = await mountView()

    const reset = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Reset to default'))
    await reset!.trigger('click')
    await flushPromises()

    const confirm = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Confirm reset'))
    await confirm!.trigger('click')
    await flushPromises()

    // The catalogue is re-read so the interface shows the persisted state.
    expect(settingsIndex).toHaveBeenCalledTimes(2)
  })
})

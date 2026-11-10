import { describe, it, expect } from 'vitest'
import {
  formatDate,
  formatFileSize,
  getStatusBadgeClass,
  getPriorityBadgeClass,
  getClassificationBadge,
} from '@/utils/formatters'

describe('Formatters Utilities', () => {
  it('formats dates properly', () => {
    expect(formatDate(null)).toBe('N/A')
    expect(formatDate(undefined)).toBe('N/A')
    const formatted = formatDate('2026-10-24T00:00:00Z')
    expect(formatted).toContain('2026')
  })

  it('formats file sizes into human-readable strings', () => {
    expect(formatFileSize(0)).toBe('0 B')
    expect(formatFileSize(1024)).toBe('1 KB')
    expect(formatFileSize(1048576)).toBe('1 MB')
    expect(formatFileSize(5242880)).toBe('5 MB')
  })

  it('maps lifecycle statuses to correct CSS classes', () => {
    expect(getStatusBadgeClass('approved')).toBe('badge-success')
    expect(getStatusBadgeClass('pending_review')).toBe('badge-warning')
    expect(getStatusBadgeClass('rejected')).toBe('badge-danger')
    expect(getStatusBadgeClass('archived')).toBe('badge-neutral')
    expect(getStatusBadgeClass('draft')).toBe('badge-info')
  })

  it('maps priorities to correct CSS classes', () => {
    expect(getPriorityBadgeClass('urgent')).toBe('badge-urgent')
    expect(getPriorityBadgeClass('high')).toBe('badge-high')
    expect(getPriorityBadgeClass('normal')).toBe('badge-normal')
    expect(getPriorityBadgeClass('low')).toBe('badge-low')
  })

  it('maps numeric clearance levels to badges', () => {
    expect(getClassificationBadge(4)).toEqual({ label: 'Restricted', className: 'badge-restricted' })
    expect(getClassificationBadge(3)).toEqual({ label: 'Confidential', className: 'badge-confidential' })
    expect(getClassificationBadge(2)).toEqual({ label: 'Internal', className: 'badge-internal' })
    expect(getClassificationBadge(1)).toEqual({ label: 'Public', className: 'badge-public' })
  })
})

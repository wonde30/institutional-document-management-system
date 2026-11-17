export function formatDate(dateStr?: string | null): string {
  if (!dateStr) return 'N/A'
  try {
    const d = new Date(dateStr)
    return isNaN(d.getTime()) ? dateStr : d.toLocaleDateString('en-GB', { year: 'numeric', month: 'short', day: 'numeric' })
  } catch {
    return dateStr
  }
}

export function formatDateTime(dateStr?: string | null): string {
  if (!dateStr) return 'N/A'
  try {
    const d = new Date(dateStr)
    return isNaN(d.getTime())
      ? dateStr
      : d.toLocaleDateString('en-GB', {
          year: 'numeric',
          month: 'short',
          day: 'numeric',
          hour: '2-digit',
          minute: '2-digit',
        })
  } catch {
    return dateStr
  }
}

export function formatFileSize(bytes?: number): string {
  if (!bytes || bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`
}

/**
 * Format snake_case status strings to Sentence case for display
 * @example formatStatus('pending_review') => 'Pending review'
 * @example formatStatus('accepted') => 'Accepted'
 */
export function formatStatus(status?: string | null): string {
  if (!status) return 'Unknown'
  return status
    .split('_')
    .map((word, index) => {
      if (index === 0) {
        return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
      }
      return word.toLowerCase()
    })
    .join(' ')
}

export function getStatusBadgeClass(status?: string): string {
  switch (status?.toLowerCase()) {
    case 'approved':
    case 'completed':
    case 'dispatched':
    case 'executed':
    case 'acknowledged':
      return 'badge-success'
    case 'pending_review':
    case 'under_review':
    case 'assigned':
    case 'in_progress':
    case 'pending':
      return 'badge-warning'
    case 'rejected':
    case 'overdue':
    case 'cancelled':
      return 'badge-danger'
    case 'archived':
    case 'disposed':
      return 'badge-neutral'
    case 'draft':
    case 'received':
    case 'sent':
    default:
      return 'badge-info'
  }
}

export function getPriorityBadgeClass(priority?: string): string {
  switch (priority?.toLowerCase()) {
    case 'urgent':
      return 'badge-urgent'
    case 'high':
      return 'badge-high'
    case 'normal':
      return 'badge-normal'
    case 'low':
    default:
      return 'badge-low'
  }
}

export function getClassificationBadge(level?: number): { label: string; className: string } {
  switch (level) {
    case 4:
      return { label: 'Restricted', className: 'badge-restricted' }
    case 3:
      return { label: 'Confidential', className: 'badge-confidential' }
    case 2:
      return { label: 'Internal', className: 'badge-internal' }
    case 1:
    default:
      return { label: 'Public', className: 'badge-public' }
  }
}

export function downloadBlob(blob: Blob, filename: string): void {
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', filename)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  window.URL.revokeObjectURL(url)
}

export function downloadBlobResponse(
  response: { data: BlobPart; headers?: Record<string, string> },
  fallbackFilename: string
): void {
  const blob = new Blob([response.data], {
    type: response.headers?.['content-type'] || 'application/octet-stream',
  })
  downloadBlob(blob, fallbackFilename)
}

import axios from 'axios'
import { emitForbidden, emitUnauthorized } from '@/api/sessionEvents'
import type {
  ApiResponse,
  PaginatedResponse,
  User,
  OrganizationUnit,
  UnitType,
  RoleItem,
  PermissionItem,
  AuthorizationScope,
  Document,
  DocumentCategory,
  ConfidentialityLevel,
  Correspondence,
  CorrespondenceType,
  Priority,
  DispatchMethod,
  DocumentMovement,
  DocumentAssignment,
  PhysicalOriginal,
  TimelineEvent,
  WorkflowDefinition,
  WorkflowTask,
  ArchiveCollection,
  RetentionPolicy,
  ArchiveEntry,
  LegalHold,
  AuditEvent,
  UserNotification,
  DashboardMetrics,
  ReportsSummary,
  AccreditationFramework,
  AccreditationStandard,
  AccreditationCycle,
  AccreditationEvidence,
  AccreditationReview,
  AccreditationCycleSummary,
  ProjectType,
  ProjectProposal,
  ResearchRecord,
  Project,
  SignatureRequestItem,
  StampDefinitionItem,
  DigitalSignatureItem,
  SigningIdentityItem,
  OperationsStatusData,
  OrganizationalContext,
  SettingCategory,
  SettingDefinition,
  SettingsPayload,
  DataDataset,
  DataExportOperation,
  DataImportOperation,
  PermissionLineageData,
} from '@/types'

export const apiClient = axios.create({
  baseURL: '/api/v1',
  timeout: 20000,
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

let csrfPromise: Promise<void> | null = null

export async function ensureCsrfCookie(): Promise<void> {
  if (typeof window === 'undefined') return
  if (!csrfPromise) {
    csrfPromise = axios
      .get('/sanctum/csrf-cookie', { withCredentials: true })
      .then(() => {})
      .catch((err) => {
        if (import.meta.env.DEV) {
          console.warn('Sanctum CSRF cookie acquisition failed:', err.message)
        }
      })
      .finally(() => {
        csrfPromise = null
      })
  }
  return csrfPromise
}

// Request Interceptor: Ensure CSRF Cookie for Mutating Requests (Cookie-Based SPA Session)
apiClient.interceptors.request.use(async (config) => {
  const method = (config.method || 'get').toLowerCase()
  if (['post', 'put', 'patch', 'delete'].includes(method) && typeof window !== 'undefined') {
    await ensureCsrfCookie()
  }
  return config
})

// Response Interceptor: 401 Session Handling, 419 CSRF Recovery & 403 Forbidden
apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config
    if (error.response?.status === 419 && originalRequest && !originalRequest._retry) {
      originalRequest._retry = true
      await ensureCsrfCookie()
      return apiClient(originalRequest)
    }

    if (error.response?.status === 401) {
      // The server session is no longer valid. The in-memory authentication
      // state must be invalidated as well, otherwise the application shell
      // keeps rendering an authenticated workspace for a dead session.
      emitUnauthorized()
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      const publicPaths = ['/', '/login', '/privacy', '/privacy-policy', '/terms', '/forgot-password', '/reset-password', '/verify-email']
      const currentPath = typeof window !== 'undefined' ? window.location.pathname : ''
      const isPublicPath = publicPaths.includes(currentPath)

      // Do not force-redirect on 401 if visitor is on a public page or initial auth bootstrap check
      if (typeof window !== 'undefined' && !isPublicPath && originalRequest?.url !== '/auth/me') {
        const redirect = encodeURIComponent(window.location.pathname + window.location.search)
        window.location.href = `/login?redirect=${redirect}`
      }
    }

    if (error.response?.status === 403) {
      // Authorization denial. Surface it without tearing down the session.
      const message =
        (error.response?.data as { message?: string } | undefined)?.message ||
        'You are not authorized to perform this action.'
      emitForbidden(message)
    }

    return Promise.reject(error)
  }
)

// ----------------------------------------------------------------------------
// 1. Authentication API
// ----------------------------------------------------------------------------
export const authApi = {
  login: (credentials: { email: string; password: string }) =>
    apiClient.post<ApiResponse<User>>('/auth/login', credentials),
  logout: () => apiClient.post<ApiResponse<void>>('/auth/logout'),
  me: () => apiClient.get<ApiResponse<User>>('/auth/me'),
  changePassword: (data: { current_password: string; new_password: string }) =>
    apiClient.put<ApiResponse<void>>('/auth/password', data),
  forgotPassword: (data: { email: string }) =>
    apiClient.post<ApiResponse<void>>('/auth/forgot-password', data),
  resetPassword: (data: {
    token: string
    email: string
    password: string
    password_confirmation: string
  }) => apiClient.post<ApiResponse<void>>('/auth/reset-password', data),
  sendVerificationEmail: () =>
    apiClient.post<ApiResponse<void>>('/auth/email/verification-notification'),
  verifyEmail: (id: number | string, hash: string) =>
    apiClient.get<ApiResponse<void>>(`/auth/verify-email/${id}/${hash}`),
  /**
   * Switch the active organizational unit.
   *
   * The server validates the requested unit against the units the caller may
   * select. The client never treats the selection as proof of authorization.
   */
  setActiveUnit: (unitId: number) =>
    apiClient.post<ApiResponse<OrganizationalContext & { context?: OrganizationalContext }>>(
      '/auth/active-unit',
      { unit_id: unitId }
    ),
}

// ----------------------------------------------------------------------------
// 2. Executive Dashboard & Reports API
// ----------------------------------------------------------------------------
export const dashboardApi = {
  getMetrics: () => apiClient.get<ApiResponse<DashboardMetrics>>('/dashboard/metrics'),
}

export const reportsApi = {
  getSummary: (params?: Record<string, unknown>) =>
    apiClient.get<ApiResponse<ReportsSummary>>('/reports/summary', { params }),
}

// ----------------------------------------------------------------------------
// 3. Reference Metadata API
// ----------------------------------------------------------------------------
export const metadataApi = {
  getAll: (params?: { include_inactive?: boolean }) => apiClient.get<ApiResponse<{
    document_categories: DocumentCategory[]
    confidentiality_levels: ConfidentialityLevel[]
    correspondence_types: CorrespondenceType[]
    priorities: Priority[]
    dispatch_methods: DispatchMethod[]
  }>>('/metadata/all', { params }),
  getCategories: () => apiClient.get<ApiResponse<DocumentCategory[]>>('/metadata/categories'),
  getConfidentialityLevels: () => apiClient.get<ApiResponse<ConfidentialityLevel[]>>('/metadata/confidentiality-levels'),
  getCorrespondenceTypes: () => apiClient.get<ApiResponse<CorrespondenceType[]>>('/metadata/correspondence-types'),
  getPriorities: () => apiClient.get<ApiResponse<Priority[]>>('/metadata/priorities'),
  getDispatchMethods: () => apiClient.get<ApiResponse<DispatchMethod[]>>('/metadata/dispatch-methods'),
  createItem: (type: string, data: { code: string; name: string; description?: string; active?: boolean }) =>
    apiClient.post<ApiResponse<any>>(`/metadata/${type}`, data),
  updateItem: (type: string, id: number, data: { name?: string; description?: string; active?: boolean }) =>
    apiClient.put<ApiResponse<any>>(`/metadata/${type}/${id}`, data),
}

// ----------------------------------------------------------------------------
// 4. Organization Structure API
// ----------------------------------------------------------------------------
export const organizationUnitsApi = {
  list: (params?: Record<string, unknown>) =>
    apiClient.get<PaginatedResponse<OrganizationUnit>>('/organization-units', { params }),
  tree: () => apiClient.get<ApiResponse<OrganizationUnit[]>>('/organization-units/tree'),
  get: (id: number) => apiClient.get<ApiResponse<OrganizationUnit>>(`/organization-units/${id}`),
  create: (data: Partial<OrganizationUnit>) =>
    apiClient.post<ApiResponse<OrganizationUnit>>('/organization-units', data),
  update: (id: number, data: Partial<OrganizationUnit>) =>
    apiClient.put<ApiResponse<OrganizationUnit>>(`/organization-units/${id}`, data),
  unitTypes: () => apiClient.get<ApiResponse<UnitType[]>>('/unit-types'),
}

// ----------------------------------------------------------------------------
// 5. User Accounts & Memberships API
// ----------------------------------------------------------------------------
export const usersApi = {
  list: (params?: Record<string, unknown>) =>
    apiClient.get<PaginatedResponse<User>>('/users', { params }),
  get: (id: number) => apiClient.get<ApiResponse<User>>(`/users/${id}`),
  create: (data: Record<string, unknown>) => apiClient.post<ApiResponse<User>>('/users', data),
  update: (id: number, data: Record<string, unknown>) => apiClient.put<ApiResponse<User>>(`/users/${id}`, data),
  updateStatus: (id: number, status: 'active' | 'inactive' | 'locked') =>
    apiClient.patch<ApiResponse<User>>(`/users/${id}/status`, { status }),
  assignMembership: (id: number, data: { unit_id: number; position_title?: string; is_primary?: boolean }) =>
    apiClient.post<ApiResponse<unknown>>(`/users/${id}/memberships`, data),
  getEffectivePermissions: (id: number, unitId?: number | null) =>
    apiClient.get<ApiResponse<PermissionLineageData>>(`/users/${id}/effective-permissions`, {
      params: unitId ? { unit_id: unitId } : undefined,
    }),
}

// ----------------------------------------------------------------------------
// 6. Scoped Roles & RBAC API
// ----------------------------------------------------------------------------
export const rolesApi = {
  list: () => apiClient.get<ApiResponse<RoleItem[]>>('/roles'),
  get: (id: number) => apiClient.get<ApiResponse<RoleItem>>(`/roles/${id}`),
  create: (data: Partial<RoleItem> & { permission_ids?: number[] }) =>
    apiClient.post<ApiResponse<RoleItem>>('/roles', data),
  update: (id: number, data: Partial<RoleItem>) =>
    apiClient.put<ApiResponse<RoleItem>>(`/roles/${id}`, data),
  syncPermissions: (id: number, permissionIds: number[]) =>
    apiClient.post<ApiResponse<RoleItem>>(`/roles/${id}/permissions`, { permission_ids: permissionIds }),
  permissions: (params?: { all?: boolean }) => apiClient.get<ApiResponse<PermissionItem[]>>('/permissions', { params }),
  scopes: () => apiClient.get<ApiResponse<AuthorizationScope[]>>('/authorization-scopes'),
  getUsers: (id: number) =>
    apiClient.get<ApiResponse<{ role_id: number; role_code: string; role_name: string; total_users: number; users: any[] }>>(
      `/roles/${id}/users`
    ),
  assignUserRole: (data: {
    user_id: number
    role_id: number
    scope_kind: 'global' | 'unit' | 'subtree'
    unit_id?: number | null
    expires_at?: string | null
  }) => apiClient.post<ApiResponse<void>>('/user-roles', data),
  revokeUserRole: (data: { user_id: number; role_id: number; scope_id: number }) =>
    apiClient.delete<ApiResponse<void>>('/user-roles', { data }),
  assignUserPermission: (data: {
    user_id: number
    permission_id: number
    scope_kind: 'global' | 'unit' | 'subtree'
    unit_id?: number | null
    expires_at?: string | null
  }) => apiClient.post<ApiResponse<void>>('/user-permissions', data),
  revokeUserPermission: (data: { user_id: number; permission_id: number; scope_id: number }) =>
    apiClient.delete<ApiResponse<void>>('/user-permissions', { data }),
}

export const permissionsApi = {
  list: (params?: { all?: boolean }) => apiClient.get<ApiResponse<PermissionItem[]>>('/permissions', { params }),
  update: (id: number, data: { active?: boolean; description?: string }) =>
    apiClient.patch<ApiResponse<PermissionItem>>(`/permissions/${id}`, data),
}

// ----------------------------------------------------------------------------
// 7. Document Repository API
// ----------------------------------------------------------------------------
export const documentsApi = {
  list: (params?: Record<string, unknown>) =>
    apiClient.get<PaginatedResponse<Document>>('/documents', { params }),
  get: (id: number) => apiClient.get<ApiResponse<Document>>(`/documents/${id}`),
  create: (formData: FormData) =>
    apiClient.post<ApiResponse<Document>>('/documents', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
  update: (id: number, data: Partial<Document> & { lock_version: number }) =>
    apiClient.put<ApiResponse<Document>>(`/documents/${id}`, data),
  uploadVersion: (id: number, formData: FormData) =>
    apiClient.post<ApiResponse<unknown>>(`/documents/${id}/versions`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
  downloadFile: (fileId: number) =>
    apiClient.get(`/files/${fileId}/download`, { responseType: 'blob' }),
  timeline: (id: number) =>
    apiClient.get<ApiResponse<TimelineEvent[]>>(`/documents/${id}/timeline`),
  digitize: (id: number, formData: FormData) =>
    apiClient.post<ApiResponse<unknown>>(`/documents/${id}/digitize`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
  physicalOriginals: (id: number) =>
    apiClient.get<ApiResponse<PhysicalOriginal[]>>(`/documents/${id}/physical-originals`),
  storePhysicalOriginal: (id: number, data: Record<string, unknown>) =>
    apiClient.post<ApiResponse<PhysicalOriginal>>(`/documents/${id}/physical-originals`, data),
  movements: (id: number) =>
    apiClient.get<ApiResponse<DocumentMovement[]>>(`/documents/${id}/movements`),
  recordMovement: (
    id: number,
    data: { from_unit_id: number; to_unit_id: number; instruction?: string; due_at?: string }
  ) => apiClient.post<ApiResponse<DocumentMovement>>(`/documents/${id}/movements`, data),
  assignments: (id: number) =>
    apiClient.get<ApiResponse<DocumentAssignment[]>>(`/documents/${id}/assignments`),
  assign: (
    id: number,
    data: { assigned_user_id: number; assigned_unit_id: number; instruction: string; due_at?: string; participants?: any[] }
  ) => apiClient.post<ApiResponse<DocumentAssignment>>(`/documents/${id}/assignments`, data),
  compareVersions: (id: number, versionA: number, versionB: number) =>
    apiClient.get<ApiResponse<any>>(`/documents/${id}/compare-versions?version_a=${versionA}&version_b=${versionB}`),
  addRelation: (id: number, data: { target_document_id: number; relation_type?: string }) =>
    apiClient.post<ApiResponse<any>>(`/documents/${id}/relations`, data),
  removeRelation: (id: number, targetId: number) =>
    apiClient.delete<ApiResponse<any>>(`/documents/${id}/relations/${targetId}`),
  verifyFileIntegrity: (id: number, fileId: number) =>
    apiClient.post<ApiResponse<any>>(`/documents/${id}/files/${fileId}/verify-integrity`),
}

export const movementsApi = {
  acknowledge: (id: number) =>
    apiClient.post<ApiResponse<any>>(`/movements/${id}/acknowledge`),
}

export const assignmentsApi = {
  addParticipant: (id: number, data: { user_id: number; role_code: string }) =>
    apiClient.post<ApiResponse<any>>(`/assignments/${id}/participants`, data),
  removeParticipant: (id: number, userId: number) =>
    apiClient.delete<ApiResponse<any>>(`/assignments/${id}/participants/${userId}`),
  recordEvent: (id: number, data: { action: string; target_user_id?: number; comment?: string }) =>
    apiClient.post<ApiResponse<any>>(`/assignments/${id}/events`, data),
  linkResponse: (
    id: number,
    data: { response_document_id: number; relationship_role?: string; is_fulfilling?: boolean }
  ) => apiClient.post<ApiResponse<any>>(`/assignments/${id}/responses`, data),
}

export const templatesApi = {
  list: (params?: Record<string, unknown>) =>
    apiClient.get<PaginatedResponse<any>>('/templates', { params }),
  get: (id: number) =>
    apiClient.get<ApiResponse<any>>(`/templates/${id}`),
  create: (data: Record<string, unknown>) =>
    apiClient.post<ApiResponse<any>>('/templates', data),
  createVersion: (id: number, data: Record<string, unknown>) =>
    apiClient.post<ApiResponse<any>>(`/templates/${id}/versions`, data),
  publishVersion: (id: number, versionId: number) =>
    apiClient.post<ApiResponse<any>>(`/templates/${id}/versions/${versionId}/publish`),
  generateDocument: (id: number, data: Record<string, unknown>) =>
    apiClient.post<ApiResponse<any>>(`/templates/${id}/render`, data),
}

export const officialLettersApi = {
  baselineConfig: (unitId?: number) =>
    apiClient.get<ApiResponse<any>>('/official-letters/baseline-config', { params: { unit_id: unitId } }),
  preview: (data: Record<string, unknown>) =>
    apiClient.post<ApiResponse<{ pdf_base64: string; byte_size: number; mime_type: string }>>('/official-letters/preview', data),
  generate: (data: Record<string, unknown>) =>
    apiClient.post<ApiResponse<Document>>('/official-letters', data),
}

export const searchApi = {
  searchDocuments: (params: Record<string, unknown>) =>
    apiClient.get<PaginatedResponse<Document>>('/search/documents', { params }),
}

// ----------------------------------------------------------------------------
// 8. Correspondence Management API
// ----------------------------------------------------------------------------
export const correspondenceApi = {
  list: (params?: Record<string, unknown>) =>
    apiClient.get<PaginatedResponse<Correspondence>>('/correspondences', { params }),
  get: (id: number) => apiClient.get<ApiResponse<Correspondence>>(`/correspondences/${id}`),
  create: (data: Record<string, unknown> | FormData) =>
    apiClient.post<ApiResponse<Correspondence>>('/correspondences', data),
  recordMovement: (
    id: number,
    data: { from_unit_id: number; to_unit_id: number; instruction?: string; due_at?: string }
  ) => apiClient.post<ApiResponse<DocumentMovement>>(`/correspondences/${id}/movements`, data),
  assign: (
    id: number,
    data: { assigned_user_id: number; assigned_unit_id: number; instruction: string; due_at?: string }
  ) => apiClient.post<ApiResponse<DocumentAssignment>>(`/correspondences/${id}/assignments`, data),
  dispatch: (
    id: number,
    data: {
      method_id?: number
      dispatch_method_id?: number
      document_version_id?: number
      tracking_reference?: string
      tracking_number?: string
      recipient_party_id?: number
    }
  ) => apiClient.post<ApiResponse<any>>(`/correspondences/${id}/dispatch`, data),
  acknowledgeMovement: (id: number, movementId: number) =>
    apiClient.post<ApiResponse<any>>(`/correspondences/${id}/movements/${movementId}/acknowledge`),
  prepareLinkedResponse: (id: number, data: Record<string, unknown>) =>
    apiClient.post<ApiResponse<Correspondence>>(`/correspondences/${id}/linked-response`, data),
  close: (id: number, data?: { reason?: string }) =>
    apiClient.post<ApiResponse<Correspondence>>(`/correspondences/${id}/close`, data),
}

// ----------------------------------------------------------------------------
// 9. Workflow & Decision Routing API
// ----------------------------------------------------------------------------
export const workflowApi = {
  definitions: () => apiClient.get<ApiResponse<WorkflowDefinition[]>>('/workflows/definitions'),
  tasks: (params?: { scope?: 'mine' | 'office' | 'all'; status?: 'open' | 'closed' | 'all'; page?: number; per_page?: number }) =>
    apiClient.get<PaginatedResponse<WorkflowTask>>('/workflows/tasks', { params }),
  startInstance: (data: { workflow_definition_id: number; document_version_id: number; parent_instance_id?: number }) =>
    apiClient.post<ApiResponse<any>>('/workflows/instances', data),
  delegate: (id: number, data: { target_user_id: number; reason?: string }) =>
    apiClient.post<ApiResponse<any>>(`/workflows/tasks/${id}/delegate`, data),
  reassign: (id: number, data: { target_user_id: number; reason?: string }) =>
    apiClient.post<ApiResponse<any>>(`/workflows/tasks/${id}/reassign`, data),
  addParticipants: (id: number, data: { participants: Array<{ user_id: number; role?: string }> }) =>
    apiClient.post<ApiResponse<any>>(`/workflows/tasks/${id}/participants`, data),
  decide: (
    id: number,
    data: { decision: 'approve' | 'reject' | 'return' | 'cancel'; reason?: string; idempotency_key?: string; spawn_revision?: boolean }
  ) => apiClient.post<ApiResponse<unknown>>(`/workflows/tasks/${id}/decide`, data),
}

// ----------------------------------------------------------------------------
// 10. Institutional Archive & Retention API
// ----------------------------------------------------------------------------
export const archiveApi = {
  collections: () => apiClient.get<ApiResponse<ArchiveCollection[]>>('/archive/collections'),
  createCollection: (data: { code: string; name: string; description?: string; parent_id?: number }) =>
    apiClient.post<ApiResponse<ArchiveCollection>>('/archive/collections', data),
  policies: () => apiClient.get<ApiResponse<RetentionPolicy[]>>('/archive/policies'),
  entries: (params?: Record<string, unknown>) =>
    apiClient.get<PaginatedResponse<ArchiveEntry>>('/archive/entries', { params }),
  transfer: (data: {
    document_id: number
    collection_id: number
    retention_policy_version_id?: number
    retention_policy_id?: number
    next_review_at?: string
  }) => apiClient.post<ApiResponse<ArchiveEntry>>('/archive/transfer', data),
  legalHolds: () => apiClient.get<ApiResponse<LegalHold[]>>('/archive/legal-holds'),
  createLegalHold: (data: { code: string; reason: string; document_ids?: number[] }) =>
    apiClient.post<ApiResponse<LegalHold>>('/archive/legal-holds', data),
  releaseLegalHold: (id: number) =>
    apiClient.post<ApiResponse<LegalHold>>(`/archive/legal-holds/${id}/release`),
  disposalEligibility: (id: number) =>
    apiClient.get<ApiResponse<{
      entry_id: number
      document_id: number
      reference_number: string
      title: string
      lifecycle: string
      eligible: boolean
      reasons: string[]
      active_holds: Array<{ id: number; code: string; reason: string; placed_at: string }>
      next_review_at: string | null
    }>>(`/archive/entries/${id}/disposal-eligibility`),
  dispose: (id: number, data: { requested_action: string; justification: string }) =>
    apiClient.post<ApiResponse<any>>(`/archive/entries/${id}/dispose`, data),
  requestDisposal: (id: number, data: { requested_action: string; justification: string }) =>
    apiClient.post<ApiResponse<any>>(`/archive/entries/${id}/request-disposal`, data),
  decideDisposal: (requestId: number, data: { decision: 'approve' | 'reject'; reason: string }) =>
    apiClient.post<ApiResponse<any>>(`/archive/disposition-requests/${requestId}/decide`, data),
  executeDisposal: (requestId: number, data?: { external_receipt?: string; evidence_document_id?: number }) =>
    apiClient.post<ApiResponse<any>>(`/archive/disposition-requests/${requestId}/execute`, data),
  restore: (id: number) =>
    apiClient.post<ApiResponse<any>>(`/archive/entries/${id}/restore`),
  verifyIntegrity: (fileObjectId: number) =>
    apiClient.post<ApiResponse<any>>(`/archive/files/${fileObjectId}/verify-integrity`),
  review: (
    id: number,
    data: { review_action: 'extend' | 'retain_permanent' | 'request_disposal'; months_extended?: number; notes: string }
  ) => apiClient.post<ApiResponse<any>>(`/archive/entries/${id}/review`, data),
}

// ----------------------------------------------------------------------------
// 11. Notifications & Audit Trail API
// ----------------------------------------------------------------------------
export const notificationsApi = {
  list: () =>
    apiClient.get<ApiResponse<{ items: PaginatedResponse<UserNotification>['data']; unread_count: number }>>(
      '/notifications'
    ),
  markRead: (id: number) => apiClient.post<ApiResponse<void>>(`/notifications/${id}/read`),
  markAllRead: () => apiClient.post<ApiResponse<void>>('/notifications/mark-all-read'),
}

export const auditApi = {
  list: (params?: Record<string, unknown>) =>
    apiClient.get<PaginatedResponse<AuditEvent>>('/audit-events', { params }),
}

export const navigationApi = {
  get: () => apiClient.get<ApiResponse<any[]>>('/navigation'),
}

// ----------------------------------------------------------------------------
// 11b. Runtime System Configuration API
// ----------------------------------------------------------------------------
export const settingsApi = {
  /** The configuration catalogue with the current values resolved. */
  index: () => apiClient.get<ApiResponse<SettingsPayload>>('/settings'),
  /** The available configuration categories. */
  categories: () => apiClient.get<ApiResponse<Record<string, SettingCategory>>>('/settings/categories'),
  /**
   * Store one or more configuration values. The server decides the type, the
   * validation rules and the permitted values; the client supplies the value
   * only.
   */
  update: (settings: Array<{ key: string; value: string | number | boolean }>, scopeUnitId?: number | null) =>
    apiClient.put<ApiResponse<{ settings: SettingDefinition[]; scope_unit_id: number | null }>>('/settings', {
      settings,
      ...(scopeUnitId != null ? { scope_unit_id: scopeUnitId } : {}),
    }),
  /** Reset a setting to its catalogue default. */
  reset: (key: string, scopeUnitId?: number | null) =>
    apiClient.post<ApiResponse<{ setting: SettingDefinition; scope_unit_id: number | null }>>(
      `/settings/${key}/reset`,
      scopeUnitId != null ? { scope_unit_id: scopeUnitId } : {}
    ),
  /** Reset every setting of a category to its catalogue default. */
  resetCategory: (category: string, scopeUnitId?: number | null) =>
    apiClient.post<ApiResponse<{ reset: string[]; settings: SettingDefinition[] }>>(
      `/settings/categories/${category}/reset`,
      scopeUnitId != null ? { scope_unit_id: scopeUnitId } : {}
    ),
  /** The configuration published on the public portal. */
  publicSettings: () => apiClient.get<ApiResponse<Record<string, string | number | boolean>>>('/settings/public'),
}

// ----------------------------------------------------------------------------
// 11c. Dataset Import and Export API
// ----------------------------------------------------------------------------
export const dataTransferApi = {
  /** The supported import and export datasets. */
  datasets: () =>
    apiClient.get<
      ApiResponse<DataDataset[]> & { meta: { max_import_kilobytes: number } }
    >('/data/datasets'),

  /** The import history of the caller. */
  imports: () => apiClient.get<ApiResponse<DataImportOperation[]>>('/data/imports'),

  /**
   * Receive and inspect an import file.
   *
   * The inspection creates no business record. The progress callback reports
   * the actual network activity of the upload.
   */
  inspectImport: (form: FormData, onUploadProgress?: (percent: number) => void) =>
    apiClient.post<ApiResponse<DataImportOperation>>('/data/imports', form, {
      onUploadProgress: (event) => {
        if (!onUploadProgress) return
        const percent = event.total ? Math.round((event.loaded / event.total) * 100) : 0
        onUploadProgress(percent)
      },
    }),

  /** The detail of an import operation, including its row level failures. */
  showImport: (id: number) => apiClient.get<ApiResponse<DataImportOperation>>(`/data/imports/${id}`),

  /** Execute a previously inspected import. */
  executeImport: (id: number) => apiClient.post<ApiResponse<DataImportOperation>>(`/data/imports/${id}/execute`),

  /** Discard an import operation and remove its uploaded file. */
  discardImport: (id: number) => apiClient.delete<ApiResponse<DataImportOperation>>(`/data/imports/${id}`),

  /** The export history of the caller. */
  exports: () => apiClient.get<ApiResponse<DataExportOperation[]>>('/data/exports'),

  /** Generate an export file. */
  generateExport: (payload: {
    dataset: string
    filters?: Record<string, string>
    scope_unit_id?: number | null
  }) => apiClient.post<ApiResponse<DataExportOperation>>('/data/exports', payload),

  /** The address of a generated export file. */
  exportDownloadUrl: (id: number) => `/api/v1/data/exports/${id}/download`,

  /** The address of the structured validation error report of an import. */
  importErrorsUrl: (id: number) => `/api/v1/data/imports/${id}/errors`,
}

// ----------------------------------------------------------------------------
// 12. Specialized Institutional Modules API
// ----------------------------------------------------------------------------
export const institutionalApi = {
  // Research
  research: (params?: Record<string, unknown>) =>
    apiClient.get<ApiResponse<ResearchRecord[]>>('/research/outputs', { params: { per_page: 100, ...(params || {}) } }),
  getResearch: (id: number) => apiClient.get<ApiResponse<ResearchRecord>>(`/research/outputs/${id}`),
  storeResearch: (data: Record<string, unknown>) =>
    apiClient.post<ApiResponse<any>>('/research/outputs', data),
  updateResearch: (id: number, data: Record<string, unknown>) =>
    apiClient.put<ApiResponse<any>>(`/research/outputs/${id}`, data),
  uploadResearchVersion: (id: number, data: Record<string, unknown>) =>
    apiClient.post<ApiResponse<any>>(`/research/outputs/${id}/versions`, data),
  togglePublication: (id: number, action: 'publish' | 'withdraw') =>
    apiClient.post<ApiResponse<any>>(`/research/outputs/${id}/toggle-publication`, { action }),

  // Staff & Physical Custody
  staffRecords: (params?: Record<string, unknown>) =>
    apiClient.get<ApiResponse<any[]>>('/staff/records', { params: { per_page: 100, ...(params || {}) } }),
  storeStaffRecord: (data: Record<string, unknown>) =>
    apiClient.post<ApiResponse<any>>('/staff/records', data),
  verifyStaffScan: (
    id: number,
    data: { verification_status: 'accepted' | 'rejected'; verification_note?: string }
  ) => apiClient.post<ApiResponse<unknown>>(`/staff/records/${id}/verify-scan`, data),
  disposeStaffPhysical: (
    id: number,
    data: {
      authorization_reference: string
      reason: string
      executive_senate_disposal_order?: string
    }
  ) => apiClient.post<ApiResponse<unknown>>(`/staff/records/${id}/dispose-physical`, data),

  // Accreditation & Quality Assurance
  accreditationFrameworks: () =>
    apiClient.get<ApiResponse<AccreditationFramework[]>>('/accreditation/frameworks'),
  accreditationStandards: (params?: { cycle_id?: number; framework_version_id?: number }) =>
    apiClient.get<ApiResponse<AccreditationStandard[]>>('/accreditation/standards', { params }),
  accreditationCycles: (params?: { unit_id?: number; active?: boolean; per_page?: number; page?: number }) =>
    apiClient.get<ApiResponse<AccreditationCycle[]>>('/accreditation/cycles', { params: { per_page: 100, ...(params || {}) } }),
  accreditationCycle: (id: number) =>
    apiClient.get<ApiResponse<AccreditationCycle>>(`/accreditation/cycles/${id}`),
  createAccreditationCycle: (data: Record<string, unknown>) =>
    apiClient.post<ApiResponse<AccreditationCycle>>('/accreditation/cycles', data),
  updateAccreditationCycle: (id: number, data: Record<string, unknown>) =>
    apiClient.put<ApiResponse<AccreditationCycle>>(`/accreditation/cycles/${id}`, data),
  accreditationCycleSummary: (cycleId: number) =>
    apiClient.get<ApiResponse<AccreditationCycleSummary>>(`/accreditation/cycles/${cycleId}/summary`),
  accreditationEvidence: (params?: Record<string, unknown>) =>
    apiClient.get<ApiResponse<AccreditationEvidence[]>>('/accreditation/evidence', { params: { per_page: 100, ...(params || {}) } }),
  accreditationEvidenceDetail: (id: number) =>
    apiClient.get<ApiResponse<AccreditationEvidence>>(`/accreditation/evidence/${id}`),
  submitAccreditationEvidence: (data: { cycle_id: number; standard_id: number; document_version_id: number; note?: string }) =>
    apiClient.post<ApiResponse<AccreditationEvidence>>('/accreditation/evidence', data),
  reviewAccreditationEvidence: (id: number, data: { decision: 'accepted' | 'rejected' | 'needs_revision'; comment?: string }) =>
    apiClient.post<ApiResponse<AccreditationReview>>(`/accreditation/evidence/${id}/reviews`, data),
  exportAccreditationDossier: (cycleId: number) =>
    apiClient.get<ApiResponse<any>>(`/accreditation/cycles/${cycleId}/export`),

  // Projects & Innovation
  projects: (params?: Record<string, unknown>) =>
    apiClient.get<ApiResponse<{ proposals: ProjectProposal[]; projects: Project[]; project_types?: ProjectType[] }>>('/projects', {
      params: { per_page: 100, ...(params || {}) },
    }),
  storeProject: (data: Record<string, unknown>) =>
    apiClient.post<ApiResponse<ProjectProposal>>('/projects', data),

  // Signatures & Stamps
  signatures: (params?: Record<string, unknown>) =>
    apiClient.get<ApiResponse<{ signatures: SignatureRequestItem[]; stamps: StampDefinitionItem[]; trust_provider_status: string }>>(
      '/signatures',
      { params: { per_page: 100, ...(params || {}) } }
    ),
  storeSignatureRequest: (data: Record<string, unknown>) =>
    apiClient.post<ApiResponse<SignatureRequestItem>>('/signatures', data),
  executeSignature: (id: number) =>
    apiClient.post<ApiResponse<{ signature: DigitalSignatureItem; signed_file: unknown }>>(`/signatures/${id}/execute`),
  verifySignature: (id: number) =>
    apiClient.post<ApiResponse<unknown>>(`/signatures/${id}/verify`),
  verifyPdfFile: (data: FormData | Record<string, unknown>) =>
    apiClient.post<ApiResponse<unknown>>('/signatures/verify-file', data),
  signingIdentities: () =>
    apiClient.get<ApiResponse<SigningIdentityItem[]>>('/signatures/identities'),
  applyStamp: (data: { document_version_id: number; stamp_id: number; reason: string }) =>
    apiClient.post<ApiResponse<any>>('/stamps/apply', data),

  // Office Procedures
  procedures: (params?: Record<string, unknown>) =>
    apiClient.get<ApiResponse<unknown[]>>('/procedures', { params: { per_page: 100, ...(params || {}) } }),
  storeProcedure: (data: Record<string, unknown>) =>
    apiClient.post<ApiResponse<unknown>>('/procedures', data),

  // Operations & System Health
  operations: () =>
    apiClient.get<ApiResponse<OperationsStatusData>>('/operations/status'),
}

export const ocrApi = {
  status: () =>
    apiClient.get<ApiResponse<{
      tesseract_installed: boolean
      tesseract_version: string | null
      tesseract_path: string | null
      supported_languages: string[]
      models_configured: string[]
    }>>('/ocr/status'),
  process: (
    documentId: number,
    versionId: number,
    data: { file_object_id?: number; languages?: string; sync?: boolean }
  ) =>
    apiClient.post<ApiResponse<any>>(
      `/documents/${documentId}/versions/${versionId}/extractions/process`,
      { sync: true, ...data }
    ),
  extractions: (documentId: number, versionId: number) =>
    apiClient.get<ApiResponse<any[]>>(
      `/documents/${documentId}/versions/${versionId}/extractions`
    ),
  verify: (
    documentId: number,
    versionId: number,
    extractionId: number,
    data: { verified_text: string; notes?: string }
  ) =>
    apiClient.post<ApiResponse<any>>(
      `/documents/${documentId}/versions/${versionId}/extractions/${extractionId}/verify`,
      data
    ),
  retry: (
    documentId: number,
    versionId: number,
    extractionId: number,
    data?: { sync?: boolean }
  ) =>
    apiClient.post<ApiResponse<any>>(
      `/documents/${documentId}/versions/${versionId}/extractions/${extractionId}/retry`,
      { sync: true, ...(data ?? {}) }
    ),
}

// ----------------------------------------------------------------------------
// 19. Institutional Configuration Center API (Workflows, Numbering, Routing, Retention)
// ----------------------------------------------------------------------------
export const workflowAdminApi = {
  list: () => apiClient.get<ApiResponse<import('@/types').WorkflowDefinition[]>>('/admin/workflows'),
  get: (id: number) => apiClient.get<ApiResponse<any>>(`/admin/workflows/${id}`),
  create: (data: { code: string; name: string; description?: string; document_category_id?: number | null; active?: boolean }) =>
    apiClient.post<ApiResponse<any>>('/admin/workflows', data),
  update: (id: number, data: { name: string; description?: string; document_category_id?: number | null; active: boolean }) =>
    apiClient.put<ApiResponse<any>>(`/admin/workflows/${id}`, data),
  createVersion: (id: number, data?: { clone_from_version_id?: number | null }) =>
    apiClient.post<ApiResponse<any>>(`/admin/workflows/${id}/versions`, data ?? {}),
  createState: (id: number, versionId: number, data: {
    code: string
    name: string
    is_initial?: boolean
    is_terminal?: boolean
    assigned_role_id?: number | null
    assigned_unit_id?: number | null
    sla_hours?: number | null
  }) => apiClient.post<ApiResponse<any>>(`/admin/workflows/${id}/versions/${versionId}/states`, data),
  updateState: (id: number, versionId: number, stateId: number, data: {
    code: string
    name: string
    is_initial?: boolean
    is_terminal?: boolean
    assigned_role_id?: number | null
    assigned_unit_id?: number | null
    sla_hours?: number | null
  }) => apiClient.put<ApiResponse<any>>(`/admin/workflows/${id}/versions/${versionId}/states/${stateId}`, data),
  deleteState: (id: number, versionId: number, stateId: number) =>
    apiClient.delete<ApiResponse<any>>(`/admin/workflows/${id}/versions/${versionId}/states/${stateId}`),
  createTransition: (id: number, versionId: number, data: {
    from_state_id: number
    to_state_id: number
    action_code: string
    permission_id?: number | null
    requires_reason?: boolean
    requires_signature?: boolean
    prohibit_self_approval?: boolean
    approval_policy?: string
    quorum_count?: number | null
    quorum_percentage?: number | null
    join_policy?: string | null
    fork_state_ids?: number[] | null
  }) => apiClient.post<ApiResponse<any>>(`/admin/workflows/${id}/versions/${versionId}/transitions`, data),
  deleteTransition: (id: number, versionId: number, transitionId: number) =>
    apiClient.delete<ApiResponse<any>>(`/admin/workflows/${id}/versions/${versionId}/transitions/${transitionId}`),
  validate: (id: number, versionId: number) =>
    apiClient.post<ApiResponse<import('@/types').GraphValidationResult>>(`/admin/workflows/${id}/versions/${versionId}/validate`),
  publish: (id: number, versionId: number) =>
    apiClient.post<ApiResponse<any>>(`/admin/workflows/${id}/versions/${versionId}/publish`),
}

export const numberingPoliciesApi = {
  list: () => apiClient.get<ApiResponse<import('@/types').NumberingPolicyItem[]>>('/admin/numbering-policies'),
  get: (id: number) => apiClient.get<ApiResponse<import('@/types').NumberingPolicyItem>>(`/admin/numbering-policies/${id}`),
  create: (data: Partial<import('@/types').NumberingPolicyItem>) =>
    apiClient.post<ApiResponse<import('@/types').NumberingPolicyItem>>('/admin/numbering-policies', data),
  update: (id: number, data: Partial<import('@/types').NumberingPolicyItem>) =>
    apiClient.put<ApiResponse<import('@/types').NumberingPolicyItem>>(`/admin/numbering-policies/${id}`, data),
  delete: (id: number) => apiClient.delete<ApiResponse<any>>(`/admin/numbering-policies/${id}`),
  preview: (data: {
    prefix?: string
    include_unit_code?: boolean
    include_category_code?: boolean
    year_format?: string
    sequence_length?: number
    unit_id?: number | null
    category_id?: number | null
    sample_sequence?: number
  }) => apiClient.post<ApiResponse<{ preview: string; parts: string[] }>>('/admin/numbering-policies/preview', data),
}

export const routingPoliciesApi = {
  list: () => apiClient.get<ApiResponse<import('@/types').RoutingPolicyItem[]>>('/admin/routing-policies'),
  get: (id: number) => apiClient.get<ApiResponse<import('@/types').RoutingPolicyItem>>(`/admin/routing-policies/${id}`),
  create: (data: Partial<import('@/types').RoutingPolicyItem>) =>
    apiClient.post<ApiResponse<import('@/types').RoutingPolicyItem>>('/admin/routing-policies', data),
  update: (id: number, data: Partial<import('@/types').RoutingPolicyItem>) =>
    apiClient.put<ApiResponse<import('@/types').RoutingPolicyItem>>(`/admin/routing-policies/${id}`, data),
  delete: (id: number) => apiClient.delete<ApiResponse<any>>(`/admin/routing-policies/${id}`),
  match: (data: {
    from_unit_id?: number | null
    correspondence_type_id?: number | null
    document_category_id?: number | null
    priority_id?: number | null
  }) => apiClient.post<ApiResponse<{ data: import('@/types').RoutingPolicyItem | null; has_match: boolean }>>('/routing-policies/match', data),
}

export const retentionPoliciesApi = {
  list: () => apiClient.get<ApiResponse<import('@/types').RetentionPolicyItem[]>>('/admin/retention-policies'),
  get: (id: number) => apiClient.get<ApiResponse<import('@/types').RetentionPolicyItem>>(`/admin/retention-policies/${id}`),
  create: (data: {
    code: string
    name: string
    description?: string
    document_category_id?: number | null
    active?: boolean
    trigger_code: string
    duration_months: number
    disposition_action: string
    effective_from?: string
  }) => apiClient.post<ApiResponse<any>>('/admin/retention-policies', data),
  update: (id: number, data: { name: string; description?: string; document_category_id?: number | null; active: boolean }) =>
    apiClient.put<ApiResponse<any>>(`/admin/retention-policies/${id}`, data),
  createVersion: (id: number, data: {
    trigger_code: string
    duration_months: number
    disposition_action: string
    effective_from?: string
  }) => apiClient.post<ApiResponse<any>>(`/admin/retention-policies/${id}/versions`, data),
  delete: (id: number) => apiClient.delete<ApiResponse<any>>(`/admin/retention-policies/${id}`),
}


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
  Project,
  SignatureRequestItem,
  StampDefinitionItem,
  DigitalSignatureItem,
  SigningIdentityItem,
  OperationsStatusData,
  OrganizationalContext,
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
        console.warn('Sanctum CSRF cookie acquisition note:', err.message)
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
    if (error.response?.status === 419 && !originalRequest._retry) {
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
      const publicPaths = ['/', '/login', '/privacy-policy', '/terms', '/forgot-password', '/reset-password']
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
  changePassword: (data: Record<string, string>) => apiClient.put<ApiResponse<void>>('/auth/password', data),
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
  getAll: () => apiClient.get<ApiResponse<{
    document_categories: DocumentCategory[]
    confidentiality_levels: ConfidentialityLevel[]
    correspondence_types: CorrespondenceType[]
    priorities: Priority[]
    dispatch_methods: DispatchMethod[]
  }>>('/metadata/all'),
  getCategories: () => apiClient.get<ApiResponse<DocumentCategory[]>>('/metadata/categories'),
  getConfidentialityLevels: () => apiClient.get<ApiResponse<ConfidentialityLevel[]>>('/metadata/confidentiality-levels'),
  getCorrespondenceTypes: () => apiClient.get<ApiResponse<CorrespondenceType[]>>('/metadata/correspondence-types'),
  getPriorities: () => apiClient.get<ApiResponse<Priority[]>>('/metadata/priorities'),
  getDispatchMethods: () => apiClient.get<ApiResponse<DispatchMethod[]>>('/metadata/dispatch-methods'),
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
  permissions: () => apiClient.get<ApiResponse<PermissionItem[]>>('/permissions'),
  scopes: () => apiClient.get<ApiResponse<AuthorizationScope[]>>('/authorization-scopes'),
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
  list: () => apiClient.get<ApiResponse<PermissionItem[]>>('/permissions'),
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
  render: (id: number, data: Record<string, unknown>) =>
    apiClient.post<ApiResponse<any>>(`/templates/${id}/render`, data),
  generateDocument: (id: number, data: Record<string, unknown>) =>
    apiClient.post<ApiResponse<any>>(`/templates/${id}/render`, data),
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
  create: (data: Record<string, unknown>) =>
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
}

// ----------------------------------------------------------------------------
// 9. Workflow & Decision Routing API
// ----------------------------------------------------------------------------
export const workflowApi = {
  definitions: () => apiClient.get<ApiResponse<WorkflowDefinition[]>>('/workflows/definitions'),
  tasks: (params?: { scope?: 'mine' | 'all' }) =>
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
  policies: () => apiClient.get<ApiResponse<RetentionPolicy[]>>('/archive/policies'),
  entries: (params?: Record<string, unknown>) =>
    apiClient.get<PaginatedResponse<ArchiveEntry>>('/archive/entries', { params }),
  transfer: (data: {
    document_id: number
    collection_id: number
    retention_policy_version_id: number
    next_review_at?: string
  }) => apiClient.post<ApiResponse<ArchiveEntry>>('/archive/transfer', data),
  legalHolds: () => apiClient.get<ApiResponse<LegalHold[]>>('/archive/legal-holds'),
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
// 12. Specialized Institutional Modules API
// ----------------------------------------------------------------------------
export const institutionalApi = {
  // Research
  research: () => apiClient.get<ApiResponse<any[]>>('/research/outputs'),
  getResearch: (id: number) => apiClient.get<ApiResponse<any>>(`/research/outputs/${id}`),
  storeResearch: (data: Record<string, unknown>) =>
    apiClient.post<ApiResponse<any>>('/research/outputs', data),
  updateResearch: (id: number, data: Record<string, unknown>) =>
    apiClient.put<ApiResponse<any>>(`/research/outputs/${id}`, data),
  uploadResearchVersion: (id: number, data: Record<string, unknown>) =>
    apiClient.post<ApiResponse<any>>(`/research/outputs/${id}/versions`, data),
  togglePublication: (id: number, action: 'publish' | 'withdraw') =>
    apiClient.post<ApiResponse<any>>(`/research/outputs/${id}/toggle-publication`, { action }),

  // Staff & Physical Custody
  staffRecords: () => apiClient.get<ApiResponse<any[]>>('/staff/records'),
  storeStaffRecord: (data: Record<string, unknown>) =>
    apiClient.post<ApiResponse<any>>('/staff/records', data),
  verifyStaffScan: (id: number) =>
    apiClient.post<ApiResponse<any>>(`/staff/records/${id}/verify-scan`),
  disposeStaffPhysical: (id: number, data: { disposal_order_reference: string }) =>
    apiClient.post<ApiResponse<any>>(`/staff/records/${id}/dispose-physical`, data),

  // Accreditation & Quality Assurance
  accreditationFrameworks: () =>
    apiClient.get<ApiResponse<AccreditationFramework[]>>('/accreditation/frameworks'),
  accreditationStandards: (params?: { cycle_id?: number; framework_version_id?: number }) =>
    apiClient.get<ApiResponse<AccreditationStandard[]>>('/accreditation/standards', { params }),
  accreditationCycles: (params?: { unit_id?: number; active?: boolean }) =>
    apiClient.get<ApiResponse<AccreditationCycle[]>>('/accreditation/cycles', { params }),
  accreditationCycle: (id: number) =>
    apiClient.get<ApiResponse<AccreditationCycle>>(`/accreditation/cycles/${id}`),
  createAccreditationCycle: (data: Record<string, unknown>) =>
    apiClient.post<ApiResponse<AccreditationCycle>>('/accreditation/cycles', data),
  updateAccreditationCycle: (id: number, data: Record<string, unknown>) =>
    apiClient.put<ApiResponse<AccreditationCycle>>(`/accreditation/cycles/${id}`, data),
  accreditationCycleSummary: (cycleId: number) =>
    apiClient.get<ApiResponse<AccreditationCycleSummary>>(`/accreditation/cycles/${cycleId}/summary`),
  accreditationEvidence: (params?: Record<string, unknown>) =>
    apiClient.get<ApiResponse<AccreditationEvidence[]>>('/accreditation/evidence', { params }),
  accreditationEvidenceDetail: (id: number) =>
    apiClient.get<ApiResponse<AccreditationEvidence>>(`/accreditation/evidence/${id}`),
  submitAccreditationEvidence: (data: { cycle_id: number; standard_id: number; document_version_id: number; note?: string }) =>
    apiClient.post<ApiResponse<AccreditationEvidence>>('/accreditation/evidence', data),
  reviewAccreditationEvidence: (id: number, data: { decision: 'accepted' | 'rejected' | 'needs_revision'; comment?: string }) =>
    apiClient.post<ApiResponse<AccreditationReview>>(`/accreditation/evidence/${id}/reviews`, data),
  exportAccreditationDossier: (cycleId: number) =>
    apiClient.get<ApiResponse<any>>(`/accreditation/cycles/${cycleId}/export`),

  // Projects & Innovation
  projects: () =>
    apiClient.get<ApiResponse<{ proposals: ProjectProposal[]; projects: Project[]; project_types?: ProjectType[] }>>('/projects'),
  storeProject: (data: Record<string, unknown>) =>
    apiClient.post<ApiResponse<ProjectProposal>>('/projects', data),

  // Signatures & Stamps
  signatures: () =>
    apiClient.get<ApiResponse<{ signatures: SignatureRequestItem[]; stamps: StampDefinitionItem[]; trust_provider_status: string }>>(
      '/signatures'
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

  // Office Procedures
  procedures: () => apiClient.get<ApiResponse<unknown[]>>('/procedures'),
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
  process: (data: { document_id: number; file_object_id?: number; languages?: string; queue_mode?: 'sync' | 'async' }) =>
    apiClient.post<ApiResponse<any>>('/ocr/process', data),
  extractions: (documentId: number) =>
    apiClient.get<ApiResponse<any[]>>(`/ocr/${documentId}/extractions`),
  verify: (extractionId: number, data: { corrected_text: string; notes?: string }) =>
    apiClient.post<ApiResponse<any>>(`/ocr/${extractionId}/verify`, data),
  retry: (extractionId: number) =>
    apiClient.post<ApiResponse<any>>(`/ocr/${extractionId}/retry`),
}


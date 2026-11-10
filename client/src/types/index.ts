// ============================================================================
// Wollo University Administrative & Document Management System (WU-DMS)
// Pure 118-Table Baseline TypeScript Domain Types
// ============================================================================

export interface ApiResponse<T> {
  success: boolean
  message?: string
  data: T
  token?: string
  token_type?: string
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page?: number
  total: number
  from?: number
  to?: number
}

export interface PaginatedResponse<T> {
  success: boolean
  data: T[]
  meta: PaginationMeta
}

// ----------------------------------------------------------------------------
// 01. Identity & Organization
// ----------------------------------------------------------------------------

export interface UnitType {
  id: number
  code: string
  name: string
  description?: string | null
  active: boolean
}

export interface OrganizationUnit {
  id: number
  parent_id?: number | null
  unit_type_id: number
  unit_type?: UnitType
  parent?: OrganizationUnit | null
  children?: OrganizationUnit[]
  code: string
  name: string
  active: boolean
  created_at?: string
  memberships_count?: number
  documents_count?: number
}

export interface OrganizationMembership {
  id: number
  user_id: number
  unit_id: number
  unit?: OrganizationUnit
  position_title?: string | null
  starts_at: string
  ends_at?: string | null
  created_by: number
  created_at: string
}

export interface PrimaryMembership {
  id: number
  unit_id: number
  unit_name?: string
  unit_code?: string
  position_title?: string | null
}

export interface UserDirectPermission {
  id: number
  permission_id: number
  permission_code?: string
  permission_name?: string
  scope_id: number
  scope_kind?: 'global' | 'unit' | 'subtree'
  scope_unit_id?: number | null
  scope_unit_name?: string | null
  expires_at?: string | null
}

export interface User {
  id: number
  name: string
  email: string
  status: 'active' | 'inactive' | 'locked'
  authz_version: number
  email_verified_at?: string | null
  created_at?: string
  primary_membership?: PrimaryMembership | null
  roles?: UserRoleAssignment[]
  direct_permissions?: UserDirectPermission[]
  /**
   * The capability set calculated by the server for the active organizational
   * context. It is never the union of every scoped grant.
   */
  permissions?: string[]
  permissions_global?: string[]
  permissions_all_scopes?: string[]
  active_unit?: OrganizationalUnitSummary | null
  active_unit_id?: number | null
  available_units?: OrganizationalUnitSummary[]
  unit_selection_required?: boolean
}

/**
 * An organizational unit the user may operate in.
 */
export interface OrganizationalUnitSummary {
  id: number
  code: string
  name: string
  unit_type?: string | null
  parent_id?: number | null
  is_primary?: boolean
}

/**
 * The organizational context published by the server.
 */
export interface OrganizationalContext {
  active_unit: OrganizationalUnitSummary | null
  active_unit_id: number | null
  available_units: OrganizationalUnitSummary[]
  unit_selection_required: boolean
  permissions: string[]
  permissions_global: string[]
  permissions_all_scopes: string[]
}

/**
 * A configuration category.
 */
export interface SettingCategory {
  label: string
  description: string
}

/**
 * A runtime configuration setting, as published by the server.
 */
export interface SettingDefinition {
  key: string
  label: string
  description: string
  category: string
  type: 'string' | 'integer' | 'boolean' | 'select'
  options: string[] | null
  default: string | number | boolean
  value: string | number | boolean
  scope: 'global' | 'global_or_unit'
  scope_unit_id: number | null
  editable: boolean
  is_default: boolean
  updated_at: string | null
  updated_by: string | null
}

/**
 * A deployment managed configuration entry. A sensitive entry never carries a
 * value; it only states that the value is managed outside the application.
 */
export interface DeploymentEntry {
  key: string
  label: string
  sensitive: boolean
  value: string | null
  managed: string
}

/**
 * The configuration payload served by the API.
 */
export interface SettingsPayload {
  categories: Record<string, SettingCategory>
  settings: SettingDefinition[]
  deployment: DeploymentEntry[]
  scope_unit_id: number | null
}

// ----------------------------------------------------------------------------
// 02. Scoped RBAC
// ----------------------------------------------------------------------------

export interface PermissionItem {
  id: number
  code: string
  name: string
  description?: string | null
  active: boolean
}

export interface RoleItem {
  id: number
  code: string
  name: string
  description?: string | null
  active: boolean
  is_system: boolean
  created_at?: string
  permissions_count?: number
  permissions?: PermissionItem[]
}

export interface AuthorizationScope {
  id: number
  kind: 'global' | 'unit' | 'subtree'
  unit_id?: number | null
  unit?: OrganizationUnit | null
}

export interface UserRoleAssignment {
  role_id: number
  role_code?: string
  role_name?: string
  scope_id: number
  scope_kind?: 'global' | 'unit' | 'subtree'
  scope_unit_id?: number | null
  scope_unit_name?: string | null
  expires_at?: string | null
}

// ----------------------------------------------------------------------------
// 03. Repository
// ----------------------------------------------------------------------------

export interface DocumentCategory {
  id: number
  code: string
  name: string
  description?: string | null
  active: boolean
}

export interface ConfidentialityLevel {
  id: number
  code: string
  name: string
  description?: string | null
  active: boolean
}

export type SecurityClassification = ConfidentialityLevel & { level?: number }

export interface Tag {
  id: number
  name: string
}

export interface FileObject {
  id: number
  storage_disk: string
  storage_key: string
  original_filename: string
  detected_mime: string
  byte_size: number
  sha256: string
  uploaded_by: number
  uploaded_at: string
  scan_status: 'pending' | 'clean' | 'infected' | 'error' | 'unavailable'
  storage_status: 'staged' | 'ready' | 'missing' | 'destroyed'
}

export interface DocumentVersion {
  id: number
  document_id: number
  version_number: number
  created_by: number
  creator?: { id: number; name: string }
  change_note?: string | null
  created_at: string
  version_files?: {
    file_object_id: number
    purpose: 'primary' | 'attachment'
    file_object?: FileObject
  }[]
}

export interface Document {
  id: number
  reference_number: string
  title: string
  summary?: string | null
  category_id: number
  category?: DocumentCategory
  confidentiality_id: number
  confidentiality?: ConfidentialityLevel
  owner_unit_id: number
  owner_unit?: OrganizationUnit
  custodian_id?: number | null
  custodian?: { id: number; name: string; email: string } | null
  created_by: number
  creator?: { id: number; name: string }
  document_date: string
  registered_at: string
  lifecycle: 'active' | 'retired' | 'tombstoned'
  lock_version: number
  created_at?: string
  updated_at?: string
  versions_count?: number
  versions?: DocumentVersion[]
  latest_version?: DocumentVersion
  tags?: string[]
  correspondence?: Correspondence | null
  movements?: DocumentMovement[]
  assignments?: DocumentAssignment[]
  physical_originals?: PhysicalOriginal[]
}

export interface PhysicalStorageLocation {
  id: number
  code: string
  name: string
  parent_id?: number | null
  unit_id?: number | null
  active?: boolean
}

export interface PhysicalCustodyEvent {
  id: number
  action: 'register' | 'move' | 'checkout' | 'return' | 'missing' | 'transfer' | 'destroy'
  occurred_at: string
  reason?: string | null
  location?: PhysicalStorageLocation | null
  custodian?: { id: number; name: string } | null
  actor?: { id: number; name: string } | null
}

export interface PhysicalOriginal {
  id: number
  document_id: number
  physical_reference?: string | null
  retention_requirement: 'required' | 'optional' | 'none'
  retention_policy_version_id?: number | null
  created_at: string
  created_by?: number
  custody_events?: PhysicalCustodyEvent[]
}

export interface TimelineEvent {
  id: string
  event_type: string
  title: string
  description?: string | null
  actor?: { id: number; name: string } | null
  timestamp: string
  metadata?: Record<string, any>
}

// ----------------------------------------------------------------------------
// 04 & 05. Workflow & Correspondence
// ----------------------------------------------------------------------------

export interface CorrespondenceType {
  id: number
  code: string
  name: string
  description?: string | null
  active: boolean
}

export interface Priority {
  id: number
  code: string
  name: string
  description?: string | null
  active: boolean
}

export interface DispatchMethod {
  id: number
  code: string
  name: string
  description?: string | null
  active: boolean
}

export interface DocumentMovement {
  id: number
  document_id: number
  from_unit_id: number
  from_unit?: OrganizationUnit
  to_unit_id: number
  to_unit?: OrganizationUnit
  sent_by: number
  sender?: { id: number; name: string }
  sent_by_user?: { id: number; name: string } | null
  sent_at: string
  instruction?: string | null
  due_at?: string | null
  receipt?: {
    id: number
    movement_id: number
    acknowledged_by: number
    acknowledged_at: string
    acknowledged_by_user?: { id: number; name: string } | null
  } | null
}

export interface DocumentAssignment {
  id: number
  document_id: number
  assigned_user_id: number
  assigned_user?: { id: number; name: string; email: string }
  assigned_unit_id: number
  assigned_unit?: OrganizationUnit
  assigned_by: number
  assigned_by_user?: { id: number; name: string }
  assigned_at: string
  due_at?: string | null
  instruction: string
}

export interface CorrespondenceParticipant {
  id: number
  correspondence_id: number
  party_id: number
  participation_type: 'sender' | 'to' | 'cc'
  party?: {
    id: number
    unit_id?: number | null
    user_id?: number | null
    external_party_id?: number | null
    unit?: OrganizationUnit
    user?: User
  }
}

export interface Correspondence {
  id: number
  document_id: number
  document?: Document
  type_id: number
  type?: CorrespondenceType
  priority_id: number
  priority?: Priority
  purpose_code: string
  external_reference?: string | null
  correspondence_date: string
  first_receiving_unit_id?: number | null
  first_receiving_unit?: OrganizationUnit | null
  received_at?: string | null
  requested_due_at?: string | null
  registered_by: number
  registrar?: { id: number; name: string }
  registered_at: string
  participants?: CorrespondenceParticipant[]
  movements?: DocumentMovement[]
  assignments?: DocumentAssignment[]
}

export interface WorkflowDefinition {
  id: number
  code: string
  name: string
  active: boolean
}

export interface WorkflowTask {
  id: number
  instance_id: number
  state_id: number
  state?: { id: number; code: string; name: string }
  assigned_user_id?: number | null
  assigned_user?: { id: number; name: string }
  assigned_unit_id?: number | null
  assigned_unit?: OrganizationUnit
  required_role_id?: number | null
  branch_key?: string | null
  created_at: string
  due_at?: string | null
  closed_at?: string | null
}

// ----------------------------------------------------------------------------
// 06. Archive & Retention
// ----------------------------------------------------------------------------

export interface ArchiveCollection {
  id: number
  parent_id?: number | null
  parent?: ArchiveCollection | null
  code: string
  name: string
  description?: string | null
}

export interface RetentionPolicy {
  id: number
  code: string
  name: string
  description?: string | null
  active: boolean
  versions?: RetentionPolicyVersion[]
}

export interface RetentionPolicyVersion {
  id: number
  policy_id: number
  policy?: RetentionPolicy
  version_number: number
  trigger_code: string
  duration_months: number
  disposition_action: 'review' | 'transfer' | 'retain' | 'destroy'
  approved_by?: number | null
  approved_at?: string | null
  effective_from: string
}

export interface ArchiveEntry {
  id: number
  document_id: number
  document?: Document
  collection_id: number
  collection?: ArchiveCollection
  retention_policy_version_id: number
  retention_policy_version?: RetentionPolicyVersion
  archived_by: number
  archived_by_user?: { id: number; name: string }
  archived_at: string
  trigger_occurred_at: string
  next_review_at?: string | null
}

export interface LegalHold {
  id: number
  code: string
  reason: string
  placed_by: number
  placed_at: string
  released_by?: number | null
  released_at?: string | null
}

// ----------------------------------------------------------------------------
// 11. Audit & Notifications
// ----------------------------------------------------------------------------

export interface AuditEvent {
  id: number
  actor_id?: number | null
  actor?: { id: number; name: string; email: string } | null
  action: string
  target_type: string
  target_id: number
  request_id?: string | null
  occurred_at: string
  ip_address?: string | null
  user_agent?: string | null
  details_json?: Record<string, unknown> | null
}

export interface UserNotification {
  id: number
  event_id: number
  user_id: number
  read_at?: string | null
  created_at: string
  event?: {
    id: number
    type_code: string
    document_id?: number | null
    actor_id?: number | null
    occurred_at: string
  }
}

// ----------------------------------------------------------------------------
// Dashboard & Reports
// ----------------------------------------------------------------------------

export interface DashboardMetrics {
  overview: {
    total_documents: number
    active_documents: number
    retired_documents: number
    tombstoned_documents: number
    total_correspondences: number
    incoming_letters: number
    outgoing_letters: number
    internal_memos: number
    active_users: number
    organization_units: number
    total_movements: number
    pending_assignments: number
    archived_records: number
  }
  recent_documents: Document[]
  recent_correspondences: Correspondence[]
}

export interface ReportsSummary {
  period: {
    from: string
    to: string
  }
  documents_by_category: { category: string; count: number }[]
  documents_by_confidentiality: { confidentiality: string; count: number }[]
  correspondence_by_type: { type: string; count: number }[]
  top_movement_destinations: { unit: string; count: number }[]
  totals: {
    documents: number
    correspondence: number
    movements: number
    archive_entries: number
  }
}

// ----------------------------------------------------------------------------
// 09. Accreditation & Quality Assurance
// ----------------------------------------------------------------------------

export interface AccreditationFramework {
  id: number
  code: string
  name: string
  description?: string | null
  active: boolean
  versions?: AccreditationFrameworkVersion[]
}

export interface AccreditationFrameworkVersion {
  id: number
  framework_id: number
  version_label: string
  effective_from?: string | null
  framework?: AccreditationFramework
  standards?: AccreditationStandard[]
}

export interface AccreditationStandard {
  id: number
  framework_version_id: number
  parent_id?: number | null
  code: string
  title: string
  description?: string | null
  framework_version?: AccreditationFrameworkVersion
  parent?: AccreditationStandard
}

export interface AccreditationCycle {
  id: number
  framework_version_id: number
  unit_id: number
  name: string
  starts_on: string
  ends_on?: string | null
  unit?: OrganizationUnit
  framework_version?: AccreditationFrameworkVersion
  evidence_count?: number
}

export interface AccreditationReview {
  id: number
  evidence_id: number
  reviewer_id: number
  decision: 'accepted' | 'rejected' | 'needs_revision'
  comment?: string | null
  reviewed_at: string
  reviewer?: { id: number; name: string }
}

export interface AccreditationEvidence {
  id: number
  cycle_id: number
  standard_id: number
  document_version_id: number
  submitted_by: number
  submitted_at: string
  note?: string | null
  cycle?: AccreditationCycle
  standard?: AccreditationStandard
  document_version?: DocumentVersion & { document?: Document }
  submitted_by_user?: { id: number; name: string }
  reviews?: AccreditationReview[]
  latest_review?: AccreditationReview | null
}

export interface AccreditationCycleSummary {
  cycle: {
    id: number
    name: string
    unit?: string
    framework?: string
    version?: string
    starts_on?: string
    ends_on?: string
    is_closed: boolean
  }
  metrics: {
    total_standards: number
    covered_standards: number
    missing_standards: number
    accepted_standards: number
    pending_review: number
    needs_revision: number
    rejected: number
    submission_rate_percent: number
    acceptance_rate_percent: number
    total_evidence_records: number
  }
  standards_breakdown: {
    standard_id: number
    code: string
    title: string
    status: 'missing' | 'pending_review' | 'accepted' | 'needs_revision' | 'rejected'
    evidence_count: number
    evidence_ids: number[]
  }[]
}

// ----------------------------------------------------------------------------
// 12. Projects & Innovation Module
// ----------------------------------------------------------------------------

export interface ProjectType {
  id: number
  code?: string
  name: string
  description?: string | null
  active?: boolean
}

export interface ProjectProposal {
  id: number
  document_id: number
  project_type_id: number
  recorded_by?: number | User
  recorded_at: string
  document?: Document
  projectType?: ProjectType
  project_type?: ProjectType
  recordedBy?: User
}

export interface Project {
  id: number
  proposal_id: number
  code?: string | null
  starts_on?: string | null
  ends_on?: string | null
  status?: string
  proposal?: ProjectProposal
}

// ----------------------------------------------------------------------------
// 13. Digital Signatures, Seals & PKI Module
// ----------------------------------------------------------------------------

export interface StampDefinitionItem {
  id: number
  unit_id: number
  name: string
  active: boolean
  unit?: OrganizationUnit
}

export interface SigningIdentityItem {
  id: number
  user_id: number
  provider_code: string
  provider_identity_reference: string
  public_certificate_file_id?: number | null
  valid_from?: string | null
  valid_until?: string | null
  revoked_at?: string | null
  active?: boolean
  user_name?: string
  user_email?: string
  user?: User
}

export interface DigitalSignatureItem {
  id: number
  request_id?: number | null
  signer_id: number
  signed_file_id: number
  signature_format: string
  signed_at: string
  signed_sha256?: string
  signingIdentity?: SigningIdentityItem
  verifications?: SignatureVerificationItem[]
  signedFile?: FileObject
}

export interface SignatureVerificationItem {
  id: number
  signature_id: number
  verified_at: string
  outcome: 'valid' | 'invalid' | 'indeterminate'
  certificate_status: string
  timestamp_status: string
  details_json?: Record<string, unknown>
  details?: {
    signer_certificate?: {
      subject_dn?: string
      issuer_dn?: string
    }
    observed_sha256?: string
    signature_algorithm?: string
    [key: string]: unknown
  }
}

export interface SignatureRequestItem {
  id: number
  document_version_id: number
  requested_by: number
  signer_id: number
  purpose: string
  requested_at: string
  expires_at?: string | null
  document_version?: DocumentVersion & { document?: Document }
  requestedBy?: User
  signer?: User
  digital_signature?: DigitalSignatureItem | null
}

// ----------------------------------------------------------------------------
// 14. System Operations & Disaster Recovery
// ----------------------------------------------------------------------------

export interface FileIntegrityCheckItem {
  id: number
  file_object_id: number
  checked_at: string
  algorithm: string
  expected_hash: string
  actual_hash: string
  status: 'passed' | 'failed'
}

export interface BackupRunItem {
  id: number
  provider_job_reference: string
  started_at: string
  completed_at?: string | null
  status: string
  manifest_reference: string
  verification_status: string
}

export interface RestoreDrillItem {
  id: number
  backup_run_id?: number | null
  started_at: string
  completed_at?: string | null
  status: 'passed' | 'failed' | string
  duration_seconds?: number
  notes?: string | null
  backupRun?: BackupRunItem
}

export interface OperationsStatusData {
  integrity_checks: FileIntegrityCheckItem[]
  backups: BackupRunItem[]
  restore_drills: RestoreDrillItem[]
  on_disk_backups_count: number
  storage_driver: string
  dr_status: {
    active_tables: number
    backup_cadence: string
    encryption_algorithm: string
    latest_verified_rto_seconds: number | null
    governance_standing: string
  }
}

// ----------------------------------------------------------------------------
// 15. Vue Router RouteMeta Declaration
// ----------------------------------------------------------------------------

declare module 'vue-router' {
  interface RouteMeta {
    title?: string
    requiresAuth?: boolean
    guestOnly?: boolean
    isPublic?: boolean
    permission?: string | string[]
    roles?: string[]
    group?: 'Workspace' | 'Records' | 'Institutional records' | 'Trust & reporting' | 'Administration' | string
  }
}

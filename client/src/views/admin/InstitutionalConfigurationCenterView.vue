<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  workflowAdminApi,
  numberingPoliciesApi,
  routingPoliciesApi,
  retentionPoliciesApi,
  metadataApi,
  organizationUnitsApi,
  rolesApi,
} from '@/api/client'
import { useToast } from '@/composables/useToast'
import LoadingSkeleton from '@/components/ui/LoadingSkeleton.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import ModalDialog from '@/components/ModalDialog.vue'
import type {
  DocumentCategory,
  OrganizationUnit,
  RoleItem,
  CorrespondenceType,
  Priority,
  PermissionItem,
  WorkflowDefinition,
  WorkflowAdminVersion,
  WorkflowAdminState,
  NumberingPolicyItem,
  RoutingPolicyItem,
  RetentionPolicyItem,
  GraphValidationResult,
} from '@/types'
import {
  Sliders,
  GitBranch,
  Hash,
  Compass,
  Archive,
  Database,
  Plus,
  Edit2,
  Trash2,
  CheckCircle2,
  AlertTriangle,
  ArrowRight,
  RefreshCw,
  Eye,
  Check,
  X,
  Shield,
  Clock,
  Building2,
  Layers,
  FileText,
  AlertCircle,
  Copy,
  Send,
} from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
const toast = useToast()

type TabType = 'workflows' | 'numbering' | 'routing' | 'retention' | 'catalogs'
const activeTab = ref<TabType>('workflows')
const loading = ref(false)

function getApiErrorMessage(err: unknown, fallback: string): string {
  if (err && typeof err === 'object' && 'response' in err) {
    const resp = (err as { response?: { data?: { message?: string } } }).response
    if (resp?.data?.message) return resp.data.message
  }
  return fallback
}

// Reference data for dropdowns
const documentCategories = ref<DocumentCategory[]>([])
const organizationUnits = ref<OrganizationUnit[]>([])
const rolesList = ref<RoleItem[]>([])
const correspondenceTypes = ref<CorrespondenceType[]>([])
const prioritiesList = ref<Priority[]>([])
const permissionsList = ref<PermissionItem[]>([])

// ============================================================================
// 1. WORKFLOW DEFINITIONS & STAGE DESIGNER STATE
// ============================================================================
// NOTE: Workflow transitions enforce DAG rules; self-referential or cyclic transitions without terminal states are rejected by backend validation.
const workflowDefinitions = ref<WorkflowDefinition[]>([])
const selectedWorkflow = ref<WorkflowDefinition | null>(null)
const selectedVersion = ref<WorkflowAdminVersion | null>(null)
const validationResult = ref<GraphValidationResult | null>(null)
const validating = ref(false)
const publishing = ref(false)

// Workflow Modal
const isWorkflowModalOpen = ref(false)
const workflowForm = ref({
  id: null as number | null,
  code: '',
  name: '',
  description: '',
  document_category_id: null as number | null,
  active: true,
})

// Stage Modal
const isStageModalOpen = ref(false)
const stageForm = ref({
  id: null as number | null,
  code: '',
  name: '',
  is_initial: false,
  is_terminal: false,
  assigned_role_id: null as number | null,
  assigned_unit_id: null as number | null,
  sla_hours: 48,
})

// Transition Modal
const isTransitionModalOpen = ref(false)
const transitionForm = ref({
  from_state_id: null as number | null,
  to_state_id: null as number | null,
  action_code: 'approve',
  permission_id: null as number | null,
  requires_reason: false,
  requires_signature: false,
  prohibit_self_approval: true,
  approval_policy: 'single',
  quorum_count: null as number | null,
})

// ============================================================================
// 2. NUMBERING POLICIES STATE
// ============================================================================
const numberingPolicies = ref<NumberingPolicyItem[]>([])
const isNumberingModalOpen = ref(false)
const numberingForm = ref({
  id: null as number | null,
  code: '',
  name: '',
  prefix: 'WU',
  include_unit_code: true,
  include_category_code: true,
  year_format: 'ethiopian_year',
  sequence_length: 6,
  reset_policy: 'yearly',
  active: true,
})
const livePreview = ref<string>('')

// ============================================================================
// 3. ORGANIZATIONAL ROUTING POLICIES STATE
// ============================================================================
const routingPolicies = ref<RoutingPolicyItem[]>([])
const isRoutingModalOpen = ref(false)
const routingForm = ref({
  id: null as number | null,
  code: '',
  name: '',
  from_unit_id: null as number | null,
  to_unit_id: null as number | null,
  correspondence_type_id: null as number | null,
  document_category_id: null as number | null,
  priority_id: null as number | null,
  default_assignee_role_id: null as number | null,
  sla_hours: 48,
  escalation_unit_id: null as number | null,
  active: true,
  description: '',
})

// Route Matcher simulator
const simulator = ref({
  from_unit_id: null as number | null,
  correspondence_type_id: null as number | null,
  document_category_id: null as number | null,
  priority_id: null as number | null,
  result: null as RoutingPolicyItem | null,
  tested: false,
})

// ============================================================================
// 4. RETENTION POLICIES STATE
// ============================================================================
const retentionPolicies = ref<RetentionPolicyItem[]>([])
const isRetentionModalOpen = ref(false)
const retentionForm = ref({
  id: null as number | null,
  code: '',
  name: '',
  description: '',
  document_category_id: null as number | null,
  active: true,
  trigger_code: 'date_archived',
  duration_months: 60,
  disposition_action: 'retain',
  effective_from: new Date().toISOString().substring(0, 10),
})

const isNewRetentionVersionModalOpen = ref(false)
const retentionVersionForm = ref({
  policy_id: null as number | null,
  trigger_code: 'date_archived',
  duration_months: 60,
  disposition_action: 'retain',
  effective_from: new Date().toISOString().substring(0, 10),
})

// ============================================================================
// LIFECYCLE & DATA LOADING
// ============================================================================
onMounted(async () => {
  const queryTab = route.query.tab as string
  if (['workflows', 'numbering', 'routing', 'retention', 'catalogs'].includes(queryTab)) {
    activeTab.value = queryTab as TabType
  }
  await loadReferenceCatalogs()
  await loadActiveTabData()
})

watch(() => route.query.tab, (newTab) => {
  if (newTab && ['workflows', 'numbering', 'routing', 'retention', 'catalogs'].includes(newTab as string)) {
    activeTab.value = newTab as TabType
    loadActiveTabData()
  }
})

watch(activeTab, (tab) => {
  router.replace({ query: { ...route.query, tab } })
  loadActiveTabData()
})

async function loadReferenceCatalogs() {
  try {
    const [metaRes, unitsRes, rolesRes] = await Promise.all([
      metadataApi.getAll({ include_inactive: true }),
      organizationUnitsApi.list(),
      rolesApi.list(),
    ])
    const meta = metaRes.data.data
    documentCategories.value = meta.document_categories || []
    correspondenceTypes.value = meta.correspondence_types || []
    prioritiesList.value = meta.priorities || []
    organizationUnits.value = unitsRes.data.data || []
    rolesList.value = rolesRes.data.data || []

    // Fetch permissions for transition requirements
    const permsRes = await rolesApi.permissions()
    permissionsList.value = permsRes.data.data || []
  } catch (err: unknown) {
    toast.error(getApiErrorMessage(err, 'Failed to load contextual catalogs.'))
  }
}

async function loadActiveTabData() {
  loading.value = true
  try {
    if (activeTab.value === 'workflows') {
      const res = await workflowAdminApi.list()
      workflowDefinitions.value = res.data.data
      if (workflowDefinitions.value.length > 0 && !selectedWorkflow.value) {
        await selectWorkflow(workflowDefinitions.value[0].id)
      } else if (selectedWorkflow.value) {
        await selectWorkflow(selectedWorkflow.value.id)
      }
    } else if (activeTab.value === 'numbering') {
      const res = await numberingPoliciesApi.list()
      numberingPolicies.value = res.data.data
      await updateLivePreview()
    } else if (activeTab.value === 'routing') {
      const res = await routingPoliciesApi.list()
      routingPolicies.value = res.data.data
    } else if (activeTab.value === 'retention') {
      const res = await retentionPoliciesApi.list()
      retentionPolicies.value = res.data.data
    }
  } catch (err: unknown) {
    toast.error(getApiErrorMessage(err, 'Failed to load configuration records.'))
  } finally {
    loading.value = false
  }
}

// ============================================================================
// WORKFLOW ACTIONS
// ============================================================================
async function selectWorkflow(id: number) {
  try {
    const res = await workflowAdminApi.get(id)
    selectedWorkflow.value = res.data.data
    const versions = selectedWorkflow.value?.versions || []
    // Default to draft version if available, otherwise latest published
    selectedVersion.value = versions.find((v: WorkflowAdminVersion) => !v.published_at) || versions[0] || null
    validationResult.value = null
  } catch (err: unknown) {
    toast.error(getApiErrorMessage(err, 'Failed to load workflow definition details.'))
  }
}

function openCreateWorkflowModal() {
  workflowForm.value = {
    id: null,
    code: '',
    name: '',
    description: '',
    document_category_id: null,
    active: true,
  }
  isWorkflowModalOpen.value = true
}

function openEditWorkflowModal(def: WorkflowDefinition) {
  workflowForm.value = {
    id: def.id,
    code: def.code,
    name: def.name,
    description: def.description || '',
    document_category_id: def.document_category_id ?? null,
    active: def.active,
  }
  isWorkflowModalOpen.value = true
}

async function saveWorkflow() {
  try {
    if (workflowForm.value.id) {
      await workflowAdminApi.update(workflowForm.value.id, {
        name: workflowForm.value.name,
        description: workflowForm.value.description,
        document_category_id: workflowForm.value.document_category_id ? Number(workflowForm.value.document_category_id) : null,
        active: workflowForm.value.active,
      })
      toast.success('Workflow definition updated.')
    } else {
      const res = await workflowAdminApi.create({
        code: workflowForm.value.code,
        name: workflowForm.value.name,
        description: workflowForm.value.description,
        document_category_id: workflowForm.value.document_category_id ? Number(workflowForm.value.document_category_id) : null,
        active: workflowForm.value.active,
      })
      toast.success('Workflow definition created.')
      selectedWorkflow.value = res.data.data.definition
    }
    isWorkflowModalOpen.value = false
    await loadActiveTabData()
  } catch (err: unknown) {
    toast.error(getApiErrorMessage(err, 'Failed to save workflow definition.'))
  }
}

async function createDraftVersion() {
  if (!selectedWorkflow.value || !selectedVersion.value) return
  try {
    await workflowAdminApi.createVersion(selectedWorkflow.value.id, {
      clone_from_version_id: selectedVersion.value.id,
    })
    toast.success('New draft workflow version created.')
    await selectWorkflow(selectedWorkflow.value.id)
  } catch (err: unknown) {
    toast.error(getApiErrorMessage(err, 'Failed to create new draft version.'))
  }
}

function openCreateStageModal() {
  stageForm.value = {
    id: null,
    code: '',
    name: '',
    is_initial: false,
    is_terminal: false,
    assigned_role_id: null,
    assigned_unit_id: null,
    sla_hours: 48,
  }
  isStageModalOpen.value = true
}

function openEditStageModal(stage: WorkflowAdminState) {
  stageForm.value = {
    id: stage.id,
    code: stage.code,
    name: stage.name,
    is_initial: stage.is_initial,
    is_terminal: stage.is_terminal,
    assigned_role_id: stage.assigned_role_id ?? null,
    assigned_unit_id: stage.assigned_unit_id ?? null,
    sla_hours: stage.sla_hours || 48,
  }
  isStageModalOpen.value = true
}

async function saveStage() {
  if (!selectedWorkflow.value || !selectedVersion.value) return
  try {
    if (stageForm.value.id) {
      await workflowAdminApi.updateState(
        selectedWorkflow.value.id,
        selectedVersion.value.id,
        stageForm.value.id,
        {
          code: stageForm.value.code,
          name: stageForm.value.name,
          is_initial: stageForm.value.is_initial,
          is_terminal: stageForm.value.is_terminal,
          assigned_role_id: stageForm.value.assigned_role_id ? Number(stageForm.value.assigned_role_id) : null,
          assigned_unit_id: stageForm.value.assigned_unit_id ? Number(stageForm.value.assigned_unit_id) : null,
          sla_hours: stageForm.value.sla_hours ? Number(stageForm.value.sla_hours) : null,
        }
      )
      toast.success('Stage updated.')
    } else {
      await workflowAdminApi.createState(
        selectedWorkflow.value.id,
        selectedVersion.value.id,
        {
          code: stageForm.value.code,
          name: stageForm.value.name,
          is_initial: stageForm.value.is_initial,
          is_terminal: stageForm.value.is_terminal,
          assigned_role_id: stageForm.value.assigned_role_id ? Number(stageForm.value.assigned_role_id) : null,
          assigned_unit_id: stageForm.value.assigned_unit_id ? Number(stageForm.value.assigned_unit_id) : null,
          sla_hours: stageForm.value.sla_hours ? Number(stageForm.value.sla_hours) : null,
        }
      )
      toast.success('Stage created.')
    }
    isStageModalOpen.value = false
    await selectWorkflow(selectedWorkflow.value.id)
  } catch (err: unknown) {
    toast.error(getApiErrorMessage(err, 'Failed to save stage.'))
  }
}

async function deleteStage(stageId: number) {
  if (!selectedWorkflow.value || !selectedVersion.value) return
  if (!confirm('Are you sure you want to delete this stage and its transitions?')) return
  try {
    await workflowAdminApi.deleteState(selectedWorkflow.value.id, selectedVersion.value.id, stageId)
    toast.success('Stage deleted.')
    await selectWorkflow(selectedWorkflow.value.id)
  } catch (err: unknown) {
    toast.error(getApiErrorMessage(err, 'Failed to delete stage.'))
  }
}

function openCreateTransitionModal(fromStageId?: number) {
  transitionForm.value = {
    from_state_id: fromStageId || (selectedVersion.value?.states?.[0]?.id ?? null),
    to_state_id: null,
    action_code: 'approve',
    permission_id: permissionsList.value.find(p => p.code === 'workflow.review')?.id ?? null,
    requires_reason: false,
    requires_signature: false,
    prohibit_self_approval: true,
    approval_policy: 'single',
    quorum_count: null,
  }
  isTransitionModalOpen.value = true
}

async function saveTransition() {
  if (!selectedWorkflow.value || !selectedVersion.value) return
  try {
    await workflowAdminApi.createTransition(selectedWorkflow.value.id, selectedVersion.value.id, {
      from_state_id: Number(transitionForm.value.from_state_id),
      to_state_id: Number(transitionForm.value.to_state_id),
      action_code: transitionForm.value.action_code,
      permission_id: transitionForm.value.permission_id ? Number(transitionForm.value.permission_id) : null,
      requires_reason: transitionForm.value.requires_reason,
      requires_signature: transitionForm.value.requires_signature,
      prohibit_self_approval: transitionForm.value.prohibit_self_approval,
      approval_policy: transitionForm.value.approval_policy,
      quorum_count: transitionForm.value.quorum_count ? Number(transitionForm.value.quorum_count) : null,
    })
    toast.success('Transition added.')
    isTransitionModalOpen.value = false
    await selectWorkflow(selectedWorkflow.value.id)
  } catch (err: unknown) {
    toast.error(getApiErrorMessage(err, 'Failed to add transition.'))
  }
}

async function deleteTransition(transitionId: number) {
  if (!selectedWorkflow.value || !selectedVersion.value) return
  if (!confirm('Are you sure you want to remove this transition?')) return
  try {
    await workflowAdminApi.deleteTransition(selectedWorkflow.value.id, selectedVersion.value.id, transitionId)
    toast.success('Transition removed.')
    await selectWorkflow(selectedWorkflow.value.id)
  } catch (err: unknown) {
    toast.error(getApiErrorMessage(err, 'Failed to remove transition.'))
  }
}

async function validateWorkflowStructure() {
  if (!selectedWorkflow.value || !selectedVersion.value) return
  validating.value = true
  try {
    const res = await workflowAdminApi.validate(selectedWorkflow.value.id, selectedVersion.value.id)
    validationResult.value = res.data.data
    if (validationResult.value.valid) {
      toast.success('Workflow structure is complete and mathematically valid.')
    } else {
      toast.warning('Workflow graph has structural issues. Review details below.')
    }
  } catch (err: unknown) {
    toast.error(getApiErrorMessage(err, 'Validation request failed.'))
  } finally {
    validating.value = false
  }
}

async function publishWorkflowVersion() {
  if (!selectedWorkflow.value || !selectedVersion.value) return
  publishing.value = true
  try {
    await workflowAdminApi.publish(selectedWorkflow.value.id, selectedVersion.value.id)
    toast.success(`Workflow version ${selectedVersion.value.version_number} is now published and active!`)
    await selectWorkflow(selectedWorkflow.value.id)
  } catch (err: unknown) {
    toast.error(getApiErrorMessage(err, 'Publishing failed.'))
  } finally {
    publishing.value = false
  }
}

// ============================================================================
// NUMBERING SCHEME ACTIONS
// ============================================================================
async function updateLivePreview() {
  try {
    const res = await numberingPoliciesApi.preview({
      prefix: numberingForm.value.prefix,
      include_unit_code: numberingForm.value.include_unit_code,
      include_category_code: numberingForm.value.include_category_code,
      year_format: numberingForm.value.year_format,
      sequence_length: numberingForm.value.sequence_length,
      sample_sequence: 1,
    })
    livePreview.value = res.data.data.preview
  } catch (e) {
    livePreview.value = ''
  }
}

function openCreateNumberingModal() {
  numberingForm.value = {
    id: null,
    code: '',
    name: '',
    prefix: 'WU',
    include_unit_code: true,
    include_category_code: true,
    year_format: 'ethiopian_year',
    sequence_length: 6,
    reset_policy: 'yearly',
    active: true,
  }
  updateLivePreview()
  isNumberingModalOpen.value = true
}

function openEditNumberingModal(p: NumberingPolicyItem) {
  numberingForm.value = {
    id: p.id,
    code: p.code,
    name: p.name,
    prefix: p.prefix,
    include_unit_code: Boolean(p.include_unit_code),
    include_category_code: Boolean(p.include_category_code),
    year_format: p.year_format,
    sequence_length: p.sequence_length,
    reset_policy: p.reset_policy,
    active: Boolean(p.active),
  }
  updateLivePreview()
  isNumberingModalOpen.value = true
}

async function saveNumberingPolicy() {
  try {
    if (numberingForm.value.id) {
      await numberingPoliciesApi.update(numberingForm.value.id, {
        name: numberingForm.value.name,
        prefix: numberingForm.value.prefix,
        include_unit_code: numberingForm.value.include_unit_code,
        include_category_code: numberingForm.value.include_category_code,
        year_format: numberingForm.value.year_format as 'calendar_year' | 'ethiopian_year' | 'none',
        sequence_length: Number(numberingForm.value.sequence_length),
        reset_policy: numberingForm.value.reset_policy as 'yearly' | 'monthly' | 'never',
        active: numberingForm.value.active,
      })
      toast.success('Numbering scheme updated.')
    } else {
      await numberingPoliciesApi.create({
        code: numberingForm.value.code,
        name: numberingForm.value.name,
        prefix: numberingForm.value.prefix,
        include_unit_code: numberingForm.value.include_unit_code,
        include_category_code: numberingForm.value.include_category_code,
        year_format: numberingForm.value.year_format as 'calendar_year' | 'ethiopian_year' | 'none',
        sequence_length: Number(numberingForm.value.sequence_length),
        reset_policy: numberingForm.value.reset_policy as 'yearly' | 'monthly' | 'never',
        active: numberingForm.value.active,
      })
      toast.success('Numbering scheme registered.')
    }
    isNumberingModalOpen.value = false
    await loadActiveTabData()
  } catch (err: unknown) {
    toast.error(getApiErrorMessage(err, 'Failed to save numbering scheme.'))
  }
}

async function deleteNumberingPolicy(id: number) {
  if (!confirm('Are you sure you want to remove this numbering scheme?')) return
  try {
    await numberingPoliciesApi.delete(id)
    toast.success('Numbering scheme deleted.')
    await loadActiveTabData()
  } catch (err: unknown) {
    toast.error(getApiErrorMessage(err, 'Failed to delete numbering scheme.'))
  }
}

// ============================================================================
// ORGANIZATIONAL ROUTING ACTIONS
// ============================================================================
function openCreateRoutingModal() {
  routingForm.value = {
    id: null,
    code: '',
    name: '',
    from_unit_id: null,
    to_unit_id: null,
    correspondence_type_id: null,
    document_category_id: null,
    priority_id: null,
    default_assignee_role_id: null,
    sla_hours: 48,
    escalation_unit_id: null,
    active: true,
    description: '',
  }
  isRoutingModalOpen.value = true
}

function openEditRoutingModal(p: RoutingPolicyItem) {
  routingForm.value = {
    id: p.id,
    code: p.code,
    name: p.name,
    from_unit_id: p.from_unit_id ?? null,
    to_unit_id: p.to_unit_id,
    correspondence_type_id: p.correspondence_type_id ?? null,
    document_category_id: p.document_category_id ?? null,
    priority_id: p.priority_id ?? null,
    default_assignee_role_id: p.default_assignee_role_id ?? null,
    sla_hours: p.sla_hours,
    escalation_unit_id: p.escalation_unit_id ?? null,
    active: Boolean(p.active),
    description: p.description || '',
  }
  isRoutingModalOpen.value = true
}

async function saveRoutingPolicy() {
  try {
    const payload = {
      name: routingForm.value.name,
      from_unit_id: routingForm.value.from_unit_id ? Number(routingForm.value.from_unit_id) : null,
      to_unit_id: Number(routingForm.value.to_unit_id),
      correspondence_type_id: routingForm.value.correspondence_type_id ? Number(routingForm.value.correspondence_type_id) : null,
      document_category_id: routingForm.value.document_category_id ? Number(routingForm.value.document_category_id) : null,
      priority_id: routingForm.value.priority_id ? Number(routingForm.value.priority_id) : null,
      default_assignee_role_id: routingForm.value.default_assignee_role_id ? Number(routingForm.value.default_assignee_role_id) : null,
      sla_hours: Number(routingForm.value.sla_hours),
      escalation_unit_id: routingForm.value.escalation_unit_id ? Number(routingForm.value.escalation_unit_id) : null,
      active: routingForm.value.active,
      description: routingForm.value.description,
    }

    if (routingForm.value.id) {
      await routingPoliciesApi.update(routingForm.value.id, payload)
      toast.success('Routing policy updated.')
    } else {
      await routingPoliciesApi.create({
        code: routingForm.value.code,
        ...payload,
      })
      toast.success('Routing policy created.')
    }
    isRoutingModalOpen.value = false
    await loadActiveTabData()
  } catch (err: unknown) {
    toast.error(getApiErrorMessage(err, 'Failed to save routing policy.'))
  }
}

async function deleteRoutingPolicy(id: number) {
  if (!confirm('Are you sure you want to remove this routing policy?')) return
  try {
    await routingPoliciesApi.delete(id)
    toast.success('Routing policy removed.')
    await loadActiveTabData()
  } catch (err: unknown) {
    toast.error(getApiErrorMessage(err, 'Failed to delete routing policy.'))
  }
}

async function runRouteSimulator() {
  try {
    const res = await routingPoliciesApi.match({
      from_unit_id: simulator.value.from_unit_id ? Number(simulator.value.from_unit_id) : null,
      correspondence_type_id: simulator.value.correspondence_type_id ? Number(simulator.value.correspondence_type_id) : null,
      document_category_id: simulator.value.document_category_id ? Number(simulator.value.document_category_id) : null,
      priority_id: simulator.value.priority_id ? Number(simulator.value.priority_id) : null,
    })
    simulator.value.result = res.data.data.data
    simulator.value.tested = true
  } catch (err: unknown) {
    toast.error(getApiErrorMessage(err, 'Simulation check failed.'))
  }
}

// ============================================================================
// RETENTION ACTIONS
// ============================================================================
function openCreateRetentionModal() {
  retentionForm.value = {
    id: null,
    code: '',
    name: '',
    description: '',
    document_category_id: null,
    active: true,
    trigger_code: 'date_archived',
    duration_months: 60,
    disposition_action: 'retain',
    effective_from: new Date().toISOString().substring(0, 10),
  }
  isRetentionModalOpen.value = true
}

function openEditRetentionModal(p: RetentionPolicyItem) {
  retentionForm.value = {
    id: p.id,
    code: p.code,
    name: p.name,
    description: p.description || '',
    document_category_id: p.document_category_id ?? null,
    active: Boolean(p.active),
    trigger_code: p.activeVersion?.trigger_code || 'date_archived',
    duration_months: p.activeVersion?.duration_months || 60,
    disposition_action: p.activeVersion?.disposition_action || 'retain',
    effective_from: p.activeVersion?.effective_from || new Date().toISOString().substring(0, 10),
  }
  isRetentionModalOpen.value = true
}

async function saveRetentionPolicy() {
  try {
    if (retentionForm.value.id) {
      await retentionPoliciesApi.update(retentionForm.value.id, {
        name: retentionForm.value.name,
        description: retentionForm.value.description,
        document_category_id: retentionForm.value.document_category_id ? Number(retentionForm.value.document_category_id) : null,
        active: retentionForm.value.active,
      })
      toast.success('Retention schedule updated.')
    } else {
      await retentionPoliciesApi.create({
        code: retentionForm.value.code,
        name: retentionForm.value.name,
        description: retentionForm.value.description,
        document_category_id: retentionForm.value.document_category_id ? Number(retentionForm.value.document_category_id) : null,
        active: retentionForm.value.active,
        trigger_code: retentionForm.value.trigger_code,
        duration_months: Number(retentionForm.value.duration_months),
        disposition_action: retentionForm.value.disposition_action,
        effective_from: retentionForm.value.effective_from,
      })
      toast.success('Retention schedule created with version 1.')
    }
    isRetentionModalOpen.value = false
    await loadActiveTabData()
  } catch (err: unknown) {
    toast.error(getApiErrorMessage(err, 'Failed to save retention schedule.'))
  }
}

function openNewRetentionVersionModal(policyId: number) {
  retentionVersionForm.value = {
    policy_id: policyId,
    trigger_code: 'date_archived',
    duration_months: 60,
    disposition_action: 'retain',
    effective_from: new Date().toISOString().substring(0, 10),
  }
  isNewRetentionVersionModalOpen.value = true
}

async function saveRetentionVersion() {
  if (!retentionVersionForm.value.policy_id) return
  try {
    await retentionPoliciesApi.createVersion(retentionVersionForm.value.policy_id, {
      trigger_code: retentionVersionForm.value.trigger_code,
      duration_months: Number(retentionVersionForm.value.duration_months),
      disposition_action: retentionVersionForm.value.disposition_action,
      effective_from: retentionVersionForm.value.effective_from,
    })
    toast.success('New retention schedule version approved and activated.')
    isNewRetentionVersionModalOpen.value = false
    await loadActiveTabData()
  } catch (err: unknown) {
    toast.error(getApiErrorMessage(err, 'Failed to create retention version.'))
  }
}

async function deleteRetentionPolicy(id: number) {
  if (!confirm('Are you sure you want to delete this retention policy?')) return
  try {
    await retentionPoliciesApi.delete(id)
    toast.success('Retention policy deleted.')
    await loadActiveTabData()
  } catch (err: unknown) {
    toast.error(getApiErrorMessage(err, 'Failed to delete retention policy.'))
  }
}
</script>

<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-hairline pb-4">
      <div>
        <div class="flex items-center gap-2.5">
          <div class="p-2 bg-primary/10 rounded-lg text-primary">
            <Sliders class="w-5 h-5" />
          </div>
          <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-ink font-sans">Institutional Configuration Center</h1>
        </div>
        <p class="text-xs text-ink-muted mt-0.5">
          Dynamic operational governance: Workflow state machines, numbering schemas, inter-unit routing, retention schedules, and reference catalogs.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <BaseButton variant="secondary" size="sm" :loading="loading" @click="loadActiveTabData">
          <RefreshCw class="w-4 h-4 mr-1.5" /> Refresh
        </BaseButton>
      </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex flex-wrap gap-2 border-b border-hairline pb-2" role="tablist" aria-label="Configuration sections">
      <button
        type="button"
        role="tab"
        :aria-selected="activeTab === 'workflows'"
        @click="activeTab = 'workflows'"
        :class="[
          'flex items-center gap-2 px-3.5 py-2 rounded-lg text-sm font-medium transition-colors cursor-pointer',
          activeTab === 'workflows'
            ? 'bg-primary text-white shadow-xs'
            : 'text-ink-secondary hover:text-ink hover:bg-surface-subtle'
        ]"
      >
        <GitBranch class="w-4 h-4" /> Workflows & Approval Stages
      </button>

      <button
        type="button"
        role="tab"
        :aria-selected="activeTab === 'numbering'"
        @click="activeTab = 'numbering'"
        :class="[
          'flex items-center gap-2 px-3.5 py-2 rounded-lg text-sm font-medium transition-colors cursor-pointer',
          activeTab === 'numbering'
            ? 'bg-primary text-white shadow-xs'
            : 'text-ink-secondary hover:text-ink hover:bg-surface-subtle'
        ]"
      >
        <Hash class="w-4 h-4" /> Reference Numbering
      </button>

      <button
        type="button"
        role="tab"
        :aria-selected="activeTab === 'routing'"
        @click="activeTab = 'routing'"
        :class="[
          'flex items-center gap-2 px-3.5 py-2 rounded-lg text-sm font-medium transition-colors cursor-pointer',
          activeTab === 'routing'
            ? 'bg-primary text-white shadow-xs'
            : 'text-ink-secondary hover:text-ink hover:bg-surface-subtle'
        ]"
      >
        <Compass class="w-4 h-4" /> Organizational Routing
      </button>

      <button
        type="button"
        role="tab"
        :aria-selected="activeTab === 'retention'"
        @click="activeTab = 'retention'"
        :class="[
          'flex items-center gap-2 px-3.5 py-2 rounded-lg text-sm font-medium transition-colors cursor-pointer',
          activeTab === 'retention'
            ? 'bg-primary text-white shadow-xs'
            : 'text-ink-secondary hover:text-ink hover:bg-surface-subtle'
        ]"
      >
        <Archive class="w-4 h-4" /> Retention Schedules
      </button>

      <router-link
        to="/admin/reference-data"
        class="flex items-center justify-center sm:justify-start gap-2 px-3.5 py-2 rounded-lg text-sm font-medium text-ink-secondary hover:text-ink hover:bg-surface-subtle transition-colors w-full sm:w-auto sm:ml-auto"
      >
        <Database class="w-4 h-4" /> Reference Catalogs &rarr;
      </router-link>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="space-y-4">
      <LoadingSkeleton class="h-32 w-full" />
      <LoadingSkeleton class="h-64 w-full" />
    </div>

    <!-- ======================================================================== -->
    <!-- TAB 1: WORKFLOW DEFINITIONS & STAGE DESIGNER                             -->
    <!-- ======================================================================== -->
    <div v-else-if="activeTab === 'workflows'" class="space-y-6">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Left: Workflow Definitions Master List -->
        <div class="lg:col-span-4 space-y-4">
          <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-ink-muted">Definitions</h2>
            <BaseButton size="sm" variant="primary" @click="openCreateWorkflowModal">
              <Plus class="w-4 h-4 mr-1" /> New Workflow
            </BaseButton>
          </div>

          <div class="space-y-2">
            <div
              v-for="def in workflowDefinitions"
              :key="def.id"
              @click="selectWorkflow(def.id)"
              :class="[
                'p-4 rounded-lg border transition-all cursor-pointer',
                selectedWorkflow?.id === def.id
                  ? 'border-primary bg-primary/5 shadow-xs'
                  : 'border-hairline bg-surface hover:border-hairline-strong'
              ]"
            >
              <div class="flex items-start justify-between">
                <div>
                  <div class="flex items-center gap-2">
                    <span class="font-medium text-ink text-sm">{{ def.name }}</span>
                    <span
                      :class="[
                        'text-[10px] font-semibold px-1.5 py-0.5 rounded',
                        def.active ? 'bg-success/10 text-success' : 'bg-surface-muted text-ink-muted'
                      ]"
                    >
                      {{ def.active ? 'Active' : 'Inactive' }}
                    </span>
                  </div>
                  <p class="text-xs font-mono text-ink-muted mt-0.5">{{ def.code }}</p>
                </div>
                <button
                  @click.stop="openEditWorkflowModal(def)"
                  class="text-ink-muted hover:text-ink p-1 rounded hover:bg-surface-subtle"
                >
                  <Edit2 class="w-3.5 h-3.5" />
                </button>
              </div>

              <div class="mt-3 flex items-center gap-3 text-xs text-ink-secondary">
                <span class="flex items-center gap-1">
                  <Layers class="w-3.5 h-3.5 text-ink-muted" /> {{ def.versions_count || 1 }} versions
                </span>
                <span v-if="def.active_version" class="flex items-center gap-1 text-success">
                  <CheckCircle2 class="w-3.5 h-3.5" /> v{{ def.active_version.version_number }} Published
                </span>
                <span v-if="def.draft_version" class="flex items-center gap-1 text-warning">
                  <Clock class="w-3.5 h-3.5" /> v{{ def.draft_version.version_number }} Draft
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Active Version Stage Machine Designer -->
        <div v-if="selectedWorkflow" class="lg:col-span-8 space-y-6">
          <!-- Definition Details & Version Header -->
          <div class="bg-surface border border-hairline rounded-lg p-5 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div>
                <h2 class="text-lg font-serif font-bold text-ink">{{ selectedWorkflow.name }}</h2>
                <p class="text-xs text-ink-secondary mt-0.5">{{ selectedWorkflow.description || 'Institutional workflow policy definition' }}</p>
              </div>

              <div class="flex items-center gap-2">
                <div class="flex items-center gap-1.5 text-xs">
                  <span class="text-ink-muted">Version:</span>
                  <select
                    v-model="selectedVersion"
                    class="bg-surface-subtle border border-hairline rounded px-2 py-1 text-xs font-medium text-ink"
                  >
                    <option v-for="ver in selectedWorkflow.versions" :key="ver.id" :value="ver">
                      v{{ ver.version_number }} ({{ ver.published_at ? 'Published' : 'Draft' }})
                    </option>
                  </select>
                </div>

                <BaseButton
                  v-if="selectedVersion?.published_at"
                  size="sm"
                  variant="secondary"
                  @click="createDraftVersion"
                >
                  <Copy class="w-3.5 h-3.5 mr-1" /> New Draft v{{ (selectedWorkflow.versions?.[0]?.version_number || 1) + 1 }}
                </BaseButton>
              </div>
            </div>

            <!-- Version Status Banner -->
            <div
              :class="[
                'p-3 rounded-lg border text-xs flex items-center justify-between',
                selectedVersion?.published_at
                  ? 'bg-success/5 border-success/20 text-success'
                  : 'bg-warning/5 border-warning/20 text-warning'
              ]"
            >
              <div class="flex items-center gap-2">
                <Shield v-if="selectedVersion?.published_at" class="w-4 h-4" />
                <AlertCircle v-else class="w-4 h-4" />
                <span>
                  {{
                    selectedVersion?.published_at
                      ? `Version ${selectedVersion.version_number} is published and immutable. Active document workflows run under this specification.`
                      : `Version ${selectedVersion?.version_number} is in draft mode. You can modify stages and transitions before publishing.`
                  }}
                </span>
              </div>

              <div class="flex items-center gap-2">
                <BaseButton
                  size="sm"
                  variant="secondary"
                  :loading="validating"
                  @click="validateWorkflowStructure"
                >
                  Validate Structure
                </BaseButton>

                <BaseButton
                  v-if="!selectedVersion?.published_at"
                  size="sm"
                  variant="primary"
                  :loading="publishing"
                  @click="publishWorkflowVersion"
                >
                  Publish & Activate
                </BaseButton>
              </div>
            </div>

            <!-- Validation Feedback Alert -->
            <div
              v-if="validationResult"
              :class="[
                'p-4 rounded-lg border text-xs space-y-2',
                validationResult.valid
                  ? 'bg-success/5 border-success/30 text-success'
                  : 'bg-danger/5 border-danger/30 text-danger'
              ]"
            >
              <div class="flex items-center gap-2 font-semibold">
                <CheckCircle2 v-if="validationResult.valid" class="w-4 h-4" />
                <AlertTriangle v-else class="w-4 h-4" />
                <span>{{ validationResult.valid ? 'Workflow Graph Structure is Complete & Sound' : 'Structural Violations Detected' }}</span>
              </div>
              <ul v-if="!validationResult.valid" class="list-disc list-inside space-y-1 text-ink-secondary">
                <li v-for="(err, idx) in validationResult.errors" :key="idx" class="text-danger">{{ err }}</li>
              </ul>
            </div>
          </div>

          <!-- Stages & Transitions Designer Table -->
          <div class="bg-surface border border-hairline rounded-lg p-5 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="font-semibold text-sm text-ink">Stages & Routing Graph</h3>
                <p class="text-xs text-ink-muted">Configure approval slots, assigned offices/roles, SLAs, and decision transitions.</p>
              </div>

              <div v-if="!selectedVersion?.published_at" class="flex items-center gap-2">
                <BaseButton size="sm" variant="secondary" @click="openCreateTransitionModal()">
                  <ArrowRight class="w-3.5 h-3.5 mr-1" /> Add Transition
                </BaseButton>
                <BaseButton size="sm" variant="primary" @click="openCreateStageModal">
                  <Plus class="w-3.5 h-3.5 mr-1" /> Add Stage
                </BaseButton>
              </div>
            </div>

            <!-- Stages Flow Cards -->
            <div class="space-y-3">
              <div
                v-for="stage in selectedVersion?.states || []"
                :key="stage.id"
                class="p-4 rounded-lg border border-hairline bg-canvas/40 space-y-3"
              >
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                  <div class="flex items-center gap-2.5">
                    <span
                      :class="[
                        'px-2 py-0.5 rounded text-[11px] font-medium',
                        stage.is_initial
                          ? 'bg-primary/10 text-primary'
                          : stage.is_terminal
                          ? 'bg-success/10 text-success'
                          : 'bg-surface-muted text-ink-secondary'
                      ]"
                    >
                      {{ stage.is_initial ? 'Initial Stage' : stage.is_terminal ? 'Terminal Stage' : 'Stage' }}
                    </span>
                    <span class="font-semibold text-ink text-sm">{{ stage.name }}</span>
                    <span class="text-xs font-mono text-ink-muted">({{ stage.code }})</span>
                  </div>

                  <div class="flex items-center gap-3">
                    <span v-if="stage.sla_hours" class="text-xs text-ink-secondary flex items-center gap-1">
                      <Clock class="w-3.5 h-3.5 text-ink-muted" /> {{ stage.sla_hours }}h SLA
                    </span>
                    <span v-if="stage.assignedRole" class="text-xs text-primary flex items-center gap-1">
                      <Shield class="w-3.5 h-3.5" /> {{ stage.assignedRole.name }}
                    </span>
                    <span v-if="stage.assignedUnit" class="text-xs text-ink-secondary flex items-center gap-1">
                      <Building2 class="w-3.5 h-3.5 text-ink-muted" /> {{ stage.assignedUnit.name }}
                    </span>

                    <div v-if="!selectedVersion?.published_at" class="flex items-center gap-1">
                      <button
                        @click="openEditStageModal(stage)"
                        class="p-1 text-ink-muted hover:text-ink rounded hover:bg-surface"
                      >
                        <Edit2 class="w-3.5 h-3.5" />
                      </button>
                      <button
                        @click="deleteStage(stage.id)"
                        class="p-1 text-danger/70 hover:text-danger rounded hover:bg-danger/10"
                      >
                        <Trash2 class="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Outgoing Transitions from this state -->
                <div class="pl-4 border-l-2 border-hairline-strong space-y-1.5">
                  <div v-if="!stage.outgoingTransitions?.length" class="text-xs text-ink-muted italic">
                    {{ stage.is_terminal ? 'Final terminal approval state (No outgoing transitions needed).' : 'No outgoing transitions configured.' }}
                  </div>
                  <div
                    v-for="t in stage.outgoingTransitions || []"
                    :key="t.id"
                    class="flex items-center justify-between text-xs py-1 px-2.5 bg-surface border border-hairline rounded"
                  >
                    <div class="flex items-center gap-2">
                      <span class="font-medium text-ink">{{ t.action_code }}</span>
                      <ArrowRight class="w-3.5 h-3.5 text-ink-muted" />
                      <span class="font-semibold text-primary">{{ t.toState?.name || 'Stage ' + t.to_state_id }}</span>
                      <span class="text-ink-muted">({{ t.approval_policy }} policy)</span>
                      <span v-if="t.prohibit_self_approval" class="px-1.5 py-0.5 rounded bg-warning/10 text-warning text-[10px]">
                        Anti-Self-Approval
                      </span>
                    </div>

                    <div v-if="!selectedVersion?.published_at">
                      <button
                        @click="deleteTransition(t.id)"
                        class="text-ink-muted hover:text-danger p-0.5"
                      >
                        <X class="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ======================================================================== -->
    <!-- TAB 2: REFERENCE NUMBERING SCHEMES                                      -->
    <!-- ======================================================================== -->
    <div v-else-if="activeTab === 'numbering'" class="space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 class="text-base font-serif font-bold text-ink">Institutional Numbering Policies</h2>
          <p class="text-xs text-ink-secondary mt-0.5">
            Configure transaction-safe atomic sequence formats with support for Ethiopian fiscal years, department codes, and zero-padding.
          </p>
        </div>
        <BaseButton variant="primary" size="sm" @click="openCreateNumberingModal">
          <Plus class="w-4 h-4 mr-1" /> New Numbering Policy
        </BaseButton>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="policy in numberingPolicies"
          :key="policy.id"
          class="bg-surface border border-hairline rounded-lg p-5 shadow-xs space-y-3"
        >
          <div class="flex items-start justify-between">
            <div>
              <div class="flex items-center gap-2">
                <span class="font-semibold text-ink text-sm">{{ policy.name }}</span>
                <span
                  :class="[
                    'text-[10px] font-semibold px-1.5 py-0.5 rounded',
                    policy.active ? 'bg-success/10 text-success' : 'bg-surface-muted text-ink-muted'
                  ]"
                >
                  {{ policy.active ? 'Active' : 'Inactive' }}
                </span>
              </div>
              <p class="text-xs font-mono text-ink-muted mt-0.5">{{ policy.code }}</p>
            </div>

            <div class="flex items-center gap-1">
              <button
                @click="openEditNumberingModal(policy)"
                class="p-1 text-ink-muted hover:text-ink rounded hover:bg-surface-subtle"
              >
                <Edit2 class="w-3.5 h-3.5" />
              </button>
              <button
                v-if="!['DOC', 'CORR'].includes(policy.code)"
                @click="deleteNumberingPolicy(policy.id)"
                class="p-1 text-danger/70 hover:text-danger rounded hover:bg-danger/10"
              >
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          <!-- Format Specification details -->
          <div class="p-3 bg-surface-subtle rounded border border-hairline text-xs space-y-1 font-mono">
            <div class="text-ink-secondary flex justify-between">
              <span>Prefix:</span> <span class="text-ink font-semibold">{{ policy.prefix }}</span>
            </div>
            <div class="text-ink-secondary flex justify-between">
              <span>Year Format:</span> <span class="text-ink">{{ policy.year_format }}</span>
            </div>
            <div class="text-ink-secondary flex justify-between">
              <span>Unit Code Inset:</span> <span class="text-ink">{{ policy.include_unit_code ? 'Yes' : 'No' }}</span>
            </div>
            <div class="text-ink-secondary flex justify-between">
              <span>Reset Period:</span> <span class="text-ink">{{ policy.reset_policy }}</span>
            </div>
            <div class="text-ink-secondary flex justify-between">
              <span>Padding Length:</span> <span class="text-ink">{{ policy.sequence_length }} digits</span>
            </div>
          </div>

          <div class="pt-2 border-t border-hairline flex items-center justify-between text-xs text-ink-secondary">
            <span>Next Sequence Value:</span>
            <span class="font-mono font-semibold text-primary">#{{ policy.next_value || 1 }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ======================================================================== -->
    <!-- TAB 3: ORGANIZATIONAL ROUTING POLICIES                                   -->
    <!-- ======================================================================== -->
    <div v-else-if="activeTab === 'routing'" class="space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 class="text-base font-serif font-bold text-ink">Inter-Unit Routing Policies</h2>
          <p class="text-xs text-ink-secondary mt-0.5">
            Configure automatic movement paths, SLAs, default assignees, and escalation points between university departments.
          </p>
        </div>
        <BaseButton variant="primary" size="sm" @click="openCreateRoutingModal">
          <Plus class="w-4 h-4 mr-1" /> New Routing Policy
        </BaseButton>
      </div>

      <!-- Route Matcher Simulator -->
      <div class="bg-surface border border-hairline rounded-lg p-5 shadow-xs space-y-4">
        <div class="flex items-center gap-2 text-sm font-semibold text-ink">
          <Compass class="w-4 h-4 text-primary" />
          <span>Interactive Route Matcher Simulation</span>
        </div>
        <p class="text-xs text-ink-secondary">
          Test which routing rule and handling SLA will be applied when correspondence or documents are submitted with given attributes.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
          <div>
            <label class="block text-xs font-medium text-ink-secondary mb-1">From Unit</label>
            <select v-model="simulator.from_unit_id" class="w-full bg-surface border border-hairline rounded px-2.5 py-1.5 text-xs text-ink">
              <option :value="null">Any Origin Unit</option>
              <option v-for="u in organizationUnits" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-ink-secondary mb-1">Correspondence Type</label>
            <select v-model="simulator.correspondence_type_id" class="w-full bg-surface border border-hairline rounded px-2.5 py-1.5 text-xs text-ink">
              <option :value="null">Any Type</option>
              <option v-for="t in correspondenceTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-ink-secondary mb-1">Document Category</label>
            <select v-model="simulator.document_category_id" class="w-full bg-surface border border-hairline rounded px-2.5 py-1.5 text-xs text-ink">
              <option :value="null">Any Category</option>
              <option v-for="c in documentCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>

          <div class="flex items-end">
            <BaseButton size="sm" variant="secondary" class="w-full" @click="runRouteSimulator">
              Simulate Route Match
            </BaseButton>
          </div>
        </div>

        <div v-if="simulator.tested" class="p-3 bg-surface-subtle border border-hairline rounded text-xs">
          <div v-if="simulator.result" class="flex items-center gap-2 text-success font-medium">
            <CheckCircle2 class="w-4 h-4" />
            <span>Matched Policy: <strong>{{ simulator.result.name }}</strong> (Routes to: <strong>{{ simulator.result.toUnit?.name || simulator.result.to_unit?.name }}</strong> with <strong>{{ simulator.result.sla_hours }}h SLA</strong>)</span>
          </div>
          <div v-else class="text-ink-muted italic">
            No specific active routing rule matched these criteria; standard default department inbox routing will apply.
          </div>
        </div>
      </div>

      <!-- Routing Rules List -->
      <div class="bg-surface border border-hairline rounded-lg overflow-x-auto shadow-xs">
        <table class="w-full text-left text-xs min-w-[640px]">
          <thead class="bg-surface-subtle border-b border-hairline text-ink-secondary uppercase tracking-wider font-semibold">
            <tr>
              <th class="p-3">Policy Code / Name</th>
              <th class="p-3">Origin Unit</th>
              <th class="p-3">Destination Unit</th>
              <th class="p-3">SLA Target</th>
              <th class="p-3">Default Assignee Role</th>
              <th class="p-3">Escalation</th>
              <th class="p-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-hairline">
            <tr v-for="p in routingPolicies" :key="p.id" class="hover:bg-surface-subtle/50 transition-colors">
              <td class="p-3">
                <div class="font-semibold text-ink">{{ p.name }}</div>
                <div class="text-[10px] font-mono text-ink-muted">{{ p.code }}</div>
              </td>
              <td class="p-3">{{ p.fromUnit?.name || 'Any Origin Unit' }}</td>
              <td class="p-3 font-medium text-primary">{{ p.toUnit?.name }}</td>
              <td class="p-3">{{ p.sla_hours }} hours</td>
              <td class="p-3">{{ p.defaultAssigneeRole?.name || 'Unit Custodian' }}</td>
              <td class="p-3 text-ink-secondary">{{ p.escalationUnit?.name || 'None' }}</td>
              <td class="p-3 text-right space-x-1">
                <button @click="openEditRoutingModal(p)" class="p-1 text-ink-muted hover:text-ink rounded">
                  <Edit2 class="w-3.5 h-3.5" />
                </button>
                <button @click="deleteRoutingPolicy(p.id)" class="p-1 text-danger/70 hover:text-danger rounded">
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ======================================================================== -->
    <!-- TAB 4: RECORDS RETENTION SCHEDULES                                      -->
    <!-- ======================================================================== -->
    <div v-else-if="activeTab === 'retention'" class="space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 class="text-base font-serif font-bold text-ink">Records Retention & Disposal Schedules</h2>
          <p class="text-xs text-ink-secondary mt-0.5">
            Configure institutional retention durations, disposition actions, and automatic review calculation with strict legal hold protection.
          </p>
        </div>
        <BaseButton variant="primary" size="sm" @click="openCreateRetentionModal">
          <Plus class="w-4 h-4 mr-1" /> New Retention Schedule
        </BaseButton>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="policy in retentionPolicies"
          :key="policy.id"
          class="bg-surface border border-hairline rounded-lg p-5 shadow-xs space-y-4"
        >
          <div class="flex items-start justify-between">
            <div>
              <div class="flex items-center gap-2">
                <span class="font-semibold text-ink text-sm">{{ policy.name }}</span>
                <span
                  :class="[
                    'text-[10px] font-semibold px-1.5 py-0.5 rounded',
                    policy.active ? 'bg-success/10 text-success' : 'bg-surface-muted text-ink-muted'
                  ]"
                >
                  {{ policy.active ? 'Active' : 'Inactive' }}
                </span>
              </div>
              <p class="text-xs font-mono text-ink-muted mt-0.5">{{ policy.code }}</p>
            </div>

            <div class="flex items-center gap-1">
              <button
                @click="openEditRetentionModal(policy)"
                class="p-1 text-ink-muted hover:text-ink rounded hover:bg-surface-subtle"
              >
                <Edit2 class="w-3.5 h-3.5" />
              </button>
              <button
                @click="deleteRetentionPolicy(policy.id)"
                class="p-1 text-danger/70 hover:text-danger rounded hover:bg-danger/10"
              >
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          <div class="p-3 bg-surface-subtle rounded border border-hairline text-xs space-y-1.5">
            <div class="flex justify-between text-ink-secondary">
              <span>Category Link:</span>
              <span class="text-ink font-medium">{{ policy.documentCategory?.name || 'All Categories' }}</span>
            </div>
            <div class="flex justify-between text-ink-secondary">
              <span>Retention Period:</span>
              <span class="text-ink font-bold">{{ policy.activeVersion?.duration_months || 0 }} months ({{ ((policy.activeVersion?.duration_months || 0) / 12).toFixed(1) }} years)</span>
            </div>
            <div class="flex justify-between text-ink-secondary">
              <span>Trigger Code:</span>
              <span class="text-ink font-mono">{{ policy.activeVersion?.trigger_code || 'date_archived' }}</span>
            </div>
            <div class="flex justify-between text-ink-secondary">
              <span>Disposition Action:</span>
              <span
                :class="[
                  'px-1.5 py-0.5 rounded font-semibold text-[10px]',
                  policy.activeVersion?.disposition_action === 'retain'
                    ? 'bg-primary/10 text-primary'
                    : policy.activeVersion?.disposition_action === 'review'
                    ? 'bg-warning/10 text-warning'
                    : 'bg-danger/10 text-danger'
                ]"
              >
                {{ policy.activeVersion?.disposition_action || 'retain' }}
              </span>
            </div>
          </div>

          <div class="flex items-center justify-between text-xs pt-2 border-t border-hairline">
            <span class="text-ink-muted">Version {{ policy.activeVersion?.version_number || 1 }}</span>
            <button
              @click="openNewRetentionVersionModal(policy.id)"
              class="text-xs text-primary font-medium hover:underline flex items-center gap-1"
            >
              <Plus class="w-3 h-3" /> New Schedule Version
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ======================================================================== -->
    <!-- MODALS: WORKFLOW DEFINITION, STAGE & TRANSITION                          -->
    <!-- ======================================================================== -->
    <ModalDialog
      :is-open="isWorkflowModalOpen"
      :title="workflowForm.id ? 'Edit Workflow Definition' : 'Create Workflow Definition'"
      @close="isWorkflowModalOpen = false"
    >
      <div class="space-y-4">
        <BaseInput
          v-if="!workflowForm.id"
          label="Workflow Code"
          v-model="workflowForm.code"
          placeholder="e.g. ACADEMIC_PROMOTION"
          required
        />
        <BaseInput
          label="Workflow Name"
          v-model="workflowForm.name"
          placeholder="e.g. Academic Promotion Approval Workflow"
          required
        />
        <div>
          <label class="block text-xs font-medium text-ink-secondary mb-1">Applicable Document Category</label>
          <select v-model="workflowForm.document_category_id" class="w-full bg-surface border border-hairline rounded px-3 py-2 text-sm text-ink">
            <option :value="null">Any Document Category</option>
            <option v-for="cat in documentCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-ink-secondary mb-1">Description</label>
          <textarea
            v-model="workflowForm.description"
            rows="3"
            class="w-full bg-surface border border-hairline rounded px-3 py-2 text-sm text-ink"
            placeholder="Institutional purpose and governance context of this workflow..."
          ></textarea>
        </div>
        <div class="flex items-center gap-2">
          <input type="checkbox" id="wf-active" v-model="workflowForm.active" class="rounded text-primary" />
          <label for="wf-active" class="text-sm text-ink">Active for document workflows</label>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-2">
          <BaseButton variant="secondary" @click="isWorkflowModalOpen = false">Cancel</BaseButton>
          <BaseButton variant="primary" @click="saveWorkflow">Save Definition</BaseButton>
        </div>
      </template>
    </ModalDialog>

    <ModalDialog
      :is-open="isStageModalOpen"
      :title="stageForm.id ? 'Edit Workflow Stage' : 'Add Workflow Stage'"
      @close="isStageModalOpen = false"
    >
      <div class="space-y-4">
        <BaseInput label="Stage Code" v-model="stageForm.code" placeholder="e.g. COMMITTEE_REVIEW" required />
        <BaseInput label="Stage Name" v-model="stageForm.name" placeholder="e.g. Faculty Committee Review" required />
        <BaseInput label="Target SLA (Hours)" type="number" v-model.number="stageForm.sla_hours" required />

        <div>
          <label class="block text-xs font-medium text-ink-secondary mb-1">Assigned Role</label>
          <select v-model="stageForm.assigned_role_id" class="w-full bg-surface border border-hairline rounded px-3 py-2 text-sm text-ink">
            <option :value="null">None (Any authorized role)</option>
            <option v-for="r in rolesList" :key="r.id" :value="r.id">{{ r.name }}</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-medium text-ink-secondary mb-1">Assigned Unit</label>
          <select v-model="stageForm.assigned_unit_id" class="w-full bg-surface border border-hairline rounded px-3 py-2 text-sm text-ink">
            <option :value="null">None (Document Custodian Unit)</option>
            <option v-for="u in organizationUnits" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>

        <div class="flex items-center gap-4 pt-2">
          <div class="flex items-center gap-2">
            <input type="checkbox" id="stg-init" v-model="stageForm.is_initial" class="rounded text-primary" />
            <label for="stg-init" class="text-sm text-ink">Initial Stage</label>
          </div>
          <div class="flex items-center gap-2">
            <input type="checkbox" id="stg-term" v-model="stageForm.is_terminal" class="rounded text-primary" />
            <label for="stg-term" class="text-sm text-ink">Terminal Stage</label>
          </div>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-2">
          <BaseButton variant="secondary" @click="isStageModalOpen = false">Cancel</BaseButton>
          <BaseButton variant="primary" @click="saveStage">Save Stage</BaseButton>
        </div>
      </template>
    </ModalDialog>

    <ModalDialog
      :is-open="isTransitionModalOpen"
      title="Add Transition Between Stages"
      @close="isTransitionModalOpen = false"
    >
      <div class="space-y-4">
        <div>
          <label class="block text-xs font-medium text-ink-secondary mb-1">Origin Stage</label>
          <select v-model="transitionForm.from_state_id" class="w-full bg-surface border border-hairline rounded px-3 py-2 text-sm text-ink">
            <option v-for="s in selectedVersion?.states?.filter(x => !x.is_terminal) || []" :key="s.id" :value="s.id">
              {{ s.name }} ({{ s.code }})
            </option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-medium text-ink-secondary mb-1">Target Stage</label>
          <select v-model="transitionForm.to_state_id" class="w-full bg-surface border border-hairline rounded px-3 py-2 text-sm text-ink">
            <option v-for="s in selectedVersion?.states?.filter(x => x.id !== transitionForm.from_state_id) || []" :key="s.id" :value="s.id">
              {{ s.name }} ({{ s.code }})
            </option>
          </select>
        </div>

        <BaseInput label="Action Code" v-model="transitionForm.action_code" placeholder="e.g. approve, endorse, forward" required />

        <div>
          <label class="block text-xs font-medium text-ink-secondary mb-1">Approval Consensus Policy</label>
          <select v-model="transitionForm.approval_policy" class="w-full bg-surface border border-hairline rounded px-3 py-2 text-sm text-ink">
            <option value="single">Single Assignee Decision</option>
            <option value="all_required">All Committee Members Required (Unanimous)</option>
            <option value="any_required">Any Committee Member May Approve</option>
            <option value="majority">Simple Majority (&gt; 50%)</option>
            <option value="quorum">Quorum Count</option>
          </select>
        </div>

        <div class="space-y-2 pt-2">
          <div class="flex items-center gap-2">
            <input type="checkbox" id="tr-anti" v-model="transitionForm.prohibit_self_approval" class="rounded text-primary" />
            <label for="tr-anti" class="text-sm text-ink">Enforce Anti-Self-Approval (Submitter cannot approve their own document)</label>
          </div>
          <div class="flex items-center gap-2">
            <input type="checkbox" id="tr-sig" v-model="transitionForm.requires_signature" class="rounded text-primary" />
            <label for="tr-sig" class="text-sm text-ink">Require Cryptographic Signature or Stamp</label>
          </div>
          <div class="flex items-center gap-2">
            <input type="checkbox" id="tr-rsn" v-model="transitionForm.requires_reason" class="rounded text-primary" />
            <label for="tr-rsn" class="text-sm text-ink">Require Written Institutional Justification</label>
          </div>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-2">
          <BaseButton variant="secondary" @click="isTransitionModalOpen = false">Cancel</BaseButton>
          <BaseButton variant="primary" @click="saveTransition">Create Transition</BaseButton>
        </div>
      </template>
    </ModalDialog>

    <!-- NUMBERING MODAL -->
    <ModalDialog
      :is-open="isNumberingModalOpen"
      :title="numberingForm.id ? 'Edit Numbering Policy' : 'Create Numbering Policy'"
      @close="isNumberingModalOpen = false"
    >
      <div class="space-y-4">
        <BaseInput
          v-if="!numberingForm.id"
          label="Sequence Code"
          v-model="numberingForm.code"
          placeholder="e.g. SENATE"
          required
        />
        <BaseInput label="Policy Name" v-model="numberingForm.name" placeholder="e.g. University Senate Directives" required />
        <BaseInput
          label="Prefix"
          v-model="numberingForm.prefix"
          placeholder="e.g. WU-SEN"
          @input="updateLivePreview"
          required
        />

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-ink-secondary mb-1">Year Format</label>
            <select
              v-model="numberingForm.year_format"
              @change="updateLivePreview"
              class="w-full bg-surface border border-hairline rounded px-3 py-2 text-sm text-ink"
            >
              <option value="ethiopian_year">Ethiopian Fiscal Year</option>
              <option value="calendar_year">Gregorian Calendar Year</option>
              <option value="none">No Year Inset</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-ink-secondary mb-1">Reset Rule</label>
            <select v-model="numberingForm.reset_policy" class="w-full bg-surface border border-hairline rounded px-3 py-2 text-sm text-ink">
              <option value="yearly">Annually</option>
              <option value="monthly">Monthly</option>
              <option value="never">Never (All-Time Continuous)</option>
            </select>
          </div>
        </div>

        <BaseInput
          label="Zero Padding Length (Digits)"
          type="number"
          v-model.number="numberingForm.sequence_length"
          @input="updateLivePreview"
          required
        />

        <div class="flex items-center gap-4">
          <div class="flex items-center gap-2">
            <input
              type="checkbox"
              id="num-unit"
              v-model="numberingForm.include_unit_code"
              @change="updateLivePreview"
              class="rounded text-primary"
            />
            <label for="num-unit" class="text-sm text-ink">Include Unit Code</label>
          </div>
          <div class="flex items-center gap-2">
            <input
              type="checkbox"
              id="num-cat"
              v-model="numberingForm.include_category_code"
              @change="updateLivePreview"
              class="rounded text-primary"
            />
            <label for="num-cat" class="text-sm text-ink">Include Category Code</label>
          </div>
        </div>

        <!-- Live Preview Banner -->
        <div class="p-3 bg-surface-subtle border border-hairline rounded text-xs space-y-1">
          <span class="text-ink-secondary font-medium">Live Reference Preview:</span>
          <div class="font-mono text-sm font-bold text-primary">{{ livePreview || 'Formatting...' }}</div>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-2">
          <BaseButton variant="secondary" @click="isNumberingModalOpen = false">Cancel</BaseButton>
          <BaseButton variant="primary" @click="saveNumberingPolicy">Save Numbering Scheme</BaseButton>
        </div>
      </template>
    </ModalDialog>

    <!-- ROUTING MODAL -->
    <ModalDialog
      :is-open="isRoutingModalOpen"
      :title="routingForm.id ? 'Edit Routing Policy' : 'Create Routing Policy'"
      @close="isRoutingModalOpen = false"
    >
      <div class="space-y-4">
        <BaseInput v-if="!routingForm.id" label="Policy Code" v-model="routingForm.code" placeholder="e.g. VPR_TO_ACAD" required />
        <BaseInput label="Policy Name" v-model="routingForm.name" placeholder="e.g. Vice President Research to Academic Affairs" required />

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-ink-secondary mb-1">Origin Unit</label>
            <select v-model="routingForm.from_unit_id" class="w-full bg-surface border border-hairline rounded px-3 py-2 text-sm text-ink">
              <option :value="null">Any Origin Unit</option>
              <option v-for="u in organizationUnits" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-ink-secondary mb-1">Destination Unit</label>
            <select v-model="routingForm.to_unit_id" class="w-full bg-surface border border-hairline rounded px-3 py-2 text-sm text-ink" required>
              <option v-for="u in organizationUnits" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-ink-secondary mb-1">Correspondence Type</label>
            <select v-model="routingForm.correspondence_type_id" class="w-full bg-surface border border-hairline rounded px-3 py-2 text-sm text-ink">
              <option :value="null">Any Type</option>
              <option v-for="t in correspondenceTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-ink-secondary mb-1">Priority Trigger</label>
            <select v-model="routingForm.priority_id" class="w-full bg-surface border border-hairline rounded px-3 py-2 text-sm text-ink">
              <option :value="null">Any Priority</option>
              <option v-for="p in prioritiesList" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <BaseInput label="Handling SLA (Hours)" type="number" v-model.number="routingForm.sla_hours" required />
          <div>
            <label class="block text-xs font-medium text-ink-secondary mb-1">Default Assignee Role</label>
            <select v-model="routingForm.default_assignee_role_id" class="w-full bg-surface border border-hairline rounded px-3 py-2 text-sm text-ink">
              <option :value="null">None (Unit Custodian)</option>
              <option v-for="r in rolesList" :key="r.id" :value="r.id">{{ r.name }}</option>
            </select>
          </div>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-2">
          <BaseButton variant="secondary" @click="isRoutingModalOpen = false">Cancel</BaseButton>
          <BaseButton variant="primary" @click="saveRoutingPolicy">Save Routing Policy</BaseButton>
        </div>
      </template>
    </ModalDialog>

    <!-- RETENTION MODAL -->
    <ModalDialog
      :is-open="isRetentionModalOpen"
      :title="retentionForm.id ? 'Edit Retention Schedule' : 'Create Retention Schedule'"
      @close="isRetentionModalOpen = false"
    >
      <div class="space-y-4">
        <BaseInput v-if="!retentionForm.id" label="Schedule Code" v-model="retentionForm.code" placeholder="e.g. FIN_AUDIT_7Y" required />
        <BaseInput label="Schedule Name" v-model="retentionForm.name" placeholder="e.g. Financial Audit 7-Year Schedule" required />

        <div>
          <label class="block text-xs font-medium text-ink-secondary mb-1">Document Category</label>
          <select v-model="retentionForm.document_category_id" class="w-full bg-surface border border-hairline rounded px-3 py-2 text-sm text-ink">
            <option :value="null">All Categories</option>
            <option v-for="cat in documentCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
          </select>
        </div>

        <div v-if="!retentionForm.id" class="grid grid-cols-2 gap-3">
          <BaseInput label="Duration (Months)" type="number" v-model.number="retentionForm.duration_months" required />
          <div>
            <label class="block text-xs font-medium text-ink-secondary mb-1">Disposition Action</label>
            <select v-model="retentionForm.disposition_action" class="w-full bg-surface border border-hairline rounded px-3 py-2 text-sm text-ink">
              <option value="retain">Permanent Archival (Retain)</option>
              <option value="review">Review Before Disposal</option>
              <option value="destroy">Secure Destruction</option>
              <option value="transfer">External Archival Transfer</option>
            </select>
          </div>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-2">
          <BaseButton variant="secondary" @click="isRetentionModalOpen = false">Cancel</BaseButton>
          <BaseButton variant="primary" @click="saveRetentionPolicy">Save Retention Schedule</BaseButton>
        </div>
      </template>
    </ModalDialog>

    <!-- NEW RETENTION VERSION MODAL -->
    <ModalDialog
      :is-open="isNewRetentionVersionModalOpen"
      title="Create New Retention Policy Version"
      @close="isNewRetentionVersionModalOpen = false"
    >
      <div class="space-y-4">
        <BaseInput label="Duration (Months)" type="number" v-model.number="retentionVersionForm.duration_months" required />
        <div>
          <label class="block text-xs font-medium text-ink-secondary mb-1">Disposition Action</label>
          <select v-model="retentionVersionForm.disposition_action" class="w-full bg-surface border border-hairline rounded px-3 py-2 text-sm text-ink">
            <option value="retain">Permanent Archival (Retain)</option>
            <option value="review">Review Before Disposal</option>
            <option value="destroy">Secure Destruction</option>
            <option value="transfer">External Archival Transfer</option>
          </select>
        </div>
        <BaseInput label="Effective Date" type="date" v-model="retentionVersionForm.effective_from" required />
      </div>
      <template #footer>
        <div class="flex justify-end gap-2">
          <BaseButton variant="secondary" @click="isNewRetentionVersionModalOpen = false">Cancel</BaseButton>
          <BaseButton variant="primary" @click="saveRetentionVersion">Approve Version</BaseButton>
        </div>
      </template>
    </ModalDialog>
  </div>
</template>


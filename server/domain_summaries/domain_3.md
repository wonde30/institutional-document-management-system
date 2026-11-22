## Domain 3: Workflow & Routing (11 Models)

### Model: `WorkflowDefinition`
- **(1) Table & PK:** table=`workflow_definitions`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[code, name, description, document_category_id, active]`
- **(3) Casts:** `active`: `boolean`, `document_category_id`: `integer`
- **(4) Relationships (3):**
  - `versions()`: **HasMany** -> `WorkflowVersion` (FK=`definition_id`, Local/OwnerKey=`id`)
  - `activeVersion()`: **HasOne** -> `WorkflowVersion` (FK=`definition_id`, Local/OwnerKey=`id`)
  - `documentCategory()`: **BelongsTo** -> `DocumentCategory` (FK=`document_category_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `WorkflowVersion`
- **(1) Table & PK:** table=`workflow_versions`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[definition_id, version_number, created_by, published_at, created_at]`
- **(3) Casts:** `version_number`: `integer`, `created_at`: `datetime`, `published_at`: `datetime`
- **(4) Relationships (5):**
  - `definition()`: **BelongsTo** -> `WorkflowDefinition` (FK=`definition_id`, Local/OwnerKey=`id`)
  - `workflowDefinition()`: **BelongsTo** -> `WorkflowDefinition` (FK=`definition_id`, Local/OwnerKey=`id`)
  - `createdBy()`: **BelongsTo** -> `User` (FK=`created_by`, Local/OwnerKey=`id`)
  - `states()`: **HasMany** -> `WorkflowState` (FK=`workflow_version_id`, Local/OwnerKey=`id`)
  - `transitions()`: **HasManyThrough** -> `WorkflowTransition` (FK=`None`, Local/OwnerKey=`None`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** methods: `[boot]`; events: `[creating]`
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `WorkflowState`
- **(1) Table & PK:** table=`workflow_states`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[workflow_version_id, code, name, is_initial, is_terminal, assigned_role_id, assigned_unit_id, sla_hours]`
- **(3) Casts:** `is_initial`: `boolean`, `is_terminal`: `boolean`, `initial_slot`: `integer`, `assigned_role_id`: `integer`, `assigned_unit_id`: `integer`, `sla_hours`: `integer`
- **(4) Relationships (5):**
  - `workflowVersion()`: **BelongsTo** -> `WorkflowVersion` (FK=`workflow_version_id`, Local/OwnerKey=`id`)
  - `assignedRole()`: **BelongsTo** -> `Role` (FK=`assigned_role_id`, Local/OwnerKey=`id`)
  - `assignedUnit()`: **BelongsTo** -> `OrganizationUnit` (FK=`assigned_unit_id`, Local/OwnerKey=`id`)
  - `outgoingTransitions()`: **HasMany** -> `WorkflowTransition` (FK=`from_state_id`, Local/OwnerKey=`id`)
  - `incomingTransitions()`: **HasMany** -> `WorkflowTransition` (FK=`to_state_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `WorkflowTransition`
- **(1) Table & PK:** table=`workflow_transitions`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[from_state_id, to_state_id, action_code, permission_id, requires_reason, requires_signature, prohibit_self_approval, approval_policy, quorum_count, quorum_percentage, join_policy, fork_state_ids]`
- **(3) Casts:** `requires_reason`: `boolean`, `requires_signature`: `boolean`, `prohibit_self_approval`: `boolean`, `quorum_count`: `integer`, `quorum_percentage`: `decimal:2`, `fork_state_ids`: `array`
- **(4) Relationships (3):**
  - `fromState()`: **BelongsTo** -> `WorkflowState` (FK=`from_state_id`, Local/OwnerKey=`id`)
  - `toState()`: **BelongsTo** -> `WorkflowState` (FK=`to_state_id`, Local/OwnerKey=`id`)
  - `permission()`: **BelongsTo** -> `Permission` (FK=`permission_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `WorkflowInstance`
- **(1) Table & PK:** table=`workflow_instances`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[document_version_id, workflow_version_id, started_by, started_at, parent_instance_id]`
- **(3) Casts:** `started_at`: `datetime`
- **(4) Relationships (8):**
  - `documentVersion()`: **BelongsTo** -> `DocumentVersion` (FK=`document_version_id`, Local/OwnerKey=`id`)
  - `workflowVersion()`: **BelongsTo** -> `WorkflowVersion` (FK=`workflow_version_id`, Local/OwnerKey=`id`)
  - `startedBy()`: **BelongsTo** -> `User` (FK=`started_by`, Local/OwnerKey=`id`)
  - `initiator()`: **BelongsTo** -> `User` (FK=`started_by`, Local/OwnerKey=`id`)
  - `parentInstance()`: **BelongsTo** -> `WorkflowInstance` (FK=`parent_instance_id`, Local/OwnerKey=`id`)
  - `childInstances()`: **HasMany** -> `WorkflowInstance` (FK=`parent_instance_id`, Local/OwnerKey=`id`)
  - `tasks()`: **HasMany** -> `WorkflowTask` (FK=`instance_id`, Local/OwnerKey=`id`)
  - `events()`: **HasMany** -> `WorkflowEvent` (FK=`instance_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** methods: `[booted]`; events: `[creating]`
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `WorkflowTask`
- **(1) Table & PK:** table=`workflow_tasks`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[instance_id, state_id, assigned_user_id, assigned_unit_id, required_role_id, delegated_to_user_id, delegated_by, delegation_reason, branch_key, created_at, due_at, closed_at]`
- **(3) Casts:** `created_at`: `datetime`, `due_at`: `datetime`, `closed_at`: `datetime`
- **(4) Relationships (10):**
  - `instance()`: **BelongsTo** -> `WorkflowInstance` (FK=`instance_id`, Local/OwnerKey=`id`)
  - `state()`: **BelongsTo** -> `WorkflowState` (FK=`state_id`, Local/OwnerKey=`id`)
  - `assignedUser()`: **BelongsTo** -> `User` (FK=`assigned_user_id`, Local/OwnerKey=`id`)
  - `assignedUnit()`: **BelongsTo** -> `OrganizationUnit` (FK=`assigned_unit_id`, Local/OwnerKey=`id`)
  - `requiredRole()`: **BelongsTo** -> `Role` (FK=`required_role_id`, Local/OwnerKey=`id`)
  - `delegatedTo()`: **BelongsTo** -> `User` (FK=`delegated_to_user_id`, Local/OwnerKey=`id`)
  - `delegatedBy()`: **BelongsTo** -> `User` (FK=`delegated_by`, Local/OwnerKey=`id`)
  - `participants()`: **HasMany** -> `WorkflowTaskParticipant` (FK=`task_id`, Local/OwnerKey=`id`)
  - `participantUsers()`: **BelongsToMany** -> `User` (FK=`task_id`, Local/OwnerKey=`user_id`, pivot=`workflow_task_participants`)
  - `decision()`: **HasOne** -> `WorkflowTaskDecision` (FK=`task_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** methods: `[booted]`; events: `[creating]`
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `WorkflowTaskParticipant`
- **(1) Table & PK:** table=`workflow_task_participants`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[task_id, user_id, participation_role, status, assigned_at, responded_at]`
- **(3) Casts:** `assigned_at`: `datetime`, `responded_at`: `datetime`
- **(4) Relationships (2):**
  - `task()`: **BelongsTo** -> `WorkflowTask` (FK=`task_id`, Local/OwnerKey=`id`)
  - `user()`: **BelongsTo** -> `User` (FK=`user_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** methods: `[booted]`; events: `[creating]`
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `WorkflowTaskDecision`
- **(1) Table & PK:** table=`workflow_task_decisions`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[task_id, decided_by, decision, reason, decided_at]`
- **(3) Casts:** `decided_at`: `datetime`
- **(4) Relationships (2):**
  - `task()`: **BelongsTo** -> `WorkflowTask` (FK=`task_id`, Local/OwnerKey=`id`)
  - `decidedBy()`: **BelongsTo** -> `User` (FK=`decided_by`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `WorkflowEvent`
- **(1) Table & PK:** table=`workflow_events`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[instance_id, sequence_number, transition_id, actor_id, event_kind, reason, occurred_at, idempotency_key]`
- **(3) Casts:** `sequence_number`: `integer`, `occurred_at`: `datetime`
- **(4) Relationships (3):**
  - `instance()`: **BelongsTo** -> `WorkflowInstance` (FK=`instance_id`, Local/OwnerKey=`id`)
  - `transition()`: **BelongsTo** -> `WorkflowTransition` (FK=`transition_id`, Local/OwnerKey=`id`)
  - `actor()`: **BelongsTo** -> `User` (FK=`actor_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `WorkflowDecisionEvent`
- **(1) Table & PK:** table=`workflow_decision_events`, PK=`decision_id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[decision_id, event_id]`
- **(3) Casts:** `decision_id`: `int`
- **(4) Relationships (2):**
  - `decision()`: **BelongsTo** -> `WorkflowTaskDecision` (FK=`decision_id`, Local/OwnerKey=`id`)
  - `event()`: **BelongsTo** -> `WorkflowEvent` (FK=`event_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `RoutingPolicy`
- **(1) Table & PK:** table=`routing_policies`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[code, name, from_unit_id, to_unit_id, correspondence_type_id, document_category_id, priority_id, default_assignee_role_id, sla_hours, escalation_unit_id, active, description]`
- **(3) Casts:** `from_unit_id`: `integer`, `to_unit_id`: `integer`, `sla_hours`: `integer`, `active`: `boolean`
- **(4) Relationships (7):**
  - `fromUnit()`: **BelongsTo** -> `OrganizationUnit` (FK=`from_unit_id`, Local/OwnerKey=`id`)
  - `toUnit()`: **BelongsTo** -> `OrganizationUnit` (FK=`to_unit_id`, Local/OwnerKey=`id`)
  - `correspondenceType()`: **BelongsTo** -> `CorrespondenceType` (FK=`correspondence_type_id`, Local/OwnerKey=`id`)
  - `documentCategory()`: **BelongsTo** -> `DocumentCategory` (FK=`document_category_id`, Local/OwnerKey=`id`)
  - `priority()`: **BelongsTo** -> `Priority` (FK=`priority_id`, Local/OwnerKey=`id`)
  - `defaultAssigneeRole()`: **BelongsTo** -> `Role` (FK=`default_assignee_role_id`, Local/OwnerKey=`id`)
  - `escalationUnit()`: **BelongsTo** -> `OrganizationUnit` (FK=`escalation_unit_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

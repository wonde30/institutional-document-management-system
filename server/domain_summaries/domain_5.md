## Domain 5: Assignments & Tasks (5 Models)

### Model: `DocumentAssignment`
- **(1) Table & PK:** table=`document_assignments`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[document_id, assigned_user_id, assigned_unit_id, assigned_by, assigned_at, due_at, instruction]`
- **(3) Casts:** `assigned_at`: `datetime`, `due_at`: `datetime`
- **(4) Relationships (10):**
  - `document()`: **BelongsTo** -> `Document` (FK=`document_id`, Local/OwnerKey=`id`)
  - `assignedUser()`: **BelongsTo** -> `User` (FK=`assigned_user_id`, Local/OwnerKey=`id`)
  - `assignedUnit()`: **BelongsTo** -> `OrganizationUnit` (FK=`assigned_unit_id`, Local/OwnerKey=`id`)
  - `assignedBy()`: **BelongsTo** -> `User` (FK=`assigned_by`, Local/OwnerKey=`id`)
  - `participants()`: **HasMany** -> `AssignmentParticipant` (FK=`assignment_id`, Local/OwnerKey=`id`)
  - `participantUsers()`: **BelongsToMany** -> `User` (FK=`assignment_id`, Local/OwnerKey=`user_id`, pivot=`assignment_participants`)
  - `responses()`: **HasMany** -> `AssignmentResponse` (FK=`assignment_id`, Local/OwnerKey=`id`)
  - `fulfillingResponses()`: **HasMany** -> `AssignmentResponse` (FK=`assignment_id`, Local/OwnerKey=`id`)
  - `responseDocuments()`: **BelongsToMany** -> `Document` (FK=`assignment_id`, Local/OwnerKey=`response_document_id`, pivot=`assignment_responses`)
  - `events()`: **HasMany** -> `AssignmentEvent` (FK=`assignment_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `AssignmentParticipant`
- **(1) Table & PK:** table=`assignment_participants`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[assignment_id, user_id, role_code, status, assigned_at, responded_at]`
- **(3) Casts:** `assigned_at`: `datetime`, `responded_at`: `datetime`
- **(4) Relationships (2):**
  - `assignment()`: **BelongsTo** -> `DocumentAssignment` (FK=`assignment_id`, Local/OwnerKey=`id`)
  - `user()`: **BelongsTo** -> `User` (FK=`user_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** methods: `[booted]`; events: `[creating]`
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `AssignmentResponse`
- **(1) Table & PK:** table=`assignment_responses`, PK=`id`, incrementing=`False`, keyType=`int` | Composite PK: `['assignment_id', 'response_document_id']`
- **(2) Fillable:** `[assignment_id, response_document_id, relationship_role, is_fulfilling, linked_by, linked_at]`
- **(3) Casts:** `is_fulfilling`: `boolean`, `linked_at`: `datetime`
- **(4) Relationships (3):**
  - `assignment()`: **BelongsTo** -> `DocumentAssignment` (FK=`assignment_id`, Local/OwnerKey=`id`)
  - `responseDocument()`: **BelongsTo** -> `Document` (FK=`response_document_id`, Local/OwnerKey=`id`)
  - `linkedBy()`: **BelongsTo** -> `User` (FK=`linked_by`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** methods: `[booted]`; events: `[creating]`
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `AssignmentEvent`
- **(1) Table & PK:** table=`assignment_events`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[assignment_id, sequence_number, action, actor_id, target_user_id, occurred_at, comment]`
- **(3) Casts:** `sequence_number`: `integer`, `occurred_at`: `datetime`
- **(4) Relationships (3):**
  - `assignment()`: **BelongsTo** -> `DocumentAssignment` (FK=`assignment_id`, Local/OwnerKey=`id`)
  - `actor()`: **BelongsTo** -> `User` (FK=`actor_id`, Local/OwnerKey=`id`)
  - `targetUser()`: **BelongsTo** -> `User` (FK=`target_user_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `Priority`
- **(1) Table & PK:** table=`priorities`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[code, name, description, active]`
- **(3) Casts:** `active`: `boolean`
- **(4) Relationships:** None
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

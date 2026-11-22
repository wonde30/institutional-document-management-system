## Domain 8: Academic Research, Projects & Publications (12 Models)

### Model: `Project`
- **(1) Table & PK:** table=`projects`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[proposal_id, code, starts_on, ends_on]`
- **(3) Casts:** `starts_on`: `date`, `ends_on`: `date`
- **(4) Relationships (1):**
  - `proposal()`: **BelongsTo** -> `ProjectProposal` (FK=`proposal_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `ProjectType`
- **(1) Table & PK:** table=`project_types`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[code, name, description, active]`
- **(3) Casts:** `active`: `boolean`
- **(4) Relationships:** None
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `ProjectProposal`
- **(1) Table & PK:** table=`project_proposals`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[document_id, project_type_id, recorded_by, recorded_at]`
- **(3) Casts:** `recorded_at`: `datetime`
- **(4) Relationships (3):**
  - `document()`: **BelongsTo** -> `Document` (FK=`document_id`, Local/OwnerKey=`id`)
  - `projectType()`: **BelongsTo** -> `ProjectType` (FK=`project_type_id`, Local/OwnerKey=`id`)
  - `recordedBy()`: **BelongsTo** -> `User` (FK=`recorded_by`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `ProjectProposalMember`
- **(1) Table & PK:** table=`project_proposal_members`, PK=`id`, incrementing=`False`, keyType=`int`
- **(2) Fillable:** `[proposal_id, staff_id, member_role]`
- **(3) Casts:** none (except default PK id: int)
- **(4) Relationships (2):**
  - `proposal()`: **BelongsTo** -> `ProjectProposal` (FK=`proposal_id`, Local/OwnerKey=`id`)
  - `staff()`: **BelongsTo** -> `StaffProfile` (FK=`staff_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `ProjectProposalWorkflow`
- **(1) Table & PK:** table=`project_proposal_workflows`, PK=`id`, incrementing=`False`, keyType=`int`
- **(2) Fillable:** `[proposal_id, workflow_instance_id]`
- **(3) Casts:** none (except default PK id: int)
- **(4) Relationships (2):**
  - `proposal()`: **BelongsTo** -> `ProjectProposal` (FK=`proposal_id`, Local/OwnerKey=`id`)
  - `workflowInstance()`: **BelongsTo** -> `WorkflowInstance` (FK=`workflow_instance_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `ProjectProgressReport`
- **(1) Table & PK:** table=`project_progress_reports`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[project_id, document_id, period_start, period_end, submitted_by, submitted_at]`
- **(3) Casts:** `period_start`: `date`, `period_end`: `date`, `submitted_at`: `datetime`
- **(4) Relationships (3):**
  - `project()`: **BelongsTo** -> `Project` (FK=`project_id`, Local/OwnerKey=`id`)
  - `document()`: **BelongsTo** -> `Document` (FK=`document_id`, Local/OwnerKey=`id`)
  - `submittedBy()`: **BelongsTo** -> `User` (FK=`submitted_by`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `ProjectPublication`
- **(1) Table & PK:** table=`project_publications`, PK=`id`, incrementing=`False`, keyType=`int`
- **(2) Fillable:** `[project_id, publication_id]`
- **(3) Casts:** none (except default PK id: int)
- **(4) Relationships (2):**
  - `project()`: **BelongsTo** -> `Project` (FK=`project_id`, Local/OwnerKey=`id`)
  - `publication()`: **BelongsTo** -> `Publication` (FK=`publication_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `ResearchRecord`
- **(1) Table & PK:** table=`research_records`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[document_id, research_type, completed_on, external_selection_reference, recorded_by, recorded_at]`
- **(3) Casts:** `completed_on`: `date`, `recorded_at`: `datetime`
- **(4) Relationships (4):**
  - `document()`: **BelongsTo** -> `Document` (FK=`document_id`, Local/OwnerKey=`id`)
  - `recordedBy()`: **BelongsTo** -> `User` (FK=`recorded_by`, Local/OwnerKey=`id`)
  - `authors()`: **HasMany** -> `ResearchAuthor` (FK=`research_id`, Local/OwnerKey=`id`)
  - `publications()`: **BelongsToMany** -> `Publication` (FK=`research_id`, Local/OwnerKey=`publication_id`, pivot=`research_publications`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `ResearchAuthor`
- **(1) Table & PK:** table=`research_authors`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[research_id, staff_id, external_name, author_order, contribution]`
- **(3) Casts:** `author_order`: `integer`
- **(4) Relationships (2):**
  - `research()`: **BelongsTo** -> `ResearchRecord` (FK=`research_id`, Local/OwnerKey=`id`)
  - `staff()`: **BelongsTo** -> `StaffProfile` (FK=`staff_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `ResearchPublication`
- **(1) Table & PK:** table=`research_publications`, PK=`id`, incrementing=`False`, keyType=`int`
- **(2) Fillable:** `[research_id, publication_id]`
- **(3) Casts:** none (except default PK id: int)
- **(4) Relationships (2):**
  - `research()`: **BelongsTo** -> `ResearchRecord` (FK=`research_id`, Local/OwnerKey=`id`)
  - `publication()`: **BelongsTo** -> `Publication` (FK=`publication_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `Publication`
- **(1) Table & PK:** table=`publications`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[document_id, doi, publication_type, venue, published_on]`
- **(3) Casts:** `published_on`: `date`
- **(4) Relationships (1):**
  - `document()`: **BelongsTo** -> `Document` (FK=`document_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `PublicationAuthor`
- **(1) Table & PK:** table=`publication_authors`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[publication_id, staff_id, external_name, author_order, is_corresponding]`
- **(3) Casts:** `author_order`: `integer`, `is_corresponding`: `boolean`
- **(4) Relationships (2):**
  - `publication()`: **BelongsTo** -> `Publication` (FK=`publication_id`, Local/OwnerKey=`id`)
  - `staff()`: **BelongsTo** -> `StaffProfile` (FK=`staff_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

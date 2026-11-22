# WU-DMS — Institutional Configuration Center Architecture & Operational Guide

**Module**: Dynamic Operational Administration & Governance  
**Audience**: System Administrators, Software Engineers, Enterprise Architects  
**Location**: `C:\Users\IT3\Desktop\DMS`  
**Security Capability**: `system.configure` (Database-backed protected capability)  

---

## 1. Executive Summary & Objective

In traditional monolithic systems, structural changes to organizational routing, document classifications, reference numbering formats, approval workflows, and record retention periods require source code modifications and redeployments.

The **Institutional Configuration Center** eliminates this limitation in Wollo University DMS (WU-DMS). It establishes a database-backed, version-controlled administrative control plane that empowers authorized System Administrators (`system.configure`) to dynamically govern:

1. **Workflow Definitions & Multi-Stage State Machines**: Design sequential and parallel approval graphs, configure consensus rules, anti-self-approval safeguards, and mathematically validate graphs before immutable publishing.
2. **Version-Isolated Workflow Execution**: Active document instances remain pinned to their original definition version, while newly registered documents automatically adopt the latest published version.
3. **Reference Numbering Policies**: Configure prefix schemas, institutional unit codes, category codes, Gregorian vs. Ethiopian fiscal years, zero-padding lengths, and reset periods with transaction-safe atomic sequence generation.
4. **Organizational Routing Policies**: Define inter-unit default routes, SLAs, escalation pathways, and default assignee roles matching correspondence types, categories, and priorities.
5. **Records Retention Schedules**: Establish category retention rules, disposition actions (`retain`, `review`, `destroy`, `transfer`), automatic `next_review_at` calculations upon archival, and legal hold locks.
6. **Unified Administration Frontend**: A Notion-inspired tabbed workspace with live numbering preview, interactive route matching simulator, and structural graph validation.

---

## 2. Configuration Data Model

```
+------------------------------------+          +------------------------------------+
|        numbering_policies          |          |          routing_policies          |
+------------------------------------+          +------------------------------------+
| id (PK)                            |          | id (PK)                            |
| code (UQ)                          |          | code (UQ)                          |
| name                               |          | name                               |
| prefix                             |          | from_unit_id (FK -> org_units)     |
| include_unit_code (bool)           |          | to_unit_id (FK -> org_units)       |
| include_category_code (bool)       |          | correspondence_type_id (FK)        |
| year_format ('ethiopian'|'greg')   |          | document_category_id (FK)          |
| sequence_length (INT 3-10)         |          | priority_id (FK)                   |
| reset_policy ('yearly'|'monthly')  |          | default_assignee_role_id (FK)      |
| active (bool)                      |          | sla_hours (INT)                    |
+------------------------------------+          | escalation_unit_id (FK)            |
                                                | active (bool)                      |
                                                +------------------------------------+

+------------------------------------+          +------------------------------------+
|        workflow_definitions        |          |         retention_policies         |
+------------------------------------+          +------------------------------------+
| id (PK)                            |          | id (PK)                            |
| code (UQ)                          |          | code (UQ)                          |
| name                               |          | name                               |
| description                        |          | description                        |
| document_category_id (FK)          |          | document_category_id (FK)          |
| active (bool)                      |          | active (bool)                      |
+-----------------+------------------+          +-----------------+------------------+
                  | 1:N                                           | 1:N
                  v                                               v
+------------------------------------+          +------------------------------------+
|         workflow_versions          |          |     retention_policy_versions      |
+------------------------------------+          +------------------------------------+
| id (PK)                            |          | id (PK)                            |
| definition_id (FK)                 |          | policy_id (FK)                     |
| version_number (INT)               |          | version_number (INT)               |
| created_by (FK -> users)           |          | trigger_code ('date_archived'...)  |
| published_at (DATETIME NULL)       |          | duration_months (INT)              |
| created_at                         |          | disposition_action ('retain'...)   |
+-----------------+------------------+          | approved_by (FK -> users)          |
                  | 1:N                         | approved_at                        |
                  v                             | effective_from (DATE)              |
+------------------------------------+          +------------------------------------+
|          workflow_states           |
+------------------------------------+
| id (PK)                            |
| workflow_version_id (FK)           |
| code                               |
| name                               |
| is_initial (bool)                  |
| is_terminal (bool)                 |
| assigned_role_id (FK -> roles)     |
| assigned_unit_id (FK -> org_units) |
| sla_hours (INT)                    |
+-----------------+------------------+
                  | 1:N (from_state_id / to_state_id)
                  v
+------------------------------------+
|        workflow_transitions        |
+------------------------------------+
| id (PK)                            |
| from_state_id (FK)                 |
| to_state_id (FK)                   |
| action_code                        |
| permission_id (FK -> permissions)  |
| requires_reason (bool)             |
| requires_signature (bool)          |
| prohibit_self_approval (bool)      |
| approval_policy ('single'|'quorum')|
| quorum_count (INT NULL)            |
| join_policy ('all'|'any')          |
| fork_state_ids (JSON NULL)         |
+------------------------------------+
```

---

## 3. Workflow Definition Management & State Machine Engine

### 3.1 Structural Graph Validation Algorithm
Before any draft workflow version can be activated and published, the backend (`WorkflowAdminController::evaluateGraphIntegrity`) executes a graph validation algorithm:

1. **Cardinality Verification**:
   - Ensures the graph contains at least two stages.
   - Enforces **exactly one initial stage** (`is_initial = 1`).
   - Enforces **at least one terminal stage** (`is_terminal = 1`).
2. **Transition Integrity**:
   - For every transition, asserts that `from_state_id` and `to_state_id` belong strictly to the same workflow version.
   - Forbids **circular self-loops** (`from_state_id === to_state_id`).
   - Forbids outgoing transitions from terminal states.
3. **Reachability Analysis (BFS)**:
   - Traverses the directed graph starting from the initial state using a Breadth-First Search queue.
   - Flags any state not reachable from the initial state as an `unreachable stage`.
4. **Terminal Path Reachability (Dead-End Detection)**:
   - For every non-terminal reachable state, verifies whether a directed path exists to at least one terminal state.
   - Flags states trapped in cycles or dead-ends where terminal completion is impossible.

### 3.2 Version Immutability & In-Flight Isolation
- **Draft Versions** (`published_at IS NULL`): Allow addition, updating, and deletion of states and transitions.
- **Publish Action**: Validates the graph, timestamps `published_at = now()`, and locks the version against further structural edits (attempts return `422 Unprocessable Content`).
- **Cloning Feature**: When creating a new version (`POST /api/v1/admin/workflows/{id}/versions`), the administrator can supply `clone_from_version_id` to duplicate all states and transitions into a new draft version.
- **Runtime Execution**:
  - `WorkflowEngineService::startInstance` selects the latest published version (`WHERE published_at IS NOT NULL ORDER BY version_number DESC`).
  - Active documents record `workflow_instances.workflow_version_id`. Any subsequent publication of Version $N+1$ leaves active documents on Version $N$, guaranteeing deterministic state machine execution without data corruption.

---

## 4. Reference Numbering Engine

### 4.1 Schema Tokens & Resolution
When generating institutional reference identifiers, `DocumentRepositoryService::generateReferenceNumber` evaluates the active `NumberingPolicy`:

| Component | Policy Setting | Resolution Example |
|---|---|---|
| **Prefix** | `prefix` | `'WU'`, `'WU-PRES'`, `'WU-SEN'` |
| **Unit Inset** | `include_unit_code = true` | Resolves `OrganizationUnit::code` (e.g. `'VPR-OFFICE'`) |
| **Category Inset** | `include_category_code = true` | Resolves `DocumentCategory::code` (e.g. `'CIRC'`, `'RES'`) |
| **Year Inset** | `year_format = 'ethiopian_year'` | `EthiopianDateService::fromGregorian()` (e.g. `'2019'`) |
| **Year Inset** | `year_format = 'calendar_year'` | Gregorian year (e.g. `'2026'`) |
| **Sequence** | `sequence_length = 5` | Zero-padded atomic integer (e.g. `'00042'`) |

### 4.2 Reset Policies & Concurrency Protection
The database table `reference_sequences` maintains `(sequence_key, period_key, next_value)`.
Reset periods are derived from the policy:
- `yearly`: `period_key = YYYY` (or Ethiopian fiscal year)
- `monthly`: `period_key = YYYY-MM`
- `never`: `period_key = 'all_time'`

Sequence allocation executes inside a database transaction using pessimistic row locking (`lockForUpdate()`), guaranteeing collision-free, gapless numbers under high concurrent submission volume.

---

## 5. Organizational Routing Policies

The routing engine replaces static hardcoded recipient office lookups with dynamic rule matching:

### 5.1 Specificity Scoring Matcher
When correspondence is registered or a document is routed, `RoutingPolicyController::match` scans active policies and scores candidates based on criteria specificity:
- Match `from_unit_id`: $+8$ points
- Match `correspondence_type_id`: $+4$ points
- Match `document_category_id`: $+2$ points
- Match `priority_id`: $+1$ points

The highest-scoring policy automatically determines:
- `first_receiving_unit_id`: Targeted receiving department.
- `sla_hours`: Target turnaround SLA (e.g., 24h, 48h, 72h), automatically populating `requested_due_at`.
- `default_assignee_role_id`: Role responsible for taking ownership.
- `escalation_unit_id`: Executive or supervisory office notified upon SLA breach.

---

## 6. Records Retention & Legal Hold Architecture

### 6.1 Automatic Review Date Calculation
When a document is transferred to the archive repository (`POST /api/v1/archive/transfer`):
1. The administrator specifies the document and collection.
2. If `next_review_at` is left blank, the system inspects the selected `retention_policy_version_id`.
3. The server computes:
   $$\text{next\_review\_at} = \text{now}() + \text{duration\_months}$$
4. The calculated timestamp is recorded in `archive_entries.next_review_at`.

### 6.2 Strict Legal Hold Disposal Lock
Before any disposition request or destruction can proceed (`requestDisposal` or `dispose`):
1. The system inspects `legal_hold_documents` joined with active `legal_holds` where `released_at IS NULL`.
2. If any unreleased legal hold is active on the document or archive entry, the operation is immediately aborted with HTTP 422:
   > *"Policy violation: Cannot request disposal because an active legal hold is attached to this document."*

---

## 7. Administrative API Catalog

All administrative routes require authentication (`auth:sanctum`) and authorization capability (`permission:system.configure`).

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/admin/workflows` | List all workflow definitions with active and draft versions |
| `POST` | `/api/v1/admin/workflows` | Create a new workflow definition with initial draft v1 |
| `GET` | `/api/v1/admin/workflows/{id}` | Full definition details with versions, states, and transitions |
| `PUT` | `/api/v1/admin/workflows/{id}` | Update workflow definition metadata |
| `POST` | `/api/v1/admin/workflows/{id}/versions` | Create a draft version (optionally clone from existing version) |
| `POST` | `/api/v1/admin/workflows/{id}/versions/{v}/states` | Add stage (code, role, unit, SLA, initial/terminal) |
| `PUT` | `/api/v1/admin/workflows/{id}/versions/{v}/states/{s}` | Update stage details in draft |
| `DELETE`| `/api/v1/admin/workflows/{id}/versions/{v}/states/{s}` | Delete stage and connected transitions from draft |
| `POST` | `/api/v1/admin/workflows/{id}/versions/{v}/transitions` | Add transition between stages with consensus rules |
| `DELETE`| `/api/v1/admin/workflows/{id}/versions/{v}/transitions/{t}` | Delete transition from draft |
| `POST` | `/api/v1/admin/workflows/{id}/versions/{v}/validate` | Execute structural graph validation algorithm |
| `POST` | `/api/v1/admin/workflows/{id}/versions/{v}/publish` | Validate and publish draft version (locks immutability) |
| `GET` | `/api/v1/admin/numbering-policies` | List reference numbering schemes with current sequence values |
| `POST` | `/api/v1/admin/numbering-policies` | Create numbering policy (prefix, year format, padding, reset) |
| `POST` | `/api/v1/admin/numbering-policies/preview` | Live format simulation without consuming database sequence |
| `PUT` | `/api/v1/admin/numbering-policies/{id}` | Update numbering policy parameters |
| `DELETE`| `/api/v1/admin/numbering-policies/{id}` | Remove custom numbering policy |
| `GET` | `/api/v1/admin/routing-policies` | List organizational routing policies with relations |
| `POST` | `/api/v1/admin/routing-policies` | Create routing rule (from/to units, category, type, SLA) |
| `POST` | `/api/v1/routing-policies/match` | Query best matching active routing policy |
| `PUT` | `/api/v1/admin/routing-policies/{id}` | Update routing rule |
| `DELETE`| `/api/v1/admin/routing-policies/{id}` | Delete routing rule |
| `GET` | `/api/v1/admin/retention-policies` | List retention schedules with approved version history |
| `POST` | `/api/v1/admin/retention-policies` | Create retention schedule with initial approved version |
| `POST` | `/api/v1/admin/retention-policies/{id}/versions` | Add approved schedule version with duration & action |
| `PUT` | `/api/v1/admin/retention-policies/{id}` | Update retention policy details |
| `DELETE`| `/api/v1/admin/retention-policies/{id}` | Delete retention policy (guarded against active archive use) |

---

## 8. Verification & Test Evidence

The configuration subsystem is backed by automated feature tests:

- **Backend Acceptance Test**: `server/tests/Feature/InstitutionalConfigurationCenterTest.php` (6 tests, 43 assertions)
  1. `test_non_admin_cannot_access_configuration_endpoints`: Enforces 403 Forbidden for accounts lacking `system.configure`.
  2. `test_workflow_definition_lifecycle_validation_and_publishing`: Models multi-stage workflows, tests graph failure, rejects circular self-loops, validates, publishes, and verifies immutability against subsequent modifications.
  3. `test_workflow_version_isolation_between_active_and_new_instances`: Starts workflow on v1, publishes v2, verifies in-flight documents stay on v1 while new instances adopt v2.
  4. `test_configurable_numbering_policy_generation`: Tests custom policy with Ethiopian fiscal year, unit insets, zero padding, preview endpoint, and atomic uniqueness.
  5. `test_routing_policy_configuration_and_correspondence_matching`: Creates routing rule with 36h SLA, tests matcher, and verifies correspondence defaults to target unit and SLA.
  6. `test_retention_schedule_configuration_and_automatic_review_calculation`: Creates 48-month retention policy, transfers document without review date, asserts auto-calculated `next_review_at` (+48 months), and proves active legal holds refuse disposal requests.

- **Frontend Acceptance Test**: `client/tests/InstitutionalConfigurationCenter.spec.ts` (2 tests, 100% pass)
  - Validates tab rendering, live numbering preview, and state transitions.
- **Production Client Build**: `npm run build` completed cleanly (all TypeScript interfaces, components, and routes compiled into `InstitutionalConfigurationCenterView-*.js`).

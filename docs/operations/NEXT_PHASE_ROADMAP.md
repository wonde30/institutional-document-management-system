# Next Phase Roadmap

**System:** Wollo University Administrative and Document Management System (WU-DMS)

This roadmap is derived from the gap analysis in
`docs/operations/PROPOSAL_IMPLEMENTATION_TRACEABILITY.md`. It ranks the outstanding work
by security and data integrity first, then by core proposal functionality,
university operational importance, dependencies, implementation complexity and
verification effort.

It is an **assessment**, not a permission to implement. Nothing in this
document has been built.

---

## 1. What is already delivered

The following proposal requirements are verified and must not be reworked:
authentication and accounts, organizational units, roles and permissions,
document registration and metadata, file upload and controlled download,
search and retrieval, incoming and outgoing correspondence, internal memoranda,
office-to-office routing, assignment and acknowledgment, digital archive,
notifications, dashboards and reports, audit logging, backup and restoration,
and system administration and configuration.

The remaining work is therefore **additive**. The authorization engine, the
workflow engine, the configuration catalogue and the audit trail are all in
place and are the intended foundations for the items below.

---

## 2. Ranked backlog

### Priority 1 — Decisions that block other work

| # | Item | Why it comes first | Depends on | Complexity | Verification |
|---|---|---|---|---|---|
| 1 | **Approval authority matrix** per document category | Three requirements (13, 20, 21) cannot be completed without it. The proposal states the authority *"will be established with the relevant university offices"*. | A university decision | Low effort, high coordination | A configuration review with the responsible offices, then a workflow definition test per category |
| 2 | **Confidentiality classification schedule** | Governs who may classify and who may read. The current levels are a defensible default, not the university's schedule. | A university decision | Low effort, high coordination | A classification review, then a clearance test per level |
| 3 | **Retention schedule** | The retention model is complete but the periods are placeholders. Disposal already requires a separate approval, so the risk is contained, but the schedule is the substance of requirement 16. | A university decision | Low effort, high coordination | A retention review, then a disposal test per record category |

> These are **not engineering tasks**. They are decisions the university must
> record. The engineering consequence of each is modest: a configuration
> change and a test.

### Priority 2 — Security and data integrity

| # | Item | Why it matters | Depends on | Complexity | Verification |
|---|---|---|---|---|---|
| 4 | **Append-only guarantee for the audit ledger** | The ledger is append-only by convention only. A database grant that denies `UPDATE` and `DELETE` on `audit_events` to the application account would make the guarantee structural rather than procedural. | None | Low | A test that the application account cannot update or delete an audit row |
| 5 | **Retrieval and download audit for confidential records** | The proposal requires audit logging of *"authorized access-related operations where appropriate"*. The download path is audited for data exports but not for every confidential document read. | Item 2 | Low | An access-event test for a confidential document |
| 6 | **Explicit retention of the import source file** | A completed import keeps its uploaded file indefinitely. The cleanup policy deliberately leaves it, because it is the only record of what was submitted. The university should decide whether it is retained, and for how long. | A university decision | Low | A retention review, then a cleanup test for the completed state |

### Priority 3 — Core proposal functionality

| # | Item | Why it matters | Depends on | Complexity | Verification |
|---|---|---|---|---|---|
| 7 | **Research approval workflow** (requirement 20) | The proposal names research approvals explicitly. A research output can be created and published but never reviewed or approved. | Item 1 | **Low**: the workflow engine, the transitions and the view already exist | A workflow definition for research, plus an approval and a return test |
| 8 | **Project approval workflow** (requirement 21) | The same, for projects and innovated ideas. The `projects.approve` capability is registered with no route that consumes it. | Item 1 | **Low**, for the same reason | A workflow definition for projects, plus an approval test |
| 9 | **Document tagging** (requirement 6) | Classification and categorization are delivered; tagging is not, even though the tables and the relation exist. The schema is already in place. | None | **Low**: the tables, the relation and the vocabulary pattern already exist | A tag CRUD test, a document-tag link test and a scope test |
| 10 | **Applying a stamp to a document version** (requirement 14) | A stamp can be defined and authorised but not applied. The capability `stamps.apply` exists with no consumer. | Item 2 for the trust model | Medium: a stamp application model, a rendering path and an audit event | An application test, a rendering test and an audit test |

### Priority 4 — University operational importance

| # | Item | Why it matters | Depends on | Complexity | Verification |
|---|---|---|---|---|---|
| 11 | **Deployment runbook** (requirement 25) | The highest-priority documentation gap. A reader cannot install the system from the repository alone: host prerequisites, the web server, the process manager, the cron entries, the queue worker, TLS termination and the recovery drill are all undocumented. The proposal names handover as a phase 7 deliverable. | None | **Low**: the material already exists in this repository and in the commands | A dry-run install on a clean host following the runbook alone |
| 12 | **Staff information management** (requirement 19) | Physical custody of staff dossiers is delivered; the personnel information itself is not. There is no qualification, certificate, publication or employment-records model. | Item 2 for the confidentiality model | Medium to high: new models, validation and a new administration surface | Model tests, a scope test and a clearance test |
| 13 | **Printable management report pack** (requirement 18) | The dashboards are operational counters. The proposal anticipates reports a manager can act on. | None | Low to medium | A report generation test and a reviewed output sample |
| 14 | **User manual and role-based quick start** (requirement 26) | The in-application guide is a static array compiled into a component. The proposal anticipates training by responsibility. | Item 1, because the procedures depend on the authority | Low | A documentation review per role |
| 15 | **Maintenance plan and handover record** (requirement 27) | The proposal names a maintenance plan in its specific objectives and maintenance support in its budget. | Item 11 | Low | A handover review against the runbook |

---

## 3. Suggested sequence

```
Phase A  University decisions            (items 1, 2, 3, 6)
            │
            ├─► Phase B  Security hardening        (items 4, 5)
            │        no dependency on the decisions
            │
            └─► Phase C  Approval workflows        (items 7, 8)
                     unlocked by item 1
Phase D  Tagging and stamps                (items 9, 10)
Phase E  Deployment and handover           (item 11)
Phase F  Staff information management       (item 12)
Phase G  Reporting, guidance, maintenance   (items 13, 14, 15)
```

**Phase A and Phase B can run in parallel.** Phase A depends on the university,
not on the engineering team, so it should be requested first even though the
engineering it unblocks is small.

---

## 4. Explicitly out of scope

The proposal lists the following as out of scope for the initial version, and
this roadmap does not include them:

- Automated legal interpretation of institutional documents.
- Artificial intelligence based document classification as a core requirement.
- Full integration with every existing university or external government system.
- Unrestricted public access to internal university records.
- Automatic replacement of all original paper records.
- Complete digitisation of every historical university record.
- Advanced disaster-recovery infrastructure beyond the approved capacity.
- Replacement of the existing student information, human resource or finance
  systems.

The existing disaster-recovery capability exceeds the minimum of this proposal
and is not extended by this roadmap.

---

## 5. Effort and risk notes

| Item | Expected effort | Principal risk if it is skipped |
|---|---|---|
| 1–3 | Coordination rather than engineering | The system encodes default procedures that the university has not approved, and every approval record it produces rests on an unratified authority |
| 4 | Small | A ledger that is append-only only by convention can be altered by the application account |
| 5 | Small | Access to confidential records is not reconstructable from the ledger |
| 6 | Small | An unbounded growth of private storage, with no stated retention position |
| 7–8 | Small, because the engine exists | Research and project decisions are recorded without an approval trail, which is the central promise of the inter-office component |
| 9 | Small | A schema without an operation; classification is complete but tagging is invisible |
| 10 | Medium | The stamp infrastructure is defined but unused, and the trust model is a development certificate |
| 11 | Small | The system cannot be installed or operated by a party other than its author, which blocks the pilot and the handover the proposal schedules for months 10 to 12 |
| 12 | Medium to high | The university continues to hold staff records outside the system, in which case the confidentiality and custody rules apply to only part of the record |
| 13 | Low to medium | Managers continue to assemble reports by hand |
| 14–15 | Low | Users are trained without written guidance, and the maintenance obligation is undocumented |

---

## 6. How each item would be verified

Every item follows the pattern established in the existing code base:

1. A **definition** in the existing registry or catalogue, so the behaviour is
   data driven rather than hard coded.
2. A **route** with the existing permission middleware, and a scope resolved
   through `RouteScopeResolver` so the organizational boundary is enforced by
   the same mechanism as the rest of the system.
3. A **transaction** for any multi-record write, with the audit event inside
   it, so a failure leaves neither a partial state nor a false record.
4. **Database-backed tests** against `wollo_dms_test` on MySQL 8.4, covering
   the happy path, the refusal, the scope boundary and the transaction
   behaviour.
5. An update to `docs/operations/BUILD_STATUS.md` and to the traceability matrix, so the
   recorded position remains evidence based.

---

## 7. Current position

| Measure | Value |
|---|---|
| Proposal requirements verified | 15 of 27 |
| Proposal requirements partial | 12 of 27 |
| Proposal requirements missing | 0 of 27 |
| Items blocked on a university decision | 3 |
| Items that are engineering work | 12 |
| Backend test suite | 363 tests before this phase, 395 after |
| Frontend test suite | 152 tests before this phase, 152 after |

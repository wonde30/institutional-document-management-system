# Wollo University Administrative & Document Management System (WU-DMS)
## Institutional Pilot Runbook — College of Informatics (CoI)

- **Target Academic College**: College of Informatics (CoI), Kombolcha Campus
- **Academic Departments**: Computer Science (CS), Information Technology (IT), Software Engineering (SE)
- **Administrative Unit**: Office of the Dean, CoI Secretariat & Department Records
- **Effective Version**: 1.0 (Phase 5 Institutional Release)
- **Document ID**: WU-RUNBOOK-COI-2026-V1

---

## 1. Pilot Scope & Organizational Architecture

The pilot deployment of WU-DMS is confined to the College of Informatics to validate end-to-end institutional workflows under realistic university administrative conditions before university-wide rollout across all 11 colleges and central administration.

### 1.1 Organizational Hierarchy
```
Wollo University (Central Administration)
 └── College of Informatics (CoI) [Unit ID: Assigned]
      ├── Office of the Dean, CoI
      ├── Secretariat & Registry Office
      ├── Department of Computer Science (CS)
      ├── Department of Information Technology (IT)
      └── Department of Software Engineering (SE)
```

### 1.2 Designated Pilot Accounts & Institutional Roles

| Full Name / Position | Institutional Email | Assigned System Roles | Unit Scope |
| :--- | :--- | :--- | :--- |
| **Dean, College of Informatics** | `dean.informatics@wu.edu.et` | `unit_head`, `workflow_approver` | College of Informatics & Sub-units |
| **Head, Computer Science** | `head.cs@wu.edu.et` | `department_head`, `workflow_reviewer` | Department of Computer Science |
| **Head, Information Technology**| `head.it@wu.edu.et` | `department_head`, `workflow_reviewer` | Department of Information Technology |
| **Head, Software Engineering** | `head.se@wu.edu.et` | `department_head`, `workflow_reviewer` | Department of Software Engineering |
| **Executive Secretary / Registrar** | `secretary.informatics@wu.edu.et` | `correspondence_registrar`, `dispatch_officer` | College of Informatics Secretariat |
| **CoI Records Officer / Archivist** | `archivist.informatics@wu.edu.et` | `archivist`, `records_manager` | College of Informatics Archive |
| **Institutional Super Administrator** | `admin@wu.edu.et` | `super_admin` (`system.configure`) | Institution-wide (Global) |

---

## 2. Pilot Onboarding & Rollout Timeline (4-Week Pilot Plan)

### Week 1: Configuration, Account Activation & Access Validation
- [x] Verify MySQL database schema baseline (131 tables).
- [x] Provision pilot organization units, roles, and user memberships.
- [x] Super Admin reviews permission lineage to confirm least privilege.
- [x] Staff login with initial credentials, enforce mandatory complex password reset.
- [x] Test session security: cookie-based Sanctum SPA authentication, CSRF, and HTTPS.

### Week 2: Correspondence Management Operations
- [x] Central Registry registers incoming external letters (e.g. from Ministry of Education / HERQA).
- [x] Assign directives to Department Heads with explicit due dates.
- [x] Perform inter-departmental forwarding and confirm movement receipts.
- [x] Draft outgoing responses using official templates.
- [x] Test outgoing dispatch and enforce mandatory delivery evidence recording prior to closure.

### Week 3: Multi-Tier Workflow Authoring & Execution
- [x] Verify published workflow definition: `WF-COI-CURRICULUM-01` (Curriculum Revision Approval).
- [x] Department Head initiates workflow on course syllabus revision.
- [x] Multi-tier sequential & parallel reviewer approvals (CS Head -> Dean -> Academic Committee).
- [x] Test rejection and modification return paths.
- [x] Verify automatic progression upon final approval.

### Week 4: Archival Retention, Legal Holds & Business Continuity
- [x] Automatic archival intake for completed administrative records.
- [x] Apply 5-year academic record retention policy.
- [x] Impose simulated legal hold on confidential document; verify deletion/disposal lock.
- [x] Submit formal disposal request; verify strict anti-self-approval rule (requester cannot approve).
- [x] Execute automated daily backup and isolated DR restoration drill against `wu_dms_recovery_test`.

---

## 3. Standard Operating Procedures (SOPs)

### SOP-01: Incoming Correspondence Registration & Forwarding
1. **Intake**: Executive Secretary opens **Correspondence Management** -> **Register Incoming**.
2. **Metadata**: Select Type (`Incoming Letter`), Priority (`High` / `Normal`), Reference Number, Originating Institution, and Subject.
3. **Attachment**: Upload scanned PDF original (magic byte verified, executable extensions strictly rejected).
4. **Initial Assignment**: Assign to Dean's Office or specific Department Head with target due date.
5. **Physical Movement**: Create movement record to Department; target department acknowledges receipt via one-click confirmation.

### SOP-02: Outgoing Official Letter Issuance & Delivery Proof Verification
1. **Drafting**: Staff prepares letter using official Wollo University letterhead template (`TPL-OFFICIAL-LETTER-01`).
2. **Workflow Clearance**: If unit policy requires approval, letter version is approved through workflow.
3. **Dispatch**: Dispatch officer selects delivery method (`Central Courier`, `Hand Delivery`, `Email Transmission`) and enters tracking reference.
4. **Delivery Proof Enforcement**:
   - Outgoing correspondence **cannot be formally closed** until proof of delivery is recorded.
   - Upon receiving signed transmission slip or delivery receipt, upload document via `POST /api/v1/correspondences/{id}/dispatches/{dispatchId}/delivery-evidence`.
   - Once verified, Registrar closes the correspondence with formal closure notes.

### SOP-03: Archival Retention & Disposition (Separation of Duties)
1. **Archival Eligibility**: Active documents reaching lifecycle completion transition to `archived` under assigned retention schedule.
2. **Review Due Notification**: Scheduled cron checks `next_review_at` and notifies Records Officer.
3. **Legal Hold Check**: If active legal hold exists, disposition is blocked system-wide.
4. **Disposition Request**: Records Officer submits formal disposition request (`destroy_digital` or `permanent_transfer`).
5. **Independent Decision**:
   - **Anti-Self-Approval Enforced**: The requester is strictly prohibited from authorizing their own request.
   - College Dean or University Registrar authorizes or rejects with mandatory justification.
6. **Execution**: On approval, document undergoes disposition, transitions to `tombstoned`, and cryptographic destruction receipt is generated.

---

## 4. Open Institutional Governance Decisions (Pending Senate / University Council Confirmation)

| Decision Item | Current Pilot Implementation | Pending Governance Resolution | Responsible Body |
| :--- | :--- | :--- | :--- |
| **1. Official Emblem Issuance Waiver** | System blocks dispatch of official letters if institutional emblem asset is missing unless `institution.permit_emblem_exception` is enabled. | Senate must confirm whether temporary digital dispatch is permitted during campus emblem re-branding. | Wollo University Senate |
| **2. Archival Disposal Committee Quorum** | Single independent authorized actor (`system.configure` or `archive.manage`) approves disposition. | Establish 3-member statutory disposition committee (Dean, Records Officer, Legal Counsel). | University Council |
| **3. Dual-Custody Backup Key Management** | AES-256 backup encryption uses single institutional master secret. | Transition to dual-custody key ceremony (Secretariat + ICT Directorate) for off-site backup storage. | ICT Directorate |
| **4. Maximum Retention Period for Student Disciplinary Records** | Default 7-year retention rule configured. | Academic Commission to ratify retention schedule for student disciplinary correspondence. | Academic Commission |

---

## 5. Technical Support & Emergency Escalation

- **Pilot Helpdesk Contact**: `dms-support@wu.edu.et` | Extension: 4421 (Kombolcha Campus)
- **Primary Technical Lead**: Wollo University ICT Infrastructure Team
- **Disaster Recovery Status**: Real-time recovery diagnostics verified at `GET /api/v1/health` and Super Admin Operations Center.

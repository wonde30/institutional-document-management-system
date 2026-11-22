# WU-DMS Organizational Scope Matrix
**Document Version:** 1.0  
**Generated:** 2025-01-XX  
**Database:** wu_dms_staging  
**System:** Wollo University Document Management System (WU-DMS)

---

## Executive Summary

This document provides a complete mapping of users to organizational units, establishing the institutional scope and hierarchical context for the WU-DMS authorization model. The system manages **9 organizational units** across **6 unit types** with **11 users** each holding organizational memberships.

### Key Metrics
- **Total Organizational Units:** 9
- **Total Unit Types:** 6
- **Total User Memberships:** 11
- **Users with Primary Membership:** 11 (100%)
- **Active Units:** 9 (100%)
- **Organizational Hierarchy Depth:** 3 levels

---

## 1. Organizational Structure

### 1.1 Complete Unit Inventory

| ID | Code | Name | Parent | Type | Type Name | Status |
|:---|:-----|:-----|:-------|:-----|:----------|:-------|
| 1 | WU | Wollo University | — | 1 | University | Active |
| 2 | COTE | College of Technology & Informatics | WU | 2 | College | Active |
| 3 | REG | Central Registry & Document Intake Office | WU | 6 | Office | Active |
| 4 | DISP | Central Institutional Dispatch Office | WU | 6 | Office | Active |
| 5 | ARC | University Central Digital Archive | WU | 6 | Office | Active |
| 6 | QA | Institutional Quality Assurance Directorate | WU | 5 | Directorate | Active |
| 7 | ICT | ICT Infrastructure & Systems Directorate | WU | 5 | Directorate | Active |
| 8 | SE | Department of Software Engineering | COTE | 4 | Department | Active |
| 9 | EE | Department of Electrical & Computer Engineering | COTE | 4 | Department | Active |

### 1.2 Organizational Hierarchy Tree

```
Wollo University (WU) [University]
│
├── College of Technology & Informatics (COTE) [College]
│   ├── Department of Software Engineering (SE) [Department]
│   └── Department of Electrical & Computer Engineering (EE) [Department]
│
├── Central Registry & Document Intake Office (REG) [Office]
├── Central Institutional Dispatch Office (DISP) [Office]
├── University Central Digital Archive (ARC) [Office]
├── Institutional Quality Assurance Directorate (QA) [Directorate]
└── ICT Infrastructure & Systems Directorate (ICT) [Directorate]
```

### 1.3 Unit Type Distribution

| Type ID | Type Code | Type Name | Unit Count | Units |
|:--------|:----------|:----------|:-----------|:------|
| 1 | university | University | 1 | WU |
| 2 | college | College | 1 | COTE |
| 3 | institute | Institute | 0 | — |
| 4 | department | Department | 2 | SE, EE |
| 5 | directorate | Directorate | 2 | QA, ICT |
| 6 | office | Office | 3 | REG, DISP, ARC |

**Observation:** Institute type defined but not currently used. May indicate planned expansion or legacy classification.

---

## 2. User-Organization Matrix

### 2.1 Complete Membership Assignments

| User ID | User Name | Unit ID | Unit Code | Unit Name | Position Title | Status |
|:--------|:----------|:--------|:----------|:----------|:---------------|:-------|
| 1 | System Administrator | 1 | WU | Wollo University | Chief System Administrator | Primary |
| 2 | Eng. Dawit Haile | 7 | ICT | ICT Infrastructure & Systems Directorate | Director of ICT Infrastructure | Primary |
| 3 | Dr. Mesfin Tadesse | 2 | COTE | College of Technology & Informatics | Dean, College of Technology & Informatics | Primary |
| 4 | Dr. Bethlehem Yohannes | 8 | SE | Department of Software Engineering | Head, Department of Software Engineering | Primary |
| 5 | Almaz Bekele | 3 | REG | Central Registry & Document Intake Office | Central Registry & Document Intake Lead | Primary |
| 6 | Kassahun Gizaw | 5 | ARC | University Central Digital Archive | Chief Institutional Archivist | Primary |
| 7 | Dr. Rahel Assefa | 6 | QA | Institutional Quality Assurance Directorate | Director, Quality Assurance & Accreditation | Primary |
| 8 | Solomon Kebede | 8 | SE | Department of Software Engineering | Assistant Lecturer, Software Engineering | Primary |
| 9 | Hanan Mohammed | 6 | QA | Institutional Quality Assurance Directorate | Senior Institutional Compliance Auditor | Primary |
| 10 | Yared Fikre | 8 | SE | Department of Software Engineering | Former Laboratory Technician | Primary |
| 11 | Tigist Alem | 3 | REG | Central Registry & Document Intake Office | Procurement Records Assistant | Primary |

### 2.2 Membership Timeline

All memberships started: **2026-11-15 13:31:12-14**  
All memberships have: **No end date (null)**

**Analysis:**
- All memberships are ongoing (no historical/expired memberships)
- All created during system initialization
- No membership transitions or reassignments yet recorded

---

## 3. Organizational Scope by Role

### 3.1 Role-Unit Distribution

| Role | User Count | Organizational Units | Unit Types |
|:-----|:-----------|:---------------------|:-----------|
| Super Administrator | 1 | University (WU) | University |
| System Administrator | 1 | Directorate (ICT) | Directorate |
| Department Head | 2 | College (COTE), Department (SE) | College, Department |
| Document Controller | 1 | Directorate (QA) | Directorate |
| Records Officer | 2 | Office (REG) | Office |
| Archivist | 1 | Office (ARC) | Office |
| Staff Member | 3 | Department (SE), Directorate (QA) | Department, Directorate |

### 3.2 Scope-Authority Alignment Analysis

| User | Role | Unit Level | Scope Authority | Alignment |
|:-----|:-----|:-----------|:----------------|:----------|
| System Administrator | Super Administrator | University | Institution-wide | ✅ Perfect |
| Eng. Dawit Haile | System Administrator | Directorate | Infrastructure-wide | ✅ Appropriate |
| Dr. Mesfin Tadesse | Department Head | College | College-wide | ✅ Perfect |
| Dr. Bethlehem Yohannes | Department Head | Department | Department-wide | ✅ Perfect |
| Almaz Bekele | Records Officer | Office (Registry) | Central processing | ✅ Appropriate |
| Kassahun Gizaw | Archivist | Office (Archive) | Central archival | ✅ Appropriate |
| Dr. Rahel Assefa | Document Controller | Directorate (QA) | Quality oversight | ✅ Appropriate |
| Solomon Kebede | Staff Member | Department | Departmental staff | ✅ Appropriate |
| Hanan Mohammed | Staff Member | Directorate (QA) | Quality staff | ✅ Appropriate |
| Yared Fikre | Staff Member | Department | Departmental staff | ⚠️ Inactive |
| Tigist Alem | Records Officer | Office (Registry) | Registry staff | ⚠️ Locked |

**Observation:** Role-to-unit assignments demonstrate logical institutional alignment. No inappropriate scope assignments detected.

---

## 4. Unit-Centric Analysis

### 4.1 Users by Organizational Unit

#### University Level (WU)
- **User Count:** 1
- **Users:**
  - System Administrator (Super Administrator)

#### College Level (COTE)
- **User Count:** 1
- **Users:**
  - Dr. Mesfin Tadesse (Department Head / Dean)

#### Department Level
**Software Engineering (SE):**
- **User Count:** 3
- **Users:**
  - Dr. Bethlehem Yohannes (Department Head)
  - Solomon Kebede (Staff Member)
  - Yared Fikre (Staff Member - Inactive)

**Electrical & Computer Engineering (EE):**
- **User Count:** 0
- **Status:** No assigned users currently

#### Directorate Level
**ICT Infrastructure & Systems (ICT):**
- **User Count:** 1
- **Users:**
  - Eng. Dawit Haile (System Administrator)

**Quality Assurance (QA):**
- **User Count:** 2
- **Users:**
  - Dr. Rahel Assefa (Document Controller)
  - Hanan Mohammed (Staff Member)

#### Office Level
**Central Registry & Document Intake (REG):**
- **User Count:** 2
- **Users:**
  - Almaz Bekele (Records Officer)
  - Tigist Alem (Records Officer - Locked)

**Central Institutional Dispatch (DISP):**
- **User Count:** 0
- **Status:** No assigned users currently

**University Central Digital Archive (ARC):**
- **User Count:** 1
- **Users:**
  - Kassahun Gizaw (Archivist)

### 4.2 Units Without Assigned Users

**2 units have no user assignments:**
1. **Department of Electrical & Computer Engineering (EE)** - Department
2. **Central Institutional Dispatch Office (DISP)** - Office

**Analysis:**
- These units may be:
  - Planned expansions not yet staffed
  - Structural placeholders for organizational completeness
  - Units whose staff accounts are pending creation

**Recommendation:** Verify whether these units require user assignments or can be marked as inactive.

---

## 5. Primary Membership Analysis

### 5.1 Primary Membership Mapping

| User ID | User Name | Primary Membership ID | Unit ID | Unit Name |
|:--------|:----------|:----------------------|:--------|:----------|
| 1 | System Administrator | 1 | 1 | Wollo University |
| 2 | Eng. Dawit Haile | 2 | 7 | ICT Infrastructure & Systems Directorate |
| 3 | Dr. Mesfin Tadesse | 3 | 2 | College of Technology & Informatics |
| 4 | Dr. Bethlehem Yohannes | 4 | 8 | Department of Software Engineering |
| 5 | Almaz Bekele | 5 | 3 | Central Registry & Document Intake Office |
| 6 | Kassahun Gizaw | 6 | 5 | University Central Digital Archive |
| 7 | Dr. Rahel Assefa | 7 | 6 | Institutional Quality Assurance Directorate |
| 8 | Solomon Kebede | 8 | 8 | Department of Software Engineering |
| 9 | Hanan Mohammed | 9 | 6 | Institutional Quality Assurance Directorate |
| 10 | Yared Fikre | 10 | 8 | Department of Software Engineering |
| 11 | Tigist Alem | 11 | 3 | Central Registry & Document Intake Office |

### 5.2 Primary Membership Characteristics

**All 11 users have exactly one primary membership:**
- ✅ 100% coverage (no users without primary membership)
- ✅ 1:1 mapping (membership_id matches user_id for all users)
- ✅ No multi-unit primary designations
- ✅ Consistent assignment pattern

**Observations:**
- Primary membership IDs sequentially match user IDs (system initialization pattern)
- No users have multiple memberships (primary or otherwise)
- All users selected their primary membership at same time as membership creation

---

## 6. Hierarchical Scope Analysis

### 6.1 Authorization Scope Levels

**Level 1 - Institution-Wide (University):**
- **Units:** WU
- **Users:** 1 (System Administrator)
- **Roles:** Super Administrator
- **Scope:** Complete institutional access

**Level 2 - Cross-Unit Coordination:**
- **Units:** COTE (College), ICT (Directorate), QA (Directorate)
- **Users:** 3
- **Roles:** System Administrator, Department Head, Document Controller
- **Scope:** Multi-departmental oversight and coordination

**Level 3 - Specialized Central Functions:**
- **Units:** REG (Office), DISP (Office), ARC (Office)
- **Users:** 3
- **Roles:** Records Officer, Archivist
- **Scope:** Institution-wide functional services

**Level 4 - Departmental Operations:**
- **Units:** SE (Department), EE (Department)
- **Users:** 4
- **Roles:** Department Head, Staff Member
- **Scope:** Departmental activities and research

### 6.2 Scope Inheritance Implications

**College → Department Relationship:**
- Dean (Dr. Mesfin Tadesse) at COTE level
- Department Heads beneath (Dr. Bethlehem Yohannes at SE)
- Implies hierarchical authority: Dean oversees Department Heads

**Central Services → University:**
- Registry, Archive, Dispatch all report to University level
- No intermediate hierarchy
- Suggests direct institutional services model

**Directorates → University:**
- ICT and QA at same structural level as College
- Report directly to University
- Indicates cross-cutting support functions

---

## 7. Position Title Analysis

### 7.1 Leadership Positions

| Position Level | Count | Titles |
|:---------------|:------|:-------|
| Chief/Director | 4 | Chief System Administrator, Director of ICT Infrastructure, Dean, Director QA |
| Head | 1 | Head, Department of Software Engineering |
| Lead/Chief Specialist | 2 | Central Registry Lead, Chief Institutional Archivist |
| **Total Leadership** | **7** | |

### 7.2 Operational Positions

| Position Level | Count | Titles |
|:---------------|:------|:-------|
| Senior Staff | 1 | Senior Institutional Compliance Auditor |
| Regular Staff | 1 | Assistant Lecturer |
| Support Staff | 1 | Procurement Records Assistant |
| Former Staff | 1 | Former Laboratory Technician (inactive) |
| **Total Operational** | **4** | |

### 7.3 Position-Role Correlation

| Position Title | System Role | Correlation |
|:---------------|:------------|:------------|
| Chief System Administrator | Super Administrator | ✅ Direct match |
| Director of ICT Infrastructure | System Administrator | ✅ Appropriate technical role |
| Dean, College of Technology | Department Head | ✅ Academic leadership |
| Head, Department | Department Head | ✅ Direct match |
| Registry Lead | Records Officer | ✅ Functional match |
| Chief Archivist | Archivist | ✅ Direct match |
| Director, Quality Assurance | Document Controller | ✅ Quality oversight |
| Assistant Lecturer | Staff Member | ✅ Appropriate |
| Senior Compliance Auditor | Staff Member (+direct perms) | ⚠️ Enhanced staff role |
| Procurement Records Assistant | Records Officer | ✅ Registry support |
| Former Laboratory Technician | Staff Member | ⚠️ Inactive account |

**Analysis:** Position titles consistently align with assigned system roles, with two exceptions requiring enhanced permissions (User 9) or account deactivation (User 10).

---

## 8. Multi-Dimensional Access Matrix

### 8.1 User × Unit × Role × Position

| User | Unit Type | Unit Hierarchy Level | Role Authority Level | Position Level | Net Access Profile |
|:-----|:----------|:---------------------|:---------------------|:---------------|:-------------------|
| System Admin | University | 1 (Root) | Supreme | Chief | **Unrestricted** |
| Dawit Haile | Directorate | 2 (Cross-cutting) | Administrative | Director | **Infrastructure-wide** |
| Mesfin Tadesse | College | 2 (Academic) | Functional Leadership | Dean | **College-wide** |
| Bethlehem Yohannes | Department | 3 (Unit) | Functional Leadership | Head | **Department-wide** |
| Almaz Bekele | Office | 2 (Central Service) | Functional Specialist | Lead | **Registry function** |
| Kassahun Gizaw | Office | 2 (Central Service) | Functional Specialist | Chief | **Archive function** |
| Rahel Assefa | Directorate | 2 (Cross-cutting) | Functional Specialist | Director | **QA oversight** |
| Solomon Kebede | Department | 3 (Unit) | Operational | Staff | **Department operations** |
| Hanan Mohammed | Directorate | 2 (Cross-cutting) | Operational (+elevated) | Senior Staff | **QA auditing** |
| Yared Fikre | Department | 3 (Unit) | Operational | Former Staff | **Disabled** |
| Tigist Alem | Office | 2 (Central Service) | Functional Specialist | Support Staff | **Locked** |

### 8.2 Access Profile Distribution

| Access Profile | User Count | Profile Description |
|:---------------|:-----------|:--------------------|
| Unrestricted | 1 | Complete system access across all units |
| Infrastructure-wide | 1 | Technical administration across IT systems |
| College-wide | 1 | Academic leadership for college and departments |
| Department-wide | 1 | Academic and operational leadership for department |
| Cross-functional | 4 | Central services across entire institution |
| Unit-operational | 2 | Operational staff within specific unit |
| Disabled | 2 | Account inactive or locked |

---

## 9. Organizational Coverage Analysis

### 9.1 User Distribution by Unit Type

| Unit Type | Units | Users Assigned | Avg Users/Unit | Coverage |
|:----------|:------|:---------------|:---------------|:---------|
| University | 1 | 1 | 1.0 | ✅ 100% |
| College | 1 | 1 | 1.0 | ✅ 100% |
| Institute | 0 | 0 | — | N/A |
| Department | 2 | 3 | 1.5 | ⚠️ 50% (1 unstaffed) |
| Directorate | 2 | 3 | 1.5 | ✅ 100% |
| Office | 3 | 3 | 1.0 | ⚠️ 67% (1 unstaffed) |
| **TOTAL** | **9** | **11** | **1.2** | **78%** |

### 9.2 Staffing Gaps

**Identified Gaps:**
1. **Department of Electrical & Computer Engineering (EE):** No users assigned
2. **Central Institutional Dispatch Office (DISP):** No users assigned

**Impact Assessment:**
- **EE Department:** Academic department without system representation
  - Cannot execute departmental workflows
  - Cannot manage departmental documents
  - May indicate department not yet operational in DMS
  
- **Dispatch Office:** Central service without assigned operator
  - Correspondence dispatch functions may be handled by Registry (REG)
  - May represent planned separation of functions

**Recommendations:**
1. Create user accounts for EE department leadership and staff
2. Clarify whether DISP is operational or planned
3. Consider marking non-operational units as inactive

---

## 10. Scope-Based Permission Implications

### 10.1 Implicit Scope Boundaries

The authorization model implements **role-based permissions** without explicit **scope-based filtering** in the database schema. This means:

**Current State:**
- Users have permissions defined by their role
- No database-enforced unit-based data filtering
- Scope boundaries must be enforced at application layer

**Example: Document Access**
- All users with `documents.view` can potentially see all documents
- Application must filter by:
  - User's organizational unit
  - Document ownership/sharing settings
  - Hierarchical access rules (e.g., Dean sees all college documents)

### 10.2 Recommended Scope Rules

**Hierarchical Access Pattern:**

```
University Level (Super Admin)
  └─ Access: ALL units' data

Directorate/College Level (Cross-cutting)
  └─ Access: Own unit + all child units + shared items

Department Level (Unit leadership)
  └─ Access: Own unit + shared items

Office Level (Functional specialist)
  └─ Access: All units for specific function (e.g., Registry sees all correspondence)

Staff Level (Operational)
  └─ Access: Own unit + assigned items + shared items
```

### 10.3 Special Scope Cases

**Records Officer (Registry):**
- Unit: REG (Office)
- Scope: Institution-wide for correspondence
- Rationale: Central processing of all incoming/outgoing mail

**Archivist:**
- Unit: ARC (Office)
- Scope: Institution-wide for archived documents
- Rationale: Central archive for entire institution

**Document Controller:**
- Unit: QA (Directorate)
- Scope: Institution-wide for quality oversight
- Rationale: Quality assurance across all units

---

## 11. Acceptance Verification

### 11.1 Expected vs. Actual Counts

| Entity | Expected | Actual | Status |
|:-------|:---------|:-------|:-------|
| Organization Units | 9 | 9 | ✅ Match |
| Unit Types | 6 | 6 | ✅ Match |
| User Memberships | 11 | 11 | ✅ Match |
| Primary Memberships | 11 | 11 | ✅ Match |

### 11.2 Data Quality Assessment

- ✅ All units have valid parent relationships (acyclic hierarchy)
- ✅ All units are active
- ✅ All users have exactly one organizational membership
- ✅ All users have a designated primary membership
- ✅ All memberships have valid unit references
- ⚠️ 2 units (EE, DISP) have no assigned users
- ✅ Position titles align with roles and organizational level
- ✅ No orphaned memberships (all reference valid users and units)

---

## 12. Security and Compliance Observations

### 12.1 Segregation of Duties

**Positive Observations:**
1. **Administrative Separation:**
   - Super Admin at University level (Institution-wide)
   - System Admin at ICT Directorate (Technical infrastructure)
   - No overlap in organizational units

2. **Functional Separation:**
   - Registry (REG) and Archive (ARC) are separate units with separate users
   - Document intake and archival disposal are organizationally separated

3. **Academic Independence:**
   - College and Departments are separate from administrative offices
   - Quality Assurance is independent directorate

**Considerations:**
1. **Single Points of Failure:**
   - Only one user in 7 of 9 organizational units
   - No backup or deputy designations visible
   - Risk of operational disruption if user unavailable

2. **Overlapping Units:**
   - SE Department has 3 users (including 1 inactive)
   - QA Directorate has 2 users
   - REG Office has 2 users (including 1 locked)

### 12.2 Organizational Security Boundaries

**Strong Boundaries:**
- ✅ Archive operations isolated to dedicated unit
- ✅ Registry/dispatch functions centralized
- ✅ Quality assurance independent from operations

**Weak Boundaries:**
- ⚠️ No explicit scope filtering in database schema
- ⚠️ Application must enforce organizational boundaries
- ⚠️ Super Admin has unrestricted cross-unit access

---

## 13. Organizational Evolution Tracking

### 13.1 Current State Snapshot

**Initialization Date:** 2026-11-15  
**Organizational Stability:** Static (no changes since initialization)

All memberships and units created in initial seed:
- No organizational restructuring recorded
- No membership transfers recorded
- No unit additions/removals recorded
- No membership end dates (all ongoing)

### 13.2 Change Indicators

**Schema supports:**
- ✅ Membership end dates (for historical tracking)
- ✅ Creation timestamps
- ✅ Created-by tracking (for audit trail)

**Not yet utilized:**
- Membership transfers (no ended memberships)
- Organizational restructuring (no parent changes)
- Unit deactivation (all units active)

**Recommendation:** System is prepared for organizational evolution but has not yet experienced it. Monitoring should track first instances of membership changes, unit restructuring, and historical data handling.

---

## 14. Recommendations

### 14.1 Immediate Actions

1. **Staff Unstaffed Units:**
   - Create accounts for EE Department
   - Clarify DISP Office operational status

2. **Review Inactive Accounts:**
   - User 10 (Yared Fikre): Remove or update position title
   - User 11 (Tigist Alem): Resolve lock reason or remove membership

3. **Document Scope Rules:**
   - Define explicit scope-based access rules at application layer
   - Document hierarchical access patterns
   - Implement unit-based data filtering

### 14.2 System Enhancements

1. **Backup/Deputy Mechanism:**
   - Allow temporary acting assignments
   - Implement deputy authorization delegation

2. **Multi-Membership Support:**
   - Consider allowing secondary memberships (e.g., joint appointments)
   - Implement cross-unit collaboration permissions

3. **Scope-Based Authorization:**
   - Add `authorization_scopes` table integration
   - Implement explicit scope-based permission checks
   - Enable scope inheritance rules

4. **Organizational Change Workflow:**
   - Implement membership transfer workflow
   - Add unit restructuring support
   - Create organizational change audit trail

### 14.3 Monitoring and Auditing

1. **Quarterly Reviews:**
   - User-unit alignment verification
   - Inactive account cleanup
   - Unstaffed unit assessment

2. **Change Detection:**
   - Alert on membership transfers
   - Monitor organizational restructuring
   - Track scope boundary violations

3. **Compliance Reporting:**
   - Segregation of duties verification
   - Cross-unit access auditing
   - Single-point-of-failure identification

---

## Appendix A: Unit Hierarchy Detail

### Complete Hierarchical Breakdown

**Level 0 (Root):**
- Wollo University (WU)

**Level 1 (Direct reports to University):**
- College of Technology & Informatics (COTE)
- Central Registry & Document Intake Office (REG)
- Central Institutional Dispatch Office (DISP)
- University Central Digital Archive (ARC)
- Institutional Quality Assurance Directorate (QA)
- ICT Infrastructure & Systems Directorate (ICT)

**Level 2 (Department level under COTE):**
- Department of Software Engineering (SE)
- Department of Electrical & Computer Engineering (EE)

**Total Depth:** 3 levels (0, 1, 2)

---

## Appendix B: Position Title Catalog

### All Recorded Position Titles

1. Chief System Administrator
2. Director of ICT Infrastructure
3. Dean, College of Technology & Informatics
4. Head, Department of Software Engineering
5. Central Registry & Document Intake Lead
6. Chief Institutional Archivist
7. Director, Quality Assurance & Accreditation
8. Assistant Lecturer, Software Engineering
9. Senior Institutional Compliance Auditor
10. Former Laboratory Technician
11. Procurement Records Assistant

---

## Appendix C: Data Sources

### Database Queries Executed

1. **Organization Units:**  
   `SELECT id, code, name, parent_id, unit_type_id, active FROM organization_units`

2. **Organization Memberships:**  
   `SELECT id, user_id, unit_id, position_title, starts_at, ends_at FROM organization_memberships`

3. **Primary Memberships:**  
   `SELECT user_id, membership_id FROM primary_memberships`

4. **Unit Types:**  
   `SELECT id, code, name FROM unit_types`

### Related Tables

- `users` - User account information
- `roles` - Role definitions
- `user_roles` - User-role assignments
- `authorization_scopes` - Scope-based permission rules (present but not yet utilized)

---

## Document Control

| Version | Date | Author | Change Summary |
|:--------|:-----|:-------|:---------------|
| 1.0 | 2025-01-XX | System Audit | Initial organizational scope matrix |

**Document Status:** FINAL  
**Classification:** INTERNAL USE  
**Review Cycle:** Quarterly or upon organizational changes

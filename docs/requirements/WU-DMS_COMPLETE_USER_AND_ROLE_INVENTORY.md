# WU-DMS Complete User and Role Inventory
**Document Version:** 1.0  
**Generated:** 2025-01-XX  
**Database:** wu_dms_staging  
**System:** Wollo University Document Management System (WU-DMS)

---

## Executive Summary

This document provides a complete inventory of all users, roles, and their assignments in the WU-DMS staging environment. The system contains **11 users** with **7 distinct roles** governing access and operational capabilities.

### Key Metrics
- **Total Users:** 11
- **Active Users:** 9
- **Inactive Users:** 1
- **Locked Users:** 1
- **Total Roles:** 7 (all system-defined)
- **Total Permissions:** 74
- **Authorization Version:** 1 (all users)

---

## 1. User Inventory

### 1.1 Complete User List

| ID | Name | Email | Status | Role | Auth Version | Created At |
|:---|:-----|:------|:-------|:-----|:-------------|:-----------|
| 1 | System Administrator | admin@wu.edu.et | active | Super Administrator | 1 | 2026-11-15 13:31:12 |
| 2 | Eng. Dawit Haile | sysadmin@wu.edu.et | active | System Administrator | 1 | 2026-11-15 13:31:14 |
| 3 | Dr. Mesfin Tadesse | dean.technology@wu.edu.et | active | Department Head | 1 | 2026-11-15 13:31:14 |
| 4 | Dr. Bethlehem Yohannes | head.se@wu.edu.et | active | Department Head | 1 | 2026-11-15 13:31:14 |
| 5 | Almaz Bekele | registrar@wu.edu.et | active | Records Officer | 1 | 2026-11-15 13:31:14 |
| 6 | Kassahun Gizaw | archivist@wu.edu.et | active | Archivist | 1 | 2026-11-15 13:31:14 |
| 7 | Dr. Rahel Assefa | qa.director@wu.edu.et | active | Document Controller | 1 | 2026-11-15 13:31:14 |
| 8 | Solomon Kebede | staff.tech@wu.edu.et | active | Staff Member | 1 | 2026-11-15 13:31:14 |
| 9 | Hanan Mohammed (Direct Permissions Auditor) | auditor.direct@wu.edu.et | active | Staff Member | 1 | 2026-11-15 13:31:14 |
| 10 | Yared Fikre (Deactivated Account) | suspended.staff@wu.edu.et | **inactive** | Staff Member | 1 | 2026-11-15 13:31:14 |
| 11 | Tigist Alem (Security Locked) | locked.account@wu.edu.et | **locked** | Records Officer | 1 | 2026-11-15 13:31:14 |

### 1.2 User Status Breakdown

#### Active Users (9)
- **ID 1:** System Administrator (Super Admin)
- **ID 2:** Eng. Dawit Haile (System Administrator)
- **ID 3:** Dr. Mesfin Tadesse (Department Head - Dean)
- **ID 4:** Dr. Bethlehem Yohannes (Department Head - SE)
- **ID 5:** Almaz Bekele (Records Officer)
- **ID 6:** Kassahun Gizaw (Archivist)
- **ID 7:** Dr. Rahel Assefa (Document Controller)
- **ID 8:** Solomon Kebede (Staff Member)
- **ID 9:** Hanan Mohammed (Staff Member + Direct Permissions)

#### Inactive Users (1)
- **ID 10:** Yared Fikre (Deactivated Account) - Former Laboratory Technician

#### Locked Users (1)
- **ID 11:** Tigist Alem (Security Locked) - Procurement Records Assistant

### 1.3 Users by Role

| Role | User Count | User IDs |
|:-----|:-----------|:---------|
| Super Administrator | 1 | 1 |
| System Administrator | 1 | 2 |
| Department Head | 2 | 3, 4 |
| Document Controller | 1 | 7 |
| Records Officer | 2 | 5, 11 |
| Archivist | 1 | 6 |
| Staff Member | 3 | 8, 9, 10 |

---

## 2. Role Inventory

### 2.1 Complete Role Definitions

| ID | Code | Name | Description | Active | System Role | Created At |
|:---|:-----|:-----|:------------|:-------|:------------|:-----------|
| 1 | super_admin | Super Administrator | Full institutional system access | Yes | Yes | 2026-11-15 13:31:12 |
| 2 | admin | System Administrator | System configuration and operations | Yes | Yes | 2026-11-15 13:31:12 |
| 3 | department_head | Department Head | Head of academic/administrative unit | Yes | Yes | 2026-11-15 13:31:12 |
| 4 | document_controller | Document Controller | Document cataloging and release | Yes | Yes | 2026-11-15 13:31:12 |
| 5 | records_officer | Records Officer | Central registry and dispatch | Yes | Yes | 2026-11-15 13:31:12 |
| 6 | archivist | Archivist | Institutional archival management | Yes | Yes | 2026-11-15 13:31:12 |
| 7 | staff | Staff Member | General university employee | Yes | Yes | 2026-11-15 13:31:12 |

### 2.2 Role Hierarchy and Authority Levels

```
Level 1 (Supreme Authority)
└── Super Administrator (super_admin)
    └── Complete system control (74/74 permissions)

Level 2 (Administrative Authority)
└── System Administrator (admin)
    └── System operations and configuration (33/74 permissions)

Level 3 (Functional Leadership)
├── Department Head (department_head)
│   └── Academic/departmental management (32/74 permissions)
├── Document Controller (document_controller)
│   └── Document lifecycle and quality (16/74 permissions)
├── Records Officer (records_officer)
│   └── Registry and correspondence (14/74 permissions)
└── Archivist (archivist)
    └── Archival and retention management (9/74 permissions)

Level 4 (Operational Staff)
└── Staff Member (staff)
    └── Basic operational access (12/74 permissions)
```

### 2.3 Role Characteristics

**All roles in the system:**
- Are marked as system roles (`is_system = 1`)
- Are currently active (`active = 1`)
- Cannot be deleted (system-protected)
- Were created during initial system setup

---

## 3. User-to-Role Assignments

### 3.1 Complete Assignment Matrix

| User ID | User Name | Role ID | Role Code | Role Name |
|:--------|:----------|:--------|:----------|:----------|
| 1 | System Administrator | 1 | super_admin | Super Administrator |
| 2 | Eng. Dawit Haile | 2 | admin | System Administrator |
| 3 | Dr. Mesfin Tadesse | 3 | department_head | Department Head |
| 4 | Dr. Bethlehem Yohannes | 3 | department_head | Department Head |
| 5 | Almaz Bekele | 5 | records_officer | Records Officer |
| 6 | Kassahun Gizaw | 6 | archivist | Archivist |
| 7 | Dr. Rahel Assefa | 4 | document_controller | Document Controller |
| 8 | Solomon Kebede | 7 | staff | Staff Member |
| 9 | Hanan Mohammed | 7 | staff | Staff Member |
| 10 | Yared Fikre | 7 | staff | Staff Member |
| 11 | Tigist Alem | 5 | records_officer | Records Officer |

### 3.2 Assignment Notes

1. **Single-Role Assignment:** Each user has exactly one assigned role
2. **No Role Stacking:** The system does not support multiple simultaneous roles per user
3. **Direct Permissions:** User ID 9 (Hanan Mohammed) has additional direct permissions beyond their role
4. **Inactive/Locked Users:** Users 10 and 11 retain their role assignments despite account status

---

## 4. Direct User Permissions

### 4.1 Users with Direct Permissions

Only **1 user** has direct permissions assigned outside of role-based permissions:

**User ID 9: Hanan Mohammed (Direct Permissions Auditor)**
- **Base Role:** Staff Member (7)
- **Additional Direct Permissions:**
  - `documents.delete` (Permission 17)
  - `accreditation.review_evidence` (Permission 60)
  - `audit.view` (Permission 71)

### 4.2 Direct Permission Analysis

| Permission ID | Permission Code | Permission Name | Business Justification |
|:--------------|:----------------|:----------------|:-----------------------|
| 17 | documents.delete | Documents delete | Quality assurance document cleanup authority |
| 60 | accreditation.review_evidence | Accreditation review evidence | QA compliance auditor role |
| 71 | audit.view | Audit view | Institutional compliance audit access |

**Purpose:** This user represents a specialized auditor role that requires elevated permissions beyond standard staff access for institutional compliance and quality assurance functions.

---

## 5. Authorization Version Tracking

### 5.1 Authorization Schema Version

- **Current Version:** 1
- **All Users:** Operating on authorization version 1
- **Schema Status:** Uniform across entire user base

### 5.2 Version Management Notes

The `authz_version` field tracks the authorization schema version for each user. All users currently share the same version, indicating:
- No pending authorization migrations
- Consistent permission enforcement model
- No legacy users requiring upgrade

---

## 6. Account Status Analysis

### 6.1 Status Distribution

| Status | Count | Percentage |
|:-------|:------|:-----------|
| Active | 9 | 81.8% |
| Inactive | 1 | 9.1% |
| Locked | 1 | 9.1% |
| **Total** | **11** | **100%** |

### 6.2 Non-Active Account Details

#### Inactive Account
- **User:** Yared Fikre (ID 10)
- **Email:** suspended.staff@wu.edu.et
- **Role:** Staff Member
- **Position:** Former Laboratory Technician
- **Status Reason:** Account deactivated (likely employment termination)
- **Security Impact:** Cannot authenticate; retains role assignment for audit trail

#### Locked Account
- **User:** Tigist Alem (ID 11)
- **Email:** locked.account@wu.edu.et
- **Role:** Records Officer
- **Position:** Procurement Records Assistant
- **Status Reason:** Security lock (potential security incident or policy violation)
- **Security Impact:** Authentication prevented; requires administrative unlock

---

## 7. Email Domain Analysis

### 7.1 Email Domains

All 11 users use the **@wu.edu.et** institutional domain:

| Subdomain Pattern | Count | User Types |
|:------------------|:------|:-----------|
| `admin@` | 1 | System administrator |
| `sysadmin@` | 1 | Technical administrator |
| `dean.*@` | 1 | Academic leadership |
| `head.*@` | 1 | Department leadership |
| `*director@` | 1 | Directorate leadership |
| Functional role addresses | 2 | registrar, archivist |
| Staff addresses | 4 | staff.*, auditor.*, suspended.*, locked.* |

**Observation:** Email addresses follow institutional naming conventions and reflect organizational hierarchy and functional roles.

---

## 8. User Creation Timeline

### 8.1 Creation Sequence

- **Initial User:** System Administrator (ID 1) - `2026-11-15 13:31:12.460092`
- **Batch Creation:** Users 2-11 - `2026-11-15 13:31:14.200656`

**Analysis:**
- System Administrator created first (bootstrap account)
- All other accounts created in a single batch 2 seconds later
- Indicates automated seeding process during system initialization
- All accounts pre-configured with roles and organizational memberships

---

## 9. System Configuration Insights

### 9.1 Authorization Design Patterns

1. **Role-Based Access Control (RBAC):** Primary authorization model
2. **Direct Permission Override:** Supported for exceptional cases (e.g., User 9)
3. **Account Status Controls:** Three-state model (active/inactive/locked)
4. **Single-Role Assignment:** No role stacking; clearer permission model
5. **System Role Protection:** All roles marked as system-protected

### 9.2 Security Observations

**Strengths:**
- Clear role hierarchy with defined authority levels
- Account status controls for lifecycle management
- Authorization version tracking for schema evolution
- Direct permission capability for exceptional needs
- Audit trail preservation (inactive users retain assignments)

**Considerations:**
- Super Administrator has unrestricted access (all 74 permissions)
- No multi-factor authentication indicators in schema
- Two non-active accounts present in production staging data
- Single email domain dependency (@wu.edu.et)

---

## 10. Acceptance Criteria Verification

### 10.1 Expected vs. Actual Counts

| Entity | Expected | Actual | Status |
|:-------|:---------|:-------|:-------|
| Users | 11 | 11 | ✅ Match |
| Roles | 7 | 7 | ✅ Match |
| Permissions | 74 | 74 | ✅ Match |

### 10.2 Data Quality Assessment

- ✅ All users have valid role assignments
- ✅ All users have organizational memberships
- ✅ All users have primary membership designations
- ✅ All roles are active and system-protected
- ✅ Authorization version consistent across all users
- ✅ Email addresses follow institutional format
- ✅ Creation timestamps reflect logical seeding sequence

---

## 11. Recommendations

### 11.1 User Account Management

1. **Review Inactive Accounts:**
   - User 10 (Yared Fikre): Consider permanent removal if employment terminated
   - User 11 (Tigist Alem): Review lock reason and determine resolution path

2. **Direct Permission Audit:**
   - Document business justification for User 9's elevated permissions
   - Establish formal process for direct permission requests

3. **Super Administrator Controls:**
   - Implement additional authentication requirements for User 1
   - Consider activity monitoring for super-privileged accounts

### 11.2 System Enhancements

1. **Multi-Factor Authentication:** Add MFA support for administrative roles
2. **Role Delegation:** Consider time-bound role delegation mechanism
3. **Permission Request Workflow:** Formalize direct permission request process
4. **Account Lifecycle Automation:** Implement automated account expiration for inactive users

---

## Appendix A: Data Sources

### Database Queries Executed

1. **Users:** `SELECT id, name, email, status, authz_version, created_at FROM users`
2. **Roles:** `SELECT id, code, name, description, active, is_system, created_at FROM roles`
3. **User Roles:** `SELECT user_id, role_id FROM user_roles`
4. **User Permissions:** `SELECT user_id, permission_id FROM user_permissions`

### Schema Information

- **Database:** wu_dms_staging
- **DBMS:** MySQL 127.0.0.1:3306
- **Query Date:** 2025-01-XX
- **Schema Version:** Authorization v1

---

## Document Control

| Version | Date | Author | Change Summary |
|:--------|:-----|:-------|:---------------|
| 1.0 | 2025-01-XX | System Audit | Initial comprehensive inventory |

**Document Status:** FINAL  
**Classification:** INTERNAL USE  
**Review Cycle:** Quarterly

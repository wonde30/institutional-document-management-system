# Wollo University DMS — Documentation Index

This directory contains the authoritative technical, operational, and architectural documentation for the Wollo University Administrative and Document Management System (WU-DMS).

---

## Documentation Structure

```
docs/
├── architecture/     # Core system topology, security boundaries, and institutional workflows
├── deployment/       # Production deployment, infrastructure setup, and provisioning
├── development/      # API contracts, authentication mechanics, configuration, and security audits
└── operations/       # Operational runbooks, pilot guidelines, quality scorecards, and roadmap
```

---

## 1. Architecture (`docs/architecture/`)

System topology, authorization design, institutional data flows, and workflow engines.

| Document | Purpose |
| :--- | :--- |
| [System Architecture](architecture/SYSTEM_ARCHITECTURE.md) | High-level system architecture, technology stack, zero-trust backend boundaries, and data flow topologies. |
| [Authorization Model](architecture/AUTHORIZATION_MODEL.md) | Dynamic multi-scoped authorization model, unit vs. subtree scoping, and instant session revocation (`authz_version`). |
| [RBAC Authorization Matrix](architecture/RBAC_AUTHORIZATION_MATRIX.md) | NIST SP 800-162 aligned relational RBAC specification, roles, permissions, and matrix. |
| [Document Registration & Routing](architecture/DOCUMENT_REGISTRATION_WORKFLOW.md) | Registration sequence locking, cryptographic SHA-256 integrity, multi-tier routing, and official letter issuance safeguards. |
| [Frontend Routing Architecture](architecture/ROUTING_ARCHITECTURE.md) | Vue Router 4 navigation guards, route hierarchy, RBAC route protection, and deep-link preservation. |
| [Institutional Configuration Center](architecture/INSTITUTIONAL_CONFIGURATION_CENTER.md) | Dynamic administrative governance for workflows, numbering policies, routing policies, and retention schedules. |

---

## 2. Deployment (`docs/deployment/`)

Infrastructure provisioning, environment configuration, and production hosting specifications.

| Document | Purpose |
| :--- | :--- |
| [Production Deployment Guide](deployment/PRODUCTION_DEPLOYMENT_GUIDE.md) | Target operating system (Ubuntu 24.04 LTS / RHEL 9), Nginx reverse proxy configuration, PHP 8.4+ FPM tuning, MySQL 8.4 LTS hardening, SSL/TLS, and systemd service templates. |

---

## 3. Development (`docs/development/`)

API contracts, authentication protocols, runtime catalogs, and engineering changelog.

| Document | Purpose |
| :--- | :--- |
| [REST API Contract Specification](development/API_CONTRACT_AUDIT.md) | Comprehensive REST API contract across all 62 endpoints, status codes, request/response JSON envelopes. |
| [Authentication & Session Security](development/AUTHENTICATION_SECURITY_AUDIT.md) | Laravel Sanctum first-party stateful cookie architecture, CSRF double-submit protection, and session lifecycles. |
| [System Settings Configuration Catalog](development/CONFIGURATION_CATALOG.md) | Complete schema of database-persisted runtime settings, defaults, validation rules, and scopes. |
| [Data Import & Export Catalog](development/DATA_IMPORT_EXPORT_CATALOG.md) | Specifications for audited dataset import/export operations, validation stages, and data schemas. |
| [Security Audit & Threat Model](development/SECURITY_FINDINGS.md) | OWASP ASVS alignment, threat models, Proclamation No. 1152/2019 compliance, and vulnerability remediation registry. |
| [Implementation Changelog](development/IMPLEMENTATION_CHANGELOG.md) | Architectural decision record and modification log from baseline consolidation. |

---

## 4. Operations (`docs/operations/`)

Routine maintenance, pilot deployment runbooks, background processes, and traceability.

| Document | Purpose |
| :--- | :--- |
| [Operations Runbook](operations/OPERATIONS.md) | Laravel scheduler configuration, queue workers, health monitoring endpoints, log rotation, and automated backup procedures. |
| [College of Informatics Pilot Runbook](operations/COLLEGE_OF_INFORMATICS_PILOT_RUNBOOK.md) | Pilot deployment procedures, role provisioning, sample document lifecycles, and verification checklist for the College of Informatics. |
| [Build Status & Verification Matrix](operations/BUILD_STATUS.md) | Continuous integration status, automated test coverage matrix, and verified architectural capabilities. |
| [Proposal Implementation Traceability](operations/PROPOSAL_IMPLEMENTATION_TRACEABILITY.md) | Traceability matrix cross-referencing functional requirements against delivered system modules. |
| [Next Phase Roadmap](operations/NEXT_PHASE_ROADMAP.md) | Prioritized backlog, institutional enhancements, and upcoming release milestones. |

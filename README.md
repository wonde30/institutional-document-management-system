# Wollo University Administrative and Document Management System (WU-DMS)

WU-DMS is an institutional-grade document repository, correspondence management, and workflow automation system built for Wollo University. The system provides verifiable revision control, departmental scoping, dynamic role-based access control (RBAC), digital signatures, and statutory archival compliance across all academic and administrative units.

---

## Architecture Overview

```
DMS/
├── client/     # Frontend Single Page Application (Vue 3, TypeScript, Pinia, Tailwind CSS)
└── server/     # Backend REST API Service (Laravel 13, PHP 8.4+, MySQL 8.4 InnoDB)
```

### Core Subsystems

* **Institutional Document Repository:** Multi-version document intake with optimistic concurrency locking (`lock_version`), canonical reference serial generation, and cryptographic SHA-256 integrity verification.
* **Dual-Path Correspondence Registry:** Centralized ledger for incoming institutional correspondence, internal document movements, receipt acknowledgments, and outgoing dispatches.
* **Dynamic Scoped RBAC:** Granular permissions operating across three authorization tiers (`global`, `subtree`, and `unit`) with instant session invalidation upon privilege alteration (`authz_version`).
* **Workflow & Decision Engine:** State machine transition routing with approval, rejection, and return-for-revision decision handling.
* **Digital Archiving & Statutory Retention:** Long-term archival preservation schedules and legal hold enforcement adhering to the **Statutory Physical Originals Preservation Rule** (Proclamations No. 1152/2019 and No. 1072/2018).
* **HERQA Accreditation Dossiers:** Evidence collection, criteria scoring, and dossier compilation aligned with Higher Education Relevance and Quality Agency standards.
* **PKI Digital Trust Infrastructure:** X.509 cryptographic signing with OpenSSL, RFC 3161 timestamping, and detached PKCS#7 ByteRange envelope verification for PDF documents.

---

## Technology Stack

### Backend
* **Framework:** Laravel 13 REST API
* **Runtime:** PHP 8.4+
* **Database:** MySQL 8.4 Community Server (InnoDB Engine, `utf8mb4_0900_as_cs` collation)
* **Authentication:** Laravel Sanctum SPA stateful session cookies with double-submit CSRF protection
* **Testing:** PHPUnit / Pest test suites (363 comprehensive feature and unit tests)

### Frontend
* **Framework:** Vue 3 (Composition API, `<script setup lang="ts">`)
* **Type System:** TypeScript 5.8+ (Strict type checking via `vue-tsc`)
* **State Management:** Pinia 4
* **Routing:** Vue Router 4 with route-level permission navigation guards
* **Styling:** Tailwind CSS 4 with custom institutional theme tokens (`#0B6B63`, `#81D7CA`, `#142D45`, `#101F30`, `#EAF5F2`)
* **Icons:** Lucide Icons
* **Testing:** Vitest 3 unit and component test suites (152 tests)

---

## Getting Started

### Prerequisites
* PHP 8.4 or higher with OpenSSL, PDO, and Mbstring extensions
* Composer 2.x
* Node.js 20+ and npm
* MySQL 8.4+ Community Server

### 1. Backend Setup

```bash
cd server

# Install PHP dependencies
composer install

# Configure environment
cp .env.example .env
php artisan key:generate

# Run database migrations
php artisan migrate

# Seed baseline credentials and reference taxonomies
php artisan db:seed

# Start Laravel development server
php artisan serve --port=8000
```

### 2. Frontend Setup

```bash
cd client

# Install JavaScript dependencies
npm install

# Start Vite development server
npm run dev
```

The application will be accessible at `http://localhost:5173`.

---

## Testing & Quality Assurance

### Backend Test Suite
Run the full PHPUnit/Pest suite covering authorization, state machines, concurrency, and cryptographic engines:

```bash
cd server
php artisan test
```

### Frontend Test Suite & Static Analysis
Run type checking and the Vitest component suite:

```bash
cd client

# TypeScript compile check
npx vue-tsc -b

# Component and store unit tests
npm test

# Production build test
npm run build
```

---

## Security & Access Control

* **Session Boundary:** Stateful HttpOnly, SameSite encrypted session cookies. Sensitive access tokens are never persisted to browser `localStorage`.
* **Instant Session Revocation:** When an account is locked or permissions are modified by a system administrator, the user's `authz_version` is incremented, terminating active sessions immediately.
* **Scoped Record Authorization:** Holding a capability is necessary but not sufficient. Every record-scoped request is additionally evaluated against the organizational unit or document that owns it.
* **Active Organizational Unit:** The interface operates in an explicit organizational context. The server determines which units an account may select, validates every selection, and publishes the capability set calculated for that context. A user without a selected unit receives globally scoped capabilities only — never the union of every scoped grant.
* **Runtime Configuration:** A code-defined catalogue of administrator-managed settings, persisted in the database, validated server-side and audited on every change. Deployment configuration and secrets remain under environment management and are never exposed to the browser.
* **Dataset Import & Export:** A bounded, audited module over an explicit catalogue of datasets. Imports are validated and previewed before any record is created, and exports are bounded by the records the caller is permitted to reach.
* **Account Recovery:** Recovery links are delivered by mail, are single use, are rate limited per account and per source address, and terminate every session established with the previous credential. The recovery response is identical for a registered and an unregistered address.
* **Physical Custody Rule:** Digital scanning never authorizes the physical destruction of official university records without a formal Gazetted disposal order.

The complete authorization specification is in [`docs/AUTHORIZATION_MODEL.md`](docs/AUTHORIZATION_MODEL.md);
the deployment configuration registry is in [`docs/CONFIGURATION_CATALOG.md`](docs/CONFIGURATION_CATALOG.md);
and the current verification scorecard is in [`docs/BUILD_STATUS.md`](docs/BUILD_STATUS.md).

---

## License

Institutional Property of Wollo University. All rights reserved.

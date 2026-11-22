# Wollo University Document Management System

A secure, institutional-grade document repository, correspondence management, and workflow automation system built for Wollo University.

## Overview

WU-DMS provides verifiable revision control, departmental scoping, dynamic role-based access control (RBAC), digital signatures, and statutory archival compliance across all academic and administrative units.

## Tech Stack

**Server:** Laravel 13, PHP 8.4+, MySQL 8.4, Laravel Sanctum  
**Client:** Vue 3, TypeScript, Pinia, Vue Router, Tailwind CSS, Axios

## Requirements

- PHP 8.4+ (OpenSSL, PDO, Mbstring extensions)
- Composer 2.x
- Node.js 20+ and npm
- MySQL 8.4+

## Project Structure

```
├── client/     Vue 3 SPA (frontend)
├── server/     Laravel 13 REST API (backend)
```

## Setup

### Server

```bash
cd server
composer install
cp .env.example .env
php artisan key:generate

# Create MySQL database (wollo_dms), then:
php artisan migrate --seed
php artisan serve
```

Default: `http://localhost:8000`

### Client

```bash
cd client
npm install
cp .env.example .env
npm run dev
```

Default: `http://localhost:5173`

## Authentication

Uses Laravel Sanctum SPA cookie-based authentication. Client and server communicate over stateful session cookies with CSRF protection.

## Authorization

- **Roles & Permissions:** Dynamic scoped RBAC (global, unit, subtree)
- **Direct User Permissions:** Permissions can be assigned directly to users
- **Policies:** Server enforces authorization on every protected route
- **Client Guards:** Client uses permissions for UX (navigation guards, menus, v-can directive)
- **Effective Permissions:** Client reads role permissions + direct user permissions from API (`GET /api/v1/auth/me`)

## Testing

### Server

```bash
cd server
php artisan test
./vendor/bin/pint
```

### Client

```bash
cd client
npm run lint
npx vue-tsc --noEmit
npm run build
```

## Build

### Server

No build step required. Deploy via `php artisan serve` or configure web server (Apache/Nginx).

### Client

```bash
cd client
npm run build
```

Outputs to `client/dist/`.

---

**Institutional Property of Wollo University**

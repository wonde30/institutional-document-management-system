# STACK UPGRADE — FROM TODO APP TO INSTITUTION SYSTEM

## What to add beyond the QMT Todo app stack

---

## BACKEND ADDITIONS

### 1. Spatie Laravel Permission
Replaces your hand-rolled RBAC tables. Production standard across 50M+ Laravel installs.

```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

User model gets: `use HasRoles;`
Check permissions: `$user->can('documents.create')`
Assign: `$user->assignRole('department_admin')`

**Why not hand-roll:** Spatie handles cache invalidation, team scoping, and the pivot tables correctly. Your hand-rolled version will have bugs you don't know about yet.

---

### 2. Spatie Media Library
Handles all file uploads, storage, versioning, and format conversion.

```bash
composer require spatie/laravel-medialibrary
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan migrate
```

Document model gets: `use HasMedia; implements HasMedia`
Upload: `$document->addMediaFromRequest('file')->toMediaCollection('documents')`
Retrieve: `$document->getFirstMediaUrl('documents')`

---

### 3. Spatie Activity Log
Mandatory for an administrative system. Every document state change, approval action, and user action must be logged.

```bash
composer require spatie/laravel-activitylog
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan migrate
```

Automatic logging on models: `use LogsActivity;`
Manual: `activity()->causedBy($user)->performedOn($document)->log('submitted for approval')`

---

### 4. Laravel Reverb (Real-Time WebSockets)
Self-hosted WebSocket server. Free. Replaces Pusher for real-time messaging and notifications.

```bash
composer require laravel/reverb
php artisan reverb:install
```

Frontend: `npm install --save-dev laravel-echo pusher-js`

Runs as: `php artisan reverb:start` (dev) / systemd service (production)

---

### 5. Laravel Horizon (Queue Monitoring)
Required once you have file processing, notifications, and approval routing on queues.

```bash
composer require laravel/horizon
php artisan horizon:install
php artisan migrate
```

Dashboard at `/horizon` — gate it to `super_admin` only.
Queues to configure: `default`, `notifications`, `file-processing`, `approvals`

---

### 6. Laravel Telescope (Dev Only)
Essential for debugging N+1 queries, slow requests, failed jobs during development.

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

**Dev only** — never deploy to production. Gate it in `TelescopeServiceProvider`.

---

## FRONTEND ADDITIONS

### 1. Laravel Echo
Real-time WebSocket client. Connects to Reverb.

```bash
npm install --save-dev laravel-echo pusher-js
```

```typescript
// src/plugins/echo.ts
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher
export const echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: import.meta.env.VITE_REVERB_PORT,
  forceTLS: false,
  enabledTransports: ['ws', 'wss'],
})
```

---

### 2. VueUse
Utility composables library. Used by senior Vue devs on every project.

```bash
npm install @vueuse/core
```

Useful for: `useIntersectionObserver` (infinite scroll), `useLocalStorage` (never for tokens), `useDebounceFn` (search inputs), `useEventListener` (cleanup-safe), `useTimeoutFn`

---

### 3. Zod (Runtime API Validation)
TypeScript types tell you the shape at compile time. Zod validates it at runtime.

```bash
npm install zod
```

```typescript
// src/types/document.ts
import { z } from 'zod'

export const DocumentSchema = z.object({
  id: z.string().uuid(),
  title: z.string(),
  status: z.enum(['draft', 'pending_approval', 'approved', 'rejected']),
  created_at: z.string().datetime(),
})

export type Document = z.infer<typeof DocumentSchema>
```

**Why:** Your API will return unexpected shapes. Zod catches it at the boundary — before it crashes inside a component.

---

### 4. TipTap (Rich Text Editor)
For message composition and announcement creation.

```bash
npm install @tiptap/vue-3 @tiptap/pm @tiptap/starter-kit
```

---

## INFRASTRUCTURE (for when you deploy)

| Need | Free Tier Option | Notes |
|---|---|---|
| App hosting | Railway | Laravel + queue workers |
| Database | Railway MySQL | 1GB free |
| File storage | Cloudflare R2 | S3-compatible, 10GB free |
| WebSocket | Self-hosted Reverb on Railway | No extra cost |
| Queue monitor | Horizon (same server) | |
| Email | Resend | 3,000 emails/month free |

---

## WHAT NOT TO ADD

| Temptation | Reason to skip |
|---|---|
| GraphQL | REST is correct for this system. GraphQL overhead is not worth it. |
| Microservices | Solo dev + university scale = monolith wins |
| Docker (now) | Learn it after the app works. Don't add complexity before the system exists. |
| Redis (now) | MySQL queue driver is fine until you have >50 concurrent users |
| Next.js / Nuxt | Your Vue 3 SPA is correct. Don't switch. |
| Inertia.js | You already have a clean SPA/API separation. Inertia adds no value here. |

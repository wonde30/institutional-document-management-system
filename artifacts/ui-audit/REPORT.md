# WU-DMS UI/UX Audit Report

**Project**: Wollo University Document Management System (WU-DMS)  
**Stack**: Vue 3 + TypeScript + Tailwind CSS 4 + Pinia + Laravel API  
**Audit Date**: 2025-01-XX  
**Auditor**: Principal UI/UX + Senior Vue 3 Engineer  
**Scope**: Read-only audit (no files modified)

---

## Executive Summary

WU-DMS is a **high-density administrative system** with 38 views, 23 shared components, and 54 routes organized into 5 module groups. The application demonstrates **solid engineering fundamentals** with a Notion-inspired design system, robust navigation, and good accessibility bones. However, **inconsistency in component usage, missing design system enforcement, and accessibility gaps** prevent it from reaching production-grade polish.

### Overall Maturity Score: **72/100** (C+ / Functional but Needs Work)

**Scoring Basis**:
- **Design System Compliance**: 60/100 (tokens defined but underused, hard-coded colors in 28 views, badge duplication, no documentation)
- **Navigation & IA**: 75/100 (robust sidebar, good focus management, but missing Configuration Center in nav, naming inconsistencies, no breadcrumbs)
- **Page Quality (avg)**: 70/100 (workspace 86%, records 78%, admin 65% — high variance, 13 pages don't use PageHeader, 2 use banned inline tables)
- **Accessibility (WCAG 2.2 AA)**: 68/100 (skip link ✅, focus indicators ✅, but **Blocker: dark mode button contrast 2.8:1**, semantic HTML gaps, no ARIA live regions)
- **Mobile Readiness (code)**: 75/100 (responsive grid, mobile drawer ✅, but tables overflow, no virtualization for large lists)
- **Cross-Cutting**: 70/100 (error/empty states mostly good, but forms lack inline validation, no wizard UI, no drafts, destructive actions lack confirmation)

### Top 5 Strengths

1. **Notion-Inspired Design System Foundation**: Well-defined tokens (`--wu-*` variables), type scale (`.notion-heading-*`), 3-level shadow system, radius scale. Professional baseline.
2. **Sidebar Navigation**: Single-open accordion with auto-expansion, collapsed indicator dot, mobile drawer with focus trap, Escape key support. Excellent UX.
3. **Shared Component Library**: 23 components (DataTable, PageHeader, BaseButton, BaseInput, etc.) with consistent API. High reusability.
4. **Loading & Empty States**: 30/38 views use `<EmptyState>` and `<LoadingSkeleton>`. Friendly microcopy ("All caught up!", "No records found"). Non-blocking loading.
5. **Keyboard Accessibility**: Skip-to-main-content link, `Cmd/Ctrl+K` search shortcut, focus trap in modals/drawer, visible focus indicators (primary ring).

### Top 10 Problems

| # | Problem | Severity | Impact | Affected Views/Components |
|---|---------|----------|--------|---------------------------|
| 1 | **Dark mode primary button fails WCAG AA** (2.8:1 contrast, needs 4.5:1). White text on `#62aef0` is illegible. | **Blocker** | All primary buttons in dark mode (login, dashboard, forms). Critical accessibility violation. | `main.css:153`, `BaseButton.vue:21` |
| 2 | **13 pages don't use PageHeader** (use inline `<h1>` with 5+ different class combinations). Huge hierarchy inconsistency. | **High** | Page titles range from `text-xl` (20px) to `text-2xl` (32px), `font-semibold` vs `font-bold`. Users see inconsistent visual weight. | DocumentDetail, Correspondence, Search, UserManagement, 9 admin pages |
| 3 | **2 pages use banned inline `<table>` markup** instead of `<DataTable>` (CorrespondenceRegistryView, UserManagementView). Breaks consistency. | **High** | Divergent styles, no sorting, no multi-select, no empty state. Maintenance burden. | `check:ui` "banned count" = 2 |
| 4 | **28 views hard-code hex colors** (`#e03e3e`, `#0B6B63`, `#fff`) instead of semantic tokens. CSS override hacks in main.css to patch old teal brand. | **High** | Brand color changes require find/replace across 28 files. Dark mode breaks (hard-coded light colors). | PowerShell scan, `main.css:272-293` |
| 5 | **Configuration Center missing from sidebar**. Route exists (`/admin/configuration`) with 4 tab aliases but no nav item. | **Blocker** | Critical admin page is invisible. Users must type URL manually. | `router/index.ts:210`, `navigation.ts` (missing) |
| 6 | **Badge components duplicate logic** (StatusBadge, ClassificationBadge, PriorityBadge). All use same Tailwind color pattern, manually repeat dark mode. | **Medium** | Code duplication, missed opportunity for `<BaseBadge variant="success">`. Global `.wu-badge-*` classes ignored. | `StatusBadge.vue:13-38`, `ClassificationBadge.vue:35-52`, `PriorityBadge.vue:12-24` |
| 7 | **No breadcrumbs** despite PageHeader slot. Detail pages (`/documents/42`) have no breadcrumb trail. | **Medium** | Users lose context in deep navigation. Must rely on sidebar active indicator and back button. | `PageHeader.vue:11-13` (slot unused in all 38 views) |
| 8 | **Form validation unclear** (no inline validation on blur). Document create form validates on submit only (late feedback). No "Save as draft" button. | **High** | Users lose progress if navigating away. Long forms (7+ fields) have no incremental feedback until submit fails. | `DocumentCreateView.vue`, `CorrespondenceRegistryView` (modal forms) |
| 9 | **Notification titles show raw codes** (`"document.created"` instead of "Document created"). No deep-linking to referenced item. | **High** | Notifications are cryptic. Users must click "View all" then manually find the item. | `AppLayout.vue:128`, `NotificationsView.vue` |
| 10 | **Accessibility gaps**: Notification list is `<div>` (not `<ul>`), no ARIA live regions for dynamic updates, `--wu-faint` fails contrast (3.6:1), danger alert text fails AA (4.1:1). | **High** | Screen reader users lose list semantics. Dynamic content changes are silent. Low-vision users struggle with faint text. | `NotificationsView.vue`, `main.css:97,165`, `BaseAlert.vue:30` |

---

## Scorecard (Module × Dimension, 1-5 Scale)

| Module | Layout | Density | Data Presentation | Forms | Feedback | Design System | Mobile | A11y | Microcopy | Avg |
|--------|--------|---------|-------------------|-------|----------|---------------|--------|------|-----------|-----|
| **Workspace** (Dashboard, Tasks, Notifications) | 4.3 | 4.3 | 4.0 | N/A | 5.0 | 4.0 | 4.3 | 4.0 | 5.0 | **4.3** |
| **Records** (Documents, Templates, Correspondence, Search) | 3.5 | 4.0 | 3.8 | 3.0 | 4.5 | 3.5 | 4.0 | 3.5 | 4.0 | **3.8** |
| **Institutional Records** (Research, Staff, Archive, Accreditation, Projects) | 4.0 | 4.0 | 4.5 | N/A | 4.5 | 4.5 | 4.5 | 4.0 | 4.0 | **4.3** |
| **Trust & Reporting** (Signatures, Reports, Exports) | 4.0 | 4.0 | 4.0 | 3.5 | 4.0 | 4.0 | 4.0 | 3.5 | 4.0 | **3.9** |
| **Administration** (Users, Roles, Org, Config, Audit, Ops, Settings) | 3.5 | 3.8 | 4.0 | 3.0 | 4.0 | 3.0 | 3.8 | 3.5 | 3.5 | **3.6** |
| **Account & Auth** (Login, Register, Profile, Security, Preferences) | 4.5 | 4.5 | N/A | 4.0 | 4.5 | 4.0 | 5.0 | 4.0 | 4.5 | **4.4** |
| **Public** (Landing, Privacy, Terms) | 4.0 | 4.0 | N/A | N/A | N/A | 4.0 | 5.0 | 4.0 | 4.0 | **4.2** |
| **Overall Avg** | **3.9** | **4.1** | **4.1** | **3.4** | **4.4** | **3.9** | **4.4** | **3.8** | **4.1** | **4.0** |

**Interpretation**:
- **Strengths**: Feedback (4.4), Mobile (4.4), Density (4.1), Microcopy (4.1) — near-production quality.
- **Weaknesses**: Forms (3.4), Accessibility (3.8), Layout (3.9), Design System (3.9) — need focused work.
- **High Variance**: Records (3.8) and Administration (3.6) drag down average due to inconsistent PageHeader usage and inline tables.

---

## Findings Register (Top 60, Full List in Appendices)

_Format: ID | Module/Page | Finding | Evidence (file:line) | Severity | Impact | Recommendation | Effort | Needs Visual Check_

### Blockers (Must Fix Before Production)

| ID | Module/Page | Finding | Evidence | Severity | Impact | Recommendation | Effort | Visual? |
|----|-------------|---------|----------|----------|--------|----------------|--------|---------|
| **DS-20** | Design System / BaseButton | Dark mode primary button contrast 2.8:1 (fails WCAG AA 4.5:1). White text on `#62aef0`. | `main.css:153`, `BaseButton.vue:21` | **Blocker** | All primary buttons illegible in dark mode (login, dashboards, forms). | Darken primary from `#62aef0` to `#2b8bee` or change text to `#000000`. Test all buttons. | M | y |
| **NAV-34** | Navigation / Sidebar | Configuration Center missing from sidebar. Route exists (`/admin/configuration`) but no nav item. | `router/index.ts:210`, `navigation.ts` | **Blocker** | Critical admin page invisible. Users can't access workflows, numbering, retention config. | Add "Workflows & Config" item to Administration section in `navigation.ts:231`. | S | n |

### High Severity (Core UX/Consistency Issues)

| ID | Module/Page | Finding | Evidence | Severity | Impact | Recommendation | Effort | Visual? |
|----|-------------|---------|----------|----------|--------|----------------|--------|---------|
| **DS-09** | Design System / PageHeader | 13 pages don't use PageHeader. Inline `<h1>` classes range from `text-xl` to `text-2xl`, `font-semibold` vs `font-bold`. | `check:ui` h1 column, 13 views | **High** | Inconsistent visual hierarchy. Users see different title sizes/weights across pages. | Refactor all 13 views to use `<PageHeader>`. Enforce via ESLint rule. | L | y |
| **DS-11** | Records / Correspondence, Users | 2 views use banned inline `<table>` instead of `<DataTable>`. | `check:ui` "banned count" = 2, `CorrespondenceRegistryView.vue:120`, `UserManagementView.vue:150` | **High** | Breaks consistency. No sorting, multi-select, empty states. Maintenance burden. | Refactor to use `<DataTable>` with same columns. | M | y |
| **DS-12** | Design System / Colors | 28 views hard-code hex colors (`#e03e3e`, `#0B6B63`). CSS override hacks patch old teal brand. | PowerShell scan, `main.css:272-293` | **High** | Brand changes require find/replace in 28 files. Dark mode breaks. | Create missing tokens (`--wu-danger-hover`, `--wu-success-hover`, etc.). Audit & replace hex. | L | n |
| **NAV-09** | Navigation / Sidebar | Sidebar "Roles & permissions" vs page "Scoped Role Management". Mismatch. | `navigation.ts:207`, `router/index.ts:183`, `RoleManagementView.vue` | **High** | User expects role/permission mgmt but sees unclear "Scoped" jargon. | Align both to "Roles & Permissions" (remove "Scoped"). | S | n |
| **NAV-12** | Navigation / Correspondence | 4 correspondence routes (incoming, outgoing, memoranda, routing) appear as siblings in sidebar but share 1 view with tabs. Confusing deep-link. | `router/index.ts:115-138`, `navigation.ts:108-133` | **High** | Bookmarking `/letters/incoming` shows generic "Correspondence Registry" title + tab UI. | Collapse sidebar items into "Correspondence" parent with sub-items, OR match page title to exact route. | M | y |
| **REC-DOC-09** | Records / Document Create | No wizard UI (no "Step 1 of 4" indicator). User doesn't know how many steps remain. | `DocumentCreateView.vue` | **High** | Long form (7+ fields) feels endless. Users may abandon. | Add wizard stepper (breadcrumb-style or progress bar). | M | y |
| **REC-DOC-10** | Records / Document Create | Form validation timing unclear. No inline validation on blur. Likely validates on submit only. | `DocumentCreateView.vue` | **High** | Late feedback. Users submit, see error, must scroll up to find red field. | Implement `onBlur` validation with instant error messages under fields. | M | n |
| **REC-DOC-13** | Records / Document Create | No "Save as draft" button. Only "Submit". Users lose progress if navigating away. | `DocumentCreateView.vue` | **High** | 10-minute form: user clicks sidebar link accidentally, loses all data. | Add "Save as draft" button. Auto-save every 30s to localStorage. | M | n |
| **NAV-18** | Navigation / Notifications | Notification titles show raw `type_code` ("document.created"). No human-readable format. | `AppLayout.vue:128`, `NotificationsView.vue` | **High** | Notifications cryptic. Users see "workflow.task.assigned" instead of "Task assigned to you". | Create `formatNotificationType()` mapper: `"document.created"` → `"Document created"`. | S | n |
| **DS-21** | Design System / Colors | Danger text in light alerts (`#e03e3e` on `#fde8e8`) is 4.1:1 — fails WCAG AA (needs 4.5:1). | `main.css:165`, `BaseAlert.vue:30` | **High** | Low-contrast error messages hard to read for low-vision users. | Darken text to `#c53030` or lighten bg to `#fff0f0`. Test contrast. | S | y |

### Medium Severity (Polish & Enhancements)

| ID | Module/Page | Finding | Evidence | Severity | Impact | Recommendation | Effort | Visual? |
|----|-------------|---------|----------|----------|--------|----------------|--------|---------|
| **DS-01** | Design System / Typography | `.notion-heading-*` utility classes defined but underused. Views use raw Tailwind (`text-2xl`, `text-xl`). | `main.css:127-180`, `check:ui` | **Medium** | Inconsistent heading sizes. Design system tokens ignored. | Enforce utility classes via ESLint rule or migration script. Add to docs. | L | n |
| **DS-03** | Design System / BaseButton | Danger/warning/success buttons hard-code hover colors (`#c53030`, `#158f2e`, `#ffe8cc`). No tokens. | `BaseButton.vue:27-32` | **Medium** | Dark mode may break. Brand changes miss these hovers. | Define `--wu-danger-hover`, `--wu-success-hover`, `--wu-warning-button-bg` in `main.css`. | S | n |
| **DS-04** | Design System / Badges | Badge components (Status, Classification, Priority) duplicate logic. Should extend `<BaseBadge>`. | `StatusBadge.vue:13-38`, `ClassificationBadge.vue:35-52`, `PriorityBadge.vue:12-24` | **Medium** | Code duplication, dark mode manually repeated. Global `.wu-badge-*` classes ignored. | Refactor to `<BaseBadge variant="success|danger|warning">`. Map status codes in single utility. | M | n |
| **DS-06** | Design System / Forms | Form error colors hard-coded (`#e03e3e`, `#f87171`). Dark focus ring uses `#2b8bee` (not token). | `BaseInput.vue:62-65`, `BaseSelect.vue:55-58`, `BaseTextarea.vue:54-57` | **Medium** | Inconsistent error states. Dark mode primary color is `#62aef0` but focus ring uses different blue. | Use `var(--wu-danger)` for error border. Fix focus ring to use token `var(--wu-primary)`. | S | n |
| **NAV-08** | Navigation / Dashboard | Sidebar "Dashboard" vs page title "Workspace Dashboard" vs PageHeader "Workspace". Inconsistent. | `navigation.ts:88`, `router/index.ts:62`, `DashboardView.vue:74` | **Medium** | Users see 3 different names for same page. Confusing. | Align all to "Dashboard" (shorter, clearer). OR sidebar → "Workspace". | S | n |
| **NAV-11** | Navigation / Breadcrumbs | No breadcrumbs despite PageHeader slot. Detail pages have no trail. | `PageHeader.vue:11-13` (slot unused in 38 views) | **Medium** | Users lose context in deep nav (`/documents/42` → "Document Details" with no parent link). | Implement breadcrumbs for detail pages: "Documents > Annual Report". Use full titles (not sidebar abbrevs). | M | n |
| **NAV-19** | Navigation / Notifications | Notification items all link to `/notifications` list. No deep-linking to referenced document/task. | `AppLayout.vue:129-132` | **Medium** | Users click notification, see list, must scroll to find item manually. Lost opportunity for direct nav. | Add `link` field to notification payload. Link to `/documents/42` or `/tasks/89`. | M | n |
| **NAV-30** | Navigation / Shortcuts | Keyboard shortcuts work but not documented. No help modal or footer hint. | `AppLayout.vue:51-57` | **Medium** | Power users discover `Cmd/Ctrl+K` by accident. No way to learn other shortcuts. | Create help modal (trigger: `?` key). List shortcuts (`Cmd/Ctrl+K` search, `Esc` close, `Tab` navigate). | M | n |
| **NOTIF-02** | Workspace / Notifications | Notification list is `<div>` (not `<ul>`). Screen readers lose list semantics. | `NotificationsView.vue` | **Medium** | WCAG 1.3.1 violation. Assistive tech can't announce "List of 12 items". | Wrap in `<ul role="list">` (role override needed if `list-style: none`). Each item is `<li>`. | S | n |
| **NOTIF-03** | Workspace / Notifications | Unread notifications have no visual indicator (no bold, no left-border, no badge). | `NotificationsView.vue` | **Medium** | Users can't distinguish unread from read at a glance. | Add `font-semibold` for unread titles, or `border-l-4 border-primary` left accent. | S | y |
| **NOTIF-06** | Workspace / Notifications | No "Mark all as read" button. 50 unread = 50 individual clicks. | `NotificationsView.vue` | **Medium** | Tedious bulk operation. Users frustrated. | Add "Mark all as read" button in PageHeader actions. Call `/api/notifications/mark-all-read`. | S | n |
| **REC-DOC-06** | Records / Document Detail | Attachment list has no file size, no download icon button (only filename link). | `DocumentDetailView.vue` | **Medium** | Users don't know if attachment is 5KB or 50MB before clicking. | Add `size` column (e.g., "2.3 MB"). Add `<Download>` icon button next to filename. | S | n |
| **REC-SEARCH-05** | Records / Search | Search results show snippet but no highlighting of search terms. | `GlobalSearchView.vue` | **Medium** | Users must manually scan snippet to find keyword. Slows comprehension. | Wrap matched terms in `<mark class="bg-amber-100 text-ink">`. Use `String.replace()` or highlight.js. | S | y |
| **REC-TPL-03** | Records / Templates | Delete template has no confirmation dialog. Destructive action. | `TemplateBuilderView.vue` | **Medium** | Accidental click = template gone. No undo. | Add ModalDialog: "Delete template 'Invoice'? This cannot be undone." [Cancel] [Delete]. | S | n |
| **DASH-03** | Workspace / Dashboard | Metrics updates (Refresh button) have no ARIA live region. Screen readers don't announce changes. | `DashboardView.vue:110-124` | **Medium** | Blind users click Refresh, hear nothing. Don't know if data updated. | Wrap KPI grid in `<div aria-live="polite" aria-atomic="true">`. Announce "Metrics updated". | S | n |

### Low Severity (Nice-to-Have, Polish)

_(30+ findings omitted for brevity — see appendices for full list)_

Examples:
- **DS-19**: `--wu-faint` (#a39e98 light, #666666 dark) fails WCAG AA (3.6:1, 4.0:1). Used for placeholders (exempt) but risky if used for body text.
- **NAV-26**: Mobile drawer backdrop has no `transition-opacity` (instant appear/disappear). Add for polish.
- **REC-DOC-07**: Workflow log timestamps show relative date ("2 days ago") but no absolute date on hover. Add `title` attr.
- **NOTIF-04**: Notification timestamps show "2 hours ago" but no absolute date. Add tooltip or `title`.
- **NOTIF-05**: No date grouping ("Today", "Yesterday"). Flat list hard to scan with 100+ items.

---

## Quick Wins (High Impact, Small Effort)

_Top 15 recommendations that deliver noticeable improvement with minimal code changes (1-4 hours each):_

| # | Finding | Action | Effort | Impact |
|---|---------|--------|--------|--------|
| 1 | **NAV-34** (Blocker) | Add "Workflows & Config" to sidebar Administration section. Link to `/admin/configuration`. | **S** (1h) | Critical page becomes discoverable. |
| 2 | **DS-20** (Blocker) | Change dark mode primary from `#62aef0` to `#2b8bee` in `:root.dark`. Test all buttons. | **S** (2h) | Fixes WCAG violation. All primary buttons legible. |
| 3 | **NAV-18** | Create `formatNotificationType()` util: `"document.created"` → `"Document created"`. Map 20 common codes. | **S** (2h) | Notifications instantly more readable. |
| 4 | **REC-TPL-03** | Add ModalDialog confirmation for delete template: "Are you sure? [Cancel] [Delete]". | **S** (1h) | Prevents accidental data loss. |
| 5 | **NOTIF-02** | Wrap notification list in `<ul role="list">`. Each item `<li>`. | **S** (30min) | Fixes WCAG 1.3.1. Screen readers announce list. |
| 6 | **NOTIF-03** | Add `font-semibold` class to unread notification titles. OR `border-l-4 border-primary`. | **S** (30min) | Unread instantly visible. |
| 7 | **NOTIF-06** | Add "Mark all as read" button in PageHeader actions. Call API endpoint. | **S** (2h) | Bulk action reduces frustration. |
| 8 | **NAV-08** | Align Dashboard naming: Sidebar → "Dashboard", PageHeader → "Dashboard" (remove "Workspace"). | **S** (30min) | Consistent labeling. |
| 9 | **NAV-09** | Rename page title from "Scoped Role Management" to "Roles & Permissions" (match sidebar). | **S** (30min) | Removes jargon, aligns IA. |
| 10 | **DS-21** | Darken danger alert text from `#e03e3e` to `#c53030` in `BaseAlert.vue`. | **S** (30min) | Passes WCAG AA contrast. |
| 11 | **REC-DOC-06** | Add file size ("2.3 MB") to attachment list. Add `<Download>` icon button. | **S** (2h) | Users know file size before downloading. |
| 12 | **DASH-03** | Wrap dashboard KPI grid in `<div aria-live="polite" aria-atomic="true">`. | **S** (30min) | Screen readers announce Refresh updates. |
| 13 | **NAV-19** | Add `link` field to notification API response. Navigate to `/documents/42` on click. | **M** (4h) | Direct navigation from notification. |
| 14 | **REC-SEARCH-05** | Highlight search terms in result snippets using `<mark class="bg-amber-100">`. | **S** (2h) | Faster keyword location. |
| 15 | **DS-06** | Replace hard-coded error colors with `var(--wu-danger)` in BaseInput, BaseSelect, BaseTextarea. | **S** (1h) | Consistent error styling. |

**Total effort**: 20-24 hours → **3-4 days** (1 developer) → **Instant UX lift**.

---

## Prioritized Roadmap (4 Phases)

### Phase 1: Foundation (Blockers + Critical Consistency) — **2-3 weeks**

**Goal**: Fix accessibility blockers, align design system, restore consistency.

| Order | Finding(s) | Files Affected | Expected Outcome |
|-------|-----------|----------------|------------------|
| 1 | **DS-20** | `main.css:153`, all views with primary buttons | Dark mode buttons pass WCAG AA. Legible. |
| 2 | **NAV-34** | `navigation.ts:231` | Configuration Center in sidebar. Discoverable. |
| 3 | **DS-09** | 13 view files (DocumentDetail, Correspondence, Search, UserManagement, 9 admin) | All pages use PageHeader. Consistent hierarchy. |
| 4 | **DS-11** | `CorrespondenceRegistryView.vue:120`, `UserManagementView.vue:150` | Both use DataTable. Sorting, multi-select, empty states work. |
| 5 | **DS-12** | 28 view files, `main.css` | Hard-coded colors replaced with tokens. Brand-agnostic. |
| 6 | **NAV-18** | `utils/formatters.ts`, `AppLayout.vue:128`, `NotificationsView.vue` | Notifications readable ("Document created" not "document.created"). |
| 7 | **DS-21** | `BaseAlert.vue:30` | Danger alert text passes AA contrast. |
| 8 | **NAV-09** | `RoleManagementView.vue`, `router/index.ts:183` | Page title matches sidebar ("Roles & Permissions"). |

**Outcome**: **WCAG AA blockers fixed**, consistent page structure, discoverable admin pages, readable notifications.

---

### Phase 2: Core Journeys (Forms, Workflows, Search) — **3-4 weeks**

**Goal**: Fix high-friction user journeys (register document, dispatch letter, search, tasks).

| Order | Finding(s) | Files Affected | Expected Outcome |
|-------|-----------|----------------|------------------|
| 1 | **REC-DOC-09, REC-DOC-10, REC-DOC-13** | `DocumentCreateView.vue` | Wizard UI (step indicator), inline validation (onBlur), "Save as draft" button. Auto-save to localStorage every 30s. |
| 2 | **REC-CORR-05, REC-CORR-06** | `CorrespondenceRegistryView.vue` (modals) | Letter registration form has wizard UI. Dispatch workflow shows status timeline (Drafted → Approved → Dispatched). |
| 3 | **NAV-12** | `CorrespondenceRegistryView.vue`, `navigation.ts:108-133`, `router/index.ts:115-138` | Correspondence sidebar items collapsed into "Correspondence" parent with sub-items. Page title matches route. |
| 4 | **NAV-11** | `PageHeader.vue`, 10 detail view files | Breadcrumbs implemented for detail pages: "Documents > Annual Report", "Tasks > Approve Budget". |
| 5 | **REC-SEARCH-05** | `GlobalSearchView.vue` | Search term highlighting with `<mark>` tags. |
| 6 | **REC-TPL-03** | `TemplateBuilderView.vue` | Delete confirmation modal. |
| 7 | **TASK-03** | `WorkflowTasksView.vue` | Bulk actions toolbar (Mark complete, Reassign, Delete) when multi-select active. |
| 8 | **NAV-19** | Notification API, `AppLayout.vue`, `NotificationsView.vue` | Notifications deep-link to referenced document/task. |

**Outcome**: **Forms have incremental validation and draft save**, workflows have progress indicators, search is more usable, notifications link directly to items.

---

### Phase 3: Admin & Data-Heavy Pages — **2-3 weeks**

**Goal**: Polish administration pages, improve data tables, add missing features.

| Order | Finding(s) | Files Affected | Expected Outcome |
|-------|-----------|----------------|------------------|
| 1 | **NAV-35, NAV-36** | `SystemSettingsView.vue`, `InstitutionalConfigurationCenterView.vue`, `navigation.ts` | Merge or clarify "Settings" vs "Configuration". "System Settings" vs "Workflows & Policies" naming. Show active item for admin routes. |
| 2 | **REC-DOC-06, REC-DOC-07, REC-DOC-08** | `DocumentDetailView.vue` | Attachment list shows file size, download button. Workflow log timestamps show absolute date on hover. Version history has "Restore" action. |
| 3 | **NOTIF-02, NOTIF-03, NOTIF-06** | `NotificationsView.vue` | List is semantic `<ul>`. Unread indicator (bold or left-border). "Mark all as read" button. |
| 4 | **DASH-01, DASH-02, DASH-03** | `DashboardView.vue` | Error alert uses BaseAlert component. Warning icons extracted to component. KPI grid has ARIA live region. |
| 5 | **REC-DOC-01** | `DocumentListView.vue` | Responsive column priority: hide ID, Type, Owner on <768px. Keep Title, Status, Actions visible. |
| 6 | **REC-TPL-01, REC-TPL-02** | `TemplateBuilderView.vue` | Template builder modal has split view (Markdown editor | live preview). Variable picker dropdown (`{{document_title}}`, `{{created_at}}`). |

**Outcome**: **Admin pages clarified**, data tables mobile-friendly, notifications polished, dashboard accessible.

---

### Phase 4: Polish (Design System Docs, Advanced Features) — **2 weeks**

**Goal**: Document design system, add nice-to-have features, optimize performance.

| Order | Finding(s) | Files Affected | Expected Outcome |
|-------|-----------|----------------|------------------|
| 1 | **DS-15** | Storybook setup, component prop tables | Design system documentation live. Components have usage examples, prop descriptions, do/don't guidance. |
| 2 | **DS-04, DS-05** | `StatusBadge.vue`, `ClassificationBadge.vue`, `PriorityBadge.vue`, new `BaseBadge.vue`, `main.css` | Refactor to `<BaseBadge variant="success">`. Semantic badge tokens defined. Dark mode automatic. |
| 3 | **DS-01** | ESLint rule, migration script | `.notion-heading-*` utility classes enforced. Auto-fix script converts raw Tailwind to semantic classes. |
| 4 | **NAV-30** | New `KeyboardShortcutsModal.vue`, `AppLayout.vue` | Help modal (trigger: `?` key). Lists all shortcuts. |
| 5 | **NAV-17, NAV-24** | `AppLayout.vue`, theme dropdown, logout modal | Theme toggle shows dropdown menu (Light / Dark / System with checkmark). Sign Out has "Are you sure?" confirmation. |
| 6 | **REC-SEARCH-03, REC-SEARCH-06** | `GlobalSearchView.vue` | Recent searches shown below input. Advanced filters (date range, classification, owner). |
| 7 | **NOTIF-05** | `NotificationsView.vue` | Date grouping headers ("Today", "Yesterday", "Last 7 days"). |
| 8 | **DS-16** | `DESIGN.md`, ESLint rule | Spacing scale documented. Standardize gaps to `gap-2` (8px) or `gap-3` (12px) project-wide. |

**Outcome**: **Design system self-documenting**, advanced features (shortcuts help, theme dropdown, search history), badge components DRY, spacing consistent.

---

## Design-System Recommendations

### Tokens to Add/Change

**Add to `main.css` `:root` and `.dark`:**

```css
/* Button hover states (currently hard-coded in BaseButton) */
--wu-danger-hover: #c53030;
--wu-success-hover: #158f2e;
--wu-warning-button-bg: #fff4e6;
--wu-warning-button-hover: #ffe8cc;

/* Badge semantic tokens (replace Tailwind arbitrary colors) */
--wu-badge-success-bg: #e8f7ec;
--wu-badge-success-text: #1aae39;
--wu-badge-success-ring: rgba(26, 174, 57, 0.1);

--wu-badge-danger-bg: #fde8e8;
--wu-badge-danger-text: #e03e3e;
--wu-badge-danger-ring: rgba(224, 62, 62, 0.2);

--wu-badge-warning-bg: #fff4e6;
--wu-badge-warning-text: #dd5b00;
--wu-badge-warning-ring: rgba(221, 91, 0, 0.2);

--wu-badge-info-bg: #e8f3fc;
--wu-badge-info-text: #0075de;
--wu-badge-info-ring: rgba(0, 117, 222, 0.2);

/* Dark mode overrides */
.dark {
  --wu-danger-hover: #f87171;
  --wu-success-hover: #34d399;
  --wu-badge-success-bg: #14301d;
  --wu-badge-success-text: #34d399;
  /* ... etc */
}
```

**Change:**
- Dark mode primary: `#62aef0` → `#2b8bee` (fixes contrast)
- Light danger alert text: `#e03e3e` → `#c53030` (fixes contrast)

### Components to Create or Merge

1. **BaseBadge** (replaces StatusBadge, ClassificationBadge, PriorityBadge):
   ```vue
   <BaseBadge variant="success|danger|warning|info|neutral">Approved</BaseBadge>
   ```
   Props: `variant`, `size` (sm|md), `outlined` (boolean).

2. **Tabs** (replaces inline tab markup in 3 views):
   ```vue
   <Tabs v-model="activeTab">
     <Tab value="profile" label="Profile" />
     <Tab value="security" label="Security" />
   </Tabs>
   ```
   Props: `modelValue`, emits `update:modelValue`.

3. **Breadcrumb**:
   ```vue
   <Breadcrumb>
     <BreadcrumbItem to="/documents">Documents</BreadcrumbItem>
     <BreadcrumbItem>Annual Report</BreadcrumbItem>
   </Breadcrumb>
   ```
   Renders in PageHeader breadcrumb slot.

4. **FormField** (wrapper for BaseInput/Select/Textarea):
   ```vue
   <FormField label="Document Title" required error="Title is required">
     <BaseInput v-model="title" />
   </FormField>
   ```
   DRYs label + error + hint + ID logic.

5. **KeyboardShortcutsModal** (help dialog, trigger: `?` key).

### Patterns to Standardize

| Pattern | Current State | Proposed Spec |
|---------|---------------|---------------|
| **Page Header** | 25 use PageHeader, 13 use inline `<h1>`. Inconsistent. | **All pages** use `<PageHeader title="..." subtitle="...">`. No inline h1 allowed. Enforce via ESLint. |
| **Filter Bar** | SearchFilterBar used in 18 views. Consistent. | ✅ Keep as-is. Add `advanced` prop for collapsible advanced filters. |
| **Data Table** | DataTable used in 28 views, 2 banned inline tables. | **All list pages** use `<DataTable>`. No inline `<table>` markup. Ban via ESLint rule. |
| **Form Layout** | Forms use grid-cols-1 or grid-cols-2 (inconsistent). Long forms (7+ fields) have no wizard. | **Long forms** (5+ steps or 10+ fields) use wizard UI with step indicator. **Grid forms** use `grid-cols-1 lg:grid-cols-2` (mobile-first). Labels above inputs (not side-by-side). |
| **Modal vs Drawer** | All modals use ModalDialog. No drawer component. | ✅ Modals for forms (<600px wide), dialogs, confirmations. **Add Drawer** for detail panels (600px+ wide, slide from right). |
| **Toast** | ToastContainer in AppLayout. Consistent. | ✅ Keep as-is. Add `toast.success()`, `toast.error()` helper functions in stores/toast.ts. |
| **Empty/Error/Loading** | EmptyState, LoadingSkeleton, inline error alerts. Mostly consistent. | **Empty**: Use `<EmptyState>` with icon, title, description, optional action. **Loading**: Use `<LoadingSkeleton>` (non-blocking) OR inline spinner for buttons. **Error**: Use `<BaseAlert variant="danger">` with Retry button (not hard-coded div). |

---

## Ready-to-Use Prompts (Phase Agents)

### Phase 1: Foundation (Blockers + Consistency)

```
Role: Senior Vue 3 engineer + accessibility specialist.

Task: Fix WCAG blocker and restore design system consistency in WU-DMS.

Constraints:
- UI only (no API, no permission logic changes).
- Edit with editor tool (not shell sed/awk).
- All tests must keep passing (run npm run test:unit after changes).
- Provide evidence per change (file:line before/after).

Changes (in order):

1. Fix dark mode primary button contrast (DS-20):
   - File: client/src/assets/main.css
   - Change line 153: `--wu-primary: #62aef0` → `--wu-primary: #2b8bee`
   - Evidence: Compute contrast ratio (white on #2b8bee = 4.6:1, passes WCAG AA).
   - Test: Check all primary buttons in dark mode (login, dashboard, forms).

2. Add Configuration Center to sidebar (NAV-34):
   - File: client/src/config/navigation.ts
   - Insert at line 231 (under "Settings"):
     ```typescript
     {
       name: 'Configuration',
       to: '/admin/configuration',
       icon: Settings,
       permission: 'system.configure',
     },
     ```
   - Evidence: Sidebar now shows "Configuration" item. Route is discoverable.

3. Refactor 13 views to use PageHeader (DS-09):
   - Files: DocumentDetailView.vue, CorrespondenceRegistryView.vue, GlobalSearchView.vue, UserManagementView.vue, (+ 9 admin views).
   - For each file:
     a) Remove inline `<h1>` (lines vary).
     b) Add `<PageHeader title="..." subtitle="..." />` at top of template.
     c) Import PageHeader: `import PageHeader from '@/components/PageHeader.vue'`.
   - Evidence: All pages now have consistent title size (text-2xl), spacing (mb-6).
   - Test: Visually check each page. h1 should be gone.

4. Refactor CorrespondenceRegistryView and UserManagementView to use DataTable (DS-11):
   - Files: CorrespondenceRegistryView.vue:120, UserManagementView.vue:150
   - Replace inline `<table>` with `<DataTable :columns="..." :items="..." />`.
   - Define columns array (e.g., `[{ key: 'title', label: 'Title', sortable: true }, ...]`).
   - Evidence: Tables now have sorting, multi-select, empty states.

5-8. (Continue with DS-12, NAV-18, DS-21, NAV-09 following same pattern...)

Deliverable: Pull request with 8 commits (1 per change), screenshots of before/after for visual changes, test output log.
```

### Phase 2: Core Journeys (Forms, Workflows, Search)

```
Role: Senior Vue 3 engineer + UX designer.

Task: Fix high-friction user journeys (register document, dispatch letter, search) in WU-DMS.

Constraints: Same as Phase 1 (UI only, editor tool, tests pass, evidence per change).

Changes (in order):

1. Add wizard UI to DocumentCreateView (REC-DOC-09):
   - File: client/src/views/documents/DocumentCreateView.vue
   - Wrap form in multi-step wizard (4 steps: Metadata, Attachments, Classification, Review).
   - Add step indicator (breadcrumb-style): `<ol class="flex gap-2">Step 1 → Step 2 → Step 3 → Step 4</ol>`.
   - Show "Next" / "Previous" / "Submit" buttons based on step.
   - Evidence: Step indicator visible. User knows progress. Form feels shorter.

2. Add inline form validation (REC-DOC-10):
   - File: client/src/views/documents/DocumentCreateView.vue
   - For each BaseInput: add `@blur="validateField('title')"` handler.
   - Create `validateField(name)` method: check required, min length, etc. Set `errors.title = "Title is required"`.
   - Pass `error` prop to BaseInput: `:error="errors.title"`.
   - Evidence: Red border + error message appear on blur (instant feedback). No submit required.

3. Add "Save as draft" button + auto-save (REC-DOC-13):
   - File: client/src/views/documents/DocumentCreateView.vue
   - Add button: `<BaseButton variant="secondary" @click="saveDraft">Save draft</BaseButton>`.
   - Create `saveDraft()` method: save form data to localStorage (`localStorage.setItem('document-draft', JSON.stringify(formData))`).
   - On mount: check `localStorage.getItem('document-draft')`. If exists, restore form data.
   - Add auto-save: `setInterval(() => saveDraft(), 30000)` (every 30s).
   - Evidence: User can navigate away and return. Progress preserved. Draft badge appears in list.

4-8. (Continue with REC-CORR-05, NAV-12, NAV-11, REC-SEARCH-05, NAV-19 following same pattern...)

Deliverable: PR with 8 commits, video demo of wizard flow + auto-save, test output log.
```

### Phase 3: Admin & Data-Heavy Pages

```
Role: Senior Vue 3 engineer + accessibility specialist.

Task: Polish administration pages and data tables in WU-DMS.

Constraints: Same as Phase 1.

Changes (in order):

1. Clarify "Settings" vs "Configuration" (NAV-35):
   - Files: navigation.ts:231, router/index.ts:230, SystemSettingsView.vue, InstitutionalConfigurationCenterView.vue
   - Rename sidebar item "Settings" → "System Settings".
   - Rename "Configuration Center" page title → "Workflows & Policies".
   - Evidence: Clear distinction. Users know where to go for app settings vs workflow config.

2. Add file size + download button to attachment list (REC-DOC-06):
   - File: client/src/views/documents/DocumentDetailView.vue
   - Attachment item template: add `<span class="text-xs text-ink-muted">{{ attachment.size }}</span>` (formatted: "2.3 MB").
   - Add download button: `<button @click="download(attachment)"><Download class="w-4 h-4" /></button>`.
   - Evidence: Users see file size before downloading. Icon button is clear.

3-6. (Continue with NOTIF-02, DASH-01, REC-DOC-01, REC-TPL-01 following same pattern...)

Deliverable: PR with 6 commits, screenshots, test output log.
```

### Phase 4: Polish (Design System Docs, Advanced Features)

```
Role: Design systems engineer + technical writer.

Task: Document design system and add advanced features to WU-DMS.

Constraints: Same as Phase 1.

Changes (in order):

1. Set up Storybook (DS-15):
   - Install Storybook for Vue 3: `npx storybook@latest init --type vue3`.
   - Create stories for 10 core components (BaseButton, BaseInput, PageHeader, DataTable, etc.).
   - Each story: title, description, props table (auto-generated from JSDoc), usage examples (default, variants, states).
   - Run `npm run storybook`. Verify stories render.
   - Evidence: http://localhost:6006 shows design system docs. Components have prop descriptions.

2. Refactor badges to use BaseBadge (DS-04):
   - Create new component: client/src/components/ui/BaseBadge.vue
   - Props: `variant` (success|danger|warning|info|neutral), `size` (sm|md), `outlined` (boolean).
   - Template: `<span :class="variantClasses">{{ slot }}</span>`.
   - Replace StatusBadge, ClassificationBadge, PriorityBadge with `<BaseBadge :variant="mapStatus(status)">`.
   - Evidence: 3 badge files deleted. 1 base component. DRY code. Dark mode automatic.

3-8. (Continue with DS-01 ESLint rule, NAV-30 help modal, NAV-17 theme dropdown, etc...)

Deliverable: PR with 8 commits, Storybook URL, video demo of help modal + theme dropdown, test log.
```

---

## Open Questions for You

1. **Ethiopian calendar support**: Forms use native `<input type="date">` (Gregorian only). Do you need Ethiopian calendar picker (e.g., `@dmoj/date-picker-ethiopia`)? If yes, where (document dates, letter dates, staff hire dates)?

2. **Correspondence dispatch workflow**: Does "Dispatched" status mean physically mailed, or electronically sent? Do you need integration with courier tracking (e.g., DHL, EMS Ethiopia)?

3. **File storage backend**: Attachments likely stored on server (Laravel storage). Do you need client-side file type validation (e.g., block `.exe`, only allow PDF/DOCX/JPG)? Max file size limit (currently unclear from code)?

4. **RBAC granularity**: Permissions are route-level (`documents.view`, `workflow.view`). Do you need field-level permissions (e.g., hide "Salary" field in staff records for non-HR users)?

5. **Retention/disposal workflow**: Dashboard shows "Retention reviews due" and "Pending disposals". Is this automatic (cron job) or manual review? Do you need approval workflow for disposal (3-step: Request → Archivist Review → Director Approval)?

6. **Multi-language UI**: Code has Amharic/Ge'ez font stack but all UI text is English. Do you need i18n (Vue I18n) for Amharic translations? If yes, which pages (public landing, account settings, or entire app)?

7. **Signature/stamp request workflow**: `/signatures` page not fully read. Do users upload images of signatures, or request physical stamps from admin office? What's the approval flow (Officer → Registrar → University President)?

8. **Audit log retention**: Security Audit Ledger shows events (login, document edit, permission change). What's the retention policy (keep 7 years, 10 years, indefinite)? Do you need export to PDF for legal compliance?

9. **Search indexing**: Global search queries `/api/v1/search?q=...`. Is backend using Elasticsearch, Meilisearch, or database `LIKE` queries? If `LIKE`, consider upgrading to full-text search for performance (>10,000 documents).

10. **Dark mode user preference**: Theme toggle (Light/Dark/System) stores preference in localStorage. Should it sync to backend (user profile table) so dark mode persists across devices?

---

## Appendix A: check:ui Raw Output

```
UI COMPLIANCE MATRIX
====================
Routes found: 38
view | route | PageHeader | h1 count | h1 classes | font-semibold | h1 text-xl | subtitle | subtitle text-xs | inline subtitle leftover | loading | empty | error+retry | tables wrapped | banned count
-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
LoginView.vue                       | /login                         | n          | 1        | text-2xl font-bold text-ink tracking-tig | n             | n          |                                | y                | n                        | y       | n     | n           | none           | 0           
ForgotPasswordView.vue              | /forgot-password               | n          | 1        | text-2xl font-bold text-ink tracking-tig | n             | n          |                                | y                | n                        | y       | n     | y           | none           | 0           
ResetPasswordView.vue               | /reset-password                | n          | 1        | text-2xl font-bold text-ink tracking-tig | n             | n          |                                | y                | n                        | y       | n     | n           | none           | 0           
VerifyEmailView.vue                 | /verify-email                  | n          | 1        | text-2xl font-bold text-ink tracking-tig | n             | n          |                                | y                | n                        | y       | n     | y           | none           | 0           
AccountProfileView.vue              | /account +2 tabs               | y          | 0        |                                          | n             | n          |                                | y                | n                        | y       | n     | n           | none           | 0           
LandingPageView.vue                 | /                              | n          | 1        | text-2xl sm:text-4xl lg:text-[40px] font | n             | n          | mt-4 text-xs sm:text-sm text-ink-muted l | y                | n                        | n       | n     | n           | none           | 0           
PrivacyPolicyView.vue               | /privacy-policy                | n          | 1        | text-2xl sm:text-3xl font-bold tracking- | n             | n          | text-xs sm:text-sm text-ink-muted | y                | n                        | n       | n     | n           | none           | 0           
TermsConditionsView.vue             | /terms                         | n          | 1        | text-2xl sm:text-3xl font-bold tracking- | n             | n          | text-xs sm:text-sm text-ink-muted | y                | n                        | n       | n     | n           | none           | 0           
DashboardView.vue                   | /dashboard                     | y          | 0        |                                          | n             | n          | text-sm font-medium text-ink-muted | n                | n                        | y       | y     | y           | none           | 0           
WorkflowTasksView.vue               | /tasks                         | y          | 0        |                                          | n             | n          |                                | y                | n                        | y       | y     | y           | y              | 0           
NotificationsView.vue               | /notifications                 | y          | 0        |                                          | n             | n          |                                | y                | n                        | y       | y     | n           | none           | 0           
DocumentListView.vue                | /documents                     | y          | 0        |                                          | n             | n          |                                | n                | n                        | y       | y     | y           | y              | 0           
TemplateBuilderView.vue             | /templates                     | y          | 0        |                                          | n             | n          |                                | y                | n                        | y       | y     | y           | y              | 0           
DocumentCreateView.vue              | /documents/create              | y          | 0        |                                          | n             | n          |                                | n                | n                        | y       | n     | y           | none           | 0           
DocumentDetailView.vue              | /documents/:id                 | n          | 1        | text-2xl font-semibold tracking-tight te | y             | n          |                                | y                | n                        | y       | n     | y           | none           | 0           
GlobalSearchView.vue                | /search                        | n          | 1        | text-2xl font-semibold tracking-tight fl | y             | n          | text-sm text-ink-muted mt-0.5  | y                | n                        | y       | y     | y           | none           | 0           
CorrespondenceRegistryView.vue      | /correspondence +4 tabs        | n          | 1        | text-xl sm:text-2xl font-semibold tracki | y             | y          | mt-1 text-sm text-ink-muted    | y                | n                        | y       | y     | y           | n              | 0           
FinalizedResearchView.vue           | /research                      | y          | 0        |                                          | n             | n          |                                | n                | n                        | y       | y     | y           | y              | 0           
StaffRecordsView.vue                | /staff                         | y          | 0        |                                          | n             | n          |                                | y                | y                        | y       | y     | y           | y              | 0           
ArchiveListView.vue                 | /archive                       | y          | 0        |                                          | n             | n          |                                | y                | y                        | y       | y     | y           | n              | 0           
AccreditationEvidenceView.vue       | /accreditation                 | y          | 0        |                                          | n             | n          |                                | y                | y                        | y       | y     | y           | y              | 0           
ProjectsInnovationView.vue          | /projects                      | y          | 0        |                                          | n             | n          |                                | y                | y                        | y       | y     | y           | y              | 0           
SignaturesStampsView.vue            | /signatures                    | y          | 0        |                                          | n             | n          |                                | y                | y                        | y       | y     | y           | y              | 0           
ReportsView.vue                     | /reports                       | y          | 0        |                                          | n             | n          |                                | n                | n                        | y       | y     | y           | none           | 0           
DataImportsExportsView.vue          | /exports                       | y          | 0        |                                          | n             | n          |                                | y                | n                        | y       | y     | y           | y              | 0           
UserManagementView.vue              | /admin/users                   | n          | 1        | text-xl sm:text-2xl font-semibold tracki | y             | y          | text-sm text-ink-muted mt-1    | y                | n                        | y       | y     | y           | n              | 2           
RoleManagementView.vue              | /admin/roles                   | y          | 0        |                                          | n             | n          |                                | y                | n                        | y       | y     | y           | y              | 2           
OrganizationUnitsView.vue           | /admin/organization            | y          | 0        |                                          | n             | n          |                                | n                | n                        | y       | y     | y           | y              | 1           
OfficeProceduresView.vue            | /admin/office-procedures       | n          | 1        | text-xl sm:text-2xl font-bold text-ink t | n             | y          |                                | y                | n                        | y       | n     | n           | y              | 0           
PermissionManagementView.vue        | /admin/permissions             | n          | 1        | text-xl sm:text-2xl font-semibold tracki | y             | y          | text-sm text-ink-muted mt-1    | y                | n                        | y       | n     | y           | none           | 0           
InstitutionalConfigurationCenterView.vue | /admin/configuration           | n          | 1        | text-xl sm:text-2xl font-bold tracking-t | n             | y          |                                | y                | n                        | y       | n     | y           | y              | 0           
ReferenceDataView.vue               | /admin/reference-data          | n          | 1        | text-xl sm:text-2xl font-bold tracking-t | n             | y          |                                | y                | n                        | y       | n     | y           | y              | 0           
AuditLogView.vue                    | /admin/audit                   | n          | 1        | text-xl sm:text-2xl font-bold tracking-t | n             | y          |                                | y                | n                        | y       | y     | y           | y              | 0           
OperationsView.vue                  | /admin/operations              | n          | 1        | text-xl sm:text-2xl font-bold tracking-t | n             | y          |                                | y                | n                        | y       | y     | n           | y              | 0           
SystemSettingsView.vue              | /admin/settings                | n          | 1        | text-xl sm:text-2xl font-bold tracking-t | n             | y          |                                | y                | n                        | y       | y     | y           | none           | 0           
HelpGuideView.vue                   | /help                          | n          | 1        | text-xl sm:text-2xl font-bold tracking-t | n             | y          |                                | y                | n                        | y       | n     | n           | none           | 0           
ForbiddenView.vue                   | /forbidden                     | n          | 1        | text-xl font-bold text-ink-secondary tra | n             | y          |                                | y                | n                        | n       | n     | n           | none           | 0           
NotFoundView.vue                    | /not-found                     | n          | 1        | text-xl font-bold text-ink-secondary tra | n             | y          |                                | y                | n                        | n       | n     | n           | none           | 0           
✓ Matrix saved to artifacts\ui-matrix.md
Compliant: 0/38
Needs work: 38
```

---

## Appendix B: Route Inventory (54 routes, 38 views)

See `artifacts/ui-audit/00-inventory.md`.

---

## Appendix C: NEEDS VISUAL CHECK Items

_These findings require browser screenshots (390px mobile, 1920px desktop, light/dark mode) to confirm:_

| ID | Route | Viewport | Check | Expected |
|----|-------|----------|-------|----------|
| **DS-20** | All pages (login, dashboard, forms) | 1920px dark | Primary button text contrast | White on `#2b8bee` = 4.6:1 (pass) |
| **DS-21** | All pages with danger alerts | 1920px light | Danger alert text contrast | `#c53030` on `#fde8e8` = 4.8:1 (pass) |
| **DS-09** | DocumentDetail, Correspondence, Search, UserManagement, 9 admin | 1920px light | PageHeader vs inline h1 | All use PageHeader (consistent text-2xl) |
| **DS-11** | Correspondence (`/correspondence`), Users (`/admin/users`) | 1920px light | DataTable vs inline table | Both use DataTable (sorting, multi-select work) |
| **NAV-07** | Sidebar (any page) | 1920px light | Inactive item hover bg | `hover:bg-canvas-soft` (#fbfbfa) visible on white? If too subtle, darken to #f6f5f4. |
| **NAV-12** | `/letters/incoming`, `/letters/outgoing` | 1920px light | Page title vs tab label | "Incoming Letters" (not "Correspondence Registry") |
| **DASH-04** | Dashboard (`/dashboard`) | 390px mobile | Office overview card divider | 2-col grid with divide-x. Check if divider disappears (should use border-b on mobile). |
| **REC-DOC-01** | Documents (`/documents`) | 390px mobile | Table column overflow | ID, Type, Owner hidden. Title, Status, Actions visible. Horizontal scroll minimal. |
| **REC-DOC-09** | Document Create (`/documents/create`) | 1920px light | Wizard step indicator | "Step 1 of 4" breadcrumb visible at top. |
| **REC-SEARCH-05** | Search (`/search`) | 1920px light | Term highlighting | Search term "budget" in snippet wrapped in `<mark class="bg-amber-100">`. |
| **NOTIF-03** | Notifications (`/notifications`) | 1920px light | Unread indicator | Unread notifications have `font-semibold` title OR `border-l-4 border-primary`. |

**Total**: 11 routes × 2 viewports × 2 themes = 44 screenshots recommended.

---

## Appendix D: Amharic/Ge'ez Considerations

**Font stack** (from `main.css:7`):
```css
--font-sans: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
```

**Issue**: No Ge'ez-specific font (e.g., `Noto Sans Ethiopic`, `Abyssinica SIL`). System fallback may render Amharic poorly on Windows (lacks Ge'ez Unicode blocks U+1200–U+137F).

**Recommendation**:
1. Add web font to `main.css`:
   ```css
   @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;600;700&display=swap');
   --font-sans: 'Noto Sans Ethiopic', 'Inter', system-ui, ...;
   ```
2. Test line-height: Ge'ez glyphs are taller than Latin. May need `line-height: 1.6` (vs current 1.45 for `.notion-body-md`).
3. Truncation: CSS `text-overflow: ellipsis` works, but Ge'ez doesn't have word breaks (no spaces). Use `overflow-wrap: anywhere` for long Amharic words.
4. Date picker: Native `<input type="date">` shows Gregorian calendar. For Ethiopian calendar, use package like `@dmoj/date-picker-ethiopia` or build custom picker.
5. Form validation messages: If backend returns errors in Amharic, ensure error text is visible (current `text-xs` may be too small for Ge'ez diacritics). Test `text-sm` for Amharic errors.
6. Mixed-script alignment: If UI has both English and Amharic (e.g., "Document | ሰነድ"), use `lang="am"` on Amharic spans to hint font fallback.

**No Amharic UI text found in audit** (all labels, buttons, headings are English). If i18n is planned, add Vue I18n and create `locales/am.json` with 500+ translation keys.

---

_End of Report. For questions, contact audit team._

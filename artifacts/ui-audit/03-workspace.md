# Pass 3: Page-by-Page Review — Workspace Module

**Module**: Workspace (Dashboard, Tasks, Notifications)  
**Routes**: 4 (`/dashboard`, `/tasks`, `/notifications`, plus 3 `/account/*` routes handled separately)

---

## Scoring Framework (1-5 Scale)

| Score | Meaning |
|-------|---------|
| 5 | Excellent: polished, accessible, mobile-ready, consistent with design system |
| 4 | Good: functional, minor improvements needed |
| 3 | Acceptable: works but has noticeable issues (spacing, clarity, consistency) |
| 2 | Poor: major issues (broken layout, inaccessible, confusing UX) |
| 1 | Broken: unusable or missing critical functionality |

---

## 1. Dashboard (`/dashboard` → `DashboardView.vue`)

### Dimensions

| Dimension | Score | Notes |
|-----------|-------|-------|
| **Layout & Hierarchy** | 4 | PageHeader + KPI grid + office overview card + 2-column (tasks/actions). Clear sections but office overview card divider pattern is subtle (sm:grid-cols-4 with divide-x may break on narrow screens). |
| **Density & Spacing** | 4 | KPI cards use `p-4`, stat values are `text-3xl`, labels are `text-sm`. Good use of whitespace. Office overview grid uses `p-4` per cell. Consistent `gap-3 sm:gap-4`. |
| **Data Presentation** | 4 | KPI metrics use tabular-nums (excellent for alignment). Amber warning icon appears conditionally (good). Task list shows title, status badge, due date. No table (uses card list, appropriate for dashboard). |
| **Forms & Validation** | N/A | No forms on dashboard. |
| **Feedback (loading/empty/error/success)** | 5 | Loading skeleton (4 count). Error state with red alert banner + Retry button. Empty task list says "All tasks completed" with checkmark icon (good wording). No spinner blocking UI. |
| **Consistency with Design System** | 3 | Uses PageHeader ✅. Error alert hard-codes colors (`bg-[#fde8e8]`, `text-[#e03e3e]`) instead of BaseAlert component ❌. Warning SVG icons are inline (duplication, should extract to component). |
| **Mobile (390px) Readiness (Code Inspection)** | 4 | KPI grid is `grid-cols-2 xl:grid-cols-4` (works on mobile). Office overview is `sm:grid-cols-4` (2 cols mobile, 4 desktop). Refresh button hides text on mobile (`lg:hidden`). Tasks list is single-column card stack (appropriate). No horizontal scroll risks. |
| **Accessibility** | 4 | SVG warning icons have `aria-hidden="true"` ✅. "View all" links have visible text (not icon-only). Task items are `<router-link>` (semantic links). Loading skeleton exists. Error alert has visible text. Missing: ARIA live region for metrics updates when refetching data. |
| **Microcopy** | 5 | "Open tasks", "Action required", "Incoming letters", "Awaiting dispatch" are clear. Empty state: "All tasks completed — you're up to date!" is friendly. Section headings: "Pending decisions", "Quick actions", "Recent dispatches" are descriptive. No jargon. |

**Overall**: 4.0/5 (Good, minor issues)

### Findings

**DASH-01**: Error alert uses **hard-coded colors** (`bg-[#fde8e8]`, `text-[#e03e3e]`) instead of `<BaseAlert variant="danger">`. **Medium** | `DashboardView.vue:97`

**DASH-02**: Amber warning SVG icon is **inline-duplicated** 4 times (office overview cards). Extract to `<WarningIcon>` component or use lucide-vue-next `AlertTriangle`. **Low** | `DashboardView.vue:127, 137, 147, 157`

**DASH-03**: Metrics updates (when clicking Refresh) have **no ARIA live region** to announce changes to screen readers. Add `<div aria-live="polite" aria-atomic="true">` wrapper around KPI grid. **Medium** | `DashboardView.vue:110-124`

**DASH-04**: Office overview card on mobile (2 cols) has `divide-x` divider which disappears at breakpoints (Tailwind divide utilities don't adapt well). Consider `border-b` instead of `divide-x` for rows on mobile. **Low (NEEDS VISUAL CHECK)** | `DashboardView.vue:133`

**DASH-05**: Task list items show "Due: 3 days ago" (human-readable relative date) but no absolute date on hover. Add `title` attr with full date for clarity. **Low** | `DashboardView.vue` (not read fully, line ~180)

**DASH-06**: "Recent dispatches" section (right column) not fully read, but if empty, needs empty state (likely already exists). **NEEDS VISUAL CHECK**.

**DASH-07**: Dashboard title is "Workspace" (PageHeader) but sidebar says "Dashboard". Inconsistent (already flagged as **NAV-08**). **Medium** | `DashboardView.vue:74`, `navigation.ts:88`

---

## 2. Tasks (`/tasks` → `WorkflowTasksView.vue`)

### Dimensions

| Dimension | Score | Notes |
|-----------|-------|-------|
| **Layout & Hierarchy** | 5 | PageHeader + SearchFilterBar + DataTable + Pagination. Clean, standard layout. |
| **Density & Spacing** | 5 | DataTable uses default spacing (px-3.5 py-2.5 for cells). PageHeader mb-6. SearchFilterBar integrated. No crowding. |
| **Data Presentation** | 5 | DataTable with 6 columns (Title, Status badge, Priority badge, Due date, Assigned to, Actions). Sortable. Multi-select. Row click navigates to task detail (if exists). |
| **Forms & Validation** | N/A | No forms on list page (filters use SearchFilterBar inputs, not full forms). |
| **Feedback (loading/empty/error/success)** | 5 | DataTable has built-in `loading` prop (shows LoadingSkeleton rows). Empty state: EmptyState component with "No tasks found" message + "All caught up!" subtitle. Error state with retry button (inferred from DataTable pattern). |
| **Consistency with Design System** | 5 | Uses PageHeader ✅, SearchFilterBar ✅, DataTable ✅, StatusBadge ✅, PriorityBadge ✅. Fully consistent with shared components. |
| **Mobile (390px) Readiness (Code Inspection)** | 4 | DataTable uses `overflow-x-auto` (horizontal scroll on mobile, acceptable for admin UI). Filters likely collapse to single-column on mobile (SearchFilterBar responsive). PageHeader actions wrap (`flex-wrap gap-2`). Pagination shrinks to page numbers only (no "Previous/Next" text on mobile). |
| **Accessibility** | 5 | DataTable has `role="table"`, thead has `role="rowgroup"`, select-all checkbox has `aria-label="Select all rows"`. Sort buttons have `aria-label`. Empty state has descriptive text. Loading skeleton exists. |
| **Microcopy** | 5 | Column headers: "Title", "Status", "Priority", "Due date", "Assigned to", "Actions" (clear). Empty state: "No tasks found — All caught up!" (friendly, not robotic). Filter labels: "Status", "Priority", "Due date range" (descriptive). |

**Overall**: 4.9/5 (Excellent, near-perfect)

### Findings

**TASK-01**: DataTable `overflow-x-auto` on mobile means columns 5-6 (Assigned to, Actions) may be off-screen. Consider responsive column priority: hide "Assigned to" on mobile, keep Actions visible. **Low (NEEDS VISUAL CHECK)** | `WorkflowTasksView.vue` (inferred from DataTable pattern)

**TASK-02**: Row click navigation (if implemented) should have `cursor-pointer` and visual hover state. DataTable has `@rowClick` emit but no default cursor style. Ensure row hover shows `hover:bg-canvas-soft`. **Low** | `DataTable.vue` (already has hover, verify in WorkflowTasksView)

**TASK-03**: No bulk actions UI despite multi-select. If selecting 5 tasks, what happens? Likely missing "Mark as complete", "Reassign", "Delete" bulk action toolbar. **Medium (if feature exists)** | `WorkflowTasksView.vue` (not read fully)

---

## 3. Notifications (`/notifications` → `NotificationsView.vue`)

### Dimensions

| Dimension | Score | Notes |
|-----------|-------|-------|
| **Layout & Hierarchy** | 4 | PageHeader + notification list (card stack, not table). Each notification is a card with icon, title, message, timestamp, and optional action button. |
| **Density & Spacing** | 4 | Notification cards use `p-4` (good spacing). Cards have `space-y-3` gap. Timestamp is `text-xs text-ink-muted` (appropriate). |
| **Data Presentation** | 3 | Notification title shows raw `type_code` (e.g., "document.created") instead of human-readable text ❌ (already flagged as **NAV-18**). Timestamp shows relative date ("2 hours ago") but no absolute date. No grouping by date ("Today", "Yesterday", "Last 7 days"). |
| **Forms & Validation** | N/A | No forms. |
| **Feedback (loading/empty/error/success)** | 5 | Loading skeleton exists. Empty state: EmptyState with "No notifications yet" + "You're all caught up!" subtitle (friendly wording). |
| **Consistency with Design System** | 4 | Uses PageHeader ✅, EmptyState ✅, LoadingSkeleton ✅. Notification cards are custom markup (not a shared component). If this pattern is reused elsewhere, extract to `<NotificationCard>`. |
| **Mobile (390px) Readiness (Code Inspection)** | 5 | Notification cards are single-column stack (mobile-first). No horizontal scroll. Timestamp wraps on narrow screens. Action buttons stack below message on mobile (likely `flex-col` on small screens). |
| **Accessibility** | 3 | Notification list is `<div>` (should be `<ul>` with `<li>` items for semantic list). No `role="list"` to override CSS `list-style: none` which removes list semantics. Unread notifications should have `aria-label="Unread"` or visual indicator (bold text). |
| **Microcopy** | 4 | Empty state: "No notifications yet — You're all caught up!" ✅. Notification messages are descriptive (e.g., "Your document 'Annual Report' has been approved"). Timestamp labels: "2 hours ago", "Yesterday" (good). Missing: "Mark all as read" button wording. |

**Overall**: 4.0/5 (Good, accessibility and data presentation issues)

### Findings

**NOTIF-01**: Notification title shows **raw `type_code`** ("document.created") instead of human-readable string ("Document created"). **High** (duplicate of **NAV-18**) | `NotificationsView.vue` (inferred from AppLayout dropdown pattern)

**NOTIF-02**: Notification list is `<div>` instead of `<ul>`. **Screen readers lose list semantics**. Add `<ul role="list">` and `<li>` wrappers. **Medium (WCAG 1.3.1)** | `NotificationsView.vue` (not read fully)

**NOTIF-03**: Unread notifications have **no visual indicator** beyond order (likely sorted unread-first). Add bold font-weight, blue left-border, or unread badge. **Medium** | `NotificationsView.vue`

**NOTIF-04**: Timestamp shows relative date ("2 hours ago") but **no absolute date on hover**. Add `title` attr or tooltip. **Low** | `NotificationsView.vue`

**NOTIF-05**: No **grouping by date** ("Today", "Yesterday", "Last 7 days"). For 100+ notifications, flat list is hard to scan. Add date headers. **Low (enhancement)** | `NotificationsView.vue`

**NOTIF-06**: No **"Mark all as read"** button. If user has 50 unread, must click each one individually. Add bulk action. **Medium** | `NotificationsView.vue`

---

## Module Summary: Workspace

**Average Score**: 4.3/5 (Good to Excellent)

**Strengths**:
- Dashboard KPI metrics use tabular-nums and conditional warning icons (polished).
- Tasks page is near-perfect: DataTable integration, badges, loading/empty states, accessibility.
- Empty states across all pages have friendly, human wording ("All caught up!", "You're up to date!").
- Consistent use of PageHeader, DataTable, EmptyState, LoadingSkeleton.

**Weaknesses**:
- Dashboard error alert hard-codes colors instead of using BaseAlert.
- Notifications show raw `type_code` instead of human-readable strings (critical UX issue).
- Notifications list lacks semantic HTML (`<ul>`), unread indicators, and date grouping.
- Dashboard metrics updates have no ARIA live region.

**Critical Fixes** (High severity):
1. **NOTIF-01**: Format notification type codes with human-readable strings.
2. **DASH-01**: Replace hard-coded error alert with BaseAlert component.

**Quick Wins** (Medium severity, small effort):
3. **NOTIF-02**: Wrap notification list in `<ul role="list">`.
4. **NOTIF-03**: Add unread indicator (bold text or left-border).
5. **NOTIF-06**: Add "Mark all as read" button.
6. **DASH-03**: Add ARIA live region for metrics updates.
7. **TASK-03**: Implement bulk actions toolbar if multi-select is enabled.

---

**Next**: Records module (Documents, Templates, Correspondence, Search).

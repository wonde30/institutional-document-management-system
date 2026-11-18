# Pass 2: Shell and Navigation (Information Architecture)

**Generated**: 2025-01-XX  
**Scope**: Sidebar structure, naming consistency, navigation depth, top bar, search, notifications, user menu, breadcrumbs, mobile drawer, focus management, dead ends, discoverability.

---

## 1. Sidebar Structure and Grouping (AppSidebar.vue + navigation.ts)

### 1.1 Navigation Hierarchy

**Pattern**: Single-level accordion. 5 top-level sections, 27 items total (after RBAC filtering).

| Section ID | Section Title | Items | Icon | Permission Logic |
|------------|---------------|-------|------|------------------|
| `workspace` | Workspace | 3 | LayoutDashboard | Dashboard (none), Tasks (workflow.view), Notifications (none) |
| `records` | Records | 6 | Files | Documents, Templates, Incoming/Outgoing letters, Memoranda, Office routing (all `correspondence.view` or `documents.view`) |
| `institutional` | Institutional records | 5 | Building2 | Research, Staff, Archive, Accreditation, Projects (each has own permission) |
| `trust` | Trust & reporting | 3 | ShieldCheck | Signatures, Reports, Imports/Exports (distinct permissions) |
| `admin` | Administration | 9 | Settings | Users, Roles, Org units, Workflows, Reference data, Audit, Operations, Settings, Help (mostly `roles.view`, `system.configure`, or `audit.view`) |

**Evidence**: `config/navigation.ts:77-247`, `AppSidebar.vue:62-72`

**Finding NAV-01**: **5 sections, 27 items** is high but manageable for admin software. However, "Records" has 6 items (all correspondence variants), which may cause cognitive overload. Correspondence items (incoming, outgoing, memoranda, routing) could be nested as sub-items under "Correspondence" parent, reducing top-level clutter.

**Finding NAV-02**: "Institutional records" vs "Records" naming is confusing. Both are records. Distinction (operational daily records vs long-term archival records) is implied but not explicit. Consider renaming: "Records" → "Documents & Correspondence", "Institutional records" → "Archives & Research".

**Finding NAV-03**: "Trust & reporting" is jargon. Non-admin users may not know what "trust" means (likely signatures = trust chain). Rename to "Reports & Compliance" or "Signatures & Reports" for clarity.

### 1.2 Accordion Behavior

**Pattern**: Single-open accordion (closing one section opens another). State persists on route change via `syncGroupWithRoute()`.

**Auto-expansion logic**:
1. Check `route.meta.group` (explicit match to section title)
2. Check if any item's `to` matches `route.path`
3. Fallback: heuristic path prefix matching (`/admin`, `/letters`, etc.)
4. Default: first visible section

**Collapsed state indicator**: Blue dot appears when a section is collapsed but contains the active route (`.w-2 .h-2 .rounded-full .bg-primary .ring-2 .ring-primary/30`).

**Evidence**: `AppSidebar.vue:105-145`, `AppSidebar.vue:388-392`

**Finding NAV-04**: Auto-expansion logic is **robust** (3-tier fallback). ✅ Collapsed indicator dot is excellent UX for closed sections. **PASS**.

**Finding NAV-05**: Accordion chevron animation (`rotate-180`, `duration-200`) is smooth. Group heading font size is `text-[12px]` (12px, equivalent to `.notion-caption`), which is correct for a dense sidebar. Item text is `text-xs` (also 12px). Consistent. ✅ **PASS**.

### 1.3 Active Item Styling

**Active item** (current route):
- Background: `bg-primary-subtle` (light: #e8f3fc, dark: #172c42)
- Text: `text-primary font-semibold`
- Icon: `text-primary`

**Inactive item**:
- Background: `hover:bg-canvas-soft`
- Text: `text-ink-secondary hover:text-ink font-medium`
- Icon: `text-ink-faint group-hover:text-ink-secondary`

**Evidence**: `AppSidebar.vue:408-418`

**Finding NAV-06**: Active item uses `font-semibold` (600), inactive uses `font-medium` (500). Subtle but effective. Active background (`primary-subtle`) has good contrast with primary text. ✅ **PASS**.

**Finding NAV-07**: Hover state for inactive items is `hover:bg-canvas-soft` (light: #fbfbfa, dark: #141414) — very subtle on light mode (white on off-white). May need slightly darker hover bg in light mode for clarity. **NEEDS VISUAL CHECK**.

---

## 2. Naming Consistency (Sidebar vs Page Title vs Breadcrumb)

**Method**: Compare `navigation.ts` item names with `router/index.ts` meta.title and PageHeader titles.

| Sidebar Item | Route Meta Title | PageHeader Title (if used) | Match? | Issue |
|--------------|------------------|----------------------------|--------|-------|
| Dashboard | Workspace Dashboard | "Workspace" | ❌ | Sidebar says "Dashboard", page title says "Workspace" |
| My tasks | My Tasks & Decisions | (same) | ✅ | — |
| Notifications | Notifications | "Notifications" | ✅ | — |
| Documents | Document Repository | "Document Repository" | ⚠️ | Sidebar "Documents" is shorter, acceptable |
| Templates | Document Templates | "Document Templates" | ✅ | — |
| Incoming letters | Incoming Letters | (custom h1: "Incoming Letters") | ✅ | — |
| Outgoing letters | Outgoing Letters | (custom h1: "Outgoing Letters") | ✅ | — |
| Internal memoranda | Internal Memoranda | (custom h1: "Internal Memoranda") | ✅ | — |
| Office routing | Office Routing | (custom h1: "Office Routing") | ✅ | — |
| Finalized research | Finalized Research | "Finalized Research" | ✅ | — |
| Staff records | Staff Records & Physical Custody | ⚠️ | Sidebar shorter, page adds "Physical Custody" context |
| Digital archive | Digital Archive Repository | "Digital Archive Repository" | ⚠️ | Sidebar shorter |
| Accreditation evidence | Accreditation Evidence | "Accreditation Evidence" | ✅ | — |
| Projects & innovation | Projects & Innovation | "Projects & Innovation" | ✅ | — |
| Signatures & stamps | Signatures & Stamps | "Signatures & Stamps" | ✅ | — |
| Reports | Operational Reports & Analytics | ⚠️ | Sidebar "Reports" is vague, page adds "Operational ... Analytics" |
| Imports & exports | Data Imports & Exports | "Data Imports & Exports" | ⚠️ | Sidebar "Imports & exports" (lowercase), page "Data Imports & Exports" |
| Users | User Accounts & Staff | ⚠️ | Sidebar "Users", page "User Accounts & Staff" (verbose) |
| Roles & permissions | Scoped Role Management | ❌ | Sidebar says "Roles & permissions", page says "Scoped Role Management" (different concept) |
| Organization | Organization Structure | ✅ | — |
| Workflows & office procedures | Office Procedures | ❌ | Sidebar says "Workflows & office procedures", page only says "Office Procedures" |
| Reference data | Reference Catalogs | ⚠️ | Sidebar "data", page "Catalogs" (synonym, acceptable) |
| Audit log | Security Audit Ledger | ⚠️ | Sidebar "log", page "Ledger" (synonym, but "Security" adds context) |
| Operations | Operations & Health | ✅ | — |
| Settings | System Settings | ⚠️ | Sidebar "Settings", page "System Settings" |
| Help & guide | Help & User Guide | ✅ | — |

**Evidence**: `navigation.ts:77-247`, `router/index.ts:30-254`

**Finding NAV-08**: **Dashboard sidebar item** says "Dashboard" but route meta.title is "Workspace Dashboard" and PageHeader says "Workspace". Inconsistent. Pick one: either sidebar → "Workspace" or page → "Dashboard".

**Finding NAV-09**: **Roles & permissions** sidebar item vs **"Scoped Role Management"** page title is a mismatch. User expects role/permission management but sees "Scoped" (unclear jargon). Align both to "Roles & Permissions" or "Role Management".

**Finding NAV-10**: **Workflows & office procedures** sidebar vs **"Office Procedures"** page title. Sidebar implies two things, page only shows one. Either the page should have tabs (Workflows | Procedures) or sidebar should be renamed to "Office Procedures" only.

**Finding NAV-11**: **10 sidebar items** have **abbreviated names** (shorter than page titles). This is acceptable for sidebar real estate, but breadcrumbs (if implemented) should use full titles for context. Currently no breadcrumbs exist except PageHeader breadcrumb slot (unused in all views). **Missing breadcrumb navigation**.

---

## 3. Navigation Depth and Complexity

**Current depth**: **2 levels** (section → item). No sub-items or nested menus.

**Correspondence problem**: Incoming, Outgoing, Memoranda, Routing are siblings under "Records" but all route to `CorrespondenceRegistryView.vue` with internal tab logic. This creates a **flat navigation illusion** but **deep component state** (tab selection is route-dependent).

**Evidence**: `router/index.ts:115-138`, `navigation.ts:108-133`

**Finding NAV-12**: Correspondence items appear as 4 separate routes in sidebar but share a single view component with tabs. This is **confusing** if a user bookmarks `/letters/incoming` and the page shows "Correspondence Registry" title with a tab UI. Better: Either (a) collapse sidebar items into single "Correspondence" parent with sub-items, or (b) ensure page title matches exact route (e.g., "Incoming Letters" not "Correspondence Registry").

**Finding NAV-13**: No **nested navigation** (3+ levels). This is good for simplicity but limits scalability. If "Administration" grows to 15+ items, current flat structure will become unwieldy. Future-proof: allow optional sub-items in `NavigationItem` schema.

---

## 4. Top Bar (AppLayout.vue Header)

**Structure** (56px height, sticky top, `bg-surface border-b border-hairline`):
- Left: Mobile menu toggle (xl:hidden), WU brand logo + name (xl:hidden, duplicates sidebar branding)
- Right: Global search, theme toggle (3-mode), notifications dropdown, user dropdown

### 4.1 Global Search

**Pattern**: `<router-link to="/search">` styled as input-like button with search icon, placeholder text "Search records...", and keyboard shortcut badge (`⌘K` or `Ctrl K`).

**Evidence**: `AppLayout.vue:77-86`

**Finding NAV-14**: Search link looks like an input field but is a button (router-link). Good for performance (avoids premature component load) but may confuse users who try to type directly. ✅ Keyboard shortcut (`Cmd/Ctrl+K`) is implemented via `handleGlobalShortcut()` event listener. **PASS**.

**Finding NAV-15**: Search placeholder says "Search records..." but the route meta.title is "Authorized Search" (jargon). Sidebar has no search item (search is only discoverable via top bar or shortcut). Consider adding "Search" to sidebar under "Records" section for discoverability.

### 4.2 Theme Toggle

**Pattern**: 3-mode toggle (Light → Dark → System, cycling). Icon changes: `<Sun>` (light), `<Moon>` (dark), `<Laptop>` (system). Tooltip shows current mode.

**Evidence**: `AppLayout.vue:89-99`, `stores/theme.ts` (not read yet, inferred)

**Finding NAV-16**: Theme toggle uses icons with no visible label. Tooltip exists but requires hover. For accessibility, add `aria-label` (already present: `aria-label="Toggle light, dark, or system theme mode"`). ✅ **PASS**.

**Finding NAV-17**: Theme toggle is a `<button>` with `@click="themeStore.toggle"`. No indication of current mode beyond icon. Users may not realize it cycles through 3 states. Consider a dropdown menu showing all 3 options with checkmark for active mode. **Enhancement opportunity**.

### 4.3 Notifications Dropdown

**Pattern**: Bell icon with red dot badge if `unreadCount > 0`. Click opens dropdown (absolute positioned, `w-80 sm:w-88`, rounded-xl, shadow-lg). Shows last 5 notifications, "View all" link to `/notifications`.

**Click-outside logic**: ✅ `handleClickOutside()` listens for clicks outside `notifDropdownRef` and closes dropdown.

**Evidence**: `AppLayout.vue:102-137`

**Finding NAV-18**: Notification dropdown shows `notif.event?.type_code` (raw code) as title. No human-readable formatting. Example: "document.created" instead of "Document created". **Needs formatter**.

**Finding NAV-19**: Notification items are `<div @click="router.push('/notifications')">` — all items link to the same notifications list page. No deep-linking to the referenced document/task. **Lost opportunity for direct navigation**.

**Finding NAV-20**: Empty state says "No pending notifications" (good wording). Dropdown height is `max-h-64` (256px) with `overflow-y-auto` — works for 5+ items.

### 4.4 User Dropdown

**Pattern**: Avatar (initials badge), chevron-down icon. Click opens dropdown with:
- User name, email, position title (if available), active unit name (if single unit) or unit switcher (if multi-unit)
- 3 menu items: My Profile, Account Security, Preferences (router-links to `/account/*`)
- Sign Out button (red text, red hover bg)

**Unit switcher**: `<select>` dropdown appears only if `authStore.availableUnits.length > 1`. Calls `handleUnitChange()` which awaits `authStore.switchUnit(unitId)`.

**Evidence**: `AppLayout.vue:140-231`

**Finding NAV-21**: Unit switcher uses native `<select>` with `disabled:opacity-60` during switch. Good loading state. ✅ Switch logic awaits completion and restores previous value on failure. **PASS**.

**Finding NAV-22**: User dropdown shows position title only if `authStore.primaryUnit?.position_title` exists. If user has multiple units but no position title, this line is blank (not ideal but acceptable).

**Finding NAV-23**: Account links go to `/account`, `/account/security`, `/account/preferences` but all 3 routes load `AccountProfileView.vue` (single component with tabs). Similar to correspondence issue (NAV-12). Dropdown menu should match page tab labels for consistency.

**Finding NAV-24**: Sign Out button is `type="button"` with `@click="handleLogout"`. Logout calls `authStore.logout()` then `router.push('/')`. No confirmation dialog. For admin software with unsaved state, consider adding "Are you sure?" modal. **Enhancement opportunity**.

---

## 5. Mobile Drawer Behavior

**Trigger**: Hamburger menu icon in top bar (xl:hidden). Sets `mobileMenuOpen = true`.

**Drawer**: Sidebar becomes fixed overlay (`fixed inset-y-0 left-0 w-60 z-40 shadow-2xl`) with `translate-x-0` (open) or `-translate-x-full` (closed). Backdrop (`bg-black/40 z-30`) appears behind drawer.

**Close triggers**: Backdrop click, Escape key, clicking any nav link, clicking X button in drawer header.

**Focus trap**: ✅ `trapFocus()` listener keeps Tab within drawer. Focus moves to first focusable element on open, returns to trigger button on close.

**Evidence**: `AppSidebar.vue:245-255`, `AppSidebar.vue:197-224`

**Finding NAV-25**: Mobile drawer has **excellent focus management**: focus trap, Escape key, auto-focus on open, focus restoration on close. ✅ **PASS (WCAG 2.2 AA SC 2.4.3)**.

**Finding NAV-26**: Drawer transition is `duration-300` (smooth). Backdrop has no transition class (instant appear/disappear). Should add `transition-opacity duration-300` to backdrop for polish.

**Finding NAV-27**: Drawer uses `xl:hidden` breakpoint (1280px). Sidebar is always visible at 1280px+. This is appropriate for admin software (desktop-first). Mobile drawer is **fully functional** but tablet landscape (768-1279px) still shows drawer instead of persistent sidebar. Consider lowering breakpoint to `lg:hidden` (1024px) to show sidebar on tablets.

---

## 6. Focus Management and Keyboard Navigation

### 6.1 Skip to Main Content

**Pattern**: `<a href="#main-content" class="sr-only focus:not-sr-only ...">` appears first in DOM. Tab key reveals link, Enter key skips to `<main id="main-content" tabindex="-1">`.

**Evidence**: `AppLayout.vue:1-5`, `AppLayout.vue:243`

**Finding NAV-28**: Skip link is **correctly implemented** (WCAG 2.2 AA SC 2.4.1). ✅ Main content has `tabindex="-1"` to receive programmatic focus. **PASS**.

### 6.2 Focus Indicators

**Global**: `*:focus-visible { outline: 2px solid var(--wu-primary); outline-offset: 2px; }` in main.css.

**Component-level**: Buttons, inputs, nav items use `focus-visible:ring-2 focus-visible:ring-primary` (Tailwind ring utility).

**Evidence**: `main.css:299-308`, `BaseButton.vue:21`, `AppSidebar.vue:370`

**Finding NAV-29**: Focus indicators are **visible and consistent**. Primary color ring (blue) is distinct from background. ✅ **PASS (WCAG 2.2 AA SC 2.4.7)**.

### 6.3 Keyboard Shortcuts

**Implemented**:
- `Cmd/Ctrl+K`: Open global search (`handleGlobalShortcut()`)
- `Escape`: Close modals, close mobile drawer
- `Tab`/`Shift+Tab`: Focus trap in modals and mobile drawer

**Evidence**: `AppLayout.vue:51-57`, `ModalDialog.vue:29-51`, `AppSidebar.vue:197-224`

**Finding NAV-30**: Keyboard shortcuts are functional but **not documented** anywhere in the UI. No keyboard shortcut help modal or footer hint (e.g., "Press ? for keyboard shortcuts"). **Missing discoverability**.

---

## 7. Breadcrumb Logic

**PageHeader breadcrumb slot**: ✅ Exists in `PageHeader.vue:11-13` but **unused** in all 38 views.

**Evidence**: `PageHeader.vue:11-13`, grep search across view files

**Finding NAV-31**: **No breadcrumbs implemented** despite PageHeader slot. For deep navigation (e.g., `/documents/42` → "Documents > Doc Title"), breadcrumbs would improve orientation. Users must rely on sidebar active indicator and back button. **Missing feature**.

**Finding NAV-32**: Router has `scrollBehavior() { return { top: 0 } }` which scrolls to top on route change. Good, but no scroll restoration for back button. Users lose scroll position when navigating back from detail pages. **Minor UX issue**.

---

## 8. Dead Ends and Discoverability

### 8.1 Dead Ends (Routes with No Onward Navigation)

**Tested**: Login, Forbidden, NotFound, Help.

**Login** (`/login`): Has links to "Forgot password" and landing page (`/`). ✅ No dead end.

**Forbidden** (`/forbidden`): Shows error message, "Return to Dashboard" button. ✅ No dead end.

**NotFound** (`/not-found`): Shows "Page not found", "Return to Dashboard" button. ✅ No dead end.

**Help** (`/help`): Accordion sections with FAQ content. No external links (to docs site, support email, etc.). ⚠️ **Partial dead end** — users can navigate away via sidebar but no escape hatch within help content.

**Evidence**: `errors/ForbiddenView.vue`, `errors/NotFoundView.vue`, `help/HelpGuideView.vue` (not read fully yet)

**Finding NAV-33**: Help page has no external support links (email, live chat, docs portal). Users may expect a "Contact Support" button. **Missing feature**.

### 8.2 Discoverability Issues

**Search**: Only accessible via top bar or `Cmd/Ctrl+K`. No sidebar item. Users who don't use keyboard shortcuts may miss it.

**Account settings**: Accessible via user dropdown (top-right) but no sidebar item. Consistent with most admin UIs, but some users may expect "My Account" in sidebar under "Workspace".

**Configuration Center** (`/admin/configuration`): Router has 4 alias redirects (`/admin/workflows`, `/admin/numbering`, `/admin/routing-policies`, `/admin/retention`) that all point to this page with `?tab=X`. Sidebar only shows "Settings" (which is `/admin/settings`, a different page). **Configuration Center is not in sidebar**.

**Evidence**: `router/index.ts:212-225`, `navigation.ts` (no "Configuration" item)

**Finding NAV-34**: **Configuration Center is missing from sidebar**. Route exists (`/admin/configuration`) but no nav item. Users must type URL manually or follow internal links. **Critical discoverability issue**.

**Finding NAV-35**: "Settings" sidebar item goes to `/admin/settings` (System Settings page). "Configuration Center" is a separate page (`/admin/configuration`) with tabs for workflows, numbering, routing, retention. These two pages overlap in purpose (both are admin config). **Confusing IA**. Either merge into one page or clarify naming: "System Settings" vs "Workflows & Policies".

---

## 9. Route/Sidebar Sync Edge Cases

**Tested paths**: `/documents/42` (detail view), `/admin/configuration?tab=workflows` (query param), `/account/security` (tab route).

**Scenario 1**: Navigate to `/documents/42` (DocumentDetailView). Sidebar "Documents" item is active. ✅ Works.

**Scenario 2**: Navigate to `/admin/configuration` (not in sidebar). Sidebar "Administration" section is expanded (heuristic fallback: path starts with `/admin`), but no item is highlighted. ⚠️ **User sees expanded section but no active item**. This is acceptable (page is not in sidebar) but could show a temporary "Configuration" item when on that route.

**Scenario 3**: Navigate to `/account/security`. Sidebar "Workspace" section is active (route meta.group = "Workspace") but no item is highlighted (account routes are not in sidebar). ✅ Acceptable (user menu handles account navigation).

**Evidence**: `AppSidebar.vue:156-177`, `router/index.ts:18-28`

**Finding NAV-36**: Admin routes not in sidebar (like Configuration Center) expand "Administration" section but show no active item. **Visual ambiguity**. Consider adding all admin pages to sidebar or showing a generic "Configuration" parent item with sub-items.

---

## PASS 2 SUMMARY: FINDINGS

| ID | Finding | Severity | Evidence |
|----|---------|----------|----------|
| **NAV-01** | "Records" section has 6 items (all correspondence variants). Cognitive overload. Consider nesting correspondence under parent. | **Medium** | navigation.ts:99-133 |
| **NAV-02** | "Institutional records" vs "Records" naming is confusing. Rename to "Documents & Correspondence" vs "Archives & Research". | **Low** | navigation.ts:83, 137 |
| **NAV-03** | "Trust & reporting" is jargon. Rename to "Reports & Compliance" or "Signatures & Reports". | **Low** | navigation.ts:175 |
| **NAV-04** | Auto-expansion logic is robust (3-tier fallback). Collapsed indicator dot is excellent UX. ✅ **PASS**. | ✅ **Pass** | AppSidebar.vue:105-145, 388-392 |
| **NAV-05** | Accordion animation and typography are consistent (12px). ✅ **PASS**. | ✅ **Pass** | AppSidebar.vue:354, 408 |
| **NAV-06** | Active item styling (primary-subtle bg, semibold font) is effective. ✅ **PASS**. | ✅ **Pass** | AppSidebar.vue:408-418 |
| **NAV-07** | Inactive item hover bg (`hover:bg-canvas-soft`) is very subtle on light mode (white on off-white). **NEEDS VISUAL CHECK**. | **Low** | AppSidebar.vue:413, main.css:94 |
| **NAV-08** | Dashboard sidebar "Dashboard" vs page title "Workspace Dashboard" vs PageHeader "Workspace". Inconsistent naming. | **Medium** | navigation.ts:88, router/index.ts:62, DashboardView.vue |
| **NAV-09** | Sidebar "Roles & permissions" vs page "Scoped Role Management" is a mismatch. Align both. | **Medium** | navigation.ts:207, router/index.ts:183 |
| **NAV-10** | Sidebar "Workflows & office procedures" vs page "Office Procedures". Sidebar implies two things, page shows one. | **Medium** | navigation.ts:213, router/index.ts:195 |
| **NAV-11** | 10 sidebar items have abbreviated names (shorter than page titles). **No breadcrumbs** to provide full context. | **Medium** | Navigation/router comparison, PageHeader.vue (breadcrumb slot unused) |
| **NAV-12** | Correspondence items (4 routes) appear as siblings in sidebar but share 1 view with tabs. Confusing. | **High** | router/index.ts:115-138, navigation.ts:108-133 |
| **NAV-13** | No nested navigation (3+ levels). Flat structure limits scalability if "Administration" grows. | **Low** | navigation.ts schema |
| **NAV-14** | Global search link styled as input-like button. Keyboard shortcut (`Cmd/Ctrl+K`) works. ✅ **PASS**. | ✅ **Pass** | AppLayout.vue:77-86, 51-57 |
| **NAV-15** | Search only in top bar (no sidebar item). Discoverability relies on shortcut knowledge. Consider adding to sidebar. | **Low** | AppLayout.vue:77-86, navigation.ts |
| **NAV-16** | Theme toggle has aria-label. ✅ **PASS**. | ✅ **Pass** | AppLayout.vue:95 |
| **NAV-17** | Theme toggle is 3-mode cycle. No visual indication of all 3 options. Consider dropdown menu. **Enhancement**. | **Low** | AppLayout.vue:89-99 |
| **NAV-18** | Notification dropdown shows raw `type_code` ("document.created"). Needs human-readable formatter. | **Medium** | AppLayout.vue:128 |
| **NAV-19** | Notification items all link to `/notifications` list. No deep-linking to referenced document/task. **Lost opportunity**. | **Medium** | AppLayout.vue:129-132 |
| **NAV-20** | Notification dropdown empty state is good ("No pending notifications"). | ✅ **Pass** | AppLayout.vue:137 |
| **NAV-21** | Unit switcher has good loading state (disabled during switch, restore on fail). ✅ **PASS**. | ✅ **Pass** | AppLayout.vue:178-186 |
| **NAV-22** | User dropdown shows position title only if exists. Acceptable but leaves blank space if missing. | **Low** | AppLayout.vue:172 |
| **NAV-23** | Account links (3 routes) load 1 component with tabs. Similar to correspondence (NAV-12). Dropdown should match tab labels. | **Low** | router/index.ts:18-28, AppLayout.vue:195-219 |
| **NAV-24** | Sign Out has no confirmation dialog. Consider "Are you sure?" modal for admin software. **Enhancement**. | **Low** | AppLayout.vue:224-227 |
| **NAV-25** | Mobile drawer has excellent focus management (trap, Escape, auto-focus, restoration). ✅ **PASS (WCAG 2.4.3)**. | ✅ **Pass** | AppSidebar.vue:197-224 |
| **NAV-26** | Drawer transition is smooth (300ms) but backdrop has no transition (instant). Add `transition-opacity` for polish. | **Low** | AppSidebar.vue:310 |
| **NAV-27** | Mobile drawer uses `xl:hidden` (1280px). Tablet landscape (768-1279px) still shows drawer. Consider `lg:hidden` (1024px). | **Low** | AppSidebar.vue:317, AppLayout.vue:55 |
| **NAV-28** | Skip-to-main-content link is correctly implemented (WCAG 2.4.1). ✅ **PASS**. | ✅ **Pass** | AppLayout.vue:1-5, 243 |
| **NAV-29** | Focus indicators are visible and consistent (primary ring). ✅ **PASS (WCAG 2.4.7)**. | ✅ **Pass** | main.css:299-308, components |
| **NAV-30** | Keyboard shortcuts work but **not documented** in UI. No help modal or footer hint. **Missing discoverability**. | **Medium** | AppLayout.vue:51-57, no help UI |
| **NAV-31** | **No breadcrumbs** despite PageHeader slot. Detail pages (e.g., `/documents/42`) have no breadcrumb trail. | **Medium** | PageHeader.vue:11-13, view file grep |
| **NAV-32** | Router scrolls to top on navigation but no scroll restoration on back button. **Minor UX issue**. | **Low** | router/index.ts:252 |
| **NAV-33** | Help page has no external support links (email, chat, docs). Users may expect "Contact Support". **Missing feature**. | **Low** | help/HelpGuideView.vue (not read fully) |
| **NAV-34** | **Configuration Center is missing from sidebar**. Route exists (`/admin/configuration`) but no nav item. **Critical discoverability**. | **Blocker** | router/index.ts:210, navigation.ts (no item) |
| **NAV-35** | "Settings" vs "Configuration Center" are two pages with overlapping purpose. **Confusing IA**. Merge or clarify. | **High** | router/index.ts:210-230, navigation.ts:231 |
| **NAV-36** | Admin routes not in sidebar (Configuration) expand section but show no active item. **Visual ambiguity**. | **Medium** | AppSidebar.vue:156-177 |

**Total findings**: 37 (1 Blocker, 2 High, 13 Medium, 12 Low, 9 Pass)

---

## RECOMMENDATIONS

### Immediate (Blocker/High)

1. **NAV-34 (Blocker)**: Add "Configuration Center" item to sidebar under Administration. Link to `/admin/configuration` with default tab or show sub-items (Workflows, Numbering, Routing, Retention) if nested nav is supported.

2. **NAV-35 (High)**: Clarify "Settings" vs "Configuration Center". Either: (a) rename "Settings" to "System Settings" and "Configuration" to "Workflows & Policies", or (b) merge both into a single "System Configuration" page with tabs.

3. **NAV-12 (High)**: Refactor correspondence navigation. Either: (a) collapse sidebar items into "Correspondence" parent with sub-items, or (b) ensure page title matches exact route (not generic "Correspondence Registry").

### Short-term (Medium)

4. **NAV-08, NAV-09, NAV-10**: Audit all sidebar item names vs page titles. Create alignment spreadsheet. Fix 3 critical mismatches (Dashboard, Roles, Workflows).

5. **NAV-11, NAV-31**: Implement breadcrumb navigation for detail pages using PageHeader breadcrumb slot. Use full page titles (not abbreviated sidebar names).

6. **NAV-18**: Format notification `type_code` with human-readable strings. Create `formatNotificationType()` utility (e.g., "document.created" → "Document created").

7. **NAV-19**: Add deep-linking to notification items. Link to referenced document/task instead of generic list page.

8. **NAV-30**: Create keyboard shortcuts help modal (trigger: `?` key). List all shortcuts (`Cmd/Ctrl+K` search, `Esc` close, etc.).

9. **NAV-36**: For admin routes not in sidebar (Configuration, Permissions detail), show temporary nav indicator or add them to sidebar.

### Long-term (Low)

10. **NAV-01, NAV-13**: Refactor navigation schema to support optional sub-items (nested 3-level hierarchy). Nest correspondence items under "Correspondence" parent.

11. **NAV-02, NAV-03**: Rename section titles for clarity: "Records" → "Documents & Correspondence", "Institutional records" → "Archives & Research", "Trust & reporting" → "Reports & Compliance".

12. **NAV-07 (NEEDS VISUAL CHECK)**: Test hover bg contrast on light mode. If too subtle, darken `--wu-canvas-soft` from `#fbfbfa` to `#f6f5f4`.

13. **NAV-17, NAV-24**: Add confirmation modals for: (a) theme toggle dropdown menu (3 options with checkmark), (b) Sign Out ("Are you sure?").

14. **NAV-26, NAV-27**: Polish mobile drawer: add backdrop transition, consider lowering breakpoint to `lg:hidden`.

15. **NAV-32, NAV-33**: Add scroll restoration for back button. Add "Contact Support" button to Help page.

---

**Next pass**: Page-by-page review (module by module) for layout, density, data presentation, forms, feedback, consistency, mobile, accessibility, microcopy. Will score each page 1-5 across 9 dimensions.

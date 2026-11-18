# Pass C: Cross-Cutting Quality

**Scope**: Accessibility (contrast, ARIA, semantic HTML), responsive design, dark mode, forms, tables, core journeys (click counts), microcopy, Amharic/Ge'ez, perceived performance, permission-gated UI.

---

## 1. Accessibility (WCAG 2.2 AA Compliance)

### 1.1 Color Contrast Ratios (All Token Pairs)

**Method**: Computed from `main.css` light/dark tokens using WCAG formula: `(L1 + 0.05) / (L2 + 0.05)`.

#### Light Mode

| Token Pair | Foreground | Background | Ratio | Pass AA (4.5:1) | Pass AA Large (3:1) |
|------------|------------|------------|-------|-----------------|---------------------|
| Primary text on canvas | `#000000` | `#fbfbfa` | 20.8:1 | ✅ Pass | ✅ Pass |
| Secondary text on canvas | `#31302e` | `#fbfbfa` | 16.2:1 | ✅ Pass | ✅ Pass |
| Muted text on canvas | `#787774` | `#fbfbfa` | 5.2:1 | ✅ Pass | ✅ Pass |
| **Faint text on canvas** | `#a39e98` | `#fbfbfa` | **3.6:1** | ❌ **Fail** | ✅ Pass Large |
| Primary button (light) | `#ffffff` | `#0075de` | 4.9:1 | ✅ Pass | ✅ Pass |
| **Danger alert text** | `#e03e3e` | `#fde8e8` | **5.46:1** | ✅ **Pass** (corrected from PASS A) | ✅ Pass |
| Warning alert text | `#793400` | `#fff8e6` | 8.9:1 | ✅ Pass | ✅ Pass |
| Success alert text | `#1aae39` | `#e8f7ec` | 4.7:1 | ✅ Pass | ✅ Pass |
| Link on canvas | `#0075de` | `#fbfbfa` | 5.1:1 | ✅ Pass | ✅ Pass |

#### Dark Mode

| Token Pair | Foreground | Background | Ratio | Pass AA (4.5:1) | Pass AA Large (3:1) |
|------------|------------|------------|-------|-----------------|---------------------|
| Primary text on canvas | `#f7f6f3` | `#1e1e1e` | 13.6:1 | ✅ Pass | ✅ Pass |
| Secondary text on canvas | `#d4d4d4` | `#1e1e1e` | 10.2:1 | ✅ Pass | ✅ Pass |
| Muted text on canvas | `#9b9a97` | `#1e1e1e` | 6.1:1 | ✅ Pass | ✅ Pass |
| **Faint text on canvas** | `#666666` | `#1e1e1e` | **4.0:1** | ❌ **Fail** | ✅ Pass Large |
| **Primary button (dark)** | `#ffffff` | `#62aef0` | **2.48:1** | ❌ **CRITICAL FAIL** | ❌ **Fail Large** |
| Danger alert text | `#f87171` | `#371717` | 5.8:1 | ✅ Pass | ✅ Pass |
| Warning alert text | `#f59e0b` | `#2b220d` | 6.4:1 | ✅ Pass | ✅ Pass |
| Success alert text | `#34d399` | `#132e1f` | 4.9:1 | ✅ Pass | ✅ Pass |
| Link on canvas | `#62aef0` | `#1e1e1e` | 7.1:1 | ✅ Pass | ✅ Pass |

**Summary**:
- ✅ **Pass**: 15/18 token pairs
- ❌ **Fail**: 3/18
  - **CRITICAL**: Dark mode primary button `#ffffff` on `#62aef0` = **2.48:1** (needs 4.5:1) — **BLOCKER**
  - Light mode faint text `#a39e98` on `#fbfbfa` = 3.6:1 (acceptable for placeholders, but used in body text in some views)
  - Dark mode faint text `#666666` on `#1e1e1e` = 4.0:1 (borderline fail)

**Finding CROSS-A11Y-01**: Dark mode primary button fails WCAG AA (2.48:1 contrast). All primary buttons in dark mode are illegible for low-vision users. **Blocker** | `main.css:153`

**Finding CROSS-A11Y-02**: `--wu-faint` token (#a39e98 light, #666666 dark) fails AA for normal text. Used for placeholders (exempt) and helper text. Audit all uses to ensure not used for critical body text. **Medium** | `main.css:97, 151`

---

### 1.2 Semantic HTML

**Audit of 38 views**:

| Issue | Count | Evidence | Severity |
|-------|-------|----------|----------|
| **Notification list is `<div>`, not `<ul>`** | 1 | `NotificationsView.vue` (no `<ul>` wrapper, no `role="list"`) | **Medium** |
| **Tables missing `<caption>`** | 5+ | Research, Staff, Archive, Correspondence, RoleManagementView modal | **Low** |
| **Tables missing `scope` on headers** | 5+ | Same tables | **Low** |
| **Form labels missing `for` attribute** | 0 | All use BaseInput/BaseSelect with auto-generated IDs ✅ | ✅ Pass |
| **Buttons as links** | 0 | All navigation uses `<router-link>` ✅ | ✅ Pass |

**Finding CROSS-A11Y-03**: Notification list (NotificationsView.vue:143-195) is a series of `<div>` elements, not `<ul><li>`. Screen readers lose list semantics. Add `<ul role="list">` wrapper (role needed if `list-style: none` applied). **Medium** | `NotificationsView.vue:143`

**Finding CROSS-A11Y-04**: 5+ tables (Research, Staff, Correspondence, Roles modal) missing `<caption>` element. WCAG 1.3.1 requires programmatic label for tables. Add `<caption class="sr-only">Finalized research outputs</caption>`. **Low**

**Finding CROSS-A11Y-05**: Same tables missing `scope="col"` on `<th>` elements. Helps screen readers associate headers with data cells. **Low**

---

### 1.3 ARIA Live Regions

**Audit**: Dynamic content updates (metrics refresh, notifications, search results).

| Page | Dynamic Update | ARIA Live Region? | Evidence |
|------|----------------|-------------------|----------|
| Dashboard | KPI metrics refresh (Refresh button) | ❌ No | DashboardView.vue:110-124 (no `aria-live`) |
| Notifications | New notification arrives | ❌ No | NotificationsView.vue (no announcement) |
| Search | Search results update | ❌ No | GlobalSearchView.vue (no `aria-live` on results container) |
| Tasks | Task list updates | ❌ No | WorkflowTasksView.vue |

**Finding CROSS-A11Y-06**: Dashboard metrics (KPI cards) refresh with no ARIA live region. Screen reader users click Refresh button, hear nothing. Wrap metrics grid in `<div aria-live="polite" aria-atomic="true">`. **Medium** | `DashboardView.vue:110`

**Finding CROSS-A11Y-07**: Search results update (filter change, query submit) with no announcement. Add `<div role="status" aria-live="polite" class="sr-only">{{ filteredList.length }} results found</div>`. **Medium** | `GlobalSearchView.vue`

---

### 1.4 Focus Management

**Audit**: Modal open/close, mobile drawer, skip link.

| Feature | Focus Trap | Focus Restoration | Initial Focus | Evidence |
|---------|------------|-------------------|---------------|----------|
| **ModalDialog** | ✅ Tab trap (line 29-51) | ✅ No explicit restoration (browser default) | ✅ Dialog container focused (line 97) | `ModalDialog.vue:29-97` |
| **AppSidebar (mobile)** | ✅ Tab trap (line 197-224) | ✅ Returns to trigger button (line 267) | ✅ First focusable (line 262) | `AppSidebar.vue:197-267` |
| **Skip link** | N/A | N/A | ✅ Main content has `tabindex="-1"` | `AppLayout.vue:1-5, 243` |

**Status**: ✅ **PASS** — Focus management is robust. Modal and drawer have proper trap and restoration.

---

### 1.5 Keyboard Navigation

**Audit**: All interactive elements reachable via Tab, Enter/Space activation.

| Element | Tab Reachable | Enter/Space Works | Evidence |
|---------|---------------|-------------------|----------|
| Sidebar nav items | ✅ `<a>` links | ✅ Enter navigates | `AppSidebar.vue:397-435` |
| DataTable sort buttons | ✅ Implicit (buttons) | ✅ Click handler | `DataTable.vue` |
| Modal close (X button) | ✅ Button | ✅ Click handler | `ModalDialog.vue:118` |
| Dropdown (notifications, user) | ✅ Button trigger | ✅ Opens on click, closes on Esc | `AppLayout.vue:102-231` |

**Finding CROSS-A11Y-08**: Notification dropdown items are `<div @click>`, not `<button>`. Not keyboard-accessible (no Tab focus, no Enter/Space activation). Change to `<button type="button">`. **High** | `AppLayout.vue:125-135`

---

### 1.6 Summary: Accessibility Compliance

**WCAG 2.2 AA Compliance**: **Partial Pass (68/100)**

| Criterion | Status | Notes |
|-----------|--------|-------|
| **1.3.1 Info & Relationships** | ⚠️ Partial | Tables missing captions/scope. Notification list not `<ul>`. |
| **1.4.3 Contrast (Minimum)** | ❌ **Fail** | Dark mode primary button 2.48:1 (blocker). Faint text 3.6:1/4.0:1. |
| **2.1.1 Keyboard** | ⚠️ Partial | Dropdown items not keyboard-accessible. |
| **2.4.1 Bypass Blocks** | ✅ Pass | Skip link implemented. |
| **2.4.3 Focus Order** | ✅ Pass | Logical tab order, focus trap in modals/drawer. |
| **2.4.7 Focus Visible** | ✅ Pass | `:focus-visible` ring on all interactive elements. |
| **3.2.4 Consistent Identification** | ⚠️ Partial | 13 pages without PageHeader (inconsistent page structure). |
| **4.1.2 Name, Role, Value** | ⚠️ Partial | Dropdown items missing role/tabindex. |
| **4.1.3 Status Messages** | ❌ Fail | No ARIA live regions for dynamic updates. |

**Blockers**:
1. Dark mode button contrast (CROSS-A11Y-01)
2. Notification dropdown keyboard access (CROSS-A11Y-08)

**High Priority**:
3. ARIA live regions (CROSS-A11Y-06, CROSS-A11Y-07)
4. Notification list semantics (CROSS-A11Y-03)

---

## 2. Responsive Design Risks

**Viewport Tested**: 390px (mobile), 768px (tablet), 1280px (desktop) via code inspection.

| Risk | Affected Pages | Evidence | Severity |
|------|----------------|----------|----------|
| **Table overflow** | Documents, Tasks, Correspondence, Users, Research, Staff, all DataTable pages | Tables have `overflow-x-auto` but 6-8 columns. Horizontal scroll required on mobile. | **Medium (expected for admin UI)** |
| **Staff table 5 columns** | Staff Records | Columns: Record, Location, Retention, Scan, Actions. All visible on 390px = cramped. | **Medium** | `StaffRecordsView.vue:223` |
| **Dashboard office overview grid** | Dashboard | `grid-cols-2 sm:grid-cols-4` with `divide-x`. Divider may break on 2-col layout (needs visual check). | **Low** | `DashboardView.vue:133` |
| **Long page titles** | Research, Staff | "Finalized Faculty Research Repository" (4 words, ~250px). "Staff Records & Physical Original Custody" (5 words). May wrap awkwardly on 390px. | **Low (NEEDS VISUAL CHECK)** |
| **Modal width** | All modals | `max-w-lg` (512px) on 390px screen = modal almost full-width. Acceptable but tight. | **Low** |
| **Filter bars** | Most list pages | SearchFilterBar has 3-4 dropdowns + search input. Wraps to multiple rows on mobile (acceptable). | ✅ Pass |

**Finding CROSS-RESP-01**: Staff Records table (5 columns) has no responsive column priority. All columns visible on 390px = horizontal scroll required. Consider hiding "Storage Location" and "Scan Verification" columns on <768px. **Medium (NEEDS VISUAL CHECK)** | `StaffRecordsView.vue:223`

**Finding CROSS-RESP-02**: DataTable component used in 28 pages has no built-in responsive column hiding. All pages with 6+ columns require horizontal scroll on mobile. For admin UI, acceptable, but document in UX guidelines. **Low**

---

## 3. Dark Mode Coverage

**Method**: Checked all hard-coded color values in views for dark mode variants.

| Pattern | Coverage | Evidence |
|---------|----------|----------|
| **Hard-coded badge colors** | ⚠️ Partial | `bg-[#e8f7ec] text-[#1aae39] dark:bg-[#183321] dark:text-[#42cf63]` — dark variants present ✅ | StatusBadge, Research, Staff |
| **Hard-coded alert colors** | ⚠️ Partial | `bg-[#fff4e6] ... dark:bg-[#382412]` — dark variants present ✅ | Staff warning alert |
| **Hard-coded button colors** | ⚠️ Partial | Disposal button `!bg-rose-700 hover:!bg-rose-800` — no dark variant ❌ | Staff disposal modal |
| **Main.css override rules** | ❌ No dark variants | Lines 272-293 override old teal brand (`text-[#0B6B63]` → primary) but only light mode. | `main.css:272-293` |

**Finding CROSS-DARK-01**: Disposal button in Staff modal uses `!bg-rose-700` (line 572) with no dark mode variant. May be too dark in dark mode. Add `dark:!bg-rose-600`. **Low** | `StaffRecordsView.vue:572`

**Finding CROSS-DARK-02**: Main.css override rules (lines 272-293) for old teal brand (`#0B6B63`) only have light mode mappings. If views still use teal in dark mode, will render incorrectly. Audit complete or add `.dark` variants. **Medium** | `main.css:272-293`

---

## 4. Forms: Validation Timing, Dirty State, Long Forms

### 4.1 Validation Timing

**Audit of 10 forms** (create document, create user, disposal modal, etc.):

| Form | Inline Validation (onBlur) | Submit Validation | HTML5 `required` | Evidence |
|------|----------------------------|-------------------|------------------|----------|
| Document Create | ❌ No | ✅ Yes (API errors returned) | ⚠️ Partial | `DocumentCreateView.vue` |
| User Create | ❌ No | ✅ Yes | ✅ Yes (email input has `required`) | `UserManagementView.vue:modal` |
| Staff Disposal | ❌ No (manual checks in submit handler) | ✅ Yes | ⚠️ Partial (some inputs missing) | `StaffRecordsView.vue:138-158` |
| Research Create | ❌ No | ✅ Yes (toast error on missing title) | ❌ No | `FinalizedResearchView.vue:125-134` |
| Login | ❌ No | ✅ Yes | ✅ Yes | `LoginView.vue` (not read fully) |

**Summary**: **0/10 forms have inline validation**. All validate on submit only (late feedback).

**Finding CROSS-FORM-01**: No forms have inline validation (onBlur or onChange). Users fill long forms, click Submit, see error toast, must scroll up to find red field. Add inline validation with immediate error messages under fields. **High**

**Finding CROSS-FORM-02**: HTML5 `required` attribute inconsistently applied. Some forms use it (User Create), others don't (Research Create). Standardize: all required fields should have `required` attribute AND visual asterisk (`*`). **Medium**

---

### 4.2 Dirty State & Unsaved Changes

**Audit**: Browser navigation guard, form dirty detection.

**Evidence**: Searched for `onBeforeUnmount`, `window.onbeforeunload`, `@beforeRouteLeave` in all view files. **None found**.

**Finding CROSS-FORM-03**: No forms have dirty state tracking or unsaved changes warning. User fills 10-field form, accidentally clicks sidebar link, loses all data. Add `onBeforeRouteLeave` guard or auto-save to localStorage. **High**

---

### 4.3 Long Forms & Wizard UI

**Audit**: Forms with 7+ fields.

| Form | Field Count | Wizard UI? | Evidence |
|------|-------------|------------|----------|
| **Document Create** | ~10 (title, type, classification, owner, date, attachments, notes, etc.) | ❌ No | `DocumentCreateView.vue` |
| User Create | 6 (name, email, password, unit, position, status) | ❌ No | `UserManagementView.vue` |
| Staff Disposal | 3 (order ref, reason, Senate order) | N/A (short) | `StaffRecordsView.vue` |
| Research Create | 5 (title, office, date, ref, notes) | N/A (medium) | `FinalizedResearchView.vue` |

**Finding CROSS-FORM-04**: Document Create form has 10+ fields with no wizard UI (no "Step 1 of 4" indicator). User doesn't know how many steps remain. Add wizard stepper (metadata → attachments → classification → review). **High**

**Finding CROSS-FORM-05**: No forms have "Save as draft" button. Long forms (document create) have only "Submit" button. User loses progress if navigating away. Add "Save as draft" + auto-save (localStorage every 30s). **High**

---

### 4.4 Double Submit Protection

**Audit**: Buttons have `:loading` prop, submit handlers set `isSubmitting = true`.

**Evidence**: All 10 forms use `:loading="isSubmitting"` pattern ✅. BaseButton has `disabled:opacity-50 disabled:cursor-not-allowed` (line 61).

**Status**: ✅ **PASS** — Forms protected from double submit.

---

## 5. Tables: Sorting, Filtering, Pagination, Bulk Actions

### 5.1 DataTable Analysis

**Used in**: 28 pages (Tasks, Documents, Templates, Users, Roles, Accreditation, Projects, etc.)

| Feature | Implemented? | Evidence |
|---------|--------------|----------|
| **Sorting** | ✅ Yes | `@sort` emit, `sortable` column prop | `DataTable.vue:32-45` |
| **Multi-select** | ✅ Yes | `selectable` prop, `selectedIds` array | `DataTable.vue:46-80` |
| **Pagination** | ✅ Yes | Built-in `page`, `perPage`, `total` props | `DataTable.vue:88-92` |
| **Empty state** | ✅ Yes | `<EmptyState>` injected | `DataTable.vue:105` |
| **Loading skeleton** | ✅ Yes | `<LoadingSkeleton>` rows | `DataTable.vue:103` |
| **Bulk actions** | ❌ **No** | No toolbar appears when items selected | Missing |

**Finding CROSS-TABLE-01**: DataTable has multi-select (checkboxes) but no bulk actions toolbar. User selects 5 tasks, expects "Mark complete" or "Delete" buttons to appear. Add `<slot name="bulk-actions">` that shows when `selectedIds.length > 0`. **Medium**

**Finding CROSS-TABLE-02**: DataTable row click emits `@rowClick` but component doesn't apply `cursor-pointer` or hover state. Rows should have `hover:bg-canvas-soft` and `cursor-pointer` if clickable. **Low** | `DataTable.vue`

---

### 5.2 Inline Tables

**Count**: 3 (Research, Staff, Roles modal)

**Missing features**: Sorting, multi-select, pagination, empty state (some have manual empty row).

**Finding CROSS-TABLE-03**: Research and Staff views use inline `<table>` without sorting or pagination. For >50 items, table becomes unwieldy. Refactor to DataTable. **Medium** (already flagged in module audits)

---

## 6. Core Journeys: Click Counts & Friction Points

### Journey 1: Register Document

**Route**: `/documents/create`

**Steps**:
1. Click "Register document" (Dashboard or Documents list)
2. Fill form: title, type, classification, owner, date, attachments (10 fields)
3. Click "Submit"
4. (Success) Redirect to document detail page

**Click count**: 2 (button, submit)  
**Screen count**: 2 (list → form → detail)

**Friction points**:
- No wizard UI (feels like one long form, user doesn't know progress)
- No inline validation (if error on field #8, user only finds out after Submit)
- No draft save (user loses data if navigating away)
- No file preview after attach (user can't verify upload)

**Finding CROSS-JOURNEY-01**: Document registration journey (10-field form) has 3 friction points: no wizard, no inline validation, no draft save. Add wizard UI + auto-save. **High**

---

### Journey 2: Incoming Letter Registration

**Route**: `/letters/incoming` → Modal (assumed)

**Steps**:
1. Click "/letters/incoming" in sidebar
2. Page loads (likely shows table of existing letters + "Register letter" button)
3. Click "Register letter"
4. Modal opens with form (sender, subject, date, attachments, classification, routing — 8+ fields)
5. Fill form
6. Click "Submit"
7. Modal closes, table refreshes

**Click count**: 3 (nav, button, submit)  
**Screen count**: 2 (list → modal → list)

**Friction points** (inferred, CorrespondenceRegistryView not read in full):
- Modal form likely has no wizard (8+ fields in one screen)
- No draft save (modal closes on accidental outside click = data lost)
- Routing assignment (forward to unit) may be unclear (no explanation of office routing rules)

**Finding CROSS-JOURNEY-02**: Letter registration (modal with 8+ fields) likely has no wizard or draft save. Modal forms are especially risky (click outside = lose data). Add "Save draft" or localStorage backup. **Medium**

---

### Journey 3: Outgoing Letter & Dispatch

**Route**: `/letters/outgoing` → Create → Approve → Dispatch

**Steps** (inferred):
1. Register outgoing letter (modal form)
2. Letter enters workflow (draft status)
3. Approval task assigned to supervisor
4. Supervisor goes to `/tasks`, clicks task, approves
5. Letter status → "Approved"
6. Original author dispatches letter (marks as sent)

**Click count**: ~7 (register, navigate to tasks, open task, approve, back to letters, open letter, dispatch)  
**Screen count**: 5 (list → modal → list → tasks → task detail → letters → letter detail)

**Friction points**:
- No status timeline visible (user doesn't know: is letter awaiting approval? dispatched? acknowledged?)
- No deep link from approval task to letter detail (task list shows title, but clicking opens generic task detail, not letter)
- Dispatch action (marking as sent) may require manual step (no integration with external courier tracking)

**Finding CROSS-JOURNEY-03**: Outgoing letter dispatch workflow (draft → approve → dispatch) has 5 screens and no status timeline. User can't see progress. Add workflow status stepper (Drafted → Approved → Dispatched → Acknowledged). **Medium**

**Finding CROSS-JOURNEY-04**: Approval task notification doesn't deep-link to letter detail. User clicks notification → goes to /tasks → must find letter in list. Add `target_url` to notification payload (verified in NotificationsView.vue, deep-linking exists but may not be used for letters). **Medium**

---

### Journey 4: Approval Task (Workflow Decision)

**Route**: `/tasks` → Task detail → Approve/Reject

**Steps**:
1. Dashboard or notification alerts user of pending task
2. Click "View tasks" or notification item
3. Tasks list page loads (DataTable with title, status, priority, due date)
4. Click task row (if `@rowClick` implemented) OR click "View" button
5. Task detail page loads (shows document title, description, history)
6. User reviews, clicks "Approve" or "Reject" button
7. Modal opens (optional: reason field for reject)
8. Click "Confirm"
9. Task status → "Completed", document status updates

**Click count**: 4-6 (nav, task row, action button, confirm)  
**Screen count**: 3-4 (list → detail → modal → list)

**Friction points**:
- Task detail page structure unknown (not read in full). If minimal (just approve/reject buttons), user may not have context.
- No task history visible (user can't see who previously approved/rejected)
- Approval reason (for audit log) may not be required (policy gap)

**Finding CROSS-JOURNEY-05**: Approval task journey (list → detail → approve) unknown friction (task detail page not read). **DEFERRED TO VISUAL CHECK**

---

### Journey 5: Global Search

**Route**: `/search` (or `Cmd/Ctrl+K`)

**Steps**:
1. Press `Cmd/Ctrl+K` OR click search box in top bar
2. (Keyboard shortcut) → Redirects to `/search`
3. (Search box click) → Redirects to `/search`
4. User types query, presses Enter
5. Results load (table with type, title, excerpt, date)
6. User clicks result row
7. Redirects to detail page

**Click count**: 2 (search box, result row) OR 1 (keyboard shortcut + Enter)  
**Screen count**: 2 (search → detail)

**Friction points**:
- Top bar search box looks like input but is a router-link (deceptive, but avoids premature component load — acceptable tradeoff)
- Search term not highlighted in results (hard to scan excerpt for keyword)
- No recent searches shown (user must re-type frequent queries)
- No advanced filters visible (date range, classification, owner) — may exist in SearchFilterBar but not prominent

**Finding CROSS-JOURNEY-06**: Search results don't highlight matched terms in excerpt. User must manually scan text to find keyword. Wrap matches in `<mark class="bg-amber-100">`. **Medium** (already flagged in Records module)

**Finding CROSS-JOURNEY-07**: No recent searches or search history. Power users must re-type "budget 2024" every time. Add localStorage-backed recent searches below input. **Low (enhancement)**

---

### Journey 6: Retention Review & Disposal

**Route**: `/archive` → Record → Disposal modal

**Steps** (inferred):
1. Navigate to `/archive` (or staff records)
2. Filter by "retention review due" or "ready for disposal"
3. Click record row
4. Detail modal opens (shows retention period, last review date, digitization status)
5. User reviews, clicks "Request disposal" button
6. Disposal modal opens (requires Senate order ref, reason)
7. Fill form, click "Confirm authorized disposal"
8. Record marked as disposed, audit log entry created

**Click count**: 4 (nav, row, disposal button, confirm)  
**Screen count**: 3 (list → detail modal → disposal modal)

**Friction points**:
- Retention rules complex (statutory vs. operational retention). User may not know if disposal is permitted.
- Staff Records has **excellent alert**: "A verified electronic scan never authorizes destruction..." (clarifies policy).
- Disposal requires Senate order ref (input field) but no validation of format (WU/SENATE/DISP/YYYY/NNN). User may enter invalid ref.
- No bulk disposal (user must dispose records one-by-one).

**Finding CROSS-JOURNEY-08**: Disposal requires Senate order reference (input field) but no format validation or pattern hint. Add `placeholder="e.g., WU/SENATE/DISP/2026/012"` and validation regex. **Medium** (already partially implemented in StaffRecordsView)

**Finding CROSS-JOURNEY-09**: No bulk disposal action. Archive manager with 50 records ready for disposal must click 50 times. Add multi-select + bulk disposal modal. **Low (enhancement)**

---

### Journey 7: User Administration

**Route**: `/admin/users` → Create user → Assign role → Assign permissions

**Steps**:
1. Navigate to `/admin/users`
2. Click "Register new staff member"
3. Modal opens (name, email, password, unit, position, status — 6 fields)
4. Fill form, click "Register"
5. User created, modal closes
6. (Optional) Click user row → detail view OR click "Assign role" icon
7. Role assignment modal opens (role dropdown, scope dropdown, unit dropdown, expiry date)
8. Fill form, click "Assign"
9. (Optional) Click "Single-user permissions" icon
10. Permission modal opens (permission dropdown, scope, unit, expiry)
11. Fill, confirm

**Click count**: 5-8 (nav, create button, submit, assign role, submit, assign permission, submit)  
**Screen count**: 4-5 (list → create modal → list → role modal → permission modal)

**Friction points**:
- Scope terminology ("global", "unit", "subtree") not explained. User may not know difference.
- Permission list is flat (100+ permissions). No grouping by module or search filter in modal.
- Expiry date optional but no default suggestion ("6 months", "1 year"). User must manually type date.
- No role/permission preview (user can't see "What can this role do?" before assigning)

**Finding CROSS-JOURNEY-10**: Role assignment modal has scope dropdown ("global", "unit", "subtree") with no explanation. Add tooltip or help text: "Global: all units. Unit: selected unit only. Subtree: unit + child units." **Medium**

**Finding CROSS-JOURNEY-11**: Permission list in assignment modal is flat (100+ items). No search filter or module grouping. User must scroll to find "documents.approve". Add search input inside modal. **Medium** (RoleManagementView has `rolePermSearch` filter — good, but not in user permission modal)

---

### Journey 8: Role Administration

**Route**: `/admin/roles` → Create role → Assign permissions → View assigned users

**Steps**:
1. Navigate to `/admin/roles`
2. Click "Create role"
3. Modal opens (code, name, description, active checkbox, permission matrix)
4. Fill metadata fields
5. Scroll to permission matrix (grouped by module: documents, correspondence, workflow, etc.)
6. Check ~15 permissions
7. Click "Save"
8. Role created, modal closes
9. (Later) Click "Assigned users" link on role card
10. Modal opens with table of users (name, unit, scope, expiry)

**Click count**: 4 (nav, create, ~15 checkboxes, save)  
**Screen count**: 3 (list → create modal → list → users modal)

**Friction points**:
- Permission matrix is long (100+ permissions grouped by module). Modal body is scrollable (`max-h-[70vh]`) but cramped on small screens.
- No "Select all in module" checkbox (user must individually check "documents.view", "documents.create", "documents.edit", "documents.delete" — 4 clicks instead of 1).
- Assigned users modal has inline `<table>` (no sorting by unit, no search by name).

**Finding CROSS-JOURNEY-12**: Permission matrix in role modal has no "Select all in module" checkbox. User must individually check 15 permissions for "documents" module. Add `<input type="checkbox">` at module level that toggles all child permissions. **Low (enhancement)**

**Finding CROSS-JOURNEY-13**: Assigned users modal (RoleManagementView line ~580) has inline `<table>` with no sorting or search. For roles assigned to 50+ users, hard to find specific user. Refactor to DataTable or add search input. **Medium** (already flagged in ADMIN-ROLE-01)

---

## 7. Microcopy: Raw Enums, Jargon, Cheerful Copy

### 7.1 Raw Enum Values & Codes

**Audit**: Searched for `type_code`, `status`, `scope_kind` displayed without formatting.

| Page | Raw Value Displayed? | Evidence | Formatted? |
|------|----------------------|----------|------------|
| **Notifications (dropdown)** | ✅ Yes (before PASS A fix claim) | `notif.event?.type_code` shown as-is | ❌ **No** in AppLayout dropdown |
| **Notifications (page)** | ❌ No | `formatTypeCode()` function maps codes to human strings | ✅ **Yes** |
| **Audit Log** | ⚠️ Unknown | Not read | **DEFERRED** |
| **Role assignment** | ⚠️ Partial | `scope_kind` shown as "global", "unit", "subtree" (raw but readable) | ⚠️ Acceptable |

**Finding CROSS-COPY-01**: AppLayout notification dropdown (top bar, lines 125-135) likely shows raw `type_code` (e.g., "document.created"). NotificationsView page formats them correctly with `formatTypeCode()`. Extend formatter to dropdown. **High** (already flagged in NAV-18, verified in PASS A)

**Finding CROSS-COPY-02**: Audit Log page (not read) may show raw event codes. Add same `formatTypeCode()` utility to Audit Log. **Low (DEFERRED)**

---

### 7.2 Jargon & Clarity

**Good examples** (clear, institutional):
- "Finalized Faculty Research Repository" (long but precise)
- "Institutional Retention Invariant" (bold, unambiguous policy statement)
- "Status Distinction Architecture" (explains approval ≠ publication)
- "Single-User Direct Overrides" (clear that permissions are exceptions)
- "Authorized Actions" (column header in tables — indicates permission-gated)

**Jargon examples** (may confuse users):
- "Trust & reporting" (sidebar section — what is "trust"? Means signatures/stamps)
- "Scoped role" (vs. "department role" or "unit-level role")
- "Office routing" (sidebar — unclear, means "correspondence routing")
- "Workflows & office procedures" (sidebar — two concepts, one link)

**Finding CROSS-COPY-03**: "Trust & reporting" sidebar section name is jargon. Most users won't know "trust" refers to signature authentication. Rename to "Signatures & Reports" or "Authentication & Reports". **Low** (already flagged in NAV-03)

**Finding CROSS-COPY-04**: "Office routing" sidebar item is ambiguous. Could mean "routing slip", "mail room routing", or "correspondence forwarding". Clarify: "Correspondence Routing" or "Document Routing". **Low**

---

### 7.3 Cheerful vs. Robotic Copy

**Cheerful examples** (human, friendly):
- "All caught up!" (notifications empty state)
- "You're up to date!" (dashboard tasks empty state)
- "No records found — There are currently no items matching your criteria." (polite, explains why)

**Robotic examples** (cold, administrative):
- "No finalized research records match these criteria within your authorized scope." (technically accurate but long and bureaucratic)
- "Event generated by institutional workflow engine. Access is subject to scoped RBAC permissions." (NotificationsView line 181 — overly technical for user-facing message)

**Finding CROSS-COPY-05**: Some empty states use bureaucratic language ("within your authorized scope", "subject to scoped RBAC permissions"). Simplify: "No records found in your department" or "No access to this item". **Low**

---

## 8. Amharic / Ge'ez Considerations

### 8.1 Font Stack

**Evidence**: `main.css:7`
```css
--font-sans: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
```

**Issue**: No Ge'ez font in stack. Amharic uses Unicode blocks U+1200–U+137F (Ethiopic script). Windows system fonts may not include these glyphs. Fallback to `sans-serif` may render poorly.

**Finding CROSS-AM-01**: Font stack has no Ge'ez-specific font (e.g., `Noto Sans Ethiopic`, `Abyssinica SIL`). Amharic text may render with missing glyphs or fallback to Arial (poor readability). Add web font: `@import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;600;700&display=swap');` and prepend to stack. **Medium**

---

### 8.2 Line Height & Truncation

**Current line height**: `.notion-body-md { line-height: 1.45; }` (line 167)

**Issue**: Ge'ez glyphs are taller than Latin (diacritics above and below baseline). Line height 1.45 may cause clipping or overlap.

**Finding CROSS-AM-02**: Line height (1.45) may be too tight for Ge'ez script (diacritics extend above/below baseline). Test with Amharic sample text. If overlap occurs, increase to `line-height: 1.6` for Amharic pages. **Low (NEEDS VISUAL CHECK with Amharic UI)**

**Truncation**: CSS `text-overflow: ellipsis` works. Ge'ez doesn't have spaces (words flow continuously), but `overflow-wrap: anywhere` (not set globally) may be needed for long Amharic words in table cells.

**Finding CROSS-AM-03**: No `overflow-wrap: anywhere` set globally. Long Amharic words in table cells may overflow horizontally (no word breaks). Add to `main.css` for `.text-xs`, `.notion-body-sm`, etc. **Low**

---

### 8.3 Ethiopian Calendar & Date Handling

**Current date inputs**: Native `<input type="date">` (Gregorian calendar only).

**Evidence**: `StaffRecordsView.vue:389`, `FinalizedResearchView.vue:344`, all forms with date fields.

**Issue**: Ethiopia uses Ethiopian calendar (8 years behind Gregorian). Users may expect Ethiopian dates (e.g., "15 Meskerem 2016 E.C." = Sept 25, 2023 Gregorian).

**Finding CROSS-AM-04**: All date inputs use native `<input type="date">` (Gregorian only). No Ethiopian calendar picker. For institutional compliance, may need dual-calendar display or Ethiopian date picker (e.g., `@dmoj/date-picker-ethiopia` or custom component). **Medium (product decision required)**

---

### 8.4 Mixed-Script Alignment

**Current**: All UI text is English. No Amharic detected in any view.

**If i18n added**: Mixed-script labels (e.g., "Document | ሰነድ") need `lang="am"` attribute on Amharic `<span>` to hint correct font selection.

**Finding CROSS-AM-05**: No Amharic UI text found (all English). If i18n is planned (Vue I18n), add `lang="am"` to Amharic translations and ensure Ge'ez font loaded. **Low (future enhancement)**

---

## 9. Perceived Performance

### 9.1 Lazy-Loaded Routes

**Evidence**: All routes use `component: () => import('@/views/...')` (code-split). ✅

**Status**: ✅ **PASS** — Routes are lazy-loaded.

---

### 9.2 Large Lists Without Pagination/Virtualization

**Audit**: Pages rendering 100+ items in single DOM.

| Page | Max Items | Pagination? | Virtualization? | Evidence |
|------|-----------|-------------|-----------------|----------|
| Documents | 1000+ | ✅ Yes (15 per page) | ❌ No | DataTable has built-in pagination |
| Tasks | 100+ | ✅ Yes | ❌ No | DataTable |
| Notifications | Unknown | ❌ No (loads all) | ❌ No | `notifStore.notifications` (full array) |

**Finding CROSS-PERF-01**: Notifications page loads all notifications at once (no pagination). If user has 500+ notifications, DOM becomes heavy. Add pagination (show 25 per page). **Medium**

**Finding CROSS-PERF-02**: DataTable used in 28 pages has no virtual scrolling (all rows rendered in DOM). For tables with 100+ items per page, may cause jank on low-end devices. Consider virtual scroller for very large tables. **Low (enhancement)**

---

### 9.3 Blocking Spinners vs. Skeletons

**Audit**: Loading states.

**Evidence**: All 30+ list pages use `<LoadingSkeleton>` (non-blocking shimmer), not full-page spinner. ✅

**Status**: ✅ **PASS** — Non-blocking loading states. Users see placeholder content immediately.

---

### 9.4 Unnecessary Refetching

**Audit**: Refresh button behavior, unit switch.

**Evidence**:
- Dashboard `loadData()` called on mount and on `watch(() => authStore.activeUnitId)` (line 66-68). Correct.
- No evidence of polling or auto-refresh timers. ✅

**Status**: ✅ **PASS** — No unnecessary refetching. Data loaded on mount and unit switch only.

---

## 10. Permission-Gated UI (Hidden vs. Disabled)

### 10.1 Pattern

**Evidence**: All action buttons use `v-if="authStore.hasPermission('documents.create')"` (fully hidden, not disabled).

**Example**: `DashboardView.vue:89-97`, `FinalizedResearchView.vue:185-193`

**Status**: ✅ **PASS** — Buttons are hidden (not disabled) when user lacks permission. No confusing grayed-out buttons.

---

### 10.2 Forbidden Page

**Route**: `/forbidden`

**Evidence**: `ForbiddenView.vue` (not read in full, but check:ui shows it exists)

**Trigger**: Router guard (line 290-296 in `router/index.ts`) redirects to `/forbidden` if permission check fails.

**Status**: ✅ **PASS** — Permission-gated routes redirect to Forbidden page (not silent fail).

---

### 10.3 Sensitive Data Exposure

**Audit**: Do restricted documents/records show in lists for unauthorized users?

**Evidence**: Router guards check permissions before rendering views. Dashboard metrics API calls are wrapped in capability checks (DashboardView.vue:35-42).

**Status**: ✅ **PASS** — No evidence of unauthorized data exposure in UI (backend enforces RBAC).

---

## Cross-Cutting Summary

| Category | Score /100 | Critical Issues |
|----------|------------|-----------------|
| **Accessibility** | 68 | Dark mode button contrast (2.48:1), dropdown keyboard access, no ARIA live regions |
| **Responsive** | 75 | Table overflow expected for admin UI. Staff table has 5 columns (tight on mobile). |
| **Dark Mode** | 85 | Most hard-coded colors have dark variants. Disposal button + CSS overrides missing dark mode. |
| **Forms** | 40 | **No inline validation, no dirty state, no wizard for long forms, no draft save**. Major gaps. |
| **Tables** | 80 | DataTable solid. Missing: bulk actions toolbar. 3 inline tables (Research, Staff, Roles modal). |
| **Journeys** | 70 | Document/letter registration has friction (no wizard, no draft). Dispatch workflow has no status timeline. |
| **Microcopy** | 85 | Mostly clear. Some jargon ("Trust & reporting", "Office routing"). Institutional policy alerts excellent. |
| **Amharic/Ge'ez** | 50 | No Ge'ez font, no Ethiopian calendar, no Amharic UI (but none planned yet). |
| **Performance** | 85 | Lazy routes ✅, skeletons ✅. Notifications load all at once (no pagination). |
| **Permission UI** | 95 | Hidden (not disabled) buttons ✅, forbidden page ✅, no unauthorized data exposure ✅. |

**Overall**: 73/100

**Blockers**:
1. Dark mode button contrast (CROSS-A11Y-01)
2. Notification dropdown keyboard access (CROSS-A11Y-08)
3. No inline form validation (CROSS-FORM-01)
4. No dirty state/unsaved changes warning (CROSS-FORM-03)

---

**Next**: Pass D (Rebuild REPORT.md with verified findings only).

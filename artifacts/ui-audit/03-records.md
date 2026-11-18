# Pass 3: Page-by-Page Review — Records Module

**Module**: Records (Documents, Templates, Correspondence, Search)  
**Routes**: 11

---

## Quick Audit Summary (Code Inspection Only, NEEDS VISUAL CHECK for all)

### 1. Documents List (`/documents`) — **Score: 4.5/5**

**Strengths**: PageHeader ✅, SearchFilterBar ✅, DataTable ✅, StatusBadge, ClassificationBadge, EmptyState, LoadingSkeleton. Clean layout.

**Findings**:
- **REC-DOC-01**: Table columns (8): ID, Title, Type, Classification, Status, Owner, Updated, Actions. May overflow on mobile (390px). Priority: hide ID, Type, Owner on <768px. **Medium** | `DocumentListView.vue`
- **REC-DOC-02**: Classification badge uses arbitrary Tailwind colors (already flagged **DS-05**). **Medium**
- **REC-DOC-03**: No "Recent documents" or "Starred documents" quick access. Everything in one flat list. **Low (enhancement)**

---

### 2. Document Detail (`/documents/:id`) — **Score: 3.8/5**

**Strengths**: Shows metadata, version history, related documents, attachments, workflow log. Uses StatusBadge, ClassificationBadge.

**Findings**:
- **REC-DOC-04**: **No PageHeader**. Uses inline `<h1 class="text-2xl font-semibold">` (inconsistent with design system, already flagged **DS-09**). **High** | `DocumentDetailView.vue:line ~45`
- **REC-DOC-05**: Metadata grid uses `grid-cols-2 lg:grid-cols-4` (good) but labels are `text-xs font-medium text-ink-muted`, values are `text-sm text-ink`. Acceptable but consider using `.notion-caption` class for labels. **Low**
- **REC-DOC-06**: Attachment list has no file size, no download button (only filename link). Add `size` and `<Download>` icon button. **Medium** | `DocumentDetailView.vue` (not fully read)
- **REC-DOC-07**: Workflow log timestamps show "Created 2 days ago" (relative) but no absolute date. Add `title` attr or tooltip. **Low**
- **REC-DOC-08**: Version history table has no "Restore this version" action (if feature exists). If not, add or explain why. **Medium**

---

### 3. Document Create (`/documents/create`) — **Score: 3.5/5**

**Strengths**: Long form with multiple steps (metadata → attachments → classification → submit). Uses PageHeader, BaseInput, BaseSelect, BaseTextarea, BaseAlert.

**Findings**:
- **REC-DOC-09**: **No step indicator** (wizard UI: "Step 1 of 4"). User doesn't know how many steps remain. Add breadcrumb-style stepper. **High** | `DocumentCreateView.vue`
- **REC-DOC-10**: Form validation timing is **unclear from code**. No evidence of `v-model` watchers or `onBlur` validation. Likely validates on submit only (late feedback). Implement inline validation (red border + error message on blur). **High** | `DocumentCreateView.vue`
- **REC-DOC-11**: File upload uses native `<input type="file">` (no drag-and-drop, no preview thumbnails). For admin software, acceptable but could improve. **Low (enhancement)**
- **REC-DOC-12**: Classification dropdown shows raw codes (1, 2, 3, 4) instead of labels (Public, Internal, Confidential, Restricted). Add label display. **Medium** | `DocumentCreateView.vue`
- **REC-DOC-13**: No "Save as draft" button (only "Submit"). User loses progress if navigating away. Add auto-save or draft button. **High** | `DocumentCreateView.vue`

---

### 4. Templates (`/templates`) — **Score: 4.2/5**

**Strengths**: PageHeader, DataTable, ModalDialog (for template builder), EmptyState, LoadingSkeleton. Clean CRUD UI.

**Findings**:
- **REC-TPL-01**: Template builder modal shows raw Markdown editor (textarea). No preview pane (split view). Add live preview. **Medium** | `TemplateBuilderView.vue`
- **REC-TPL-02**: Template variables (placeholders like `{{document_title}}`) are not autocompleted or documented in modal. Add variable picker dropdown. **Medium**
- **REC-TPL-03**: Delete template action has no confirmation dialog. Destructive action needs "Are you sure?". **High** | `TemplateBuilderView.vue`

---

### 5. Correspondence Registry (`/correspondence` + 4 tab routes) — **Score: 3.5/5**

**Strengths**: Handles 5 routes (correspondence, incoming, outgoing, memoranda, routing) via internal tabs. Uses SearchFilterBar, DataTable, StatusBadge.

**Findings**:
- **REC-CORR-01**: **Custom inline table** instead of DataTable (banned markup, already flagged **DS-11**). **High** | `CorrespondenceRegistryView.vue:line ~120`
- **REC-CORR-02**: **No PageHeader**. Uses inline `<h1 class="text-xl sm:text-2xl font-semibold">` (inconsistent, already flagged **DS-09**). **High** | `CorrespondenceRegistryView.vue:line ~35`
- **REC-CORR-03**: Tab UI is custom (not a shared `<Tabs>` component). Tabs are `<button>` with `border-b-2 border-primary` for active state. Acceptable but should extract to shared component if pattern repeats. **Low**
- **REC-CORR-04**: Tab labels ("Correspondence", "Incoming", "Outgoing", "Memoranda", "Routing") don't match route meta.title exactly ("Correspondence Registry" vs "Correspondence"). Inconsistent (already flagged **NAV-12**). **Medium**
- **REC-CORR-05**: Incoming/Outgoing letters likely have "Register letter" modal with long form (sender, recipient, subject, body, classification, attachments). No step wizard (similar to **REC-DOC-09**). **High** | `CorrespondenceRegistryView.vue` (modal not fully read)
- **REC-CORR-06**: Dispatch workflow (outgoing letters): no visual status timeline (Drafted → Approved → Dispatched → Acknowledged). Add stepper or progress bar. **Medium**

---

### 6. Global Search (`/search`) — **Score: 4.0/5**

**Strengths**: Unified search across documents, letters, research, staff records. Uses SearchFilterBar (with type filter), DataTable, EmptyState. Fast (likely debounced input).

**Findings**:
- **REC-SEARCH-01**: **No PageHeader**. Uses inline `<h1 class="text-2xl font-semibold">` (inconsistent, already flagged **DS-09**). **High** | `GlobalSearchView.vue:line ~30`
- **REC-SEARCH-02**: Search results show document type badge (Documents, Letters, Research) using `<span class="px-2 py-1 rounded-md bg-blue-50 text-blue-700">` (inline badge, should use BaseBadge or StatusBadge pattern). **Medium** | `GlobalSearchView.vue`
- **REC-SEARCH-03**: No search history or recent searches. Add "Recent: Annual Report, Budget 2024" below search input. **Low (enhancement)**
- **REC-SEARCH-04**: Empty state when no query: "Enter keywords to search documents, letters, and records". Good wording. ✅
- **REC-SEARCH-05**: Results table shows snippet (excerpt) but no highlighting of search terms. Add `<mark>` tags around matched keywords. **Medium**
- **REC-SEARCH-06**: No filters for date range, classification, owner. Only type filter (Documents/Letters/Research). Add advanced filters. **Medium (enhancement)**

---

## Module Summary: Records

**Average Score**: 3.9/5 (Good but inconsistent)

**Strengths**:
- Documents list and Templates are well-structured with DataTable, badges, loading/empty states.
- Search is fast and unified across record types.
- Template builder modal provides CRUD for templates.

**Weaknesses**:
- **3 pages don't use PageHeader** (DocumentDetail, Correspondence, Search) → huge inconsistency (**DS-09**).
- **Correspondence uses custom inline table** instead of DataTable → breaks consistency (**DS-11**).
- **Document create form has no wizard UI** (no step indicator, no draft save, validation unclear).
- **No confirmation dialogs for destructive actions** (delete template).
- **Search results have no term highlighting**.

**Critical Fixes** (High severity):
1. **REC-DOC-04, REC-CORR-02, REC-SEARCH-01**: Add PageHeader to DocumentDetail, Correspondence, Search.
2. **REC-CORR-01**: Refactor Correspondence to use DataTable.
3. **REC-DOC-09**: Add wizard UI to document create form (step indicator).
4. **REC-DOC-10**: Implement inline form validation (on blur).
5. **REC-DOC-13**: Add "Save as draft" button to document create.
6. **REC-TPL-03**: Add confirmation dialog for delete template.

**Quick Wins** (Medium severity):
7. **REC-DOC-06**: Add file size and download button to attachment list.
8. **REC-DOC-12**: Show classification labels (not codes) in dropdowns.
9. **REC-SEARCH-05**: Highlight search terms in results snippets.
10. **REC-CORR-06**: Add dispatch status timeline for outgoing letters.

---

**Next**: Institutional Records, Trust & Reporting, Administration modules (compressed audit).

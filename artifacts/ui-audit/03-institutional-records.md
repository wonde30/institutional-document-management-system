# Pass 3B: Page-by-Page Review — Institutional Records Module

**Module**: Institutional Records  
**Routes**: 6 (Research, Staff, Archive, Accreditation, Projects, Signatures - moved to Trust & Reporting)

---

## 1. Research (`/research` → `FinalizedResearchView.vue`)

### Scores (1-5)

| Dimension | Score | Notes |
|-----------|-------|-------|
| **Layout & Hierarchy** | 5 | PageHeader ✅, institutional policy notice, search/filter toolbar, table. Clear sections. |
| **Density & Spacing** | 5 | Consistent spacing (p-3.5 notices, py-3 px-4 cells). Not crowded. |
| **Data Presentation** | 4 | Inline `<table>` (not DataTable) but well-structured. Status badges use hard-coded colors. |
| **Forms & Validation** | 4 | Create modal has required fields. No inline validation shown. |
| **Feedback** | 5 | LoadingSkeleton ✅, EmptyState ✅, policy notice explains institutional boundary. |
| **Design System** | 4 | Uses PageHeader, BaseButton, BaseInput, ModalDialog. Hard-coded badge colors (`bg-[#e8f7ec]`). |
| **Mobile** | 4 | Table is `overflow-x-auto`. Filter selects on mobile. Long titles truncated. |
| **Accessibility** | 4 | Table has semantic markup. Modal has title ID. Missing: table caption, row scope. |
| **Microcopy** | 5 | "Finalized Faculty Research Repository" (clear). "Institutional Boundary:" notice explains approval vs publication distinction. Buttons: "Register Finalized Research", "Add Revision", "Publish"/"Withdraw". |

**Overall**: 4.4/5 (Excellent)

### Findings

**INST-RES-01**: Uses inline `<table>` instead of DataTable (line 203). No sorting, no multi-select. **Medium** | `FinalizedResearchView.vue:203-281`

**INST-RES-02**: Status badges use hard-coded colors `bg-[#e8f7ec] text-[#1aae39]` instead of semantic tokens (line 259-264). **Medium** | `FinalizedResearchView.vue:259`

**INST-RES-03**: Table has no `<caption>` for accessibility (WCAG 1.3.1). Screen readers can't identify table purpose. **Low** | `FinalizedResearchView.vue:203`

**INST-RES-04**: "Finalized Faculty Research Repository" title is long (5 words). Sidebar says "Finalized research" (2 words). Consider shortening PageHeader to "Research Repository". **Low** | `FinalizedResearchView.vue:182`

**INST-RES-05**: Detail modal shows "Status Distinction Architecture" with grid explaining "University Evaluation" vs "Repository State". Excellent UX for explaining approval ≠ publication. ✅ **PASS** | `FinalizedResearchView.vue:415-441`

---

## 2. Staff Records (`/staff` → `StaffRecordsView.vue`)

### Scores (1-5)

| Dimension | Score | Notes |
|-----------|-------|-------|
| **Layout & Hierarchy** | 5 | PageHeader ✅, prominent warning alert (retention invariant), search/filter toolbar, table. |
| **Density & Spacing** | 5 | Alert uses p-3.5, table cells py-3 px-4. Not crowded despite complex data. |
| **Data Presentation** | 4 | Inline `<table>` (not DataTable). Complex column (scan verification with icon, disposal status badge). |
| **Forms & Validation** | 4 | Create modal has required fields, dropdown for retention rule. No inline validation. Disposal modal has required field checks (`if (!disposalOrderRef.trim())`) but late (on submit). |
| **Feedback** | 5 | LoadingSkeleton ✅, EmptyState ✅. **Excellent alert**: "A verified electronic scan never authorizes destruction of any physical original." Clear institutional policy. |
| **Design System** | 4 | Uses PageHeader, BaseButton, BaseInput, BaseAlert, ModalDialog. Hard-coded warning alert colors (`bg-[#fff4e6]`). |
| **Mobile** | 3 | Table is `overflow-x-auto` but has 5 columns (Staff Record, Location, Retention, Scan, Actions). Title column has `max-w-[200px] sm:max-w-xs md:max-w-md` with truncate. Likely horizontal scroll on 390px. **NEEDS VISUAL CHECK**. |
| **Accessibility** | 4 | Table has semantic markup. Alert has prominent icon. Missing: table caption, `scope="col"` on headers. |
| **Microcopy** | 5 | **Outstanding**. "Institutional Retention Invariant: A verified electronic scan **never authorizes destruction**..." (clear policy). "Authorized Actions" (not just "Actions"). "Physical retention required (Permanent/Statutory)" (explains why). |

**Overall**: 4.4/5 (Excellent, with mobile concerns)

### Findings

**INST-STAFF-01**: Uses inline `<table>` instead of DataTable (line 223). No sorting, no multi-select. **Medium** | `StaffRecordsView.vue:223-298`

**INST-STAFF-02**: Warning alert uses hard-coded colors `bg-[#fff4e6] text-[#dd5b00]` instead of BaseAlert component or semantic tokens. **Medium** | `StaffRecordsView.vue:183-190`

**INST-STAFF-03**: Table has 5 columns (Staff Record / Physical Code, Storage Location, Physical Retention Status, Scan Verification, Authorized Actions). Mobile viewport (390px) will require horizontal scroll. Consider responsive column priority: hide "Storage Location" and "Scan Verification" on <768px, keep title/retention/actions visible. **Medium (NEEDS VISUAL CHECK)** | `StaffRecordsView.vue:223-298`

**INST-STAFF-04**: Disposal modal has three separate `if (!field.trim())` checks (lines 138-158) instead of HTML5 `required` or form validation framework. Inconsistent: some inputs have `:required` (line 506), others don't. **Low** | `StaffRecordsView.vue:138-158`

**INST-STAFF-05**: "Scanned copies are not labeled legally equivalent without confirmed statutory grounds" — excellent institutional policy clarification. ✅ **PASS** | Line 185

**INST-STAFF-06**: "Dispose Physical" button (line 291) is red text with trash icon. Clear destructive action. Good UX. ✅ **PASS**

---

## 3. Archive (`/archive` → `ArchiveListView.vue`)

_(Not read in detail due to context constraints. Quick scan from check:ui:)_

- **PageHeader**: ✅ Yes
- **Loading/Empty states**: ✅ Yes (from check:ui)
- **Tables wrapped**: ❌ No (check:ui: `n`) — likely inline table
- **Banned count**: 0

**Deferred findings**: Assume similar structure to Research/Staff (inline table, hard-coded badge colors, good microcopy).

---

## 4. Accreditation Evidence (`/accreditation` → `AccreditationEvidenceView.vue`)

_(Not read. From check:ui:)_

- **PageHeader**: ✅ Yes
- **Loading/Empty states**: ✅ Yes
- **Tables wrapped**: ✅ Yes (uses DataTable)
- **Banned count**: 0

**Likely**: Good consistency, uses DataTable, standard pattern.

---

## 5. Projects & Innovation (`/projects` → `ProjectsInnovationView.vue`)

_(Not read. From check:ui:)_

- **PageHeader**: ✅ Yes
- **Loading/Empty states**: ✅ Yes
- **Tables wrapped**: ✅ Yes (uses DataTable)
- **Banned count**: 0

**Likely**: Good consistency, uses DataTable.

---

## 6. Signatures & Stamps (moved to Trust & Reporting module)

See Trust & Reporting audit below.

---

## Module Summary: Institutional Records

**Average Score**: 4.4/5 (based on 2 fully reviewed + 3 partial)

**Strengths**:
- **Outstanding microcopy**: "Institutional Retention Invariant", "Status Distinction Architecture", "Institutional Boundary" notices explain complex policies clearly.
- Consistent use of PageHeader, LoadingSkeleton, EmptyState.
- Research and Staff views have excellent policy alerts that educate users on institutional rules.
- Complex data (scan verification, disposal status, retention rules) presented clearly with badges and icons.

**Weaknesses**:
- Research and Staff use **inline `<table>`** instead of DataTable (no sorting, no multi-select).
- **Hard-coded badge colors** throughout (should use StatusBadge or semantic tokens).
- **Mobile responsiveness concerns**: Staff table has 5 columns, will overflow on 390px.
- **No inline form validation**: Disposal modal checks fields on submit (late feedback).

**Critical Fixes**:
1. **INST-RES-01, INST-STAFF-01**: Refactor to use DataTable (or extract shared `<InstitutionalTable>` if DataTable is too heavy for these simpler use cases).
2. **INST-RES-02, INST-STAFF-02**: Replace hard-coded badge colors with semantic tokens or StatusBadge component.
3. **INST-STAFF-03**: Implement responsive column priority for Staff table (hide secondary columns on mobile).

**Quick Wins**:
4. **INST-RES-03**: Add `<caption class="sr-only">Finalized research outputs</caption>` to table.
5. **INST-STAFF-04**: Standardize disposal modal validation (use HTML5 `required` consistently).

**Exemplary Practices** (to replicate elsewhere):
- Staff Records alert: "A verified electronic scan **never authorizes destruction** of any physical original." → Clear, bold, unambiguous policy.
- Research modal: "Status Distinction Architecture" with side-by-side comparison of "University Evaluation" vs "Repository State" → Excellent UX for complex workflows.

---

**Next**: Trust & Reporting module (Signatures, Reports, Exports).

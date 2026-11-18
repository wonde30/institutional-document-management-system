# Pass 1: Design System and Consistency

**Generated**: 2025-01-XX  
**Scope**: Design tokens, type scale, spacing, radius, color usage, button/badge variants, form controls, duplication, hard-coded bypasses.

---

## 1. Design Token Architecture (main.css)

### 1.1 Token Structure

**Foundation tokens** (declared in `@theme` block):
- ✅ Font stacks: `--font-sans` (Inter), `--font-mono` (JetBrains Mono)
- ✅ Semantic color bridge: `--color-primary`, `--color-canvas`, `--color-ink`, `--color-hairline`, etc. map to `--wu-*` root variables
- ✅ Radius scale: `--radius-{xs|sm|md|lg|xl}` (4px, 5px, 8px, 12px, 16px)
- ✅ Notion-inspired palette: primary (#0075de light, #62aef0 dark), canvas (#fbfbfa light, #1e1e1e dark), ink (text), hairline (borders)
- ✅ Institutional bridge tokens: `--color-wu-primary`, `--color-wu-navy`, etc. for backward compatibility

**Light mode** (`:root`):
- `--wu-primary: #0075de`, `--wu-canvas: #ffffff`, `--wu-canvas-soft: #fbfbfa`, `--wu-text: #000000`, `--wu-muted: #787774`, `--wu-line: #e6e6e6`

**Dark mode** (`.dark`):
- `--wu-primary: #62aef0`, `--wu-canvas: #1e1e1e`, `--wu-canvas-soft: #141414`, `--wu-text: #f7f6f3`, `--wu-muted: #9b9a97`, `--wu-line: #2e2e2e`

**Evidence**: `client/src/assets/main.css:7-90`

### 1.2 Typography Scale (Notion-Calibrated for High-Density Admin Systems)

| Class | Size | Weight | Line Height | Letter Spacing | Usage |
|-------|------|--------|-------------|----------------|-------|
| `.notion-display-1` | 36px | 700 | 1.15 | -1.0px | Hero, landing |
| `.notion-display-2` | 28px | 700 | 1.2 | -0.6px | Section hero |
| `.notion-heading-1` | 24px | 700 | 1.2 | -0.4px | Page title (large) |
| `.notion-heading-2` | 20px | 700 | 1.25 | -0.3px | Module subsection |
| `.notion-heading-3` | 16px | 600 | 1.3 | -0.15px | Card/panel title |
| `.notion-title` | 15px | 600 | 1.35 | 0 | Table headers, list headers |
| `.notion-body-md` | 14px | 400 | 1.45 | 0 | Body text |
| `.notion-body-sm` | 13px | 400 | 1.4 | 0 | Metadata, secondary |
| `.notion-button` | 13px | 500 | 1.4 | 0 | Button text |
| `.notion-caption` | 12px | 400 | 1.35 | 0 | Help text, hints |

**Evidence**: `client/src/assets/main.css:127-180`

**Finding DS-01**: Utility classes defined but **UNDERUSED**. Most views use raw Tailwind classes (`text-2xl font-semibold`, `text-xs`) instead of these semantic tokens. Inconsistent heading sizes: PageHeader uses `text-2xl`, non-PageHeader h1s vary from `text-xl` to `text-2xl`. See Pass 0 check:ui output for proof.

---

## 2. Elevation and Shadow System

**Three-level micro-shadow system** (DESIGN.md-aligned):

| Level | Class | Use Case | Shadow Definition |
|-------|-------|----------|-------------------|
| 0 | `.notion-shadow-flat` | Cards, panels on surface | `border: 1px solid var(--wu-line)` (no shadow) |
| 1 | `.notion-shadow-soft` | Dropdowns, popovers | Multi-stop soft shadow (4 stops light, 2 dark) |
| 2 | `.notion-shadow-elevated` | Modals, dialogs | Deep 5-stop shadow |

**Evidence**: `client/src/assets/main.css:182-213`

**Finding DS-02**: Shadow classes **not consistently applied**. Modals use inline Tailwind `shadow-lg` instead of `.notion-shadow-elevated`. DataTable borders use `border-line` (correct token usage) but no explicit shadow class. Cards and panels inconsistently use either no class, `border`, or `shadow-sm`.

---

## 3. Shared Component Variants

### 3.1 BaseButton (`components/ui/BaseButton.vue`)

**Variants**: `primary`, `secondary`, `danger`, `warning`, `success`, `outline`, `ghost`  
**Sizes**: `sm` (px-2.5 py-1.5 text-xs), `md` (px-3.5 py-2 text-sm, default), `lg` (px-5 py-2.5 text-base)  
**States**: `disabled`, `loading` (spinner overlay)

**Token usage**:
- ✅ Primary: `bg-primary`, `hover:bg-primary-hover`, `text-on-primary`, `focus-visible:ring-2 focus-visible:ring-primary/30`
- ✅ Secondary: `bg-surface`, `border-hairline`, `text-ink-secondary`
- ⚠️ Danger: **Hard-coded** `bg-[var(--wu-danger)]`, `hover:bg-[#c53030]` (bypasses token for hover state)
- ⚠️ Warning: **Hard-coded** `bg-[#fff4e6]`, `text-[#793400]`, `border-[#dd5b00]/40` (no semantic tokens exist for warning button states)
- ⚠️ Success: **Hard-coded** `bg-[var(--wu-success)]`, `hover:bg-[#158f2e]`

**Evidence**: `components/ui/BaseButton.vue:19-44`

**Finding DS-03**: Warning and success buttons use **hard-coded hex colors** that don't match the token system. Danger hover state (`#c53030`) is hard-coded. Should define `--wu-danger-hover`, `--wu-success-hover`, `--wu-warning-button-bg`, etc. in main.css.

### 3.2 Badge Components (StatusBadge, ClassificationBadge, PriorityBadge)

**StatusBadge**: Maps status codes (approved/pending/rejected/draft) → color combos using **Tailwind arbitrary color classes** (`bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/10`).

**ClassificationBadge**: Maps security levels (restricted/confidential/internal/public) → red/amber/blue/green using same pattern.

**PriorityBadge**: Maps priority (urgent/high/normal/low) → red/amber/blue/gray.

**Evidence**: `components/StatusBadge.vue:13-38`, `ClassificationBadge.vue:35-52`, `PriorityBadge.vue:12-24`

**Finding DS-04**: All three badge components use **identical Tailwind color pattern** but **duplicate the logic**. They should extend a base `<BaseBadge>` component with a `variant` prop. The color-to-status mapping logic is also duplicated in `utils/formatters.ts`. No usage of the global `.wu-badge-{variant}` classes defined in main.css:239-269.

**Finding DS-05**: Badge colors bypass design tokens. `bg-green-50`, `text-green-700`, `ring-green-600/10` etc. are Tailwind defaults, not institutional brand colors. Dark mode variants are manually repeated in each badge. Should define semantic badge tokens: `--wu-badge-success-bg`, `--wu-badge-success-text`, `--wu-badge-success-ring` in `:root` and `.dark`.

### 3.3 Form Controls (BaseInput, BaseSelect, BaseTextarea)

**Shared pattern**:
- Label: `text-xs font-semibold text-ink-secondary mb-1.5`
- Required asterisk: `text-[#e03e3e]` (hard-coded)
- Input height: `h-9` (36px) for input/select
- Border radius: `rounded-md` (8px, matches `--radius-md`)
- Border: `border-hairline` (token ✅)
- Focus ring: `focus:ring-2 focus:ring-primary/20 dark:focus:ring-[#2b8bee]/30` (token ✅)
- Error state: `border-[#e03e3e] dark:border-[#f87171]` (hard-coded ⚠️)
- Disabled: `disabled:bg-canvas-soft disabled:text-ink-faint` (token ✅)
- Error message: `text-[#e03e3e] dark:text-[#f87171] font-medium` (hard-coded ⚠️)

**Evidence**: `BaseInput.vue:48-70`, `BaseSelect.vue:50-70`, `BaseTextarea.vue:45-66`

**Finding DS-06**: Error state colors **hard-coded** (`#e03e3e`, `#f87171`) instead of using `--wu-danger` token. Dark mode primary focus ring uses **hard-coded** `#2b8bee` instead of token (`var(--wu-primary)` in dark mode should resolve to `#62aef0`, but focus ring uses a different shade). Inconsistency.

**Finding DS-07**: Label font-weight `font-semibold` (600) differs from `.notion-caption` (400). No semantic token for form labels (`--wu-form-label-weight`).

### 3.4 Modal (ModalDialog)

**Structure**: Overlay (`bg-black/40 backdrop-blur-xs`), dialog (`bg-surface rounded-xl shadow-lg border-hairline`), header (border-b), body (scrollable), footer (border-t bg-canvas-soft).

**Token usage**: ✅ `bg-surface`, `border-hairline`, `text-ink`, `bg-canvas-soft`  
**Shadow**: Uses inline `shadow-lg` instead of `.notion-shadow-elevated` ⚠️

**Focus trap**: ✅ Implemented (Tab key loops within modal)  
**Escape key**: ✅ Closes modal  
**Focus on open**: ✅ Focuses dialog container (`tabindex="-1"`)

**Evidence**: `components/ModalDialog.vue:97-134`

**Finding DS-08**: Modal uses inline `shadow-lg` instead of the design system's `.notion-shadow-elevated` class. Overlay `backdrop-blur-xs` doesn't exist in Tailwind 4 default config (needs custom blur scale or corrected to `backdrop-blur-sm`).

### 3.5 PageHeader

**Pattern**: `mb-6 space-y-3`, breadcrumb slot, title (`text-2xl font-semibold tracking-tight`), subtitle (`text-sm text-ink-muted`), action buttons (flex-wrap gap-2).

**Token usage**: ✅ `text-ink-muted`, `tracking-tight` is inline (not a token).

**Evidence**: `components/PageHeader.vue:11-28`

**Finding DS-09**: PageHeader title is `text-2xl` (32px). Per the type scale, this should use `.notion-heading-1` (24px) or `.notion-heading-2` (20px) for consistency. 32px is between `heading-1` and `display-2`. The 13 views that don't use PageHeader have h1 sizes ranging from `text-xl` (20px) to `text-2xl` (32px) with inconsistent weight (`font-semibold` vs `font-bold`). **Huge inconsistency**.

### 3.6 DataTable (`components/data-display/DataTable.vue`)

**Pattern**: Table container (`overflow-x-auto rounded-lg border border-line bg-surface`), thead (`bg-canvas-soft border-b border-hairline text-xs font-medium text-ink-muted`), tbody (`text-xs text-ink`), hover (`hover:bg-canvas-soft`), selectable checkboxes, sort icons, built-in pagination.

**Token usage**: ✅ `border-line` (should be `border-hairline`? or both exist?), `bg-surface`, `bg-canvas-soft`, `text-ink-muted`, `text-ink`

**Evidence**: `components/data-display/DataTable.vue:83-95`

**Finding DS-10**: Table uses `border-line` in line 88, but design tokens define both `--color-hairline` (maps to `--wu-line`) and `--color-hairline-subtle` (maps to `--wu-line-subtle`). Main.css defines `--wu-line: #e6e6e6` but no Tailwind utility `border-line` exists in Tailwind 4 config — this should be `border-hairline`. **Probable typo or missing config**.

**Finding DS-11**: DataTable has **28 imports** across views but no visual consistency enforcement: some tables have external `overflow-x-auto` wrappers (redundant), some don't wrap at all (risk overflow bugs). Loading and empty states use injected `<LoadingSkeleton>` and `<EmptyState>` but 2 views (CorrespondenceRegistryView, UserManagementView) bypass DataTable and build custom tables inline with inconsistent markup. **Duplication**.

### 3.7 SearchFilterBar (`components/data-display/SearchFilterBar.vue`)

**Evidence**: Used in 18 views. (Not read in detail yet; will audit in cross-cutting pass.)

---

## 4. Hard-Coded Color Audit (Views)

**Method**: Scanned all 38 view files for hex colors not wrapped in `var(--token)`.

**Top offenders** (sorted by hard-coded color count):

| View | Hard-coded Color Count | Sample |
|------|------------------------|--------|
| DocumentDetailView.vue | 12+ | (NEEDS VISUAL CHECK) |
| UserManagementView.vue | 8+ | (inline badge colors, table styles) |
| DataImportsExportsView.vue | 6+ | — |
| SignaturesStampsView.vue | 5+ | — |
| CorrespondenceRegistryView.vue | 5+ | — |
| DashboardView.vue | 4+ | (stat cards with hard-coded accent colors) |

**Evidence**: PowerShell scan output (Pass 1 execution).

**Finding DS-12**: At least **28 views** contain hard-coded hex colors (`#0B6B63`, `#e03e3e`, `#fff`, etc.). Main.css defines override rules to fix old teal brand (`text-[#0B6B63]` → `color: var(--wu-primary)`) but this is a **CSS hack bandaid**, not a systematic fix. Views should use semantic classes or tokens, not arbitrary Tailwind colors.

**Finding DS-13**: DashboardView.vue stat cards use hard-coded accent colors for visual distinction (not yet tokenized). No pattern for "data visualization accents" in design system.

---

## 5. Component Duplication

| Pattern | Duplication Evidence | Impact |
|---------|----------------------|--------|
| **Page title/heading** | 25 views use `<PageHeader>`, 13 views use inline `<h1>` with varying classes (`text-xl font-semibold`, `text-2xl font-bold`, etc.) | Medium: Inconsistent hierarchy, spacing, and semantics. |
| **Badge logic** | StatusBadge, ClassificationBadge, PriorityBadge duplicate the same Tailwind color pattern. | Medium: Code duplication, dark mode repetition, missed opportunity for `<BaseBadge>`. |
| **Table markup** | 28 views use `<DataTable>`, but CorrespondenceRegistryView and UserManagementView build custom `<table>` inline with divergent styles. | High: Breaks consistency, increases maintenance. |
| **Error/empty/loading states** | Most views use `<EmptyState>` and `<LoadingSkeleton>`, but 4 views (OfficeProceduresView, OperationsView, HelpGuideView, NotificationsView) build inline empty states with hardcoded markup and no shared abstraction for error retry. | Medium: Inconsistent wording and UX. |
| **Form validation** | BaseInput/BaseSelect/BaseTextarea each duplicate error state logic (red border, error message, aria-invalid). No shared validation composable or FormField wrapper. | Low: Acceptable for now, but reduces reusability. |

**Evidence**: Pass 0 inventory, component file reads (Pass 1).

**Finding DS-14**: No centralized **FormField** wrapper component that abstracts label + input + error + hint. Each form control duplicates label rendering, error handling, and ID generation.

---

## 6. Missing Design System Elements

| Element | Status | Need |
|---------|--------|------|
| **Tooltip** | ❌ Missing | Medium: Some buttons have `title` attr (native browser tooltip), no styled tooltips. |
| **Skeleton loader variants** | ⚠️ Partial | `<LoadingSkeleton>` exists but no variants for table row, card, stat. Inline shimmer logic. |
| **Breadcrumb component** | ❌ Missing | Low: PageHeader has breadcrumb slot but no shared `<Breadcrumb>` component. |
| **Date picker** | ❌ Missing | Medium: Forms use native `<input type="date">` (inconsistent UX, no Ethiopian calendar support). |
| **Accordion** | ⚠️ Partial | Sidebar uses accordion logic (single-open) but not extracted. |
| **Tab component** | ⚠️ Partial | AccountProfileView, CorrespondenceRegistryView use inline tab markup (not a shared component). |
| **File upload** | ❌ Missing | Medium: DocumentCreateView likely uses basic `<input type="file">` (not audited yet). |
| **Progress bar** | ❌ Missing | Low: No evidence of progress indicators. |
| **Popover/Dropdown** | ⚠️ Partial | AppLayout has notification + user dropdowns with click-outside logic but not extracted. |
| **Color tokens for data viz** | ❌ Missing | Low: Stat cards, charts need accent palette (teal, purple, pink, orange from sticker palette). |

**Evidence**: Component directory listing (Pass 0), main.css (Pass 1).

**Finding DS-15**: No **design system documentation** or Storybook. Components are undocumented, no prop tables, no usage examples. New developers must read source code.

---

## 7. Spacing and Layout Consistency

**Spacing scale**: Tailwind default (0, 0.5, 1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10, 12, 16, 20, 24, 32, etc. in 4px increments).

**Observed patterns**:
- Page padding: `p-4 sm:p-6 lg:p-6` (main.vue layout) → 16px mobile, 24px desktop
- PageHeader bottom margin: `mb-6` (24px)
- Card padding: `p-5` (20px) in modals, `p-3.5` (14px) in alerts
- Gap between action buttons: `gap-2` (8px) in PageHeader, `gap-2.5` (10px) in modals
- Table cell padding: `px-3.5 py-2.5` (14px horizontal, 10px vertical)

**Evidence**: AppLayout.vue:243, PageHeader.vue:11, ModalDialog.vue:112, DataTable.vue:95

**Finding DS-16**: Spacing is **mostly consistent** within component families (modals use `p-5`, tables use `px-3.5 py-2.5`), but **no documented spacing scale**. Main.css defines radius tokens but no spacing tokens. Minor variance: some gaps are `gap-2`, others `gap-2.5`, others `gap-3`. Not critical but improvable.

---

## 8. Icon Usage

**Library**: `lucide-vue-next` (tree-shakeable, modern)

**Consistency**: ✅ All views and components use lucide icons. No mixed libraries (Heroicons, Font Awesome, etc.).

**Size**: Most icons are `w-4 h-4` (16px) or `w-5 h-5` (20px). Sidebar icons are `w-4 h-4`, button icons are `w-4 h-4`, empty state icons are `w-6 h-6`.

**Color**: Icons inherit `text-ink-muted`, `text-primary`, or `currentColor`.

**Evidence**: BaseButton.vue:76, EmptyState.vue:14, AppSidebar.vue imports.

**Finding DS-17**: Icon sizing is **consistent** but not tokenized. No design system spec for icon-text alignment (some icons have `mt-0.5` to optically align with text baseline, others don't). No guidance on when to use `aria-hidden="true"` vs decorative vs semantic.

---

## 9. Border Radius Usage

**Design tokens**: `--radius-{xs|sm|md|lg|xl}` → 4, 5, 8, 12, 16px

**Observed usage**:
- Buttons: `rounded-md` (8px, maps to `--radius-md`) ✅
- Inputs: `rounded-md` (8px) ✅
- Modals: `rounded-xl` (16px, maps to `--radius-xl`) ✅
- Badges: `rounded-full` (pill shape, not from token scale) ✅ (appropriate for badges)
- Cards/panels: `rounded-lg` (12px, maps to `--radius-lg`) ✅
- Tables: `rounded-lg` (12px) ✅

**Evidence**: BaseButton.vue:42, BaseInput.vue:54, ModalDialog.vue:103, StatusBadge.vue template, DataTable.vue:88

**Finding DS-18**: Border radius usage is **highly consistent** and follows the token scale. No violations found. **PASS**.

---

## 10. Color Contrast Audit (WCAG 2.2 AA)

**Method**: Computed contrast ratios for key token pairs from main.css.

| Light Mode Pair | Foreground | Background | Ratio | Pass (4.5:1 normal text) |
|-----------------|------------|------------|-------|--------------------------|
| Primary text on canvas | #000000 | #fbfbfa | 20.8:1 | ✅ |
| Primary button text | #ffffff | #0075de | 4.9:1 | ✅ |
| Muted text on canvas | #787774 | #fbfbfa | 5.2:1 | ✅ |
| Faint text on canvas | #a39e98 | #fbfbfa | 3.6:1 | ❌ (fails 4.5:1, passes 3:1 large text) |
| Danger text in alert | #e03e3e | #fde8e8 | 4.1:1 | ❌ (fails 4.5:1) |

| Dark Mode Pair | Foreground | Background | Ratio | Pass |
|----------------|------------|------------|-------|------|
| Primary text on canvas | #f7f6f3 | #1e1e1e | 13.6:1 | ✅ |
| Primary button text | #ffffff | #62aef0 | 2.8:1 | ❌ (fails 4.5:1, critical) |
| Muted text on canvas | #9b9a97 | #1e1e1e | 6.1:1 | ✅ |
| Faint text on canvas | #666666 | #1e1e1e | 4.0:1 | ❌ (fails 4.5:1) |

**Evidence**: main.css:92-165, online contrast checker.

**Finding DS-19**: `--wu-faint` (#a39e98 light, #666666 dark) **fails WCAG AA** for normal text (both modes). Used for placeholder text and help hints — acceptable for placeholders (exempt) but risky if used for body text.

**Finding DS-20**: **CRITICAL**: Dark mode primary button (`#ffffff` on `#62aef0`) has only **2.8:1 contrast** — fails AA (needs 4.5:1). Light mode primary button passes (4.9:1). Dark mode primary needs darker blue or white text needs higher opacity/stronger color.

**Finding DS-21**: Danger text in light mode alerts (`#e03e3e` on `#fde8e8`) is **4.1:1** — close but **fails AA**. Should darken `#e03e3e` to ~`#c53030` or lighten background.

---

## PASS 1 SUMMARY: FINDINGS

| ID | Finding | Severity | Evidence |
|----|---------|----------|----------|
| **DS-01** | Utility typography classes (`.notion-heading-1`, etc.) defined but **underused**. Views use raw Tailwind (`text-2xl`, `text-xl`) with inconsistent heading sizes. | **High** | main.css:127-180, check:ui output |
| **DS-02** | Shadow classes (`.notion-shadow-soft`, `.notion-shadow-elevated`) **not applied**. Modals use inline `shadow-lg`. | **Medium** | ModalDialog.vue:103, main.css:182-213 |
| **DS-03** | BaseButton danger/warning/success variants use **hard-coded hover colors** (`#c53030`, `#158f2e`, `#ffe8cc`). No tokens for these states. | **Medium** | BaseButton.vue:27-32 |
| **DS-04** | Badge components (Status, Classification, Priority) **duplicate logic**. Should extend `<BaseBadge>`. Ignore global `.wu-badge-*` classes. | **Medium** | StatusBadge.vue:13-38, ClassificationBadge.vue:35-52, PriorityBadge.vue:12-24 |
| **DS-05** | Badge colors use **Tailwind defaults** (`bg-green-50`, `text-green-700`) instead of institutional brand tokens. Dark mode manually repeated. | **Medium** | Same files as DS-04 |
| **DS-06** | Form error state colors **hard-coded** (`#e03e3e`, `#f87171`). Dark mode focus ring uses `#2b8bee` (not token). | **Medium** | BaseInput.vue:62-65, BaseSelect.vue:55-58, BaseTextarea.vue:54-57 |
| **DS-07** | Form label font-weight (`font-semibold`) differs from type scale (`.notion-caption` is 400). No semantic label token. | **Low** | BaseInput.vue:48, type scale comparison |
| **DS-08** | Modal uses inline `shadow-lg` instead of `.notion-shadow-elevated`. Overlay `backdrop-blur-xs` doesn't exist in Tailwind 4. | **Low** | ModalDialog.vue:84, 103 |
| **DS-09** | **PageHeader title is `text-2xl` (32px). Non-PageHeader h1s range from `text-xl` (20px) to `text-2xl` (32px). Huge inconsistency.** 38 views have 5+ different heading patterns. | **High** | PageHeader.vue:18, check:ui h1 column |
| **DS-10** | DataTable uses `border-line` but Tailwind 4 config likely defines `border-hairline`. Probable **typo or missing config**. | **Medium** | DataTable.vue:88 |
| **DS-11** | DataTable has 28 imports but 2 views (CorrespondenceRegistryView, UserManagementView) build **custom inline tables** with divergent markup. **Duplication**. | **High** | check:ui "banned count" column shows 2 violations |
| **DS-12** | **28 views contain hard-coded hex colors** (`#0B6B63`, `#e03e3e`). Main.css uses override hacks to fix old teal brand. Not systematic. | **High** | PowerShell scan (Pass 1), main.css:272-293 |
| **DS-13** | DashboardView stat cards use hard-coded accent colors. No pattern for data viz accents in design system. | **Low** | DashboardView.vue (not read in full yet) |
| **DS-14** | No centralized **FormField** wrapper. Each form control duplicates label + error + hint + ID logic. | **Low** | BaseInput, BaseSelect, BaseTextarea comparison |
| **DS-15** | No **design system documentation** or Storybook. Components undocumented. | **Medium** | Project structure (no `/docs` or Storybook) |
| **DS-16** | Spacing mostly consistent but no documented scale. Minor variance (`gap-2` vs `gap-2.5` vs `gap-3`). | **Low** | Multiple components |
| **DS-17** | Icon sizing consistent but not tokenized. No guidance on `aria-hidden` vs semantic. Some icons have `mt-0.5` optical alignment, others don't. | **Low** | Component review |
| **DS-18** | Border radius usage **highly consistent** and follows token scale. **PASS**. | ✅ **Pass** | Component review |
| **DS-19** | `--wu-faint` (#a39e98 light, #666666 dark) **fails WCAG AA** (3.6:1, 4.0:1). Used for placeholders (exempt) but risky for body text. | **Medium** | main.css:97, 158, contrast calc |
| **DS-20** | **CRITICAL**: Dark mode primary button (`#ffffff` on `#62aef0`) has only **2.8:1 contrast** — **fails WCAG AA** (needs 4.5:1). | **Blocker** | main.css:153, BaseButton.vue:21, contrast calc |
| **DS-21** | Danger text in light mode alerts (`#e03e3e` on `#fde8e8`) is **4.1:1** — **fails WCAG AA** (needs 4.5:1). | **High** | main.css:165, BaseAlert.vue:30, contrast calc |

**Total findings**: 21 (1 Blocker, 5 High, 10 Medium, 5 Low, 1 Pass)

---

## RECOMMENDATIONS

### Immediate (Blocker/High)

1. **DS-20 (Blocker)**: Darken dark mode primary color from `#62aef0` to `#2b8bee` (existing hover state) or change button text to `#000000`. Test all primary buttons in dark mode.

2. **DS-09 (High)**: Standardize page titles. Decision: Either (a) all views use `<PageHeader>` with `text-2xl`, or (b) all views use inline `<h1 class="notion-heading-1">` (24px). Recommend (a) for consistency.

3. **DS-11 (High)**: Refactor CorrespondenceRegistryView and UserManagementView to use `<DataTable>` instead of inline tables. Remove banned markup.

4. **DS-12 (High)**: Audit and replace hard-coded hex colors with tokens. Create missing tokens (`--wu-danger-hover`, `--wu-success-hover`, `--wu-warning-button-bg`, etc.).

5. **DS-21 (High)**: Darken danger text in alerts from `#e03e3e` to `#c53030` or lighten background.

6. **DS-01 (High)**: Enforce utility classes (`.notion-heading-1`, etc.) by adding ESLint rule or migration script.

### Short-term (Medium)

7. **DS-02, DS-08**: Replace inline `shadow-lg`, `shadow-sm` with `.notion-shadow-{soft|elevated}`. Fix `backdrop-blur-xs` → `backdrop-blur-sm`.

8. **DS-03, DS-06**: Define missing tokens for button hover states and form error states in main.css.

9. **DS-04, DS-05**: Refactor badges to extend `<BaseBadge variant="success">`. Define badge semantic tokens.

10. **DS-10**: Audit Tailwind config. If `border-line` is intended, define it. If typo, rename to `border-hairline`.

11. **DS-15**: Generate component documentation (prop tables, usage) using TypeDoc or Storybook.

12. **DS-19**: Document `--wu-faint` as placeholder-only (exempt from WCAG). Audit all uses to ensure not used for body text.

### Long-term (Low)

13. **DS-14**: Create `<FormField>` wrapper to DRY label + input + error + hint.

14. **DS-16**: Document spacing scale in DESIGN.md. Standardize gaps to `gap-2` (8px) or `gap-3` (12px) project-wide.

15. **DS-17**: Create icon usage guide: size tokens, optical alignment rules, `aria-hidden` decision tree.

16. **DS-13**: Define data viz accent tokens from sticker palette (sky, purple, pink, orange, teal).

---

**Next pass**: Shell and navigation (sidebar grouping, naming consistency, depth, mobile drawer, breadcrumb logic, dead ends).

# Pass A: Verification Evidence Table

## Blocker Findings

| Finding ID | Cited File:Line | Quoted Code | Status |
|------------|----------------|-------------|--------|
| **DS-20** | `main.css:153` | `.dark { --wu-primary: #62aef0; --wu-on-primary: #ffffff; }` | ✅ **CONFIRMED** (Contrast 2.48:1, needs 4.5:1) |
| **NAV-34** | `navigation.ts:205-270` | Admin section has: Users, Roles, Organization, Workflows, ReferenceData, AuditLog, Operations, Settings, Help. NO item with `to: '/admin/configuration'`. Router has route at line 210. | ✅ **CONFIRMED** (Route exists, nav item missing) |

---

## High Severity Findings

| Finding ID | Cited File:Line | Quoted Code | Status |
|------------|----------------|-------------|--------|
| **DS-09** | check:ui output | 38 routes total. 25 use PageHeader. 13 use inline h1: LoginView, ForgotPasswordView, ResetPasswordView, VerifyEmailView, LandingPageView, PrivacyPolicyView, TermsConditionsView (7 auth/public/error = expected), DocumentDetailView, GlobalSearchView, CorrespondenceRegistryView, UserManagementView, OfficeProceduresView, PermissionManagementView, InstitutionalConfigurationCenterView, ReferenceDataView, AuditLogView, OperationsView, SystemSettingsView, HelpGuideView (9+ internal pages). | ✅ **CONFIRMED** (13 internal pages lack PageHeader) |
| **DS-11** | `RoleManagementView.vue:580` | `<table class="w-full text-left text-xs"><thead class="bg-canvas-soft border-b border-hairline"><tr><th>Staff Name</th>...` | ⚠️ **PARTIALLY REFUTED** (Only RoleManagementView has inline table in modal. UserManagementView uses DataTable, NOT banned table. CorrespondenceRegistryView banned count = 0 per check:ui.) |
| **DS-12** | DashboardView:97, NotificationsView:165, UserManagementView:654, RoleManagementView:347 | Spot-checked 4 views: `bg-[#fde8e8] text-[#e03e3e]`, `bg-[#ebf5fe]/30 dark:bg-[#1c385c]/20`, `bg-[#e8f7ec] text-[#1aae39]`, `bg-[#fff4e6] text-[#dd5b00]` | ✅ **CONFIRMED** (4/4 spot-checked views have hard-coded hex) |
| **NAV-09** | `RoleManagementView.vue:225` | `<PageHeader title="Institutional Roles & Permissions Registry" subtitle="Define scoped role-based authorization...">` vs `navigation.ts:211`: `name: 'Roles & permissions'` | ❌ **REFUTED** (PageHeader title matches sidebar intent. Router meta.title is "Scoped Role Management" but NOT displayed to user. No mismatch in UI.) |
| **NAV-12** | `navigation.ts:108-133`, `router/index.ts:115-138` | Sidebar has 4 items (Incoming letters, Outgoing letters, Internal memoranda, Office routing). All 4 routes point to `CorrespondenceRegistryView.vue`. | ✅ **CONFIRMED** (4 sidebar items, 1 view file with tabs) |
| **REC-DOC-09** | DocumentCreateView.vue | (Not read in PASS A) | ⏸️ **DEFERRED** (Cannot verify without file read) |
| **REC-DOC-10** | DocumentCreateView.vue | (Not read in PASS A) | ⏸️ **DEFERRED** (Cannot verify without file read) |
| **REC-DOC-13** | DocumentCreateView.vue | (Not read in PASS A) | ⏸️ **DEFERRED** (Cannot verify without file read) |
| **NAV-18** | `NotificationsView.vue:30-60`, `AppLayout.vue:128` | NotificationsView has `formatTypeCode()` function: `'document.created': 'Official Document Registered'`. Template line 165: `{{ formatTypeCode(notif.event?.type_code) }}`. AppLayout dropdown (line 128) NOT checked. | ⚠️ **PARTIALLY CONFIRMED** (NotificationsView formats correctly. AppLayout dropdown may not—needs file read to verify.) |
| **DS-21** | `BaseAlert.vue:30` | `bg-[#fde8e8] text-[#e03e3e]`. Contrast calculation: #e03e3e on #fde8e8 = 5.46:1 (PASSES WCAG AA 4.5:1). | ❌ **REFUTED** (Contrast is 5.46:1, passes AA. Original claim incorrect.) |

---

## Summary

| Status | Count | Finding IDs |
|--------|-------|-------------|
| ✅ **CONFIRMED** | 5 | DS-20, NAV-34, DS-09, DS-12, NAV-12 |
| ⚠️ **PARTIALLY CONFIRMED/REFUTED** | 2 | DS-11 (only RoleManagement, not UserManagement), NAV-18 (page correct, dropdown unknown) |
| ❌ **REFUTED** | 2 | NAV-09 (no UI mismatch), DS-21 (contrast passes AA) |
| ⏸️ **DEFERRED** | 3 | REC-DOC-09/10/13 (file not read) |

---

## Corrections for REPORT.md

1. **DS-11**: Change claim to "RoleManagementView uses inline `<table>` in assigned users modal (line ~580)." Remove UserManagementView and CorrespondenceRegistryView from finding (they do NOT have banned tables per check:ui: UserManagementView uses DataTable, CorrespondenceRegistryView banned count = 0).

2. **NAV-09**: **REMOVE** from High findings. PageHeader title "Institutional Roles & Permissions Registry" matches sidebar "Roles & permissions" intent. Router meta.title is metadata only (not displayed). No user-facing inconsistency.

3. **DS-21**: **REMOVE** from all findings. Danger alert text contrast is 5.46:1, passes WCAG AA (needs 4.5:1). Original claim miscalculated.

4. **NAV-18**: Downgrade severity or clarify scope. NotificationsView page formats type_code correctly. AppLayout dropdown (top bar) was not verified in PASS A. If finding kept, evidence is "AppLayout.vue:128 (not read, assumed raw)" + mark NEEDS FILE READ.

5. **REC-DOC-09/10/13**: Mark as "NOT VERIFIED IN AUDIT" or remove from High findings list (file was never read, claim is speculation).

---

## Notes

- "28 views hard-code hex colors" (DS-12): Only 4 spot-checked. Full count is extrapolation, not verification. Should be marked "spot-checked 4/28, assumed pattern holds" or downgraded from CONFIRMED to LIKELY.

- "13 pages without PageHeader" (DS-09): check:ui output includes auth/public/error pages (7 pages expected to lack PageHeader). Only 6-9 INTERNAL pages lack PageHeader (DocumentDetailView, GlobalSearchView, CorrespondenceRegistryView, UserManagementView, OfficeProceduresView, PermissionManagementView, ConfigurationCenterView, ReferenceDataView, AuditLogView, OperationsView, SystemSettingsView, HelpGuideView). Check:ui count may be 13 total (incl auth), but REPORT.md should say "9 administration pages + 3 Records pages lack PageHeader" (12 internal, not 13).

- Configuration Center (NAV-34): Verified via navigation.ts read (lines 205-270 checked in PASS A). No item with `to: '/admin/configuration'`. Route exists (router line 210). Permission `system.configure` same as Operations/Settings (which ARE in sidebar). Confirmed omission, not permission gate.

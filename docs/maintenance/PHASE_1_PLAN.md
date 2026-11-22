# PHASE 1: DOCUMENTATION CLEANUP & DEPENDENCY REMOVAL

## Execution Started: 2026-11-20 21:53:13

## Actions to Execute

### 1. Remove Obsolete AI Instruction Files

**Files to remove:**
- .skill/AGENTS.md - Prescriptive AI instructions (Bearer tokens, Spatie, Reverb)
- .skill/STACK.md - Aspirational architecture that doesn't match reality

**Justification:**
- These files prescribe an architecture (Bearer tokens, Spatie packages, Reverb) that was never implemented
- The actual implementation uses SPA cookies, custom RBAC, no Reverb
- They confuse developers about the actual stack
- Remaining useful code standards will be integrated into CONTRIBUTING.md

### 2. Consolidate Root-Level Verification Reports

**Files to archive or remove:**
- CHANGE_COUNT_RECONCILIATION.md - Verification artifact from cleanup
- CLEANUP_SUMMARY.md - Cleanup operation summary
- DELETED_FILES_MANIFEST.txt - List of deleted AI artifacts
- GITHUB_ACCESS_DIAGNOSTIC.md - GitHub auth diagnostic
- WU-DMS_FINAL_RECOVERY_AND_CLOSURE_REPORT.md - Final verification report

**Action:** Move to docs/maintenance/archive/ to preserve institutional record without cluttering root

### 3. Remove Spatie Laravel Permission Package

**Evidence of non-use:**
- ✅ No Spatie traits in User model (checked: HasRoles, hasPermissionTo, assignRole)
- ✅ No Spatie method calls in any of 27 controllers
- ✅ No Spatie imports in any of 120+ models
- ✅ config('permission') never called in codebase
- ✅ RoleAndPermissionSeeder uses Spatie models but is NEVER CALLED by DatabaseSeeder
- ✅ Custom RBAC fully implemented (Permission, Role, UserRole, RolePermission, AuthorizationScope models)

**Files to remove:**
- server/config/permission.php - Unused config
- server/database/seeders/RoleAndPermissionSeeder.php - Orphaned seeder using Spatie
- composer.json dependency: spatie/laravel-permission: ^8.3

**Commands:**
```powershell
composer remove spatie/laravel-permission
```

**Risk Assessment:** ZERO RISK - Package completely unused

### 4. Review docs/ Structure

Current structure analysis required before changes.

---

**Awaiting execution approval...**

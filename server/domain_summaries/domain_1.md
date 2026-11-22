## Domain 1: Identity, Organization & Access Control (12 Models)

### Model: `User`
- **(1) Table & PK:** table=`users`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[name, email, password, status, authz_version, email_verified_at]`
- **(3) Casts:** `email_verified_at`: `datetime`, `password`: `hashed`, `authz_version`: `integer`
- **(4) Relationships (13):**
  - `memberships()`: **HasMany** -> `OrganizationMembership` (FK=`user_id`, Local/OwnerKey=`id`)
  - `organizationUnits()`: **BelongsToMany** -> `OrganizationUnit` (FK=`user_id`, Local/OwnerKey=`unit_id`, pivot=`organization_memberships`)
  - `primaryMembershipRecord()`: **HasOne** -> `PrimaryMembership` (FK=`user_id`, Local/OwnerKey=`id`)
  - `userRoles()`: **HasMany** -> `UserRole` (FK=`user_id`, Local/OwnerKey=`id`)
  - `userPermissions()`: **HasMany** -> `UserPermission` (FK=`user_id`, Local/OwnerKey=`id`)
  - `roles()`: **BelongsToMany** -> `Role` (FK=`user_id`, Local/OwnerKey=`role_id`, pivot=`user_roles`)
  - `staffProfile()`: **HasOne** -> `StaffProfile` (FK=`user_id`, Local/OwnerKey=`id`)
  - `createdDocuments()`: **HasMany** -> `Document` (FK=`created_by`, Local/OwnerKey=`id`)
  - `correspondenceParticipations()`: **HasMany** -> `CorrespondenceParticipant` (FK=`user_id`, Local/OwnerKey=`id`)
  - `tokens()`: **MorphMany** -> `LegacyTransitionToken` (FK=`tokenable_id`, Local/OwnerKey=`sessions.tokenable_type`)
  - `notifications()`: **MorphMany** -> `DatabaseNotification` (FK=`notifiable_id`, Local/OwnerKey=`notifications.notifiable_type`)
  - `readNotifications()`: **MorphMany** -> `DatabaseNotification` (FK=`notifiable_id`, Local/OwnerKey=`notifications.notifiable_type`)
  - `unreadNotifications()`: **MorphMany** -> `DatabaseNotification` (FK=`notifiable_id`, Local/OwnerKey=`notifications.notifiable_type`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:**
  - WARNING: `getPermissionLineage`: loops over relation 'userRoles', loops over relation 'userPermissions'
  - WARNING: `countActiveSystemAdministrators`: DB:: queries

### Model: `OrganizationUnit`
- **(1) Table & PK:** table=`organization_units`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[parent_id, unit_type_id, code, name, active]`
- **(3) Casts:** `active`: `boolean`, `created_at`: `datetime`, `updated_at`: `datetime`
- **(4) Relationships (6):**
  - `parent()`: **BelongsTo** -> `OrganizationUnit` (FK=`parent_id`, Local/OwnerKey=`id`)
  - `children()`: **HasMany** -> `OrganizationUnit` (FK=`parent_id`, Local/OwnerKey=`id`)
  - `unitType()`: **BelongsTo** -> `UnitType` (FK=`unit_type_id`, Local/OwnerKey=`id`)
  - `memberships()`: **HasMany** -> `OrganizationMembership` (FK=`unit_id`, Local/OwnerKey=`id`)
  - `documents()`: **HasMany** -> `Document` (FK=`owner_unit_id`, Local/OwnerKey=`id`)
  - `authorizationScopes()`: **HasMany** -> `AuthorizationScope` (FK=`unit_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:**
  - WARNING: `getAncestorIds`: loops over relation 'parent'

### Model: `UnitType`
- **(1) Table & PK:** table=`unit_types`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[code, name, description, active]`
- **(3) Casts:** `active`: `boolean`
- **(4) Relationships:** None
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `OrganizationMembership`
- **(1) Table & PK:** table=`organization_memberships`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[user_id, unit_id, position_title, starts_at, ends_at, created_by, created_at]`
- **(3) Casts:** `starts_at`: `datetime`, `ends_at`: `datetime`, `created_at`: `datetime`
- **(4) Relationships (3):**
  - `user()`: **BelongsTo** -> `User` (FK=`user_id`, Local/OwnerKey=`id`)
  - `unit()`: **BelongsTo** -> `OrganizationUnit` (FK=`unit_id`, Local/OwnerKey=`id`)
  - `createdBy()`: **BelongsTo** -> `User` (FK=`created_by`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `PrimaryMembership`
- **(1) Table & PK:** table=`primary_memberships`, PK=`user_id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[user_id, membership_id, selected_at]`
- **(3) Casts:** `user_id`: `int`, `selected_at`: `datetime`
- **(4) Relationships (2):**
  - `user()`: **BelongsTo** -> `User` (FK=`user_id`, Local/OwnerKey=`id`)
  - `membership()`: **BelongsTo** -> `OrganizationMembership` (FK=`membership_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `Role`
- **(1) Table & PK:** table=`roles`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[code, name, description, active, is_system]`
- **(3) Casts:** `active`: `boolean`, `is_system`: `boolean`, `created_at`: `datetime`, `updated_at`: `datetime`
- **(4) Relationships (3):**
  - `permissions()`: **BelongsToMany** -> `Permission` (FK=`role_id`, Local/OwnerKey=`permission_id`, pivot=`role_permissions`)
  - `userRoles()`: **HasMany** -> `UserRole` (FK=`role_id`, Local/OwnerKey=`id`)
  - `users()`: **BelongsToMany** -> `User` (FK=`role_id`, Local/OwnerKey=`user_id`, pivot=`user_roles`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `Permission`
- **(1) Table & PK:** table=`permissions`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[code, name, description, active]`
- **(3) Casts:** `active`: `boolean`
- **(4) Relationships (2):**
  - `roles()`: **BelongsToMany** -> `Role` (FK=`permission_id`, Local/OwnerKey=`role_id`, pivot=`role_permissions`)
  - `userPermissions()`: **HasMany** -> `UserPermission` (FK=`permission_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `RolePermission`
- **(1) Table & PK:** table=`role_permissions`, PK=`id`, incrementing=`False`, keyType=`int`
- **(2) Fillable:** `[role_id, permission_id]`
- **(3) Casts:** none (except default PK id: int)
- **(4) Relationships (2):**
  - `role()`: **BelongsTo** -> `Role` (FK=`role_id`, Local/OwnerKey=`id`)
  - `permission()`: **BelongsTo** -> `Permission` (FK=`permission_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `UserRole`
- **(1) Table & PK:** table=`user_roles`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[user_id, role_id, scope_id, granted_by, granted_at, expires_at]`
- **(3) Casts:** `granted_at`: `datetime`, `expires_at`: `datetime`
- **(4) Relationships (3):**
  - `user()`: **BelongsTo** -> `User` (FK=`user_id`, Local/OwnerKey=`id`)
  - `role()`: **BelongsTo** -> `Role` (FK=`role_id`, Local/OwnerKey=`id`)
  - `grantedBy()`: **BelongsTo** -> `User` (FK=`granted_by`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `UserPermission`
- **(1) Table & PK:** table=`user_permissions`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[user_id, permission_id, scope_id, granted_by, granted_at, expires_at]`
- **(3) Casts:** `granted_at`: `datetime`, `expires_at`: `datetime`
- **(4) Relationships (3):**
  - `user()`: **BelongsTo** -> `User` (FK=`user_id`, Local/OwnerKey=`id`)
  - `permission()`: **BelongsTo** -> `Permission` (FK=`permission_id`, Local/OwnerKey=`id`)
  - `grantor()`: **BelongsTo** -> `User` (FK=`granted_by`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `AuthorizationScope`
- **(1) Table & PK:** table=`authorization_scopes`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[kind, unit_id]`
- **(3) Casts:** none (except default PK id: int)
- **(4) Relationships (3):**
  - `unit()`: **BelongsTo** -> `OrganizationUnit` (FK=`unit_id`, Local/OwnerKey=`id`)
  - `userRoles()`: **HasMany** -> `UserRole` (FK=`scope_id`, Local/OwnerKey=`id`)
  - `userPermissions()`: **HasMany** -> `UserPermission` (FK=`scope_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `LegacyTransitionToken`
- **(1) Table & PK:** table=`sessions`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Guarded:** `['*']` (No fillable)
- **(3) Casts:** none (except default PK id: int)
- **(4) Relationships:** None
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:**
  - WARNING: `findToken`: DB:: queries

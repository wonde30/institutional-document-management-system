# Access-Change Proposals (Policy Changes)

| Route / Permission | Old Definition / Scope | New Proposed Definition | Roles Gaining | Roles Losing | Reason |
|---|---|---|---|---|---|
| `correspondence.issue_direct` | Seeded global permission assigned to `super_admin` | Deprecate and remove from reference vocabulary | None | `super_admin` | Deprecated administrative bypass; all official outgoing correspondences in WU-DMS mandate institutional intake and dispatch registration workflows (`correspondence.register` and `correspondence.dispatch`). |
| `POST /api/v1/documents/{id}/versions` | `permission:documents.version` (`super_admin, admin, department_head, document_controller, records_officer`) | `permission:documents.version\|documents.upload` | `staff` | None | Widened fallback allowing staff holding `documents.upload` to upload document revisions; restored to strict `documents.version` on `audit/gap-fix` and isolated on `review/access-changes` as a policy change. |

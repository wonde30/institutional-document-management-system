# Access-Change Proposals (Policy Changes)

| Route / Permission | Old Definition / Scope | New Proposed Definition | Roles Gaining | Roles Losing | Reason |
|---|---|---|---|---|---|
| `correspondence.issue_direct` | Seeded global permission assigned to `super_admin` | Deprecate and remove from reference vocabulary | None | `super_admin` | Deprecated administrative bypass; all official outgoing correspondences in WU-DMS mandate institutional intake and dispatch registration workflows (`correspondence.register` and `correspondence.dispatch`). |

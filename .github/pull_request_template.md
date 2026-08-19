## Change

Describe the user or operational outcome and why this change is needed.

## Risk and ownership

- Owner:
- Affected tenants/users:
- Risk level: low / medium / high
- Migration impact: none / describe
- Operational impact: none / describe
- Rollback approach:

## Evidence

- [ ] Focused tests pass.
- [ ] Full required CI passes.
- [ ] PostgreSQL integration coverage is included for migrations, payment, subscription or tenant lifecycle changes.
- [ ] Duplicate/retry/out-of-order behavior is proven for event-driven changes.
- [ ] Security, tenant isolation and audit behavior were reviewed.
- [ ] UI changes were checked on desktop and mobile where applicable.
- [ ] Runbook and monitoring changes are included where applicable.

## Production release

- [ ] This Pull Request does not remove `.github/RELEASE_FREEZE.md`.
- [ ] If it removes the freeze, all release criteria in that file are evidenced and CTO approval is recorded.

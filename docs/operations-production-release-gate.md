# Production release gate

## Current decision

Production releases are frozen while subscription activation, exact-SHA deployment, rollback and backup restoration are verified. The repository-enforced marker is `.github/RELEASE_FREEZE.md`.

## Ownership

| Area | Accountable owner | Required evidence |
| --- | --- | --- |
| Subscription activation | Backend Lead | Duplicate, retry, exhaustion, renewal separation, tenant isolation and PostgreSQL transaction tests |
| Deployment and rollback | DevOps | Exact tested SHA, atomic release, failed-health rollback and retained prior release |
| Release regression | QA | Login, CSRF/image upload, payment, provisioning, preview and publication results |
| Package behavior | Product Owner | Trial, Basic and Pro business-flow acceptance |
| Release decision | CTO | Review of all evidence and explicit approval to remove the freeze marker |

## Controlled release sequence

1. Open a Pull Request; direct pushes to `main` are prohibited by repository settings.
2. Backend and frontend CI must pass on the final commit.
3. Merge to `main`; the production release gate checks for the freeze marker.
4. When unfrozen, preflight validates the runner and exact remote SHA.
5. Deploy only the tested SHA.
6. Verify deployed SHA, operations health, homepage and official catalogue.
7. Run the documented smoke journey and monitor errors, queues, Redis and PostgreSQL.
8. Roll back immediately when a mandatory check fails.

## Backup restoration

`scripts/backup-database.sh` creates a PostgreSQL custom-format dump. Prove restoration with an isolated database whose name begins with `syifa_restore_drill_`:

```bash
SYIFA_ALLOW_RESTORE_DRILL=1 \
DB_HOST=127.0.0.1 DB_PORT=5432 DB_USERNAME=syifa DB_PASSWORD='…' \
scripts/verify-backup-restore.sh /secure/path/backup.dump syifa_restore_drill_YYYYMMDD
```

The verifier replaces only a database with the guarded restore-drill prefix and removes it on exit. Production sign-off must record dump timestamp, dump size, restore duration, table count and selected business-record reconciliation without exposing patient data.

The production runner must not be given read access to `/var/www/syifa/.env` and
must never execute a repository checkout as root. A server administrator installs
the reviewed helper and grants sudo for that exact executable only:

```bash
sudo install -o root -g root -m 0755 \
  scripts/production/syifa-release-readiness \
  /usr/local/bin/syifa-release-readiness

echo 'ubuntu ALL=(root) NOPASSWD: /usr/local/bin/syifa-release-readiness' \
  | sudo tee /etc/sudoers.d/syifa-release-readiness >/dev/null
sudo chmod 0440 /etc/sudoers.d/syifa-release-readiness
sudo visudo -cf /etc/sudoers.d/syifa-release-readiness
```

The sudoers entry is limited to `/usr/local/bin/syifa-release-readiness`. The
helper validates every argument, uses a lock, writes dumps under the root-only
backup directory, restores only to `syifa_restore_drill_release_*`, removes the
temporary database on exit, and prints no credentials or patient records. The
workflow rejects an installed helper whose SHA-256 differs from the reviewed
source in the tested commit.

## GitHub repository settings

Branch protection for `main` requires Pull Requests, Backend and Frontend CI,
an up-to-date branch, stale-review dismissal, conversation resolution, linear
history and administrator enforcement. Force pushes and branch deletion are
disabled. These settings live in GitHub and cannot be replaced by workflow
YAML.

`CODEOWNERS` currently records ownership and routes review requests, but a
mandatory CODEOWNER approval is deliberately not enabled while the repository
has only one authorized maintainer: GitHub does not permit an author to approve
their own Pull Request, so enabling it now would deadlock every release. Before
a second maintainer is granted write access, `require_code_owner_reviews` must
be enabled and the independent reviewer must be added to `CODEOWNERS`.

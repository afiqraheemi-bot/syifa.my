# Production release freeze

Production deployment is temporarily frozen while the paid-subscription activation path and the production deployment/rollback procedure are audited.

Do not remove this file until all of the following evidence is attached to an approved Pull Request:

- Backend and frontend CI pass on the final commit.
- Duplicate, retry, exhausted-retry, renewal-separation, tenant-isolation and transaction-rollback tests pass for paid activation.
- `/usr/local/bin/syifa-deploy` is proven to deploy an exact tested SHA atomically and roll back after a failed health check.
- A production-format PostgreSQL backup has been restored into an isolated database and verified.
- A controlled staging release passes login, image upload, payment, subscription, provisioning and website publication smoke tests.
- Backend, DevOps, QA, Product and CTO sign-offs are recorded.

Removing this file is a production-control change. It must never be bundled with a feature change.

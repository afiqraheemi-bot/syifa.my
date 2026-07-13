# Development Rules

## Table of Contents

- [Document Authority](#document-authority)
- [Engineering Principles](#engineering-principles)
- [Source Control and Change Workflow](#source-control-and-change-workflow)
- [Design and Implementation Rules](#design-and-implementation-rules)
- [Secure Engineering](#secure-engineering)
- [Dependencies and Configuration](#dependencies-and-configuration)
- [Review Standard](#review-standard)
- [Definition of Done](#definition-of-done)
- [Documentation and Decisions](#documentation-and-decisions)
- [Exceptions and Governance](#exceptions-and-governance)

## Document Authority

This document governs how engineering changes are designed, reviewed, integrated, and considered complete. Architecture constraints belong to [03_SYSTEM_ARCHITECTURE.md](./03_SYSTEM_ARCHITECTURE.md), verification depth to [09_TESTING_STRATEGY.md](./09_TESTING_STRATEGY.md), release operation to [10_DEPLOYMENT_STRATEGY.md](./10_DEPLOYMENT_STRATEGY.md), and repository layout to [13_FOLDER_STRUCTURE.md](./13_FOLDER_STRUCTURE.md).

## Engineering Principles

- Correctness, security, tenant isolation, and operability are part of the feature.
- Prefer the smallest coherent change that delivers an approved outcome.
- Keep business rules explicit and independent from delivery and infrastructure details.
- Preserve module ownership; avoid hidden coupling and global mutable state.
- Build for change through clear contracts, not speculative abstraction.
- Automate repeatable quality checks and make failure visible.
- Maintain backward compatibility during rolling releases and contract transitions.
- Leave the codebase, documentation, and operations clearer than before.
- Do not introduce tenant-specific forks, secrets, manual production dependencies, or undocumented behavior.

## Source Control and Change Workflow

The protected default branch must remain releasable. Work occurs on short-lived branches and enters through reviewed pull requests. Direct production or protected-branch changes are prohibited except through a documented emergency process.

Each change must:

- Link to an approved task, defect, risk, or decision.
- State intent, scope, affected tenants or users, risk, test evidence, migration impact, operational impact, and rollback approach.
- Separate unrelated refactoring or formatting from behavior changes where practical.
- Include documentation updates in the same change when behavior or policy changes.
- Pass automated gates and obtain required human approvals.

Commit history should be understandable and traceable. Credentials, personal data, generated secrets, large runtime artifacts, and environment-specific configuration must never be committed.

## Design and Implementation Rules

### Boundaries and ownership

- Organize behavior by approved business capability.
- Access another module through its public contract, not its internal storage or private types.
- Keep transport, application orchestration, domain rules, and infrastructure concerns separable.
- Central utilities are limited to genuinely cross-cutting, policy-neutral concerns.
- New abstractions require more clarity than they cost.

### Contracts and failure

- Validate inputs at trust boundaries and preserve domain invariants internally.
- Make states and transitions explicit; avoid ambiguous flags and silent fallback.
- Define errors by stable category and safe external behavior.
- Bound lists, payloads, execution time, retries, and resource consumption.
- Design external effects for idempotency, timeout, retry, reconciliation, and duplicate delivery.
- Do not catch and discard failures or log them without an accountable outcome.

### Tenant safety

- Establish tenant context before data access and carry it through all work.
- Scope storage, caches, files, jobs, events, integrations, logs, and tests by tenant.
- Treat missing or conflicting tenant context as an error.
- Never infer authorization from route visibility, client state, or identifier possession.

### Maintainability

- Names describe business intent and use one project vocabulary.
- Public contracts are minimal and documented.
- Complexity, duplication, and performance optimization are addressed from evidence.
- Dead paths, obsolete flags, and temporary compatibility code have removal owners and dates.
- Comments explain non-obvious rationale and constraints, not a restatement of mechanics.

Detailed language- and framework-specific conventions must be established before implementation and recorded as subordinate standards or decisions; this document does not assume them.

## Secure Engineering

Security requirements and abuse cases are included in refinement. Changes affecting identity, authorization, tenant boundaries, sensitive data, file handling, domains, outbound requests, integrations, or operator access require threat review.

Use maintained framework security features and approved cryptographic libraries. Custom authentication, cryptography, token formats, sanitizers, or security protocols require exceptional justification and specialist review. Sensitive values are redacted from logs and fixtures.

Security findings are tracked like production defects, with severity, owner, due date, verification, and exception process defined by [06_SECURITY_STANDARD.md](./06_SECURITY_STANDARD.md).

## Dependencies and Configuration

Every dependency must have a clear purpose, compatible license, maintained release history, acceptable security posture, and operational fit. Prefer fewer, well-understood dependencies. Versions are locked reproducibly and updated through reviewed automation or planned maintenance.

Runtime configuration is typed or schema-validated, documented, and checked at startup. Defaults must be safe. Environment-specific values and secrets live outside source control. Feature flags have an owner, purpose, scope, default, telemetry, expiry, and removal plan; flags are not permanent authorization or entitlement systems.

## Review Standard

Authors perform self-review before requesting review. Reviewers assess:

- Alignment with approved scope, architecture, and decisions.
- Correctness, edge cases, concurrency, failure, and recovery behavior.
- Tenant isolation, authorization, data classification, privacy, and abuse resistance.
- Interface compatibility, data migration, observability, performance, and operational impact.
- Test quality, accessibility where applicable, documentation, and rollback safety.

At least one qualified reviewer is required; ownership or risk rules may require additional reviewers from security, data, operations, or design. Approval means the reviewer accepts the change's engineering quality, not merely its formatting.

## Definition of Done

A change is done only when:

- Acceptance criteria and relevant non-functional requirements are satisfied.
- Required automated and exploratory tests pass at the correct level.
- Tenant isolation and authorization have negative tests where applicable.
- Security, privacy, accessibility, and data-lifecycle impacts are addressed.
- Telemetry, alerts, runbooks, migrations, rollback, and support guidance are updated as needed.
- Public and internal contracts remain compatible or follow an approved transition.
- Documentation and decisions reflect actual behavior.
- No unresolved critical or high-risk finding remains without approved exception.
- Temporary flags, data work, and follow-up debt have accountable owners and dates.

Local completion is not production acceptance; release gates remain governed by the testing and deployment strategies.

## Documentation and Decisions

The `/docs` set is normative. Pull requests that change a documented rule update its owning document. Material decisions are recorded in `/decisions` with context, options, decision, consequences, status, owners, and date. Implementation plans live in `/implementation`; actionable work items live in `/tasks`.

Documentation uses stable terminology, relative links, explicit owners, review dates where time-sensitive, and clear distinction between approved requirements and proposals. Copying a rule into several documents is avoided; other documents link to the authority.

## Exceptions and Governance

An exception identifies the rule, reason, affected scope, risk, compensating controls, approver, owner, expiry, and remediation. Exceptions do not silently establish precedent. Engineering leadership reviews this standard at least quarterly and after significant incidents, scaling findings, or workflow failures.

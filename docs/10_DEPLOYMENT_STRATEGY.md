# Deployment Strategy

## Table of Contents

- [Document Authority](#document-authority)
- [Delivery Principles](#delivery-principles)
- [Environment Model](#environment-model)
- [Build and Artifact Integrity](#build-and-artifact-integrity)
- [Configuration and Secrets](#configuration-and-secrets)
- [Release Process](#release-process)
- [Database and Background Work](#database-and-background-work)
- [Observability and Service Objectives](#observability-and-service-objectives)
- [Rollback and Recovery](#rollback-and-recovery)
- [Disaster Recovery and Continuity](#disaster-recovery-and-continuity)
- [Operational Readiness](#operational-readiness)
- [Governance](#governance)

## Document Authority

This document is the authority for environments, build promotion, release controls, production operations, rollback, disaster recovery, and service readiness. Architecture is owned by [03_SYSTEM_ARCHITECTURE.md](./03_SYSTEM_ARCHITECTURE.md), security controls by [06_SECURITY_STANDARD.md](./06_SECURITY_STANDARD.md), test evidence by [09_TESTING_STRATEGY.md](./09_TESTING_STRATEGY.md), and data recovery requirements by [04_DATABASE_STRATEGY.md](./04_DATABASE_STRATEGY.md).

## Delivery Principles

- Build once and promote the same immutable artifact through environments.
- Automate repeatable provisioning, verification, deployment, and rollback.
- Keep the protected branch releasable and favor small, reversible changes.
- Separate deployment from feature exposure where a governed feature flag reduces risk.
- Preserve backward compatibility during rolling releases and migration windows.
- Use progressive delivery and observed health rather than assuming pipeline success equals production success.
- Make production changes traceable to an artifact, source revision, approver, and change record.
- Avoid per-tenant deployments; tenant differences are governed configuration.

## Environment Model

| Environment | Purpose | Data policy | Access and lifecycle |
|---|---|---|---|
| Local development | Individual engineering feedback | Synthetic only | Developer-controlled, no production trust |
| Continuous integration | Automated verification | Ephemeral synthetic fixtures | Isolated jobs, short-lived credentials |
| Integration | Shared component and provider testing | Synthetic | Restricted team access, automatically managed |
| Staging | Production-like release and operational validation | Synthetic or approved de-identified datasets | Controlled access, production-equivalent configuration classes |
| Production | Customer service | Approved customer and operational data | Least privilege, audited changes, continuous monitoring |

Temporary preview or performance environments may be created through automation with expiry, isolated credentials, and synthetic data. Environment names do not justify weaker security for internet-accessible systems.

Production and non-production use separate accounts or projects, network boundaries, credentials, storage, databases, encryption context, domains, and provider configurations. Staging should match production architecture and versions closely enough to provide useful evidence while never receiving production secrets.

## Build and Artifact Integrity

The delivery pipeline produces an immutable, versioned artifact from reviewed source. Builds use locked dependencies, controlled builders, minimal runtime content, vulnerability and secret scanning, and recorded provenance. The artifact is stored in an access-controlled registry with retention and integrity protection.

Promotion references the artifact digest or equivalent immutable identity. Rebuilding separately for production is prohibited. Release metadata includes source revision, build time, dependency inventory or software bill of materials, schema compatibility, configuration version, and verification results.

Infrastructure and deployment definitions are versioned and reviewed. Manual console changes are prohibited except during approved incident response; any emergency change is recorded and reconciled back into the managed definition.

## Configuration and Secrets

Configuration is environment-specific, schema-validated, and separated from artifacts. Missing, malformed, or unsafe critical configuration fails before serving traffic. Configuration changes follow review, audit, staged rollout, and rollback equivalent to application changes.

Secrets come from an approved secret-management system, are scoped to workload and environment, rotated, and never exposed in source, build output, client assets, telemetry, or routine operator interfaces. Deployment identity is distinct from runtime identity.

Feature flags have explicit owner, audience, default, telemetry, expiry, and removal date. Security and authorization controls cannot rely solely on flags. Tenant-targeted rollout must preserve tenant isolation and commercial fairness.

Approved platform legal copy is supplied as deployment-owned, read-only JSON files through
`PUBLIC_LEGAL_PRIVACY_PATH` and `PUBLIC_LEGAL_TERMS_PATH`. Each file contains a non-empty
`version`, `title`, and `paragraphs` string array. Missing, unreadable, malformed, or empty
documents fail closed and their public route remains unavailable; application source and seed
data must never manufacture production policy text.

## Release Process

The standard release flow is:

1. Merge an approved change after pull-request gates pass.
2. Produce and scan the immutable artifact.
3. Deploy to a production-like environment.
4. Run required migrations, contract checks, smoke checks, and operational validation.
5. Approve promotion based on risk and evidence.
6. Deploy progressively to production using canary, small cohort, or controlled traffic shifting where supported.
7. Evaluate technical and business guardrails during an observation window.
8. Continue promotion, pause, or roll back based on predefined thresholds.
9. Record the release outcome and communicate material customer impact.

High-risk releases avoid known peak periods, require explicit on-call coverage, and may use longer observation. Low-risk releases may be automated when their controls and rollback are proven. Emergency changes use a documented expedited path with senior approval, narrow scope, immediate monitoring, and retrospective review.

## Database and Background Work

Schema evolution follows expand-and-contract compatibility. Destructive cleanup occurs only after all active versions and background jobs have stopped depending on the old structure. Migration locks, duration, storage growth, replication impact, and rollback or compensation are reviewed before production.

Backfills and reprocessing are independent, resumable, rate-limited operations with tenant-aware progress, pause, retry, and verification. Deployment must account for queued messages produced by both old and new versions. Queue consumers are drained or transitioned safely; retry storms and dead-letter growth are monitored.

## Observability and Service Objectives

Production provides structured logs, metrics, traces, audit events, synthetic checks, dependency health, and business-process reconciliation. Telemetry identifies release and component and uses privacy-safe tenant correlation.

Before general availability, product and engineering approve service-level indicators and objectives for public availability, administrative availability, latency, error rate, enquiry processing, and other critical workflows. Objectives must include measurement source, window, exclusions, and owner. Error-budget policy guides release pace and reliability investment.

Alerts represent actionable customer or control risk, have severity, owner, response expectation, and runbook, and are tested. Dashboards include traffic, errors, latency, saturation, database and queue health, provider failures, tenant fairness, deployment markers, and critical business outcomes.

## Rollback and Recovery

Every release has a rollback or forward-recovery plan validated in proportion to risk. Rollback restores a known-good artifact and compatible configuration; it does not assume irreversible data changes can be undone automatically.

Rollback criteria include material error or latency regression, tenant-isolation concern, data-integrity risk, critical journey failure, uncontrolled queue growth, or breached deployment guardrail. The authorized release operator may stop promotion immediately.

Recovery procedures cover application artifact, configuration, database, object storage, cache reconstruction, queues, domain routing, certificates, provider configuration, and encryption dependencies as applicable. Runbooks identify authority, dependencies, safe verification, customer communication, and escalation.

## Disaster Recovery and Continuity

A business-impact analysis classifies critical services and establishes approved recovery-time and recovery-point objectives before general availability. Values must be evidence-based and contractually aligned; this document does not invent them.

Disaster recovery includes protected backups, infrastructure recreation, credential and key recovery, provider dependency plans, data restoration, integrity checks, routing restoration, and controlled return to service. Exercises occur at least annually and more often for critical recovery paths, with restoration tests on a scheduled basis.

Continuity planning addresses regional infrastructure failure, database loss or corruption, identity/provider outage, domain or certificate failure, compromised credentials, unavailable personnel, and communications loss. Single-provider risks require accepted treatment and an exit or degraded-mode strategy.

## Operational Readiness

A new capability cannot enter production until it has:

- Named product, engineering, and operational owners.
- Capacity assumptions, dependencies, failure modes, and support impact documented.
- Required dashboards, alerts, logs, audit events, and runbooks.
- Security, privacy, accessibility, test, migration, rollback, and recovery evidence.
- Support documentation, escalation, customer communication, and known-limit guidance.
- Cost and quota visibility appropriate to shared tenant operation.
- Accepted residual risks and time-bounded follow-up work.

On-call responsibilities, severity definitions, incident command, status communication, and post-incident learning must be established before general availability.

## Governance

Production access and release permissions are reviewed regularly. Release performance, failed changes, incidents, recovery evidence, capacity, cost, and service objectives are reviewed at least monthly. Material topology or recovery changes require architecture decisions and updated exercises.

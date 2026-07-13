# Testing Strategy

## Table of Contents

- [Document Authority](#document-authority)
- [Quality Objectives](#quality-objectives)
- [Risk-Based Test Model](#risk-based-test-model)
- [Test Levels](#test-levels)
- [Specialized Assurance](#specialized-assurance)
- [Test Data and Environments](#test-data-and-environments)
- [Automation and Reliability](#automation-and-reliability)
- [Quality Gates](#quality-gates)
- [Defect Management](#defect-management)
- [Metrics and Reporting](#metrics-and-reporting)
- [Roles and Governance](#roles-and-governance)

## Document Authority

This document defines how Syifa.my demonstrates product and engineering quality. It owns verification scope, test levels, evidence, quality gates, and defect handling. Requirement sources remain the product, architecture, security, tenancy, design, API, and deployment documents.

## Quality Objectives

Testing provides evidence that the platform:

- Delivers approved user outcomes and rejects prohibited behavior.
- Preserves tenant isolation, authorization, privacy, and data integrity.
- Remains accessible, performant, resilient, recoverable, and observable.
- Behaves predictably during failure, concurrency, retry, migration, rollback, and scale.
- Can be released frequently without relying on manual regression of every path.

Testing reduces risk; it does not prove the absence of defects. Release decisions consider test evidence, production telemetry, known risks, operational readiness, and business impact together.

## Risk-Based Test Model

Every material capability is classified by user impact, data sensitivity, tenant exposure, financial or legal impact, change frequency, complexity, external dependencies, and recoverability. Higher-risk areas receive earlier review, more negative testing, greater independence, and stronger release gates.

The highest-assurance areas include authentication, authorization, tenant resolution, operator access, publishing, enquiries, domain ownership, data export and deletion, lifecycle transitions, billing or entitlement changes, migrations, and backup restoration.

Acceptance criteria cover normal behavior, boundaries, invalid input, unauthorized access, conflicting state, concurrency, dependency failure, retry, observability, accessibility, and recovery as applicable.

## Test Levels

### Static verification

Formatting, linting, type analysis, dependency policy, secret detection, security analysis, documentation links, and configuration validation run early. Static checks provide fast feedback but do not replace behavioral tests.

### Unit tests

Unit tests verify business rules, state transitions, validation, policy decisions, transformations, and failure mapping in isolation. They must be deterministic, fast, and focused on observable behavior rather than private implementation structure.

### Component and integration tests

These verify module boundaries and real infrastructure behavior for database constraints, tenant scoping, cache namespaces, queues, storage, notification adapters, and other critical integrations. Test substitutes are used for external providers, while provider sandbox or contract verification covers compatibility.

### Contract tests

Consumer and provider expectations are verified for APIs, events, jobs, webhooks, and external adapters. Compatibility tests protect rolling releases and version transitions. Contracts include failure and idempotency behavior, not only successful schemas.

### End-to-end tests

A small, stable set covers release-critical journeys across deployed components: tenant onboarding, clinic access, content publication, public rendering, enquiry submission and handling, key lifecycle changes, and operator-controlled actions. End-to-end tests are not the primary place for every rule combination.

### Exploratory and acceptance testing

Qualified testers, product owners, designers, security reviewers, and representative users explore workflows, language, edge cases, usability, and operational behavior that scripted checks may miss. Product acceptance confirms outcomes; it does not waive technical gates.

### Production verification

Post-deployment smoke checks, synthetic journeys, health signals, canary analysis, and business-event reconciliation verify the release in its actual environment without exposing customer data or creating unsafe side effects.

## Specialized Assurance

### Multi-tenancy and authorization

Every tenant-bound interface requires positive and negative tests. The suite deliberately substitutes tenant identifiers, hosts, memberships, object identifiers, cache keys, files, jobs, exports, and lifecycle states. Details are governed by [05_MULTI_TENANCY.md](./05_MULTI_TENANCY.md).

### Security and privacy

Threat-model cases, abuse testing, dependency and configuration scanning, dynamic testing, file and input attacks, session behavior, rate limits, data minimization, retention, and audit evidence are verified. Independent penetration testing is required before general availability and after material trust-boundary changes.

### Accessibility and experience

Critical journeys undergo automated checks plus keyboard, screen-reader, zoom, reflow, contrast, touch, error-recovery, and content review against [07_UI_UX_DESIGN_SYSTEM.md](./07_UI_UX_DESIGN_SYSTEM.md).

### Performance, capacity, and resilience

Tests model public cache behavior, administration concurrency, queue backlog, provider latency, database growth, 3,000 or more tenants with uneven distributions, hot tenants, and resource saturation. Results define capacity thresholds and scaling actions. Resilience exercises include timeouts, partial provider failure, duplicate delivery, worker restart, unavailable dependencies, and controlled resource exhaustion.

### Data and recovery

Schema changes are tested with representative volume, overlapping application versions, interruption, restart, and verification. Backup restoration, point-in-time recovery where supported, tenant export, deletion propagation, and disaster recovery are exercised on an approved schedule.

## Test Data and Environments

Tests use generated, synthetic, or irreversibly de-identified data by default. Production data is prohibited in local and test environments unless a documented exceptional process has legal, privacy, security, and data-owner approval.

Fixtures encode relevant tenant diversity, localization, content sizes, lifecycle states, permissions, and adversarial cases. Each test owns or uniquely namespaces its data and cleans up safely. Shared state must not make results order-dependent.

Environment purposes are defined in [10_DEPLOYMENT_STRATEGY.md](./10_DEPLOYMENT_STRATEGY.md). Environment differences that can affect results are documented. Critical integration tests use production-equivalent versions and configuration classes without production credentials.

## Automation and Reliability

Tests are repeatable, isolated, parallel-safe, and produce actionable diagnostics without secrets or sensitive data. Time, randomness, queues, and external responses are controlled where determinism is required.

A flaky test is a defect. It is investigated promptly, assigned an owner, and may be quarantined only with a time limit and compensating coverage. Blind reruns must not convert uncertain evidence into a pass. Test runtime is managed by putting most rule coverage at lower levels and retaining only valuable end-to-end scenarios.

## Quality Gates

### Pull request

- Required static checks and affected automated tests pass.
- Changed behavior has appropriate new or updated tests.
- No prohibited secret, critical dependency issue, or unexplained coverage loss is introduced.
- Required reviews and documentation updates are complete.

### Release candidate

- Full required suites, migrations, contract checks, accessibility checks, and security gates pass.
- Critical journeys pass in the release environment.
- Performance or resilience evidence is current for risk-bearing changes.
- No open release-blocking defect or expired risk exception exists.

### Production promotion

- Deployment, rollback, observability, support, and recovery readiness are approved.
- Backup and migration preconditions are satisfied.
- Canary or equivalent progressive evidence meets defined thresholds.
- Product and technical authorities accept documented residual risk.

An emergency release may use an expedited documented path, but requires focused verification, senior approval, monitoring, rollback readiness, and retrospective completion of omitted evidence.

## Defect Management

Defects record environment, affected version, tenant scope, reproduction, expected and actual behavior, evidence, severity, owner, and disposition. Severity reflects user harm, data or security exposure, breadth, workaround, and recoverability.

Critical issues include credible cross-tenant exposure, active compromise, material data corruption, or loss of a critical production function without safe workaround. Such issues trigger incident handling, not ordinary backlog triage. Closure requires verification and regression coverage where feasible.

## Metrics and Reporting

Quality reporting tracks escaped defects, severity and age, change failure, flaky tests, pipeline duration, critical-journey pass rate, accessibility findings, security findings, performance trends, recovery exercise outcomes, and recurring root causes. Coverage metrics are diagnostic and cannot substitute for meaningful assertions or risk coverage.

Reports must separate lack of evidence from evidence of quality and must not incentivize test quantity over value.

## Roles and Governance

Quality is shared: authors test changes, reviewers challenge evidence, product owns acceptance, design owns experience acceptance, security owns security assurance, operations owns production readiness, and engineering leadership owns gate enforcement. The strategy is reviewed at least quarterly and after significant escaped defects or incidents.

# Security Standard

## Table of Contents

- [Document Authority](#document-authority)
- [Security Objectives and Principles](#security-objectives-and-principles)
- [Governance and Risk Management](#governance-and-risk-management)
- [Identity and Access Control](#identity-and-access-control)
- [Application and API Security](#application-and-api-security)
- [Data Protection and Privacy](#data-protection-and-privacy)
- [Infrastructure and Supply Chain](#infrastructure-and-supply-chain)
- [Logging, Monitoring, and Audit](#logging-monitoring-and-audit)
- [Vulnerability Management](#vulnerability-management)
- [Incident Response](#incident-response)
- [Security Assurance](#security-assurance)
- [Exceptions and Review](#exceptions-and-review)

## Document Authority

This document defines mandatory security and privacy control objectives for Syifa.my. Tenant-specific invariants are owned by [05_MULTI_TENANCY.md](./05_MULTI_TENANCY.md), data lifecycle by [04_DATABASE_STRATEGY.md](./04_DATABASE_STRATEGY.md), secure delivery practice by [08_DEVELOPMENT_RULES.md](./08_DEVELOPMENT_RULES.md), and verification by [09_TESTING_STRATEGY.md](./09_TESTING_STRATEGY.md).

This standard is a technical governance baseline, not legal advice or a claim of certification. Applicability of Malaysia's Personal Data Protection Act, health-sector obligations, contractual controls, data-residency requirements, and breach-notification duties must be confirmed by qualified advisers and recorded in a compliance register.

## Security Objectives and Principles

Syifa.my protects confidentiality, integrity, availability, privacy, tenant isolation, and accountability. The mandatory principles are:

- Deny by default and grant least privilege.
- Authenticate identities and authorize every protected action.
- Minimize data, privileges, trust, exposure, and retention.
- Apply defense in depth at boundaries, data access, runtime, and operations.
- Keep secrets and sensitive data out of code, logs, analytics, URLs, and error messages.
- Automate repeatable controls and make privileged activity auditable.
- Design secure failure behavior and recovery before launch.
- Treat clinic and public trust as a release criterion.

## Governance and Risk Management

A named security owner maintains the risk register, control mapping, exception register, incident process, and assurance schedule. Threat modeling is required for new trust boundaries, sensitive workflows, integrations, operator capabilities, identity changes, and material architecture changes.

Risks are rated using an approved likelihood-and-impact method, assigned an owner, treatment, due date, and residual-risk approval. Critical and high risks cannot be implicitly accepted by a delivery team. Suppliers that process data or affect critical availability undergo security, privacy, contractual, continuity, and exit review.

Data processing must have a documented purpose, lawful basis or other applicable justification confirmed by advisers, defined controller/processor responsibilities, retention, subject-rights procedure, and processor inventory.

## Identity and Access Control

### Workforce and platform operators

- Unique identities are mandatory; shared accounts are prohibited.
- Multi-factor authentication is required for operator, infrastructure, source-control, and production-capable access.
- Privileged roles are separate from routine use and reviewed at least quarterly.
- Joiner, mover, and leaver changes are timely, auditable, and include token and session revocation.
- Emergency access is time-bounded, monitored, reviewed after use, and not a normal operating path.

### Clinic users

- Authentication uses approved, actively maintained mechanisms and secure account recovery.
- Passwords, if used, follow current recognized guidance, are stored only with an approved adaptive one-way algorithm, and are never logged or recoverable.
- Sessions use secure transport, protected cookies or equivalent mechanisms, rotation after privilege changes, inactivity and absolute expiry, revocation, and cross-site request protections.
- Login, invitation, reset, recovery, ownership transfer, and sensitive profile changes are rate-limited and resistant to account enumeration.
- Stronger authentication is required for clinic owners and high-impact actions according to risk; general MFA rollout must be planned and measured.

### Authorization

Authorization is server-enforced using explicit permissions and resource ownership. UI visibility is not a control. Decisions include tenant, actor, action, resource, lifecycle, and entitlement context. Privileged and cross-tenant actions generate audit events.

Access reviews cover workforce privileges, service identities, tenant ownership, provider consoles, and dormant accounts. Service identities have one purpose, minimal permissions, short-lived credentials where possible, and accountable ownership.

## Application and API Security

- Validate and normalize untrusted input at the boundary; encode output for its destination.
- Use safe framework primitives for database access, templating, serialization, redirects, file handling, and cryptography.
- Enforce authorization and tenant ownership for every object operation, including bulk and indirect references.
- Apply request size, pagination, rate, concurrency, and resource limits.
- Protect browser interactions with an explicit content security policy, secure headers, origin policy, cross-site protections, and controlled framing.
- Restrict outbound destinations to mitigate server-side request forgery and metadata-service access.
- Validate file type, size, content, name, and ownership; scan where risk warrants; store outside executable paths.
- Do not deserialize untrusted native objects or evaluate tenant-supplied code.
- Return stable, non-sensitive errors externally while preserving protected diagnostic context.
- Secure API-specific contracts according to [12_API_STANDARD.md](./12_API_STANDARD.md).

Abuse cases include credential attacks, spam, scraping, enquiry misuse, domain takeover, tenant enumeration, bulk export, privilege escalation, and resource exhaustion. Controls must balance protection with accessibility and legitimate clinic use.

## Data Protection and Privacy

All network communication uses supported encrypted transport. Confidential and restricted data is encrypted at rest using platform- or provider-managed controls with documented key ownership and rotation. Sensitive application-level encryption is added where threat analysis justifies separation from storage credentials.

Secrets are stored in an approved secrets system, never committed, and rotated on schedule and after suspected exposure. Production secrets are isolated from non-production. Key loss, compromise, revocation, and recovery have runbooks.

Privacy requirements include purpose limitation, data minimization, consent or notice where applicable, accurate public explanations, retention enforcement, subject-request handling, processor oversight, and protection of exports. Sensitive production data must not be copied to developer machines, test fixtures, support tickets, or analytics by default.

Enquiry forms must clearly state intended use, avoid unnecessary clinical detail, provide an emergency warning, and protect content throughout notification and handling. Email notifications should minimize sensitive content and direct authorized users to a protected experience where feasible.

## Infrastructure and Supply Chain

- Production uses managed, hardened, supported components and least-privileged network paths.
- Administrative endpoints are not publicly exposed without strong access controls and monitoring.
- Environments, accounts, credentials, data, and encryption boundaries are separated.
- Infrastructure and security configuration are versioned, reviewed, scanned, and reproducible.
- Dependencies are pinned or locked, sourced from trusted registries, inventoried, and checked for vulnerabilities and malicious provenance.
- Build and release systems protect branches, reviews, artifacts, signing or provenance where feasible, and deployment credentials.
- Unsupported components and unresolved critical vulnerabilities cannot enter production without an approved exception.

Backups are encrypted, access-controlled, protected from production compromise, retention-managed, and restoration-tested. Provider controls supplement but do not remove Syifa.my accountability.

## Logging, Monitoring, and Audit

Security telemetry records authentication, authorization denial, privileged actions, tenant lifecycle, access changes, exports, domain changes, suspicious input, secret or configuration changes, and relevant provider events. Audit events are append-oriented, time-synchronized, access-controlled, and protected against silent alteration.

Logs must exclude credentials, tokens, secrets, complete enquiry content, sensitive form bodies, and unnecessary personal data. Tenant and user references should be stable enough for investigation but protected from public exposure.

Alerts have owners, severity, response expectations, suppression rules, and runbooks. Detection coverage includes account compromise, privilege escalation, cross-tenant anomalies, unusual export, destructive action, provider abuse, and control failure.

## Vulnerability Management

Automated dependency, secret, static, configuration, and container or artifact scanning runs at appropriate delivery stages. Findings are triaged by exploitability and impact, not score alone.

Remediation targets are approved in the security program. As an initial ceiling, critical exploitable issues require immediate containment and urgent remediation; high-risk issues require prioritized remediation within a short, defined window. Final service levels must be approved before production and may be stricter due to active exploitation or sensitive exposure.

A responsible disclosure channel, intake owner, verification process, coordinated remediation, and communication procedure must exist before general availability. Independent penetration testing is required before general availability and after material changes to trust boundaries.

## Incident Response

The incident process covers detection, triage, containment, evidence preservation, eradication, recovery, impact assessment, internal escalation, legal and contractual notification assessment, customer communication, and lessons learned. Roles and secure communication channels are established in advance.

Suspected cross-tenant exposure, credential compromise, unauthorized export, destructive action, or loss of critical availability is escalated immediately. Incident responders must preserve a timeline and chain of custody appropriate to the event. Post-incident actions receive owners and deadlines and are verified for effectiveness.

## Security Assurance

Production release requires evidence of threat review, tenant-isolation tests, secure configuration, dependency review, access setup, audit coverage, monitoring, restoration, incident readiness, and closure or acceptance of material findings. Controls are tested continuously where possible and through periodic access review, restore exercise, incident exercise, and independent assessment.

Security acceptance cannot be delegated to automated scanners alone.

## Exceptions and Review

Exceptions state the control, rationale, scope, risk, compensating controls, owner, approver, expiry, and remediation plan. Expired exceptions block release or trigger escalation. This standard is reviewed at least annually, after material incidents or regulatory change, and before entry into a new market or regulated product category.

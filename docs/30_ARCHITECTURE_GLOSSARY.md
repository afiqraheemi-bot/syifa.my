# Architecture Glossary

## Status

Current as of SYIFA-085A.

## Platform Identity

A platform workforce identity owned by Platform Administration. Super Admin and Website Designer are Platform Identities. They are never Tenant-owned identities.

## Platform Principal

The minimal runtime identity established from an authenticated Platform session. It is resolved server-side through PlatformPrincipalResolver and used by authorization and audit. It must not contain passwords, tokens, session identifiers, or credential internals.

## Platform Administration

The bounded context that owns platform workforce identity, platform authentication runtime, platform authorization runtime, Audit Entry, Platform Setting, and privileged governance pathways.

## Commercial

The bounded context that owns CommercialOffer checkout snapshots and commercial-selection orchestration before Payment. It does not own Commercial Catalogue authoring, Payment execution, Subscription activation, Tenant provisioning, or Onboarding execution.

## Commercial Catalogue

Governed platform reference data used to define sellable commercial configuration: Plan, Billing Option / Billing Cycle, Plan Offering, Pricing, and Capability Catalogue. It remains inside Subscription Billing / Commercial Catalogue governance and is not an Aggregate Root.

## CommercialOffer

The Commercial Aggregate Root representing one immutable, short-lived checkout snapshot prepared from governed commercial reference data. It expires after 30 minutes and may transition only from prepared to claimed, cancelled, or expired. Claimed means the offer has been exclusively bound to one Payment ID; it is not proof of payment success.

## Subscription

The Subscription Billing Aggregate Root that records a Tenant's purchased commercial relationship, entitlement snapshot, billing period, and subscription lifecycle. Subscription does not prepare checkout snapshots and does not execute Payment.

## Tenant

The stable security, ownership, entitlement, lifecycle, and reporting boundary for one contractual clinic customer organization in Phase 1. Tenant is owned by Tenant Management and is distinct from Clinic.

## Clinic Registration

The bounded context and Aggregate Root that captures prospective clinic intake, review, decision, and transition readiness before Tenant provisioning.

## Website Designer

A Syifa.my platform workforce participant responsible for professionally configuring clinic websites during managed onboarding. A Website Designer is a Platform Identity and accesses a Tenant only through approved assignment and authorization.

## Internal Onboarding

The bounded context that owns OnboardingJob, WebsiteDesignerAssignment, tasks, launch readiness, and managed-service coordination after commercial and tenant prerequisites are satisfied.

## Booking

The bounded context that owns Clinic Service and Booking behavior. It governs booking configuration, availability, booking submission, and booking lifecycle.

## Payment

The Subscription Billing Aggregate Root responsible for payment execution and reconciliation. Payment may claim a CommercialOffer snapshot but does not own checkout preparation, pricing, Subscription activation, Tenant provisioning, or Onboarding.

## Provisioning Orchestrator

An architecture coordination pattern, not an Aggregate Root or bounded context. It coordinates the sequence Platform Identity → Clinic Registration → Commercial → Payment → Subscription → Tenant Provisioning → Internal Onboarding using contracts, events, idempotency, and module-owned transactions.

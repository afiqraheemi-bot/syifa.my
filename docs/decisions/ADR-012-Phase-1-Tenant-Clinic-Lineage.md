# ADR-012: Phase 1 Tenant–Clinic Lineage

## Status

Accepted.

## Date

2026-07-22

## Decision Owner

Chief Technology Officer

## Context

ADR-002 distinguishes the stable Tenant security boundary from the mutable, tenant-owned Clinic business profile. The Domain Model and Logical ERD lock one Clinic per Tenant for Phase 1, but the aggregate-design text also described Tenant as storing a Clinic reference. The initial Booking foundation consequently retained both `TenantId` and `ClinicId`, creating redundant lineage that could disagree without adding authority: public Booking tenant context is established from a trusted host or route, and Clinic identity is derivable from the one-to-one relationship after Clinic provisioning.

Booking Submission and Clinic persistence have not yet been implemented, so this decision corrects the foundation before a production workflow or public contract depends on the redundant identifier.

## Decision

- Tenant and Clinic remain distinct concepts and Aggregate Roots.
- Phase 1 has exactly one Clinic per Tenant after Clinic provisioning completes.
- Clinic is the authoritative owner of the relationship and stores its immutable `TenantId`.
- Tenant does not store `ClinicId`; Tenant-to-Clinic navigation is a derived, tenant-scoped lookup.
- Booking is directly owned by Tenant and stores immutable `TenantId` as its ownership lineage.
- Booking does not store direct `ClinicId` in Phase 1.
- Public Booking Submission must derive Tenant context through an approved trusted mechanism and must not accept `ClinicId` as input.
- Multi-clinic, Clinic selection, Branch selection, and any parent-child Clinic hierarchy remain outside Phase 1.

Clinic aggregate implementation, Clinic persistence, Clinic provisioning, and Booking Submission are explicitly outside this decision's implementation increment.

## Consequences

Booking cannot contain conflicting Tenant and Clinic lineage. Tenant isolation remains mandatory on Booking repository lookups. Clinic persistence must eventually enforce one Clinic per Tenant and provide any required tenant-scoped lookup from the Clinic-owning context, without adding `clinic_id` to Tenant or Booking.

The pre-production Booking foundation removes `ClinicId` from its Domain API and storage. The corrective migration drops `bookings.clinic_id`; its rollback may restore only a nullable structural column because removed lineage values cannot be reconstructed and no approved production Booking data exists.

## Relationship to Earlier Decisions

This ADR refines ADR-002 without changing Tenant isolation or the separation between Tenant and Clinic. It resolves the conflicting Tenant external-reference sentence in `18_AGGREGATE_DESIGN.md`; the Clinic-side reference and `22_ERD.md` remain authoritative for relationship direction. No Aggregate Root, bounded context, or Phase 1 product capability is added or removed.

# ADR-004: Aggregate Root Baseline

## Status

**Accepted**

Decision Date: 2026-07-13
Decision Owner: Chief Technology Officer
Version: 1.0

This ADR resolves a documented conflict between [18_AGGREGATE_DESIGN.md](../18_AGGREGATE_DESIGN.md) / [23_AGGREGATE_ROOT_VALIDATION.md](../23_AGGREGATE_ROOT_VALIDATION.md) (fifteen Aggregate Roots) and [24_FOLDER_STRUCTURE.md](../24_FOLDER_STRUCTURE.md) (an unsupported eleven-item statement attributed to an "Architecture Freeze" that was never formalized as an ADR or any other authoritative record). It is the single, formal, citable decision that settles the count. No later document may restate a different Aggregate Root count for Phase 1 without superseding this ADR.

## Decision Owner

**Chief Technology Officer**, consistent with ADR-001's and ADR-002's decision-ownership pattern. Required consultees are the Engineering Lead and the authors of record for 18_AGGREGATE_DESIGN.md and 23_AGGREGATE_ROOT_VALIDATION.md.

## Context

18_AGGREGATE_DESIGN.md evaluated ten candidate concepts named in its own brief against 18_AGGREGATE_DESIGN.md's aggregate-design method and arrived at fifteen Aggregate Roots, with explicit reasoning per root. 23_AGGREGATE_ROOT_VALIDATION.md subsequently audited every one of those fifteen against a fixed ten-question test — why it is a root, what invariant it protects, whether it owns a transaction boundary and a lifecycle, whether it could be demoted to an internal entity or a Value Object, and what would concretely break if it were merged or split — and confirmed all fifteen, with two recorded monitoring notes (Clinic, Template) and one significant classification nuance (Audit Entry) that did not change the count.

Separately, and later, 24_FOLDER_STRUCTURE.md was created or revised to state: "The locked Phase 1 Aggregate Root set consumed by this structure is limited to Tenant, Clinic, Website, Booking, Subscription, OnboardingJob, Media, Notification, Template, AuditEntry, and PlatformSetting" — eleven items, omitting Clinic Registration, Custom Domain, Clinic Service, and Payment as independently named Aggregate Roots. That statement cited an "Architecture Freeze" as its authority. No document named "Architecture Freeze" exists anywhere in this repository, in `docs/decisions/`, or in any archived material. The eleven-item statement was therefore an assertion without a supporting decision record, discovered and flagged (but not corrected, since it was out of scope) during the drafting of 03_SYSTEM_ARCHITECTURE.md.

This ADR exists to close that gap with an actual, citable decision, before any implementation work makes the ambiguity costly to unwind.

## Problem Statement

Two documents both described as authoritative disagreed on a foundational count that downstream work — the ERD, the system architecture, the coding standard, and every future test suite and folder — depends on. Proceeding to implementation without resolving which count is correct risks one of two failure modes: engineering work quietly standardizes on the unsupported eleven-item list because it appeared in the folder-structure document engineers read most operationally, silently discarding Clinic Registration, Custom Domain, Clinic Service, and Payment as first-class consistency boundaries the domain model says they are; or engineering work standardizes on fifteen while some other artifact (a future onboarding document, a future summary) continues to cite eleven, reintroducing the same ambiguity one level downstream.

The decision must also do more than pick a number — it must fix the conceptual error that allowed the discrepancy to occur at all: 24_FOLDER_STRUCTURE.md's eleven-item list was never a domain decision. It was a folder-scaffolding document making an implicit domain claim it had no authority to make, exactly the failure mode 24_FOLDER_STRUCTURE.md's own Document Authority section already warns against: "It does not define business behavior, classify new Aggregate Roots... folder creation must not be used to settle that conflict." This ADR treats that self-warning as correct and enforces it.

## Decision Drivers

1. Compliance with ADR-001 (evidence-led restraint: a documented, audited decision (18 → 23) outranks an unaudited assertion (24's eleven-item list)).
2. 23_AGGREGATE_ROOT_VALIDATION.md is, by construction, the more rigorous artifact — it is a dedicated audit whose entire purpose was to confirm or challenge the aggregate count, and it found no defect in any of the fifteen.
3. Folder-structure documentation must follow domain decisions, never precede or silently override them (24_FOLDER_STRUCTURE.md's own stated principle).
4. Every downstream document already produced (22_ERD.md, 03_SYSTEM_ARCHITECTURE.md, 25_CODING_STANDARD.md) was authored against the fifteen-root model; reducing to eleven now would require rewriting three additional documents with no corresponding gain in rigor.
5. A single, unambiguous, citable source of truth must exist before Laravel initialization or any application code is written, since aggregate boundaries directly shape module structure, persistence boundaries, and test ownership.

## The Official Fifteen Aggregate Roots

| # | Aggregate Root (business name, 18_AGGREGATE_DESIGN.md) | Code/Folder Identifier | Owning Bounded Context |
|---|---|---|---|
| 1 | Clinic Registration | `ClinicRegistration` | Tenant Management |
| 2 | Tenant | `Tenant` | Tenant Management |
| 3 | Clinic | `Clinic` | Website Builder |
| 4 | Website | `Website` | Website Builder |
| 5 | Custom Domain | `CustomDomain` | Website Builder |
| 6 | Template | `Template` | Template & Design System |
| 7 | Media | `Media` | Media & Asset Management |
| 8 | Clinic Service | `ClinicService` | Booking |
| 9 | Booking | `Booking` | Booking |
| 10 | Subscription | `Subscription` | Subscription & Billing |
| 11 | Payment | `Payment` | Subscription & Billing |
| 12 | Onboarding Job | `OnboardingJob` | Onboarding |
| 13 | Notification | `Notification` | Notification |
| 14 | Audit Entry | `AuditEntry` | Platform Administration |
| 15 | Platform Setting | `PlatformSetting` | Platform Administration |

This is the exact fifteen-item list from 18_AGGREGATE_DESIGN.md, independently re-confirmed by 23_AGGREGATE_ROOT_VALIDATION.md, presented here with the code-identifier form (PascalCase, singular) that 24_FOLDER_STRUCTURE.md and 25_CODING_STANDARD.md use when referring to a folder or class name. The business name and the code identifier name the same concept; neither is a different or additional Aggregate Root.

## Why Fifteen Is Accepted

Each of the fifteen passed 23_AGGREGATE_ROOT_VALIDATION.md's ten-question test on its own terms — an independent transaction boundary, an independent lifecycle, a concrete, named consequence if merged into another aggregate, and a concrete, named consequence if further split. Four of the fifteen are precisely the ones the superseded eleven-item list omitted, and each has an explicit, evidenced reason to remain independent rather than being folded into a neighbor:

- **Clinic Registration** protects a duplicate-prevention invariant that has no meaning once folded into Tenant, since it exists in a state before any Tenant does.
- **Custom Domain** enforces a platform-wide uniqueness invariant (one host maps to at most one active Tenant Website) that cannot be evaluated correctly if scoped inside one Website's own boundary.
- **Clinic Service** was the aggregate ADR-001 itself already names as the canonical risk example for merging too aggressively ("Service Setup may acquire conflicting owners") — keeping it independent from Clinic is what prevents that named risk from becoming real.
- **Payment** requires independent reconciliation against asynchronous, out-of-band provider outcomes that a child record of Subscription could not safely represent without threatening Subscription's own transaction boundary.

None of the four benefits from being folded into a neighboring aggregate; each fold was evaluated in 23_AGGREGATE_ROOT_VALIDATION.md's Merge Candidates table and explicitly rejected with a named, concrete consequence.

## Why the Earlier Eleven-Root Statement Is Superseded

The eleven-item statement in 24_FOLDER_STRUCTURE.md is superseded because it was never itself a decision — it was an unattributed claim citing a document ("the Architecture Freeze") that does not exist. A folder-structure document has no authority to classify or declassify an Aggregate Root; 24_FOLDER_STRUCTURE.md's own Document Authority section already says so. Superseding it here is not a reversal of a prior CTO decision, because no such decision was ever actually made and recorded — it is the correction of a documentation error, formalized so it cannot recur.

## Difference Between an Aggregate Root and a Physical Folder

This distinction is the operational heart of this ADR and must not be lost in the correction: **being one of the fifteen approved Aggregate Roots does not, by itself, justify the existence of a folder.** An Aggregate Root is a domain-modeling classification — a statement about which concepts own an independent consistency boundary. A folder under `Domain/Aggregates/<Name>/` is an implementation artifact that should exist only once real, non-speculative source files are ready to occupy it.

24_FOLDER_STRUCTURE.md's own Aggregate Folder Rules already state this correctly and are **not** changed by this ADR: an aggregate folder is created only when (1) the Aggregate Root is recognized by this baseline, (2) its owning module is established, (3) implementation of that aggregate has been explicitly authorized, (4) the folder will contain legitimate source files in the same change, and (5) its placement does not promote an internal entity, Value Object, Projection, or Audit Object into Aggregate Root status. Raising the recognized count from eleven to fifteen does not mean fifteen empty folders should now be created — it means fifteen names are now correctly available to be scaffolded, one at a time, exactly when their own implementation work begins.

## Consequences

- **22_ERD.md** already draws all fifteen as Aggregate Root entities and requires no change; this ADR gives that diagram's entity set a citable authority it previously lacked.
- **03_SYSTEM_ARCHITECTURE.md** already uses the fifteen-root model and already flagged this exact discrepancy in its own Document Authority section; that document is updated to cite this ADR as the resolution rather than an open conflict.
- **25_CODING_STANDARD.md** already states "the fifteen Aggregate Roots confirmed in 18_AGGREGATE_DESIGN.md and re-validated in 23_AGGREGATE_ROOT_VALIDATION.md"; that document is updated to also cite this ADR as the formal decision record.
- **24_FOLDER_STRUCTURE.md** is revised in the same change as this ADR to state the fifteen-item baseline, remove the unattributed "Architecture Freeze" citation, and keep its own lazy-folder-creation rule unchanged.
- **Tests** (`tests/Unit/Modules/`, `tests/Architecture/`, and the tenant-isolation negative-test requirement in 25_CODING_STANDARD.md) are written against all fifteen Aggregate Roots as their consistency-boundary units, including Clinic Registration, Custom Domain, Clinic Service, and Payment, which a team working from the superseded eleven-item list would have under-tested.
- **Future implementation** scaffolds an Aggregate-specific folder only for the Aggregate currently being built, per the lazy-creation rule restated above — this ADR expands the *set of approved names*, not the *set of folders that must exist today*.

## Rule: Changing This Baseline Requires a New ADR

The fifteen-item list in this ADR is the Phase 1 Aggregate Root baseline until a superseding ADR is accepted. No document — a folder-structure standard, a coding standard, an implementation plan, or a task ticket — may add, remove, merge, or split an Aggregate Root by restating a different count or list. A proposed change to this baseline (for example, promoting Invoice out of Subscription, or a future split candidate named in 23_AGGREGATE_ROOT_VALIDATION.md's own Split Candidates table) requires its own ADR, citing this one, following the same ten-question validation discipline 23_AGGREGATE_ROOT_VALIDATION.md already established.

## Related ADRs

This ADR cites and is subordinate to [ADR-001](./ADR-001-Architecture-Principles.md), [ADR-002](./ADR-002-Multi-Tenant-Strategy.md), and [ADR-003](./ADR-003-Technology-Stack.md), none of which are modified by this decision. It formalizes, and does not alter, the domain analysis already recorded in 18_AGGREGATE_DESIGN.md and 23_AGGREGATE_ROOT_VALIDATION.md.

## CTO Review Checklist

- Confirm the fifteen-item list above is complete and matches 18_AGGREGATE_DESIGN.md and 23_AGGREGATE_ROOT_VALIDATION.md exactly.
- Confirm no other authoritative document, after this ADR is accepted, states a different Aggregate Root count.
- Confirm the Aggregate-Root-versus-folder distinction is understood by anyone about to begin implementation, so this ADR is not misread as an instruction to scaffold fifteen empty folders immediately.
- Confirm 24_FOLDER_STRUCTURE.md, 03_SYSTEM_ARCHITECTURE.md, and 25_CODING_STANDARD.md all cite this ADR consistently.

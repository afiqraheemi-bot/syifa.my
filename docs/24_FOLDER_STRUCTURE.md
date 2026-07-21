# Phase 1 Folder Structure

## Table of Contents

- [Document Authority](#document-authority)
- [Purpose](#purpose)
- [Approved Top-Level Repository Structure](#approved-top-level-repository-structure)
- [Bounded-Context Module Structure](#bounded-context-module-structure)
- [Internal Layer Responsibilities](#internal-layer-responsibilities)
- [Dependency Direction](#dependency-direction)
- [Aggregate Folder Rules](#aggregate-folder-rules)
- [Frontend Ownership Structure](#frontend-ownership-structure)
- [Public Blade Template Location](#public-blade-template-location)
- [Test Structure](#test-structure)
- [Forbidden Generic Folders](#forbidden-generic-folders)
- [Allowed and Forbidden Dependencies](#allowed-and-forbidden-dependencies)
- [Laravel Initialization Boundary](#laravel-initialization-boundary)
- [Governance](#governance)

## Document Authority

This document is the authoritative application-scaffold standard for Syifa.my Phase 1. It refines the future application structure anticipated by [13_FOLDER_STRUCTURE.md](./13_FOLDER_STRUCTURE.md) using the locked bounded-context decisions in [16_BOUNDED_CONTEXTS.md](./16_BOUNDED_CONTEXTS.md) and the Aggregate Root baseline formally accepted in [ADR-004](./decisions/ADR-004-Aggregate-Root-Baseline.md).

It governs folder ownership and dependency direction only. It does not define business behavior, classify new Aggregate Roots, or authorize implementation or framework initialization — that authority belongs to [18_AGGREGATE_DESIGN.md](./18_AGGREGATE_DESIGN.md), [23_AGGREGATE_ROOT_VALIDATION.md](./23_AGGREGATE_ROOT_VALIDATION.md), and ADR-004. A prior revision of this document stated an unsupported, narrower Aggregate Root count attributed to an "Architecture Freeze" that was never formalized as an ADR or any other decision record; that statement is corrected below and formally superseded by ADR-004. If a future document appears to show an aggregate classification that conflicts with ADR-004, ADR-004 controls, and a new ADR — not a folder-structure edit — is required to change it.

## Purpose

The structure makes business ownership visible before implementation begins. It is designed to preserve high cohesion within a bounded context, low coupling between contexts, explicit integration boundaries, and test ownership without introducing speculative folders.

The scaffold is intentionally minimal. A folder is created only when it has an approved, durable responsibility. Deeper folders are added when legitimate implementation files require them, not to predict a future design.

## Approved Top-Level Repository Structure

```text
Syifa.my/
├── README.md
├── CLAUDE.md
├── AGENTS.md
├── app/
│   └── Modules/
├── docs/
│   └── decisions/
├── implementation/
├── resources/
│   ├── js/
│   └── views/
├── tasks/
└── tests/
```

- `app/Modules/` is the sole application-module root. Business ownership is organized by bounded context rather than by framework artifact type.
- `docs/` contains normative product, domain, architecture, security, delivery, and engineering documentation. `docs/decisions/` contains the current Architecture Decision Records.
- `implementation/` is reserved for approved implementation plans. A plan cannot override architecture or domain authority.
- `resources/` contains presentation assets with explicit frontend ownership.
- `tasks/` is reserved for bounded delivery tasks linked to approved scope.
- `tests/` contains the approved test ownership structure. No test implementation is created by this scaffold.

Framework runtime, configuration, dependency, route, persistence-evolution, and generated-output directories are deliberately absent until Laravel initialization is separately authorized.

## Bounded-Context Module Structure

The approved Phase 1 module shells are:

```text
app/Modules/
├── TenantManagement/
├── WebsiteBuilder/
├── TemplateDesignSystem/
├── MediaAssetManagement/
├── Booking/
├── Commercial/
├── ClinicRegistration/
├── SubscriptionBilling/
├── Onboarding/
├── Notification/
├── ReportingAnalytics/
└── PlatformAdministration/
```

Each bounded context has exactly this initial internal structure:

```text
app/Modules/<Context>/
├── Application/
├── Contracts/
├── Domain/
├── Infrastructure/
└── Presentation/
```

A bounded-context directory is a **permanent architecture boundary**, not a deployment unit and not a speculative or provisional grouping. The twelve module shells listed above correspond exactly to the twelve bounded contexts locked in 16_BOUNDED_CONTEXTS.md after SYIFA-085A alignment; they are created as the stable ownership map for the Phase 1 domain, and are not subject to the same lazy, implementation-triggered creation rule that governs Aggregate folders (see Aggregate Folder Rules below). Renaming, merging, or removing a bounded-context module folder is a bounded-context change requiring the same review 16_BOUNDED_CONTEXTS.md's own governance demands — it is never a routine folder-structure edit. The structure permits later modularization without requiring separate services, packages, repositories, or deployments in Phase 1.

## Internal Layer Responsibilities

### `Domain/`

`Domain/` owns the bounded context's business language, invariants, policies, and approved aggregate behavior. It must remain independent of delivery mechanisms, persistence implementations, framework facilities, and external providers. Its purpose is to keep business decisions stable when technical mechanisms change.

### `Application/`

`Application/` coordinates use cases. It invokes domain behavior, applies workflow sequencing, and uses explicit contracts for required capabilities. It must not become a second home for business invariants and must not directly depend on another module's infrastructure.

### `Contracts/`

`Contracts/` defines the bounded context's explicit callable or consumable boundary. It is the only approved path for another module to depend on that context. Contracts must be narrow, owned by the module exposing them, and expressed in business-relevant language without exposing persistence details.

### `Infrastructure/`

`Infrastructure/` contains future technical adapters that implement approved contracts, such as persistence or external-provider integration. It does not own business rules. Replacing an adapter must not require rewriting the domain model or allow callers to bypass the owning module.

### `Presentation/`

`Presentation/` contains future delivery adapters. It may translate an authorized request into an Application use case and translate the outcome for the caller. It must not implement business rules, access persistence directly, or invoke another module's Infrastructure layer.

## Dependency Direction

Dependencies must point toward business policy and explicit boundaries:

```text
Presentation ──> Application ──> Domain
                         └─────> Contracts

Infrastructure ──implements──> Contracts

Module A ──> Module B Contracts
```

The mandatory rules are:

1. `Domain/` must not depend on `Infrastructure/` or `Presentation/`.
2. `Application/` may depend on its own `Domain/` and the contracts needed to execute a use case.
3. `Infrastructure/` implements technical contracts but does not own or redefine business invariants.
4. `Presentation/` invokes `Application/` use cases and does not bypass them to reach persistence.
5. Cross-module communication uses the providing module's explicit `Contracts/` boundary.
6. No module may query, import, or mutate another module's persistence implementation directly.
7. A dependency cycle between modules is prohibited. If two modules appear to require each other's internals, the contract or workflow ownership must be redesigned before implementation.

## Aggregate Folder Rules

The scaffold does not contain aggregate-specific folders. Approval as an Aggregate Root does not, by itself, justify an empty directory — this remains true regardless of how many Aggregate Roots are recognized.

An aggregate folder may be created only when all of the following are true:

1. The Aggregate Root is already recognized by the accepted baseline in [26_ARCHITECTURE_FREEZE_V1.md](./26_ARCHITECTURE_FREEZE_V1.md), currently superseding ADR-004 through [ADR-006](./decisions/ADR-006-Commercial.md).
2. Its owning bounded context is established.
3. Implementation of that aggregate has been explicitly authorized.
4. The folder will contain legitimate source files in the same change.
5. Its placement does not promote an internal entity, Value Object, Projection, Integration Object, or Audit Object into Aggregate Root status. **Concretely, this means no folder is ever created under `Domain/Aggregates/` for a Projection (Report, Activity Log, Launch Readiness, Booking Opportunity), a Value Object (Booking Contact, Theme, Entitlement), or an ordinary internal entity composed within an approved Aggregate Root (Clinic Location, Practitioner Profile, Onboarding Task, Availability Schedule, Domain Verification, and the like) as though it were independently a root — each of those belongs inside its owning Aggregate's own implementation, never as a sibling folder.**

When these conditions are met, the placement is:

```text
app/Modules/<OwningContext>/Domain/Aggregates/<ApprovedAggregateRoot>/
```

The Phase 1 Aggregate Root baseline consumed by this structure is the **sixteen** roots indexed in 26_ARCHITECTURE_FREEZE_V1.md and accepted through ADR-006: `ClinicRegistration`, `Tenant`, `Clinic`, `Website`, `CustomDomain`, `Template`, `Media`, `ClinicService`, `Booking`, `Subscription`, `Payment`, `CommercialOffer`, `OnboardingJob`, `Notification`, `AuditEntry`, and `PlatformSetting`. This document records that set as consumed by the folder-creation rule above; it has no authority to add to it, remove from it, or reinterpret the classification of any concept — that authority belongs solely to the active ADR set and any ADR that later supersedes it.

**Recognizing sixteen names does not mean sixteen folders are created now.** The lazy-creation rule above applies identically regardless of the count: a name is available to be scaffolded the moment its own implementation begins, and not before.

Creating a directory must never be treated as an architecture decision. Any change to the sixteen-item baseline requires a new ADR citing and superseding the active registry — a folder-structure edit alone can never change the recognized set.

## Frontend Ownership Structure

```text
resources/js/
├── DesignSystem/
├── Modules/
└── Shells/
    ├── ClinicOwner/
    ├── WebsiteDesigner/
    └── SuperAdmin/
```

- `DesignSystem/` is the governed home for shared visual foundations. It must not become a general shared-code directory.
- `Modules/` is the frontend business-capability root. A context-specific child folder is created only when legitimate frontend files for that context are introduced.
- `Shells/ClinicOwner/`, `Shells/WebsiteDesigner/`, and `Shells/SuperAdmin/` own role-specific authenticated application framing. A shell may compose module-owned screens but must not absorb their business behavior.

Frontend module code must consume explicit backend interfaces and remain aligned with bounded-context ownership. Role shells are presentation composition boundaries, not new business modules.

## Public Blade Template Location

Public clinic website templates belong at:

```text
resources/views/public/templates/
```

This path separates the public, server-rendered website surface from authenticated frontend shells. It does not authorize template implementation, create template variants, or change the locked number or classification of Templates.

## Test Structure

```text
tests/
├── Architecture/
├── Contract/
│   └── Modules/
├── Feature/
│   └── Modules/
├── Integration/
│   └── Modules/
└── Unit/
    └── Modules/
```

- `Architecture/` will verify dependency direction, context boundaries, and prohibited coupling.
- `Contract/Modules/` will verify module-owned interfaces and cross-context expectations.
- `Feature/Modules/` will verify externally observable module behavior.
- `Integration/Modules/` will verify approved technical adapters and integration boundaries.
- `Unit/Modules/` will verify isolated domain and application behavior.

Context-specific test directories are created only when the same change introduces legitimate tests. Empty test folders must not be expanded speculatively, and this scaffold contains no test code.

## Forbidden Generic Folders

Unowned generic directories are prohibited, including top-level or module-level folders named `Common`, `Shared`, `Helpers`, `Misc`, `Utils`, or equivalent catch-all terms.

A broadly reusable concept must still have a named owner, a narrow responsibility, and an explicit dependency rule. Reuse alone is not sufficient reason to create a shared location. Code belongs with the bounded context that owns its business meaning; technical reuse must be introduced only through an approved, deliberately named foundation.

Tenant-specific source trees, clinic-specific modules, copied templates, and customer-specific forks are also prohibited. Tenant variation belongs in governed configuration and data when implementation is authorized.

## Allowed and Forbidden Dependencies

| Direction | Status | Reason |
|---|---|---|
| `WebsiteBuilder/Presentation` to `WebsiteBuilder/Application` | Allowed | Presentation invokes an owning module's use case. |
| `Booking/Application` to `Booking/Domain` | Allowed | Application coordinates behavior owned by its domain. |
| `SubscriptionBilling/Infrastructure` implementing `SubscriptionBilling/Contracts` | Allowed | A technical adapter fulfils an explicit module-owned boundary. |
| `Onboarding/Application` to `WebsiteBuilder/Contracts` | Allowed | Cross-context collaboration occurs through the provider's public contract. |
| `Booking/Domain` to `Booking/Infrastructure` | Forbidden | Domain policy must not depend on a technical implementation. |
| `WebsiteBuilder/Presentation` to `WebsiteBuilder/Infrastructure` | Forbidden | Presentation must not bypass the Application layer. |
| `ReportingAnalytics` to another module's persistence implementation | Forbidden | Reporting must consume approved contracts or projections, never another module's storage internals. |
| `Onboarding` directly mutating `WebsiteBuilder/Domain` internals | Forbidden | A coordinating context cannot bypass the owning context's contract and invariants. |
| Any module importing another module's `Infrastructure` directory | Forbidden | Infrastructure is private to its owning bounded context. |
| Any module placing unrelated reusable code in an unowned shared folder | Forbidden | This hides ownership and creates uncontrolled coupling. |

## Laravel Initialization Boundary

This folder scaffold does not initialize Laravel. The following are not created or authorized by this document:

- Framework bootstrap or provider files.
- Configuration files or configuration directories.
- Route files or route definitions.
- Dependency manifests or installed dependencies.
- Models, controllers, middleware, commands, jobs, or other framework classes.
- Database migrations, factories, seeders, or persistence schemas.
- Runtime storage, caches, logs, generated assets, or public entry points.

Those paths may be introduced only through a separately authorized Laravel initialization task that preserves this module structure and its dependency rules.

## Governance

Engineering leadership owns this structure. A new top-level directory, bounded context, internal layer, cross-context dependency, or aggregate folder requires review against this document and the Aggregate Root baseline accepted in ADR-004 before it is created.

Folder structure must follow approved architecture; it must never create architecture by implication. Empty `.gitkeep` files are not used to preserve speculative directories. Documentation must be updated in the same governed change whenever an approved structural rule changes.

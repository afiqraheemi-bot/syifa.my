# Coding Standard

**Status: Draft — Under CTO Review.** This is the official engineering standard for Syifa.my. It defines how code is written, named, organized, tested, and reviewed. It does not define, redefine, or reinterpret architecture, domain boundaries, or technology selection — those remain governed by [03_SYSTEM_ARCHITECTURE.md](./03_SYSTEM_ARCHITECTURE.md), [ADR-002](./decisions/ADR-002-Multi-Tenant-Strategy.md), [ADR-003](./decisions/ADR-003-Technology-Stack.md), and [ADR-004](./decisions/ADR-004-Aggregate-Root-Baseline.md). This document contains no implementation and no code — only naming patterns, placement rules, and short illustrative identifiers used the same way 19_DATABASE_STRATEGY.md illustrated field-naming conventions without writing a schema.

## Table of Contents

- [Document Authority](#document-authority)
- [1. Engineering Principles](#1-engineering-principles)
- [2. Clean Code Principles](#2-clean-code-principles)
- [3. SOLID Principles](#3-solid-principles)
- [4. DDD Rules](#4-ddd-rules)
- [5. Laravel Standards](#5-laravel-standards)
- [6. Vue Standards](#6-vue-standards)
- [7. PHP Standards](#7-php-standards)
- [8. Naming Convention](#8-naming-convention)
- [9. File Naming](#9-file-naming)
- [10. Folder Rules](#10-folder-rules)
- [11. Controller Rules](#11-controller-rules)
- [12. Service Rules](#12-service-rules)
- [13. Domain Rules](#13-domain-rules)
- [14. Repository Rules](#14-repository-rules)
- [15. DTO Rules](#15-dto-rules)
- [16. Form Request Rules](#16-form-request-rules)
- [17. Resource Rules](#17-resource-rules)
- [18. API Rules](#18-api-rules)
- [19. Error Handling](#19-error-handling)
- [20. Logging](#20-logging)
- [21. Queue Rules](#21-queue-rules)
- [22. Event Rules](#22-event-rules)
- [23. Testing Rules](#23-testing-rules)
- [24. Security Rules](#24-security-rules)
- [25. Multi Tenant Rules](#25-multi-tenant-rules)
- [26. Performance Rules](#26-performance-rules)
- [27. Documentation Rules](#27-documentation-rules)
- [28. Git Branch Convention](#28-git-branch-convention)
- [29. Commit Convention](#29-commit-convention)
- [30. Pull Request Checklist](#30-pull-request-checklist)
- [31. Code Review Checklist](#31-code-review-checklist)
- [32. Definition of Done](#32-definition-of-done)

## Document Authority

This document is the PHP/Laravel/Vue-specific engineering standard that [08_DEVELOPMENT_RULES.md](./08_DEVELOPMENT_RULES.md) itself anticipated and deliberately deferred: "Detailed language- and framework-specific conventions must be established before implementation and recorded as subordinate standards or decisions; this document does not assume them." This document is that subordinate standard. It does not replace 08_DEVELOPMENT_RULES.md's engineering principles, review standard, or Definition of Done — it applies them concretely to the technology stack locked in ADR-003 and the module, layer, aggregate, API, and permission structures locked in [16_BOUNDED_CONTEXTS.md](./16_BOUNDED_CONTEXTS.md), [18_AGGREGATE_DESIGN.md](./18_AGGREGATE_DESIGN.md), [19_DATABASE_STRATEGY.md](./19_DATABASE_STRATEGY.md), [20_API_DESIGN.md](./20_API_DESIGN.md), [21_PERMISSION_MATRIX.md](./21_PERMISSION_MATRIX.md), [24_FOLDER_STRUCTURE.md](./24_FOLDER_STRUCTURE.md), and 03_SYSTEM_ARCHITECTURE.md.

Where this document names a Laravel-idiomatic pattern (Service, Repository, DTO, Form Request, Resource) that is not itself a named layer in 03_SYSTEM_ARCHITECTURE.md's Presentation → Application → Domain → Infrastructure model, that pattern is a **placement clarification within an already-locked layer**, never a new layer or a new module. Section 12 states explicitly, for example, that "Service" is this codebase's name for an Application-layer use-case class — not a sixth architectural layer.

## 1. Engineering Principles

- Correctness, tenant isolation, security, and accessibility are part of the feature, not a follow-up task — restated from 08_DEVELOPMENT_RULES.md and made non-negotiable at the code level.
- The smallest coherent change that delivers an approved outcome is preferred over a larger, "while I'm in there" change.
- Business rules live in `Domain/`; delivery and infrastructure concerns never leak into it (Section 13).
- Every module boundary from 03_SYSTEM_ARCHITECTURE.md Section 5 is enforced in code, not just in documentation — an architecture test (Section 23) proves it on every build.
- No tenant-specific fork, tenant-specific branch, or tenant-specific configuration escape hatch is ever introduced, per ADR-001's Platform First principle.
- Abstractions are earned by a second or third real use, never introduced for a single, speculative future case (ADR-001's evidence-led restraint, applied at the class-design level).
- A change that is hard to reverse (a public API contract, a persisted enum value, an identifier format) is held to a higher review bar than a change that is cheap to undo.

## 2. Clean Code Principles

- Names say what a thing is or does in Syifa.my's own business vocabulary (14_DOMAIN_MODEL.md), never a generic technical noun — a class named `Handler`, `Manager`, or `Processor` without a business qualifier is not acceptable.
- A function does one thing at one level of abstraction; if a function needs an "and" to describe it, it is two functions.
- A class has one reason to change (Section 3's Single Responsibility Principle, restated here as a habit, not just a rule to cite).
- Nesting depth is kept low by returning early and extracting named conditions, never by adding a comment explaining a deeply nested block.
- Magic numbers, magic strings, and magic status codes are named constants or enums (Section 7), never inlined.
- A comment explains a non-obvious *why* — a hidden constraint, a workaround, a business rule that would surprise a reader. It never restates *what* the code already says.
- Dead code, commented-out code, and speculative feature-flagged branches are removed, not preserved "just in case."

## 3. SOLID Principles

| Principle | Syifa.my Application |
|---|---|
| **Single Responsibility** | Each Application-layer Service class executes exactly one use case (Section 12); each Domain class owns exactly one Aggregate Root's or Value Object's behavior. |
| **Open/Closed** | New Template variation or Notification category is added through governed configuration or a new implementation of an existing Contract, never by editing a shared conditional that branches on every known case. |
| **Liskov Substitution** | Any Repository implementation behind a Contract-defined interface (Section 14) must be fully substitutable — a test double used in a Unit test must satisfy the same contract as the real Infrastructure adapter. |
| **Interface Segregation** | A module's `Contracts/` exposes narrow, purpose-specific interfaces (per 03_SYSTEM_ARCHITECTURE.md Section 6) — a consuming module never depends on a wide interface just to reach one method it needs. |
| **Dependency Inversion** | `Domain/` and `Application/` depend on interfaces declared in `Contracts/`; `Infrastructure/` depends on and implements those interfaces. Dependency direction is always inward toward business policy (03_SYSTEM_ARCHITECTURE.md Section 6), never outward toward a concrete technology. |

## 4. DDD Rules

- The fifteen Aggregate Roots confirmed in 18_AGGREGATE_DESIGN.md, re-validated in 23_AGGREGATE_ROOT_VALIDATION.md, and formally accepted as the Phase 1 baseline in [ADR-004](./decisions/ADR-004-Aggregate-Root-Baseline.md) are the only classes that may be loaded, mutated, and persisted as a unit of consistency. No code introduces a sixteenth.
- One transaction touches exactly one Aggregate Root, per 18_AGGREGATE_DESIGN.md's Aggregate Persistence Principles — a Service class that needs to affect two aggregates issues two separate, independently-authorized operations, never one shared transaction.
- Cross-aggregate references are identifiers only. A Domain class never holds a live object reference into another aggregate; it holds an ID and resolves it, when needed, through that aggregate's own Repository or Contract.
- Internal entities (Registration Decision, Clinic Owner Authority, Clinic Location, Practitioner Profile, Website Content, Availability Schedule, Availability Exception, Onboarding Task, Website Designer Assignment, Invoice) are only ever constructed, mutated, and persisted through their owning Aggregate Root — never independently instantiated or persisted by another class.
- Where 18_AGGREGATE_DESIGN.md specifies a snapshot (Booking's captured Clinic Service/Location/Practitioner values), the code captures an immutable value at the moment it matters — it never holds a live reference that could let history silently change later.
- Ubiquitous language is enforced in code: a class, method, or variable name never uses a term 14_DOMAIN_MODEL.md's Domain Language Rules warn against conflating (Tenant vs. Clinic, Template vs. Theme, Public Visitor vs. Customer, and so on).
- A Value Object (Booking Contact, Theme, Entitlement, Money) is immutable once constructed and has no setter — a change produces a new instance, never a mutation in place.

## 5. Laravel Standards

- Eloquent models exist only inside a module's `Infrastructure/`, as the technical mechanism a Repository implementation uses — never as the object a Domain class or a Controller manipulates directly (19_DATABASE_STRATEGY.md's "explicit repositories... preferred for security-critical and cross-module access," applied as a hard rule here, not a preference).
- A model's Active Record convenience methods (`::create`, `::update`, mass-assignment) are never called from `Application/` or `Presentation/` — only from the Repository implementation in `Infrastructure/` that is responsible for translating a Domain Aggregate into and out of persisted rows.
- Global helper functions and Facades are used only in `Presentation/` and `Infrastructure/`; `Domain/` never calls a Laravel Facade, global helper, or container resolution — its constructor dependencies are passed in explicitly (Dependency Inversion, Section 3).
- Each bounded-context module registers its own service bindings (interface-to-implementation) in its own provider; no module's bindings are declared in another module's provider.
- Laravel's default `App\Models`, `App\Http\Controllers`, and other framework-default top-level folders are not used for business code — every class lives inside its owning `app/Modules/<Context>/<Layer>/` path (24_FOLDER_STRUCTURE.md).
- Middleware that resolves tenant context is applied once, centrally, and is never re-implemented per module (Section 25).

## 6. Vue Standards

- Every component is a Single File Component with `<script setup>` Composition API syntax, consistent with modern Vue 3 practice and ADR-003's Decision 3 selection.
- A component's file name and its registered name match exactly, in PascalCase (Section 8).
- Props are explicitly typed and declared; a component never reads an untyped, arbitrary prop.
- Business logic (authorization checks, tenant-scoping decisions, invariant enforcement) never lives in a Vue component — a component renders state and emits intent; the Application layer, reached through Inertia's server-driven request, decides what is allowed (03_SYSTEM_ARCHITECTURE.md Section 8).
- `resources/js/Shells/{ClinicOwner,WebsiteDesigner,SuperAdmin}/` compose module-owned screens; they do not contain module business behavior themselves (24_FOLDER_STRUCTURE.md's Frontend Ownership Structure).
- `resources/js/DesignSystem/` provides shared visual primitives only — it never contains a bounded-context-specific component.
- Direct `fetch`/`axios` calls bypassing Inertia's page-visit model are used only for the narrow, explicitly JSON-API-driven interactions 03_SYSTEM_ARCHITECTURE.md Section 10 names (Booking's live-availability check and submission) — every other authenticated interaction goes through an Inertia visit.

## 7. PHP Standards

- PHP 8.3+ with `declare(strict_types=1)` in every file, per ADR-003's Decision 1 — no file relies on PHP's loose type coercion.
- PSR-12 formatting is enforced automatically; no manual formatting debate occurs in review.
- Constructor property promotion and `readonly` properties are used for Value Objects and DTOs (Sections 4, 15) to make immutability a language-enforced fact, not a convention someone can forget.
- Every business lifecycle state (Booking status, Subscription lifecycle stage, Onboarding Job state, and so on) is a native PHP enum, matching 19_DATABASE_STRATEGY.md's Enum Policy — never a bare string or integer constant.
- Union types and nullable types are declared explicitly; a method never silently accepts or returns an implicit `null` without the signature saying so.
- Static analysis (at the strictest practical level for the codebase's maturity) runs in CI and is treated as a required check, not an advisory one.

## 8. Naming Convention

| Concept | Convention | Example Pattern |
|---|---|---|
| PHP class | PascalCase, singular business noun | `Booking`, `ClinicService`, `WebsiteDesignerAssignment` |
| PHP method | camelCase, verb-first for actions, `is`/`has`/`can` prefix for predicates | `confirm()`, `isBookable()`, `canPublish()` |
| PHP interface (Contract) | PascalCase, suffixed `Interface` or named as a capability | `NotificationDispatcherInterface` |
| Enum | PascalCase type, PascalCase or SCREAMING_SNAKE_CASE cases per team convention, fixed once chosen | `BookingStatus::Confirmed` |
| Database field | `snake_case`, matching 19_DATABASE_STRATEGY.md exactly (`*_id`, `*_at`, `*_on`, `*_amount_minor`, `currency_code`, `timezone`, `status`) | `published_at`, `clinic_service_id` |
| API JSON field | `snake_case`, matching 20_API_DESIGN.md's chosen convention (no translation layer from the persistence naming) | `booking_contact`, `available_from` |
| API URI segment | kebab-case, plural for collections | `/clinic-services`, `/onboarding-jobs` |
| Vue component file | PascalCase | `BookingCalendar.vue` |
| Vue composable | camelCase, `use` prefix | `useTenantContext.ts` |
| Blade view | kebab-case, matching its route/purpose | `public/templates/clinic-profile.blade.php` |
| Route name | dot-notation, module-prefixed | `booking.available-slots` |

Terms 14_DOMAIN_MODEL.md's Domain Language Rules warn must not be used interchangeably are never aliased in code either — a variable named `$customer` is never used to mean a Public Visitor, and a variable named `$theme` is never used to mean a Template.

## 9. File Naming

- One class, interface, enum, or trait per file; the file name matches the type name exactly, including case.
- A test file mirrors the path and name of the thing it tests, suffixed `Test` (for example, a test of `Booking` in `Domain/` is named `BookingTest` and lives at the mirrored path under `tests/Unit/Modules/Booking/Domain/`).
- A Form Request is named for the operation it validates, not the resource alone (`SubmitBookingRequest`, not `BookingRequest`), since a resource may have several distinct operations (20_API_DESIGN.md).
- A Resource (Section 17) is named for the shape it produces (`BookingResource`, `BookingCollectionResource`), never reused across two different response shapes.
- An Event class name is a past-tense business fact (Section 22): `BookingConfirmed`, not `ConfirmBooking` or `BookingConfirmationEvent`.

## 10. Folder Rules

This document does not redefine folder structure — [24_FOLDER_STRUCTURE.md](./24_FOLDER_STRUCTURE.md) remains authoritative. The rules restated here are the ones most frequently relevant during day-to-day coding:

- Every class lives under `app/Modules/<Context>/<Layer>/`, where `<Context>` is one of the ten bounded-context module shells and `<Layer>` is exactly one of `Application/`, `Contracts/`, `Domain/`, `Infrastructure/`, or `Presentation/`.
- An Aggregate-specific subfolder (`Domain/Aggregates/<ApprovedAggregateRoot>/`) is created only when implementation of that specific aggregate begins and will contain legitimate files in the same change — never scaffolded speculatively (24_FOLDER_STRUCTURE.md's Aggregate Folder Rules).
- No `Common/`, `Shared/`, `Helpers/`, `Misc/`, or `Utils/` folder is created at any level, at the top of the repository or inside a module. A broadly reusable concept still needs a named owner and a narrow responsibility.
- No `.gitkeep` file is added merely to preserve a speculative empty folder.
- Frontend code follows `resources/js/DesignSystem/`, `resources/js/Modules/`, and `resources/js/Shells/{ClinicOwner,WebsiteDesigner,SuperAdmin}/` exactly as 24_FOLDER_STRUCTURE.md defines them; public Blade templates live at `resources/views/public/templates/`.

---

## 11. Controller Rules

- A Controller lives in `Presentation/` and is the thinnest possible translation between an inbound request (Inertia visit or JSON API call) and an Application-layer Service invocation.
- A Controller method corresponds to exactly one operation from 20_API_DESIGN.md's Resource Catalogue — it never branches internally to handle two different business operations based on a request parameter.
- A Controller never queries Eloquent directly, never contains a business rule, and never reaches into another module's `Infrastructure/` — it calls exactly one Service (Section 12) and translates the result into a response.
- Authorization is invoked from the Controller (or the framework's routing/middleware layer) but *decided* by the policy classes described in ADR-003 Decision 7 and 21_PERMISSION_MATRIX.md — a Controller never contains its own ad hoc `if ($user->role === ...)` check.
- A Controller returns a Resource (Section 17) or an Inertia response; it never returns a raw Eloquent model or a raw array shaped by convenience.

## 12. Service Rules

"Service" is this codebase's name for an **Application-layer use-case class** — the same `Application/` layer 03_SYSTEM_ARCHITECTURE.md Section 6 already locks, not a new architectural layer.

- One Service class executes one use case (for example, `SubmitBookingService`, `PublishWebsiteService`), matching one named operation in 20_API_DESIGN.md's per-resource operation list.
- A Service coordinates: it loads or creates the relevant Aggregate Root (via its Repository, Section 14), invokes Domain behavior, calls other modules only through their `Contracts/`, and persists the result within that one Aggregate Root's transaction (Section 4).
- A Service never contains a business invariant itself — if a rule belongs to the Aggregate, it is enforced inside the Aggregate's own method, and the Service simply calls that method and reacts to its outcome.
- A Service is the layer responsible for translating a Domain-level failure (an invariant violation) into the error category 20_API_DESIGN.md's Error Handling conventions expect (Section 19) — the Domain layer itself does not know about HTTP status codes.

## 13. Domain Rules

- A Domain class exposes behavior through named methods that enforce the Aggregate's own invariants (18_AGGREGATE_DESIGN.md's Business Invariants field, per aggregate) — it never exposes a public setter that lets a caller put it into an invalid state.
- Construction of an Aggregate Root either goes through a named factory method expressing the business event that creates it (for example, a Clinic Registration's `submit()`), or, where 18_AGGREGATE_DESIGN.md states the aggregate is created only as a side effect of another aggregate's action (Clinic, Website, Onboarding Job), through the coordinating Service that owns that side effect — never through a bare `new` call from arbitrary calling code.
- Every Allowed State Change named in 18_AGGREGATE_DESIGN.md's per-aggregate specification is a named method on that Aggregate; there is no generic `update($attributes)` method on a Domain class.
- A Domain class has zero dependency on Eloquent, HTTP, the queue, the cache, or any Laravel Facade — it is constructible and testable with plain PHP objects only (Section 23's Unit test level depends on this).
- Domain exceptions are named for the business rule they protect (`BookingConflictException`, `DuplicateActiveRegistrationException`), never a generic `DomainException` used for every failure.

## 14. Repository Rules

- Every Aggregate Root has exactly one Repository — never one Repository per database table, and never a shared, generic repository parameterized by model class.
- The Repository *interface* is declared in the owning module's `Domain/` or `Contracts/`; the Repository *implementation* lives in `Infrastructure/` and is the only place Eloquent is used to load or persist that Aggregate (Sections 4–5, Dependency Inversion in Section 3).
- A Repository's public methods are expressed in business language (`findActiveByTenant`, `save`, `nextIdentifier`), never in query-builder language (`whereRaw`, `joinSub`) leaking into its interface.
- Every method that loads a tenant-owned Aggregate re-validates tenant ownership against the currently resolved tenant context (Section 25) before returning it — object identifier alone is never sufficient (ADR-002).
- A Repository returns and accepts Domain objects, never raw Eloquent models, across its interface boundary.

## 15. DTO Rules

- A Data Transfer Object is immutable (Section 7's `readonly` convention) and has no behavior beyond simple, side-effect-free accessors — it is a shape, not a class with business logic.
- DTOs are used at two boundaries: crossing a module's `Contracts/` (so a consuming module never receives another module's Eloquent model or Domain object directly), and carrying validated input from a Form Request into a Service.
- A DTO's fields are named in the same business vocabulary as the Domain concept they represent — never a raw copy of a database row's column names if those differ from the ubiquitous language.
- A DTO never crosses a module boundary carrying a reference to another module's internal entity (Section 4) — only identifiers and value data.

## 16. Form Request Rules

- A Form Request lives in `Presentation/` and validates exactly one operation, named for that operation (Section 9).
- Form Request validation is boundary validation only: shape, type, required-ness, format — the distinction 19_DATABASE_STRATEGY.md's Validation Philosophy and 20_API_DESIGN.md's Error Handling Principles already draw between a malformed request (`400`/`422` at the boundary) and a business-rule violation (enforced inside the Domain, surfaced as its own error category by the Service, Section 12).
- A Form Request never queries the database to check a business rule (for example, "is this slot still available") — that check belongs to the Domain, inside the Aggregate's own method, evaluated at the moment of the actual state change to avoid a race between validation and commit.
- Authorization is not decided inside a Form Request — a Form Request may assume the caller is already authenticated, but the actual authorization decision is the policy class's job (Section 11).

## 17. Resource Rules

- A Resource shapes exactly one API response defined in 20_API_DESIGN.md's Resource Catalogue — its fields match that document's Response Summary for the corresponding operation.
- A Resource never includes a field 21_PERMISSION_MATRIX.md's Resource Permission Matrix says the current caller is not authorized to see; field-level masking (for example, Booking Contact detail shown differently to a Public Visitor than to a Clinic Owner) is applied in the Resource, using the currently authenticated context, not left to the frontend to hide.
- A Resource never exposes an internal identifier format, a persistence detail, or a field not already named in 20_API_DESIGN.md's contract — adding a field to a Resource that is not in the locked API design is an API change requiring the same review 20_API_DESIGN.md's own Versioning Strategy requires, not a casual addition.
- A collection Resource always respects the cursor-pagination convention 20_API_DESIGN.md's API Conventions section locks — no Resource returns an unbounded list.

---

## 18. API Rules

- Every endpoint matches an entry in 20_API_DESIGN.md's Endpoint Matrix — no endpoint is added, renamed, or re-scoped without that document being updated in the same governed change.
- URI versioning (`/api/v1/...`), kebab-case plural resource paths, and `snake_case` JSON fields are applied without exception (Section 8).
- Every `POST` that creates a tenant-owned record with a credible duplicate-submission risk (Booking submission, Payment initiation, Clinic Registration submission) requires and honors an idempotency key, exactly as 20_API_DESIGN.md's API Conventions specify.
- HTTP status codes carry the exact, distinguishable meaning 20_API_DESIGN.md's conventions define — a `404` is used, not a `403`, wherever a resource's existence itself is sensitive (Section 19).
- A mutating request to an Aggregate Root includes or is checked against a version indicator; a stale-version write is rejected with `409`, never silently applied over a concurrent change (19_DATABASE_STRATEGY.md's Optimistic Locking Policy).
- No endpoint accepts a client-supplied tenant identifier as authorization (Section 25) — tenant context is always resolved server-side.

## 19. Error Handling

- Every module defines its own Domain exception hierarchy (Section 13); Application Services catch Domain exceptions and translate them into the stable, machine-readable error categories 20_API_DESIGN.md's Error Handling Principles require — a stack trace, a raw query fragment, or an internal identifier is never part of an external error response.
- A validation failure (`400`/`422`) and a business-rule failure (`422` with a domain-specific error category) are distinguishable in the response body, per Section 16's boundary-versus-business-rule distinction.
- An unexpected/unhandled exception maps to `5xx` and is logged with full internal detail server-side while returning a safe, generic message externally — the internal detail and the external message are never the same payload.
- Every custom exception class is named for the condition it represents, not for the layer that throws it (`ClinicServiceNotBookableException`, not `ValidationException2`).
- Exceptions are never caught and silently discarded; a caught exception either results in an accountable outcome (a returned error, a logged and re-thrown failure, a compensating action) or it is not caught at all (08_DEVELOPMENT_RULES.md's "do not catch and discard failures" rule, restated here as a hard rule).

## 20. Logging

- Every log entry is structured (JSON), never a free-text interpolated string, matching ADR-003 Decision 15 and 19_DATABASE_STRATEGY.md's logging restrictions.
- Every log entry correlates to a request or job by a stable identifier, and to a Tenant by a privacy-safe reference — never by a display name or raw identifier that could itself leak information.
- No log entry ever contains a credential, a secret, a raw Booking Contact field, or any other Sensitive Personal Data or Security and Authentication Data value (19_DATABASE_STRATEGY.md's Data Classification) — only a stable, non-reversible reference.
- A log entry is not a substitute for an Audit Entry (Section 24) — a privileged or security-sensitive action produces both an Audit Entry (mandatory, append-only, per 21_PERMISSION_MATRIX.md) and, where useful for operational diagnosis, an ordinary structured log entry — the two are never conflated into one mechanism.
- Log level is chosen deliberately: `error` for genuine failures requiring attention, `warning` for a degraded-but-recovered condition, `info` for a notable business event, `debug` only in non-production configuration.

## 21. Queue Rules

- Every queued job carries an immutable, validated tenant identifier established at the moment of enqueue, and the consumer re-establishes and re-validates tenant context before any side effect (ADR-002, restated as a coding rule) — a job never trusts a tenant identifier it was merely handed without re-checking it against the aggregate it is about to touch.
- Every job is idempotent with respect to its business effect — a duplicate delivery of the same job must never produce a duplicate business outcome (Notification, Payment reconciliation, and Media processing jobs are held to this standard without exception).
- Jobs are dispatched to the workload-class-specific queue their business concern belongs to (Notification dispatch, Media processing, Onboarding evidence aggregation are separate pools, per ADR-003 Decision 24) — a job is never dispatched to a generic, undifferentiated queue "for now."
- A job never duplicates business logic that already exists in a Service or Domain class — a job's own code is the thinnest possible wrapper that resolves context and calls the same Service a synchronous request path would call.
- Failed jobs are retried according to an explicit, bounded policy and eventually dead-lettered — a job is never retried indefinitely without an escalation path.

## 22. Event Rules

- A domain event's name is a past-tense business fact (`BookingConfirmed`, `WebsiteDesignerAssigned`, `ClinicRegistrationApproved`), per 12_API_STANDARD.md's event convention, restated in Section 9's File Naming rule.
- An event is minimal — it carries the identifiers and facts needed to act on it, never an embedded copy of the full Aggregate state (matching Section 4's "reference by identifier only" rule).
- Events are the mechanism a module uses to notify other modules of something that happened, especially to trigger Notification (03_SYSTEM_ARCHITECTURE.md Section 12) — they are never used as a back door to let a listener directly write another module's Aggregate. A listener that needs to change a business fact in another module calls that module's `Contracts/`, using the event only as the trigger to do so.
- Event listeners are idempotent for the same reason queued jobs are (Section 21), since most listeners execute asynchronously via the queue.
- An event schema change (adding a required field, renaming a field, changing meaning) is a breaking change subject to the same compatibility discipline 12_API_STANDARD.md requires for any other contract.

## 23. Testing Rules

Applying 09_TESTING_STRATEGY.md's test levels concretely to this codebase's layers, and to the `tests/` ownership structure 24_FOLDER_STRUCTURE.md already locks:

- **`tests/Unit/Modules/`** tests `Domain/` classes in isolation — no database, no HTTP, no queue. An Aggregate Root's invariants (18_AGGREGATE_DESIGN.md's Business Invariants field) are proven here, exhaustively, for every aggregate.
- **`tests/Feature/Modules/`** tests one module's externally observable behavior end-to-end within the application (a Controller through to a persisted outcome), covering the Allowed State Changes named in 20_API_DESIGN.md per resource.
- **`tests/Contract/Modules/`** tests a module's `Contracts/` boundary — proving that what a module promises to expose is what it actually exposes, and that a consuming module's expectations are met.
- **`tests/Integration/Modules/`** tests `Infrastructure/` adapters against real or realistic infrastructure (a real PostgreSQL instance, not a mock) — this is where Repository implementations are proven correct.
- **`tests/Architecture/`** tests the dependency-direction rules in Section 5 and 03_SYSTEM_ARCHITECTURE.md's Section 6 mechanically — a `Domain/` class importing an `Infrastructure/` or `Presentation/` class fails the build, not just a code review comment.
- Every tenant-owned Aggregate Root has a mandatory negative test suite substituting a foreign tenant's identifier at every read and write path, per ADR-002's release-blocking tenant-isolation testing requirement — this is not optional coverage, it is a release gate.
- Every 🔒 Privileged or 🔒 Category cell in 21_PERMISSION_MATRIX.md has a corresponding negative test proving the denial, not only a positive test proving the grant.
- Test data is synthetic or irreversibly de-identified; production data never appears in a test fixture (09_TESTING_STRATEGY.md, 19_DATABASE_STRATEGY.md's Testing Database Philosophy).
- A flaky test is a defect, triaged and fixed or explicitly, time-boundedly quarantined — never silently re-run until it passes.

## 24. Security Rules

- Every protected action is authorized server-side against 21_PERMISSION_MATRIX.md's Resource and Role Permission Matrices — UI visibility is never treated as a control (06_SECURITY_STANDARD.md).
- Mass assignment is never used to persist a tenant-owned Aggregate directly from request input — a Form Request's validated DTO passes through a Service and a Domain method that decides exactly which fields may change and under what business rule.
- All database access uses parameterized queries via the framework's query builder or Eloquent; raw, string-concatenated SQL is prohibited without an exceptional, specialist-reviewed justification (08_DEVELOPMENT_RULES.md's Secure Engineering section).
- Every privileged or cross-tenant action (every 🔒 Privileged cell in 21_PERMISSION_MATRIX.md) produces a mandatory Audit Entry as an inseparable part of that action succeeding — this is restated here as a coding rule, not only a permission rule: the code path that performs the action and the code path that records the Audit Entry are not separable steps that could succeed independently.
- File uploads (Media) are validated by content, not by extension or client-supplied MIME type alone, and are scanned before reaching an approved state, per 19_DATABASE_STRATEGY.md's Media Lifecycle.
- Secrets are never hardcoded, never committed, and are read only from the approved secrets system (ADR-003 Decision 18) via typed, validated configuration (Section 27) — a secret is never logged (Section 20) or included in an error response (Section 19).
- Output is encoded for its destination (HTML escaping in Blade/Vue templates, JSON encoding in API responses) by default, using the framework's safe primitives — never manually concatenated into a response.

---

## 25. Multi Tenant Rules

The most consequential section in this standard — every rule here restates an ADR-002 invariant as a concrete, non-negotiable coding discipline.

- Tenant context is resolved exactly once per request or job, through the trusted path appropriate to the caller (03_SYSTEM_ARCHITECTURE.md Section 9), and is immutable for the remainder of that request or job — no code path re-resolves or overrides it mid-flight.
- Tenant context is never accepted from a client-supplied value (a route parameter, a request body field, a header) as authorization by itself — it is always cross-checked against the independently resolved context, and a mismatch fails closed.
- Every Repository method that loads a tenant-owned Aggregate Root is centrally, structurally tenant-scoped — there is no code path that queries a tenant-owned table without the current tenant context applied, whether through a centrally-enforced scope or an explicit condition every Repository method includes without exception.
- Cache keys for tenant-bound data always include the tenant identifier as part of the key; a shared, public cache entry never contains tenant-private data (03_SYSTEM_ARCHITECTURE.md Section 14).
- Queue job payloads, event payloads, file storage paths, and search index entries all carry tenant context explicitly, per Sections 21–22 and 19_DATABASE_STRATEGY.md's File Reference Strategy.
- Website Designer access is additionally scoped to the currently active Website Designer Assignment (18_AGGREGATE_DESIGN.md) — a Website Designer's tenant-scoped query is further narrowed to only the Tenant of their active assignment, re-checked on every request, not cached across requests.
- Super Admin privileged and cross-tenant code paths are structurally separate classes and routes from ordinary Clinic-Owner-facing ones (21_PERMISSION_MATRIX.md's Privilege Escalation Prevention) — a privileged capability is never reached by adding a conditional branch to an existing Clinic Owner controller or Service.
- A worker process, connection, or test fixture never carries tenant context over from one unit of work to the next — context is established fresh and cleared at the end of every request and job, including failure paths.

## 26. Performance Rules

- Eloquent relationships accessed in a loop are always eager-loaded first; an N+1 query pattern is a defect, caught by static analysis or query-count assertions in tests where practical.
- Every collection endpoint is paginated per 20_API_DESIGN.md's cursor-pagination convention (Section 18) — no code path returns an unbounded result set.
- An index is added only against a verified access pattern, reviewed for cardinality, tenant-skew, and write-amplification cost, per 19_DATABASE_STRATEGY.md's Index Strategy — never added defensively "in case it helps."
- Work that does not need to complete within the request/response cycle is dispatched to the queue (Section 21), not executed inline — Notification dispatch, Media processing, and any analytics emission are the concrete, always-async examples.
- A database connection is never held open across an external HTTP call (an ESP call, an object-storage call) — the transaction completes first, and any external side effect is triggered outside it or via the queue.

## 27. Documentation Rules

- A pull request that changes documented behavior updates the owning document in the same change, per 08_DEVELOPMENT_RULES.md's rule restated here as a hard requirement — a Resource, endpoint, or permission change without a corresponding update to 20_API_DESIGN.md or 21_PERMISSION_MATRIX.md is incomplete, not merely under-documented.
- A code comment explains only what the code itself cannot express — a hidden constraint, a workaround for a specific defect, a business rule whose *why* is non-obvious (Section 2). It never restates a type signature or a method name in prose.
- PHPDoc blocks are added only where PHP's own type system cannot express the needed information (an array shape, a generic-like relationship) — not as a mandatory header on every method.
- Configuration is self-documenting through typed, schema-validated definitions (ADR-003 Decision 17) rather than through a separate prose description that can drift out of sync.

## 28. Git Branch Convention

- The protected default branch is always releasable; all work happens on short-lived branches (08_DEVELOPMENT_RULES.md).
- Branch names follow `type/scope-short-description`, where `type` is one of `feature`, `fix`, `chore`, `refactor`, `docs`, or `spike`, and `scope` names the owning bounded-context module in lowercase-kebab form — for example `feature/booking-conflict-check` or `fix/tenant-management-authority-revocation`.
- A branch is linked to an approved task, defect, or decision before work begins, per 08_DEVELOPMENT_RULES.md's Source Control rule.
- A branch is deleted after merge; long-lived, parallel "integration" branches are not used without an explicit, documented reason.

## 29. Commit Convention

- Commit messages follow a Conventional-Commits-style structure: `type(scope): summary`, where `type` matches the branch-type vocabulary in Section 28 (`feat`, `fix`, `chore`, `refactor`, `docs`, `test`) and `scope` names the affected module.
- The summary line is imperative mood, under roughly 72 characters, and states the *why* or the outcome, not a mechanical description of the diff — "Prevent double-booking on concurrent slot selection," not "Update Booking.php."
- A commit that changes behavior is never combined with an unrelated formatting-only or refactoring-only change in the same commit, per 08_DEVELOPMENT_RULES.md's separation rule.
- Credentials, secrets, generated artifacts, and environment-specific configuration are never committed, under any circumstance (08_DEVELOPMENT_RULES.md, 24_FOLDER_STRUCTURE.md's Generated, Runtime, and Sensitive Files rules).

## 30. Pull Request Checklist

- [ ] Linked to an approved task, defect, risk, or decision.
- [ ] States intent, scope, affected Tenants or roles, risk, test evidence, and rollback approach.
- [ ] Every touched Aggregate Root's invariants (Section 4, Section 13) are covered by a Unit test.
- [ ] Every new or changed endpoint matches 20_API_DESIGN.md, updated in the same change if it does not.
- [ ] Every new or changed permission matches 21_PERMISSION_MATRIX.md, updated in the same change if it does not.
- [ ] Tenant-isolation negative tests exist for every touched tenant-owned Aggregate (Section 23).
- [ ] No `Common/`, `Shared/`, or equivalent unowned folder was introduced (Section 10).
- [ ] No cross-module dependency bypasses a `Contracts/` boundary (Section 5, Section 3's Dependency Inversion).
- [ ] Static analysis, formatting, and the full required test suite pass.
- [ ] Documentation affected by this change is updated in the same pull request (Section 27).
- [ ] No secret, credential, or production data is present in the diff.

## 31. Code Review Checklist

Extending 08_DEVELOPMENT_RULES.md's Review Standard with this standard's specific concerns:

- [ ] Does the change stay within its module's boundary, or does it reach into another module's `Domain/` or `Infrastructure/` directly?
- [ ] Does every mutation of a tenant-owned Aggregate happen within that Aggregate's own transaction boundary (Section 4), never spanning two aggregates?
- [ ] Does authorization happen server-side, against 21_PERMISSION_MATRIX.md, rather than being inferred from route structure or client state?
- [ ] Are Domain classes free of Eloquent, HTTP, and Facade dependencies (Section 13)?
- [ ] Is every new lifecycle state a named enum value (Section 7), not a boolean or a magic string?
- [ ] Would a reviewer unfamiliar with this specific change still understand *why* a non-obvious decision was made, from a comment or the PR description — not by needing to ask the author?
- [ ] Are tests asserting observable behavior, not internal implementation structure (09_TESTING_STRATEGY.md)?
- [ ] Is there a negative test for every new or changed authorization boundary?
- [ ] Does the change introduce a new dependency, and if so, is it justified, license-compatible, and actively maintained (08_DEVELOPMENT_RULES.md's Dependencies section)?

Approval means the reviewer accepts the change's engineering quality against this standard, not merely its formatting or its passing tests.

## 32. Definition of Done

A change is done only when, in addition to 08_DEVELOPMENT_RULES.md's own Definition of Done:

- [ ] It satisfies every applicable rule in this standard, not only the ones a linter can check automatically.
- [ ] Every Aggregate Root invariant it touches has a Unit test proving the invariant holds and a negative test proving a violation is rejected.
- [ ] Every tenant-owned resource it touches has a tenant-isolation negative test (Section 23, Section 25).
- [ ] Every permission it touches is reflected correctly in 21_PERMISSION_MATRIX.md.
- [ ] Every endpoint it touches is reflected correctly in 20_API_DESIGN.md.
- [ ] Logging and, where the change is privileged or cross-tenant, Audit Entry creation are in place and tested (Sections 20, 24).
- [ ] No unresolved critical or high-risk finding remains without an approved, time-bounded exception.
- [ ] Documentation affected by the change is updated in the same change (Section 27).

Local completion is not production acceptance; release gates remain governed by 09_TESTING_STRATEGY.md and 10_DEPLOYMENT_STRATEGY.md.
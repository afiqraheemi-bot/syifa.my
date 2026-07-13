# Folder Structure

## Table of Contents

- [Document Authority](#document-authority)
- [Repository Principles](#repository-principles)
- [Current Foundation](#current-foundation)
- [Root Responsibilities](#root-responsibilities)
- [Future Application Structure](#future-application-structure)
- [Documentation Structure](#documentation-structure)
- [Decision Records](#decision-records)
- [Implementation Plans and Tasks](#implementation-plans-and-tasks)
- [Naming and Placement Rules](#naming-and-placement-rules)
- [Generated, Runtime, and Sensitive Files](#generated-runtime-and-sensitive-files)
- [Governance](#governance)

## Document Authority

This document is the source of truth for repository organization, file placement, and naming responsibilities. It intentionally does not prescribe an application tree before foundational technology and module decisions are approved.

## Repository Principles

- Structure communicates ownership and dependency direction.
- A concept has one authoritative home and is linked rather than copied.
- Business-capability boundaries take precedence over generic technical buckets where the chosen framework permits.
- Generated, runtime, vendor, and local-environment artifacts remain separate from maintained source.
- Tenant-specific behavior is configuration or data, never a tenant-specific source tree.
- New top-level paths require a durable repository-wide responsibility.
- Empty speculative directories are not created before their purpose and owner exist.

## Current Foundation

The approved pre-implementation structure is:

```text
Syifa.my/
├── README.md
├── CLAUDE.md
├── AGENTS.md
├── docs/
│   ├── 01_PRODUCT_VISION.md
│   ├── 02_MVP_SCOPE.md
│   ├── 03_SYSTEM_ARCHITECTURE.md
│   ├── 04_DATABASE_STRATEGY.md
│   ├── 05_MULTI_TENANCY.md
│   ├── 06_SECURITY_STANDARD.md
│   ├── 07_UI_UX_DESIGN_SYSTEM.md
│   ├── 08_DEVELOPMENT_RULES.md
│   ├── 09_TESTING_STRATEGY.md
│   ├── 10_DEPLOYMENT_STRATEGY.md
│   ├── 11_ROADMAP.md
│   ├── 12_API_STANDARD.md
│   ├── 13_FOLDER_STRUCTURE.md
│   ├── ... (additional numbered documents added as governance approves them)
│   ├── decisions/
│   │   └── ADR-NNN-Title.md
│   └── archive/
├── implementation/
└── tasks/
```

This tree is documentation infrastructure only. It is not evidence that application technology or deployment topology has been selected.

**`docs/decisions/` is the only official location for Architecture Decision Records.** An earlier version of this tree placed `decisions/` at the repository root, as a sibling of `docs/`; that placement was never adopted in practice and is corrected here. Every accepted ADR (ADR-001 through the current highest-numbered ADR) lives under `docs/decisions/`, and no other location is authoritative. `docs/archive/` holds historical, explicitly superseded material — such as an early architecture proposal that predates and was superseded by the accepted ADR series — retained for record-keeping with a clear superseded header, never treated as a current source of truth.

## Root Responsibilities

- `README.md` is the repository entry point: project identity, status, documentation map, and approved onboarding path.
- `AGENTS.md` defines repository-wide instructions for automated coding agents. More-specific agent files may be added only when a subtree genuinely needs different rules.
- `CLAUDE.md` contains tool-specific guidance only when it differs from the repository-wide rules; it must not contradict `AGENTS.md` or duplicate normative engineering standards.
- `docs/` contains durable, normative product and engineering standards.
- `docs/decisions/` is the sole official location for Architecture Decision Records — immutable decision history and supersession records. `docs/archive/` holds explicitly superseded historical material that is preserved for record-keeping but is never authoritative.
- `implementation/` contains bounded plans for approved changes.
- `tasks/` contains actionable work definitions or repository-local task artifacts when the team chooses to maintain them here.

Future root files for license, contribution, ownership, security reporting, environment templates, dependency manifests, automation, or infrastructure are introduced only when their process is approved.

## Future Application Structure

Before application code is created, an architecture decision must confirm language, framework, package layout, runtime boundaries, frontend organization, and testing placement. The resulting tree must preserve the logical capabilities in [03_SYSTEM_ARCHITECTURE.md](./03_SYSTEM_ARCHITECTURE.md).

The future structure must distinguish:

- Business modules and their public contracts.
- Delivery interfaces such as public web, clinic administration, operator interfaces, and APIs.
- Infrastructure adapters and external provider integrations.
- Background and scheduled workloads.
- Shared design-system assets and approved cross-cutting foundations.
- Automated tests at appropriate levels.
- Database evolution, deployment, and operational assets.

Framework defaults may be used when they do not obscure ownership. If framework conventions require technical directories, business logic must still have clear capability ownership. A generic `helpers`, `common`, `misc`, or equivalent dumping ground is prohibited.

No clinic or customer gets a source directory, theme fork, deployment manifest, or branch. Tenant assets and configuration belong in governed runtime storage.

## Documentation Structure

The numbered documents in `docs/` are normative and ordered from product intent through engineering governance. Each document declares its authority and points to adjacent owners rather than repeating their rules.

Documentation requirements:

- Use Markdown, descriptive headings, relative repository links, and one top-level title.
- Maintain a table of contents for substantial documents.
- State scope, owner or governance, approved requirements, and unresolved assumptions.
- Use requirement language consistently: **must** for mandatory, **should** for expected unless justified, and **may** for optional.
- Do not present proposals, estimates, or hypotheses as approved facts.
- Update documentation in the same change as affected behavior or policy.
- Avoid time-sensitive copies of provider or legal guidance; cite an approved authority and record review dates when such references are added.

New normative documents require an unowned durable subject. Otherwise, extend the existing authority. Temporary meeting notes and raw research do not belong beside normative standards without an explicit lifecycle and owner.

## Decision Records

Material decisions are stored in `docs/decisions/` using the adopted `ADR-NNN-Title.md` naming convention (zero-padded sequence number, concise Title-Case title), matching ADR-001 through the current highest-numbered accepted ADR. An earlier draft of this convention illustrated a `0001-<decision-title>.md` kebab-case pattern; that pattern was superseded in practice by `ADR-NNN-Title.md` and is not used for new records.

Each record contains status, date, owners, context, decision drivers, considered options, decision, consequences, risks, validation, and related documents. Accepted records are not rewritten to hide history. A later record supersedes an earlier one and links both directions.

Decisions are required for foundational technology, major dependencies, trust boundaries, tenant topology, data topology, identity, public domain model, integration strategy, deployment topology, and material deviations from these standards.

## Implementation Plans and Tasks

`implementation/` translates an approved outcome or decision into a reviewable delivery plan. A plan defines scope, dependencies, sequence, migration, tests, telemetry, rollout, rollback, risks, owners, and completion evidence. Plans do not override normative documents or decision records.

`tasks/` contains units of execution linked to product scope, a plan, defect, incident action, or risk. Tasks specify outcome and acceptance evidence without becoming an alternate architecture repository. Completed task retention follows the team's approved work-management policy.

Files in both directories use a stable work identifier where one exists, followed by a concise kebab-case title. Status belongs inside the document or authoritative work system, not in frequently renamed filenames.

## Naming and Placement Rules

- Directory and ordinary file names use lowercase kebab-case unless a tool or established ecosystem convention requires otherwise.
- Normative root filenames retain their conventional uppercase names.
- Types and source symbols follow the language standard selected later.
- Test names describe observable behavior and live near the governed test layer chosen by decision.
- One file should have one primary responsibility; unusually broad files are split by ownership, not arbitrary size.
- Imports and dependencies follow module ownership and must not use path tricks to bypass public boundaries.
- Experimental work is time-bounded and cannot enter the main structure without an owner and disposition.

Any exception driven by a framework is documented once in the approved framework decision or subordinate convention.

## Generated, Runtime, and Sensitive Files

Generated output, dependencies, caches, logs, coverage reports, local databases, uploads, build artifacts, editor state, and operating-system files are excluded from version control unless a reviewed process explicitly requires an artifact. Generated files must be reproducible and clearly distinguishable from maintained source.

Secrets, credentials, private keys, access tokens, production data, personal data, and sensitive exports are prohibited in the repository and its history. Safe environment templates may contain variable names and non-sensitive descriptions only.

Media source assets may be versioned only when licensing, optimization, ownership, and update process are known. Tenant-uploaded media never belongs in the source repository.

## Governance

Engineering leadership owns repository structure. Creating a new top-level directory, moving an authoritative domain, or introducing a new package boundary requires review and corresponding documentation. This document is updated after foundational technology decisions and reviewed whenever the tree stops clearly reflecting ownership.

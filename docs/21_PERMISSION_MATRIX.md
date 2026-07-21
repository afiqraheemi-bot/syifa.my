# Permission Matrix

**Status: Draft — Under CTO Review.** This document is the authoritative Phase 1 RBAC (role-based access control) matrix for Syifa.my. It defines permissions only. It does not create a Laravel Policy, a middleware, or any other implementation artifact — those require separately governed engineering work once this matrix is approved.

## Table of Contents

- [Document Authority](#document-authority)
- [Purpose and Method](#purpose-and-method)
- [Roles Overview](#roles-overview)
- [Actions Vocabulary](#actions-vocabulary)
- [Matrix Legend and Reason Codes](#matrix-legend-and-reason-codes)
- [1. Resource Permission Matrix](#1-resource-permission-matrix)
- [2. Role Permission Matrix](#2-role-permission-matrix)
- [3. Assignment Rules](#3-assignment-rules)
- [4. Ownership Rules](#4-ownership-rules)
- [5. Tenant Boundary Rules](#5-tenant-boundary-rules)
- [6. Cross-Tenant Rules](#6-cross-tenant-rules)
- [7. Audit Requirements](#7-audit-requirements)
- [8. Privilege Escalation Prevention](#8-privilege-escalation-prevention)
- [9. Security Principles](#9-security-principles)
- [10. CTO Recommendations](#10-cto-recommendations)

## Document Authority

This document is the authoritative Phase 1 authorization matrix for Syifa.my. It applies the role model in [02_MVP_SCOPE.md](./02_MVP_SCOPE.md) and [14_DOMAIN_MODEL.md](./14_DOMAIN_MODEL.md), the tenant-isolation invariants in [ADR-002](./decisions/ADR-002-Multi-Tenant-Strategy.md), the identity and access control objectives in [06_SECURITY_STANDARD.md](./06_SECURITY_STANDARD.md), the authorization approach selected in [ADR-003](./decisions/ADR-003-Technology-Stack.md) (Decision 7, framework-native policy classes co-located with each aggregate), and the resource catalogue in [20_API_DESIGN.md](./20_API_DESIGN.md), against every named action for every resource. It does not replace any of these documents — it is the exhaustive, action-by-action extension of 20_API_DESIGN.md's own Authorization Matrix, which stated the general shape of these rules but not every action for every resource. [28_COMMERCIAL_CATALOGUE_SPECIFICATION.md](./28_COMMERCIAL_CATALOGUE_SPECIFICATION.md) resolved a prior conflict in this document's treatment of Plan and Add-On (Section 4, Ownership Rules below) — that resolution is reflected directly in this document rather than left as an unreconciled cross-reference.

Where this document states a permission that conflicts with 20_API_DESIGN.md's narrower Endpoint Matrix, this document controls for authorization purposes, and 20_API_DESIGN.md should be read as the currently implemented subset of the fuller authorization surface defined here. This document does not authorize implementation: no Laravel Policy, Gate, middleware, route, or database access-control mechanism is created here.

## Purpose and Method

Every cell in the matrices below answers one question: **may this role perform this action on this resource, and if not, why not?** Three distinct kinds of "no" are deliberately never collapsed into one:

1. **Denied** — the action exists as a real operation on this resource, but this specific role has no standing to perform it (a security/authorization boundary).
2. **Not Applicable** — the action does not exist as a meaningful operation on this resource at all, for any role (a resource-design fact, not a security boundary) — for example, Booking has no `Delete` for any role, because 19_DATABASE_STRATEGY.md's Deletion Matrix already established that Booking must never use deletion.
3. **Conditional** — the action exists and this role may perform it, but only within a stated scope (own Tenant, an active assignment, an authorized category, or an explicit privileged/purpose-limited pathway).

Collapsing these three into a single "no" would hide exactly the distinction a security review needs: whether a gap is a deliberate design boundary or a genuine authorization rule to test.

## Roles Overview

Syifa.my recognizes exactly four Phase 1 roles, per 02_MVP_SCOPE.md. No fifth role, and no resource-specific sub-role, is introduced anywhere in this document.

- **Public Visitor (PV).** Unauthenticated. Accesses only published content (outside this API's JSON surface, per 20_API_DESIGN.md's note on server-rendered public pages) and the narrow, genuinely interactive public actions: Clinic Registration submission, live Clinic Service availability, and Booking submission/own-Booking management via a confirmation reference. Never holds a session, a Tenant relationship, or any standing authority.
- **Clinic Owner (CO).** The accountable customer role for exactly one Tenant at a time per authority relationship (a person may hold separate Clinic Owner Authority for more than one Tenant, but each is independent and non-transferable between Tenants). Full authority within their own Tenant's boundary, subject to platform-governed constraints; no authority over any other Tenant or over platform-owned resources.
- **Website Designer (WD).** An internal Syifa.my role whose tenant access exists **only** through an active Website Designer Assignment to a specific Onboarding Job. No assignment means no access — this is not a default-deny-with-exceptions model, it is a default-zero-access model that an assignment specifically and narrowly grants.
- **Super Admin (SA).** An internal Syifa.my role with platform-wide administrative capability, exercised only through explicit, purpose-limited, audited privileged pathways. Never an implicit member of every Tenant; never permitted to reuse a Clinic-Owner-facing pathway to reach the same outcome a privileged pathway is meant to gate.

## Actions Vocabulary

Seventeen actions are evaluated against every resource. Several do not map onto a generic CRUD vocabulary by design — that mismatch is itself informative, and is preserved rather than forced into a false fit.

| Action | Meaning |
|---|---|
| View | Read one specific instance of a resource. |
| List | Read a collection of a resource's instances. |
| Create | Bring a new instance of a resource into existence. |
| Update | Modify an existing instance's ordinary fields. |
| Delete | Permanently remove an instance. |
| Approve | Give accountable sign-off on content or a decision that requires it. |
| Publish | Make content or a configuration publicly live. |
| Assign | Establish a bounded authority or assignment relationship. |
| Cancel | Terminate an in-flight workflow before its natural completion. |
| Confirm | Affirm a pending state as accepted. |
| Complete | Close out a workflow at its natural, successful end. |
| Archive | Move an instance to reduced-access historical storage without deleting it (19_DATABASE_STRATEGY.md's Archive Policy — distinct from Delete). |
| Restore | Reverse a suspension, detachment, or archival back toward active status. |
| Configure | Set a governed configuration value distinct from ordinary content (Theme, Operating Hours, SEO/tracking configuration, Platform Setting values). |
| Export | Extract data out of the platform in a portable form. |
| Manage | A bundled administrative-control action for a resource whose lifecycle is expressed through named transitions not individually enumerated in this vocabulary (for example, Tenant suspension/offboarding) — always privileged, never a generic catch-all for an ordinary role. |
| Support | A privileged, purpose-limited, audited Super-Admin-only action performed on behalf of a Tenant for troubleshooting or correction, structurally distinct from any Clinic-Owner-facing pathway. |

## Matrix Legend and Reason Codes

**Access symbols:**

- ✅ **Full** — the role may perform this action, subject only to ordinary tenant-scoping already implied by the role (see Ownership Rules).
- 🔒 **Own** — allowed only on the Clinic Owner's own Tenant and its owned resources.
- 🔒 **Assigned** — allowed only on the Website Designer's actively assigned Tenant/Onboarding Job; ends the instant the assignment ends.
- 🔒 **Category** — allowed only within a Super Admin's explicitly authorized Setting/Template governance category, per 19_DATABASE_STRATEGY.md's rule that "not every Super Admin receives universal authority."
- 🔒 **Privileged** — an explicit, purpose-limited, audited Super Admin action, structurally separate from any ordinary pathway.
- ❌ **Denied** — the action exists on this resource, but this role has no standing to perform it. See Ownership Rules and Tenant Boundary Rules for why.
- **N/A [Rn]** — the action does not exist as a meaningful operation on this resource for any role. The reason code explains why, from the table below.

**Reason codes for Not Applicable cells:**

| Code | Reason |
|---|---|
| R1 | No delete path exists for this resource — a named business lifecycle state is used instead, per 19_DATABASE_STRATEGY.md's Deletion Matrix. |
| R2 | This resource has no publication concept. |
| R3 | No assignment relationship attaches to this resource directly; the relevant assignment lives on a different resource, cross-referenced in the row. |
| R4 | Confirm is Booking-specific semantics and does not apply here. |
| R5 | Complete is Booking- or Onboarding-Job-specific workflow closure and does not apply here. |
| R6 | This resource has no governed configuration surface distinct from its ordinary content. |
| R7 | This action's real equivalent belongs to a different resource's workflow, cross-referenced in the row. |
| R8 | This resource has no history-tiering concept requiring archival as a distinct action from its own lifecycle state. |
| R9 | No suspended, detached, or archived state exists on this resource to restore from. |
| R10 | This resource has no in-flight workflow to cancel. |
| R11 | Export is not a defined Phase 1 capability for this resource. |
| R12 | No bundled administrative action exists beyond the other named actions already listed for this resource — a generic catch-all is deliberately not introduced. |
| R13 | This action's effect is already fully expressed through another named action already listed for this resource (most often Create or Update), and introducing a second path to the same effect would create redundant, driftable authorization surface. |
| R14 | Listing is not meaningful for this resource — it is a singleton per parent (Tenant/Website), and View already serves the same need. |

A cell that is `N/A` is never a security gap — it is a statement that the operation itself does not exist. A cell that is `❌ Denied` is the actual authorization boundary a test suite must verify.

---

## 1. Resource Permission Matrix

Nineteen resources, matching 20_API_DESIGN.md's Resource Catalogue exactly. Column order: **PV · CO · WD · SA**.

### 1.1 Clinic Registration

| Action | PV | CO | WD | SA |
|---|---|---|---|---|
| View | 🔒 Own (via tracking reference) | N/A [R7] — no Tenant yet exists to own a Registration | ❌ | ✅ |
| List | ❌ | N/A [R7] | ❌ | ✅ |
| Create | ✅ (submit) | ❌ | ❌ | ❌ — SA reviews, never creates on an applicant's behalf |
| Update | 🔒 Own (correction resubmission only) | N/A [R7] | ❌ | ❌ |
| Delete | N/A [R1] | N/A [R1] | N/A [R1] | N/A [R1] |
| Approve | ❌ | ❌ | ❌ | ✅ (decision: approve/reject/request correction) |
| Publish | N/A [R2] | N/A [R2] | N/A [R2] | N/A [R2] |
| Assign | N/A [R3] — assignment attaches to the resulting Onboarding Job only, after approval | same | same | same |
| Cancel | 🔒 Own (withdrawal) | ❌ | ❌ | ❌ |
| Confirm | N/A [R4] | N/A [R4] | N/A [R4] | N/A [R4] |
| Complete | N/A [R5] | N/A [R5] | N/A [R5] | N/A [R5] |
| Archive | ❌ | ❌ | ❌ | 🔒 Privileged (decided, aged Registrations) |
| Restore | N/A [R9] — a withdrawn/rejected Registration is never restored; a new one is submitted | same | same | same |
| Configure | N/A [R6] | N/A [R6] | N/A [R6] | N/A [R6] |
| Export | ❌ | ❌ | ❌ | 🔒 Privileged |
| Manage | ❌ | ❌ | ❌ | ✅ (portfolio oversight) |
| Support | ❌ | ❌ | ❌ | ✅ (correction/exception handling) |

### 1.2 Tenant

| Action | PV | CO | WD | SA |
|---|---|---|---|---|
| View | ❌ | 🔒 Own (limited lifecycle fields only) | ❌ — no standing on Tenant as a resource | ✅ |
| List | ❌ | ❌ | ❌ | ✅ |
| Create | N/A [R13] — always a side effect of Clinic Registration approval | same | same | same |
| Update | N/A [R13] — identity is immutable; lifecycle moves only through named transitions (Manage, Restore, Archive) | same | same | same |
| Delete | N/A [R1] | N/A [R1] | N/A [R1] | N/A [R1] |
| Approve | N/A [R7] — belongs to Clinic Registration's Approve | same | same | same |
| Publish | N/A [R2] | N/A [R2] | N/A [R2] | N/A [R2] |
| Assign | ❌ | 🔒 Own (controlled ownership transfer only) | ❌ | ✅ (establish/revoke Clinic Owner Authority) |
| Cancel | N/A [R10] — use Manage for suspension/offboarding | same | same | same |
| Confirm | N/A [R4] | N/A [R4] | N/A [R4] | N/A [R4] |
| Complete | N/A [R5] | N/A [R5] | N/A [R5] | N/A [R5] |
| Archive | ❌ | ❌ | ❌ | 🔒 Privileged (offboarding-related) |
| Restore | ❌ | ❌ | ❌ | 🔒 Privileged (reactivation) |
| Configure | N/A [R6] | N/A [R6] | N/A [R6] | N/A [R6] |
| Export | ❌ | ❌ | ❌ | 🔒 Privileged |
| Manage | ❌ | ❌ | ❌ | ✅ (suspension, offboarding, and other named lifecycle transitions bundled here since they have no individual verb in this vocabulary) |
| Support | ❌ | ❌ | ❌ | ✅ |

### 1.3 Session

| Action | PV | CO | WD | SA |
|---|---|---|---|---|
| View | ❌ — no session exists before authentication | ✅ Own | ✅ Own | ✅ Own |
| List | N/A [R14] — a caller holds at most one current session | same | same | same |
| Create | ✅ (any caller with valid credentials, regardless of the role they will resolve to) | same | same | same (stricter mandatory MFA) |
| Update | N/A [R13] — a session is replaced by a new Create, never edited | same | same | same |
| Delete | ❌ | ✅ Own (logout) | ✅ Own | ✅ Own |
| Approve | N/A [R7] — MFA challenge verification is part of Create | same | same | same |
| Publish | N/A [R2] | N/A [R2] | N/A [R2] | N/A [R2] |
| Assign | N/A [R3] | N/A [R3] | N/A [R3] | N/A [R3] |
| Cancel | N/A [R10] — Delete (logout) is the correct action | same | same | same |
| Confirm | N/A [R13] — MFA challenge confirmation is part of Create | same | same | same |
| Complete | N/A [R5] | N/A [R5] | N/A [R5] | N/A [R5] |
| Archive | N/A [R8] | N/A [R8] | N/A [R8] | N/A [R8] |
| Restore | N/A [R9] | N/A [R9] | N/A [R9] | N/A [R9] |
| Configure | N/A [R6] — MFA enrollment lives on Profile | same | same | same |
| Export | N/A [R11] | N/A [R11] | N/A [R11] | N/A [R11] |
| Manage | ❌ | ❌ | ❌ | ✅ (forced session revocation, incident response) |
| Support | ❌ | ❌ | ❌ | ✅ |

### 1.4 Profile

| Action | PV | CO | WD | SA |
|---|---|---|---|---|
| View | ❌ | ✅ Own | ✅ Own | ✅ Own |
| List | N/A [R14] — inherently self-scoped, no cross-participant listing | same | same | same |
| Create | N/A [R13] — exists automatically once the underlying authority relationship is established | same | same | same |
| Update | ❌ | ✅ Own | ✅ Own | ✅ Own |
| Delete | N/A [R1] — no self-service account deletion; removal is a governed Tenant Owner Authority or workforce-offboarding action | same | same | same |
| Approve | N/A [R7] | N/A [R7] | N/A [R7] | N/A [R7] |
| Publish | N/A [R2] | N/A [R2] | N/A [R2] | N/A [R2] |
| Assign | N/A [R3] | N/A [R3] | N/A [R3] | N/A [R3] |
| Cancel | N/A [R10] | N/A [R10] | N/A [R10] | N/A [R10] |
| Confirm | ❌ | ✅ Own (MFA challenge verification) | ✅ Own | ✅ Own |
| Complete | N/A [R5] | N/A [R5] | N/A [R5] | N/A [R5] |
| Archive | N/A [R8] | N/A [R8] | N/A [R8] | N/A [R8] |
| Restore | N/A [R9] | N/A [R9] | N/A [R9] | N/A [R9] |
| Configure | ❌ | ✅ Own (MFA settings) | ✅ Own | ✅ Own |
| Export | N/A [R11] — a future subject-access-request capability is not yet approved Phase 1 scope | same | same | same |
| Manage | N/A [R12] | N/A [R12] | N/A [R12] | N/A [R12] |
| Support | ❌ | ❌ | ❌ | ✅ (privileged correction of another participant's profile) |

### 1.5 Clinic

| Action | PV | CO | WD | SA |
|---|---|---|---|---|
| View | ❌ | ✅ Own | 🔒 Assigned | ✅ |
| List | N/A [R14] — one Clinic per Tenant in Phase 1 (ADR-002) | same | same | same |
| Create | N/A [R13] — established automatically on Registration approval | same | same | same |
| Update | ❌ | ✅ Own | 🔒 Assigned (onboarding scope) | 🔒 Privileged (support only) |
| Delete | N/A [R1] — removal is part of governed Tenant offboarding | same | same | same |
| Approve | N/A [R7] — content/claim approval lives on Website Pages | same | same | same |
| Publish | N/A [R2] — Clinic itself has no publication state; Website does | same | same | same |
| Assign | N/A [R3] — no assignment attaches to Clinic directly | same | same | same |
| Cancel | N/A [R10] | N/A [R10] | N/A [R10] | N/A [R10] |
| Confirm | N/A [R4] | N/A [R4] | N/A [R4] | N/A [R4] |
| Complete | N/A [R5] | N/A [R5] | N/A [R5] | N/A [R5] |
| Archive | ❌ | ❌ | ❌ | 🔒 Privileged (long-offboarded Clinics) |
| Restore | ❌ | ❌ | ❌ | 🔒 Privileged |
| Configure | ❌ | ✅ Own (Operating Hours) | 🔒 Assigned | ❌ |
| Export | ❌ | 🔒 Own | ❌ | 🔒 Privileged |
| Manage | N/A [R12] | N/A [R12] | N/A [R12] | N/A [R12] |
| Support | ❌ | ❌ | ❌ | ✅ |

### 1.6 Website

| Action | PV | CO | WD | SA |
|---|---|---|---|---|
| View | ❌ — public consumption is server-rendered, not this API | ✅ Own | 🔒 Assigned | ✅ |
| List | N/A [R14] — one Website per Tenant in Phase 1 | same | same | same |
| Create | N/A [R13] — initialized automatically when its Onboarding Job begins | same | same | same |
| Update | ❌ | 🔒 Own (where approved self-service controls permit) | 🔒 Assigned | 🔒 Privileged |
| Delete | N/A [R1] — retirement is a lifecycle state | same | same | same |
| Approve | N/A [R7] — belongs to Onboarding Jobs' Website Approval workflow | same | same | same |
| Publish | ❌ | ✅ Own | ❌ — a Website Designer prepares but never publishes on the Clinic Owner's behalf | 🔒 Privileged |
| Assign | N/A [R3] | N/A [R3] | N/A [R3] | N/A [R3] |
| Cancel | N/A [R10] — use Update (unpublication) instead | same | same | same |
| Confirm | N/A [R4] | N/A [R4] | N/A [R4] | N/A [R4] |
| Complete | N/A [R5] | N/A [R5] | N/A [R5] | N/A [R5] |
| Archive | ❌ | ❌ | ❌ | 🔒 Privileged |
| Restore | ❌ | ❌ | ❌ | 🔒 Privileged |
| Configure | ❌ | 🔒 Own (SEO/tracking, where approved) | 🔒 Assigned | ❌ |
| Export | ❌ | ❌ | ❌ | 🔒 Privileged |
| Manage | N/A [R12] | N/A [R12] | N/A [R12] | N/A [R12] |
| Support | ❌ | ❌ | ❌ | ✅ |

### 1.7 Website Pages

| Action | PV | CO | WD | SA |
|---|---|---|---|---|
| View | ❌ | ✅ Own | 🔒 Assigned | ✅ |
| List | ❌ | ✅ Own | 🔒 Assigned | ✅ |
| Create | ❌ | 🔒 Own (where approved) | 🔒 Assigned | ❌ |
| Update | ❌ | 🔒 Own | 🔒 Assigned | 🔒 Privileged |
| Delete | ❌ | 🔒 Own (draft state only, per 19_DATABASE_STRATEGY.md) | 🔒 Assigned (draft only) | ❌ |
| Approve | ❌ | ✅ Own | ❌ — cannot approve content it prepared itself | ❌ |
| Publish | N/A [R7] — page-level exposure follows Website's own single Publish action | same | same | same |
| Assign | N/A [R3] | N/A [R3] | N/A [R3] | N/A [R3] |
| Cancel | N/A [R10] | N/A [R10] | N/A [R10] | N/A [R10] |
| Confirm | N/A [R4] | N/A [R4] | N/A [R4] | N/A [R4] |
| Complete | N/A [R5] | N/A [R5] | N/A [R5] | N/A [R5] |
| Archive | ❌ | ❌ | ❌ | 🔒 Privileged (superseded historical revisions) |
| Restore | N/A [R9] — a retired page is never restored; a new page is created | same | same | same |
| Configure | N/A [R13] — structure is set via Create/Update | same | same | same |
| Export | ❌ | ❌ | ❌ | 🔒 Privileged |
| Manage | N/A [R12] | N/A [R12] | N/A [R12] | N/A [R12] |
| Support | ❌ | ❌ | ❌ | ✅ |

---

### 1.8 Custom Domains

| Action | PV | CO | WD | SA |
|---|---|---|---|---|
| View | ❌ | ✅ Own | 🔒 Assigned | ✅ |
| List | ❌ | ✅ Own | 🔒 Assigned | ✅ |
| Create | ❌ | ✅ Own (request) | 🔒 Assigned | ❌ |
| Update | N/A [R13] — a domain is not edited in place; a changed domain is a new request plus detachment of the old | same | same | same |
| Delete | ❌ | ✅ Own (detach) | ❌ — cannot detach | 🔒 Privileged |
| Approve | N/A [R7] — verification and activation are their own named actions | same | same | same |
| Publish | N/A [R7] — Restore/Manage cover the domain-specific activation equivalent | same | same | same |
| Assign | N/A [R3] — association to a Website happens via Create/Manage, not a separate Assign | same | same | same |
| Cancel | ❌ | ✅ Own (cancel a pending verification) | 🔒 Assigned | ❌ |
| Confirm | N/A [R7] — verification submission is its own named action, not Confirm | same | same | same |
| Complete | N/A [R5] | N/A [R5] | N/A [R5] | N/A [R5] |
| Archive | ❌ | ❌ | ❌ | 🔒 Privileged (detached-domain history) |
| Restore | ❌ | ❌ | ❌ | 🔒 Privileged (reassignment after quarantine) |
| Configure | N/A [R13] | N/A [R13] | N/A [R13] | N/A [R13] |
| Export | ❌ | ❌ | ❌ | 🔒 Privileged |
| Manage | ❌ | ✅ Own (verification + activation bundle) | 🔒 Assigned | ✅ |
| Support | ❌ | ❌ | ❌ | ✅ |

### 1.9 Template

| Action | PV | CO | WD | SA |
|---|---|---|---|---|
| View | ❌ — not directly API-facing for the public | ✅ (read-only, selection context) | ✅ | ✅ |
| List | ❌ | ✅ | ✅ | ✅ |
| Create | ❌ | ❌ | ❌ | 🔒 Category (design governance) |
| Update | ❌ | ❌ | ❌ | 🔒 Category |
| Delete | N/A [R1] — retired, never deleted | same | same | same |
| Approve | ❌ | ❌ | ❌ | 🔒 Category |
| Publish | ❌ | ❌ | ❌ | 🔒 Category |
| Assign | N/A [R3] — Template selection on a Website is that Website's Update action | same | same | same |
| Cancel | N/A [R10] | N/A [R10] | N/A [R10] | N/A [R10] |
| Confirm | N/A [R4] | N/A [R4] | N/A [R4] | N/A [R4] |
| Complete | N/A [R5] | N/A [R5] | N/A [R5] | N/A [R5] |
| Archive | ❌ | ❌ | ❌ | 🔒 Category (deprecated Templates) |
| Restore | N/A [R9] — a retired Template is never restored | same | same | same |
| Configure | N/A [R13] — structure and Theme boundary are set via Update | same | same | same |
| Export | N/A [R11] | N/A [R11] | N/A [R11] | N/A [R11] |
| Manage | ❌ | ❌ | ❌ | 🔒 Category |
| Support | N/A [R12] — no per-tenant support concept; Template is platform-owned | same | same | same |

### 1.10 Media

| Action | PV | CO | WD | SA |
|---|---|---|---|---|
| View | ❌ — a published asset reaches the public only through the rendered page that references it, never this resource directly | ✅ Own | 🔒 Assigned | ✅ |
| List | ❌ | ✅ Own | 🔒 Assigned | ✅ |
| Create | ❌ | ✅ Own | 🔒 Assigned | ✅ (platform assets) |
| Update | ❌ | ✅ Own | 🔒 Assigned | ✅ |
| Delete | ❌ | ✅ Own (orphan-check gated) | 🔒 Assigned (orphan-check gated) | ✅ |
| Approve | ❌ | ✅ Own | 🔒 Assigned (may request, not grant) | ✅ (platform assets) |
| Publish | ❌ | ✅ Own | 🔒 Assigned | ✅ |
| Assign | N/A [R3] — usage association happens via the consuming resource (Website Content, Onboarding Task) | same | same | same |
| Cancel | N/A [R10] | N/A [R10] | N/A [R10] | N/A [R10] |
| Confirm | N/A [R4] | N/A [R4] | N/A [R4] | N/A [R4] |
| Complete | N/A [R5] | N/A [R5] | N/A [R5] | N/A [R5] |
| Archive | N/A [R8] — storage-tier archival is an operational decision, not a role-facing action | same | same | same |
| Restore | ❌ | ❌ | ❌ | 🔒 Privileged (un-quarantine a moderated asset) |
| Configure | N/A [R13] — metadata is set via Update | same | same | same |
| Export | ❌ | ❌ | ❌ | 🔒 Privileged |
| Manage | N/A [R12] | N/A [R12] | N/A [R12] | N/A [R12] |
| Support | ❌ | ❌ | ❌ | ✅ (moderation) |

### 1.11 Clinic Services

| Action | PV | CO | WD | SA |
|---|---|---|---|---|
| View | 🔒 (live availability only, via the dedicated public read — never the full record) | ✅ Own | 🔒 Assigned | ✅ |
| List | ❌ | ✅ Own | 🔒 Assigned | ✅ |
| Create | ❌ | ✅ Own | 🔒 Assigned | ❌ |
| Update | ❌ | ✅ Own | 🔒 Assigned | 🔒 Privileged |
| Delete | N/A [R1] — retired, never deleted; explicit invariant per 18_AGGREGATE_DESIGN.md and 19_DATABASE_STRATEGY.md | same | same | same |
| Approve | N/A [R7] — service-meaning approval is inherent to Create/Update by the Clinic Owner | same | same | same |
| Publish | ❌ | ✅ Own (presentation publish, distinct from becoming bookable) | 🔒 Assigned | ❌ |
| Assign | N/A [R3] — Location/Practitioner associations are Update, not Assign | same | same | same |
| Cancel | N/A [R10] | N/A [R10] | N/A [R10] | N/A [R10] |
| Confirm | N/A [R4] | N/A [R4] | N/A [R4] | N/A [R4] |
| Complete | N/A [R5] | N/A [R5] | N/A [R5] | N/A [R5] |
| Archive | N/A [R8] | N/A [R8] | N/A [R8] | N/A [R8] |
| Restore | N/A [R9] | N/A [R9] | N/A [R9] | N/A [R9] |
| Configure | ❌ | ✅ Own (availability schedules/exceptions) | 🔒 Assigned | ❌ |
| Export | ❌ | ❌ | ❌ | 🔒 Privileged |
| Manage | N/A [R12] | N/A [R12] | N/A [R12] | N/A [R12] |
| Support | ❌ | ❌ | ❌ | ✅ |

### 1.12 Booking

| Action | PV | CO | WD | SA |
|---|---|---|---|---|
| View | 🔒 Own (via confirmation reference only — never a list, never another visitor's Booking) | ✅ Own | ❌ — no standing on Booking per 18_AGGREGATE_DESIGN.md | ✅ |
| List | ❌ | ✅ Own | ❌ | ✅ |
| Create | ✅ (submit) | ❌ — cannot submit on a visitor's behalf in Phase 1 | ❌ | ❌ |
| Update | N/A [R13] — explicitly modeled as a workflow; every change is a named action, never a generic edit | same | same | same |
| Delete | N/A [R1] — explicitly forbidden; Booking must not use generic deletion (19_DATABASE_STRATEGY.md) | same | same | same |
| Approve | N/A [R7] — Confirm is the analogous action for Booking | same | same | same |
| Publish | N/A [R2] | N/A [R2] | N/A [R2] | N/A [R2] |
| Assign | N/A [R3] | N/A [R3] | N/A [R3] | N/A [R3] |
| Cancel | 🔒 Own | ✅ Own | ❌ | 🔒 Privileged (support correction only) |
| Confirm | ❌ | 🔒 Own (if manual-confirmation policy applies; otherwise system-triggered) | ❌ | ❌ — not an ordinary Super Admin action |
| Complete | ❌ | ✅ Own | ❌ | ❌ |
| Archive | ❌ | ❌ | ❌ | 🔒 Privileged (old completed/cancelled Bookings) |
| Restore | N/A [R9] | N/A [R9] | N/A [R9] | N/A [R9] |
| Configure | N/A [R6] | N/A [R6] | N/A [R6] | N/A [R6] |
| Export | ❌ | 🔒 Own | ❌ | 🔒 Privileged |
| Manage | N/A [R12] | N/A [R12] | N/A [R12] | N/A [R12] |
| Support | ❌ | ❌ | ❌ | ✅ (correction only) |

### 1.13 Subscription

| Action | PV | CO | WD | SA |
|---|---|---|---|---|
| View | ❌ | ✅ Own | ❌ — no commercial authority per ADR-002 | ✅ |
| List | ❌ | ✅ Own | ❌ | ✅ |
| Create | ❌ | ✅ Own | ❌ | ❌ |
| Update | N/A [R13] — every change is a named commercial action (plan change, cancellation, reactivation), never a generic edit | same | same | same |
| Delete | N/A [R1] | N/A [R1] | N/A [R1] | N/A [R1] |
| Approve | N/A [R7] | N/A [R7] | N/A [R7] | N/A [R7] |
| Publish | N/A [R2] | N/A [R2] | N/A [R2] | N/A [R2] |
| Assign | N/A [R3] | N/A [R3] | N/A [R3] | N/A [R3] |
| Cancel | ❌ | ✅ Own | ❌ | 🔒 Privileged |
| Confirm | N/A [R4] | N/A [R4] | N/A [R4] | N/A [R4] |
| Complete | N/A [R5] | N/A [R5] | N/A [R5] | N/A [R5] |
| Archive | ❌ | ❌ | ❌ | 🔒 Privileged (expired/cancelled history) |
| Restore | ❌ | ✅ Own (reactivation) | ❌ | 🔒 Privileged |
| Configure | N/A [R13] — Plan change is the named action | same | same | same |
| Export | ❌ | 🔒 Own | ❌ | 🔒 Privileged |
| Manage | N/A [R12] | N/A [R12] | N/A [R12] | N/A [R12] |
| Support | ❌ | ❌ | ❌ | ✅ |

---

### 1.14 Invoices

| Action | PV | CO | WD | SA |
|---|---|---|---|---|
| View | ❌ | ✅ Own | ❌ | ✅ |
| List | ❌ | ✅ Own | ❌ | ✅ |
| Create | N/A [R13] — system-generated only, never directly authored by any role | same | same | same |
| Update | N/A [R1] — must never be silently rewritten (19_DATABASE_STRATEGY.md) | same | same | same |
| Delete | N/A [R1] | N/A [R1] | N/A [R1] | N/A [R1] |
| Approve | N/A [R7] | N/A [R7] | N/A [R7] | N/A [R7] |
| Publish | N/A [R2] | N/A [R2] | N/A [R2] | N/A [R2] |
| Assign | N/A [R3] | N/A [R3] | N/A [R3] | N/A [R3] |
| Cancel | N/A [R10] — an Invoice's void/cancel state is a system commercial-policy outcome in Phase 1, not role-initiated | same | same | same |
| Confirm | N/A [R4] | N/A [R4] | N/A [R4] | N/A [R4] |
| Complete | N/A [R5] | N/A [R5] | N/A [R5] | N/A [R5] |
| Archive | ❌ | ❌ | ❌ | 🔒 Privileged |
| Restore | N/A [R9] | N/A [R9] | N/A [R9] | N/A [R9] |
| Configure | N/A [R6] | N/A [R6] | N/A [R6] | N/A [R6] |
| Export | ❌ | 🔒 Own | ❌ | 🔒 Privileged |
| Manage | N/A [R12] | N/A [R12] | N/A [R12] | N/A [R12] |
| Support | ❌ | ❌ | ❌ | ✅ |

### 1.15 Payments

| Action | PV | CO | WD | SA |
|---|---|---|---|---|
| View | ❌ | ✅ Own | ❌ | ✅ |
| List | ❌ | ✅ Own | ❌ | ✅ |
| Create | ❌ | ❌ Phase 1 | 🔒 Assigned platform workflow only | 🔒 Category |
| Update | N/A [R1] — immutable once recorded; a correction is a new, linked record, never an edit | same | same | same |
| Delete | N/A [R1] | N/A [R1] | N/A [R1] | N/A [R1] |
| Approve | N/A [R7] | N/A [R7] | N/A [R7] | N/A [R7] |
| Publish | N/A [R2] | N/A [R2] | N/A [R2] | N/A [R2] |
| Assign | N/A [R3] | N/A [R3] | N/A [R3] | N/A [R3] |
| Cancel | ❌ | ❌ Phase 1 | ❌ | 🔒 Category, only if Payment application policy and provider capability permit |
| Confirm | N/A [R7] — outcome recording is provider-driven reconciliation, not a role action | same | same | same |
| Complete | N/A [R5] | N/A [R5] | N/A [R5] | N/A [R5] |
| Archive | ❌ | ❌ | ❌ | 🔒 Privileged |
| Restore | N/A [R9] | N/A [R9] | N/A [R9] | N/A [R9] |
| Configure | N/A [R6] | N/A [R6] | N/A [R6] | N/A [R6] |
| Export | ❌ | 🔒 Own | ❌ | 🔒 Privileged |
| Manage | N/A [R12] | N/A [R12] | N/A [R12] | N/A [R12] |
| Support | ❌ | ❌ | ❌ | ✅ (reconciliation) |

Phase 1 Payment initiation is platform-assisted only. It may be performed only by an authenticated Platform Identity that owns the relevant Clinic Registration and owns the CommercialOffer being claimed. Ownership is derived from the trusted PlatformPrincipal, never from client-supplied identity fields. Clinic Owner self-service Payment initiation requires a future ADR or approved implementation decision.

### 1.16 Onboarding Jobs

| Action | PV | CO | WD | SA |
|---|---|---|---|---|
| View | ❌ | ✅ Own | 🔒 Assigned | ✅ |
| List | ❌ | ✅ Own | 🔒 Assigned | ✅ |
| Create | N/A [R13] — created automatically on Tenant provisioning (SA's exception path is under Manage) | same | same | same |
| Update | N/A [R13] — every change is a named workflow action | same | same | same |
| Delete | N/A [R1] | N/A [R1] | N/A [R1] | N/A [R1] |
| Approve | ❌ | ✅ Own (Website Approval decision) | ❌ (may request, not grant) | ❌ |
| Publish | N/A [R7] — belongs to Website's own Publish action, gated by this Job's Launch Readiness | same | same | same |
| Assign | ❌ | ❌ | ❌ | ✅ (Website Designer Assignment) |
| Cancel | ❌ | ❌ | ❌ | ✅ |
| Confirm | N/A [R4] | N/A [R4] | N/A [R4] | N/A [R4] |
| Complete | ❌ | ❌ | ❌ | ✅ |
| Archive | ❌ | ❌ | ❌ | 🔒 Privileged (completed Jobs outside review window) |
| Restore | ❌ | ❌ | ❌ | ✅ (controlled reopening) |
| Configure | N/A [R6] | N/A [R6] | N/A [R6] | N/A [R6] |
| Export | ❌ | ❌ | ❌ | 🔒 Privileged |
| Manage | ❌ | ❌ | ❌ | ✅ (includes privileged exception creation) |
| Support | ❌ | ❌ | ❌ | ✅ |

### 1.17 Notifications

| Action | PV | CO | WD | SA |
|---|---|---|---|---|
| View | ❌ | ✅ Own | ❌ — no standing per 18_AGGREGATE_DESIGN.md | ✅ |
| List | ❌ | ✅ Own | ❌ | ✅ |
| Create | N/A [R13] — originates no business truth of its own; never directly created by any role | same | same | same |
| Update | N/A [R1] | N/A [R1] | N/A [R1] | N/A [R1] |
| Delete | N/A [R1] | N/A [R1] | N/A [R1] | N/A [R1] |
| Approve | N/A [R7] | N/A [R7] | N/A [R7] | N/A [R7] |
| Publish | N/A [R2] | N/A [R2] | N/A [R2] | N/A [R2] |
| Assign | N/A [R3] | N/A [R3] | N/A [R3] | N/A [R3] |
| Cancel | N/A [R10] — suppression is a system policy outcome in Phase 1, not role-initiated | same | same | same |
| Confirm | N/A [R4] | N/A [R4] | N/A [R4] | N/A [R4] |
| Complete | N/A [R5] | N/A [R5] | N/A [R5] | N/A [R5] |
| Archive | ❌ | ❌ | ❌ | 🔒 Privileged (delivery history) |
| Restore | N/A [R9] | N/A [R9] | N/A [R9] | N/A [R9] |
| Configure | N/A [R6] — Notification Template governance is a separate, non-API-exposed reference-data concern | same | same | same |
| Export | ❌ | ❌ | ❌ | 🔒 Privileged |
| Manage | N/A [R12] | N/A [R12] | N/A [R12] | N/A [R12] |
| Support | ❌ | ❌ | ❌ | ✅ (remediation) |

### 1.18 Reports

| Action | PV | CO | WD | SA |
|---|---|---|---|---|
| View | ❌ | ✅ Own | 🔒 Own workload | ✅ |
| List | ❌ | ✅ Own | 🔒 Own workload | ✅ |
| Create | ❌ | ✅ Own (request generation) | 🔒 Own workload | ✅ (portfolio scope) |
| Update | N/A [R13] — a Report is regenerated (Create), never edited | same | same | same |
| Delete | N/A [R1] | N/A [R1] | N/A [R1] | N/A [R1] |
| Approve | N/A [R7] | N/A [R7] | N/A [R7] | N/A [R7] |
| Publish | N/A [R2] | N/A [R2] | N/A [R2] | N/A [R2] |
| Assign | N/A [R3] | N/A [R3] | N/A [R3] | N/A [R3] |
| Cancel | N/A [R10] | N/A [R10] | N/A [R10] | N/A [R10] |
| Confirm | N/A [R4] | N/A [R4] | N/A [R4] | N/A [R4] |
| Complete | N/A [R5] | N/A [R5] | N/A [R5] | N/A [R5] |
| Archive | N/A [R8] — derived and rebuildable; pruned rather than archived | same | same | same |
| Restore | N/A [R9] | N/A [R9] | N/A [R9] | N/A [R9] |
| Configure | N/A [R6] — Metric Definition governance is a separate, non-API-exposed reference-data concern | same | same | same |
| Export | ❌ | 🔒 Own (where approved) | ❌ | 🔒 Privileged |
| Manage | N/A [R12] | N/A [R12] | N/A [R12] | N/A [R12] |
| Support | ❌ | ❌ | ❌ | ✅ |

### 1.19 Platform Settings

| Action | PV | CO | WD | SA |
|---|---|---|---|---|
| View | N/A [R12] — Clinic Owner and Website Designer have no standing on any platform-owned resource, see Ownership Rules | N/A [R12] | N/A [R12] | 🔒 Category |
| List | N/A [R12] | N/A [R12] | N/A [R12] | 🔒 Category |
| Create | ❌ | ❌ | ❌ | 🔒 Category |
| Update | N/A [R13] — lifecycle transition (Approve/Publish/Restore/Archive) replaces a generic Update | same | same | same |
| Delete | N/A [R1] | N/A [R1] | N/A [R1] | N/A [R1] |
| Approve | ❌ | ❌ | ❌ | 🔒 Category |
| Publish | ❌ | ❌ | ❌ | 🔒 Category (activation) |
| Assign | N/A [R3] | N/A [R3] | N/A [R3] | N/A [R3] |
| Cancel | N/A [R10] | N/A [R10] | N/A [R10] | N/A [R10] |
| Confirm | N/A [R4] | N/A [R4] | N/A [R4] | N/A [R4] |
| Complete | N/A [R5] | N/A [R5] | N/A [R5] | N/A [R5] |
| Archive | ❌ | ❌ | ❌ | 🔒 Category (retire) |
| Restore | N/A [R9] — a retired Setting is never restored; a new one is proposed | same | same | same |
| Configure | N/A [R13] — value is set via Create/Update within its lifecycle | same | same | same |
| Export | N/A [R11] | N/A [R11] | N/A [R11] | N/A [R11] |
| Manage | N/A [R12] | N/A [R12] | N/A [R12] | N/A [R12] |
| Support | N/A [R12] — no per-tenant support concept; platform-owned | same | same | same |

### 1.20 Commercial Catalogue (Plan, Billing Option, Plan Offering, Capability Catalogue)

Per 28_COMMERCIAL_CATALOGUE_SPECIFICATION.md, resolving this document's prior "not independently exposed" statement for Plan and Add-On: Plan, Billing Option, Plan Offering, and Capability Catalogue are governed, platform-owned commercial configuration, authored only through this resource family, following exactly the Platform Settings access pattern below. Plan Offering — the specific record connecting Plan, Billing Option, Price, effective period, capability-package/configuration version, and availability — has its own explicit endpoint family in 20_API_DESIGN.md (`/api/v1/platform/commercial-catalogue/plan-offerings`) but the identical permission rows below, since it is authorized exactly like every other Commercial Catalogue entry: Super Admin only, category-scoped, audited, never Clinic Owner, Website Designer, or Public Visitor. A lifetime (non-recurring) Plan Offering additionally may never be activated in Phase 1 regardless of caller authority (28_COMMERCIAL_CATALOGUE_SPECIFICATION.md, Lifetime Offering Rules) — no role, including Super Admin, may bypass that restriction through this resource. Add-On remains deferred (28_COMMERCIAL_CATALOGUE_SPECIFICATION.md, Add-On Decision) and is not exposed here or anywhere else in Phase 1.

| Action | PV | CO | WD | SA |
|---|---|---|---|---|
| View | N/A [R12] — Clinic Owner and Website Designer have no standing on any platform-owned resource, see Ownership Rules | N/A [R12] | N/A [R12] | 🔒 Category |
| List | N/A [R12] | N/A [R12] | N/A [R12] | 🔒 Category |
| Create | ❌ | ❌ | ❌ | 🔒 Category |
| Update | N/A [R13] — lifecycle transition (Approve/Publish/Restore/Archive) replaces a generic Update | same | same | same |
| Delete | N/A [R1] — a catalogue entry is retired or deprecated, never deleted, to preserve historical Subscription snapshot integrity | N/A [R1] | N/A [R1] | N/A [R1] |
| Approve | ❌ | ❌ | ❌ | 🔒 Category |
| Publish | ❌ | ❌ | ❌ | 🔒 Category (activation) |
| Assign | N/A [R3] | N/A [R3] | N/A [R3] | N/A [R3] |
| Cancel | N/A [R10] | N/A [R10] | N/A [R10] | N/A [R10] |
| Confirm | N/A [R4] | N/A [R4] | N/A [R4] | N/A [R4] |
| Complete | N/A [R5] | N/A [R5] | N/A [R5] | N/A [R5] |
| Archive | ❌ | ❌ | ❌ | 🔒 Category (retire) |
| Restore | N/A [R9] — a retired Plan, Billing Option, or Capability is never restored; a new one is proposed | same | same | same |
| Configure | N/A [R13] — value is set via Create/Update within its lifecycle | same | same | same |
| Export | N/A [R11] | N/A [R11] | N/A [R11] | N/A [R11] |
| Manage | N/A [R12] | N/A [R12] | N/A [R12] | N/A [R12] |
| Support | N/A [R12] — no per-tenant support concept; platform-owned | same | same | same |

---

### 1.21 Commercial Offers

CommercialOffer is a checkout-preparation Aggregate Root owned by the Commercial context. It is not public browsing, not Commercial Catalogue authoring, not Payment, not Subscription activation, and not Tenant provisioning.

Commercial Offer endpoints require authenticated Platform Identity. The actor identity must come from the server-side PlatformPrincipalResolver, never from request body, query string, headers, or client-supplied DTO fields.

| Action | PV | CO | WD | SA |
|---|---|---|---|---|
| View | ❌ | ❌ | 🔒 Assigned platform workflow only | 🔒 Category |
| List | ❌ | ❌ | 🔒 Assigned platform workflow only | 🔒 Category |
| Create | ❌ | ❌ | 🔒 Assigned platform workflow only | 🔒 Category |
| Update | N/A [R13] — lifecycle actions replace generic update | same | same | same |
| Delete | N/A [R1] — CommercialOffer is cancelled, expired, or claimed; never deleted | N/A [R1] | N/A [R1] | N/A [R1] |
| Cancel | ❌ | ❌ | 🔒 Assigned platform workflow only | 🔒 Category |
| Archive | N/A [R10] | N/A [R10] | N/A [R10] | N/A [R10] |
| Restore | N/A [R9] | N/A [R9] | N/A [R9] | N/A [R9] |
| Configure | N/A [R13] | N/A [R13] | N/A [R13] | N/A [R13] |
| Manage | N/A [R12] | N/A [R12] | N/A [R12] | N/A [R12] |

CommercialOffer mutations require mandatory Audit Entry coverage:

- `commercial.offer.prepare`
- `commercial.offer.cancel`
- `commercial.offer.expire`
- `commercial.offer.claim`

Claiming a CommercialOffer means exclusive binding to one Payment ID. It is not proof of payment success and is not Subscription activation, Tenant provisioning, or Onboarding.

---

## 2. Role Permission Matrix

The same data as Section 1, pivoted by role rather than by resource — useful for a role-centric security review ("what can a Website Designer reach, system-wide?") rather than a resource-centric one.

### 2.1 Public Visitor

| Resource | Access Level | Scope |
|---|---|---|
| Clinic Registration | Submit, view own, withdraw | Own submission only, via tracking reference |
| Tenant | None | No standing |
| Session | Authenticate only | No session exists until credentials resolve |
| Profile | None | No standing |
| Clinic | None | Public content is server-rendered, outside this API |
| Website | None | Public content is server-rendered, outside this API |
| Website Pages | None | Public content is server-rendered, outside this API |
| Custom Domains | None | No standing |
| Template | None | No standing |
| Media | None | Public media reaches the visitor only through the rendered page, never this resource |
| Clinic Services | Live availability read only | The one genuinely interactive public catalogue read |
| Booking | Submit, view own, cancel own | Via confirmation reference only; never a list, never another visitor's Booking |
| Subscription | None | No standing |
| Invoices | None | No standing |
| Payments | None | No standing |
| Onboarding Jobs | None | No standing |
| Notifications | None | No standing |
| Reports | None | No standing |
| Platform Settings | None | No standing |

**Summary:** Public Visitor's entire authorized surface is four resources (Clinic Registration, Clinic Services' availability read, Booking, and unauthenticated Session creation) — exactly the interactive actions 20_API_DESIGN.md identified as unable to be served by server rendering alone. Every other resource is either private administrative data or public content delivered outside this API entirely.

### 2.2 Clinic Owner

| Resource | Access Level | Scope |
|---|---|---|
| Clinic Registration | None (post-approval) | Authority transferred to Tenant/Clinic once approved |
| Tenant | View (limited), controlled ownership transfer | Own Tenant only, lifecycle fields limited |
| Session | Full (own) | Own session only |
| Profile | Full (own) | Own profile only |
| Clinic | Full | Own Tenant only |
| Website | Content/theme (where approved), publish/unpublish | Own Tenant only |
| Website Pages | Create/edit/approve (where approved), delete draft only | Own Tenant only |
| Custom Domains | Request/verify/activate/detach | Own Tenant only |
| Template | Read-only | Selection context only; no authoring |
| Media | Full | Own Tenant assets only |
| Clinic Services | Full | Own Tenant only |
| Booking | View/manage, cancel, confirm (per policy), complete | Own Tenant's Bookings only |
| Subscription | Full commercial actions | Own Tenant only |
| Invoices | Read-only | Own Tenant only |
| Payments | View own Phase 1 records only; no self-service initiation/retry in Phase 1 | Own Tenant only |
| Onboarding Jobs | View, task input, approval decisions | Own Tenant's Job only |
| Notifications | Read-only | Own Tenant only |
| Reports | Full (own scope) | Own Tenant scope only |
| Platform Settings | None | No standing — see the explicit rule below |

**Summary:** Clinic Owner has broad, often full authority — but always bounded to exactly one Tenant per authority relationship, and **never** extends to any platform-owned resource (Template authoring, Platform Settings) or to another Tenant's data under any circumstance. This directly implements the brief's explicit rule: **Clinic Owner cannot access platform resources.**

### 2.3 Website Designer

| Resource | Access Level | Scope |
|---|---|---|
| Clinic Registration | None | No role in registration |
| Tenant | None | No standing on Tenant as a resource |
| Session | Full (own) | Own session only |
| Profile | Full (own) | Own profile only |
| Clinic | View/update | Assigned onboarding scope only |
| Website | Content/theme preparation | Assigned onboarding scope only; cannot publish |
| Website Pages | Create/edit, delete draft only | Assigned onboarding scope only; cannot approve own prepared content |
| Custom Domains | Request/verify/activate | Assigned onboarding scope only; cannot detach |
| Template | Read + selection | No authoring authority |
| Media | Full for assigned Tenant + private onboarding assets | Assigned onboarding scope only |
| Clinic Services | Full | Assigned onboarding scope only |
| Booking | None | No standing per 18_AGGREGATE_DESIGN.md — booking operations are Clinic Owner's own commercial workflow |
| Subscription | None | No commercial authority per ADR-002 |
| Invoices | None | No commercial authority |
| Payments | None | No commercial authority |
| Onboarding Jobs | Full within assignment | Cannot self-assign, reassign, cancel, or complete the Job (Super Admin only) |
| Notifications | None | No standing |
| Reports | Own workload only | Assigned-project progress and workload views only |
| Platform Settings | None | No standing |

**Summary:** Website Designer access exists **only** through an active Website Designer Assignment to a specific Onboarding Job, and even within that assignment it is deliberately narrower than Clinic Owner's own authority (no Booking access, no commercial resource access, no publish authority, no ability to approve its own prepared content). This directly implements the brief's explicit rule: **Website Designer only has access to assigned Tenants** — see Assignment Rules below for exactly how that boundary is established and enforced.

### 2.4 Super Admin

| Resource | Access Level | Scope |
|---|---|---|
| Clinic Registration | Review, decide, portfolio view | Platform-wide, ordinary function |
| Tenant | Full lifecycle control | Platform-wide, privileged for mutating actions |
| Session | Full (own), forced revocation | Own session ordinary; others' sessions privileged (incident response) |
| Profile | Full (own), privileged correction of others | Own ordinary; others' privileged |
| Clinic | View/privileged support only | Cross-tenant view ordinary (portfolio); mutation privileged |
| Website | Privileged unpublish/support only | Cannot author content; support/exception actions only |
| Website Pages | View, privileged support only | Cannot author content |
| Custom Domains | Privileged activation/detachment | Exception handling, not routine management |
| Template | Full authoring | Category-scoped even within Super Admin — not every Super Admin holds design-governance authority |
| Media | Full, including platform assets | Cross-tenant moderation and platform-asset ownership |
| Clinic Services | View, privileged support only | Cannot author a Tenant's service catalogue |
| Booking | View, privileged correction only | Cannot submit, confirm, or complete on a Tenant's behalf |
| Subscription | Controlled administrative actions | Privileged, not routine commercial self-service |
| Invoices | Read-only, any | Portfolio visibility |
| Payments | View any, reconciliation support, authorized platform-assisted initiation where category-scoped | Cannot bypass CommercialOffer claim or Payment provider verification |
| Onboarding Jobs | Full, privileged | Assignment, lifecycle control, and exception handling are exclusively Super Admin functions |
| Notifications | Read-only, any + remediation | Portfolio visibility and delivery remediation only |
| Reports | Portfolio scope | Explicit privileged, minimized cross-tenant aggregation path, never the ordinary Clinic Owner pathway with scoping disabled |
| Platform Settings | Category-scoped | Not every Super Admin holds every category's authority |
| Commercial Catalogue | Category-scoped | Not every Super Admin holds Commercial Catalogue authority; mandatory Audit Entry on every mutation (28_COMMERCIAL_CATALOGUE_SPECIFICATION.md) |

**Summary:** Super Admin's authority is broad in *reach* but narrow in *routine use* — nearly every mutating cross-tenant or platform-wide action is marked 🔒 Privileged or 🔒 Category in Section 1, meaning it requires an explicit, purpose-limited, audited pathway rather than being a standing, unconditional grant. See Cross-Tenant Rules, Audit Requirements, and Privilege Escalation Prevention below for exactly how this is enforced.

## 3. Assignment Rules

Two distinct assignment mechanisms exist in Syifa.my, and they are never conflated:

**Clinic Owner Authority** (18_AGGREGATE_DESIGN.md — composed within the Tenant aggregate):

- Established only through an explicit, controlled verification process — 14_DOMAIN_MODEL.md is explicit that authority "cannot be inferred from email, domain, Subscription payment, or possession of a link."
- Scoped to exactly one Tenant per authority relationship; a person holding Clinic Owner Authority for multiple Tenants holds separate, independently revocable relationships, never one authority that spans Tenants.
- Established and revoked only by Super Admin, or transferred through a controlled process the existing Clinic Owner may initiate but not unilaterally complete.
- Revocation invalidates active sessions for that authority promptly (05_MULTI_TENANCY.md) — a revoked Clinic Owner's existing session does not continue to grant access until it naturally expires.

**Website Designer Assignment** (18_AGGREGATE_DESIGN.md — composed within the Onboarding Job aggregate):

- **This is the mechanism that implements the brief's explicit rule: Website Designer only has access to assigned Tenants.** Access is not a standing grant scoped by role membership — it is a per-Onboarding-Job relationship that must exist, be active, and name the specific Website Designer before any of that Designer's resource access in Sections 1–2 becomes valid.
- Established and revoked only by Super Admin (Section 1.16, Onboarding Jobs — Assign).
- Scoped to exactly one Onboarding Job and, transitively, exactly one Tenant; an assignment on Tenant A's Job grants zero access to Tenant B, even for the same Website Designer holding a separate, simultaneous assignment there.
- **Assignment is not Tenant membership** (18_AGGREGATE_DESIGN.md) — it does not grant access to unrelated Bookings, commercial resources, or any resource this document does not explicitly mark 🔒 Assigned in Section 1.
- **Ends the instant the assignment ends.** A reassignment, a completed Onboarding Job, or an explicit revocation each immediately zero out the prior Designer's access — there is no grace period, and no residual access persists into a subsequent assignment on the same or a different Tenant.
- More than one Website Designer collaborating on one Onboarding Job is not approved Phase 1 scope (18_AGGREGATE_DESIGN.md's Future Split Candidates) — this document assumes exactly one active, accountable Designer per Job at a time.

## 4. Ownership Rules

Every resource in Section 1 belongs to exactly one of the five ownership classes 19_DATABASE_STRATEGY.md already established, and the class determines the shape of every role's access to it:

| Ownership Class | Resources | Access Rule |
|---|---|---|
| **Tenant-owned** | Clinic Registration (pre-approval), Tenant, Clinic, Website, Website Pages, Custom Domains, Clinic Services, Booking, Subscription, Invoices, Payments, Onboarding Jobs, Notifications, Media (Tenant assets) | Accessible only to that Tenant's own Clinic Owner, an actively assigned Website Designer (where marked in Section 1), and Super Admin through a privileged pathway. Never accessible to another Tenant's Clinic Owner or Website Designer under any circumstance. |
| **Platform-owned** | Template, Platform Settings, Media (platform assets), Platform Identity and Platform Authorization runtime | Accessible only through authenticated Platform Identity and explicitly authorized platform pathways. Category scope applies where one applies (Template design governance, Platform Setting category, Commercial Catalogue category). **Clinic Owner has zero standing on platform-owned resources. Website Designer access is only through Platform Identity plus assignment/permission, not tenant ownership.** |
| **Reference or governed shared data** | Not independently exposed as a Phase 1 resource (Add-On, Notification Template, Metric Definition — referenced by, but not directly addressable through, Subscription, Notifications, and Reports respectively) | Consumed by reference only; no role directly authors this data through this API in Phase 1. |
| **Commercial Catalogue (governed reference data with authoring)** | Plan, Billing Option, Plan Offering, Capability Catalogue (28_COMMERCIAL_CATALOGUE_SPECIFICATION.md) | Category-scoped Super Admin only, following exactly the Platform Settings pattern (Section 1.20 below) — never Clinic Owner, Website Designer, or Public Visitor, and never unscoped Super Admin authority. Every mutation requires a mandatory Audit Entry (Audit Requirements below). Consumed by Subscription and its future Entitlement Computation boundary by reference only. |
| **Commercial Offer (checkout snapshot)** | CommercialOffer | Authenticated Platform Identity only. Website Designer may act only through an assigned, authorized platform workflow. Super Admin requires category-scoped platform authority. Public Visitor and Clinic Owner do not prepare or cancel CommercialOffer in Phase 1. |
| **Projection or derived data** | Reports, Clinic Services' available-slots read, Onboarding Jobs' launch-readiness read | Read-only for every role that may see it at all; never a source of truth and never a write target, per 18_AGGREGATE_DESIGN.md's explicit interaction rule. |
| **Audit or accountability data** | Not exposed as a Phase 1 resource (Audit Entry, deliberately excluded per 20_API_DESIGN.md) | No role accesses Audit Entry through this general API; it requires its own privileged-tooling design. |

**The ownership class, not the caller's role alone, determines the ceiling of what is possible.** A Super Admin's category-scoped access to Platform Settings is still bounded by that resource's platform ownership — no amount of Super Admin privilege converts a platform-owned resource into something a Clinic Owner or Website Designer can reach, and no amount of Clinic Owner authority extends past their own Tenant's boundary into another Tenant's identically-shaped resource.

---

## 5. Tenant Boundary Rules

These rules restate and operationalize ADR-002's tenant-isolation model specifically against the role and resource set in this document. They are invariants, not defaults that a convenient exception may quietly weaken.

1. **Tenant context is resolved before any tenant-owned resource in Section 1 is touched**, through the trusted path appropriate to the caller (verified host for Public Visitor, authenticated membership for Clinic Owner, active assignment for Website Designer, explicit privileged context for Super Admin) — never inferred from a client-supplied identifier.
2. **A Clinic Owner's authority never crosses Tenant boundaries**, even where the same individual holds Clinic Owner Authority for more than one Tenant — each relationship in Section 3 is independent, and this document's matrices assume a single Tenant context per request throughout.
3. **A Website Designer's access exists only inside an active assignment's Tenant boundary.** This is restated here as an explicit, standalone rule because it is one of the brief's four named non-negotiable requirements: **Website Designer only has access to assigned Tenants** — never a Tenant the Designer previously worked on, never a Tenant they might be assigned to later, and never any Tenant at all in the absence of a currently active Website Designer Assignment (Section 3).
4. **Clinic Owner never gains standing on a platform-owned resource**, restated here as an explicit, standalone rule because it is a second of the brief's four named non-negotiable requirements: **Clinic Owner cannot access platform resources** — Template authoring, Platform Settings, and any future platform-governance resource are structurally outside every Clinic Owner's reachable surface regardless of their Tenant's size, subscription tier, or tenure, per the Ownership Rules above.
5. **A mismatch between any two tenant-context signals in one request (path, body, token, resolved host) fails closed**, per ADR-002 — it is treated as a security event, never resolved by preferring one signal over another or falling back to a default Tenant.
6. **Tenant context is immutable within one request or job** and is never silently switched mid-operation; a caller needing to act on a second Tenant issues a second, independently authorized request.

## 6. Cross-Tenant Rules

Only Super Admin may ever act across more than one Tenant, and only through the explicit mechanisms below — no other role has a cross-tenant pathway anywhere in Section 1.

- **Portfolio views** (Clinic Registration list, Tenant list, Reports' portfolio scope) are read-only, minimized, and exist specifically for platform oversight — they never expose full tenant-owned resource detail as a side effect of listing.
- **Privileged support actions** (any cell marked 🔒 Privileged in Section 1) require an explicit purpose statement and are recorded as Audit Entries (Section 7) — a Super Admin does not casually browse into a Tenant's data "just in case" without a stated, accountable reason.
- **Category-scoped platform actions** (Template, Platform Settings) are cross-tenant by nature (they affect every Tenant simultaneously once active) but are restricted to the specific governance category a given Super Admin is explicitly authorized for — "Super Admin" alone is not sufficient authorization for every category.
- **No cross-tenant data aggregation bypasses tenant scoping by disabling it** — a Super Admin's privileged Report pathway is a distinct, separately implemented contract from the ordinary Clinic-Owner-facing Report endpoint, never the same endpoint with a scoping check turned off (19_DATABASE_STRATEGY.md's Reporting Philosophy).
- **Cross-tenant export is exceptional, never routine** — every Export cell in Section 1 marked 🔒 Privileged requires the same purpose-limited, audited justification as any other privileged action, and Phase 1 does not approve a general-purpose cross-tenant export capability.
- **No role other than Super Admin ever has a cross-tenant pathway of any kind** — this is a structural property of Sections 1–2, not a policy statement layered on afterward: every Clinic Owner and Website Designer cell in the matrices is either `❌`, `N/A`, or scoped to `Own`/`Assigned`, with zero exceptions.

## 7. Audit Requirements

Every action marked 🔒 Privileged, 🔒 Category, or explicitly called out as privileged in Sections 1–2 produces a mandatory Audit Entry (18_AGGREGATE_DESIGN.md's Audit Entry aggregate), consistent with 20_API_DESIGN.md's per-operation Audit Requirements. This section consolidates that pattern into standalone rules and states the brief's third non-negotiable requirement explicitly.

1. **Super Admin must never bypass audit.** This is stated here as an absolute, standalone rule, not merely an implication of the per-cell markings above: every privileged action a Super Admin performs — every suspension, offboarding, reactivation, Clinic Owner Authority change, Website Designer Assignment, Template or Platform Setting change, Custom Domain activation or detachment, cross-tenant Report access, privileged export, or support/correction action anywhere in Section 1 — produces an Audit Entry as an inseparable part of that action succeeding. There is no maintenance mode, emergency path, or administrative convenience that performs the underlying effect without also recording it.
2. **Audit Entry creation is not optional, retryable-without-consequence, or best-effort.** If the audit-recording step cannot complete, the privileged action itself does not complete either — an action is never "successful but unaudited."
3. **Access to Audit Entry data is itself an audited event** (19_DATABASE_STRATEGY.md's Audit Policy) — reviewing another operator's privileged history is not a silent read.
4. **Ordinary, non-privileged actions are not over-audited.** A Clinic Owner updating their own Clinic profile or a Website Designer completing an assigned Onboarding Task does not generate Audit Entries — audit is reserved for the accountability-critical actions Sections 1–2 explicitly mark, keeping the Audit Entry stream meaningful rather than diluted with routine activity (which is instead covered by ordinary Activity Log projection, per 18_AGGREGATE_DESIGN.md).
5. **The specific mandatory-Audit-Entry actions, consolidated from Section 1, are:** Clinic Registration decisions; every Tenant lifecycle transition and Clinic Owner Authority change; Website Designer Assignment and reassignment; Custom Domain activation and detachment; Website Designer-facing tracking-configuration changes (19_DATABASE_STRATEGY.md); Template and Platform Setting proposals and lifecycle transitions; Onboarding Job assignment, task waivers, completion, cancellation, and reopening; privileged Booking, Subscription, Payment, Media, and Website support/correction actions; and any privileged cross-tenant Report or export access.
6. **Audit Entries are append-only and never rewritten**, per 18_AGGREGATE_DESIGN.md's Audit Entry invariant — a correction to a prior privileged action is a new, linked entry, never an edit to history.

## 8. Privilege Escalation Prevention

- **No role grants itself a broader role.** A Clinic Owner cannot elevate their own account to Super Admin; a Website Designer cannot self-assign to an Onboarding Job (Section 1.16 — Assign is Super-Admin-only with zero exception); a Super Admin cannot grant themselves a Platform Setting category they were not explicitly authorized for.
- **No assignment or authority relationship is inferable from possession.** Holding a valid session, knowing a resource identifier, or having a plausible-looking URL never substitutes for an actual Clinic Owner Authority or Website Designer Assignment record — per ADR-002, "client-supplied tenant identifiers are never sufficient authorization," and the same principle extends to every identifier in this API.
- **MFA is a precondition for privileged capability, not a bypassable convenience.** Website Designer and Super Admin roles require completed MFA enrollment (06_SECURITY_STANDARD.md, 20_API_DESIGN.md's Profile resource) before their privileged actions in Section 1 are reachable; a session that has not completed an outstanding MFA challenge cannot exercise privileged capability regardless of the underlying role.
- **Assignment and authority changes take effect immediately, with no residual access window.** The moment a Website Designer Assignment ends or a Clinic Owner Authority is revoked, every subsequent request under the old grant fails — there is no cached, stale, or "still logged in so still allowed" exception (05_MULTI_TENANCY.md's revocation-promptness rule).
- **A privileged pathway never shares an endpoint or policy check with an ordinary one.** Per 20_API_DESIGN.md's API Security Principles, a Super Admin does not reach a Tenant's data by calling the same endpoint a Clinic Owner uses with an elevated flag — privileged actions have their own, structurally separate pathway, which is also what makes Rule 1 of Audit Requirements enforceable rather than aspirational.
- **Category scoping within Super Admin is enforced the same way tenant scoping is enforced for Clinic Owner** — a Super Admin authorized for Platform Setting category "Commercial Policy" gains no implicit authority over category "Security Mode," exactly as a Clinic Owner for Tenant A gains no implicit authority over Tenant B.
- **A resource never grants authority merely by existing or being reachable.** Object ownership is revalidated after every lookup, not assumed from the fact that a query returned a result — per ADR-002's rule that "possession of an identifier does not grant access."

## 9. Security Principles

- **Deny by default.** Every cell in Section 1 that is not explicitly ✅, 🔒, or a scoped grant is ❌ or N/A — there is no implicit "probably fine" middle ground anywhere in this matrix.
- **Public Visitor cannot infer Tenant existence.** This is the brief's fourth non-negotiable requirement, stated here as an explicit, standalone security principle: every Public-Visitor-facing lookup that could reveal whether a specific Tenant, Clinic Registration, Booking, or Custom Domain exists returns the same outcome (typically `404`, or a generic "unavailable" state for domain resolution) regardless of whether the underlying record exists and belongs to someone else, or does not exist at all — consistent with 20_API_DESIGN.md's explicit `404`-over-`403` pattern and ADR-002's "safe unavailable state without exposing whether another tenant owns the domain."
- **Authorization is server-enforced, never inferred from client state.** No cell in this matrix is satisfied by hiding a button, omitting a menu item, or trusting a client-side role claim — every access decision is evaluated on the server against the actual, current authority relationship.
- **Least privilege is the default shape of every role**, not an exception applied to a broader default — Public Visitor's four-resource surface, Website Designer's assignment-bound access, and Super Admin's privileged/category-scoped pathways are all narrower than "everything this role could plausibly need," by design.
- **Every boundary in this document is independently testable**, per ADR-002's release-blocking tenant-isolation testing requirement — a security review can, and must, attempt every ❌ cell in Section 1 as a negative test case, not only verify the ✅ cells as positive ones.
- **This matrix is the single source of truth for authorization intent.** Where a future implementation detail (a Laravel Policy, a Gate, a middleware) appears to diverge from a cell in Section 1, the divergence is a defect in the implementation, not evidence that this document should be reinterpreted after the fact.

## 10. CTO Recommendations

1. **Approve this document as the binding Phase 1 authorization matrix before any Policy, Gate, or middleware implementation begins.** Every access-control decision in code should trace to a specific cell in Section 1, not be independently re-derived by whoever implements it.
2. **Confirm the four non-negotiable rules from the brief are each individually testable and release-blocking**, exactly as ADR-002 already treats tenant-isolation tests: Website-Designer-assignment-boundedness, Super-Admin-audit-completeness, Clinic-Owner-platform-resource-exclusion, and Public-Visitor-tenant-existence-non-inference should each have dedicated negative test suites before general availability.
3. **Resolve the still-open booking-semantics and commercial-model questions this document inherited from 18_AGGREGATE_DESIGN.md and 20_API_DESIGN.md** (manual vs. automatic Booking confirmation, Public Visitor self-cancellation scope, Invoice's provisional status) before their corresponding permission cells are treated as final rather than provisional.
4. **Commission the Super Admin category-authorization model as its own follow-up decision** — this document assumes Platform Setting and Template categories exist and are individually assignable, but the exact category taxonomy and who is authorized for each is not itself defined here and should not be improvised at implementation time.
5. **Require this document to be updated in the same change as 20_API_DESIGN.md whenever a resource or action is added, removed, or re-scoped** — the two documents are companion artifacts, and letting them drift apart would silently reintroduce exactly the kind of undocumented authorization surface this document exists to prevent.
6. **Do not treat any `❌` cell as a future feature backlog item by default.** Several denials in Section 1 (Website Designer on Booking, Clinic Owner on Template authoring, Public Visitor everywhere except four resources) are deliberate security boundaries reflecting the locked role model in 02_MVP_SCOPE.md, not gaps awaiting a convenient relaxation.
7. **Independently verify this matrix against a threat model before general availability**, per 06_SECURITY_STANDARD.md's requirement for threat modeling on new trust boundaries — this document defines the intended boundary; it does not by itself constitute the security assurance evidence ADR-002 and 06_SECURITY_STANDARD.md require.

# Authentication Foundation

**Implementation Alignment Note — SYIFA-085A.** Platform workforce identity placement is now governed by [ADR-005](./decisions/ADR-005-Platform-Identity.md). Super Admin and Website Designer are Platform Identities owned by Platform Administration and authenticated through the platform browser-session model. Any earlier open question in this document about platform-workforce identity placement is superseded by ADR-005.

## Table of Contents

- [Document Authority](#document-authority)
- [1. Authentication Objectives](#1-authentication-objectives)
- [2. Authentication Principles](#2-authentication-principles)
- [3. Authentication Boundary](#3-authentication-boundary)
- [4. Identity Model](#4-identity-model)
- [5. User Lifecycle](#5-user-lifecycle)
- [6. Login Flow](#6-login-flow)
- [7. Logout Flow](#7-logout-flow)
- [8. Password Reset Flow](#8-password-reset-flow)
- [9. Email Verification Flow](#9-email-verification-flow)
- [10. Session Strategy](#10-session-strategy)
- [11. Remember Me Strategy](#11-remember-me-strategy)
- [12. Authentication State](#12-authentication-state)
- [13. Authentication Events](#13-authentication-events)
- [14. Failed Login Handling](#14-failed-login-handling)
- [15. Account Lock Rules](#15-account-lock-rules)
- [16. Password Policy](#16-password-policy)
- [17. Role Relationship](#17-role-relationship)
- [18. Permission Boundary](#18-permission-boundary)
- [19. Security Requirements](#19-security-requirements)
- [20. Audit Requirements](#20-audit-requirements)
- [21. Future MFA Strategy](#21-future-mfa-strategy)
- [22. Testing Strategy](#22-testing-strategy)
- [23. Out of Scope](#23-out-of-scope)
- [24. Acceptance Criteria](#24-acceptance-criteria)
- [25. Implementation Sequencing for Codex](#25-implementation-sequencing-for-codex)
- [26. CTO Recommendations](#26-cto-recommendations)

## Document Authority

**Status: Draft — Under CTO Review.** This document specifies the Authentication Foundation for Syifa.my Phase 1: how a Clinic Owner, Website Designer, or Super Admin proves identity, and how that identity is established, maintained, and revoked. It contains no Laravel code, no middleware, no migration, no controller, and no other implementation artifact — those require separately governed engineering work once this specification is approved, consistent with how [20_API_DESIGN.md](./20_API_DESIGN.md) and [21_PERMISSION_MATRIX.md](./21_PERMISSION_MATRIX.md) were themselves specified before implementation.

This document does not redesign anything the [Architecture Freeze](./26_ARCHITECTURE_FREEZE_V1.md) already settled. It is subordinate to, and only elaborates, decisions already made in [ADR-001](./decisions/ADR-001-Architecture-Principles.md), [ADR-002](./decisions/ADR-002-Multi-Tenant-Strategy.md) (tenant isolation invariants), [ADR-003](./decisions/ADR-003-Technology-Stack.md) (Decision 6: framework-native session authentication), [ADR-005](./decisions/ADR-005-Platform-Identity.md) (platform workforce identity), [06_SECURITY_STANDARD.md](./06_SECURITY_STANDARD.md) (identity and access control objectives), [14_DOMAIN_MODEL.md](./14_DOMAIN_MODEL.md) (the four Domain Participants), [19_DATABASE_STRATEGY.md](./19_DATABASE_STRATEGY.md) (Security and Authentication Data classification), [20_API_DESIGN.md](./20_API_DESIGN.md) (the Session and Profile resources), [21_PERMISSION_MATRIX.md](./21_PERMISSION_MATRIX.md) (the four-role authorization model), and [25_CODING_STANDARD.md](./25_CODING_STANDARD.md). Where this document appears to say something new, it is filling an implementation-relevant gap those documents deliberately left at a coarser grain (for example, 20_API_DESIGN.md's Session resource states authentication is "grounded in Tenant, Onboarding Job, and Platform Administration" without specifying exactly where a credential record lives) — never overriding what they already decided. Where a genuine tension exists against the brief this document was commissioned under, it is flagged explicitly rather than silently resolved (see [21. Future MFA Strategy](#21-future-mfa-strategy) and [26. CTO Recommendations](#26-cto-recommendations)).

## 1. Authentication Objectives

- Reliably verify that a caller claiming to be a Clinic Owner, Website Designer, or Super Admin is who they claim to be, before any tenant-owned or platform-owned action is permitted.
- Gate privileged capability (Website Designer, Super Admin) behind mandatory multi-factor authentication, per ADR-003 Decision 6 and 06_SECURITY_STANDARD.md's operator identity requirements.
- Provide each of the three authenticated roles a secure, self-service credential lifecycle: registration-triggered account establishment, email verification, login, logout, and password reset — without requiring platform-operator intervention for routine cases.
- Preserve tenant isolation at the identity layer: authenticating successfully must never, by itself, grant access to any Tenant the authenticated identity is not explicitly and currently authorized for (ADR-002).
- Produce complete, non-repudiable security telemetry for every authentication-relevant event, satisfying 06_SECURITY_STANDARD.md's Logging, Monitoring, and Audit objectives and 19_DATABASE_STRATEGY.md's Audit and Accountability Data classification.
- Resist credential attacks (brute force, credential stuffing, account enumeration) as a release-blocking property, not a follow-up hardening pass.
- Remain implementable entirely with the framework-native primitives ADR-003 Decision 6 already selected — no custom cryptography, no custom token format, no external Identity-as-a-Service dependency in Phase 1.

## 2. Authentication Principles

- **Framework-native only.** Authentication uses Laravel's maintained, security-reviewed authentication primitives (ADR-003 Decision 6; 08_DEVELOPMENT_RULES.md's prohibition on custom cryptography or token formats without specialist review). No bespoke hashing, token signing, or session-storage mechanism is introduced.
- **Deny by default.** An unauthenticated caller has zero standing beyond the narrow, explicitly public actions 02_MVP_SCOPE.md and 20_API_DESIGN.md already enumerate for Public Visitor (Clinic Registration submission, Booking submission, live availability lookup) — none of which require a session.
- **Authentication is necessary, never sufficient.** A valid session establishes *who* the caller is. It never by itself establishes *what* the caller may do — that is exclusively 21_PERMISSION_MATRIX.md's responsibility, re-checked on every protected action (see [18. Permission Boundary](#18-permission-boundary)).
- **Fail closed.** Any ambiguity — an unresolvable session, a partially completed MFA challenge, a mismatched tenant signal — resolves to unauthenticated or unauthorized, never to a permissive default.
- **Enumeration resistance.** No authentication-adjacent response (login, password reset, email verification, registration) may allow an external caller to distinguish "credential exists but is wrong" from "credential does not exist," per 06_SECURITY_STANDARD.md.
- **Defense in depth.** Rate limiting, MFA, session expiry, audit, and server-side re-authorization are independent, overlapping controls — no single control is treated as sufficient on its own.
- **Least privilege at the session layer.** A session carries the minimum claims needed to resolve role and immediate context (see [10. Session Strategy](#10-session-strategy)); it never caches a computed permission set.
- **No Phase 1 patient account.** Public Visitor remains permanently unauthenticated in Phase 1 (02_MVP_SCOPE.md); this document introduces no login, password, or session concept for Public Visitor.

## 3. Authentication Boundary

**In scope:** identity verification, credential lifecycle (creation, verification, reset, revocation), session issuance and termination, multi-factor authentication for privileged roles, failed-login and lockout handling, and the audit trail those actions produce — for exactly the three authenticated Domain Participants (14_DOMAIN_MODEL.md): Clinic Owner, Website Designer, Super Admin.

**Out of scope**, per the task brief and by construction:

- Booking, Website Builder, and Subscription business logic — this document only notes where an authentication event (e.g., a revoked Clinic Owner session) has a downstream effect those documents own, never how that downstream effect itself works.
- Tenant Resolution as a general mechanism — ADR-002 and 03_SYSTEM_ARCHITECTURE.md's Section 9 own how tenant context is resolved and propagated for a request. This document goes only as far as: which authenticated relationship a successful login establishes, and what minimal tenant signal a session may carry (see [10. Session Strategy](#10-session-strategy)). It does not re-specify verified-host resolution, background-job tenant scoping, or Super Admin's explicit privileged-context mechanism beyond how a Super Admin authenticates.
- Authorization decisions themselves — owned exhaustively by 21_PERMISSION_MATRIX.md.

## 4. Identity Model

Authentication identity is the technical, credential-bearing representation of a Domain Participant (14_DOMAIN_MODEL.md). It introduces no additional Aggregate Root. Instead, each authenticated role's credential is placed against the existing model as follows:

**Clinic Owner — tenant-scoped identity.** A Clinic Owner's credential (password hash, MFA enrollment state, verification state) is an attribute cluster of the **Clinic Owner Authority** relationship, the internal entity already composed within the **Tenant** Aggregate Root (22_ERD.md's internal-entity list; 14_DOMAIN_MODEL.md: "the Tenant owns the authority relationship"). One person holding Clinic Owner Authority for more than one Tenant holds a genuinely separate credential per relationship, consistent with 14_DOMAIN_MODEL.md's rule that "authority in one Tenant never carries to another" and 21_PERMISSION_MATRIX.md's Roles Overview ("each is independent and non-transferable between Tenants"). This is a deliberate consequence of tenant isolation, not an oversight: a Clinic Owner Authority record cannot outlive or be shared across the Tenant that owns it.

**Website Designer and Super Admin — platform-workforce identity.** Both are, per 14_DOMAIN_MODEL.md, "Platform participant[s]" governed by Syifa.my operations leadership and executive/security governance respectively — never owned by, or composed within, any single tenant-facing aggregate. Critically, a Website Designer "may have many assignments over time" (14_DOMAIN_MODEL.md), each belonging to a different Onboarding Job; the Designer's credential must therefore persist independently of any one assignment or Onboarding Job, or it would be destroyed or duplicated every time an assignment ends or a new one begins. The same reasoning applies to Super Admin, whose authority is explicitly "never an implicit member of every Tenant" and whose access is "separately purpose-bound for each privileged action" rather than tied to one aggregate.

Both are therefore modeled as a **platform-workforce identity record** — an internal operational record governed under Platform Administration's existing cross-module governance scope (14_DOMAIN_MODEL.md's "cross-module platform governance" ownership for both roles), analogous in kind to how Platform Setting already holds Platform-owned, non-tenant-owned configuration. This record holds the credential and MFA enrollment; it is referenced **by identity**, never composed, from:
- **Website Designer Assignment** (internal entity of Onboarding Job) — one workforce identity may be referenced by many assignments over time, each granting time-bounded tenant access, never identity itself.
- **Super Admin's explicit privileged-context mechanism** (ADR-002) — the workforce identity is the actor recorded on every privileged, purpose-limited, audited action; it grants no standing tenant access by itself.

This platform-workforce identity record is not an Aggregate Root either: it has no tenant-owned transactional boundary of its own, and its lifecycle is workforce/HR-governance driven (joiner/mover/leaver, 06_SECURITY_STANDARD.md), not a business consistency boundary in the sense 18_AGGREGATE_DESIGN.md's method tests for. Its placement is now settled by ADR-005: Platform Administration owns Platform Identity.

**Public Visitor** has no identity record of any kind in Phase 1 (02_MVP_SCOPE.md; 14_DOMAIN_MODEL.md: "No continuing account lifecycle exists in Phase 1").

## 5. User Lifecycle

**Clinic Owner:** invited or registered (via an approved Clinic Registration, ADR-002's provisioning workflow) → email-unverified → email-verified → active → restricted (per approved policy exception) → transferred (a controlled ownership-transfer process, 20_API_DESIGN.md's `POST /tenants/{id}/owner-authorities`) → revoked. A revoked Clinic Owner Authority immediately invalidates all active sessions for that authority (14_DOMAIN_MODEL.md: "revocation invalidates active sessions for that authority promptly").

**Website Designer:** workforce-onboarded by an authorized Super Admin → eligible (identity exists, no active assignment, no tenant access) → assigned (active Website Designer Assignment grants scoped tenant access) → assignment completed or withdrawn (identity persists; tenant access ends) → may receive a new assignment (identity re-used, not re-created) → revoked (identity itself deactivated; all sessions and all assignments end). Ending an assignment ends only that assignment's tenant access, per 14_DOMAIN_MODEL.md; it does not by itself revoke the underlying identity.

**Super Admin:** workforce-onboarded by executive/security governance → authorized → active → restricted → suspended → revoked, per 06_SECURITY_STANDARD.md's joiner/mover/leaver requirements ("timely, auditable, and include token and session revocation") and 14_DOMAIN_MODEL.md's requirement that Super Admin authority be "explicit, purpose-limited, observable, revocable, and audited."

All three lifecycles share: identity creation never itself grants access (email verification, and for privileged roles MFA enrollment, must complete first); every state transition that removes or restricts standing must revoke live sessions promptly, never wait for natural session expiry.

## 6. Login Flow

Implements `POST /sessions` (20_API_DESIGN.md).

1. Caller submits an identifier (email) and password. No signal in the request or response may reveal whether the identifier exists before credential verification completes (enumeration resistance, 06_SECURITY_STANDARD.md).
2. The submitted password is verified against the stored hash using the approved adaptive one-way algorithm (19_DATABASE_STRATEGY.md's Security and Authentication Data classification). A mismatch returns the same generic `401` used for a non-existent identifier.
3. [Failed Login Handling](#14-failed-login-handling) and [Account Lock Rules](#15-account-lock-rules) are evaluated before and after verification, regardless of outcome.
4. If the resolved identity is Clinic Owner and the credential is valid, and the account is email-verified and not locked or revoked, a fully authenticated session is issued (`201`).
5. If the resolved identity is Website Designer or Super Admin, a valid password alone yields a *credential-verified, MFA-pending* intermediate state (`202`), never a fully authenticated session — per ADR-003 Decision 6's mandatory MFA for privileged roles. The caller must then submit a valid MFA challenge response before a fully authenticated session is issued.
6. Every outcome (success, credential failure, lockout, MFA-pending, MFA failure) is recorded as security telemetry; a Super Admin's successful login is additionally a mandatory Audit Entry (20_API_DESIGN.md).
7. Login is rate-limited per identifier and per originating network signal, independently of each other, per 06_SECURITY_STANDARD.md.

## 7. Logout Flow

Implements `DELETE /sessions/current` (20_API_DESIGN.md).

1. The caller must hold a currently valid session; there is no unauthenticated logout.
2. The current session is revoked immediately and unconditionally — server-side invalidation, not merely client-side credential discardal, so a stolen session token cannot be replayed after logout.
3. Logout is naturally idempotent: calling it again with an already-invalidated session returns `401`, not an error about "already logged out."
4. Logout ends the current session only; it does not implicitly revoke other concurrent sessions of the same identity (see [10. Session Strategy](#10-session-strategy) for concurrent-session posture) unless the caller uses an explicit "revoke all sessions" action, which is a Profile-level, not a Session-level, capability and requires the same re-authentication rigor as a sensitive Profile change.

## 8. Password Reset Flow

1. Caller submits an identifier (email) to a reset-request endpoint. The response is identical regardless of whether the identifier exists (enumeration resistance).
2. If the identifier resolves to a real, active identity, a time-bounded, single-use reset token is generated and delivered exclusively through the transactional-only ESP (ADR-003 Decision 14) — never displayed in the response, never logged in raw form (19_DATABASE_STRATEGY.md: credentials "never logged in raw form").
3. The reset-request endpoint is rate-limited per identifier and per network signal (06_SECURITY_STANDARD.md: "reset, recovery... are rate-limited and resistant to account enumeration").
4. Caller submits the token together with a new password meeting [Password Policy](#16-password-policy). The token is validated for expiry, single use, and correspondence to the requesting identity.
5. On success: the password hash is replaced, every existing session for that identity is invalidated (a password reset is a privilege-relevant event; a session issued under a potentially compromised credential must not survive it), and a mandatory Audit Entry is recorded (20_API_DESIGN.md: "a change affects an authentication credential" is always audited).
6. A used or expired token is rejected with a generic error that does not distinguish "expired" from "already used" from "never existed," to avoid leaking token-guessing feedback.
7. Password reset does not bypass email verification or MFA enrollment state; an unverified or MFA-pending identity that resets its password remains unverified or MFA-pending afterward.

## 9. Email Verification Flow

1. Triggered automatically on identity creation for every role (Clinic Owner on Registration approval; Website Designer and Super Admin on workforce onboarding).
2. A time-bounded, single-use verification token is delivered through the transactional-only ESP (ADR-003 Decision 14).
3. An unverified identity may authenticate (to permit a resend-verification and basic profile-completion flow) but is blocked from every tenant-owned or privileged action until verified — verification state is a precondition checked independently of, and in addition to, ordinary role-based authorization.
4. Resend-verification is rate-limited per identifier, with the same enumeration-resistant response shape as password reset.
5. A used or expired verification token is rejected with a generic error, consistent with the reset flow's anti-enumeration posture.
6. Verification completion is recorded as security telemetry; it is not, on its own, a privileged Audit Entry (it is a lifecycle event, not a credential-security event), unless it is later re-triggered by a sensitive email change on Profile, which 20_API_DESIGN.md's `PATCH /profile` already marks as requiring re-verification and a mandatory Audit Entry.

## 10. Session Strategy

- **Mechanism:** framework-native, server-side session state (ADR-003 Decision 6) — not a self-contained bearer token (JWT) validated only client-side — so that revocation in [Logout Flow](#7-logout-flow), lockout, and lifecycle-driven revocation ([5. User Lifecycle](#5-user-lifecycle)) take effect immediately rather than only at natural token expiry.
- **Transport:** a secure, `HttpOnly`, `Secure`, appropriately-scoped cookie or the framework's equivalent mechanism, per 06_SECURITY_STANDARD.md's "protected cookies or equivalent mechanisms."
- **Contents:** the resolved identity reference, role, MFA-completion state, and — for Clinic Owner and Website Designer — the minimal tenant/assignment reference needed to seed (never substitute for) ADR-002's own trusted tenant-context resolution on each subsequent request. A session never carries a computed permission set (see [18. Permission Boundary](#18-permission-boundary)).
- **Rotation:** the session identifier is rotated whenever privilege changes within a session's lifetime (e.g., completing a pending MFA challenge, a role or assignment change) per 06_SECURITY_STANDARD.md's "rotation after privilege changes."
- **Expiry:** both an inactivity timeout and an absolute maximum lifetime apply to every session, per 06_SECURITY_STANDARD.md; a Super Admin session is held to a stricter timeout than Clinic Owner or Website Designer sessions, consistent with 20_API_DESIGN.md's "stricter MFA and monitoring standard" for Super Admin. Exact durations are a configuration decision, not fixed by this document (see [26. CTO Recommendations](#26-cto-recommendations)).
- **Concurrency:** multiple concurrent sessions per identity are permitted by default (a Clinic Owner on two devices is an expected, legitimate case); an explicit "revoke all other sessions" capability must exist on Profile for the caller's own use after a suspected compromise.
- **Cross-site protection:** every state-changing session-authenticated request is protected against cross-site request forgery, per 06_SECURITY_STANDARD.md.

## 11. Remember Me Strategy

- An opt-in, longer-lived authentication signal bound to a specific device/browser, distinct from the ordinary session's inactivity timeout — it extends *how long a caller can return without re-entering a password*, not *how long a single session stays valid without activity*.
- Remains fully revocable: logout-all, password reset, and lifecycle revocation ([5. User Lifecycle](#5-user-lifecycle)) invalidate a remember-me signal exactly as they invalidate an ordinary session.
- Never bypasses a still-pending MFA challenge — a remembered device may skip re-entering the password, but a privileged role (Website Designer, Super Admin) still requires MFA verification within a bounded trusted-device window before reaching a fully authenticated state; that window is stricter than the remember-me duration itself.
- Not offered on shared or platform-operational contexts — remember-me is a Clinic-Owner/Website-Designer/Super-Admin end-user convenience on their own device, never a mechanism used by a service identity or background process.
- Still subject to the same absolute-lifetime ceiling philosophy as ordinary sessions: a remembered device eventually requires full re-authentication rather than persisting indefinitely.

## 12. Authentication State

A caller occupies exactly one of the following states at any time:

| State | Meaning | Can perform privileged/tenant-owned actions? |
|---|---|---|
| Unauthenticated | No valid session. | No — only the narrow public actions in [3. Authentication Boundary](#3-authentication-boundary). |
| Credential-Verified, Email-Unverified | Password correct; email verification outstanding. | No — blocked pending [9. Email Verification Flow](#9-email-verification-flow). |
| Credential-Verified, MFA-Pending | Password correct (privileged role); MFA challenge outstanding. | No — this is not a session yet, only an intermediate `202` state per [6. Login Flow](#6-login-flow). |
| Fully Authenticated | Password (and MFA, if required) verified; email verified. | Yes, subject to 21_PERMISSION_MATRIX.md's role and scope rules. |
| Locked | Failed-login threshold exceeded (see [15. Account Lock Rules](#15-account-lock-rules)). | No — rejected regardless of subsequent credential correctness until unlock conditions are met. |
| Revoked | Identity or authority relationship explicitly ended. | No — permanently, until a new authorized lifecycle event re-establishes standing. |
| Expired | Session exceeded inactivity or absolute lifetime. | No — caller must re-authenticate from Unauthenticated. |

Transitions between these states are the union of [5. User Lifecycle](#5-user-lifecycle), [6. Login Flow](#6-login-flow), [10. Session Strategy](#10-session-strategy)'s expiry rules, and [15. Account Lock Rules](#15-account-lock-rules) — no other pathway may move a caller between states.

## 13. Authentication Events

The following are the authentication-domain events this foundation produces. Each is security telemetry at minimum; those marked **Audit** are additionally a mandatory Audit Entry per 19_DATABASE_STRATEGY.md's Audit and Accountability Data classification and 20_API_DESIGN.md's stated audit rules.

| Event | Trigger | Audit |
|---|---|---|
| `LoginSucceeded` | Fully authenticated session issued | Audit for Super Admin only; telemetry for all roles |
| `LoginFailed` | Invalid credential, any role | Telemetry |
| `MfaChallengeIssued` | Privileged-role credential verified, challenge sent | Telemetry |
| `MfaChallengeSucceeded` | Challenge response verified | Audit (completes a Super Admin/Website Designer login) |
| `MfaChallengeFailed` | Challenge response invalid | Telemetry |
| `LogoutCompleted` | Session explicitly ended | Telemetry |
| `SessionExpired` | Inactivity or absolute timeout reached | Telemetry |
| `SessionRevoked` | Lifecycle event, admin action, or password reset invalidated a session | Audit |
| `PasswordResetRequested` | Reset flow step 1 | Telemetry |
| `PasswordResetCompleted` | Reset flow step 5 | Audit |
| `EmailVerificationRequested` | Verification sent or resent | Telemetry |
| `EmailVerified` | Verification token accepted | Telemetry |
| `MfaEnrolled` | MFA enrollment completed (20_API_DESIGN.md `POST /profile/mfa-enrollment`) | Audit |
| `AccountLocked` | Failed-login threshold reached | Audit |
| `AccountUnlocked` | Auto-expiry or administrator action | Audit |
| `CredentialChanged` | Password changed via reset or Profile | Audit |

## 14. Failed Login Handling

- Every failed attempt (wrong password, non-existent identifier, locked account, unverified email blocking a privileged action) returns the same generic `401` message and the same response timing profile, so no external signal distinguishes the cause (06_SECURITY_STANDARD.md's enumeration resistance).
- Failed attempts are rate-limited independently by identifier and by originating network signal, so an attacker cannot bypass a per-identifier limit by rotating source addresses, nor bypass a per-network limit by rotating target identifiers.
- Every failed attempt is recorded as security telemetry with enough correlation detail for investigation, and none of it includes the submitted password or any other credential value in raw form (19_DATABASE_STRATEGY.md).
- Repeated failure against the same identifier progresses toward [Account Lock Rules](#15-account-lock-rules); repeated failure across many identifiers from the same network signal is a distinct abuse pattern handled by network-level rate limiting, not account locking.

## 15. Account Lock Rules

- A consecutive-failure threshold on a single identifier triggers a temporary lock, independent of the network-level rate limiting in [14. Failed Login Handling](#14-failed-login-handling).
- The lock response is identical in shape to an ordinary credential failure — a locked account never confirms its own existence or lock state to an unauthenticated caller (enumeration resistance).
- Lock duration is time-bounded and auto-expiring for Clinic Owner and Website Designer; a Super Admin lock additionally requires explicit administrator review before unlock, given the stricter monitoring standard 20_API_DESIGN.md already applies to Super Admin sessions.
- A successful password reset ([8. Password Reset Flow](#8-password-reset-flow)) clears an active lock, since it proves possession of the verified email channel independently of the locked password.
- Both `AccountLocked` and `AccountUnlocked` are mandatory Audit Entries ([13. Authentication Events](#13-authentication-events)).
- Exact failure threshold and lock duration are configuration decisions, not fixed by this document (see [26. CTO Recommendations](#26-cto-recommendations)).

## 16. Password Policy

Consistent with 06_SECURITY_STANDARD.md's instruction that passwords "follow current recognized guidance" rather than an arbitrary composition rule:

- Minimum length is enforced; no maximum short of a generous technical ceiling, and no mandatory character-class composition rule (no forced mix of symbols/numbers/case), which current guidance treats as encouraging predictable patterns rather than improving strength.
- Submitted passwords are checked against a maintained breached/compromised-password list at creation and reset time; a match is rejected with a generic "choose a different password" message, not a description of the match.
- No mandatory periodic password rotation — rotation is required only in response to a specific compromise indicator (a lifecycle-driven `CredentialChanged`, never a calendar-driven prompt), consistent with current guidance's rejection of rotation-for-its-own-sake.
- Passwords are stored exclusively via the approved adaptive one-way algorithm named in 19_DATABASE_STRATEGY.md's Security and Authentication Data classification — never encrypted reversibly, never logged, never included in any export.
- No security-question-style "recovery question" mechanism is used; account recovery is exclusively the token-based [Password Reset Flow](#8-password-reset-flow).
- The specific minimum length and the specific breached-password-list provider are configuration/vendor decisions, not fixed by this document (see [26. CTO Recommendations](#26-cto-recommendations)).

## 17. Role Relationship

- A successful authentication resolves to exactly one of the three authenticated roles in 21_PERMISSION_MATRIX.md's Roles Overview — Clinic Owner, Website Designer, or Super Admin — never more than one simultaneously, and never a role not already defined there.
- The role is a direct consequence of which identity record authenticated ([4. Identity Model](#4-identity-model)), not a separate selection the caller makes at login.
- For Clinic Owner, authentication additionally resolves the specific Tenant the Clinic Owner Authority belongs to; for Website Designer, it resolves the currently active Website Designer Assignment (if any) and therefore the currently accessible Tenant; for Super Admin, authentication resolves platform-wide standing with no implicit Tenant, consistent with 21_PERMISSION_MATRIX.md's "never an implicit member of every Tenant."
- A person holding Clinic Owner Authority for multiple Tenants authenticates once per relationship (per [4. Identity Model](#4-identity-model)); this document does not introduce a single login that lets a caller switch between multiple Clinic Owner identities within one session.
- This document does not create, and must never be read as creating, a fifth role or any resource-specific sub-role (21_PERMISSION_MATRIX.md: "No fifth role... is introduced anywhere in this document" — a constraint this document inherits and does not weaken).

## 18. Permission Boundary

- Authentication answers **who**; 21_PERMISSION_MATRIX.md answers **what**. This document's authority ends the moment a role and context are resolved — every subsequent action-level decision (Own/Assigned/Category/Privileged scope, the seventeen-action vocabulary, every Denied/Not Applicable/Conditional cell) belongs exclusively to 21_PERMISSION_MATRIX.md.
- A session must never be treated as a cached authorization decision. Every protected action is re-authorized server-side at the time it is performed (25_CODING_STANDARD.md Section 24: "UI visibility is never treated as a control"), even within the same authenticated session.
- Authentication state ([12. Authentication State](#12-authentication-state)) gates whether an action is reachable at all (fully authenticated vs. not); 21_PERMISSION_MATRIX.md gates whether a specific fully authenticated caller may perform a specific action on a specific resource. Both checks are mandatory and neither substitutes for the other.
- Website Designer's additional assignment-scoped narrowing and Super Admin's privileged-pathway structural separation (25_CODING_STANDARD.md Section 25) are authorization concerns this document does not restate beyond noting, in [17. Role Relationship](#17-role-relationship), that authentication is what supplies the assignment/privileged-context signal those checks consume.

## 19. Security Requirements

Restated here as authentication-specific obligations; 06_SECURITY_STANDARD.md remains the controlling document for all of them:

- All authentication traffic uses encrypted transport; no credential, token, or session identifier is ever transmitted or logged unencrypted.
- Session cookies (or equivalent) are `HttpOnly`, `Secure`, and scoped as narrowly as the framework allows.
- Every state-changing authenticated request carries cross-site request forgery protection.
- MFA is mandatory for Website Designer and Super Admin (ADR-003 Decision 6; 06_SECURITY_STANDARD.md's operator MFA requirement) — this is a Phase 1 requirement, not deferred (see [21. Future MFA Strategy](#21-future-mfa-strategy) for the important distinction between this and what is genuinely still future work).
- Login, reset, verification, and MFA-enrollment endpoints are rate-limited and enumeration-resistant, per [14. Failed Login Handling](#14-failed-login-handling), [8. Password Reset Flow](#8-password-reset-flow), and [9. Email Verification Flow](#9-email-verification-flow).
- No custom cryptographic primitive, token format, or authentication protocol is implemented from scratch; only the framework's maintained primitives are used (08_DEVELOPMENT_RULES.md).
- Credentials and MFA secrets are never logged, never included in an error response, and never exported in reversible form (19_DATABASE_STRATEGY.md's Security and Authentication Data classification).
- Joiner/mover/leaver changes for Website Designer and Super Admin workforce identities are timely, auditable, and include prompt token and session revocation (06_SECURITY_STANDARD.md).

## 20. Audit Requirements

- Every event marked **Audit** in [13. Authentication Events](#13-authentication-events) produces a mandatory Audit Entry, inseparable from the action that triggered it — the code path that performs the action and the code path that records the entry are not independently-succeedable steps (25_CODING_STANDARD.md Section 24).
- A Super Admin's own authentication events (login, session revocation, lock/unlock) are held to the same "mandatory Audit Entry" standard 20_API_DESIGN.md already applies to Super Admin session establishment — no authentication event for this role is ever "ordinary telemetry only."
- Audit Entry content for authentication events records the identity, role, event type, outcome, and correlation detail needed for investigation — never the credential value itself, per 19_DATABASE_STRATEGY.md's rule that authentication data is "never logged in raw form; only event metadata."
- Access to authentication-related Audit Entry data is itself logged as a new Audit Entry, per 19_DATABASE_STRATEGY.md's Audit Policy (ADR-002 Security Invariant 19) — this document introduces no exception for authentication data.
- Non-audit authentication telemetry (ordinary login success/failure for Clinic Owner and Website Designer, session expiry) still satisfies 06_SECURITY_STANDARD.md's Logging, Monitoring, and Audit objectives as security telemetry, distinct from and less strict than a mandatory Audit Entry.

## 21. Future MFA Strategy

**A necessary clarification before this section can be answered honestly:** the task brief that commissioned this document has now twice instructed that MFA is deferred from or not implemented in Phase 1 — first as "(MFA not implemented in Phase 1)," and again in this revision as "Future MFA strategy, explicitly deferred from Phase 1." Both instructions conflict with three already-Accepted, frozen sources: ADR-003 Decision 6 ("mandatory TOTP-based multi-factor authentication for Website Designer and Super Admin"), 06_SECURITY_STANDARD.md ("Multi-factor authentication is required for operator, infrastructure, source-control, and production-capable access"), and 21_PERMISSION_MATRIX.md ("MFA is a precondition for privileged capability, not a bypassable convenience... Website Designer and Super Admin roles require completed MFA enrollment... before their privileged actions... are reachable"). Every task in this series has also carried an explicit, equally weighted constraint not to redesign the architecture and not to change any frozen architecture decision. Because those two constraints cannot both be satisfied by silently adopting the brief's framing, and because the Architecture Freeze's own rule is that a frozen ADR controls until superseded by a new one, this document again follows the frozen decision rather than the brief's section title: **MFA for Website Designer and Super Admin is a Phase 1 requirement, specified above in [6. Login Flow](#6-login-flow), [9. Email Verification Flow](#9-email-verification-flow), [13. Authentication Events](#13-authentication-events), and [19. Security Requirements](#19-security-requirements) — not future work.** The recurrence of this instruction across two consecutive task briefs suggests it may be a deliberate intent to change ADR-003 Decision 6, rather than an oversight; if so, the correct path is a new ADR explicitly superseding Decision 6, not a repeated instruction to a specification document that has no authority to override a frozen ADR. This is flagged for explicit CTO resolution in [26. CTO Recommendations](#26-cto-recommendations) rather than silently decided either way here.

What is genuinely future, and not yet decided by any frozen document:

- **MFA for Clinic Owner.** 06_SECURITY_STANDARD.md states only that "stronger authentication is required for clinic owners and high-impact actions according to risk" and that "general MFA rollout must be planned and measured" — this is explicitly not a Phase 1 mandate the way it is for Website Designer and Super Admin. A future decision would define the risk trigger, rollout mechanism, and whether it becomes mandatory or remains opt-in/risk-adaptive.
- **MFA methods beyond TOTP.** ADR-003 Decision 6 names TOTP specifically; WebAuthn/FIDO2 hardware-key support, push-based approval, or SMS-based fallback (generally discouraged by current guidance) are all undecided future options, not selected or rejected here.
- **Adaptive/step-up authentication.** Triggering an additional MFA challenge based on risk signals (new device, unusual network, sensitive action) beyond the fixed points already specified (login for privileged roles, sensitive Profile changes) is a future enhancement, not a Phase 1 requirement.
- **MFA recovery-code exhaustion policy.** How a privileged-role identity regains access if both its primary MFA method and any backup recovery codes are lost is not specified by this document and requires its own decision, likely modeled on [8. Password Reset Flow](#8-password-reset-flow)'s verified-channel pattern but with the stricter administrator-review posture [15. Account Lock Rules](#15-account-lock-rules) already applies to a locked Super Admin account.
- **External IDaaS or enterprise SSO.** ADR-003's own Migration Strategy already names Keycloak as the preferred first migration target if a future enterprise clinic-chain customer requires SSO; this document does not revisit that evaluation.

## 22. Testing Strategy

Applying 25_CODING_STANDARD.md Section 23's test-level model to this specification's flows:

- **Unit tests** (`tests/Unit/Modules/`): password-hash verification logic, MFA challenge validation logic, token expiry/single-use enforcement (reset and verification tokens), lock-threshold evaluation — tested as isolated Domain-level rules, no database or HTTP.
- **Feature tests** (`tests/Feature/Modules/`): each flow in this document end-to-end — [Login Flow](#6-login-flow) (including the MFA-pending intermediate state), [Logout Flow](#7-logout-flow), [Password Reset Flow](#8-password-reset-flow), [Email Verification Flow](#9-email-verification-flow) — covering every state transition in [12. Authentication State](#12-authentication-state).
- **Negative tests are mandatory, not optional coverage**, per 25_CODING_STANDARD.md Section 23's rule that every 🔒 Privileged/Category permission cell requires a corresponding denial test:
  - Every privileged action attempted with a valid-but-unverified, valid-but-MFA-pending, valid-but-locked, or valid-but-revoked identity must be proven denied.
  - Every login, reset, and verification response must be proven identical in shape and timing profile for an existing vs. non-existent identifier (enumeration-resistance regression tests).
  - A Clinic Owner session issued for one Tenant must be proven unable to resolve or act against a different Tenant's resources — the tenant-isolation negative-test requirement ADR-002 and 25_CODING_STANDARD.md Section 23 make release-blocking, applied specifically at the authentication/session layer here.
  - Rate-limit and lockout thresholds must be proven to actually trigger at their configured boundary, not merely assumed from configuration.
- **Architecture tests** (`tests/Architecture/`): prove that authentication logic depends only on framework-native primitives (no custom cryptography import), per Section 5/19 of 25_CODING_STANDARD.md and 03_SYSTEM_ARCHITECTURE.md's dependency-direction rules.
- Test data is synthetic; no real credential, email, or personal identifier ever appears in a fixture (09_TESTING_STRATEGY.md; 19_DATABASE_STRATEGY.md's Testing Database Philosophy).

## 23. Out of Scope

- Booking, Website Builder, and Subscription business logic — not discussed anywhere above beyond the explicit non-discussion already noted in [3. Authentication Boundary](#3-authentication-boundary).
- General Tenant Resolution mechanics beyond what a login establishes — owned by ADR-002 and 03_SYSTEM_ARCHITECTURE.md.
- Any Public Visitor account, login, or session — none exists in Phase 1 (02_MVP_SCOPE.md).
- External IDaaS integration or enterprise SSO — explicitly deferred by ADR-003's Migration Strategy.
- Exact numeric configuration values (session timeouts, lock thresholds, password minimum length, MFA trusted-device window, remember-me duration) — named as decisions, not fixed, throughout this document and consolidated in [26. CTO Recommendations](#26-cto-recommendations).
- Laravel code, middleware, migrations, controllers, routes, or any other implementation artifact, per this task's explicit constraint.
- Any new Aggregate Root, bounded context, or module — this document introduces neither; see [4. Identity Model](#4-identity-model)'s explicit "not an Aggregate Root" statements.

## 24. Acceptance Criteria

This specification is satisfied by an implementation only when all of the following hold. Each criterion is stated as a verifiable outcome, not an implementation technique:

1. A Clinic Owner, Website Designer, or Super Admin can complete [Login Flow](#6-login-flow) end to end using only framework-native primitives (ADR-003 Decision 6), and no other pathway reaches a fully authenticated state.
2. A Website Designer or Super Admin cannot reach a fully authenticated state without completing a valid MFA challenge — proven by a negative test, not only documentation ([19. Security Requirements](#19-security-requirements); [22. Testing Strategy](#22-testing-strategy)).
3. [Logout Flow](#7-logout-flow) revokes the session server-side; a captured or replayed session token is rejected immediately afterward.
4. [Password Reset Flow](#8-password-reset-flow) and [Email Verification Flow](#9-email-verification-flow) are both enumeration-resistant end to end — an external caller cannot distinguish an existing identifier from a non-existent one at any step, proven by a regression test asserting identical response shape and timing profile.
5. A successful password reset invalidates every pre-existing session for that identity, with no exception path.
6. [Failed Login Handling](#14-failed-login-handling) and [Account Lock Rules](#15-account-lock-rules) trigger at their configured thresholds, proven by a test that actually exercises the boundary rather than asserting configuration values alone.
7. Every event marked **Audit** in [13. Authentication Events](#13-authentication-events) produces a mandatory Audit Entry in the same transaction as the action it records — proven by a test that the action cannot succeed while the audit write fails.
8. A Clinic Owner session issued for one Tenant is proven, by a dedicated negative test, unable to read or act on any other Tenant's resources — the tenant-isolation gate required by ADR-002 and 25_CODING_STANDARD.md Section 23.
9. No credential, MFA secret, or password value appears in any log, error response, or export, verified by a review of every logging and error-handling call site this foundation introduces.
10. Every numeric configuration value this document leaves open ([26. CTO Recommendations](#26-cto-recommendations), item 3) has a recorded, reviewed value before this criterion can be considered met — an unset placeholder does not satisfy acceptance.
11. All authorization decisions after authentication continue to be re-evaluated server-side against 21_PERMISSION_MATRIX.md on every request; no code path treats session possession alone as sufficient ([18. Permission Boundary](#18-permission-boundary)).

## 25. Implementation Sequencing for Codex

A recommended build order, so that each step is testable against this specification before the next depends on it. This is a sequencing recommendation, not a design decision — Codex may reorder within a step where no dependency is violated, but should not begin a later step's flows before an earlier step's acceptance criteria pass.

1. **Credential and identity groundwork.** Implement the credential-bearing structures described in [4. Identity Model](#4-identity-model) — the Clinic Owner Authority attribute cluster and the platform-workforce identity record — once [26. CTO Recommendations](#26-cto-recommendations) item 2 (placement) is confirmed. No flow in this document is testable before this step exists.
2. **Password hashing and policy enforcement.** [16. Password Policy](#16-password-policy)'s adaptive one-way hashing and breached-password check, isolated and unit-testable independent of any HTTP flow.
3. **Session mechanism.** The framework-native session issuance, storage, rotation, and expiry behavior in [10. Session Strategy](#10-session-strategy), before any flow that issues or consumes a session.
4. **Login flow, non-privileged path first.** [6. Login Flow](#6-login-flow) for Clinic Owner (no MFA gate), including [14. Failed Login Handling](#14-failed-login-handling) and [15. Account Lock Rules](#15-account-lock-rules), fully tested before adding MFA complexity.
5. **MFA enrollment and challenge.** The TOTP enrollment and challenge mechanism, then the privileged-role branch of [6. Login Flow](#6-login-flow) (Website Designer, Super Admin) that depends on it — per the frozen ADR-003 Decision 6 requirement restated in [21. Future MFA Strategy](#21-future-mfa-strategy).
6. **Logout flow.** [7. Logout Flow](#7-logout-flow), including the "revoke all other sessions" capability referenced in [10. Session Strategy](#10-session-strategy).
7. **Email verification flow.** [9. Email Verification Flow](#9-email-verification-flow), including the blocked-until-verified precondition on privileged and tenant-owned actions.
8. **Password reset flow.** [8. Password Reset Flow](#8-password-reset-flow), reusing the transactional-ESP token pattern established in step 7.
9. **Remember-me strategy.** [11. Remember Me Strategy](#11-remember-me-strategy), only after ordinary session and MFA behavior (steps 3–5) are stable, since it modifies their expiry and re-challenge behavior rather than replacing it.
10. **Audit and telemetry wiring.** [13. Authentication Events](#13-authentication-events) and [20. Audit Requirements](#20-audit-requirements) across every flow built in steps 4–9 — implemented alongside each flow, not retrofitted, per [24. Acceptance Criteria](#24-acceptance-criteria) item 7's same-transaction requirement.
11. **Full negative-test pass.** [22. Testing Strategy](#22-testing-strategy)'s mandatory negative tests (enumeration resistance, tenant isolation, permission-boundary denial, lockout thresholds) run against the completed set, as a release gate rather than a final step performed casually.

## 26. CTO Recommendations

1. **Resolve the MFA-timing conflict explicitly — this is now a recurring instruction, not a one-off.** Two consecutive task briefs for this document have instructed that MFA is not implemented in, or is explicitly deferred from, Phase 1. Both conflict with ADR-003 Decision 6, 06_SECURITY_STANDARD.md, and 21_PERMISSION_MATRIX.md, all of which already mandate MFA for Website Designer and Super Admin in Phase 1. This document followed the frozen decision both times rather than the brief (see [21. Future MFA Strategy](#21-future-mfa-strategy)), because neither brief carries the authority to override a frozen ADR, and both briefs simultaneously instructed "do not change any frozen architecture decision." If the intent is genuinely to defer privileged-role MFA, the CTO must issue a new ADR explicitly superseding ADR-003 Decision 6; until then, [Acceptance Criteria](#24-acceptance-criteria) item 2 and [Implementation Sequencing](#25-implementation-sequencing-for-codex) step 5 correctly treat MFA as in-scope for Phase 1, and Codex should build against that, not against the brief's framing.
2. **Confirm platform-workforce identity placement.** [4. Identity Model](#4-identity-model) proposes that Website Designer and Super Admin credentials live in a shared platform-workforce identity record under Platform Administration's governance, referenced by (not composed within) Website Designer Assignment and Super Admin's privileged pathways. This is a reasonable filling of a gap the frozen documents left at a coarser grain, but it was not itself previously decided at this precision and should be explicitly confirmed before implementation.
3. **Set exact numeric thresholds.** Session inactivity/absolute timeouts (with a stricter value for Super Admin), failed-login lockout threshold and duration, password minimum length, remember-me duration, and MFA trusted-device re-challenge window are all named as required but left as configuration decisions throughout this document.
4. **Select a breached-password-list provider and MFA/TOTP library**, consistent with ADR-003 Decision 6's "framework-native, maintained primitives only" constraint — a vendor/library selection, not an architecture decision, but one that should be recorded before implementation begins.
5. **Decide the Clinic Owner MFA rollout trigger.** 06_SECURITY_STANDARD.md leaves this as "planned and measured" rather than mandatory; a future decision should define what risk signal or business trigger would make it mandatory, and whether it is opt-in in the interim.
6. **Define the MFA recovery-code exhaustion path** for Website Designer and Super Admin before general availability — currently unspecified beyond the general pattern noted in [21. Future MFA Strategy](#21-future-mfa-strategy).
7. **Confirm this document's own approval status.** Per [26_ARCHITECTURE_FREEZE_V1.md](./26_ARCHITECTURE_FREEZE_V1.md)'s pattern, this document should remain Draft — Under CTO Review until the above items are explicitly resolved, then be updated to Accepted alongside (not ahead of) 20_API_DESIGN.md and 21_PERMISSION_MATRIX.md, which it depends on and must not become more authoritative than.

# ADR-014: Website Core Foundation

## Status

Accepted — 2026-08-07.

## Decision

MVP has exactly one Website per Tenant and Clinic. Website is implemented inside the existing Website Builder bounded context as the root to which future website capabilities attach.

Website Core owns immutable WebsiteId and TenantId, exactly one governed Template reference, constrained Branding, lifecycle, optimistic version, and timestamps. The five Template references are SYIFA Essential, SYIFA Care, SYIFA Dental, SYIFA Aesthetic, and SYIFA Specialist. Template may change in draft or review but is immutable after publication.

Lifecycle is `draft → ready_for_review → published → archived`. The published state requires explicit Website Approval and active-entitlement evidence at the application boundary, without importing Onboarding or Subscription domains. Core state does not deploy, render, host, or expose a public website.

Branding is limited to clinic name, tagline, two colors, logo/favicon opaque references, contact email/phone, address, and governed social-link channels. Arbitrary CSS, HTML, fonts, scripts, and unstructured configuration are prohibited.

Clinic Owner access is Tenant-bound, Website Designer access is assignment-bound, Super Admin support is explicitly authorized, and Public Visitor access is denied.

## Consequences

Persistence is additive and normalized. `websites.tenant_id` is unique. No Booking, Payment, Subscription, Clinic, file, SEO, CMS, rendering, deployment, domain, analytics, or tracking implementation is introduced.

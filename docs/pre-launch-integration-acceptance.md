# Pre-launch integration acceptance

This runbook is the release gate for SYIFA AI, Custom Domain, and WhatsApp booking notifications.
Run it first in staging with a newly provisioned clinic. Never use a real patient's data.

## Automated baseline

```bash
vendor/bin/phpunit tests/Architecture/SyifaAiArchitectureTest.php \
  tests/Feature/Support/Dashboard/WebsiteDesignerSyifaAiAuthorizationTest.php \
  tests/Unit/Modules/WebsiteBuilder/Application/SyifaAi/AssistWebsiteDraftServiceTest.php \
  tests/Unit/Modules/WebsiteBuilder/Infrastructure/SyifaAi/OpenAiSyifaAiProviderTest.php

vendor/bin/phpunit tests/Unit/Modules/WebsiteBuilder/Domain/CustomDomainTest.php \
  tests/Integration/Modules/WebsiteBuilder/Persistence/PostgresWebsitePublicAddressRepositoryTest.php

vendor/bin/phpunit tests/Feature/Modules/Notification/BookingWhatsAppNotificationTest.php \
  tests/Unit/Modules/Booking/Application/SubmitBookingServiceTest.php
```

The full PHPUnit suite, PHPStan, ESLint, and production frontend build must also pass.

## SYIFA AI live acceptance

Prerequisites: configure `OPENAI_API_KEY`, `OPENAI_BASE_URL`, and the approved model settings. Do
not put credentials in Git or screenshots.

1. Open a newly assigned Website Designer job.
2. Request one HERO content suggestion and one quality/SEO review.
3. Confirm the response is structured, editable, and is not saved until the user explicitly saves.
4. Confirm usage appears in Super Admin → SYIFA AI Usage for the correct tenant.
5. Temporarily use an invalid key and confirm the UI shows a safe generic failure without leaking the
   provider response or key.
6. Restore the valid key and confirm a retry succeeds.

Pass: correct tenant scope, no automatic content persistence, usage recorded, safe failure, retry works.

## Custom Domain live acceptance

Prerequisites: use a staging domain controlled by the team and configure the approved platform DNS
targets. The clinic Website must already be published and its subscription must include Custom Domain.

1. Request the staging hostname from the assigned Website Designer job.
2. Add the exact TXT ownership record shown by SYIFA.my.
3. Confirm verification fails before DNS propagation and succeeds after propagation.
4. Add the required A/AAAA/CNAME route to an approved platform target.
5. Activate the domain and confirm HTTPS serves the correct clinic, not another tenant.
6. Confirm the canonical URL, assets, Blog, and Booking paths use the custom host correctly.
7. Detach the domain and confirm it no longer resolves as an active clinic address.

Pass: ownership proof enforced, routing target approved, correct tenant served, HTTPS valid, detach safe.

## WhatsApp booking live acceptance

Follow `docs/operations-whatsapp-booking-notifications.md` first. Use a Meta-approved template and a
team-owned recipient number.

1. Leave notification disabled and submit a Website booking; confirm the booking is stored and no
   WhatsApp delivery is created.
2. Enable notification for the staging clinic and submit a new Website booking.
3. Confirm the booking appears in the clinic dashboard before checking WhatsApp.
4. Run the `notifications` queue worker and confirm one message reaches the configured number.
5. Confirm name, reference, date, time, service, and patient phone are correct; notes and medical data
   must not be present.
6. Confirm the dashboard delivery summary changes from queued/sending to sent.
7. Stop or misconfigure the provider temporarily, submit another booking, and confirm the booking is
   retained while delivery becomes failed and is retried without duplication.
8. Confirm staff-created Phone/WhatsApp/Walk-in/Staff bookings do not trigger this notification.

Pass: no lost booking, exactly one delivery, correct safe payload, observable status, retry without duplicate.

## Release evidence

Record the staging tenant, tester, UTC timestamp, release commit, and pass/fail result for every step.
Never record access tokens, patient data, DNS verification tokens, or provider response bodies containing
sensitive information. Any failed step blocks general production launch until fixed and rerun.

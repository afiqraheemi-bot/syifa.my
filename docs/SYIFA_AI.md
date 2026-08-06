# SYIFA AI

SYIFA AI is a governed writing and review assistant for the existing Website Draft Engine. It does not replace the Website aggregate, authorization rules, human review, or publication workflow.

## Initial capabilities

- Content Assistant for Hero, About, Services, Doctors, FAQ, and Contact.
- SEO and content quality review across the authoritative private draft.
- Designer Copilot for prioritising the next highest-impact Website improvements.
- Image assistance is intentionally disabled until text usage, cost, and operational flow are stable.

Clinic Owners and assigned Website Designers receive the same three capabilities: Content Assistant, SEO and content quality review, and Designer Copilot. Contact assistance reports completeness and next actions because contact data remains governed by the existing clinic contact configuration.

## Safety and authority

- Tenant and Website authority always comes from the authenticated Clinic Owner context or the active Website Designer assignment.
- The browser never submits a Tenant ID or Website ID.
- The provider receives a minimum authoritative projection: draft section data, approved services, template, SEO configuration, and non-secret clinic content.
- Prompts prohibit invented doctors, services, credentials, pricing, opening hours, outcomes, testimonials, medical claims, diagnoses, and medical advice.
- Results use strict structured output and remain suggestions. SYIFA AI cannot save a draft, alter lifecycle state, approve, or publish.
- API requests use `store: false`; usage events store token counts and identifiers, never prompts or generated content.

## Cost controls

- Per-actor rate limiting through the `syifa-ai` limiter.
- Per-tenant monthly token allowance, enforced before each request.
- Configurable output-token ceiling.
- Usage ledger records input and output tokens per capability, tenant, Website, actor, section, and model.
- The feature is fail-closed when disabled, missing a provider key, over allowance, or when the provider is unavailable.

## Runtime configuration

```dotenv
SYIFA_AI_ENABLED=true
SYIFA_AI_PROVIDER=openai
SYIFA_AI_MODEL=gpt-5.6-luna
OPENAI_API_KEY=<set-in-secret-manager>
OPENAI_BASE_URL=https://api.openai.com/v1
SYIFA_AI_TIMEOUT_SECONDS=30
SYIFA_AI_MAX_OUTPUT_TOKENS=1400
SYIFA_AI_MONTHLY_TENANT_TOKEN_LIMIT=250000
RATE_LIMIT_SYIFA_AI_PER_MINUTE=12
```

Keep the key in the production secret manager. Never expose it through Vite variables, Vue props, logs, or repository files. Clear the Laravel configuration cache after changing runtime secrets.

The integration uses the OpenAI Responses API and strict Structured Outputs. Review the current provider guidance before changing models or schema:

- <https://developers.openai.com/api/docs/guides/latest-model>
- <https://developers.openai.com/api/docs/guides/structured-outputs>

## Activation checklist

1. Apply the `create_syifa_ai_usage_events` migration.
2. Add the server-side API key through the environment secret facility.
3. Confirm the monthly token allowance and per-minute limiter.
4. Enable SYIFA AI.
5. Run one Clinic Owner content suggestion and one assigned Designer quality review.
6. Confirm token usage is recorded and no prompt or output text is persisted.
7. Monitor cost and acceptance quality before considering image assistance.

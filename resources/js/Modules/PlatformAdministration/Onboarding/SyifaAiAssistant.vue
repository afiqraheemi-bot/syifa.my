<script setup>
import { computed, ref } from 'vue';
import { browserHttpRequest } from '../../../Shared/Authentication/session.js';

const props = defineProps({
    endpoint: { type: String, required: true },
    enabled: { type: Boolean, required: true },
    imageAssistanceEnabled: { type: Boolean, default: false },
});
const emit = defineEmits(['apply']);

const capabilities = [
    {
        value: 'content_assistant',
        label: 'Content Assistant',
        detail: 'Improve approved copy without inventing clinic facts.',
    },
    {
        value: 'quality_review',
        label: 'SEO & Quality Check',
        detail: 'Review clarity, completeness, accessibility and search readiness.',
    },
    {
        value: 'designer_copilot',
        label: 'Designer Copilot',
        detail: 'Prioritise the next highest-impact improvements.',
    },
];
const sections = [
    ['HERO', 'Hero'],
    ['ABOUT', 'About'],
    ['SERVICES', 'Services'],
    ['DOCTORS', 'Doctors'],
    ['FAQ', 'FAQ'],
    ['CONTACT', 'Contact'],
];
const directlyApplicable = {
    HERO: new Set(['headline', 'subheadline', 'primary_cta_label', 'secondary_cta_label']),
    ABOUT: new Set(['heading', 'description']),
};
const capability = ref('content_assistant');
const section = ref('HERO');
const instruction = ref('');
const loading = ref(false);
const error = ref('');
const result = ref(null);
const copiedIndex = ref(null);

const needsSection = computed(() => capability.value === 'content_assistant');
const selectedCapability = computed(() =>
    capabilities.find((item) => item.value === capability.value),
);

function canApply(suggestion) {
    return directlyApplicable[section.value]?.has(suggestion.field) ?? false;
}

async function requestAssistance() {
    if (loading.value || !props.enabled) return;
    loading.value = true;
    error.value = '';
    result.value = null;

    try {
        const response = await browserHttpRequest(props.endpoint, {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({
                capability: capability.value,
                section: needsSection.value ? section.value : null,
                instruction: instruction.value.trim() || null,
            }),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(payload.detail ?? 'SYIFA AI could not complete this request.');
        }
        result.value = payload.data;
    } catch (requestError) {
        error.value = requestError.message ?? 'SYIFA AI could not complete this request.';
    } finally {
        loading.value = false;
    }
}

async function copySuggestion(suggestion, index) {
    try {
        await navigator.clipboard.writeText(suggestion.proposed_value);
        copiedIndex.value = index;
        window.setTimeout(() => {
            if (copiedIndex.value === index) copiedIndex.value = null;
        }, 1600);
    } catch {
        error.value = 'The suggestion could not be copied. Select the text and copy it manually.';
    }
}

function applySuggestion(suggestion) {
    emit('apply', { section: section.value, ...suggestion });
}
</script>

<template>
    <section
        id="syifa-ai"
        class="overflow-hidden rounded-2xl border border-violet-200 bg-white shadow-sm"
        aria-labelledby="syifa-ai-title"
    >
        <div
            class="bg-gradient-to-r from-violet-950 via-indigo-950 to-emerald-950 p-5 text-white sm:p-6"
        >
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-violet-200">
                        Governed website assistance
                    </p>
                    <h2 id="syifa-ai-title" class="mt-2 text-2xl font-bold">SYIFA AI</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-violet-100">
                        Create stronger clinic content and review the current private draft. Nothing
                        is saved or published until you review and apply it.
                    </p>
                </div>
                <span
                    class="w-fit rounded-full px-3 py-1 text-xs font-bold"
                    :class="enabled ? 'bg-emerald-300 text-emerald-950' : 'bg-white/15 text-white'"
                >
                    {{ enabled ? 'Ready' : 'Configuration required' }}
                </span>
            </div>
        </div>

        <div class="p-5 sm:p-6">
            <div class="grid gap-3 md:grid-cols-3" aria-label="SYIFA AI capability">
                <button
                    v-for="item in capabilities"
                    :key="item.value"
                    type="button"
                    class="rounded-xl border p-4 text-left transition focus:outline-none focus:ring-2 focus:ring-violet-600 focus:ring-offset-2"
                    :class="
                        capability === item.value
                            ? 'border-violet-500 bg-violet-50'
                            : 'border-slate-200 hover:border-violet-300'
                    "
                    @click="capability = item.value"
                >
                    <span class="font-bold text-slate-950">{{ item.label }}</span>
                    <span class="mt-1 block text-sm leading-5 text-slate-600">{{
                        item.detail
                    }}</span>
                </button>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-[14rem_1fr]">
                <label v-if="needsSection" class="text-sm font-semibold text-slate-800">
                    Website section
                    <select
                        v-model="section"
                        class="mt-1 block min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-slate-950 outline-none focus:border-violet-600 focus:ring-2 focus:ring-violet-600/20"
                    >
                        <option v-for="item in sections" :key="item[0]" :value="item[0]">
                            {{ item[1] }}
                        </option>
                    </select>
                </label>
                <label
                    class="text-sm font-semibold text-slate-800"
                    :class="needsSection ? '' : 'md:col-span-2'"
                >
                    Optional direction
                    <input
                        v-model="instruction"
                        maxlength="600"
                        class="mt-1 block min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-slate-950 outline-none focus:border-violet-600 focus:ring-2 focus:ring-violet-600/20"
                        placeholder="Example: Make the tone warmer and easier for families to understand"
                    />
                </label>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg bg-violet-700 px-5 font-bold text-white transition hover:bg-violet-800 focus:outline-none focus:ring-2 focus:ring-violet-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="loading || !enabled"
                    @click="requestAssistance"
                >
                    {{ loading ? 'SYIFA AI is reviewing…' : `Run ${selectedCapability.label}` }}
                </button>
                <p v-if="!enabled" class="text-sm text-slate-600">
                    Add the approved provider key and enable SYIFA AI in the server environment.
                </p>
                <p v-if="!imageAssistanceEnabled" class="text-sm text-slate-500">
                    Image assistance is intentionally deferred while usage and cost are stabilised.
                </p>
            </div>

            <p
                v-if="error"
                role="alert"
                class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"
            >
                {{ error }}
            </p>

            <div v-if="result" class="mt-6 border-t border-slate-200 pt-6">
                <h3 class="text-lg font-bold text-slate-950">{{ result.title }}</h3>
                <p class="mt-2 max-w-4xl text-sm leading-6 text-slate-700">
                    {{ result.summary }}
                </p>

                <div v-if="result.suggestions.length" class="mt-5 grid gap-3 lg:grid-cols-2">
                    <article
                        v-for="(suggestion, index) in result.suggestions"
                        :key="`${suggestion.field}-${index}`"
                        class="rounded-xl border border-slate-200 bg-slate-50 p-4"
                    >
                        <p class="text-xs font-bold uppercase tracking-wide text-violet-700">
                            {{ suggestion.label }}
                        </p>
                        <p class="mt-2 whitespace-pre-line font-semibold text-slate-950">
                            {{ suggestion.proposed_value }}
                        </p>
                        <p class="mt-2 text-sm leading-5 text-slate-600">
                            {{ suggestion.rationale }}
                        </p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button
                                v-if="canApply(suggestion)"
                                type="button"
                                class="min-h-10 rounded-lg bg-slate-900 px-4 text-sm font-bold text-white"
                                @click="applySuggestion(suggestion)"
                            >
                                Use in draft form
                            </button>
                            <button
                                type="button"
                                class="min-h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-800"
                                @click="copySuggestion(suggestion, index)"
                            >
                                {{ copiedIndex === index ? 'Copied' : 'Copy' }}
                            </button>
                        </div>
                    </article>
                </div>

                <ul v-if="result.checks.length" class="mt-5 grid gap-3 md:grid-cols-2">
                    <li
                        v-for="check in result.checks"
                        :key="`${check.label}-${check.message}`"
                        class="rounded-xl border p-4"
                        :class="
                            check.status === 'pass'
                                ? 'border-emerald-200 bg-emerald-50'
                                : 'border-amber-200 bg-amber-50'
                        "
                    >
                        <p class="font-bold text-slate-950">{{ check.label }}</p>
                        <p class="mt-1 text-sm leading-5 text-slate-700">{{ check.message }}</p>
                    </li>
                </ul>

                <div
                    v-if="result.next_actions.length"
                    class="mt-5 rounded-xl bg-slate-950 p-4 text-white"
                >
                    <p class="font-bold">Recommended next actions</p>
                    <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm leading-6 text-slate-200">
                        <li v-for="action in result.next_actions" :key="action">{{ action }}</li>
                    </ol>
                </div>
                <p class="mt-4 text-xs text-slate-500">
                    AI output can be inaccurate. Verify all clinic facts before saving or
                    publishing.
                </p>
            </div>
        </div>
    </section>
</template>

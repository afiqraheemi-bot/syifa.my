<script setup>
import { computed, ref } from 'vue';
import { createDashboardNavigation, DashboardShell } from '../../../Shared/Dashboard/index.js';

const props = defineProps({
    navigation: { type: Array, required: true },
    breadcrumbs: { type: Array, required: true },
    pageTitle: { type: String, required: true },
    pageDescription: { type: String, required: true },
    identityName: { type: String, default: null },
    contextLabel: { type: String, required: true },
    csrfToken: { type: String, required: true },
    formKind: { type: String, required: true },
    action: { type: String, required: true },
    cancelUrl: { type: String, required: true },
    error: { type: String, default: null },
    validationErrors: { type: Array, required: true },
    oldInput: { type: Object, required: true },
    plan: { type: Object, default: null },
    offering: { type: Object, default: null },
    billingOptions: { type: Array, required: true },
});

const navigation = createDashboardNavigation(props.navigation);
const submitting = ref(false);
let submissionStarted = false;
const isPlan = computed(() => props.formKind.startsWith('plan-'));
const isBillingOption = computed(() => props.formKind.startsWith('billing-option-'));
const isEdit = computed(() => props.formKind.endsWith('-edit'));
const recurrence = ref(fieldValue('recurrence_classification', 'recurring'));
const amountMyr = ref(formatMyr(fieldValue('amount_minor', props.offering?.amountMinor)));
const amountMinor = computed(() => {
    if (amountMyr.value.trim() === '') return '';

    const amount = Number(amountMyr.value);

    return Number.isFinite(amount) ? String(Math.round(amount * 100)) : '';
});

function fieldValue(name, fallback = '') {
    const previous = props.oldInput[name];
    return previous === null || previous === undefined ? fallback : previous;
}

function formatMyr(minor) {
    if (minor === null || minor === undefined || minor === '') return '';

    return (Number(minor) / 100).toFixed(2);
}

function beginSubmit(event) {
    if (submissionStarted) {
        event.preventDefault();
        return;
    }
    submissionStarted = true;
    window.setTimeout(() => {
        submitting.value = true;
    }, 0);
}
</script>

<template>
    <DashboardShell
        :navigation="navigation"
        :breadcrumbs="breadcrumbs"
        :page-title="pageTitle"
        :page-description="pageDescription"
        :identity-name="identityName"
        :context-label="contextLabel"
    >
        <div
            v-if="error || validationErrors.length"
            role="alert"
            class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"
        >
            <p class="font-semibold">
                {{ error ?? 'Please correct the highlighted commercial values.' }}
            </p>
            <ul v-if="validationErrors.length" class="mt-2 list-disc space-y-1 pl-5">
                <li v-for="message in validationErrors" :key="message">{{ message }}</li>
            </ul>
        </div>

        <section class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <form
                v-if="isPlan"
                class="grid gap-4 sm:grid-cols-2"
                :action="action"
                method="post"
                novalidate
                @submit="beginSubmit"
            >
                <input type="hidden" name="_token" :value="csrfToken" />
                <input v-if="isEdit" type="hidden" name="_method" value="patch" />
                <input v-if="isEdit" type="hidden" name="code" :value="plan.code" />
                <input v-if="isEdit" type="hidden" name="expected_version" :value="plan.version" />
                <label class="grid gap-1 text-sm font-semibold">
                    Plan code
                    <input
                        v-if="!isEdit"
                        name="code"
                        maxlength="50"
                        :value="fieldValue('code')"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                    <input
                        v-else
                        :value="plan.code"
                        disabled
                        class="min-h-11 rounded-xl border border-slate-200 bg-slate-100 px-3"
                    />
                </label>
                <label class="grid gap-1 text-sm font-semibold">
                    Plan name
                    <input
                        name="name"
                        maxlength="100"
                        :value="fieldValue('name', plan?.name)"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">
                    Description
                    <textarea
                        name="description"
                        maxlength="1000"
                        rows="4"
                        :value="fieldValue('description', plan?.description)"
                        class="rounded-xl border border-slate-300 px-3 py-2"
                    />
                </label>
                <label class="grid gap-1 text-sm font-semibold">
                    Display order
                    <input
                        name="display_order"
                        type="number"
                        min="0"
                        :value="fieldValue('display_order', plan?.displayOrder ?? 0)"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <div class="flex items-end gap-3">
                    <a
                        :href="cancelUrl"
                        class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-5 py-2 font-bold text-slate-900"
                    >
                        Cancel
                    </a>
                    <button
                        type="submit"
                        class="min-h-11 rounded-xl bg-slate-950 px-5 py-2 font-bold text-white disabled:opacity-60"
                        :disabled="submitting"
                    >
                        {{ submitting ? 'Saving…' : isEdit ? 'Save Plan' : 'Create Plan' }}
                    </button>
                </div>
            </form>

            <form
                v-else-if="isBillingOption"
                class="grid gap-4 sm:grid-cols-2"
                :action="action"
                method="post"
                novalidate
                @submit="beginSubmit"
            >
                <input type="hidden" name="_token" :value="csrfToken" />
                <label class="grid gap-1 text-sm font-semibold">
                    Billing option code
                    <input
                        name="code"
                        maxlength="50"
                        :value="fieldValue('code')"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <label class="grid gap-1 text-sm font-semibold">
                    Billing option name
                    <input
                        name="name"
                        maxlength="100"
                        :value="fieldValue('name')"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <label class="grid gap-1 text-sm font-semibold">
                    Billing type
                    <select
                        v-model="recurrence"
                        name="recurrence_classification"
                        class="min-h-11 rounded-xl border border-slate-300 bg-white px-3"
                    >
                        <option value="recurring">Recurring</option>
                        <option value="non_recurring">One-off</option>
                    </select>
                </label>
                <label v-if="recurrence === 'recurring'" class="grid gap-1 text-sm font-semibold">
                    Billing interval
                    <select
                        name="interval_unit"
                        class="min-h-11 rounded-xl border border-slate-300 bg-white px-3"
                    >
                        <option
                            value="month"
                            :selected="fieldValue('interval_unit', 'year') === 'month'"
                        >
                            Month
                        </option>
                        <option
                            value="year"
                            :selected="fieldValue('interval_unit', 'year') === 'year'"
                        >
                            Year
                        </option>
                    </select>
                </label>
                <label v-if="recurrence === 'recurring'" class="grid gap-1 text-sm font-semibold">
                    Number of intervals
                    <input
                        name="interval_count"
                        type="number"
                        min="1"
                        :value="fieldValue('interval_count', 1)"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <label class="grid gap-1 text-sm font-semibold">
                    Effective start
                    <input
                        name="effective_start"
                        type="date"
                        :value="fieldValue('effective_start')"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <label class="grid gap-1 text-sm font-semibold">
                    Effective end
                    <input
                        name="effective_end"
                        type="date"
                        :value="fieldValue('effective_end')"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <label class="grid gap-1 text-sm font-semibold">
                    Display order
                    <input
                        name="display_order"
                        type="number"
                        min="0"
                        :value="fieldValue('display_order', 0)"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <div class="flex items-end gap-3 sm:col-span-2">
                    <a
                        :href="cancelUrl"
                        class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-5 py-2 font-bold text-slate-900"
                    >
                        Cancel
                    </a>
                    <button
                        type="submit"
                        class="min-h-11 rounded-xl bg-slate-950 px-5 py-2 font-bold text-white disabled:opacity-60"
                        :disabled="submitting"
                    >
                        {{ submitting ? 'Creating…' : 'Create Billing Option' }}
                    </button>
                </div>
            </form>

            <form
                v-else
                class="grid gap-4 sm:grid-cols-2"
                :action="action"
                method="post"
                novalidate
                @submit="beginSubmit"
            >
                <input type="hidden" name="_token" :value="csrfToken" />
                <input v-if="isEdit" type="hidden" name="_method" value="patch" />
                <input type="hidden" name="plan_id" :value="plan.id" />
                <input
                    v-if="isEdit"
                    type="hidden"
                    name="expected_version"
                    :value="offering.version"
                />
                <label class="grid gap-1 text-sm font-semibold">
                    Billing option
                    <select
                        name="billing_option_id"
                        :disabled="isEdit"
                        class="min-h-11 rounded-xl border border-slate-300 bg-white px-3"
                    >
                        <option
                            v-for="option in billingOptions"
                            :key="option.id"
                            :value="option.id"
                            :selected="
                                fieldValue('billing_option_id', offering?.billingOptionId) ===
                                option.id
                            "
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <input
                        v-if="isEdit"
                        type="hidden"
                        name="billing_option_id"
                        :value="offering.billingOptionId"
                    />
                </label>
                <label class="grid gap-1 text-sm font-semibold">
                    Price (RM)
                    <span class="relative">
                        <span
                            aria-hidden="true"
                            class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-500"
                        >
                            RM
                        </span>
                        <input
                            v-model="amountMyr"
                            type="number"
                            inputmode="decimal"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            class="min-h-11 w-full rounded-xl border border-slate-300 pr-3 pl-12"
                        />
                    </span>
                    <input type="hidden" name="amount_minor" :value="amountMinor" />
                </label>
                <label class="grid gap-1 text-sm font-semibold">
                    Effective start
                    <input
                        name="effective_start"
                        type="date"
                        :value="fieldValue('effective_start', offering?.effectiveStart)"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <label class="grid gap-1 text-sm font-semibold">
                    Effective end
                    <input
                        name="effective_end"
                        type="date"
                        :value="fieldValue('effective_end', offering?.effectiveEnd)"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">
                    Feature configuration reference
                    <input
                        name="capability_configuration_reference"
                        maxlength="100"
                        :value="
                            fieldValue(
                                'capability_configuration_reference',
                                offering?.featureConfiguration,
                            )
                        "
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <label class="grid gap-1 text-sm font-semibold">
                    Display order
                    <input
                        name="display_order"
                        type="number"
                        min="0"
                        :value="fieldValue('display_order', offering?.displayOrder ?? 0)"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <div class="flex items-end gap-3">
                    <a
                        :href="cancelUrl"
                        class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-5 py-2 font-bold text-slate-900"
                    >
                        Cancel
                    </a>
                    <button
                        type="submit"
                        class="min-h-11 rounded-xl bg-slate-950 px-5 py-2 font-bold text-white disabled:opacity-60"
                        :disabled="submitting"
                    >
                        {{ submitting ? 'Saving…' : isEdit ? 'Save Offering' : 'Create Offering' }}
                    </button>
                </div>
            </form>
        </section>
    </DashboardShell>
</template>

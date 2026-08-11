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
    billingOption: { type: Object, default: null },
    capability: { type: Object, default: null },
    billingOptions: { type: Array, required: true },
    billingOptionCreateUrl: { type: String, required: true },
});

const navigation = createDashboardNavigation(props.navigation);
const submitting = ref(false);
let submissionStarted = false;
const isPackage = computed(() => props.formKind.startsWith('package-'));
const isPlan = computed(() => props.formKind.startsWith('plan-'));
const isBillingOption = computed(() => props.formKind.startsWith('billing-option-'));
const isCapability = computed(() => props.formKind.startsWith('capability-'));
const isEdit = computed(() => props.formKind.endsWith('-edit'));
const availableBillingOptions = computed(() =>
    props.billingOptions.filter((option) => option.availability === 'available'),
);
const formContext = computed(() => {
    if (isPackage.value) {
        return {
            eyebrow: 'Subscription package',
            title: 'Create the plan and its first price together',
            description:
                'Enter the customer-facing package once. SYIFA.my keeps the plan, billing cycle and pricing records separately behind the scenes for safe future changes.',
        };
    }

    if (isPlan.value) {
        return {
            eyebrow: 'Subscription plan',
            title: 'Define the package clinics will recognise',
            description:
                'Use a short name and clear customer-facing description. Pricing is managed separately so plan content can evolve safely.',
        };
    }

    if (isBillingOption.value) {
        return {
            eyebrow: 'Billing cycle',
            title: 'Define when clinics are charged',
            description:
                'Set the recurrence and effective period once, then reuse this cycle when adding plan prices.',
        };
    }

    if (isCapability.value) {
        return {
            eyebrow: 'Plan feature',
            title: 'Describe an approved customer benefit',
            description:
                'Keep the internal key stable and explain the feature in language that Commercial and support teams can understand.',
        };
    }

    return {
        eyebrow: 'Plan price',
        title: 'Connect this plan to a billing cycle and price',
        description:
            'The browser sends the selected cycle and MYR price; the server remains authoritative for lifecycle, validation and audit.',
    };
});
const recurrence = ref(
    fieldValue('recurrence_classification', props.billingOption?.recurrence ?? 'recurring'),
);
const amountMyr = ref(
    isPackage.value
        ? String(fieldValue('price_myr', formatMyr(props.offering?.amountMinor)))
        : formatMyr(fieldValue('amount_minor', props.offering?.amountMinor)),
);
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
            <div class="mb-6 rounded-xl border border-emerald-100 bg-emerald-50 p-4 sm:p-5">
                <p class="text-xs font-bold tracking-[0.16em] text-emerald-700 uppercase">
                    {{ formContext.eyebrow }}
                </p>
                <h2 class="mt-1 text-lg font-bold text-slate-950">{{ formContext.title }}</h2>
                <p class="mt-1 text-sm leading-6 text-slate-600">
                    {{ formContext.description }}
                </p>
            </div>
            <form
                v-if="isPackage"
                class="grid gap-4 sm:grid-cols-2"
                :action="action"
                method="post"
                @submit="beginSubmit"
            >
                <input type="hidden" name="_token" :value="csrfToken" />
                <label class="grid gap-1 text-sm font-semibold">
                    Package code
                    <span class="font-normal text-slate-500">
                        Stable internal reference, for example ESSENTIAL. Uppercase is accepted;
                        SYIFA.my stores a safe normalized reference.
                    </span>
                    <input
                        name="code"
                        required
                        maxlength="50"
                        spellcheck="false"
                        :value="fieldValue('code')"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <label class="grid gap-1 text-sm font-semibold">
                    Package name
                    <span class="font-normal text-slate-500">The name clinics will see.</span>
                    <input
                        name="name"
                        required
                        maxlength="100"
                        :value="fieldValue('name')"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">
                    Package description
                    <textarea
                        name="description"
                        required
                        maxlength="1000"
                        rows="4"
                        :value="fieldValue('description')"
                        class="rounded-xl border border-slate-300 px-3 py-2"
                    />
                </label>
                <label class="grid gap-1 text-sm font-semibold">
                    Billing cycle
                    <span class="font-normal text-slate-500">
                        Choose an approved cycle such as annual or monthly.
                    </span>
                    <select
                        name="billing_option_id"
                        required
                        class="min-h-11 rounded-xl border border-slate-300 bg-white px-3"
                    >
                        <option value="">Select billing cycle</option>
                        <option
                            v-for="option in availableBillingOptions"
                            :key="option.id"
                            :value="option.id"
                            :selected="fieldValue('billing_option_id') === option.id"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <a
                        v-if="availableBillingOptions.length === 0"
                        :href="billingOptionCreateUrl"
                        class="mt-1 font-bold text-emerald-700 underline underline-offset-4"
                    >
                        Create the first billing cycle
                    </a>
                </label>
                <label class="grid gap-1 text-sm font-semibold">
                    Price (MYR)
                    <span class="font-normal text-slate-500">
                        Enter ringgit, for example 1200.00.
                    </span>
                    <input
                        v-model="amountMyr"
                        name="price_myr"
                        type="number"
                        required
                        inputmode="decimal"
                        min="0.01"
                        step="0.01"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <label class="grid gap-1 text-sm font-semibold">
                    Available for sale from
                    <input
                        name="effective_start"
                        type="date"
                        required
                        :value="fieldValue('effective_start')"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <label class="grid gap-1 text-sm font-semibold">
                    Sales end date (optional)
                    <input
                        name="effective_end"
                        type="date"
                        :value="fieldValue('effective_end')"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <div class="flex flex-col-reverse gap-3 sm:col-span-2 sm:flex-row sm:justify-end">
                    <a
                        :href="cancelUrl"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-5 py-2 font-bold text-slate-900"
                    >
                        Cancel
                    </a>
                    <button
                        type="submit"
                        class="min-h-11 rounded-xl bg-slate-950 px-5 py-2 font-bold text-white disabled:opacity-60"
                        :disabled="submitting || availableBillingOptions.length === 0"
                    >
                        {{ submitting ? 'Creating package…' : 'Create subscription package' }}
                    </button>
                </div>
            </form>

            <form
                v-else-if="isPlan"
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
                    <span class="font-normal text-slate-500">
                        Stable internal reference, for example essential. Capital letters are
                        converted automatically.
                    </span>
                    <input
                        v-if="!isEdit"
                        name="code"
                        maxlength="50"
                        autocapitalize="none"
                        spellcheck="false"
                        :value="fieldValue('code')"
                        class="min-h-11 rounded-xl border border-slate-300 px-3 lowercase"
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
                    <span class="font-normal text-slate-500">Lower numbers appear first.</span>
                    <input
                        name="display_order"
                        type="number"
                        min="0"
                        :value="fieldValue('display_order', plan?.displayOrder ?? 0)"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-end">
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
                <input v-if="isEdit" type="hidden" name="_method" value="patch" />
                <input
                    v-if="isEdit"
                    type="hidden"
                    name="expected_version"
                    :value="billingOption.version"
                />
                <label class="grid gap-1 text-sm font-semibold">
                    Billing cycle code
                    <span class="font-normal text-slate-500">
                        Stable internal reference, for example annual. Capital letters are converted
                        automatically.
                    </span>
                    <input
                        name="code"
                        maxlength="50"
                        autocapitalize="none"
                        spellcheck="false"
                        :value="fieldValue('code', billingOption?.code)"
                        :readonly="isEdit"
                        class="min-h-11 rounded-xl border border-slate-300 px-3 lowercase"
                    />
                </label>
                <label class="grid gap-1 text-sm font-semibold">
                    Billing cycle name
                    <input
                        name="name"
                        maxlength="100"
                        :value="fieldValue('name', billingOption?.name)"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <label v-if="isEdit" class="grid gap-1 text-sm font-semibold">
                    Availability
                    <select
                        name="availability"
                        class="min-h-11 rounded-xl border border-slate-300 bg-white px-3"
                    >
                        <option
                            value="available"
                            :selected="
                                fieldValue('availability', billingOption?.availability) ===
                                'available'
                            "
                        >
                            Available
                        </option>
                        <option
                            value="unavailable"
                            :selected="
                                fieldValue('availability', billingOption?.availability) ===
                                'unavailable'
                            "
                        >
                            Unavailable
                        </option>
                    </select>
                </label>
                <label class="grid gap-1 text-sm font-semibold">
                    Payment pattern
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
                            value="day"
                            :selected="
                                fieldValue(
                                    'interval_unit',
                                    billingOption?.intervalUnit ?? 'year',
                                ) === 'day'
                            "
                        >
                            Day
                        </option>
                        <option
                            value="month"
                            :selected="
                                fieldValue(
                                    'interval_unit',
                                    billingOption?.intervalUnit ?? 'year',
                                ) === 'month'
                            "
                        >
                            Month
                        </option>
                        <option
                            value="year"
                            :selected="
                                fieldValue(
                                    'interval_unit',
                                    billingOption?.intervalUnit ?? 'year',
                                ) === 'year'
                            "
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
                        :value="fieldValue('interval_count', billingOption?.intervalCount ?? 1)"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <label class="grid gap-1 text-sm font-semibold">
                    Available from
                    <input
                        name="effective_start"
                        type="date"
                        :value="fieldValue('effective_start', billingOption?.effectiveStart)"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <label class="grid gap-1 text-sm font-semibold">
                    Available until
                    <input
                        name="effective_end"
                        type="date"
                        :value="fieldValue('effective_end', billingOption?.effectiveEnd)"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <label class="grid gap-1 text-sm font-semibold">
                    Display order
                    <span class="font-normal text-slate-500">Lower numbers appear first.</span>
                    <input
                        name="display_order"
                        type="number"
                        min="0"
                        :value="fieldValue('display_order', billingOption?.displayOrder ?? 0)"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <div class="flex flex-col-reverse gap-3 sm:col-span-2 sm:flex-row sm:items-end">
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
                        {{
                            submitting
                                ? 'Saving…'
                                : isEdit
                                  ? 'Save Billing Cycle'
                                  : 'Create Billing Cycle'
                        }}
                    </button>
                </div>
            </form>

            <form
                v-else-if="isCapability"
                class="grid gap-4 sm:grid-cols-2"
                :action="action"
                method="post"
                novalidate
                @submit="beginSubmit"
            >
                <input type="hidden" name="_token" :value="csrfToken" />
                <input v-if="isEdit" type="hidden" name="_method" value="patch" />
                <input
                    v-if="isEdit"
                    type="hidden"
                    name="expected_version"
                    :value="capability.version"
                />
                <label class="grid gap-1 text-sm font-semibold">
                    Feature key
                    <span class="font-normal text-slate-500">
                        Stable internal reference; it cannot change after creation.
                    </span>
                    <input
                        name="capability_key"
                        maxlength="80"
                        :value="fieldValue('capability_key', capability?.key)"
                        :readonly="isEdit"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <label class="grid gap-1 text-sm font-semibold">
                    Feature name
                    <input
                        name="name"
                        maxlength="100"
                        :value="fieldValue('name', capability?.name)"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">
                    Description
                    <textarea
                        name="description"
                        maxlength="1000"
                        rows="4"
                        :value="fieldValue('description', capability?.description)"
                        class="rounded-xl border border-slate-300 px-3 py-2"
                    />
                </label>
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">
                    Customer-facing value
                    <textarea
                        name="commercial_meaning"
                        maxlength="1000"
                        rows="4"
                        :value="fieldValue('commercial_meaning', capability?.commercialMeaning)"
                        class="rounded-xl border border-slate-300 px-3 py-2"
                    />
                </label>
                <div class="flex flex-col-reverse gap-3 sm:col-span-2 sm:flex-row sm:items-end">
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
                        {{
                            submitting
                                ? 'Saving…'
                                : isEdit
                                  ? 'Save Feature Definition'
                                  : 'Create Feature Definition'
                        }}
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
                    Billing cycle
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
                            {{ option.label }} · {{ option.availabilityLabel }}
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
                    Available from
                    <input
                        name="effective_start"
                        type="date"
                        :value="fieldValue('effective_start', offering?.effectiveStart)"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <label class="grid gap-1 text-sm font-semibold">
                    Available until
                    <input
                        name="effective_end"
                        type="date"
                        :value="fieldValue('effective_end', offering?.effectiveEnd)"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">
                    Approved feature set reference
                    <span class="font-normal text-slate-500">
                        Reference the existing approved configuration; do not enter customer data.
                    </span>
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
                    <span class="font-normal text-slate-500">Lower numbers appear first.</span>
                    <input
                        name="display_order"
                        type="number"
                        min="0"
                        :value="fieldValue('display_order', offering?.displayOrder ?? 0)"
                        class="min-h-11 rounded-xl border border-slate-300 px-3"
                    />
                </label>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-end">
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
                        {{ submitting ? 'Saving…' : isEdit ? 'Save Price' : 'Create Price' }}
                    </button>
                </div>
            </form>
        </section>
    </DashboardShell>
</template>

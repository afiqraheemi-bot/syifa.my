<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, toRaw, watch } from 'vue';
import { browserHttpRequest } from '../../../Shared/Authentication/session.js';
import {
    ContentHealthSummary,
    createDashboardNavigation,
    DashboardShell,
} from '../../../Shared/Dashboard/index.js';
import WebsiteImageUpload from '../../../Shared/Website/WebsiteImageUpload.vue';
import WebsiteSeoEditor from '../../../Shared/Website/WebsiteSeoEditor.vue';
import { websiteTemplateThemeStyle } from '../../../Shared/Website/templateTheme.js';
import ClinicOwnerDraftSections from './ClinicOwnerDraftSections.vue';

const props = defineProps({
    navigation: { type: Array, required: true },
    breadcrumbs: { type: Array, required: true },
    pageTitle: { type: String, required: true },
    pageDescription: { type: String, required: true },
    identityName: { type: String, default: null },
    contextLabel: { type: String, required: true },
    contentHealth: { type: Object, required: true },
    contentSections: { type: Array, required: true },
    editableContent: { type: Object, required: true },
    templateOptions: { type: Array, required: true },
    canChangeTemplate: { type: Boolean, required: true },
    updateUrl: { type: String, required: true },
    previewUrl: { type: String, required: true },
    blogUrl: { type: String, required: true },
    blogVisibilityUrl: { type: String, required: true },
    blogVisible: { type: Boolean, default: true },
    publishedWebsite: { type: Object, default: null },
    websiteDraft: { type: Object, required: true },
    syifaAi: { type: Object, required: true },
    contactProfile: { type: Object, required: true },
    contactUpdateUrl: { type: String, required: true },
});

const whatsAppBusy = ref(false);
const whatsAppFeedback = ref('');
const whatsAppError = ref('');
const whatsAppJustSaved = ref(false);
const whatsAppEnabled = ref(Boolean(props.contactProfile.whatsapp_number));
const whatsAppNumber = ref(props.contactProfile.whatsapp_number ?? '');
const whatsAppVersion = ref(props.contactProfile.version);
const whatsAppIsSetToShow = computed(() => Boolean(props.contactProfile.whatsapp_number));
const blogSectionVisible = ref(props.blogVisible);
const blogVisibilitySaving = ref(false);
const blogVisibilityError = ref('');
const lastSavedBlogVisibility = ref(props.blogVisible);
const hasBlogVisibilityChanges = computed(
    () => blogSectionVisible.value !== lastSavedBlogVisibility.value,
);

async function saveBlogVisibility() {
    if (blogVisibilitySaving.value || !hasBlogVisibilityChanges.value) return true;
    const intendedVisibility = blogSectionVisible.value;
    blogVisibilitySaving.value = true;
    blogVisibilityError.value = '';

    try {
        const response = await browserHttpRequest(props.blogVisibilityUrl, {
            method: 'PATCH',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ enabled: intendedVisibility }),
        });
        if (!response.ok) throw new Error('Unable to save Blog visibility.');
        lastSavedBlogVisibility.value = intendedVisibility;
        return true;
    } catch {
        blogVisibilityError.value =
            'Blog visibility could not be saved. Please check your connection and try again.';
        return false;
    } finally {
        blogVisibilitySaving.value = false;
    }
}

watch(
    () => props.contactProfile.version,
    () => {
        whatsAppEnabled.value = Boolean(props.contactProfile.whatsapp_number);
        whatsAppNumber.value = props.contactProfile.whatsapp_number ?? '';
        whatsAppVersion.value = props.contactProfile.version;
    },
);

function saveWhatsApp() {
    if (whatsAppBusy.value) return;
    whatsAppBusy.value = true;
    whatsAppFeedback.value = '';
    whatsAppError.value = '';
    whatsAppJustSaved.value = false;

    router.patch(
        props.contactUpdateUrl,
        {
            whatsapp_number: whatsAppEnabled.value ? whatsAppNumber.value.trim() : '',
            version: whatsAppVersion.value,
        },
        {
            preserveScroll: true,
            onSuccess: async () => {
                const configurationSaved = await saveConfiguration();
                if (!configurationSaved) {
                    whatsAppError.value =
                        'Your WhatsApp number was saved, but the button design could not be saved. Review the highlighted Website setting and try again.';
                    return;
                }
                whatsAppFeedback.value = whatsAppEnabled.value
                    ? 'Saved. Your WhatsApp button and selected design are ready to preview.'
                    : 'Saved. WhatsApp is now set to hidden.';
                whatsAppJustSaved.value = true;
            },
            onError: (errors) => {
                whatsAppError.value =
                    Object.values(errors)[0] ?? 'Your WhatsApp setting could not be saved.';
            },
            onFinish: () => {
                whatsAppBusy.value = false;
            },
        },
    );
}

const navigation = createDashboardNavigation(props.navigation);
const saved = ref(false);
const configurationSaving = ref(false);
const draftEditor = ref(null);
const draftState = ref({ loading: true, saving: false, dirty: false });
const socialChannels = ['facebook', 'instagram', 'youtube', 'tiktok', 'linkedin'];
const form = useForm({
    version: props.editableContent.version,
    template_id: props.editableContent.template_id,
    branding: {
        ...props.editableContent.branding,
        whatsapp_button_style: props.editableContent.branding.whatsapp_button_style ?? 'pill',
        social_links: Object.fromEntries(
            socialChannels.map((channel) => [
                channel,
                props.editableContent.branding.social_links[channel] ?? '',
            ]),
        ),
    },
    seo: { ...props.editableContent.seo },
    sections: Object.fromEntries(
        props.editableContent.sections.map((section) => [section.key, section.enabled]),
    ),
});

function configurationSnapshot() {
    return JSON.stringify({
        template_id: form.template_id,
        // Keep these reactive proxies intact while serialising so Vue tracks
        // every nested field used to determine whether the form is dirty.
        branding: form.branding,
        seo: form.seo,
        sections: form.sections,
    });
}

function editableConfigurationSnapshot(content) {
    return JSON.stringify({
        template_id: content.template_id,
        branding: {
            ...content.branding,
            social_links: Object.fromEntries(
                socialChannels.map((channel) => [
                    channel,
                    content.branding.social_links[channel] ?? '',
                ]),
            ),
        },
        seo: content.seo,
        sections: Object.fromEntries(
            content.sections.map((section) => [section.key, section.enabled]),
        ),
    });
}

const lastSavedConfiguration = ref(configurationSnapshot());
const hasConfigurationChanges = computed(
    () => configurationSnapshot() !== lastSavedConfiguration.value,
);
const configurationRefreshed = ref(false);
const savingAnything = computed(
    () => configurationSaving.value || draftState.value.saving || blogVisibilitySaving.value,
);
const hasAnyChanges = computed(
    () => hasConfigurationChanges.value || draftState.value.dirty || hasBlogVisibilityChanges.value,
);

function configurationPayload() {
    return {
        version: form.version,
        template_id: form.template_id,
        branding: toRaw(form.branding),
        seo: toRaw(form.seo),
        sections: toRaw(form.sections),
    };
}

function applyValidationErrors(errors) {
    form.clearErrors();
    for (const [field, messages] of Object.entries(errors ?? {})) {
        form.setError(field, Array.isArray(messages) ? messages[0] : messages);
    }
}

async function saveConfiguration(allowSafeRetry = true) {
    if (configurationSaving.value) return false;
    if (!hasConfigurationChanges.value) return true;

    saved.value = false;
    configurationRefreshed.value = false;
    configurationSaving.value = true;
    form.clearErrors();

    try {
        const response = await browserHttpRequest(props.updateUrl, {
            method: 'PATCH',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(configurationPayload()),
        });

        if (response.ok && Number.isInteger(response.body?.data?.version)) {
            form.version = response.body.data.version;
            lastSavedConfiguration.value = configurationSnapshot();
            saved.value = true;
            return true;
        }

        const current = response.body?.data?.editable_content;
        if (response.status === 409 && current && Number.isInteger(current.version)) {
            const safeToRetry =
                allowSafeRetry &&
                editableConfigurationSnapshot(current) === lastSavedConfiguration.value;
            form.version = current.version;

            if (safeToRetry) {
                configurationSaving.value = false;
                return saveConfiguration(false);
            }

            configurationRefreshed.value = true;
            return false;
        }

        applyValidationErrors(response.body?.errors);
        if (!form.hasErrors) {
            form.setError(
                'save',
                response.body?.detail ?? 'Website settings could not be saved. Please try again.',
            );
        }
    } catch {
        form.setError('save', 'Website settings could not be saved. Check your connection.');
    } finally {
        configurationSaving.value = false;
    }

    return false;
}

async function saveAll() {
    if (savingAnything.value || !hasAnyChanges.value) return;

    saved.value = false;
    const draftSaved = draftState.value.dirty ? ((await draftEditor.value?.save()) ?? false) : true;
    if (!draftSaved) return;

    const configurationSaved = await saveConfiguration();
    if (!configurationSaved) return;

    const blogVisibilitySaved = await saveBlogVisibility();
    if (blogVisibilitySaved) saved.value = true;
}

function synchronizeWebsiteVersion(value) {
    const version = Number.isInteger(value?.website_version) ? value.website_version : value;
    if (Number.isInteger(version) && version > form.version) form.version = version;
}

const inputClass =
    'website-theme-input mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-950 shadow-sm outline-none transition';
const editorThemeStyle = computed(() => websiteTemplateThemeStyle(form.template_id));
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
        <div :data-website-template="form.template_id" :style="editorThemeStyle" class="space-y-8">
            <section
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                aria-labelledby="website-design-heading"
            >
                <div class="grid lg:grid-cols-[minmax(0,1fr)_minmax(20rem,0.72fr)]">
                    <div class="p-5 sm:p-6">
                        <div class="flex flex-wrap items-center gap-3">
                            <h2
                                id="website-design-heading"
                                class="text-xl font-bold text-slate-950"
                            >
                                Website design
                            </h2>
                            <span
                                v-if="!canChangeTemplate"
                                class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-900"
                            >
                                Published website
                            </span>
                        </div>
                        <p class="mt-1 text-sm leading-6 text-slate-600">
                            <template v-if="canChangeTemplate">
                                Choose an approved template. Your saved clinic content will remain
                                unchanged.
                            </template>
                            <template v-else>
                                Your Website Designer manages template changes after publication to
                                protect the live layout.
                            </template>
                        </p>

                        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end">
                            <label class="min-w-0 flex-1 text-sm font-semibold text-slate-800">
                                Current template
                                <select
                                    v-model="form.template_id"
                                    :disabled="!canChangeTemplate || configurationSaving"
                                    :class="inputClass"
                                >
                                    <option
                                        v-for="template in templateOptions"
                                        :key="template.value"
                                        :value="template.value"
                                    >
                                        {{ template.label }}
                                    </option>
                                </select>
                            </label>
                            <button
                                v-if="canChangeTemplate"
                                type="button"
                                :disabled="configurationSaving || !hasConfigurationChanges"
                                class="website-theme-outline inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl border bg-white px-5 font-bold transition focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                                @click="saveConfiguration"
                            >
                                {{ configurationSaving ? 'Saving…' : 'Save template' }}
                            </button>
                        </div>
                        <span
                            v-if="form.errors.template_id"
                            class="mt-2 block text-sm text-red-700"
                        >
                            {{ form.errors.template_id }}
                        </span>
                    </div>

                    <div
                        class="website-theme-surface flex flex-col justify-center border-t p-5 sm:p-6 lg:border-l lg:border-t-0"
                    >
                        <p class="website-theme-text text-xs font-bold uppercase tracking-[0.16em]">
                            Latest draft · Private
                        </p>
                        <h3 class="mt-2 text-lg font-bold text-slate-950">
                            Preview changes before publication
                        </h3>
                        <p class="mt-1 text-sm leading-6 text-slate-700">
                            This protected preview uses your latest saved draft. It is intentionally
                            different from the public Website and is visible only inside your Clinic
                            Owner workspace.
                        </p>
                        <a
                            :href="previewUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="website-theme-primary mt-5 inline-flex min-h-11 items-center justify-center rounded-xl px-5 font-bold shadow-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2"
                        >
                            Preview latest draft
                            <span aria-hidden="true" class="ml-2">↗</span>
                            <span class="sr-only"> (opens in a new tab)</span>
                        </a>
                        <div
                            v-if="publishedWebsite"
                            class="website-theme-divider mt-4 border-t pt-4 text-sm"
                        >
                            <p class="font-bold text-slate-900">Published Website · Public</p>
                            <p class="mt-1 break-all text-slate-600">
                                {{ publishedWebsite.host }}
                            </p>
                            <a
                                :href="publishedWebsite.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="website-theme-link mt-2 inline-flex font-bold underline underline-offset-4"
                            >
                                Open live Website
                                <span aria-hidden="true" class="ml-1">↗</span>
                                <span class="sr-only"> (opens in a new tab)</span>
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <ClinicOwnerDraftSections
                ref="draftEditor"
                :website-draft="websiteDraft"
                :template-id="form.template_id"
                :syifa-ai="syifaAi"
                :external-dirty="hasConfigurationChanges || hasBlogVisibilityChanges"
                :external-saving="configurationSaving || blogVisibilitySaving"
                @state="draftState = $event"
                @website-version="synchronizeWebsiteVersion"
                @save-all="saveAll"
            >
                <template #whatsapp>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="max-w-prose text-sm leading-6 text-slate-600">
                            Give patients a quick way to ask a question without replacing your
                            appointment form. The shortcut stays visible as they browse your
                            website.
                        </p>
                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="
                                whatsAppIsSetToShow
                                    ? 'bg-emerald-100 text-emerald-800'
                                    : 'bg-slate-100 text-slate-700'
                            "
                        >
                            {{ whatsAppIsSetToShow ? 'Set to show' : 'Set to hidden' }}
                        </span>
                    </div>
                    <p
                        v-if="whatsAppJustSaved"
                        class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900"
                    >
                        Saved. Use <strong>Preview latest draft</strong> above to see it — your live
                        website only updates once it's next republished.
                    </p>
                    <p
                        v-if="whatsAppError"
                        role="alert"
                        class="mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800"
                    >
                        {{ whatsAppError }}
                    </p>

                    <div class="mt-5 grid gap-5">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input
                                v-model="whatsAppEnabled"
                                type="checkbox"
                                class="mt-1 size-5 rounded border-slate-300 text-emerald-700 focus:ring-emerald-500"
                            />
                            <span class="text-sm font-medium text-slate-900">
                                Show a WhatsApp button on my website
                            </span>
                        </label>

                        <template v-if="whatsAppEnabled">
                            <label class="max-w-xl text-sm font-semibold text-slate-800">
                                WhatsApp number
                                <input
                                    v-model.trim="whatsAppNumber"
                                    type="tel"
                                    required
                                    placeholder="+60123456789"
                                    maxlength="40"
                                    :class="inputClass"
                                    @keydown.enter.prevent="saveWhatsApp"
                                />
                                <span class="mt-1 block text-xs font-normal text-slate-500">
                                    Include your country code, e.g. +60 for Malaysia.
                                </span>
                            </label>

                            <fieldset>
                                <legend class="text-sm font-semibold text-slate-800">
                                    Button design
                                </legend>
                                <p class="mt-1 text-xs text-slate-500">
                                    Choose one mobile-friendly style. Every option stays fixed at
                                    the lower corner of the website.
                                </p>
                                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                    <label
                                        v-for="option in [
                                            {
                                                value: 'pill',
                                                label: 'Pill',
                                                description: 'Icon and text',
                                            },
                                            {
                                                value: 'circle',
                                                label: 'Circle',
                                                description: 'Compact icon',
                                            },
                                            {
                                                value: 'rounded_square',
                                                label: 'Rounded square',
                                                description: 'Bold icon',
                                            },
                                        ]"
                                        :key="option.value"
                                        class="cursor-pointer rounded-2xl border p-4 transition"
                                        :class="
                                            form.branding.whatsapp_button_style === option.value
                                                ? 'border-emerald-700 bg-emerald-50 ring-2 ring-emerald-700/15'
                                                : 'border-slate-200 bg-white hover:border-slate-400'
                                        "
                                    >
                                        <span class="flex items-start gap-3">
                                            <input
                                                v-model="form.branding.whatsapp_button_style"
                                                type="radio"
                                                name="whatsapp_button_style"
                                                :value="option.value"
                                                class="mt-1 size-4 border-slate-300 text-emerald-700 focus:ring-emerald-600"
                                            />
                                            <span>
                                                <span class="block font-bold text-slate-950">{{
                                                    option.label
                                                }}</span>
                                                <span
                                                    class="mt-0.5 block text-xs font-normal text-slate-500"
                                                    >{{ option.description }}</span
                                                >
                                            </span>
                                        </span>
                                        <span
                                            class="mt-4 flex min-h-14 items-center justify-center rounded-xl bg-slate-50"
                                        >
                                            <span
                                                class="inline-flex items-center justify-center bg-[#128c4a] text-white shadow-md"
                                                :class="{
                                                    'h-11 gap-2 rounded-full px-4':
                                                        option.value === 'pill',
                                                    'size-12 rounded-full':
                                                        option.value === 'circle',
                                                    'size-12 rounded-xl':
                                                        option.value === 'rounded_square',
                                                }"
                                            >
                                                <svg
                                                    aria-hidden="true"
                                                    viewBox="0 0 24 24"
                                                    class="size-5 fill-none stroke-current"
                                                >
                                                    <path
                                                        d="M21 11.5a8.38 8.38 0 0 1-.9 3.8A8.5 8.5 0 0 1 12.5 20a8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7A8.38 8.38 0 0 1 4 11.5 8.5 8.5 0 0 1 8.7 3.9 8.38 8.38 0 0 1 12.5 3h.5a8.48 8.48 0 0 1 8 8v.5Z"
                                                        stroke-width="1.8"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                    />
                                                </svg>
                                                <span
                                                    v-if="option.value === 'pill'"
                                                    class="text-xs font-bold"
                                                >
                                                    WhatsApp
                                                </span>
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <span
                                    v-if="form.errors['branding.whatsapp_button_style']"
                                    class="mt-2 block text-sm text-red-700"
                                >
                                    {{ form.errors['branding.whatsapp_button_style'] }}
                                </span>
                            </fieldset>
                        </template>

                        <button
                            type="button"
                            :disabled="
                                whatsAppBusy ||
                                configurationSaving ||
                                (whatsAppEnabled && whatsAppNumber.trim() === '')
                            "
                            class="w-full rounded-xl bg-emerald-700 px-5 py-3 font-semibold text-white disabled:opacity-60 sm:w-auto"
                            @click="saveWhatsApp"
                        >
                            {{
                                whatsAppBusy || configurationSaving
                                    ? 'Saving…'
                                    : 'Save WhatsApp settings'
                            }}
                        </button>
                    </div>
                </template>
                <template #contact>
                    <div class="rounded-xl bg-slate-50 p-4 sm:p-5">
                        <p class="website-theme-text text-xs font-bold uppercase tracking-[0.16em]">
                            Clinic and brand
                        </p>
                        <h3 class="mt-2 text-lg font-bold text-slate-950">
                            Identity, contact and social details
                        </h3>
                        <p class="mt-1 text-sm leading-6 text-slate-600">
                            Keep the clinic identity and public contact information together. These
                            changes remain private until the governed publication step.
                        </p>
                    </div>
                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        <label class="text-sm font-semibold text-slate-800">
                            Clinic name
                            <input
                                v-model="form.branding.clinic_name"
                                :class="inputClass"
                                required
                                maxlength="200"
                            />
                            <span
                                v-if="form.errors['branding.clinic_name']"
                                class="mt-1 block text-sm text-red-700"
                            >
                                {{ form.errors['branding.clinic_name'] }}
                            </span>
                        </label>
                        <label class="text-sm font-semibold text-slate-800">
                            Tagline
                            <input
                                v-model="form.branding.tagline"
                                :class="inputClass"
                                maxlength="240"
                            />
                        </label>
                        <label class="text-sm font-semibold text-slate-800">
                            Primary colour
                            <input
                                v-model="form.branding.primary_color"
                                type="color"
                                class="website-theme-input mt-2 block h-14 w-full cursor-pointer rounded-xl border border-slate-300 bg-white p-1.5 shadow-sm transition focus:outline-none"
                            />
                            <span class="mt-1 block text-xs font-normal text-slate-500">
                                Choose the main brand colour.
                            </span>
                        </label>
                        <label class="text-sm font-semibold text-slate-800">
                            Secondary colour
                            <input
                                v-model="form.branding.secondary_color"
                                type="color"
                                class="website-theme-input mt-2 block h-14 w-full cursor-pointer rounded-xl border border-slate-300 bg-white p-1.5 shadow-sm transition focus:outline-none"
                            />
                            <span class="mt-1 block text-xs font-normal text-slate-500">
                                Choose the supporting brand colour.
                            </span>
                        </label>
                        <WebsiteImageUpload
                            v-model="form.branding.logo_reference"
                            class="md:col-span-2"
                            label="Clinic logo"
                            :upload-url="websiteDraft.mediaUploadUrl"
                            :asset-url-template="websiteDraft.assetUrlTemplate"
                            :aspect-ratio="6"
                            :aspect-ratio-options="[
                                { label: 'Wide wordmark', value: 6 },
                                { label: 'Landscape logo', value: 3 },
                                { label: 'Square symbol', value: 1 },
                            ]"
                            :disabled="configurationSaving"
                            @uploaded="synchronizeWebsiteVersion"
                        />
                        <fieldset
                            v-if="form.branding.logo_reference"
                            class="rounded-xl border border-slate-200 p-4 md:col-span-2"
                        >
                            <legend class="px-1 text-sm font-semibold text-slate-800">
                                Logo size
                            </legend>
                            <p class="mb-3 text-xs text-slate-500">
                                Choose how prominently the clinic logo appears in the website
                                header.
                            </p>
                            <div class="grid gap-2 sm:grid-cols-3">
                                <label
                                    v-for="option in [
                                        ['compact', 'Compact'],
                                        ['standard', 'Standard'],
                                        ['large', 'Large'],
                                    ]"
                                    :key="option[0]"
                                    class="website-theme-option flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-3 text-sm font-medium text-slate-800 transition"
                                >
                                    <input
                                        v-model="form.branding.logo_display_size"
                                        type="radio"
                                        name="clinic_owner_logo_display_size"
                                        :value="option[0]"
                                        class="website-theme-control"
                                    />
                                    {{ option[1] }}
                                </label>
                            </div>
                        </fieldset>
                        <label class="text-sm font-semibold text-slate-800">
                            Contact email
                            <input
                                v-model="form.branding.contact_email"
                                :class="inputClass"
                                type="email"
                                required
                            />
                        </label>
                        <label class="text-sm font-semibold text-slate-800">
                            Contact phone
                            <input
                                v-model="form.branding.contact_phone"
                                :class="inputClass"
                                required
                                maxlength="40"
                            />
                        </label>
                        <label class="text-sm font-semibold text-slate-800 md:col-span-2">
                            Address
                            <textarea
                                v-model="form.branding.address"
                                :class="inputClass"
                                rows="3"
                                required
                                maxlength="500"
                            />
                        </label>
                    </div>
                    <fieldset class="mt-6 border-t border-slate-200 pt-5">
                        <legend class="text-sm font-semibold text-slate-800">Social links</legend>
                        <div class="mt-2 grid gap-4 md:grid-cols-2">
                            <label
                                v-for="channel in socialChannels"
                                :key="channel"
                                class="text-sm font-medium capitalize text-slate-700"
                            >
                                {{ channel }}
                                <input
                                    v-model="form.branding.social_links[channel]"
                                    :class="inputClass"
                                    type="text"
                                    inputmode="url"
                                    :placeholder="`${channel}.com/klinikanda`"
                                />
                            </label>
                        </div>
                    </fieldset>
                </template>
                <template #search-sharing>
                    <WebsiteSeoEditor
                        v-model="form.seo"
                        :fallback-title="form.branding.clinic_name"
                        :fallback-description="form.branding.tagline"
                        :input-class="inputClass"
                        :upload-url="websiteDraft.mediaUploadUrl"
                        :asset-url-template="websiteDraft.assetUrlTemplate"
                    />
                </template>
            </ClinicOwnerDraftSections>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-xl font-bold text-slate-950">Visible sections</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Public rendering still omits sections without renderable published evidence.
                </p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <label
                        v-for="section in editableContent.sections"
                        :key="section.key"
                        class="flex items-center gap-3 rounded-lg border border-slate-200 p-3 text-sm font-semibold text-slate-800"
                    >
                        <input
                            v-model="form.sections[section.key]"
                            type="checkbox"
                            class="website-theme-control size-4"
                        />
                        {{ section.label }}
                    </label>
                    <div
                        class="flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50/70 p-3 text-sm text-slate-800"
                    >
                        <input
                            v-model="blogSectionVisible"
                            type="checkbox"
                            class="website-theme-control size-4 shrink-0"
                            :disabled="blogVisibilitySaving"
                            aria-label="Show Blog section on the Website"
                        />
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold">Blog</span>
                            <span class="block text-xs font-normal text-slate-600">
                                {{
                                    blogVisibilitySaving
                                        ? 'Saving visibility…'
                                        : hasBlogVisibilityChanges
                                          ? 'Unsaved visibility change'
                                          : 'Shown when published articles are available'
                                }}
                            </span>
                        </span>
                        <a
                            :href="blogUrl"
                            class="rounded-full bg-white px-2 py-1 text-[11px] font-bold text-emerald-800 ring-1 ring-emerald-200 transition hover:bg-emerald-100 hover:ring-emerald-300"
                            >Manage</a
                        >
                    </div>
                </div>
                <p v-if="blogVisibilityError" class="mt-3 text-sm font-semibold text-red-700">
                    {{ blogVisibilityError }}
                </p>
            </section>

            <div
                v-if="form.hasErrors"
                role="alert"
                class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"
            >
                <p class="font-semibold">Please review the following content and try again.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li v-for="(message, field) in form.errors" :key="field">{{ message }}</li>
                </ul>
            </div>
            <div
                v-if="saved"
                role="status"
                class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"
            >
                Website configuration saved. Publishing remains a separate governed step.
            </div>
            <div
                v-if="configurationRefreshed"
                role="status"
                class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
            >
                A newer Website configuration was detected. Your entries were kept unchanged; review
                them, then save again to apply them to the latest version.
            </div>
            <p class="text-sm leading-6 text-slate-600">
                This action safely saves section content first, followed by Clinic and brand, Search
                and sharing, and visible-section settings.
            </p>
            <button
                type="button"
                :disabled="savingAnything || !hasAnyChanges"
                class="website-theme-primary rounded-lg px-5 py-3 font-semibold transition focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                @click="saveAll"
            >
                {{
                    savingAnything
                        ? 'Saving…'
                        : hasAnyChanges
                          ? 'Save all Website changes'
                          : 'All Website changes saved'
                }}
            </button>
        </div>

        <ContentHealthSummary :health="contentHealth" :sections="contentSections" />
    </DashboardShell>
</template>

<style scoped>
.website-theme-input:focus {
    border-color: var(--website-theme-primary);
    box-shadow: 0 0 0 0.2rem color-mix(in srgb, var(--website-theme-primary) 20%, transparent);
}

.website-theme-primary {
    color: white;
    background-color: var(--website-theme-primary);
    --tw-ring-color: var(--website-theme-primary);
}

.website-theme-primary:hover {
    background-color: var(--website-theme-primary-hover);
}

.website-theme-primary:active {
    background-color: var(--website-theme-primary-active);
}

.website-theme-outline {
    color: var(--website-theme-primary);
    border-color: var(--website-theme-primary);
    --tw-ring-color: var(--website-theme-primary);
}

.website-theme-outline:hover {
    background-color: var(--website-theme-secondary);
}

.website-theme-surface {
    color: var(--website-theme-on-secondary);
    border-color: var(--website-theme-border);
    background-color: var(--website-theme-secondary);
}

.website-theme-text,
.website-theme-link {
    color: var(--website-theme-primary-active);
}

.website-theme-link {
    text-decoration-color: var(--website-theme-primary);
}

.website-theme-divider {
    border-color: var(--website-theme-border);
}

.website-theme-control {
    accent-color: var(--website-theme-primary);
}

.website-theme-option:has(input:checked) {
    border-color: var(--website-theme-primary);
    background-color: var(--website-theme-secondary);
}
</style>

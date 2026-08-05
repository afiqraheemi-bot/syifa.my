<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    ContentHealthSummary,
    ContentSectionSummary,
    createDashboardNavigation,
    DashboardShell,
} from '../../../Shared/Dashboard/index.js';

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
    updateUrl: { type: String, required: true },
});

const navigation = createDashboardNavigation(props.navigation);
const saved = ref(false);
const socialChannels = ['facebook', 'instagram', 'youtube', 'tiktok', 'linkedin'];
const form = useForm({
    version: props.editableContent.version,
    branding: {
        ...props.editableContent.branding,
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

function save() {
    saved.value = false;
    form.patch(props.updateUrl, {
        preserveScroll: true,
        onSuccess: (page) => {
            const current = page.props.editableContent;
            if (current) {
                form.version = current.version;
            }
            saved.value = true;
        },
    });
}

const inputClass =
    'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-950 shadow-sm outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20';
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
        <form class="space-y-8" novalidate @submit.prevent="save">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-xl font-bold text-slate-950">Clinic and brand</h2>
                <p class="mt-1 text-sm text-slate-600">
                    These changes update the current configuration. They do not publish
                    automatically.
                </p>
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
                            class="mt-2 block h-14 w-full cursor-pointer rounded-xl border border-slate-300 bg-white p-1.5 shadow-sm transition focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/20"
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
                            class="mt-2 block h-14 w-full cursor-pointer rounded-xl border border-slate-300 bg-white p-1.5 shadow-sm transition focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/20"
                        />
                        <span class="mt-1 block text-xs font-normal text-slate-500">
                            Choose the supporting brand colour.
                        </span>
                    </label>
                    <fieldset
                        v-if="form.branding.logo_reference"
                        class="rounded-xl border border-slate-200 p-4 md:col-span-2"
                    >
                        <legend class="px-1 text-sm font-semibold text-slate-800">Logo size</legend>
                        <p class="mb-3 text-xs text-slate-500">
                            Choose how prominently the clinic logo appears in the website header.
                        </p>
                        <div class="grid gap-2 sm:grid-cols-3">
                            <label
                                v-for="option in [
                                    ['compact', 'Compact'],
                                    ['standard', 'Standard'],
                                    ['large', 'Large'],
                                ]"
                                :key="option[0]"
                                class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-3 text-sm font-medium text-slate-800 transition has-[:checked]:border-teal-700 has-[:checked]:bg-teal-50"
                            >
                                <input
                                    v-model="form.branding.logo_display_size"
                                    type="radio"
                                    name="clinic_owner_logo_display_size"
                                    :value="option[0]"
                                    class="text-teal-700 focus:ring-teal-600"
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
                <fieldset class="mt-6">
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
                                type="url"
                                inputmode="url"
                                placeholder="https://"
                            />
                        </label>
                    </div>
                </fieldset>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-xl font-bold text-slate-950">Search and sharing</h2>
                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    <label class="text-sm font-semibold text-slate-800">
                        Meta title
                        <input
                            v-model="form.seo.meta_title"
                            :class="inputClass"
                            required
                            maxlength="60"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-800">
                        Meta keywords
                        <input
                            v-model="form.seo.meta_keywords"
                            :class="inputClass"
                            maxlength="255"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-800 md:col-span-2">
                        Meta description
                        <textarea
                            v-model="form.seo.meta_description"
                            :class="inputClass"
                            rows="3"
                            required
                            maxlength="160"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-800">
                        Open Graph title
                        <input
                            v-model="form.seo.open_graph_title"
                            :class="inputClass"
                            required
                            maxlength="60"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-800">
                        Canonical URL
                        <input
                            v-model="form.seo.canonical_url"
                            :class="inputClass"
                            type="url"
                            placeholder="https://"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-800 md:col-span-2">
                        Open Graph description
                        <textarea
                            v-model="form.seo.open_graph_description"
                            :class="inputClass"
                            rows="3"
                            required
                            maxlength="160"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-800">
                        Robots directive
                        <select v-model="form.seo.robots_directive" :class="inputClass">
                            <option value="index,follow">Index, follow</option>
                            <option value="index,nofollow">Index, no follow</option>
                            <option value="noindex,follow">No index, follow</option>
                            <option value="noindex,nofollow">No index, no follow</option>
                        </select>
                    </label>
                    <label
                        class="flex items-center gap-3 self-end rounded-lg border border-slate-200 p-3 text-sm font-semibold text-slate-800"
                    >
                        <input
                            v-model="form.seo.indexing_enabled"
                            type="checkbox"
                            class="size-4 accent-teal-700"
                        />
                        Allow search-engine indexing
                    </label>
                </div>
            </section>

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
                            class="size-4 accent-teal-700"
                        />
                        {{ section.label }}
                    </label>
                </div>
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
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-lg bg-teal-700 px-5 py-3 font-semibold text-white transition hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-700 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
            >
                {{ form.processing ? 'Saving…' : 'Save changes' }}
            </button>
        </form>

        <div class="border-t border-slate-200 pt-8">
            <ContentHealthSummary :health="contentHealth" />
            <section aria-labelledby="content-sections-heading">
                <h2 id="content-sections-heading" class="mb-4 text-xl font-bold text-slate-950">
                    Content sections
                </h2>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <ContentSectionSummary
                        v-for="section in contentSections"
                        :key="section.key"
                        :section="section"
                    />
                </div>
            </section>
        </div>
    </DashboardShell>
</template>

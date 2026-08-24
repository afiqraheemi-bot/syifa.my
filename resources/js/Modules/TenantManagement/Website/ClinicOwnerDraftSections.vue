<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { browserHttpRequest } from '../../../Shared/Authentication/session.js';
import WebsiteImageUpload from '../../../Shared/Website/WebsiteImageUpload.vue';
import SyifaAiAssistant from '../../PlatformAdministration/Onboarding/SyifaAiAssistant.vue';

const props = defineProps({
    websiteDraft: { type: Object, required: true },
    templateId: { type: String, default: '' },
    syifaAi: { type: Object, required: true },
    externalDirty: { type: Boolean, default: false },
    externalSaving: { type: Boolean, default: false },
});
const emit = defineEmits(['state', 'website-version', 'save-all']);
// Serialising through the reactive proxy deliberately reads every nested field.
// That keeps dirty-state computations subscribed to edits inside each section.
const cloneData = (value) => JSON.parse(JSON.stringify(value));
const draft = ref(null);
const lastSavedSections = ref([]);
const loading = ref(true);
const saving = ref(false);
const success = ref('');
const error = ref('');
const inputClass =
    'website-theme-input mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-950 outline-none';
const byType = (type) => draft.value?.sections.find((section) => section.type === type);
const hero = computed(() => byType('HERO'));
const about = computed(() => byType('ABOUT'));
const services = computed(() => byType('SERVICES'));
const doctors = computed(() => byType('DOCTORS'));
const gallery = computed(() => byType('GALLERY'));
const testimonials = computed(() => byType('TESTIMONIALS'));
const faq = computed(() => byType('FAQ'));
const heroAspectRatio = computed(() => (props.templateId === 'SYIFA_AESTHETIC' ? 4 / 5 : 4 / 3));
const ctaDestinations = [
    { value: '/booking', label: 'Appointment booking' },
    { value: '/#services', label: 'Services section' },
    { value: '/#about', label: 'About the clinic' },
    { value: '/#doctors', label: 'Doctors section' },
    { value: '/#contact', label: 'Contact information' },
];
const knownCtaTargets = new Set(ctaDestinations.map((destination) => destination.value));
const legacyCtaTargets = {
    '/services': '/#services',
    '/about': '/#about',
    '/doctors': '/#doctors',
    '/contact': '/#contact',
};
const primaryLabel = computed({
    get: () => hero.value?.primary_cta_label ?? 'Book Appointment',
    set: (value) => {
        hero.value.primary_cta_label = value;
        hero.value.primary_cta_target ??= '/booking';
    },
});
const secondaryEnabled = computed({
    get: () => Boolean(hero.value?.secondary_cta_label || hero.value?.secondary_cta_target),
    set: (enabled) => {
        hero.value.secondary_cta_label = enabled ? 'View services' : null;
        hero.value.secondary_cta_target = enabled ? '/#services' : null;
    },
});
const destinationType = (target, fallback = '/booking') => {
    if (!target) return fallback;
    if (legacyCtaTargets[target]) return legacyCtaTargets[target];
    return knownCtaTargets.has(target) ? target : 'custom';
};
function setCtaDestination(kind, destination) {
    const key = `${kind}_cta_target`;
    hero.value[key] = destination === 'custom' ? 'https://' : destination;
    if (kind === 'primary' && !hero.value.primary_cta_label)
        hero.value.primary_cta_label = 'Book Appointment';
}
const governedSectionTypes = [
    'HERO',
    'ABOUT',
    'SERVICES',
    'DOCTORS',
    'GALLERY',
    'TESTIMONIALS',
    'FAQ',
    'CONTACT',
    'BOOKING_CTA',
];

function isCompleteDraft(value) {
    if (!value || !Number.isInteger(value.version) || !Array.isArray(value.sections)) return false;

    const sectionTypes = value.sections.map((section) => section?.type);

    return (
        sectionTypes.length === governedSectionTypes.length &&
        governedSectionTypes.every(
            (type) => sectionTypes.filter((sectionType) => sectionType === type).length === 1,
        )
    );
}

onMounted(async () => {
    try {
        const response = await browserHttpRequest(props.websiteDraft.loadUrl, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        if (response.ok && isCompleteDraft(response.body?.data)) {
            draft.value = cloneData(response.body.data);
            lastSavedSections.value = normalizeSections(response.body.data.sections);
        } else {
            error.value = response.ok
                ? 'The website draft response was incomplete. No content has been changed.'
                : (response.body?.detail ?? 'The website draft could not be loaded.');
        }
    } catch {
        error.value = 'The website draft could not be loaded.';
    } finally {
        loading.value = false;
    }
});

function optional(value) {
    const normalized = typeof value === 'string' ? value.trim() : '';
    return normalized === '' ? null : normalized;
}

function normalizeSections(sections) {
    const copy = cloneData(sections);
    const section = (type) => copy.find((item) => item.type === type);
    for (const key of [
        'headline',
        'subheadline',
        'primary_cta_label',
        'primary_cta_target',
        'secondary_cta_label',
        'secondary_cta_target',
        'hero_image_asset_id',
    ])
        section('HERO')[key] = optional(section('HERO')[key]);
    for (const key of ['heading', 'description', 'image_asset_id'])
        section('ABOUT')[key] = optional(section('ABOUT')[key]);
    section('DOCTORS').profiles = section('DOCTORS').profiles.map((item) => ({
        ...item,
        name: item.name.trim(),
        professional_title: optional(item.professional_title),
        photo_asset_id: optional(item.photo_asset_id),
    }));
    section('GALLERY').images = section('GALLERY').images.map((item) => ({
        ...item,
        asset_id: item.asset_id.trim(),
        alt_text: item.decorative ? null : optional(item.alt_text),
        caption: optional(item.caption),
    }));
    section('TESTIMONIALS').testimonials = section('TESTIMONIALS').testimonials.map((item) => ({
        ...item,
        quote: item.quote.trim(),
        author_name: item.author_name.trim(),
    }));
    section('FAQ').entries = section('FAQ').entries.map((item) => ({
        ...item,
        question: item.question.trim(),
        answer: item.answer.trim(),
    }));
    return copy;
}

const hasUnsavedChanges = computed(() => {
    if (!draft.value || !isCompleteDraft(draft.value)) return false;

    return (
        JSON.stringify(normalizeSections(draft.value.sections)) !==
        JSON.stringify(lastSavedSections.value)
    );
});

function savedDraftMatchesSubmission(savedDraft, submission) {
    return (
        isCompleteDraft(savedDraft) &&
        savedDraft.version === submission.version + 1 &&
        JSON.stringify(normalizeSections(savedDraft.sections)) ===
            JSON.stringify(submission.sections)
    );
}

async function save() {
    if (saving.value || !draft.value) return false;
    if (!hasUnsavedChanges.value) return true;

    const ctaIsInvalid =
        !primaryLabel.value.trim() ||
        hero.value.primary_cta_target === 'https://' ||
        (secondaryEnabled.value &&
            (!hero.value.secondary_cta_label?.trim() ||
                hero.value.secondary_cta_target === 'https://'));
    if (ctaIsInvalid) {
        error.value =
            'Complete the button text and external link before saving the Homepage section.';
        return false;
    }

    const submission = {
        version: draft.value.version,
        sections: normalizeSections(draft.value.sections),
    };

    saving.value = true;
    success.value = '';
    error.value = '';

    try {
        const response = await browserHttpRequest(props.websiteDraft.updateUrl, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(submission),
        });

        if (response.ok && savedDraftMatchesSubmission(response.body?.data, submission)) {
            draft.value = cloneData(response.body.data);
            lastSavedSections.value = normalizeSections(response.body.data.sections);
            success.value = 'All section changes were saved to the private draft.';
            return true;
        } else if (response.ok) {
            error.value =
                'The save response did not match your changes. Your form has been kept unchanged; please try again.';
        } else {
            error.value =
                response.body?.detail ??
                (response.status === 409
                    ? 'The draft changed in another session. Your form is still intact; refresh only after copying any unsaved text.'
                    : 'The website draft could not be saved. Your form is still intact; check the highlighted content.');
        }
    } catch {
        error.value =
            'The save request could not be completed. Your form is still intact; check your connection and try again.';
    } finally {
        saving.value = false;
    }

    return false;
}

function toggleService(serviceId) {
    const index = services.value.items.findIndex((item) => item.service_id === serviceId);
    if (index >= 0) services.value.items.splice(index, 1);
    else services.value.items.push({ service_id: serviceId, display_order: 1, is_featured: false });
    services.value.items.forEach((item, position) => (item.display_order = position + 1));
}
function createDraftItemId() {
    if (typeof globalThis.crypto?.randomUUID === 'function') return globalThis.crypto.randomUUID();

    const bytes = new Uint8Array(16);
    if (typeof globalThis.crypto?.getRandomValues === 'function') {
        globalThis.crypto.getRandomValues(bytes);
    } else {
        for (let index = 0; index < bytes.length; index += 1) {
            bytes[index] = Math.floor(Math.random() * 256);
        }
    }
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const value = [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('');
    return `${value.slice(0, 8)}-${value.slice(8, 12)}-${value.slice(12, 16)}-${value.slice(16, 20)}-${value.slice(20)}`;
}

function addDoctor() {
    doctors.value.profiles.push({
        id: createDraftItemId(),
        name: '',
        professional_title: '',
        visible: true,
        photo_asset_id: null,
    });
}
function addGalleryImage() {
    gallery.value.images.push({
        id: createDraftItemId(),
        asset_id: '',
        alt_text: '',
        caption: '',
        decorative: false,
    });
}
function addTestimonial() {
    testimonials.value.testimonials.push({
        id: createDraftItemId(),
        quote: '',
        author_name: '',
        featured: true,
    });
}
function addFaq() {
    faq.value.entries.push({ id: createDraftItemId(), question: '', answer: '' });
}

function applySyifaAiSuggestion(suggestion) {
    const target = byType(suggestion.section);
    if (!target || !Object.prototype.hasOwnProperty.call(target, suggestion.field)) return;
    target[suggestion.field] = suggestion.proposed_value;
    success.value = 'Suggestion added to the form. Review it, then save the private draft.';
}

function synchronizeWebsiteVersion(asset) {
    if (Number.isInteger(asset?.website_version)) emit('website-version', asset.website_version);
}

watch(
    [loading, saving, hasUnsavedChanges],
    ([isLoading, isSaving, isDirty]) => {
        emit('state', { loading: isLoading, saving: isSaving, dirty: isDirty });
    },
    { immediate: true },
);

defineExpose({ save });
</script>

<template>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-slate-950">Website pages and sections</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Open a section and make your changes. This save action covers Homepage, About,
                    Services, Doctors, Gallery, Testimonials and FAQ. Publishing remains a separate
                    approval step.
                </p>
            </div>
            <button
                type="button"
                class="website-theme-primary rounded-xl px-5 py-3 font-bold disabled:opacity-60"
                :disabled="
                    saving ||
                    externalSaving ||
                    loading ||
                    !draft ||
                    (!hasUnsavedChanges && !externalDirty)
                "
                @click="externalDirty ? emit('save-all') : save()"
            >
                {{
                    saving || externalSaving
                        ? 'Saving…'
                        : hasUnsavedChanges || externalDirty
                          ? 'Save all Website changes'
                          : 'All changes saved'
                }}
            </button>
        </div>
        <p
            v-if="success"
            class="mt-4 rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800"
        >
            {{ success }}
        </p>
        <p v-if="error" class="mt-4 rounded-lg bg-red-50 p-3 text-sm font-semibold text-red-800">
            {{ error }}
        </p>

        <p v-if="loading" class="mt-6 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">
            Loading website draft…
        </p>
        <div v-else-if="draft" class="mt-6 space-y-4">
            <SyifaAiAssistant
                :endpoint="syifaAi.assistUrl"
                :enabled="syifaAi.enabled"
                :image-assistance-enabled="syifaAi.imageAssistanceEnabled"
                @apply="applySyifaAiSuggestion"
            />
            <details class="rounded-xl border border-slate-200 p-4" open>
                <summary class="cursor-pointer text-lg font-bold">Homepage</summary>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="text-sm font-semibold"
                        >Headline<input v-model="hero.headline" :class="inputClass"
                    /></label>
                    <label class="text-sm font-semibold"
                        >Subheadline<input v-model="hero.subheadline" :class="inputClass"
                    /></label>
                    <fieldset
                        class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 md:col-span-2"
                    >
                        <legend class="px-1 text-base font-bold text-slate-950">Main action</legend>
                        <p class="mt-1 text-sm text-slate-600">
                            The main button should guide patients towards the most important next
                            step.
                        </p>
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <label class="text-sm font-semibold text-slate-800">
                                Button text
                                <input
                                    v-model="primaryLabel"
                                    :class="inputClass"
                                    maxlength="80"
                                    placeholder="Book Appointment"
                                />
                                <span class="mt-1 block text-xs font-normal text-slate-500"
                                    >Use a short action of 2–4 words.</span
                                >
                            </label>
                            <label class="text-sm font-semibold text-slate-800">
                                Opens
                                <select
                                    :value="destinationType(hero.primary_cta_target)"
                                    :class="inputClass"
                                    @change="setCtaDestination('primary', $event.target.value)"
                                >
                                    <option
                                        v-for="destination in ctaDestinations"
                                        :key="destination.value"
                                        :value="destination.value"
                                    >
                                        {{ destination.label }}
                                    </option>
                                    <option value="custom">Custom external link</option>
                                </select>
                            </label>
                            <label
                                v-if="destinationType(hero.primary_cta_target) === 'custom'"
                                class="text-sm font-semibold text-slate-800 md:col-span-2"
                            >
                                External link
                                <input
                                    v-model="hero.primary_cta_target"
                                    :class="inputClass"
                                    type="text"
                                    inputmode="url"
                                    maxlength="2048"
                                    placeholder="example.com"
                                />
                            </label>
                        </div>
                    </fieldset>
                    <fieldset class="rounded-xl border border-slate-200 p-4 md:col-span-2">
                        <legend class="px-1 text-base font-bold text-slate-950">
                            Second action <span class="font-normal text-slate-500">(optional)</span>
                        </legend>
                        <label
                            class="mt-1 flex items-center gap-3 text-sm font-semibold text-slate-800"
                        >
                            <input v-model="secondaryEnabled" type="checkbox" class="size-4" />
                            Show a second button
                        </label>
                        <div v-if="secondaryEnabled" class="mt-4 grid gap-4 md:grid-cols-2">
                            <label class="text-sm font-semibold text-slate-800">
                                Button text
                                <input
                                    v-model="hero.secondary_cta_label"
                                    :class="inputClass"
                                    maxlength="80"
                                    placeholder="View services"
                                />
                            </label>
                            <label class="text-sm font-semibold text-slate-800">
                                Opens
                                <select
                                    :value="
                                        destinationType(hero.secondary_cta_target, '/#services')
                                    "
                                    :class="inputClass"
                                    @change="setCtaDestination('secondary', $event.target.value)"
                                >
                                    <option
                                        v-for="destination in ctaDestinations"
                                        :key="destination.value"
                                        :value="destination.value"
                                    >
                                        {{ destination.label }}
                                    </option>
                                    <option value="custom">Custom external link</option>
                                </select>
                            </label>
                            <label
                                v-if="
                                    destinationType(hero.secondary_cta_target, '/#services') ===
                                    'custom'
                                "
                                class="text-sm font-semibold text-slate-800 md:col-span-2"
                            >
                                External link
                                <input
                                    v-model="hero.secondary_cta_target"
                                    :class="inputClass"
                                    type="text"
                                    inputmode="url"
                                    maxlength="2048"
                                    placeholder="example.com"
                                />
                            </label>
                        </div>
                    </fieldset>
                    <WebsiteImageUpload
                        v-model="hero.hero_image_asset_id"
                        class="md:col-span-2"
                        label="Homepage hero image"
                        :upload-url="websiteDraft.mediaUploadUrl"
                        :asset-url-template="websiteDraft.assetUrlTemplate"
                        :aspect-ratio="heroAspectRatio"
                        :disabled="saving"
                        @uploaded="synchronizeWebsiteVersion"
                    />
                </div>
            </details>
            <details class="rounded-xl border border-slate-200 p-4">
                <summary class="cursor-pointer text-lg font-bold">About</summary>
                <div class="mt-4 space-y-4">
                    <label class="text-sm font-semibold"
                        >Heading<input v-model="about.heading" :class="inputClass"
                    /></label>
                    <label class="text-sm font-semibold"
                        >Description<textarea
                            v-model="about.description"
                            :class="inputClass"
                            rows="5"
                        />
                    </label>
                    <WebsiteImageUpload
                        v-model="about.image_asset_id"
                        label="About image"
                        :upload-url="websiteDraft.mediaUploadUrl"
                        :asset-url-template="websiteDraft.assetUrlTemplate"
                        :disabled="saving"
                        @uploaded="synchronizeWebsiteVersion"
                    />
                </div>
            </details>
            <details class="rounded-xl border border-slate-200 p-4">
                <summary class="cursor-pointer text-lg font-bold">Services</summary>
                <p class="mt-2 text-sm text-slate-600">
                    Only active clinic services can be displayed.
                </p>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    <label
                        v-for="serviceId in websiteDraft.activeServices"
                        :key="serviceId"
                        class="flex items-center gap-3 rounded-lg border p-3 text-sm font-semibold"
                    >
                        <input
                            type="checkbox"
                            :checked="services.items.some((item) => item.service_id === serviceId)"
                            @change="toggleService(serviceId)"
                        />{{ serviceId }}
                    </label>
                </div>
            </details>
            <details class="rounded-xl border border-slate-200 p-4">
                <summary class="cursor-pointer text-lg font-bold">Doctors</summary>
                <div class="mt-4 space-y-3">
                    <div
                        v-for="(doctor, index) in doctors.profiles"
                        :key="doctor.id"
                        class="grid gap-3 rounded-lg bg-slate-50 p-3 md:grid-cols-2"
                    >
                        <input
                            v-model="doctor.name"
                            :class="inputClass"
                            placeholder="Doctor name"
                        />
                        <input
                            v-model="doctor.professional_title"
                            :class="inputClass"
                            placeholder="Professional title"
                        />
                        <WebsiteImageUpload
                            v-model="doctor.photo_asset_id"
                            class="md:col-span-2"
                            :upload-url="websiteDraft.mediaUploadUrl"
                            :asset-url-template="websiteDraft.assetUrlTemplate"
                            :label="`Photo for ${doctor.name || `Doctor ${index + 1}`}`"
                            :aspect-ratio="1"
                            :disabled="saving"
                            @uploaded="synchronizeWebsiteVersion"
                        />
                        <label class="flex items-center gap-2 text-sm"
                            ><input v-model="doctor.visible" type="checkbox" /> Visible</label
                        >
                        <button
                            type="button"
                            class="text-left text-sm font-bold text-red-700"
                            @click="doctors.profiles.splice(index, 1)"
                        >
                            Remove
                        </button>
                    </div>
                    <button type="button" class="website-theme-link font-bold" @click="addDoctor">
                        + Add doctor
                    </button>
                </div>
            </details>
            <details class="rounded-xl border border-slate-200 p-4">
                <summary class="cursor-pointer text-lg font-bold">Gallery</summary>
                <p class="mt-2 text-sm text-slate-600">
                    Upload each image here, then add its accessibility text and optional caption.
                </p>
                <div class="mt-4 space-y-3">
                    <div
                        v-for="(image, index) in gallery.images"
                        :key="image.id"
                        class="grid gap-3 rounded-lg bg-slate-50 p-3 md:grid-cols-2"
                    >
                        <WebsiteImageUpload
                            v-model="image.asset_id"
                            class="md:col-span-2"
                            :upload-url="websiteDraft.mediaUploadUrl"
                            :asset-url-template="websiteDraft.assetUrlTemplate"
                            :label="`Gallery image ${index + 1}`"
                            :disabled="saving"
                            required
                            @uploaded="synchronizeWebsiteVersion"
                        />
                        <input
                            v-model="image.alt_text"
                            :class="inputClass"
                            placeholder="Alternative text"
                        />
                        <input v-model="image.caption" :class="inputClass" placeholder="Caption" />
                        <button
                            type="button"
                            class="text-left text-sm font-bold text-red-700"
                            @click="gallery.images.splice(index, 1)"
                        >
                            Remove image
                        </button>
                    </div>
                    <p v-if="gallery.images.length === 0" class="text-sm text-slate-500">
                        No gallery images added yet.
                    </p>
                    <button
                        type="button"
                        class="website-theme-link font-bold"
                        @click="addGalleryImage"
                    >
                        + Add gallery image
                    </button>
                </div>
            </details>
            <details class="rounded-xl border border-slate-200 p-4">
                <summary class="cursor-pointer text-lg font-bold">Testimonials</summary>
                <div class="mt-4 space-y-3">
                    <div
                        v-for="(item, index) in testimonials.testimonials"
                        :key="item.id"
                        class="grid gap-3 rounded-lg bg-slate-50 p-3 md:grid-cols-2"
                    >
                        <textarea
                            v-model="item.quote"
                            :class="inputClass"
                            placeholder="Testimonial"
                        />
                        <input
                            v-model="item.author_name"
                            :class="inputClass"
                            placeholder="Patient name"
                        />
                        <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <input
                                v-model="item.featured"
                                type="checkbox"
                                class="size-4 rounded border-slate-300"
                            />
                            Display this testimonial on the Website
                        </label>
                        <button
                            type="button"
                            class="text-left text-sm font-bold text-red-700"
                            @click="testimonials.testimonials.splice(index, 1)"
                        >
                            Remove
                        </button>
                    </div>
                    <button
                        type="button"
                        class="website-theme-link font-bold"
                        @click="addTestimonial"
                    >
                        + Add testimonial
                    </button>
                </div>
            </details>
            <details class="rounded-xl border border-slate-200 p-4">
                <summary class="cursor-pointer text-lg font-bold">FAQ</summary>
                <div class="mt-4 space-y-3">
                    <div
                        v-for="(item, index) in faq.entries"
                        :key="item.id"
                        class="grid gap-3 rounded-lg bg-slate-50 p-3 md:grid-cols-2"
                    >
                        <input v-model="item.question" :class="inputClass" placeholder="Question" />
                        <textarea v-model="item.answer" :class="inputClass" placeholder="Answer" />
                        <button
                            type="button"
                            class="text-left text-sm font-bold text-red-700"
                            @click="faq.entries.splice(index, 1)"
                        >
                            Remove
                        </button>
                    </div>
                    <button type="button" class="website-theme-link font-bold" @click="addFaq">
                        + Add FAQ
                    </button>
                </div>
            </details>
            <details class="rounded-xl border border-slate-200 p-4">
                <summary class="cursor-pointer text-lg font-bold">Contact</summary>
                <div class="mt-4">
                    <slot name="contact" />
                </div>
            </details>
            <details class="rounded-xl border border-slate-200 p-4">
                <summary class="cursor-pointer text-lg font-bold">WhatsApp button</summary>
                <div class="mt-4">
                    <slot name="whatsapp" />
                </div>
            </details>
            <details class="rounded-xl border border-slate-200 p-4">
                <summary class="cursor-pointer text-lg font-bold">Search and sharing</summary>
                <div class="mt-4">
                    <slot name="search-sharing" />
                </div>
            </details>
        </div>
    </section>
</template>

<style scoped>
.website-theme-input:focus {
    border-color: var(--website-theme-primary);
    box-shadow: 0 0 0 0.2rem color-mix(in srgb, var(--website-theme-primary) 20%, transparent);
}

.website-theme-primary {
    color: white;
    background-color: var(--website-theme-primary);
}

.website-theme-primary:hover {
    background-color: var(--website-theme-primary-hover);
}

.website-theme-link {
    color: var(--website-theme-primary);
}
</style>

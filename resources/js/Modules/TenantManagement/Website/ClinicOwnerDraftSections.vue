<script setup>
import { computed, onMounted, ref, toRaw } from 'vue';
import { browserHttpRequest } from '../../../Shared/Authentication/session.js';
import WebsiteImageUpload from '../../../Shared/Website/WebsiteImageUpload.vue';

const props = defineProps({
    websiteDraft: { type: Object, required: true },
    templateId: { type: String, default: '' },
});
const cloneData = (value) => structuredClone(toRaw(value));
const draft = ref(null);
const loading = ref(true);
const saving = ref(false);
const success = ref('');
const error = ref('');
const inputClass =
    'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-950 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20';
const byType = (type) => draft.value?.sections.find((section) => section.type === type);
const hero = computed(() => byType('HERO'));
const about = computed(() => byType('ABOUT'));
const services = computed(() => byType('SERVICES'));
const doctors = computed(() => byType('DOCTORS'));
const gallery = computed(() => byType('GALLERY'));
const testimonials = computed(() => byType('TESTIMONIALS'));
const faq = computed(() => byType('FAQ'));
const heroAspectRatio = computed(() => (props.templateId === 'SYIFA_AESTHETIC' ? 4 / 5 : 4 / 3));

onMounted(async () => {
    try {
        const response = await browserHttpRequest(props.websiteDraft.loadUrl, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        if (response.ok) draft.value = cloneData(response.body.data);
        else error.value = response.body?.detail ?? 'The website draft could not be loaded.';
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

function normalize() {
    const copy = cloneData(draft.value.sections);
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

async function save() {
    if (saving.value || !draft.value) return;
    saving.value = true;
    success.value = '';
    error.value = '';
    const response = await browserHttpRequest(props.websiteDraft.updateUrl, {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ version: draft.value.version, sections: normalize() }),
    });
    if (response.ok) {
        draft.value = cloneData(response.body.data);
        success.value = 'Website draft saved. It remains private until review and publication.';
    } else {
        error.value =
            response.body?.detail ??
            (response.status === 409
                ? 'The draft changed in another session. Refresh before saving again.'
                : 'The website draft could not be saved. Check the highlighted content.');
    }
    saving.value = false;
}

function toggleService(serviceId) {
    const index = services.value.items.findIndex((item) => item.service_id === serviceId);
    if (index >= 0) services.value.items.splice(index, 1);
    else services.value.items.push({ service_id: serviceId, display_order: 1, is_featured: false });
    services.value.items.forEach((item, position) => (item.display_order = position + 1));
}
function addDoctor() {
    doctors.value.profiles.push({
        id: crypto.randomUUID(),
        name: '',
        professional_title: '',
        visible: true,
        photo_asset_id: null,
    });
}
function addGalleryImage() {
    gallery.value.images.push({
        id: crypto.randomUUID(),
        asset_id: '',
        alt_text: '',
        caption: '',
        decorative: false,
    });
}
function addTestimonial() {
    testimonials.value.testimonials.push({
        id: crypto.randomUUID(),
        quote: '',
        author_name: '',
        featured: false,
    });
}
function addFaq() {
    faq.value.entries.push({ id: crypto.randomUUID(), question: '', answer: '' });
}
</script>

<template>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-slate-950">Website pages and sections</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Open a section, upload the images it needs and save the private draft once.
                    Publishing remains a separate approval step.
                </p>
            </div>
            <button
                type="button"
                class="rounded-xl bg-teal-700 px-5 py-3 font-bold text-white disabled:opacity-60"
                :disabled="saving || loading || !draft"
                @click="save"
            >
                {{ saving ? 'Saving…' : 'Save draft content' }}
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
            <details class="rounded-xl border border-slate-200 p-4" open>
                <summary class="cursor-pointer text-lg font-bold">Homepage</summary>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="text-sm font-semibold"
                        >Headline<input v-model="hero.headline" :class="inputClass"
                    /></label>
                    <label class="text-sm font-semibold"
                        >Subheadline<input v-model="hero.subheadline" :class="inputClass"
                    /></label>
                    <label class="text-sm font-semibold"
                        >Primary button label<input
                            v-model="hero.primary_cta_label"
                            :class="inputClass"
                    /></label>
                    <label class="text-sm font-semibold"
                        >Primary button target<input
                            v-model="hero.primary_cta_target"
                            :class="inputClass"
                    /></label>
                    <label class="text-sm font-semibold"
                        >Secondary button label<input
                            v-model="hero.secondary_cta_label"
                            :class="inputClass"
                    /></label>
                    <label class="text-sm font-semibold"
                        >Secondary button target<input
                            v-model="hero.secondary_cta_target"
                            :class="inputClass"
                    /></label>
                    <WebsiteImageUpload
                        v-model="hero.hero_image_asset_id"
                        class="md:col-span-2"
                        label="Homepage hero image"
                        :upload-url="websiteDraft.mediaUploadUrl"
                        :asset-url-template="websiteDraft.assetUrlTemplate"
                        :aspect-ratio="heroAspectRatio"
                        :disabled="saving"
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
                    <button type="button" class="font-bold text-teal-700" @click="addDoctor">
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
                    <button type="button" class="font-bold text-teal-700" @click="addGalleryImage">
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
                        <button
                            type="button"
                            class="text-left text-sm font-bold text-red-700"
                            @click="testimonials.testimonials.splice(index, 1)"
                        >
                            Remove
                        </button>
                    </div>
                    <button type="button" class="font-bold text-teal-700" @click="addTestimonial">
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
                    <button type="button" class="font-bold text-teal-700" @click="addFaq">
                        + Add FAQ
                    </button>
                </div>
            </details>
            <div class="rounded-xl border border-slate-200 p-4">
                <h3 class="text-lg font-bold">Contact</h3>
                <p class="mt-1 text-sm text-slate-600">
                    Contact details are edited in “Clinic and brand” above, avoiding duplicate
                    contact records.
                </p>
            </div>
        </div>
    </section>
</template>

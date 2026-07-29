<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { browserHttpRequest } from '../../../Shared/Authentication/session.js';
import {
    createDashboardNavigation,
    createDashboardQuickActions,
    DashboardQuickActions,
    DashboardShell,
} from '../../../Shared/Dashboard/index.js';

const props = defineProps({
    navigation: { type: Array, required: true },
    breadcrumbs: { type: Array, required: true },
    pageTitle: { type: String, required: true },
    pageDescription: { type: String, required: true },
    identityName: { type: String, default: null },
    contextLabel: { type: String, required: true },
    job: { type: Object, required: true },
    websiteSetup: { type: Object, required: true },
    bookingSetup: { type: Object, required: true },
    clinicContact: { type: Object, required: true },
    websiteDraft: { type: Object, required: true },
    taskUpdateUrlTemplate: { type: String, required: true },
});

const navigation = createDashboardNavigation(props.navigation);
const actions = createDashboardQuickActions(props.job.actions);
const saved = ref(false);
const socialChannels = ['facebook', 'instagram', 'youtube', 'tiktok', 'linkedin'];
const form = useForm({
    workspace: 'website_setup',
    version: props.websiteSetup.configuration.version,
    template_id: props.websiteSetup.configuration.template_id,
    branding: {
        ...props.websiteSetup.configuration.branding,
        social_links: Object.fromEntries(
            socialChannels.map((channel) => [
                channel,
                props.websiteSetup.configuration.branding.social_links[channel] ?? '',
            ]),
        ),
    },
    seo: { ...props.websiteSetup.configuration.seo },
    sections: Object.fromEntries(
        props.websiteSetup.configuration.sections.map((section) => [section.key, section.enabled]),
    ),
});
const bookingSaved = ref(false);
const contactSaved = ref(false);
const contactForm = useForm({
    workspace: 'clinic_contact',
    ...props.clinicContact.configuration,
});
const bookingFields = [
    ['patient_name', 'Patient name'],
    ['phone', 'Phone'],
    ['appointment_date', 'Appointment date'],
    ['appointment_time', 'Appointment time'],
    ['service', 'Service'],
    ['email', 'Email'],
    ['notes', 'Notes'],
];
const bookingForm = useForm({
    workspace: 'booking_configuration',
    ...props.bookingSetup.configuration,
    labels: Object.fromEntries(
        bookingFields.map(([key]) => [key, props.bookingSetup.configuration.labels[key] ?? '']),
    ),
});
const enabledBookingFields = computed(() =>
    bookingFields.filter(([key]) => {
        if (key === 'service') return bookingForm.service_selection_enabled;
        if (key === 'email') return bookingForm.email_enabled;
        if (key === 'notes') return bookingForm.notes_enabled;
        return true;
    }),
);
const inputClass =
    'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-950 shadow-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20';
const draft = ref({
    version: props.websiteDraft.draft.version,
    sections: props.websiteDraft.draft.sections.map((section) => ({ ...section })),
});
const initialHero = draft.value.sections.find((section) => section.type === 'HERO');
const initialAbout = draft.value.sections.find((section) => section.type === 'ABOUT');
const initialServices = draft.value.sections.find((section) => section.type === 'SERVICES');
const initialDoctors = draft.value.sections.find((section) => section.type === 'DOCTORS');
const initialGallery = draft.value.sections.find((section) => section.type === 'GALLERY');
const initialTestimonials = draft.value.sections.find((section) => section.type === 'TESTIMONIALS');
const initialFaq = draft.value.sections.find((section) => section.type === 'FAQ');
const initialBookingCta = draft.value.sections.find((section) => section.type === 'BOOKING_CTA');
const heroForm = ref({
    headline: initialHero?.headline ?? '',
    subheadline: initialHero?.subheadline ?? '',
    primary_cta_label: initialHero?.primary_cta_label ?? '',
    primary_cta_target: initialHero?.primary_cta_target ?? '',
    secondary_cta_label: initialHero?.secondary_cta_label ?? '',
    secondary_cta_target: initialHero?.secondary_cta_target ?? '',
    hero_image_asset_id: initialHero?.hero_image_asset_id ?? '',
});
const heroSaving = ref(false);
const heroSuccess = ref('');
const heroError = ref('');
const heroConflict = ref(false);
const aboutForm = ref({
    heading: initialAbout?.heading ?? '',
    description: initialAbout?.description ?? '',
    image_asset_id: initialAbout?.image_asset_id ?? '',
});
const aboutSaving = ref(false);
const aboutSuccess = ref('');
const aboutError = ref('');
const aboutConflict = ref(false);
const activeServices = props.bookingSetup.configuration.active_services;
const activeServiceIds = new Set(activeServices.map((service) => service.id));
const servicesItems = ref(
    (initialServices?.items ?? [])
        .filter((item) => activeServiceIds.has(item.service_id))
        .sort((left, right) => left.display_order - right.display_order),
);
const servicesSaving = ref(false);
const servicesSuccess = ref('');
const servicesError = ref('');
const servicesConflict = ref(false);
const doctorProfiles = ref(
    (initialDoctors?.profiles ?? []).map((profile) => ({
        ...profile,
        professional_title: profile.professional_title ?? '',
        photo_asset_id: profile.photo_asset_id ?? '',
    })),
);
const doctorsSaving = ref(false);
const doctorsSuccess = ref('');
const doctorsError = ref('');
const doctorsConflict = ref(false);
const galleryImages = ref(
    (initialGallery?.images ?? []).map((image) => ({
        ...image,
        alt_text: image.alt_text ?? '',
        caption: image.caption ?? '',
    })),
);
const gallerySaving = ref(false);
const gallerySuccess = ref('');
const galleryError = ref('');
const galleryConflict = ref(false);
const testimonials = ref(
    (initialTestimonials?.testimonials ?? []).map((testimonial) => ({ ...testimonial })),
);
const testimonialsSaving = ref(false);
const testimonialsSuccess = ref('');
const testimonialsError = ref('');
const testimonialsConflict = ref(false);
const faqEntries = ref((initialFaq?.entries ?? []).map((entry) => ({ ...entry })));
const faqSaving = ref(false);
const faqSuccess = ref('');
const faqError = ref('');
const faqConflict = ref(false);
const bookingCtaForm = ref({
    heading: initialBookingCta?.heading ?? '',
    description: initialBookingCta?.description ?? '',
    button_label: initialBookingCta?.button_label ?? '',
});
const bookingCtaSaving = ref(false);
const bookingCtaSuccess = ref('');
const bookingCtaError = ref('');
const bookingCtaConflict = ref(false);
const reviewSubmitting = ref(false);
const reviewSuccess = ref('');
const reviewError = ref('');
const reviewConflict = ref(false);
const reviewCompleted = ref(props.websiteSetup.configuration.lifecycle === 'ready_for_review');
const previewOpening = ref(false);
const previewError = ref('');
const publishSubmitting = ref(false);
const publishSuccess = ref('');
const publishError = ref('');
const publishConflict = ref(false);
const addressForm = ref({
    subdomain: props.websiteSetup.address?.host?.split('.')[0] ?? '',
});
const addressSaving = ref(false);
const addressSuccess = ref('');
const addressError = ref('');
const websiteAddress = ref(props.websiteSetup.address);
const taskBusy = ref(null);
const taskError = ref('');
const taskSuccess = ref('');

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function optionalDraftValue(value) {
    const normalized = typeof value === 'string' ? value.trim() : '';
    return normalized === '' ? null : normalized;
}

async function saveHero() {
    if (heroSaving.value) return;

    heroSaving.value = true;
    heroSuccess.value = '';
    heroError.value = '';
    heroConflict.value = false;

    const sections = draft.value.sections.map((section) =>
        section.type === 'HERO'
            ? {
                  ...section,
                  headline: optionalDraftValue(heroForm.value.headline),
                  subheadline: optionalDraftValue(heroForm.value.subheadline),
                  primary_cta_label: optionalDraftValue(heroForm.value.primary_cta_label),
                  primary_cta_target: optionalDraftValue(heroForm.value.primary_cta_target),
                  secondary_cta_label: optionalDraftValue(heroForm.value.secondary_cta_label),
                  secondary_cta_target: optionalDraftValue(heroForm.value.secondary_cta_target),
                  hero_image_asset_id: optionalDraftValue(heroForm.value.hero_image_asset_id),
              }
            : section,
    );

    try {
        const response = await browserHttpRequest(props.websiteDraft.updateUrl, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ version: draft.value.version, sections }),
        });
        const body = response.body;
        if (!response.ok) {
            heroConflict.value = response.status === 409;
            throw new Error(
                body.detail ??
                    (response.status === 409
                        ? 'The Website draft changed. Refresh before saving again.'
                        : 'The Hero section could not be saved.'),
            );
        }

        draft.value = {
            version: body.data.version,
            sections: body.data.sections.map((section) => ({ ...section })),
        };
        heroSuccess.value = 'Hero section saved successfully. It has not been published.';
    } catch (exception) {
        heroError.value =
            exception instanceof Error ? exception.message : 'The Hero section could not be saved.';
    } finally {
        heroSaving.value = false;
    }
}

async function saveAbout() {
    if (aboutSaving.value) return;

    aboutSaving.value = true;
    aboutSuccess.value = '';
    aboutError.value = '';
    aboutConflict.value = false;

    const sections = draft.value.sections.map((section) =>
        section.type === 'ABOUT'
            ? {
                  ...section,
                  heading: optionalDraftValue(aboutForm.value.heading),
                  description: optionalDraftValue(aboutForm.value.description),
                  image_asset_id: optionalDraftValue(aboutForm.value.image_asset_id),
              }
            : section,
    );

    try {
        const response = await browserHttpRequest(props.websiteDraft.updateUrl, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ version: draft.value.version, sections }),
        });
        const body = response.body;
        if (!response.ok) {
            aboutConflict.value = response.status === 409;
            throw new Error(
                body.detail ??
                    (response.status === 409
                        ? 'The Website draft changed. Refresh before saving again.'
                        : 'The About section could not be saved.'),
            );
        }

        draft.value = {
            version: body.data.version,
            sections: body.data.sections.map((section) => ({ ...section })),
        };
        aboutSuccess.value = 'About section saved successfully. It has not been published.';
    } catch (exception) {
        aboutError.value =
            exception instanceof Error
                ? exception.message
                : 'The About section could not be saved.';
    } finally {
        aboutSaving.value = false;
    }
}

function isServiceSelected(serviceId) {
    return servicesItems.value.some((item) => item.service_id === serviceId);
}

function toggleService(serviceId) {
    if (servicesSaving.value) return;
    if (isServiceSelected(serviceId)) {
        servicesItems.value = servicesItems.value.filter((item) => item.service_id !== serviceId);
        return;
    }
    servicesItems.value.push({
        service_id: serviceId,
        display_order: servicesItems.value.length + 1,
        is_featured: false,
    });
}

function moveService(index, offset) {
    const target = index + offset;
    if (servicesSaving.value || target < 0 || target >= servicesItems.value.length) return;
    const next = [...servicesItems.value];
    [next[index], next[target]] = [next[target], next[index]];
    servicesItems.value = next;
}

function serviceName(serviceId) {
    return activeServices.find((service) => service.id === serviceId)?.name ?? serviceId;
}

function featureService(serviceId) {
    if (servicesSaving.value) return;
    servicesItems.value = servicesItems.value.map((item) => ({
        ...item,
        is_featured: item.service_id === serviceId ? !item.is_featured : false,
    }));
}

async function saveServices() {
    if (servicesSaving.value) return;

    servicesSaving.value = true;
    servicesSuccess.value = '';
    servicesError.value = '';
    servicesConflict.value = false;
    const configuredItems = servicesItems.value.map((item, index) => ({
        service_id: item.service_id,
        display_order: index + 1,
        is_featured: item.is_featured,
    }));
    const sections = draft.value.sections.map((section) =>
        section.type === 'SERVICES' ? { ...section, items: configuredItems } : section,
    );

    try {
        const response = await browserHttpRequest(props.websiteDraft.updateUrl, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ version: draft.value.version, sections }),
        });
        const body = response.body;
        if (!response.ok) {
            servicesConflict.value = response.status === 409;
            throw new Error(
                body.detail ??
                    (response.status === 409
                        ? 'The Website draft changed. Refresh before saving again.'
                        : 'The Services section could not be saved.'),
            );
        }

        draft.value = {
            version: body.data.version,
            sections: body.data.sections.map((section) => ({ ...section })),
        };
        servicesItems.value = [
            ...(draft.value.sections.find((section) => section.type === 'SERVICES')?.items ?? []),
        ];
        servicesSuccess.value = 'Services section saved successfully. It has not been published.';
    } catch (exception) {
        servicesError.value =
            exception instanceof Error
                ? exception.message
                : 'The Services section could not be saved.';
    } finally {
        servicesSaving.value = false;
    }
}

function addDoctor() {
    if (doctorsSaving.value) return;
    doctorProfiles.value.push({
        id: crypto.randomUUID(),
        name: '',
        professional_title: '',
        visible: true,
        photo_asset_id: '',
    });
}

function removeDoctor(index) {
    if (!doctorsSaving.value) doctorProfiles.value.splice(index, 1);
}

function moveDoctor(index, offset) {
    const target = index + offset;
    if (doctorsSaving.value || target < 0 || target >= doctorProfiles.value.length) return;
    const next = [...doctorProfiles.value];
    [next[index], next[target]] = [next[target], next[index]];
    doctorProfiles.value = next;
}

async function saveDoctors() {
    if (doctorsSaving.value) return;

    doctorsSaving.value = true;
    doctorsSuccess.value = '';
    doctorsError.value = '';
    doctorsConflict.value = false;
    const profiles = doctorProfiles.value.map((profile) => ({
        id: profile.id,
        name: profile.name.trim(),
        professional_title: optionalDraftValue(profile.professional_title),
        visible: profile.visible,
        photo_asset_id: optionalDraftValue(profile.photo_asset_id),
    }));
    const sections = draft.value.sections.map((section) =>
        section.type === 'DOCTORS' ? { ...section, profiles } : section,
    );

    try {
        const response = await browserHttpRequest(props.websiteDraft.updateUrl, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ version: draft.value.version, sections }),
        });
        const body = response.body;
        if (!response.ok) {
            doctorsConflict.value = response.status === 409;
            throw new Error(
                body.detail ??
                    (response.status === 409
                        ? 'The Website draft changed. Refresh before saving again.'
                        : 'The Doctors section could not be saved.'),
            );
        }

        draft.value = {
            version: body.data.version,
            sections: body.data.sections.map((section) => ({ ...section })),
        };
        doctorProfiles.value = (
            draft.value.sections.find((section) => section.type === 'DOCTORS')?.profiles ?? []
        ).map((profile) => ({
            ...profile,
            professional_title: profile.professional_title ?? '',
            photo_asset_id: profile.photo_asset_id ?? '',
        }));
        doctorsSuccess.value = 'Doctors section saved successfully. It has not been published.';
    } catch (exception) {
        doctorsError.value =
            exception instanceof Error
                ? exception.message
                : 'The Doctors section could not be saved.';
    } finally {
        doctorsSaving.value = false;
    }
}

function addGalleryImage() {
    if (gallerySaving.value) return;
    galleryImages.value.push({
        id: crypto.randomUUID(),
        asset_id: '',
        alt_text: '',
        caption: '',
        decorative: false,
    });
}

function removeGalleryImage(index) {
    if (!gallerySaving.value) galleryImages.value.splice(index, 1);
}

function moveGalleryImage(index, offset) {
    const target = index + offset;
    if (gallerySaving.value || target < 0 || target >= galleryImages.value.length) return;
    const next = [...galleryImages.value];
    [next[index], next[target]] = [next[target], next[index]];
    galleryImages.value = next;
}

async function saveGallery() {
    if (gallerySaving.value) return;

    gallerySaving.value = true;
    gallerySuccess.value = '';
    galleryError.value = '';
    galleryConflict.value = false;
    const images = galleryImages.value.map((image) => ({
        id: image.id,
        asset_id: image.asset_id.trim(),
        alt_text: image.decorative ? null : optionalDraftValue(image.alt_text),
        caption: optionalDraftValue(image.caption),
        decorative: image.decorative,
    }));
    const sections = draft.value.sections.map((section) =>
        section.type === 'GALLERY' ? { ...section, images } : section,
    );

    try {
        const response = await browserHttpRequest(props.websiteDraft.updateUrl, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ version: draft.value.version, sections }),
        });
        const body = response.body;
        if (!response.ok) {
            galleryConflict.value = response.status === 409;
            throw new Error(
                body.detail ??
                    (response.status === 409
                        ? 'The Website draft changed. Refresh before saving again.'
                        : 'The Gallery section could not be saved.'),
            );
        }

        draft.value = {
            version: body.data.version,
            sections: body.data.sections.map((section) => ({ ...section })),
        };
        galleryImages.value = (
            draft.value.sections.find((section) => section.type === 'GALLERY')?.images ?? []
        ).map((image) => ({
            ...image,
            alt_text: image.alt_text ?? '',
            caption: image.caption ?? '',
        }));
        gallerySuccess.value = 'Gallery section saved successfully. It has not been published.';
    } catch (exception) {
        galleryError.value =
            exception instanceof Error
                ? exception.message
                : 'The Gallery section could not be saved.';
    } finally {
        gallerySaving.value = false;
    }
}

function addTestimonial() {
    if (testimonialsSaving.value) return;
    testimonials.value.push({
        id: crypto.randomUUID(),
        quote: '',
        author_name: '',
        featured: false,
    });
}

function removeTestimonial(index) {
    if (!testimonialsSaving.value) testimonials.value.splice(index, 1);
}

function moveTestimonial(index, offset) {
    const target = index + offset;
    if (testimonialsSaving.value || target < 0 || target >= testimonials.value.length) return;
    const next = [...testimonials.value];
    [next[index], next[target]] = [next[target], next[index]];
    testimonials.value = next;
}

async function saveTestimonials() {
    if (testimonialsSaving.value) return;

    testimonialsSaving.value = true;
    testimonialsSuccess.value = '';
    testimonialsError.value = '';
    testimonialsConflict.value = false;
    const configuredTestimonials = testimonials.value.map((testimonial) => ({
        id: testimonial.id,
        quote: testimonial.quote.trim(),
        author_name: testimonial.author_name.trim(),
        featured: testimonial.featured,
    }));
    const sections = draft.value.sections.map((section) =>
        section.type === 'TESTIMONIALS'
            ? { ...section, testimonials: configuredTestimonials }
            : section,
    );

    try {
        const response = await browserHttpRequest(props.websiteDraft.updateUrl, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ version: draft.value.version, sections }),
        });
        const body = response.body;
        if (!response.ok) {
            testimonialsConflict.value = response.status === 409;
            throw new Error(
                body.detail ??
                    (response.status === 409
                        ? 'The Website draft changed. Refresh before saving again.'
                        : 'The Testimonials section could not be saved.'),
            );
        }

        draft.value = {
            version: body.data.version,
            sections: body.data.sections.map((section) => ({ ...section })),
        };
        testimonials.value = [
            ...(draft.value.sections.find((section) => section.type === 'TESTIMONIALS')
                ?.testimonials ?? []),
        ];
        testimonialsSuccess.value =
            'Testimonials section saved successfully. It has not been published.';
    } catch (exception) {
        testimonialsError.value =
            exception instanceof Error
                ? exception.message
                : 'The Testimonials section could not be saved.';
    } finally {
        testimonialsSaving.value = false;
    }
}

function addFaqEntry() {
    if (faqSaving.value) return;
    faqEntries.value.push({
        id: crypto.randomUUID(),
        question: '',
        answer: '',
    });
}

function removeFaqEntry(index) {
    if (!faqSaving.value) faqEntries.value.splice(index, 1);
}

function moveFaqEntry(index, offset) {
    const target = index + offset;
    if (faqSaving.value || target < 0 || target >= faqEntries.value.length) return;
    const next = [...faqEntries.value];
    [next[index], next[target]] = [next[target], next[index]];
    faqEntries.value = next;
}

async function saveFaq() {
    if (faqSaving.value) return;

    faqSaving.value = true;
    faqSuccess.value = '';
    faqError.value = '';
    faqConflict.value = false;
    const entries = faqEntries.value.map((entry) => ({
        id: entry.id,
        question: entry.question.trim(),
        answer: entry.answer.trim(),
    }));
    const sections = draft.value.sections.map((section) =>
        section.type === 'FAQ' ? { ...section, entries } : section,
    );

    try {
        const response = await browserHttpRequest(props.websiteDraft.updateUrl, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ version: draft.value.version, sections }),
        });
        const body = response.body;
        if (!response.ok) {
            faqConflict.value = response.status === 409;
            throw new Error(
                body.detail ??
                    (response.status === 409
                        ? 'The Website draft changed. Refresh before saving again.'
                        : 'The FAQ section could not be saved.'),
            );
        }

        draft.value = {
            version: body.data.version,
            sections: body.data.sections.map((section) => ({ ...section })),
        };
        faqEntries.value = [
            ...(draft.value.sections.find((section) => section.type === 'FAQ')?.entries ?? []),
        ];
        faqSuccess.value = 'FAQ section saved successfully. It has not been published.';
    } catch (exception) {
        faqError.value =
            exception instanceof Error ? exception.message : 'The FAQ section could not be saved.';
    } finally {
        faqSaving.value = false;
    }
}

async function saveBookingCta() {
    if (bookingCtaSaving.value) return;

    bookingCtaSaving.value = true;
    bookingCtaSuccess.value = '';
    bookingCtaError.value = '';
    bookingCtaConflict.value = false;
    const sections = draft.value.sections.map((section) =>
        section.type === 'BOOKING_CTA'
            ? {
                  ...section,
                  heading: optionalDraftValue(bookingCtaForm.value.heading),
                  description: optionalDraftValue(bookingCtaForm.value.description),
                  button_label: optionalDraftValue(bookingCtaForm.value.button_label),
              }
            : section,
    );

    try {
        const response = await browserHttpRequest(props.websiteDraft.updateUrl, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ version: draft.value.version, sections }),
        });
        const body = response.body;
        if (!response.ok) {
            bookingCtaConflict.value = response.status === 409;
            throw new Error(
                body.detail ??
                    (response.status === 409
                        ? 'The Website draft changed. Refresh before saving again.'
                        : 'The Booking CTA section could not be saved.'),
            );
        }

        draft.value = {
            version: body.data.version,
            sections: body.data.sections.map((section) => ({ ...section })),
        };
        bookingCtaSuccess.value =
            'Booking CTA section saved successfully. It has not been published.';
    } catch (exception) {
        bookingCtaError.value =
            exception instanceof Error
                ? exception.message
                : 'The Booking CTA section could not be saved.';
    } finally {
        bookingCtaSaving.value = false;
    }
}

async function submitForReview() {
    if (reviewSubmitting.value || reviewCompleted.value || !props.websiteSetup.canSubmitForReview)
        return;
    if (
        !window.confirm(
            'Submit this Website for review? Content can continue only through the approved review workflow.',
        )
    ) {
        return;
    }

    reviewSubmitting.value = true;
    reviewSuccess.value = '';
    reviewError.value = '';
    reviewConflict.value = false;

    try {
        const response = await browserHttpRequest(props.websiteSetup.readyForReviewUrl, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                workspace: 'ready_for_review',
                version: props.websiteSetup.configuration.version,
                draft_version: props.websiteDraft.draft.version,
                job_version: props.job.version,
            }),
        });
        const body = response.body;
        if (!response.ok) {
            reviewConflict.value = response.status === 409;
            throw new Error(body.detail ?? body.message ?? 'The Website could not be submitted.');
        }

        reviewSuccess.value = body.message ?? 'Website submitted for review.';
        reviewCompleted.value = true;
        window.setTimeout(() => window.location.reload(), 600);
    } catch (exception) {
        reviewError.value =
            exception instanceof Error
                ? exception.message
                : 'The Website could not be submitted for review.';
    } finally {
        reviewSubmitting.value = false;
    }
}

async function openDraftPreview() {
    if (previewOpening.value) return;
    previewOpening.value = true;
    previewError.value = '';
    const previewWindow = window.open('', '_blank');

    try {
        const response = await browserHttpRequest(props.websiteSetup.previewUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { Accept: 'text/html' },
        });
        if (!response.ok) {
            throw new Error('The Draft Preview could not be opened.');
        }
        if (previewWindow === null) {
            throw new Error('Allow pop-ups for SYIFA.my to open the Draft Preview.');
        }
        previewWindow.location.href = props.websiteSetup.previewUrl;
    } catch (exception) {
        previewWindow?.close();
        previewError.value =
            exception instanceof Error
                ? exception.message
                : 'The Draft Preview could not be opened.';
    } finally {
        previewOpening.value = false;
    }
}

async function publishWebsite() {
    if (publishSubmitting.value || !props.websiteSetup.canPublish) return;
    if (
        !window.confirm(
            'Publish this Website now? A new immutable public version will be created from the current Draft.',
        )
    ) {
        return;
    }

    publishSubmitting.value = true;
    publishSuccess.value = '';
    publishError.value = '';
    publishConflict.value = false;

    try {
        const response = await browserHttpRequest(props.websiteSetup.publishUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                website_version: props.websiteSetup.configuration.version,
                draft_version: draft.value.version,
            }),
        });
        const body = response.body;
        if (!response.ok) {
            publishConflict.value = response.status === 409;
            throw new Error(body.detail ?? body.message ?? 'The Website could not be published.');
        }

        publishSuccess.value = body.message ?? 'Website published successfully.';
        window.setTimeout(() => window.location.reload(), 600);
    } catch (exception) {
        publishError.value =
            exception instanceof Error ? exception.message : 'The Website could not be published.';
    } finally {
        publishSubmitting.value = false;
    }
}

async function reserveWebsiteAddress() {
    if (addressSaving.value || !props.websiteSetup.canReserveAddress) return;
    addressSaving.value = true;
    addressSuccess.value = '';
    addressError.value = '';

    try {
        const response = await browserHttpRequest(props.websiteSetup.addressUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ subdomain: addressForm.value.subdomain }),
        });
        const body = response.body;
        if (!response.ok) {
            throw new Error(
                body.detail ?? body.message ?? 'The Website address could not be saved.',
            );
        }
        websiteAddress.value = body.data;
        addressSuccess.value = body.message ?? 'Website address reserved.';
    } catch (exception) {
        addressError.value =
            exception instanceof Error
                ? exception.message
                : 'The Website address could not be saved.';
    } finally {
        addressSaving.value = false;
    }
}

function saveWebsiteSetup() {
    saved.value = false;
    form.patch(props.websiteSetup.updateUrl, {
        preserveScroll: true,
        onSuccess: (page) => {
            const current = page.props.websiteSetup?.configuration;
            if (current) {
                form.version = current.version;
            }
            saved.value = true;
        },
    });
}

function synchronizeBookingOrder() {
    const enabled = enabledBookingFields.value.map(([key]) => key);
    bookingForm.field_order = [
        ...bookingForm.field_order.filter((field) => enabled.includes(field)),
        ...enabled.filter((field) => !bookingForm.field_order.includes(field)),
    ];
    if (!bookingForm.service_selection_enabled) bookingForm.service_required = false;
    if (!bookingForm.email_enabled) bookingForm.email_required = false;
    if (!bookingForm.notes_enabled) bookingForm.notes_required = false;
}

function moveBookingField(index, offset) {
    const target = index + offset;
    if (target < 0 || target >= bookingForm.field_order.length) return;
    const next = [...bookingForm.field_order];
    [next[index], next[target]] = [next[target], next[index]];
    bookingForm.field_order = next;
}

function bookingFieldLabel(field) {
    return bookingFields.find(([key]) => key === field)?.[1] ?? field;
}

function saveBookingConfiguration() {
    synchronizeBookingOrder();
    bookingSaved.value = false;
    bookingForm.patch(props.bookingSetup.updateUrl, {
        preserveScroll: true,
        onSuccess: (page) => {
            const current = page.props.bookingSetup?.configuration;
            if (current) {
                bookingForm.version = current.version;
                bookingForm.field_order = [...current.field_order];
            }
            bookingSaved.value = true;
        },
    });
}

function saveClinicContact() {
    contactSaved.value = false;
    contactForm.patch(props.clinicContact.updateUrl, {
        preserveScroll: true,
        onSuccess: (page) => {
            const current = page.props.clinicContact?.configuration;
            if (current) {
                contactForm.defaults(current);
                contactForm.version = current.version;
            }
            contactSaved.value = true;
        },
    });
}

async function progressTask(task, operation) {
    if (taskBusy.value || !task.actionable) return;
    const evidence =
        operation === 'complete'
            ? window.prompt('Describe the authoritative completion evidence:')?.trim()
            : null;
    if (operation === 'complete' && !evidence) return;
    if (!window.confirm(`${operation === 'complete' ? 'Complete' : 'Update'} ${task.title}?`)) {
        return;
    }

    taskBusy.value = task.id;
    taskError.value = '';
    taskSuccess.value = '';
    const response = await browserHttpRequest(
        props.taskUpdateUrlTemplate.replace('__TASK_ID__', task.id),
        {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                operation,
                expected_version: props.job.version,
                evidence_reference: evidence,
            }),
        },
    );
    taskBusy.value = null;
    if (!response.ok) {
        taskError.value = response.body?.message ?? 'The Onboarding Task could not be updated.';
        return;
    }
    taskSuccess.value = response.body?.message ?? 'Onboarding Task updated successfully.';
    window.location.reload();
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
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                        Assigned onboarding job
                    </p>
                    <h2 class="mt-1 break-all text-lg font-bold text-slate-950">{{ job.id }}</h2>
                    <p class="mt-2 text-sm text-slate-600">
                        Tenant {{ job.tenantId }} · Website {{ job.websiteId }}
                    </p>
                </div>
                <span
                    class="rounded-full bg-emerald-50 px-3 py-1 text-sm font-bold text-emerald-800"
                >
                    {{ job.statusLabel }}
                </span>
            </div>

            <div class="mt-6" aria-labelledby="job-progress-label">
                <div class="flex items-center justify-between gap-3 text-sm">
                    <h3 id="job-progress-label" class="font-bold text-slate-900">Progress</h3>
                    <span class="font-semibold text-slate-600">{{ job.progress.label }}</span>
                </div>
                <div
                    class="mt-2 h-3 overflow-hidden rounded-full bg-slate-200"
                    role="progressbar"
                    aria-label="Onboarding progress"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    :aria-valuenow="job.progress.value"
                >
                    <div
                        class="h-full rounded-full bg-emerald-600"
                        :style="{ width: `${job.progress.value}%` }"
                    />
                </div>
            </div>
        </section>

        <section
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
            aria-labelledby="onboarding-tasks-title"
        >
            <h2 id="onboarding-tasks-title" class="text-xl font-bold text-slate-950">
                Onboarding tasks
            </h2>
            <p class="mt-1 text-sm text-slate-600">
                Progress only the tasks assigned to the Website Designer. Completion requires
                evidence.
            </p>
            <p v-if="taskError" role="alert" class="mt-4 rounded-lg bg-red-50 p-3 text-red-800">
                {{ taskError }}
            </p>
            <p
                v-if="taskSuccess"
                role="status"
                class="mt-4 rounded-lg bg-emerald-50 p-3 text-emerald-800"
            >
                {{ taskSuccess }}
            </p>
            <div class="mt-5 grid gap-3">
                <article
                    v-for="task in job.tasks"
                    :key="task.id"
                    class="flex flex-col gap-3 rounded-xl border border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h3 class="font-bold text-slate-950">{{ task.title }}</h3>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ task.responsibilityLabel }} · {{ task.statusLabel }}
                        </p>
                    </div>
                    <div v-if="task.actionable" class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="min-h-10 rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-800 disabled:opacity-50"
                            :disabled="taskBusy !== null"
                            @click="progressTask(task, 'start')"
                        >
                            Start
                        </button>
                        <button
                            type="button"
                            class="min-h-10 rounded-lg bg-emerald-700 px-4 text-sm font-semibold text-white disabled:opacity-50"
                            :disabled="taskBusy !== null"
                            @click="progressTask(task, 'complete')"
                        >
                            {{ taskBusy === task.id ? 'Updating…' : 'Complete' }}
                        </button>
                    </div>
                </article>
            </div>
        </section>

        <section aria-labelledby="workflow-stages-title">
            <h2 id="workflow-stages-title" class="text-lg font-bold text-slate-950">
                Operational workflow
            </h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="stage in job.stages"
                    :key="stage.key"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <h3 class="font-bold text-slate-950">{{ stage.label }}</h3>
                    <p
                        :class="[
                            'mt-3 text-sm font-semibold',
                            stage.state === 'current' ? 'text-emerald-700' : 'text-slate-500',
                        ]"
                    >
                        {{ stage.stateLabel }}
                    </p>
                </article>
            </div>
        </section>

        <section
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
            aria-labelledby="website-review-title"
        >
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 id="website-review-title" class="text-xl font-bold text-slate-950">
                        Website review
                    </h2>
                    <p class="mt-1 text-sm text-slate-600">
                        {{
                            reviewCompleted
                                ? 'This Website has been submitted to the approved review workflow.'
                                : 'Submit only after every enabled section is complete and renderable.'
                        }}
                    </p>
                </div>
                <span
                    class="w-fit rounded-full bg-slate-100 px-3 py-1 text-sm font-bold text-slate-700"
                >
                    {{ websiteSetup.configuration.lifecycle_label }}
                </span>
            </div>

            <div
                v-if="reviewError"
                role="alert"
                class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"
            >
                <p class="font-semibold">
                    {{
                        reviewConflict
                            ? 'Website version conflict'
                            : 'Website was not submitted for review'
                    }}
                </p>
                <p class="mt-1">{{ reviewError }}</p>
            </div>
            <div
                v-if="reviewSuccess"
                role="status"
                class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"
            >
                {{ reviewSuccess }}
            </div>
            <div
                v-if="publishError"
                role="alert"
                class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"
            >
                <p class="font-semibold">
                    {{ publishConflict ? 'Publication conflict' : 'Website was not published' }}
                </p>
                <p class="mt-1">{{ publishError }}</p>
            </div>
            <div
                v-if="publishSuccess"
                role="status"
                class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"
            >
                {{ publishSuccess }}
            </div>
            <div class="mt-5 flex flex-wrap gap-3">
                <button
                    type="button"
                    class="min-h-11 rounded-lg border border-emerald-700 px-5 py-3 font-semibold text-emerald-800 transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="previewOpening"
                    @click="openDraftPreview"
                >
                    {{ previewOpening ? 'Opening preview…' : 'Preview Website' }}
                </button>
                <button
                    v-if="websiteSetup.canSubmitForReview && !reviewCompleted"
                    type="button"
                    class="min-h-11 rounded-lg bg-emerald-700 px-5 py-3 font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="reviewSubmitting"
                    @click="submitForReview"
                >
                    {{ reviewSubmitting ? 'Submitting for review…' : 'Submit for review' }}
                </button>
                <button
                    v-if="websiteSetup.canPublish"
                    type="button"
                    class="min-h-11 rounded-lg bg-emerald-700 px-5 py-3 font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="publishSubmitting"
                    @click="publishWebsite"
                >
                    {{ publishSubmitting ? 'Publishing Website…' : 'Publish Website' }}
                </button>
            </div>
            <p
                v-if="previewError"
                role="alert"
                class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"
            >
                {{ previewError }}
            </p>
        </section>

        <section
            id="website-setup"
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
            aria-labelledby="website-setup-title"
        >
            <div class="mb-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="font-bold text-slate-950">Public Website address</h2>
                        <p class="mt-1 text-sm text-slate-600">
                            {{
                                websiteAddress?.host ??
                                'Reserve the clinic’s default SYIFA.my subdomain.'
                            }}
                        </p>
                        <span
                            v-if="websiteAddress"
                            class="mt-2 inline-flex rounded-full bg-white px-3 py-1 text-xs font-bold uppercase tracking-wide text-slate-700"
                        >
                            {{ websiteAddress.active ? 'Live' : 'Preparing' }}
                        </span>
                    </div>
                    <a
                        v-if="websiteAddress?.active"
                        :href="websiteAddress.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex min-h-11 items-center rounded-lg border border-emerald-700 px-4 py-2 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2"
                    >
                        Open Live Website
                    </a>
                </div>
                <form
                    v-if="websiteSetup.canReserveAddress"
                    class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end"
                    @submit.prevent="reserveWebsiteAddress"
                >
                    <label class="flex-1 text-sm font-semibold text-slate-800">
                        SYIFA.my subdomain
                        <span class="mt-1 flex rounded-lg border border-slate-300 bg-white">
                            <input
                                v-model.trim="addressForm.subdomain"
                                required
                                minlength="3"
                                maxlength="63"
                                pattern="[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])?"
                                autocomplete="off"
                                class="min-h-11 min-w-0 flex-1 rounded-l-lg px-3 text-slate-950 outline-none focus:ring-2 focus:ring-emerald-600"
                            />
                            <span
                                class="flex items-center border-l border-slate-300 px-3 text-slate-500"
                            >
                                .{{ websiteSetup.baseDomain }}
                            </span>
                        </span>
                    </label>
                    <button
                        type="submit"
                        :disabled="addressSaving"
                        class="min-h-11 rounded-lg bg-emerald-700 px-5 py-3 font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ addressSaving ? 'Saving address…' : 'Reserve address' }}
                    </button>
                </form>
                <p v-if="addressSuccess" role="status" class="mt-3 text-sm text-emerald-700">
                    {{ addressSuccess }}
                </p>
                <p v-if="addressError" role="alert" class="mt-3 text-sm text-red-700">
                    {{ addressError }}
                </p>
            </div>
            <h2 id="website-setup-title" class="text-xl font-bold text-slate-950">Website setup</h2>
            <p class="mt-1 text-sm text-slate-600">
                Configure the assigned clinic Website. Saving does not publish the Website.
            </p>

            <form class="mt-6 space-y-6" novalidate @submit.prevent="saveWebsiteSetup">
                <fieldset>
                    <legend class="font-bold text-slate-900">Approved template</legend>
                    <label class="mt-4 block text-sm font-semibold text-slate-800">
                        Current template
                        <select v-model="form.template_id" :class="inputClass" required>
                            <option
                                v-for="template in websiteSetup.templateOptions"
                                :key="template.value"
                                :value="template.value"
                            >
                                {{ template.label }}
                            </option>
                        </select>
                    </label>
                    <p class="mt-2 text-sm text-slate-600">
                        Select one of the five governed SYIFA.my templates.
                    </p>
                </fieldset>

                <fieldset>
                    <legend class="font-bold text-slate-900">Clinic and branding</legend>
                    <div class="mt-4 grid gap-5 md:grid-cols-2">
                        <label class="text-sm font-semibold text-slate-800">
                            Clinic name
                            <input
                                v-model="form.branding.clinic_name"
                                :class="inputClass"
                                required
                                maxlength="200"
                            />
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
                                :class="inputClass"
                                pattern="#[0-9A-F]{6}"
                            />
                        </label>
                        <label class="text-sm font-semibold text-slate-800">
                            Secondary colour
                            <input
                                v-model="form.branding.secondary_color"
                                :class="inputClass"
                                pattern="#[0-9A-F]{6}"
                            />
                        </label>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="font-bold text-slate-900">Contact configuration</legend>
                    <div class="mt-4 grid gap-5 md:grid-cols-2">
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
                            Clinic address
                            <textarea
                                v-model="form.branding.address"
                                :class="inputClass"
                                rows="3"
                                required
                                maxlength="500"
                            />
                        </label>
                    </div>
                    <div class="mt-5 grid gap-4 md:grid-cols-2">
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

                <fieldset>
                    <legend class="font-bold text-slate-900">SEO configuration</legend>
                    <p class="mt-1 text-sm text-slate-600">
                        Configure existing search and sharing metadata. Saving does not publish the
                        Website.
                    </p>
                    <div class="mt-4 grid gap-5 md:grid-cols-2">
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
                            Keywords
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
                            Canonical URL
                            <input
                                v-model="form.seo.canonical_url"
                                :class="inputClass"
                                type="url"
                                inputmode="url"
                                placeholder="https://"
                            />
                        </label>
                        <label class="text-sm font-semibold text-slate-800">
                            Robots directive
                            <select
                                v-model="form.seo.robots_directive"
                                :class="inputClass"
                                required
                            >
                                <option value="index,follow">Index, follow</option>
                                <option value="index,nofollow">Index, no follow</option>
                                <option value="noindex,follow">No index, follow</option>
                                <option value="noindex,nofollow">No index, no follow</option>
                            </select>
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
                        <label
                            class="flex items-center gap-3 rounded-lg border border-slate-200 p-3 text-sm font-semibold text-slate-800 md:col-span-2"
                        >
                            <input
                                v-model="form.seo.indexing_enabled"
                                type="checkbox"
                                class="size-4 accent-emerald-700"
                            />
                            Allow search-engine indexing
                        </label>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="font-bold text-slate-900">Section visibility</legend>
                    <p class="mt-1 text-sm text-slate-600">
                        Choose which Website sections are enabled. Sections without publishable
                        content remain omitted from the public Website.
                    </p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <label
                            v-for="section in websiteSetup.configuration.sections"
                            :key="section.key"
                            class="flex min-h-12 items-center justify-between gap-4 rounded-lg border border-slate-200 p-3 text-sm font-semibold text-slate-800"
                        >
                            <span>{{ section.label }}</span>
                            <span class="flex items-center gap-2">
                                <span class="text-xs text-slate-500">
                                    {{ form.sections[section.key] ? 'Enabled' : 'Disabled' }}
                                </span>
                                <input
                                    v-model="form.sections[section.key]"
                                    type="checkbox"
                                    class="size-4 accent-emerald-700"
                                    :disabled="form.processing"
                                />
                            </span>
                        </label>
                    </div>
                </fieldset>

                <div
                    v-if="form.hasErrors"
                    role="alert"
                    class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"
                >
                    <p class="font-semibold">Review the Website setup and try again.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li v-for="(message, field) in form.errors" :key="field">
                            {{ message }}
                        </li>
                    </ul>
                </div>
                <div
                    v-if="saved"
                    role="status"
                    class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"
                >
                    Website setup saved successfully.
                </div>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="min-h-11 rounded-lg bg-emerald-700 px-5 py-3 font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ form.processing ? 'Saving…' : 'Save Website setup' }}
                </button>
            </form>
        </section>

        <section
            id="hero-editor"
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
            aria-labelledby="hero-editor-title"
        >
            <h2 id="hero-editor-title" class="text-xl font-bold text-slate-950">Hero section</h2>
            <p class="mt-1 text-sm text-slate-600">
                Edit the opening message and calls to action. Saving updates the draft only.
            </p>

            <form class="mt-6 space-y-6" novalidate @submit.prevent="saveHero">
                <div class="grid gap-5 md:grid-cols-2">
                    <label class="text-sm font-semibold text-slate-800 md:col-span-2">
                        Headline
                        <input
                            v-model="heroForm.headline"
                            :class="inputClass"
                            maxlength="160"
                            :disabled="heroSaving"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-800 md:col-span-2">
                        Subheadline
                        <textarea
                            v-model="heroForm.subheadline"
                            :class="inputClass"
                            rows="3"
                            maxlength="500"
                            :disabled="heroSaving"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-800">
                        Primary CTA label
                        <input
                            v-model="heroForm.primary_cta_label"
                            :class="inputClass"
                            maxlength="80"
                            :disabled="heroSaving"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-800">
                        Primary CTA target
                        <input
                            v-model="heroForm.primary_cta_target"
                            :class="inputClass"
                            maxlength="2048"
                            placeholder="/booking or https://"
                            :disabled="heroSaving"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-800">
                        Secondary CTA label
                        <input
                            v-model="heroForm.secondary_cta_label"
                            :class="inputClass"
                            maxlength="80"
                            :disabled="heroSaving"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-800">
                        Secondary CTA target
                        <input
                            v-model="heroForm.secondary_cta_target"
                            :class="inputClass"
                            maxlength="2048"
                            placeholder="/about or https://"
                            :disabled="heroSaving"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-800 md:col-span-2">
                        Existing Hero image asset ID
                        <input
                            v-model="heroForm.hero_image_asset_id"
                            :class="inputClass"
                            inputmode="text"
                            placeholder="UUID of an existing Website asset"
                            :disabled="heroSaving"
                        />
                        <span class="mt-2 block font-normal text-slate-600">
                            Only an asset already owned by this Website can be referenced.
                        </span>
                    </label>
                </div>

                <div
                    v-if="heroError"
                    role="alert"
                    class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"
                >
                    <p class="font-semibold">
                        {{ heroConflict ? 'Draft version conflict' : 'Hero section was not saved' }}
                    </p>
                    <p class="mt-1">{{ heroError }}</p>
                </div>
                <div
                    v-if="heroSuccess"
                    role="status"
                    class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"
                >
                    {{ heroSuccess }}
                </div>
                <button
                    type="submit"
                    :disabled="heroSaving"
                    class="min-h-11 rounded-lg bg-emerald-700 px-5 py-3 font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ heroSaving ? 'Saving Hero…' : 'Save Hero section' }}
                </button>
            </form>
        </section>

        <section
            id="about-editor"
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
            aria-labelledby="about-editor-title"
        >
            <h2 id="about-editor-title" class="text-xl font-bold text-slate-950">About section</h2>
            <p class="mt-1 text-sm text-slate-600">
                Maintain the clinic introduction in the Website draft. Saving does not publish it.
            </p>

            <form class="mt-6 space-y-6" novalidate @submit.prevent="saveAbout">
                <div class="grid gap-5 md:grid-cols-2">
                    <label class="text-sm font-semibold text-slate-800 md:col-span-2">
                        Heading
                        <input
                            v-model="aboutForm.heading"
                            :class="inputClass"
                            maxlength="160"
                            :disabled="aboutSaving"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-800 md:col-span-2">
                        Description
                        <textarea
                            v-model="aboutForm.description"
                            :class="inputClass"
                            rows="6"
                            maxlength="5000"
                            :disabled="aboutSaving"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-800 md:col-span-2">
                        Existing About image asset ID
                        <input
                            v-model="aboutForm.image_asset_id"
                            :class="inputClass"
                            inputmode="text"
                            placeholder="UUID of an existing Website asset"
                            :disabled="aboutSaving"
                        />
                        <span class="mt-2 block font-normal text-slate-600">
                            Only an asset already owned by this Website can be referenced.
                        </span>
                    </label>
                </div>

                <div
                    v-if="aboutError"
                    role="alert"
                    class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"
                >
                    <p class="font-semibold">
                        {{
                            aboutConflict ? 'Draft version conflict' : 'About section was not saved'
                        }}
                    </p>
                    <p class="mt-1">{{ aboutError }}</p>
                </div>
                <div
                    v-if="aboutSuccess"
                    role="status"
                    class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"
                >
                    {{ aboutSuccess }}
                </div>
                <button
                    type="submit"
                    :disabled="aboutSaving"
                    class="min-h-11 rounded-lg bg-emerald-700 px-5 py-3 font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ aboutSaving ? 'Saving About…' : 'Save About section' }}
                </button>
            </form>
        </section>

        <section
            id="services-editor"
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
            aria-labelledby="services-editor-title"
        >
            <h2 id="services-editor-title" class="text-xl font-bold text-slate-950">
                Services section
            </h2>
            <p class="mt-1 text-sm text-slate-600">
                Choose and order active clinic Services for the Website draft. Service records are
                managed by Booking.
            </p>

            <form class="mt-6 space-y-6" novalidate @submit.prevent="saveServices">
                <fieldset>
                    <legend class="font-bold text-slate-900">Active tenant Services</legend>
                    <p class="mt-1 text-sm text-slate-600">
                        Only existing active Services can be included. Names cannot be edited here.
                    </p>
                    <div v-if="activeServices.length" class="mt-4 grid gap-3 sm:grid-cols-2">
                        <label
                            v-for="service in activeServices"
                            :key="service.id"
                            class="flex min-h-12 items-center gap-3 rounded-lg border border-slate-200 p-3 text-sm font-semibold text-slate-800"
                        >
                            <input
                                type="checkbox"
                                class="size-4 accent-emerald-700"
                                :checked="isServiceSelected(service.id)"
                                :disabled="servicesSaving"
                                @change="toggleService(service.id)"
                            />
                            {{ service.name }}
                        </label>
                    </div>
                    <p
                        v-else
                        class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600"
                    >
                        No active Services are available for this clinic.
                    </p>
                </fieldset>

                <fieldset v-if="servicesItems.length">
                    <legend class="font-bold text-slate-900">Display order</legend>
                    <div class="mt-4 space-y-3">
                        <article
                            v-for="(item, index) in servicesItems"
                            :key="item.service_id"
                            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 p-3"
                        >
                            <div>
                                <p class="font-semibold text-slate-900">
                                    {{ index + 1 }}. {{ serviceName(item.service_id) }}
                                </p>
                                <button
                                    type="button"
                                    class="mt-1 text-sm font-semibold text-emerald-700 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2"
                                    :disabled="servicesSaving"
                                    @click="featureService(item.service_id)"
                                >
                                    {{
                                        item.is_featured
                                            ? 'Remove featured status'
                                            : 'Mark featured'
                                    }}
                                </button>
                            </div>
                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    class="min-h-10 rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 disabled:opacity-40"
                                    :disabled="servicesSaving || index === 0"
                                    :aria-label="`Move ${serviceName(item.service_id)} up`"
                                    @click="moveService(index, -1)"
                                >
                                    Up
                                </button>
                                <button
                                    type="button"
                                    class="min-h-10 rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 disabled:opacity-40"
                                    :disabled="servicesSaving || index === servicesItems.length - 1"
                                    :aria-label="`Move ${serviceName(item.service_id)} down`"
                                    @click="moveService(index, 1)"
                                >
                                    Down
                                </button>
                            </div>
                        </article>
                    </div>
                </fieldset>

                <div
                    v-if="servicesError"
                    role="alert"
                    class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"
                >
                    <p class="font-semibold">
                        {{
                            servicesConflict
                                ? 'Draft version conflict'
                                : 'Services section was not saved'
                        }}
                    </p>
                    <p class="mt-1">{{ servicesError }}</p>
                </div>
                <div
                    v-if="servicesSuccess"
                    role="status"
                    class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"
                >
                    {{ servicesSuccess }}
                </div>
                <button
                    type="submit"
                    :disabled="servicesSaving"
                    class="min-h-11 rounded-lg bg-emerald-700 px-5 py-3 font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ servicesSaving ? 'Saving Services…' : 'Save Services section' }}
                </button>
            </form>
        </section>

        <section
            id="doctors-editor"
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
            aria-labelledby="doctors-editor-title"
        >
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 id="doctors-editor-title" class="text-xl font-bold text-slate-950">
                        Doctors section
                    </h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Maintain Website presentation profiles only. This does not manage scheduling
                        or clinical credentials.
                    </p>
                </div>
                <button
                    type="button"
                    :disabled="doctorsSaving"
                    class="min-h-11 rounded-lg border border-emerald-700 px-4 py-2 font-semibold text-emerald-700 transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 disabled:opacity-60"
                    @click="addDoctor"
                >
                    Add Doctor
                </button>
            </div>

            <form class="mt-6 space-y-6" novalidate @submit.prevent="saveDoctors">
                <div v-if="doctorProfiles.length" class="space-y-5">
                    <fieldset
                        v-for="(profile, index) in doctorProfiles"
                        :key="profile.id"
                        class="rounded-xl border border-slate-200 p-4 sm:p-5"
                    >
                        <legend class="px-2 font-bold text-slate-900">
                            Doctor {{ index + 1 }}
                        </legend>
                        <div class="grid gap-5 md:grid-cols-2">
                            <label class="text-sm font-semibold text-slate-800">
                                Name
                                <input
                                    v-model="profile.name"
                                    :class="inputClass"
                                    maxlength="160"
                                    required
                                    :disabled="doctorsSaving"
                                />
                            </label>
                            <label class="text-sm font-semibold text-slate-800">
                                Professional title
                                <input
                                    v-model="profile.professional_title"
                                    :class="inputClass"
                                    maxlength="160"
                                    :disabled="doctorsSaving"
                                />
                            </label>
                            <label class="text-sm font-semibold text-slate-800 md:col-span-2">
                                Existing photo asset ID
                                <input
                                    v-model="profile.photo_asset_id"
                                    :class="inputClass"
                                    placeholder="UUID of an existing Website asset"
                                    :disabled="doctorsSaving"
                                />
                                <span class="mt-2 block font-normal text-slate-600">
                                    Only an asset already owned by this Website can be referenced.
                                </span>
                            </label>
                            <label
                                class="flex items-center gap-3 rounded-lg border border-slate-200 p-3 text-sm font-semibold text-slate-800 md:col-span-2"
                            >
                                <input
                                    v-model="profile.visible"
                                    type="checkbox"
                                    class="size-4 accent-emerald-700"
                                    :disabled="doctorsSaving"
                                />
                                Visible on the published Website after a future publish
                            </label>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="min-h-10 rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 disabled:opacity-40"
                                :disabled="doctorsSaving || index === 0"
                                @click="moveDoctor(index, -1)"
                            >
                                Move up
                            </button>
                            <button
                                type="button"
                                class="min-h-10 rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 disabled:opacity-40"
                                :disabled="doctorsSaving || index === doctorProfiles.length - 1"
                                @click="moveDoctor(index, 1)"
                            >
                                Move down
                            </button>
                            <button
                                type="button"
                                class="min-h-10 rounded-lg border border-red-300 px-3 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-40"
                                :disabled="doctorsSaving"
                                @click="removeDoctor(index)"
                            >
                                Remove
                            </button>
                        </div>
                    </fieldset>
                </div>
                <p
                    v-else
                    class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600"
                >
                    No Doctor presentation profiles have been added.
                </p>

                <div
                    v-if="doctorsError"
                    role="alert"
                    class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"
                >
                    <p class="font-semibold">
                        {{
                            doctorsConflict
                                ? 'Draft version conflict'
                                : 'Doctors section was not saved'
                        }}
                    </p>
                    <p class="mt-1">{{ doctorsError }}</p>
                </div>
                <div
                    v-if="doctorsSuccess"
                    role="status"
                    class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"
                >
                    {{ doctorsSuccess }}
                </div>
                <button
                    type="submit"
                    :disabled="doctorsSaving"
                    class="min-h-11 rounded-lg bg-emerald-700 px-5 py-3 font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ doctorsSaving ? 'Saving Doctors…' : 'Save Doctors section' }}
                </button>
            </form>
        </section>

        <section
            id="testimonials-editor"
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
            aria-labelledby="testimonials-editor-title"
        >
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 id="testimonials-editor-title" class="text-xl font-bold text-slate-950">
                        Testimonials section
                    </h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Maintain manual Website testimonials. No external review provider is
                        connected.
                    </p>
                </div>
                <button
                    type="button"
                    :disabled="testimonialsSaving"
                    class="min-h-11 rounded-lg border border-emerald-700 px-4 py-2 font-semibold text-emerald-700 transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 disabled:opacity-60"
                    @click="addTestimonial"
                >
                    Add Testimonial
                </button>
            </div>

            <form class="mt-6 space-y-6" novalidate @submit.prevent="saveTestimonials">
                <div v-if="testimonials.length" class="space-y-5">
                    <fieldset
                        v-for="(testimonial, index) in testimonials"
                        :key="testimonial.id"
                        class="rounded-xl border border-slate-200 p-4 sm:p-5"
                    >
                        <legend class="px-2 font-bold text-slate-900">
                            Testimonial {{ index + 1 }}
                        </legend>
                        <div class="grid gap-5 md:grid-cols-2">
                            <label class="text-sm font-semibold text-slate-800 md:col-span-2">
                                Quote
                                <textarea
                                    v-model="testimonial.quote"
                                    :class="inputClass"
                                    rows="5"
                                    maxlength="2000"
                                    required
                                    :disabled="testimonialsSaving"
                                />
                            </label>
                            <label class="text-sm font-semibold text-slate-800 md:col-span-2">
                                Author name
                                <input
                                    v-model="testimonial.author_name"
                                    :class="inputClass"
                                    maxlength="160"
                                    required
                                    :disabled="testimonialsSaving"
                                />
                            </label>
                            <label
                                class="flex items-center gap-3 rounded-lg border border-slate-200 p-3 text-sm font-semibold text-slate-800 md:col-span-2"
                            >
                                <input
                                    v-model="testimonial.featured"
                                    type="checkbox"
                                    class="size-4 accent-emerald-700"
                                    :disabled="testimonialsSaving"
                                />
                                Featured and eligible to render after a future publish
                            </label>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="min-h-10 rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 disabled:opacity-40"
                                :disabled="testimonialsSaving || index === 0"
                                @click="moveTestimonial(index, -1)"
                            >
                                Move up
                            </button>
                            <button
                                type="button"
                                class="min-h-10 rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 disabled:opacity-40"
                                :disabled="testimonialsSaving || index === testimonials.length - 1"
                                @click="moveTestimonial(index, 1)"
                            >
                                Move down
                            </button>
                            <button
                                type="button"
                                class="min-h-10 rounded-lg border border-red-300 px-3 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-40"
                                :disabled="testimonialsSaving"
                                @click="removeTestimonial(index)"
                            >
                                Remove
                            </button>
                        </div>
                    </fieldset>
                </div>
                <p
                    v-else
                    class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600"
                >
                    No manual Testimonials have been added.
                </p>

                <div
                    v-if="testimonialsError"
                    role="alert"
                    class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"
                >
                    <p class="font-semibold">
                        {{
                            testimonialsConflict
                                ? 'Draft version conflict'
                                : 'Testimonials section was not saved'
                        }}
                    </p>
                    <p class="mt-1">{{ testimonialsError }}</p>
                </div>
                <div
                    v-if="testimonialsSuccess"
                    role="status"
                    class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"
                >
                    {{ testimonialsSuccess }}
                </div>
                <button
                    type="submit"
                    :disabled="testimonialsSaving"
                    class="min-h-11 rounded-lg bg-emerald-700 px-5 py-3 font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ testimonialsSaving ? 'Saving Testimonials…' : 'Save Testimonials section' }}
                </button>
            </form>
        </section>

        <section
            id="gallery-editor"
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
            aria-labelledby="gallery-editor-title"
        >
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 id="gallery-editor-title" class="text-xl font-bold text-slate-950">
                        Gallery section
                    </h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Arrange existing Website-owned assets. Uploading is not available here.
                    </p>
                </div>
                <button
                    type="button"
                    :disabled="gallerySaving"
                    class="min-h-11 rounded-lg border border-emerald-700 px-4 py-2 font-semibold text-emerald-700 transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 disabled:opacity-60"
                    @click="addGalleryImage"
                >
                    Add Gallery image
                </button>
            </div>

            <form class="mt-6 space-y-6" novalidate @submit.prevent="saveGallery">
                <div v-if="galleryImages.length" class="space-y-5">
                    <fieldset
                        v-for="(image, index) in galleryImages"
                        :key="image.id"
                        class="rounded-xl border border-slate-200 p-4 sm:p-5"
                    >
                        <legend class="px-2 font-bold text-slate-900">
                            Gallery image {{ index + 1 }}
                        </legend>
                        <div class="grid gap-5 md:grid-cols-2">
                            <label class="text-sm font-semibold text-slate-800 md:col-span-2">
                                Existing asset ID
                                <input
                                    v-model="image.asset_id"
                                    :class="inputClass"
                                    required
                                    placeholder="UUID of an existing Website asset"
                                    :disabled="gallerySaving"
                                />
                            </label>
                            <label class="text-sm font-semibold text-slate-800">
                                Alternative text
                                <textarea
                                    v-model="image.alt_text"
                                    :class="inputClass"
                                    rows="3"
                                    maxlength="500"
                                    :disabled="gallerySaving || image.decorative"
                                />
                            </label>
                            <label class="text-sm font-semibold text-slate-800">
                                Caption
                                <textarea
                                    v-model="image.caption"
                                    :class="inputClass"
                                    rows="3"
                                    maxlength="1000"
                                    :disabled="gallerySaving"
                                />
                            </label>
                            <label
                                class="flex items-center gap-3 rounded-lg border border-slate-200 p-3 text-sm font-semibold text-slate-800 md:col-span-2"
                            >
                                <input
                                    v-model="image.decorative"
                                    type="checkbox"
                                    class="size-4 accent-emerald-700"
                                    :disabled="gallerySaving"
                                />
                                Decorative image with no alternative text
                            </label>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="min-h-10 rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 disabled:opacity-40"
                                :disabled="gallerySaving || index === 0"
                                @click="moveGalleryImage(index, -1)"
                            >
                                Move up
                            </button>
                            <button
                                type="button"
                                class="min-h-10 rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 disabled:opacity-40"
                                :disabled="gallerySaving || index === galleryImages.length - 1"
                                @click="moveGalleryImage(index, 1)"
                            >
                                Move down
                            </button>
                            <button
                                type="button"
                                class="min-h-10 rounded-lg border border-red-300 px-3 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-40"
                                :disabled="gallerySaving"
                                @click="removeGalleryImage(index)"
                            >
                                Remove
                            </button>
                        </div>
                    </fieldset>
                </div>
                <p
                    v-else
                    class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600"
                >
                    No Gallery images have been configured.
                </p>

                <div
                    v-if="galleryError"
                    role="alert"
                    class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"
                >
                    <p class="font-semibold">
                        {{
                            galleryConflict
                                ? 'Draft version conflict'
                                : 'Gallery section was not saved'
                        }}
                    </p>
                    <p class="mt-1">{{ galleryError }}</p>
                </div>
                <div
                    v-if="gallerySuccess"
                    role="status"
                    class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"
                >
                    {{ gallerySuccess }}
                </div>
                <button
                    type="submit"
                    :disabled="gallerySaving"
                    class="min-h-11 rounded-lg bg-emerald-700 px-5 py-3 font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ gallerySaving ? 'Saving Gallery…' : 'Save Gallery section' }}
                </button>
            </form>
        </section>

        <section
            id="faq-editor"
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
            aria-labelledby="faq-editor-title"
        >
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 id="faq-editor-title" class="text-xl font-bold text-slate-950">
                        FAQ section
                    </h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Maintain plain-text questions and answers in the Website draft.
                    </p>
                </div>
                <button
                    type="button"
                    :disabled="faqSaving"
                    class="min-h-11 rounded-lg border border-emerald-700 px-4 py-2 font-semibold text-emerald-700 transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 disabled:opacity-60"
                    @click="addFaqEntry"
                >
                    Add FAQ
                </button>
            </div>

            <form class="mt-6 space-y-6" novalidate @submit.prevent="saveFaq">
                <div v-if="faqEntries.length" class="space-y-5">
                    <fieldset
                        v-for="(entry, index) in faqEntries"
                        :key="entry.id"
                        class="rounded-xl border border-slate-200 p-4 sm:p-5"
                    >
                        <legend class="px-2 font-bold text-slate-900">FAQ {{ index + 1 }}</legend>
                        <div class="grid gap-5">
                            <label class="text-sm font-semibold text-slate-800">
                                Question
                                <textarea
                                    v-model="entry.question"
                                    :class="inputClass"
                                    rows="2"
                                    maxlength="500"
                                    required
                                    :disabled="faqSaving"
                                />
                            </label>
                            <label class="text-sm font-semibold text-slate-800">
                                Answer
                                <textarea
                                    v-model="entry.answer"
                                    :class="inputClass"
                                    rows="6"
                                    maxlength="5000"
                                    required
                                    :disabled="faqSaving"
                                />
                            </label>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="min-h-10 rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 disabled:opacity-40"
                                :disabled="faqSaving || index === 0"
                                @click="moveFaqEntry(index, -1)"
                            >
                                Move up
                            </button>
                            <button
                                type="button"
                                class="min-h-10 rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 disabled:opacity-40"
                                :disabled="faqSaving || index === faqEntries.length - 1"
                                @click="moveFaqEntry(index, 1)"
                            >
                                Move down
                            </button>
                            <button
                                type="button"
                                class="min-h-10 rounded-lg border border-red-300 px-3 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-40"
                                :disabled="faqSaving"
                                @click="removeFaqEntry(index)"
                            >
                                Remove
                            </button>
                        </div>
                    </fieldset>
                </div>
                <p
                    v-else
                    class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600"
                >
                    No FAQ entries have been added.
                </p>

                <div
                    v-if="faqError"
                    role="alert"
                    class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"
                >
                    <p class="font-semibold">
                        {{ faqConflict ? 'Draft version conflict' : 'FAQ section was not saved' }}
                    </p>
                    <p class="mt-1">{{ faqError }}</p>
                </div>
                <div
                    v-if="faqSuccess"
                    role="status"
                    class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"
                >
                    {{ faqSuccess }}
                </div>
                <button
                    type="submit"
                    :disabled="faqSaving"
                    class="min-h-11 rounded-lg bg-emerald-700 px-5 py-3 font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ faqSaving ? 'Saving FAQ…' : 'Save FAQ section' }}
                </button>
            </form>
        </section>

        <section
            id="booking-cta-editor"
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
            aria-labelledby="booking-cta-editor-title"
        >
            <h2 id="booking-cta-editor-title" class="text-xl font-bold text-slate-950">
                Booking CTA section
            </h2>
            <p class="mt-1 text-sm text-slate-600">
                Maintain the Website booking call to action. This does not change Booking
                configuration.
            </p>

            <form class="mt-6 space-y-6" novalidate @submit.prevent="saveBookingCta">
                <div class="grid gap-5 md:grid-cols-2">
                    <label class="text-sm font-semibold text-slate-800 md:col-span-2">
                        Heading
                        <input
                            v-model="bookingCtaForm.heading"
                            :class="inputClass"
                            maxlength="160"
                            :disabled="bookingCtaSaving"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-800 md:col-span-2">
                        Description
                        <textarea
                            v-model="bookingCtaForm.description"
                            :class="inputClass"
                            rows="4"
                            maxlength="1000"
                            :disabled="bookingCtaSaving"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-800 md:col-span-2">
                        Button label
                        <input
                            v-model="bookingCtaForm.button_label"
                            :class="inputClass"
                            maxlength="80"
                            :disabled="bookingCtaSaving"
                        />
                    </label>
                </div>

                <div
                    v-if="bookingCtaError"
                    role="alert"
                    class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"
                >
                    <p class="font-semibold">
                        {{
                            bookingCtaConflict
                                ? 'Draft version conflict'
                                : 'Booking CTA section was not saved'
                        }}
                    </p>
                    <p class="mt-1">{{ bookingCtaError }}</p>
                </div>
                <div
                    v-if="bookingCtaSuccess"
                    role="status"
                    class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"
                >
                    {{ bookingCtaSuccess }}
                </div>
                <button
                    type="submit"
                    :disabled="bookingCtaSaving"
                    class="min-h-11 rounded-lg bg-emerald-700 px-5 py-3 font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ bookingCtaSaving ? 'Saving Booking CTA…' : 'Save Booking CTA section' }}
                </button>
            </form>
        </section>

        <section
            id="clinic-contact"
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
            aria-labelledby="clinic-contact-title"
        >
            <h2 id="clinic-contact-title" class="text-xl font-bold text-slate-950">
                Extended clinic contact
            </h2>
            <p class="mt-1 text-sm text-slate-600">
                Maintain the clinic’s operational contact details without changing Website branding.
            </p>

            <form class="mt-6 space-y-6" novalidate @submit.prevent="saveClinicContact">
                <div class="grid gap-5 md:grid-cols-2">
                    <label class="text-sm font-semibold text-slate-800">
                        Operational phone
                        <input
                            v-model="contactForm.operational_phone"
                            :class="inputClass"
                            type="tel"
                            maxlength="40"
                            placeholder="+60312345678"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-800">
                        Operational email
                        <input
                            v-model="contactForm.operational_email"
                            :class="inputClass"
                            type="email"
                            maxlength="254"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-800">
                        WhatsApp
                        <input
                            v-model="contactForm.whatsapp_number"
                            :class="inputClass"
                            type="tel"
                            maxlength="40"
                            placeholder="+60123456789"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-800 md:col-span-2">
                        Postal address
                        <textarea
                            v-model="contactForm.postal_address"
                            :class="inputClass"
                            rows="3"
                            maxlength="500"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-800">
                        Latitude
                        <input
                            v-model="contactForm.latitude"
                            :class="inputClass"
                            type="number"
                            min="-90"
                            max="90"
                            step="any"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-800">
                        Longitude
                        <input
                            v-model="contactForm.longitude"
                            :class="inputClass"
                            type="number"
                            min="-180"
                            max="180"
                            step="any"
                        />
                    </label>
                </div>

                <div
                    v-if="contactForm.hasErrors"
                    role="alert"
                    class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"
                >
                    <p class="font-semibold">Review the clinic contact details and try again.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li v-for="(message, field) in contactForm.errors" :key="field">
                            {{ message }}
                        </li>
                    </ul>
                </div>
                <div
                    v-if="contactSaved"
                    role="status"
                    class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"
                >
                    Clinic contact details saved successfully.
                </div>
                <button
                    type="submit"
                    :disabled="contactForm.processing"
                    class="min-h-11 rounded-lg bg-emerald-700 px-5 py-3 font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ contactForm.processing ? 'Saving…' : 'Save clinic contact' }}
                </button>
            </form>
        </section>

        <section
            id="booking-setup"
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
            aria-labelledby="booking-setup-title"
        >
            <h2 id="booking-setup-title" class="text-xl font-bold text-slate-950">
                Booking form configuration
            </h2>
            <p class="mt-1 text-sm text-slate-600">
                Configure only the approved Booking fields. Active Services are supplied by the
                clinic and cannot be selected individually here.
            </p>

            <form class="mt-6 space-y-6" novalidate @submit.prevent="saveBookingConfiguration">
                <fieldset>
                    <legend class="font-bold text-slate-900">Optional fields</legend>
                    <div class="mt-4 grid gap-4 md:grid-cols-3">
                        <div
                            v-for="field in [
                                ['service', 'Service selection', 'service_selection_enabled'],
                                ['email', 'Email', 'email_enabled'],
                                ['notes', 'Notes', 'notes_enabled'],
                            ]"
                            :key="field[0]"
                            class="rounded-xl border border-slate-200 p-4"
                        >
                            <label class="flex items-center gap-3 text-sm font-semibold">
                                <input
                                    v-model="bookingForm[field[2]]"
                                    type="checkbox"
                                    class="size-4 accent-emerald-700"
                                    @change="synchronizeBookingOrder"
                                />
                                Enable {{ field[1] }}
                            </label>
                            <label class="mt-3 flex items-center gap-3 text-sm">
                                <input
                                    v-model="bookingForm[`${field[0]}_required`]"
                                    type="checkbox"
                                    class="size-4 accent-emerald-700"
                                    :disabled="!bookingForm[field[2]]"
                                />
                                Required
                            </label>
                        </div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="font-bold text-slate-900">Governed field ordering</legend>
                    <ol class="mt-4 space-y-2">
                        <li
                            v-for="(field, index) in bookingForm.field_order"
                            :key="field"
                            class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3"
                        >
                            <span class="text-sm font-semibold">
                                {{ index + 1 }}. {{ bookingFieldLabel(field) }}
                            </span>
                            <span class="flex gap-2">
                                <button
                                    type="button"
                                    class="rounded-lg border border-slate-300 px-3 py-1 text-sm"
                                    :disabled="index === 0"
                                    @click="moveBookingField(index, -1)"
                                >
                                    Up
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border border-slate-300 px-3 py-1 text-sm"
                                    :disabled="index === bookingForm.field_order.length - 1"
                                    @click="moveBookingField(index, 1)"
                                >
                                    Down
                                </button>
                            </span>
                        </li>
                    </ol>
                </fieldset>

                <fieldset>
                    <legend class="font-bold text-slate-900">Approved labels</legend>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <label
                            v-for="[key, label] in enabledBookingFields"
                            :key="key"
                            class="text-sm font-semibold text-slate-800"
                        >
                            {{ label }}
                            <input
                                v-model="bookingForm.labels[key]"
                                :class="inputClass"
                                maxlength="120"
                                :placeholder="label"
                            />
                        </label>
                    </div>
                </fieldset>

                <section aria-labelledby="active-services-title">
                    <h3 id="active-services-title" class="font-bold text-slate-900">
                        Active Services preview
                    </h3>
                    <ul
                        v-if="bookingSetup.configuration.active_services.length"
                        class="mt-3 grid gap-2 sm:grid-cols-2"
                    >
                        <li
                            v-for="service in bookingSetup.configuration.active_services"
                            :key="service.id"
                            class="rounded-lg bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-800"
                        >
                            {{ service.name }}
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-sm text-slate-600">
                        No active clinic Services are currently available.
                    </p>
                </section>

                <div
                    v-if="bookingForm.hasErrors"
                    role="alert"
                    class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"
                >
                    <p class="font-semibold">Review the Booking configuration and try again.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li v-for="(message, field) in bookingForm.errors" :key="field">
                            {{ message }}
                        </li>
                    </ul>
                </div>
                <div
                    v-if="bookingSaved"
                    role="status"
                    class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"
                >
                    Booking form configuration saved successfully.
                </div>
                <button
                    type="submit"
                    :disabled="bookingForm.processing"
                    class="min-h-11 rounded-lg bg-emerald-700 px-5 py-3 font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ bookingForm.processing ? 'Saving…' : 'Save Booking configuration' }}
                </button>
            </form>
        </section>

        <section aria-labelledby="timeline-title">
            <h2 id="timeline-title" class="text-lg font-bold text-slate-950">Timeline</h2>
            <ol class="mt-4 space-y-3 border-l-2 border-slate-200 pl-5">
                <li v-for="event in job.timeline" :key="event.key" class="relative">
                    <span
                        class="absolute -left-[1.7rem] top-1.5 size-3 rounded-full bg-emerald-600 ring-4 ring-white"
                        aria-hidden="true"
                    />
                    <p class="font-semibold text-slate-900">{{ event.title }}</p>
                    <time :datetime="event.occurredAt" class="text-sm text-slate-600">
                        {{ event.occurredAtLabel }}
                    </time>
                </li>
            </ol>
        </section>

        <DashboardQuickActions :actions="actions" />
    </DashboardShell>
</template>

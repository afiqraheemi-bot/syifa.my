<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { browserHttpRequest } from '../../../Shared/Authentication/session.js';
import {
    createOnboardingCheckpoints,
    isOnboardingTaskComplete,
} from '../../../Shared/Onboarding/checkpoints.js';
import {
    createDashboardNavigation,
    createDashboardQuickActions,
    DashboardQuickActions,
    DashboardShell,
} from '../../../Shared/Dashboard/index.js';
import WebsiteImageUpload from '../../../Shared/Website/WebsiteImageUpload.vue';
import WebsiteSeoEditor from '../../../Shared/Website/WebsiteSeoEditor.vue';
import DesignerWorkspacePanel from './DesignerWorkspacePanel.vue';
import SyifaAiAssistant from './SyifaAiAssistant.vue';

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
    syifaAi: { type: Object, required: true },
    taskUpdateUrlTemplate: { type: String, required: true },
    launchReadiness: { type: Object, default: null },
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
        whatsapp_button_style:
            props.websiteSetup.configuration.branding.whatsapp_button_style ?? 'pill',
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
const heroAspectRatio = computed(() => (form.template_id === 'SYIFA_AESTHETIC' ? 4 / 5 : 4 / 3));
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

function applySyifaAiSuggestion(suggestion) {
    if (suggestion.section === 'HERO' && suggestion.field in heroForm.value) {
        heroForm.value[suggestion.field] = suggestion.proposed_value;
        heroSuccess.value = 'SYIFA AI suggestion added to the form. Review and save when ready.';
        document.querySelector('#hero-editor')?.scrollIntoView({ behavior: 'smooth' });
        return;
    }
    if (suggestion.section === 'ABOUT' && suggestion.field in aboutForm.value) {
        aboutForm.value[suggestion.field] = suggestion.proposed_value;
        aboutSuccess.value = 'SYIFA AI suggestion added to the form. Review and save when ready.';
        document.querySelector('#about-editor')?.scrollIntoView({ behavior: 'smooth' });
    }
}
const bookingCtaConflict = ref(false);
const reviewSubmitting = ref(false);
const reviewSuccess = ref('');
const reviewError = ref('');
const reviewConflict = ref(false);
const reviewCompleted = ref(
    props.websiteSetup.configuration.lifecycle === 'ready_for_review' &&
        !props.websiteSetup.canSubmitForReview,
);
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

function synchronizeWebsiteVersion(asset) {
    if (Number.isInteger(asset?.website_version)) {
        form.version = asset.website_version;
    }
}

const taskByKey = (key) => props.job.tasks.find((task) => task.key === key);
const taskComplete = (key) => isOnboardingTaskComplete(taskByKey(key));
const designerTasks = computed(() =>
    props.job.tasks.filter((task) => task.responsibility === 'website_designer'),
);
const nextDesignerTask = computed(() =>
    designerTasks.value.find((task) => !isOnboardingTaskComplete(task)),
);
const clinicInputsComplete = computed(() => taskComplete('clinic_inputs'));
const workflowCheckpoints = computed(() => {
    const destinations = {
        clinic_inputs: '#onboarding-tasks',
        service_setup: '#services-editor',
        website_setup: '#website-setup',
        booking_setup: '#booking-setup',
        website_approval: '#website-review',
        launch_readiness: '#website-review',
    };

    return createOnboardingCheckpoints(props.job.tasks, destinations).map((checkpoint) => {
        const ownerCheckpoint = checkpoint.responsibility === 'clinic_owner';
        return {
            ...checkpoint,
            detail:
                checkpoint.state === 'current' && ownerCheckpoint
                    ? 'Waiting for the Clinic Owner to complete this checkpoint.'
                    : checkpoint.state === 'current'
                      ? 'Finish and save this step, then confirm completion to continue.'
                      : checkpoint.detail,
        };
    });
});
const currentWorkflowTask = computed(() => {
    const task = nextDesignerTask.value;

    return task?.actionable ? task : null;
});

const currentFocus = computed(() => {
    if (!clinicInputsComplete.value) {
        return {
            title: 'Waiting for Clinic Owner information',
            detail: 'No setup work should be completed until the clinic input checkpoint is ready.',
            href: '#onboarding-tasks',
            label: 'View dependency',
        };
    }

    const key = nextDesignerTask.value?.key;
    const destinations = {
        service_setup: ['Configure clinic services', '#services-editor', 'Open services'],
        website_setup: ['Build website and content', '#website-setup', 'Open website setup'],
        booking_setup: ['Configure patient booking', '#booking-setup', 'Open booking setup'],
        launch_readiness: ['Complete launch readiness', '#website-review', 'Open review'],
    };
    const destination = destinations[key];
    if (destination) {
        return {
            title: destination[0],
            detail: nextDesignerTask.value?.title ?? 'Continue the current onboarding checkpoint.',
            href: destination[1],
            label: destination[2],
        };
    }

    return {
        title: props.websiteSetup.canPublish
            ? 'Publish the approved website'
            : 'Review website readiness',
        detail: props.websiteSetup.canPublish
            ? 'All required evidence is ready for the immutable publication step.'
            : 'Preview the current draft and submit it when every enabled section is renderable.',
        href: '#website-review',
        label: 'Open review and publish',
    };
});

const unmetLaunchConditions = computed(
    () => props.launchReadiness?.conditions?.filter((condition) => !condition.satisfied) ?? [],
);
const completedLaunchConditions = computed(
    () => props.launchReadiness?.conditions?.filter((condition) => condition.satisfied) ?? [],
);
const launchReadinessFocus = computed(() => {
    const condition = unmetLaunchConditions.value[0];
    if (!condition) {
        return {
            title: 'All checks complete',
            detail: 'The Website has the evidence required for the approved publish action.',
            href: '#website-review',
            label: 'Open publish step',
        };
    }

    if (
        ['approval', 'clinic_owner_approval'].includes(condition.key) &&
        props.websiteSetup.canSubmitForReview
    ) {
        return {
            title: 'Submit the updated version for approval',
            detail: 'The Website changed after the previous approval. Submit this latest saved version so the Clinic Owner receives a new approval action.',
            href: '#website-review',
            label: 'Submit updated version',
        };
    }

    const guidance = {
        tasks: [
            'Complete the remaining checkpoint',
            'Finish the required onboarding task and record its completion evidence.',
            '#workflow',
            'Open checkpoints',
        ],
        approval: [
            'Waiting for Clinic Owner approval',
            'The Clinic Owner must review the current Website from their Website Overview and approve it. No approval action is required from the Website Designer.',
            '#website-review',
            'Preview submitted Website',
        ],
        clinic_owner_approval: [
            'Waiting for Clinic Owner approval',
            'The Clinic Owner must review the current Website from their Website Overview and approve it. No approval action is required from the Website Designer.',
            '#website-review',
            'Preview submitted Website',
        ],
        subscription: [
            'Subscription must be active',
            'The clinic needs an active publication entitlement. Ask the Super Admin to verify the subscription if this remains blocked.',
            '#website-review',
            'Review status',
        ],
        website: [
            'Submit the Website for review',
            'Complete the enabled content and template, then submit the current Website version for review.',
            '#website-review',
            'Open review step',
        ],
        assets: [
            'Resolve a missing Website asset',
            'Check that every selected logo and image is available and valid for this Website.',
            '#website-setup',
            'Check Website content',
        ],
        services: [
            'Add at least one active service',
            'The clinic needs an active service before the Website can launch.',
            '#services-editor',
            'Open services',
        ],
        booking: [
            'Complete booking configuration',
            'Save the governed booking form configuration for this clinic.',
            '#booking-setup',
            'Open booking setup',
        ],
        address: [
            'Activate the public Website address',
            'A primary public Website address must be active before launch.',
            '#website-review',
            'Review status',
        ],
    };
    const selected = guidance[condition.key] ?? [
        condition.label,
        condition.detail,
        '#website-review',
        'Review requirement',
    ];

    return {
        title: selected[0],
        detail: selected[1],
        href: selected[2],
        label: selected[3],
    };
});

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

    bookingCtaSuccess.value = '';
    bookingCtaError.value = '';
    bookingCtaConflict.value = false;
    if (
        bookingCtaForm.value.heading.trim() === '' ||
        bookingCtaForm.value.description.trim() === '' ||
        bookingCtaForm.value.button_label.trim() === ''
    ) {
        bookingCtaError.value =
            'Complete the heading, description and button label before saving this section.';
        return;
    }

    bookingCtaSaving.value = true;
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
            props.websiteSetup.configuration.lifecycle === 'ready_for_review'
                ? 'Submit the updated Website version to the Clinic Owner for approval?'
                : 'Submit this Website for review? Content can continue only through the approved review workflow.',
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

        reviewSuccess.value =
            body.message ??
            (props.websiteSetup.configuration.lifecycle === 'ready_for_review'
                ? 'Updated Website version submitted to the Clinic Owner.'
                : 'Website submitted for review.');
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
    const evidence = operation === 'complete' ? completionEvidence(task) : null;
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

function completionEvidence(task) {
    const evidence = {
        service_setup: `website_draft:services:v${draft.value.version}`,
        website_setup: `website_configuration:v${form.version};draft:v${draft.value.version}`,
        booking_setup: `booking_form_configuration:v${bookingForm.version}`,
        launch_readiness: `website_readiness:v${form.version};draft:v${draft.value.version}`,
    };

    return evidence[task.key] ?? `designer_workspace:${task.key}`;
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
        <section
            id="workflow"
            class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6"
        >
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                        Assigned onboarding job
                    </p>
                    <h2 class="mt-1 text-2xl font-bold text-slate-950">{{ job.clinicName }}</h2>
                    <a
                        v-if="websiteAddress?.url"
                        :href="websiteAddress.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-2 inline-flex break-all text-sm font-semibold text-emerald-700 underline decoration-emerald-300 underline-offset-4 hover:text-emerald-900"
                    >
                        {{ websiteAddress.host }} ↗
                    </a>
                    <p v-else class="mt-2 text-sm text-slate-500">
                        Website address is being prepared
                    </p>
                    <details class="mt-2 text-sm text-slate-500">
                        <summary class="cursor-pointer font-semibold">Technical references</summary>
                        <dl class="mt-3 grid gap-2 sm:grid-cols-3">
                            <div>
                                <dt class="text-xs font-semibold">Job</dt>
                                <dd :title="job.id" class="mt-1 font-mono text-xs text-slate-800">
                                    {{ job.jobReference }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold">Tenant</dt>
                                <dd
                                    :title="job.tenantId"
                                    class="mt-1 font-mono text-xs text-slate-800"
                                >
                                    {{ job.tenantReference }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold">Website</dt>
                                <dd
                                    :title="job.websiteId"
                                    class="mt-1 font-mono text-xs text-slate-800"
                                >
                                    {{ job.websiteReference }}
                                </dd>
                            </div>
                        </dl>
                    </details>
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

            <div class="mt-6 grid gap-5 border-t border-slate-200 pt-6 lg:grid-cols-[1fr_20rem]">
                <ol aria-label="Website onboarding checkpoints">
                    <li
                        v-for="(checkpoint, index) in workflowCheckpoints"
                        :key="checkpoint.key"
                        class="relative flex gap-4 pb-5 last:pb-0"
                    >
                        <div class="flex shrink-0 flex-col items-center" aria-hidden="true">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold ring-4 ring-white"
                                :class="
                                    checkpoint.state === 'complete'
                                        ? 'bg-emerald-700 text-white'
                                        : checkpoint.state === 'current'
                                          ? 'bg-amber-200 text-amber-900'
                                          : 'bg-slate-200 text-slate-600'
                                "
                            >
                                {{ checkpoint.state === 'complete' ? '✓' : index + 1 }}
                            </span>
                            <span
                                v-if="index < workflowCheckpoints.length - 1"
                                class="mt-1 h-full min-h-6 w-0.5"
                                :class="
                                    checkpoint.state === 'complete'
                                        ? 'bg-emerald-300'
                                        : 'bg-slate-200'
                                "
                            />
                        </div>
                        <a
                            :href="checkpoint.href"
                            class="min-w-0 flex-1 rounded-xl border p-3 text-left transition focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 sm:p-4"
                            :class="
                                checkpoint.state === 'complete'
                                    ? 'border-emerald-200 bg-emerald-50'
                                    : checkpoint.state === 'current'
                                      ? 'border-amber-300 bg-amber-50'
                                      : 'border-slate-200 bg-slate-50 opacity-70'
                            "
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-bold text-slate-950">
                                    {{ checkpoint.label }}
                                </p>
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-bold"
                                    :class="
                                        checkpoint.state === 'complete'
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : checkpoint.state === 'current'
                                              ? 'bg-amber-200 text-amber-900'
                                              : 'bg-slate-200 text-slate-600'
                                    "
                                >
                                    {{ checkpoint.statusLabel }}
                                </span>
                            </div>
                            <p class="mt-2 text-xs leading-5 text-slate-600">
                                {{ checkpoint.detail }}
                            </p>
                        </a>
                    </li>
                </ol>

                <aside class="rounded-2xl bg-emerald-950 p-4 text-white sm:p-5">
                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-300">
                        Focus now
                    </p>
                    <h3 class="mt-2 text-lg font-bold">{{ currentFocus.title }}</h3>
                    <p class="mt-2 text-sm leading-6 text-emerald-100">{{ currentFocus.detail }}</p>
                    <a
                        :href="currentFocus.href"
                        class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-white px-4 text-center font-bold text-emerald-950 sm:w-auto"
                    >
                        {{ currentFocus.label }}
                    </a>
                    <div v-if="currentWorkflowTask" class="mt-5 border-t border-emerald-800 pt-5">
                        <p class="text-sm leading-6 text-emerald-100">
                            Finished this step? Confirm once to unlock the next checkpoint.
                        </p>
                        <button
                            type="button"
                            class="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-emerald-300 px-4 font-bold text-white transition hover:bg-emerald-900 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="taskBusy !== null"
                            @click="progressTask(currentWorkflowTask, 'complete')"
                        >
                            {{
                                taskBusy === currentWorkflowTask.id
                                    ? 'Completing…'
                                    : 'Complete this checkpoint'
                            }}
                        </button>
                    </div>
                </aside>
            </div>
        </section>

        <SyifaAiAssistant
            :endpoint="syifaAi.assistUrl"
            :enabled="syifaAi.enabled"
            :image-assistance-enabled="syifaAi.imageAssistanceEnabled"
            @apply="applySyifaAiSuggestion"
        />

        <nav
            aria-label="Website workspace shortcuts"
            class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5"
        >
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">
                        Website workspace
                    </p>
                    <p class="mt-1 text-sm text-slate-600">
                        Open only the area you need. Your saved work remains in the private draft.
                    </p>
                </div>
                <a
                    href="#website-review"
                    class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-slate-950 px-4 text-sm font-bold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-800 focus:ring-offset-2 sm:w-auto"
                >
                    Review and publish
                </a>
            </div>
            <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <a
                    href="#website-setup"
                    class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-800 transition hover:border-emerald-300 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                >
                    1. Website setup
                </a>
                <a
                    href="#hero-editor"
                    class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-800 transition hover:border-emerald-300 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                >
                    2. Content sections
                </a>
                <a
                    href="#booking-setup"
                    class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-800 transition hover:border-emerald-300 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                >
                    3. Booking form
                </a>
                <a
                    href="#workflow"
                    class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-800 transition hover:border-emerald-300 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-700"
                >
                    View checkpoints
                </a>
            </div>
        </nav>

        <section
            v-if="launchReadiness"
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
            aria-labelledby="launch-readiness-title"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="launch-readiness-title" class="text-xl font-bold text-slate-950">
                        Ready to publish?
                    </h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Final automatic checks that protect the clinic before its Website goes live.
                    </p>
                </div>
                <span
                    class="rounded-full px-3 py-1 text-sm font-bold"
                    :class="
                        launchReadiness.ready
                            ? 'bg-emerald-100 text-emerald-800'
                            : 'bg-amber-100 text-amber-900'
                    "
                >
                    {{
                        launchReadiness.ready
                            ? 'Ready to publish'
                            : `${unmetLaunchConditions.length} step${unmetLaunchConditions.length === 1 ? '' : 's'} remaining`
                    }}
                </span>
            </div>

            <div
                class="mt-5 rounded-xl border p-4 sm:flex sm:items-center sm:justify-between sm:gap-5"
                :class="
                    launchReadiness.ready
                        ? 'border-emerald-200 bg-emerald-50'
                        : 'border-amber-200 bg-amber-50'
                "
            >
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-600">
                        {{ launchReadiness.ready ? 'Ready now' : 'Next step' }}
                    </p>
                    <h3 class="mt-1 font-bold text-slate-950">{{ launchReadinessFocus.title }}</h3>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-700">
                        {{ launchReadinessFocus.detail }}
                    </p>
                </div>
                <a
                    :href="launchReadinessFocus.href"
                    class="mt-4 inline-flex min-h-11 w-full shrink-0 items-center justify-center rounded-lg bg-slate-900 px-4 text-center font-semibold text-white sm:mt-0 sm:w-auto"
                >
                    {{ launchReadinessFocus.label }}
                </a>
            </div>

            <details class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <summary class="cursor-pointer font-semibold text-slate-900">
                    View all launch checks
                    <span class="ml-1 font-normal text-slate-600">
                        ({{ completedLaunchConditions.length }}/{{
                            launchReadiness.conditions.length
                        }}
                        complete)
                    </span>
                </summary>
                <ul class="mt-4 divide-y divide-slate-200">
                    <li
                        v-for="condition in launchReadiness.conditions"
                        :key="condition.key"
                        class="flex gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <span
                            class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                            :class="
                                condition.satisfied
                                    ? 'bg-emerald-100 text-emerald-800'
                                    : 'bg-amber-100 text-amber-900'
                            "
                        >
                            {{ condition.satisfied ? '✓' : '!' }}
                        </span>
                        <div>
                            <p class="font-semibold text-slate-900">{{ condition.label }}</p>
                            <p class="mt-0.5 text-sm leading-6 text-slate-600">
                                {{ condition.detail }}
                            </p>
                        </div>
                    </li>
                </ul>
            </details>
        </section>

        <details
            id="onboarding-tasks"
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
        >
            <summary class="cursor-pointer font-bold text-slate-950">
                Technical task details
            </summary>
            <p class="mt-1 text-sm text-slate-600">
                Optional workflow information for troubleshooting and handover.
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
                </article>
            </div>
        </details>

        <details class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <summary class="cursor-pointer font-bold text-slate-950">Lifecycle details</summary>
            <p class="mt-2 text-sm text-slate-600">
                Supporting status detail for troubleshooting and handover.
            </p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="stage in job.stages"
                    :key="stage.key"
                    class="rounded-xl border border-slate-200 bg-slate-50 p-4"
                >
                    <p class="font-semibold text-slate-950">{{ stage.label }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ stage.stateLabel }}</p>
                </div>
            </div>
        </details>

        <section
            id="website-review"
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
                <p class="mt-1 whitespace-pre-line">{{ reviewError }}</p>
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
            <div class="mt-5 grid gap-3 text-sm lg:grid-cols-2">
                <div
                    class="flex flex-col rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-950"
                >
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-bold">Private draft</p>
                        <span
                            class="rounded-full bg-white/80 px-2.5 py-1 text-xs font-bold uppercase tracking-wide"
                        >
                            Latest saved
                        </span>
                    </div>
                    <p class="mt-2 flex-1 leading-6">
                        Your current working version. Only the assigned Website Designer can open
                        this protected preview.
                    </p>
                    <button
                        type="button"
                        class="mt-4 inline-flex min-h-11 w-fit items-center rounded-lg border border-amber-700 px-4 py-2 font-semibold text-amber-950 transition hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-700 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="previewOpening"
                        @click="openDraftPreview"
                    >
                        {{ previewOpening ? 'Opening draft…' : 'Preview private draft' }}
                    </button>
                </div>
                <div
                    class="flex flex-col rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-950"
                >
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-bold">Live website</p>
                        <span
                            class="rounded-full bg-white/80 px-2.5 py-1 text-xs font-bold uppercase tracking-wide"
                        >
                            {{ websiteAddress?.active ? 'Published' : 'Not live' }}
                        </span>
                    </div>
                    <p class="mt-2 flex-1 leading-6">
                        The public version at
                        <span class="font-semibold">{{
                            websiteAddress?.host ?? 'the clinic address'
                        }}</span>
                        changes only after an approved publication.
                    </p>
                    <a
                        v-if="websiteAddress?.active"
                        :href="websiteAddress.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-4 inline-flex min-h-11 w-fit items-center rounded-lg border border-emerald-700 px-4 py-2 font-semibold text-emerald-900 transition hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2"
                    >
                        Open live website
                    </a>
                    <p v-else class="mt-4 font-semibold text-emerald-900">
                        Publish the approved draft to activate the public website.
                    </p>
                </div>
            </div>
            <div class="mt-5 flex flex-wrap gap-3">
                <button
                    v-if="websiteSetup.canSubmitForReview && !reviewCompleted"
                    type="button"
                    class="min-h-11 rounded-lg bg-emerald-700 px-5 py-3 font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="reviewSubmitting"
                    @click="submitForReview"
                >
                    {{
                        reviewSubmitting
                            ? 'Submitting for review…'
                            : websiteSetup.configuration.lifecycle === 'ready_for_review'
                              ? 'Submit Updated Version'
                              : 'Submit for review'
                    }}
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

        <DesignerWorkspacePanel
            id="website-setup"
            title="Website setup"
            description="Choose the approved template, manage the public identity and control search and section settings."
            eyebrow="Foundation"
            open
        >
            <div class="mb-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="font-bold text-slate-950">Published Website address</h2>
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
                </div>
                <form
                    v-if="websiteSetup.canReserveAddress"
                    class="mt-4 flex min-w-0 flex-col gap-3 sm:flex-row sm:items-end"
                    @submit.prevent="reserveWebsiteAddress"
                >
                    <label class="flex-1 text-sm font-semibold text-slate-800">
                        SYIFA.my subdomain
                        <span
                            class="mt-1 flex min-w-0 flex-col overflow-hidden rounded-lg border border-slate-300 bg-white min-[430px]:flex-row"
                        >
                            <input
                                v-model.trim="addressForm.subdomain"
                                required
                                minlength="3"
                                maxlength="63"
                                pattern="[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])?"
                                autocomplete="off"
                                class="min-h-11 min-w-0 flex-1 px-3 text-slate-950 outline-none focus:ring-2 focus:ring-emerald-600"
                            />
                            <span
                                class="flex min-h-10 items-center border-t border-slate-300 px-3 text-sm text-slate-500 min-[430px]:border-l min-[430px]:border-t-0"
                            >
                                .{{ websiteSetup.baseDomain }}
                            </span>
                        </span>
                    </label>
                    <button
                        type="submit"
                        :disabled="addressSaving"
                        class="min-h-11 w-full rounded-lg bg-emerald-700 px-5 py-3 font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
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
            <form class="space-y-6" novalidate @submit.prevent="saveWebsiteSetup">
                <fieldset class="rounded-xl border border-slate-200 bg-slate-50 p-5">
                    <legend class="font-bold text-slate-900">Approved template</legend>
                    <label class="mt-4 block text-sm font-semibold text-slate-800">
                        Website template
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
                        Choose any approved SYIFA.my template. For a live Website, the public
                        template changes only after the Website is published again.
                    </p>
                </fieldset>

                <details class="group rounded-xl border border-slate-200 bg-white">
                    <summary
                        class="flex min-h-20 cursor-pointer list-none items-center justify-between gap-4 rounded-xl px-5 py-4 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2"
                    >
                        <span>
                            <span
                                class="block text-xs font-bold uppercase tracking-[0.16em] text-emerald-800"
                            >
                                Clinic and brand
                            </span>
                            <span class="mt-1 block text-lg font-bold text-slate-950">Contact</span>
                            <span class="mt-1 block text-sm font-normal leading-6 text-slate-600">
                                Clinic identity, logo, public contact details and social channels.
                            </span>
                        </span>
                        <span
                            class="shrink-0 text-xl text-slate-500 transition group-open:rotate-90"
                            aria-hidden="true"
                        >
                            ›
                        </span>
                    </summary>
                    <div class="border-t border-slate-200 p-5">
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
                                        type="color"
                                        class="mt-2 block h-14 w-full cursor-pointer rounded-xl border border-slate-300 bg-white p-1.5 shadow-sm transition focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20"
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
                                        class="mt-2 block h-14 w-full cursor-pointer rounded-xl border border-slate-300 bg-white p-1.5 shadow-sm transition focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20"
                                    />
                                    <span class="mt-1 block text-xs font-normal text-slate-500">
                                        Choose the supporting brand colour.
                                    </span>
                                </label>
                            </div>
                            <WebsiteImageUpload
                                v-model="form.branding.logo_reference"
                                class="mt-5"
                                label="Clinic logo"
                                :upload-url="websiteDraft.assetUploadUrl"
                                :asset-url-template="websiteDraft.assetUrlTemplate"
                                :aspect-ratio="6"
                                :aspect-ratio-options="[
                                    { label: 'Wide wordmark', value: 6 },
                                    { label: 'Landscape logo', value: 3 },
                                    { label: 'Square symbol', value: 1 },
                                ]"
                                :disabled="form.processing"
                                @uploaded="synchronizeWebsiteVersion"
                            />
                            <p class="mt-2 text-sm text-slate-600">
                                Crop the logo closely using Wide, Landscape, or Square. It appears
                                in preview after saving and becomes public after the Website is
                                published.
                            </p>
                            <fieldset v-if="form.branding.logo_reference" class="mt-5">
                                <legend class="text-sm font-semibold text-slate-800">
                                    Logo size
                                </legend>
                                <div class="mt-2 grid gap-2 sm:grid-cols-3">
                                    <label
                                        v-for="option in [
                                            ['compact', 'Compact'],
                                            ['standard', 'Standard'],
                                            ['large', 'Large'],
                                        ]"
                                        :key="option[0]"
                                        class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-3 text-sm font-medium text-slate-800 transition has-[:checked]:border-emerald-700 has-[:checked]:bg-emerald-50"
                                    >
                                        <input
                                            v-model="form.branding.logo_display_size"
                                            type="radio"
                                            name="logo_display_size"
                                            :value="option[0]"
                                            class="text-emerald-700 focus:ring-emerald-600"
                                        />
                                        {{ option[1] }}
                                    </label>
                                </div>
                            </fieldset>
                        </fieldset>

                        <fieldset class="mt-6 border-t border-slate-200 pt-5">
                            <legend class="font-bold text-slate-900">Contact details</legend>
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
                    </div>
                </details>

                <details class="group rounded-xl border border-slate-200 bg-white">
                    <summary
                        class="flex min-h-20 cursor-pointer list-none items-center justify-between gap-4 rounded-xl px-5 py-4 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2"
                    >
                        <span>
                            <span
                                class="block text-xs font-bold uppercase tracking-[0.16em] text-emerald-800"
                            >
                                Discoverability
                            </span>
                            <span class="mt-1 block text-lg font-bold text-slate-950">
                                Search and sharing
                            </span>
                            <span class="mt-1 block text-sm font-normal leading-6 text-slate-600">
                                Search metadata, social previews and indexing controls.
                            </span>
                        </span>
                        <span
                            class="shrink-0 text-xl text-slate-500 transition group-open:rotate-90"
                            aria-hidden="true"
                        >
                            ›
                        </span>
                    </summary>
                    <div class="border-t border-slate-200 p-5">
                        <WebsiteSeoEditor
                            v-model="form.seo"
                            :fallback-title="form.branding.clinic_name"
                            :fallback-description="form.branding.tagline"
                            :input-class="inputClass"
                        />
                    </div>
                </details>

                <fieldset class="rounded-xl border border-slate-200 bg-white p-5">
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
        </DesignerWorkspacePanel>

        <DesignerWorkspacePanel
            id="hero-editor"
            title="Homepage"
            description="Edit the opening message, calls to action and Hero image in the private draft."
        >
            <form class="space-y-6" novalidate @submit.prevent="saveHero">
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
                    <WebsiteImageUpload
                        v-model="heroForm.hero_image_asset_id"
                        class="md:col-span-2"
                        label="Hero image"
                        :upload-url="websiteDraft.assetUploadUrl"
                        :asset-url-template="websiteDraft.assetUrlTemplate"
                        :aspect-ratio="heroAspectRatio"
                        :disabled="heroSaving"
                        @uploaded="synchronizeWebsiteVersion"
                    />
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
        </DesignerWorkspacePanel>

        <DesignerWorkspacePanel
            id="about-editor"
            title="About"
            description="Maintain the clinic introduction and supporting image in the private draft."
        >
            <form class="space-y-6" novalidate @submit.prevent="saveAbout">
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
                    <WebsiteImageUpload
                        v-model="aboutForm.image_asset_id"
                        class="md:col-span-2"
                        label="About image"
                        :upload-url="websiteDraft.assetUploadUrl"
                        :asset-url-template="websiteDraft.assetUrlTemplate"
                        :disabled="aboutSaving"
                        @uploaded="synchronizeWebsiteVersion"
                    />
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
        </DesignerWorkspacePanel>

        <DesignerWorkspacePanel
            id="services-editor"
            title="Services"
            description="Choose and order active clinic Services without duplicating Booking records."
        >
            <form class="space-y-6" novalidate @submit.prevent="saveServices">
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
        </DesignerWorkspacePanel>

        <DesignerWorkspacePanel
            id="doctors-editor"
            title="Doctors"
            description="Maintain public Website profiles only; scheduling and credentials remain outside this editor."
        >
            <div class="flex justify-end">
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
                            <WebsiteImageUpload
                                v-model="profile.photo_asset_id"
                                class="md:col-span-2"
                                :label="`Photo for ${profile.name || `Doctor ${index + 1}`}`"
                                :upload-url="websiteDraft.assetUploadUrl"
                                :asset-url-template="websiteDraft.assetUrlTemplate"
                                :aspect-ratio="1"
                                :disabled="doctorsSaving"
                                @uploaded="synchronizeWebsiteVersion"
                            />
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
        </DesignerWorkspacePanel>

        <DesignerWorkspacePanel
            id="testimonials-editor"
            title="Testimonials"
            description="Maintain governed clinic testimonials without connecting an external review provider."
        >
            <div class="flex justify-end">
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
                                Display this testimonial on the Website
                            </label>
                            <p class="text-sm text-slate-600 md:col-span-2">
                                At least one testimonial must be selected while this section is
                                enabled.
                            </p>
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
        </DesignerWorkspacePanel>

        <DesignerWorkspacePanel
            id="gallery-editor"
            title="Gallery"
            description="Upload, describe and arrange Website-owned gallery images."
        >
            <div class="flex justify-end">
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
                            <WebsiteImageUpload
                                v-model="image.asset_id"
                                class="md:col-span-2"
                                :label="`Gallery image ${index + 1}`"
                                :upload-url="websiteDraft.assetUploadUrl"
                                :asset-url-template="websiteDraft.assetUrlTemplate"
                                :disabled="gallerySaving"
                                required
                                @uploaded="synchronizeWebsiteVersion"
                            />
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
        </DesignerWorkspacePanel>

        <DesignerWorkspacePanel
            id="faq-editor"
            title="FAQ"
            description="Maintain clear, plain-text answers to common patient questions."
        >
            <div class="flex justify-end">
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
        </DesignerWorkspacePanel>

        <DesignerWorkspacePanel
            id="booking-cta-editor"
            title="Booking call to action"
            description="Complete all three fields to guide patients into the existing Booking flow."
        >
            <form class="space-y-6" novalidate @submit.prevent="saveBookingCta">
                <div class="grid gap-5 md:grid-cols-2">
                    <label class="text-sm font-semibold text-slate-800 md:col-span-2">
                        Heading
                        <input
                            v-model="bookingCtaForm.heading"
                            :class="inputClass"
                            maxlength="160"
                            required
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
                            required
                            :disabled="bookingCtaSaving"
                        />
                    </label>
                    <label class="text-sm font-semibold text-slate-800 md:col-span-2">
                        Button label
                        <input
                            v-model="bookingCtaForm.button_label"
                            :class="inputClass"
                            maxlength="80"
                            required
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
        </DesignerWorkspacePanel>

        <DesignerWorkspacePanel
            id="clinic-contact"
            title="Extended clinic contact"
            description="Maintain operational contact details without duplicating Website branding."
            eyebrow="Clinic information"
        >
            <form class="space-y-6" novalidate @submit.prevent="saveClinicContact">
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
        </DesignerWorkspacePanel>

        <DesignerWorkspacePanel
            id="booking-setup"
            title="Booking form"
            description="Configure approved patient fields while the Clinic Owner remains responsible for availability."
            eyebrow="Patient booking"
        >
            <form class="space-y-6" novalidate @submit.prevent="saveBookingConfiguration">
                <div
                    class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm leading-6 text-sky-950"
                >
                    <p class="font-bold">Appointment availability is managed by the Clinic Owner</p>
                    <p class="mt-1">
                        You may configure the Website Booking form below. Weekly Booking Hours,
                        closures, appointment duration, and slot capacity remain authoritative in
                        the Clinic Owner workspace.
                    </p>
                </div>

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
        </DesignerWorkspacePanel>

        <details class="group rounded-xl border border-slate-200 bg-white">
            <summary
                class="flex min-h-20 cursor-pointer list-none items-center justify-between gap-4 rounded-xl px-5 py-4 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2"
            >
                <span>
                    <span class="block text-lg font-bold text-slate-950">Activity timeline</span>
                    <span class="mt-1 block text-sm font-normal text-slate-600">
                        Review authoritative assignment and Website events.
                    </span>
                </span>
                <span
                    class="shrink-0 text-xl text-slate-500 transition group-open:rotate-90"
                    aria-hidden="true"
                >
                    ›
                </span>
            </summary>
            <ol class="mx-5 mb-5 space-y-3 border-l-2 border-slate-200 pl-5 pt-5">
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
        </details>

        <DashboardQuickActions :actions="actions" />
    </DashboardShell>
</template>

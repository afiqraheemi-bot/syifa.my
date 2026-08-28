<script setup>
import { router } from '@inertiajs/vue3';
import { computed, nextTick, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { createDashboardNavigation, DashboardShell } from '../../../Shared/Dashboard';

const { t } = useI18n();

const props = defineProps({
    navigation: { type: Array, required: true },
    breadcrumbs: { type: Array, required: true },
    pageTitle: { type: String, required: true },
    pageDescription: { type: String, required: true },
    identityName: { type: String, required: true },
    contextLabel: { type: String, required: true },
    services: { type: Array, required: true },
    operationsUrl: { type: String, required: true },
});

const navigation = createDashboardNavigation(props.navigation);
const busy = ref(false);
const editingId = ref(null);
const formPanel = ref(null);
const nameInput = ref(null);
const feedback = ref('');
const error = ref('');
const form = reactive({ name: '', description: '', sort_order: 0, version: null });
const activeServices = computed(() =>
    props.services.filter((service) => service.status === 'active'),
);
const inactiveServices = computed(() =>
    props.services.filter((service) => service.status !== 'active'),
);

function resetForm() {
    editingId.value = null;
    Object.assign(form, {
        name: '',
        description: '',
        sort_order: props.services.length,
        version: null,
    });
    error.value = '';
}

function edit(service) {
    editingId.value = service.id;
    Object.assign(form, {
        name: service.name,
        description: service.description ?? '',
        sort_order: service.sortOrder,
        version: service.version,
    });
    feedback.value = '';
    error.value = '';
    nextTick(() => {
        formPanel.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        nameInput.value?.focus();
    });
}

function createService() {
    resetForm();
    nextTick(() => {
        formPanel.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        nameInput.value?.focus();
    });
}

function submit() {
    if (busy.value) return;
    busy.value = true;
    feedback.value = '';
    error.value = '';
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            feedback.value = editingId.value
                ? t('services.updateSuccess')
                : t('services.createSuccess');
            resetForm();
        },
        onError: (errors) => {
            error.value = Object.values(errors)[0] ?? t('services.saveError');
        },
        onFinish: () => {
            busy.value = false;
        },
    };
    const payload = { ...form };
    if (editingId.value) {
        router.patch(`${props.operationsUrl}/${editingId.value}`, payload, options);
    } else {
        router.post(props.operationsUrl, payload, options);
    }
}

function changeStatus(service) {
    if (busy.value) return;
    const activating = service.status !== 'active';
    if (
        !window.confirm(
            activating
                ? t('services.activateConfirm', { name: service.name })
                : t('services.deactivateConfirm', { name: service.name }),
        )
    )
        return;
    busy.value = true;
    feedback.value = '';
    error.value = '';
    router.patch(
        `${props.operationsUrl}/${service.id}/status`,
        { version: service.version, active: activating },
        {
            preserveScroll: true,
            onSuccess: () => {
                feedback.value = activating
                    ? t('services.activateSuccess')
                    : t('services.deactivateSuccess');
            },
            onError: (errors) => {
                error.value = Object.values(errors)[0] ?? t('services.statusChangeError');
            },
            onFinish: () => {
                busy.value = false;
            },
        },
    );
}

resetForm();
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
        <p
            v-if="feedback"
            role="status"
            class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900"
        >
            {{ feedback }}
        </p>
        <p
            v-if="error"
            role="alert"
            class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-red-900"
        >
            {{ error }}
        </p>

        <section
            class="mb-6 overflow-hidden rounded-[1.75rem] border border-emerald-950/10 bg-emerald-950 px-6 py-7 text-white shadow-sm sm:px-8"
        >
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-lime-300">
                        {{ t('services.catalogueEyebrow') }}
                    </p>
                    <h2 class="mt-3 text-2xl font-bold sm:text-3xl">
                        {{ t('services.manageHeading') }}
                    </h2>
                    <p class="mt-2 leading-7 text-emerald-50/80">
                        {{ t('services.manageDescription') }}
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex min-h-12 items-center justify-center rounded-xl bg-lime-400 px-5 py-3 font-bold text-emerald-950 transition hover:bg-lime-300 focus:outline-none focus:ring-2 focus:ring-lime-200 focus:ring-offset-2 focus:ring-offset-emerald-950"
                    @click="createService"
                >
                    {{ t('services.addService') }}
                </button>
            </div>
            <dl class="mt-7 grid grid-cols-3 gap-3 border-t border-white/15 pt-5 sm:max-w-lg">
                <div>
                    <dt class="text-xs text-emerald-50/65">{{ t('services.total') }}</dt>
                    <dd class="mt-1 text-2xl font-bold">{{ services.length }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-emerald-50/65">{{ t('services.active') }}</dt>
                    <dd class="mt-1 text-2xl font-bold text-lime-300">
                        {{ activeServices.length }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-emerald-50/65">{{ t('services.inactive') }}</dt>
                    <dd class="mt-1 text-2xl font-bold">{{ inactiveServices.length }}</dd>
                </div>
            </dl>
        </section>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_25rem]">
            <section class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-slate-950">
                            {{ t('services.listHeading') }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ t('services.listDescription') }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="services.length === 0"
                    class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-10 text-center"
                >
                    <p class="font-semibold text-slate-900">{{ t('services.emptyTitle') }}</p>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-600">
                        {{ t('services.emptyDescription') }}
                    </p>
                    <button
                        type="button"
                        class="mt-5 rounded-xl bg-emerald-700 px-5 py-3 font-semibold text-white hover:bg-emerald-800"
                        @click="createService"
                    >
                        {{ t('services.addFirstService') }}
                    </button>
                </div>
                <ul v-else class="mt-6 space-y-3">
                    <li
                        v-for="service in services"
                        :key="service.id"
                        class="rounded-2xl border p-4 transition sm:p-5"
                        :class="
                            editingId === service.id
                                ? 'border-emerald-500 bg-emerald-50/50 ring-2 ring-emerald-100'
                                : 'border-slate-200 hover:border-slate-300'
                        "
                    >
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-xs font-bold text-slate-400">
                                        #{{ service.sortOrder }}
                                    </span>
                                    <h3 class="font-bold text-slate-950">{{ service.name }}</h3>
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-semibold capitalize"
                                        :class="
                                            service.status === 'active'
                                                ? 'bg-emerald-100 text-emerald-800'
                                                : 'bg-slate-100 text-slate-700'
                                        "
                                    >
                                        {{
                                            service.status === 'active'
                                                ? t('services.statusActive')
                                                : t('services.statusInactive')
                                        }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-slate-600">
                                    {{ service.description || t('services.noPublicDescription') }}
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-wrap gap-2">
                                <button
                                    type="button"
                                    :disabled="busy"
                                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold disabled:opacity-60"
                                    @click="edit(service)"
                                >
                                    {{ t('services.edit') }}
                                </button>
                                <button
                                    type="button"
                                    :disabled="busy"
                                    class="rounded-lg border px-3 py-2 text-sm font-semibold disabled:opacity-60"
                                    :class="
                                        service.status === 'active'
                                            ? 'border-amber-300 text-amber-800'
                                            : 'border-emerald-300 text-emerald-800'
                                    "
                                    @click="changeStatus(service)"
                                >
                                    {{
                                        service.status === 'active'
                                            ? t('services.deactivate')
                                            : t('services.activate')
                                    }}
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>
            </section>

            <section
                ref="formPanel"
                class="h-fit scroll-mt-6 rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm xl:sticky xl:top-6 xl:p-6"
            >
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">
                    {{ editingId ? t('services.editingLabel') : t('services.newServiceLabel') }}
                </p>
                <h2 class="mt-2 text-xl font-bold text-slate-950">
                    {{ editingId ? t('services.editService') : t('services.addServiceTitle') }}
                </h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    {{ t('services.formDescription') }}
                </p>
                <form class="mt-5 space-y-4" @submit.prevent="submit">
                    <label class="block">
                        <span class="text-sm font-semibold text-slate-800">{{
                            t('services.serviceName')
                        }}</span>
                        <input
                            ref="nameInput"
                            v-model.trim="form.name"
                            required
                            maxlength="200"
                            class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                        />
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-slate-800">{{
                            t('services.customerDescription')
                        }}</span>
                        <textarea
                            v-model.trim="form.description"
                            maxlength="2000"
                            rows="5"
                            class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                        />
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold text-slate-800">{{
                            t('services.displayOrder')
                        }}</span>
                        <input
                            v-model.number="form.sort_order"
                            type="number"
                            min="0"
                            max="10000"
                            required
                            class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                        />
                        <span class="mt-1.5 block text-xs leading-5 text-slate-500">
                            {{ t('services.displayOrderHelp') }}
                        </span>
                    </label>
                    <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row">
                        <button
                            type="submit"
                            :disabled="busy"
                            class="min-h-12 flex-1 rounded-xl bg-emerald-700 px-5 py-3 font-semibold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{
                                busy
                                    ? t('services.saving')
                                    : editingId
                                      ? t('services.saveChanges')
                                      : t('services.addServiceButton')
                            }}
                        </button>
                        <button
                            v-if="editingId"
                            type="button"
                            :disabled="busy"
                            class="rounded-xl border border-slate-300 px-5 py-3 font-semibold disabled:opacity-60"
                            @click="resetForm"
                        >
                            {{ t('services.cancel') }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </DashboardShell>
</template>

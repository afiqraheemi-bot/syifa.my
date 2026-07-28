<script setup>
import { router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import { createDashboardNavigation, DashboardShell } from '../../../Shared/Dashboard';

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
const feedback = ref('');
const error = ref('');
const form = reactive({ name: '', description: '', sort_order: 0, version: null });

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
}

function submit() {
    if (busy.value) return;
    busy.value = true;
    feedback.value = '';
    error.value = '';
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            feedback.value = editingId.value ? 'Service updated.' : 'Service created.';
            resetForm();
        },
        onError: (errors) => {
            error.value = Object.values(errors)[0] ?? 'The service could not be saved.';
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
                ? `Activate ${service.name}?`
                : `Deactivate ${service.name}? It will no longer be selectable for new bookings.`,
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
                feedback.value = activating ? 'Service activated.' : 'Service deactivated.';
            },
            onError: (errors) => {
                error.value =
                    Object.values(errors)[0] ?? 'The service status could not be changed.';
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

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">Clinic services</h2>
                        <p class="mt-1 text-sm text-slate-600">
                            Active services appear in governed Website and Booking selections.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-xl bg-emerald-700 px-4 py-2 font-semibold text-white"
                        @click="resetForm"
                    >
                        New service
                    </button>
                </div>

                <div
                    v-if="services.length === 0"
                    class="mt-6 rounded-xl border border-dashed border-slate-300 p-8 text-center text-slate-600"
                >
                    No services configured yet. Add the first clinic service to continue onboarding.
                </div>
                <ul v-else class="mt-6 space-y-3">
                    <li
                        v-for="service in services"
                        :key="service.id"
                        class="rounded-xl border border-slate-200 p-4"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="font-semibold text-slate-950">{{ service.name }}</h3>
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-semibold capitalize"
                                        :class="
                                            service.status === 'active'
                                                ? 'bg-emerald-100 text-emerald-800'
                                                : 'bg-slate-100 text-slate-700'
                                        "
                                    >
                                        {{ service.status }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-slate-600">
                                    {{ service.description || 'No public description.' }}
                                </p>
                                <p class="mt-2 text-xs text-slate-500">
                                    Display order {{ service.sortOrder }}
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    :disabled="busy"
                                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold disabled:opacity-60"
                                    @click="edit(service)"
                                >
                                    Edit
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
                                    {{ service.status === 'active' ? 'Deactivate' : 'Activate' }}
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>
            </section>

            <section class="h-fit rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-950">
                    {{ editingId ? 'Edit service' : 'Add service' }}
                </h2>
                <form class="mt-5 space-y-4" @submit.prevent="submit">
                    <label class="block">
                        <span class="text-sm font-medium text-slate-800">Service name</span>
                        <input
                            v-model.trim="form.name"
                            required
                            maxlength="200"
                            class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                        />
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium text-slate-800">Public description</span>
                        <textarea
                            v-model.trim="form.description"
                            maxlength="2000"
                            rows="5"
                            class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                        />
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium text-slate-800">Display order</span>
                        <input
                            v-model.number="form.sort_order"
                            type="number"
                            min="0"
                            max="10000"
                            required
                            class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                        />
                    </label>
                    <div class="flex gap-3">
                        <button
                            type="submit"
                            :disabled="busy"
                            class="rounded-xl bg-emerald-700 px-5 py-3 font-semibold text-white disabled:opacity-60"
                        >
                            {{ busy ? 'Saving…' : 'Save service' }}
                        </button>
                        <button
                            v-if="editingId"
                            type="button"
                            :disabled="busy"
                            class="rounded-xl border border-slate-300 px-5 py-3 font-semibold disabled:opacity-60"
                            @click="resetForm"
                        >
                            Cancel
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </DashboardShell>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { createDashboardNavigation, DashboardShell } from '../../../Shared/Dashboard';

const props = defineProps({
    navigation: { type: Array, required: true },
    breadcrumbs: { type: Array, required: true },
    pageTitle: { type: String, required: true },
    pageDescription: { type: String, required: true },
    identityName: { type: String, required: true },
    contextLabel: { type: String, required: true },
    job: { type: Object, required: true },
    domain: { type: Object, default: null },
    routingRecords: { type: Array, default: () => [] },
    operationsUrl: { type: String, required: true },
    backUrl: { type: String, required: true },
});

const hostname = ref('');
const busy = ref(false);
const feedback = ref('');
const error = ref('');
const statusLabel = computed(
    () =>
        ({
            verification_pending: 'Menunggu pengesahan',
            verified: 'Disahkan',
            active: 'Aktif',
            failing: 'Bermasalah',
            detached: 'Diputuskan',
        })[props.domain?.status] ?? 'Belum dikonfigurasi',
);
const navigation = createDashboardNavigation(props.navigation);

async function copy(value, label = 'Nilai DNS') {
    feedback.value = '';
    error.value = '';
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(value);
        } else {
            const field = document.createElement('textarea');
            field.value = value;
            field.style.position = 'fixed';
            field.style.opacity = '0';
            document.body.appendChild(field);
            field.select();
            const copied = document.execCommand('copy');
            field.remove();
            if (!copied) throw new Error('Copy unavailable');
        }
        feedback.value = `${label} berjaya disalin.`;
    } catch {
        error.value = 'Salinan automatik tidak tersedia. Pilih dan salin nilai secara manual.';
    }
}

function submit(path, payload, confirmation = null) {
    if (busy.value || (confirmation && !window.confirm(confirmation))) return;

    busy.value = true;
    feedback.value = '';
    error.value = '';
    router.post(path, payload, {
        preserveScroll: true,
        onSuccess: () => {
            feedback.value = 'Custom domain berjaya dikemas kini.';
            hostname.value = '';
        },
        onError: (errors) => {
            error.value =
                Object.values(errors)[0] ?? 'Operasi custom domain tidak dapat diselesaikan.';
        },
        onFinish: () => {
            busy.value = false;
        },
    });
}

function requestDomain() {
    submit(props.operationsUrl, { hostname: hostname.value });
}

function verifyDomain() {
    submit(`${props.operationsUrl}/verify`, {
        domain_id: props.domain.id,
        version: props.domain.version,
    });
}

function activateDomain() {
    submit(
        `${props.operationsUrl}/activate`,
        { domain_id: props.domain.id, version: props.domain.version },
        'Aktifkan custom domain ini untuk website awam?',
    );
}

function detachDomain() {
    submit(
        `${props.operationsUrl}/detach`,
        { domain_id: props.domain.id, version: props.domain.version },
        'Putuskan custom domain ini? Routing awam melalui domain tersebut akan dihentikan.',
    );
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
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div class="rounded-full bg-violet-100 px-4 py-2 text-sm font-semibold text-violet-900">
                Add-on terurus
            </div>
            <a
                :href="backUrl"
                class="rounded-xl border border-slate-300 bg-white px-4 py-2 font-semibold text-slate-800 transition hover:border-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            >
                Kembali ke tugasan
            </a>
        </div>

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

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="mb-6 max-w-2xl">
                <h2 class="text-xl font-semibold text-slate-950">Sambungan custom domain</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Alamat asal SYIFA.my kekal tersedia. Sambungkan custom domain hanya selepas
                    add-on disahkan dan klinik mempunyai akses kepada pengurusan DNS.
                </p>
            </div>

            <template v-if="!domain">
                <form
                    class="flex max-w-3xl flex-col gap-3 sm:flex-row"
                    @submit.prevent="requestDomain"
                >
                    <label class="flex-1">
                        <span class="text-sm font-medium text-slate-800">Hostname domain</span>
                        <input
                            v-model.trim="hostname"
                            required
                            pattern="([a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,63}"
                            autocomplete="url"
                            placeholder="www.yourclinic.my"
                            class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                        />
                    </label>
                    <button
                        type="submit"
                        :disabled="busy"
                        class="self-stretch rounded-xl bg-emerald-700 px-5 py-3 font-semibold text-white disabled:opacity-60 sm:self-end"
                    >
                        {{ busy ? 'Memohon…' : 'Mohon domain' }}
                    </button>
                </form>
            </template>

            <template v-else>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium uppercase tracking-wide text-slate-500">
                            Domain klinik
                        </p>
                        <h2 class="mt-1 break-all text-xl font-semibold text-slate-950">
                            {{ domain.hostname }}
                        </h2>
                    </div>
                    <span
                        class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium capitalize text-slate-700"
                    >
                        {{ statusLabel }}
                    </span>
                </div>

                <div
                    v-if="domain.status === 'verification_pending'"
                    class="mt-6 rounded-xl bg-amber-50 p-4"
                >
                    <p class="font-semibold text-amber-950">Pengesahan pemilikan DNS diperlukan</p>
                    <dl class="mt-3 space-y-3 text-sm">
                        <div>
                            <dt class="text-amber-800">Nama rekod TXT</dt>
                            <dd class="mt-1 flex items-start justify-between gap-3">
                                <span class="break-all font-mono text-amber-950">{{
                                    domain.verificationName
                                }}</span>
                                <button
                                    type="button"
                                    class="shrink-0 font-semibold text-amber-900 underline"
                                    @click="copy(domain.verificationName, 'Nama rekod TXT')"
                                >
                                    Salin
                                </button>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-amber-800">Nilai rekod TXT</dt>
                            <dd class="mt-1 flex items-start justify-between gap-3">
                                <span class="break-all font-mono text-amber-950">{{
                                    domain.verificationValue
                                }}</span>
                                <button
                                    type="button"
                                    class="shrink-0 font-semibold text-amber-900 underline"
                                    @click="copy(domain.verificationValue, 'Nilai rekod TXT')"
                                >
                                    Salin
                                </button>
                            </dd>
                        </div>
                    </dl>
                    <button
                        type="button"
                        :disabled="busy"
                        class="mt-4 rounded-xl bg-amber-900 px-4 py-2 font-semibold text-white disabled:opacity-60"
                        @click="verifyDomain"
                    >
                        {{ busy ? 'Memeriksa…' : 'Semak DNS' }}
                    </button>
                </div>

                <div
                    v-if="['verification_pending', 'verified'].includes(domain.status)"
                    class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4"
                >
                    <p class="font-semibold text-slate-950">Halakan trafik ke SYIFA.my</p>
                    <p class="mt-1 text-sm leading-6 text-slate-600">
                        Tambah rekod berikut pada penyedia DNS klinik. Kekalkan rekod pemilikan TXT
                        selagi domain disambungkan. Perubahan DNS mungkin mengambil masa.
                    </p>
                    <div v-if="routingRecords.length" class="mt-4 overflow-x-auto">
                        <table class="w-full min-w-[36rem] text-left text-sm">
                            <thead class="text-slate-500">
                                <tr>
                                    <th class="pb-2 pr-4 font-medium">Type</th>
                                    <th class="pb-2 pr-4 font-medium">Name</th>
                                    <th class="pb-2 pr-4 font-medium">Value</th>
                                    <th class="pb-2 font-medium">
                                        <span class="sr-only">Copy</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 font-mono text-slate-900">
                                <tr
                                    v-for="record in routingRecords"
                                    :key="`${record.type}-${record.value}`"
                                >
                                    <td class="py-3 pr-4">{{ record.type }}</td>
                                    <td class="break-all py-3 pr-4">{{ record.name }}</td>
                                    <td class="break-all py-3 pr-4">{{ record.value }}</td>
                                    <td class="py-3 text-right">
                                        <button
                                            type="button"
                                            class="font-sans font-semibold text-emerald-700 hover:text-emerald-900"
                                            @click="copy(record.value, 'Nilai routing')"
                                        >
                                            Salin
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="mt-3 text-sm font-medium text-amber-800">
                        Routing target production belum dikonfigurasi. Minta operator platform
                        menetapkan PUBLIC_WEBSITE_CUSTOM_DOMAIN_TARGETS sebelum aktivasi.
                    </p>
                    <p
                        v-if="
                            routingRecords.some(
                                (record) =>
                                    record.type === 'CNAME' && record.name.split('.').length === 2,
                            )
                        "
                        class="mt-3 text-xs leading-5 text-slate-500"
                    >
                        Untuk domain root, gunakan ALIAS, ANAME atau CNAME flattening yang disokong
                        oleh penyedia DNS jika CNAME standard tidak tersedia.
                    </p>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <button
                        v-if="domain.status === 'verified' && routingRecords.length"
                        type="button"
                        :disabled="busy"
                        class="rounded-xl bg-emerald-700 px-4 py-2 font-semibold text-white disabled:opacity-60"
                        @click="activateDomain"
                    >
                        Aktifkan domain
                    </button>
                    <a
                        v-if="domain.status === 'active'"
                        :href="`https://${domain.hostname}`"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="rounded-xl bg-emerald-700 px-4 py-2 font-semibold text-white"
                    >
                        Buka website
                    </a>
                    <button
                        v-if="['verified', 'active', 'failing'].includes(domain.status)"
                        type="button"
                        :disabled="busy"
                        class="rounded-xl border border-red-300 px-4 py-2 font-semibold text-red-700 disabled:opacity-60"
                        @click="detachDomain"
                    >
                        Putuskan domain
                    </button>
                </div>
            </template>
        </section>
    </DashboardShell>
</template>

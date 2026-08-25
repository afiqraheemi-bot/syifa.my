<script setup>
import { router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { createDashboardNavigation, DashboardShell } from '../../Shared/Dashboard/index.js';

const props = defineProps({
    navigation: { type: Array, required: true },
    breadcrumbs: { type: Array, required: true },
    pageTitle: { type: String, required: true },
    pageDescription: { type: String, required: true },
    identityName: { type: String, default: null },
    contextLabel: { type: String, required: true },
    posts: { type: Object, required: true },
    entitled: Boolean,
    filters: { type: Object, required: true },
    summary: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    role: { type: String, required: true },
    clinicName: { type: String, default: null },
    indexUrl: { type: String, required: true },
    createUrl: { type: String, required: true },
    showUpgrade: Boolean,
});

const navigation = createDashboardNavigation(props.navigation);
const filters = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    category: props.filters.category ?? '',
});
const labels = {
    draft: 'Draf',
    in_review: 'Dalam semakan',
    correction_required: 'Perlu pembetulan',
    scheduled: 'Dijadualkan',
    published: 'Diterbitkan',
    archived: 'Diarkibkan',
};
const summaryStatuses = ['draft', 'in_review', 'scheduled', 'published'];
const hasFilters = computed(() => Boolean(filters.search || filters.status || filters.category));
const totalPosts = computed(() =>
    Object.values(props.summary).reduce((total, count) => total + Number(count || 0), 0),
);

function applyFilters() {
    router.get(props.indexUrl, filters, { preserveState: true, replace: true });
}
function resetFilters() {
    Object.assign(filters, { search: '', status: '', category: '' });
    applyFilters();
}
function statusClass(status) {
    return {
        draft: 'bg-slate-100 text-slate-700',
        in_review: 'bg-sky-100 text-sky-800',
        correction_required: 'bg-amber-100 text-amber-800',
        scheduled: 'bg-violet-100 text-violet-800',
        published: 'bg-emerald-100 text-emerald-800',
        archived: 'bg-rose-100 text-rose-800',
    }[status];
}
function displayDate(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat('ms-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(date);
}
function paginationLabel(label) {
    return String(label).replace('&laquo;', '‹').replace('&raquo;', '›');
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
        <template #page-actions>
            <a
                v-if="entitled && ['clinic_owner', 'website_designer'].includes(role)"
                :href="createUrl"
                class="inline-flex min-h-12 items-center justify-center rounded-xl bg-emerald-700 px-5 py-3 font-bold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
            >
                + Artikel baharu
            </a>
        </template>

        <div class="mx-auto max-w-7xl space-y-6">
            <section
                v-if="entitled"
                class="overflow-hidden rounded-[1.75rem] border border-emerald-950/10 bg-emerald-950 px-6 py-7 text-white shadow-sm sm:px-8"
            >
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="text-xs font-bold uppercase tracking-[0.22em] text-lime-300">
                            Kandungan kesihatan
                        </p>
                        <h2 class="mt-3 text-2xl font-bold sm:text-3xl">
                            Bina kepercayaan melalui artikel berguna
                        </h2>
                        <p class="mt-2 leading-7 text-emerald-50/80">
                            {{
                                clinicName
                                    ? `Urus artikel khusus untuk ${clinicName} dalam assignment ini.`
                                    : 'Tulis, semak dan terbitkan artikel untuk laman web klinik.'
                            }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-5 py-4">
                        <p class="text-xs text-emerald-50/65">Jumlah artikel</p>
                        <p class="mt-1 text-3xl font-black text-lime-300">{{ totalPosts }}</p>
                    </div>
                </div>
            </section>

            <section
                v-if="!entitled"
                class="rounded-[1.5rem] border border-amber-300 bg-amber-50 p-6"
            >
                <h2 class="font-black">Blog tidak tersedia untuk klinik ini</h2>
                <p class="mt-1 leading-6 text-amber-950/80">
                    <template v-if="showUpgrade">
                        Naik taraf ke Syifa Pro untuk menerbitkan artikel dengan metadata, halaman
                        artikel dan sitemap yang diurus secara tersusun.
                    </template>
                    <template v-else>
                        Pakej klinik yang ditugaskan tidak mempunyai Blog. Website Designer tidak
                        boleh mengubah langganan klinik.
                    </template>
                </p>
                <a
                    v-if="showUpgrade"
                    href="/dashboard/subscription"
                    class="mt-3 inline-block font-bold text-emerald-800 hover:underline"
                    >Lihat pilihan naik taraf</a
                >
            </section>

            <section class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Ringkasan artikel">
                <div
                    v-for="status in summaryStatuses"
                    :key="status"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <p class="text-sm text-slate-500">{{ labels[status] }}</p>
                    <p class="mt-1 text-2xl font-black">{{ summary[status] ?? 0 }}</p>
                </div>
            </section>

            <form
                class="grid gap-4 rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-[minmax(0,2fr)_minmax(10rem,1fr)_minmax(10rem,1fr)_auto]"
                @submit.prevent="applyFilters"
            >
                <label>
                    <span class="text-sm font-bold">Cari artikel</span>
                    <input
                        v-model="filters.search"
                        class="mt-1 w-full rounded-xl border border-slate-300 p-3 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100"
                        placeholder="Tajuk atau ringkasan"
                    />
                </label>
                <label>
                    <span class="text-sm font-bold">Status</span>
                    <select
                        v-model="filters.status"
                        class="mt-1 w-full rounded-xl border border-slate-300 p-3"
                    >
                        <option value="">Semua status</option>
                        <option v-for="(label, key) in labels" :key="key" :value="key">
                            {{ label }}
                        </option>
                    </select>
                </label>
                <label>
                    <span class="text-sm font-bold">Kategori</span>
                    <select
                        v-model="filters.category"
                        class="mt-1 w-full rounded-xl border border-slate-300 p-3"
                    >
                        <option value="">Semua kategori</option>
                        <option v-for="category in categories" :key="category" :value="category">
                            {{ category }}
                        </option>
                    </select>
                </label>
                <div class="flex self-end gap-2">
                    <button
                        class="min-h-12 rounded-xl bg-slate-950 px-5 py-3 font-bold text-white hover:bg-slate-800"
                    >
                        Tapis
                    </button>
                    <button
                        v-if="hasFilters"
                        type="button"
                        class="min-h-12 rounded-xl px-3 py-3 font-bold text-slate-600 hover:bg-slate-100"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </div>
            </form>

            <div
                class="overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-sm"
            >
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[48rem] text-left">
                        <thead>
                            <tr
                                class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wider text-slate-500"
                            >
                                <th class="px-5 py-4">Artikel</th>
                                <th class="px-5 py-4">Status</th>
                                <th class="px-5 py-4">Penulis</th>
                                <th class="px-5 py-4">Tarikh</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="post in posts.data"
                                :key="post.id"
                                class="border-b border-slate-100 transition last:border-0 hover:bg-slate-50/80"
                            >
                                <td class="px-5 py-5">
                                    <a
                                        :href="`${indexUrl}/${post.id}`"
                                        class="font-bold text-slate-950 hover:text-emerald-700 hover:underline"
                                        >{{ post.title }}</a
                                    >
                                    <p class="mt-1 text-sm text-slate-500">{{ post.category }}</p>
                                </td>
                                <td class="px-5 py-5">
                                    <span
                                        class="rounded-full px-3 py-1.5 text-xs font-bold"
                                        :class="statusClass(post.status)"
                                        >{{ labels[post.status] ?? post.status }}</span
                                    >
                                </td>
                                <td class="px-5 py-5 text-sm text-slate-700">
                                    {{ post.author_name }}
                                </td>
                                <td class="px-5 py-5 text-sm text-slate-600">
                                    {{
                                        displayDate(
                                            post.published_at ??
                                                post.scheduled_at ??
                                                post.last_changed_at,
                                        )
                                    }}
                                </td>
                            </tr>
                            <tr v-if="!posts.data.length">
                                <td colspan="4" class="p-12 text-center">
                                    <strong class="text-lg">{{
                                        hasFilters ? 'Tiada artikel ditemui' : 'Belum ada artikel'
                                    }}</strong>
                                    <p
                                        class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500"
                                    >
                                        {{
                                            hasFilters
                                                ? 'Cuba ubah atau kosongkan penapis carian.'
                                                : 'Mulakan dengan artikel yang menjawab soalan lazim pesakit.'
                                        }}
                                    </p>
                                    <button
                                        v-if="hasFilters"
                                        type="button"
                                        class="mt-4 font-bold text-emerald-700 hover:underline"
                                        @click="resetFilters"
                                    >
                                        Kosongkan penapis
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <nav
                    v-if="posts.links?.length > 3"
                    aria-label="Navigasi halaman artikel"
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-4"
                >
                    <p class="text-sm text-slate-500">
                        Memaparkan {{ posts.from }}–{{ posts.to }} daripada
                        {{ posts.total }} artikel
                    </p>
                    <div class="flex flex-wrap gap-1">
                        <component
                            :is="link.url ? 'a' : 'span'"
                            v-for="link in posts.links"
                            :key="link.label"
                            :href="link.url"
                            class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg px-3 text-sm font-semibold"
                            :class="
                                link.active
                                    ? 'bg-emerald-700 text-white'
                                    : link.url
                                      ? 'text-slate-700 hover:bg-slate-100'
                                      : 'cursor-not-allowed text-slate-300'
                            "
                        >
                            {{ paginationLabel(link.label) }}
                        </component>
                    </div>
                </nav>
            </div>
        </div>
    </DashboardShell>
</template>

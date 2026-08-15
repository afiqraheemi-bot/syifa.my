<script setup>
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';
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
    role: { type: String, required: true },
});
const navigation = createDashboardNavigation(props.navigation);
const filters = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    category: props.filters.category ?? '',
});
function applyFilters() {
    router.get('/dashboard/blog', filters, { preserveState: true, replace: true });
}
const labels = {
    draft: 'Draf',
    in_review: 'Dalam semakan',
    correction_required: 'Perlu pembetulan',
    scheduled: 'Dijadualkan',
    published: 'Diterbitkan',
    archived: 'Diarkibkan',
};
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
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="font-bold text-emerald-700">Website klinik</p>
                    <h1 class="text-3xl font-black">Blog</h1>
                    <p class="mt-2 text-slate-600">
                        Urus artikel kesihatan, penerbitan dan metadata SEO.
                    </p>
                </div>
                <a
                    v-if="entitled && ['clinic_owner', 'website_designer'].includes(role)"
                    href="/dashboard/blog/create"
                    class="rounded-xl bg-emerald-700 px-5 py-3 font-bold text-white"
                    >Artikel baharu</a
                >
            </header>
            <section
                v-if="!entitled"
                class="mt-6 rounded-2xl border border-amber-300 bg-amber-50 p-5"
            >
                <h2 class="font-black">Blog tersedia dengan Syifa Standard</h2>
                <p class="mt-1">
                    Naik taraf untuk menerbitkan artikel dengan metadata, halaman artikel dan
                    sitemap yang diurus secara tersusun.
                </p>
                <a
                    href="/dashboard/subscription"
                    class="mt-3 inline-block font-bold text-emerald-800"
                    >Lihat pilihan naik taraf</a
                >
            </section>
            <section
                class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4"
                aria-label="Ringkasan artikel"
            >
                <div
                    v-for="status in ['draft', 'in_review', 'scheduled', 'published']"
                    :key="status"
                    class="rounded-2xl bg-white p-4 shadow-sm"
                >
                    <p class="text-sm text-slate-500">{{ labels[status] }}</p>
                    <p class="text-2xl font-black">{{ summary[status] ?? 0 }}</p>
                </div>
            </section>
            <form
                class="mt-6 grid gap-3 rounded-2xl bg-white p-4 sm:grid-cols-4"
                @submit.prevent="applyFilters"
            >
                <label class="sm:col-span-2"
                    ><span class="text-sm font-bold">Cari</span
                    ><input
                        v-model="filters.search"
                        class="mt-1 w-full rounded-lg border p-3"
                        placeholder="Tajuk atau ringkasan" /></label
                ><label
                    ><span class="text-sm font-bold">Status</span
                    ><select v-model="filters.status" class="mt-1 w-full rounded-lg border p-3">
                        <option value="">Semua status</option>
                        <option v-for="(label, key) in labels" :key="key" :value="key">
                            {{ label }}
                        </option>
                    </select></label
                ><button class="self-end rounded-lg border px-4 py-3 font-bold">Tapis</button>
            </form>
            <div class="mt-4 overflow-x-auto rounded-2xl bg-white shadow-sm">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b">
                            <th class="p-4">Artikel</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">Penulis</th>
                            <th class="p-4">Tarikh</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="post in posts.data" :key="post.id" class="border-b">
                            <td class="p-4">
                                <a
                                    :href="`/dashboard/blog/${post.id}`"
                                    class="font-bold text-emerald-800"
                                    >{{ post.title }}</a
                                >
                                <p class="text-sm text-slate-500">{{ post.category }}</p>
                            </td>
                            <td class="p-4">
                                <span
                                    class="rounded-full bg-slate-100 px-3 py-1 text-sm font-bold"
                                    >{{ labels[post.status] }}</span
                                >
                            </td>
                            <td class="p-4">{{ post.author_name }}</td>
                            <td class="p-4">
                                {{ post.published_at ?? post.scheduled_at ?? post.last_changed_at }}
                            </td>
                        </tr>
                        <tr v-if="!posts.data.length">
                            <td colspan="4" class="p-10 text-center">
                                <strong>Belum ada artikel.</strong>
                                <p class="text-slate-500">
                                    Mulakan dengan artikel yang menjawab soalan lazim pesakit.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </DashboardShell>
</template>

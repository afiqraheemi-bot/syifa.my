<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref } from 'vue';
import { createDashboardNavigation, DashboardShell } from '../../Shared/Dashboard/index.js';
import WebsiteImageUpload from '../../Shared/Website/WebsiteImageUpload.vue';
const props = defineProps({
    navigation: { type: Array, required: true },
    breadcrumbs: { type: Array, required: true },
    pageTitle: { type: String, required: true },
    pageDescription: { type: String, required: true },
    identityName: { type: String, default: null },
    contextLabel: { type: String, required: true },
    post: { type: Object, default: null },
    role: { type: String, default: 'clinic_owner' },
    websites: { type: Array, default: () => [] },
    mediaUploadUrl: { type: String, default: null },
    assetUrlTemplate: { type: String, required: true },
    clinicName: { type: String, default: null },
    indexUrl: { type: String, required: true },
    storeUrl: { type: String, required: true },
    updateUrl: { type: String, default: null },
    transitionUrl: { type: String, default: null },
    canEdit: { type: Boolean, default: true },
});
const navigation = createDashboardNavigation(props.navigation);
const bodyEditor = ref(null);
const bodyError = ref('');
const form = useForm({
    version: props.post?.version ?? 1,
    website_id: props.post?.website_id ?? props.websites[0]?.id ?? null,
    title: props.post?.title ?? '',
    slug: props.post?.slug ?? '',
    excerpt: props.post?.excerpt ?? '',
    body: props.post?.body_html ?? '',
    featured_image_asset_id: props.post?.featured_image_asset_id ?? null,
    featured_image_alt_text: props.post?.featured_image_alt_text ?? '',
    category: props.post?.category ?? '',
    tags: props.post?.tags ?? [],
    meta_title: props.post?.meta_title ?? '',
    meta_description: props.post?.meta_description ?? '',
    canonical_url: props.post?.canonical_url ?? '',
    robots_directive: props.post?.robots_directive ?? 'index,follow',
    open_graph_title: props.post?.open_graph_title ?? '',
    open_graph_description: props.post?.open_graph_description ?? '',
});
const editing = computed(() => Boolean(props.post));
const statusLabel = computed(
    () =>
        ({
            draft: 'Draf',
            in_review: 'Dalam semakan',
            correction_required: 'Perlu pembetulan',
            scheduled: 'Dijadualkan',
            published: 'Diterbitkan',
            archived: 'Diarkibkan',
        })[props.post?.status] ?? props.post?.status,
);
const searchTitle = computed(() => form.meta_title.trim() || form.title.trim() || 'Tajuk artikel');
const searchDescription = computed(
    () =>
        form.meta_description.trim() ||
        form.excerpt.trim() ||
        'Ringkasan artikel akan dipaparkan di sini.',
);
const transitionForm = useForm({
    version: props.post?.version ?? 1,
    action: '',
    scheduled_at: null,
});
const transitionOptions = computed(() => {
    if (!editing.value) return [];
    if (props.role === 'super_admin') {
        return props.post.status === 'archived'
            ? []
            : [{ action: 'archive', label: 'Arkibkan', destructive: true }];
    }
    if (props.role === 'website_designer') {
        return ['draft', 'correction_required'].includes(props.post.status)
            ? [{ action: 'submit_review', label: 'Hantar kepada Clinic Owner untuk semakan' }]
            : [];
    }

    const options = {
        draft: [
            { action: 'submit_review', label: 'Hantar semakan' },
            { action: 'schedule', label: 'Jadualkan penerbitan' },
            { action: 'publish', label: 'Terbitkan' },
            { action: 'archive', label: 'Arkibkan', destructive: true },
        ],
        correction_required: [
            { action: 'submit_review', label: 'Hantar semula untuk semakan' },
            { action: 'schedule', label: 'Jadualkan penerbitan' },
            { action: 'publish', label: 'Terbitkan' },
            { action: 'archive', label: 'Arkibkan', destructive: true },
        ],
        in_review: [
            { action: 'correction', label: 'Minta pembetulan' },
            { action: 'schedule', label: 'Jadualkan penerbitan' },
            { action: 'publish', label: 'Terbitkan' },
            { action: 'archive', label: 'Arkibkan', destructive: true },
        ],
        scheduled: [
            { action: 'publish', label: 'Terbitkan sekarang' },
            { action: 'archive', label: 'Arkibkan', destructive: true },
        ],
        published: [
            { action: 'publish', label: 'Terbitkan perubahan' },
            { action: 'archive', label: 'Arkibkan', destructive: true },
        ],
        archived: [],
    };

    return options[props.post.status] ?? [];
});
const selectedUploadUrl = computed(
    () =>
        props.mediaUploadUrl ??
        props.websites.find((website) => website.id === form.website_id)?.upload_url ??
        '',
);
function slugify() {
    if (!editing.value)
        form.slug = form.title
            .toLowerCase()
            .normalize('NFKD')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
}
function save() {
    if (!props.canEdit) return;
    syncBody();
    if (!bodyEditor.value?.innerText.trim()) {
        bodyError.value = 'Tulis isi artikel sebelum menyimpan.';
        bodyEditor.value?.focus();
        return;
    }

    slugify();
    editing.value ? form.patch(props.updateUrl) : form.post(props.storeUrl);
}
function syncBody() {
    form.body = bodyEditor.value?.innerHTML ?? '';
    if (bodyEditor.value?.innerText.trim()) bodyError.value = '';
}
function formatBody(command, value = null) {
    bodyEditor.value?.focus();
    document.execCommand(command, false, value);
    syncBody();
}
function setBodyBlock(event) {
    formatBody('formatBlock', event.target.value);
    event.target.value = 'p';
}
onMounted(() => {
    nextTick(() => {
        if (bodyEditor.value) bodyEditor.value.innerHTML = form.body;
    });
});
function transition(action) {
    transitionForm.action = action;
    transitionForm.version = props.post.version;
    transitionForm.post(props.transitionUrl, {
        preserveScroll: true,
    });
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
        <form
            class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[minmax(0,1fr)_19rem]"
            @submit.prevent="save"
        >
            <div class="space-y-5">
                <section class="rounded-2xl bg-white p-5 shadow-sm sm:p-7">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <a :href="indexUrl" class="text-sm font-bold text-emerald-800"
                                >← Kembali ke Blog</a
                            >
                            <h1 class="mt-3 text-2xl font-black sm:text-3xl">
                                {{ editing ? 'Edit artikel' : 'Tulis artikel baharu' }}
                            </h1>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                                {{
                                    clinicName
                                        ? `Artikel ini khusus untuk ${clinicName} dalam assignment semasa.`
                                        : 'Fokus pada maklumat yang berguna untuk pesakit. Tetapan carian akan dijana secara automatik.'
                                }}
                            </p>
                        </div>
                        <p
                            v-if="editing"
                            class="rounded-full bg-emerald-50 px-3 py-1 text-sm font-bold text-emerald-800"
                        >
                            {{ statusLabel }}
                        </p>
                    </div>
                </section>

                <section class="space-y-5 rounded-2xl bg-white p-5 shadow-sm sm:p-7">
                    <div>
                        <p class="text-xs font-black uppercase tracking-wider text-emerald-700">
                            Langkah 1
                        </p>
                        <h2 class="mt-1 text-xl font-black">Maklumat artikel</h2>
                    </div>
                    <label class="block font-bold"
                        >Apa tajuk artikel ini?<input
                            v-model="form.title"
                            required
                            maxlength="200"
                            placeholder="Contoh: 5 cara menjaga tekanan darah"
                            class="mt-2 w-full rounded-xl border border-slate-300 p-3"
                            @blur="slugify"
                        /><small class="mt-1 block font-normal text-slate-500"
                            >Gunakan tajuk yang jelas dan mudah difahami pesakit.</small
                        ></label
                    >
                    <label class="block font-bold"
                        >Ringkasan pendek<textarea
                            v-model="form.excerpt"
                            required
                            maxlength="600"
                            rows="3"
                            placeholder="Terangkan manfaat artikel ini dalam 1–2 ayat."
                            class="mt-2 w-full rounded-xl border border-slate-300 p-3"
                        ></textarea
                        ><small class="mt-1 block font-normal text-slate-500"
                            >Ringkasan ini muncul pada kad artikel dan hasil carian.</small
                        ></label
                    >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="font-bold"
                            >Kategori<input
                                v-model="form.category"
                                required
                                list="blog-categories"
                                placeholder="Contoh: Kesihatan keluarga"
                                class="mt-2 w-full rounded-xl border border-slate-300 p-3"
                        /></label>
                        <datalist id="blog-categories">
                            <option value="Kesihatan Keluarga" />
                            <option value="Pemeriksaan Kesihatan" />
                            <option value="Pemakanan" />
                            <option value="Kesihatan Wanita" />
                            <option value="Kesihatan Kanak-kanak" />
                        </datalist>
                    </div>
                    <details class="rounded-xl border border-slate-200 p-4">
                        <summary class="cursor-pointer font-bold text-slate-700">
                            Tetapan pautan (pilihan)
                        </summary>
                        <label class="mt-3 block text-sm font-bold"
                            >Alamat artikel<input
                                v-model="form.slug"
                                required
                                pattern="[a-z0-9-]+"
                                class="mt-2 w-full rounded-xl border border-slate-300 p-3"
                            /><small class="mt-1 block font-normal text-slate-500"
                                >Dijana secara automatik daripada tajuk. Biasanya tidak perlu
                                diubah.</small
                            ></label
                        >
                    </details>
                </section>

                <section class="space-y-4 rounded-2xl bg-white p-5 shadow-sm sm:p-7">
                    <div>
                        <p class="text-xs font-black uppercase tracking-wider text-emerald-700">
                            Langkah 2
                        </p>
                        <h2 class="mt-1 text-xl font-black">Tulis kandungan</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            Mulakan dengan penerangan ringkas, pecahkan isi kepada tajuk kecil dan
                            akhiri dengan langkah yang pesakit boleh ambil.
                        </p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 p-4 text-sm leading-6 text-emerald-950">
                        <strong>Cadangan susunan:</strong> Pengenalan → perkara penting → bila perlu
                        berjumpa doktor → nota keselamatan.
                    </div>
                    <div>
                        <p class="font-bold">Isi artikel</p>
                        <div
                            class="mt-2 overflow-hidden rounded-xl border bg-white"
                            :class="
                                bodyError || form.errors.body
                                    ? 'border-red-400'
                                    : 'border-slate-300'
                            "
                        >
                            <div
                                class="flex flex-wrap items-center gap-1 border-b border-slate-200 bg-slate-50 p-2"
                                role="toolbar"
                                aria-label="Format isi artikel"
                            >
                                <select
                                    class="h-9 rounded-lg border border-slate-300 bg-white px-2 text-sm font-semibold"
                                    aria-label="Gaya teks"
                                    @change="setBodyBlock"
                                >
                                    <option value="p">Perenggan</option>
                                    <option value="h2">Tajuk bahagian</option>
                                    <option value="h3">Tajuk kecil</option>
                                </select>
                                <button
                                    type="button"
                                    class="h-9 min-w-9 rounded-lg px-2 font-black hover:bg-slate-200"
                                    aria-label="Teks tebal"
                                    title="Teks tebal"
                                    @click="formatBody('bold')"
                                >
                                    B
                                </button>
                                <button
                                    type="button"
                                    class="h-9 min-w-9 rounded-lg px-2 italic hover:bg-slate-200"
                                    aria-label="Teks condong"
                                    title="Teks condong"
                                    @click="formatBody('italic')"
                                >
                                    I
                                </button>
                                <button
                                    type="button"
                                    class="h-9 rounded-lg px-3 text-sm font-bold hover:bg-slate-200"
                                    aria-label="Senarai berbutir"
                                    title="Senarai berbutir"
                                    @click="formatBody('insertUnorderedList')"
                                >
                                    • Senarai
                                </button>
                                <button
                                    type="button"
                                    class="h-9 rounded-lg px-3 text-sm font-bold hover:bg-slate-200"
                                    aria-label="Senarai bernombor"
                                    title="Senarai bernombor"
                                    @click="formatBody('insertOrderedList')"
                                >
                                    1. Senarai
                                </button>
                                <span class="mx-1 h-6 w-px bg-slate-300" aria-hidden="true"></span>
                                <button
                                    type="button"
                                    class="h-9 rounded-lg px-3 text-sm font-semibold hover:bg-slate-200"
                                    @click="formatBody('undo')"
                                >
                                    Undo
                                </button>
                                <button
                                    type="button"
                                    class="h-9 rounded-lg px-3 text-sm font-semibold hover:bg-slate-200"
                                    @click="formatBody('redo')"
                                >
                                    Redo
                                </button>
                            </div>
                            <div
                                ref="bodyEditor"
                                contenteditable="true"
                                role="textbox"
                                aria-multiline="true"
                                aria-describedby="body-help"
                                data-placeholder="Mulakan menulis artikel di sini…"
                                class="blog-body-editor min-h-[28rem] px-5 py-4 font-normal leading-7 text-slate-800 focus:outline-none sm:px-6"
                                @input="syncBody"
                                @blur="syncBody"
                            ></div>
                        </div>
                        <small id="body-help" class="mt-2 block font-normal text-slate-500">
                            Pilih teks untuk menjadikannya tajuk, senarai, tebal atau condong. Kod
                            HTML tidak perlu ditulis.
                        </small>
                        <p
                            v-if="bodyError || form.errors.body"
                            class="mt-2 text-sm font-semibold text-red-700"
                        >
                            {{ bodyError || form.errors.body }}
                        </p>
                    </div>
                </section>

                <section class="space-y-4 rounded-2xl bg-white p-5 shadow-sm sm:p-7">
                    <div>
                        <p class="text-xs font-black uppercase tracking-wider text-emerald-700">
                            Langkah 3
                        </p>
                        <h2 class="mt-1 text-xl font-black">Gambar utama</h2>
                        <p class="mt-2 text-sm text-slate-600">
                            Pilih satu gambar yang jelas dan berkaitan dengan topik artikel.
                        </p>
                    </div>
                    <WebsiteImageUpload
                        v-if="selectedUploadUrl"
                        v-model="form.featured_image_asset_id"
                        :upload-url="selectedUploadUrl"
                        :asset-url-template="assetUrlTemplate"
                        label="Muat naik gambar"
                        :aspect-ratio="16 / 9"
                        :aspect-ratio-options="[
                            { label: 'Landskap 16:9', value: 16 / 9 },
                            { label: 'Artikel 4:3', value: 4 / 3 },
                            { label: 'Petak 1:1', value: 1 },
                        ]"
                        :output-width="1200"
                        :output-width-options="[600, 1200, 1800, 2400]"
                        :max-bytes="5 * 1024 * 1024"
                        :disabled="form.processing || !canEdit"
                    />
                    <p v-else class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm">
                        Pilih klinik dengan assignment aktif sebelum memuat naik imej.
                    </p>
                    <label class="block font-bold"
                        >Terangkan gambar<input
                            v-model="form.featured_image_alt_text"
                            :required="Boolean(form.featured_image_asset_id)"
                            placeholder="Contoh: Doktor memeriksa tekanan darah pesakit"
                            class="mt-2 w-full rounded-xl border border-slate-300 p-3"
                        /><small class="mt-1 block font-normal text-slate-500"
                            >Membantu pengguna yang tidak dapat melihat gambar.</small
                        ></label
                    >
                </section>
            </div>

            <aside class="space-y-4 lg:sticky lg:top-5 lg:self-start">
                <section class="rounded-2xl bg-white p-5 shadow-sm">
                    <div class="flex items-center gap-2">
                        <span aria-hidden="true" class="text-emerald-700">✓</span>
                        <h2 class="font-black">Carian Google</h2>
                    </div>
                    <p class="mt-2 text-sm leading-5 text-slate-600">
                        Kami gunakan tajuk dan ringkasan artikel secara automatik.
                    </p>
                    <div class="mt-4 rounded-xl border border-slate-200 p-3">
                        <p class="truncate text-xs text-emerald-700">website-klinik.my › blog</p>
                        <p class="mt-1 line-clamp-2 font-bold text-blue-800">{{ searchTitle }}</p>
                        <p class="mt-1 line-clamp-3 text-xs leading-5 text-slate-600">
                            {{ searchDescription }}
                        </p>
                    </div>
                    <details class="mt-4 border-t border-slate-200 pt-4">
                        <summary class="cursor-pointer text-sm font-bold text-emerald-800">
                            Ubah paparan carian (pilihan)
                        </summary>
                        <label class="mt-4 block text-sm font-bold"
                            >Tajuk carian
                            <small class="font-normal text-slate-500"
                                >{{ form.meta_title.length }}/60</small
                            ><input
                                v-model="form.meta_title"
                                maxlength="60"
                                placeholder="Guna tajuk artikel"
                                class="mt-2 w-full rounded-xl border border-slate-300 p-3" /></label
                        ><label class="mt-4 block text-sm font-bold"
                            >Penerangan carian
                            <small class="font-normal text-slate-500"
                                >{{ form.meta_description.length }}/160</small
                            ><textarea
                                v-model="form.meta_description"
                                maxlength="160"
                                rows="3"
                                placeholder="Guna ringkasan artikel"
                                class="mt-2 w-full rounded-xl border border-slate-300 p-3"
                            ></textarea>
                        </label>
                        <details class="mt-4 rounded-lg bg-slate-50 p-3">
                            <summary class="cursor-pointer text-xs font-bold text-slate-600">
                                Tetapan teknikal
                            </summary>
                            <label class="mt-3 block text-xs font-bold"
                                >Pautan asal (canonical)<input
                                    v-model="form.canonical_url"
                                    type="text"
                                    inputmode="url"
                                    placeholder="Kosongkan jika tidak pasti, cth. klinikanda.syifa.my/blog/artikel"
                                    class="mt-2 w-full rounded-lg border border-slate-300 p-2" /></label
                            ><label class="mt-3 block text-xs font-bold"
                                >Paparan enjin carian<select
                                    v-model="form.robots_directive"
                                    class="mt-2 w-full rounded-lg border border-slate-300 p-2"
                                >
                                    <option value="index,follow">Benarkan muncul di carian</option>
                                    <option value="noindex,follow">
                                        Jangan paparkan di carian
                                    </option>
                                    <option value="noindex,nofollow">Sembunyikan sepenuhnya</option>
                                </select></label
                            >
                        </details>
                    </details>
                </section>
                <button
                    v-if="canEdit"
                    class="w-full rounded-xl bg-emerald-700 px-5 py-3 font-bold text-white"
                    :disabled="form.processing"
                >
                    {{ editing ? 'Simpan perubahan' : 'Simpan draf' }}
                </button>
                <p
                    v-else
                    class="rounded-xl border border-sky-200 bg-sky-50 p-3 text-sm leading-6 text-sky-950"
                >
                    Artikel telah dihantar untuk semakan. Clinic Owner akan meminta pembetulan atau
                    menerbitkannya.
                </p>
                <label
                    v-if="
                        role === 'clinic_owner' &&
                        editing &&
                        ['draft', 'in_review', 'correction_required'].includes(post.status)
                    "
                    class="block rounded-xl border border-slate-200 p-3 text-sm font-bold"
                >
                    Tarikh dan masa penerbitan
                    <input
                        v-model="transitionForm.scheduled_at"
                        type="datetime-local"
                        class="mt-2 w-full rounded-lg border border-slate-300 p-2"
                    />
                    <small class="mt-1 block font-normal text-slate-500"
                        >Isi sebelum menekan “Jadualkan penerbitan”.</small
                    >
                </label>
                <div v-if="transitionOptions.length" class="grid gap-2">
                    <button
                        v-for="option in transitionOptions"
                        :key="option.action"
                        type="button"
                        class="rounded-xl border p-3 font-bold disabled:cursor-not-allowed disabled:opacity-60"
                        :class="
                            option.destructive
                                ? 'border-red-200 text-red-700'
                                : 'border-slate-300 text-slate-800'
                        "
                        :disabled="transitionForm.processing"
                        @click="transition(option.action)"
                    >
                        {{ option.label }}
                    </button>
                </div>
                <p v-else-if="editing" class="rounded-xl bg-slate-100 p-3 text-sm text-slate-600">
                    Tiada tindakan status tersedia untuk artikel ini.
                </p>
                <p
                    v-if="transitionForm.errors.action || transitionForm.errors.scheduled_at"
                    role="alert"
                    class="rounded-xl bg-red-50 p-3 text-sm font-medium text-red-800"
                >
                    {{ transitionForm.errors.action || transitionForm.errors.scheduled_at }}
                </p>
                <p
                    v-if="Object.keys(form.errors).length"
                    role="alert"
                    class="rounded-xl bg-red-50 p-3 text-red-800"
                >
                    Semak semula medan yang ditandakan.
                </p>
            </aside>
        </form>
    </DashboardShell>
</template>

<style scoped>
.blog-body-editor:empty::before {
    color: #94a3b8;
    content: attr(data-placeholder);
    pointer-events: none;
}

.blog-body-editor :deep(h2) {
    margin: 1.75rem 0 0.65rem;
    font-size: 1.5rem;
    font-weight: 800;
    line-height: 1.3;
}

.blog-body-editor :deep(h3) {
    margin: 1.4rem 0 0.5rem;
    font-size: 1.15rem;
    font-weight: 800;
    line-height: 1.4;
}

.blog-body-editor :deep(p) {
    margin: 0.75rem 0;
}

.blog-body-editor :deep(ul),
.blog-body-editor :deep(ol) {
    margin: 0.75rem 0;
    padding-left: 1.5rem;
}

.blog-body-editor :deep(ul) {
    list-style: disc;
}

.blog-body-editor :deep(ol) {
    list-style: decimal;
}
</style>

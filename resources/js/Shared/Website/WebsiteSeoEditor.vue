<script setup>
import { computed } from 'vue';

const props = defineProps({
    fallbackTitle: { type: String, default: '' },
    fallbackDescription: { type: String, default: '' },
    inputClass: { type: String, required: true },
});

const seo = defineModel({ type: Object, required: true });
const searchTitle = computed(
    () => seo.value.meta_title?.trim() || props.fallbackTitle.trim() || 'Nama klinik',
);
const searchDescription = computed(
    () =>
        seo.value.meta_description?.trim() ||
        props.fallbackDescription.trim() ||
        'Maklumat perkhidmatan dan tempahan klinik akan dipaparkan di sini.',
);
</script>

<template>
    <div class="space-y-5">
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 text-emerald-700" aria-hidden="true">✓</span>
                <div>
                    <h3 class="font-bold text-emerald-950">SEO asas disediakan secara automatik</h3>
                    <p class="mt-1 text-sm leading-6 text-emerald-900">
                        Sistem menggunakan nama dan penerangan klinik. Anda hanya perlu mengubahnya
                        jika mahu ayat khusus untuk Google.
                    </p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs font-medium text-emerald-700">website-klinik.my</p>
            <p class="mt-1 line-clamp-2 text-lg font-semibold text-blue-800">
                {{ searchTitle }}
            </p>
            <p class="mt-1 line-clamp-3 text-sm leading-6 text-slate-600">
                {{ searchDescription }}
            </p>
        </div>

        <details class="rounded-xl border border-slate-200 bg-white">
            <summary class="cursor-pointer px-4 py-3 font-bold text-emerald-800">
                Ubah paparan Google (pilihan)
            </summary>
            <div class="space-y-4 border-t border-slate-200 p-4">
                <label class="block text-sm font-semibold text-slate-800">
                    Tajuk carian
                    <span class="font-normal text-slate-500"
                        >{{ seo.meta_title?.length ?? 0 }}/60</span
                    >
                    <input
                        v-model="seo.meta_title"
                        :class="inputClass"
                        maxlength="60"
                        :placeholder="fallbackTitle || 'Nama klinik'"
                    />
                </label>
                <label class="block text-sm font-semibold text-slate-800">
                    Penerangan carian
                    <span class="font-normal text-slate-500"
                        >{{ seo.meta_description?.length ?? 0 }}/160</span
                    >
                    <textarea
                        v-model="seo.meta_description"
                        :class="inputClass"
                        rows="3"
                        maxlength="160"
                        :placeholder="fallbackDescription || 'Penerangan ringkas klinik'"
                    />
                </label>
            </div>
        </details>

        <details class="rounded-xl border border-slate-200 bg-white">
            <summary class="cursor-pointer px-4 py-3 text-sm font-bold text-slate-700">
                Tetapan teknikal (jarang perlu diubah)
            </summary>
            <div class="grid gap-4 border-t border-slate-200 p-4 md:grid-cols-2">
                <label class="text-sm font-semibold text-slate-800 md:col-span-2">
                    Kata kunci tambahan
                    <input v-model="seo.meta_keywords" :class="inputClass" maxlength="255" />
                </label>
                <label class="text-sm font-semibold text-slate-800 md:col-span-2">
                    Pautan asal (canonical)
                    <input
                        v-model="seo.canonical_url"
                        :class="inputClass"
                        type="url"
                        inputmode="url"
                        placeholder="Kosongkan jika tidak pasti"
                    />
                </label>
                <label class="text-sm font-semibold text-slate-800">
                    Paparan enjin carian
                    <select v-model="seo.robots_directive" :class="inputClass">
                        <option value="index,follow">Benarkan muncul di carian</option>
                        <option value="index,nofollow">Muncul tanpa mengikuti pautan</option>
                        <option value="noindex,follow">Jangan paparkan di carian</option>
                        <option value="noindex,nofollow">Sembunyikan sepenuhnya</option>
                    </select>
                </label>
                <label
                    class="flex items-center gap-3 self-end rounded-lg border border-slate-200 p-3 text-sm font-semibold text-slate-800"
                >
                    <input v-model="seo.indexing_enabled" type="checkbox" class="size-4" />
                    Benarkan website disenaraikan di Google
                </label>
            </div>
        </details>
    </div>
</template>

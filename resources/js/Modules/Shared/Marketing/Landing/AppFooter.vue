<script setup>
defineProps({
    copy: { type: Object, required: true }, // { blurb, navTitle, legalTitle, rights }
    links: { type: Array, required: true }, // [{ id, label }]
    privacyUrl: { type: String, required: true },
    termsUrl: { type: String, required: true },
    privacyLabel: { type: String, required: true },
    termsLabel: { type: String, required: true },
});

const year = new Date().getFullYear();
</script>

<template>
    <footer class="border-t border-slate-100 bg-white">
        <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-10">
            <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                <div class="lg:col-span-2">
                    <a
                        href="#top"
                        class="inline-flex items-center rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                    >
                        <img :src="'/images/marketing/syifa-logo.webp'" alt="SYIFA.my" class="h-7 w-auto" width="1836" height="857" />
                    </a>
                    <p class="mt-3 max-w-sm text-sm leading-6 text-slate-600">{{ copy.blurb }}</p>
                </div>

                <div>
                    <p class="text-xs font-bold tracking-[0.14em] text-slate-400 uppercase">
                        {{ copy.navTitle }}
                    </p>
                    <ul class="mt-4 space-y-2 text-sm text-slate-600">
                        <li v-for="link in links" :key="link.id">
                            <a
                                :href="`#${link.id}`"
                                class="rounded-sm hover:text-slate-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                                >{{ link.label }}</a
                            >
                        </li>
                    </ul>
                </div>

                <div>
                    <p class="text-xs font-bold tracking-[0.14em] text-slate-400 uppercase">
                        {{ copy.legalTitle }}
                    </p>
                    <ul class="mt-4 space-y-2 text-sm text-slate-600">
                        <li>
                            <a
                                :href="privacyUrl"
                                class="rounded-sm hover:text-slate-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                                >{{ privacyLabel }}</a
                            >
                        </li>
                        <li>
                            <a
                                :href="termsUrl"
                                class="rounded-sm hover:text-slate-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                                >{{ termsLabel }}</a
                            >
                        </li>
                    </ul>
                </div>
            </div>

            <div class="mt-10 border-t border-slate-100 pt-6 text-xs text-slate-500">
                <p>&copy; {{ year }} SYIFA.my. {{ copy.rights }}</p>
            </div>
        </div>
    </footer>
</template>

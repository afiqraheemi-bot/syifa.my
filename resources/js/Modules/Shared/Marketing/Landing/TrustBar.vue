<script setup>
defineProps({
    copy: { type: Object, required: true }, // { title, items: [{ id, label }] }
});

const icons = {
    health: 'M12 21s-6.5-4.35-9-8.5C1 8.5 3 5 6.5 5c2 0 3.5 1.2 5.5 3.5C14 6.2 15.5 5 17.5 5 21 5 23 8.5 21 12.5c-2.5 4.15-9 8.5-9 8.5z',
    dental: 'M12 3c-2 0-3.2 1-4.5 1S5 3.3 3.6 4C2.3 4.7 2 6.5 2.3 8c.4 2 1.7 2.5 2 5 .3 2.6 1 8 3.2 8 1.7 0 1.5-4 2-6 .3-1.2.8-1.7 1.5-1.7s1.2.5 1.5 1.7c.5 2 .3 6 2 6 2.2 0 2.9-5.4 3.2-8 .3-2.5 1.6-3 2-5 .3-1.5 0-3.3-1.3-4C18.5 3.3 16.8 4 15.5 4S14 3 12 3z',
    specialist: 'M12 15a5 5 0 100-10 5 5 0 000 10zM8.5 14.5L6 22l6-3.5L18 22l-2.5-7.5',
    women: 'M12 4a5 5 0 100 10 5 5 0 000-10zM12 14v7M9 18h6',
    children: 'M12 4a3 3 0 100 6 3 3 0 000-6zM6 20c0-4 3-6 6-6s6 2 6 6',
    physio: 'M6 19l3-7 3 3 3-8M2 19h20',
    aesthetic: 'M12 2l1.8 5.6L19 9l-5.2 1.4L12 16l-1.8-5.6L5 9l5.2-1.4L12 2z',
    more: 'M5 12h.01M12 12h.01M19 12h.01',
};
</script>

<template>
    <section class="border-y border-slate-100 bg-slate-50/60 py-16 sm:py-20">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <p
                data-reveal
                class="reveal text-center text-xs font-bold tracking-[0.2em] text-slate-500 uppercase"
            >
                {{ copy.title }}
            </p>
            <div
                data-reveal
                class="reveal mt-10 grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-8"
            >
                <div
                    v-for="item in copy.items"
                    :key="item.id"
                    class="flex flex-col items-center gap-3 rounded-2xl px-3 py-5 text-center transition hover:bg-white hover:shadow-md hover:shadow-slate-900/5"
                >
                    <span
                        class="flex size-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            class="size-6 fill-none stroke-current stroke-[1.75]"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                :d="icons[item.id]"
                            />
                        </svg>
                    </span>
                    <span class="text-xs font-semibold text-slate-700">{{ item.label }}</span>
                </div>
            </div>
        </div>
    </section>
</template>

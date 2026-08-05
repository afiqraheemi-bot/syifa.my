<script setup></script>

<template>
    <div class="relative mx-auto max-w-lg lg:mx-0">
        <div
            class="pointer-events-none absolute -inset-x-10 -inset-y-10 -z-10 rounded-[3rem] bg-emerald-100/60 blur-3xl"
        />

        <!-- Device frame: mini website preview -->
        <div
            class="float-slow relative overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-2xl shadow-slate-900/15"
        >
            <div class="flex items-center gap-1.5 border-b border-slate-100 bg-slate-50 px-4 py-3">
                <span class="size-2.5 rounded-full bg-red-300" />
                <span class="size-2.5 rounded-full bg-amber-300" />
                <span class="size-2.5 rounded-full bg-emerald-400" />
                <span
                    class="ml-3 flex-1 truncate rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] text-slate-400"
                >
                    klinikanda.syifa.my
                </span>
            </div>
            <div class="bg-gradient-to-br from-emerald-800 to-emerald-950 p-6 text-white">
                <p class="text-[11px] font-bold tracking-wide text-emerald-200">KLINIK ANDA</p>
                <p class="mt-2 max-w-[14rem] text-lg font-bold">Kesihatan Anda, Keutamaan Kami</p>
                <span
                    class="mt-4 inline-flex min-h-9 items-center rounded-full bg-white px-4 text-xs font-bold text-emerald-800"
                >
                    Tempah Temujanji
                </span>
            </div>
            <div class="grid grid-cols-3 gap-3 p-5">
                <div v-for="n in 3" :key="n" class="rounded-xl bg-emerald-50 p-3">
                    <span class="block size-6 rounded-full bg-emerald-200" />
                    <span class="mt-2 block h-1.5 w-4/5 rounded-full bg-emerald-200" />
                    <span class="mt-1 block h-1.5 w-3/5 rounded-full bg-emerald-100" />
                </div>
            </div>
        </div>

        <!-- Floating cards -->
        <div
            class="float-slower absolute -top-6 -right-6 hidden w-52 rounded-2xl border border-slate-100 bg-white p-4 shadow-xl shadow-slate-900/10 sm:block"
        >
            <div class="flex items-center gap-2.5">
                <span
                    class="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700"
                >
                    <svg viewBox="0 0 24 24" class="size-5 fill-none stroke-current stroke-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-bold text-slate-900">Tempahan Disahkan</p>
                    <p class="truncate text-[11px] text-slate-500">Aisyah &middot; 10:30 AM</p>
                </div>
            </div>
        </div>

        <div
            class="float-slow absolute -bottom-6 -left-6 hidden w-48 rounded-2xl border border-slate-100 bg-white p-4 shadow-xl shadow-slate-900/10 sm:block"
        >
            <p class="text-[11px] font-bold text-slate-500">Tempahan Bulan Ini</p>
            <p class="mt-1 text-2xl font-black text-emerald-700">+128</p>
            <div class="mt-2 flex items-end gap-1">
                <span
                    v-for="(height, index) in [40, 65, 50, 80, 60, 95]"
                    :key="index"
                    class="w-full rounded-sm bg-emerald-200"
                    :style="{ height: height / 4 + 'px' }"
                />
            </div>
        </div>

        <div
            class="float-slower absolute top-1/3 -right-10 hidden w-44 rounded-2xl border border-slate-100 bg-white p-3.5 shadow-xl shadow-slate-900/10 xl:block"
        >
            <div class="flex items-center gap-2">
                <span
                    class="flex size-7 items-center justify-center rounded-full bg-amber-100 text-amber-600"
                >
                    <svg viewBox="0 0 24 24" class="size-4 fill-none stroke-current stroke-2">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9"
                        />
                    </svg>
                </span>
                <p class="text-[11px] font-semibold text-slate-700">Pesakit baharu mendaftar</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes float-slow {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-0.625rem);
    }
}

@keyframes float-slower {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(0.5rem);
    }
}

.float-slow {
    animation: float-slow 6s ease-in-out infinite;
}

.float-slower {
    animation: float-slower 7s ease-in-out infinite;
}

@media (prefers-reduced-motion: reduce) {
    .float-slow,
    .float-slower {
        animation: none;
    }
}
</style>

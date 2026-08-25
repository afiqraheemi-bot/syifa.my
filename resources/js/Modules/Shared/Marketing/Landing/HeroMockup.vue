<template>
    <div class="relative mx-auto max-w-[42rem] pb-10 sm:px-8 lg:mx-0">
        <div class="absolute inset-8 -z-10 rotate-3 rounded-[3rem] bg-emerald-800/10" />
        <div
            class="relative overflow-hidden rounded-[1.75rem] border border-white/80 bg-white shadow-[0_32px_80px_-30px_rgba(6,78,59,.38)]"
        >
            <div
                class="flex items-center gap-2 border-b border-slate-100 bg-white px-4 py-3.5 sm:px-5"
            >
                <div class="flex gap-1.5" aria-hidden="true">
                    <span class="size-2.5 rounded-full bg-rose-300" />
                    <span class="size-2.5 rounded-full bg-amber-300" />
                    <span class="size-2.5 rounded-full bg-emerald-400" />
                </div>
                <span
                    class="mx-auto rounded-full bg-slate-100 px-5 py-1.5 text-[10px] font-semibold text-slate-500 sm:text-xs"
                    >klinikanda.syifa.my</span
                >
            </div>

            <div class="relative min-h-[25rem] overflow-hidden bg-emerald-950 sm:min-h-[30rem]">
                <img
                    :src="'/images/marketing/klinik-aafiyah-hero.jpg'"
                    alt=""
                    class="absolute inset-0 size-full object-cover opacity-55 mix-blend-luminosity"
                />
                <div
                    class="absolute inset-0 bg-gradient-to-r from-emerald-950 via-emerald-950/85 to-emerald-900/15"
                />
                <div
                    class="relative z-10 flex items-center justify-between px-5 py-5 text-white sm:px-8"
                >
                    <p class="text-xs font-black tracking-[0.14em]">KLINIK ANDA</p>
                    <div class="hidden gap-5 text-[10px] font-bold text-emerald-100 sm:flex">
                        <span>Servis</span><span>Doktor</span><span>Hubungi</span>
                    </div>
                </div>
                <div class="relative z-10 max-w-md px-6 pt-10 text-white sm:px-10 sm:pt-16">
                    <p class="text-[10px] font-black tracking-[0.2em] text-lime-300 uppercase">
                        Penjagaan keluarga dipercayai
                    </p>
                    <h2 class="mt-4 text-3xl leading-tight font-black tracking-tight sm:text-5xl">
                        Kesihatan anda,<br />keutamaan kami.
                    </h2>
                    <p class="mt-4 max-w-xs text-sm leading-6 text-emerald-50/80 sm:text-base">
                        Rawatan menyeluruh dan mesra untuk setiap peringkat usia.
                    </p>
                    <span
                        class="mt-7 inline-flex min-h-11 items-center rounded-xl bg-lime-300 px-5 text-sm font-black text-emerald-950"
                        >Buat Temu Janji</span
                    >
                </div>
            </div>
        </div>

        <div
            class="float-card absolute right-0 -bottom-1 w-[15rem] rounded-2xl border border-white bg-white p-4 shadow-2xl shadow-emerald-950/15 sm:right-2 sm:w-[17rem]"
        >
            <div class="flex items-start gap-3">
                <span
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700"
                >
                    <svg viewBox="0 0 24 24" class="size-5 fill-none stroke-current stroke-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </span>
                <div>
                    <p class="text-xs font-black text-slate-950">Tempahan disahkan</p>
                    <p class="mt-1 text-[11px] text-slate-500">Aisyah · Pemeriksaan am</p>
                    <p class="mt-2 text-xs font-bold text-emerald-700">Hari ini, 10:30 pagi</p>
                </div>
            </div>
        </div>

        <div
            class="float-card-slow absolute -left-2 bottom-4 hidden rounded-2xl border border-white bg-emerald-950 p-4 text-white shadow-2xl sm:block"
        >
            <p class="text-[10px] font-bold tracking-wide text-emerald-200 uppercase">
                Dibuka untuk tempahan
            </p>
            <div class="mt-2 flex items-center gap-2">
                <span class="size-2 rounded-full bg-lime-300 ring-4 ring-lime-300/15" /><span
                    class="text-sm font-black"
                    >24 jam, setiap hari</span
                >
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes float-card {
    0%,
    100% {
        transform: translateY(0) rotate(0deg);
    }
    50% {
        transform: translateY(-8px) rotate(0.4deg);
    }
}
.float-card {
    animation: float-card 6s ease-in-out infinite;
}
.float-card-slow {
    animation: float-card 7.5s ease-in-out infinite reverse;
}
@media (prefers-reduced-motion: reduce) {
    .float-card,
    .float-card-slow {
        animation: none;
    }
}
</style>

<template>
    <figure class="mx-auto max-w-[40rem] lg:mx-0">
        <figcaption
            class="mb-3 flex items-center justify-between gap-4 px-1 text-xs font-semibold text-slate-500"
        >
            <span>Website sebenar dihasilkan melalui SYIFA.my</span>
            <a
                href="https://kliniksihat.syifa.my"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex shrink-0 items-center gap-1.5 text-emerald-800 hover:text-emerald-950"
            >
                Lihat website
                <svg viewBox="0 0 20 20" class="size-3.5 fill-none stroke-current stroke-2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 14 14 6m-6 0h6v6" />
                </svg>
            </a>
        </figcaption>

        <div
            class="overflow-hidden rounded-[1.35rem] border border-slate-200 bg-white shadow-[0_24px_64px_-32px_rgba(15,23,42,.32)]"
        >
            <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-3 sm:px-5">
                <div class="flex gap-1.5" aria-hidden="true">
                    <span class="size-2.5 rounded-full bg-rose-300" />
                    <span class="size-2.5 rounded-full bg-amber-300" />
                    <span class="size-2.5 rounded-full bg-emerald-400" />
                </div>
                <span
                    class="mx-auto flex items-center gap-2 rounded-full bg-slate-100 px-5 py-1.5 text-[10px] font-semibold text-slate-500 sm:text-xs"
                >
                    <svg
                        viewBox="0 0 24 24"
                        class="size-3 fill-none stroke-emerald-700 stroke-2.5"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M7 11V8a5 5 0 0110 0v3m-11 0h12v9H6z"
                        />
                    </svg>
                    kliniksihat.syifa.my
                </span>
            </div>

            <a
                href="https://kliniksihat.syifa.my"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="Buka website sebenar Klinik Sihat"
                class="block bg-[#f2faf3]"
            >
                <img
                    :src="'/images/marketing/klinik-sihat-live.png'"
                    alt="Paparan sebenar website Klinik Sihat yang dihasilkan melalui SYIFA.my"
                    width="1440"
                    height="900"
                    class="block aspect-[16/10] w-full object-cover object-top"
                />
            </a>
        </div>
    </figure>
</template>

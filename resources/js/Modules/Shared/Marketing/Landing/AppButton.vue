<script setup>
defineProps({
    href: { type: String, required: true },
    variant: { type: String, default: 'primary' }, // 'primary' | 'secondary' | 'ghost' | 'ghost-inverse'
    size: { type: String, default: 'md' }, // 'md' | 'lg'
    target: { type: String, default: null },
    rel: { type: String, default: null },
});

const base =
    'inline-flex items-center justify-center gap-2 rounded-2xl font-bold transition duration-200 ' +
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 ' +
    'active:scale-[0.98]';

const variants = {
    primary:
        'bg-emerald-700 text-white shadow-lg shadow-emerald-700/20 hover:-translate-y-0.5 hover:bg-emerald-800 hover:shadow-xl hover:shadow-emerald-700/25',
    secondary:
        'border-2 border-slate-200 bg-white text-slate-900 hover:-translate-y-0.5 hover:border-slate-300 hover:bg-slate-50',
    ghost: 'text-emerald-800 hover:text-emerald-950',
    'ghost-inverse': 'border-2 border-white/30 text-white hover:border-white/60 hover:bg-white/10',
};

const sizes = {
    md: 'min-h-11 px-5 text-sm',
    lg: 'min-h-13 px-7 text-base',
};
</script>

<template>
    <a :href="href" :target="target" :rel="rel" :class="[base, variants[variant], sizes[size]]">
        <slot />
    </a>
</template>

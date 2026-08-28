<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    settings: { type: Object, required: true },
});

const deliveryStatusLabels = computed(() => ({
    queued: t('bookingWhatsApp.statusQueued'),
    sending: t('bookingWhatsApp.statusSending'),
    sent: t('bookingWhatsApp.statusSent'),
    failed: t('bookingWhatsApp.statusFailed'),
}));
const saved = ref(false);
const form = useForm({
    enabled: props.settings.enabled,
    recipient_number: props.settings.recipient_number ?? '',
});

function save() {
    if (form.processing) return;
    saved.value = false;
    form.patch(props.settings.updateUrl, {
        preserveScroll: true,
        onSuccess: (page) => {
            const current = page.props.whatsAppSettings;
            if (current) {
                form.enabled = current.enabled;
                form.recipient_number = current.recipient_number ?? '';
            }
            saved.value = true;
        },
    });
}
</script>

<template>
    <form
        class="mt-6 space-y-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 sm:p-5"
        novalidate
        @submit.prevent="save"
    >
        <div>
            <h3 class="font-bold text-slate-950">{{ t('bookingWhatsApp.title') }}</h3>
            <p class="mt-1 text-sm leading-6 text-slate-700">
                {{ t('bookingWhatsApp.description') }}
            </p>
        </div>
        <label class="flex min-h-11 items-center gap-3 text-sm font-bold text-slate-900">
            <input
                v-model="form.enabled"
                type="checkbox"
                :disabled="!settings.provider_configured"
                class="size-4 accent-emerald-700"
            />
            {{ t('bookingWhatsApp.toggleLabel') }}
        </label>
        <p
            v-if="!settings.provider_configured"
            class="rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm leading-6 text-amber-950"
            role="status"
        >
            {{ t('bookingWhatsApp.notConfigured') }}
        </p>
        <div
            v-else
            class="grid grid-cols-2 gap-2 rounded-xl border border-emerald-200 bg-white p-3 text-sm sm:grid-cols-4"
            :aria-label="t('bookingWhatsApp.deliverySummary')"
        >
            <div v-for="item in ['queued', 'sending', 'sent', 'failed']" :key="item">
                <p class="capitalize text-slate-500">{{ deliveryStatusLabels[item] }}</p>
                <p
                    class="text-lg font-black"
                    :class="{
                        'text-emerald-800': item === 'sent',
                        'text-red-700': item === 'failed' && settings.delivery_summary.failed,
                    }"
                >
                    {{ settings.delivery_summary[item] }}
                </p>
            </div>
        </div>
        <p v-if="form.errors.enabled" class="text-sm font-semibold text-red-700" role="alert">
            {{ form.errors.enabled }}
        </p>
        <label class="grid max-w-xl gap-2 text-sm font-semibold text-slate-800">
            {{ t('bookingWhatsApp.recipientLabel') }}
            <input
                v-model="form.recipient_number"
                type="tel"
                inputmode="tel"
                autocomplete="tel"
                placeholder="+60123456789"
                :required="form.enabled"
                class="min-h-11 rounded-xl border border-slate-300 bg-white px-3"
            />
            <span class="font-normal leading-5 text-slate-600">
                {{ t('bookingWhatsApp.recipientHelp') }}
            </span>
            <span v-if="form.errors.recipient_number" class="text-sm text-red-700" role="alert">
                {{ form.errors.recipient_number }}
            </span>
        </label>
        <p
            v-if="saved"
            role="status"
            class="rounded-xl border border-emerald-300 bg-white p-3 text-sm font-semibold text-emerald-900"
        >
            {{ t('bookingWhatsApp.saved') }}
        </p>
        <button
            type="submit"
            :disabled="form.processing"
            class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-700 px-5 py-3 text-sm font-bold text-white hover:bg-emerald-800 disabled:opacity-60"
        >
            {{ form.processing ? t('bookingWhatsApp.saving') : t('bookingWhatsApp.save') }}
        </button>
    </form>
</template>

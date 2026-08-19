<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    settings: { type: Object, required: true },
});

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
            <h3 class="font-bold text-slate-950">WhatsApp booking notifications</h3>
            <p class="mt-1 text-sm leading-6 text-slate-700">
                Send every new Website booking to a WhatsApp number chosen by the clinic. Manual
                bookings created by staff are not sent.
            </p>
        </div>
        <label class="flex min-h-11 items-center gap-3 text-sm font-bold text-slate-900">
            <input
                v-model="form.enabled"
                type="checkbox"
                :disabled="!settings.provider_configured"
                class="size-4 accent-emerald-700"
            />
            Send new booking details to WhatsApp
        </label>
        <p
            v-if="!settings.provider_configured"
            class="rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm leading-6 text-amber-950"
            role="status"
        >
            WhatsApp delivery is not available yet. Ask the platform administrator to configure the
            Meta WhatsApp Business API.
        </p>
        <div
            v-else
            class="grid grid-cols-2 gap-2 rounded-xl border border-emerald-200 bg-white p-3 text-sm sm:grid-cols-4"
            aria-label="WhatsApp delivery summary"
        >
            <div v-for="item in ['queued', 'sending', 'sent', 'failed']" :key="item">
                <p class="capitalize text-slate-500">{{ item }}</p>
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
            WhatsApp recipient number
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
                Use a complete international number. Malaysian numbers may also be entered as
                01xxxxxxxx.
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
            WhatsApp notification settings saved successfully.
        </p>
        <button
            type="submit"
            :disabled="form.processing"
            class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-700 px-5 py-3 text-sm font-bold text-white hover:bg-emerald-800 disabled:opacity-60"
        >
            {{ form.processing ? 'Saving…' : 'Save WhatsApp settings' }}
        </button>
    </form>
</template>

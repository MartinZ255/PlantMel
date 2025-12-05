<!-- resources/js/pages/auth/VerifyEmail.vue -->
<template>
    <div
        class="min-h-screen flex items-center justify-center px-4 bg-[radial-gradient(circle_at_top_left,_#ffe4f0_0,_#fdf7ff_40%,_#e6f0ff_100%)] text-slate-900"
    >
        <Head title="E-Mail bestätigen – favRezepte" />

        <div class="w-full max-w-md rounded-2xl bg-white/90 backdrop-blur shadow-xl border border-pink-100 px-6 py-7 space-y-6">
            <div class="space-y-1">
                <h1 class="text-lg font-semibold text-slate-900">
                    E-Mail-Adresse bestätigen
                </h1>
                <p class="text-xs text-slate-500">
                    Wir haben dir einen Bestätigungslink gesendet. Bitte prüfe dein Postfach.
                </p>
            </div>

            <p
                v-if="status === 'verification-link-sent'"
                class="text-xs text-emerald-600 bg-emerald-50 border border-emerald-100 rounded-lg px-3 py-2"
            >
                Ein neuer Bestätigungslink wurde an deine E-Mail-Adresse gesendet.
            </p>

            <form
                class="space-y-4"
                @submit.prevent="submit"
            >
                <button
                    type="submit"
                    class="w-full inline-flex items-center justify-center rounded-full bg-gradient-to-r from-pink-400 to-rose-400 text-white px-5 py-2.5 text-sm font-semibold shadow-md hover:brightness-105 transition-all disabled:opacity-60 disabled:cursor-not-allowed"
                    :disabled="form.processing"
                >
                    Link erneut senden
                </button>
            </form>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

interface Props {
    status?: string | null;
}

defineProps<Props>();

const form = useForm({});

function submit() {
    form.post(route('verification.send'));
}
</script>

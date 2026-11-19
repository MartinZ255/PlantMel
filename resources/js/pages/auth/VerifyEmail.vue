<!-- resources/js/pages/auth/VerifyEmail.vue -->
<template>
    <div
        class="flex min-h-screen flex-col items-center justify-center bg-[radial-gradient(circle_at_top_left,_#FBEAE6_0,_#FFFDF6_50%,_#EFF5E8_100%)] px-4 text-[#3C382F]"
    >
        <Head title="E-Mail bestätigen" />

        <Link :href="route('landing')" class="auth-brand">PlantMel</Link>

        <p
            v-if="flashError"
            class="flash-message flash-message--error mb-4 w-full max-w-md"
            role="alert"
        >
            {{ flashError }}
        </p>

        <div
            class="w-full max-w-md space-y-6 rounded-2xl border border-[#EBE3D2] bg-white/90 px-6 py-7 shadow-xl backdrop-blur"
        >
            <div class="space-y-1">
                <h1 class="text-lg font-semibold text-[#3C382F]">
                    E-Mail-Adresse bestätigen
                </h1>
                <p class="text-xs text-[#877D6C]">
                    Wir haben dir einen Bestätigungslink gesendet. Bitte prüfe
                    dein Postfach.
                </p>
            </div>

            <p
                v-if="status === 'verification-link-sent'"
                class="rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-2 text-xs text-emerald-600"
            >
                Ein neuer Bestätigungslink wurde an deine E-Mail-Adresse
                gesendet.
            </p>

            <form class="space-y-4" @submit.prevent="submit">
                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center rounded-full bg-[#7DA879] px-5 py-2.5 text-sm font-semibold text-white shadow-md transition-all hover:bg-[#628F5E] hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="form.processing"
                >
                    Link erneut senden
                </button>
            </form>
        </div>

        <AppFooter variant="auth" />
    </div>
</template>

<script setup lang="ts">
import AppFooter from '@/components/layout/AppFooter.vue';
import type { AppPageProps } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const page = usePage<AppPageProps>();
const flashError = computed(() => page.props.flash?.error ?? null);

interface Props {
    status?: string | null;
}

defineProps<Props>();

const form = useForm({});

function submit() {
    form.post(route('verification.send'));
}
</script>

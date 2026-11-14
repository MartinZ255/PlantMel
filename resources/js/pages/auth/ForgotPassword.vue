<!-- resources/js/pages/auth/ForgotPassword.vue -->
<template>
    <div
        class="flex min-h-screen items-center justify-center bg-[radial-gradient(circle_at_top_left,_#FBEAE6_0,_#FFFDF6_50%,_#EFF5E8_100%)] px-4 text-[#3C382F]"
    >
        <Head title="Passwort vergessen" />

        <div class="w-full max-w-md">
            <div class="text-center">
                <Link :href="route('landing')" class="auth-brand">
                    PlantMel
                </Link>
            </div>

            <p
                v-if="flashError"
                class="flash-message flash-message--error mb-4"
                role="alert"
            >
                {{ flashError }}
            </p>

            <div class="mb-6 text-center">
                <h1 class="text-lg font-semibold text-[#3C382F]">
                    Passwort zurücksetzen
                </h1>
                <p class="mt-1 text-xs text-[#877D6C]">
                    Wir senden dir einen Link zum Zurücksetzen deines Passworts.
                </p>
            </div>

            <div
                class="space-y-6 rounded-2xl border border-[#EBE3D2] bg-white/90 px-6 py-7 shadow-xl backdrop-blur"
            >
                <p
                    v-if="status"
                    class="rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-2 text-xs text-emerald-600"
                >
                    {{ status }}
                </p>

                <form class="space-y-4" @submit.prevent="submit">
                    <div class="space-y-1.5">
                        <label
                            class="text-xs font-medium text-[#4A463C]"
                            for="email"
                        >
                            E-Mail
                        </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            autocomplete="email"
                            class="w-full rounded-lg border border-[#EBE3D2] bg-white/80 px-3 py-2 text-base text-[#3C382F] placeholder:text-[#C9C0AE] focus:border-[#A8C7A4] focus:ring-2 focus:ring-[#A8C7A4] focus:outline-none md:text-sm"
                            placeholder="du@example.com"
                        />
                        <p
                            v-if="errors.email"
                            class="text-[11px] text-[#B95454]"
                        >
                            {{ errors.email }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center rounded-full bg-[#7DA879] px-5 py-2.5 text-sm font-semibold text-white shadow-md transition-all hover:bg-[#628F5E] hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        Link senden
                    </button>
                </form>

                <p class="text-center text-[11px] text-[#877D6C]">
                    <Link
                        :href="route('login')"
                        class="text-[#628F5E] hover:text-[#4E7A4A]"
                    >
                        ← Zurück zum Login
                    </Link>
                </p>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import type { AppPageProps } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

// z. B. "Sitzung abgelaufen" nach 419 (Seite ohne AppLayout)
const page = usePage<AppPageProps>();
const flashError = computed(() => page.props.flash?.error ?? null);

interface Props {
    status?: string | null;
    errors: Record<string, string>;
}

defineProps<Props>();

const form = useForm({
    email: '',
});

function submit() {
    form.post(route('password.email'));
}
</script>

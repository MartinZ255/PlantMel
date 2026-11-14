<!-- resources/js/pages/auth/ResetPassword.vue -->
<template>
    <div
        class="flex min-h-screen items-center justify-center bg-[radial-gradient(circle_at_top_left,_#FBEAE6_0,_#FFFDF6_50%,_#EFF5E8_100%)] px-4 text-[#3C382F]"
    >
        <Head title="Neues Passwort" />

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
                    Neues Passwort setzen
                </h1>
                <p class="mt-1 text-xs text-[#877D6C]">
                    Vergib ein neues Passwort für deinen Account.
                </p>
            </div>

            <div
                class="space-y-6 rounded-2xl border border-[#EBE3D2] bg-white/90 px-6 py-7 shadow-xl backdrop-blur"
            >
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
                        />
                        <p
                            v-if="errors.email"
                            class="text-[11px] text-[#B95454]"
                        >
                            {{ errors.email }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <label
                            class="text-xs font-medium text-[#4A463C]"
                            for="password"
                        >
                            Neues Passwort
                        </label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            required
                            autocomplete="new-password"
                            class="w-full rounded-lg border border-[#EBE3D2] bg-white/80 px-3 py-2 text-base text-[#3C382F] placeholder:text-[#C9C0AE] focus:border-[#A8C7A4] focus:ring-2 focus:ring-[#A8C7A4] focus:outline-none md:text-sm"
                        />
                        <p
                            v-if="errors.password"
                            class="text-[11px] text-[#B95454]"
                        >
                            {{ errors.password }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <label
                            class="text-xs font-medium text-[#4A463C]"
                            for="password_confirmation"
                        >
                            Passwort bestätigen
                        </label>
                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            required
                            autocomplete="new-password"
                            class="w-full rounded-lg border border-[#EBE3D2] bg-white/80 px-3 py-2 text-base text-[#3C382F] placeholder:text-[#C9C0AE] focus:border-[#A8C7A4] focus:ring-2 focus:ring-[#A8C7A4] focus:outline-none md:text-sm"
                        />
                    </div>

                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center rounded-full bg-[#7DA879] px-5 py-2.5 text-sm font-semibold text-white shadow-md transition-all hover:bg-[#628F5E] hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        Passwort speichern
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import type { AppPageProps } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const page = usePage<AppPageProps>();
const flashError = computed(() => page.props.flash?.error ?? null);

interface Props {
    token: string;
    email: string;
    errors: Record<string, string>;
}

const props = defineProps<Props>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    // Fortify benennt POST /reset-password als "password.update" (F-01)
    form.post(route('password.update'));
}
</script>

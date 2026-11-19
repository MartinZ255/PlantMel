<!-- resources/js/pages/auth/ConfirmPassword.vue -->
<template>
    <div
        class="flex min-h-screen flex-col items-center justify-center bg-[radial-gradient(circle_at_top_left,_#FBEAE6_0,_#FFFDF6_50%,_#EFF5E8_100%)] px-4 text-[#3C382F]"
    >
        <Head title="Passwort bestätigen" />

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
                    Passwort bestätigen
                </h1>
                <p class="text-xs text-[#877D6C]">
                    Bitte gib zur Bestätigung dein Passwort ein, bevor du
                    fortfährst.
                </p>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-1.5">
                    <label
                        class="text-xs font-medium text-[#4A463C]"
                        for="password"
                    >
                        Passwort
                    </label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="w-full rounded-lg border border-[#EBE3D2] bg-white/80 px-3 py-2 text-base text-[#3C382F] placeholder:text-[#C9C0AE] focus:border-[#A8C7A4] focus:ring-2 focus:ring-[#A8C7A4] focus:outline-none md:text-sm"
                    />
                    <p
                        v-if="errors.password"
                        class="text-[11px] text-[#B95454]"
                    >
                        {{ errors.password }}
                    </p>
                </div>

                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center rounded-full bg-[#7DA879] px-5 py-2.5 text-sm font-semibold text-white shadow-md transition-all hover:bg-[#628F5E] hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="form.processing"
                >
                    Bestätigen
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
    errors: Record<string, string>;
}

defineProps<Props>();

const form = useForm({
    password: '',
});

function submit() {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset('password'),
    });
}
</script>

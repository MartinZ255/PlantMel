<!-- resources/js/pages/auth/ConfirmPassword.vue -->
<template>
    <div
        class="min-h-screen flex items-center justify-center px-4 bg-[radial-gradient(circle_at_top_left,_#ffe4f0_0,_#fdf7ff_40%,_#e6f0ff_100%)] text-slate-900"
    >
        <Head title="Passwort bestätigen – favRezepte" />

        <div class="w-full max-w-md rounded-2xl bg-white/90 backdrop-blur shadow-xl border border-pink-100 px-6 py-7 space-y-6">
            <div class="space-y-1">
                <h1 class="text-lg font-semibold text-slate-900">
                    Passwort bestätigen
                </h1>
                <p class="text-xs text-slate-500">
                    Bitte gib zur Bestätigung dein Passwort ein, bevor du fortfährst.
                </p>
            </div>

            <form
                class="space-y-4"
                @submit.prevent="submit"
            >
                <div class="space-y-1.5">
                    <label
                        class="text-xs font-medium text-slate-700"
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
                        class="w-full rounded-lg border border-pink-100 bg-white/80 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-pink-300 focus:border-pink-300"
                    />
                    <p
                        v-if="errors.password"
                        class="text-[11px] text-rose-500"
                    >
                        {{ errors.password }}
                    </p>
                </div>

                <button
                    type="submit"
                    class="w-full inline-flex items-center justify-center rounded-full bg-gradient-to-r from-pink-400 to-rose-400 text-white px-5 py-2.5 text-sm font-semibold shadow-md hover:brightness-105 transition-all disabled:opacity-60 disabled:cursor-not-allowed"
                    :disabled="form.processing"
                >
                    Bestätigen
                </button>
            </form>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

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

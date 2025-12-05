<!-- resources/js/pages/auth/ResetPassword.vue -->
<template>
    <div
        class="min-h-screen flex items-center justify-center px-4 bg-[radial-gradient(circle_at_top_left,_#ffe4f0_0,_#fdf7ff_40%,_#e6f0ff_100%)] text-slate-900"
    >
        <Head title="Neues Passwort – favRezepte" />

        <div class="w-full max-w-md">
            <div class="mb-6 text-center">
                <h1 class="text-lg font-semibold text-slate-900">
                    Neues Passwort setzen
                </h1>
                <p class="mt-1 text-xs text-slate-500">
                    Vergib ein neues Passwort für deinen Account.
                </p>
            </div>

            <div class="rounded-2xl bg-white/90 backdrop-blur shadow-xl border border-pink-100 px-6 py-7 space-y-6">
                <form
                    class="space-y-4"
                    @submit.prevent="submit"
                >
                    <div class="space-y-1.5">
                        <label
                            class="text-xs font-medium text-slate-700"
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
                            class="w-full rounded-lg border border-pink-100 bg-white/80 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-pink-300 focus:border-pink-300"
                        />
                        <p
                            v-if="errors.email"
                            class="text-[11px] text-rose-500"
                        >
                            {{ errors.email }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <label
                            class="text-xs font-medium text-slate-700"
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
                            class="w-full rounded-lg border border-pink-100 bg-white/80 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-pink-300 focus:border-pink-300"
                        />
                        <p
                            v-if="errors.password"
                            class="text-[11px] text-rose-500"
                        >
                            {{ errors.password }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <label
                            class="text-xs font-medium text-slate-700"
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
                            class="w-full rounded-lg border border-pink-100 bg-white/80 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-pink-300 focus:border-pink-300"
                        />
                    </div>

                    <button
                        type="submit"
                        class="w-full inline-flex items-center justify-center rounded-full bg-gradient-to-r from-pink-400 to-rose-400 text-white px-5 py-2.5 text-sm font-semibold shadow-md hover:brightness-105 transition-all disabled:opacity-60 disabled:cursor-not-allowed"
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
import { Head, useForm } from '@inertiajs/vue3';

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
    form.post(route('password.store'));
}
</script>

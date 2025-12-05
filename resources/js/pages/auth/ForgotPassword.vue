<!-- resources/js/pages/auth/ForgotPassword.vue -->
<template>
    <div
        class="min-h-screen flex items-center justify-center px-4 bg-[radial-gradient(circle_at_top_left,_#ffe4f0_0,_#fdf7ff_40%,_#e6f0ff_100%)] text-slate-900"
    >
        <Head title="Passwort vergessen – favRezepte" />

        <div class="w-full max-w-md">
            <div class="mb-6 text-center">
                <h1 class="text-lg font-semibold text-slate-900">
                    Passwort zurücksetzen
                </h1>
                <p class="mt-1 text-xs text-slate-500">
                    Wir senden dir einen Link zum Zurücksetzen deines Passworts.
                </p>
            </div>

            <div class="rounded-2xl bg-white/90 backdrop-blur shadow-xl border border-pink-100 px-6 py-7 space-y-6">
                <p
                    v-if="status"
                    class="text-xs text-emerald-600 bg-emerald-50 border border-emerald-100 rounded-lg px-3 py-2"
                >
                    {{ status }}
                </p>

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
                            placeholder="du@example.com"
                        />
                        <p
                            v-if="errors.email"
                            class="text-[11px] text-rose-500"
                        >
                            {{ errors.email }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        class="w-full inline-flex items-center justify-center rounded-full bg-gradient-to-r from-pink-400 to-rose-400 text-white px-5 py-2.5 text-sm font-semibold shadow-md hover:brightness-105 transition-all disabled:opacity-60 disabled:cursor-not-allowed"
                        :disabled="form.processing"
                    >
                        Link senden
                    </button>
                </form>

                <p class="text-[11px] text-slate-500 text-center">
                    <Link
                        :href="route('login')"
                        class="text-rose-500 hover:text-rose-600"
                    >
                        ← Zurück zum Login
                    </Link>
                </p>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

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

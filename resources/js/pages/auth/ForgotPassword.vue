<!-- resources/js/pages/auth/ForgotPassword.vue -->
<template>
    <div
        class="min-h-screen flex items-center justify-center px-4 bg-[radial-gradient(circle_at_top_left,_#FBEAE6_0,_#FFFDF6_50%,_#EFF5E8_100%)] text-[#3C382F]"
    >
        <Head title="Passwort vergessen – favRezepte" />

        <div class="w-full max-w-md">
            <div class="mb-6 text-center">
                <h1 class="text-lg font-semibold text-[#3C382F]">
                    Passwort zurücksetzen
                </h1>
                <p class="mt-1 text-xs text-[#877D6C]">
                    Wir senden dir einen Link zum Zurücksetzen deines Passworts.
                </p>
            </div>

            <div class="rounded-2xl bg-white/90 backdrop-blur shadow-xl border border-[#EBE3D2] px-6 py-7 space-y-6">
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
                            class="w-full rounded-lg border border-[#EBE3D2] bg-white/80 px-3 py-2 text-sm text-[#3C382F] placeholder:text-[#C9C0AE] focus:outline-none focus:ring-2 focus:ring-[#A8C7A4] focus:border-[#A8C7A4]"
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
                        class="w-full inline-flex items-center justify-center rounded-full bg-[#7DA879] hover:bg-[#628F5E] text-white px-5 py-2.5 text-sm font-semibold shadow-md hover:brightness-105 transition-all disabled:opacity-60 disabled:cursor-not-allowed"
                        :disabled="form.processing"
                    >
                        Link senden
                    </button>
                </form>

                <p class="text-[11px] text-[#877D6C] text-center">
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

<!-- resources/js/pages/auth/TwoFactorChallenge.vue -->
<template>
    <div
        class="min-h-screen flex items-center justify-center px-4 bg-[radial-gradient(circle_at_top_left,_#ffe4f0_0,_#fdf7ff_40%,_#e6f0ff_100%)] text-slate-900"
    >
        <Head title="Zwei-Faktor – favRezepte" />

        <div class="w-full max-w-md rounded-2xl bg-white/90 backdrop-blur shadow-xl border border-pink-100 px-6 py-7 space-y-6">
            <div class="space-y-1">
                <h1 class="text-lg font-semibold text-slate-900">
                    Zwei-Faktor-Authentifizierung
                </h1>
                <p class="text-xs text-slate-500">
                    Gib deinen Authenticator-Code oder deinen Recovery-Code ein.
                </p>
            </div>

            <div class="flex gap-2 text-[11px]">
                <button
                    type="button"
                    class="flex-1 rounded-full border border-pink-100 px-3 py-1.5 text-slate-600 hover:border-pink-300 hover:text-slate-900 transition-colors"
                    :class="{ 'bg-pink-50': !usingRecoveryCodes }"
                    @click="usingRecoveryCodes = false"
                >
                    Authenticator-Code
                </button>
                <button
                    type="button"
                    class="flex-1 rounded-full border border-pink-100 px-3 py-1.5 text-slate-600 hover:border-pink-300 hover:text-slate-900 transition-colors"
                    :class="{ 'bg-pink-50': usingRecoveryCodes }"
                    @click="usingRecoveryCodes = true"
                >
                    Recovery-Code
                </button>
            </div>

            <form
                class="space-y-4"
                @submit.prevent="submit"
            >
                <div
                    v-if="!usingRecoveryCodes"
                    class="space-y-1.5"
                >
                    <label
                        class="text-xs font-medium text-slate-700"
                        for="code"
                    >
                        Authenticator-Code
                    </label>
                    <input
                        id="code"
                        v-model="form.code"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        class="w-full rounded-lg border border-pink-100 bg-white/80 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-pink-300 focus:border-pink-300"
                        placeholder="123456"
                    />
                </div>

                <div
                    v-else
                    class="space-y-1.5"
                >
                    <label
                        class="text-xs font-medium text-slate-700"
                        for="recovery_code"
                    >
                        Recovery-Code
                    </label>
                    <input
                        id="recovery_code"
                        v-model="form.recovery_code"
                        type="text"
                        autocomplete="one-time-code"
                        class="w-full rounded-lg border border-pink-100 bg-white/80 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-300 focus:outline-none focus:ring-2 focus:ring-pink-300 focus:border-pink-300"
                        placeholder="xxxx-xxxx-xxxx-xxxx"
                    />
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
import { ref } from 'vue';

const usingRecoveryCodes = ref(false);

const form = useForm({
    code: '',
    recovery_code: '',
});

function submit() {
    form.post(route('two-factor.login'));
}
</script>

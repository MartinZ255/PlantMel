<!-- resources/js/pages/auth/TwoFactorChallenge.vue -->
<template>
    <div
        class="min-h-screen flex items-center justify-center px-4 bg-[radial-gradient(circle_at_top_left,_#FBEAE6_0,_#FFFDF6_50%,_#EFF5E8_100%)] text-[#3C382F]"
    >
        <Head title="Zwei-Faktor – favRezepte" />

        <div class="w-full max-w-md rounded-2xl bg-white/90 backdrop-blur shadow-xl border border-[#EBE3D2] px-6 py-7 space-y-6">
            <div class="space-y-1">
                <h1 class="text-lg font-semibold text-[#3C382F]">
                    Zwei-Faktor-Authentifizierung
                </h1>
                <p class="text-xs text-[#877D6C]">
                    Gib deinen Authenticator-Code oder deinen Recovery-Code ein.
                </p>
            </div>

            <div class="flex gap-2 text-[11px]">
                <button
                    type="button"
                    class="flex-1 rounded-full border border-[#EBE3D2] px-3 py-1.5 text-[#6C6557] hover:border-[#A8C7A4] hover:text-[#3C382F] transition-colors"
                    :class="{ 'bg-[#E7F0DF]': !usingRecoveryCodes }"
                    @click="usingRecoveryCodes = false"
                >
                    Authenticator-Code
                </button>
                <button
                    type="button"
                    class="flex-1 rounded-full border border-[#EBE3D2] px-3 py-1.5 text-[#6C6557] hover:border-[#A8C7A4] hover:text-[#3C382F] transition-colors"
                    :class="{ 'bg-[#E7F0DF]': usingRecoveryCodes }"
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
                        class="text-xs font-medium text-[#4A463C]"
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
                        class="w-full rounded-lg border border-[#EBE3D2] bg-white/80 px-3 py-2 text-sm text-[#3C382F] placeholder:text-[#C9C0AE] focus:outline-none focus:ring-2 focus:ring-[#A8C7A4] focus:border-[#A8C7A4]"
                        placeholder="123456"
                    />
                </div>

                <div
                    v-else
                    class="space-y-1.5"
                >
                    <label
                        class="text-xs font-medium text-[#4A463C]"
                        for="recovery_code"
                    >
                        Recovery-Code
                    </label>
                    <input
                        id="recovery_code"
                        v-model="form.recovery_code"
                        type="text"
                        autocomplete="one-time-code"
                        class="w-full rounded-lg border border-[#EBE3D2] bg-white/80 px-3 py-2 text-sm text-[#3C382F] placeholder:text-[#C9C0AE] focus:outline-none focus:ring-2 focus:ring-[#A8C7A4] focus:border-[#A8C7A4]"
                        placeholder="xxxx-xxxx-xxxx-xxxx"
                    />
                </div>

                <button
                    type="submit"
                    class="w-full inline-flex items-center justify-center rounded-full bg-[#7DA879] hover:bg-[#628F5E] text-white px-5 py-2.5 text-sm font-semibold shadow-md hover:brightness-105 transition-all disabled:opacity-60 disabled:cursor-not-allowed"
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

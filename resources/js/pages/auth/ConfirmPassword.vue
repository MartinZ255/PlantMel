<!-- resources/js/pages/auth/ConfirmPassword.vue -->
<template>
    <div
        class="min-h-screen flex items-center justify-center px-4 bg-[radial-gradient(circle_at_top_left,_#FBEAE6_0,_#FFFDF6_50%,_#EFF5E8_100%)] text-[#3C382F]"
    >
        <Head title="Passwort bestätigen – favRezepte" />

        <div class="w-full max-w-md rounded-2xl bg-white/90 backdrop-blur shadow-xl border border-[#EBE3D2] px-6 py-7 space-y-6">
            <div class="space-y-1">
                <h1 class="text-lg font-semibold text-[#3C382F]">
                    Passwort bestätigen
                </h1>
                <p class="text-xs text-[#877D6C]">
                    Bitte gib zur Bestätigung dein Passwort ein, bevor du fortfährst.
                </p>
            </div>

            <form
                class="space-y-4"
                @submit.prevent="submit"
            >
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
                        class="w-full rounded-lg border border-[#EBE3D2] bg-white/80 px-3 py-2 text-sm text-[#3C382F] placeholder:text-[#C9C0AE] focus:outline-none focus:ring-2 focus:ring-[#A8C7A4] focus:border-[#A8C7A4]"
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

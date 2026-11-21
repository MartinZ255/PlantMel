<script setup lang="ts">
import AuthShell from '@/components/auth/AuthShell.vue';
import { authLinkClass } from '@/components/auth/authStyles';
import GoogleAuthButton from '@/components/auth/GoogleAuthButton.vue';
import Button from '@/components/ui/Button.vue';
import Field from '@/components/ui/Field.vue';
import TextInput from '@/components/ui/TextInput.vue';
import type { AppPageProps } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

defineProps<{
    canResetPassword: boolean;
    canRegister: boolean;
    status: string | null;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

// E17: Knopf nur bei gesetzter Google-Konfiguration; „Angemeldet bleiben“
// wird an die Weiterleitung übergeben.
const page = usePage<AppPageProps>();
const googleLogin = computed(() => page.props.features?.googleLogin === true);
const googleHref = computed(() =>
    route('auth.google.redirect', form.remember ? { remember: 1 } : {}),
);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthShell
        page-title="Anmelden"
        eyebrow="Anmelden"
        title="Willkommen zurück"
        description="Melde dich an, um deine Sammlungen und Zutaten zu öffnen."
        :status="status"
    >
        <template #title>
            Willkommen
            <span class="accent-word text-primary">zurück</span>
        </template>

        <GoogleAuthButton
            v-if="googleLogin"
            :href="googleHref"
            label="Mit Google anmelden"
            class="mb-5"
        />

        <form
            class="flex flex-col gap-5"
            aria-label="Anmelden"
            @submit.prevent="submit"
        >
            <Field
                id="email"
                v-slot="field"
                label="E-Mail"
                required
                :error="form.errors.email"
            >
                <TextInput
                    v-model="form.email"
                    v-bind="field.inputAttrs"
                    type="email"
                    autocomplete="email"
                />
            </Field>

            <Field
                id="password"
                v-slot="field"
                label="Passwort"
                required
                :error="form.errors.password"
            >
                <TextInput
                    v-model="form.password"
                    v-bind="field.inputAttrs"
                    type="password"
                    autocomplete="current-password"
                />
            </Field>

            <div
                class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1"
            >
                <label
                    class="inline-flex min-h-11 cursor-pointer items-center gap-2.5 text-[0.9375rem] text-ink"
                >
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        class="size-5 shrink-0 cursor-pointer accent-primary"
                    />
                    Angemeldet bleiben
                </label>
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    :class="authLinkClass"
                >
                    Passwort vergessen?
                </Link>
            </div>

            <Button type="submit" size="lg" block :loading="form.processing">
                Einloggen
            </Button>
        </form>

        <template v-if="canRegister" #after>
            <p class="text-ink-muted">
                Noch kein Konto?
                <Link :href="route('register')" :class="authLinkClass">
                    Registrieren
                </Link>
            </p>
        </template>
    </AuthShell>
</template>

<script setup lang="ts">
import AuthShell from '@/components/auth/AuthShell.vue';
import { authLinkClass } from '@/components/auth/authStyles';
import Button from '@/components/ui/Button.vue';
import Field from '@/components/ui/Field.vue';
import TextInput from '@/components/ui/TextInput.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthShell
        page-title="Registrieren"
        eyebrow="Registrieren"
        title="Dein Rezeptgarten beginnt hier"
        description="Lege ein kostenloses Konto an und sammle deine Lieblingsrezepte."
        quote="Jedes Lieblingsrezept war einmal"
        quote-accent="ein erster Versuch."
    >
        <template #title>
            Dein Rezept<span class="accent-word text-primary">garten</span>
            beginnt hier
        </template>

        <form
            class="flex flex-col gap-5"
            aria-label="Registrieren"
            @submit.prevent="submit"
        >
            <Field
                id="name"
                v-slot="field"
                label="Name"
                required
                :error="form.errors.name"
            >
                <TextInput
                    v-model="form.name"
                    v-bind="field.inputAttrs"
                    type="text"
                    autocomplete="name"
                />
            </Field>

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
                    autocomplete="new-password"
                />
            </Field>

            <Field
                id="password_confirmation"
                v-slot="field"
                label="Passwort bestätigen"
                required
                :error="form.errors.password_confirmation"
            >
                <TextInput
                    v-model="form.password_confirmation"
                    v-bind="field.inputAttrs"
                    type="password"
                    autocomplete="new-password"
                />
            </Field>

            <!-- Slice 4 (F-10): Informationspflicht, keine Einwilligung (keine Checkbox) -->
            <p
                class="rounded-[1.1rem_0.9rem_1.2rem_0.85rem] bg-tint px-4 py-3 text-sm text-ink-muted"
                data-auth-privacy-note
            >
                Mit der Registrierung gilt unsere
                <Link
                    :href="route('legal.privacy')"
                    class="font-semibold text-primary underline decoration-berry/70 decoration-2 underline-offset-4 hover:text-primary-hover"
                    >Datenschutzerklärung</Link
                >.
            </p>

            <Button type="submit" size="lg" block :loading="form.processing">
                Konto erstellen
            </Button>
        </form>

        <template #after>
            <p class="text-ink-muted">
                Bereits registriert?
                <Link :href="route('login')" :class="authLinkClass">
                    Zum Login
                </Link>
            </p>
        </template>
    </AuthShell>
</template>

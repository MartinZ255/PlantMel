<script setup lang="ts">
import AuthShell from '@/components/auth/AuthShell.vue';
import Button from '@/components/ui/Button.vue';
import Field from '@/components/ui/Field.vue';
import TextInput from '@/components/ui/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

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
    // Fortify benennt POST /reset-password als "password.update" (F-01)
    form.post(route('password.update'));
}
</script>

<template>
    <AuthShell
        page-title="Neues Passwort"
        eyebrow="Neues Passwort"
        title="Neues Passwort setzen"
        description="Vergib ein neues Passwort für deinen Account."
        quote="Frisch gesät ist"
        quote-accent="halb geerntet."
    >
        <template #title>
            Neues Passwort
            <span class="accent-word text-primary">setzen</span>
        </template>

        <form
            class="flex flex-col gap-5"
            aria-label="Neues Passwort setzen"
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
                label="Neues Passwort"
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

            <Button type="submit" size="lg" block :loading="form.processing">
                Passwort speichern
            </Button>
        </form>
    </AuthShell>
</template>

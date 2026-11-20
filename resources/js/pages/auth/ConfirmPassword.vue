<script setup lang="ts">
import AuthShell from '@/components/auth/AuthShell.vue';
import Button from '@/components/ui/Button.vue';
import Field from '@/components/ui/Field.vue';
import TextInput from '@/components/ui/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

defineProps<{
    errors: Record<string, string>;
}>();

const form = useForm({
    password: '',
});

function submit() {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <AuthShell
        page-title="Passwort bestätigen"
        eyebrow="Sicherheitsabfrage"
        title="Passwort bestätigen"
        description="Bitte gib zur Bestätigung dein Passwort ein, bevor du fortfährst."
        quote="Ein gutes Rezept verrät man"
        quote-accent="nur Vertrauten."
    >
        <template #title>
            Passwort
            <span class="accent-word text-primary">bestätigen</span>
        </template>

        <form
            class="flex flex-col gap-5"
            aria-label="Passwort bestätigen"
            @submit.prevent="submit"
        >
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

            <Button type="submit" size="lg" block :loading="form.processing">
                Bestätigen
            </Button>
        </form>
    </AuthShell>
</template>

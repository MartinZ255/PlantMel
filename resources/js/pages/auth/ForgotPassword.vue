<script setup lang="ts">
import AuthShell from '@/components/auth/AuthShell.vue';
import { authLinkClass } from '@/components/auth/authStyles';
import Button from '@/components/ui/Button.vue';
import Field from '@/components/ui/Field.vue';
import TextInput from '@/components/ui/TextInput.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { route } from 'ziggy-js';

// Fehlermeldungen wie "Sitzung abgelaufen" (419) zeigt AuthShell (flash.error)
defineProps<{
    status?: string | null;
    errors: Record<string, string>;
}>();

const form = useForm({
    email: '',
});

function submit() {
    form.post(route('password.email'));
}
</script>

<template>
    <AuthShell
        page-title="Passwort vergessen"
        eyebrow="Passwort vergessen"
        title="Passwort zurücksetzen"
        description="Wir senden dir einen Link zum Zurücksetzen deines Passworts."
        :status="status"
        quote="Auch Kräuter brauchen manchmal"
        quote-accent="einen Neuanfang."
    >
        <template #title>
            Passwort
            <span class="accent-word text-primary">zurücksetzen</span>
        </template>

        <form
            class="flex flex-col gap-5"
            aria-label="Passwort zurücksetzen"
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
                    placeholder="du@example.com"
                />
            </Field>

            <Button type="submit" size="lg" block :loading="form.processing">
                Link senden
            </Button>
        </form>

        <template #after>
            <Link :href="route('login')" :class="[authLinkClass, 'gap-1.5']">
                <ArrowLeft class="size-4" aria-hidden="true" />
                Zurück zum Login
            </Link>
        </template>
    </AuthShell>
</template>

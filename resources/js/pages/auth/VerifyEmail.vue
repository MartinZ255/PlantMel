<script setup lang="ts">
import AuthShell from '@/components/auth/AuthShell.vue';
import Button from '@/components/ui/Button.vue';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps<{
    status?: string | null;
}>();

const statusText = computed(() =>
    props.status === 'verification-link-sent'
        ? 'Ein neuer Bestätigungslink wurde an deine E-Mail-Adresse gesendet.'
        : null,
);

const form = useForm({});

function submit() {
    form.post(route('verification.send'));
}
</script>

<template>
    <AuthShell
        page-title="E-Mail bestätigen"
        eyebrow="Fast geschafft"
        title="E-Mail-Adresse bestätigen"
        description="Wir haben dir einen Bestätigungslink gesendet. Bitte prüfe dein Postfach."
        :status="statusText"
        quote="Was Wurzeln schlägt,"
        quote-accent="braucht etwas Geduld."
    >
        <template #title>
            E-Mail-Adresse
            <span class="accent-word text-primary">bestätigen</span>
        </template>

        <form
            class="flex flex-col gap-4"
            aria-label="Bestätigungslink erneut senden"
            @submit.prevent="submit"
        >
            <p class="text-ink-muted">
                Keine E-Mail erhalten? Wir schicken dir gern einen neuen Link.
            </p>
            <Button type="submit" size="lg" block :loading="form.processing">
                Link erneut senden
            </Button>
        </form>
    </AuthShell>
</template>

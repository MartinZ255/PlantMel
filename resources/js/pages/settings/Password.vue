<script setup lang="ts">
import SettingsShell from '@/components/settings/SettingsShell.vue';
import Button from '@/components/ui/Button.vue';
import Field from '@/components/ui/Field.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import TextInput from '@/components/ui/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.put(route('user-password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <SettingsShell
        page-title="Passwort ändern"
        title="Passwort ändern"
        description="Wähle ein sicheres Passwort für dein Konto"
        current="password"
    >
        <template #title>
            Passwort
            <span class="accent-word text-primary">ändern</span>
        </template>

        <section
            class="relative overflow-hidden rounded-[2rem_1.6rem_2.25rem_1.5rem] bg-surface p-5 shadow-card sm:p-8"
            aria-labelledby="password-section-title"
        >
            <LeafOrnament
                variant="leaf"
                class="pointer-events-none absolute -top-8 -right-6 h-32 rotate-[32deg] text-tint"
            />
            <h2
                id="password-section-title"
                class="relative font-display text-3xl font-semibold text-ink"
            >
                Neues Passwort
            </h2>
            <p class="relative mt-1 max-w-prose text-ink-muted">
                Zur Sicherheit brauchen wir dein aktuelles Passwort.
            </p>

            <form
                class="relative mt-6 flex max-w-xl flex-col gap-5"
                @submit.prevent="submit"
            >
                <Field
                    id="current_password"
                    v-slot="field"
                    label="Aktuelles Passwort"
                    required
                    :error="form.errors.current_password"
                >
                    <TextInput
                        v-model="form.current_password"
                        v-bind="field.inputAttrs"
                        type="password"
                        autocomplete="current-password"
                    />
                </Field>

                <Field
                    id="password"
                    v-slot="field"
                    label="Neues Passwort"
                    required
                    hint="Das Passwort sollte mindestens 8 Zeichen lang sein."
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

                <div
                    class="mt-2 flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between"
                >
                    <Button :href="route('settings.index')" variant="ghost">
                        Zurück
                    </Button>
                    <Button type="submit" :loading="form.processing">
                        Passwort ändern
                    </Button>
                </div>
            </form>
        </section>
    </SettingsShell>
</template>

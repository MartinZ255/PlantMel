<script setup lang="ts">
import SettingsShell from '@/components/settings/SettingsShell.vue';
import Button from '@/components/ui/Button.vue';
import Dialog from '@/components/ui/Dialog.vue';
import Field from '@/components/ui/Field.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import TextInput from '@/components/ui/TextInput.vue';
import type { AppPageProps } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { CircleCheck, TriangleAlert } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

defineProps<{
    mustVerifyEmail: boolean;
    status?: string | null;
}>();

const page = usePage<AppPageProps>();
const user = computed(() => page.props.auth?.user ?? null);

const form = useForm({
    name: '',
    email: '',
});

// Form-Werte setzen, sobald User verfügbar ist
if (user.value) {
    form.name = user.value.name ?? '';
    form.email = user.value.email ?? '';
}

const submit = () => {
    form.patch(route('profile.update'));
};

// --- Konto löschen: Bestätigung im Dialog (statt window.confirm) ---------------
const deleteForm = useForm({
    password: '',
});
const isDeleteOpen = ref(false);

const openDelete = () => {
    deleteForm.clearErrors();
    isDeleteOpen.value = true;
};
const closeDelete = () => {
    if (deleteForm.processing) return;
    isDeleteOpen.value = false;
    deleteForm.reset('password');
    deleteForm.clearErrors();
};
const deleteAccount = () => {
    deleteForm.delete(route('profile.destroy'), {
        preserveScroll: true,
        onError: () => deleteForm.reset('password'),
    });
};
</script>

<template>
    <SettingsShell
        page-title="Profil bearbeiten"
        title="Profil bearbeiten"
        description="Aktualisiere deine persönlichen Informationen"
        current="profile"
    >
        <template #title>
            Profil
            <span class="accent-word text-primary">bearbeiten</span>
        </template>

        <p
            v-if="status"
            class="flex items-start gap-3 rounded-[1.25rem_1rem_1.4rem_0.9rem] border border-primary/30 bg-success-surface px-4 py-3 text-sm font-medium text-wrap-anywhere text-success shadow-card"
            role="status"
        >
            <CircleCheck class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <span class="min-w-0 flex-1">{{ status }}</span>
        </p>

        <section
            class="relative overflow-hidden rounded-[2rem_1.6rem_2.25rem_1.5rem] bg-surface p-5 shadow-card sm:p-8"
            aria-labelledby="profile-section-title"
        >
            <LeafOrnament
                variant="eucalyptus"
                class="pointer-events-none absolute -top-10 -right-4 h-36 rotate-[38deg] text-tint"
            />
            <h2
                id="profile-section-title"
                class="relative font-display text-3xl font-semibold text-ink"
            >
                Persönliche Angaben
            </h2>
            <p class="relative mt-1 max-w-prose text-ink-muted">
                So erscheinst du in PlantMel.
            </p>

            <form
                class="relative mt-6 flex max-w-xl flex-col gap-5"
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

                <div class="flex flex-col gap-2">
                    <Field
                        id="email"
                        v-slot="field"
                        label="E-Mail-Adresse"
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
                    <p
                        v-if="
                            mustVerifyEmail && user?.email_verified_at === null
                        "
                        class="rounded-[1rem_0.8rem_1.1rem_0.75rem] bg-warning-surface px-3.5 py-2.5 text-sm text-warning"
                    >
                        Deine E-Mail-Adresse ist noch nicht verifiziert.
                        <Link
                            :href="route('verification.notice')"
                            class="font-semibold underline underline-offset-4"
                        >
                            Klicke hier, um eine Verifizierungs-E-Mail zu
                            erhalten.
                        </Link>
                    </p>
                </div>

                <div
                    class="mt-2 flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between"
                >
                    <Button :href="route('settings.index')" variant="ghost">
                        Zurück
                    </Button>
                    <Button type="submit" :loading="form.processing">
                        Speichern
                    </Button>
                </div>
            </form>
        </section>

        <!-- Konto löschen -->
        <section
            class="relative overflow-hidden rounded-[1.75rem_2rem_1.5rem_2.25rem] border-[1.5px] border-danger/35 bg-danger-surface p-5 sm:p-8"
            aria-labelledby="delete-section-title"
        >
            <div
                class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between"
            >
                <div class="min-w-0">
                    <h2
                        id="delete-section-title"
                        class="inline-flex items-center gap-2 font-display text-3xl font-semibold text-ink"
                    >
                        <TriangleAlert
                            class="size-6 shrink-0 text-danger"
                            aria-hidden="true"
                        />
                        Konto löschen
                    </h2>
                    <p class="mt-1 font-semibold text-danger">
                        Diese Aktion kann nicht rückgängig gemacht werden
                    </p>
                    <p class="mt-2 max-w-prose text-ink-muted">
                        Wenn du dein Konto löschst, werden alle deine Daten
                        unwiderruflich entfernt. Bitte stelle sicher, dass du
                        wirklich fortfahren möchtest.
                    </p>
                </div>
                <Button
                    variant="ghost"
                    class="shrink-0 border-[1.5px] border-danger text-danger hover:bg-surface"
                    aria-haspopup="dialog"
                    @click="openDelete"
                >
                    Konto löschen
                </Button>
            </div>
        </section>

        <Dialog
            :open="isDeleteOpen"
            title="Konto endgültig löschen?"
            description="Alle deine Daten, Sammlungen und Einstellungen werden unwiderruflich entfernt. Gib zur Bestätigung dein Passwort ein."
            size="sm"
            @close="closeDelete"
        >
            <form
                id="delete-account-form"
                class="flex flex-col gap-4"
                @submit.prevent="deleteAccount"
            >
                <Field
                    id="delete_password"
                    v-slot="field"
                    label="Passwort zur Bestätigung"
                    required
                    :error="deleteForm.errors.password"
                >
                    <TextInput
                        v-model="deleteForm.password"
                        v-bind="field.inputAttrs"
                        type="password"
                        autocomplete="current-password"
                    />
                </Field>
            </form>
            <template #footer>
                <Button
                    variant="ghost"
                    :disabled="deleteForm.processing"
                    @click="closeDelete"
                >
                    Abbrechen
                </Button>
                <Button
                    type="submit"
                    form="delete-account-form"
                    variant="danger"
                    :loading="deleteForm.processing"
                >
                    Endgültig löschen
                </Button>
            </template>
        </Dialog>
    </SettingsShell>
</template>

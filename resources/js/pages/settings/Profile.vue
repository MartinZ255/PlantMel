<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import { useForm, Head } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    mustVerifyEmail: boolean;
    status?: string | null;
}>();

const page = usePage();
const user = computed(() => (page.props as any).auth?.user);

const form = useForm({
    name: '',
    email: '',
});

// Form-Werte setzen, sobald User verfügbar ist
if (user.value) {
    form.name = user.value.name ?? '';
    form.email = user.value.email ?? '';
}

const deleteForm = useForm({
    password: '',
});

const submit = () => {
    form.patch(route('profile.update'));
};

const deleteAccount = () => {
    if (confirm('Bist du sicher, dass du dein Konto löschen möchtest? Diese Aktion kann nicht rückgängig gemacht werden.')) {
        deleteForm.delete(route('profile.destroy'));
    }
};
</script>

<template>
    <AppLayout page-title="Profil bearbeiten">
        <Head title="Profil bearbeiten" />

        <section class="content-section">
            <div class="section-header">
                <div class="section-header__title">
                    Profil bearbeiten
                </div>
                <div class="section-header__meta">
                    Aktualisiere deine persönlichen Informationen
                </div>
            </div>

            <p
                v-if="status"
                class="status-message"
            >
                {{ status }}
            </p>

            <form
                @submit.prevent="submit"
                class="recipe-form"
            >
                <div class="recipe-form__section">
                    <div class="form-field">
                        <label
                            for="name"
                            class="form-field__label"
                        >
                            Name
                        </label>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="form-field__input"
                            required
                            autocomplete="name"
                        >
                        <p
                            v-if="form.errors.name"
                            class="form-field__error"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div class="form-field">
                        <label
                            for="email"
                            class="form-field__label"
                        >
                            E-Mail-Adresse
                        </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="form-field__input"
                            required
                            autocomplete="email"
                        >
                        <p
                            v-if="form.errors.email"
                            class="form-field__error"
                        >
                            {{ form.errors.email }}
                        </p>
                        <p
                            v-if="mustVerifyEmail && user?.email_verified_at === null"
                            class="form-field__hint"
                        >
                            Deine E-Mail-Adresse ist noch nicht verifiziert.
                            <a
                                :href="route('verification.notice')"
                                class="underline-text"
                            >
                                Klicke hier, um eine Verifizierungs-E-Mail zu erhalten.
                            </a>
                        </p>
                    </div>
                </div>

                <div class="form-actions">
                    <div class="form-actions__left">
                        <a
                            :href="route('settings.index')"
                            class="secondary-pill-button"
                        >
                            Zurück
                        </a>
                    </div>
                    <div class="form-actions__right">
                        <button
                            type="submit"
                            class="primary-pill-button"
                            :disabled="form.processing"
                        >
                            Speichern
                        </button>
                    </div>
                </div>
            </form>
        </section>

        <!-- Konto löschen -->
        <section class="content-section">
            <div class="section-header">
                <div class="section-header__title">
                    Konto löschen
                </div>
                <div class="section-header__meta">
                    Diese Aktion kann nicht rückgängig gemacht werden
                </div>
            </div>

            <p>
                Wenn du dein Konto löschst, werden alle deine Daten unwiderruflich
                entfernt. Bitte stelle sicher, dass du wirklich fortfahren möchtest.
            </p>

            <form
                @submit.prevent="deleteAccount"
                class="recipe-form"
            >
                <div class="recipe-form__section">
                    <div class="form-field">
                        <label
                            for="password"
                            class="form-field__label"
                        >
                            Passwort zur Bestätigung
                        </label>
                        <input
                            id="password"
                            v-model="deleteForm.password"
                            type="password"
                            class="form-field__input"
                            required
                            autocomplete="current-password"
                        >
                        <p
                            v-if="deleteForm.errors.password"
                            class="form-field__error"
                        >
                            {{ deleteForm.errors.password }}
                        </p>
                    </div>
                </div>

                <div class="form-actions">
                    <div class="form-actions__left" />
                    <div class="form-actions__right">
                        <button
                            type="submit"
                            class="primary-pill-button"
                            style="background: #b95454; border-color: #b95454;"
                            :disabled="deleteForm.processing"
                        >
                            Konto löschen
                        </button>
                    </div>
                </div>
            </form>
        </section>
    </AppLayout>
</template>


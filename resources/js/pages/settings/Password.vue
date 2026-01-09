<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import { useForm, Head } from '@inertiajs/vue3';
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
    <AppLayout page-title="Passwort ändern">
        <Head title="Passwort ändern" />

        <section class="content-section">
            <div class="section-header">
                <div class="section-header__title">
                    Passwort ändern
                </div>
                <div class="section-header__meta">
                    Wähle ein sicheres Passwort für dein Konto
                </div>
            </div>

            <form
                @submit.prevent="submit"
                class="recipe-form"
            >
                <div class="recipe-form__section">
                    <div class="form-field">
                        <label
                            for="current_password"
                            class="form-field__label"
                        >
                            Aktuelles Passwort
                        </label>
                        <input
                            id="current_password"
                            v-model="form.current_password"
                            type="password"
                            class="form-field__input"
                            required
                            autocomplete="current-password"
                        >
                        <p
                            v-if="form.errors.current_password"
                            class="form-field__error"
                        >
                            {{ form.errors.current_password }}
                        </p>
                    </div>

                    <div class="form-field">
                        <label
                            for="password"
                            class="form-field__label"
                        >
                            Neues Passwort
                        </label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            class="form-field__input"
                            required
                            autocomplete="new-password"
                        >
                        <p
                            v-if="form.errors.password"
                            class="form-field__error"
                        >
                            {{ form.errors.password }}
                        </p>
                        <p class="form-field__hint">
                            Das Passwort sollte mindestens 8 Zeichen lang sein.
                        </p>
                    </div>

                    <div class="form-field">
                        <label
                            for="password_confirmation"
                            class="form-field__label"
                        >
                            Passwort bestätigen
                        </label>
                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            class="form-field__input"
                            required
                            autocomplete="new-password"
                        >
                        <p
                            v-if="form.errors.password_confirmation"
                            class="form-field__error"
                        >
                            {{ form.errors.password_confirmation }}
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
                            Passwort ändern
                        </button>
                    </div>
                </div>
            </form>
        </section>
    </AppLayout>
</template>


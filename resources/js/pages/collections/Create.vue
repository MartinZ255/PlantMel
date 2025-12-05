<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    description: '',
});

const submit = () => {
    form.post(route('collections.store'));
};

const cancel = () => {
    router.visit(route('collections.index'));
};
</script>

<template>
    <AppLayout page-title="Neue Sammlung">
        <Head title="Neue Sammlung anlegen" />

        <div class="page">
            <div class="layout">
                <section class="content-section">
                    <div class="section-header">
                        <div>
                            <div class="section-header__title">
                                Neue Sammlung anlegen
                            </div>
                            <div class="section-header__meta">
                                Erstelle eine Sammlung, um deine Rezepte in
                                thematischen Listen zu organisieren.
                            </div>
                        </div>

                        <button
                            type="button"
                            class="secondary-pill-button"
                            @click="cancel"
                        >
                            Abbrechen
                        </button>
                    </div>

                    <form class="recipe-form" @submit.prevent="submit">
                        <div class="recipe-form__section recipe-form__section--wide">
                            <h2 class="recipe-form__section-title">
                                Basis-Informationen
                            </h2>
                            <p class="recipe-form__section-hint">
                                Gib deiner Sammlung einen klaren Namen und
                                beschreibe kurz, welche Rezepte hineingehören.
                            </p>

                            <!-- Name -->
                            <div class="form-field">
                                <label
                                    class="form-field__label"
                                    for="collection-name"
                                >
                                    Name der Sammlung
                                </label>
                                <input
                                    id="collection-name"
                                    v-model="form.name"
                                    type="text"
                                    class="form-field__input"
                                    placeholder="z. B. Sonntagskuchen, Mealprep unter der Woche"
                                    required
                                />
                                <p v-if="form.errors.name" class="form-field__error">
                                    {{ form.errors.name }}
                                </p>
                            </div>

                            <!-- Beschreibung -->
                            <div class="form-field">
                                <label
                                    class="form-field__label"
                                    for="collection-description"
                                >
                                    Beschreibung
                                    <span class="inline-badge">optional</span>
                                </label>
                                <textarea
                                    id="collection-description"
                                    v-model="form.description"
                                    class="form-field__textarea"
                                    rows="3"
                                    placeholder="Kurze Beschreibung der Sammlung – wofür ist sie gedacht?"
                                />
                                <p
                                    v-if="form.errors.description"
                                    class="form-field__error"
                                >
                                    {{ form.errors.description }}
                                </p>
                            </div>
                        </div>

                        <div class="form-actions">
                            <div class="form-actions__left">
                                Du kannst später Rezepte zu dieser Sammlung
                                hinzufügen oder entfernen.
                            </div>

                            <div class="form-actions__right">
                                <button
                                    type="button"
                                    class="secondary-pill-button"
                                    @click="cancel"
                                >
                                    Abbrechen
                                </button>
                                <button
                                    type="submit"
                                    class="primary-pill-button"
                                    :disabled="form.processing"
                                >
                                    Sammlung erstellen
                                </button>
                            </div>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import { usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const page = usePage();
const user = page.props.auth?.user ?? null;

</script>

<template>
    <AppLayout page-title="PlantMel – Deine Rezept-Sammlung">
        <section class="content-section">
            <div class="section-header">
                <div class="section-header__title">
                    PlantMel – Lieblingsrezepte an einem Ort
                </div>
                <div class="section-header__meta">
                    Speichere, sortiere und entdecke pflanzenbetonte Rezepte für deinen Alltag.
                </div>
            </div>

            <p>
                Mit PlantMel behältst du den Überblick über deine Rezepte:
                Du kannst Gerichte nach Zutaten suchen, eigene Sammlungen
                für Anlässe wie „Unter der Woche“, „Besuch“ oder „Meal Prep“
                anlegen und neue Ideen ausprobieren.
            </p>

            <ul>
                <li>Rezepte nach Zutaten und Kategorien filtern</li>
                <li>Eigene Sammlungen erstellen und verwalten</li>
                <li>Bewertungen und Notizen zu Rezepten hinterlegen</li>
            </ul>

            <div class="section-header__actions">
                <template v-if="user">
                    <a
                        :href="route('home')"
                        class="primary-pill-button"
                    >
                        Zu meinem Dashboard
                    </a>
                    <a
                        :href="route('recipes.index')"
                        class="secondary-pill-button"
                    >
                        Rezepte durchsuchen
                    </a>
                </template>
                <template v-else>
                    <a
                        :href="route('login')"
                        class="primary-pill-button"
                    >
                        Anmelden
                    </a>
                    <a
                        v-if="page.props.canRegister"
                        :href="route('register')"
                        class="secondary-pill-button"
                    >
                        Konto erstellen
                    </a>
                </template>
            </div>
        </section>
    </AppLayout>
</template>

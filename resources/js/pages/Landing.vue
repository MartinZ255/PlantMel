<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import type { AppPageProps } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const page = usePage<AppPageProps<{ canRegister?: boolean }>>();
const user = computed(() => page.props.auth?.user ?? null);
</script>

<template>
    <AppLayout page-title="PlantMel – Deine Rezept-Sammlung">
        <section class="content-section">
            <div class="section-header">
                <h1 class="section-header__title">
                    PlantMel – Lieblingsrezepte an einem Ort
                </h1>
                <div class="section-header__meta">
                    Speichere, sortiere und entdecke pflanzenbetonte Rezepte für
                    deinen Alltag.
                </div>
            </div>

            <p>
                Mit PlantMel behältst du den Überblick über deine Rezepte: Du
                kannst Gerichte nach Zutaten suchen, eigene Sammlungen für
                Anlässe wie „Unter der Woche“, „Besuch“ oder „Meal Prep“ anlegen
                und neue Ideen ausprobieren.
            </p>

            <!-- E8/F-55: nur vorhandene Funktionen bewerben -->
            <ul>
                <li>
                    Rezepte durchsuchen und nach Zutaten und Kategorien filtern
                </li>
                <li>Eigene Sammlungen erstellen und verwalten</li>
                <li>In der Zutatensuche alle Zutaten auf einen Blick finden</li>
            </ul>

            <div class="section-header__actions">
                <template v-if="user">
                    <a :href="route('home')" class="primary-pill-button">
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
                    <!-- F-21: oeffentlicher Einstieg ohne Anmeldung -->
                    <a
                        :href="route('recipes.index')"
                        class="primary-pill-button"
                    >
                        Rezepte entdecken
                    </a>
                    <a :href="route('login')" class="secondary-pill-button">
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

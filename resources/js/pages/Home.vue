<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import RecipeCollectionBoard from '@/components/recipes/RecipeCollectionBoard.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

interface HomeStats {
    totalRecipes: number;
    totalCollections: number;
}

interface LatestRecipe {
    id: number | string;
    name: string;
    categories: string[];
    image: string | null;
}

const page = usePage<{
    stats?: HomeStats;
    latestRecipes?: LatestRecipe[];
}>();

const stats = computed<HomeStats>(() => page.props.stats ?? {
    totalRecipes: 0,
    totalCollections: 0,
});

const latestRecipes = computed<LatestRecipe[]>(() => page.props.latestRecipes ?? []);

const highlightCollections = computed(() => [
    {
        id: 'collections',
        title: 'Deine Sammlungen',
        count: stats.value.totalCollections,
    },
    {
        id: 'all',
        title: 'Alle Rezepte',
        count: stats.value.totalRecipes,
    },
]);
</script>

<template>
    <AppLayout page-title="Willkommen bei PlantMel">
        <!-- Intro / Hero -->
        <section class="content-section">
            <div class="section-header">
                <div class="section-header__title">
                    Willkommen in deiner Rezeptwelt 🌿
                </div>
                <div class="section-header__meta">
                    Sammle, strukturiere und entdecke deine Lieblingsrezepte.
                </div>
            </div>
            <p>
                Auf PlantMel kannst du Rezepte speichern, nach Zutaten filtern, Sammlungen
                für verschiedene Anlässe anlegen und neue Ideen entdecken. Nutze die Suche,
                um schnell etwas Passendes zu finden – oder stöbere einfach durch deine
                Sammlungen.
            </p>
            <div class="section-header__actions">
                <a
                    :href="route('recipes.index')"
                    class="primary-pill-button"
                >
                    Rezepte entdecken
                </a>
                <a
                    :href="route('collections.index')"
                    class="secondary-pill-button"
                >
                    Zu meinen Sammlungen
                </a>
            </div>
        </section>

        <!-- Kurzer Überblick / Stats + Board -->
        <RecipeCollectionBoard
            :collections="highlightCollections"
            :total="stats.totalRecipes"
        />

        <!-- Letzte Rezepte -->
        <section
            v-if="latestRecipes.length"
            class="content-section"
        >
            <div class="section-header">
                <div class="section-header__title">
                    Zuletzt hinzugefügte Rezepte
                </div>
                <div class="section-header__meta">
                    {{ latestRecipes.length }} aktuelle Einträge
                </div>
            </div>

            <div class="recipes-grid">
                <article
                    v-for="recipe in latestRecipes"
                    :key="recipe.id"
                    class="recipe-detail-card"
                >
                    <a
                        class="recipe-card__link"
                        :href="route('recipes.show', recipe.id)"
                    >
                        <div class="recipe-card__image">
                            <span v-if="!recipe.image">
                                Kein Bild vorhanden
                            </span>
                            <img
                                v-else
                                :src="recipe.image"
                                :alt="recipe.name"
                                class="image"
                            >
                        </div>
                        <div class="recipe-card__body">
                            <h3 class="recipe-card__title">
                                {{ recipe.name }}
                            </h3>
                            <div
                                v-if="recipe.categories.length"
                                class="recipe-card__tags"
                            >
                                <span
                                    v-for="cat in recipe.categories"
                                    :key="cat"
                                    class="recipe-card__tag"
                                >
                                    {{ cat }}
                                </span>
                            </div>
                        </div>
                    </a>
                </article>
            </div>
        </section>
    </AppLayout>
</template>

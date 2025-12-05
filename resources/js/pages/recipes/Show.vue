<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import AddToCollectionModal from '@/components/widgets/AddToCollectionModal.vue';
import { route } from 'ziggy-js';
import { computed, ref } from 'vue';

type RatingDimension = {
    id: number | null;
    name: string;
    avg: number;   // 1.0–5.0
    count: number; // Anzahl Bewertungen für diese Dimension
};

type CollectionOption = {
    id: number;
    name: string;
};

const props = defineProps<{
    recipe: {
        id: number;
        name: string;
        description: string | null;
        image: string | null;
        duration_minutes: number | null;
        servings: number | null;
        difficulty: string | null;
        categories: string[];
        tags: string[];
        rating: number | null;             // Gesamtbewertung
        ratingCount: number;               // Anzahl Bewertungen
        ratingDimensions: RatingDimension[];
        source: string | null;
        ingredients: { amount: string | null; name: string | null }[];
        steps: string[];
        notes: string[];
        createdAt: string | null;
        updatedAt: string | null;
    };
    collections: CollectionOption[];
}>();

const recipe = props.recipe;

const hasAnyRatings = computed(
    () => recipe.rating != null && recipe.ratingCount > 0,
);

const ratingDimensions = computed(
    () => recipe.ratingDimensions ?? [],
);

const collections = computed(() => props.collections);

// Modal-State für "Zur Sammlung hinzufügen"
const isCollectionModalOpen = ref(false);

const openCollectionModal = () => {
    isCollectionModalOpen.value = true;
};

const closeCollectionModal = () => {
    isCollectionModalOpen.value = false;
};

// Hilfsfunktion für Sterne 0–5
const getStars = (value: number | null) => {
    if (value == null || Number.isNaN(value)) return { filled: 0, empty: 5 };
    const clamped = Math.min(5, Math.max(0, Math.round(value)));
    return {
        filled: clamped,
        empty: 5 - clamped,
    };
};
</script>

<template>
    <AppLayout :page-title="recipe.name">
        <div class="page">
            <div class="layout">
                <!-- Link zurück / Breadcrumb -->
                <div class="breadcrumb">
                    <a
                        :href="route('recipes.index')"
                        class="breadcrumb-link"
                    >
                        ← Zur Übersicht
                    </a>
                </div>

                <!-- Header: Bild + Metadaten -->
                <header class="header">
                    <div class="image-wrapper" v-if="recipe.image">
                        <img :src="recipe.image" :alt="recipe.name" class="image" />
                    </div>

                    <!-- Header-Content mit Button oben rechts -->
                    <div class="header-content recipe-container">
                        <button
                            type="button"
                            class="recipe-button"
                            @click="openCollectionModal"
                        >
                            Zur Sammlung hinzufügen
                        </button>

                        <h1 class="title">
                            {{ recipe.name }}
                        </h1>
                        <p class="description" v-if="recipe.description">
                            {{ recipe.description }}
                        </p>

                        <div class="meta-grid">
                            <div class="meta-card">
                                <span class="meta-label">Zubereitung</span>
                                <span class="meta-value">
                                    {{ recipe.duration_minutes !== null
                                    ? recipe.duration_minutes + ' Min.'
                                    : '–' }}
                                </span>
                            </div>
                            <div class="meta-card">
                                <span class="meta-label">Portionen</span>
                                <span class="meta-value">
                                    {{ recipe.servings !== null ? recipe.servings : '–' }}
                                </span>
                            </div>
                        </div>

                        <div class="tags" v-if="recipe.categories?.length">
                            <div
                                class="pill pill-soft"
                                v-for="cat in recipe.categories"
                                :key="cat"
                            >
                                {{ cat }}
                            </div>
                        </div>

                        <div class="tags tags-secondary" v-if="recipe.tags?.length">
                            <div
                                class="pill pill-outline"
                                v-for="tag in recipe.tags"
                                :key="tag"
                            >
                                #{{ tag }}
                            </div>
                        </div>

                        <div class="source-row" v-if="recipe.source">
                            Quelle: <span class="source">{{ recipe.source }}</span>
                        </div>
                    </div>

                    <!-- Bewertungen -->
                    <section class="rating-section">
                        <h2 class="section-title">Bewertungen</h2>

                        <div
                            v-if="ratingDimensions.length"
                            class="rating-dimensions-grid"
                        >
                            <div
                                v-for="dim in ratingDimensions"
                                :key="dim.id ?? dim.name"
                                class="card"
                            >
                                <div class="rating-dimension-header">
                                    <span class="rating-dimension-name">
                                        {{ dim.name }}
                                    </span>
                                    <span class="rating-dimension-value">
                                        {{ dim.avg.toFixed(1) }} / 5
                                    </span>
                                </div>

                                <div class="rating-dimension-stars">
                                    <span class="stars-filled">
                                        {{ '★ '.repeat(getStars(dim.avg).filled).trim() }}
                                    </span>
                                    <span class="stars-empty">
                                        {{ '★ '.repeat(getStars(dim.avg).empty).trim() }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="rating-overall" v-if="hasAnyRatings">
                            <div class="rating-overall-label">
                                Gesamtbewertung
                            </div>
                            <div class="rating-overall-main">
                                <span class="rating-overall-stars">
                                    <span class="stars-filled">
                                        {{
                                            '★ '
                                                .repeat(getStars(recipe.rating).filled)
                                                .trim()
                                        }}
                                    </span>
                                    <span class="stars-empty">
                                        {{
                                            '★ '
                                                .repeat(getStars(recipe.rating).empty)
                                                .trim()
                                        }}
                                    </span>
                                </span>
                                <span class="rating-overall-value">
                                    {{ recipe.rating!.toFixed(1) }} / 5
                                </span>
                            </div>
                        </div>

                        <div class="rating-empty" v-else>
                            Noch keine Bewertungen
                        </div>
                    </section>
                </header>

                <main class="content">
                    <!-- Zutaten -->
                    <section class="section" v-if="recipe.ingredients?.length">
                        <h2 class="section-title">Zutaten</h2>
                        <div class="ingredients">
                            <div
                                class="ingredient-row"
                                v-for="(ingredient, index) in recipe.ingredients"
                                :key="ingredient.name ?? index"
                            >
                                <span class="ingredient-amount">
                                    {{ ingredient.amount ?? '-' }}
                                </span>
                                <span class="ingredient-name">
                                    {{ ingredient.name }}
                                </span>
                            </div>
                        </div>
                    </section>

                    <!-- Schritte -->
                    <section class="section" v-if="recipe.steps?.length">
                        <h2 class="section-title">Zubereitung</h2>
                        <ol class="steps">
                            <li
                                v-for="(step, index) in recipe.steps"
                                :key="index"
                                class="step-item"
                            >
                                <div class="step-badge">{{ index + 1 }}</div>
                                <p class="step-text">
                                    {{ step }}
                                </p>
                            </li>
                        </ol>
                    </section>

                    <!-- Notizen -->
                    <section class="section">
                        <h3 class="section-description">Notizen & Tipps</h3>
                        <div class="card" v-if="recipe.notes?.length">
                            <ul class="notes">
                                <li
                                    v-for="(note, index) in recipe.notes"
                                    :key="index"
                                    class="note-item"
                                >
                                    {{ note }}
                                </li>
                            </ul>
                        </div>
                    </section>
                </main>
            </div>
        </div>

        <!-- Ausgelagertes Modal zum Hinzufügen zur Sammlung -->
        <AddToCollectionModal
            :open="isCollectionModalOpen"
            :collections="collections"
            :recipe-id="recipe.id"
            @close="closeCollectionModal"
        />
    </AppLayout>
</template>

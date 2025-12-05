<template>
    <AppLayout page-title="Meine Zutaten">
        <!-- Such- / Filterbereich -->
        <IngredientSearchArea
            :tags="ingredientTags"
            :tag-stats="ingredientTagStats"
            @update:results="handleResultsUpdate"
            @update:filters="handleFiltersUpdate"
        />

        <!-- Ergebnisliste / Karten -->
        <section class="content-section">
            <div class="section-header">
                <div>
                    <div class="section-header__title">Zutaten</div>
                    <div class="section-header__subtitle">
                        Alle Zutaten, die du in deinen Rezepten verwendest.
                    </div>
                </div>

                <div class="section-header__meta">
                    {{ ingredientCards.length }} Zutaten gefunden
                </div>
            </div>

            <div class="recipes-grid">
                <IngredientCard
                    v-for="ingredient in ingredientCards"
                    :key="ingredient.id"
                    :ingredient="ingredient"
                />
            </div>
        </section>
    </AppLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import AppLayout from '@/components/layout/AppLayout.vue';
import IngredientSearchArea from '@/components/recipes/IngredientSearchArea.vue';
import IngredientCard from '@/components/recipes/IngredientCard.vue';

interface TagStat {
    key: string;
    label: string;
    count: number;
}

interface Ingredient {
    id: number;
    name: string;
    category: string;
    tags: string[];
}

/**
 * Tags, die in der IngredientSearchArea angezeigt werden
 * – später gerne aus der DB / API laden.
 */
const ingredientTags: string[] = [
    'alle',
    'gemüse',
    'obst',
    'gewürz',
    'käse',
    'nüsse',
    'vegan',
    'glutenfrei',
];

/**
 * Statistiken für das Tag-Panel
 * – derzeit nur Dummywerte, später serverseitig befüllen.
 */
const ingredientTagStats: TagStat[] = [
    { key: 'gemüse',      label: 'Gemüse',      count: 14 },
    { key: 'obst',        label: 'Obst',        count: 7 },
    { key: 'gewürz',      label: 'Gewürze',     count: 9 },
    { key: 'käse',        label: 'Käse',        count: 4 },
    { key: 'nüsse',       label: 'Nüsse',       count: 5 },
    { key: 'vegan',       label: 'Vegan',       count: 21 },
    { key: 'glutenfrei',  label: 'Glutenfrei',  count: 18 },
];

/**
 * Ergebnisliste, die aus IngredientSearchArea kommt.
 * IngredientSearchArea filtert aktuell auf Basis eigener Dummydaten
 * und emittiert die gefilterte Liste via @update:results.
 */
const ingredientCards = ref<Ingredient[]>([]);

/**
 * Wird aufgerufen, wenn IngredientSearchArea neue gefilterte
 * Ergebnisse emittiert.
 */
const handleResultsUpdate = (results: Ingredient[]) => {
    ingredientCards.value = results;
};

/**
 * Optional: wenn du später serverseitig filtern willst, kannst du
 * dieses Event nutzen, um einen API-/Inertia-Call auszulösen.
 */
const handleFiltersUpdate = (payload: {
    searchTerm: string;
    includeIngredients: string;
    excludeIngredients: string;
    activeTag: string;
}) => {
    // TODO: später durch echten Request ersetzen
    console.log('Aktive Zutaten-Filter:', payload);
};
</script>

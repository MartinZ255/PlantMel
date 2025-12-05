<template>
    <AppLayout page-title="Zutatenübersicht">
        <!-- Suche / Filter -->
        <IngredientSearchArea
            :tags="ingredientTags"
            :tag-stats="ingredientTagStats"
            :ingredients="allIngredients"
            @update:results="handleResultsUpdate"
        />

        <!-- Grid aus „Zutat-Karten“ -->
        <section class="content-section">
            <div class="section-header">
                <div class="section-header__title">Zutaten</div>
                <div class="section-header__meta">
                    {{ ingredientCards.length }} Ergebnisse
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
import { computed, ref } from 'vue';
import AppLayout from '@/components/layout/AppLayout.vue';
import IngredientSearchArea from '@/components/recipes/IngredientSearchArea.vue';
import IngredientCard from '@/components/recipes/IngredientCard.vue';

// Typen für Tag-Statistiken
interface TagStat {
    key: string;
    label: string;
    count: number;
}

// Ingredient-Struktur:
// - category: string[] (mehrere Kategorienamen)
interface Ingredient {
    id: number;
    name: string;
    category: string[];
}

// Props von Inertia (aus dem Controller)
// -> ingredients ist OPTIONAL typisiert, damit kein Runtime-Fehler entsteht,
//    falls das Backend es (noch) nicht liefert.
const props = defineProps<{
    ingredients?: Ingredient[];
}>();

// Alle Zutaten aus den Props, mit Fallback auf leeres Array
const allIngredients = computed<Ingredient[]>(() => props.ingredients ?? []);

// Filter-Tags (UI), unabhängig von der DB-Struktur
const ingredientTags = [
    'alle',
    'gemüse',
    'obst',
    'gewürz',
    'vegan',
    'glutenfrei',
];

const ingredientTagStats: TagStat[] = [
    { key: 'gemüse',      label: 'Gemüse',      count: 12 },
    { key: 'obst',        label: 'Obst',        count: 8 },
    { key: 'gewürz',      label: 'Gewürze',     count: 5 },
    { key: 'vegan',       label: 'Vegan',       count: 20 },
    { key: 'glutenfrei',  label: 'Glutenfrei',  count: 15 },
];

// Ergebnisliste – initial alle Zutaten aus der DB (oder [] wenn noch nichts kommt)
const ingredientCards = ref<Ingredient[]>([...allIngredients.value]);

// Wird von IngredientSearchArea aufgerufen, wenn gefilterte Ergebnisse
// (z. B. nach Suchtext / Tag) übergeben werden sollen
const handleResultsUpdate = (results: Ingredient[]) => {
    ingredientCards.value = results;
};
</script>

<template>
    <AppLayout page-title="Zutatenübersicht">
        <!-- Suche / Filter -->
        <!-- Keine Kategorie-Chips/-Zaehler: das Backend liefert keine Kategorien (F-02) -->
        <IngredientSearchArea
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
import AppLayout from '@/components/layout/AppLayout.vue';
import IngredientCard from '@/components/recipes/IngredientCard.vue';
import IngredientSearchArea from '@/components/recipes/IngredientSearchArea.vue';
import { computed, ref } from 'vue';

// Ingredient-Struktur: das Backend liefert id und name; category ist optional
interface Ingredient {
    id: number;
    name: string;
    category?: string[] | null;
}

// Props von Inertia (aus dem Controller)
// -> ingredients ist OPTIONAL typisiert, damit kein Runtime-Fehler entsteht,
//    falls das Backend es (noch) nicht liefert.
const props = defineProps<{
    ingredients?: Ingredient[];
}>();

// Alle Zutaten aus den Props, mit Fallback auf leeres Array
const allIngredients = computed<Ingredient[]>(() => props.ingredients ?? []);

// Ergebnisliste – initial alle Zutaten aus der DB (oder [] wenn noch nichts kommt)
const ingredientCards = ref<Ingredient[]>([...allIngredients.value]);

// Wird von IngredientSearchArea aufgerufen, wenn gefilterte Ergebnisse
// (z. B. nach Suchtext / Tag) übergeben werden sollen
const handleResultsUpdate = (results: Ingredient[]) => {
    ingredientCards.value = results;
};
</script>

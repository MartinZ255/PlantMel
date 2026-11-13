<template>
    <AppLayout page-title="Meine Zutaten">
        <!-- Such- / Filterbereich nur, wenn es gespeicherte Zutaten gibt -->
        <IngredientSearchArea
            v-if="allIngredients.length"
            :ingredients="allIngredients"
            @update:results="handleResultsUpdate"
        />

        <section class="content-section">
            <div class="section-header">
                <div>
                    <h1 class="section-header__title">Meine Zutaten</h1>
                    <div class="section-header__subtitle">
                        Hier speicherst du die Zutaten, die du zu Hause hast.
                    </div>
                </div>

                <div v-if="allIngredients.length" class="section-header__meta">
                    {{ ingredientCards.length }} Zutaten gefunden
                </div>
            </div>

            <!-- Ehrlicher Leerzustand: Speichern ist noch nicht gebaut (F-03, Entscheidung E3) -->
            <p
                v-if="!allIngredients.length"
                class="search-meta my-ingredients__empty"
            >
                Du hast noch keine Zutaten gespeichert. Diese Funktion wird
                gerade ausgebaut.
            </p>

            <div v-else class="recipes-grid">
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

interface Ingredient {
    id: number;
    name: string;
    category?: string[] | null;
}

// Die Route liefert `ingredients` (derzeit leer); fehlt die Prop, gilt ebenfalls [].
const props = defineProps<{
    ingredients?: Ingredient[];
}>();

const allIngredients = computed<Ingredient[]>(() => props.ingredients ?? []);

const ingredientCards = ref<Ingredient[]>([...allIngredients.value]);

const handleResultsUpdate = (results: Ingredient[]) => {
    ingredientCards.value = results;
};
</script>

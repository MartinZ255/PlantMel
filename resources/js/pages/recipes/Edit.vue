<template>
    <AppLayout page-title="Rezept bearbeiten">
        <section class="content-section">
            <div class="section-header">
                <div class="section-header__title">Rezept bearbeiten</div>
                <div class="section-header__meta">
                    Änderungen werden nach dem Speichern übernommen
                </div>
            </div>

            <RecipeForm
                mode="edit"
                :rating-dimensions="ratingDimensions"
                :categories="categories"
                :ingredients="ingredients"
                :recipe="recipe"
                :submit-route="route('recipes.update', recipe.id)"
                submit-method="post"
            />
        </section>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import RecipeForm from '@/components/recipes/RecipeForm.vue';
import { route } from 'ziggy-js';

interface RatingDimension {
    id: number;
    name: string;
    description?: string | null;
}

interface Category {
    id: number;
    name: string;
}

interface IngredientOption {
    id: number;
    name: string;
}

interface IngredientRow {
    amount: string;
    name: string;
    // F-13: vom Server gelieferte Alternativen der Zeile
    alternativeIngredientIds: number[];
}

// F-15: kein Feld „Quelle“ mehr; ADR-001: mehrzeilige Schritte als EIN String
interface RecipeFormInput {
    id: number;
    name: string;
    description: string | null;
    duration_minutes: number | null;
    servings: number | null;
    tags: string[];
    ingredients: IngredientRow[];
    steps: string[];
    notes: string | null;
    ratings: Record<number, number | null>;
}

defineProps<{
    ratingDimensions: RatingDimension[];
    categories: Category[];
    ingredients: IngredientOption[];
    recipe: RecipeFormInput;
    isHost: boolean;
}>();
</script>

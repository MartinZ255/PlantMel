<template>
    <AppLayout page-title="Rezept anlegen">
        <section class="content-section">
            <div class="section-header">
                <div class="section-header__title">Neues Rezept</div>
                <div class="section-header__meta">
                    Entwurf · noch nicht gespeichert
                </div>
            </div>

            <RecipeForm
                mode="create"
                :rating-dimensions="ratingDimensions"
                :categories="categories"
                :ingredients="ingredients"
                :recipe="null"
                :submit-route="route('recipes.store')"
                submit-method="post"
            />
        </section>
    </AppLayout>
</template>

<script setup lang="ts">
import { route } from 'ziggy-js';
import AppLayout from '@/components/layout/AppLayout.vue';
import RecipeForm from '@/components/recipes/RecipeForm.vue';

const props = defineProps<{
    ratingDimensions: {
        id: number;
        name: string;
        description?: string | null;
    }[];
    categories: {
        id: number;
        name: string;
    }[];
    ingredients: {
        id: number;
        name: string;
    }[];
}>();
</script>

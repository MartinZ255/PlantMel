<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import RecipeCollectionBoard from '@/components/recipes/RecipeCollectionBoard.vue';

const collections = [
    { id: 1, title: 'unter 30 Minuten', count: 5 },
    { id: 2, title: 'Pasta-Favoriten', count: 3 },
    { id: 3, title: 'One-Pot Gerichte', count: 2 },
    { id: 4, title: 'Meal Prep', count: 2 },
];

const totalRecipes = 12;
</script>

<template>
    <AppLayout page-title="Rezeptübersicht">
        <!-- Board oben -->
        <RecipeCollectionBoard
            :collections="collections"
            :total="totalRecipes"
        />
    </AppLayout>
</template>

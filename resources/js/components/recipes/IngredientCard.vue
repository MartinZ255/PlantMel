<template>
    <article class="ingredient-card">
        <header class="ingredient-card__header">
            <div class="ingredient-card__name">
                {{ ingredient.name }}
            </div>

            <!-- Badge: erste Kategorie oder Platzhalter -->
            <span class="ingredient-card__category">
                {{ ingredient.category[0] ?? 'ohne Kategorie' }}
            </span>
        </header>

        <!-- Tags: alle Kategorien als kleine Chips -->
        <div
            v-if="ingredient.category && ingredient.category.length"
            class="ingredient-card__tags"
        >
            <span
                v-for="tag in ingredient.category"
                :key="tag"
                class="ingredient-card__tag"
            >
                {{ tag }}
            </span>
        </div>
    </article>
</template>

<script setup lang="ts">
interface Ingredient {
    id: number;
    name: string;
    category: string[]; // mehrere Kategorienamen
}

defineProps<{
    ingredient: Ingredient;
}>();
</script>

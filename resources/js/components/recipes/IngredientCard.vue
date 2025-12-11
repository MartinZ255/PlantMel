<template>
    <article class="ingredient-card">
        <header class="ingredient-card__header">
            <div class="ingredient-card__name">
                {{ ingredient.name }}
            </div>
        </header>
    </article>
</template>

<script setup lang="ts">
interface Ingredient {
    id: number;
    name: string;
}

defineProps<{
    ingredient: Ingredient;
}>();
</script>

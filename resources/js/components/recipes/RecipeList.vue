<template>
    <div>
        <div class="section-header">
            <div class="section-header__title">Rezepte</div>
            <div class="section-header__meta">
                {{ recipes.length }} Ergebnisse
            </div>
        </div>

        <div class="recipe-list">
            <article
                v-for="recipe in recipes"
                :key="recipe.id"
                class="recipe-list-item"
                @click="$emit('select', recipe)"
            >
                <div class="recipe-list-item__dot"></div>
                <div>
                    <div class="recipe-list-item__name">{{ recipe.name }}</div>
                    <div class="recipe-list-item__meta">
                        {{ recipe.meta }}
                    </div>
                </div>
            </article>
        </div>
    </div>
</template>

<script setup lang="ts">
interface RecipeListItem {
    id: number | string;
    name: string;
    meta: string;
}

interface Props {
    recipes: RecipeListItem[];
}

defineProps<Props>();

defineEmits<{
    (e: 'select', recipe: RecipeListItem): void;
}>();
</script>

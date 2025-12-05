<template>
    <AppLayout page-title="Rezeptübersicht">
        <!-- Suche / Filter in der Mitte -->
        <RecipeSearchArea
            :tags="tags"
            :tag-stats="tagStats"
        />

        <a
            :href="route('recipes.create')"
            class="content-section"
        >
            Rezept erstellen (Testweise)
        </a>

        <!-- Grid aus Karten unten -->
        <section class="content-section">
            <div class="section-header">
                <div class="section-header__title">Rezepte</div>
                <div class="section-header__meta">
                    {{ recipeCards.length }} Ergebnisse
                </div>
            </div>

            <div class="recipes-grid">
                <RecipeDetailCard
                    v-for="card in recipeCards"
                    :key="card.id"
                    :recipe="card"
                />
            </div>
        </section>
    </AppLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { route } from 'ziggy-js';
import AppLayout from '@/components/layout/AppLayout.vue';
import RecipeSearchArea from '@/components/recipes/RecipeSearchArea.vue';
import RecipeDetailCard from '@/components/recipes/RecipeDetailCard.vue';

interface Recipes {
    id: number;
    name: string;
    rating: number;          // 0–5
    tags: string[];
    time: string;            // '20 Min'
    servings: string | null;
    summary: string | null;
    image: string | null;
}

interface TagStat {
    key: string;
    label: string;
    count: number;
}

const props = withDefaults(defineProps<{
    recipes?: Recipes[];
    tags?: string[];
    tagStats?: TagStat[];
}>(), {
    recipes: () => [],
    tags: () => [],
    tagStats: () => [],
});

const tags = computed(() => props.tags);
const tagStats = computed(() => props.tagStats);

const recipeCards = computed(() =>
    props.recipes.map((r) => ({
        id: r.id,
        name: r.name,
        tags: r.tags,
        time: r.time,
        rating: r.rating,
        image: r.image,
    })),
);
</script>

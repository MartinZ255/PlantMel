<template>
    <AppLayout page-title="Rezeptübersicht">
        <!-- Suche / Filter in der Mitte -->
        <RecipeSearchArea
            :tags="tags"
            :tag-stats="tagStats"
            :initial-filters="props.filters"
            @update:filters="onUpdateFilters"
        />

        <a
            v-if="props.isHost"
            :href="route('recipes.create')"
            class="content-section"
        >
            Rezept erstellen
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
import { router } from '@inertiajs/vue3';
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

interface Filters {
    search?: string | null;
    includeIngredients?: string[];
    excludeIngredients?: string[];
    tags?: string[];
}

const props = withDefaults(defineProps<{
    recipes?: Recipes[];
    tags?: string[];
    tagStats?: TagStat[];
    filters?: Filters;
    isHost: boolean;
}>(), {
    recipes: () => [],
    tags: () => [],
    tagStats: () => [],
    filters: () => ({
        search: '',
        includeIngredients: [],
        excludeIngredients: [],
        tags: [],
    }),
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

type FilterPayload = {
    search: string;
    includeIngredients: string[];
    excludeIngredients: string[];
    tags: string[];
};

const onUpdateFilters = (payload: FilterPayload) => {
    router.get(
        route('recipes.index'),
        {
            search: payload.search || undefined,
            includeIngredients: payload.includeIngredients,
            excludeIngredients: payload.excludeIngredients,
            tags: payload.tags,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};
</script>

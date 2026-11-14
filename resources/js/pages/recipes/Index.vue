<template>
    <AppLayout page-title="Rezepte">
        <!-- Suche / Filter in der Mitte -->
        <RecipeSearchArea
            ref="searchArea"
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
                <h1 class="section-header__title">Rezepte</h1>
                <!-- F-44: Trefferzahl als Live-Region -->
                <div class="section-header__meta" role="status">
                    {{
                        recipeCards.length === 1
                            ? '1 Ergebnis'
                            : `${recipeCards.length} Ergebnisse`
                    }}
                </div>
            </div>

            <!-- F-44: Leerzustand -->
            <div v-if="recipeCards.length === 0" class="recipes-empty">
                <p class="recipes-empty__text">
                    {{
                        hasActiveFilters
                            ? 'Keine Rezepte passen zu deiner Suche.'
                            : 'Es gibt noch keine Rezepte.'
                    }}
                </p>
                <button
                    v-if="hasActiveFilters"
                    type="button"
                    class="secondary-pill-button"
                    @click="resetFilters"
                >
                    Filter zurücksetzen
                </button>
            </div>

            <div v-else class="recipes-grid">
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
import AppLayout from '@/components/layout/AppLayout.vue';
import RecipeDetailCard from '@/components/recipes/RecipeDetailCard.vue';
import RecipeSearchArea from '@/components/recipes/RecipeSearchArea.vue';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

interface Recipes {
    id: number;
    name: string;
    rating: number; // 0–5
    tags: string[];
    time: string; // '20 Min'
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

const props = withDefaults(
    defineProps<{
        recipes?: Recipes[];
        tags?: string[];
        tagStats?: TagStat[];
        filters?: Filters;
        isHost: boolean;
    }>(),
    {
        recipes: () => [],
        tags: () => [],
        tagStats: () => [],
        filters: () => ({
            search: '',
            includeIngredients: [],
            excludeIngredients: [],
            tags: [],
        }),
    },
);

const tags = computed(() => props.tags);

const searchArea = ref<{ reset: () => void } | null>(null);

const hasActiveFilters = computed(() => {
    const f = props.filters ?? {};
    return (
        !!(f.search && f.search.trim()) ||
        (f.includeIngredients?.length ?? 0) > 0 ||
        (f.excludeIngredients?.length ?? 0) > 0 ||
        (f.tags?.length ?? 0) > 0
    );
});

const resetFilters = () => {
    searchArea.value?.reset();
};
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
    const params: Record<string, any> = {};

    if (payload.search && payload.search.trim()) {
        params.search = payload.search.trim();
    }

    if (payload.includeIngredients && payload.includeIngredients.length > 0) {
        params.includeIngredients = payload.includeIngredients;
    }

    if (payload.excludeIngredients && payload.excludeIngredients.length > 0) {
        params.excludeIngredients = payload.excludeIngredients;
    }

    if (payload.tags && payload.tags.length > 0) {
        params.tags = payload.tags;
    }

    router.get(route('recipes.index'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};
</script>

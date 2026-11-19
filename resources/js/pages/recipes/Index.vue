<template>
    <AppLayout page-title="Rezepte" bleed>
        <!-- Kopf mit Suche -->
        <section class="border-b border-line bg-surface">
            <div
                class="mx-auto flex w-full max-w-(--content-max) flex-col gap-6 px-4 pt-8 pb-6 sm:px-6 lg:px-8 lg:pt-10"
            >
                <PageHeader
                    title="Rezepte"
                    description="Finde pflanzenbetonte Gerichte nach Name, Kategorie oder Zutaten."
                >
                    <template v-if="props.isHost" #actions>
                        <Button :href="route('recipes.create')">
                            <Plus class="size-5" aria-hidden="true" />
                            Rezept erstellen
                        </Button>
                    </template>
                </PageHeader>

                <RecipeSearchArea
                    ref="searchArea"
                    :tags="tags"
                    :tag-stats="tagStats"
                    :initial-filters="props.filters"
                    @update:filters="onUpdateFilters"
                />
            </div>
        </section>

        <!-- Ergebnisse -->
        <section
            aria-labelledby="recipe-results-title"
            class="mx-auto w-full max-w-(--content-max) px-4 py-8 sm:px-6 lg:px-8"
        >
            <div
                class="mb-5 flex flex-wrap items-baseline justify-between gap-2"
            >
                <h2
                    id="recipe-results-title"
                    class="text-xl font-semibold text-ink"
                >
                    {{ hasActiveFilters ? 'Suchergebnisse' : 'Alle Rezepte' }}
                </h2>
                <!-- F-44: Trefferzahl als Live-Region -->
                <p class="text-sm font-medium text-ink-muted" role="status">
                    {{
                        recipeCards.length === 1
                            ? '1 Ergebnis'
                            : `${recipeCards.length} Ergebnisse`
                    }}
                </p>
            </div>

            <!-- F-44: Leerzustand -->
            <EmptyState
                v-if="recipeCards.length === 0"
                :icon="SearchX"
                :title="
                    hasActiveFilters
                        ? 'Keine Rezepte gefunden'
                        : 'Noch keine Rezepte'
                "
                :description="
                    hasActiveFilters
                        ? 'Keine Rezepte passen zu deiner Suche. Ändere die Filter oder setze sie zurück.'
                        : 'Es gibt noch keine Rezepte.'
                "
                :heading-level="3"
            >
                <template v-if="hasActiveFilters" #actions>
                    <Button variant="secondary" @click="resetFilters">
                        Filter zurücksetzen
                    </Button>
                </template>
            </EmptyState>

            <ul
                v-else
                class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                aria-labelledby="recipe-results-title"
            >
                <li v-for="card in recipeCards" :key="card.id" class="min-w-0">
                    <RecipeDetailCard :recipe="card" />
                </li>
            </ul>
        </section>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import RecipeDetailCard from '@/components/recipes/RecipeDetailCard.vue';
import RecipeSearchArea from '@/components/recipes/RecipeSearchArea.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { router } from '@inertiajs/vue3';
import { Plus, SearchX } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

interface Recipes {
    id: number;
    name: string;
    rating: number; // 0–5, ganzzahlig (0 = keine Bewertung)
    tags: string[];
    time: string; // '20 Min' oder '–'
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
const tagStats = computed(() => props.tagStats);

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
    const params: Record<string, string | string[]> = {};

    if (payload.search && payload.search.trim()) {
        params.search = payload.search.trim();
    }
    if (payload.includeIngredients.length > 0) {
        params.includeIngredients = payload.includeIngredients;
    }
    if (payload.excludeIngredients.length > 0) {
        params.excludeIngredients = payload.excludeIngredients;
    }
    if (payload.tags.length > 0) {
        params.tags = payload.tags;
    }

    router.get(route('recipes.index'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};
</script>

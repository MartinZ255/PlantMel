<template>
    <AppLayout page-title="Rezepte" bleed>
        <!-- Kopf: organischer Hintergrund, große Fraunces-Headline, Suche als schwebende Kapsel -->
        <section class="relative overflow-hidden">
            <BlobBackdrop tone="light" />
            <LeafOrnament
                variant="eucalyptus"
                class="pointer-events-none absolute top-4 right-[4%] hidden h-44 animate-sway text-tint-strong [--float-rotate:-16deg] md:block lg:h-56"
            />
            <LeafOrnament
                variant="leaf"
                class="pointer-events-none absolute top-24 right-[22%] hidden h-16 animate-float-slow text-moss/60 [--float-rotate:38deg] lg:block"
            />
            <div
                class="relative mx-auto flex w-full max-w-(--content-max) flex-col gap-8 px-4 pt-8 pb-10 sm:px-6 lg:px-8 lg:pt-12 lg:pb-14"
            >
                <PageHeader
                    title="Rezepte"
                    eyebrow="Aus dem Rezeptgarten"
                    description="Finde pflanzenbetonte Gerichte nach Name, Kategorie oder Zutaten."
                    size="xl"
                >
                    <template v-if="props.isHost" #actions>
                        <Button :href="route('recipes.create')">
                            <Plus class="size-5" aria-hidden="true" />
                            Rezept erstellen
                        </Button>
                    </template>
                </PageHeader>

                <div
                    class="rounded-[2.25rem_1.75rem_2.5rem_2rem] bg-surface/85 p-3 shadow-float ring-1 ring-line/60 backdrop-blur-md sm:p-4"
                >
                    <RecipeSearchArea
                        ref="searchArea"
                        :tags="tags"
                        :tag-stats="tagStats"
                        :initial-filters="props.filters"
                        @update:filters="onUpdateFilters"
                    />
                </div>
            </div>
        </section>

        <!-- Ergebnisse -->
        <section
            aria-labelledby="recipe-results-title"
            class="mx-auto w-full max-w-(--content-max) px-4 pt-4 pb-16 sm:px-6 lg:px-8"
        >
            <div class="mb-8 flex flex-wrap items-center justify-between gap-3">
                <h2
                    id="recipe-results-title"
                    class="font-display-wonk text-3xl font-semibold text-ink sm:text-4xl"
                >
                    {{ hasActiveFilters ? 'Suchergebnisse' : 'Alle Rezepte' }}
                </h2>
                <!-- F-44: Trefferzahl als Live-Region -->
                <p
                    class="rounded-seed bg-tint px-3.5 py-1 text-sm font-semibold text-primary"
                    role="status"
                >
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

            <!-- leicht gestaffeltes Raster: Reihenfolge im DOM = Lesereihenfolge -->
            <ul
                v-else
                class="grid grid-cols-1 gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3 lg:gap-x-10"
                aria-labelledby="recipe-results-title"
            >
                <Reveal
                    v-for="(card, i) in recipeCards"
                    :key="card.id"
                    as="li"
                    :delay="(i % 3) * 90"
                    class="min-w-0 sm:max-lg:even:mt-12 lg:[&:nth-child(3n+2)]:mt-16"
                >
                    <RecipeDetailCard :recipe="card" :shape="(i % 6) + 1" />
                </Reveal>
            </ul>
        </section>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import RecipeDetailCard from '@/components/recipes/RecipeDetailCard.vue';
import RecipeSearchArea from '@/components/recipes/RecipeSearchArea.vue';
import BlobBackdrop from '@/components/ui/BlobBackdrop.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Reveal from '@/components/ui/Reveal.vue';
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

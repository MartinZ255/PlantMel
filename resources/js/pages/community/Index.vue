<template>
    <AppLayout page-title="Community-Rezepte" bleed>
        <!-- Kopf: Waldgrün mit Körnung, Headline, Einladung zum Mitmachen -->
        <header
            class="relative overflow-hidden bg-forest grain-light on-forest"
        >
            <BlobBackdrop tone="forest" />
            <LeafOrnament
                variant="sprig"
                class="pointer-events-none absolute -top-6 right-[8%] hidden h-44 animate-sway text-forest-soft [--float-rotate:18deg] md:block"
            />
            <LeafOrnament
                variant="leaf"
                class="pointer-events-none absolute -bottom-6 -left-8 h-36 rotate-[40deg] text-forest-soft"
            />
            <div
                class="relative mx-auto grid w-full max-w-(--content-max) items-end gap-8 px-4 pt-8 pb-8 sm:px-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:px-8 lg:pt-12 lg:pb-10"
            >
                <div class="min-w-0">
                    <p
                        class="mb-3 inline-flex items-center gap-1.5 text-sm font-semibold tracking-[0.14em] text-matcha uppercase"
                    >
                        <LeafOrnament variant="sprout" class="size-4" />
                        Aus vielen Küchen
                    </p>
                    <h1
                        class="font-display-wonk text-[clamp(2.4rem,9vw,5rem)] leading-[1.02] font-semibold text-balance text-on-forest"
                    >
                        Rezepte der
                        <span class="accent-word text-matcha">Community</span>
                    </h1>
                    <p
                        class="mt-4 max-w-2xl text-lg text-on-forest-muted sm:text-xl"
                    >
                        Von Nutzerinnen und Nutzern eingereicht, vom
                        PlantMel-Team geprüft. Das Rezeptbuch des Hosts findest
                        du unter „Rezepte“.
                    </p>
                </div>
                <div
                    class="relative flex min-w-0 flex-col gap-3 rounded-[2rem_1.6rem_2.25rem_1.75rem] bg-forest-deep/55 p-5 ring-1 ring-forest-soft backdrop-blur-sm sm:max-w-sm"
                >
                    <p
                        class="font-display text-xl font-semibold text-on-forest"
                    >
                        Dein Lieblingsrezept fehlt?
                    </p>
                    <p class="text-sm text-on-forest-muted">
                        Teile es mit allen – nach einer kurzen Prüfung ist es
                        öffentlich.
                    </p>
                    <Button
                        v-if="user"
                        variant="lime"
                        :href="route('community.create')"
                        data-submit-recipe
                    >
                        <Plus class="size-5" aria-hidden="true" />
                        Rezept einreichen
                    </Button>
                    <Button
                        v-else
                        variant="lime"
                        :href="route('login')"
                        data-submit-recipe
                    >
                        Anmelden und Rezept teilen
                    </Button>
                </div>
            </div>
            <WaveDivider :variant="2" class="text-canvas" />
        </header>

        <section class="relative">
            <div
                class="relative mx-auto w-full max-w-(--content-max) px-4 pt-2 pb-6 sm:px-6 lg:px-8"
            >
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

        <section
            aria-labelledby="community-results-title"
            class="mx-auto w-full max-w-(--content-max) px-4 pt-4 pb-16 sm:px-6 lg:px-8"
        >
            <div class="mb-8 flex flex-wrap items-center justify-between gap-3">
                <h2
                    id="community-results-title"
                    ref="resultsHeading"
                    tabindex="-1"
                    class="font-display-wonk text-3xl font-semibold text-ink focus:outline-none sm:text-4xl"
                >
                    {{
                        hasActiveFilters
                            ? 'Suchergebnisse'
                            : 'Alle Community-Rezepte'
                    }}
                </h2>
                <p
                    class="rounded-seed bg-tint px-3.5 py-1 text-sm font-semibold text-primary"
                    role="status"
                    data-result-count
                >
                    {{ resultText }}
                </p>
            </div>

            <EmptyState
                v-if="props.recipes.length === 0"
                :icon="Sprout"
                :title="
                    hasActiveFilters
                        ? 'Keine Rezepte gefunden'
                        : 'Noch keine Community-Rezepte'
                "
                :description="
                    hasActiveFilters
                        ? 'Keine Community-Rezepte passen zu deiner Suche. Ändere die Filter oder setze sie zurück.'
                        : 'Hier erscheinen Rezepte, sobald sie eingereicht und freigegeben sind. Mach den Anfang!'
                "
                :heading-level="3"
            >
                <template #actions>
                    <Button
                        v-if="hasActiveFilters"
                        variant="secondary"
                        @click="resetFilters"
                    >
                        Filter zurücksetzen
                    </Button>
                    <Button v-else-if="user" :href="route('community.create')">
                        Rezept einreichen
                    </Button>
                </template>
            </EmptyState>

            <ul
                v-else
                class="grid grid-cols-1 gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3 lg:gap-x-10"
                aria-labelledby="community-results-title"
                data-community-list
            >
                <Reveal
                    v-for="(card, i) in props.recipes"
                    :key="card.id"
                    as="li"
                    :delay="(i % 3) * 90"
                    class="min-w-0 sm:max-lg:even:mt-12 lg:[&:nth-child(3n+2)]:mt-16"
                >
                    <RecipeDetailCard
                        :recipe="card"
                        :shape="((i + 2) % 6) + 1"
                        :href="route('community.show', card.id)"
                        :byline="card.author ? `von ${card.author}` : null"
                    />
                </Reveal>
            </ul>

            <Pagination
                v-if="props.recipes.length > 0"
                class="mt-16"
                :pagination="props.pagination"
                :only="LIST_PROPS"
                @navigate="focusResults"
            />
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
import Pagination, {
    type PaginationData,
} from '@/components/ui/Pagination.vue';
import Reveal from '@/components/ui/Reveal.vue';
import WaveDivider from '@/components/ui/WaveDivider.vue';
import type { AppPageProps } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { Plus, Sprout } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';
import { route } from 'ziggy-js';

/**
 * E18: öffentliche Liste der freigegebenen Community-Rezepte. Suche,
 * Filter und Pagination wie `/rezepte` (24 je Seite, Partial Reloads).
 */
interface CommunityCard {
    id: number;
    name: string;
    rating: number;
    tags: string[];
    time: string;
    image: string | null;
    author: string | null;
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
        recipes?: CommunityCard[];
        tags?: string[];
        tagStats?: TagStat[];
        filters?: Filters;
        pagination?: PaginationData;
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
        pagination: () => ({
            currentPage: 1,
            lastPage: 1,
            perPage: 24,
            total: 0,
            from: null,
            to: null,
            prevUrl: null,
            nextUrl: null,
            pages: [],
        }),
    },
);

const page = usePage<AppPageProps>();
const user = computed(() => page.props.auth?.user ?? null);

const tags = computed(() => props.tags);
const tagStats = computed(() => props.tagStats);
const searchArea = ref<{ reset: () => void } | null>(null);
const resultsHeading = ref<HTMLElement | null>(null);

const LIST_PROPS = ['recipes', 'pagination', 'filters'];

const resultText = computed(() => {
    const { total, currentPage, lastPage } = props.pagination;
    const count = total === 1 ? '1 Rezept' : `${total} Rezepte`;
    return lastPage > 1
        ? `${count} · Seite ${currentPage} von ${lastPage}`
        : count;
});

const hasActiveFilters = computed(() => {
    const f = props.filters ?? {};
    return (
        !!(f.search && f.search.trim()) ||
        (f.includeIngredients?.length ?? 0) > 0 ||
        (f.excludeIngredients?.length ?? 0) > 0 ||
        (f.tags?.length ?? 0) > 0
    );
});

const resetFilters = () => searchArea.value?.reset();

const focusResults = async () => {
    await nextTick();
    const heading = resultsHeading.value;
    if (!heading) return;
    heading.focus({ preventScroll: true });
    const reduce = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;
    heading.scrollIntoView({
        block: 'start',
        behavior: reduce ? 'auto' : 'smooth',
    });
};

type FilterPayload = {
    search: string;
    includeIngredients: string[];
    excludeIngredients: string[];
    tags: string[];
};

const onUpdateFilters = (payload: FilterPayload) => {
    const params: Record<string, string | string[]> = {};
    if (payload.search && payload.search.trim())
        params.search = payload.search.trim();
    if (payload.includeIngredients.length)
        params.includeIngredients = payload.includeIngredients;
    if (payload.excludeIngredients.length)
        params.excludeIngredients = payload.excludeIngredients;
    if (payload.tags.length) params.tags = payload.tags;

    router.get(route('community.index'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: LIST_PROPS,
    });
};
</script>

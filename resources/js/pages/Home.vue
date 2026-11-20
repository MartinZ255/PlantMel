<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import RecipeCollectionBoard from '@/components/recipes/RecipeCollectionBoard.vue';
import Badge from '@/components/ui/Badge.vue';
import BlobBackdrop from '@/components/ui/BlobBackdrop.vue';
import BlobImage from '@/components/ui/BlobImage.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import Reveal from '@/components/ui/Reveal.vue';
import WaveDivider from '@/components/ui/WaveDivider.vue';
import type { AppPageProps } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, CookingPot, Sprout } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';

/**
 * Gate C2: persönlicher Einstieg nach der Anmeldung.
 * Zeigt nur vorhandene Daten: Begrüßung mit dem Namen aus `auth.user`,
 * Zähler aus `stats`, die eigenen Sammlungen aus `auth.collections` und die
 * vier zuletzt hinzugefügten Rezepte (`latestRecipes`, routes/web.php).
 */
interface HomeStats {
    totalRecipes: number;
    totalCollections: number;
}

interface LatestRecipe {
    id: number | string;
    name: string;
    categories: string[];
    image: string | null;
}

const page = usePage<
    AppPageProps<{
        stats?: HomeStats;
        latestRecipes?: LatestRecipe[];
    }>
>();

const stats = computed<HomeStats>(
    () =>
        page.props.stats ?? {
            totalRecipes: 0,
            totalCollections: 0,
        },
);

const latestRecipes = computed<LatestRecipe[]>(
    () => page.props.latestRecipes ?? [],
);

const userCollections = computed(() => page.props.auth?.collections ?? []);

// Vorname = erstes Wort des Namens; ohne Namen eine neutrale Begrüßung
const firstName = computed(() => {
    const name = page.props.auth?.user?.name?.trim() ?? '';
    return name.split(/\s+/)[0] ?? '';
});

const recipeCountText = (n: number) => (n === 1 ? 'Rezept' : 'Rezepte');
const collectionCountText = (n: number) =>
    n === 1 ? 'Sammlung' : 'Sammlungen';

const MAX_TAGS = 2;
</script>

<template>
    <AppLayout page-title="Willkommen bei PlantMel" bleed>
        <!-- ============================================================
             Begrüßung: Waldgrün mit Körnung, Fraunces-Headline, Zähler
             ============================================================ -->
        <section
            aria-labelledby="home-title"
            class="relative overflow-hidden bg-forest grain-light on-forest"
        >
            <BlobBackdrop tone="forest" />
            <LeafOrnament
                variant="sprig"
                class="pointer-events-none absolute -top-6 right-[38%] hidden h-40 animate-sway text-forest-soft [--float-rotate:22deg] lg:block"
            />
            <LeafOrnament
                variant="leaf"
                class="pointer-events-none absolute -bottom-6 -left-8 h-32 rotate-[42deg] text-forest-soft sm:h-40"
            />

            <div
                class="relative mx-auto grid w-full max-w-(--content-max) items-center gap-10 px-4 pt-8 pb-6 sm:px-6 lg:grid-cols-[1.25fr_1fr] lg:gap-14 lg:px-8 lg:pt-14 lg:pb-8"
            >
                <div class="min-w-0">
                    <p
                        class="mb-4 inline-flex items-center gap-2 text-sm font-semibold tracking-[0.14em] text-matcha uppercase"
                    >
                        <LeafOrnament variant="sprout" class="size-4" />
                        Deine Rezeptwelt
                    </p>
                    <h1
                        id="home-title"
                        class="font-display-wonk text-[clamp(2.4rem,9vw,5rem)] leading-[1.02] font-semibold text-balance text-wrap-anywhere text-on-forest"
                    >
                        <template v-if="firstName">
                            Hallo,
                            <span class="relative inline-block max-w-full">
                                <span class="accent-word text-matcha">{{
                                    firstName
                                }}</span>
                                <svg
                                    viewBox="0 0 220 24"
                                    preserveAspectRatio="none"
                                    class="absolute -bottom-1 left-0 h-[0.2em] w-full text-berry-surface"
                                    aria-hidden="true"
                                    focusable="false"
                                >
                                    <path
                                        d="M3 17 C 40 6 92 4 140 10 C 168 13 196 9 217 5"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="5"
                                        stroke-linecap="round"
                                    />
                                </svg>
                            </span>
                            – schön, dass du da bist.
                        </template>
                        <template v-else>
                            Schön, dass du
                            <span class="accent-word text-matcha">da</span>
                            bist.
                        </template>
                    </h1>
                    <p
                        class="mt-5 max-w-xl text-lg leading-relaxed text-on-forest-muted sm:text-xl"
                    >
                        Speichere Rezepte, filtere nach Zutaten und lege
                        Sammlungen für verschiedene Anlässe an – hier geht es
                        direkt los.
                    </p>
                    <div
                        class="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap"
                    >
                        <Button
                            :href="route('recipes.index')"
                            variant="lime"
                            size="lg"
                        >
                            Rezepte entdecken
                            <ArrowRight class="size-5" aria-hidden="true" />
                        </Button>
                        <Button
                            :href="route('collections.index')"
                            variant="outline-light"
                            size="lg"
                        >
                            Zu meinen Sammlungen
                        </Button>
                    </div>
                </div>

                <!-- Zähler als organische Kiesel auf einem Matcha-Blob -->
                <div
                    class="relative mx-auto w-full max-w-sm sm:max-w-md lg:max-w-none"
                >
                    <span
                        class="pointer-events-none absolute inset-[4%_2%_0_10%] animate-morph bg-forest-soft/70"
                        aria-hidden="true"
                    ></span>
                    <dl
                        class="relative grid grid-cols-2 gap-4 py-6 sm:gap-6 sm:px-4"
                        aria-label="Überblick"
                    >
                        <div
                            class="flex min-w-0 flex-col items-center justify-center rounded-[58%_42%_52%_48%/46%_54%_46%_54%] bg-matcha px-3 py-8 text-center text-forest shadow-float sm:py-10"
                        >
                            <dt class="order-2 text-sm font-semibold">
                                {{ recipeCountText(stats.totalRecipes) }}
                            </dt>
                            <dd
                                class="order-1 font-display-wonk text-5xl font-semibold sm:text-6xl"
                            >
                                {{ stats.totalRecipes }}
                            </dd>
                        </div>
                        <div
                            class="mt-6 flex min-w-0 flex-col items-center justify-center rounded-[42%_58%_61%_39%/52%_38%_62%_48%] bg-surface px-3 py-8 text-center text-ink shadow-float sm:mt-10 sm:py-10"
                        >
                            <dt class="order-2 text-sm font-semibold">
                                {{
                                    collectionCountText(stats.totalCollections)
                                }}
                            </dt>
                            <dd
                                class="order-1 font-display-wonk text-5xl font-semibold text-berry sm:text-6xl"
                            >
                                {{ stats.totalCollections }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
            <WaveDivider :variant="3" class="text-canvas" />
        </section>

        <!-- ============================================================
             Schnelle Wege: Rezepte, Sammlungen, Zutaten
             ============================================================ -->
        <RecipeCollectionBoard
            :recipe-count="stats.totalRecipes"
            :collection-count="stats.totalCollections"
            :collections="userCollections"
        />

        <!-- ============================================================
             Zuletzt hinzugefügt
             ============================================================ -->
        <section
            aria-labelledby="home-latest-title"
            class="relative overflow-hidden"
        >
            <LeafOrnament
                variant="eucalyptus"
                class="pointer-events-none absolute top-4 -right-6 hidden h-44 rotate-[-18deg] text-tint-strong md:block"
            />
            <div
                class="relative mx-auto w-full max-w-(--content-max) px-4 pt-6 pb-16 sm:px-6 lg:px-8"
            >
                <div
                    class="mb-8 flex flex-wrap items-end justify-between gap-3"
                >
                    <h2
                        id="home-latest-title"
                        class="font-display-wonk text-3xl font-semibold text-ink sm:text-4xl"
                    >
                        Frisch aus dem
                        <span class="accent-word text-primary">Garten</span>
                    </h2>
                    <Link
                        v-if="latestRecipes.length"
                        :href="route('recipes.index')"
                        class="inline-flex min-h-11 items-center gap-1.5 rounded-pebble px-2 font-semibold text-primary underline decoration-berry decoration-2 underline-offset-4 hover:text-primary-hover"
                    >
                        Alle Rezepte ansehen
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </div>

                <EmptyState
                    v-if="!latestRecipes.length"
                    :icon="Sprout"
                    title="Hier wächst bald etwas"
                    description="Es gibt noch keine Rezepte. Sobald neue Rezepte hinzukommen, erscheinen sie hier."
                    :heading-level="3"
                />

                <ul
                    v-else
                    class="grid grid-cols-2 gap-x-4 gap-y-8 sm:gap-x-8 sm:gap-y-10 lg:grid-cols-4"
                    aria-labelledby="home-latest-title"
                >
                    <Reveal
                        v-for="(recipe, i) in latestRecipes"
                        :key="recipe.id"
                        as="li"
                        :delay="i * 90"
                        class="min-w-0 even:mt-8 lg:even:mt-10"
                    >
                        <article
                            class="group relative isolate flex h-full min-w-0 flex-col gap-3"
                        >
                            <BlobImage
                                :src="recipe.image"
                                :shape="((i * 2) % 6) + 1"
                                ratio="1/1"
                                sizes="(min-width: 1024px) 22vw, 45vw"
                                class="shadow-float"
                            >
                                <template #fallback>
                                    <div
                                        class="flex size-full flex-col items-center justify-center gap-2 bg-gradient-to-br from-tint via-tint-strong to-matcha text-primary"
                                    >
                                        <span
                                            class="blob-mask inline-flex size-16 items-center justify-center bg-surface/80 blob-3"
                                            aria-hidden="true"
                                        >
                                            <CookingPot class="size-8" />
                                        </span>
                                        <span
                                            class="text-sm font-semibold text-ink"
                                        >
                                            Noch kein Foto
                                        </span>
                                    </div>
                                </template>
                            </BlobImage>
                            <ul
                                v-if="recipe.categories.length"
                                class="flex flex-wrap gap-1.5 px-1"
                                aria-label="Kategorien"
                            >
                                <li
                                    v-for="cat in recipe.categories.slice(
                                        0,
                                        MAX_TAGS,
                                    )"
                                    :key="cat"
                                    class="max-w-full"
                                >
                                    <Badge tone="primary">{{ cat }}</Badge>
                                </li>
                                <li v-if="recipe.categories.length > MAX_TAGS">
                                    <Badge>
                                        +{{
                                            recipe.categories.length - MAX_TAGS
                                        }}
                                        <span class="sr-only"
                                            >weitere Kategorien</span
                                        >
                                    </Badge>
                                </li>
                            </ul>
                            <h3
                                class="px-1 font-display text-lg leading-snug font-semibold text-wrap-anywhere text-ink sm:text-xl"
                            >
                                <Link
                                    :href="route('recipes.show', recipe.id)"
                                    class="rounded-sm decoration-berry decoration-2 underline-offset-4 group-hover:underline after:absolute after:inset-0 after:rounded-[2rem] after:content-[''] focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-offset-4 focus-visible:after:outline-focus"
                                >
                                    {{ recipe.name }}
                                </Link>
                            </h3>
                        </article>
                    </Reveal>
                </ul>
            </div>
        </section>
    </AppLayout>
</template>

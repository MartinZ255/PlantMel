<template>
    <!--
        Gate C2: „Schnelle Wege“ auf dem Dashboard. Drei organische Kacheln
        (Rezepte, Sammlungen, Zutaten) in wechselnden Tönen, leicht versetzt.
        Der Titel-Link jeder Kachel deckt die ganze Fläche ab; weitere Links in
        der Kachel (eigene Sammlungen, Meine Zutaten) liegen darüber (z-10).
    -->
    <section
        aria-labelledby="home-paths-title"
        class="relative mx-auto w-full max-w-(--content-max) px-4 pt-6 pb-10 sm:px-6 lg:px-8 lg:pt-4"
    >
        <h2
            id="home-paths-title"
            class="mb-8 font-display-wonk text-3xl font-semibold text-ink sm:text-4xl"
        >
            Wohin
            <span class="accent-word text-berry">heute</span>?
        </h2>

        <ul class="grid grid-cols-1 gap-6 md:grid-cols-3 lg:gap-8">
            <!-- Rezepte -->
            <Reveal
                as="li"
                class="group relative isolate flex min-w-0 flex-col overflow-hidden rounded-[2.5rem_1.75rem_2.75rem_2rem] bg-surface p-6 shadow-card transition-[box-shadow,translate] duration-300 focus-within:shadow-raised hover:-translate-y-1 hover:shadow-float sm:p-7"
            >
                <span
                    class="pointer-events-none absolute -top-10 -right-12 -z-10 size-40 animate-morph bg-tint"
                    aria-hidden="true"
                ></span>
                <span
                    class="relative blob-mask inline-flex size-16 items-center justify-center bg-primary text-on-primary shadow-card blob-1"
                    aria-hidden="true"
                >
                    <BookOpen class="size-7" />
                </span>
                <h3 class="mt-5 font-display text-2xl font-semibold text-ink">
                    <Link
                        :href="route('recipes.index')"
                        class="decoration-berry decoration-2 underline-offset-4 group-hover:underline after:absolute after:inset-0 after:rounded-[2.5rem_1.75rem_2.75rem_2rem] after:content-[''] focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:-outline-offset-4 focus-visible:after:outline-focus"
                    >
                        Rezepte
                    </Link>
                </h3>
                <p class="mt-1.5 text-ink-muted">
                    {{ recipeCountLabel }} – nach Name, Kategorie oder Zutaten
                    filtern.
                </p>
                <span
                    class="mt-auto inline-flex items-center gap-1.5 pt-5 text-sm font-semibold text-primary"
                    aria-hidden="true"
                >
                    Stöbern
                    <ArrowRight
                        class="size-4 transition-transform group-hover:translate-x-1"
                    />
                </span>
            </Reveal>

            <!-- Sammlungen -->
            <Reveal
                as="li"
                :delay="110"
                class="group relative isolate flex min-w-0 flex-col overflow-hidden rounded-[1.75rem_2.75rem_2rem_2.5rem] bg-matcha grain p-6 shadow-card transition-[box-shadow,translate] duration-300 focus-within:shadow-raised hover:-translate-y-1 hover:shadow-float sm:p-7 md:mt-8"
            >
                <LeafOrnament
                    variant="sprig"
                    class="pointer-events-none absolute -right-4 -bottom-6 -z-10 h-36 rotate-[-14deg] text-moss/40"
                />
                <span
                    class="relative blob-mask inline-flex size-16 items-center justify-center bg-forest text-matcha shadow-card blob-3"
                    aria-hidden="true"
                >
                    <FolderHeart class="size-7" />
                </span>
                <h3
                    class="mt-5 font-display text-2xl font-semibold text-forest"
                >
                    <Link
                        :href="route('collections.index')"
                        class="decoration-berry decoration-2 underline-offset-4 group-hover:underline after:absolute after:inset-0 after:rounded-[1.75rem_2.75rem_2rem_2.5rem] after:content-[''] focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:-outline-offset-4 focus-visible:after:outline-focus"
                    >
                        Sammlungen
                    </Link>
                </h3>
                <p class="mt-1.5 text-forest">
                    <template v-if="collectionCount > 0">
                        {{ collectionCountLabel }} – deine Rezepte nach Anlass
                        sortiert.
                    </template>
                    <template v-else>
                        Noch keine Sammlung – lege deine erste an, etwa für
                        „Unter der Woche“.
                    </template>
                </p>
                <ul
                    v-if="shownCollections.length"
                    class="relative z-10 mt-4 flex flex-wrap gap-2"
                    aria-label="Deine Sammlungen"
                >
                    <li
                        v-for="c in shownCollections"
                        :key="c.id"
                        class="max-w-full"
                    >
                        <Link
                            :href="route('collections.show', c.id)"
                            class="inline-flex min-h-9 max-w-full items-center rounded-seed bg-surface/85 px-3 py-1 text-sm font-semibold text-wrap-anywhere text-forest hover:bg-surface"
                        >
                            {{ c.name }}
                        </Link>
                    </li>
                    <li v-if="hiddenCollectionCount > 0" class="max-w-full">
                        <span
                            class="inline-flex min-h-9 items-center px-1 text-sm font-semibold text-forest"
                        >
                            +{{ hiddenCollectionCount }} weitere
                        </span>
                    </li>
                </ul>
                <Link
                    v-else
                    :href="route('collections.create')"
                    class="relative z-10 mt-4 inline-flex min-h-11 items-center gap-1.5 self-start rounded-pebble bg-forest px-4 text-sm font-semibold text-on-forest hover:bg-forest-soft"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Neue Sammlung anlegen
                </Link>
            </Reveal>

            <!-- Zutaten -->
            <Reveal
                as="li"
                :delay="220"
                class="group relative isolate flex min-w-0 flex-col overflow-hidden rounded-[2.25rem_2rem_1.75rem_2.75rem] bg-tint-strong p-6 shadow-card transition-[box-shadow,translate] duration-300 focus-within:shadow-raised hover:-translate-y-1 hover:shadow-float sm:p-7 md:mt-3"
            >
                <span
                    class="pointer-events-none absolute -bottom-12 -left-10 -z-10 size-36 rounded-[63%_37%_45%_55%/55%_61%_39%_45%] bg-berry-surface/70"
                    aria-hidden="true"
                ></span>
                <span
                    class="relative blob-mask inline-flex size-16 items-center justify-center bg-berry text-white shadow-berry blob-5"
                    aria-hidden="true"
                >
                    <Carrot class="size-7" />
                </span>
                <h3 class="mt-5 font-display text-2xl font-semibold text-ink">
                    <Link
                        :href="route('IngredientSearch')"
                        class="decoration-berry decoration-2 underline-offset-4 group-hover:underline after:absolute after:inset-0 after:rounded-[2.25rem_2rem_1.75rem_2.75rem] after:content-[''] focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:-outline-offset-4 focus-visible:after:outline-focus"
                    >
                        Zutatensuche
                    </Link>
                </h3>
                <p class="mt-1.5 text-ink-muted">
                    Alle Zutaten auf einen Blick finden.
                </p>
                <Link
                    :href="route('myIngredients')"
                    class="relative z-10 mt-auto inline-flex min-h-11 items-center gap-1.5 self-start pt-4 text-sm font-semibold text-primary underline decoration-berry decoration-2 underline-offset-4 hover:text-primary-hover"
                >
                    Meine Zutaten
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </Reveal>
        </ul>
    </section>
</template>

<script setup lang="ts">
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import Reveal from '@/components/ui/Reveal.vue';
import type { SharedCollection } from '@/types';
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpen,
    Carrot,
    FolderHeart,
    Plus,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';

interface Props {
    recipeCount: number;
    collectionCount: number;
    /** eigene Sammlungen (geteilte Prop `auth.collections`) */
    collections?: SharedCollection[];
}

const props = withDefaults(defineProps<Props>(), { collections: () => [] });

const MAX_COLLECTIONS = 4;
const shownCollections = computed(() =>
    props.collections.slice(0, MAX_COLLECTIONS),
);
const hiddenCollectionCount = computed(() =>
    Math.max(0, props.collections.length - MAX_COLLECTIONS),
);

const recipeCountLabel = computed(() =>
    props.recipeCount === 1
        ? '1 Rezept gesammelt'
        : `${props.recipeCount} Rezepte gesammelt`,
);
const collectionCountLabel = computed(() =>
    props.collectionCount === 1
        ? '1 Sammlung'
        : `${props.collectionCount} Sammlungen`,
);
</script>

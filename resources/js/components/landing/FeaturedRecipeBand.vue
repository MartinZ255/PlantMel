<script setup lang="ts">
import Badge from '@/components/ui/Badge.vue';
import BlobImage from '@/components/ui/BlobImage.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import { prefersReducedMotion } from '@/composables/useReveal';
import type { FeaturedRecipe } from '@/types';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Clock } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { route } from 'ziggy-js';

/**
 * Gate C1b: horizontales Band mit einer Auswahl von Rezepten.
 *
 * - mobil wischbar (Scroll-Snap), Tastatur: Tab durch die Rezept-Links
 *   (der Browser scrollt den fokussierten Link in den Sichtbereich)
 * - ab `md` zusätzlich „Zurück“/„Weiter“-Knöpfe; deaktiviert am Rand
 * - Bilder in Blob-Masken mit festem Seitenverhältnis, `loading="lazy"`
 * - versetzte Höhen (jede zweite Karte tiefer) für einen lebendigen Rhythmus
 */
const props = defineProps<{ recipes: FeaturedRecipe[] }>();

const scroller = ref<HTMLElement | null>(null);
const atStart = ref(true);
const atEnd = ref(false);

const update = () => {
    const el = scroller.value;
    if (!el) return;
    atStart.value = el.scrollLeft <= 4;
    atEnd.value = el.scrollLeft + el.clientWidth >= el.scrollWidth - 4;
};

const scrollByPage = (dir: 1 | -1) => {
    const el = scroller.value;
    if (!el) return;
    el.scrollBy({
        left: dir * Math.max(240, el.clientWidth * 0.8),
        behavior: prefersReducedMotion() ? 'auto' : 'smooth',
    });
};

let resizeObserver: ResizeObserver | null = null;
onMounted(() => {
    update();
    if ('ResizeObserver' in window && scroller.value) {
        resizeObserver = new ResizeObserver(update);
        resizeObserver.observe(scroller.value);
    }
});
onBeforeUnmount(() => resizeObserver?.disconnect());

const durationText = (m: number | null) =>
    m ? `${m} Min.` : 'Ohne Zeitangabe';
</script>

<template>
    <section
        aria-labelledby="landing-featured-title"
        class="relative overflow-hidden py-10 lg:py-14"
    >
        <div
            class="mx-auto flex w-full max-w-(--content-max) flex-wrap items-end justify-between gap-4 px-4 sm:px-6 lg:px-8"
        >
            <div class="min-w-0">
                <p
                    class="mb-2 inline-flex items-center gap-2 text-sm font-semibold tracking-[0.14em] text-primary uppercase"
                >
                    <LeafOrnament variant="sprout" class="size-4 text-moss" />
                    Frisch im Rezeptbuch
                </p>
                <h2
                    id="landing-featured-title"
                    class="font-display-wonk text-4xl font-semibold text-balance text-ink sm:text-5xl"
                >
                    Eine kleine
                    <span class="accent-word text-primary">Auswahl</span>
                </h2>
            </div>
            <div class="flex items-center gap-2">
                <Link
                    :href="route('recipes.index')"
                    class="inline-flex min-h-11 items-center gap-1.5 rounded-pebble px-3 font-semibold text-primary underline decoration-berry decoration-2 underline-offset-4 hover:text-primary-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                >
                    Alle Rezepte
                </Link>
                <button
                    type="button"
                    class="hidden size-11 items-center justify-center rounded-pebble border-[1.5px] border-primary text-primary transition-colors hover:bg-tint focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus disabled:cursor-not-allowed disabled:border-line-strong disabled:text-ink-muted disabled:hover:bg-transparent md:inline-flex"
                    aria-controls="landing-featured-list"
                    :disabled="atStart"
                    @click="scrollByPage(-1)"
                >
                    <ArrowLeft class="size-5" aria-hidden="true" />
                    <span class="sr-only">Vorherige Rezepte</span>
                </button>
                <button
                    type="button"
                    class="hidden size-11 items-center justify-center rounded-pebble bg-primary text-on-primary transition-colors hover:bg-primary-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus disabled:cursor-not-allowed disabled:bg-line-strong md:inline-flex"
                    aria-controls="landing-featured-list"
                    :disabled="atEnd"
                    @click="scrollByPage(1)"
                >
                    <ArrowRight class="size-5" aria-hidden="true" />
                    <span class="sr-only">Weitere Rezepte</span>
                </button>
            </div>
        </div>

        <ul
            id="landing-featured-list"
            ref="scroller"
            class="mt-8 flex snap-x snap-mandatory scroll-px-4 scrollbar-thin gap-5 overflow-x-auto overscroll-x-contain px-4 pt-2 pb-8 sm:scroll-px-6 sm:gap-7 sm:px-6 lg:scroll-px-[max(2rem,calc((100%_-_var(--content-max))/2_+_2rem))] lg:px-[max(2rem,calc((100%_-_var(--content-max))/2_+_2rem))]"
            aria-labelledby="landing-featured-title"
            @scroll.passive="update"
        >
            <li
                v-for="(r, i) in props.recipes"
                :key="r.id"
                class="w-[76%] max-w-[22rem] shrink-0 snap-start sm:w-[44%] lg:w-[21rem]"
                :class="i % 2 === 1 ? 'sm:mt-10' : ''"
            >
                <article class="group relative isolate flex flex-col gap-3">
                    <BlobImage
                        :src="r.image"
                        :shape="i + 2"
                        ratio="4/5"
                        class="shadow-float"
                    />
                    <span
                        class="absolute top-4 left-4 inline-flex items-center gap-1.5 rounded-seed bg-surface/90 px-3 py-1 text-sm font-semibold text-ink shadow-card backdrop-blur-sm"
                    >
                        <Clock class="size-4 text-primary" aria-hidden="true" />
                        <span class="sr-only">Dauer:</span>
                        {{ durationText(r.duration_minutes) }}
                    </span>
                    <div class="flex min-w-0 flex-col gap-2 px-1">
                        <h3
                            class="font-display text-2xl leading-tight font-semibold text-wrap-anywhere text-ink"
                        >
                            <Link
                                :href="route('recipes.show', r.id)"
                                class="rounded-sm decoration-berry decoration-2 underline-offset-4 group-hover:underline after:absolute after:inset-0 after:rounded-[2rem] after:content-[''] focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-offset-4 focus-visible:after:outline-focus"
                            >
                                {{ r.name }}
                            </Link>
                        </h3>
                        <ul
                            v-if="r.categories.length"
                            class="flex flex-wrap gap-1.5"
                            aria-label="Kategorien"
                        >
                            <li
                                v-for="c in r.categories.slice(0, 3)"
                                :key="c"
                                class="max-w-full"
                            >
                                <Badge tone="primary">{{ c }}</Badge>
                            </li>
                        </ul>
                    </div>
                </article>
            </li>
        </ul>
    </section>
</template>

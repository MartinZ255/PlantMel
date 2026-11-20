<template>
    <!--
        Gate C2: Sammlung als organische Kachel. Oben eine Komposition in der
        Farbe der Kachel (wechselnde Töne, Blob-Formen, großer Anfangsbuchstabe);
        liefert der Server Rezeptbilder (`images`, CollectionController@index),
        werden bis zu vier davon als Collage gezeigt (dekorativ, alt=""). Der Titel-Link deckt die
        ganze Kachel ab (Tastatur, Screenreader, Klick überall).
    -->
    <article class="group relative isolate flex h-full min-w-0 flex-col gap-4">
        <div class="relative" aria-hidden="true">
            <!-- versetzte zweite Form hinter der Kachel -->
            <span
                class="absolute inset-x-6 inset-y-2 blob-mask -z-10 translate-x-2 translate-y-3 rotate-[-3deg] transition-[translate,rotate,border-radius] duration-700 ease-organic group-hover:translate-x-3 group-hover:translate-y-4 group-hover:rotate-[-6deg]"
                :class="[shapeClass(shape + 3), tone.back]"
            ></span>

            <!-- Collage aus echten Rezeptbildern -->
            <div
                v-if="images.length"
                class="relative blob-mask aspect-[5/4] overflow-hidden"
                :class="[shapeClass(shape), tone.base]"
            >
                <div
                    data-collage
                    class="grid size-full gap-1"
                    :class="collageGridClass"
                >
                    <img
                        :src="images[0]"
                        alt=""
                        loading="lazy"
                        decoding="async"
                        class="size-full min-h-0 object-cover"
                        :class="images.length === 3 ? 'row-span-2' : ''"
                    />
                    <img
                        v-for="(src, i) in images.slice(1)"
                        :key="`${i}-${src}`"
                        :src="src"
                        alt=""
                        loading="lazy"
                        decoding="async"
                        class="size-full min-h-0 object-cover"
                    />
                </div>
            </div>

            <!-- ohne Bilder: grafische Komposition -->
            <div
                v-else
                class="relative blob-mask aspect-[5/4] overflow-hidden shadow-float"
                :class="[shapeClass(shape), tone.base]"
            >
                <span
                    class="absolute top-[12%] right-[10%] size-[38%] animate-morph"
                    :class="tone.blob"
                ></span>
                <span
                    class="absolute top-[14%] left-[14%] size-[10%] rounded-[58%_42%_52%_48%/46%_54%_46%_54%]"
                    :class="tone.dot"
                ></span>
                <LeafOrnament
                    :variant="leafVariant"
                    class="absolute right-[8%] -bottom-[6%] h-[62%] rotate-[-16deg] transition-transform duration-700 ease-organic group-hover:rotate-[-4deg]"
                    :class="tone.leaf"
                />
                <span
                    class="absolute top-1/2 left-[22%] -translate-y-1/2 font-display-wonk text-[clamp(4rem,14vw,7rem)] leading-none font-semibold"
                    :class="tone.letter"
                    >{{ initial }}</span
                >
            </div>

            <!-- Zähler als Sticker -->
            <span
                class="absolute bottom-3 left-4 inline-flex items-center gap-1.5 rounded-seed bg-surface/95 px-3 py-1 text-sm font-semibold text-ink shadow-card"
            >
                <BookOpen class="size-4 text-primary" />
                {{ countText }}
            </span>
        </div>

        <div class="flex min-w-0 flex-1 flex-col gap-1.5 px-1">
            <h2
                class="font-display text-2xl leading-snug font-semibold text-wrap-anywhere text-ink"
            >
                <Link
                    :href="route('collections.show', collection.id)"
                    class="rounded-sm decoration-berry decoration-2 underline-offset-4 group-hover:underline after:absolute after:inset-0 after:rounded-[2rem] after:content-[''] focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-offset-4 focus-visible:after:outline-focus"
                >
                    {{ collection.name }}
                </Link>
            </h2>
            <!-- Zähler für Screenreader beim Link-Kontext (Sticker ist aria-hidden) -->
            <p class="sr-only">{{ countText }}</p>
            <p
                v-if="collection.description"
                class="line-clamp-3 text-wrap-anywhere text-ink-muted"
            >
                {{ collection.description }}
            </p>
            <ul
                v-if="collection.tags?.length"
                class="mt-1 flex flex-wrap gap-1.5"
                aria-label="Kategorien"
            >
                <li
                    v-for="tag in collection.tags"
                    :key="tag"
                    class="max-w-full"
                >
                    <Badge tone="primary">{{ tag }}</Badge>
                </li>
            </ul>
        </div>
    </article>
</template>

<script setup lang="ts">
import Badge from '@/components/ui/Badge.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import { Link } from '@inertiajs/vue3';
import { BookOpen } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';

interface Collection {
    id: number | string;
    name: string;
    description?: string | null;
    recipeCount?: number;
    tags?: string[];
    /** optional: Bild-URLs von Rezepten der Sammlung (Collage) */
    images?: string[] | null;
}

const props = withDefaults(
    defineProps<{
        collection: Collection;
        /** Form und Farbton (1–6); in Listen je Kachel verschieden */
        shape?: number;
    }>(),
    { shape: 1 },
);

const shapeClass = (n: number) =>
    ['blob-1', 'blob-2', 'blob-3', 'blob-4', 'blob-5', 'blob-6'][
        (((n - 1) % 6) + 6) % 6
    ];

/*
 * Farbtöne der Kachel (alle Textpaare ≥ 4,5:1, siehe DESIGN-SYSTEM.md):
 * Buchstabe ist große Schrift und zusätzlich aria-hidden (dekorativ).
 */
const tones = [
    {
        base: 'bg-forest',
        back: 'bg-matcha',
        blob: 'bg-forest-soft',
        dot: 'bg-berry',
        leaf: 'text-forest-soft',
        letter: 'text-matcha',
    },
    {
        base: 'bg-matcha',
        back: 'bg-tint-strong',
        blob: 'bg-tint-strong',
        dot: 'bg-berry',
        leaf: 'text-moss/60',
        letter: 'text-forest',
    },
    {
        base: 'bg-berry-surface',
        back: 'bg-tint',
        blob: 'bg-surface/70',
        dot: 'bg-forest',
        leaf: 'text-berry/25',
        letter: 'text-berry',
    },
    {
        base: 'bg-tint-strong',
        back: 'bg-berry-surface',
        blob: 'bg-matcha',
        dot: 'bg-primary',
        leaf: 'text-moss/50',
        letter: 'text-primary',
    },
] as const;

const tone = computed(
    () =>
        tones[
            (((props.shape - 1) % tones.length) + tones.length) % tones.length
        ],
);

const leafVariant = computed(
    () =>
        (['sprig', 'eucalyptus', 'leaf'] as const)[
            (((props.shape - 1) % 3) + 3) % 3
        ],
);

const initial = computed(
    () => Array.from(props.collection.name.trim())[0]?.toUpperCase() ?? '',
);

const images = computed(() =>
    (props.collection.images ?? []).filter(Boolean).slice(0, 4),
);

/** 1 Bild: voll; 2: nebeneinander; 3: groß links + zwei rechts; 4: 2 × 2 */
const collageGridClass = computed(() => {
    switch (images.value.length) {
        case 1:
            return '';
        case 2:
            return 'grid-cols-[1.4fr_1fr]';
        case 3:
            return 'grid-cols-[1.4fr_1fr] grid-rows-2';
        default:
            return 'grid-cols-2 grid-rows-2';
    }
});

const countText = computed(() => {
    const n = props.collection.recipeCount ?? 0;
    return n === 1 ? '1 Rezept' : `${n} Rezepte`;
});
</script>

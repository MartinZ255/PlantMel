<template>
    <!--
        Gate C1b: keine Kastenkarte mehr. Das Bild schwebt in einer Blob-Maske
        über einer versetzten zweiten Form (Ebenen), Text steht direkt auf dem
        Naturgrund. Der Titel-Link deckt die ganze Karte ab (after:inset-0).
    -->
    <article
        v-if="recipe"
        class="group relative isolate flex h-full min-w-0 flex-col gap-3.5"
    >
        <div class="relative">
            <!-- zweite, versetzte Form hinter dem Bild -->
            <span
                class="absolute inset-x-5 inset-y-1 blob-mask -z-10 translate-x-1.5 translate-y-2.5 rotate-[3deg] transition-[translate,rotate,border-radius] duration-700 ease-organic group-hover:translate-x-3 group-hover:translate-y-3.5 group-hover:rotate-[6deg] sm:translate-x-2.5 sm:translate-y-3 sm:rotate-[4deg] sm:group-hover:translate-x-4 sm:group-hover:translate-y-4 sm:group-hover:rotate-[7deg]"
                :class="[shapeClass(shape + 2), backTone]"
                aria-hidden="true"
            ></span>
            <BlobImage
                :src="recipe.image ?? null"
                :shape="shape"
                ratio="4/3"
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
                        <span class="text-sm font-semibold text-ink">
                            Noch kein Foto
                        </span>
                    </div>
                </template>
            </BlobImage>
            <span
                class="absolute bottom-3 left-4 inline-flex items-center gap-1.5 rounded-seed bg-surface/95 px-3 py-1 text-sm font-semibold text-ink shadow-card"
            >
                <Clock class="size-4 text-primary" aria-hidden="true" />
                <span class="sr-only">Dauer:</span>
                {{ durationText }}
            </span>
        </div>

        <div class="flex min-w-0 flex-1 flex-col gap-2.5 px-1 pt-1">
            <ul
                v-if="visibleTags.length"
                class="flex flex-wrap gap-1.5"
                aria-label="Kategorien"
            >
                <li v-for="tag in visibleTags" :key="tag" class="max-w-full">
                    <Badge tone="primary">{{ tag }}</Badge>
                </li>
                <li v-if="hiddenTagCount > 0">
                    <Badge>
                        +{{ hiddenTagCount }}
                        <span class="sr-only">weitere Kategorien</span>
                    </Badge>
                </li>
            </ul>

            <h3
                class="font-display text-[1.375rem] leading-snug font-semibold text-wrap-anywhere text-ink"
            >
                <span v-if="disableLink">{{ recipe.name }}</span>
                <Link
                    v-else
                    :href="route('recipes.show', recipe.id)"
                    class="rounded-sm decoration-berry decoration-2 underline-offset-4 group-hover:underline after:absolute after:inset-0 after:rounded-[2rem] after:content-[''] focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-offset-4 focus-visible:after:outline-focus"
                >
                    {{ recipe.name }}
                </Link>
            </h3>

            <!-- E14: einzige Bewertung ist der Geschmack -->
            <div class="pt-0.5">
                <span class="sr-only">Geschmack: </span>
                <StarRating :value="recipe.rating ?? null" compact size="sm" />
            </div>
        </div>
    </article>
</template>

<script setup lang="ts">
import Badge from '@/components/ui/Badge.vue';
import BlobImage from '@/components/ui/BlobImage.vue';
import StarRating from '@/components/ui/StarRating.vue';
import { Link } from '@inertiajs/vue3';
import { Clock, CookingPot } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';

interface RecipeDetail {
    id: number | string;
    name: string;
    tags?: string[]; // Kategorienamen
    time?: string; // '20 Min' oder '–'
    rating?: number; // Geschmack 0–5 (0 = keine Bewertung, E14)
    image?: string | null;
}

interface Props {
    recipe: RecipeDetail | null;
    /** Sammlung im Bearbeitungsmodus: Karte ist nicht verlinkt */
    disableLink?: boolean;
    /** Form der Bildmaske (1–6); ohne Angabe aus der Rezept-ID abgeleitet */
    shape?: number;
}

const props = withDefaults(defineProps<Props>(), {
    disableLink: false,
    shape: undefined,
});

const shape = computed(() => {
    if (props.shape) return props.shape;
    const id = Number(props.recipe?.id ?? 1);
    return Number.isFinite(id) ? (Math.abs(id - 1) % 6) + 1 : 1;
});

const shapeClass = (n: number) =>
    ['blob-1', 'blob-2', 'blob-3', 'blob-4', 'blob-5', 'blob-6'][
        (((n - 1) % 6) + 6) % 6
    ];

// Farbe der zweiten Form wechselt mit der Form (Matcha, Hellgrün, Beerenhauch)
const backTone = computed(
    () => ['bg-matcha', 'bg-tint-strong', 'bg-berry-surface'][shape.value % 3],
);

const MAX_TAGS = 3;
const tags = computed(() => props.recipe?.tags ?? []);
const visibleTags = computed(() => tags.value.slice(0, MAX_TAGS));
const hiddenTagCount = computed(() =>
    Math.max(0, tags.value.length - MAX_TAGS),
);

const durationText = computed(() => {
    const t = props.recipe?.time;
    if (!t || t === '–') return 'Ohne Zeitangabe';
    return t.replace(/\bMin\b\.?/, 'Min.');
});
</script>

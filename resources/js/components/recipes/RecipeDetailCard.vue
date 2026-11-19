<template>
    <!-- Gate C: Karte mit großem Bild oben; der Titel-Link deckt die ganze Karte ab -->
    <Card v-if="recipe" as="article" :padded="false" interactive class="h-full">
        <template #media>
            <div class="relative aspect-[4/3] w-full overflow-hidden bg-tint">
                <img
                    v-if="recipe.image"
                    :src="recipe.image"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    class="size-full object-cover"
                />
                <div
                    v-else
                    class="flex size-full flex-col items-center justify-center gap-2 bg-gradient-to-br from-tint to-tint-strong text-primary"
                >
                    <CookingPot class="size-10" aria-hidden="true" />
                    <span class="text-sm font-medium text-ink-muted">
                        Noch kein Foto
                    </span>
                </div>
            </div>
        </template>

        <div class="flex h-full flex-col gap-3 p-4 sm:p-5">
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
                class="text-wrap-anywhere text-lg leading-snug font-semibold text-ink"
            >
                <span v-if="disableLink">{{ recipe.name }}</span>
                <Link
                    v-else
                    :href="route('recipes.show', recipe.id)"
                    class="rounded-sm after:absolute after:inset-0 after:rounded-xl after:content-[''] hover:text-primary focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-offset-2 focus-visible:after:outline-focus"
                >
                    {{ recipe.name }}
                </Link>
            </h3>

            <div
                class="mt-auto flex flex-wrap items-center justify-between gap-x-4 gap-y-2 pt-1 text-sm text-ink-muted"
            >
                <span class="inline-flex items-center gap-1.5">
                    <Clock class="size-4" aria-hidden="true" />
                    <span class="sr-only">Dauer:</span>
                    {{ durationText }}
                </span>
                <StarRating :value="recipe.rating ?? null" compact size="sm" />
            </div>
        </div>
    </Card>
</template>

<script setup lang="ts">
import Badge from '@/components/ui/Badge.vue';
import Card from '@/components/ui/Card.vue';
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
    rating?: number; // 0–5 (0 = keine Bewertung)
    image?: string | null;
}

interface Props {
    recipe: RecipeDetail | null;
    /** Sammlung im Bearbeitungsmodus: Karte ist nicht verlinkt */
    disableLink?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    disableLink: false,
});

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

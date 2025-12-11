<template>
    <!-- Eine Karte pro Rezept -->
    <article v-if="recipe" class="recipe-card">
        <component
            :is="disableLink ? 'div' : 'a'"
            class="recipe-card__link"
            v-bind="!disableLink ? { href: route('recipes.show', recipe.id) } : {}"
        >
            <div class="recipe-detail-card">
                <div class="recipe-card__image image-wrapper">
                    <img
                        v-if="recipe.image"
                        :src="recipe.image"
                        :alt="recipe.name"
                        class="recipe-card__image-img"
                    />
                    <div v-else class="recipe-card__image-placeholder">
                        Bild
                    </div>
                </div>

                <div class="recipe-card__body">
                    <h3 class="recipe-card__title underline-text">
                        {{ recipe.name }}
                    </h3>

                    <!-- Tags nur anzeigen, wenn vorhanden -->
                    <div v-if="hasTags" class="recipe-card__tags">
                        <span
                            v-for="tag in recipe.tags"
                            :key="tag"
                            class="recipe-card__tag"
                        >
                            {{ tag }}
                        </span>
                    </div>

                    <div class="recipe-card__rating-row">
                        <span class="recipe-card__rating-label">gesamt</span>
                        <div class="recipe-card__stars">
                            <span class="recipe-card__stars--filled">
                                {{ '★ '.repeat(filledStars).trim() }}
                            </span>
                            <span>
                                {{ '★ '.repeat(emptyStars).trim() }}
                            </span>
                        </div>
                    </div>

                    <div class="recipe-card__footer">
                        <span class="recipe-card__footer-right">
                            Dauer: {{ recipe.time }}
                        </span>
                    </div>
                </div>
            </div>
        </component>
    </article>
</template>


<script setup lang="ts">
import { computed } from 'vue';
import { route } from 'ziggy-js';

interface RecipeDetail {
    id: number | string;
    name: string;
    tags?: string[];      // ['scharf','unter 30 Min', ...]
    time?: string;         // '20 Min'
    rating?: number;  // 0–5
    image?: string | null;
}

interface Props {
    recipe: RecipeDetail | null;
    disableLink?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    disableLink: false,
});

const hasTags = computed(
    () => !!props.recipe && Array.isArray(props.recipe.tags) && props.recipe.tags.length > 0,
);

// Sterne auf 0–5 begrenzen
const filledStars = computed(() => {
    if (!props.recipe) return 0;
    return Math.min(5, Math.max(0, props.recipe.rating ?? 0));
});

const emptyStars = computed(() => 5 - filledStars.value);
</script>

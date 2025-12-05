<template>
    <AppLayout :page-title="collection.name">
        <section class="content-section">
            <header class="collection-hero">
                <div class="collection-hero__text">
                    <h1 class="collection-hero__title">
                        {{ collection.name }}
                    </h1>

                    <p
                        v-if="collection.description"
                        class="collection-hero__description"
                    >
                        {{ collection.description }}
                    </p>

                    <div class="collection-hero__meta">
                        <span>{{ recipes.length }} Rezepte</span>
                        <span v-if="collection.updatedAt">
                            · Aktualisiert: {{ collection.updatedAt }}
                        </span>
                        <span v-if="collection.isPublic">
                            · Öffentlich
                        </span>
                    </div>

                    <div
                        v-if="collection.tags && collection.tags.length"
                        class="collection-hero__tags"
                    >
                        <span
                            v-for="tag in collection.tags"
                            :key="tag"
                            class="collection-hero__tag"
                        >
                            {{ tag }}
                        </span>
                    </div>
                </div>

                <div class="collection-hero__actions">
                    <button
                        type="button"
                        class="secondary-pill-button"
                        @click="editCollection"
                    >
                        Sammlung bearbeiten
                    </button>
                </div>
            </header>

            <div class="recipes-grid">
                <RecipeDetailCard
                    v-for="recipe in recipes"
                    :key="recipe.id"
                    :recipe="recipe"
                />
            </div>
        </section>
    </AppLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import AppLayout from '@/components/layout/AppLayout.vue';
import RecipeDetailCard from '@/components/recipes/RecipeDetailCard.vue';

interface Collection {
    id: number | string;
    name: string;
    description?: string | null;
    updatedAt?: string | null;
    isPublic?: boolean;
    tags?: string[];
}

interface RecipeCard {
    id: number;
    name: string;
    rating: number;      // 0–5
    tags: string[];
    time: string;        // '20 Min'
    image: string | null;
}

// Props von Inertia
const props = defineProps<{
    collection: Collection;
    recipes: RecipeCard[];
}>();

const collection = computed(() => props.collection);
const recipes = computed(() => props.recipes);

const editCollection = () => {
    // später: router.visit(route('collections.edit', collection.value.id))
    console.log('Sammlung bearbeiten', collection.value.id);
};
</script>

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
                    </div>
                </div>

                <div class="collection-hero__actions">
                    <button
                        type="button"
                        class="secondary-pill-button"
                        :class="{ 'secondary-pill-button--active': isEditMode }"
                        @click="toggleEditMode"
                    >
                        {{ isEditMode ? 'Bearbeitungsmodus beenden' : 'Rezepte entfernen' }}
                    </button>

                    <button
                        type="button"
                        class="secondary-pill-button"
                        @click="deleteCollection"
                    >
                        Sammlung löschen
                    </button>

                </div>
            </header>

            <div class="recipes-grid">
                <div
                    v-for="recipe in recipes"
                    :key="recipe.id"
                    class="collection-recipe-item"
                    :class="{ 'collection-recipe-item--edit-mode': isEditMode }"
                    @click="onRecipeClick(recipe.id)"
                >
                    <RecipeDetailCard
                        :recipe="recipe"
                        :disable-link="isEditMode"
                    />
                </div>
            </div>
        </section>
    </AppLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppLayout from '@/components/layout/AppLayout.vue';
import RecipeDetailCard from '@/components/recipes/RecipeDetailCard.vue';

interface Collection {
    id: number | string;
    name: string;
    description?: string | null;
    updatedAt?: string | null;
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

// Bearbeitungsmodus
const isEditMode = ref(false);

const toggleEditMode = () => {
    isEditMode.value = !isEditMode.value;
};

// Klick auf eine Rezeptkarte
const onRecipeClick = (recipeId: number) => {
    // Wenn nicht im Edit-Modus: normales Verhalten (z. B. Detailseite öffnen)
    if (!isEditMode.value) {
        // Beispiel: falls deine RecipeDetailCard ansonsten nur ein Link ist,
        // kannst du das hier auch leer lassen. Wenn du die Detailseite
        // explizit öffnen willst:
        // router.visit(route('recipes.show', recipeId));
        return;
    }

    // Im Edit-Modus: Rezept aus Sammlung entfernen
    router.delete(
        route('collections.removeRecipe', {
            collection: collection.value.id,
            recipe: recipeId,
        }),
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const deleteCollection = () => {
    if (!confirm('Möchtest du diese Sammlung wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.')) {
        return;
    }

    router.delete(
        route('collections.delete', collection.value.id),
        {
            preserveScroll: true,
        },
    );
};

</script>

<style scoped>

.collection-hero {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 24px; /* optional, Abstand zwischen Text und Buttons */
}

/* Buttons rechts im Header untereinander anordnen */
.collection-hero__actions {
    display: flex;
    flex-direction: column;
    gap: 8px;          /* Abstand zwischen den Buttons */
    align-items: flex-end;
}

.collection-recipe-item {
    /* nur semantischer Wrapper */
}

/* Edit-Modus: der Wrapper zeigt, dass etwas passieren kann */
.collection-recipe-item--edit-mode {
    cursor: pointer;
}

/* Markierung des Buttons im Edit-Modus */
.secondary-pill-button--active {
    border-color: #dc3545;
    color: #dc3545;
    border-radius: 18px;
}


</style>

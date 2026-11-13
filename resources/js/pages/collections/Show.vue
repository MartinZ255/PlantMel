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
                        ref="editModeButton"
                        type="button"
                        class="secondary-pill-button"
                        :class="{ 'secondary-pill-button--active': isEditMode }"
                        :aria-pressed="isEditMode"
                        @click="toggleEditMode"
                    >
                        {{
                            isEditMode
                                ? 'Bearbeitungsmodus beenden'
                                : 'Rezepte entfernen'
                        }}
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

            <!-- F-39: Modus klar erkennbar; entfernt wird nur über den Button mit Rückfrage -->
            <p v-if="isEditMode" class="collection-remove-hint" role="status">
                Bearbeitungsmodus: Wähle bei einem Rezept „Aus Sammlung
                entfernen“ und bestätige. Das Rezept selbst bleibt erhalten und
                kann jederzeit wieder hinzugefügt werden.
            </p>

            <div class="recipes-grid">
                <div
                    v-for="recipe in recipes"
                    :key="recipe.id"
                    class="collection-recipe-item"
                    :class="{ 'collection-recipe-item--edit-mode': isEditMode }"
                >
                    <RecipeDetailCard
                        :recipe="recipe"
                        :disable-link="isEditMode"
                    />

                    <div v-if="isEditMode" class="collection-remove">
                        <div
                            v-if="confirmingRecipeId === recipe.id"
                            class="collection-remove__confirm"
                            role="group"
                            :aria-labelledby="`collection-remove-question-${recipe.id}`"
                        >
                            <p
                                :id="`collection-remove-question-${recipe.id}`"
                                class="collection-remove__question"
                            >
                                Wirklich entfernen?
                            </p>
                            <div class="collection-remove__actions">
                                <button
                                    type="button"
                                    class="secondary-pill-button collection-remove__yes"
                                    :aria-label="`Ja, „${recipe.name}“ aus der Sammlung entfernen`"
                                    :disabled="removingRecipeId !== null"
                                    @click="removeRecipe(recipe.id)"
                                >
                                    Ja
                                </button>
                                <button
                                    :ref="
                                        (el) => setCancelButton(recipe.id, el)
                                    "
                                    type="button"
                                    class="secondary-pill-button"
                                    :aria-label="`Abbrechen, „${recipe.name}“ behalten`"
                                    :disabled="removingRecipeId !== null"
                                    @click="cancelRemove(recipe.id)"
                                >
                                    Abbrechen
                                </button>
                            </div>
                        </div>

                        <button
                            v-else
                            :ref="(el) => setRemoveButton(recipe.id, el)"
                            type="button"
                            class="secondary-pill-button collection-remove__button"
                            :aria-label="`Aus Sammlung entfernen: ${recipe.name}`"
                            :disabled="removingRecipeId !== null"
                            @click="askRemove(recipe.id)"
                        >
                            Aus Sammlung entfernen
                        </button>

                        <p
                            v-if="
                                removeError &&
                                removeError.recipeId === recipe.id
                            "
                            class="collection-remove__error"
                            role="alert"
                        >
                            {{ removeError.message }}
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import RecipeDetailCard from '@/components/recipes/RecipeDetailCard.vue';
import { router } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import { route } from 'ziggy-js';

interface Collection {
    id: number | string;
    name: string;
    description?: string | null;
    updatedAt?: string | null;
}

interface RecipeCard {
    id: number;
    name: string;
    rating: number; // 0–5
    tags: string[];
    time: string; // '20 Min'
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
const editModeButton = ref<HTMLButtonElement | null>(null);

// F-39: Rückfrage direkt an der Karte statt sofortigem Entfernen
const confirmingRecipeId = ref<number | null>(null);
const removingRecipeId = ref<number | null>(null);
const removeError = ref<{ recipeId: number; message: string } | null>(null);

const removeButtons = new Map<number, HTMLButtonElement>();
const cancelButtons = new Map<number, HTMLButtonElement>();

const setRemoveButton = (id: number, el: unknown) => {
    if (el instanceof HTMLButtonElement) removeButtons.set(id, el);
    else removeButtons.delete(id);
};
const setCancelButton = (id: number, el: unknown) => {
    if (el instanceof HTMLButtonElement) cancelButtons.set(id, el);
    else cancelButtons.delete(id);
};

const toggleEditMode = () => {
    isEditMode.value = !isEditMode.value;
    confirmingRecipeId.value = null;
    removeError.value = null;
};

const askRemove = async (recipeId: number) => {
    removeError.value = null;
    confirmingRecipeId.value = recipeId;
    await nextTick();
    // Fokus auf „Abbrechen“: zweimal Enter entfernt nicht versehentlich
    cancelButtons.get(recipeId)?.focus();
};

const cancelRemove = async (recipeId: number) => {
    confirmingRecipeId.value = null;
    await nextTick();
    removeButtons.get(recipeId)?.focus();
};

/** Verständliche deutsche Meldung je HTTP-Status (ohne Status: Netzwerkfehler). */
const describeStatus = (status?: number): string => {
    if (!status)
        return 'Keine Verbindung zum Server. Bitte prüfe die Verbindung und versuche es erneut.';
    if (status === 403) return 'Dafür fehlt dir die Berechtigung.';
    if (status === 404)
        return 'Das Rezept oder die Sammlung wurde nicht gefunden. Bitte lade die Seite neu.';
    if (status === 419)
        return 'Deine Sitzung ist abgelaufen. Bitte lade die Seite neu und versuche es erneut.';
    if (status >= 500)
        return 'Auf dem Server ist ein Fehler aufgetreten. Bitte versuche es später erneut.';
    return 'Das Rezept konnte nicht aus der Sammlung entfernt werden. Bitte versuche es erneut.';
};

/**
 * Erst nach „Ja“: DELETE als Inertia-Visit (Laravel antwortet mit einem
 * Redirect, den Inertia als 303 erhält; ein XHR würde ihn als DELETE
 * wiederholen). Fehlerantworten (`invalid`) und Netzwerkfehler (`exception`)
 * werden abgefangen und an der Karte angezeigt statt im Inertia-Fehlermodal.
 */
const removeRecipe = (recipeId: number) => {
    if (removingRecipeId.value !== null) return;
    removingRecipeId.value = recipeId;
    removeError.value = null;

    const stopInvalid = router.on('invalid', (event) => {
        event.preventDefault();
        removeError.value = {
            recipeId,
            message: describeStatus(event.detail.response.status),
        };
    });
    const stopException = router.on('exception', (event) => {
        event.preventDefault();
        removeError.value = { recipeId, message: describeStatus() };
    });

    router.delete(
        route('collections.removeRecipe', {
            collection: collection.value.id,
            recipe: recipeId,
        }),
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                confirmingRecipeId.value = null;
                nextTick(() => editModeButton.value?.focus());
            },
            onFinish: () => {
                stopInvalid();
                stopException();
                removingRecipeId.value = null;
            },
        },
    );
};

const deleteCollection = () => {
    if (
        !confirm(
            'Möchtest du diese Sammlung wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.',
        )
    ) {
        return;
    }

    router.delete(route('collections.delete', collection.value.id), {
        preserveScroll: true,
    });
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
    gap: 8px; /* Abstand zwischen den Buttons */
    align-items: flex-end;
}

.collection-recipe-item {
    /* nur semantischer Wrapper */
}

/* Edit-Modus: Karte selbst ist inaktiv, entfernt wird nur über den Button (F-39) */
.collection-recipe-item--edit-mode {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.collection-remove-hint {
    margin: 0 0 16px;
    padding: 10px 14px;
    border: 1px dashed var(--danger);
    border-radius: 12px;
    font-size: 0.9rem;
    color: var(--text-main);
}

.collection-remove {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.collection-remove__button {
    align-self: flex-start;
    border-color: var(--danger);
    color: var(--danger);
}

.collection-remove__confirm {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.collection-remove__question {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--danger);
}

.collection-remove__actions {
    display: flex;
    gap: 8px;
}

.collection-remove__yes {
    border-color: var(--danger);
    color: var(--danger);
}

.collection-remove__error {
    margin: 0;
    font-size: 0.85rem;
    color: var(--danger);
}

/* Markierung des Buttons im Edit-Modus */
.secondary-pill-button--active {
    border-color: var(--danger);
    color: var(--danger);
    border-radius: 18px;
}

@media (max-width: 720px) {
    /* Hero auf Mobile stapeln: Text oben, Buttons in voller Breite darunter */
    .collection-hero {
        flex-direction: column;
        gap: 12px;
    }

    .collection-hero__actions {
        width: 100%;
        align-items: stretch;
    }
}
</style>

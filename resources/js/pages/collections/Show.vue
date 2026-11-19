<template>
    <AppLayout :page-title="collection.name" bleed>
        <!-- ============================================================
             Kopf: Waldgrün mit Körnung, Fraunces-Titel, Eckdaten-Kiesel
             ============================================================ -->
        <header
            class="relative overflow-hidden bg-forest grain-light on-forest"
        >
            <BlobBackdrop tone="forest" />
            <LeafOrnament
                variant="sprig"
                class="pointer-events-none absolute -top-6 right-[8%] h-36 animate-sway text-forest-soft [--float-rotate:-18deg] sm:h-48"
            />
            <LeafOrnament
                variant="eucalyptus"
                class="pointer-events-none absolute -bottom-10 left-[44%] hidden h-44 rotate-[68deg] text-forest-soft lg:block"
            />

            <div
                class="relative mx-auto w-full max-w-(--content-max) px-4 pt-6 pb-4 sm:px-6 lg:px-8 lg:pt-10 lg:pb-6"
            >
                <Link
                    :href="route('collections.index')"
                    class="mb-5 inline-flex min-h-10 items-center gap-1.5 rounded-pebble px-2 text-sm font-semibold text-on-forest-muted hover:bg-forest-soft hover:text-on-forest focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                >
                    <ArrowLeft class="size-4" aria-hidden="true" />
                    Alle Sammlungen
                </Link>

                <div
                    class="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between"
                >
                    <div class="min-w-0 lg:max-w-3xl">
                        <p
                            class="mb-3 inline-flex items-center gap-2 text-sm font-semibold tracking-[0.14em] text-matcha uppercase"
                        >
                            <LeafOrnament variant="sprout" class="size-4" />
                            Sammlung
                        </p>
                        <h1
                            class="font-display-wonk text-[clamp(2.25rem,8vw,4.5rem)] leading-[1.02] font-semibold text-balance text-wrap-anywhere text-on-forest"
                        >
                            {{ collection.name }}
                        </h1>
                        <p
                            v-if="collection.description"
                            class="mt-4 max-w-2xl text-lg text-wrap-anywhere text-on-forest-muted sm:text-xl"
                        >
                            {{ collection.description }}
                        </p>

                        <dl
                            class="mt-6 flex flex-wrap gap-3"
                            aria-label="Eckdaten"
                        >
                            <div
                                class="rounded-[1.4rem_1.1rem_1.5rem_1.2rem] bg-forest-deep/55 px-4 py-3 ring-1 ring-forest-soft backdrop-blur-sm"
                            >
                                <dt
                                    class="flex items-center gap-1.5 text-sm text-on-forest-muted"
                                >
                                    <BookOpen
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    Rezepte
                                </dt>
                                <dd
                                    class="mt-0.5 font-display text-2xl font-semibold text-on-forest"
                                >
                                    {{ recipes.length }}
                                </dd>
                            </div>
                            <div
                                v-if="collection.updatedAt"
                                class="rounded-[1.1rem_1.5rem_1.2rem_1.4rem] bg-forest-deep/55 px-4 py-3 ring-1 ring-forest-soft backdrop-blur-sm"
                            >
                                <dt
                                    class="flex items-center gap-1.5 text-sm text-on-forest-muted"
                                >
                                    <Clock class="size-4" aria-hidden="true" />
                                    Aktualisiert
                                </dt>
                                <dd
                                    class="mt-0.5 font-display text-2xl font-semibold text-on-forest"
                                >
                                    {{ collection.updatedAt }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Aktionen: umbrechend, nie über dem Titel -->
                    <div class="flex flex-wrap gap-2.5 lg:justify-end">
                        <!-- ohne Rezepte gibt es nichts zu entfernen; im Modus bleibt
                             der Knopf, damit der Fokus nach dem letzten Entfernen landet -->
                        <button
                            v-if="recipes.length || isEditMode"
                            ref="editModeButton"
                            type="button"
                            class="inline-flex min-h-11 items-center justify-center gap-2 pebble-morph border-[1.5px] px-5 text-base font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                            :class="
                                isEditMode
                                    ? 'border-matcha bg-matcha text-forest hover:bg-matcha-hover'
                                    : 'border-on-forest-muted text-on-forest hover:border-on-forest hover:bg-forest-soft'
                            "
                            :aria-pressed="isEditMode"
                            @click="toggleEditMode"
                        >
                            <Check
                                v-if="isEditMode"
                                class="size-4"
                                aria-hidden="true"
                            />
                            <ListX v-else class="size-4" aria-hidden="true" />
                            {{
                                isEditMode
                                    ? 'Bearbeitungsmodus beenden'
                                    : 'Rezepte entfernen'
                            }}
                        </button>

                        <Button
                            variant="ghost"
                            class="text-danger-on-forest hover:bg-forest-soft active:bg-forest-deep"
                            aria-haspopup="dialog"
                            @click="isDeleteOpen = true"
                        >
                            <Trash2 class="size-4" aria-hidden="true" />
                            Sammlung löschen
                        </Button>
                    </div>
                </div>
            </div>
            <WaveDivider :variant="2" class="text-canvas" />
        </header>

        <section
            class="mx-auto w-full max-w-(--content-max) px-4 pt-6 pb-16 sm:px-6 lg:px-8"
            aria-labelledby="collection-recipes-title"
        >
            <h2
                id="collection-recipes-title"
                class="mb-6 font-display-wonk text-3xl font-semibold text-ink sm:text-4xl"
            >
                Rezepte in dieser
                <span class="accent-word text-primary">Sammlung</span>
            </h2>

            <!-- F-39: Modus klar erkennbar; entfernt wird nur über den Button mit Rückfrage -->
            <p
                v-if="isEditMode"
                class="collection-remove-hint mb-8 flex items-start gap-3 rounded-[1.4rem_1.1rem_1.5rem_1.2rem] border-[1.5px] border-dashed border-danger bg-danger-surface px-4 py-3 text-ink"
                role="status"
            >
                <Info
                    class="mt-0.5 size-5 shrink-0 text-danger"
                    aria-hidden="true"
                />
                <span>
                    Bearbeitungsmodus: Wähle bei einem Rezept „Aus Sammlung
                    entfernen“ und bestätige. Das Rezept selbst bleibt erhalten
                    und kann jederzeit wieder hinzugefügt werden.
                </span>
            </p>

            <EmptyState
                v-if="!recipes.length"
                :icon="BookmarkPlus"
                title="Diese Sammlung ist noch leer"
                description="Öffne ein Rezept und wähle „Zur Sammlung hinzufügen“, um es hier abzulegen."
                :heading-level="3"
            >
                <template #actions>
                    <Button :href="route('recipes.index')">
                        Rezepte entdecken
                    </Button>
                </template>
            </EmptyState>

            <ul
                v-else
                class="grid grid-cols-1 gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3 lg:gap-x-10"
                aria-labelledby="collection-recipes-title"
            >
                <li
                    v-for="(recipe, i) in recipes"
                    :key="recipe.id"
                    class="collection-recipe-item flex min-w-0 flex-col gap-3 sm:max-lg:even:mt-12 lg:[&:nth-child(3n+2)]:mt-16"
                    :class="
                        isEditMode && 'rounded-[2rem_1.5rem_2.25rem_1.75rem]'
                    "
                >
                    <RecipeDetailCard
                        :recipe="recipe"
                        :shape="(i % 6) + 1"
                        :disable-link="isEditMode"
                    />

                    <div v-if="isEditMode" class="flex flex-col gap-2 px-1">
                        <div
                            v-if="confirmingRecipeId === recipe.id"
                            class="flex flex-wrap items-center gap-2 rounded-[1.2rem_1rem_1.3rem_0.9rem] bg-danger-surface p-2.5"
                            role="group"
                            :aria-labelledby="`collection-remove-question-${recipe.id}`"
                        >
                            <p
                                :id="`collection-remove-question-${recipe.id}`"
                                class="mr-auto pl-1.5 text-sm font-semibold text-danger"
                            >
                                Wirklich entfernen?
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    variant="danger"
                                    size="sm"
                                    :aria-label="`Ja, „${recipe.name}“ aus der Sammlung entfernen`"
                                    :disabled="removingRecipeId !== null"
                                    :loading="removingRecipeId === recipe.id"
                                    @click="removeRecipe(recipe.id)"
                                >
                                    Ja
                                </Button>
                                <button
                                    :ref="
                                        (el) => setCancelButton(recipe.id, el)
                                    "
                                    type="button"
                                    class="inline-flex min-h-9 items-center justify-center pebble-morph bg-surface px-4 text-sm font-semibold text-ink hover:bg-tint focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus disabled:cursor-not-allowed disabled:opacity-60"
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
                            class="inline-flex min-h-11 items-center gap-2 self-start pebble-morph border-[1.5px] border-danger bg-surface px-4 text-sm font-semibold text-danger hover:bg-danger-surface focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus disabled:cursor-not-allowed disabled:opacity-60"
                            :aria-label="`Aus Sammlung entfernen: ${recipe.name}`"
                            :disabled="removingRecipeId !== null"
                            @click="askRemove(recipe.id)"
                        >
                            <X class="size-4" aria-hidden="true" />
                            Aus Sammlung entfernen
                        </button>

                        <p
                            v-if="
                                removeError &&
                                removeError.recipeId === recipe.id
                            "
                            class="text-sm font-medium text-danger"
                            role="alert"
                        >
                            {{ removeError.message }}
                        </p>
                    </div>
                </li>
            </ul>
        </section>

        <!-- Löschen mit Rückfrage (Dialog statt confirm()) -->
        <Dialog
            :open="isDeleteOpen"
            title="Sammlung löschen?"
            :description="`„${collection.name}“ wird dauerhaft gelöscht. Die Rezepte selbst bleiben erhalten. Diese Aktion kann nicht rückgängig gemacht werden.`"
            size="sm"
            @close="closeDelete"
        >
            <template #footer>
                <Button
                    variant="ghost"
                    :disabled="deleting"
                    @click="closeDelete"
                >
                    Abbrechen
                </Button>
                <Button
                    variant="danger"
                    :loading="deleting"
                    @click="deleteCollection"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                    Endgültig löschen
                </Button>
            </template>
        </Dialog>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import RecipeDetailCard from '@/components/recipes/RecipeDetailCard.vue';
import BlobBackdrop from '@/components/ui/BlobBackdrop.vue';
import Button from '@/components/ui/Button.vue';
import Dialog from '@/components/ui/Dialog.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import WaveDivider from '@/components/ui/WaveDivider.vue';
import { Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BookmarkPlus,
    BookOpen,
    Check,
    Clock,
    Info,
    ListX,
    Trash2,
    X,
} from 'lucide-vue-next';
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

// --- Sammlung löschen: Rückfrage im Dialog (vorher window.confirm) -------------
const isDeleteOpen = ref(false);
const deleting = ref(false);

const closeDelete = () => {
    if (!deleting.value) isDeleteOpen.value = false;
};

const deleteCollection = () => {
    if (deleting.value) return;
    deleting.value = true;
    router.delete(route('collections.delete', collection.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            isDeleteOpen.value = false;
        },
    });
};
</script>

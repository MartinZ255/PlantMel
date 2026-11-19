<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Dialog from '@/components/ui/Dialog.vue';
import StarRating from '@/components/ui/StarRating.vue';
import AddToCollectionModal from '@/components/widgets/AddToCollectionModal.vue';
import { Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BookmarkPlus,
    Clock,
    CookingPot,
    Pencil,
    Trash2,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

type RatingDimension = {
    id: number | null;
    name: string;
    avg: number; // 1.0–5.0
    count: number; // Anzahl Bewertungen für diese Dimension
};

type CollectionOption = {
    id: number;
    name: string;
};

const props = defineProps<{
    recipe: {
        id: number;
        name: string;
        description: string | null;
        image: string | null;
        duration_minutes: number | null;
        servings: number | null;
        difficulty: string | null;
        categories: string[];
        tags: string[];
        rating: number | null;
        ratingCount: number;
        ratingDimensions: RatingDimension[];
        // F-15: kein Feld „Quelle“ mehr
        ingredients: {
            amount: string | null;
            name: string | null;
            alternatives?: string[];
        }[];
        // ADR-001: mehrzeilige Schritte als EIN String mit "\n"
        steps: string[];
        notes: string[];
        createdAt: string | null;
        updatedAt: string | null;
    };
    collections: CollectionOption[];
    isHost: boolean;
}>();

const recipe = computed(() => props.recipe);

const hasAnyRatings = computed(
    () => recipe.value.rating != null && recipe.value.ratingCount > 0,
);
const ratingDimensions = computed(() => recipe.value.ratingDimensions ?? []);

// --- Zur Sammlung hinzufügen ---------------------------------------------------
const isCollectionModalOpen = ref(false);

// --- Löschen mit Rückfrage (Dialog statt confirm()) ------------------------------
const isDeleteOpen = ref(false);
const deleting = ref(false);

const deleteRecipe = () => {
    if (deleting.value) return;
    deleting.value = true;
    router.delete(route('recipes.delete', recipe.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            isDeleteOpen.value = false;
        },
    });
};

const closeDelete = () => {
    if (!deleting.value) isDeleteOpen.value = false;
};

const durationText = computed(() =>
    recipe.value.duration_minutes !== null
        ? `${recipe.value.duration_minutes} Min.`
        : 'Keine Angabe',
);
const servingsText = computed(() =>
    recipe.value.servings !== null
        ? String(recipe.value.servings)
        : 'Keine Angabe',
);
</script>

<template>
    <AppLayout :page-title="recipe.name" bleed>
        <article class="pb-12">
            <!-- Bild oben: festes Seitenverhältnis, kein Layout-Springen (F-85) -->
            <div
                class="mx-auto w-full max-w-(--content-max) sm:px-6 sm:pt-6 lg:px-8"
            >
                <div
                    class="relative w-full overflow-hidden bg-tint sm:rounded-2xl"
                    :class="
                        recipe.image
                            ? 'aspect-[4/3] sm:aspect-[16/9] lg:aspect-[21/9]'
                            : 'aspect-[3/1] sm:aspect-[5/1]'
                    "
                >
                    <img
                        v-if="recipe.image"
                        :src="recipe.image"
                        :alt="`Foto: ${recipe.name}`"
                        class="size-full object-cover"
                    />
                    <!-- ohne Bild: dekorative Fläche statt leerer Kasten -->
                    <div
                        v-else
                        class="absolute inset-0 overflow-hidden bg-gradient-to-br from-tint via-tint to-tint-strong"
                        aria-hidden="true"
                    >
                        <span
                            class="absolute -top-10 right-[6%] size-40 rounded-full bg-accent/15 sm:size-56"
                        ></span>
                        <span
                            class="absolute -bottom-12 left-8 size-32 rounded-full bg-primary/10 sm:size-44"
                        ></span>
                        <CookingPot
                            class="absolute top-1/2 left-1/2 size-12 -translate-x-1/2 -translate-y-1/2 text-primary sm:size-16"
                        />
                    </div>
                </div>
            </div>

            <div class="mx-auto w-full max-w-5xl px-4 sm:px-6 lg:px-8">
                <!-- Kopf -->
                <header class="pt-6 sm:pt-8">
                    <Link
                        :href="route('recipes.index')"
                        class="mb-4 inline-flex min-h-10 items-center gap-1.5 rounded-full text-sm font-semibold text-primary hover:text-primary-hover hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                    >
                        <ArrowLeft class="size-4" aria-hidden="true" />
                        Zur Übersicht
                    </Link>

                    <ul
                        v-if="recipe.categories?.length"
                        class="mb-3 flex flex-wrap gap-2"
                        aria-label="Kategorien"
                    >
                        <li
                            v-for="cat in recipe.categories"
                            :key="cat"
                            class="max-w-full"
                        >
                            <Badge tone="primary">{{ cat }}</Badge>
                        </li>
                    </ul>

                    <h1
                        class="text-wrap-anywhere text-3xl font-semibold tracking-tight text-ink sm:text-4xl lg:text-5xl"
                    >
                        {{ recipe.name }}
                    </h1>

                    <p
                        v-if="recipe.description"
                        class="text-wrap-anywhere mt-3 max-w-3xl text-lg text-ink-muted"
                    >
                        {{ recipe.description }}
                    </p>

                    <!-- Metadaten -->
                    <dl
                        class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3"
                        aria-label="Eckdaten"
                    >
                        <div
                            class="rounded-xl border border-line bg-surface p-4"
                        >
                            <dt
                                class="flex items-center gap-1.5 text-sm text-ink-muted"
                            >
                                <Clock class="size-4" aria-hidden="true" />
                                Zubereitung
                            </dt>
                            <dd class="mt-1 text-lg font-semibold text-ink">
                                {{ durationText }}
                            </dd>
                        </div>
                        <div
                            class="rounded-xl border border-line bg-surface p-4"
                        >
                            <dt
                                class="flex items-center gap-1.5 text-sm text-ink-muted"
                            >
                                <Users class="size-4" aria-hidden="true" />
                                Portionen
                            </dt>
                            <dd class="mt-1 text-lg font-semibold text-ink">
                                {{ servingsText }}
                            </dd>
                        </div>
                        <div
                            class="col-span-2 rounded-xl border border-line bg-surface p-4 sm:col-span-1"
                        >
                            <dt class="text-sm text-ink-muted">Bewertung</dt>
                            <dd class="mt-1.5">
                                <StarRating
                                    :value="
                                        hasAnyRatings ? recipe.rating : null
                                    "
                                    :count="
                                        hasAnyRatings
                                            ? recipe.ratingCount
                                            : null
                                    "
                                />
                            </dd>
                        </div>
                    </dl>

                    <!-- Aktionen: umbrechend, nie über dem Titel (F-31) -->
                    <div class="mt-6 flex flex-wrap gap-2">
                        <Button
                            aria-haspopup="dialog"
                            @click="isCollectionModalOpen = true"
                        >
                            <BookmarkPlus class="size-5" aria-hidden="true" />
                            Zur Sammlung hinzufügen
                        </Button>
                        <template v-if="isHost">
                            <Button
                                variant="secondary"
                                :href="route('recipes.edit', recipe.id)"
                            >
                                <Pencil class="size-4" aria-hidden="true" />
                                Rezept bearbeiten
                            </Button>
                            <Button
                                variant="ghost"
                                class="text-danger hover:bg-danger-surface"
                                aria-haspopup="dialog"
                                @click="isDeleteOpen = true"
                            >
                                <Trash2 class="size-4" aria-hidden="true" />
                                Rezept löschen
                            </Button>
                        </template>
                    </div>
                </header>

                <!-- Inhalt: Zutaten | Zubereitung -->
                <div class="mt-10 grid gap-8 lg:grid-cols-12 lg:gap-10">
                    <section
                        v-if="recipe.ingredients?.length"
                        aria-labelledby="recipe-ingredients-title"
                        class="lg:col-span-5"
                    >
                        <div
                            class="rounded-2xl border border-line bg-surface p-5 shadow-card sm:p-6 lg:sticky lg:top-[calc(var(--header-height)+1.5rem)]"
                        >
                            <h2
                                id="recipe-ingredients-title"
                                class="text-xl font-semibold text-ink"
                            >
                                Zutaten
                            </h2>
                            <ul
                                class="mt-4 divide-y divide-line"
                                aria-labelledby="recipe-ingredients-title"
                            >
                                <li
                                    v-for="(
                                        ingredient, index
                                    ) in recipe.ingredients"
                                    :key="`${index}-${ingredient.name}`"
                                    class="grid grid-cols-[minmax(4.5rem,auto)_minmax(0,1fr)] gap-x-4 py-2.5"
                                >
                                    <span
                                        class="text-wrap-anywhere font-semibold text-ink"
                                    >
                                        {{ ingredient.amount || '–' }}
                                    </span>
                                    <span
                                        class="text-wrap-anywhere min-w-0 text-ink"
                                    >
                                        {{ ingredient.name }}
                                        <span
                                            v-if="
                                                ingredient.alternatives?.length
                                            "
                                            class="block text-sm text-ink-muted"
                                        >
                                            oder:
                                            {{
                                                ingredient.alternatives.join(
                                                    ' / ',
                                                )
                                            }}
                                        </span>
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </section>

                    <section
                        v-if="recipe.steps?.length"
                        aria-labelledby="recipe-steps-title"
                        :class="
                            recipe.ingredients?.length
                                ? 'lg:col-span-7'
                                : 'lg:col-span-12'
                        "
                    >
                        <h2
                            id="recipe-steps-title"
                            class="text-xl font-semibold text-ink"
                        >
                            Zubereitung
                        </h2>
                        <ol
                            class="mt-4 flex flex-col gap-5"
                            aria-labelledby="recipe-steps-title"
                        >
                            <li
                                v-for="(step, index) in recipe.steps"
                                :key="index"
                                class="flex gap-4"
                            >
                                <span
                                    class="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-primary text-base font-semibold text-on-primary"
                                    aria-hidden="true"
                                    >{{ index + 1 }}</span
                                >
                                <!-- F-14/ADR-001: Zeilenumbrüche innerhalb eines Schritts sichtbar -->
                                <p
                                    class="text-wrap-anywhere min-w-0 flex-1 pt-1 text-base leading-relaxed whitespace-pre-line text-ink"
                                >
                                    {{ step }}
                                </p>
                            </li>
                        </ol>
                    </section>
                </div>

                <!-- Notizen (nur, wenn vorhanden) -->
                <section
                    v-if="recipe.notes?.length"
                    aria-labelledby="recipe-notes-title"
                    class="mt-10 rounded-2xl border border-accent/30 bg-accent-surface p-5 sm:p-6"
                >
                    <h2
                        id="recipe-notes-title"
                        class="text-xl font-semibold text-ink"
                    >
                        Notizen & Tipps
                    </h2>
                    <ul
                        class="mt-3 flex list-disc flex-col gap-1.5 pl-5 text-ink marker:text-accent-strong"
                    >
                        <li
                            v-for="(note, index) in recipe.notes"
                            :key="index"
                            class="text-wrap-anywhere"
                        >
                            {{ note }}
                        </li>
                    </ul>
                </section>

                <!-- Bewertungen je Dimension -->
                <section aria-labelledby="recipe-ratings-title" class="mt-10">
                    <h2
                        id="recipe-ratings-title"
                        class="text-xl font-semibold text-ink"
                    >
                        Bewertungen
                    </h2>
                    <div v-if="hasAnyRatings" class="mt-2">
                        <span class="sr-only">Gesamtbewertung: </span>
                        <StarRating
                            :value="recipe.rating"
                            :count="recipe.ratingCount"
                        />
                    </div>
                    <ul
                        v-if="ratingDimensions.length"
                        class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <li
                            v-for="dim in ratingDimensions"
                            :key="dim.id ?? dim.name"
                            class="rounded-xl border border-line bg-surface p-4"
                        >
                            <p
                                class="text-wrap-anywhere font-semibold text-ink"
                            >
                                {{ dim.name }}
                            </p>
                            <div class="mt-1.5">
                                <StarRating
                                    :value="dim.avg"
                                    :count="dim.count"
                                    size="sm"
                                />
                            </div>
                        </li>
                    </ul>
                    <p v-else-if="!hasAnyRatings" class="mt-2 text-ink-muted">
                        Noch keine Bewertungen
                    </p>
                </section>
            </div>
        </article>

        <AddToCollectionModal
            :open="isCollectionModalOpen"
            :collections="props.collections"
            :recipe-id="recipe.id"
            @close="isCollectionModalOpen = false"
        />

        <Dialog
            v-if="isHost"
            :open="isDeleteOpen"
            title="Rezept löschen?"
            :description="`„${recipe.name}“ wird endgültig gelöscht. Das kann nicht rückgängig gemacht werden.`"
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
                    @click="deleteRecipe"
                >
                    Endgültig löschen
                </Button>
            </template>
        </Dialog>
    </AppLayout>
</template>

<template>
    <AppLayout page-title="Zutatenübersicht" bleed>
        <!-- Kopf: organischer Hintergrund, große Fraunces-Headline, Suche als Kapsel -->
        <section class="relative overflow-hidden">
            <BlobBackdrop tone="light" />
            <LeafOrnament
                variant="leaf"
                class="pointer-events-none absolute top-6 right-[6%] hidden h-40 animate-sway text-tint-strong [--float-rotate:-22deg] md:block lg:h-52"
            />
            <LeafOrnament
                variant="sprout"
                class="pointer-events-none absolute top-28 right-[26%] hidden size-14 animate-float-slow text-moss/60 [--float-rotate:-12deg] lg:block"
            />
            <div
                class="relative mx-auto flex w-full max-w-(--content-max) flex-col gap-8 px-4 pt-8 pb-10 sm:px-6 lg:px-8 lg:pt-12 lg:pb-14"
            >
                <PageHeader
                    title="Zutaten"
                    eyebrow="Aus der Vorratskammer"
                    description="Alle Zutaten aus den Rezepten auf einen Blick – suche nach Namen oder filtere nach Begriffen."
                    size="xl"
                />

                <!-- Keine Kategorie-Chips/-Zähler: das Backend liefert keine Kategorien (F-02) -->
                <IngredientSearchArea
                    :ingredients="allIngredients"
                    @update:results="handleResultsUpdate"
                />
            </div>
        </section>

        <section
            aria-labelledby="ingredient-results-title"
            class="mx-auto w-full max-w-(--content-max) px-4 pt-2 pb-16 sm:px-6 lg:px-8"
        >
            <h2
                id="ingredient-results-title"
                class="mb-8 font-display-wonk text-3xl font-semibold text-ink sm:text-4xl"
            >
                Von
                <span class="accent-word text-primary">A</span>
                bis
                <span class="accent-word text-berry">Z</span>
            </h2>

            <EmptyState
                v-if="!ingredientCards.length"
                :icon="allIngredients.length ? SearchX : Carrot"
                :title="
                    allIngredients.length
                        ? 'Keine Zutaten gefunden'
                        : 'Noch keine Zutaten'
                "
                :description="
                    allIngredients.length
                        ? 'Keine Zutat passt zu deiner Suche. Ändere den Suchbegriff oder die Filter.'
                        : 'Sobald Rezepte Zutaten enthalten, erscheinen sie hier.'
                "
                :heading-level="3"
            />

            <IngredientGrid v-else :ingredients="ingredientCards" />
        </section>
    </AppLayout>
</template>

<script setup lang="ts">
import IngredientGrid from '@/components/ingredients/IngredientGrid.vue';
import AppLayout from '@/components/layout/AppLayout.vue';
import IngredientSearchArea from '@/components/recipes/IngredientSearchArea.vue';
import BlobBackdrop from '@/components/ui/BlobBackdrop.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { Carrot, SearchX } from 'lucide-vue-next';
import { computed, ref } from 'vue';

// Ingredient-Struktur: das Backend liefert id und name; category ist optional
interface Ingredient {
    id: number;
    name: string;
    category?: string[] | null;
}

// Props von Inertia (aus dem Controller)
// -> ingredients ist OPTIONAL typisiert, damit kein Runtime-Fehler entsteht,
//    falls das Backend es (noch) nicht liefert.
const props = defineProps<{
    ingredients?: Ingredient[];
}>();

// Alle Zutaten aus den Props, mit Fallback auf leeres Array
const allIngredients = computed<Ingredient[]>(() => props.ingredients ?? []);

// Ergebnisliste – initial alle Zutaten aus der DB (oder [] wenn noch nichts kommt)
const ingredientCards = ref<Ingredient[]>([...allIngredients.value]);

// Wird von IngredientSearchArea aufgerufen, wenn gefilterte Ergebnisse
// (z. B. nach Suchtext / Tag) übergeben werden sollen
const handleResultsUpdate = (results: Ingredient[]) => {
    ingredientCards.value = results;
};
</script>

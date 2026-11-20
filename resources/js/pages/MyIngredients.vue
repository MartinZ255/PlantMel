<template>
    <AppLayout page-title="Meine Zutaten" bleed>
        <section class="relative overflow-hidden">
            <BlobBackdrop tone="light" />
            <LeafOrnament
                variant="eucalyptus"
                class="pointer-events-none absolute top-4 right-[5%] hidden h-44 animate-sway text-tint-strong [--float-rotate:-14deg] md:block lg:h-56"
            />
            <div
                class="relative mx-auto flex w-full max-w-(--content-max) flex-col gap-8 px-4 pt-8 pb-10 sm:px-6 lg:px-8 lg:pt-12 lg:pb-14"
            >
                <PageHeader
                    title="Meine Zutaten"
                    eyebrow="Dein Vorrat"
                    description="Hier speicherst du die Zutaten, die du zu Hause hast."
                    size="xl"
                >
                    <template #title>
                        Meine
                        <span class="accent-word text-primary">Zutaten</span>
                    </template>
                </PageHeader>

                <!-- Such- / Filterbereich nur, wenn es gespeicherte Zutaten gibt -->
                <IngredientSearchArea
                    v-if="allIngredients.length"
                    :ingredients="allIngredients"
                    @update:results="handleResultsUpdate"
                />
            </div>
        </section>

        <section
            class="mx-auto w-full max-w-(--content-max) px-4 pt-2 pb-16 sm:px-6 lg:px-8"
            aria-labelledby="my-ingredients-title"
        >
            <h2 id="my-ingredients-title" class="sr-only">
                Gespeicherte Zutaten
            </h2>

            <!-- Ehrlicher Leerzustand: Speichern ist noch nicht gebaut (F-03, Entscheidung E3) -->
            <div v-if="!allIngredients.length" class="relative">
                <!-- kleiner „Vorratskorb“ aus Blobs und Blättern über dem Leerzustand -->
                <div
                    class="pointer-events-none relative z-10 mx-auto -mb-10 flex h-24 w-56 items-end justify-center"
                    aria-hidden="true"
                >
                    <span
                        class="absolute bottom-2 left-6 size-14 rounded-[58%_42%_52%_48%/46%_54%_46%_54%] bg-berry shadow-berry"
                    ></span>
                    <span
                        class="absolute right-8 bottom-4 size-10 rounded-[42%_58%_61%_39%/52%_38%_62%_48%] bg-matcha shadow-card"
                    ></span>
                    <LeafOrnament
                        variant="sprig"
                        class="absolute bottom-0 left-1/2 h-24 -translate-x-1/2 rotate-[8deg] text-moss"
                    />
                </div>
                <EmptyState
                    :icon="ShoppingBasket"
                    title="Noch nichts im Vorrat"
                    description="Du hast noch keine Zutaten gespeichert. Diese Funktion wird gerade ausgebaut."
                    :heading-level="3"
                >
                    <template #actions>
                        <Button :href="route('IngredientSearch')">
                            Zur Zutatensuche
                        </Button>
                        <Button
                            :href="route('recipes.index')"
                            variant="secondary"
                        >
                            Rezepte entdecken
                        </Button>
                    </template>
                </EmptyState>
            </div>

            <template v-else>
                <EmptyState
                    v-if="!ingredientCards.length"
                    :icon="SearchX"
                    title="Keine Zutaten gefunden"
                    description="Keine gespeicherte Zutat passt zu deiner Suche."
                    :heading-level="3"
                />
                <IngredientGrid v-else :ingredients="ingredientCards" />
            </template>
        </section>
    </AppLayout>
</template>

<script setup lang="ts">
import IngredientGrid from '@/components/ingredients/IngredientGrid.vue';
import AppLayout from '@/components/layout/AppLayout.vue';
import IngredientSearchArea from '@/components/recipes/IngredientSearchArea.vue';
import BlobBackdrop from '@/components/ui/BlobBackdrop.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { SearchX, ShoppingBasket } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

interface Ingredient {
    id: number;
    name: string;
    category?: string[] | null;
}

// Die Route liefert `ingredients` (derzeit leer); fehlt die Prop, gilt ebenfalls [].
const props = defineProps<{
    ingredients?: Ingredient[];
}>();

const allIngredients = computed<Ingredient[]>(() => props.ingredients ?? []);

const ingredientCards = ref<Ingredient[]>([...allIngredients.value]);

const handleResultsUpdate = (results: Ingredient[]) => {
    ingredientCards.value = results;
};
</script>

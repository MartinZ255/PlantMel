<template>
    <AppLayout :page-title="`${recipe.name} bearbeiten`" bleed>
        <!-- Kopf: Waldgrün mit Körnung; rechts das aktuelle Bild in Blob-Maske -->
        <header
            class="relative overflow-hidden bg-forest grain-light on-forest"
        >
            <BlobBackdrop tone="forest" />
            <LeafOrnament
                variant="eucalyptus"
                class="pointer-events-none absolute -top-8 right-[34%] hidden h-40 animate-sway text-forest-soft [--float-rotate:-14deg] lg:block"
            />
            <LeafOrnament
                variant="leaf"
                class="pointer-events-none absolute -bottom-6 -left-8 h-36 rotate-[40deg] text-forest-soft"
            />

            <div
                class="relative mx-auto grid w-full max-w-(--content-max) items-center gap-8 px-4 pt-7 pb-6 sm:px-6 md:grid-cols-[minmax(0,1fr)_14rem] lg:grid-cols-[minmax(0,1fr)_18rem] lg:gap-14 lg:px-8 lg:pt-12 lg:pb-8"
            >
                <div class="min-w-0">
                    <Link
                        :href="route('recipes.show', recipe.id)"
                        class="mb-5 inline-flex min-h-11 items-center gap-1.5 rounded-pebble px-2 text-sm font-semibold text-on-forest-muted hover:bg-forest-soft hover:text-on-forest focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                    >
                        <ArrowLeft class="size-4" aria-hidden="true" />
                        Zurück zum Rezept
                    </Link>
                    <p
                        class="mb-3 inline-flex items-center gap-1.5 text-sm font-semibold tracking-[0.14em] text-matcha uppercase"
                    >
                        <LeafOrnament variant="sprout" class="size-4" />
                        Aus dem Rezeptbuch
                    </p>
                    <h1
                        class="font-display-wonk text-[clamp(2.4rem,8vw,4.5rem)] leading-[1.02] font-semibold text-balance text-on-forest"
                    >
                        Rezept
                        <span class="accent-word text-matcha">bearbeiten</span>
                    </h1>
                    <p
                        class="mt-4 max-w-2xl font-display text-2xl font-medium text-wrap-anywhere text-on-forest sm:text-3xl"
                    >
                        „{{ recipe.name }}“
                    </p>
                    <p class="mt-3 max-w-xl text-lg text-on-forest-muted">
                        Änderungen werden nach dem Speichern übernommen.
                    </p>
                </div>

                <div
                    class="relative mx-auto hidden w-full max-w-[18rem] md:block"
                    aria-hidden="true"
                >
                    <span
                        class="pointer-events-none absolute inset-[7%_-5%_-5%_9%] animate-morph bg-matcha/90"
                    ></span>
                    <BlobImage
                        :src="recipe.image ?? null"
                        alt=""
                        :shape="5"
                        ratio="1/1"
                        eager
                        class="relative shadow-float ring-[6px] ring-forest-deep/40"
                    />
                    <LeafOrnament
                        variant="sprig"
                        class="pointer-events-none absolute -bottom-5 -left-6 h-28 animate-float text-matcha [--float-rotate:-30deg]"
                    />
                </div>
            </div>
            <WaveDivider :variant="1" class="text-canvas" />
        </header>

        <div class="mx-auto w-full max-w-6xl px-4 pt-6 pb-16 sm:px-6 lg:px-8">
            <RecipeForm
                mode="edit"
                :rating-dimensions="ratingDimensions"
                :categories="categories"
                :ingredients="ingredients"
                :recipe="recipe"
                :submit-route="route('recipes.update', recipe.id)"
                submit-method="post"
                :cancel-href="route('recipes.show', recipe.id)"
                :current-image="recipe.image ?? null"
            />
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import RecipeForm from '@/components/recipes/RecipeForm.vue';
import BlobBackdrop from '@/components/ui/BlobBackdrop.vue';
import BlobImage from '@/components/ui/BlobImage.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import WaveDivider from '@/components/ui/WaveDivider.vue';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { route } from 'ziggy-js';

interface RatingDimension {
    id: number;
    name: string;
    description?: string | null;
}

interface Category {
    id: number;
    name: string;
}

interface IngredientOption {
    id: number;
    name: string;
}

interface IngredientRow {
    amount: string;
    name: string;
    // F-13: vom Server gelieferte Alternativen der Zeile
    alternativeIngredientIds: number[];
}

// F-15: kein Feld „Quelle“ mehr; ADR-001: mehrzeilige Schritte als EIN String
interface RecipeFormInput {
    id: number;
    name: string;
    description: string | null;
    duration_minutes: number | null;
    servings: number | null;
    tags: string[];
    ingredients: IngredientRow[];
    steps: string[];
    notes: string | null;
    ratings: Record<number, number | null>;
    // F-56: aktuelles Bild für die Vorschau – liefert `RecipeController::edit`
    // derzeit noch nicht; ohne den Wert entfällt die Vorschau einfach.
    image?: string | null;
}

defineProps<{
    ratingDimensions: RatingDimension[];
    categories: Category[];
    ingredients: IngredientOption[];
    recipe: RecipeFormInput;
    isHost: boolean;
}>();
</script>

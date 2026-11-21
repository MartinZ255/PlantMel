<template>
    <AppLayout page-title="Rezept erstellen" bleed>
        <!-- Kopf: Waldgrün mit Körnung, große Headline, TikTok-Import als Papierkarte -->
        <header
            class="relative overflow-hidden bg-forest grain-light on-forest"
        >
            <BlobBackdrop tone="forest" />
            <LeafOrnament
                variant="sprig"
                class="pointer-events-none absolute -top-6 left-[38%] hidden h-40 animate-sway text-forest-soft [--float-rotate:18deg] lg:block"
            />
            <LeafOrnament
                variant="leaf"
                class="pointer-events-none absolute -bottom-6 -left-8 h-36 rotate-[40deg] text-forest-soft"
            />

            <div
                class="relative mx-auto grid w-full max-w-(--content-max) items-center gap-10 px-4 pt-7 pb-6 sm:px-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] lg:gap-14 lg:px-8 lg:pt-12 lg:pb-8"
            >
                <div class="min-w-0">
                    <Link
                        :href="route('recipes.index')"
                        class="mb-5 inline-flex min-h-11 items-center gap-1.5 rounded-pebble px-2 text-sm font-semibold text-on-forest-muted hover:bg-forest-soft hover:text-on-forest focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                    >
                        <ArrowLeft class="size-4" aria-hidden="true" />
                        Zur Übersicht
                    </Link>
                    <p
                        class="mb-3 inline-flex items-center gap-1.5 text-sm font-semibold tracking-[0.14em] text-matcha uppercase"
                    >
                        <LeafOrnament variant="sprout" class="size-4" />
                        Neuer Eintrag im Rezeptbuch
                    </p>
                    <h1
                        class="font-display-wonk text-[clamp(2.5rem,9vw,5rem)] leading-[1.02] font-semibold text-balance text-on-forest"
                    >
                        Neues
                        <span class="accent-word text-matcha">Rezept</span>
                    </h1>
                    <p
                        class="mt-4 max-w-xl text-lg text-on-forest-muted sm:text-xl"
                    >
                        Trag dein Rezept Kapitel für Kapitel ein – oder lass es
                        dir aus einem TikTok-Video vorbereiten und prüfe es vor
                        dem Speichern.
                    </p>
                    <p class="mt-5">
                        <Badge tone="matcha"
                            >Entwurf · noch nicht gespeichert</Badge
                        >
                    </p>
                </div>

                <!-- TikTok-Import -->
                <TikTokImportCard
                    store-route="recipeImports.store"
                    status-route="recipeImports.show"
                    create-route="recipes.create"
                    :has-user-input="() => recipeForm?.hasUserInput() ?? false"
                    :imported="!!importedRecipe"
                />
            </div>
            <WaveDivider :variant="2" class="text-canvas" />
        </header>

        <div class="mx-auto w-full max-w-6xl px-4 pt-6 pb-16 sm:px-6 lg:px-8">
            <RecipeForm
                ref="recipeForm"
                mode="create"
                :rating-dimensions="ratingDimensions"
                :categories="categories"
                :ingredients="ingredients"
                :recipe="importedRecipe ?? null"
                :submit-route="route('recipes.store')"
                submit-method="post"
            />
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import RecipeForm from '@/components/recipes/RecipeForm.vue';
import TikTokImportCard from '@/components/recipes/TikTokImportCard.vue';
import Badge from '@/components/ui/Badge.vue';
import BlobBackdrop from '@/components/ui/BlobBackdrop.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import WaveDivider from '@/components/ui/WaveDivider.vue';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { ref } from 'vue';
import { route } from 'ziggy-js';

interface ImportedRecipe {
    name?: string;
    description?: string;
    duration_minutes?: number | null;
    servings?: number | null;
    // F-15: `source` wird nicht mehr übernommen; ältere Import-Ergebnisse
    // dürfen das Feld noch enthalten (RecipeForm ignoriert es)
    source?: string | null;
    tags?: string[];
    ingredients?: { amount: string; name: string }[];
    steps?: string[];
    notes?: string;
}

defineProps<{
    ratingDimensions: {
        id: number;
        name: string;
        description?: string | null;
    }[];
    categories: {
        id: number;
        name: string;
    }[];
    ingredients: {
        id: number;
        name: string;
    }[];
    importedRecipe?: ImportedRecipe | null;
}>();

// TikTok-Import: components/recipes/TikTokImportCard.vue (E18 herausgelöst)
const recipeForm = ref<{ hasUserInput: () => boolean } | null>(null);
</script>

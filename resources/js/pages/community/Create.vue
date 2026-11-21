<template>
    <AppLayout page-title="Community-Rezept einreichen" bleed>
        <header
            class="relative overflow-hidden bg-forest grain-light on-forest"
        >
            <BlobBackdrop tone="forest" />
            <LeafOrnament
                variant="eucalyptus"
                class="pointer-events-none absolute -top-6 left-[36%] hidden h-40 animate-sway text-forest-soft [--float-rotate:-18deg] lg:block"
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
                        :href="route('community.index')"
                        class="mb-5 inline-flex min-h-11 items-center gap-1.5 rounded-pebble px-2 text-sm font-semibold text-on-forest-muted hover:bg-forest-soft hover:text-on-forest focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                    >
                        <ArrowLeft class="size-4" aria-hidden="true" />
                        Zu den Community-Rezepten
                    </Link>
                    <p
                        class="mb-3 inline-flex items-center gap-1.5 text-sm font-semibold tracking-[0.14em] text-matcha uppercase"
                    >
                        <LeafOrnament variant="sprout" class="size-4" />
                        Für die Community
                    </p>
                    <h1
                        class="font-display-wonk text-[clamp(2.5rem,9vw,5rem)] leading-[1.02] font-semibold text-balance text-on-forest"
                    >
                        Rezept
                        <span class="accent-word text-matcha">teilen</span>
                    </h1>
                    <p
                        class="mt-4 max-w-xl text-lg text-on-forest-muted sm:text-xl"
                    >
                        Trag dein Rezept ein und reiche es zur Prüfung ein.
                        Öffentlich erscheint es erst, wenn das PlantMel-Team es
                        freigegeben hat.
                    </p>
                    <p class="mt-4 max-w-xl text-sm text-on-forest-muted">
                        Lade nur eigene Texte und Bilder hoch oder solche, die
                        du verwenden darfst.
                    </p>
                </div>

                <TikTokImportCard
                    store-route="communityImports.store"
                    status-route="communityImports.show"
                    create-route="community.create"
                    :has-user-input="() => recipeForm?.hasUserInput() ?? false"
                    :imported="!!importedRecipe"
                    :disabled="!importAvailable"
                >
                    <template v-if="!importAvailable" #notice>
                        <p
                            class="mt-3 rounded-[1.1rem_0.9rem_1.2rem_1rem] bg-warning-surface px-3.5 py-2.5 text-sm text-warning"
                            data-import-disabled
                        >
                            Der Import braucht deinen eigenen
                            Gemini-API-Schlüssel.
                            <Link
                                :href="route('settings.ai.edit')"
                                class="font-semibold underline underline-offset-4"
                                >Schlüssel unter „KI-Import“ hinterlegen</Link
                            >
                        </p>
                    </template>
                </TikTokImportCard>
            </div>
            <WaveDivider :variant="3" class="text-canvas" />
        </header>

        <div class="mx-auto w-full max-w-6xl px-4 pt-6 pb-16 sm:px-6 lg:px-8">
            <RecipeForm
                ref="recipeForm"
                mode="create"
                variant="community"
                :rating-dimensions="[]"
                :categories="categories"
                :ingredients="ingredients"
                :recipe="importedRecipe ?? null"
                :submit-route="route('community.store')"
                submit-method="post"
            />
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import RecipeForm from '@/components/recipes/RecipeForm.vue';
import TikTokImportCard from '@/components/recipes/TikTokImportCard.vue';
import BlobBackdrop from '@/components/ui/BlobBackdrop.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import WaveDivider from '@/components/ui/WaveDivider.vue';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { ref } from 'vue';
import { route } from 'ziggy-js';

/**
 * E18: Community-Rezept einreichen. Gleiches Formular wie der Host
 * (`RecipeForm`, Variante `community`). Der TikTok-Import läuft nur mit dem
 * eigenen Gemini-Schlüssel; ohne ist er deaktiviert (Hinweis + Link).
 */
interface ImportedRecipe {
    name?: string;
    description?: string;
    duration_minutes?: number | null;
    servings?: number | null;
    tags?: string[];
    ingredients?: { amount: string; name: string }[];
    steps?: string[];
    notes?: string;
}

defineProps<{
    categories: { id: number; name: string }[];
    ingredients: { id: number; name: string }[];
    importedRecipe?: ImportedRecipe | null;
    importAvailable: boolean;
}>();

const recipeForm = ref<{ hasUserInput: () => boolean } | null>(null);
</script>

<template>
    <AppLayout :page-title="`${recipe.name} bearbeiten`" bleed>
        <header
            class="relative overflow-hidden bg-forest grain-light on-forest"
        >
            <BlobBackdrop tone="forest" />
            <LeafOrnament
                variant="leaf"
                class="pointer-events-none absolute -bottom-6 -left-8 h-36 rotate-[40deg] text-forest-soft"
            />
            <div
                class="relative mx-auto grid w-full max-w-(--content-max) items-center gap-8 px-4 pt-7 pb-6 sm:px-6 md:grid-cols-[minmax(0,1fr)_14rem] lg:grid-cols-[minmax(0,1fr)_18rem] lg:gap-14 lg:px-8 lg:pt-12 lg:pb-8"
            >
                <div class="min-w-0">
                    <Link
                        :href="route('community.show', recipe.id)"
                        class="mb-5 inline-flex min-h-11 items-center gap-1.5 rounded-pebble px-2 text-sm font-semibold text-on-forest-muted hover:bg-forest-soft hover:text-on-forest focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                    >
                        <ArrowLeft class="size-4" aria-hidden="true" />
                        Zurück zum Rezept
                    </Link>
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
                    <p
                        v-if="!isAuthor && recipe.author"
                        class="mt-2 text-on-forest-muted"
                    >
                        von {{ recipe.author }} · du bearbeitest als Host, der
                        Status bleibt unverändert.
                    </p>

                    <!-- E18: klare Ankündigung der erneuten Prüfung -->
                    <div
                        v-if="requiresReview && recipe.status === 'approved'"
                        class="mt-5 max-w-xl rounded-[1.4rem_1.1rem_1.5rem_1.2rem] bg-matcha px-4 py-3 text-forest"
                        role="note"
                        data-review-warning
                    >
                        <p class="font-semibold">
                            Dein Rezept ist gerade öffentlich.
                        </p>
                        <p class="mt-0.5 text-sm">
                            Nach dem Speichern wird es erneut geprüft und ist
                            bis zur Freigabe nicht mehr öffentlich sichtbar.
                        </p>
                    </div>
                    <div
                        v-else-if="
                            recipe.status === 'rejected' &&
                            recipe.rejectionReason
                        "
                        class="mt-5 max-w-xl rounded-[1.4rem_1.1rem_1.5rem_1.2rem] bg-danger-surface px-4 py-3 text-ink"
                        data-rejection-reason
                    >
                        <p class="font-semibold text-danger">
                            Grund der Ablehnung
                        </p>
                        <p
                            class="mt-0.5 text-wrap-anywhere whitespace-pre-line"
                        >
                            {{ recipe.rejectionReason }}
                        </p>
                    </div>
                </div>

                <div
                    class="relative mx-auto hidden w-full max-w-[18rem] md:block"
                    aria-hidden="true"
                >
                    <span
                        class="pointer-events-none absolute inset-[7%_-5%_-5%_9%] animate-morph bg-berry-surface/90"
                    ></span>
                    <BlobImage
                        :src="recipe.image ?? null"
                        alt=""
                        :shape="4"
                        ratio="1/1"
                        eager
                        class="relative shadow-float ring-[6px] ring-forest-deep/40"
                    />
                </div>
            </div>
            <WaveDivider :variant="1" class="text-canvas" />
        </header>

        <div class="mx-auto w-full max-w-6xl px-4 pt-6 pb-16 sm:px-6 lg:px-8">
            <RecipeForm
                mode="edit"
                variant="community"
                :rating-dimensions="ratingDimensions"
                :categories="categories"
                :ingredients="ingredients"
                :recipe="recipe"
                :submit-route="route('community.update', recipe.id)"
                submit-method="post"
                :cancel-href="route('community.show', recipe.id)"
                :current-image="recipe.image ?? null"
                :review-notice="reviewNotice"
                :hide-draft="!isAuthor"
                :hide-rating="!isAuthor"
                :submit-label="isAuthor ? null : 'Änderungen speichern'"
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
import { computed } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps<{
    ratingDimensions: {
        id: number;
        name: string;
        description?: string | null;
    }[];
    categories: { id: number; name: string }[];
    ingredients: { id: number; name: string }[];
    recipe: {
        id: number;
        name: string;
        description: string | null;
        image?: string | null;
        duration_minutes: number | null;
        servings: number | null;
        tags: string[];
        ingredients: {
            amount: string;
            name: string;
            alternativeIngredientIds: number[];
        }[];
        steps: string[];
        notes: string | null;
        ratings: Record<number, number | null>;
        status: 'draft' | 'pending' | 'approved' | 'rejected';
        rejectionReason: string | null;
        author: string | null;
    };
    isAuthor: boolean;
    requiresReview: boolean;
}>();

const reviewNotice = computed(() =>
    !props.isAuthor
        ? 'Du speicherst als Host; der Status des Rezepts bleibt gleich.'
        : props.recipe.status === 'approved'
          ? 'Nach dem Einreichen wird dein Rezept erneut geprüft und ist bis zur Freigabe nicht öffentlich.'
          : null,
);
</script>

<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import IngredientChecklist from '@/components/recipes/IngredientChecklist.vue';
import Badge from '@/components/ui/Badge.vue';
import BlobBackdrop from '@/components/ui/BlobBackdrop.vue';
import BlobImage from '@/components/ui/BlobImage.vue';
import Button from '@/components/ui/Button.vue';
import Dialog from '@/components/ui/Dialog.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import Reveal from '@/components/ui/Reveal.vue';
import StarRating from '@/components/ui/StarRating.vue';
import WaveDivider from '@/components/ui/WaveDivider.vue';
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
        // E14: einzige Bewertung ist Geschmack (Durchschnitt 1–5 oder null)
        rating: number | null;
        ratingCount: number;
        // E14: bleibt im Vertrag, ist aber immer leer (keine Kategorien mehr)
        ratingDimensions?: unknown[];
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

/** E14: nur noch eine Geschmacks-Bewertung (1–5 Sterne) */
const hasTasteRating = computed(
    () =>
        recipe.value.rating != null &&
        recipe.value.rating > 0 &&
        recipe.value.ratingCount > 0,
);

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
/** große Ziffer der Gesamtbewertung (rein grafisch, Text kommt aus StarRating) */
const formatRating = (v: number | null) =>
    v == null
        ? ''
        : v.toLocaleString('de-DE', {
              minimumFractionDigits: Number.isInteger(v) ? 0 : 1,
              maximumFractionDigits: 1,
          });

/** Schritt-Ziffern in wechselnden Blob-Formen */
const stepShape = (index: number) =>
    ['blob-1', 'blob-4', 'blob-2', 'blob-5', 'blob-3', 'blob-6'][index % 6];

const servingsText = computed(() =>
    recipe.value.servings !== null
        ? String(recipe.value.servings)
        : 'Keine Angabe',
);
</script>

<template>
    <AppLayout :page-title="recipe.name" bleed>
        <article>
            <!-- ============================================================
                 Kopf: Waldgrün mit Körnung, großer Titel, Bild in Blob-Maske
                 ============================================================ -->
            <header
                class="relative overflow-hidden bg-forest grain-light on-forest"
            >
                <BlobBackdrop tone="forest" />
                <LeafOrnament
                    variant="sprig"
                    class="pointer-events-none absolute -top-4 left-[46%] hidden h-40 animate-sway text-forest-soft [--float-rotate:20deg] lg:block"
                />
                <LeafOrnament
                    variant="leaf"
                    class="pointer-events-none absolute -bottom-8 -left-6 h-36 rotate-[38deg] text-forest-soft"
                />

                <div
                    class="relative mx-auto grid w-full max-w-(--content-max) items-center gap-10 px-4 pt-6 pb-4 sm:px-6 lg:grid-cols-[1.08fr_1fr] lg:gap-14 lg:px-8 lg:pt-10 lg:pb-6"
                >
                    <div class="min-w-0">
                        <Link
                            :href="route('recipes.index')"
                            class="mb-5 inline-flex min-h-10 items-center gap-1.5 rounded-pebble px-2 text-sm font-semibold text-on-forest-muted hover:bg-forest-soft hover:text-on-forest focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                        >
                            <ArrowLeft class="size-4" aria-hidden="true" />
                            Zur Übersicht
                        </Link>

                        <ul
                            v-if="recipe.categories?.length"
                            class="mb-4 flex flex-wrap gap-2"
                            aria-label="Kategorien"
                        >
                            <li
                                v-for="cat in recipe.categories"
                                :key="cat"
                                class="max-w-full"
                            >
                                <Badge tone="matcha">{{ cat }}</Badge>
                            </li>
                        </ul>

                        <h1
                            class="font-display-wonk text-[clamp(2.25rem,8vw,4.5rem)] leading-[1.02] font-semibold text-balance text-wrap-anywhere text-on-forest"
                        >
                            {{ recipe.name }}
                        </h1>

                        <p
                            v-if="recipe.description"
                            class="mt-4 max-w-2xl text-lg text-wrap-anywhere text-on-forest-muted sm:text-xl"
                        >
                            {{ recipe.description }}
                        </p>

                        <!-- Eckdaten als schwebende Kiesel -->
                        <dl
                            class="mt-7 grid grid-cols-2 gap-3 sm:grid-cols-3"
                            aria-label="Eckdaten"
                        >
                            <div
                                class="rounded-[1.4rem_1.1rem_1.5rem_1.2rem] bg-forest-deep/55 px-4 py-3.5 ring-1 ring-forest-soft backdrop-blur-sm"
                            >
                                <dt
                                    class="flex items-center gap-1.5 text-sm text-on-forest-muted"
                                >
                                    <Clock class="size-4" aria-hidden="true" />
                                    Zubereitung
                                </dt>
                                <dd
                                    class="mt-1 font-display text-2xl font-semibold text-on-forest"
                                >
                                    {{ durationText }}
                                </dd>
                            </div>
                            <div
                                class="rounded-[1.1rem_1.5rem_1.2rem_1.4rem] bg-forest-deep/55 px-4 py-3.5 ring-1 ring-forest-soft backdrop-blur-sm"
                            >
                                <dt
                                    class="flex items-center gap-1.5 text-sm text-on-forest-muted"
                                >
                                    <Users class="size-4" aria-hidden="true" />
                                    Portionen
                                </dt>
                                <dd
                                    class="mt-1 font-display text-2xl font-semibold text-on-forest"
                                >
                                    {{ servingsText }}
                                </dd>
                            </div>
                            <div
                                class="col-span-2 rounded-[1.5rem_1.2rem_1.4rem_1.1rem] bg-forest-deep/55 px-4 py-3.5 ring-1 ring-forest-soft backdrop-blur-sm sm:col-span-1"
                            >
                                <dt class="text-sm text-on-forest-muted">
                                    Geschmack
                                </dt>
                                <dd class="mt-1.5">
                                    <StarRating
                                        v-if="hasTasteRating"
                                        tone="on-forest"
                                        :value="recipe.rating"
                                    />
                                    <span
                                        v-else
                                        class="text-sm font-medium text-on-forest"
                                        >Noch nicht bewertet</span
                                    >
                                </dd>
                            </div>
                        </dl>

                        <!-- Aktionen: umbrechend, nie über dem Titel (F-31) -->
                        <div class="mt-7 flex flex-wrap gap-2.5">
                            <Button
                                variant="lime"
                                aria-haspopup="dialog"
                                @click="isCollectionModalOpen = true"
                            >
                                <BookmarkPlus
                                    class="size-5"
                                    aria-hidden="true"
                                />
                                Zur Sammlung hinzufügen
                            </Button>
                            <template v-if="isHost">
                                <Button
                                    variant="outline-light"
                                    :href="route('recipes.edit', recipe.id)"
                                >
                                    <Pencil class="size-4" aria-hidden="true" />
                                    Rezept bearbeiten
                                </Button>
                                <Button
                                    variant="ghost"
                                    class="text-danger-on-forest hover:bg-forest-soft active:bg-forest-deep"
                                    aria-haspopup="dialog"
                                    @click="isDeleteOpen = true"
                                >
                                    <Trash2 class="size-4" aria-hidden="true" />
                                    Rezept löschen
                                </Button>
                            </template>
                        </div>
                    </div>

                    <!-- Bild in organischer Maske; ohne Bild eine Komposition -->
                    <div class="relative mx-auto w-full max-w-md lg:max-w-none">
                        <span
                            class="pointer-events-none absolute inset-[6%_-3%_-4%_8%] animate-morph bg-matcha/90"
                            aria-hidden="true"
                        ></span>
                        <span
                            class="pointer-events-none absolute -top-3 left-[6%] size-[16%] rounded-[58%_42%_52%_48%/46%_54%_46%_54%] bg-berry shadow-berry"
                            aria-hidden="true"
                        ></span>
                        <div class="group relative">
                            <BlobImage
                                v-if="recipe.image"
                                :src="recipe.image"
                                :alt="`Foto: ${recipe.name}`"
                                :shape="2"
                                ratio="1/1"
                                eager
                                sizes="(min-width: 1024px) 40vw, 90vw"
                                class="shadow-float ring-[6px] ring-forest-deep/40"
                            />
                            <div
                                v-else
                                class="relative blob-mask aspect-square w-full overflow-hidden bg-forest-soft shadow-float blob-2"
                                aria-hidden="true"
                            >
                                <span
                                    class="absolute top-[14%] left-[12%] size-[46%] rounded-full bg-forest-deep/60"
                                ></span>
                                <span
                                    class="absolute right-[12%] bottom-[16%] flex size-[38%] items-center justify-center rounded-full bg-canvas shadow-float ring-[10px] ring-tint-strong"
                                >
                                    <CookingPot
                                        class="size-[42%] text-primary"
                                    />
                                </span>
                                <LeafOrnament
                                    variant="leaf"
                                    class="absolute top-[12%] left-[16%] h-[44%] rotate-[-24deg] text-matcha"
                                />
                                <LeafOrnament
                                    variant="eucalyptus"
                                    class="absolute bottom-[4%] left-[30%] h-[42%] rotate-[52deg] text-moss"
                                />
                            </div>
                        </div>
                        <LeafOrnament
                            variant="sprig"
                            class="pointer-events-none absolute -bottom-6 -left-4 h-28 animate-float text-matcha [--float-rotate:-30deg] sm:h-36"
                        />
                    </div>
                </div>
                <WaveDivider :variant="3" class="text-canvas" />
            </header>

            <!-- ============================================================
                 Inhalt: Zutaten (Papierblatt, abhakbar) | Zubereitung
                 ============================================================ -->
            <div
                class="mx-auto w-full max-w-6xl px-4 pt-6 pb-16 sm:px-6 lg:px-8"
            >
                <div class="grid gap-12 lg:grid-cols-12 lg:gap-12">
                    <section
                        v-if="recipe.ingredients?.length"
                        aria-labelledby="recipe-ingredients-title"
                        class="lg:col-span-5"
                    >
                        <div
                            class="relative overflow-hidden rounded-[2.25rem_1.75rem_2.5rem_2rem] bg-surface p-5 shadow-float sm:p-7 lg:sticky lg:top-[calc(var(--header-height)+1.5rem)]"
                        >
                            <LeafOrnament
                                variant="eucalyptus"
                                class="pointer-events-none absolute -top-6 -right-4 h-32 rotate-[32deg] text-tint"
                            />
                            <h2
                                id="recipe-ingredients-title"
                                class="relative font-display-wonk text-3xl font-semibold text-ink"
                            >
                                Zutaten
                            </h2>
                            <p
                                v-if="recipe.servings"
                                class="relative mt-1 text-sm text-ink-muted"
                            >
                                für {{ recipe.servings }}
                                {{
                                    recipe.servings === 1
                                        ? 'Portion'
                                        : 'Portionen'
                                }}
                                · zum Abhaken beim Kochen
                            </p>
                            <IngredientChecklist
                                class="relative mt-4"
                                :ingredients="recipe.ingredients"
                                labelled-by="recipe-ingredients-title"
                            />
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
                            class="font-display-wonk text-3xl font-semibold text-ink sm:text-4xl"
                        >
                            Zubereitung
                        </h2>
                        <ol
                            class="mt-6 flex flex-col gap-7"
                            aria-labelledby="recipe-steps-title"
                        >
                            <Reveal
                                v-for="(step, index) in recipe.steps"
                                :key="index"
                                as="li"
                                class="relative flex items-start gap-4 sm:gap-6"
                            >
                                <!-- Ranke zwischen den Schritten -->
                                <span
                                    v-if="index < recipe.steps.length - 1"
                                    class="pointer-events-none absolute top-16 -bottom-7 left-7 border-l-2 border-dashed border-tint-strong sm:left-8"
                                    aria-hidden="true"
                                ></span>
                                <span
                                    class="relative blob-mask inline-flex size-14 shrink-0 items-center justify-center font-display-wonk text-3xl font-semibold shadow-card sm:size-16 sm:text-4xl"
                                    :class="[
                                        stepShape(index),
                                        index % 2 === 0
                                            ? 'bg-matcha text-forest'
                                            : 'bg-forest text-matcha',
                                    ]"
                                    aria-hidden="true"
                                    >{{ index + 1 }}</span
                                >
                                <!-- F-14/ADR-001: Zeilenumbrüche innerhalb eines Schritts sichtbar -->
                                <p
                                    class="min-w-0 flex-1 pt-2.5 text-lg leading-relaxed text-wrap-anywhere whitespace-pre-line text-ink sm:pt-3.5"
                                >
                                    {{ step }}
                                </p>
                            </Reveal>
                        </ol>
                    </section>
                </div>

                <!-- Notizen (nur, wenn vorhanden): Hinweis mit Blatt -->
                <Reveal
                    v-if="recipe.notes?.length"
                    as="section"
                    aria-labelledby="recipe-notes-title"
                    class="relative mt-16 overflow-hidden rounded-[2.5rem_2rem_2.75rem_1.75rem] bg-tint p-6 sm:p-9"
                >
                    <LeafOrnament
                        variant="leaf"
                        class="pointer-events-none absolute -right-6 -bottom-10 h-48 rotate-[-28deg] text-tint-strong sm:right-6"
                    />
                    <div class="relative flex items-center gap-3">
                        <span
                            class="blob-mask inline-flex size-11 shrink-0 items-center justify-center bg-forest text-matcha blob-4"
                            aria-hidden="true"
                        >
                            <LeafOrnament variant="sprout" class="size-6" />
                        </span>
                        <h2
                            id="recipe-notes-title"
                            class="font-display-wonk text-3xl font-semibold text-ink"
                        >
                            Notizen &amp; Tipps
                        </h2>
                    </div>
                    <ul
                        class="relative mt-5 flex max-w-3xl flex-col gap-3 text-lg text-ink"
                    >
                        <li
                            v-for="(note, index) in recipe.notes"
                            :key="index"
                            class="flex items-start gap-3 text-wrap-anywhere"
                        >
                            <LeafOrnament
                                variant="leaf"
                                class="mt-1.5 h-4 shrink-0 rotate-[30deg] text-moss"
                            />
                            <span class="min-w-0">{{ note }}</span>
                        </li>
                    </ul>
                </Reveal>

                <!-- Bewertung (E14): nur Geschmack, 1–5 Sterne, keine Kategorien -->
                <Reveal
                    as="section"
                    aria-labelledby="recipe-ratings-title"
                    class="mt-16"
                >
                    <h2
                        id="recipe-ratings-title"
                        class="font-display-wonk text-3xl font-semibold text-ink sm:text-4xl"
                    >
                        Bewertung
                    </h2>
                    <div
                        v-if="hasTasteRating"
                        class="relative mt-6 flex max-w-md flex-wrap items-center gap-x-6 gap-y-3 overflow-hidden rounded-[2.5rem_2rem_2.75rem_2.25rem] bg-berry-surface p-6"
                    >
                        <!-- große Ziffer rein grafisch; Text steht in StarRating -->
                        <span
                            class="font-display-wonk text-7xl leading-none font-semibold text-berry"
                            aria-hidden="true"
                            >{{ formatRating(recipe.rating) }}</span
                        >
                        <div class="relative flex min-w-0 flex-col gap-1.5">
                            <p
                                class="font-display text-xl font-semibold text-ink"
                            >
                                Geschmack
                            </p>
                            <StarRating :value="recipe.rating" />
                        </div>
                    </div>
                    <p v-else class="mt-3 text-lg text-ink-muted">
                        Noch nicht bewertet
                    </p>
                </Reveal>
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

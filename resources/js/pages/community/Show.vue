<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import IngredientChecklist from '@/components/recipes/IngredientChecklist.vue';
import Badge from '@/components/ui/Badge.vue';
import BlobBackdrop from '@/components/ui/BlobBackdrop.vue';
import BlobImage from '@/components/ui/BlobImage.vue';
import Button from '@/components/ui/Button.vue';
import Dialog from '@/components/ui/Dialog.vue';
import Field from '@/components/ui/Field.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import Reveal from '@/components/ui/Reveal.vue';
import StarRating from '@/components/ui/StarRating.vue';
import Textarea from '@/components/ui/Textarea.vue';
import TextInput from '@/components/ui/TextInput.vue';
import WaveDivider from '@/components/ui/WaveDivider.vue';
import AddToCollectionModal from '@/components/widgets/AddToCollectionModal.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BookmarkPlus,
    Clock,
    CookingPot,
    Flag,
    Pencil,
    ShieldCheck,
    Trash2,
    Users,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';

/**
 * E18: Detailseite eines Community-Rezepts. Gleicher Aufbau wie die
 * Host-Rezeptseite, zusätzlich: Autor („von …“), Badge „Community“,
 * Status für Autor und Host, Bewertung durch andere Nutzer, Meldeweg.
 */
type Status = 'draft' | 'pending' | 'approved' | 'rejected';

const props = defineProps<{
    recipe: {
        id: number;
        name: string;
        description: string | null;
        image: string | null;
        duration_minutes: number | null;
        servings: number | null;
        categories: string[];
        rating: number | null;
        ratingCount: number;
        myRating: number | null;
        ingredients: {
            amount: string | null;
            name: string | null;
            alternatives?: string[];
            proposed?: boolean;
        }[];
        steps: string[];
        notes: string[];
        author: string | null;
        isOwn: boolean;
        status: Status | null;
        rejectionReason: string | null;
        createdAt: string | null;
        updatedAt: string | null;
    };
    can: {
        edit: boolean;
        delete: boolean;
        moderate: boolean;
        rate: boolean;
        report: boolean;
        collect: boolean;
    };
    reportReasons: { key: string; label: string }[];
    collections: { id: number; name: string }[];
}>();

const recipe = computed(() => props.recipe);

const hasRating = computed(
    () => recipe.value.rating != null && recipe.value.ratingCount > 0,
);

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
const formatRating = (v: number | null) =>
    v == null
        ? ''
        : v.toLocaleString('de-DE', {
              minimumFractionDigits: Number.isInteger(v) ? 0 : 1,
              maximumFractionDigits: 1,
          });
const stepShape = (index: number) =>
    ['blob-1', 'blob-4', 'blob-2', 'blob-5', 'blob-3', 'blob-6'][index % 6];

const proposedNames = computed(() =>
    recipe.value.ingredients
        .filter((i) => i.proposed && i.name)
        .map((i) => i.name as string),
);

/** Status-Hinweis für Autor und Host (Gäste sehen nur freigegebene Rezepte). */
const statusInfo = computed(() => {
    switch (recipe.value.status) {
        case 'draft':
            return {
                tone: 'neutral' as const,
                label: 'Entwurf',
                text: 'Nur du siehst diesen Entwurf. Reiche ihn zur Prüfung ein, wenn er fertig ist.',
            };
        case 'pending':
            return {
                tone: 'warning' as const,
                label: 'Wird geprüft',
                text: 'Das Rezept wartet auf die Freigabe und ist noch nicht öffentlich sichtbar.',
            };
        case 'rejected':
            return {
                tone: 'danger' as const,
                label: 'Abgelehnt',
                text: 'Das Rezept wurde nicht freigegeben. Du kannst es überarbeiten und erneut einreichen.',
            };
        case 'approved':
            return {
                tone: 'success' as const,
                label: 'Freigegeben',
                text: recipe.value.isOwn
                    ? 'Dein Rezept ist öffentlich. Wenn du es bearbeitest, wird es erneut geprüft und ist bis zur Freigabe verborgen.'
                    : 'Das Rezept ist öffentlich.',
            };
        default:
            return null;
    }
});

// --- Sammlung ----------------------------------------------------------------
const isCollectionModalOpen = ref(false);

// --- Bewerten ----------------------------------------------------------------
const myRating = ref<number | null>(props.recipe.myRating);
watch(
    () => props.recipe.myRating,
    (v) => (myRating.value = v),
);
const rating = ref(false);
const saveRating = (value: number | null) => {
    rating.value = true;
    router.post(
        route('community.rate', recipe.value.id),
        { value },
        {
            preserveScroll: true,
            onFinish: () => (rating.value = false),
        },
    );
};

// --- Löschen -----------------------------------------------------------------
const isDeleteOpen = ref(false);
const deleting = ref(false);
const deleteRecipe = () => {
    if (deleting.value) return;
    deleting.value = true;
    router.delete(route('community.destroy', recipe.value.id), {
        onFinish: () => {
            deleting.value = false;
            isDeleteOpen.value = false;
        },
    });
};
const closeDelete = () => {
    if (!deleting.value) isDeleteOpen.value = false;
};

// --- Melden (Art. 16 DSA) ----------------------------------------------------
const isReportOpen = ref(false);
const reportForm = useForm({
    reason: '',
    message: '',
    contact_email: '',
    good_faith: false,
});
const openReport = () => {
    reportForm.clearErrors();
    isReportOpen.value = true;
};
const closeReport = () => {
    if (!reportForm.processing) isReportOpen.value = false;
};
const sendReport = () => {
    reportForm.post(route('community.report', recipe.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            isReportOpen.value = false;
            reportForm.reset();
        },
    });
};
</script>

<template>
    <AppLayout :page-title="recipe.name" bleed>
        <article>
            <header
                class="relative overflow-hidden bg-forest grain-light on-forest"
            >
                <BlobBackdrop tone="forest" />
                <LeafOrnament
                    variant="eucalyptus"
                    class="pointer-events-none absolute -top-6 left-[44%] hidden h-40 animate-sway text-forest-soft [--float-rotate:-16deg] lg:block"
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
                            :href="route('community.index')"
                            class="mb-5 inline-flex min-h-10 items-center gap-1.5 rounded-pebble px-2 text-sm font-semibold text-on-forest-muted hover:bg-forest-soft hover:text-on-forest focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                        >
                            <ArrowLeft class="size-4" aria-hidden="true" />
                            Zu den Community-Rezepten
                        </Link>

                        <ul
                            class="mb-4 flex flex-wrap gap-2"
                            aria-label="Kennzeichnung und Kategorien"
                        >
                            <li class="max-w-full">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-seed bg-berry px-2.5 py-0.5 text-xs font-semibold tracking-wide text-white"
                                    data-community-badge
                                >
                                    <LeafOrnament
                                        variant="sprout"
                                        class="size-3.5"
                                    />
                                    Community
                                </span>
                            </li>
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
                            v-if="recipe.author"
                            class="mt-3 font-display text-xl text-wrap-anywhere text-matcha italic"
                            data-recipe-author
                        >
                            von {{ recipe.author }}
                        </p>

                        <p
                            v-if="recipe.description"
                            class="mt-4 max-w-2xl text-lg text-wrap-anywhere text-on-forest-muted sm:text-xl"
                        >
                            {{ recipe.description }}
                        </p>

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
                                        v-if="hasRating"
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

                        <div class="mt-7 flex flex-wrap gap-2.5">
                            <Button
                                v-if="can.collect"
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
                            <Button
                                v-if="can.edit"
                                variant="outline-light"
                                :href="route('community.edit', recipe.id)"
                            >
                                <Pencil class="size-4" aria-hidden="true" />
                                Rezept bearbeiten
                            </Button>
                            <Button
                                v-if="can.moderate"
                                variant="outline-light"
                                :href="route('moderation.index')"
                            >
                                <ShieldCheck
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                Zur Moderation
                            </Button>
                            <Button
                                v-if="can.delete"
                                variant="ghost"
                                class="text-danger-on-forest hover:bg-forest-soft active:bg-forest-deep"
                                aria-haspopup="dialog"
                                @click="isDeleteOpen = true"
                            >
                                <Trash2 class="size-4" aria-hidden="true" />
                                Rezept löschen
                            </Button>
                        </div>
                    </div>

                    <div class="relative mx-auto w-full max-w-md lg:max-w-none">
                        <span
                            class="pointer-events-none absolute inset-[6%_-3%_-4%_8%] animate-morph bg-berry-surface/90"
                            aria-hidden="true"
                        ></span>
                        <span
                            class="pointer-events-none absolute -top-3 right-[8%] size-[15%] rounded-[58%_42%_52%_48%/46%_54%_46%_54%] bg-matcha shadow-float"
                            aria-hidden="true"
                        ></span>
                        <div class="group relative">
                            <BlobImage
                                v-if="recipe.image"
                                :src="recipe.image"
                                :alt="`Foto: ${recipe.name}`"
                                :shape="4"
                                ratio="1/1"
                                eager
                                sizes="(min-width: 1024px) 40vw, 90vw"
                                class="shadow-float ring-[6px] ring-forest-deep/40"
                            />
                            <div
                                v-else
                                class="relative blob-mask aspect-square w-full overflow-hidden bg-forest-soft shadow-float blob-4"
                                aria-hidden="true"
                            >
                                <span
                                    class="absolute right-[14%] bottom-[18%] flex size-[38%] items-center justify-center rounded-full bg-canvas shadow-float ring-[10px] ring-tint-strong"
                                >
                                    <CookingPot
                                        class="size-[42%] text-primary"
                                    />
                                </span>
                                <LeafOrnament
                                    variant="sprig"
                                    class="absolute top-[10%] left-[14%] h-[50%] rotate-[-18deg] text-matcha"
                                />
                            </div>
                        </div>
                    </div>
                </div>
                <WaveDivider :variant="1" class="text-canvas" />
            </header>

            <div
                class="mx-auto w-full max-w-6xl px-4 pt-6 pb-16 sm:px-6 lg:px-8"
            >
                <!-- Status (nur Autor und Host) -->
                <section
                    v-if="statusInfo"
                    class="relative mb-12 overflow-hidden rounded-[2rem_1.6rem_2.25rem_1.5rem] bg-surface p-5 shadow-card ring-1 ring-line/70 sm:p-7"
                    aria-labelledby="recipe-status-title"
                    data-recipe-status
                    :data-status="recipe.status"
                >
                    <div class="flex flex-wrap items-center gap-3">
                        <h2
                            id="recipe-status-title"
                            class="font-display text-2xl font-semibold text-ink"
                        >
                            Status
                        </h2>
                        <Badge :tone="statusInfo.tone">{{
                            statusInfo.label
                        }}</Badge>
                    </div>
                    <p class="mt-2 max-w-3xl text-ink">{{ statusInfo.text }}</p>
                    <div
                        v-if="
                            recipe.status === 'rejected' &&
                            recipe.rejectionReason
                        "
                        class="mt-4 rounded-[1.25rem_1rem_1.4rem_1.1rem] bg-danger-surface px-4 py-3 text-ink"
                        data-rejection-reason
                    >
                        <p class="text-sm font-semibold text-danger">
                            Begründung
                        </p>
                        <p class="mt-1 text-wrap-anywhere whitespace-pre-line">
                            {{ recipe.rejectionReason }}
                        </p>
                    </div>
                    <p
                        v-if="proposedNames.length"
                        class="mt-4 text-sm text-ink-muted"
                        data-proposed-list
                    >
                        Neu vorgeschlagene Zutaten (kommen erst mit der Freigabe
                        in den Katalog):
                        <span class="font-semibold text-ink">{{
                            proposedNames.join(', ')
                        }}</span>
                    </p>
                </section>

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
                                <p
                                    class="min-w-0 flex-1 pt-2.5 text-lg leading-relaxed text-wrap-anywhere whitespace-pre-line text-ink sm:pt-3.5"
                                >
                                    {{ step }}
                                </p>
                            </Reveal>
                        </ol>
                    </section>
                </div>

                <Reveal
                    v-if="recipe.notes?.length"
                    as="section"
                    aria-labelledby="recipe-notes-title"
                    class="relative mt-16 overflow-hidden rounded-[2.5rem_2rem_2.75rem_1.75rem] bg-tint p-6 sm:p-9"
                >
                    <h2
                        id="recipe-notes-title"
                        class="relative font-display-wonk text-3xl font-semibold text-ink"
                    >
                        Notizen &amp; Tipps
                    </h2>
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

                <!-- Bewertung: Durchschnitt und eigene Sterne -->
                <section aria-labelledby="recipe-ratings-title" class="mt-16">
                    <h2
                        id="recipe-ratings-title"
                        class="font-display-wonk text-3xl font-semibold text-ink sm:text-4xl"
                    >
                        Bewertung
                    </h2>
                    <div class="mt-6 grid gap-6 md:grid-cols-2">
                        <div
                            class="relative flex flex-wrap items-center gap-x-6 gap-y-3 overflow-hidden rounded-[2.5rem_2rem_2.75rem_2.25rem] bg-berry-surface p-6"
                        >
                            <template v-if="hasRating">
                                <span
                                    class="font-display-wonk text-7xl leading-none font-semibold text-berry"
                                    aria-hidden="true"
                                    >{{ formatRating(recipe.rating) }}</span
                                >
                                <div class="flex min-w-0 flex-col gap-1.5">
                                    <p
                                        class="font-display text-xl font-semibold text-ink"
                                    >
                                        Geschmack
                                    </p>
                                    <StarRating :value="recipe.rating" />
                                    <p class="text-sm text-ink-muted">
                                        {{
                                            recipe.ratingCount === 1
                                                ? '1 Bewertung'
                                                : `${recipe.ratingCount} Bewertungen`
                                        }}
                                    </p>
                                </div>
                            </template>
                            <p v-else class="text-lg text-ink">
                                Noch nicht bewertet
                            </p>
                        </div>

                        <div
                            v-if="can.rate"
                            class="rounded-[2rem_2.5rem_2.25rem_2rem] bg-surface p-6 shadow-card"
                            data-rate-recipe
                        >
                            <StarRating
                                v-model="myRating"
                                mode="input"
                                label="Deine Bewertung: Geschmack"
                            />
                            <div class="mt-4 flex flex-wrap gap-2">
                                <Button
                                    :disabled="myRating === null"
                                    :loading="rating"
                                    data-save-rating
                                    @click="saveRating(myRating)"
                                >
                                    Bewertung speichern
                                </Button>
                                <Button
                                    v-if="recipe.myRating !== null"
                                    variant="ghost"
                                    :disabled="rating"
                                    @click="saveRating(null)"
                                >
                                    Bewertung entfernen
                                </Button>
                            </div>
                        </div>
                        <p
                            v-else-if="recipe.isOwn"
                            class="self-center text-ink-muted"
                        >
                            Eigene Rezepte kannst du nicht bewerten.
                        </p>
                        <p
                            v-else-if="recipe.status === null && can.report"
                            class="self-center text-ink-muted"
                        >
                            <Link
                                :href="route('login')"
                                class="font-semibold text-primary underline underline-offset-4"
                                >Melde dich an</Link
                            >, um dieses Rezept zu bewerten.
                        </p>
                    </div>
                </section>

                <!-- Meldeweg -->
                <section
                    v-if="can.report"
                    class="mt-16 flex flex-wrap items-center justify-between gap-4 rounded-[2rem_1.5rem_2.25rem_1.75rem] border-[1.5px] border-dashed border-line-strong px-5 py-4"
                    aria-labelledby="recipe-report-title"
                >
                    <div class="min-w-0">
                        <h2
                            id="recipe-report-title"
                            class="font-display text-xl font-semibold text-ink"
                        >
                            Stimmt etwas nicht?
                        </h2>
                        <p class="text-sm text-ink-muted">
                            Melde rechtswidrige oder unpassende Inhalte, z. B.
                            fremde Bilder. Das geht auch ohne Konto.
                        </p>
                    </div>
                    <Button
                        variant="secondary"
                        aria-haspopup="dialog"
                        data-report-open
                        @click="openReport"
                    >
                        <Flag class="size-4" aria-hidden="true" />
                        Rezept melden
                    </Button>
                </section>
            </div>
        </article>

        <AddToCollectionModal
            v-if="can.collect"
            :open="isCollectionModalOpen"
            :collections="props.collections"
            :recipe-id="recipe.id"
            @close="isCollectionModalOpen = false"
        />

        <Dialog
            v-if="can.delete"
            :open="isDeleteOpen"
            title="Rezept löschen?"
            :description="`„${recipe.name}“ wird samt Bild endgültig gelöscht. Das kann nicht rückgängig gemacht werden.`"
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

        <Dialog
            v-if="can.report"
            :open="isReportOpen"
            title="Rezept melden"
            description="Deine Meldung geht an das PlantMel-Team. Wir prüfen sie und blenden das Rezept bei Bedarf aus."
            @close="closeReport"
        >
            <form
                id="report-form"
                novalidate
                class="flex flex-col gap-5"
                data-report-form
                @submit.prevent="sendReport"
            >
                <fieldset class="flex flex-col gap-2">
                    <legend class="mb-1 text-sm font-semibold text-ink">
                        Grund
                        <span class="text-danger" aria-hidden="true">*</span>
                    </legend>
                    <label
                        v-for="r in reportReasons"
                        :key="r.key"
                        class="flex min-h-11 cursor-pointer items-center gap-3 rounded-[0.9rem_0.75rem_0.95rem_0.8rem] px-2 hover:bg-tint"
                    >
                        <input
                            v-model="reportForm.reason"
                            type="radio"
                            name="report-reason"
                            :value="r.key"
                            class="size-5 accent-[var(--color-primary)]"
                            required
                        />
                        <span class="text-ink">{{ r.label }}</span>
                    </label>
                    <p
                        v-if="reportForm.errors.reason"
                        class="text-sm font-medium text-danger"
                        data-field-error
                    >
                        {{ reportForm.errors.reason }}
                    </p>
                </fieldset>
                <Field
                    id="report-message"
                    v-slot="field"
                    label="Beschreibung"
                    hint="Was genau ist das Problem? Bei Urheberrecht bitte angeben, wem die Rechte gehören."
                    :error="reportForm.errors.message"
                >
                    <Textarea
                        v-model="reportForm.message"
                        :rows="3"
                        v-bind="field.inputAttrs"
                    />
                </Field>
                <Field
                    id="report-email"
                    v-slot="field"
                    label="E-Mail für Rückfragen (optional)"
                    :error="reportForm.errors.contact_email"
                >
                    <TextInput
                        v-model="reportForm.contact_email"
                        v-bind="field.inputAttrs"
                        type="email"
                        autocomplete="email"
                    />
                </Field>
                <label class="flex items-start gap-3 text-sm text-ink">
                    <input
                        v-model="reportForm.good_faith"
                        type="checkbox"
                        class="mt-0.5 size-5 shrink-0 accent-[var(--color-primary)]"
                        :aria-invalid="
                            reportForm.errors.good_faith ? 'true' : undefined
                        "
                    />
                    <span
                        >Ich bestätige, dass meine Angaben nach bestem Wissen
                        richtig und vollständig sind.</span
                    >
                </label>
                <p
                    v-if="reportForm.errors.good_faith"
                    class="-mt-3 text-sm font-medium text-danger"
                    data-field-error
                >
                    {{ reportForm.errors.good_faith }}
                </p>
            </form>
            <template #footer>
                <Button
                    variant="ghost"
                    :disabled="reportForm.processing"
                    @click="closeReport"
                >
                    Abbrechen
                </Button>
                <Button
                    type="submit"
                    form="report-form"
                    :loading="reportForm.processing"
                    data-report-send
                >
                    Meldung senden
                </Button>
            </template>
        </Dialog>
    </AppLayout>
</template>

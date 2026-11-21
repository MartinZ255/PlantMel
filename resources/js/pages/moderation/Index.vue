<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import Badge from '@/components/ui/Badge.vue';
import BlobBackdrop from '@/components/ui/BlobBackdrop.vue';
import BlobImage from '@/components/ui/BlobImage.vue';
import Button from '@/components/ui/Button.vue';
import Dialog from '@/components/ui/Dialog.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Field from '@/components/ui/Field.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Textarea from '@/components/ui/Textarea.vue';
import TextInput from '@/components/ui/TextInput.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    Check,
    CookingPot,
    EyeOff,
    Flag,
    Sparkles,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import { route } from 'ziggy-js';

/**
 * E18: Moderation („Zu prüfen“, nur Host). Wartende Community-Rezepte mit
 * Vorschau-Link, Freigeben/Ablehnen mit Grund; vorgeschlagene Zutaten sind
 * markiert und lassen sich vor der Freigabe umbenennen oder einer
 * vorhandenen Zutat zuordnen. Darunter offene Meldungen mit Ausblenden,
 * Löschen und „Erledigt“.
 */
interface PendingRecipe {
    id: number;
    name: string;
    description: string | null;
    author: string | null;
    submittedAt: string | null;
    image: string | null;
    categories: string[];
    ingredients: {
        id: number | null;
        name: string | null;
        amount: string | null;
        proposed: boolean;
    }[];
    openReports: number;
    wasReviewed: boolean;
}

interface OpenReport {
    id: number;
    reason: string;
    message: string | null;
    contactEmail: string | null;
    byUser: boolean;
    createdAt: string | null;
    recipe: {
        id: number | null;
        name: string | null;
        status: string | null;
        author: string | null;
    };
}

const props = withDefaults(
    defineProps<{
        pending: PendingRecipe[];
        reports: OpenReport[];
        catalog?: string[];
    }>(),
    { catalog: () => [] },
);

const busy = ref<string | null>(null);
const post = (key: string, url: string) => {
    busy.value = key;
    router.post(
        url,
        {},
        {
            preserveScroll: true,
            onFinish: () => (busy.value = null),
        },
    );
};

// --- Ablehnen mit Grund (Dialog) --------------------------------------------
const rejecting = ref<PendingRecipe | null>(null);
const rejectForm = useForm({ reason: '' });
const openReject = (r: PendingRecipe) => {
    rejectForm.reset();
    rejectForm.clearErrors();
    rejecting.value = r;
};
const closeReject = () => {
    if (!rejectForm.processing) rejecting.value = null;
};
const sendReject = () => {
    if (!rejecting.value) return;
    rejectForm.post(route('moderation.reject', rejecting.value.id), {
        preserveScroll: true,
        onSuccess: () => (rejecting.value = null),
    });
};

// --- Vorschläge: umbenennen bzw. zuordnen -----------------------------------
const drafts = reactive<Record<number, { name: string; target: string }>>({});
const draftFor = (id: number, name: string) =>
    (drafts[id] ??= { name, target: '' });
const errors = reactive<Record<string, string>>({});

const renameProposal = (id: number) => {
    busy.value = `rename-${id}`;
    router.patch(
        route('moderation.ingredients.rename', id),
        { name: drafts[id]?.name ?? '' },
        {
            preserveScroll: true,
            onError: (e) => (errors[`rename-${id}`] = e.name ?? ''),
            onSuccess: () => delete errors[`rename-${id}`],
            onFinish: () => (busy.value = null),
        },
    );
};
const mergeProposal = (id: number) => {
    busy.value = `merge-${id}`;
    router.post(
        route('moderation.ingredients.merge', id),
        { target: drafts[id]?.target ?? '' },
        {
            preserveScroll: true,
            onError: (e) => (errors[`merge-${id}`] = e.target ?? ''),
            onSuccess: () => delete errors[`merge-${id}`],
            onFinish: () => (busy.value = null),
        },
    );
};

// --- Löschen nach Meldung ----------------------------------------------------
const deleting = ref<OpenReport['recipe'] | null>(null);
const deleteBusy = ref(false);
const confirmDelete = () => {
    if (!deleting.value?.id) return;
    deleteBusy.value = true;
    router.delete(route('community.destroy', deleting.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleteBusy.value = false;
            deleting.value = null;
        },
    });
};

const total = computed(() => props.pending.length + props.reports.length);
const proposalCount = (r: PendingRecipe) =>
    r.ingredients.filter((i) => i.proposed).length;
</script>

<template>
    <AppLayout page-title="Zu prüfen" bleed>
        <section class="relative overflow-hidden">
            <BlobBackdrop tone="light" />
            <LeafOrnament
                variant="eucalyptus"
                class="pointer-events-none absolute top-4 right-[5%] hidden h-40 animate-sway text-tint-strong [--float-rotate:-14deg] md:block"
            />
            <div
                class="relative mx-auto w-full max-w-(--content-max) px-4 pt-8 pb-8 sm:px-6 lg:px-8 lg:pt-12"
            >
                <PageHeader
                    title="Zu prüfen"
                    eyebrow="Moderation"
                    description="Community-Rezepte freigeben oder mit Begründung ablehnen, Meldungen bearbeiten."
                    size="xl"
                >
                    <template #meta>
                        <p
                            class="mt-3 inline-flex rounded-seed bg-tint px-3.5 py-1 text-sm font-semibold text-primary"
                            role="status"
                        >
                            {{ pending.length }} wartend ·
                            {{ reports.length }}
                            {{ reports.length === 1 ? 'Meldung' : 'Meldungen' }}
                        </p>
                    </template>
                </PageHeader>
            </div>
        </section>

        <div
            class="mx-auto flex w-full max-w-(--content-max) flex-col gap-16 px-4 pb-16 sm:px-6 lg:px-8"
        >
            <EmptyState
                v-if="total === 0"
                :icon="Check"
                title="Alles erledigt"
                description="Gerade wartet kein Community-Rezept auf die Freigabe, und es gibt keine offenen Meldungen."
            />

            <!-- Wartende Rezepte -->
            <section
                v-if="pending.length"
                aria-labelledby="pending-title"
                data-moderation-pending
            >
                <h2
                    id="pending-title"
                    class="font-display-wonk text-3xl font-semibold text-ink sm:text-4xl"
                >
                    Wartende Rezepte
                </h2>
                <ul class="mt-6 flex flex-col gap-8">
                    <li
                        v-for="(r, i) in pending"
                        :key="r.id"
                        class="relative overflow-hidden rounded-[2.25rem_1.75rem_2.5rem_2rem] bg-surface p-5 shadow-float sm:p-7"
                        :data-pending-recipe="r.id"
                    >
                        <div
                            class="grid min-w-0 gap-5 sm:grid-cols-[8rem_minmax(0,1fr)] sm:gap-7"
                        >
                            <BlobImage
                                :src="r.image"
                                alt=""
                                :shape="(i % 6) + 1"
                                ratio="1/1"
                                class="mx-auto w-32 shadow-card sm:w-full"
                            >
                                <template #fallback>
                                    <div
                                        class="flex size-full items-center justify-center bg-tint-strong text-primary"
                                        aria-hidden="true"
                                    >
                                        <CookingPot class="size-8" />
                                    </div>
                                </template>
                            </BlobImage>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <Badge tone="warning">Wird geprüft</Badge>
                                    <Badge v-if="r.wasReviewed" tone="neutral"
                                        >erneut eingereicht</Badge
                                    >
                                    <Badge v-if="r.openReports" tone="danger">
                                        {{ r.openReports }}
                                        {{
                                            r.openReports === 1
                                                ? 'Meldung'
                                                : 'Meldungen'
                                        }}
                                    </Badge>
                                </div>
                                <h3
                                    class="mt-2 font-display text-2xl leading-snug font-semibold text-wrap-anywhere text-ink sm:text-3xl"
                                >
                                    {{ r.name }}
                                </h3>
                                <p class="mt-1 text-sm text-ink-muted">
                                    von {{ r.author ?? 'unbekannt' }}
                                    <template v-if="r.submittedAt">
                                        · eingereicht am
                                        {{ r.submittedAt }}</template
                                    >
                                </p>
                                <p
                                    v-if="r.description"
                                    class="mt-3 max-w-3xl text-wrap-anywhere text-ink"
                                >
                                    {{ r.description }}
                                </p>
                                <p
                                    v-if="r.categories.length"
                                    class="mt-2 text-sm text-ink-muted"
                                >
                                    Kategorien: {{ r.categories.join(', ') }}
                                </p>

                                <h4
                                    class="mt-5 text-sm font-semibold tracking-[0.12em] text-primary uppercase"
                                >
                                    Zutaten
                                    <span
                                        v-if="proposalCount(r)"
                                        class="ml-1 tracking-normal text-berry normal-case"
                                        >· {{ proposalCount(r) }} neu
                                        vorgeschlagen</span
                                    >
                                </h4>
                                <ul class="mt-2 flex flex-col gap-2">
                                    <li
                                        v-for="(ing, k) in r.ingredients"
                                        :key="`${r.id}-${k}`"
                                        class="min-w-0"
                                        :data-proposed="
                                            ing.proposed ? '' : undefined
                                        "
                                    >
                                        <p
                                            class="flex flex-wrap items-center gap-2 text-ink"
                                        >
                                            <span
                                                v-if="ing.amount"
                                                class="text-ink-muted"
                                                >{{ ing.amount }}</span
                                            >
                                            <span
                                                class="font-medium text-wrap-anywhere"
                                                >{{ ing.name }}</span
                                            >
                                            <span
                                                v-if="ing.proposed"
                                                class="inline-flex items-center gap-1 rounded-seed bg-berry-surface px-2.5 py-0.5 text-xs font-semibold text-berry"
                                            >
                                                <Sparkles
                                                    class="size-3.5"
                                                    aria-hidden="true"
                                                />
                                                Neuer Vorschlag
                                            </span>
                                        </p>
                                        <!-- Vorschlag vor der Freigabe bereinigen -->
                                        <div
                                            v-if="ing.proposed && ing.id"
                                            class="mt-2 grid gap-3 rounded-[1.25rem_1rem_1.4rem_1.1rem] bg-tint/70 p-3 md:grid-cols-2"
                                        >
                                            <form
                                                class="flex min-w-0 flex-col gap-1.5"
                                                @submit.prevent="
                                                    renameProposal(ing.id)
                                                "
                                            >
                                                <label
                                                    :for="`rename-${ing.id}-${r.id}`"
                                                    class="text-sm font-semibold text-ink"
                                                    >Umbenennen</label
                                                >
                                                <div class="flex gap-2">
                                                    <TextInput
                                                        :id="`rename-${ing.id}-${r.id}`"
                                                        v-model="
                                                            draftFor(
                                                                ing.id,
                                                                ing.name ?? '',
                                                            ).name
                                                        "
                                                        :aria-invalid="
                                                            errors[
                                                                `rename-${ing.id}`
                                                            ]
                                                                ? 'true'
                                                                : undefined
                                                        "
                                                    />
                                                    <Button
                                                        type="submit"
                                                        variant="secondary"
                                                        size="sm"
                                                        class="min-h-11 shrink-0"
                                                        :loading="
                                                            busy ===
                                                            `rename-${ing.id}`
                                                        "
                                                        >Speichern</Button
                                                    >
                                                </div>
                                                <p
                                                    v-if="
                                                        errors[
                                                            `rename-${ing.id}`
                                                        ]
                                                    "
                                                    class="text-sm font-medium text-danger"
                                                    role="alert"
                                                >
                                                    {{
                                                        errors[
                                                            `rename-${ing.id}`
                                                        ]
                                                    }}
                                                </p>
                                            </form>
                                            <form
                                                class="flex min-w-0 flex-col gap-1.5"
                                                @submit.prevent="
                                                    mergeProposal(ing.id)
                                                "
                                            >
                                                <label
                                                    :for="`merge-${ing.id}-${r.id}`"
                                                    class="text-sm font-semibold text-ink"
                                                    >Vorhandener Zutat
                                                    zuordnen</label
                                                >
                                                <div class="flex gap-2">
                                                    <TextInput
                                                        :id="`merge-${ing.id}-${r.id}`"
                                                        v-model="
                                                            draftFor(
                                                                ing.id,
                                                                ing.name ?? '',
                                                            ).target
                                                        "
                                                        list="catalog-ingredients"
                                                        placeholder="Zutat aus dem Katalog"
                                                        :aria-invalid="
                                                            errors[
                                                                `merge-${ing.id}`
                                                            ]
                                                                ? 'true'
                                                                : undefined
                                                        "
                                                    />
                                                    <Button
                                                        type="submit"
                                                        variant="secondary"
                                                        size="sm"
                                                        class="min-h-11 shrink-0"
                                                        :loading="
                                                            busy ===
                                                            `merge-${ing.id}`
                                                        "
                                                        >Zuordnen</Button
                                                    >
                                                </div>
                                                <p
                                                    v-if="
                                                        errors[
                                                            `merge-${ing.id}`
                                                        ]
                                                    "
                                                    class="text-sm font-medium text-danger"
                                                    role="alert"
                                                >
                                                    {{
                                                        errors[
                                                            `merge-${ing.id}`
                                                        ]
                                                    }}
                                                </p>
                                            </form>
                                        </div>
                                    </li>
                                </ul>

                                <div class="mt-6 flex flex-wrap gap-2.5">
                                    <Button
                                        :loading="busy === `approve-${r.id}`"
                                        :data-approve="r.id"
                                        @click="
                                            post(
                                                `approve-${r.id}`,
                                                route(
                                                    'moderation.approve',
                                                    r.id,
                                                ),
                                            )
                                        "
                                    >
                                        <Check
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        Freigeben
                                        <span class="sr-only"
                                            >: {{ r.name }}</span
                                        >
                                    </Button>
                                    <Button
                                        variant="secondary"
                                        aria-haspopup="dialog"
                                        :data-reject="r.id"
                                        @click="openReject(r)"
                                    >
                                        <X class="size-4" aria-hidden="true" />
                                        Ablehnen
                                        <span class="sr-only"
                                            >: {{ r.name }}</span
                                        >
                                    </Button>
                                    <Link
                                        :href="route('community.show', r.id)"
                                        class="inline-flex min-h-11 items-center rounded-pebble px-3 font-semibold text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                                    >
                                        Vorschau ansehen
                                        <span class="sr-only"
                                            >: {{ r.name }}</span
                                        >
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </li>
                </ul>
                <datalist id="catalog-ingredients">
                    <option v-for="name in catalog" :key="name" :value="name" />
                </datalist>
            </section>

            <!-- Offene Meldungen -->
            <section
                v-if="reports.length"
                aria-labelledby="reports-title"
                data-moderation-reports
            >
                <h2
                    id="reports-title"
                    class="font-display-wonk text-3xl font-semibold text-ink sm:text-4xl"
                >
                    Offene Meldungen
                </h2>
                <ul class="mt-6 grid gap-5 md:grid-cols-2">
                    <li
                        v-for="rep in reports"
                        :key="rep.id"
                        class="relative flex min-w-0 flex-col gap-3 overflow-hidden rounded-[2rem_1.6rem_2.25rem_1.5rem] bg-surface p-5 shadow-card ring-1 ring-danger/20"
                        :data-report="rep.id"
                    >
                        <p class="flex items-center gap-2 text-danger">
                            <Flag class="size-4" aria-hidden="true" />
                            <span class="font-semibold">{{ rep.reason }}</span>
                        </p>
                        <h3
                            class="font-display text-xl font-semibold text-wrap-anywhere text-ink"
                        >
                            <Link
                                v-if="rep.recipe.id"
                                :href="route('community.show', rep.recipe.id)"
                                class="underline-offset-4 hover:underline"
                                >{{ rep.recipe.name }}</Link
                            >
                        </h3>
                        <p class="text-sm text-ink-muted">
                            von {{ rep.recipe.author ?? 'unbekannt' }} ·
                            gemeldet am {{ rep.createdAt }} ·
                            {{ rep.byUser ? 'angemeldet' : 'Gast' }}
                            <template v-if="rep.recipe.status !== 'approved'">
                                · derzeit nicht öffentlich</template
                            >
                        </p>
                        <p
                            v-if="rep.message"
                            class="rounded-[1rem_0.8rem_1.1rem_0.9rem] bg-canvas px-3 py-2 text-wrap-anywhere whitespace-pre-line text-ink"
                        >
                            {{ rep.message }}
                        </p>
                        <p
                            v-if="rep.contactEmail"
                            class="text-sm text-wrap-anywhere text-ink-muted"
                        >
                            Kontakt: {{ rep.contactEmail }}
                        </p>
                        <div class="mt-auto flex flex-wrap gap-2 pt-1">
                            <Button
                                v-if="
                                    rep.recipe.id &&
                                    rep.recipe.status === 'approved'
                                "
                                variant="secondary"
                                size="sm"
                                :loading="busy === `hide-${rep.id}`"
                                @click="
                                    post(
                                        `hide-${rep.id}`,
                                        route('moderation.hide', rep.recipe.id),
                                    )
                                "
                            >
                                <EyeOff class="size-4" aria-hidden="true" />
                                Ausblenden
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="text-danger hover:bg-danger-surface"
                                aria-haspopup="dialog"
                                @click="deleting = rep.recipe"
                            >
                                <Trash2 class="size-4" aria-hidden="true" />
                                Rezept löschen
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                :loading="busy === `resolve-${rep.id}`"
                                @click="
                                    post(
                                        `resolve-${rep.id}`,
                                        route(
                                            'moderation.reports.resolve',
                                            rep.id,
                                        ),
                                    )
                                "
                            >
                                <Check class="size-4" aria-hidden="true" />
                                Erledigt
                            </Button>
                        </div>
                    </li>
                </ul>
            </section>
        </div>

        <Dialog
            :open="rejecting !== null"
            title="Rezept ablehnen"
            :description="
                rejecting
                    ? `Der Grund wird ${rejecting.author ?? 'dem Autor'} bei „${rejecting.name}“ angezeigt.`
                    : undefined
            "
            @close="closeReject"
        >
            <form id="reject-form" @submit.prevent="sendReject">
                <Field
                    id="reject-reason"
                    v-slot="field"
                    label="Begründung"
                    required
                    hint="Sachlich und konkret, z. B. was fehlt oder geändert werden sollte."
                    :error="rejectForm.errors.reason"
                >
                    <Textarea
                        v-model="rejectForm.reason"
                        :rows="4"
                        v-bind="field.inputAttrs"
                    />
                </Field>
            </form>
            <template #footer>
                <Button
                    variant="ghost"
                    :disabled="rejectForm.processing"
                    @click="closeReject"
                >
                    Abbrechen
                </Button>
                <Button
                    type="submit"
                    form="reject-form"
                    variant="danger"
                    :loading="rejectForm.processing"
                    data-reject-send
                >
                    Ablehnen
                </Button>
            </template>
        </Dialog>

        <Dialog
            :open="deleting !== null"
            title="Rezept löschen?"
            :description="`„${deleting?.name ?? ''}“ wird samt Bild endgültig gelöscht.`"
            size="sm"
            @close="!deleteBusy && (deleting = null)"
        >
            <template #footer>
                <Button
                    variant="ghost"
                    :disabled="deleteBusy"
                    @click="deleting = null"
                >
                    Abbrechen
                </Button>
                <Button
                    variant="danger"
                    :loading="deleteBusy"
                    @click="confirmDelete"
                >
                    Endgültig löschen
                </Button>
            </template>
        </Dialog>
    </AppLayout>
</template>

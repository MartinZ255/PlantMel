<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import Badge, { type BadgeTone } from '@/components/ui/Badge.vue';
import BlobBackdrop from '@/components/ui/BlobBackdrop.vue';
import BlobImage from '@/components/ui/BlobImage.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import { Link } from '@inertiajs/vue3';
import { CookingPot, Pencil, Plus, Sprout } from 'lucide-vue-next';
import { route } from 'ziggy-js';

/**
 * E18: „Meine Rezepte“ – eigene Community-Rezepte mit Status
 * (Entwurf, wird geprüft, freigegeben, abgelehnt inkl. Grund).
 */
type Status = 'draft' | 'pending' | 'approved' | 'rejected';

defineProps<{
    recipes: {
        id: number;
        name: string;
        status: Status;
        rejectionReason: string | null;
        image: string | null;
        updatedAt: string | null;
        submittedAt: string | null;
        reviewedAt: string | null;
    }[];
}>();

const statusMeta: Record<
    Status,
    { label: string; tone: BadgeTone; text: string }
> = {
    draft: {
        label: 'Entwurf',
        tone: 'neutral',
        text: 'Nur für dich sichtbar.',
    },
    pending: {
        label: 'Wird geprüft',
        tone: 'warning',
        text: 'Wartet auf die Freigabe, noch nicht öffentlich.',
    },
    approved: {
        label: 'Freigegeben',
        tone: 'success',
        text: 'Öffentlich sichtbar. Änderungen werden erneut geprüft.',
    },
    rejected: {
        label: 'Abgelehnt',
        tone: 'danger',
        text: 'Nicht freigegeben – überarbeiten und erneut einreichen.',
    },
};

const formatDate = (iso: string | null) =>
    iso
        ? new Date(`${iso}T12:00:00`).toLocaleDateString('de-DE', {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
          })
        : null;
</script>

<template>
    <AppLayout page-title="Meine Rezepte" bleed>
        <section class="relative overflow-hidden">
            <BlobBackdrop tone="light" />
            <LeafOrnament
                variant="sprig"
                class="pointer-events-none absolute top-6 right-[6%] hidden h-36 animate-float-slow text-tint-strong [--float-rotate:20deg] md:block"
            />
            <div
                class="relative mx-auto w-full max-w-(--content-max) px-4 pt-8 pb-8 sm:px-6 lg:px-8 lg:pt-12"
            >
                <PageHeader
                    title="Meine Rezepte"
                    eyebrow="Für die Community"
                    description="Deine eingereichten Rezepte und ihr Stand der Prüfung."
                    size="xl"
                >
                    <template #actions>
                        <Button :href="route('community.create')">
                            <Plus class="size-5" aria-hidden="true" />
                            Rezept einreichen
                        </Button>
                    </template>
                </PageHeader>
            </div>
        </section>

        <section
            class="mx-auto w-full max-w-(--content-max) px-4 pb-16 sm:px-6 lg:px-8"
            aria-label="Deine Rezepte"
        >
            <EmptyState
                v-if="recipes.length === 0"
                :icon="Sprout"
                title="Noch keine eigenen Rezepte"
                description="Teile dein erstes Rezept mit der Community. Nach der Prüfung ist es für alle sichtbar."
            >
                <template #actions>
                    <Button :href="route('community.create')">
                        Rezept einreichen
                    </Button>
                </template>
            </EmptyState>

            <ul v-else class="flex flex-col gap-5" data-my-recipes>
                <li
                    v-for="(r, i) in recipes"
                    :key="r.id"
                    class="relative grid min-w-0 grid-cols-[4.5rem_minmax(0,1fr)] items-start gap-4 rounded-[2rem_1.6rem_2.25rem_1.5rem] bg-surface p-4 shadow-card sm:grid-cols-[6rem_minmax(0,1fr)_auto] sm:items-center sm:gap-6 sm:p-5"
                    :data-status="r.status"
                >
                    <BlobImage
                        :src="r.image"
                        alt=""
                        :shape="(i % 6) + 1"
                        ratio="1/1"
                        class="shadow-card"
                    >
                        <template #fallback>
                            <div
                                class="flex size-full items-center justify-center bg-tint-strong text-primary"
                                aria-hidden="true"
                            >
                                <CookingPot class="size-7" />
                            </div>
                        </template>
                    </BlobImage>
                    <div class="min-w-0">
                        <h2
                            class="font-display text-2xl leading-snug font-semibold text-wrap-anywhere text-ink"
                        >
                            <Link
                                :href="route('community.show', r.id)"
                                class="decoration-berry decoration-2 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                                >{{ r.name }}</Link
                            >
                        </h2>
                        <p class="mt-1.5 flex flex-wrap items-center gap-2">
                            <Badge :tone="statusMeta[r.status].tone">{{
                                statusMeta[r.status].label
                            }}</Badge>
                            <span class="text-sm text-ink-muted">{{
                                statusMeta[r.status].text
                            }}</span>
                        </p>
                        <p
                            v-if="r.status === 'rejected' && r.rejectionReason"
                            class="mt-2 rounded-[1rem_0.8rem_1.1rem_0.9rem] bg-danger-surface px-3 py-2 text-sm text-wrap-anywhere text-ink"
                            data-rejection-reason
                        >
                            <span class="font-semibold text-danger"
                                >Grund:</span
                            >
                            {{ r.rejectionReason }}
                        </p>
                        <p
                            v-if="formatDate(r.updatedAt)"
                            class="mt-1 text-xs text-ink-muted"
                        >
                            Zuletzt geändert am {{ formatDate(r.updatedAt) }}
                        </p>
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <Button
                            variant="secondary"
                            size="sm"
                            :href="route('community.edit', r.id)"
                        >
                            <Pencil class="size-4" aria-hidden="true" />
                            Bearbeiten
                            <span class="sr-only">: {{ r.name }}</span>
                        </Button>
                    </div>
                </li>
            </ul>
        </section>
    </AppLayout>
</template>

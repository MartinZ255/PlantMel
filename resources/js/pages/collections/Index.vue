<template>
    <AppLayout page-title="Sammlungen" bleed>
        <section class="relative overflow-hidden">
            <BlobBackdrop tone="light" />
            <LeafOrnament
                variant="sprig"
                class="pointer-events-none absolute top-6 right-[5%] hidden h-44 animate-sway text-tint-strong [--float-rotate:-20deg] md:block lg:h-56"
            />
            <LeafOrnament
                variant="leaf"
                class="pointer-events-none absolute top-28 right-[24%] hidden h-14 animate-float-slow text-moss/60 [--float-rotate:32deg] lg:block"
            />

            <div
                class="relative mx-auto w-full max-w-(--content-max) px-4 pt-8 pb-10 sm:px-6 lg:px-8 lg:pt-12 lg:pb-14"
            >
                <PageHeader
                    title="Meine Sammlungen"
                    eyebrow="Dein Rezeptregal"
                    description="Gruppiere deine Lieblingsrezepte in thematischen Listen."
                    size="xl"
                >
                    <template #title>
                        Meine
                        <span class="accent-word text-primary">Sammlungen</span>
                    </template>
                    <template #meta>
                        <p class="mt-2 font-semibold text-primary">
                            {{ countText }}
                        </p>
                    </template>
                    <template v-if="collections.length" #actions>
                        <Button :href="route('collections.create')" size="lg">
                            <Plus class="size-5" aria-hidden="true" />
                            Neue Sammlung
                        </Button>
                    </template>
                </PageHeader>
            </div>
        </section>

        <section
            class="mx-auto w-full max-w-(--content-max) px-4 pt-2 pb-16 sm:px-6 lg:px-8"
            aria-label="Sammlungen"
        >
            <EmptyState
                v-if="!collections.length"
                :icon="FolderHeart"
                title="Noch keine Sammlungen"
                description="Lege deine erste Sammlung an – etwa für „Unter der Woche“, „Besuch“ oder „Meal Prep“ – und füge Rezepte über „Zur Sammlung hinzufügen“ hinzu."
            >
                <template #actions>
                    <Button :href="route('collections.create')">
                        <Plus class="size-5" aria-hidden="true" />
                        Erste Sammlung anlegen
                    </Button>
                    <Button :href="route('recipes.index')" variant="secondary">
                        Rezepte entdecken
                    </Button>
                </template>
            </EmptyState>

            <!-- leicht gestaffeltes Raster; DOM-Reihenfolge = Lesereihenfolge -->
            <ul
                v-else
                class="grid grid-cols-1 gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3 lg:gap-x-10"
            >
                <Reveal
                    v-for="(collection, i) in collections"
                    :key="collection.id"
                    as="li"
                    :delay="(i % 3) * 90"
                    class="min-w-0 sm:max-lg:even:mt-10 lg:[&:nth-child(3n+2)]:mt-14"
                >
                    <CollectionCard :collection="collection" :shape="i + 1" />
                </Reveal>

                <!-- Anlegen als eigene, gestrichelte „Saatfläche“ am Ende -->
                <li
                    class="min-w-0 sm:max-lg:even:mt-10 lg:[&:nth-child(3n+2)]:mt-14"
                >
                    <Link
                        :href="route('collections.create')"
                        class="group flex aspect-[5/4] w-full flex-col items-center justify-center gap-3 rounded-[46%_54%_40%_60%/56%_44%_58%_42%] border-2 border-dashed border-line-strong bg-surface/60 px-6 text-center text-primary transition-[border-radius,background-color,border-color] duration-700 ease-organic hover:rounded-[58%_42%_52%_48%/46%_54%_46%_54%] hover:border-primary hover:bg-tint focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-focus"
                    >
                        <span
                            class="blob-mask inline-flex size-16 items-center justify-center bg-matcha text-forest shadow-card blob-4"
                            aria-hidden="true"
                        >
                            <Plus class="size-7" />
                        </span>
                        <span class="font-display text-xl font-semibold"
                            >Weitere Sammlung anlegen</span
                        >
                    </Link>
                </li>
            </ul>
        </section>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import CollectionCard from '@/components/recipes/CollectionCard.vue';
import BlobBackdrop from '@/components/ui/BlobBackdrop.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import Reveal from '@/components/ui/Reveal.vue';
import { Link } from '@inertiajs/vue3';
import { FolderHeart, Plus } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';

interface Collection {
    id: number | string;
    name: string;
    description?: string | null;
    recipeCount?: number;
    /** bis zu 4 Bild-URLs enthaltener Rezepte (Collage der Kachel) */
    images?: string[];
}

const props = defineProps<{
    collections: Collection[];
}>();

const collections = computed(() => props.collections ?? []);

const countText = computed(() => {
    const n = collections.value.length;
    if (n === 0) return 'Noch keine Sammlung angelegt';
    return n === 1 ? '1 Sammlung' : `${n} Sammlungen`;
});
</script>

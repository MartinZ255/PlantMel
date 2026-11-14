<template>
    <AppLayout page-title="Sammlungen">
        <section class="content-section">
            <div class="section-header">
                <div>
                    <h1 class="section-header__title">Meine Sammlungen</h1>
                    <div class="section-header__subtitle">
                        Gruppiere deine Lieblingsrezepte in thematischen Listen.
                    </div>
                </div>

                <button
                    type="button"
                    class="primary-pill-button"
                    @click="createCollection"
                >
                    + Neue Sammlung
                </button>
            </div>

            <div class="collections-grid">
                <CollectionCard
                    v-for="collection in collections"
                    :key="collection.id"
                    :collection="collection"
                    @open="openCollection"
                />
            </div>
        </section>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import CollectionCard from '@/components/recipes/CollectionCard.vue';
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

interface Collection {
    id: number | string;
    name: string;
    description?: string | null;
    recipeCount?: number;
}

const props = defineProps<{
    collections: Collection[];
}>();

const collections = computed(() => props.collections);

const createCollection = () => {
    router.visit(route('collections.create'));
};

const openCollection = (id: Collection['id']) => {
    router.visit(route('collections.show', id));
};
</script>

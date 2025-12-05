<template>
    <article class="collection-card" @click="handleClick">
        <header class="collection-card__header">
            <div class="collection-card__title-row">
                <h3 class="collection-card__title">
                    {{ collection.name }}
                </h3>
            </div>

            <p v-if="collection.description" class="collection-card__description">
                {{ collection.description }}
            </p>
        </header>

        <div class="collection-card__meta">
            <span class="collection-card__count">
                {{ collection.recipeCount }} Rezepte
            </span>
        </div>

        <div v-if="collection.tags?.length" class="collection-card__tags">
            <span
                v-for="tag in collection.tags"
                :key="tag"
                class="collection-card__tag"
            >
                {{ tag }}
            </span>
        </div>
    </article>
</template>

<script setup lang="ts">
interface Collection {
    id: number | string;
    name: string;
    description?: string | null;
    recipeCount?: number;
    tags?: string[];
}

const props = defineProps<{
    collection: Collection;
}>();

const emit = defineEmits<{
    (e: 'open', id: Collection['id']): void;
}>();

const handleClick = () => {
    emit('open', props.collection.id);
};
</script>

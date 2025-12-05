<template>
    <section class="content-section content-section--transparent">
        <div class="section-header">
            <div class="section-header__title">Sammlungen</div>
            <div class="section-header__meta">
                • {{ total }} gespeicherte Rezepte
            </div>
        </div>

        <div class="board-row">
            <article
                v-for="collection in collections"
                :key="collection.id"
                class="note-card"
            >
                <div class="note-card__title">{{ collection.title }}</div>
                <div class="note-card__meta">
                    {{ collection.count }} Rezepte
                </div>
            </article>
        </div>
    </section>
</template>

<script setup lang="ts">
interface Collection {
    id: number | string;
    title: string;
    count: number;
}

interface Props {
    collections: Collection[];
    total: number;
}

defineProps<Props>();
</script>

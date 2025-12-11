<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

type CollectionOption = {
    id: number;
    name: string;
};

const props = defineProps<{
    open: boolean;
    collections: CollectionOption[];
    recipeId: number;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

// Mehrfachauswahl: Array von IDs
const selectedCollectionIds = ref<number[]>([]);

const form = useForm({
    recipe_id: props.recipeId,
});

// falls das Modal mit einem anderen Rezept wiederverwendet wird
watch(
    () => props.recipeId,
    (val) => {
        form.recipe_id = val;
    }
);

const close = () => {
    selectedCollectionIds.value = [];
    form.reset();
    emit('close');
};

const addToCollection = () => {
    if (selectedCollectionIds.value.length === 0) return;

    // nacheinander in jede ausgewählte Collection posten
    // (Backend erwartet offenbar eine einzelne collection-ID)
    const ids = [...selectedCollectionIds.value];

    const postNext = () => {
        const id = ids.shift();
        if (!id) {
            close();
            return;
        }

        form.post(
            route('collections.addRecipe', {
                collection: id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    // nächste Collection, wenn noch welche übrig sind
                    postNext();
                },
            }
        );
    };

    postNext();
};
</script>

<template>
    <div
        v-if="open"
        class="modal-backdrop"
        @click.self="close"
    >
        <div class="modal">
            <h2 class="modal__title">Zur Sammlung hinzufügen</h2>
            <p class="modal__subtitle">
                Wähle eine deiner Sammlungen aus, in der dieses Rezept gespeichert werden soll.
            </p>

            <div class="modal__list">
                <label
                    v-for="collection in collections"
                    :key="collection.id"
                    class="modal__list-item"
                >
                    <input
                        type="checkbox"
                        name="collection"
                        :value="collection.id"
                        v-model="selectedCollectionIds"
                    />
                    <span>{{ collection.name }}</span>
                </label>

                <p
                    v-if="collections.length === 0"
                    class="modal__empty"
                >
                    Du hast noch keine Sammlungen angelegt.
                </p>
            </div>

            <div class="modal__actions">
                <button
                    type="button"
                    class="secondary-pill-button"
                    @click="close"
                >
                    Abbrechen
                </button>
                <button
                    type="button"
                    class="primary-pill-button"
                    :disabled="!selectedCollectionIds || form.processing || collections.length === 0"
                    @click="addToCollection"
                >
                    Hinzufügen
                </button>
            </div>
        </div>
    </div>
</template>

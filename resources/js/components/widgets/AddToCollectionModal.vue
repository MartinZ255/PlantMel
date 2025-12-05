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

const selectedCollectionId = ref<number | null>(null);

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
    selectedCollectionId.value = null;
    form.reset();
    emit('close');
};

const addToCollection = () => {
    if (!selectedCollectionId.value) return;

    form.post(
        route('collections.addRecipe', {
            collection: selectedCollectionId.value,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                close();
            },
        }
    );
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
                        type="radio"
                        name="collection"
                        :value="collection.id"
                        v-model="selectedCollectionId"
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
                    :disabled="!selectedCollectionId || form.processing || collections.length === 0"
                    @click="addToCollection"
                >
                    Hinzufügen
                </button>
            </div>
        </div>
    </div>
</template>

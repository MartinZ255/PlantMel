<template>
    <div v-if="show" class="modal-backdrop">
        <div class="modal">
            <h2 class="modal__title">Tag bearbeiten</h2>

            <div class="form-field">
                <label class="form-field__label" for="tag-name">
                    Tag-Name
                </label>
                <input
                    id="tag-name"
                    v-model="localName"
                    type="text"
                    class="form-field__input"
                />
                <p v-if="errorMessage" class="form-field__error">
                    {{ errorMessage }}
                </p>
            </div>

            <div class="modal__actions">
                <button
                    type="button"
                    class="secondary-pill-button"
                    @click="emit('close')"
                    :disabled="loadingSave || loadingDelete"
                >
                    Abbrechen
                </button>

                <button
                    type="button"
                    class="secondary-pill-button modal__delete"
                    @click="onDelete"
                    :disabled="loadingSave || loadingDelete"
                >
                    Löschen
                </button>

                <button
                    type="button"
                    class="primary-pill-button"
                    @click="onSave"
                    :disabled="loadingSave || loadingDelete || !localName.trim()"
                >
                    Speichern
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import axios from 'axios';
import { route } from 'ziggy-js';

interface Category {
    id: number;
    name: string;
}

const props = defineProps<{
    show: boolean;
    category: Category;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'updated', payload: { oldName: string; category: Category }): void;
    (e: 'deleted', payload: { id: number; name: string }): void;
}>();

const localName = ref(props.category.name);
const loadingSave = ref(false);
const loadingDelete = ref(false);
const errorMessage = ref('');

// Wenn sich die übergebene Kategorie ändert, Eingabefeld aktualisieren
watch(
    () => props.category,
    (newVal) => {
        if (newVal) {
            localName.value = newVal.name;
            errorMessage.value = '';
        }
    },
    { immediate: true },
);

const onSave = async () => {
    const trimmed = localName.value.trim();
    if (!trimmed) return;

    loadingSave.value = true;
    errorMessage.value = '';

    const oldName = props.category.name;

    try {
        const response = await axios.put(
            route('categories.update', props.category.id),
            { name: trimmed },
        );

        const updated: Category = response.data;

        emit('updated', { oldName, category: updated });
        emit('close');
    } catch (error: any) {
        if (error.response && error.response.data) {
            const data = error.response.data;

            if (data.errors && data.errors.name && data.errors.name[0]) {
                errorMessage.value = data.errors.name[0];
            } else if (data.message) {
                errorMessage.value = data.message;
            } else {
                errorMessage.value = 'Speichern fehlgeschlagen.';
            }
        } else {
            errorMessage.value = 'Speichern fehlgeschlagen.';
        }
    } finally {
        loadingSave.value = false;
    }
};

const onDelete = async () => {
    loadingDelete.value = true;
    errorMessage.value = '';

    try {
        await axios.delete(route('categories.destroy', props.category.id));

        emit('deleted', { id: props.category.id, name: props.category.name });
        emit('close');
    } catch (error: any) {
        if (error.response && error.response.data && error.response.data.message) {
            errorMessage.value = error.response.data.message;
        } else {
            errorMessage.value = 'Löschen fehlgeschlagen.';
        }
    } finally {
        loadingDelete.value = false;
    }
};
</script>

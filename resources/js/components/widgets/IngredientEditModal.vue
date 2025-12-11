<template>
    <div v-if="show" class="modal-backdrop">
        <div class="modal">
            <h2 class="modal__title">Zutat bearbeiten</h2>

            <div class="form-field">
                <label class="form-field__label" for="ingredient-name">
                    Name der Zutat
                </label>
                <input
                    id="ingredient-name"
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

interface IngredientOption {
    id: number;
    name: string;
}

const props = defineProps<{
    show: boolean;
    ingredient: IngredientOption;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'updated', payload: { oldName: string; ingredient: IngredientOption }): void;
    (e: 'deleted', payload: { id: number; name: string }): void;
}>();

const localName = ref(props.ingredient.name);
const loadingSave = ref(false);
const loadingDelete = ref(false);
const errorMessage = ref('');

// Wenn sich die übergebene Ingredient ändert (andere Zeile), Feld aktualisieren
watch(
    () => props.ingredient,
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

    const oldName = props.ingredient.name;

    try {
        const response = await axios.put(
            route('ingredients.update', props.ingredient.id),
            { name: trimmed },
        );

        const updated: IngredientOption = response.data;

        emit('updated', { oldName, ingredient: updated });
        emit('close');
    } catch (error: any) {
        if (error.response && error.response.data && error.response.data.message) {
            errorMessage.value = error.response.data.message;
        } else if (error.response && error.response.data && error.response.data.errors) {
            // Laravel-Validation-Fehler
            const errors = error.response.data.errors;
            errorMessage.value = (errors.name && errors.name[0]) || 'Speichern fehlgeschlagen.';
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
        await axios.delete(route('ingredients.destroy', props.ingredient.id));

        emit('deleted', { id: props.ingredient.id, name: props.ingredient.name });
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

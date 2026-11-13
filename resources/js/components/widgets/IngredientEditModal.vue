<template>
    <div v-if="show" class="modal-backdrop">
        <div
            class="modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="ingredient-edit-title"
        >
            <h2 id="ingredient-edit-title" class="modal__title">
                Zutat bearbeiten
            </h2>

            <div class="form-field">
                <label class="form-field__label" for="ingredient-name">
                    Name der Zutat
                </label>
                <input
                    id="ingredient-name"
                    v-model="localName"
                    type="text"
                    class="form-field__input"
                    aria-describedby="ingredient-name-hint"
                    :disabled="isBusy || !!confirmMessage"
                />
                <p id="ingredient-name-hint" class="form-field__hint">
                    Umbenennen und Löschen gelten für alle Rezepte mit dieser
                    Zutat.
                </p>
                <p v-if="saveError" class="form-field__error" role="alert">
                    {{ saveError }}
                </p>
            </div>

            <p v-if="deleteError" class="modal__alert" role="alert">
                {{ deleteError }}
            </p>

            <!-- ADR-002: Bestätigung mit der Meldung des Servers (Name + Anzahl) -->
            <div
                v-if="confirmMessage"
                class="modal__confirm"
                role="group"
                aria-labelledby="ingredient-confirm-text"
            >
                <p id="ingredient-confirm-text" class="modal__confirm-text">
                    {{ confirmMessage }}
                </p>
                <div class="modal__confirm-actions">
                    <button
                        ref="confirmCancelButton"
                        type="button"
                        class="secondary-pill-button"
                        :disabled="isBusy"
                        @click="cancelConfirm"
                    >
                        Abbrechen
                    </button>
                    <button
                        type="button"
                        class="primary-pill-button modal__confirm-delete"
                        :disabled="isBusy"
                        @click="deleteIngredient(true)"
                    >
                        Endgültig löschen
                    </button>
                </div>
            </div>

            <div v-else class="modal__actions">
                <button
                    type="button"
                    class="secondary-pill-button"
                    @click="emit('close')"
                    :disabled="isBusy"
                >
                    Abbrechen
                </button>

                <button
                    ref="deleteButton"
                    type="button"
                    class="secondary-pill-button modal__delete"
                    @click="deleteIngredient(false)"
                    :disabled="isBusy"
                >
                    Löschen
                </button>

                <button
                    type="button"
                    class="primary-pill-button"
                    @click="onSave"
                    :disabled="isBusy || !localName.trim()"
                >
                    Speichern
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import axios from 'axios';
import { computed, nextTick, ref, watch } from 'vue';
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
    (
        e: 'updated',
        payload: { oldName: string; ingredient: IngredientOption },
    ): void;
    (e: 'deleted', payload: { id: number; name: string }): void;
}>();

const localName = ref(props.ingredient.name);
const loadingSave = ref(false);
const loadingDelete = ref(false);
const saveError = ref('');
const deleteError = ref('');
// Meldung des Servers aus der 409-Antwort; gesetzt = Bestätigungsbereich sichtbar
const confirmMessage = ref<string | null>(null);
const confirmCancelButton = ref<HTMLButtonElement | null>(null);
const deleteButton = ref<HTMLButtonElement | null>(null);

const isBusy = computed(() => loadingSave.value || loadingDelete.value);

const resetState = () => {
    localName.value = props.ingredient.name;
    saveError.value = '';
    deleteError.value = '';
    confirmMessage.value = null;
};

// Andere Zutat oder erneutes Öffnen: Zustand (inkl. Bestätigung) zurücksetzen
watch(() => props.ingredient, resetState, { immediate: true });
watch(
    () => props.show,
    (show) => {
        if (show) resetState();
    },
);

/** Verständliche deutsche Meldung für fehlgeschlagene Requests. */
const describeError = (error: unknown, fallback: string): string => {
    if (!axios.isAxiosError(error)) return fallback;
    const response = error.response;
    if (!response) {
        return 'Keine Verbindung zum Server. Bitte prüfe die Verbindung und versuche es erneut.';
    }
    const data = (response.data ?? {}) as {
        message?: string;
        errors?: Record<string, string[]>;
    };
    switch (response.status) {
        case 403:
            return 'Dafür fehlt dir die Berechtigung.';
        case 404:
            return 'Die Zutat wurde nicht gefunden – vielleicht wurde sie bereits gelöscht.';
        case 419:
            return 'Deine Sitzung ist abgelaufen. Bitte lade die Seite neu und versuche es erneut.';
        case 422:
            // z. B. „Zutat wird in mindestens einem Rezept verwendet“ (Server, deutsch)
            return data.errors?.name?.[0] ?? data.message ?? fallback;
    }
    if (response.status >= 500) {
        return 'Auf dem Server ist ein Fehler aufgetreten. Bitte versuche es später erneut.';
    }
    return data.message ?? fallback;
};

const onSave = async () => {
    const trimmed = localName.value.trim();
    if (!trimmed) return;

    loadingSave.value = true;
    saveError.value = '';
    deleteError.value = '';

    const oldName = props.ingredient.name;

    try {
        const response = await axios.put(
            route('ingredients.update', props.ingredient.id),
            { name: trimmed },
        );

        const updated: IngredientOption = {
            id: response.data.id,
            name: response.data.name,
        };

        emit('updated', { oldName, ingredient: updated });
        emit('close');
    } catch (error) {
        saveError.value = describeError(error, 'Speichern fehlgeschlagen.');
    } finally {
        loadingSave.value = false;
    }
};

const cancelConfirm = async () => {
    confirmMessage.value = null;
    await nextTick();
    deleteButton.value?.focus();
};

/**
 * ADR-002: zuerst ohne `confirmed`. 422 (Zutat in Verwendung) hat auf dem
 * Server Vorrang und wird als Fehler angezeigt; 409 öffnet die Bestätigung.
 * Erst „Endgültig löschen“ sendet mit `confirmed=1`.
 */
const deleteIngredient = async (confirmed: boolean) => {
    loadingDelete.value = true;
    saveError.value = '';
    deleteError.value = '';

    const params: Record<string, number> = {
        ingredient: props.ingredient.id,
    };
    if (confirmed) params.confirmed = 1;

    try {
        await axios.delete(route('ingredients.destroy', params));

        confirmMessage.value = null;
        emit('deleted', {
            id: props.ingredient.id,
            name: props.ingredient.name,
        });
        emit('close');
    } catch (error) {
        const data = axios.isAxiosError(error)
            ? (error.response?.data as
                  | { message?: string; requires_confirmation?: boolean }
                  | undefined)
            : undefined;

        if (
            !confirmed &&
            axios.isAxiosError(error) &&
            error.response?.status === 409 &&
            data?.requires_confirmation
        ) {
            confirmMessage.value =
                data.message ?? 'Bitte bestätige das Löschen.';
            loadingDelete.value = false;
            await nextTick();
            confirmCancelButton.value?.focus();
            return;
        }

        confirmMessage.value = null;
        deleteError.value = describeError(error, 'Löschen fehlgeschlagen.');
    } finally {
        loadingDelete.value = false;
    }
};
</script>

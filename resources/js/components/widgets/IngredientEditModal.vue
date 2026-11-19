<template>
    <!-- F-30 / Gate C2: gemeinsamer Dialog-Baustein (Teleport außerhalb des
         Formulars – Enter sendet es nicht ab; Fokusfalle, Escape, Fokus zurück) -->
    <Dialog
        :open="show"
        title="Zutat bearbeiten"
        size="sm"
        @close="requestClose"
    >
        <div class="flex min-w-0 flex-col gap-1.5">
            <label for="ingredient-name" class="text-sm font-semibold text-ink">
                Name der Zutat
            </label>
            <TextInput
                id="ingredient-name"
                v-model="localName"
                type="text"
                aria-describedby="ingredient-name-hint"
                :disabled="isBusy || !!confirmMessage"
            />
            <p
                id="ingredient-name-hint"
                data-field-hint
                class="text-sm text-ink-muted"
            >
                Umbenennen und Löschen gelten für alle Rezepte mit dieser Zutat.
            </p>
            <p
                v-if="saveError"
                data-field-error
                class="text-sm font-medium text-danger"
                role="alert"
            >
                {{ saveError }}
            </p>
        </div>

        <p
            v-if="deleteError"
            data-field-error
            class="mt-4 rounded-[1.1rem_0.9rem_1.2rem_1rem] bg-danger-surface p-3.5 text-sm font-medium text-danger"
            role="alert"
        >
            {{ deleteError }}
        </p>

        <!-- ADR-002: Bestätigung mit der Meldung des Servers (Name + Anzahl) -->
        <div
            v-if="confirmMessage"
            class="mt-5 rounded-[1.5rem_1.2rem_1.6rem_1.3rem] border-[1.5px] border-danger/40 bg-danger-surface p-4"
            role="group"
            aria-labelledby="ingredient-confirm-text"
        >
            <p
                id="ingredient-confirm-text"
                data-confirm-text
                class="font-medium text-wrap-anywhere text-ink"
            >
                {{ confirmMessage }}
            </p>
            <div
                class="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
            >
                <Button
                    ref="confirmCancelButton"
                    variant="secondary"
                    :disabled="isBusy"
                    @click="cancelConfirm"
                >
                    Abbrechen
                </Button>
                <Button
                    variant="danger"
                    :loading="loadingDelete"
                    :disabled="isBusy"
                    @click="deleteIngredient(true)"
                >
                    Endgültig löschen
                </Button>
            </div>
        </div>

        <template v-if="!confirmMessage" #footer>
            <Button variant="ghost" :disabled="isBusy" @click="emit('close')">
                Abbrechen
            </Button>
            <Button
                ref="deleteButton"
                variant="ghost"
                class="text-danger hover:bg-danger-surface active:bg-danger-surface"
                :loading="loadingDelete"
                :disabled="isBusy"
                @click="deleteIngredient(false)"
            >
                <Trash2 class="size-4" aria-hidden="true" />
                Löschen
            </Button>
            <Button
                :loading="loadingSave"
                :disabled="isBusy || !localName.trim()"
                @click="onSave"
            >
                Speichern
            </Button>
        </template>
    </Dialog>
</template>

<script setup lang="ts">
import Button from '@/components/ui/Button.vue';
import Dialog from '@/components/ui/Dialog.vue';
import TextInput from '@/components/ui/TextInput.vue';
import axios from 'axios';
import { Trash2 } from 'lucide-vue-next';
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
// Baustein `Button`: Fokus über das gerenderte Element
const confirmCancelButton = ref<{ $el: HTMLElement } | null>(null);
const deleteButton = ref<{ $el: HTMLElement } | null>(null);

const isBusy = computed(() => loadingSave.value || loadingDelete.value);

// F-30: Escape, Klick auf den Hintergrund und „Schließen“ (Dialog-Baustein)
// schließen – nicht während eines laufenden Requests. Fokus beim Öffnen auf
// das Namensfeld (erstes Bedienelement), beim Schließen zurück zum Auslöser.
const requestClose = () => {
    if (!isBusy.value) emit('close');
};

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
    deleteButton.value?.$el?.focus();
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
            confirmCancelButton.value?.$el?.focus();
            return;
        }

        confirmMessage.value = null;
        deleteError.value = describeError(error, 'Löschen fehlgeschlagen.');
    } finally {
        loadingDelete.value = false;
    }
};
</script>

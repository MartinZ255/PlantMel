<template>
    <div v-if="show" class="modal-backdrop">
        <div
            class="modal alternatives-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="alternatives-modal-title"
        >
            <h2 id="alternatives-modal-title" class="modal__title">
                Alternativen für: {{ ingredient?.name || 'Zutat' }}
            </h2>

            <p class="modal__hint">
                Wähle links Zutaten aus, die als Alternativen für diese
                Rezeptzutat gelten sollen.
            </p>
            <!-- F-13: Das Modal speichert nichts selbst; gespeichert wird mit dem Formular -->
            <p class="modal__hint alternatives-modal__save-hint">
                Wird mit dem Rezept gespeichert. „Übernehmen“ merkt die Auswahl
                vor, „Abbrechen“ verwirft die Änderungen in diesem Fenster.
            </p>

            <!-- Falls gar keine Zutat ausgewählt ist -->
            <div v-if="!ingredient" class="modal__warning">
                Bitte zuerst eine Zutat in der Zeile eingeben/auswählen.
            </div>

            <!-- Hauptlayout: arbeitet immer auf einem Entwurf -->
            <div v-else class="alternatives-layout">
                <!-- Linke Seite: alle Zutaten mit Suche -->
                <div class="alternatives-panel">
                    <div class="form-field">
                        <label class="form-field__label" for="alt-search"
                            >Zutat suchen</label
                        >
                        <input
                            id="alt-search"
                            v-model="searchTerm"
                            type="text"
                            class="form-field__input"
                            placeholder="z. B. Vollkornpasta"
                        />
                    </div>

                    <div class="alternatives-list">
                        <button
                            v-for="ing in filteredAvailableIngredients"
                            :key="ing.id"
                            type="button"
                            class="alternatives-list__item"
                            :class="{
                                'alternatives-list__item--selected':
                                    selectedAvailableId === ing.id,
                            }"
                            @click="selectedAvailableId = ing.id"
                        >
                            {{ ing.name }}
                        </button>

                        <div
                            v-if="filteredAvailableIngredients.length === 0"
                            class="alternatives-list__empty"
                        >
                            Keine passenden Zutaten gefunden.
                        </div>
                    </div>
                </div>

                <!-- Mitte: Add/Remove -->
                <div class="alternatives-panel alternatives-panel--middle">
                    <button
                        type="button"
                        class="primary-pill-button alternatives-panel__btn"
                        aria-label="Als Alternative hinzufügen"
                        @click="addAlternative"
                        :disabled="!canAdd"
                    >
                        &gt;&gt;
                    </button>
                    <button
                        type="button"
                        class="secondary-pill-button alternatives-panel__btn"
                        aria-label="Alternative entfernen"
                        @click="removeAlternative"
                        :disabled="!canRemove"
                    >
                        &lt;&lt;
                    </button>
                </div>

                <!-- Rechte Seite: ausgewählte Alternativen -->
                <div class="alternatives-panel">
                    <div class="form-field">
                        <div class="form-field__label">
                            Ausgewählte Alternativen
                            <span class="inline-badge">Entwurf</span>
                        </div>
                    </div>

                    <div class="alternatives-list">
                        <button
                            v-for="alt in rightItems"
                            :key="alt.id"
                            type="button"
                            class="alternatives-list__item"
                            :class="{
                                'alternatives-list__item--selected':
                                    selectedAlternativeId === alt.id,
                            }"
                            @click="selectedAlternativeId = alt.id"
                        >
                            {{ alt.alternative.name }}
                        </button>

                        <div
                            v-if="rightItems.length === 0"
                            class="alternatives-list__empty"
                        >
                            Noch keine Alternativzutaten hinterlegt.
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal__actions">
                <button
                    type="button"
                    class="secondary-pill-button"
                    @click="cancel"
                >
                    Abbrechen
                </button>
                <button
                    v-if="ingredient"
                    type="button"
                    class="primary-pill-button"
                    @click="apply"
                >
                    Übernehmen
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';

/**
 * F-13: Das Modal arbeitet immer als Entwurf. Es sendet keine Requests,
 * sondern gibt die gewählten Zutat-IDs per `update-draft` an das
 * Rezeptformular zurück; gespeichert wird beim Absenden des Rezepts
 * (`ingredients[i].alternativeIngredientIds`). Startwert sind die IDs der
 * Formularzeile – beim Bearbeiten also die vom Server gelieferten
 * Alternativen aus den Edit-Props.
 */

interface IngredientRow {
    amount: string;
    name: string;
}

interface IngredientOption {
    id: number;
    name: string;
}

interface AlternativeItem {
    id: number;
    alternative: IngredientOption;
}

const props = defineProps<{
    show: boolean;
    ingredient: IngredientRow | null;
    allIngredients: IngredientOption[];
    baseIngredientId: number | null;
    // IDs der Formularzeile (Startwert der Sitzung)
    draftAlternativeIds?: number[];
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'update-draft', ids: number[]): void;
}>();

const searchTerm = ref('');
const selectedAvailableId = ref<number | null>(null);
const selectedAlternativeId = ref<number | null>(null);

// Entwurf dieser Sitzung; wird erst mit „Übernehmen“ an das Formular gegeben
const draftIds = ref<number[]>([]);

const initDraft = () => {
    searchTerm.value = '';
    selectedAvailableId.value = null;
    selectedAlternativeId.value = null;
    draftIds.value = [...new Set(props.draftAlternativeIds ?? [])];
};

// Beim Öffnen mit dem aktuellen Stand der Zeile beginnen
watch(
    () => props.show,
    (show) => {
        if (show) initDraft();
    },
    { immediate: true },
);

// Verfügbare Zutaten für die linke Liste berechnen
const filteredAvailableIngredients = computed(() => {
    const q = searchTerm.value.toLowerCase().trim();
    const excludeIds = new Set(draftIds.value);

    return props.allIngredients.filter((ing) => {
        if (props.baseIngredientId && ing.id === props.baseIngredientId) {
            return false; // Basiszutat selbst nicht als Alternative
        }
        if (excludeIds.has(ing.id)) {
            return false;
        }
        if (!q) return true;
        return ing.name.toLowerCase().includes(q);
    });
});

// Rechte Seite: gewählte Alternativen mit Namen
const rightItems = computed<AlternativeItem[]>(() =>
    draftIds.value.map((id) => ({
        id,
        alternative: props.allIngredients.find((i) => i.id === id) ?? {
            id,
            name: 'Unbekannte Zutat',
        },
    })),
);

const canAdd = computed(() => !!selectedAvailableId.value);
const canRemove = computed(() => !!selectedAlternativeId.value);

const addAlternative = () => {
    const id = selectedAvailableId.value;
    if (!id) return;

    if (!draftIds.value.includes(id)) {
        draftIds.value = [...draftIds.value, id];
    }
    selectedAvailableId.value = null;
};

const removeAlternative = () => {
    const id = selectedAlternativeId.value;
    if (!id) return;

    draftIds.value = draftIds.value.filter((altId) => altId !== id);
    selectedAlternativeId.value = null;
};

// „Übernehmen“: Entwurf an das Formular geben (gespeichert wird mit dem Rezept)
const apply = () => {
    emit('update-draft', [...draftIds.value]);
    emit('close');
};

// „Abbrechen“: Änderungen dieser Sitzung verwerfen
const cancel = () => {
    initDraft();
    emit('close');
};
</script>

<style scoped>
.modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 40;
}

.modal.alternatives-modal {
    background: var(--cream);
    border-radius: var(--radius-lg, 18px);
    border: 1px solid var(--line-soft);
    box-shadow: var(--shadow-soft);
    padding: 1.5rem;
    max-width: 860px;
    width: 100%;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    overflow: hidden; /* Inhalt wird innerhalb gescrollt */
}

.modal__title {
    margin-bottom: 0.5rem;
    font-size: 1.125rem;
    font-weight: 600;
}

.modal__hint {
    font-size: 0.9rem;
    margin-bottom: 0.75rem;
}

.modal__warning {
    font-size: 0.85rem;
    color: var(--danger);
    margin-bottom: 1rem;
}

.alternatives-layout {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 1rem;
    margin-top: 0.5rem;
    flex: 1;
    min-height: 260px;
    max-height: 100%;
    overflow: hidden; /* Panels selbst scrollen */
}

.alternatives-panel {
    display: flex;
    flex-direction: column;
    min-width: 0;
    min-height: 0; /* erlaubt, dass die Liste flex:1 wirklich schrumpft */
}

.alternatives-panel--middle {
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

.alternatives-panel__btn {
    width: 3.5rem;
}

.alternatives-list {
    border: 1px solid var(--line-soft);
    background: #fff;
    border-radius: 0.5rem;
    padding: 0.25rem;
    flex: 1;
    overflow-y: auto;
    margin-top: 0.5rem;
}

.alternatives-list__item {
    width: 100%;
    text-align: left;
    border: none;
    background: transparent;
    padding: 0.35rem 0.5rem;
    border-radius: 0.375rem;
    font-size: 0.9rem;
    cursor: pointer;
}

.alternatives-list__item:hover {
    background: var(--sand);
}

.alternatives-list__item--selected {
    background: var(--sage);
    font-weight: 500;
}

.alternatives-list__empty {
    font-size: 0.85rem;
    color: var(--text-soft);
    padding: 0.35rem 0.5rem;
}

.modal__actions {
    margin-top: 0.75rem;
    display: flex;
    justify-content: flex-end;
    gap: 2rem;
}

@media (max-width: 720px) {
    /* Panels untereinander, Verschiebe-Buttons als horizontale Zeile */
    .alternatives-layout {
        grid-template-columns: 1fr;
        overflow-y: auto;
    }

    .alternatives-panel--middle {
        flex-direction: row;
        justify-content: center;
    }

    .alternatives-panel__btn {
        transform: rotate(90deg);
    }

    .modal.alternatives-modal {
        max-height: 92vh;
        padding: 1rem;
    }

    .modal__actions {
        gap: 0.75rem;
    }
}
</style>

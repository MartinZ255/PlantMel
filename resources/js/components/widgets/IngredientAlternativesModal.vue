<template>
    <div v-if="show" class="modal-backdrop">
        <div class="modal alternatives-modal">
            <h2 class="modal__title">
                Alternativen für: {{ ingredient?.name || 'Zutat' }}
            </h2>

            <p class="modal__hint">
                Wähle links Zutaten aus, die als Alternativen für diese Rezeptzutat gelten sollen.
            </p>

            <!-- Falls gar keine Zutat ausgewählt ist -->
            <div v-if="!ingredient" class="modal__warning">
                Bitte zuerst eine Zutat in der Zeile eingeben/auswählen.
            </div>

            <!-- Hauptlayout: funktioniert sowohl im Draft- als auch im DB-Modus -->
            <div v-else class="alternatives-layout">
                <!-- Linke Seite: alle Zutaten mit Suche -->
                <div class="alternatives-panel">
                    <div class="form-field">
                        <label class="form-field__label" for="alt-search">Zutat suchen</label>
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
                            :class="{ 'alternatives-list__item--selected': selectedAvailableId === ing.id }"
                            @click="selectedAvailableId = ing.id"
                        >
                            {{ ing.name }}
                        </button>

                        <div v-if="filteredAvailableIngredients.length === 0" class="alternatives-list__empty">
                            Keine passenden Zutaten gefunden.
                        </div>
                    </div>
                </div>

                <!-- Mitte: Add/Remove -->
                <div class="alternatives-panel alternatives-panel--middle">
                    <button
                        type="button"
                        class="primary-pill-button alternatives-panel__btn"
                        @click="addAlternative"
                        :disabled="!canAdd"
                    >
                        &gt;&gt;
                    </button>
                    <button
                        type="button"
                        class="secondary-pill-button alternatives-panel__btn"
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
                            <span v-if="isDbMode" class="inline-badge">gespeichert</span>
                            <span v-else class="inline-badge">Entwurf</span>
                        </div>
                    </div>

                    <div class="alternatives-list">
                        <button
                            v-for="alt in rightItems"
                            :key="alt.id"
                            type="button"
                            class="alternatives-list__item"
                            :class="{ 'alternatives-list__item--selected': selectedAlternativeId === alt.id }"
                            @click="selectedAlternativeId = alt.id"
                        >
                            {{ alt.alternative.name }}
                        </button>

                        <div v-if="rightItems.length === 0" class="alternatives-list__empty">
                            Noch keine Alternativzutaten hinterlegt.
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal__actions">
                <button
                    type="button"
                    class="secondary-pill-button"
                    @click="emit('close')"
                >
                    Schließen
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { route } from 'ziggy-js';

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
    ingredient_id: number;
    alternative_ingredient_id: number;
    alternative: IngredientOption;
}

const props = defineProps<{
    show: boolean;
    ingredient: IngredientRow | null;
    allIngredients: IngredientOption[];
    recipeId?: number | null;
    baseIngredientId: number | null;
    // Draft-IDs aus dem Formular (Create-Mode oder später vereinheitlicht)
    draftAlternativeIds?: number[];
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'update-draft', ids: number[]): void;
}>();

const searchTerm = ref('');
const selectedAvailableId = ref<number | null>(null);
const selectedAlternativeId = ref<number | null>(null);
const selectedAlternatives = ref<AlternativeItem[]>([]);
const loading = ref(false);

// DB- vs. Draft-Modus
const isDbMode = computed(
    () => !!(props.recipeId && props.baseIngredientId),
);

// Draft-State
const draftAlternativeIds = ref<number[]>(props.draftAlternativeIds ?? []);

// Draft von außen aktualisieren, falls Props sich ändern
watch(
    () => props.draftAlternativeIds,
    (val) => {
        if (!isDbMode.value) {
            draftAlternativeIds.value = val ?? [];
        }
    },
    { immediate: true },
);

// Verfügbare Zutaten für die linke Liste berechnen
const filteredAvailableIngredients = computed(() => {
    const q = searchTerm.value.toLowerCase().trim();

    const excludeIds = new Set<number>();

    if (isDbMode.value) {
        selectedAlternatives.value.forEach((alt) => {
            excludeIds.add(alt.alternative_ingredient_id);
        });
    } else {
        draftAlternativeIds.value.forEach((id) => excludeIds.add(id));
    }

    return props.allIngredients
        .filter((ing) => {
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

// Rechte Seite vereinheitlichen
const rightItems = computed<AlternativeItem[]>(() => {
    if (isDbMode.value) {
        return selectedAlternatives.value;
    }

    // Draft-Modus: aus IDs eine „Fake-Liste“ bauen
    return draftAlternativeIds.value.map((id) => {
        const ing = props.allIngredients.find((i) => i.id === id);
        return {
            id, // hier dient id nur als key
            ingredient_id: 0,
            alternative_ingredient_id: id,
            alternative: ing ?? { id, name: 'Unbekannte Zutat' },
        };
    });
});

// Kann hinzugefügt / entfernt werden?
const canAdd = computed(() =>
    isDbMode.value
        ? !!(props.recipeId && props.baseIngredientId && selectedAvailableId.value)
        : !!selectedAvailableId.value,
);

const canRemove = computed(() =>
    isDbMode.value
        ? !!(props.recipeId && props.baseIngredientId && selectedAlternativeId.value)
        : !!selectedAlternativeId.value,
);

// DB-Alternativen laden
const loadAlternativesFromApi = async () => {
    selectedAlternatives.value = [];
    selectedAvailableId.value = null;
    selectedAlternativeId.value = null;

    if (!props.recipeId || !props.baseIngredientId) {
        return;
    }

    try {
        loading.value = true;
        const response = await axios.get(
            route('ingredientAlternatives.index', {
                recipe: props.recipeId,
                ingredient: props.baseIngredientId,
            }),
        );

        selectedAlternatives.value = response.data as AlternativeItem[];
    } catch (error) {
        console.error('Alternativen laden fehlgeschlagen', error);
    } finally {
        loading.value = false;
    }
};

// Draft initialisieren
const initDraftAlternatives = () => {
    selectedAvailableId.value = null;
    selectedAlternativeId.value = null;
    draftAlternativeIds.value = props.draftAlternativeIds ?? [];
};

// Auf show reagieren: beim Öffnen initialisieren
watch(
    () => props.show,
    (show) => {
        if (!show) return;

        if (isDbMode.value) {
            loadAlternativesFromApi();
        } else {
            initDraftAlternatives();
        }
    },
);

// Beim Wechsel der Basiszutat im DB-Modus neu laden
watch(
    () => props.baseIngredientId,
    () => {
        if (props.show && isDbMode.value) {
            loadAlternativesFromApi();
        }
    },
);

const addAlternative = async () => {
    if (!selectedAvailableId.value) return;

    if (isDbMode.value) {
        if (!props.recipeId || !props.baseIngredientId) return;

        try {
            loading.value = true;

            const response = await axios.post(
                route('ingredientAlternatives.store', {
                    recipe: props.recipeId,
                    ingredient: props.baseIngredientId,
                }),
                {
                    alternative_ingredient_id: selectedAvailableId.value,
                },
            );

            const created = response.data as AlternativeItem;
            selectedAlternatives.value.push(created);
            selectedAvailableId.value = null;
        } catch (error) {
            console.error('Alternativzutat anlegen fehlgeschlagen', error);
        } finally {
            loading.value = false;
        }
    } else {
        // Draft-Modus
        if (!draftAlternativeIds.value.includes(selectedAvailableId.value)) {
            draftAlternativeIds.value = [
                ...draftAlternativeIds.value,
                selectedAvailableId.value,
            ];
            emit('update-draft', [...draftAlternativeIds.value]);
        }
        selectedAvailableId.value = null;
    }
};

const removeAlternative = async () => {
    if (!selectedAlternativeId.value) return;

    const id = selectedAlternativeId.value;

    if (isDbMode.value) {
        try {
            loading.value = true;

            await axios.delete(route('ingredientAlternatives.destroy', id));

            selectedAlternatives.value = selectedAlternatives.value.filter(
                (alt) => alt.id !== id,
            );
            selectedAlternativeId.value = null;
        } catch (error) {
            console.error('Alternativzutat löschen fehlgeschlagen', error);
        } finally {
            loading.value = false;
        }
    } else {
        // Draft-Modus
        draftAlternativeIds.value = draftAlternativeIds.value.filter(
            (altId) => altId !== id,
        );
        selectedAlternativeId.value = null;
        emit('update-draft', [...draftAlternativeIds.value]);
    }
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
    background: #fff;
    border-radius: 0.75rem;
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
    color: #b91c1c;
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
    overflow: hidden;  /* Panels selbst scrollen */
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
    border: 1px solid #e5e7eb;
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
    background: #f3f4f6;
}

.alternatives-list__item--selected {
    background: #e5e7eb;
    font-weight: 500;
}

.alternatives-list__empty {
    font-size: 0.85rem;
    color: #6b7280;
    padding: 0.35rem 0.5rem;
}

.modal__actions {
    margin-top: 0.75rem;
    display: flex;
    justify-content: flex-end;
    gap: 2rem;
}
</style>

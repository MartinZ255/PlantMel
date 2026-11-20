<template>
    <!-- F-30 / Gate C2: gemeinsamer Dialog-Baustein (Teleport außerhalb des
         Formulars – Enter sendet es nicht ab; Fokusfalle, Escape, Fokus zurück).
         Escape und Hintergrund-Klick verhalten sich wie „Abbrechen“. -->
    <Dialog
        :open="show"
        :title="`Alternativen für: ${ingredient?.name || 'Zutat'}`"
        description="Wähle Zutaten aus, die als Alternativen für diese Rezeptzutat gelten sollen."
        size="lg"
        @close="cancel"
    >
        <!-- F-13: Das Modal speichert nichts selbst; gespeichert wird mit dem Formular -->
        <p
            class="flex items-start gap-2.5 rounded-[1.25rem_1rem_1.4rem_1.1rem] bg-tint p-3.5 text-sm text-ink"
        >
            <LeafOrnament
                variant="sprout"
                class="mt-0.5 size-5 shrink-0 text-primary"
            />
            <span class="min-w-0">
                Wird mit dem Rezept gespeichert. „Übernehmen“ merkt die Auswahl
                vor, „Abbrechen“ verwirft die Änderungen in diesem Fenster.
            </span>
        </p>

        <!-- Falls gar keine Zutat ausgewählt ist -->
        <p
            v-if="!ingredient"
            class="mt-4 text-sm font-medium text-danger"
            data-field-error
        >
            Bitte zuerst eine Zutat in der Zeile eingeben/auswählen.
        </p>

        <!-- Hauptlayout: arbeitet immer auf einem Entwurf -->
        <div
            v-else
            class="mt-5 grid min-w-0 gap-4 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] sm:gap-5"
        >
            <!-- Alle Zutaten mit Suche -->
            <div data-alternatives-panel class="flex min-w-0 flex-col gap-2">
                <label for="alt-search" class="text-sm font-semibold text-ink">
                    Zutat suchen
                </label>
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-moss"
                        aria-hidden="true"
                    />
                    <input
                        id="alt-search"
                        v-model="searchTerm"
                        type="text"
                        class="block min-h-11 w-full min-w-0 rounded-[0.9rem_0.75rem_0.95rem_0.8rem] border-[1.5px] border-line-strong bg-surface py-2 pr-3.5 pl-11 text-base text-ink placeholder:text-ink-muted hover:border-primary focus-visible:border-primary focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-focus"
                        placeholder="z. B. Vollkornpasta"
                    />
                </div>

                <div :class="listClass">
                    <button
                        v-for="ing in filteredAvailableIngredients"
                        :key="ing.id"
                        type="button"
                        :class="itemClass(selectedAvailableId === ing.id)"
                        :aria-pressed="
                            selectedAvailableId === ing.id ? 'true' : 'false'
                        "
                        @click="selectedAvailableId = ing.id"
                    >
                        <span
                            class="size-2 shrink-0 rounded-[60%_40%_55%_45%]"
                            :class="
                                selectedAvailableId === ing.id
                                    ? 'bg-matcha'
                                    : 'bg-moss'
                            "
                            aria-hidden="true"
                        ></span>
                        <span class="min-w-0 text-wrap-anywhere">{{
                            ing.name
                        }}</span>
                    </button>

                    <p
                        v-if="filteredAvailableIngredients.length === 0"
                        class="px-3 py-2.5 text-sm text-ink-muted"
                    >
                        Keine passenden Zutaten gefunden.
                    </p>
                </div>
            </div>

            <!-- Mitte: hinzufügen / entfernen (mobil zwischen den Listen) -->
            <div
                class="flex items-center justify-center gap-3 sm:flex-col sm:pt-8"
            >
                <Button
                    class="size-12 px-0"
                    aria-label="Als Alternative hinzufügen"
                    title="Als Alternative hinzufügen"
                    :disabled="!canAdd"
                    @click="addAlternative"
                >
                    <ArrowRight
                        class="size-5 rotate-90 sm:rotate-0"
                        aria-hidden="true"
                    />
                </Button>
                <Button
                    variant="secondary"
                    class="size-12 px-0"
                    aria-label="Alternative entfernen"
                    title="Alternative entfernen"
                    :disabled="!canRemove"
                    @click="removeAlternative"
                >
                    <ArrowLeft
                        class="size-5 rotate-90 sm:rotate-0"
                        aria-hidden="true"
                    />
                </Button>
            </div>

            <!-- Ausgewählte Alternativen -->
            <div data-alternatives-panel class="flex min-w-0 flex-col gap-2">
                <p
                    class="flex min-h-[1.25rem] flex-wrap items-center gap-2 text-sm font-semibold text-ink"
                >
                    Ausgewählte Alternativen
                    <Badge tone="accent">Entwurf</Badge>
                </p>

                <div :class="[listClass, 'sm:mt-[3.25rem]']">
                    <button
                        v-for="alt in rightItems"
                        :key="alt.id"
                        type="button"
                        :class="itemClass(selectedAlternativeId === alt.id)"
                        :aria-pressed="
                            selectedAlternativeId === alt.id ? 'true' : 'false'
                        "
                        @click="selectedAlternativeId = alt.id"
                    >
                        <LeafOrnament
                            variant="leaf"
                            class="h-3.5 shrink-0 rotate-[30deg]"
                            :class="
                                selectedAlternativeId === alt.id
                                    ? 'text-matcha'
                                    : 'text-moss'
                            "
                        />
                        <span class="min-w-0 text-wrap-anywhere">{{
                            alt.alternative.name
                        }}</span>
                    </button>

                    <p
                        v-if="rightItems.length === 0"
                        class="px-3 py-2.5 text-sm text-ink-muted"
                    >
                        Noch keine Alternativzutaten hinterlegt.
                    </p>
                </div>
            </div>
        </div>

        <template #footer>
            <Button variant="ghost" @click="cancel">Abbrechen</Button>
            <Button v-if="ingredient" @click="apply">
                <Check class="size-5" aria-hidden="true" />
                Übernehmen
            </Button>
        </template>
    </Dialog>
</template>

<script setup lang="ts">
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Dialog from '@/components/ui/Dialog.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import { ArrowLeft, ArrowRight, Check, Search } from 'lucide-vue-next';
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

// F-30: Fokus beim Öffnen auf die Suche (erstes Bedienelement), Escape und
// Hintergrund-Klick wie „Abbrechen“ – übernimmt der Dialog-Baustein.

const listClass =
    'scrollbar-thin flex max-h-56 min-h-32 flex-col gap-1 overflow-y-auto rounded-[1.25rem_1rem_1.4rem_1.1rem] bg-canvas p-1.5 ring-1 ring-line sm:max-h-64';

const itemClass = (selected: boolean) => [
    'flex min-h-11 w-full items-center gap-2.5 rounded-[0.9rem_0.75rem_0.95rem_0.8rem] px-3 text-left text-base focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-focus',
    selected
        ? 'bg-primary font-semibold text-on-primary'
        : 'text-ink hover:bg-tint',
];

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

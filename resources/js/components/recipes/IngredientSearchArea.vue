<template>
    <!--
        Gate C2: Zutatensuche als schwebende Kapsel (wie die Rezeptliste).
        Filter liegt als aufklappbares Papierblatt direkt darunter (kein
        überlagerndes Panel => kein Überlauf bei 320 px). Filterlogik unverändert.
    -->
    <div class="flex min-w-0 flex-col gap-3">
        <div
            class="flex min-w-0 flex-col gap-2 rounded-[2.25rem_1.75rem_2.5rem_2rem] bg-surface/85 p-2.5 shadow-float ring-1 ring-line/60 backdrop-blur-md sm:flex-row sm:items-center sm:p-3"
        >
            <div class="relative min-w-0 flex-1">
                <label :for="searchId" class="sr-only">Zutat suchen</label>
                <span
                    class="pointer-events-none absolute top-1/2 left-1.5 inline-flex size-9 -translate-y-1/2 items-center justify-center rounded-[58%_42%_52%_48%/46%_54%_46%_54%] bg-forest text-matcha"
                    aria-hidden="true"
                >
                    <Search class="size-4" />
                </span>
                <input
                    :id="searchId"
                    v-model="searchTerm"
                    type="search"
                    autocomplete="off"
                    enterkeyhint="search"
                    placeholder="Zutat suchen …"
                    class="block min-h-12 w-full min-w-0 rounded-[1.4rem_1.1rem_1.5rem_1.2rem] border-[1.5px] border-line-strong bg-surface py-2 pr-4 pl-13 text-base text-ink placeholder:text-ink-muted hover:border-primary focus-visible:border-primary focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-focus"
                    @input="applyFilters"
                />
            </div>
            <button
                ref="filterToggle"
                type="button"
                class="inline-flex min-h-12 shrink-0 items-center justify-center gap-2 pebble-morph border-[1.5px] px-5 text-base font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                :class="
                    showIngredientFilter
                        ? 'border-primary bg-primary text-on-primary hover:bg-primary-hover'
                        : 'border-primary bg-surface text-primary hover:bg-tint'
                "
                :aria-expanded="showIngredientFilter ? 'true' : 'false'"
                :aria-controls="panelId"
                @click="toggleIngredientFilter"
            >
                <SlidersHorizontal class="size-4" aria-hidden="true" />
                Filter
                <span
                    v-if="activeFilterCount"
                    class="inline-flex min-w-6 items-center justify-center rounded-full bg-berry px-1.5 text-xs font-semibold text-white"
                >
                    {{ activeFilterCount }}
                    <span class="sr-only">aktiv</span>
                </span>
            </button>
        </div>

        <!-- Zutaten-Filterblatt -->
        <div
            v-show="showIngredientFilter"
            :id="panelId"
            role="group"
            :aria-labelledby="`${panelId}-title`"
            class="relative min-w-0 overflow-hidden rounded-[2rem_1.5rem_2.25rem_1.75rem] bg-surface p-5 shadow-raised sm:p-6"
            @keydown.esc.stop="closeIngredientFilter(true)"
        >
            <LeafOrnament
                variant="sprig"
                class="pointer-events-none absolute -top-6 -right-4 h-24 rotate-[24deg] text-tint"
            />
            <h2
                :id="`${panelId}-title`"
                class="relative font-display text-xl font-semibold text-ink"
            >
                Nach Zutaten filtern
            </h2>
            <p class="relative mt-1 text-sm text-ink-muted">
                Gib Begriffe an, die im Namen oder in den Kategorien vorkommen
                sollen. Mehrere Begriffe trennst du mit Komma.
            </p>

            <div class="relative mt-4 grid gap-4 sm:grid-cols-2">
                <Field
                    id="includeIngredients"
                    label="Enthaltene Begriffe"
                    v-slot="field"
                >
                    <TextInput
                        v-model="includeIngredients"
                        v-bind="field.inputAttrs"
                        type="text"
                        autocomplete="off"
                        placeholder="z. B. Tomaten, Knoblauch"
                        @keyup.enter="applyFromPanel"
                    />
                </Field>
                <Field
                    id="excludeIngredients"
                    label="Ausschließen"
                    v-slot="field"
                >
                    <TextInput
                        v-model="excludeIngredients"
                        v-bind="field.inputAttrs"
                        type="text"
                        autocomplete="off"
                        placeholder="z. B. Nüsse, Sellerie"
                        @keyup.enter="applyFromPanel"
                    />
                </Field>
            </div>

            <div class="relative mt-4">
                <p
                    :id="`${panelId}-frequent`"
                    class="text-sm font-semibold text-ink"
                >
                    Häufig genutzte Zutaten
                </p>
                <ul
                    class="mt-2 flex flex-wrap gap-2"
                    :aria-labelledby="`${panelId}-frequent`"
                >
                    <li v-for="chip in frequentIngredients" :key="chip">
                        <Chip
                            :aria-label="`${chip} zu den enthaltenen Begriffen hinzufügen`"
                            @click="appendFromPanel(chip)"
                        >
                            {{ chip }}
                        </Chip>
                    </li>
                </ul>
            </div>

            <div
                class="relative mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
            >
                <Button variant="ghost" @click="closeIngredientFilter(true)">
                    Abbrechen
                </Button>
                <Button @click="applyFromPanel">Anwenden</Button>
            </div>
        </div>

        <!-- Kategorie-Chips; nur mit echten Kategorien (F-02) -->
        <div
            v-if="visibleTags.length"
            role="group"
            aria-label="Kategorien"
            class="flex flex-wrap gap-2"
        >
            <Chip
                v-for="tag in visibleTags"
                :key="tag.key"
                :pressed="tag.key === activeTag"
                @click="setActiveTag(tag.key)"
            >
                {{ tag.label }}
            </Chip>
            <Chip
                id="moreFilterToggle"
                :pressed="showTagPanel"
                aria-label="Weitere Kategorien"
                @click="toggleTagPanel"
            >
                …
            </Chip>
        </div>

        <!-- Kategorien-Liste mit Zählern; nur mit echten Daten (F-02) -->
        <ul
            v-if="extraTagStats.length && showTagPanel"
            aria-label="Weitere Kategorien"
            class="grid gap-1 rounded-[1.5rem_1.25rem_1.75rem_1.1rem] bg-surface p-2 shadow-card sm:grid-cols-2"
        >
            <li v-for="tag in extraTagStats" :key="tag.key">
                <button
                    type="button"
                    class="flex min-h-11 w-full items-center justify-between gap-3 rounded-seed px-3 text-left hover:bg-tint focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                    @click="setActiveTag(tag.key)"
                >
                    <span class="min-w-0 text-wrap-anywhere">{{
                        tag.label
                    }}</span>
                    <span class="text-sm font-semibold text-ink-muted">{{
                        tag.count
                    }}</span>
                </button>
            </li>
        </ul>

        <!-- Trefferzahl als Live-Region -->
        <p
            class="self-start rounded-seed bg-tint px-3.5 py-1 text-sm font-semibold text-primary"
            role="status"
            data-ingredient-count
        >
            {{ filteredIngredients.length }} Zutaten gefunden
        </p>
    </div>
</template>

<script setup lang="ts">
import Button from '@/components/ui/Button.vue';
import Chip from '@/components/ui/Chip.vue';
import Field from '@/components/ui/Field.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import TextInput from '@/components/ui/TextInput.vue';
import { Search, SlidersHorizontal } from 'lucide-vue-next';
import { computed, nextTick, ref, useId } from 'vue';

interface TagStat {
    key: string;
    label: string;
    count: number;
}

/**
 * Ingredient-Struktur:
 * - category: string[] (optional; das Backend liefert derzeit nur id und name, F-02)
 */
interface Ingredient {
    id: number;
    name: string;
    category?: string[] | null;
}

interface Props {
    tags?: string[]; // ['alle', 'gemüse', ...] – ohne echte Kategorien leer
    tagStats?: TagStat[]; // Stats für das Kategorien-Panel – ohne echte Daten leer
    ingredients?: Ingredient[]; // komplette Liste aus dem Backend
}

const props = withDefaults(defineProps<Props>(), {
    tags: () => [],
    tagStats: () => [],
    ingredients: () => [],
});

const emit = defineEmits<{
    // komplette Ergebnisliste nach außen geben (für Karten-Grid etc.)
    (e: 'update:results', ingredients: Ingredient[]): void;
    // optional: Filterwerte nach außen reichen (wenn du später serverseitig filtern willst)
    (
        e: 'update:filters',
        payload: {
            searchTerm: string;
            includeIngredients: string;
            excludeIngredients: string;
            activeTag: string;
        },
    ): void;
}>();

const uid = useId();
const searchId = `ingredient-search-${uid}`;
const panelId = `ingredient-filter-${uid}`;
const filterToggle = ref<HTMLButtonElement | null>(null);

// Such-/Filterzustände
const searchTerm = ref('');
const activeTag = ref('alle');

const showIngredientFilter = ref(false);
const showTagPanel = ref(false);

const includeIngredients = ref('');
const excludeIngredients = ref('');

// Nur UI/Hilfe – unabhängig von der DB
const frequentIngredients = ['Tomaten', 'Knoblauch', 'Zwiebeln', 'Paprika'];

// Tags für die Chip-Leiste
const visibleTags = computed(() =>
    props.tags.filter((t) => t !== '...').map((t) => ({ key: t, label: t })),
);

const extraTagStats = computed(() => props.tagStats);

/** Zahl der ausgefüllten Filterfelder (Anzeige am Filter-Knopf) */
const activeFilterCount = computed(
    () =>
        [includeIngredients.value, excludeIngredients.value].filter((v) =>
            v.trim(),
        ).length,
);

// Hilfsfunktionen zur Normalisierung
// fehlende Kategorien als leere Liste behandeln (F-02)
const categoriesOf = (ingredient: Ingredient): string[] =>
    Array.isArray(ingredient.category) ? ingredient.category : [];

const toTokens = (value: string): string[] =>
    value
        .split(',')
        .map((v) => v.trim().toLowerCase())
        .filter(Boolean);

const matchesSearch = (ingredient: Ingredient): boolean => {
    const term = searchTerm.value.trim().toLowerCase();
    if (!term) return true;

    const name = ingredient.name.toLowerCase();
    const categories = categoriesOf(ingredient).map((c) => c.toLowerCase());

    return name.includes(term) || categories.some((c) => c.includes(term));
};

const matchesInclude = (ingredient: Ingredient): boolean => {
    const tokens = toTokens(includeIngredients.value);
    if (!tokens.length) return true;

    const haystack =
        `${ingredient.name} ${categoriesOf(ingredient).join(' ')}`.toLowerCase();

    return tokens.every((token) => haystack.includes(token));
};

const matchesExclude = (ingredient: Ingredient): boolean => {
    const tokens = toTokens(excludeIngredients.value);
    if (!tokens.length) return true;

    const haystack =
        `${ingredient.name} ${categoriesOf(ingredient).join(' ')}`.toLowerCase();

    return !tokens.some((token) => haystack.includes(token));
};

const matchesTag = (ingredient: Ingredient): boolean => {
    const tag = activeTag.value;
    if (!tag || tag === 'alle') return true;

    const t = tag.toLowerCase();

    // Treffer, wenn die aktive Tag-Kategorie in den Kategorien der Zutat vorkommt
    return categoriesOf(ingredient)
        .map((c) => c.toLowerCase())
        .includes(t);
};

// Gefilterte Zutaten auf Basis der vom Parent gelieferten props.ingredients
const filteredIngredients = computed(() =>
    props.ingredients.filter(
        (ing) =>
            matchesSearch(ing) &&
            matchesInclude(ing) &&
            matchesExclude(ing) &&
            matchesTag(ing),
    ),
);

// Panel-Toggles
const toggleIngredientFilter = () => {
    showIngredientFilter.value = !showIngredientFilter.value;
};

/** Schließt das Filterblatt; aus dem Blatt heraus geht der Fokus an den Knopf zurück. */
const closeIngredientFilter = (returnFocus = false) => {
    const wasOpen = showIngredientFilter.value;
    showIngredientFilter.value = false;
    if (returnFocus && wasOpen) nextTick(() => filterToggle.value?.focus());
};

const toggleTagPanel = () => {
    showTagPanel.value = !showTagPanel.value;
};

const setActiveTag = (key: string) => {
    activeTag.value = key;
    applyFilters();
};

const appendIngredient = (name: string) => {
    const current = includeIngredients.value.trim();
    includeIngredients.value = current ? `${current}, ${name}` : name;
    applyFilters();
};

// Filter anwenden + nach außen emittieren
const applyFilters = () => {
    emit('update:filters', {
        searchTerm: searchTerm.value,
        includeIngredients: includeIngredients.value,
        excludeIngredients: excludeIngredients.value,
        activeTag: activeTag.value,
    });

    emit('update:results', filteredIngredients.value);

    closeIngredientFilter();
};

// Aus dem Filterblatt: wie bisher anwenden und schließen, Fokus zurück an den Knopf
const applyFromPanel = () => {
    applyFilters();
    nextTick(() => filterToggle.value?.focus());
};

const appendFromPanel = (name: string) => {
    appendIngredient(name);
    nextTick(() => filterToggle.value?.focus());
};
</script>

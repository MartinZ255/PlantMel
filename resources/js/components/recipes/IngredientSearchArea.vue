<template>
    <section class="content-section">
        <div class="search-area">
            <!-- Suchzeile + Zutaten-Filter -->
            <div class="search-row">
                <input
                    class="search-row__input"
                    type="text"
                    placeholder="Zutat suchen …"
                    v-model="searchTerm"
                    @input="applyFilters"
                />
                <button
                    class="search-row__filter-button"
                    type="button"
                    @click="toggleIngredientFilter"
                >
                    ☰ Filter
                </button>

                <!-- Zutaten-Filterpanel -->
                <div
                    class="filter-panel filter-panel--ingredients"
                    :class="{ 'is-open': showIngredientFilter }"
                >
                    <div class="filter-panel__title">Nach Zutaten filtern</div>
                    <div class="filter-panel__subtitle">
                        Gib Begriffe an, die im Namen oder in den Kategorien vorkommen sollen.
                    </div>

                    <div class="filter-panel__field">
                        <label for="includeIngredients">enthaltene Begriffe</label>
                        <input
                            id="includeIngredients"
                            class="filter-panel__input"
                            type="text"
                            v-model="includeIngredients"
                            placeholder="z.B. Tomaten, Knoblauch"
                            @keyup.enter="applyFilters"
                        />
                    </div>

                    <div class="filter-panel__field">
                        <label for="excludeIngredients">ausschließen</label>
                        <input
                            id="excludeIngredients"
                            class="filter-panel__input"
                            type="text"
                            v-model="excludeIngredients"
                            placeholder="z.B. Nüsse, Sellerie"
                            @keyup.enter="applyFilters"
                        />
                    </div>

                    <div class="filter-panel__field">
                        <span class="filter-panel__subtitle">häufig genutzte Zutaten:</span>
                        <div class="filter-panel__chips">
                            <button
                                v-for="chip in frequentIngredients"
                                :key="chip"
                                type="button"
                                class="filter-panel__chip"
                                @click="appendIngredient(chip)"
                            >
                                {{ chip }}
                            </button>
                        </div>
                    </div>

                    <div class="filter-panel__actions">
                        <button
                            type="button"
                            class="filter-panel__btn filter-panel__btn--ghost"
                            @click="closeIngredientFilter"
                        >
                            Abbrechen
                        </button>
                        <button
                            type="button"
                            class="filter-panel__btn filter-panel__btn--primary"
                            @click="applyFilters"
                        >
                            Anwenden
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tag-Chips; "..." öffnet Kategorien-Panel -->
            <div class="tag-chips">
                <button
                    v-for="tag in visibleTags"
                    :key="tag.key"
                    class="tag-chip"
                    :class="{ 'tag-chip--active': tag.key === activeTag }"
                    type="button"
                    @click="setActiveTag(tag.key)"
                >
                    {{ tag.label }}
                </button>

                <button
                    class="tag-chip"
                    type="button"
                    id="moreFilterToggle"
                    @click="toggleTagPanel"
                >
                    ...
                </button>
            </div>

            <!-- Kategorien-Panel (z.B. gemüse, obst, vegan, glutenfrei, ...) -->
            <div
                class="filter-panel filter-panel--tags"
                :class="{ 'is-open': showTagPanel }"
            >
                <div
                    v-for="tag in extraTagStats"
                    :key="tag.key"
                    class="filter-panel__row"
                    @click="setActiveTag(tag.key)"
                >
                    <span>{{ tag.label }}</span>
                    <span>{{ tag.count }}</span>
                </div>
            </div>

            <!-- kleine Meta-Info, wie viele Zutaten aktuell gefunden wurden -->
            <div class="search-meta">
                {{ filteredIngredients.length }} Zutaten gefunden
            </div>
        </div>
    </section>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';

interface TagStat {
    key: string;
    label: string;
    count: number;
}

/**
 * Ingredient-Struktur:
 * - category: string[]  (Liste von Kategorienamen, z.B. ['gemüse', 'vegan'])
 */
interface Ingredient {
    id: number;
    name: string;
    category: string[];
}

interface Props {
    tags: string[];        // ['alle', 'gemüse', 'obst', 'gewürz', 'vegan', ...]
    tagStats: TagStat[];   // Stats für Panel (vegan, glutenfrei, ...)
    ingredients: Ingredient[]; // komplette Liste aus dem Backend
}

const props = defineProps<Props>();

const emit = defineEmits<{
    // komplette Ergebnisliste nach außen geben (für Karten-Grid etc.)
    (e: 'update:results', ingredients: Ingredient[]): void;
    // optional: Filterwerte nach außen reichen (wenn du später serverseitig filtern willst)
    (e: 'update:filters', payload: {
        searchTerm: string;
        includeIngredients: string;
        excludeIngredients: string;
        activeTag: string;
    }): void;
}>();

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
    props.tags
        .filter((t) => t !== '...')
        .map((t) => ({ key: t, label: t }))
);

const extraTagStats = computed(() => props.tagStats);

// Hilfsfunktionen zur Normalisierung
const toTokens = (value: string): string[] =>
    value
        .split(',')
        .map((v) => v.trim().toLowerCase())
        .filter(Boolean);

const matchesSearch = (ingredient: Ingredient): boolean => {
    const term = searchTerm.value.trim().toLowerCase();
    if (!term) return true;

    const name = ingredient.name.toLowerCase();
    const categories = ingredient.category.map((c) => c.toLowerCase());

    return (
        name.includes(term) ||
        categories.some((c) => c.includes(term))
    );
};

const matchesInclude = (ingredient: Ingredient): boolean => {
    const tokens = toTokens(includeIngredients.value);
    if (!tokens.length) return true;

    const haystack =
        `${ingredient.name} ${ingredient.category.join(' ')}`.toLowerCase();

    return tokens.every((token) => haystack.includes(token));
};

const matchesExclude = (ingredient: Ingredient): boolean => {
    const tokens = toTokens(excludeIngredients.value);
    if (!tokens.length) return true;

    const haystack =
        `${ingredient.name} ${ingredient.category.join(' ')}`.toLowerCase();

    return !tokens.some((token) => haystack.includes(token));
};

const matchesTag = (ingredient: Ingredient): boolean => {
    const tag = activeTag.value;
    if (!tag || tag === 'alle') return true;

    const t = tag.toLowerCase();

    // Treffer, wenn die aktive Tag-Kategorie in den Kategorien der Zutat vorkommt
    return ingredient.category
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
            matchesTag(ing)
    )
);

// Panel-Toggles
const toggleIngredientFilter = () => {
    showIngredientFilter.value = !showIngredientFilter.value;
};

const closeIngredientFilter = () => {
    showIngredientFilter.value = false;
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
</script>

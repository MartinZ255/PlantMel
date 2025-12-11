<template>
    <section class="content-section">
        <div class="search-area">
            <!-- Suchzeile + Zutaten-Filter -->
            <div class="search-row">
                <input
                    class="search-row__input"
                    type="text"
                    placeholder="Rezptname eingeben..."
                    v-model="searchTerm"
                    @keyup="emitFilters"
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
                        Gib Zutaten an, die enthalten sein sollen.
                    </div>

                    <div class="filter-panel__field">
                        <label for="includeIngredients">enthaltene Zutaten</label>
                        <input
                            id="includeIngredients"
                            class="filter-panel__input"
                            type="text"
                            v-model="includeIngredients"
                            placeholder="z.B. Tomaten, Knoblauch"
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
                    :class="{ 'tag-chip--active': isTagActive(tag.key) }"
                    type="button"
                    @click="toggleTag(tag.key)"
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

            <!-- Kategorien-Panel (scharf, schnell, glutenfrei, ...) -->
            <div
                class="filter-panel filter-panel--tags"
                :class="{ 'is-open': showTagPanel }"
            >
                <div
                    v-for="tag in extraTagStats"
                    :key="tag.key"
                    class="filter-panel__row"
                    @click="toggleTag(tag.key)"
                >
                    <span>{{ tag.label }}</span>
                    <span>{{ tag.count }}</span>
                </div>
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

interface Filters {
    search?: string | null;
    includeIngredients?: string[];
    excludeIngredients?: string[];
    tags?: string[]; // mehrere aktive Tags
}

interface Props {
    tags: string[];        // ['alle', 'Dessert', 'Low Carb', ...]
    tagStats: TagStat[];   // Stats für Panel (scharf, schnell, glutenfrei, ...)
    initialFilters?: Filters;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:filters', payload: {
        search: string;
        includeIngredients: string[];
        excludeIngredients: string[];
        tags: string[];
    }): void;
}>();

// State aus Initial-Filtern vorbelegen
const searchTerm = ref(props.initialFilters?.search ?? '');
const activeTags = ref<string[]>(props.initialFilters?.tags ?? []);

const includeIngredients = ref(
    (props.initialFilters?.includeIngredients ?? []).join(', ')
);
const excludeIngredients = ref(
    (props.initialFilters?.excludeIngredients ?? []).join(', ')
);

const showIngredientFilter = ref(false);
const showTagPanel = ref(false);

const frequentIngredients = ['Tomaten', 'Knoblauch', 'Zwiebeln', 'Paprika'];

const visibleTags = computed(() =>
    props.tags
        .filter((t) => t !== '...')
        .map((t) => ({ key: t, label: t }))
);

const extraTagStats = computed(() => props.tagStats);

const toggleIngredientFilter = () => {
    showIngredientFilter.value = !showIngredientFilter.value;
};

const closeIngredientFilter = () => {
    showIngredientFilter.value = false;
};

const toggleTagPanel = () => {
    showTagPanel.value = !showTagPanel.value;
};

const parseIngredientList = (value: string): string[] =>
    value
        .split(',')
        .map((v) => v.trim())
        .filter((v) => v.length > 0);

const isTagActive = (key: string): boolean => {
    return activeTags.value.includes(key);
};

const toggleTag = (key: string) => {
    // Optional: spezieller "alle"-Tag, der alle anderen zurücksetzt
    if (key === 'alle') {
        activeTags.value = [];
        emitFilters();
        return;
    }

    const current = activeTags.value;
    if (current.includes(key)) {
        activeTags.value = current.filter((t) => t !== key);
    } else {
        activeTags.value = [...current, key];
    }
    emitFilters();
};

const emitFilters = () => {
    emit('update:filters', {
        search: searchTerm.value.trim(),
        includeIngredients: parseIngredientList(includeIngredients.value),
        excludeIngredients: parseIngredientList(excludeIngredients.value),
        tags: activeTags.value,
    });
};

const appendIngredient = (name: string) => {
    const current = includeIngredients.value.trim();
    includeIngredients.value = current ? `${current}, ${name}` : name;
};

const applyFilters = () => {
    emitFilters();
    closeIngredientFilter();
};
</script>

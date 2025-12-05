<template>
    <section class="content-section">
        <div class="search-area">
            <!-- Suchzeile + Zutaten-Filter -->
            <div class="search-row">
                <input
                    class="search-row__input"
                    type="text"
                    placeholder="Rezeptname, Zutat ..."
                    v-model="searchTerm"
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

            <!-- Kategorien-Panel (scharf, schnell, glutenfrei, ...) -->
            <div
                class="filter-panel filter-panel--tags"
                :class="{ 'is-open': showTagPanel }"
            >
                <div
                    v-for="tag in extraTagStats"
                    :key="tag.key"
                    class="filter-panel__row"
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

interface Props {
    tags: string[];        // ['alle', 'scharf', 'schnell', 'glutenfrei', 'vegan']
    tagStats: TagStat[];   // Stats für Panel (scharf, schnell, glutenfrei, ...)
}

const props = defineProps<Props>();

const searchTerm = ref('');
const activeTag = ref('alle');

const showIngredientFilter = ref(false);
const showTagPanel = ref(false);

const includeIngredients = ref('');
const excludeIngredients = ref('');

const frequentIngredients = ['Tomaten', 'Knoblauch', 'Zwiebeln', 'Paprika'];

const visibleTags = computed(() =>
    props.tags.filter((t) => t !== '...')
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

const setActiveTag = (key: string) => {
    activeTag.value = key;
};

const appendIngredient = (name: string) => {
    const current = includeIngredients.value.trim();
    includeIngredients.value = current ? `${current}, ${name}` : name;
};

const applyFilters = () => {
    // Hier später: Emit eines Events an die Seite (z.B. 'update:filters')
    closeIngredientFilter();
};
</script>

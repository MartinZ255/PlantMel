<template>
    <div class="flex flex-col gap-4">
        <!-- Suchzeile -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-stretch">
            <div class="relative min-w-0 flex-1">
                <!-- F-44: echtes Label, Suche entprellt und nur bei Textänderung -->
                <label for="recipe-search" class="sr-only">
                    Rezepte nach Namen durchsuchen
                </label>
                <span
                    class="pointer-events-none absolute top-1/2 left-2 inline-flex size-10 -translate-y-1/2 items-center justify-center rounded-[58%_42%_52%_48%/46%_54%_46%_54%] bg-forest text-matcha"
                    aria-hidden="true"
                >
                    <Search class="size-5" />
                </span>
                <input
                    id="recipe-search"
                    v-model="searchTerm"
                    type="search"
                    enterkeyhint="search"
                    autocomplete="off"
                    placeholder="Rezeptname eingeben …"
                    class="block min-h-14 w-full min-w-0 rounded-[1.6rem_1.2rem_1.7rem_1.3rem] border-[1.5px] border-line-strong bg-surface py-3 pr-4 pl-15 text-base text-ink placeholder:text-ink-muted hover:border-primary focus-visible:border-primary focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-focus sm:text-lg"
                    @keydown.enter.prevent="flushSearch"
                />
            </div>
            <button
                ref="filterButton"
                type="button"
                class="inline-flex min-h-14 shrink-0 items-center justify-center gap-2 pebble-morph border-[1.5px] px-6 text-base font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                :class="
                    isPanelOpen
                        ? 'border-forest bg-forest text-on-forest'
                        : 'border-primary bg-surface text-primary hover:bg-tint'
                "
                :aria-expanded="
                    isDesktop ? (isPanelOpen ? 'true' : 'false') : undefined
                "
                :aria-controls="isDesktop ? 'recipe-filter-panel' : undefined"
                :aria-haspopup="isDesktop ? undefined : 'dialog'"
                @click="togglePanel"
            >
                <SlidersHorizontal class="size-5" aria-hidden="true" />
                Filter
                <span
                    v-if="activeFilterCount > 0"
                    class="inline-flex min-w-6 items-center justify-center rounded-[58%_42%_52%_48%/46%_54%_46%_54%] bg-berry px-1.5 text-sm text-white"
                >
                    {{ activeFilterCount }}
                    <span class="sr-only">aktiv</span>
                </span>
            </button>
        </div>

        <!-- Filterbereich: ab md aufklappbar im Fluss -->
        <section
            v-if="isDesktop"
            v-show="isPanelOpen"
            id="recipe-filter-panel"
            aria-labelledby="recipe-filter-title"
            class="rounded-[1.75rem_1.5rem_2rem_1.25rem] bg-canvas p-5 ring-1 ring-line/70 lg:p-7"
            @keydown.esc.stop="closePanel(true)"
        >
            <h2
                id="recipe-filter-title"
                class="mb-4 font-display text-2xl font-semibold text-ink"
            >
                Filter
            </h2>
            <RecipeFilterFields
                v-model:include="includeIngredients"
                v-model:exclude="excludeIngredients"
                :categories="categoryOptions"
                :suggestions="frequentIngredients"
                @toggle-category="toggleTag"
                @add-suggestion="appendIngredient"
                @apply="applyFilters"
            />
            <div
                class="mt-6 flex flex-col-reverse gap-2 border-t border-line pt-5 sm:flex-row sm:justify-end"
            >
                <Button variant="ghost" @click="closePanel(true)">
                    Schließen
                </Button>
                <Button @click="applyFilters">Zutatenfilter anwenden</Button>
            </div>
        </section>

        <!-- mobil: als Panel (Dialog) -->
        <Dialog
            v-else
            :open="isPanelOpen"
            title="Filter"
            description="Kategorien wirken sofort, Zutaten nach „Anwenden“."
            close-label="Filter schließen"
            @close="closePanel()"
        >
            <RecipeFilterFields
                v-model:include="includeIngredients"
                v-model:exclude="excludeIngredients"
                :categories="categoryOptions"
                :suggestions="frequentIngredients"
                @toggle-category="toggleTag"
                @add-suggestion="appendIngredient"
                @apply="applyFilters"
            />
            <template #footer>
                <Button variant="ghost" @click="closePanel()">Schließen</Button>
                <Button @click="applyFilters">Zutatenfilter anwenden</Button>
            </template>
        </Dialog>

        <!-- F-44: aktive Filter als entfernbare Chips -->
        <div
            v-if="activeChips.length"
            class="flex flex-wrap items-center gap-2 px-1"
        >
            <span class="text-sm font-medium text-ink-muted"
                >Aktive Filter:</span
            >
            <ul class="contents" aria-label="Aktive Filter">
                <li
                    v-for="chip in activeChips"
                    :key="chip.key"
                    class="max-w-full"
                >
                    <Chip
                        removable
                        :remove-label="`Filter entfernen: ${chip.label}`"
                        @click="removeChip(chip)"
                    >
                        {{ chip.label }}
                    </Chip>
                </li>
            </ul>
            <button
                type="button"
                class="min-h-9 rounded-seed px-2 text-sm font-semibold text-primary underline decoration-berry decoration-2 underline-offset-4 hover:text-primary-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                @click="reset"
            >
                Alle Filter entfernen
            </button>
        </div>
    </div>
</template>

<script setup lang="ts">
import RecipeFilterFields from '@/components/recipes/RecipeFilterFields.vue';
import Button from '@/components/ui/Button.vue';
import Chip from '@/components/ui/Chip.vue';
import Dialog from '@/components/ui/Dialog.vue';
import { Search, SlidersHorizontal } from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';

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
    tags: string[]; // alle Kategorienamen
    tagStats: TagStat[]; // Anzahl Rezepte je Kategorie
    initialFilters?: Filters;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (
        e: 'update:filters',
        payload: {
            search: string;
            includeIngredients: string[];
            excludeIngredients: string[];
            tags: string[];
        },
    ): void;
}>();

// State aus Initial-Filtern vorbelegen
const searchTerm = ref(props.initialFilters?.search ?? '');
const activeTags = ref<string[]>([...(props.initialFilters?.tags ?? [])]);

const includeIngredients = ref(
    (props.initialFilters?.includeIngredients ?? []).join(', '),
);
const excludeIngredients = ref(
    (props.initialFilters?.excludeIngredients ?? []).join(', '),
);

const frequentIngredients = ['Tomaten', 'Knoblauch', 'Zwiebeln', 'Paprika'];

// --- Panel: ab md im Fluss, darunter als Dialog ---------------------------
const DESKTOP_QUERY = '(min-width: 48rem)';
const isDesktop = ref(true);
const isPanelOpen = ref(false);
const filterButton = ref<HTMLButtonElement | null>(null);
let mediaQuery: MediaQueryList | null = null;
const onMediaChange = (e: MediaQueryListEvent) => {
    isDesktop.value = e.matches;
};

onMounted(() => {
    mediaQuery = window.matchMedia(DESKTOP_QUERY);
    isDesktop.value = mediaQuery.matches;
    mediaQuery.addEventListener('change', onMediaChange);
});

const togglePanel = () => {
    isPanelOpen.value = !isPanelOpen.value;
};

const closePanel = (restoreFocus = false) => {
    isPanelOpen.value = false;
    if (restoreFocus) nextTick(() => filterButton.value?.focus());
};

// --- Kategorien -------------------------------------------------------------
const countByLabel = computed(
    () => new Map(props.tagStats.map((t) => [t.label, t.count])),
);

const categoryOptions = computed(() =>
    props.tags
        .filter((t) => t !== '...' && t !== 'alle')
        .map((t) => ({
            key: t,
            label: t,
            count: countByLabel.value.get(t) ?? null,
            active: activeTags.value.includes(t),
        })),
);

const parseIngredientList = (value: string): string[] =>
    value
        .split(',')
        .map((v) => v.trim())
        .filter((v) => v.length > 0);

const toggleTag = (key: string) => {
    const current = activeTags.value;
    activeTags.value = current.includes(key)
        ? current.filter((t) => t !== key)
        : [...current, key];
    emitFilters();
};

// --- Aktive Filter (vom Server bestätigt) --------------------------------------
type ActiveChip = {
    key: string;
    label: string;
    kind: 'search' | 'tag' | 'include' | 'exclude';
    value: string;
};

const activeChips = computed<ActiveChip[]>(() => {
    const f = props.initialFilters ?? {};
    const chips: ActiveChip[] = [];
    const search = (f.search ?? '').trim();
    if (search)
        chips.push({
            key: 'search',
            label: `Suche: „${search}“`,
            kind: 'search',
            value: search,
        });
    for (const t of f.tags ?? [])
        chips.push({ key: `tag-${t}`, label: t, kind: 'tag', value: t });
    for (const i of f.includeIngredients ?? [])
        chips.push({
            key: `inc-${i}`,
            label: `mit ${i}`,
            kind: 'include',
            value: i,
        });
    for (const e of f.excludeIngredients ?? [])
        chips.push({
            key: `exc-${e}`,
            label: `ohne ${e}`,
            kind: 'exclude',
            value: e,
        });
    return chips;
});

const activeFilterCount = computed(
    () => activeChips.value.filter((c) => c.kind !== 'search').length,
);

const removeChip = (chip: ActiveChip) => {
    if (chip.kind === 'search') {
        searchTerm.value = '';
    } else if (chip.kind === 'tag') {
        activeTags.value = activeTags.value.filter((t) => t !== chip.value);
    } else {
        const model =
            chip.kind === 'include' ? includeIngredients : excludeIngredients;
        model.value = parseIngredientList(model.value)
            .filter((v) => v !== chip.value)
            .join(', ');
    }
    emitFilters();
};

// --- Suche (entprellt, F-44) -------------------------------------------------
// Zuletzt gesendeter Suchtext: nur echte Änderungen lösen eine Anfrage aus
let lastSearch = (props.initialFilters?.search ?? '').trim();
let searchTimer: ReturnType<typeof setTimeout> | null = null;
const SEARCH_DEBOUNCE_MS = 300;

const clearSearchTimer = () => {
    if (searchTimer) {
        clearTimeout(searchTimer);
        searchTimer = null;
    }
};

const flushSearch = () => {
    clearSearchTimer();
    if (searchTerm.value.trim() === lastSearch) return;
    emitFilters();
};

watch(searchTerm, () => {
    clearSearchTimer();
    searchTimer = setTimeout(flushSearch, SEARCH_DEBOUNCE_MS);
});

onBeforeUnmount(() => {
    clearSearchTimer();
    mediaQuery?.removeEventListener('change', onMediaChange);
});

/** Alle Filter leeren und neu laden (Leerzustand „Filter zurücksetzen“). */
const reset = () => {
    clearSearchTimer();
    searchTerm.value = '';
    activeTags.value = [];
    includeIngredients.value = '';
    excludeIngredients.value = '';
    // Der Watcher auf searchTerm plant danach zwar noch einmal, sendet aber
    // nichts, weil sich der Text gegenüber lastSearch nicht mehr ändert
    emitFilters();
};

defineExpose({ reset });

const emitFilters = () => {
    clearSearchTimer();
    lastSearch = searchTerm.value.trim();
    emit('update:filters', {
        search: searchTerm.value.trim(),
        includeIngredients: parseIngredientList(includeIngredients.value),
        excludeIngredients: parseIngredientList(excludeIngredients.value),
        tags: activeTags.value,
    });
};

const appendIngredient = (name: string) => {
    const current = parseIngredientList(includeIngredients.value);
    if (current.includes(name)) return;
    includeIngredients.value = [...current, name].join(', ');
};

const applyFilters = () => {
    emitFilters();
    if (!isDesktop.value) closePanel();
};
</script>

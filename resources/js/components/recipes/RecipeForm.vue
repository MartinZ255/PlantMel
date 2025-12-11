<template>
    <form class="recipe-form" @submit.prevent="submit">
        <div class="recipe-form__grid">
            <!-- Stammdaten links -->
            <div class="recipe-form__section">
                <h2 class="recipe-form__section-title">Stammdaten</h2>

                <div class="form-field">
                    <label class="form-field__label" for="name">Titel</label>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="form-field__input"
                        placeholder="z. B. Cremige One-Pot Pasta mit Spinat"
                    />
                    <p v-if="form.errors.name" class="form-field__error">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="form-field">
                    <label class="form-field__label" for="description">Kurzbeschreibung</label>
                    <textarea
                        id="description"
                        v-model="form.description"
                        class="form-field__textarea"
                        rows="2"
                        placeholder="Kurze Beschreibung, wann und wofür das Rezept ideal ist …"
                    ></textarea>
                    <p v-if="form.errors.description" class="form-field__error">
                        {{ form.errors.description }}
                    </p>
                </div>

                <div class="form-row-split">
                    <div class="form-field">
                        <label class="form-field__label" for="cook_time">Zubereitungsdauer (Min.)</label>
                        <input
                            id="cook_time"
                            v-model="form.duration_minutes"
                            type="number"
                            min="0"
                            class="form-field__input form-field__input--sm"
                            placeholder="20"
                        />
                    </div>

                    <div class="form-field">
                        <label class="form-field__label" for="servings">Portionen</label>
                        <input
                            id="servings"
                            v-model="form.servings"
                            type="number"
                            min="1"
                            class="form-field__input form-field__input--sm"
                            placeholder="2"
                        />
                    </div>
                </div>

                <!-- Sternebewertung pro Dimension -->
                <div class="recipe-form__ratings">
                    <div class="form-field">
                        <div class="recipe-form__section-title">
                            Bewertung
                        </div>
                    </div>

                    <div class="rating-grid">
                        <div
                            v-for="dimension in ratingDimensions"
                            :key="dimension.id"
                            class="rating-row"
                        >
                            <div class="rating-row__label">
                                <div class="rating-row__name">
                                    {{ dimension.name }}
                                </div>
                                <div
                                    v-if="dimension.description"
                                    class="rating-row__hint"
                                >
                                    {{ dimension.description }}
                                </div>
                            </div>

                            <div class="rating-stars-input">
                                <button
                                    v-for="star in 5"
                                    :key="star"
                                    type="button"
                                    class="rating-star-btn"
                                    :class="{
                                        'rating-star-btn--active':
                                            (form.ratings[dimension.id] ?? 0) >= star,
                                    }"
                                    @click="setRating(dimension.id, star)"
                                >
                                    ★
                                </button>
                                <span
                                    v-if="form.ratings[dimension.id]"
                                    class="rating-stars-input__value"
                                >
                                    {{ form.ratings[dimension.id] }} / 5
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Meta rechts -->
            <div class="recipe-form__section">
                <h2 class="recipe-form__section-title">Meta & Tags</h2>

                <!-- Bild-Upload -->
                <div class="form-field">
                    <label class="form-field__label" for="image">
                        Bild hochladen
                    </label>
                    <input
                        id="image"
                        type="file"
                        accept="image/*"
                        class="form-field__input"
                        @change="onImageChange"
                    />
                    <p class="form-field__hint">
                        Optional – wird z. B. für die Vorschaukarte oder Detailseite verwendet.
                    </p>
                    <p v-if="form.errors.image" class="form-field__error">
                        {{ form.errors.image }}
                    </p>
                </div>

                <div class="form-field">
                    <label class="form-field__label" for="source">Quelle</label>
                    <input
                        id="source"
                        v-model="form.source"
                        type="text"
                        class="form-field__input"
                        placeholder="z. B. Eigene Sammlung, Blog, Kochbuch …"
                    />
                </div>

                <!-- TAGS -->
                <div class="form-field">
                    <div class="form-field__label">
                        Tags
                        <span class="inline-badge">optional</span>
                    </div>
                    <div class="chips-input">
                        <div class="chips-input__chips">
                            <button
                                v-for="category in categoryOptions"
                                :key="category.id"
                                type="button"
                                class="tag-chip"
                                :class="{ 'tag-chip--active': form.tags.includes(category.name) }"
                                @click="toggleTag(category.name)"
                                @dblclick.stop="openTagEditModal(category)"
                            >
                                {{ category.name }}
                            </button>
                        </div>
                        <input
                            v-model="tagDraft"
                            type="text"
                            class="chips-input__input"
                            placeholder="Weitere Tags mit Enter hinzufügen …"
                            @keydown.enter.prevent="addTagFromDraft"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Zutaten -->
        <div class="recipe-form__section recipe-form__section--wide">
            <h2 class="recipe-form__section-title">Zutaten</h2>
            <p class="recipe-form__section-hint">
                Jede Zeile entspricht einer Zutat. Menge links, Bezeichnung rechts.
            </p>

            <div class="ingredients-builder">
                <div
                    v-for="(ingredient, index) in form.ingredients"
                    :key="index"
                    class="ingredients-builder__row"
                >
                    <input
                        v-model="ingredient.amount"
                        type="text"
                        class="form-field__input ingredients-builder__amount"
                        placeholder="z. B. 200 g"
                    />

                    <!-- Autocomplete-Wrapper für den Zutaten-Namen -->
                    <div class="ingredients-autocomplete">
                        <input
                            v-model="ingredient.name"
                            type="text"
                            class="form-field__input ingredients-builder__name"
                            placeholder="z. B. Pasta (Penne)"
                            @focus="openIngredientDropdown(index)"
                            @input="openIngredientDropdown(index)"
                        />

                        <div
                            v-if="ingredientDropdownOpenIndex === index"
                            class="ingredients-dropdown"
                        >
                            <button
                                v-for="option in getIngredientOptions(index)"
                                :key="option.id"
                                type="button"
                                class="ingredients-dropdown__item"
                                @mousedown.prevent="selectIngredient(index, option.name)"
                            >
                                {{ option.name }}
                            </button>
                        </div>
                    </div>

                    <!-- + oder Stift abhängig davon, ob Zutat existiert -->
                    <button
                        v-if="ingredient.name.trim() && !findIngredientByName(ingredient.name)"
                        type="button"
                        class="secondary-pill-button ingredients-builder__action"
                        @click="createIngredientFromRow(index)"
                    >
                        +
                    </button>

                    <button
                        v-else-if="findIngredientByName(ingredient.name)"
                        type="button"
                        class="secondary-pill-button ingredients-builder__action"
                        @click="openEditIngredientModal(findIngredientByName(ingredient.name)!)"
                        aria-label="Zutat bearbeiten"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="size-6"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"
                            />
                        </svg>
                    </button>

                    <!-- Alternatives-Icon: Buch -->
                    <button
                        type="button"
                        class="secondary-pill-button ingredients-builder__action"
                        @click="openIngredientAlternatives(index)"
                        aria-label="Alternativzutaten bearbeiten"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="size-6"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"
                            />
                        </svg>

                    </button>

                    <button
                        v-if="form.ingredients.length > 1"
                        type="button"
                        class="secondary-pill-button ingredients-builder__remove"
                        @click="removeIngredient(index)"
                    >
                        entfernen
                    </button>
                </div>

                <button
                    type="button"
                    class="primary-pill-button ingredients-builder__add"
                    @click="addIngredient"
                >
                    + weitere Zutat
                </button>
            </div>
        </div>

        <!-- Zubereitungsschritte -->
        <div class="recipe-form__section recipe-form__section--wide">
            <h2 class="recipe-form__section-title">Zubereitung</h2>
            <p class="recipe-form__section-hint">
                Die Reihenfolge der Schritte entspricht der Darstellung im Rezept.
            </p>

            <ol class="steps-builder">
                <li
                    v-for="(step, index) in form.steps"
                    :key="index"
                    class="steps-builder__row"
                >
                    <div class="steps-builder__badge">
                        {{ index + 1 }}
                    </div>
                    <textarea
                        v-model="form.steps[index]"
                        rows="2"
                        class="form-field__textarea steps-builder__textarea"
                        placeholder="Beschreibe hier die Schritte …"
                    ></textarea>

                    <button
                        v-if="form.steps.length > 1"
                        type="button"
                        class="secondary-pill-button steps-builder__remove"
                        @click="removeStep(index)"
                    >
                        entfernen
                    </button>
                </li>
            </ol>

            <button
                type="button"
                class="primary-pill-button steps-builder__add"
                @click="addStep"
            >
                + weiteren Schritt hinzufügen
            </button>
        </div>

        <!-- Notizen -->
        <div class="recipe-form__section recipe-form__section--wide">
            <h2 class="recipe-form__section-title">Notizen & Varianten</h2>
            <textarea
                v-model="form.notes"
                rows="3"
                class="form-field__textarea"
                placeholder="Optionale Hinweise, Varianten, Aufbewahrungstipps …"
            ></textarea>
        </div>

        <!-- Aktionen unten -->
        <div class="form-actions">
            <div class="form-actions__left">
                <span class="form-actions__meta">
                    Felder können jederzeit später bearbeitet werden.
                </span>
            </div>
            <div class="form-actions__right">
                <button
                    type="button"
                    class="secondary-pill-button"
                    @click="resetForm"
                    :disabled="form.processing"
                >
                    Zurücksetzen
                </button>
                <button
                    type="submit"
                    class="primary-pill-button"
                    :disabled="form.processing"
                >
                    {{ mode === 'edit' ? 'Rezept aktualisieren' : 'Rezept speichern' }}
                </button>
            </div>
        </div>

        <IngredientEditModal
            v-if="ingredientBeingEdited"
            :show="isIngredientModalOpen"
            :ingredient="ingredientBeingEdited"
            @close="isIngredientModalOpen = false"
            @updated="onIngredientUpdated"
            @deleted="onIngredientDeleted"
        />

        <TagEditModal
            v-if="tagBeingEdited"
            :show="isTagModalOpen"
            :category="tagBeingEdited"
            @close="isTagModalOpen = false"
            @updated="onTagUpdated"
            @deleted="onTagDeleted"
        />

        <IngredientAlternativesModal
            v-if="ingredientForAlternatives"
            :show="isAlternativesModalOpen"
            :ingredient="ingredientForAlternatives"
            :all-ingredients="ingredientOptions"
            :recipe-id="props.recipe?.id ?? null"
            :base-ingredient-id="ingredientForAlternatives? (findIngredientByName(ingredientForAlternatives.name)?.id ?? null): null"
            :draft-alternative-ids="ingredientAlternativesIndex !== null? (form.ingredients[ingredientAlternativesIndex]?.alternativeIngredientIds ?? []): []"
            @update-draft="(ids) => {
                if (ingredientAlternativesIndex !== null) {
                    form.ingredients[ingredientAlternativesIndex].alternativeIngredientIds = ids;
                }
            }"
            @close="isAlternativesModalOpen = false"
        />

    </form>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { route } from 'ziggy-js';
import IngredientEditModal from '@/components/widgets/IngredientEditModal.vue';
import TagEditModal from '@/components/widgets/TagEditModal.vue';
import IngredientAlternativesModal from '@/components/widgets/IngredientAlternativesModal.vue';

interface RatingDimension {
    id: number;
    name: string;
    description?: string | null;
}

interface Category {
    id: number;
    name: string;
}

interface IngredientOption {
    id: number;
    name: string;
    alternativeIngredientIds?: number[];
}

interface IngredientRow {
    amount: string;
    name: string;
    alternativeIngredientIds?: number[];
}

interface RecipeFormInput {
    id?: number;
    name?: string;
    description?: string;
    duration_minutes?: string | number | null;
    servings?: string | number | null;
    source?: string;
    tags?: string[];
    ingredients?: IngredientRow[];
    steps?: string[];
    notes?: string;
    ratings?: Record<number, number | null>;
}

const props = defineProps<{
    mode: 'create' | 'edit';
    ratingDimensions: RatingDimension[];
    categories: Category[];
    ingredients: IngredientOption[];
    recipe?: RecipeFormInput | null;
    submitRoute: string;
    submitMethod: 'post' | 'put' | 'patch';
}>();

const ingredientOptions = ref<IngredientOption[]>(props.ingredients ?? []);
const ingredientBeingEdited = ref<IngredientOption | null>(null);
const isIngredientModalOpen = ref(false);

// Tags / Kategorien
const categoryOptions = ref<Category[]>(props.categories ?? []);
const tagBeingEdited = ref<Category | null>(null);
const isTagModalOpen = ref(false);

// Alternativzutaten-Modal
const isAlternativesModalOpen = ref(false);
const ingredientForAlternatives = ref<IngredientRow | null>(null);
const ingredientAlternativesIndex = ref<number | null>(null);

const form = useForm({
    name: props.recipe?.name
        ?? (props.recipe as any)?.title
        ?? '',
    description: props.recipe?.description ?? '',
    duration_minutes: props.recipe?.duration_minutes ?? '',
    servings: props.recipe?.servings ?? '',
    image: null as File | null,
    source: props.recipe?.source ?? '',
    tags: props.recipe?.tags ?? [],
    ingredients: props.recipe?.ingredients ?? [
        {
            amount: '',
            name: '',
        },
    ],
    steps: props.recipe?.steps ?? [''],
    notes: props.recipe?.notes ?? '',
    ratings: props.recipe?.ratings ?? ({} as Record<number, number | null>),
});

const tagDraft = ref('');

// Index der Zutaten-Zeile, deren Dropdown aktuell offen ist
const ingredientDropdownOpenIndex = ref<number | null>(null);

// Tags hinzufügen / toggeln
const toggleTag = (tag: string) => {
    if (form.tags.includes(tag)) {
        form.tags = form.tags.filter((t) => t !== tag);
    } else {
        form.tags.push(tag);
    }
};

// Tag per Enter anlegen (inkl. DB)
const addTagFromDraft = async () => {
    const value = tagDraft.value.trim();
    if (!value) return;

    // Prüfen, ob es die Kategorie schon gibt
    const existing = categoryOptions.value.find(
        (cat) => cat.name.toLowerCase().trim() === value.toLowerCase()
    );

    if (existing) {
        if (!form.tags.includes(existing.name)) {
            form.tags.push(existing.name);
        }
        tagDraft.value = '';
        return;
    }

    // Neu -> in DB anlegen
    try {
        const response = await axios.post(route('categories.store'), {
            name: value,
        });

        const created: Category = response.data;

        categoryOptions.value.push(created);

        if (!form.tags.includes(created.name)) {
            form.tags.push(created.name);
        }

        tagDraft.value = '';
    } catch (error) {
        console.error('Tag-Erstellung fehlgeschlagen', error);
    }
};

// Zutaten
const addIngredient = () => {
    form.ingredients.push({
        amount: '',
        name: '',
    });
};

const removeIngredient = (index: number) => {
    form.ingredients.splice(index, 1);
};

// Dropdown öffnen
const openIngredientDropdown = (index: number) => {
    ingredientDropdownOpenIndex.value = index;
};

// Dropdown schließen
const closeIngredientDropdown = () => {
    ingredientDropdownOpenIndex.value = null;
};

// Vorschlagsliste für eine bestimmte Zeile
const getIngredientOptions = (index: number) => {
    const query = form.ingredients[index]?.name?.toLowerCase().trim() ?? '';
    const list = ingredientOptions.value;

    if (!query) {
        return list;
    }

    return list.filter((ing) =>
        ing.name.toLowerCase().includes(query)
    );
};

// Auswahl eines Vorschlags
const selectIngredient = (index: number, ingredientName: string) => {
    form.ingredients[index].name = ingredientName;
    closeIngredientDropdown();
};

// Schritte
const addStep = () => {
    form.steps.push('');
};

const removeStep = (index: number) => {
    form.steps.splice(index, 1);
};

// Rating setzen
const setRating = (dimensionId: number, value: number) => {
    form.ratings[dimensionId] = value;
};

const onImageChange = (event: Event) => {
    const target = event.target as HTMLInputElement;

    if (!target.files || target.files.length === 0) {
        form.image = null;
        return;
    }

    form.image = target.files[0];
};

const submit = () => {
    const method = props.submitMethod;

    if (method === 'post') {
        form.post(props.submitRoute, {
            forceFormData: true,
        });
    } else if (method === 'put') {
        form.put(props.submitRoute, {
            forceFormData: true,
        });
    } else if (method === 'patch') {
        form.patch(props.submitRoute, {
            forceFormData: true,
        });
    }
};

const resetForm = () => {
    form.reset();
    form.clearErrors();
};

const findIngredientByName = (name: string): IngredientOption | undefined => {
    const query = name.toLowerCase().trim();
    if (!query) return undefined;

    return ingredientOptions.value.find((ing) =>
        ing.name.toLowerCase().trim() === query
    );
};

const createIngredientFromRow = async (index: number) => {
    const name = form.ingredients[index].name.trim();
    if (!name) return;

    // Falls die Zutat inzwischen existiert, kein Doppel-Create
    if (findIngredientByName(name)) {
        return;
    }

    try {
        const response = await axios.post(route('ingredients.store'), {
            name,
        });

        const created: IngredientOption = response.data;

        // Neue Zutat in Options aufnehmen
        ingredientOptions.value.push(created);

        // Exakten Namen aus DB übernehmen
        form.ingredients[index].name = created.name;

        closeIngredientDropdown();
    } catch (error) {
        console.error('Zutatenerstellung fehlgeschlagen', error);
    }
};

// Ingredient-Modal
const openEditIngredientModal = (ingredient: IngredientOption) => {
    ingredientBeingEdited.value = ingredient;
    isIngredientModalOpen.value = true;
};

const openIngredientAlternatives = (index: number) => {
    const row = form.ingredients[index];
    if (!row) return;

    ingredientAlternativesIndex.value = index;
    ingredientForAlternatives.value = row;
    isAlternativesModalOpen.value = true;
};

const onIngredientUpdated = (payload: { oldName: string; ingredient: IngredientOption }) => {
    const { oldName, ingredient } = payload;

    // 1) ingredientOptions aktualisieren
    const idx = ingredientOptions.value.findIndex((ing) => ing.id === ingredient.id);
    if (idx !== -1) {
        ingredientOptions.value[idx] = ingredient;
    } else {
        ingredientOptions.value.push(ingredient);
    }

    // 2) Alle aktuell im Formular verwendeten Namen anpassen
    form.ingredients.forEach((row) => {
        if (row.name.trim().toLowerCase() === oldName.toLowerCase()) {
            row.name = ingredient.name;
        }
    });
};

const onIngredientDeleted = (payload: { id: number; name: string }) => {
    const { id, name } = payload;

    // 1) Aus Optionen entfernen
    ingredientOptions.value = ingredientOptions.value.filter((ing) => ing.id !== id);

    // 2) In aktuell offenen Formular-Zeilen ggf. Namen leeren
    form.ingredients.forEach((row) => {
        if (row.name.trim().toLowerCase() === name.toLowerCase()) {
            row.name = '';
        }
    });
};

// Tag-Modal
const openTagEditModal = (category: Category) => {
    tagBeingEdited.value = category;
    isTagModalOpen.value = true;
};

const onTagUpdated = (payload: { oldName: string; category: Category }) => {
    const { oldName, category } = payload;

    // 1) categoryOptions aktualisieren
    const idx = categoryOptions.value.findIndex((c) => c.id === category.id);
    if (idx !== -1) {
        categoryOptions.value[idx] = category;
    } else {
        categoryOptions.value.push(category);
    }

    // 2) form.tags (Name-Liste) aktualisieren
    form.tags = form.tags.map((t) =>
        t.trim().toLowerCase() === oldName.toLowerCase()
            ? category.name
            : t
    );
};

const onTagDeleted = (payload: { id: number; name: string }) => {
    const { id, name } = payload;

    // 1) Kategorie aus Options entfernen
    categoryOptions.value = categoryOptions.value.filter((c) => c.id !== id);

    // 2) Tag aus dem Rezept entfernen
    form.tags = form.tags.filter(
        (t) => t.trim().toLowerCase() !== name.toLowerCase()
    );
};
</script>

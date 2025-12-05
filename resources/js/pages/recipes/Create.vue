<template>
    <AppLayout page-title="Rezept anlegen">
        <section class="content-section">
            <div class="section-header">
                <div class="section-header__title">Neues Rezept</div>
                <div class="section-header__meta">
                    Entwurf · noch nicht gespeichert
                </div>
            </div>

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

                        <div class="form-field">
                            <div class="form-field__label">
                                Tags
                                <span class="inline-badge">optional</span>
                            </div>
                            <div class="chips-input">
                                <div class="chips-input__chips">
                                    <button
                                        v-for="category in categories"
                                        :key="category.id"
                                        type="button"
                                        class="tag-chip"
                                        :class="{ 'tag-chip--active': form.tags.includes(category.name) }"
                                        @click="toggleTag(category.name)"
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
                            Rezept speichern
                        </button>
                    </div>
                </div>
            </form>
        </section>
    </AppLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { route } from 'ziggy-js';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/layout/AppLayout.vue';

const props = defineProps<{
    ratingDimensions: {
        id: number;
        name: string;
        description?: string | null;
    }[];
    categories: {
        id: number;
        name: string;
    }[];
    ingredients: {
        id: number;
        name: string;
    }[];
}>();

const form = useForm({
    name: '',
    description: '',
    duration_minutes: '',
    servings: '',
    difficulty: 'einfach',
    image: null as File | null,
    source: '',
    tags: [] as string[],
    ingredients: [
        {
            amount: '',
            name: '',
        },
    ],
    steps: [''],
    notes: '',
    ratings: {} as Record<number, number | null>,
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

const addTagFromDraft = () => {
    const value = tagDraft.value.trim();
    if (!value) return;
    if (!form.tags.includes(value)) {
        form.tags.push(value);
    }
    tagDraft.value = '';
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

// Dropdown öffnen (und ggf. neu filtern)
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
    const list = props.ingredients ?? [];

    if (!query) {
        // ohne Filter alle anzeigen
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

// Submit
const submit = () => {
    form.post(route('recipes.store'), {
        forceFormData: true,
    });
};

const resetForm = () => {
    form.reset();
    form.clearErrors();
};
</script>

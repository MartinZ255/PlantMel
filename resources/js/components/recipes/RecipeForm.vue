<template>
    <!-- novalidate: Pflichtfelder sind markiert, geprüft wird auf dem Server
         (die Meldungen erscheinen am Feld und in der Zusammenfassung) -->
    <form class="recipe-form" novalidate @submit.prevent="submit">
        <!-- F-06/F-74: Fehlerzusammenfassung mit Sprunglinks -->
        <div
            v-if="errorEntries.length"
            ref="summaryEl"
            class="form-error-summary"
            role="alert"
            tabindex="-1"
            aria-labelledby="form-error-summary-title"
        >
            <h2 id="form-error-summary-title" class="form-error-summary__title">
                {{
                    errorEntries.length === 1
                        ? 'Bitte prüfe 1 Angabe:'
                        : `Bitte prüfe ${errorEntries.length} Angaben:`
                }}
            </h2>
            <ul class="form-error-summary__list">
                <li v-for="entry in errorEntries" :key="entry.key">
                    <a
                        v-if="entry.target"
                        :href="`#${entry.target}`"
                        class="form-error-summary__link"
                        @click.prevent="focusField(entry.target)"
                    >
                        {{ entry.label }}: {{ entry.message }}
                    </a>
                    <span v-else>{{ entry.label }}: {{ entry.message }}</span>
                </li>
            </ul>
        </div>

        <div class="recipe-form__grid">
            <!-- Stammdaten links -->
            <div class="recipe-form__section">
                <h2 class="recipe-form__section-title">Stammdaten</h2>
                <p class="recipe-form__required-hint">
                    Mit <span aria-hidden="true">*</span> markierte Angaben sind
                    Pflicht.
                </p>

                <div class="form-field">
                    <label class="form-field__label" for="name">
                        Titel
                        <span class="form-field__required" aria-hidden="true"
                            >*</span
                        >
                    </label>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="form-field__input"
                        placeholder="z. B. Cremige One-Pot Pasta mit Spinat"
                        required
                        aria-required="true"
                        v-bind="invalidAttrs('name', 'name-error')"
                    />
                    <p
                        v-if="errorFor('name')"
                        id="name-error"
                        class="form-field__error"
                    >
                        {{ errorFor('name') }}
                    </p>
                </div>

                <div class="form-field">
                    <label class="form-field__label" for="description"
                        >Kurzbeschreibung</label
                    >
                    <textarea
                        id="description"
                        v-model="form.description"
                        class="form-field__textarea"
                        rows="2"
                        placeholder="Kurze Beschreibung, wann und wofür das Rezept ideal ist …"
                        v-bind="
                            invalidAttrs('description', 'description-error')
                        "
                    ></textarea>
                    <p
                        v-if="errorFor('description')"
                        id="description-error"
                        class="form-field__error"
                    >
                        {{ errorFor('description') }}
                    </p>
                </div>

                <div class="form-row-split">
                    <div class="form-field">
                        <label class="form-field__label" for="cook_time"
                            >Zubereitungsdauer (Min.)</label
                        >
                        <input
                            id="cook_time"
                            v-model="form.duration_minutes"
                            type="number"
                            min="0"
                            class="form-field__input form-field__input--sm"
                            placeholder="20"
                            v-bind="
                                invalidAttrs(
                                    'duration_minutes',
                                    'cook_time-error',
                                )
                            "
                        />
                        <p
                            v-if="errorFor('duration_minutes')"
                            id="cook_time-error"
                            class="form-field__error"
                        >
                            {{ errorFor('duration_minutes') }}
                        </p>
                    </div>

                    <div class="form-field">
                        <label class="form-field__label" for="servings"
                            >Portionen</label
                        >
                        <input
                            id="servings"
                            v-model="form.servings"
                            type="number"
                            min="1"
                            class="form-field__input form-field__input--sm"
                            placeholder="2"
                            v-bind="invalidAttrs('servings', 'servings-error')"
                        />
                        <p
                            v-if="errorFor('servings')"
                            id="servings-error"
                            class="form-field__error"
                        >
                            {{ errorFor('servings') }}
                        </p>
                    </div>
                </div>

                <!-- Sternebewertung pro Dimension -->
                <div class="recipe-form__ratings">
                    <div class="form-field">
                        <div
                            id="ratings-title"
                            class="recipe-form__section-title"
                        >
                            Bewertung
                        </div>
                        <p
                            v-if="errorFor('ratings')"
                            id="ratings-error"
                            class="form-field__error"
                        >
                            {{ errorFor('ratings') }}
                        </p>
                    </div>

                    <div class="rating-grid">
                        <div
                            v-for="dimension in ratingDimensions"
                            :key="dimension.id"
                            class="rating-row"
                        >
                            <div class="rating-row__label">
                                <div
                                    :id="`rating-${dimension.id}-label`"
                                    class="rating-row__name"
                                >
                                    {{ dimension.name }}
                                </div>
                                <div
                                    v-if="dimension.description"
                                    class="rating-row__hint"
                                >
                                    {{ dimension.description }}
                                </div>
                                <p
                                    v-if="ratingError(dimension.id)"
                                    :id="`rating-${dimension.id}-error`"
                                    class="form-field__error"
                                >
                                    {{ ratingError(dimension.id) }}
                                </p>
                            </div>

                            <div
                                class="rating-stars-input"
                                role="group"
                                :aria-labelledby="`rating-${dimension.id}-label`"
                                :aria-describedby="
                                    ratingDescribedBy(dimension.id)
                                "
                            >
                                <button
                                    v-for="star in 5"
                                    :id="`rating-${dimension.id}-star-${star}`"
                                    :key="star"
                                    type="button"
                                    class="rating-star-btn"
                                    :class="{
                                        'rating-star-btn--active':
                                            (form.ratings[dimension.id] ?? 0) >=
                                            star,
                                    }"
                                    :aria-label="`${star} von 5 Sternen`"
                                    :aria-pressed="
                                        form.ratings[dimension.id] === star
                                            ? 'true'
                                            : 'false'
                                    "
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
                    <!-- F-56: Vorschau des neuen bzw. aktuellen Bildes -->
                    <figure
                        v-if="shownImage"
                        class="recipe-form__image-preview"
                    >
                        <img
                            :src="shownImage"
                            alt=""
                            class="recipe-form__image-preview-img"
                        />
                        <figcaption class="form-field__hint">
                            {{
                                previewUrl
                                    ? 'Neues Bild – wird beim Speichern übernommen.'
                                    : 'Aktuelles Bild'
                            }}
                        </figcaption>
                    </figure>
                    <input
                        id="image"
                        ref="imageInput"
                        type="file"
                        accept="image/*"
                        class="form-field__input"
                        v-bind="
                            invalidAttrs('image', 'image-error', 'image-hint')
                        "
                        @change="onImageChange"
                    />
                    <p id="image-hint" class="form-field__hint">
                        Optional – wird z. B. für die Vorschaukarte oder
                        Detailseite verwendet.
                    </p>
                    <p
                        v-if="errorFor('image')"
                        id="image-error"
                        class="form-field__error"
                    >
                        {{ errorFor('image') }}
                    </p>
                </div>

                <!-- TAGS -->
                <div class="form-field">
                    <div id="tags-label" class="form-field__label">
                        Tags
                        <span class="inline-badge">optional</span>
                    </div>
                    <div class="chips-input">
                        <div
                            class="chips-input__chips"
                            role="group"
                            aria-labelledby="tags-label"
                        >
                            <button
                                v-for="category in categoryOptions"
                                :key="category.id"
                                type="button"
                                class="tag-chip"
                                :class="{
                                    'tag-chip--active': form.tags.includes(
                                        category.name,
                                    ),
                                }"
                                :aria-pressed="
                                    form.tags.includes(category.name)
                                        ? 'true'
                                        : 'false'
                                "
                                @click="toggleTag(category.name)"
                                @dblclick.stop="openTagEditModal(category)"
                            >
                                {{ category.name }}
                            </button>
                        </div>
                        <input
                            id="tag-draft"
                            v-model="tagDraft"
                            type="text"
                            class="chips-input__input"
                            placeholder="Weitere Tags mit Enter hinzufügen …"
                            aria-label="Weiteren Tag hinzufügen"
                            :aria-invalid="
                                tagDraftError || tagsError ? 'true' : undefined
                            "
                            :aria-describedby="tagDescribedBy"
                            :disabled="tagDraftBusy"
                            @input="tagDraftError = null"
                            @keydown.enter.prevent="addTagFromDraft"
                        />
                        <p
                            v-if="tagDraftError"
                            id="tag-draft-error"
                            class="form-field__error"
                            role="alert"
                        >
                            {{ tagDraftError }}
                        </p>
                        <p
                            v-if="tagsError"
                            id="tags-error"
                            class="form-field__error"
                        >
                            {{ tagsError }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Zutaten -->
        <div class="recipe-form__section recipe-form__section--wide">
            <h2 id="ingredients-title" class="recipe-form__section-title">
                Zutaten
                <span class="form-field__required" aria-hidden="true">*</span>
            </h2>
            <p id="ingredients-hint" class="recipe-form__section-hint">
                Jede Zeile entspricht einer Zutat. Menge links, Bezeichnung
                rechts. Mindestens eine Zutat ist Pflicht; leere Zeilen werden
                beim Speichern ignoriert.
            </p>
            <p
                v-if="errorFor('ingredients')"
                id="ingredients-error"
                class="form-field__error"
            >
                {{ errorFor('ingredients') }}
            </p>

            <div class="ingredients-builder" ref="ingredientsListEl">
                <div
                    v-for="(ingredient, index) in form.ingredients"
                    :key="ingredientIds[index]"
                    class="ingredients-builder__item"
                >
                    <div
                        class="ingredients-builder__row"
                        :class="{
                            'ingredients-builder__row--dragging':
                                draggedIngredientIndex === index,
                        }"
                    >
                        <button
                            v-if="form.ingredients.length > 1"
                            type="button"
                            class="ingredients-builder__handle"
                            aria-label="Zutat verschieben"
                            title="Gedrückt halten und ziehen, um die Zutat zu verschieben"
                            @pointerdown="startIngredientDrag(index, $event)"
                            @contextmenu.prevent
                        >
                            <GripVertical :size="16" />
                        </button>

                        <input
                            :id="`ingredient-${ingredientIds[index]}-amount`"
                            v-model="ingredient.amount"
                            type="text"
                            class="form-field__input ingredients-builder__amount"
                            placeholder="z. B. 200 g"
                            :aria-label="`Menge, Zutat ${index + 1}`"
                            v-bind="
                                rowInvalidAttrs(
                                    ingredientError(index, 'amount'),
                                    `ingredient-${ingredientIds[index]}-amount-error`,
                                )
                            "
                        />

                        <!-- Autocomplete-Wrapper für den Zutaten-Namen -->
                        <div class="ingredients-autocomplete">
                            <input
                                :id="`ingredient-${ingredientIds[index]}-name`"
                                v-model="ingredient.name"
                                type="text"
                                class="form-field__input ingredients-builder__name"
                                placeholder="z. B. Pasta (Penne)"
                                :aria-label="`Bezeichnung, Zutat ${index + 1}`"
                                :required="index === 0"
                                :aria-required="
                                    index === 0 ? 'true' : undefined
                                "
                                v-bind="ingredientNameAttrs(index)"
                                @focus="openIngredientDropdown(index)"
                                @input="onIngredientNameInput(index)"
                                @blur="closeIngredientDropdown"
                                @keydown.esc="closeIngredientDropdown"
                            />

                            <div
                                v-if="ingredientDropdownOpenIndex === index"
                                class="ingredients-dropdown"
                            >
                                <button
                                    v-for="option in getIngredientOptions(
                                        index,
                                    )"
                                    :key="option.id"
                                    type="button"
                                    class="ingredients-dropdown__item"
                                    @mousedown.prevent="
                                        selectIngredient(index, option.name)
                                    "
                                >
                                    {{ option.name }}
                                </button>
                            </div>
                        </div>

                        <!-- + oder Stift abhängig davon, ob Zutat existiert -->
                        <button
                            v-if="
                                ingredient.name.trim() &&
                                !findIngredientByName(ingredient.name)
                            "
                            type="button"
                            class="secondary-pill-button ingredients-builder__action"
                            title="Zutat neu anlegen"
                            :disabled="
                                creatingIngredientRowId === ingredientIds[index]
                            "
                            @click="createIngredientFromRow(index)"
                        >
                            +
                        </button>

                        <button
                            v-else-if="findIngredientByName(ingredient.name)"
                            type="button"
                            class="secondary-pill-button ingredients-builder__action"
                            aria-haspopup="dialog"
                            @click="
                                openEditIngredientModal(
                                    findIngredientByName(ingredient.name)!,
                                )
                            "
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
                            :id="`ingredient-${ingredientIds[index]}-alternatives`"
                            type="button"
                            class="secondary-pill-button ingredients-builder__action"
                            aria-haspopup="dialog"
                            @click="openIngredientAlternatives(index)"
                            aria-label="Alternativzutaten bearbeiten"
                            v-bind="
                                rowInvalidAttrs(
                                    ingredientError(index, 'alternatives'),
                                    `ingredient-${ingredientIds[index]}-alternatives-error`,
                                )
                            "
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

                    <div
                        v-if="rowMessages(index).length"
                        class="ingredients-builder__errors"
                    >
                        <p
                            v-for="msg in rowMessages(index)"
                            :id="msg.id"
                            :key="msg.id"
                            class="form-field__error"
                            :role="msg.live ? 'alert' : undefined"
                        >
                            {{ msg.text }}
                        </p>
                    </div>
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
            <h2 id="steps-title" class="recipe-form__section-title">
                Zubereitung
                <span class="form-field__required" aria-hidden="true">*</span>
            </h2>
            <p class="recipe-form__section-hint">
                Die Reihenfolge der Schritte entspricht der Darstellung im
                Rezept. Mindestens ein Schritt ist Pflicht.
            </p>
            <p
                v-if="errorFor('steps')"
                id="steps-error"
                class="form-field__error"
            >
                {{ errorFor('steps') }}
            </p>

            <ol class="steps-builder" ref="stepsListEl">
                <li
                    v-for="(step, index) in form.steps"
                    :key="stepIds[index]"
                    class="steps-builder__row"
                    :class="{
                        'steps-builder__row--dragging':
                            draggedStepIndex === index,
                    }"
                >
                    <button
                        v-if="form.steps.length > 1"
                        type="button"
                        class="steps-builder__handle"
                        aria-label="Schritt verschieben"
                        title="Gedrückt halten und ziehen, um den Schritt zu verschieben"
                        @pointerdown="startStepDrag(index, $event)"
                        @contextmenu.prevent
                    >
                        <GripVertical :size="16" />
                    </button>

                    <div class="steps-builder__badge" aria-hidden="true">
                        {{ index + 1 }}
                    </div>
                    <div class="steps-builder__field">
                        <textarea
                            :id="`step-${stepIds[index]}`"
                            v-model="form.steps[index]"
                            rows="2"
                            class="form-field__textarea steps-builder__textarea"
                            placeholder="Beschreibe hier die Schritte …"
                            :aria-label="`Schritt ${index + 1}`"
                            :required="index === 0"
                            :aria-required="index === 0 ? 'true' : undefined"
                            v-bind="stepAttrs(index)"
                        ></textarea>
                        <p
                            v-if="stepError(index)"
                            :id="`step-${stepIds[index]}-error`"
                            class="form-field__error"
                        >
                            {{ stepError(index) }}
                        </p>
                    </div>

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
            <h2 id="notes-title" class="recipe-form__section-title">
                Notizen & Varianten
            </h2>
            <textarea
                id="notes"
                v-model="form.notes"
                rows="3"
                class="form-field__textarea"
                placeholder="Optionale Hinweise, Varianten, Aufbewahrungstipps …"
                aria-labelledby="notes-title"
                v-bind="invalidAttrs('notes', 'notes-error')"
            ></textarea>
            <p
                v-if="errorFor('notes')"
                id="notes-error"
                class="form-field__error"
            >
                {{ errorFor('notes') }}
            </p>
        </div>

        <!-- Aktionen unten -->
        <div class="form-actions">
            <div class="form-actions__left">
                <span class="form-actions__meta">
                    Felder können jederzeit später bearbeitet werden.
                </span>
            </div>

            <!-- F-56: Zurücksetzen nur nach Rückfrage -->
            <div
                v-if="confirmingReset"
                class="form-actions__confirm"
                role="group"
                aria-labelledby="reset-confirm-text"
            >
                <span
                    id="reset-confirm-text"
                    class="form-actions__confirm-text"
                >
                    Alle ungespeicherten Änderungen verwerfen?
                </span>
                <button
                    ref="resetKeepButton"
                    type="button"
                    class="secondary-pill-button"
                    @click="cancelReset"
                >
                    Behalten
                </button>
                <button
                    type="button"
                    class="primary-pill-button form-actions__confirm-reset"
                    @click="confirmReset"
                >
                    Ja, zurücksetzen
                </button>
            </div>

            <div v-else class="form-actions__right">
                <Link
                    v-if="cancelHref"
                    :href="cancelHref"
                    class="secondary-pill-button form-actions__cancel"
                >
                    Abbrechen
                </Link>
                <button
                    ref="resetButton"
                    type="button"
                    class="secondary-pill-button"
                    @click="askReset"
                    :disabled="form.processing"
                >
                    Zurücksetzen
                </button>
                <button
                    type="submit"
                    class="primary-pill-button"
                    :disabled="form.processing"
                >
                    {{
                        mode === 'edit'
                            ? 'Rezept aktualisieren'
                            : 'Rezept speichern'
                    }}
                </button>
            </div>
        </div>

        <!-- Die Modals werden per Teleport außerhalb des Formulars gerendert (F-30) -->
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

        <!-- F-13: Entwurf je Zeile; gespeichert wird mit dem Rezept -->
        <IngredientAlternativesModal
            v-if="ingredientForAlternatives"
            :show="isAlternativesModalOpen"
            :ingredient="ingredientForAlternatives"
            :all-ingredients="ingredientOptions"
            :base-ingredient-id="
                findIngredientByName(ingredientForAlternatives.name)?.id ?? null
            "
            :draft-alternative-ids="
                ingredientForAlternatives.alternativeIngredientIds ?? []
            "
            @update-draft="onAlternativesDraft"
            @close="isAlternativesModalOpen = false"
        />
    </form>
</template>

<script setup lang="ts">
import IngredientAlternativesModal from '@/components/widgets/IngredientAlternativesModal.vue';
import IngredientEditModal from '@/components/widgets/IngredientEditModal.vue';
import TagEditModal from '@/components/widgets/TagEditModal.vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { GripVertical } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import { route } from 'ziggy-js';

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
    alternativeIngredientIds: number[];
}

// Eingang aus Edit-Props bzw. Import-Ergebnis (Felder können fehlen)
interface IngredientRowInput {
    amount?: string | null;
    name?: string | null;
    alternativeIngredientIds?: (number | string)[];
}

// F-15: kein Feld „Quelle“ mehr. Ältere Import-Ergebnisse dürfen `source`
// noch enthalten – es wird schlicht nicht übernommen.
interface RecipeFormInput {
    id?: number;
    name?: string;
    description?: string | null;
    duration_minutes?: string | number | null;
    servings?: string | number | null;
    tags?: string[];
    ingredients?: IngredientRowInput[];
    // ADR-001: ein mehrzeiliger Schritt ist EIN String mit "\n"
    steps?: string[];
    notes?: string | null;
    ratings?: Record<number, number | null>;
}

/** Jede Zeile bekommt eine eigene, vollständige Liste der Alternativen (F-13). */
const toIngredientRow = (row?: IngredientRowInput): IngredientRow => ({
    amount: row?.amount ?? '',
    name: row?.name ?? '',
    alternativeIngredientIds: [
        ...new Set((row?.alternativeIngredientIds ?? []).map(Number)),
    ].filter((id) => Number.isInteger(id) && id > 0),
});

const props = defineProps<{
    mode: 'create' | 'edit';
    ratingDimensions: RatingDimension[];
    categories: Category[];
    ingredients: IngredientOption[];
    recipe?: RecipeFormInput | null;
    submitRoute: string;
    submitMethod: 'post' | 'put' | 'patch';
    // F-56: Ziel des „Abbrechen“-Links (nur beim Bearbeiten)
    cancelHref?: string | null;
    // F-56: URL des aktuellen Bildes für die Vorschau (falls vorhanden)
    currentImage?: string | null;
}>();

const ingredientOptions = ref<IngredientOption[]>(props.ingredients ?? []);
const ingredientBeingEdited = ref<IngredientOption | null>(null);
const isIngredientModalOpen = ref(false);

// Tags / Kategorien
const categoryOptions = ref<Category[]>(props.categories ?? []);
const tagBeingEdited = ref<Category | null>(null);
const isTagModalOpen = ref(false);

// Alternativzutaten-Modal (hält die Zeile selbst, nicht ihren Index)
const isAlternativesModalOpen = ref(false);
const ingredientForAlternatives = ref<IngredientRow | null>(null);

const form = useForm({
    name: props.recipe?.name ?? (props.recipe as any)?.title ?? '',
    description: props.recipe?.description ?? '',
    duration_minutes: props.recipe?.duration_minutes ?? '',
    servings: props.recipe?.servings ?? '',
    image: null as File | null,
    tags: props.recipe?.tags ?? [],
    ingredients: props.recipe?.ingredients?.length
        ? props.recipe.ingredients.map(toIngredientRow)
        : [toIngredientRow()],
    steps: props.recipe?.steps?.length ? [...props.recipe.steps] : [''],
    notes: props.recipe?.notes ?? '',
    ratings: props.recipe?.ratings ?? ({} as Record<number, number | null>),
});

// Sortierbare Listen (Schritte + Zutaten)
// Stabile IDs parallel zu den Arrays, damit die DOM-Knoten beim
// Umsortieren erhalten bleiben (sonst bricht das Drag-and-drop ab)
let nextRowId = 0;
const stepIds = ref<number[]>(form.steps.map(() => nextRowId++));
const ingredientIds = ref<number[]>(form.ingredients.map(() => nextRowId++));

// ---------------------------------------------------------------------------
// F-06/F-74: Validierungsfehler des Servers am Feld und in der Zusammenfassung
//
// Vor dem Absenden werden vollständig leere Zeilen entfernt. Die Server-Keys
// (`ingredients.N.name`, `steps.N`) beziehen sich deshalb auf die GESENDETE
// Reihenfolge; die Zuordnung zur angezeigten Zeile läuft über die stabilen
// Zeilen-IDs zum Zeitpunkt des Absendens. Nach Umsortieren oder Entfernen
// werden die Zeilen-Fehler verworfen, damit nichts an der falschen Zeile hängt.
// ---------------------------------------------------------------------------
const allErrors = computed(
    () => form.errors as unknown as Record<string, string | undefined>,
);
const sentIngredientRowIds = ref<number[]>([]);
const sentStepRowIds = ref<number[]>([]);
const summaryEl = ref<HTMLElement | null>(null);

const errorFor = (key: string): string | undefined => allErrors.value[key];

/** Attribute für ein Feld mit möglichem Fehler (+ optionaler Hinweis-ID). */
const invalidAttrs = (key: string, errorId: string, hintId?: string) => {
    const hasError = !!errorFor(key);
    const describedBy = [hintId, hasError ? errorId : undefined]
        .filter(Boolean)
        .join(' ');
    return {
        'aria-invalid': hasError ? ('true' as const) : undefined,
        'aria-describedby': describedBy || undefined,
    };
};

const rowInvalidAttrs = (message: string | undefined, errorId: string) => ({
    'aria-invalid': message ? ('true' as const) : undefined,
    'aria-describedby': message ? errorId : undefined,
});

const sentIndexOf = (rowIds: number[], rowId: number | undefined) =>
    rowId === undefined ? -1 : rowIds.indexOf(rowId);

const ingredientError = (
    index: number,
    field: 'name' | 'amount' | 'alternatives',
): string | undefined => {
    const sent = sentIndexOf(
        sentIngredientRowIds.value,
        ingredientIds.value[index],
    );
    if (sent === -1) return undefined;
    if (field === 'alternatives') {
        const prefix = `ingredients.${sent}.alternativeIngredientIds`;
        const key = Object.keys(allErrors.value).find(
            (k) => k === prefix || k.startsWith(`${prefix}.`),
        );
        return key ? allErrors.value[key] : undefined;
    }
    return allErrors.value[`ingredients.${sent}.${field}`];
};

const stepError = (index: number): string | undefined => {
    const sent = sentIndexOf(sentStepRowIds.value, stepIds.value[index]);
    return sent === -1 ? undefined : allErrors.value[`steps.${sent}`];
};

const stepAttrs = (index: number) => {
    const message = stepError(index);
    return rowInvalidAttrs(message, `step-${stepIds.value[index]}-error`);
};

const tagsError = computed(() => {
    const key = Object.keys(allErrors.value).find(
        (k) => k === 'tags' || k.startsWith('tags.'),
    );
    return key ? allErrors.value[key] : undefined;
});

const ratingError = (dimensionId: number) =>
    allErrors.value[`ratings.${dimensionId}`];

const ratingDescribedBy = (dimensionId: number) =>
    [
        ratingError(dimensionId) ? `rating-${dimensionId}-error` : undefined,
        errorFor('ratings') ? 'ratings-error' : undefined,
    ]
        .filter(Boolean)
        .join(' ') || undefined;

// Fehler aus „Zutat anlegen“ (axios) je Zeilen-ID
const ingredientCreateErrors = ref<Record<number, string>>({});
const creatingIngredientRowId = ref<number | null>(null);

/** Meldungen unter einer Zutatenzeile (Server-Validierung + Anlegen). */
const rowMessages = (index: number) => {
    const rowId = ingredientIds.value[index];
    const messages: { id: string; text: string; live: boolean }[] = [];
    const amount = ingredientError(index, 'amount');
    const name = ingredientError(index, 'name');
    const alternatives = ingredientError(index, 'alternatives');
    if (amount)
        messages.push({
            id: `ingredient-${rowId}-amount-error`,
            text: amount,
            live: false,
        });
    if (name)
        messages.push({
            id: `ingredient-${rowId}-name-error`,
            text: name,
            live: false,
        });
    if (alternatives)
        messages.push({
            id: `ingredient-${rowId}-alternatives-error`,
            text: alternatives,
            live: false,
        });
    const createError = ingredientCreateErrors.value[rowId];
    if (createError)
        messages.push({
            id: `ingredient-${rowId}-create-error`,
            text: createError,
            live: true,
        });
    return messages;
};

const ingredientNameAttrs = (index: number) => {
    const rowId = ingredientIds.value[index];
    const nameError = ingredientError(index, 'name');
    const createError = ingredientCreateErrors.value[rowId];
    const describedBy = [
        nameError ? `ingredient-${rowId}-name-error` : undefined,
        createError ? `ingredient-${rowId}-create-error` : undefined,
    ]
        .filter(Boolean)
        .join(' ');
    return {
        'aria-invalid':
            nameError || createError ? ('true' as const) : undefined,
        'aria-describedby': describedBy || undefined,
    };
};

const tagDraft = ref('');
const tagDraftError = ref<string | null>(null);
const tagDraftBusy = ref(false);

const tagDescribedBy = computed(
    () =>
        [
            tagDraftError.value ? 'tag-draft-error' : undefined,
            tagsError.value ? 'tags-error' : undefined,
        ]
            .filter(Boolean)
            .join(' ') || undefined,
);

type ErrorEntry = {
    key: string;
    label: string;
    message: string;
    target: string | null;
};

/** Alle Fehler in Formular-Reihenfolge; nicht zuordenbare Keys am Ende. */
const errorEntries = computed<ErrorEntry[]>(() => {
    const errors = allErrors.value;
    const used = new Set<string>();
    const entries: ErrorEntry[] = [];
    const add = (key: string, label: string, target: string | null) => {
        const message = errors[key];
        if (!message || used.has(key)) return;
        used.add(key);
        entries.push({ key, label, message, target });
    };

    add('name', 'Titel', 'name');
    add('description', 'Kurzbeschreibung', 'description');
    add('duration_minutes', 'Zubereitungsdauer', 'cook_time');
    add('servings', 'Portionen', 'servings');
    const firstDimension = props.ratingDimensions[0]?.id;
    add(
        'ratings',
        'Bewertung',
        firstDimension !== undefined ? `rating-${firstDimension}-star-1` : null,
    );
    props.ratingDimensions.forEach((dimension) =>
        add(
            `ratings.${dimension.id}`,
            `Bewertung – ${dimension.name}`,
            `rating-${dimension.id}-star-1`,
        ),
    );
    add('image', 'Bild', 'image');
    Object.keys(errors)
        .filter((k) => k === 'tags' || k.startsWith('tags.'))
        .forEach((k) => add(k, 'Tags', 'tag-draft'));

    const firstIngredient = ingredientIds.value[0];
    add(
        'ingredients',
        'Zutaten',
        firstIngredient !== undefined
            ? `ingredient-${firstIngredient}-name`
            : null,
    );
    sentIngredientRowIds.value.forEach((rowId, sent) => {
        const index = ingredientIds.value.indexOf(rowId);
        if (index === -1) return;
        const n = index + 1;
        add(
            `ingredients.${sent}.amount`,
            `Zutat ${n} – Menge`,
            `ingredient-${rowId}-amount`,
        );
        add(
            `ingredients.${sent}.name`,
            `Zutat ${n} – Bezeichnung`,
            `ingredient-${rowId}-name`,
        );
        Object.keys(errors)
            .filter((k) =>
                k.startsWith(`ingredients.${sent}.alternativeIngredientIds`),
            )
            .forEach((k) =>
                add(
                    k,
                    `Zutat ${n} – Alternativen`,
                    `ingredient-${rowId}-alternatives`,
                ),
            );
    });

    const firstStep = stepIds.value[0];
    add(
        'steps',
        'Zubereitung',
        firstStep !== undefined ? `step-${firstStep}` : null,
    );
    sentStepRowIds.value.forEach((rowId, sent) => {
        const index = stepIds.value.indexOf(rowId);
        if (index === -1) return;
        add(`steps.${sent}`, `Schritt ${index + 1}`, `step-${rowId}`);
    });

    add('notes', 'Notizen', 'notes');

    // Alles, was keinem Feld zugeordnet werden konnte, trotzdem nennen
    Object.keys(errors).forEach((k) => add(k, 'Formular', null));

    return entries;
});

const focusField = (id: string) => {
    const el = document.getElementById(id);
    if (!el) return;
    el.scrollIntoView({ block: 'center' });
    el.focus();
};

/** Zeilen-Fehler verwerfen (nach Umsortieren/Entfernen stimmen die Indizes nicht mehr). */
const discardRowErrors = (prefix: 'ingredients' | 'steps') => {
    const keys = Object.keys(allErrors.value).filter((k) =>
        k.startsWith(`${prefix}.`),
    );
    if (keys.length) form.clearErrors(...(keys as never[]));
    if (prefix === 'ingredients') sentIngredientRowIds.value = [];
    else sentStepRowIds.value = [];
};

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

/** Verständliche deutsche Meldung für fehlgeschlagene axios-Requests. */
const describeAxiosError = (error: unknown, fallback: string): string => {
    if (!axios.isAxiosError(error)) return fallback;
    const response = error.response;
    if (!response) {
        return 'Keine Verbindung zum Server. Bitte prüfe die Verbindung und versuche es erneut.';
    }
    const data = (response.data ?? {}) as {
        message?: string;
        errors?: Record<string, string[]>;
    };
    switch (response.status) {
        case 403:
            return 'Dafür fehlt dir die Berechtigung.';
        case 419:
            return 'Deine Sitzung ist abgelaufen. Bitte lade die Seite neu und versuche es erneut.';
        case 422:
            return data.errors?.name?.[0] ?? data.message ?? fallback;
    }
    if (response.status >= 500) {
        return 'Auf dem Server ist ein Fehler aufgetreten. Bitte versuche es später erneut.';
    }
    return data.message ?? fallback;
};

// Tag per Enter anlegen (inkl. DB)
const addTagFromDraft = async () => {
    const value = tagDraft.value.trim();
    if (!value || tagDraftBusy.value) return;
    tagDraftError.value = null;

    // Prüfen, ob es die Kategorie schon gibt
    const existing = categoryOptions.value.find(
        (cat) => cat.name.toLowerCase().trim() === value.toLowerCase(),
    );

    if (existing) {
        if (!form.tags.includes(existing.name)) {
            form.tags.push(existing.name);
        }
        tagDraft.value = '';
        return;
    }

    // Neu -> in DB anlegen
    tagDraftBusy.value = true;
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
        // F-74: sichtbar am Eingabefeld statt nur in der Konsole
        tagDraftError.value = describeAxiosError(
            error,
            'Der Tag konnte nicht angelegt werden.',
        );
    } finally {
        tagDraftBusy.value = false;
        await nextTick();
        document.getElementById('tag-draft')?.focus();
    }
};

// Zutaten
const addIngredient = () => {
    form.ingredients.push(toIngredientRow());
    ingredientIds.value.push(nextRowId++);
};

const removeIngredient = (index: number) => {
    const [rowId] = ingredientIds.value.splice(index, 1);
    form.ingredients.splice(index, 1);
    delete ingredientCreateErrors.value[rowId];
    discardRowErrors('ingredients');
};

// Dropdown öffnen
const openIngredientDropdown = (index: number) => {
    ingredientDropdownOpenIndex.value = index;
};

const onIngredientNameInput = (index: number) => {
    delete ingredientCreateErrors.value[ingredientIds.value[index]];
    openIngredientDropdown(index);
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

    return list.filter((ing) => ing.name.toLowerCase().includes(query));
};

// Auswahl eines Vorschlags
const selectIngredient = (index: number, ingredientName: string) => {
    form.ingredients[index].name = ingredientName;
    closeIngredientDropdown();
};

const draggedStepIndex = ref<number | null>(null);
const draggedIngredientIndex = ref<number | null>(null);
const stepsListEl = ref<HTMLOListElement | null>(null);
const ingredientsListEl = ref<HTMLDivElement | null>(null);

const addStep = () => {
    form.steps.push('');
    stepIds.value.push(nextRowId++);
};

const removeStep = (index: number) => {
    form.steps.splice(index, 1);
    stepIds.value.splice(index, 1);
    discardRowErrors('steps');
};

// Verschieben per Pointer-Events statt HTML5-Drag-and-drop,
// damit es auch auf Touchscreens (primäre Nutzung!) funktioniert.
// Liefert einen pointerdown-Handler für den Drag-Griff einer Zeile.
const createRowDragHandler = (options: {
    listEl: () => HTMLElement | null;
    rowSelector: string;
    dragged: { value: number | null };
    move: (from: number, to: number) => void;
    onStart?: () => void;
    // nach dem Loslassen; `moved` = Reihenfolge hat sich geändert
    onEnd?: (moved: boolean) => void;
}) => {
    return (index: number, event: PointerEvent) => {
        // verhindert Scrollen/Textauswahl während des Ziehens
        event.preventDefault();

        options.onStart?.();
        options.dragged.value = index;
        let moved = false;

        const handle = event.currentTarget as HTMLElement;
        try {
            handle.setPointerCapture(event.pointerId);
        } catch {
            // ohne Capture funktioniert das Ziehen weiter, solange der
            // Zeiger über dem Handle bleibt – kein Grund abzubrechen
        }

        const onMove = (e: PointerEvent) => {
            const from = options.dragged.value;
            const list = options.listEl();
            if (from === null || !list) return;

            const rows = Array.from(list.querySelectorAll(options.rowSelector));

            // Ziel anhand der Zeilen-Mittelpunkte bestimmen: Die Zeile rückt
            // auf, sobald der Finger/Mauszeiger die Mitte einer Nachbarzeile kreuzt
            let to = from;
            rows.forEach((row, i) => {
                if (i === from) return;
                const rect = row.getBoundingClientRect();
                const mid = rect.top + rect.height / 2;
                if (i < from && e.clientY < mid) to = Math.min(to, i);
                if (i > from && e.clientY > mid) to = Math.max(to, i);
            });

            if (to !== from) {
                options.move(from, to);
                options.dragged.value = to;
                moved = true;
            }
        };

        const stop = () => {
            options.dragged.value = null;
            window.removeEventListener('pointermove', onMove);
            window.removeEventListener('pointerup', stop);
            window.removeEventListener('pointercancel', stop);
            options.onEnd?.(moved);
        };

        window.addEventListener('pointermove', onMove);
        window.addEventListener('pointerup', stop);
        window.addEventListener('pointercancel', stop);
    };
};

const startStepDrag = createRowDragHandler({
    listEl: () => stepsListEl.value,
    rowSelector: '.steps-builder__row',
    dragged: draggedStepIndex,
    move: (from, to) => {
        const [step] = form.steps.splice(from, 1);
        form.steps.splice(to, 0, step);

        const [id] = stepIds.value.splice(from, 1);
        stepIds.value.splice(to, 0, id);
    },
    // Fehler erst nach dem Loslassen verwerfen: Während des Ziehens bleiben
    // sie über die Zeilen-ID korrekt zugeordnet, und das Layout springt nicht
    onEnd: (moved) => {
        if (moved) discardRowErrors('steps');
    },
});

const startIngredientDrag = createRowDragHandler({
    listEl: () => ingredientsListEl.value,
    rowSelector: '.ingredients-builder__row',
    dragged: draggedIngredientIndex,
    move: (from, to) => {
        const [row] = form.ingredients.splice(from, 1);
        form.ingredients.splice(to, 0, row);

        const [id] = ingredientIds.value.splice(from, 1);
        ingredientIds.value.splice(to, 0, id);
    },
    onEnd: (moved) => {
        if (moved) discardRowErrors('ingredients');
    },
    // offenes Autocomplete-Dropdown schließen, sonst zeigt es
    // nach dem Umsortieren auf die falsche Zeile
    onStart: () => closeIngredientDropdown(),
});

// Rating setzen
const setRating = (dimensionId: number, value: number) => {
    form.ratings[dimensionId] = value;
};

// F-56: Bildvorschau (neu gewählte Datei bzw. aktuelles Bild)
const imageInput = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(null);
const shownImage = computed(
    () => previewUrl.value ?? props.currentImage ?? null,
);

const clearPreview = () => {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = null;
};

const onImageChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    clearPreview();

    if (!target.files || target.files.length === 0) {
        form.image = null;
        return;
    }

    form.image = target.files[0];
    previewUrl.value = URL.createObjectURL(target.files[0]);
};

onBeforeUnmount(clearPreview);

/** Vollständig leere Zeilen werden nicht gesendet (F-06). */
const isEmptyIngredient = (row: IngredientRow) =>
    !String(row.name ?? '').trim() &&
    !String(row.amount ?? '').trim() &&
    (row.alternativeIngredientIds ?? []).length === 0;

const submit = () => {
    const method = props.submitMethod;

    const ingredientRows = form.ingredients
        .map((row, i) => ({ row, id: ingredientIds.value[i] }))
        .filter(({ row }) => !isEmptyIngredient(row));
    const stepRows = form.steps
        .map((step, i) => ({ step, id: stepIds.value[i] }))
        .filter(({ step }) => String(step ?? '').trim() !== '');

    sentIngredientRowIds.value = ingredientRows.map(({ id }) => id);
    sentStepRowIds.value = stepRows.map(({ id }) => id);

    // F-13: Für JEDE gesendete Zeile die aktuelle Liste der Alternativen
    // mitsenden (auch nach Umsortieren, Umbenennen und für nie geöffnete
    // Modals). Der Server nimmt sie als einzige Wahrheit; eine leere Liste
    // überträgt FormData nicht – das bedeutet dort „keine Alternativen“.
    // Ohne gesendete Zutat/Schritt meldet der Server „Pflichtfeld“.
    form.transform((data) => ({
        ...data,
        ingredients: ingredientRows.map(({ row }) => ({
            amount: row.amount,
            name: row.name,
            alternativeIngredientIds: [
                ...new Set(row.alternativeIngredientIds ?? []),
            ],
        })),
        steps: stepRows.map(({ step }) => step),
    }));

    const options = {
        forceFormData: true,
        onError: async () => {
            // Fokus auf die Zusammenfassung, damit die Fehler sofort auffallen
            await nextTick();
            summaryEl.value?.scrollIntoView({ block: 'start' });
            summaryEl.value?.focus({ preventScroll: true });
        },
    };

    if (method === 'post') {
        form.post(props.submitRoute, options);
    } else if (method === 'put') {
        form.put(props.submitRoute, options);
    } else if (method === 'patch') {
        form.patch(props.submitRoute, options);
    }
};

// F-56: Zurücksetzen nur nach Rückfrage
const confirmingReset = ref(false);
const resetButton = ref<HTMLButtonElement | null>(null);
const resetKeepButton = ref<HTMLButtonElement | null>(null);

const askReset = async () => {
    confirmingReset.value = true;
    await nextTick();
    resetKeepButton.value?.focus();
};

const cancelReset = async () => {
    confirmingReset.value = false;
    await nextTick();
    resetButton.value?.focus();
};

const resetForm = () => {
    form.reset();
    form.clearErrors();
    sentIngredientRowIds.value = [];
    sentStepRowIds.value = [];
    ingredientCreateErrors.value = {};
    tagDraftError.value = null;
    stepIds.value = form.steps.map(() => nextRowId++);
    ingredientIds.value = form.ingredients.map(() => nextRowId++);
    clearPreview();
    if (imageInput.value) imageInput.value.value = '';
};

const confirmReset = async () => {
    resetForm();
    await cancelReset();
};

const findIngredientByName = (name: string): IngredientOption | undefined => {
    const query = name.toLowerCase().trim();
    if (!query) return undefined;

    return ingredientOptions.value.find(
        (ing) => ing.name.toLowerCase().trim() === query,
    );
};

const createIngredientFromRow = async (index: number) => {
    const rowId = ingredientIds.value[index];
    const name = form.ingredients[index].name.trim();
    if (!name) return;

    // Falls die Zutat inzwischen existiert, kein Doppel-Create
    if (findIngredientByName(name)) {
        return;
    }

    delete ingredientCreateErrors.value[rowId];
    creatingIngredientRowId.value = rowId;
    try {
        const response = await axios.post(route('ingredients.store'), {
            name,
        });

        const created: IngredientOption = response.data;

        // Neue Zutat in Options aufnehmen
        ingredientOptions.value.push(created);

        // Exakten Namen aus DB übernehmen (Zeile kann inzwischen verschoben sein)
        const currentIndex = ingredientIds.value.indexOf(rowId);
        if (currentIndex !== -1) {
            form.ingredients[currentIndex].name = created.name;
        }

        closeIngredientDropdown();
    } catch (error) {
        // F-74: sichtbar an der Zeile statt nur in der Konsole
        ingredientCreateErrors.value[rowId] = describeAxiosError(
            error,
            'Die Zutat konnte nicht angelegt werden.',
        );
    } finally {
        creatingIngredientRowId.value = null;
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

    ingredientForAlternatives.value = row;
    isAlternativesModalOpen.value = true;
};

// „Übernehmen“ im Modal: Entwurf in die Zeile schreiben (noch nicht gespeichert)
const onAlternativesDraft = (ids: number[]) => {
    if (ingredientForAlternatives.value) {
        ingredientForAlternatives.value.alternativeIngredientIds = [...ids];
    }
};

const onIngredientUpdated = (payload: {
    oldName: string;
    ingredient: IngredientOption;
}) => {
    const { oldName, ingredient } = payload;

    // 1) ingredientOptions aktualisieren
    const idx = ingredientOptions.value.findIndex(
        (ing) => ing.id === ingredient.id,
    );
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
    ingredientOptions.value = ingredientOptions.value.filter(
        (ing) => ing.id !== id,
    );

    // 2) In aktuell offenen Formular-Zeilen ggf. Namen leeren und die
    //    gelöschte Zutat aus vorgemerkten Alternativen entfernen
    form.ingredients.forEach((row) => {
        if (row.name.trim().toLowerCase() === name.toLowerCase()) {
            row.name = '';
        }
        row.alternativeIngredientIds = row.alternativeIngredientIds.filter(
            (altId) => altId !== id,
        );
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
        t.trim().toLowerCase() === oldName.toLowerCase() ? category.name : t,
    );
};

const onTagDeleted = (payload: { id: number; name: string }) => {
    const { id, name } = payload;

    // 1) Kategorie aus Options entfernen
    categoryOptions.value = categoryOptions.value.filter((c) => c.id !== id);

    // 2) Tag aus dem Rezept entfernen
    form.tags = form.tags.filter(
        (t) => t.trim().toLowerCase() !== name.toLowerCase(),
    );
};

// F-24: Die Import-Seite fragt vor dem Überschreiben nach, wenn hier schon
// etwas eingegeben wurde.
defineExpose({
    hasUserInput: () => form.isDirty,
});
</script>

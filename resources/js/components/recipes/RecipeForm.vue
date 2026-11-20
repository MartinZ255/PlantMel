<template>
    <!-- novalidate: Pflichtfelder sind markiert, geprüft wird auf dem Server
         (die Meldungen erscheinen am Feld und in der Zusammenfassung) -->
    <form class="min-w-0" data-recipe-form novalidate @submit.prevent="submit">
        <!-- F-38: Ansage nach dem Umsortieren („Schritt 2 ist jetzt Schritt 1“) -->
        <p class="sr-only" role="status" aria-live="polite" data-reorder-status>
            {{ reorderAnnouncement }}
        </p>
        <div
            class="lg:grid lg:grid-cols-[12rem_minmax(0,1fr)] lg:gap-10 xl:gap-14"
        >
            <!-- Kapitelnavigation (nur breite Bildschirme): Sprunglinks zu den Abschnitten -->
            <nav aria-label="Abschnitte des Formulars" class="hidden lg:block">
                <div
                    class="sticky top-[calc(var(--header-height)+1.5rem)] pt-2"
                >
                    <p
                        class="mb-3 inline-flex items-center gap-1.5 text-sm font-semibold tracking-[0.14em] text-primary uppercase"
                    >
                        <LeafOrnament
                            variant="sprout"
                            class="size-4 text-moss"
                        />
                        Kapitel
                    </p>
                    <div class="relative">
                        <span
                            class="pointer-events-none absolute top-4 bottom-4 left-[1.05rem] border-l-2 border-dashed border-tint-strong"
                            aria-hidden="true"
                        ></span>
                        <ol class="relative flex flex-col gap-1">
                            <li
                                v-for="(chapter, i) in chapters"
                                :key="chapter.id"
                                class="relative"
                            >
                                <a
                                    :href="`#${chapter.id}`"
                                    class="group flex min-h-11 items-center gap-3 rounded-pebble py-1 pr-2 text-base font-medium text-ink-muted hover:bg-tint hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                                >
                                    <span
                                        class="inline-flex size-[2.1rem] shrink-0 items-center justify-center rounded-[58%_42%_52%_48%/46%_54%_46%_54%] bg-surface font-display text-base font-semibold text-primary shadow-card ring-1 ring-line group-hover:bg-matcha group-hover:text-forest"
                                        aria-hidden="true"
                                        >{{ i + 1 }}</span
                                    >
                                    <span class="min-w-0">{{
                                        chapter.label
                                    }}</span>
                                </a>
                            </li>
                        </ol>
                    </div>
                </div>
            </nav>

            <div class="flex min-w-0 flex-col gap-10 sm:gap-14">
                <!-- F-06/F-74: Fehlerzusammenfassung mit Sprunglinks -->
                <div
                    v-if="errorEntries.length"
                    ref="summaryEl"
                    data-error-summary
                    class="relative overflow-hidden rounded-[2rem_1.5rem_2.25rem_1.75rem] border-[1.5px] border-danger bg-danger-surface p-5 shadow-card focus:outline-2 focus:outline-offset-4 focus:outline-focus sm:p-7"
                    role="alert"
                    tabindex="-1"
                    aria-labelledby="form-error-summary-title"
                >
                    <div class="flex items-start gap-3">
                        <span
                            class="blob-mask inline-flex size-11 shrink-0 items-center justify-center bg-danger text-white blob-3"
                            aria-hidden="true"
                        >
                            <CircleAlert class="size-6" />
                        </span>
                        <div class="min-w-0">
                            <h2
                                id="form-error-summary-title"
                                class="font-display-wonk text-2xl font-semibold text-ink sm:text-3xl"
                            >
                                {{
                                    errorEntries.length === 1
                                        ? 'Bitte prüfe 1 Angabe:'
                                        : `Bitte prüfe ${errorEntries.length} Angaben:`
                                }}
                            </h2>
                            <ul
                                class="mt-3 flex flex-col gap-1.5 text-base text-ink"
                            >
                                <li
                                    v-for="entry in errorEntries"
                                    :key="entry.key"
                                    class="flex items-start gap-2 text-wrap-anywhere"
                                >
                                    <span
                                        class="mt-2.5 size-1.5 shrink-0 rounded-[60%_40%_55%_45%] bg-danger"
                                        aria-hidden="true"
                                    ></span>
                                    <a
                                        v-if="entry.target"
                                        :href="`#${entry.target}`"
                                        class="min-w-0 py-0.5 font-medium text-danger underline decoration-2 underline-offset-4 hover:text-danger-hover"
                                        @click.prevent="
                                            focusField(entry.target)
                                        "
                                    >
                                        {{ entry.label }}: {{ entry.message }}
                                    </a>
                                    <span v-else class="min-w-0 py-0.5"
                                        >{{ entry.label }}:
                                        {{ entry.message }}</span
                                    >
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- 1 · Grunddaten -->
                <FormSection
                    id="abschnitt-grunddaten"
                    name="basics"
                    title-id="basics-title"
                    title="Grunddaten"
                    :number="1"
                >
                    <template #hint>
                        Mit <span aria-hidden="true" class="text-danger">*</span
                        ><span class="sr-only">Stern</span> markierte Angaben
                        sind Pflicht.
                    </template>

                    <div class="flex flex-col gap-6">
                        <div class="flex min-w-0 flex-col gap-1.5">
                            <label
                                for="name"
                                class="text-sm font-semibold text-ink"
                            >
                                Titel
                                <span class="text-danger" aria-hidden="true"
                                    >*</span
                                >
                            </label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="block min-h-14 w-full min-w-0 rounded-[1.1rem_0.9rem_1.2rem_1rem] border-[1.5px] border-line-strong bg-canvas px-4 py-2 font-display text-2xl font-semibold text-ink placeholder:font-sans placeholder:text-base placeholder:font-normal placeholder:text-ink-muted hover:border-primary focus-visible:border-primary focus-visible:bg-surface focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-focus aria-[invalid=true]:border-danger sm:text-3xl"
                                placeholder="z. B. Cremige One-Pot Pasta mit Spinat"
                                required
                                aria-required="true"
                                v-bind="invalidAttrs('name', 'name-error')"
                            />
                            <p
                                v-if="errorFor('name')"
                                id="name-error"
                                data-field-error
                                class="text-sm font-medium text-danger"
                            >
                                {{ errorFor('name') }}
                            </p>
                        </div>

                        <Field
                            id="description"
                            label="Kurzbeschreibung"
                            :error="errorFor('description')"
                            v-slot="field"
                        >
                            <Textarea
                                v-model="form.description"
                                :rows="3"
                                placeholder="Kurze Beschreibung, wann und wofür das Rezept ideal ist …"
                                v-bind="field.inputAttrs"
                            />
                        </Field>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <Field
                                id="cook_time"
                                label="Zubereitungsdauer (Min.)"
                                :error="errorFor('duration_minutes')"
                                v-slot="field"
                            >
                                <div class="relative">
                                    <Clock
                                        class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-moss"
                                        aria-hidden="true"
                                    />
                                    <input
                                        v-model="form.duration_minutes"
                                        type="number"
                                        min="0"
                                        inputmode="numeric"
                                        :class="numberInputClass"
                                        placeholder="20"
                                        v-bind="field.inputAttrs"
                                    />
                                </div>
                            </Field>

                            <Field
                                id="servings"
                                label="Portionen"
                                :error="errorFor('servings')"
                                v-slot="field"
                            >
                                <div class="relative">
                                    <Users
                                        class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-moss"
                                        aria-hidden="true"
                                    />
                                    <input
                                        v-model="form.servings"
                                        type="number"
                                        min="1"
                                        inputmode="numeric"
                                        :class="numberInputClass"
                                        placeholder="2"
                                        v-bind="field.inputAttrs"
                                    />
                                </div>
                            </Field>
                        </div>
                    </div>
                </FormSection>

                <!-- 2 · Bild -->
                <FormSection
                    id="abschnitt-bild"
                    name="image"
                    title-id="image-title"
                    title="Bild"
                    :number="2"
                    tone="plain"
                >
                    <div
                        class="grid items-center gap-8 sm:grid-cols-[minmax(0,17rem)_minmax(0,1fr)] md:grid-cols-[minmax(0,20rem)_minmax(0,1fr)]"
                    >
                        <!-- F-56: Vorschau des neuen bzw. aktuellen Bildes -->
                        <figure
                            class="relative mx-auto w-full max-w-[17rem] px-3 pt-3 pb-1 sm:max-w-none"
                            :data-image-preview="shownImage ? '' : undefined"
                        >
                            <span
                                class="pointer-events-none absolute inset-[8%_-4%_-5%_9%] animate-morph bg-matcha/80"
                                aria-hidden="true"
                            ></span>
                            <span
                                class="pointer-events-none absolute -top-2 left-[4%] size-[14%] rounded-[58%_42%_52%_48%/46%_54%_46%_54%] bg-berry shadow-berry"
                                aria-hidden="true"
                            ></span>
                            <BlobImage
                                :src="shownImage"
                                alt=""
                                :shape="3"
                                ratio="1/1"
                                eager
                                class="relative shadow-float"
                            >
                                <template #fallback>
                                    <div
                                        class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-tint-strong via-tint to-surface"
                                        aria-hidden="true"
                                    >
                                        <LeafOrnament
                                            variant="sprig"
                                            class="absolute -bottom-2 left-[14%] h-[56%] rotate-[-18deg] text-moss/70"
                                        />
                                        <span
                                            class="relative flex size-[38%] items-center justify-center rounded-full bg-surface shadow-float ring-8 ring-tint-strong"
                                        >
                                            <ImagePlus
                                                class="size-[44%] text-primary"
                                            />
                                        </span>
                                    </div>
                                </template>
                            </BlobImage>
                            <figcaption
                                class="relative mt-4 text-center text-sm font-medium text-ink-muted"
                            >
                                {{
                                    previewUrl
                                        ? 'Neues Bild – wird beim Speichern übernommen.'
                                        : shownImage
                                          ? 'Aktuelles Bild'
                                          : 'Noch kein Bild ausgewählt'
                                }}
                            </figcaption>
                        </figure>

                        <div class="flex min-w-0 flex-col gap-2">
                            <label
                                for="image"
                                class="font-display text-2xl font-semibold text-ink"
                            >
                                Bild hochladen
                            </label>
                            <p
                                id="image-hint"
                                data-field-hint
                                class="text-base text-ink-muted"
                            >
                                Optional – wird z. B. für die Vorschaukarte oder
                                Detailseite verwendet.
                            </p>
                            <!--
                                Deutsche Beschriftung statt „Choose File“: der
                                native Input bleibt fokussierbar und per Label
                                verknüpft, ist aber visuell versteckt; der
                                gestaltete Knopf ist aria-hidden, der
                                Dateiname wird über aria-describedby
                                angesagt.
                            -->
                            <div
                                data-image-picker
                                class="mt-2 flex min-w-0 flex-wrap items-center gap-x-3 gap-y-2 rounded-[1.1rem_0.9rem_1.2rem_1rem] border-[1.5px] border-dashed border-line-strong bg-surface p-2 hover:border-primary has-[:focus-visible]:border-primary has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-0 has-[:focus-visible]:outline-focus has-[[aria-invalid=true]]:border-danger"
                            >
                                <input
                                    id="image"
                                    ref="imageInput"
                                    type="file"
                                    accept="image/*"
                                    class="sr-only"
                                    v-bind="
                                        invalidAttrs(
                                            'image',
                                            'image-error',
                                            'image-hint image-file-name',
                                        )
                                    "
                                    @change="onImageChange"
                                />
                                <span
                                    data-image-picker-button
                                    class="inline-flex min-h-11 shrink-0 cursor-pointer items-center gap-2 rounded-pebble bg-primary px-5 font-semibold text-on-primary transition-colors hover:bg-primary-hover"
                                    aria-hidden="true"
                                    @click="imageInput?.click()"
                                >
                                    <ImagePlus class="size-5" />
                                    Bild auswählen
                                </span>
                                <span
                                    id="image-file-name"
                                    data-image-file-name
                                    class="min-w-0 text-base text-wrap-anywhere text-ink-muted"
                                    >{{
                                        imageFileName ??
                                        'Keine Datei ausgewählt'
                                    }}</span
                                >
                            </div>
                            <p
                                v-if="errorFor('image')"
                                id="image-error"
                                data-field-error
                                class="text-sm font-medium text-danger"
                            >
                                {{ errorFor('image') }}
                            </p>
                        </div>
                    </div>
                </FormSection>

                <!-- 3 · Zutaten (Papierblatt) -->
                <FormSection
                    id="abschnitt-zutaten"
                    name="ingredients"
                    title-id="ingredients-title"
                    title="Zutaten"
                    :number="3"
                    required
                >
                    <template #hint>
                        <p id="ingredients-hint">
                            Jede Zeile entspricht einer Zutat: erst die Menge,
                            dann die Bezeichnung. Mindestens eine Zutat ist
                            Pflicht; leere Zeilen werden beim Speichern
                            ignoriert.
                        </p>
                    </template>
                    <p
                        v-if="errorFor('ingredients')"
                        id="ingredients-error"
                        data-field-error
                        class="mb-4 text-sm font-medium text-danger"
                    >
                        {{ errorFor('ingredients') }}
                    </p>

                    <div
                        ref="ingredientsListEl"
                        data-ingredients-list
                        class="flex flex-col gap-3"
                    >
                        <div
                            v-for="(ingredient, index) in form.ingredients"
                            :key="ingredientIds[index]"
                            class="min-w-0"
                        >
                            <div
                                data-ingredient-row
                                :data-dragging="
                                    draggedIngredientIndex === index
                                        ? ''
                                        : undefined
                                "
                                class="grid grid-cols-[2.75rem_minmax(0,1fr)] gap-x-2 gap-y-2 rounded-[1.25rem_1rem_1.4rem_1.1rem] p-2 transition-[background-color,box-shadow] duration-200 sm:grid-cols-[auto_7.5rem_minmax(0,1fr)_auto] sm:items-center"
                                :class="
                                    draggedIngredientIndex === index
                                        ? 'bg-tint shadow-raised ring-2 ring-primary'
                                        : index % 2 === 0
                                          ? 'bg-canvas/70'
                                          : 'bg-transparent'
                                "
                            >
                                <!-- F-38: Umsortieren per Knopf (Tastatur) oder per Griff (Zeiger).
                                     Der Griff ist bewusst nicht fokussierbar: die Knöpfe sind
                                     der Tastaturweg, ein Griff ohne Tastenfunktion wäre ein
                                     toter Tab-Stopp. -->
                                <div
                                    v-if="form.ingredients.length > 1"
                                    data-ingredient-reorder
                                    class="row-span-3 flex flex-col items-center gap-1 self-start sm:row-span-1 sm:flex-row sm:self-center"
                                >
                                    <button
                                        :id="`ingredient-${ingredientIds[index]}-move-up`"
                                        type="button"
                                        data-move-up
                                        :class="reorderButtonClass"
                                        :aria-label="`Zutat ${index + 1} nach oben verschieben`"
                                        title="Nach oben verschieben"
                                        :disabled="index === 0"
                                        @click="moveIngredient(index, -1)"
                                    >
                                        <ChevronUp
                                            class="size-5"
                                            aria-hidden="true"
                                        />
                                    </button>
                                    <div
                                        data-ingredient-handle
                                        class="inline-flex size-11 cursor-grab touch-none items-center justify-center rounded-pebble text-ink-muted select-none hover:bg-tint-strong hover:text-ink active:cursor-grabbing"
                                        aria-hidden="true"
                                        title="Gedrückt halten und ziehen, um die Zutat zu verschieben"
                                        @pointerdown="
                                            startIngredientDrag(index, $event)
                                        "
                                        @contextmenu.prevent
                                    >
                                        <GripVertical class="size-5" />
                                    </div>
                                    <button
                                        :id="`ingredient-${ingredientIds[index]}-move-down`"
                                        type="button"
                                        data-move-down
                                        :class="reorderButtonClass"
                                        :aria-label="`Zutat ${index + 1} nach unten verschieben`"
                                        title="Nach unten verschieben"
                                        :disabled="
                                            index ===
                                            form.ingredients.length - 1
                                        "
                                        @click="moveIngredient(index, 1)"
                                    >
                                        <ChevronDown
                                            class="size-5"
                                            aria-hidden="true"
                                        />
                                    </button>
                                </div>

                                <input
                                    :id="`ingredient-${ingredientIds[index]}-amount`"
                                    v-model="ingredient.amount"
                                    type="text"
                                    data-ingredient-amount
                                    :class="[rowInputClass, 'col-start-2']"
                                    placeholder="z. B. 200 g"
                                    :aria-label="`Menge, Zutat ${index + 1}`"
                                    v-bind="
                                        rowInvalidAttrs(
                                            ingredientError(index, 'amount'),
                                            `ingredient-${ingredientIds[index]}-amount-error`,
                                        )
                                    "
                                />

                                <!-- Autocomplete für die Bezeichnung (F-36: schließt bei Blur/Escape) -->
                                <div
                                    class="relative col-start-2 min-w-0 sm:col-start-3"
                                >
                                    <input
                                        :id="`ingredient-${ingredientIds[index]}-name`"
                                        v-model="ingredient.name"
                                        type="text"
                                        data-ingredient-name
                                        :class="rowInputClass"
                                        placeholder="z. B. Pasta (Penne)"
                                        autocomplete="off"
                                        :aria-label="`Bezeichnung, Zutat ${index + 1}`"
                                        :required="index === 0"
                                        :aria-required="
                                            index === 0 ? 'true' : undefined
                                        "
                                        v-bind="{
                                            ...ingredientNameAttrs(index),
                                            ...comboboxAttrs(index),
                                        }"
                                        @focus="openIngredientDropdown(index)"
                                        @input="onIngredientNameInput(index)"
                                        @blur="closeIngredientDropdown"
                                        @keydown="
                                            onIngredientNameKeydown(
                                                index,
                                                $event,
                                            )
                                        "
                                    />

                                    <!-- F-38: Combobox-Muster (ARIA 1.2) – der Fokus bleibt im
                                         Eingabefeld, die aktive Option zeigt aria-activedescendant -->
                                    <ul
                                        v-if="isIngredientListOpen(index)"
                                        :id="`ingredient-${ingredientIds[index]}-listbox`"
                                        role="listbox"
                                        data-ingredient-suggestions
                                        :aria-label="`Vorschläge für Zutat ${index + 1}`"
                                        class="absolute inset-x-0 top-full z-(--z-dropdown) mt-1.5 max-h-64 scrollbar-thin overflow-y-auto rounded-[1.25rem_1rem_1.4rem_1.1rem] bg-surface p-1.5 shadow-raised ring-1 ring-line"
                                    >
                                        <li
                                            v-for="(
                                                option, optionIndex
                                            ) in getIngredientOptions(index)"
                                            :id="optionId(index, optionIndex)"
                                            :key="option.id"
                                            role="option"
                                            :aria-selected="
                                                activeOptionIndex ===
                                                optionIndex
                                            "
                                            class="flex min-h-11 w-full cursor-pointer items-center gap-2 rounded-[0.9rem_0.75rem_0.95rem_0.8rem] px-3 text-left text-base text-ink hover:bg-tint aria-selected:bg-tint aria-selected:outline-2 aria-selected:outline-offset-[-2px] aria-selected:outline-focus"
                                            @mousedown.prevent="
                                                selectIngredient(
                                                    index,
                                                    option.name,
                                                )
                                            "
                                        >
                                            <LeafOrnament
                                                variant="leaf"
                                                class="h-3.5 shrink-0 rotate-[30deg] text-moss"
                                            />
                                            <span
                                                class="min-w-0 text-wrap-anywhere"
                                                >{{ option.name }}</span
                                            >
                                        </li>
                                    </ul>
                                </div>

                                <div
                                    class="col-start-2 flex flex-wrap items-center gap-1.5 sm:col-start-4"
                                >
                                    <!-- + oder Stift abhängig davon, ob die Zutat existiert -->
                                    <button
                                        v-if="
                                            ingredient.name.trim() &&
                                            !findIngredientByName(
                                                ingredient.name,
                                            )
                                        "
                                        type="button"
                                        :class="rowActionClass"
                                        aria-label="Zutat neu anlegen"
                                        title="Zutat neu anlegen"
                                        :disabled="
                                            creatingIngredientRowId ===
                                            ingredientIds[index]
                                        "
                                        @click="createIngredientFromRow(index)"
                                    >
                                        <Plus
                                            class="size-5"
                                            aria-hidden="true"
                                        />
                                    </button>

                                    <button
                                        v-else-if="
                                            findIngredientByName(
                                                ingredient.name,
                                            )
                                        "
                                        type="button"
                                        :class="rowActionClass"
                                        aria-haspopup="dialog"
                                        aria-label="Zutat bearbeiten"
                                        title="Zutat bearbeiten"
                                        @click="
                                            openEditIngredientModal(
                                                findIngredientByName(
                                                    ingredient.name,
                                                )!,
                                            )
                                        "
                                    >
                                        <Pencil
                                            class="size-5"
                                            aria-hidden="true"
                                        />
                                    </button>

                                    <!-- Alternativen: Anzahl als Plakette NEBEN dem Knopf (rein grafisch),
                                         damit der zugängliche Name stabil bleibt -->
                                    <span class="relative inline-flex">
                                        <button
                                            :id="`ingredient-${ingredientIds[index]}-alternatives`"
                                            type="button"
                                            :class="rowActionClass"
                                            aria-haspopup="dialog"
                                            aria-label="Alternativzutaten bearbeiten"
                                            title="Alternativzutaten bearbeiten"
                                            v-bind="
                                                rowInvalidAttrs(
                                                    ingredientError(
                                                        index,
                                                        'alternatives',
                                                    ),
                                                    `ingredient-${ingredientIds[index]}-alternatives-error`,
                                                )
                                            "
                                            @click="
                                                openIngredientAlternatives(
                                                    index,
                                                )
                                            "
                                        >
                                            <ArrowLeftRight
                                                class="size-5"
                                                aria-hidden="true"
                                            />
                                        </button>
                                        <span
                                            v-if="
                                                ingredient
                                                    .alternativeIngredientIds
                                                    .length
                                            "
                                            data-alternatives-count
                                            class="pointer-events-none absolute -top-1 -right-1 inline-flex min-w-5 items-center justify-center rounded-[60%_40%_55%_45%] bg-berry px-1 text-xs font-semibold text-white"
                                            aria-hidden="true"
                                            >{{
                                                ingredient
                                                    .alternativeIngredientIds
                                                    .length
                                            }}</span
                                        >
                                    </span>

                                    <button
                                        v-if="form.ingredients.length > 1"
                                        type="button"
                                        :class="[
                                            rowActionClass,
                                            'text-danger hover:bg-danger-surface hover:text-danger-hover',
                                        ]"
                                        :aria-label="`Zutat ${index + 1} entfernen`"
                                        title="Zutat entfernen"
                                        @click="removeIngredient(index)"
                                    >
                                        <Trash2
                                            class="size-5"
                                            aria-hidden="true"
                                        />
                                    </button>
                                </div>
                            </div>

                            <div
                                v-if="rowMessages(index).length"
                                data-ingredient-errors
                                class="mt-1 flex flex-col gap-1 pl-2 sm:pl-[3.75rem]"
                            >
                                <p
                                    v-for="msg in rowMessages(index)"
                                    :id="msg.id"
                                    :key="msg.id"
                                    data-field-error
                                    class="text-sm font-medium text-danger"
                                    :role="msg.live ? 'alert' : undefined"
                                >
                                    {{ msg.text }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <Button
                        variant="secondary"
                        class="mt-5"
                        @click="addIngredient"
                    >
                        <Plus class="size-5" aria-hidden="true" />
                        <span class="sr-only">+ </span>weitere Zutat
                    </Button>
                </FormSection>

                <!-- 4 · Zubereitung: Schritte mit großen Ziffern und Ranke -->
                <FormSection
                    id="abschnitt-zubereitung"
                    name="steps"
                    title-id="steps-title"
                    title="Zubereitung"
                    :number="4"
                    tone="plain"
                    required
                >
                    <template #hint>
                        Die Reihenfolge der Schritte entspricht der Darstellung
                        im Rezept. Ein Schritt darf mehrere Zeilen haben.
                        Mindestens ein Schritt ist Pflicht.
                    </template>
                    <p
                        v-if="errorFor('steps')"
                        id="steps-error"
                        data-field-error
                        class="mb-4 text-sm font-medium text-danger"
                    >
                        {{ errorFor('steps') }}
                    </p>

                    <ol
                        ref="stepsListEl"
                        data-steps-list
                        class="flex flex-col gap-6"
                    >
                        <li
                            v-for="(step, index) in form.steps"
                            :key="stepIds[index]"
                            data-step-row
                            :data-dragging="
                                draggedStepIndex === index ? '' : undefined
                            "
                            class="relative grid grid-cols-[3.5rem_minmax(0,1fr)] gap-x-3 rounded-[1.75rem_1.4rem_2rem_1.5rem] p-1.5 transition-[background-color,box-shadow] duration-200 sm:grid-cols-[4rem_minmax(0,1fr)] sm:gap-x-5"
                            :class="
                                draggedStepIndex === index &&
                                'bg-tint shadow-raised ring-2 ring-primary'
                            "
                        >
                            <!-- Ranke zum nächsten Schritt -->
                            <span
                                v-if="index < form.steps.length - 1"
                                class="pointer-events-none absolute top-16 -bottom-7 left-[2.2rem] border-l-2 border-dashed border-tint-strong sm:left-[2.45rem]"
                                aria-hidden="true"
                            ></span>

                            <div
                                class="relative flex flex-col items-center gap-1.5"
                            >
                                <span
                                    class="blob-mask inline-flex size-14 shrink-0 items-center justify-center font-display-wonk text-3xl font-semibold shadow-card sm:size-16 sm:text-4xl"
                                    :class="[
                                        stepShape(index),
                                        index % 2 === 0
                                            ? 'bg-matcha text-forest'
                                            : 'bg-forest text-matcha',
                                    ]"
                                    aria-hidden="true"
                                    >{{ index + 1 }}</span
                                >
                                <!-- F-38: Griff nur für Zeiger (nicht fokussierbar);
                                     per Tastatur über „nach oben/unten“ unter dem Text -->
                                <div
                                    v-if="form.steps.length > 1"
                                    data-step-handle
                                    class="relative inline-flex size-11 cursor-grab touch-none items-center justify-center rounded-pebble bg-canvas text-ink-muted select-none hover:bg-tint-strong hover:text-ink active:cursor-grabbing"
                                    aria-hidden="true"
                                    title="Gedrückt halten und ziehen, um den Schritt zu verschieben"
                                    @pointerdown="startStepDrag(index, $event)"
                                    @contextmenu.prevent
                                >
                                    <GripVertical class="size-5" />
                                </div>
                            </div>

                            <div class="min-w-0 pt-1">
                                <!-- ADR-001: ein Schritt = ein mehrzeiliger Text -->
                                <textarea
                                    :id="`step-${stepIds[index]}`"
                                    v-model="form.steps[index]"
                                    rows="3"
                                    data-step-text
                                    class="block w-full min-w-0 resize-y rounded-[1.25rem_1rem_1.4rem_1.1rem] border-[1.5px] border-line-strong bg-surface px-4 py-3 text-lg leading-relaxed text-ink shadow-card placeholder:text-base placeholder:text-ink-muted hover:border-primary focus-visible:border-primary focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-focus aria-[invalid=true]:border-danger"
                                    placeholder="Beschreibe hier den Schritt …"
                                    :aria-label="`Schritt ${index + 1}`"
                                    :required="index === 0"
                                    :aria-required="
                                        index === 0 ? 'true' : undefined
                                    "
                                    v-bind="stepAttrs(index)"
                                ></textarea>
                                <div
                                    class="mt-1.5 flex flex-wrap items-start justify-between gap-2"
                                >
                                    <p
                                        v-if="stepError(index)"
                                        :id="`step-${stepIds[index]}-error`"
                                        data-field-error
                                        class="min-w-0 basis-full py-2 text-sm font-medium text-danger"
                                    >
                                        {{ stepError(index) }}
                                    </p>
                                    <div
                                        v-if="form.steps.length > 1"
                                        data-step-reorder
                                        class="flex items-center gap-1.5"
                                    >
                                        <button
                                            :id="`step-${stepIds[index]}-move-up`"
                                            type="button"
                                            data-move-up
                                            :class="reorderButtonClass"
                                            :aria-label="`Schritt ${index + 1} nach oben verschieben`"
                                            title="Nach oben verschieben"
                                            :disabled="index === 0"
                                            @click="moveStep(index, -1)"
                                        >
                                            <ChevronUp
                                                class="size-5"
                                                aria-hidden="true"
                                            />
                                        </button>
                                        <button
                                            :id="`step-${stepIds[index]}-move-down`"
                                            type="button"
                                            data-move-down
                                            :class="reorderButtonClass"
                                            :aria-label="`Schritt ${index + 1} nach unten verschieben`"
                                            title="Nach unten verschieben"
                                            :disabled="
                                                index === form.steps.length - 1
                                            "
                                            @click="moveStep(index, 1)"
                                        >
                                            <ChevronDown
                                                class="size-5"
                                                aria-hidden="true"
                                            />
                                        </button>
                                    </div>
                                    <Button
                                        v-if="form.steps.length > 1"
                                        variant="ghost"
                                        size="sm"
                                        class="ml-auto min-h-11 text-danger hover:bg-danger-surface"
                                        :aria-label="`Schritt ${index + 1} entfernen`"
                                        @click="removeStep(index)"
                                    >
                                        <Trash2
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        entfernen
                                    </Button>
                                </div>
                            </div>
                        </li>
                    </ol>

                    <Button variant="secondary" class="mt-6" @click="addStep">
                        <Plus class="size-5" aria-hidden="true" />
                        <span class="sr-only">+ </span>weiteren Schritt
                        hinzufügen
                    </Button>
                </FormSection>

                <!-- 5 · Notizen -->
                <FormSection
                    id="abschnitt-notizen"
                    name="notes"
                    title-id="notes-title"
                    title="Notizen & Varianten"
                    :number="5"
                    tone="tint"
                >
                    <template #hint>
                        Optionale Hinweise, Varianten oder Aufbewahrungstipps.
                    </template>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="4"
                        class="block w-full min-w-0 resize-y rounded-[1.25rem_1rem_1.4rem_1.1rem] border-[1.5px] border-line-strong bg-surface px-4 py-3 text-base leading-relaxed text-ink placeholder:text-ink-muted hover:border-primary focus-visible:border-primary focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-focus aria-[invalid=true]:border-danger"
                        placeholder="z. B. Hält sich abgedeckt zwei Tage im Kühlschrank …"
                        aria-labelledby="notes-title"
                        v-bind="invalidAttrs('notes', 'notes-error')"
                    ></textarea>
                    <p
                        v-if="errorFor('notes')"
                        id="notes-error"
                        data-field-error
                        class="mt-1.5 text-sm font-medium text-danger"
                    >
                        {{ errorFor('notes') }}
                    </p>
                </FormSection>

                <!-- 6 · Kategorien (Tags) -->
                <FormSection
                    id="abschnitt-kategorien"
                    name="categories"
                    title-id="tags-label"
                    title="Kategorien"
                    :number="6"
                    tone="plain"
                >
                    <template #hint>
                        Optional. Antippen wählt eine Kategorie aus; der Stift
                        daneben öffnet das Bearbeiten (am Rechner auch per
                        Doppelklick auf die Kategorie).
                    </template>
                    <div
                        class="flex flex-wrap gap-2.5"
                        role="group"
                        aria-labelledby="tags-label"
                    >
                        <!-- Umschalter + Bearbeiten-Knopf je Kategorie
                             (Tastatur/Touch, WCAG 2.1.1 / 2.5.8) -->
                        <span
                            v-for="category in categoryOptions"
                            :key="category.id"
                            class="inline-flex max-w-full min-w-0 items-center gap-1"
                        >
                            <SeedToggle
                                data-tag-chip
                                :pressed="form.tags.includes(category.name)"
                                @click="toggleTag(category.name)"
                                @dblclick.stop="
                                    openTagEditModal(category, $event)
                                "
                            >
                                {{ category.name }}
                            </SeedToggle>
                            <button
                                :ref="(el) => setTagEditButton(category.id, el)"
                                type="button"
                                data-tag-edit
                                class="inline-flex size-11 shrink-0 items-center justify-center rounded-full text-moss transition-colors hover:bg-tint hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                                aria-haspopup="dialog"
                                :aria-label="`Kategorie „${category.name}“ bearbeiten`"
                                :title="`Kategorie „${category.name}“ bearbeiten`"
                                @click="openTagEditModal(category, $event)"
                            >
                                <Pencil class="size-4" aria-hidden="true" />
                            </button>
                        </span>
                    </div>

                    <div class="mt-6 flex max-w-xl min-w-0 flex-col gap-1.5">
                        <label
                            for="tag-draft"
                            class="text-sm font-semibold text-ink"
                        >
                            Neue Kategorie
                        </label>
                        <div class="relative">
                            <Tag
                                class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-moss"
                                aria-hidden="true"
                            />
                            <input
                                id="tag-draft"
                                v-model="tagDraft"
                                type="text"
                                :class="[numberInputClass]"
                                placeholder="Weitere Tags mit Enter hinzufügen …"
                                :aria-invalid="
                                    tagDraftError || tagsError
                                        ? 'true'
                                        : undefined
                                "
                                :aria-describedby="tagDescribedBy"
                                :disabled="tagDraftBusy"
                                @input="tagDraftError = null"
                                @keydown.enter.prevent="addTagFromDraft"
                            />
                        </div>
                        <p
                            id="tag-draft-hint"
                            data-field-hint
                            class="text-sm text-ink-muted"
                        >
                            Mit Enter anlegen und gleich auswählen.
                        </p>
                        <p
                            v-if="tagDraftError"
                            id="tag-draft-error"
                            data-field-error
                            class="text-sm font-medium text-danger"
                            role="alert"
                        >
                            {{ tagDraftError }}
                        </p>
                        <p
                            v-if="tagsError"
                            id="tags-error"
                            data-field-error
                            class="text-sm font-medium text-danger"
                        >
                            {{ tagsError }}
                        </p>
                    </div>
                </FormSection>

                <!-- 7 · Bewertung: EINE Sterne-Eingabe „Geschmack“ (optional, zurücksetzbar) -->
                <FormSection
                    v-if="tasteDimension"
                    id="abschnitt-bewertung"
                    name="ratings"
                    title-id="ratings-title"
                    title="Bewertung"
                    :number="7"
                    tone="berry"
                >
                    <template #hint>
                        Optional – wie hat es geschmeckt? 1 bis 5 Sterne;
                        Pfeiltasten wählen den Wert.
                    </template>
                    <p
                        v-if="errorFor('ratings')"
                        id="ratings-error"
                        data-field-error
                        class="mb-4 text-sm font-medium text-danger"
                    >
                        {{ errorFor('ratings') }}
                    </p>
                    <div
                        class="relative flex max-w-xl min-w-0 flex-col gap-2 overflow-hidden rounded-[1.75rem_1.4rem_2rem_1.5rem] bg-surface/90 p-5 shadow-card sm:flex-row sm:items-center sm:gap-6 sm:p-6"
                    >
                        <!-- große Zahl rein grafisch; ohne Wert ein leerer Stern -->
                        <span
                            v-if="form.ratings[tasteDimension.id]"
                            class="font-display-wonk text-6xl leading-none font-semibold text-berry sm:text-7xl"
                            aria-hidden="true"
                            >{{ form.ratings[tasteDimension.id] }}</span
                        >
                        <span
                            v-else
                            class="blob-mask inline-flex size-16 shrink-0 items-center justify-center bg-berry-surface text-berry blob-2"
                            aria-hidden="true"
                        >
                            <Star class="size-8" />
                        </span>
                        <div class="min-w-0">
                            <StarRating
                                :id="`rating-${tasteDimension.id}`"
                                v-model="form.ratings[tasteDimension.id]"
                                mode="input"
                                label="Geschmack"
                                :aria-describedby="
                                    ratingDescribedBy(tasteDimension.id)
                                "
                                class="w-full"
                            />
                            <p
                                v-if="tasteDimension.description"
                                :id="`rating-${tasteDimension.id}-hint`"
                                class="mt-1 text-sm text-ink-muted"
                            >
                                {{ tasteDimension.description }}
                            </p>
                            <p
                                v-if="ratingError(tasteDimension.id)"
                                :id="`rating-${tasteDimension.id}-error`"
                                data-field-error
                                class="mt-1 text-sm font-medium text-danger"
                            >
                                {{ ratingError(tasteDimension.id) }}
                            </p>
                        </div>
                    </div>
                </FormSection>

                <!-- Abschluss: Aktionen auf Waldgrün -->
                <div
                    class="relative overflow-hidden rounded-[2.5rem_2rem_2.75rem_2.25rem] bg-forest-deep grain-light p-6 on-forest sm:p-9"
                >
                    <LeafOrnament
                        variant="sprig"
                        class="pointer-events-none absolute -right-4 -bottom-8 h-36 rotate-[-24deg] text-forest-soft"
                    />
                    <div
                        class="relative flex flex-col gap-6 md:flex-row md:items-center md:justify-between"
                    >
                        <div class="min-w-0">
                            <p
                                class="font-display-wonk text-3xl font-semibold text-on-forest"
                            >
                                {{
                                    mode === 'edit'
                                        ? 'Alles angepasst?'
                                        : 'Fertig gesammelt?'
                                }}
                            </p>
                            <p class="mt-1 text-base text-on-forest-muted">
                                Felder können jederzeit später bearbeitet
                                werden.
                            </p>
                        </div>

                        <!-- F-56: Zurücksetzen nur nach Rückfrage -->
                        <div
                            v-if="confirmingReset"
                            class="flex min-w-0 flex-col gap-3 rounded-[1.5rem_1.2rem_1.6rem_1.3rem] bg-forest-soft p-4 sm:flex-row sm:flex-wrap sm:items-center"
                            role="group"
                            aria-labelledby="reset-confirm-text"
                        >
                            <span
                                id="reset-confirm-text"
                                class="min-w-0 font-semibold text-on-forest"
                            >
                                Alle ungespeicherten Änderungen verwerfen?
                            </span>
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    ref="resetKeepButton"
                                    variant="lime"
                                    @click="cancelReset"
                                >
                                    Behalten
                                </Button>
                                <Button
                                    variant="outline-light"
                                    class="text-danger-on-forest"
                                    @click="confirmReset"
                                >
                                    Ja, zurücksetzen
                                </Button>
                            </div>
                        </div>

                        <div
                            v-else
                            class="flex flex-col-reverse gap-2.5 sm:flex-row sm:flex-wrap sm:items-center md:justify-end"
                        >
                            <Button
                                v-if="cancelHref"
                                :href="cancelHref"
                                variant="ghost"
                                class="text-on-forest hover:bg-forest-soft active:bg-forest"
                            >
                                Abbrechen
                            </Button>
                            <Button
                                ref="resetButton"
                                variant="outline-light"
                                :disabled="form.processing"
                                @click="askReset"
                            >
                                <RotateCcw class="size-4" aria-hidden="true" />
                                Zurücksetzen
                            </Button>
                            <Button
                                type="submit"
                                variant="lime"
                                size="lg"
                                :loading="form.processing"
                            >
                                <Check class="size-5" aria-hidden="true" />
                                {{
                                    mode === 'edit'
                                        ? 'Rezept aktualisieren'
                                        : 'Rezept speichern'
                                }}
                            </Button>
                        </div>
                    </div>
                </div>
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
            @close="closeTagEditModal"
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
import FormSection from '@/components/recipes/form/FormSection.vue';
import SeedToggle from '@/components/recipes/form/SeedToggle.vue';
import BlobImage from '@/components/ui/BlobImage.vue';
import Button from '@/components/ui/Button.vue';
import Field from '@/components/ui/Field.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import StarRating from '@/components/ui/StarRating.vue';
import Textarea from '@/components/ui/Textarea.vue';
import IngredientAlternativesModal from '@/components/widgets/IngredientAlternativesModal.vue';
import IngredientEditModal from '@/components/widgets/IngredientEditModal.vue';
import TagEditModal from '@/components/widgets/TagEditModal.vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ArrowLeftRight,
    Check,
    ChevronDown,
    ChevronUp,
    CircleAlert,
    Clock,
    GripVertical,
    ImagePlus,
    Pencil,
    Plus,
    RotateCcw,
    Star,
    Tag,
    Trash2,
    Users,
} from 'lucide-vue-next';
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

// Gate C2: gemeinsame Klassen der Eingabefelder (Kiesel-Ecken, 16 px+, 44 px hoch)
const fieldBase =
    'block min-h-11 w-full min-w-0 rounded-[0.9rem_0.75rem_0.95rem_0.8rem] border-[1.5px] border-line-strong bg-surface py-2 text-base text-ink placeholder:text-ink-muted hover:border-primary focus-visible:border-primary focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-focus disabled:cursor-not-allowed disabled:bg-canvas aria-[invalid=true]:border-danger';
/** Feld mit Symbol links (Dauer, Portionen, neue Kategorie) */
const numberInputClass = `${fieldBase} pr-3.5 pl-11`;
/** Felder einer Zutatenzeile */
const rowInputClass = `${fieldBase} px-3.5`;
/** Symbol-Knöpfe einer Zutatenzeile (44 × 44 px) */
const rowActionClass =
    'inline-flex size-11 shrink-0 items-center justify-center rounded-pebble border-[1.5px] border-line bg-surface text-primary transition-[background-color,border-color,border-radius] duration-200 hover:border-primary hover:bg-tint focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus disabled:cursor-not-allowed disabled:opacity-60';

/** F-38: Nach-oben/Nach-unten-Knöpfe (44 × 44 px, am Rand deaktiviert) */
const reorderButtonClass =
    'inline-flex size-11 shrink-0 items-center justify-center rounded-pebble border-[1.5px] border-line bg-surface text-primary transition-[background-color,border-color] duration-200 hover:border-primary hover:bg-tint focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus disabled:cursor-not-allowed disabled:border-transparent disabled:bg-transparent disabled:text-ink-muted disabled:opacity-50';

/** Schritt-Ziffern in wechselnden Blob-Formen (wie auf der Detailseite) */
const stepShape = (index: number) =>
    ['blob-1', 'blob-4', 'blob-2', 'blob-5', 'blob-3', 'blob-6'][index % 6];

/**
 * Bewertung nur noch nach Geschmack (Entscheidung des Auftraggebers): der
 * Server liefert höchstens EINE Dimension. Kommen doch mehrere an, gilt der
 * Eintrag „Geschmack“, sonst der erste. Ohne Dimension entfällt der Abschnitt.
 */
const tasteDimension = computed<RatingDimension | null>(
    () =>
        props.ratingDimensions.find(
            (d) => d.name.trim().toLowerCase() === 'geschmack',
        ) ??
        props.ratingDimensions[0] ??
        null,
);

/** Kapitel der Navigation (gleiche Reihenfolge wie die Abschnitte) */
const chapters = computed(() => [
    { id: 'abschnitt-grunddaten', label: 'Grunddaten' },
    { id: 'abschnitt-bild', label: 'Bild' },
    { id: 'abschnitt-zutaten', label: 'Zutaten' },
    { id: 'abschnitt-zubereitung', label: 'Zubereitung' },
    { id: 'abschnitt-notizen', label: 'Notizen' },
    { id: 'abschnitt-kategorien', label: 'Kategorien' },
    ...(tasteDimension.value
        ? [{ id: 'abschnitt-bewertung', label: 'Bewertung' }]
        : []),
]);

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
        props.ratingDimensions.find((d) => d.id === dimensionId)?.description
            ? `rating-${dimensionId}-hint`
            : undefined,
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
            'tag-draft-hint',
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
    add('image', 'Bild', 'image');

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

    // Gate C2: Reihenfolge wie im Formular (Kategorien und Bewertung am Ende)
    Object.keys(errors)
        .filter((k) => k === 'tags' || k.startsWith('tags.'))
        .forEach((k) => add(k, 'Tags', 'tag-draft'));

    // Sprungziel ist die Sternegruppe; `focusField` setzt den Fokus auf ihr
    // gewähltes bzw. erstes Optionsfeld
    const taste = tasteDimension.value;
    add('ratings', 'Bewertung', taste ? `rating-${taste.id}` : null);
    if (taste) {
        add(
            `ratings.${taste.id}`,
            'Bewertung – Geschmack',
            `rating-${taste.id}`,
        );
    }

    // Alles, was keinem Feld zugeordnet werden konnte, trotzdem nennen
    Object.keys(errors).forEach((k) => add(k, 'Formular', null));

    return entries;
});

const focusField = (id: string) => {
    const el = document.getElementById(id);
    if (!el) return;
    // Gruppen (z. B. die Sterne-Fieldsets) sind selbst nicht fokussierbar:
    // dann das gewählte bzw. erste Eingabeelement darin
    const target = el.matches('input, textarea, select, button, a[href]')
        ? el
        : (el.querySelector<HTMLElement>('input:checked') ??
          el.querySelector<HTMLElement>('input, textarea, select, button') ??
          el);
    target.scrollIntoView({ block: 'center' });
    target.focus();
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

// F-38: aktive Option der offenen Vorschlagsliste (-1 = keine)
const activeOptionIndex = ref(-1);

// Dropdown öffnen
const openIngredientDropdown = (index: number) => {
    if (ingredientDropdownOpenIndex.value !== index)
        activeOptionIndex.value = -1;
    ingredientDropdownOpenIndex.value = index;
};

const onIngredientNameInput = (index: number) => {
    delete ingredientCreateErrors.value[ingredientIds.value[index]];
    openIngredientDropdown(index);
    activeOptionIndex.value = -1;
};

// Dropdown schließen
const closeIngredientDropdown = () => {
    ingredientDropdownOpenIndex.value = null;
    activeOptionIndex.value = -1;
};

const isIngredientListOpen = (index: number) =>
    ingredientDropdownOpenIndex.value === index &&
    getIngredientOptions(index).length > 0;

const optionId = (index: number, optionIndex: number) =>
    `ingredient-${ingredientIds.value[index]}-option-${optionIndex}`;

/** Combobox-Attribute des Bezeichnungsfelds (ARIA 1.2, Liste mit Vorschlägen). */
const comboboxAttrs = (index: number) => {
    const open = isIngredientListOpen(index);
    return {
        role: 'combobox',
        'aria-autocomplete': 'list' as const,
        'aria-expanded': open ? ('true' as const) : ('false' as const),
        'aria-controls': open
            ? `ingredient-${ingredientIds.value[index]}-listbox`
            : undefined,
        'aria-activedescendant':
            open && activeOptionIndex.value >= 0
                ? optionId(index, activeOptionIndex.value)
                : undefined,
    };
};

const setActiveOption = async (index: number, optionIndex: number) => {
    activeOptionIndex.value = optionIndex;
    await nextTick();
    document
        .getElementById(optionId(index, optionIndex))
        ?.scrollIntoView({ block: 'nearest' });
};

/**
 * Tastatur im Bezeichnungsfeld: Pfeil runter/hoch öffnet die Liste bzw. wählt
 * die nächste/vorige Option, Enter übernimmt die aktive Option, Escape
 * schließt. Ohne aktive Option bleibt Enter beim Standard (Formular absenden).
 */
const onIngredientNameKeydown = (index: number, event: KeyboardEvent) => {
    const options = getIngredientOptions(index);
    const open = isIngredientListOpen(index);
    switch (event.key) {
        case 'ArrowDown':
        case 'ArrowUp': {
            if (event.altKey) {
                event.preventDefault();
                if (event.key === 'ArrowDown') openIngredientDropdown(index);
                else closeIngredientDropdown();
                return;
            }
            if (!options.length) return;
            event.preventDefault();
            if (!open) openIngredientDropdown(index);
            const last = options.length - 1;
            const current = open ? activeOptionIndex.value : -1;
            const next =
                event.key === 'ArrowDown'
                    ? current >= last
                        ? 0
                        : current + 1
                    : current <= 0
                      ? last
                      : current - 1;
            void setActiveOption(index, next);
            return;
        }
        case 'Enter': {
            const option = options[activeOptionIndex.value];
            if (open && option) {
                event.preventDefault();
                selectIngredient(index, option.name);
            }
            return;
        }
        case 'Escape':
            if (open) event.preventDefault();
            closeIngredientDropdown();
            return;
    }
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

// ---------------------------------------------------------------------------
// F-38: Umsortieren per Tastatur (Nach-oben/Nach-unten-Knöpfe je Zeile)
//
// Nach dem Verschieben bleibt der Fokus auf dem gleichen Knopf der
// verschobenen Zeile; ist dieser am Rand deaktiviert, wandert er auf den
// Knopf der Gegenrichtung. Eine Live-Region sagt die neue Position an.
// ---------------------------------------------------------------------------
const reorderAnnouncement = ref('');

const announceMove = async (
    label: 'Schritt' | 'Zutat',
    from: number,
    to: number,
) => {
    // erst leeren, damit gleiche Ansagen nacheinander wiederholt werden
    reorderAnnouncement.value = '';
    await nextTick();
    reorderAnnouncement.value = `${label} ${from + 1} ist jetzt ${label} ${to + 1}`;
};

const moveRow = async (options: {
    label: 'Schritt' | 'Zutat';
    prefix: 'step' | 'ingredient';
    rows: unknown[];
    ids: number[];
    index: number;
    direction: -1 | 1;
}) => {
    const { rows, ids, index, direction } = options;
    const to = index + direction;
    if (to < 0 || to >= rows.length) return;

    const [row] = rows.splice(index, 1);
    rows.splice(to, 0, row);
    const [rowId] = ids.splice(index, 1);
    ids.splice(to, 0, rowId);

    await nextTick();
    const atEdge = to === 0 || to === rows.length - 1;
    const same = direction === -1 ? 'up' : 'down';
    const opposite = direction === -1 ? 'down' : 'up';
    const sameButton = document.getElementById(
        `${options.prefix}-${rowId}-move-${same}`,
    ) as HTMLButtonElement | null;
    const target =
        sameButton && !sameButton.disabled && !atEdge
            ? sameButton
            : document.getElementById(
                  `${options.prefix}-${rowId}-move-${opposite}`,
              );
    target?.focus();
    void announceMove(options.label, index, to);
};

const moveStep = (index: number, direction: -1 | 1) => {
    discardRowErrors('steps');
    void moveRow({
        label: 'Schritt',
        prefix: 'step',
        rows: form.steps,
        ids: stepIds.value,
        index,
        direction,
    });
};

const moveIngredient = (index: number, direction: -1 | 1) => {
    // offenes Autocomplete zeigt sonst auf die falsche Zeile
    closeIngredientDropdown();
    discardRowErrors('ingredients');
    void moveRow({
        label: 'Zutat',
        prefix: 'ingredient',
        rows: form.ingredients,
        ids: ingredientIds.value,
        index,
        direction,
    });
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
    // nach dem Loslassen; `moved` = Reihenfolge hat sich geändert,
    // `from`/`to` = Position beim Greifen bzw. Loslassen
    onEnd?: (moved: boolean, from: number, to: number) => void;
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
            const to = options.dragged.value ?? index;
            options.dragged.value = null;
            window.removeEventListener('pointermove', onMove);
            window.removeEventListener('pointerup', stop);
            window.removeEventListener('pointercancel', stop);
            options.onEnd?.(moved, index, to);
        };

        window.addEventListener('pointermove', onMove);
        window.addEventListener('pointerup', stop);
        window.addEventListener('pointercancel', stop);
    };
};

const startStepDrag = createRowDragHandler({
    listEl: () => stepsListEl.value,
    rowSelector: '[data-step-row]',
    dragged: draggedStepIndex,
    move: (from, to) => {
        const [step] = form.steps.splice(from, 1);
        form.steps.splice(to, 0, step);

        const [id] = stepIds.value.splice(from, 1);
        stepIds.value.splice(to, 0, id);
    },
    // Fehler erst nach dem Loslassen verwerfen: Während des Ziehens bleiben
    // sie über die Zeilen-ID korrekt zugeordnet, und das Layout springt nicht
    onEnd: (moved, from, to) => {
        if (!moved) return;
        discardRowErrors('steps');
        if (from !== to) announceMove('Schritt', from, to);
    },
});

const startIngredientDrag = createRowDragHandler({
    listEl: () => ingredientsListEl.value,
    rowSelector: '[data-ingredient-row]',
    dragged: draggedIngredientIndex,
    move: (from, to) => {
        const [row] = form.ingredients.splice(from, 1);
        form.ingredients.splice(to, 0, row);

        const [id] = ingredientIds.value.splice(from, 1);
        ingredientIds.value.splice(to, 0, id);
    },
    onEnd: (moved, from, to) => {
        if (!moved) return;
        discardRowErrors('ingredients');
        if (from !== to) announceMove('Zutat', from, to);
    },
    // offenes Autocomplete-Dropdown schließen, sonst zeigt es
    // nach dem Umsortieren auf die falsche Zeile
    onStart: () => closeIngredientDropdown(),
});

// Bewertung: `StarRating` (Eingabemodus) schreibt per v-model direkt in
// `form.ratings[dimensionId]`; „Bewertung entfernen“ setzt `null`
// (der Server überspringt `null`).

/** Knopf-Baustein: Fokus auf das gerenderte Element */
type ButtonInstance = { $el: HTMLElement };
const focusButton = (button: ButtonInstance | null) => button?.$el?.focus();

// F-56: Bildvorschau (neu gewählte Datei bzw. aktuelles Bild)
const imageInput = ref<HTMLInputElement | null>(null);
/** Name der gewählten Datei (deutsche Anzeige statt „No file chosen“) */
const imageFileName = ref<string | null>(null);
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
        imageFileName.value = null;
        return;
    }

    form.image = target.files[0];
    imageFileName.value = target.files[0].name;
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
        // Nur die Geschmacks-Bewertung senden ({ [dimensionId]: value | null });
        // ältere Werte anderer Dimensionen aus den Edit-Props fallen weg
        ratings: tasteDimension.value
            ? {
                  [tasteDimension.value.id]:
                      data.ratings[tasteDimension.value.id] ?? null,
              }
            : {},
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
const resetButton = ref<ButtonInstance | null>(null);
const resetKeepButton = ref<ButtonInstance | null>(null);

const askReset = async () => {
    confirmingReset.value = true;
    await nextTick();
    focusButton(resetKeepButton.value);
};

const cancelReset = async () => {
    confirmingReset.value = false;
    await nextTick();
    focusButton(resetButton.value);
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
    imageFileName.value = null;
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
/** Bearbeiten-Knöpfe je Kategorie-ID (Fokus-Rückgabe nach dem Dialog) */
const tagEditButtons = new Map<number, HTMLElement>();
const setTagEditButton = (id: number, el: unknown) => {
    if (el instanceof HTMLElement) tagEditButtons.set(id, el);
    else tagEditButtons.delete(id);
};
/** Auslöser des Tag-Dialogs (Stift-Knopf oder per Doppelklick der Umschalter) */
let tagEditTrigger: HTMLElement | null = null;

const openTagEditModal = (category: Category, event?: Event) => {
    const target = event?.currentTarget;
    tagEditTrigger =
        target instanceof HTMLElement
            ? target
            : (tagEditButtons.get(category.id) ?? null);
    tagBeingEdited.value = category;
    isTagModalOpen.value = true;
};

const closeTagEditModal = async () => {
    const category = tagBeingEdited.value;
    isTagModalOpen.value = false;
    await nextTick();
    // Fokus zurück an den Auslöser; wurde die Kategorie gelöscht, auf das
    // Feld „Neue Kategorie“ (der Auslöser existiert dann nicht mehr).
    const fallback =
        (category ? tagEditButtons.get(category.id) : undefined) ??
        document.getElementById('tag-draft');
    const target =
        tagEditTrigger && tagEditTrigger.isConnected
            ? tagEditTrigger
            : fallback;
    tagEditTrigger = null;
    target?.focus();
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

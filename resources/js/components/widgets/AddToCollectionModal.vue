<script setup lang="ts">
import { useModal } from '@/composables/useModal';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';

type CollectionOption = {
    id: number;
    name: string;
};

const props = defineProps<{
    open: boolean;
    collections: CollectionOption[];
    recipeId: number;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const page = usePage();

// F-39: Gäste bekommen einen Login-Hinweis statt einer irreführenden Meldung
const isGuest = computed(
    () => !(page.props as { auth?: { user?: unknown } }).auth?.user,
);

// Mehrfachauswahl: Array von IDs
const selectedCollectionIds = ref<number[]>([]);
const processing = ref(false);
const errorMessage = ref<string | null>(null);
// Teilerfolg: Sammlungen, in denen das Rezept in diesem Durchgang gespeichert wurde
const savedNames = ref<string[]>([]);

const dialogEl = ref<HTMLElement | null>(null);

const resetState = () => {
    selectedCollectionIds.value = [];
    errorMessage.value = null;
    savedNames.value = [];
};

watch(
    () => props.open,
    (open) => {
        if (open) resetState();
    },
);

const close = () => {
    if (processing.value) return;
    resetState();
    emit('close');
};

// F-30: Ebene, Scroll-Sperre, Escape, Fokus
useModal({
    open: () => props.open,
    dialog: dialogEl,
    onClose: close,
});

const nameOf = (id: number) =>
    props.collections.find((c) => c.id === id)?.name ?? `#${id}`;

/** Verständliche deutsche Meldung je HTTP-Status (ohne Status: Netzwerkfehler). */
const describeStatus = (status?: number): string => {
    if (!status)
        return 'Keine Verbindung zum Server. Bitte prüfe die Verbindung und versuche es erneut.';
    if (status === 403) return 'Dafür fehlt dir die Berechtigung.';
    if (status === 404)
        return 'Die Sammlung oder das Rezept wurde nicht gefunden. Bitte lade die Seite neu.';
    if (status === 419)
        return 'Deine Sitzung ist abgelaufen. Bitte lade die Seite neu und versuche es erneut.';
    if (status >= 500)
        return 'Auf dem Server ist ein Fehler aufgetreten. Bitte versuche es später erneut.';
    return 'Das Rezept konnte nicht gespeichert werden. Bitte versuche es erneut.';
};

/**
 * Nacheinander je Sammlung ein Inertia-POST (der Server erwartet eine
 * Sammlung pro Request und antwortet mit einem Redirect samt Flash-Meldung).
 * Beim ersten Fehler wird abgebrochen; bereits gespeicherte Sammlungen
 * werden genannt (kein stiller Teilerfolg).
 */
const addToCollection = () => {
    if (selectedCollectionIds.value.length === 0 || processing.value) return;

    errorMessage.value = null;
    savedNames.value = [];
    processing.value = true;
    const queue = [...selectedCollectionIds.value];

    const fail = (id: number, message: string) => {
        errorMessage.value = `„${nameOf(id)}“: ${message}`;
    };

    const postNext = () => {
        const id = queue.shift();
        if (id === undefined) {
            processing.value = false;
            close();
            return;
        }

        let failed = false;
        const stopInvalid = router.on('invalid', (event) => {
            event.preventDefault();
            failed = true;
            fail(id, describeStatus(event.detail.response.status));
        });
        const stopException = router.on('exception', (event) => {
            event.preventDefault();
            failed = true;
            fail(id, describeStatus());
        });

        router.post(
            route('collections.addRecipe', { collection: id }),
            { recipe_id: props.recipeId },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    savedNames.value = [...savedNames.value, nameOf(id)];
                    selectedCollectionIds.value =
                        selectedCollectionIds.value.filter((v) => v !== id);
                },
                onError: (errors) => {
                    failed = true;
                    fail(id, Object.values(errors)[0] ?? describeStatus(422));
                },
                onFinish: () => {
                    stopInvalid();
                    stopException();
                    if (failed) {
                        processing.value = false;
                        return;
                    }
                    postNext();
                },
            },
        );
    };

    postNext();
};
</script>

<template>
    <!-- F-30: außerhalb von #app, über Header und Sidebar -->
    <Teleport to="body">
        <div v-if="open" class="modal-backdrop" @click.self="close">
            <div
                ref="dialogEl"
                class="modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="add-to-collection-title"
                tabindex="-1"
            >
                <h2 id="add-to-collection-title" class="modal__title">
                    Zur Sammlung hinzufügen
                </h2>

                <!-- Gast: Hinweis mit Login-Link -->
                <template v-if="isGuest">
                    <p class="modal__subtitle">
                        Melde dich an, um Rezepte in Sammlungen zu speichern.
                    </p>
                    <div class="modal__actions">
                        <button
                            type="button"
                            class="secondary-pill-button"
                            @click="close"
                        >
                            Schließen
                        </button>
                        <a :href="route('login')" class="primary-pill-button">
                            Anmelden
                        </a>
                    </div>
                </template>

                <template v-else>
                    <p
                        v-if="collections.length"
                        id="add-to-collection-hint"
                        class="modal__subtitle"
                    >
                        Wähle eine oder mehrere deiner Sammlungen aus, in denen
                        dieses Rezept gespeichert werden soll.
                    </p>

                    <fieldset
                        v-if="collections.length"
                        class="modal__list"
                        aria-describedby="add-to-collection-hint"
                    >
                        <legend class="sr-only">Sammlungen</legend>
                        <label
                            v-for="collection in collections"
                            :key="collection.id"
                            class="modal__list-item"
                        >
                            <input
                                type="checkbox"
                                name="collection"
                                :value="collection.id"
                                v-model="selectedCollectionIds"
                                :disabled="processing"
                            />
                            <span>{{ collection.name }}</span>
                        </label>
                    </fieldset>

                    <div v-else class="modal__empty-state">
                        <p class="modal__empty">
                            Du hast noch keine Sammlungen angelegt.
                        </p>
                        <Link
                            :href="route('collections.create')"
                            class="modal__link"
                        >
                            Neue Sammlung anlegen
                        </Link>
                    </div>

                    <p
                        v-if="savedNames.length"
                        class="modal__success"
                        role="status"
                    >
                        Gespeichert in: {{ savedNames.join(', ') }}
                    </p>
                    <p v-if="errorMessage" class="modal__alert" role="alert">
                        {{ errorMessage }}
                    </p>

                    <div class="modal__actions">
                        <button
                            type="button"
                            class="secondary-pill-button"
                            :disabled="processing"
                            @click="close"
                        >
                            {{ savedNames.length ? 'Schließen' : 'Abbrechen' }}
                        </button>
                        <button
                            v-if="collections.length"
                            type="button"
                            class="primary-pill-button"
                            :disabled="
                                selectedCollectionIds.length === 0 || processing
                            "
                            @click="addToCollection"
                        >
                            {{
                                processing ? 'Wird gespeichert …' : 'Hinzufügen'
                            }}
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </Teleport>
</template>

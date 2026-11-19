<script setup lang="ts">
import Button from '@/components/ui/Button.vue';
import Dialog from '@/components/ui/Dialog.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck } from 'lucide-vue-next';
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

// F-30/F-40: Ebene, Scroll-Sperre, Escape, Fokus und Fokusfalle im Baustein `Dialog`
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
    <!-- Gate C: auf dem Baustein Dialog; Verhalten aus Slice 3 (F-39) unverändert -->
    <Dialog
        :open="open"
        title="Zur Sammlung hinzufügen"
        :description="
            isGuest
                ? 'Melde dich an, um Rezepte in Sammlungen zu speichern.'
                : collections.length
                  ? 'Wähle eine oder mehrere deiner Sammlungen aus, in denen dieses Rezept gespeichert werden soll.'
                  : undefined
        "
        @close="close"
    >
        <!-- Gast: Hinweis (Beschreibung) mit Login-Link -->
        <template v-if="!isGuest">
            <fieldset v-if="collections.length" class="min-w-0 border-0 p-0">
                <legend class="sr-only">Sammlungen</legend>
                <ul class="flex max-h-[50dvh] flex-col gap-2 overflow-y-auto">
                    <li v-for="collection in collections" :key="collection.id">
                        <label
                            class="flex min-h-12 cursor-pointer items-center gap-3 rounded-xl border border-line px-4 py-2 text-ink transition-colors hover:bg-canvas has-[:checked]:border-primary has-[:checked]:bg-tint has-[:disabled]:cursor-not-allowed"
                        >
                            <input
                                v-model="selectedCollectionIds"
                                type="checkbox"
                                name="collection"
                                class="size-5 shrink-0 accent-primary"
                                :value="collection.id"
                                :disabled="processing"
                            />
                            <span
                                class="text-wrap-anywhere min-w-0 flex-1 font-medium"
                                >{{ collection.name }}</span
                            >
                        </label>
                    </li>
                </ul>
            </fieldset>

            <div v-else class="flex flex-col items-start gap-2">
                <p class="text-ink-muted">
                    Du hast noch keine Sammlungen angelegt.
                </p>
                <Link
                    :href="route('collections.create')"
                    class="font-semibold text-primary underline underline-offset-4 hover:text-primary-hover"
                >
                    Neue Sammlung anlegen
                </Link>
            </div>

            <p
                v-if="savedNames.length"
                class="mt-4 flex items-start gap-2 rounded-lg bg-success-surface px-3 py-2 text-sm font-medium text-success"
                role="status"
            >
                <CircleCheck
                    class="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <span class="text-wrap-anywhere"
                    >Gespeichert in: {{ savedNames.join(', ') }}</span
                >
            </p>
            <p
                v-if="errorMessage"
                class="mt-4 flex items-start gap-2 rounded-lg bg-danger-surface px-3 py-2 text-sm font-medium text-danger"
                role="alert"
            >
                <CircleAlert
                    class="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <span class="text-wrap-anywhere">{{ errorMessage }}</span>
            </p>
        </template>

        <template #footer>
            <template v-if="isGuest">
                <Button variant="ghost" @click="close">Schließen</Button>
                <Button :href="route('login')">Anmelden</Button>
            </template>
            <template v-else>
                <Button variant="ghost" :disabled="processing" @click="close">
                    {{ savedNames.length ? 'Schließen' : 'Abbrechen' }}
                </Button>
                <Button
                    v-if="collections.length"
                    :disabled="selectedCollectionIds.length === 0"
                    :loading="processing"
                    @click="addToCollection"
                >
                    {{ processing ? 'Wird gespeichert …' : 'Hinzufügen' }}
                </Button>
            </template>
        </template>
    </Dialog>
</template>

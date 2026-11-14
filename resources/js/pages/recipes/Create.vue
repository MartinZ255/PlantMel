<template>
    <AppLayout page-title="Rezept erstellen">
        <section class="content-section">
            <div class="section-header">
                <h1 class="section-header__title">Neues Rezept</h1>
                <div class="section-header__meta">
                    Entwurf · noch nicht gespeichert
                </div>
            </div>

            <!-- TikTok-Import -->
            <div class="tiktok-import">
                <label class="tiktok-import__label" for="tiktok-url">
                    Rezept aus TikTok importieren
                    <span class="inline-badge">optional</span>
                </label>

                <div class="tiktok-import__row">
                    <input
                        id="tiktok-url"
                        v-model="importUrl"
                        type="url"
                        class="form-field__input tiktok-import__input"
                        placeholder="https://www.tiktok.com/@…/video/…"
                        :disabled="importRunning"
                        :aria-invalid="importError ? 'true' : undefined"
                        :aria-describedby="
                            importError ? 'tiktok-import-error' : undefined
                        "
                        @keydown.enter.prevent="startImport"
                    />
                    <button
                        type="button"
                        class="primary-pill-button tiktok-import__button"
                        :disabled="importRunning || !importUrl.trim()"
                        @click="startImport"
                    >
                        {{ importRunning ? 'Import läuft …' : 'Importieren' }}
                    </button>
                    <button
                        v-if="importRunning"
                        type="button"
                        class="secondary-pill-button tiktok-import__button"
                        @click="cancelImport"
                    >
                        Abbrechen
                    </button>
                </div>

                <!-- F-24: Status als Live-Region (immer vorhanden), Fehler als Alert -->
                <p class="tiktok-import__status" role="status">
                    <template v-if="importRunning">
                        {{ importStatusText }} – das kann bis zu zwei Minuten
                        dauern.
                    </template>
                    <template v-else-if="importNotice">
                        {{ importNotice }}
                    </template>
                    <span
                        v-else-if="importedRecipe"
                        class="tiktok-import__status--success"
                    >
                        Import übernommen – bitte unten prüfen und speichern.
                    </span>
                </p>
                <p
                    v-if="importError"
                    id="tiktok-import-error"
                    class="form-field__error"
                    role="alert"
                >
                    {{ importError }}
                </p>

                <!-- Rückfrage, bevor ein bereits ausgefülltes Formular überschrieben wird -->
                <div
                    v-if="pendingImportId !== null"
                    class="tiktok-import__confirm"
                    role="group"
                    aria-labelledby="tiktok-confirm-text"
                >
                    <p
                        id="tiktok-confirm-text"
                        class="tiktok-import__confirm-text"
                    >
                        Der Import ist fertig. Soll er deine bisherigen Eingaben
                        im Formular ersetzen?
                    </p>
                    <div class="tiktok-import__confirm-actions">
                        <button
                            ref="keepButton"
                            type="button"
                            class="secondary-pill-button"
                            @click="keepOwnInput"
                        >
                            Eingaben behalten
                        </button>
                        <button
                            type="button"
                            class="primary-pill-button"
                            @click="applyImport(pendingImportId)"
                        >
                            Import übernehmen
                        </button>
                    </div>
                </div>
            </div>

            <RecipeForm
                ref="recipeForm"
                mode="create"
                :rating-dimensions="ratingDimensions"
                :categories="categories"
                :ingredients="ingredients"
                :recipe="importedRecipe ?? null"
                :submit-route="route('recipes.store')"
                submit-method="post"
            />
        </section>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import RecipeForm from '@/components/recipes/RecipeForm.vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { nextTick, onBeforeUnmount, ref } from 'vue';
import { route } from 'ziggy-js';

interface ImportedRecipe {
    name?: string;
    description?: string;
    duration_minutes?: number | null;
    servings?: number | null;
    // F-15: `source` wird nicht mehr übernommen; ältere Import-Ergebnisse
    // dürfen das Feld noch enthalten (RecipeForm ignoriert es)
    source?: string | null;
    tags?: string[];
    ingredients?: { amount: string; name: string }[];
    steps?: string[];
    notes?: string;
}

defineProps<{
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
    importedRecipe?: ImportedRecipe | null;
}>();

/**
 * F-24 (Client-Teil):
 * - Abfrage alle 3 s, jeweils erst nach Abschluss der vorherigen (keine Überlappung)
 * - Zeitlimit 16 Minuten (knapp über der Server-Schwelle von 15 Minuten)
 * - einzelne Netzwerkfehler werden toleriert, erst 3 in Folge beenden die Abfrage
 * - „Abbrechen“ stoppt die Abfrage; Aufräumen beim Verlassen der Seite
 * Zeitmessung über Timer statt Date.now(), damit sie mit gefälschter Uhr testbar ist.
 */
const POLL_INTERVAL_MS = 3000;
const MAX_DURATION_MS = 16 * 60 * 1000;
const MAX_CONSECUTIVE_ERRORS = 3;

const importUrl = ref('');
const importRunning = ref(false);
const importError = ref<string | null>(null);
const importNotice = ref<string | null>(null);
const importStatusText = ref('Import wird gestartet');
const pendingImportId = ref<number | null>(null);
const recipeForm = ref<{ hasUserInput: () => boolean } | null>(null);
const keepButton = ref<HTMLButtonElement | null>(null);

let pollTimer: ReturnType<typeof setTimeout> | null = null;
let deadlineTimer: ReturnType<typeof setTimeout> | null = null;
let consecutiveErrors = 0;
// Jede Sitzung bekommt eine Nummer; Antworten alter Sitzungen werden ignoriert
let session = 0;

const statusLabels: Record<string, string> = {
    pending: 'Import wird gestartet',
    downloading: 'Video wird geladen',
    analyzing: 'Rezept wird erkannt',
};

const stopPolling = () => {
    session++;
    if (pollTimer) {
        clearTimeout(pollTimer);
        pollTimer = null;
    }
    if (deadlineTimer) {
        clearTimeout(deadlineTimer);
        deadlineTimer = null;
    }
    importRunning.value = false;
};

const failImport = (message: string) => {
    stopPolling();
    importError.value = message;
};

const applyImport = (importId: number) => {
    pendingImportId.value = null;
    // Seite mit Vorbelegung neu laden – das Formular initialisiert sich aus
    // dem Import-Ergebnis
    router.visit(route('recipes.create', { import: importId }));
};

const keepOwnInput = () => {
    pendingImportId.value = null;
    importNotice.value =
        'Import nicht übernommen – deine Eingaben bleiben erhalten.';
};

const onImportDone = async (importId: number) => {
    stopPolling();
    if (recipeForm.value?.hasUserInput()) {
        pendingImportId.value = importId;
        await nextTick();
        keepButton.value?.focus();
        return;
    }
    applyImport(importId);
};

const poll = async (importId: number, current: number) => {
    try {
        const { data: status } = await axios.get(
            route('recipeImports.show', importId),
        );
        if (current !== session) return;
        consecutiveErrors = 0;

        if (status.status === 'done') {
            await onImportDone(importId);
            return;
        }
        if (status.status === 'failed') {
            failImport(
                status.error ??
                    'Import fehlgeschlagen. Bitte erneut versuchen.',
            );
            return;
        }
        importStatusText.value = statusLabels[status.status] ?? 'Import läuft';
    } catch {
        if (current !== session) return;
        consecutiveErrors++;
        if (consecutiveErrors >= MAX_CONSECUTIVE_ERRORS) {
            failImport(
                'Die Verbindung zum Import-Status ist mehrfach fehlgeschlagen. Bitte prüfe die Verbindung und versuche es erneut.',
            );
            return;
        }
    }
    schedulePoll(importId, current);
};

const schedulePoll = (importId: number, current: number) => {
    if (current !== session) return;
    pollTimer = setTimeout(() => poll(importId, current), POLL_INTERVAL_MS);
};

const startImport = async () => {
    const url = importUrl.value.trim();
    if (!url || importRunning.value) return;

    stopPolling();
    const current = session;
    importError.value = null;
    importNotice.value = null;
    pendingImportId.value = null;
    consecutiveErrors = 0;
    importRunning.value = true;
    importStatusText.value = statusLabels.pending;

    try {
        const { data } = await axios.post(route('recipeImports.store'), {
            url,
        });
        if (current !== session) return;
        const importId: number = data.id;

        deadlineTimer = setTimeout(() => {
            if (current !== session) return;
            failImport(
                'Der Import dauert ungewöhnlich lange und wurde abgebrochen. Bitte versuche es später erneut oder fülle das Formular selbst aus.',
            );
        }, MAX_DURATION_MS);
        schedulePoll(importId, current);
    } catch (error) {
        if (current !== session) return;
        // 409 (läuft bereits), 422 (ungültiger Link), 429 (Limit): Meldung des Servers
        const data = axios.isAxiosError(error)
            ? (error.response?.data as
                  | { message?: string; errors?: Record<string, string[]> }
                  | undefined)
            : undefined;
        failImport(
            data?.errors?.url?.[0] ??
                data?.message ??
                (axios.isAxiosError(error) && !error.response
                    ? 'Keine Verbindung zum Server. Bitte prüfe die Verbindung und versuche es erneut.'
                    : 'Import konnte nicht gestartet werden.'),
        );
    }
};

const cancelImport = () => {
    stopPolling();
    importNotice.value =
        'Import abgebrochen. Du kannst das Formular selbst ausfüllen.';
};

onBeforeUnmount(stopPolling);
</script>

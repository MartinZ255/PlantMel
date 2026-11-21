<template>
    <div
        data-tiktok-import
        class="relative min-w-0 overflow-hidden rounded-[2.25rem_1.75rem_2.5rem_2rem] bg-surface p-5 text-ink shadow-float sm:p-7"
    >
        <LeafOrnament
            variant="eucalyptus"
            class="pointer-events-none absolute -top-6 -right-4 h-32 rotate-[30deg] text-tint"
        />
        <div class="relative flex items-start gap-3.5">
            <span
                class="blob-mask inline-flex size-12 shrink-0 items-center justify-center bg-berry text-white shadow-berry blob-4"
                aria-hidden="true"
            >
                <Clapperboard class="size-6" />
            </span>
            <div class="min-w-0">
                <label
                    class="font-display text-2xl leading-tight font-semibold text-ink"
                    for="tiktok-url"
                >
                    Rezept aus TikTok importieren
                    <Badge tone="accent" class="ml-1 align-middle"
                        >optional</Badge
                    >
                </label>
                <p id="tiktok-url-hint" class="mt-1.5 text-sm text-ink-muted">
                    Link einfügen – Zutaten und Schritte werden aus Video, Ton
                    und Beschreibung erkannt.
                </p>
                <!-- E18: z. B. „ohne eigenen Schlüssel deaktiviert“ -->
                <slot name="notice" />
            </div>
        </div>

        <div class="relative mt-5 flex flex-col gap-2.5 sm:flex-row">
            <input
                id="tiktok-url"
                v-model="importUrl"
                type="url"
                inputmode="url"
                class="block min-h-12 w-full min-w-0 flex-1 rounded-pebble border-[1.5px] border-line-strong bg-canvas px-4 py-2 text-base text-ink placeholder:text-ink-muted hover:border-primary focus-visible:border-primary focus-visible:bg-surface focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-focus disabled:cursor-not-allowed aria-[invalid=true]:border-danger"
                placeholder="https://www.tiktok.com/@…/video/…"
                :disabled="importRunning || disabled"
                :aria-invalid="importError ? 'true' : undefined"
                :aria-describedby="
                    importError
                        ? 'tiktok-url-hint tiktok-import-error'
                        : 'tiktok-url-hint'
                "
                @keydown.enter.prevent="startImport"
            />
            <Button
                class="min-h-12 shrink-0"
                :disabled="!importUrl.trim() || importRunning || disabled"
                :loading="importStarting"
                @click="startImport"
            >
                <Download
                    v-if="!importRunning"
                    class="size-5"
                    aria-hidden="true"
                />
                {{
                    importStarting
                        ? 'Wird gestartet …'
                        : importRunning
                          ? 'Import läuft …'
                          : 'Importieren'
                }}
            </Button>
        </div>

        <!-- F-24 / Slice 4: Fortschritt, Live-Region (immer vorhanden) und Fehler-Alert -->
        <ImportProgress
            ref="progress"
            class="relative"
            :phase="importPhase"
            :video-stage="videoStage"
            :failed-step="failedStep"
            :elapsed-seconds="elapsedSeconds"
            :waiting-for-worker="waitingForWorker"
            :can-retry="canRetry"
            :error="importError"
            error-id="tiktok-import-error"
            :notice="
                importNotice ??
                (imported
                    ? 'Import übernommen – bitte unten prüfen und speichern.'
                    : null)
            "
            :notice-tone="!importNotice && imported ? 'success' : 'neutral'"
            @cancel="cancelImport"
            @retry="retryImport"
        />

        <!-- Rückfrage, bevor ein bereits ausgefülltes Formular überschrieben wird -->
        <div
            v-if="pendingImportId !== null"
            class="relative mt-5 rounded-[1.5rem_1.2rem_1.6rem_1.3rem] bg-tint p-4 sm:p-5"
            role="group"
            aria-labelledby="tiktok-confirm-text"
        >
            <p id="tiktok-confirm-text" class="font-semibold text-ink">
                Der Import ist fertig. Soll er deine bisherigen Eingaben im
                Formular ersetzen?
            </p>
            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                <Button
                    ref="keepButton"
                    variant="secondary"
                    @click="keepOwnInput"
                >
                    Eingaben behalten
                </Button>
                <Button @click="applyImport(pendingImportId)">
                    Import übernehmen
                </Button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import ImportProgress, {
    type ImportPhase,
} from '@/components/recipes/ImportProgress.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { Clapperboard, Download } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import { route } from 'ziggy-js';

/**
 * TikTok-Import als Karte (aus `pages/recipes/Create.vue` herausgelöst, E18).
 * Markup und Ablauf unverändert; die Routen kommen als Namen herein, damit
 * Host-Formular (`recipeImports.*`, `recipes.create`) und Community-Formular
 * (`communityImports.*`, `community.create`) dieselbe Karte nutzen.
 */
const props = withDefaults(
    defineProps<{
        storeRoute: string;
        statusRoute: string;
        createRoute: string;
        /** Fragt das Formular, ob schon etwas eingegeben wurde (Rückfrage vor dem Überschreiben). */
        hasUserInput: () => boolean;
        /** Import-Ergebnis wurde ins Formular übernommen (Erfolgshinweis). */
        imported?: boolean;
        /** E18: Import nicht verfügbar (z. B. ohne eigenen Gemini-Schlüssel). */
        disabled?: boolean;
    }>(),
    { imported: false, disabled: false },
);

/**
 * F-24 (Client-Teil):
 * - Abfrage alle 3 s, jeweils erst nach Abschluss der vorherigen (keine Überlappung)
 * - Zeitlimit 16 Minuten (knapp über der Server-Schwelle von 15 Minuten)
 * - einzelne Netzwerkfehler werden toleriert, erst 3 in Folge beenden die Abfrage
 * - „Abbrechen“ stoppt die Abfrage; Aufräumen beim Verlassen der Seite
 * Zeitmessung über Timer statt Date.now(), damit sie mit gefälschter Uhr testbar ist.
 *
 * Slice 4 – Fortschritt (ImportProgress): Startanfrage → `starting`, angenommen →
 * `pending`, danach `downloading` → `analyzing` → `done` bzw. `failed`.
 * Bleibt `pending` länger als 30 s (und meldet der Server nicht `started: true`),
 * erscheint der Hinweis auf den Hintergrunddienst.
 */
const POLL_INTERVAL_MS = 3000;
const MAX_DURATION_MS = 16 * 60 * 1000;
const MAX_CONSECUTIVE_ERRORS = 3;
/** Ab hier gilt ein `pending` als „wartet auf den Hintergrunddienst“. */
const WORKER_HINT_AFTER_MS = 30_000;
/** So lange bleibt „Fertig“ sichtbar, bevor das Formular befüllt wird. */
const DONE_DISPLAY_MS = 900;

/**
 * Antwort von `recipeImports.show`. `created_at`, `updated_at` und `started`
 * sind optional (ältere Server liefern sie nicht). `started` steuert den
 * Worker-Hinweis; die Zeitstempel werden bewusst nicht für die Uhr genutzt,
 * weil Server- und Browseruhr voneinander abweichen können.
 */
interface ImportStatusResponse {
    id: number;
    status: string;
    source?: string | null;
    error?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
    started?: boolean;
}

const importUrl = ref('');
const importRunning = ref(false);
const importStarting = ref(false);
const importError = ref<string | null>(null);
const importNotice = ref<string | null>(null);
const importPhase = ref<ImportPhase>('idle');
const failedStep = ref<number | null>(null);
const canRetry = ref(false);
const serverStarted = ref<boolean | null>(null);
/**
 * Video-Weg: Reicht die Beschreibung nicht, springt der Server von
 * `analyzing` zurück auf `downloading` (Video) und danach wieder auf
 * `analyzing`. Der Stepper bleibt dann bei „Rezept wird erkannt“ (Haken
 * bleiben), nur der Hinweistext folgt diesem Unterstatus.
 */
const videoStage = ref<'downloading' | 'analyzing' | null>(null);
const pendingImportId = ref<number | null>(null);
// Baustein `Button`: Fokus über das gerenderte Element
const keepButton = ref<{ $el: HTMLElement } | null>(null);
const progress = ref<{
    containsFocus: () => boolean;
    focusCancel: () => void;
    focusRetry: () => void;
} | null>(null);

// Zeitmessung über performance.now(): unabhängig von der Server-Uhr (kein
// Versatz) und mit Playwright-Clock steuerbar
const startedAtMs = ref(0);
const acceptedAtMs = ref<number | null>(null);
const nowMs = ref(0);

let pollTimer: ReturnType<typeof setTimeout> | null = null;
let deadlineTimer: ReturnType<typeof setTimeout> | null = null;
let doneTimer: ReturnType<typeof setTimeout> | null = null;
let clockTimer: ReturnType<typeof setInterval> | null = null;
let consecutiveErrors = 0;
let lastUrl = '';
// Jede Sitzung bekommt eine Nummer; Antworten alter Sitzungen werden ignoriert
let session = 0;

const RUNNING_PHASES: ImportPhase[] = ['pending', 'downloading', 'analyzing'];

const stepOfPhase = (phase: ImportPhase): number =>
    phase === 'downloading' ? 1 : phase === 'analyzing' ? 2 : 0;

const elapsedSeconds = computed(() =>
    Math.max(0, (nowMs.value - startedAtMs.value) / 1000),
);

const waitingForWorker = computed(
    () =>
        importPhase.value === 'pending' &&
        serverStarted.value !== true &&
        acceptedAtMs.value !== null &&
        nowMs.value - acceptedAtMs.value >= WORKER_HINT_AFTER_MS,
);

const tickClock = () => {
    nowMs.value = performance.now();
};

const stopClock = () => {
    if (clockTimer) {
        clearInterval(clockTimer);
        clockTimer = null;
    }
    tickClock();
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
    if (doneTimer) {
        clearTimeout(doneTimer);
        doneTimer = null;
    }
    stopClock();
    importRunning.value = false;
    importStarting.value = false;
};

const failImport = async (
    message: string,
    options: { showProgress?: boolean } = {},
) => {
    // Fokus lag im Fortschritt (z. B. auf „Abbrechen“, das gleich verschwindet)
    // -> auf „Erneut versuchen“ setzen, damit er nicht verloren geht
    const hadFocus = progress.value?.containsFocus() ?? false;
    const step = stepOfPhase(importPhase.value);
    stopPolling();
    importError.value = message;
    if (options.showProgress === false) {
        importPhase.value = 'idle';
        return;
    }
    failedStep.value = step;
    canRetry.value = true;
    importPhase.value = 'failed';
    if (hadFocus) {
        await nextTick();
        progress.value?.focusRetry();
    }
};

const applyImport = (importId: number) => {
    pendingImportId.value = null;
    // Seite mit Vorbelegung neu laden – das Formular initialisiert sich aus
    // dem Import-Ergebnis
    router.visit(route(props.createRoute, { import: importId }));
};

const keepOwnInput = () => {
    pendingImportId.value = null;
    importPhase.value = 'idle';
    importNotice.value =
        'Import nicht übernommen – deine Eingaben bleiben erhalten.';
};

const onImportDone = (importId: number) => {
    stopPolling();
    importPhase.value = 'done';
    const current = session;
    // „Fertig“ kurz zeigen, dann wie bisher übernehmen bzw. nachfragen
    doneTimer = setTimeout(async () => {
        doneTimer = null;
        if (current !== session) return;
        if (props.hasUserInput()) {
            pendingImportId.value = importId;
            await nextTick();
            keepButton.value?.$el?.focus();
            return;
        }
        applyImport(importId);
    }, DONE_DISPLAY_MS);
};

const poll = async (importId: number, current: number) => {
    try {
        const { data: status } = await axios.get<ImportStatusResponse>(
            route(props.statusRoute, importId),
        );
        if (current !== session) return;
        consecutiveErrors = 0;

        if (typeof status.started === 'boolean') {
            serverStarted.value = status.started;
        }
        if (status.status === 'done') {
            onImportDone(importId);
            return;
        }
        if (status.status === 'failed') {
            failImport(
                status.error ??
                    'Import fehlgeschlagen. Bitte erneut versuchen.',
            );
            return;
        }
        if (RUNNING_PHASES.includes(status.status as ImportPhase)) {
            const next = status.status as ImportPhase;
            if (importPhase.value === 'analyzing' && next === 'downloading') {
                // Rücksprung = Video-Weg, kein Fehler und kein Zurücksetzen
                videoStage.value = 'downloading';
            } else if (videoStage.value !== null && next === 'analyzing') {
                videoStage.value = 'analyzing';
            } else if (stepOfPhase(next) >= stepOfPhase(importPhase.value)) {
                // sonst nur vorwärts anzeigen
                importPhase.value = next;
            }
        }
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
    lastUrl = url;
    importError.value = null;
    importNotice.value = null;
    pendingImportId.value = null;
    failedStep.value = null;
    canRetry.value = false;
    serverStarted.value = null;
    videoStage.value = null;
    acceptedAtMs.value = null;
    consecutiveErrors = 0;
    importRunning.value = true;
    importStarting.value = true;
    importPhase.value = 'starting';
    startedAtMs.value = performance.now();
    tickClock();
    clockTimer = setInterval(tickClock, 1000);

    try {
        const { data } = await axios.post(route(props.storeRoute), {
            url,
        });
        if (current !== session) return;
        const importId: number = data.id;
        importStarting.value = false;
        importPhase.value = 'pending';
        acceptedAtMs.value = performance.now();

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
        const validationError = data?.errors?.url?.[0];
        failImport(
            validationError ??
                data?.message ??
                (axios.isAxiosError(error) && !error.response
                    ? 'Keine Verbindung zum Server. Bitte prüfe die Verbindung und versuche es erneut.'
                    : 'Import konnte nicht gestartet werden.'),
            // Ungültiger Link: Fehler am Feld, kein Fortschritt/„Erneut versuchen“
            { showProgress: validationError === undefined },
        );
    }
};

/** Neuer Import mit demselben Link; Limit-/Parallel-Regeln prüft der Server. */
const retryImport = async () => {
    if (!lastUrl || importRunning.value) return;
    importUrl.value = lastUrl;
    const run = startImport();
    await nextTick();
    progress.value?.focusCancel();
    await run;
};

const cancelImport = () => {
    stopPolling();
    importPhase.value = 'idle';
    importNotice.value =
        'Import abgebrochen. Du kannst das Formular selbst ausfüllen.';
};

onBeforeUnmount(stopPolling);
</script>

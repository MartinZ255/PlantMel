<template>
    <!--
        Fortschrittsanzeige für den TikTok-Import (Slice 4).
        Die Wurzel bleibt immer gerendert, damit die Live-Region (role="status")
        dauerhaft im DOM steht. Ohne laufenden Import ist sie per
        `display: contents` unsichtbar für das Layout.
    -->
    <div
        ref="root"
        class="import-progress"
        :class="{
            'import-progress--active': visible,
            'import-progress--failed': phase === 'failed',
            'import-progress--done': phase === 'done',
        }"
    >
        <template v-if="visible">
            <div class="import-progress__header">
                <span id="import-progress-title" class="import-progress__title">
                    Import-Fortschritt
                </span>
                <!-- Uhr bewusst außerhalb der Live-Region (keine Ansage je Sekunde) -->
                <span class="import-progress__time">
                    {{ running ? 'seit' : 'Dauer' }}
                    <time :datetime="`PT${wholeSeconds}S`">{{
                        formattedElapsed
                    }}</time>
                </span>
            </div>

            <ol
                class="import-progress__steps"
                aria-labelledby="import-progress-title"
            >
                <li
                    v-for="(step, index) in steps"
                    :key="step"
                    class="import-progress__step"
                    :class="`import-progress__step--${stepState(index)}`"
                    :aria-current="index === currentStep ? 'step' : undefined"
                >
                    <span class="import-progress__marker" aria-hidden="true">
                        <svg
                            v-if="stepState(index) === 'done'"
                            viewBox="0 0 16 16"
                            class="import-progress__icon"
                        >
                            <path d="M3.5 8.5l3 3 6-7" />
                        </svg>
                        <svg
                            v-else-if="stepState(index) === 'failed'"
                            viewBox="0 0 16 16"
                            class="import-progress__icon"
                        >
                            <path d="M4.5 4.5l7 7M11.5 4.5l-7 7" />
                        </svg>
                        <template v-else>{{ index + 1 }}</template>
                    </span>
                    <span class="import-progress__label">{{ step }}</span>
                    <span class="sr-only">
                        ({{ stateLabels[stepState(index)] }})
                    </span>
                </li>
            </ol>

            <!-- Unbestimmter Balken: keine Prozentwerte, nur „läuft“ -->
            <div v-if="running" class="import-progress__bar" aria-hidden="true">
                <span class="import-progress__bar-fill"></span>
            </div>
        </template>

        <!-- EINE Live-Region für Schrittwechsel und Hinweise -->
        <div
            class="tiktok-import__status import-progress__status"
            role="status"
        >
            <template v-if="visible && phase !== 'failed'">
                <span>{{ phaseMessage }}</span>
                <span v-if="waitingForWorker" class="import-progress__hint">
                    Der Import wartet auf die Verarbeitung. Läuft der
                    Hintergrunddienst (Queue-Worker)? Ohne ihn beginnt der
                    Import nicht.
                </span>
            </template>
            <template v-else-if="!visible && notice">
                <span
                    :class="{
                        'tiktok-import__status--success':
                            noticeTone === 'success',
                    }"
                    >{{ notice }}</span
                >
            </template>
        </div>

        <p
            v-if="error"
            :id="errorId"
            class="form-field__error import-progress__error"
            role="alert"
        >
            {{ error }}
        </p>

        <div
            v-if="visible && (running || (phase === 'failed' && canRetry))"
            class="import-progress__actions"
        >
            <button
                v-if="running"
                ref="cancelButton"
                type="button"
                class="secondary-pill-button"
                @click="emit('cancel')"
            >
                Abbrechen
            </button>
            <button
                v-else
                ref="retryButton"
                type="button"
                class="primary-pill-button"
                @click="emit('retry')"
            >
                Erneut versuchen
            </button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';

export type ImportPhase =
    | 'idle'
    | 'starting'
    | 'pending'
    | 'downloading'
    | 'analyzing'
    | 'done'
    | 'failed';

type StepState = 'done' | 'active' | 'failed' | 'todo';

const props = withDefaults(
    defineProps<{
        phase: ImportPhase;
        /** Index des Schritts, an dem der Import scheiterte (nur bei `failed`). */
        failedStep?: number | null;
        /** Unterstatus im Video-Weg (Server sprang von `analyzing` zurück). */
        videoStage?: 'downloading' | 'analyzing' | null;
        elapsedSeconds: number;
        waitingForWorker?: boolean;
        canRetry?: boolean;
        error?: string | null;
        errorId: string;
        /** Meldung ohne laufenden Import (z. B. „abgebrochen“). */
        notice?: string | null;
        noticeTone?: 'neutral' | 'success';
    }>(),
    {
        failedStep: null,
        videoStage: null,
        waitingForWorker: false,
        canRetry: false,
        error: null,
        notice: null,
        noticeTone: 'neutral',
    },
);

const emit = defineEmits<{
    cancel: [];
    retry: [];
}>();

const steps = [
    'Gestartet',
    'Video wird geladen',
    'Rezept wird erkannt',
    'Fertig',
] as const;

const stateLabels: Record<StepState, string> = {
    done: 'erledigt',
    active: 'läuft',
    failed: 'fehlgeschlagen',
    todo: 'ausstehend',
};

/** Zuordnung Status → Schritt (0-basiert). */
const phaseStep: Record<Exclude<ImportPhase, 'idle' | 'failed'>, number> = {
    starting: 0,
    pending: 0,
    downloading: 1,
    analyzing: 2,
    done: 3,
};

const phaseMessages: Record<Exclude<ImportPhase, 'idle' | 'failed'>, string> = {
    starting: 'Import wird gestartet – die Anfrage wird gesendet.',
    pending:
        'Gestartet – die Anfrage ist angenommen und wartet auf die Verarbeitung.',
    downloading:
        'Video wird geladen – Video und Beschreibung werden von TikTok abgerufen.',
    analyzing:
        'Rezept wird erkannt – Zutaten und Schritte werden aus Video, Ton und Beschreibung ausgelesen. Das kann bei längeren Videos einige Minuten dauern.',
    done: 'Fertig – der Import ist abgeschlossen.',
};

const root = ref<HTMLElement | null>(null);
const cancelButton = ref<HTMLButtonElement | null>(null);
const retryButton = ref<HTMLButtonElement | null>(null);

const visible = computed(() => props.phase !== 'idle');
const running = computed(() =>
    ['starting', 'pending', 'downloading', 'analyzing'].includes(props.phase),
);

const currentStep = computed<number | null>(() => {
    if (props.phase === 'idle') return null;
    if (props.phase === 'failed') return props.failedStep ?? 0;
    return phaseStep[props.phase];
});

const stepState = (index: number): StepState => {
    const current = currentStep.value;
    if (current === null || index > current) return 'todo';
    if (index < current) return 'done';
    if (props.phase === 'failed') return 'failed';
    if (props.phase === 'done') return 'done';
    return 'active';
};

const videoMessages = {
    downloading:
        'Beschreibung reichte nicht – Video wird geladen, um das Rezept daraus zu erkennen. Das kann bei längeren Videos einige Minuten dauern.',
    analyzing:
        'Rezept wird aus dem Video erkannt – Bild und Ton werden ausgewertet. Das kann bei längeren Videos einige Minuten dauern.',
} as const;

const phaseMessage = computed(() => {
    if (props.phase === 'idle' || props.phase === 'failed') return '';
    if (props.phase === 'analyzing' && props.videoStage)
        return videoMessages[props.videoStage];
    return phaseMessages[props.phase];
});

const wholeSeconds = computed(() =>
    Math.max(0, Math.floor(props.elapsedSeconds)),
);

const formattedElapsed = computed(() => {
    const minutes = Math.floor(wholeSeconds.value / 60);
    const seconds = wholeSeconds.value % 60;
    return `${minutes}:${String(seconds).padStart(2, '0')}`;
});

defineExpose({
    containsFocus: () =>
        !!root.value && root.value.contains(document.activeElement),
    focusCancel: () => cancelButton.value?.focus(),
    focusRetry: () => retryButton.value?.focus(),
});
</script>

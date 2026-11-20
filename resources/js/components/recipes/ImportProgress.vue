<template>
    <!--
        Fortschrittsanzeige für den TikTok-Import (Slice 4, Gate C2 im Stil
        „Natur & organisch“). Die Wurzel bleibt immer gerendert, damit die
        Live-Region (role="status") dauerhaft im DOM steht. Ohne laufenden
        Import nimmt sie keinen Platz ein.
    -->
    <div
        ref="root"
        data-import-progress
        :data-phase="phase"
        class="min-w-0"
        :class="visible || notice || error ? 'mt-5' : ''"
    >
        <div
            v-if="visible"
            class="rounded-[1.6rem_1.25rem_1.75rem_1.4rem] p-4 ring-1 sm:p-5"
            :class="
                phase === 'failed'
                    ? 'bg-danger-surface ring-danger/30'
                    : phase === 'done'
                      ? 'bg-tint ring-primary/30'
                      : 'bg-canvas ring-line'
            "
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span
                    id="import-progress-title"
                    class="font-display text-xl font-semibold text-ink"
                >
                    Import-Fortschritt
                </span>
                <!-- Uhr bewusst außerhalb der Live-Region (keine Ansage je Sekunde) -->
                <span
                    data-import-time
                    class="inline-flex items-center gap-1.5 rounded-seed bg-surface px-3 py-1 text-sm font-semibold text-ink-muted tabular-nums ring-1 ring-line"
                >
                    <Clock class="size-4 text-moss" aria-hidden="true" />
                    {{ running ? 'seit' : 'Dauer' }}
                    <time class="text-ink" :datetime="`PT${wholeSeconds}S`">{{
                        formattedElapsed
                    }}</time>
                </span>
            </div>

            <ol
                class="mt-4 flex flex-col gap-3 sm:grid sm:grid-cols-4 sm:gap-2"
                aria-labelledby="import-progress-title"
            >
                <li
                    v-for="(step, index) in steps"
                    :key="step"
                    :data-state="stepState(index)"
                    class="relative flex min-w-0 items-center gap-3 sm:flex-col sm:gap-2 sm:text-center"
                    :aria-current="index === currentStep ? 'step' : undefined"
                >
                    <!-- Ranke zum nächsten Schritt (mobil senkrecht, ab sm waagerecht) -->
                    <span
                        v-if="index < steps.length - 1"
                        class="pointer-events-none absolute top-11 -bottom-3 left-5 border-l-2 sm:top-5 sm:right-[calc(-50%+1.75rem)] sm:bottom-auto sm:left-[calc(50%+1.75rem)] sm:border-t-2 sm:border-l-0"
                        :class="
                            stepState(index) === 'done'
                                ? 'border-solid border-primary'
                                : 'border-dashed border-line-strong'
                        "
                        aria-hidden="true"
                    ></span>
                    <span
                        data-progress-marker
                        class="relative blob-mask inline-flex size-10 shrink-0 items-center justify-center font-display text-lg font-semibold"
                        :class="[markerShapes[index], markerClass(index)]"
                        aria-hidden="true"
                    >
                        <Check
                            v-if="stepState(index) === 'done'"
                            class="size-5"
                            :stroke-width="3"
                        />
                        <X
                            v-else-if="stepState(index) === 'failed'"
                            class="size-5"
                            :stroke-width="3"
                        />
                        <template v-else>{{ index + 1 }}</template>
                    </span>
                    <span
                        class="min-w-0 text-sm font-semibold text-wrap-anywhere"
                        :class="labelClass(index)"
                        >{{ step }}</span
                    >
                    <span class="sr-only">
                        ({{ stateLabels[stepState(index)] }})
                    </span>
                </li>
            </ol>

            <!-- Unbestimmter Balken: keine Prozentwerte, nur „läuft“ -->
            <div
                v-if="running"
                data-progress-bar
                class="relative mt-4 h-2.5 overflow-hidden rounded-full bg-tint-strong"
                aria-hidden="true"
            >
                <span
                    data-progress-bar-fill
                    class="progress-fill absolute inset-y-0 left-0 w-2/5 rounded-[1rem_0.6rem_1rem_0.6rem] bg-gradient-to-r from-primary via-moss to-matcha"
                ></span>
            </div>
        </div>

        <!-- EINE Live-Region für Schrittwechsel und Hinweise -->
        <div
            class="text-base text-ink"
            :class="visible ? 'mt-3' : ''"
            role="status"
        >
            <template v-if="visible && phase !== 'failed'">
                <span class="block">{{ phaseMessage }}</span>
                <span
                    v-if="waitingForWorker"
                    data-import-hint
                    class="mt-3 flex items-start gap-2.5 rounded-[1.25rem_1rem_1.4rem_1.1rem] bg-warning-surface p-3.5 text-sm font-medium text-warning"
                >
                    <Hourglass
                        class="mt-0.5 size-5 shrink-0"
                        aria-hidden="true"
                    />
                    <span class="min-w-0">
                        Der Import wartet auf die Verarbeitung. Läuft der
                        Hintergrunddienst (Queue-Worker)? Ohne ihn beginnt der
                        Import nicht.
                    </span>
                </span>
            </template>
            <template v-else-if="!visible && notice">
                <span
                    class="flex items-start gap-2 font-medium"
                    :class="
                        noticeTone === 'success' ? 'text-success' : 'text-ink'
                    "
                >
                    <CircleCheck
                        v-if="noticeTone === 'success'"
                        class="mt-0.5 size-5 shrink-0"
                        aria-hidden="true"
                    />
                    <span class="min-w-0">{{ notice }}</span>
                </span>
            </template>
        </div>

        <p
            v-if="error"
            :id="errorId"
            data-field-error
            class="mt-3 text-sm font-semibold text-wrap-anywhere text-danger"
            role="alert"
        >
            {{ error }}
        </p>

        <div
            v-if="visible && (running || (phase === 'failed' && canRetry))"
            class="mt-4 flex flex-wrap gap-2"
        >
            <Button
                v-if="running"
                ref="cancelButton"
                variant="secondary"
                @click="emit('cancel')"
            >
                <X class="size-4" aria-hidden="true" />
                Abbrechen
            </Button>
            <Button v-else ref="retryButton" @click="emit('retry')">
                <RotateCcw class="size-4" aria-hidden="true" />
                Erneut versuchen
            </Button>
        </div>
    </div>
</template>

<script setup lang="ts">
import Button from '@/components/ui/Button.vue';
import {
    Check,
    CircleCheck,
    Clock,
    Hourglass,
    RotateCcw,
    X,
} from 'lucide-vue-next';
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
// Baustein `Button`: Fokus über das gerenderte Element
const cancelButton = ref<{ $el: HTMLElement } | null>(null);
const retryButton = ref<{ $el: HTMLElement } | null>(null);

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

/** Blob-Formen der Schritt-Marken (wie die Schritte im Rezept) */
const markerShapes = ['blob-1', 'blob-4', 'blob-2', 'blob-5'];

/** Fläche der Marke je Zustand; „läuft“ pulsiert (nicht bei reduzierter Bewegung) */
const markerClass = (index: number) =>
    ({
        done: 'bg-primary text-matcha shadow-card',
        active: 'bg-matcha text-forest shadow-card after:absolute after:inset-0 after:-z-10 after:rounded-[inherit] after:bg-matcha after:animate-ping motion-reduce:after:animate-none',
        failed: 'bg-danger text-white shadow-card',
        todo: 'bg-surface text-ink-muted ring-[1.5px] ring-line-strong',
    })[stepState(index)];

const labelClass = (index: number) =>
    ({
        done: 'text-ink',
        active: 'text-ink',
        failed: 'text-danger',
        todo: 'text-ink-muted',
    })[stepState(index)];

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
    focusCancel: () => cancelButton.value?.$el?.focus(),
    focusRetry: () => retryButton.value?.$el?.focus(),
});
</script>

<style scoped>
/* Unbestimmter Balken: ein weicher Streifen wandert hin und her */
.progress-fill {
    animation: import-progress-indeterminate 1.8s ease-in-out infinite;
}

@keyframes import-progress-indeterminate {
    0% {
        translate: -100% 0;
    }
    100% {
        translate: 250% 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .progress-fill {
        animation: none;
        left: 30%;
    }
}
</style>

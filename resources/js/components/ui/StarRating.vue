<script setup lang="ts">
import { Star } from 'lucide-vue-next';
import { computed, ref, useId } from 'vue';

/**
 * Gate C: Sternebewertung (F-41, F-43).
 *
 * Anzeige (Standard): Sterne sind Grafik (`aria-hidden`), daneben steht der
 * Wert als Text „4,5 von 5“ (bzw. kompakt „4,5“ plus „von 5“ nur für
 * Screenreader). Ohne Wert: „Noch nicht bewertet“ (wie auf der Detailseite). Bewertung hängt nie
 * nur an der Farbe.
 *
 * Eingabe (`input`): native Radiogroup – Pfeiltasten wählen den Wert,
 * Tab springt in/aus der Gruppe; „Keine Bewertung“ setzt zurück.
 * Noch nicht im Formular eingesetzt (Schritt 2).
 */
interface Props {
    value?: number | null;
    /** Anzahl der Bewertungen (nur Anzeige) */
    count?: number | null;
    mode?: 'display' | 'input';
    /** Anzeige: nur Zahl sichtbar, „von 5“ für Screenreader */
    compact?: boolean;
    size?: 'sm' | 'md';
    /** Eingabe: zugänglicher Name der Gruppe */
    label?: string;
    max?: number;
    /** Anzeige auf Waldgrün-Flächen: Sterne Matcha, Text hell */
    tone?: 'default' | 'on-forest';
}

const props = withDefaults(defineProps<Props>(), {
    value: null,
    count: null,
    mode: 'display',
    compact: false,
    size: 'md',
    label: 'Bewertung',
    max: 5,
    tone: 'default',
});

const model = defineModel<number | null>('modelValue', { default: null });

const uid = useId();
const groupName = `rating-${uid}`;

const format = (v: number) =>
    v.toLocaleString('de-DE', {
        minimumFractionDigits: Number.isInteger(v) ? 0 : 1,
        maximumFractionDigits: 1,
    });

const displayValue = computed(() => {
    const v = props.value;
    if (v == null || Number.isNaN(v) || v <= 0) return null;
    return Math.min(props.max, Math.max(0, v));
});

const fillPercent = computed(() =>
    displayValue.value == null ? 0 : (displayValue.value / props.max) * 100,
);

const countText = computed(() => {
    if (props.count == null) return '';
    return props.count === 1 ? '1 Bewertung' : `${props.count} Bewertungen`;
});

const starSize = computed(() => (props.size === 'sm' ? 'size-4' : 'size-5'));
const onForest = computed(() => props.tone === 'on-forest');

// --- Eingabe ---------------------------------------------------------------
const hover = ref<number | null>(null);
const shown = computed(() => hover.value ?? model.value ?? 0);
const stars = computed(() => Array.from({ length: props.max }, (_, i) => i + 1));
</script>

<template>
    <!-- Anzeige -->
    <span
        v-if="mode === 'display'"
        class="inline-flex flex-wrap items-center gap-x-2 gap-y-0.5"
    >
        <span class="relative inline-flex shrink-0" aria-hidden="true">
            <span
                class="inline-flex"
                :class="onForest ? 'text-on-forest-muted' : 'text-star-empty'"
            >
                <Star v-for="i in max" :key="i" :class="starSize" />
            </span>
            <span
                class="absolute inset-y-0 left-0 inline-flex overflow-hidden"
                :class="onForest ? 'text-matcha' : 'text-star'"
                :style="{ width: `${fillPercent}%` }"
            >
                <Star
                    v-for="i in max"
                    :key="i"
                    :class="starSize"
                    class="shrink-0 fill-current"
                />
            </span>
        </span>
        <span
            class="text-sm"
            :class="[
                compact ? 'font-semibold' : 'font-medium',
                onForest ? 'text-on-forest' : 'text-ink',
            ]"
        >
            <template v-if="displayValue != null">
                {{ format(displayValue) }}
                <span :class="compact && 'sr-only'">von {{ max }}</span>
                <span
                    v-if="countText"
                    class="font-normal"
                    :class="onForest ? 'text-on-forest-muted' : 'text-ink-muted'"
                >
                    ({{ countText }})</span
                >
            </template>
            <template v-else>Noch nicht bewertet</template>
        </span>
    </span>

    <!-- Eingabe -->
    <fieldset
        v-else
        class="inline-flex min-w-0 flex-col gap-1.5 border-0 p-0"
        @mouseleave="hover = null"
    >
        <legend class="mb-1 text-sm font-semibold text-ink">{{ label }}</legend>
        <div role="radiogroup" :aria-label="label" class="flex items-center gap-1">
            <label
                v-for="i in stars"
                :key="i"
                class="relative inline-flex size-11 cursor-pointer items-center justify-center rounded-md has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-1 has-[:focus-visible]:outline-focus"
                @mouseenter="hover = i"
            >
                <input
                    v-model="model"
                    type="radio"
                    class="sr-only"
                    :name="groupName"
                    :value="i"
                />
                <span class="sr-only">{{ i }} von {{ max }} Sternen</span>
                <Star
                    class="size-7"
                    :class="
                        i <= shown ? 'fill-current text-star' : 'text-star-empty'
                    "
                    aria-hidden="true"
                />
            </label>
        </div>
        <p class="text-sm text-ink-muted" aria-live="polite">
            {{
                model
                    ? `${model} von ${max} Sternen gewählt`
                    : 'Noch keine Bewertung gewählt'
            }}
        </p>
        <button
            v-if="model"
            type="button"
            class="self-start text-sm font-medium text-primary underline underline-offset-2 hover:text-primary-hover"
            @click="model = null"
        >
            Bewertung entfernen
        </button>
    </fieldset>
</template>

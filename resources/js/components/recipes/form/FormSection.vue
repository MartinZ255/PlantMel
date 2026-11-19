<script setup lang="ts">
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import { computed } from 'vue';

/**
 * Gate C2: Abschnitt des Rezeptformulars („Kapitel“ im Rezeptbuch).
 *
 * - große Fraunces-Überschrift (`h2`) mit Kapitelziffer in einer Blob-Form
 *   (die Ziffer ist rein grafisch, `aria-hidden`)
 * - `tone` wählt die Fläche: `paper` (Papierblatt mit Schatten), `plain`
 *   (direkt auf dem Naturgrund), `tint` (helles Grün), `berry` (Beere hell)
 * - `required`: sichtbarer Stern; die Pflicht selbst steht am Feld
 * - Slot `#hint` unter der Überschrift, Standard-Slot für den Inhalt
 */
const props = withDefaults(
    defineProps<{
        /** Anker-ID des Abschnitts (Sprungziel der Kapitelnavigation) */
        id: string;
        /** ID der Überschrift (aria-labelledby, Formular-Hooks) */
        titleId: string;
        title: string;
        number: number;
        tone?: 'paper' | 'plain' | 'tint' | 'berry';
        required?: boolean;
        /** Kennung für Tests und Kapitelnavigation */
        name: string;
    }>(),
    { tone: 'paper', required: false },
);

const shapes = ['blob-1', 'blob-4', 'blob-2', 'blob-5', 'blob-3', 'blob-6'];
const shape = computed(() => shapes[(props.number - 1) % shapes.length]);

const surfaces = {
    paper: 'bg-surface p-5 shadow-float sm:p-8 rounded-[2.25rem_1.75rem_2.5rem_2rem]',
    plain: 'px-0.5 py-2 sm:px-1',
    tint: 'bg-tint p-5 sm:p-8 rounded-[2.5rem_2rem_2.75rem_1.75rem]',
    berry: 'bg-berry-surface p-5 sm:p-8 rounded-[2rem_2.5rem_1.75rem_2.75rem]',
} as const;

const ornaments = {
    paper: 'text-tint',
    plain: 'text-tint-strong',
    tint: 'text-tint-strong',
    berry: 'text-surface',
} as const;
</script>

<template>
    <section
        :id="id"
        :aria-labelledby="titleId"
        :data-form-section="name"
        class="relative min-w-0 overflow-hidden"
        :class="surfaces[tone]"
    >
        <LeafOrnament
            :variant="number % 2 === 0 ? 'leaf' : 'eucalyptus'"
            class="pointer-events-none absolute -top-7 -right-5 h-32 rotate-[28deg] sm:h-36"
            :class="ornaments[tone]"
        />
        <div class="relative flex items-center gap-3.5 sm:gap-4">
            <span
                class="blob-mask inline-flex size-12 shrink-0 items-center justify-center font-display-wonk text-2xl font-semibold shadow-card sm:size-14 sm:text-3xl"
                :class="[
                    shape,
                    number % 2 === 1
                        ? 'bg-matcha text-forest'
                        : 'bg-forest text-matcha',
                ]"
                aria-hidden="true"
                >{{ number }}</span
            >
            <h2
                :id="titleId"
                class="min-w-0 font-display-wonk text-3xl font-semibold text-wrap-anywhere text-ink sm:text-4xl"
            >
                {{ title }}
                <span
                    v-if="required"
                    class="align-super text-xl text-danger"
                    aria-hidden="true"
                    >*</span
                >
            </h2>
        </div>
        <div
            v-if="$slots.hint"
            class="relative mt-3 max-w-2xl text-base text-ink-muted"
        >
            <slot name="hint" />
        </div>
        <div class="relative mt-6 min-w-0">
            <slot />
        </div>
    </section>
</template>

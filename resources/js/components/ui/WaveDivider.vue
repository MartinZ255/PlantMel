<script setup lang="ts">
/**
 * Gate C1b: welliger Abschnittsübergang (SVG, rein dekorativ).
 *
 * Die Welle ist mit `currentColor` gefüllt (Farbe des FOLGENDEN Abschnitts,
 * z. B. `text-forest`), der Hintergrund des Elements ist die Farbe des
 * VORIGEN Abschnitts (z. B. `bg-canvas`). `flip` dreht die Welle um (Unter-
 * kante eines Abschnitts). Feste Höhe: kein Layout-Springen.
 */
withDefaults(
    defineProps<{
        flip?: boolean;
        variant?: 1 | 2 | 3;
    }>(),
    { flip: false, variant: 1 },
);

const paths = {
    1: 'M0 70 C 180 18 360 6 560 40 C 760 74 920 112 1120 82 C 1270 60 1360 34 1440 30 L1440 120 L0 120 Z',
    2: 'M0 40 C 220 96 420 104 640 70 C 860 36 1000 8 1200 30 C 1310 42 1390 70 1440 84 L1440 120 L0 120 Z',
    3: 'M0 88 C 160 60 300 28 470 38 C 650 50 760 100 960 96 C 1150 92 1290 40 1440 52 L1440 120 L0 120 Z',
} as const;
</script>

<template>
    <div class="pointer-events-none relative -my-px leading-none" aria-hidden="true">
        <svg
            viewBox="0 0 1440 120"
            preserveAspectRatio="none"
            focusable="false"
            class="block h-12 w-full sm:h-16 lg:h-24"
            :class="flip && 'rotate-180'"
        >
            <path :d="paths[variant]" fill="currentColor" />
        </svg>
    </div>
</template>

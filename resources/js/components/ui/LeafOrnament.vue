<script setup lang="ts">
import { useId } from 'vue';

/**
 * Gate C1b: Blatt- und Kräuter-Ornamente als eigene, schlichte SVG-Pfade
 * (keine fremden Grafiken). Rein dekorativ: `aria-hidden`, nicht fokussierbar.
 * Farbe über `currentColor` (z. B. `text-moss`), Größe über Klassen.
 *
 * - `leaf`: einzelnes Blatt, Adern als Aussparung (Maske)
 * - `sprig`: Kräuterzweig mit wechselständigen Blättern
 * - `eucalyptus`: Zweig mit runden Blättern
 * - `sprout`: Keimling mit zwei Blättern (wie das Signet)
 */
withDefaults(
    defineProps<{
        variant?: 'leaf' | 'sprig' | 'eucalyptus' | 'sprout';
    }>(),
    { variant: 'leaf' },
);

const maskId = `leaf-veins-${useId()}`;

// Blattform für den Zweig: Ursprung am Stiel, zeigt nach rechts
const sprigLeaf = 'M0 0 C 8 -11 24 -13 38 -4 C 25 7 9 7 0 0 Z';
const sprigLeaves = [
    { x: 34, y: 138, r: -24 },
    { x: 37, y: 124, r: -150 },
    { x: 43, y: 104, r: -30 },
    { x: 48, y: 88, r: -152 },
    { x: 57, y: 70, r: -36 },
    { x: 64, y: 56, r: -158 },
    { x: 74, y: 40, r: -44 },
    { x: 82, y: 28, r: -160 },
];
const eucalyptusLeaves = [
    { cx: 34, cy: 136, rx: 15, ry: 11, r: -20 },
    { cx: 66, cy: 116, rx: 15, ry: 11, r: 18 },
    { cx: 33, cy: 94, rx: 14, ry: 10.5, r: -16 },
    { cx: 66, cy: 74, rx: 13, ry: 10, r: 16 },
    { cx: 37, cy: 54, rx: 12, ry: 9, r: -12 },
    { cx: 62, cy: 35, rx: 10.5, ry: 8, r: 12 },
    { cx: 50, cy: 14, rx: 8, ry: 6.5, r: 0 },
];
</script>

<template>
    <svg
        v-if="variant === 'leaf'"
        viewBox="0 0 100 120"
        fill="none"
        aria-hidden="true"
        focusable="false"
    >
        <defs>
            <mask :id="maskId" maskUnits="userSpaceOnUse">
                <rect width="100" height="120" fill="white" />
                <g
                    stroke="black"
                    stroke-width="2.4"
                    stroke-linecap="round"
                    fill="none"
                >
                    <path d="M50 18 C 51 50 51 82 50 112" />
                    <path d="M50 40 C 58 36 64 31 70 25" />
                    <path d="M50 40 C 42 36 36 31 30 25" />
                    <path d="M50 60 C 60 56 68 50 76 44" />
                    <path d="M50 60 C 40 56 32 50 24 44" />
                    <path d="M50 80 C 58 77 66 72 72 66" />
                    <path d="M50 80 C 42 77 34 72 28 66" />
                </g>
            </mask>
        </defs>
        <path
            :mask="`url(#${maskId})`"
            fill="currentColor"
            d="M50 6 C 80 26 92 60 74 92 C 66 106 56 114 50 116 C 44 114 34 106 26 92 C 8 60 20 26 50 6 Z"
        />
    </svg>

    <svg
        v-else-if="variant === 'sprig'"
        viewBox="0 0 120 160"
        fill="none"
        aria-hidden="true"
        focusable="false"
    >
        <path
            d="M30 156 C 40 120 52 80 92 10"
            stroke="currentColor"
            stroke-width="3"
            stroke-linecap="round"
        />
        <path
            v-for="(l, i) in sprigLeaves"
            :key="i"
            :d="sprigLeaf"
            fill="currentColor"
            :transform="`translate(${l.x} ${l.y}) rotate(${l.r})`"
        />
        <path
            :d="sprigLeaf"
            fill="currentColor"
            transform="translate(91 12) rotate(-62) scale(0.8)"
        />
    </svg>

    <svg
        v-else-if="variant === 'eucalyptus'"
        viewBox="0 0 100 160"
        fill="none"
        aria-hidden="true"
        focusable="false"
    >
        <path
            d="M50 158 C 45 122 56 82 50 8"
            stroke="currentColor"
            stroke-width="2.6"
            stroke-linecap="round"
        />
        <ellipse
            v-for="(l, i) in eucalyptusLeaves"
            :key="i"
            :cx="l.cx"
            :cy="l.cy"
            :rx="l.rx"
            :ry="l.ry"
            fill="currentColor"
            :transform="`rotate(${l.r} ${l.cx} ${l.cy})`"
        />
    </svg>

    <svg
        v-else
        viewBox="0 0 24 24"
        fill="none"
        aria-hidden="true"
        focusable="false"
    >
        <path
            d="M12 21 V 11.5"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
        />
        <path
            fill="currentColor"
            d="M11.6 13.6 C 6.8 13.8 4.2 11 4 6.6 C 8.6 6.4 11.4 9 11.6 13.6 Z"
        />
        <path
            fill="currentColor"
            d="M12.4 11.4 C 12.4 6.6 15.2 3.8 20 3.6 C 20.1 8.4 17.2 11.3 12.4 11.4 Z"
        />
    </svg>
</template>

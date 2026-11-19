<script setup lang="ts">
import { useReveal } from '@/composables/useReveal';
import { ref } from 'vue';

/**
 * Gate C1b: Hülle, die ihren Inhalt beim Scrollen sanft einblendet
 * (siehe `useReveal`). Bei reduzierter Bewegung sofort sichtbar.
 * `as` bestimmt das Element (z. B. `li` in Listen), `delay` staffelt
 * Gruppen (ms).
 */
const props = withDefaults(
    defineProps<{
        as?: string;
        delay?: number;
    }>(),
    { as: 'div', delay: 0 },
);

const el = ref<HTMLElement | null>(null);
useReveal(el, { delay: props.delay });
</script>

<template>
    <component :is="as" ref="el" class="reveal">
        <slot />
    </component>
</template>

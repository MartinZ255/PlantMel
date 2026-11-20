<script setup lang="ts">
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import type { Component } from 'vue';

/**
 * Gate C: Leerzustand (F-44) mit Überschrift, Erklärung und optionalen
 * Aktionen (`#actions`). Die Überschriftenebene passt sich der Seite an.
 * C1b: organische Komposition (Blob mit Symbol, Zweig) statt Rahmenkasten.
 */
withDefaults(
    defineProps<{
        title: string;
        description?: string;
        icon?: Component;
        headingLevel?: 2 | 3;
    }>(),
    { description: undefined, icon: undefined, headingLevel: 2 },
);
</script>

<template>
    <div
        class="relative flex flex-col items-center gap-3 overflow-hidden rounded-[2.5rem_2rem_2.75rem_2.25rem] bg-tint/70 px-6 py-14 text-center"
    >
        <LeafOrnament
            variant="eucalyptus"
            class="pointer-events-none absolute -bottom-6 -left-4 h-40 rotate-[-24deg] text-tint-strong"
        />
        <LeafOrnament
            variant="leaf"
            class="pointer-events-none absolute -top-4 right-6 h-24 rotate-[28deg] text-tint-strong"
        />
        <span
            v-if="icon"
            class="blob-mask blob-2 relative inline-flex size-20 items-center justify-center bg-matcha text-forest shadow-card"
            aria-hidden="true"
        >
            <component :is="icon" class="size-8" />
        </span>
        <component
            :is="headingLevel === 3 ? 'h3' : 'h2'"
            class="font-display text-wrap-anywhere relative text-2xl font-semibold text-ink"
        >
            {{ title }}
        </component>
        <p v-if="description" class="relative max-w-prose text-ink-muted">
            {{ description }}
        </p>
        <div
            v-if="$slots.actions"
            class="relative mt-2 flex flex-wrap justify-center gap-2"
        >
            <slot name="actions" />
        </div>
    </div>
</template>

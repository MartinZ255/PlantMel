<script setup lang="ts">
import type { Component } from 'vue';

/**
 * Gate C: Leerzustand (F-44) mit Überschrift, Erklärung und optionalen
 * Aktionen (`#actions`). Die Überschriftenebene passt sich der Seite an.
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
        class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-line-strong bg-surface px-6 py-12 text-center"
    >
        <span
            v-if="icon"
            class="inline-flex size-14 items-center justify-center rounded-full bg-tint text-primary"
            aria-hidden="true"
        >
            <component :is="icon" class="size-7" />
        </span>
        <component
            :is="headingLevel === 3 ? 'h3' : 'h2'"
            class="text-wrap-anywhere text-lg font-semibold text-ink"
        >
            {{ title }}
        </component>
        <p v-if="description" class="max-w-prose text-ink-muted">
            {{ description }}
        </p>
        <div v-if="$slots.actions" class="mt-2 flex flex-wrap justify-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>

<script setup lang="ts">
/**
 * Gate C: statische Kennzeichnung (Kategorie, Status). Nicht klickbar;
 * für Filter siehe `Chip`. Alle Töne erfüllen ≥ 4,5:1 (DESIGN-SYSTEM.md).
 */
export type BadgeTone =
    | 'neutral'
    | 'primary'
    | 'accent'
    | 'success'
    | 'warning'
    | 'danger';

withDefaults(defineProps<{ tone?: BadgeTone }>(), { tone: 'neutral' });

const tones: Record<BadgeTone, string> = {
    neutral: 'bg-canvas text-ink-muted border-line',
    primary: 'bg-tint text-primary border-transparent',
    accent: 'bg-accent-surface text-accent-strong border-transparent',
    success: 'bg-success-surface text-success border-transparent',
    warning: 'bg-warning-surface text-warning border-transparent',
    danger: 'bg-danger-surface text-danger border-transparent',
};
</script>

<template>
    <span
        class="text-wrap-anywhere inline-flex max-w-full items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium"
        :class="tones[tone]"
    >
        <slot />
    </span>
</template>

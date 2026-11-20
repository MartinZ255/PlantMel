<script setup lang="ts">
/**
 * Gate C: statische Kennzeichnung (Kategorie, Status). Nicht klickbar;
 * für Filter siehe `Chip`. C1b: „Samen“-Form mit kleinem Korn als Punkt. Alle Töne erfüllen ≥ 4,5:1 (DESIGN-SYSTEM.md).
 */
export type BadgeTone =
    | 'neutral'
    | 'primary'
    | 'accent'
    | 'success'
    | 'warning'
    | 'danger'
    | 'matcha';

withDefaults(defineProps<{ tone?: BadgeTone }>(), { tone: 'neutral' });

const tones: Record<BadgeTone, string> = {
    neutral: 'bg-surface text-ink-muted border-line',
    primary: 'bg-tint text-primary border-transparent',
    accent: 'bg-berry-surface text-berry border-transparent',
    success: 'bg-success-surface text-success border-transparent',
    warning: 'bg-warning-surface text-warning border-transparent',
    danger: 'bg-danger-surface text-danger border-transparent',
    // auf Waldgrün-Flächen
    matcha: 'bg-matcha text-forest border-transparent',
};
</script>

<template>
    <span
        class="rounded-seed text-wrap-anywhere inline-flex max-w-full items-center gap-1.5 border px-2.5 py-0.5 text-xs font-semibold tracking-wide"
        :class="tones[tone]"
    >
        <!-- kleines Samenkorn als Punkt -->
        <span
            class="size-1.5 shrink-0 rounded-[60%_40%_55%_45%] bg-current opacity-70"
            aria-hidden="true"
        ></span>
        <slot />
    </span>
</template>

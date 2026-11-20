<script setup lang="ts">
import LeafOrnament from '@/components/ui/LeafOrnament.vue';

/**
 * Gate C2: Umschalter in „Samen“-Form für die Kategorien im Formular.
 *
 * Optik wie der Baustein `Chip`, aber mit 44 px Trefferfläche (Touch).
 * `aria-pressed` trägt den Zustand; gedrückt zeigt zusätzlich einen
 * Keimling (nicht nur Farbe). Weitere Listener (z. B. `dblclick`) und
 * Attribute fallen auf den Knopf durch.
 */
defineProps<{ pressed: boolean }>();
</script>

<template>
    <button
        type="button"
        class="inline-flex min-h-11 max-w-full items-center gap-2 rounded-seed border-[1.5px] px-4 py-1.5 text-left text-base font-medium text-wrap-anywhere transition-[color,background-color,border-color,border-radius,translate] duration-200 hover:-translate-y-px focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
        :class="
            pressed
                ? 'border-primary bg-primary text-on-primary shadow-card hover:bg-primary-hover'
                : 'border-line-strong bg-surface text-ink hover:border-primary hover:bg-tint'
        "
        :aria-pressed="pressed ? 'true' : 'false'"
    >
        <LeafOrnament
            v-if="pressed"
            variant="sprout"
            class="size-4 shrink-0 text-matcha"
        />
        <span
            v-else
            class="size-2 shrink-0 rounded-[60%_40%_55%_45%] bg-moss"
            aria-hidden="true"
        ></span>
        <span class="min-w-0"><slot /></span>
    </button>
</template>

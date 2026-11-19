<script setup lang="ts">
import { X } from 'lucide-vue-next';

/**
 * Gate C: Filter-Chip.
 * - `pressed` gesetzt (true/false): Umschalter mit `aria-pressed`
 * - `removable`: aktiver Filter, Klick entfernt ihn; der zugängliche Name
 *   kommt über `removeLabel` (z. B. „Filter entfernen: Dessert“)
 */
interface Props {
    pressed?: boolean;
    removable?: boolean;
    removeLabel?: string;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    pressed: undefined,
    removable: false,
    removeLabel: undefined,
    disabled: false,
});

defineEmits<{ (e: 'click', event: MouseEvent): void }>();
</script>

<template>
    <button
        type="button"
        class="text-wrap-anywhere inline-flex min-h-9 max-w-full items-center gap-1.5 rounded-full border px-3.5 py-1 text-left text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus disabled:cursor-not-allowed disabled:opacity-60"
        :class="
            props.pressed || props.removable
                ? 'border-primary bg-primary text-on-primary hover:bg-primary-hover'
                : 'border-line-strong bg-surface text-ink hover:bg-tint'
        "
        :aria-pressed="
            props.pressed === undefined || props.removable
                ? undefined
                : props.pressed
                  ? 'true'
                  : 'false'
        "
        :aria-label="props.removable ? props.removeLabel : undefined"
        :disabled="disabled"
        @click="$emit('click', $event)"
    >
        <span class="min-w-0"><slot /></span>
        <X
            v-if="removable"
            class="size-4 shrink-0"
            aria-hidden="true"
        />
    </button>
</template>

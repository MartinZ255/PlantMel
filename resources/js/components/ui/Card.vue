<script setup lang="ts">
import { cn } from '@/lib/utils';
import { computed } from 'vue';

/**
 * Gate C: Fläche (C1b: Papier auf Naturgrund, organische Ecken, waldgrüner
 * Schatten statt Rahmen). Optionaler Bildbereich oben (`#media`),
 * Inhalt mit Innenabstand (`padded`), optional `#footer`.
 * `interactive`: Hover-Anhebung für Karten, die als Ganzes verlinken
 * (der Link selbst liegt im Inhalt; siehe RecipeDetailCard).
 */
interface Props {
    as?: 'article' | 'div' | 'section' | 'li' | 'aside';
    padded?: boolean;
    interactive?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    as: 'div',
    padded: true,
    interactive: false,
});

const classes = computed(() =>
    cn(
        'relative flex min-w-0 flex-col overflow-hidden rounded-[1.75rem_1.5rem_2rem_1.25rem] bg-surface shadow-card',
        props.interactive &&
            'transition-[box-shadow,translate] duration-300 focus-within:shadow-raised hover:-translate-y-1 hover:shadow-float',
    ),
);
</script>

<template>
    <component :is="as" :class="classes">
        <slot name="media" />
        <div v-if="$slots.default" :class="cn('min-w-0 flex-1', padded && 'p-4 sm:p-5')">
            <slot />
        </div>
        <div
            v-if="$slots.footer"
            class="border-t border-line/70 px-4 py-3 sm:px-5"
        >
            <slot name="footer" />
        </div>
    </component>
</template>

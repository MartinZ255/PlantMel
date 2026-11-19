<script setup lang="ts">
import { cn } from '@/lib/utils';
import { computed } from 'vue';

/**
 * Gate C: Fläche mit Rahmen. Optionaler Bildbereich oben (`#media`),
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
        'relative flex min-w-0 flex-col overflow-hidden rounded-xl border border-line bg-surface shadow-card',
        props.interactive &&
            'transition-shadow focus-within:shadow-raised hover:shadow-raised',
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
            class="border-t border-line px-4 py-3 sm:px-5"
        >
            <slot name="footer" />
        </div>
    </component>
</template>

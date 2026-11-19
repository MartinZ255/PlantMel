<script setup lang="ts">
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import { computed } from 'vue';

/**
 * Gate C1b: Bild in organischer Blob-Maske.
 *
 * - feste Größe über das Seitenverhältnis (`ratio`), kein Layout-Springen
 * - `shape` 1–6 wählt die Form (je Karte leicht verschieden); in einer
 *   `.group` wechselt die Form bei Hover/Fokus (bei reduzierter Bewegung ohne
 *   Übergang, siehe app.css)
 * - `eager`: erstes sichtbares Bild (kein `loading="lazy"`)
 * - ohne `src`: gestaltete organische Komposition statt leerer Fläche
 *   (Slot `#fallback` überschreibt sie)
 */
const props = withDefaults(
    defineProps<{
        src?: string | null;
        alt?: string;
        shape?: number;
        ratio?: '4/3' | '1/1' | '5/4' | '4/5' | '3/2';
        eager?: boolean;
        sizes?: string;
    }>(),
    {
        src: null,
        alt: '',
        shape: 1,
        ratio: '4/3',
        eager: false,
        sizes: undefined,
    },
);

const shapeClass = computed(
    () =>
        [
            'blob-1',
            'blob-2',
            'blob-3',
            'blob-4',
            'blob-5',
            'blob-6',
        ][(((props.shape - 1) % 6) + 6) % 6],
);

const ratioClass = computed(
    () =>
        ({
            '4/3': 'aspect-[4/3]',
            '1/1': 'aspect-square',
            '5/4': 'aspect-[5/4]',
            '4/5': 'aspect-[4/5]',
            '3/2': 'aspect-[3/2]',
        })[props.ratio],
);

// Fallback-Farben wechseln mit der Form, damit Listen lebendig bleiben
const fallbackTone = computed(
    () =>
        [
            'from-tint-strong via-tint to-matcha text-moss',
            'from-matcha via-tint to-canvas text-primary',
            'from-tint via-berry-surface to-tint-strong text-moss',
        ][(((props.shape - 1) % 3) + 3) % 3],
);
</script>

<template>
    <div
        class="blob-mask relative w-full overflow-hidden bg-tint"
        :class="[shapeClass, ratioClass]"
    >
        <img
            v-if="src"
            :src="src"
            :alt="alt"
            :loading="eager ? 'eager' : 'lazy'"
            :fetchpriority="eager ? 'high' : undefined"
            :sizes="sizes"
            decoding="async"
            class="block size-full object-cover"
        />
        <slot v-else name="fallback">
            <div
                class="absolute inset-0 bg-gradient-to-br"
                :class="fallbackTone"
                aria-hidden="true"
            >
                <span
                    class="absolute top-[14%] left-[12%] size-[38%] rounded-full bg-surface/55"
                ></span>
                <span
                    class="absolute right-[10%] bottom-[12%] size-[22%] rounded-full bg-berry/15"
                ></span>
                <LeafOrnament
                    variant="sprig"
                    class="absolute top-1/2 left-1/2 h-[64%] -translate-x-1/2 -translate-y-1/2 rotate-[18deg]"
                />
            </div>
        </slot>
        <slot name="overlay" />
    </div>
</template>

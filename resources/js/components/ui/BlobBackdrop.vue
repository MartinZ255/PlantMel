<script setup lang="ts">
/**
 * Gate C1b: mehrschichtiger, weicher Blob-Hintergrund (rein dekorativ).
 * Liegt absolut im nächsten positionierten Vorfahren, der `overflow-hidden`
 * tragen sollte; nur CSS (Verläufe, Radien), keine Bilder.
 *
 * - `light`: auf Naturgrund (Hero, Kopf der Rezeptliste)
 * - `forest`: auf Waldgrün (Vorteile, Rezeptkopf, Mobilmenü)
 */
withDefaults(defineProps<{ tone?: 'light' | 'forest' }>(), {
    tone: 'light',
});
</script>

<template>
    <!-- helle Variante läuft nach unten weich aus (keine harte Kante am Abschnittsende) -->
    <div
        class="pointer-events-none absolute inset-0 overflow-hidden"
        :class="
            tone === 'light' &&
            '[mask-image:linear-gradient(to_bottom,black_55%,transparent)]'
        "
        aria-hidden="true"
    >
        <template v-if="tone === 'light'">
            <span
                class="animate-morph absolute -top-[18%] -right-[12%] size-[min(70vw,46rem)] bg-[radial-gradient(closest-side,var(--color-matcha),color-mix(in_oklab,var(--color-matcha)_35%,transparent)_65%,transparent)] opacity-80"
            ></span>
            <span
                class="animate-morph absolute top-[38%] -left-[18%] size-[min(60vw,34rem)] bg-[radial-gradient(closest-side,var(--color-tint-strong),transparent)] opacity-90 [animation-delay:-7s]"
            ></span>
            <span
                class="absolute -bottom-[20%] left-[42%] size-[min(40vw,22rem)] rounded-full bg-[radial-gradient(closest-side,color-mix(in_oklab,var(--color-berry)_16%,transparent),transparent)]"
            ></span>
        </template>
        <template v-else>
            <span
                class="animate-morph absolute -top-[25%] -left-[10%] size-[min(80vw,44rem)] bg-[radial-gradient(closest-side,var(--color-forest-soft),transparent)] opacity-90"
            ></span>
            <span
                class="animate-morph absolute -right-[15%] -bottom-[30%] size-[min(70vw,40rem)] bg-[radial-gradient(closest-side,color-mix(in_oklab,var(--color-moss)_30%,transparent),transparent)] [animation-delay:-11s]"
            ></span>
        </template>
    </div>
</template>

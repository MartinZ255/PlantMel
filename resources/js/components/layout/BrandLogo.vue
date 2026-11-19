<script setup lang="ts">
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import { Link } from '@inertiajs/vue3';

/**
 * Gate C: Wortmarke. Gäste → `/`, Angemeldete → `/home` (F-21).
 * C1b: Signet = Keimling in einem Waldgrün-Blob, Wortmarke in Fraunces mit
 * kursivem „Mel“. `tone="on-forest"` für dunkle Flächen (Footer, Mobilmenü).
 * Ohne `href` (z. B. Footer) wird nur die Marke angezeigt.
 */
withDefaults(
    defineProps<{
        href?: string;
        current?: boolean;
        tone?: 'default' | 'on-forest';
    }>(),
    { href: undefined, current: false, tone: 'default' },
);
</script>

<template>
    <component
        :is="href ? Link : 'span'"
        :href="href"
        class="group inline-flex shrink-0 items-center gap-2 rounded-full py-1 pr-1 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus sm:gap-2.5 sm:pr-2"
        :aria-current="href && current ? 'page' : undefined"
    >
        <span
            class="inline-flex size-9 items-center justify-center rounded-[58%_42%_52%_48%/46%_54%_46%_54%] transition-[border-radius,rotate] duration-700 ease-organic group-hover:-rotate-6 group-hover:rounded-[44%_56%_40%_60%/56%_44%_58%_42%]"
            :class="
                tone === 'on-forest'
                    ? 'bg-matcha text-forest'
                    : 'bg-forest text-matcha'
            "
            aria-hidden="true"
        >
            <LeafOrnament variant="sprout" class="size-5.5" />
        </span>
        <span
            class="font-display-wonk text-[1.3rem] leading-none font-semibold tracking-tight sm:text-[1.45rem]"
            :class="tone === 'on-forest' ? 'text-on-forest' : 'text-ink'"
            >Plant<span
                class="accent-word"
                :class="tone === 'on-forest' ? 'text-matcha' : 'text-primary'"
                >Mel</span
            ><span v-if="href" class="sr-only"> – Startseite</span></span
        >
    </component>
</template>

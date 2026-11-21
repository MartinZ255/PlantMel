<script setup lang="ts">
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    BRAND_NAME,
    brandAssetUrl,
    brandConfig,
    type BrandLogoSize,
    type BrandLogoTone,
    type BrandLogoVariant,
} from './brand';

/**
 * Zentrale Marke (Logo-Vorbereitung, docs/modernization/LOGO.md).
 *
 * - `variant`: `full` = Signet + Wortmarke, `mark` = nur Signet
 * - `tone`: `dark` für helle Flächen (Header), `light` für Waldgrün-Flächen
 *   (Footer, AuthShell)
 * - `size`: `md` entspricht exakt dem bisherigen Logo (Header/Footer/AuthShell)
 * - `href`: Link zur Startseite (zugänglicher Name „PlantMel – Startseite“);
 *   ohne `href` wird nur die Marke angezeigt (Footer).
 *
 * Modus `builtin` (heute): Keimling in einem Waldgrün-Blob + Wortmarke in
 * Fraunces mit kursivem „Mel“ – Markup unverändert aus Gate C1b, damit die
 * Screenshot-Baselines pixelgleich bleiben.
 * Modus `custom`: Dateien aus `public/brand/` (Schalter in brand.config.json).
 */
const props = withDefaults(
    defineProps<{
        href?: string;
        current?: boolean;
        variant?: BrandLogoVariant;
        tone?: BrandLogoTone;
        size?: BrandLogoSize;
    }>(),
    {
        href: undefined,
        current: false,
        variant: 'full',
        tone: 'dark',
        size: 'md',
    },
);

const custom = computed(() => brandConfig.logo === 'custom');
const onForest = computed(() => props.tone === 'light');

/** Feste Klassen je Größe (vollständig ausgeschrieben, damit Tailwind sie findet) */
const sizes = {
    sm: {
        signet: 'size-8',
        sprout: 'size-5',
        text: 'text-[1.15rem]',
        image: 'h-8',
    },
    md: {
        signet: 'size-9',
        sprout: 'size-5.5',
        text: 'text-[1.3rem] sm:text-[1.45rem]',
        image: 'h-9',
    },
    lg: {
        signet: 'size-12',
        sprout: 'size-7',
        text: 'text-[1.75rem] sm:text-[2rem]',
        image: 'h-12',
    },
} as const;
const sizeClasses = computed(() => sizes[props.size]);

/** Bild der eigenen Marke: Vollversion nur, wenn sie den Schriftzug enthält */
const imageVariant = computed<BrandLogoVariant>(() =>
    props.variant === 'full' && brandConfig.fullHasWordmark ? 'full' : 'mark',
);
const imageSrc = computed(() => brandAssetUrl(imageVariant.value, props.tone));
/** Text-Wortmarke neben der Grafik? (builtin oder eigene Bildmarke ohne Schriftzug) */
const textWordmark = computed(
    () =>
        props.variant === 'full' &&
        (!custom.value || !brandConfig.fullHasWordmark),
);
</script>

<template>
    <component
        :is="href ? Link : 'span'"
        :href="href"
        class="group inline-flex shrink-0 items-center gap-2 rounded-full py-1 pr-1 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus sm:gap-2.5 sm:pr-2"
        :aria-current="href && current ? 'page' : undefined"
        :data-brand-mode="custom ? 'custom' : undefined"
    >
        <img
            v-if="custom"
            :src="imageSrc"
            :alt="textWordmark ? '' : BRAND_NAME"
            class="block w-auto max-w-none"
            :class="sizeClasses.image"
            decoding="async"
        />
        <span
            v-else
            class="inline-flex items-center justify-center rounded-[58%_42%_52%_48%/46%_54%_46%_54%] transition-[border-radius,rotate] duration-700 ease-organic group-hover:-rotate-6 group-hover:rounded-[44%_56%_40%_60%/56%_44%_58%_42%]"
            :class="[
                sizeClasses.signet,
                onForest ? 'bg-matcha text-forest' : 'bg-forest text-matcha',
            ]"
            aria-hidden="true"
        >
            <LeafOrnament variant="sprout" :class="sizeClasses.sprout" />
        </span>
        <span
            v-if="textWordmark"
            class="font-display-wonk leading-none font-semibold tracking-tight"
            :class="[
                sizeClasses.text,
                onForest ? 'text-on-forest' : 'text-ink',
            ]"
            >Plant<span
                class="accent-word"
                :class="onForest ? 'text-matcha' : 'text-primary'"
                >Mel</span
            ><span v-if="href" class="sr-only"> – Startseite</span></span
        >
        <!-- nur Signet (builtin): Name als unsichtbarer Text -->
        <span v-else-if="!custom" class="sr-only"
            >{{ BRAND_NAME
            }}<template v-if="href"> – Startseite</template></span
        >
        <!-- eigene Grafik mit alt="PlantMel": nur der Zusatz für den Link -->
        <span v-else-if="href" class="sr-only"> – Startseite</span>
    </component>
</template>

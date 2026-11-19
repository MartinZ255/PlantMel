<template>
    <!-- Slice 4 (F-10): Links zu Impressum und Datenschutz auf allen Seiten -->
    <footer v-if="variant === 'auth'" class="app-footer mt-5 w-full">
        <div class="flex justify-center">
            <nav aria-label="Rechtliches">
                <ul class="flex flex-wrap justify-center gap-x-2 gap-y-1">
                    <li v-for="link in links" :key="link.name">
                        <Link
                            :href="route(link.name)"
                            class="inline-flex min-h-8 items-center rounded-md px-2 text-sm text-ink-muted underline-offset-4 hover:text-ink hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus aria-[current=page]:font-semibold aria-[current=page]:text-ink"
                            :aria-current="
                                isCurrent(link.name) ? 'page' : undefined
                            "
                        >
                            {{ link.label }}
                        </Link>
                    </li>
                </ul>
            </nav>
        </div>
    </footer>

    <!-- C1b: Waldgrün mit welliger Oberkante, Körnung und Zweigen -->
    <footer v-else class="app-footer mt-auto pt-10">
        <div
            class="relative overflow-hidden bg-forest-deep grain-light on-forest"
        >
            <WaveDivider :variant="3" flip class="text-canvas" />
            <LeafOrnament
                variant="eucalyptus"
                class="pointer-events-none absolute -right-6 -bottom-10 h-56 rotate-[-30deg] text-forest-soft md:right-auto md:left-[48%]"
            />
            <LeafOrnament
                variant="sprig"
                class="pointer-events-none absolute top-24 -left-8 hidden h-40 rotate-[40deg] text-forest sm:block"
            />
            <div
                class="relative mx-auto flex w-full max-w-(--content-max) flex-col gap-8 px-4 pt-6 pb-10 sm:px-6 md:flex-row md:items-end md:justify-between lg:px-8"
            >
                <div class="min-w-0">
                    <BrandLogo tone="on-forest" />
                    <p
                        class="mt-4 max-w-sm font-display text-xl leading-snug text-on-forest"
                    >
                        Pflanzenbetonte Rezepte –
                        <span class="accent-word text-matcha">gesammelt</span>
                        an einem Ort.
                    </p>
                </div>
                <nav aria-label="Rechtliches">
                    <ul class="flex flex-wrap gap-x-2 gap-y-1">
                        <li v-for="link in links" :key="link.name">
                            <Link
                                :href="route(link.name)"
                                class="inline-flex min-h-10 items-center rounded-pebble px-3 text-sm font-medium text-on-forest-muted underline-offset-4 hover:bg-forest-soft hover:text-on-forest hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus aria-[current=page]:font-semibold aria-[current=page]:text-on-forest"
                                :aria-current="
                                    isCurrent(link.name) ? 'page' : undefined
                                "
                            >
                                {{ link.label }}
                            </Link>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </footer>
</template>

<script setup lang="ts">
import BrandLogo from '@/components/layout/BrandLogo.vue';
import LeafOrnament from '@/components/ui/LeafOrnament.vue';
import WaveDivider from '@/components/ui/WaveDivider.vue';
import { Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

withDefaults(defineProps<{ variant?: 'app' | 'auth' }>(), {
    variant: 'app',
});

const links = [
    { name: 'legal.imprint', label: 'Impressum' },
    { name: 'legal.privacy', label: 'Datenschutz' },
] as const;

const isCurrent = (name: string) => {
    try {
        return route().current(name);
    } catch {
        return false;
    }
};
</script>

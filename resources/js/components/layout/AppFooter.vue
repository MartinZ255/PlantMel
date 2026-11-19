<template>
    <!-- Slice 4 (F-10): Links zu Impressum und Datenschutz auf allen Seiten -->
    <footer
        :class="
            variant === 'auth'
                ? 'mt-5 w-full'
                : 'mt-auto border-t border-line bg-surface'
        "
    >
        <div
            :class="
                variant === 'auth'
                    ? 'flex justify-center'
                    : 'mx-auto flex w-full max-w-(--content-max) flex-col items-center gap-3 px-4 py-6 sm:flex-row sm:justify-between sm:px-6 lg:px-8'
            "
        >
            <p
                v-if="variant !== 'auth'"
                class="inline-flex items-center gap-2 text-sm font-semibold text-ink"
            >
                <Leaf class="size-4 text-primary" aria-hidden="true" />
                PlantMel
            </p>
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
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Leaf } from 'lucide-vue-next';
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

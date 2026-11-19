<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Leaf } from 'lucide-vue-next';

/** Gate C: Wortmarke. Gäste → `/`, Angemeldete → `/home` (F-21). */
defineProps<{ href: string; current?: boolean }>();
</script>

<template>
    <Link
        :href="href"
        class="inline-flex shrink-0 items-center gap-2 rounded-full py-1 pr-2 text-lg font-semibold tracking-tight text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
        :aria-current="current ? 'page' : undefined"
    >
        <span
            class="inline-flex size-9 items-center justify-center rounded-xl bg-primary text-on-primary"
            aria-hidden="true"
        >
            <Leaf class="size-5" />
        </span>
        <span>PlantMel<span class="sr-only"> – Startseite</span></span>
    </Link>
</template>

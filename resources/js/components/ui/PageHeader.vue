<script setup lang="ts">
/**
 * Gate C: Seitenkopf mit genau einem `h1` (F-42). Optional Dachzeile
 * (`eyebrow`), Beschreibung, Aktionen rechts (mobil darunter) und ein
 * Bereich für Metadaten (`#meta`, z. B. Trefferzahl als Live-Region).
 */
withDefaults(
    defineProps<{
        title: string;
        description?: string;
        eyebrow?: string;
    }>(),
    { description: undefined, eyebrow: undefined },
);
</script>

<template>
    <div
        class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
    >
        <div class="min-w-0">
            <p
                v-if="eyebrow"
                class="mb-1 text-sm font-semibold tracking-wide text-primary uppercase"
            >
                {{ eyebrow }}
            </p>
            <h1
                class="text-wrap-anywhere text-3xl font-semibold tracking-tight text-ink sm:text-4xl"
            >
                {{ title }}
            </h1>
            <p v-if="description" class="mt-2 max-w-2xl text-lg text-ink-muted">
                {{ description }}
            </p>
            <div v-if="$slots.meta" class="mt-2 text-sm text-ink-muted">
                <slot name="meta" />
            </div>
        </div>
        <div
            v-if="$slots.actions"
            class="flex shrink-0 flex-wrap items-center gap-2"
        >
            <slot name="actions" />
        </div>
    </div>
</template>

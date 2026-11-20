<script setup lang="ts">
import LeafOrnament from '@/components/ui/LeafOrnament.vue';

/**
 * Gate C: Seitenkopf mit genau einem `h1` (F-42). Optional Dachzeile
 * (`eyebrow`), Beschreibung, Aktionen rechts (mobil darunter) und ein
 * Bereich für Metadaten (`#meta`, z. B. Trefferzahl als Live-Region).
 *
 * C1b: Titel in Fraunces (Display), Dachzeile mit Keimling. Der Slot
 * `#title` erlaubt ein kursives Akzentwort; der zugängliche Name bleibt der
 * sichtbare Text.
 */
withDefaults(
    defineProps<{
        title: string;
        description?: string;
        eyebrow?: string;
        size?: 'md' | 'xl';
    }>(),
    { description: undefined, eyebrow: undefined, size: 'md' },
);
</script>

<template>
    <div
        class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between"
    >
        <div class="min-w-0">
            <p
                v-if="eyebrow"
                class="mb-2 inline-flex items-center gap-1.5 text-sm font-semibold tracking-[0.14em] text-primary uppercase"
            >
                <LeafOrnament variant="sprout" class="size-4 text-moss" />
                {{ eyebrow }}
            </p>
            <h1
                class="font-display-wonk text-wrap-anywhere font-semibold text-ink"
                :class="
                    size === 'xl'
                        ? 'text-5xl sm:text-6xl lg:text-7xl'
                        : 'text-4xl sm:text-5xl'
                "
            >
                <slot name="title">{{ title }}</slot>
            </h1>
            <p
                v-if="description"
                class="mt-3 max-w-2xl text-lg text-ink-muted sm:text-xl"
            >
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

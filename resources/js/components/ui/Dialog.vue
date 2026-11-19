<script setup lang="ts">
import { useModal } from '@/composables/useModal';
import { X } from 'lucide-vue-next';
import { ref, useId } from 'vue';

/**
 * Gate C: modaler Dialog auf Basis von `useModal` (F-30/F-40).
 *
 * - `<Teleport to="body">`: liegt außerhalb von `#app`, über Kopfzeile und Menüs
 * - `role="dialog"`, `aria-modal="true"`, `aria-labelledby` auf den Titel,
 *   optional `aria-describedby` auf die Beschreibung
 * - Fokus beim Öffnen auf das erste Bedienelement des Inhalts (der
 *   Schließen-Knopf steht im DOM am Ende), Fokusfalle, Escape, Scroll-Sperre,
 *   Hintergrund `inert`, beim Schließen Fokus zurück an den Auslöser
 * - Klick auf den Hintergrund und Escape lösen `close` aus; der Aufrufer
 *   entscheidet, ob wirklich geschlossen wird (z. B. nicht während des Speicherns)
 */
interface Props {
    open: boolean;
    title: string;
    description?: string;
    size?: 'sm' | 'md' | 'lg';
    closeLabel?: string;
    /** Schließen-Knopf oben rechts anzeigen */
    dismissible?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    description: undefined,
    size: 'md',
    closeLabel: 'Dialog schließen',
    dismissible: true,
});

const emit = defineEmits<{ (e: 'close'): void }>();

const uid = useId();
const titleId = `dialog-${uid}-title`;
const descriptionId = `dialog-${uid}-description`;

const dialogEl = ref<HTMLElement | null>(null);

useModal({
    open: () => props.open,
    dialog: dialogEl,
    onClose: () => emit('close'),
    trapFocus: true,
});

const widths = {
    sm: 'sm:max-w-sm',
    md: 'sm:max-w-lg',
    lg: 'sm:max-w-2xl',
} as const;
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-(--z-modal-backdrop) flex items-end justify-center bg-ink/55 p-3 sm:items-center sm:p-6"
            @click.self="emit('close')"
        >
            <div
                ref="dialogEl"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="titleId"
                :aria-describedby="description ? descriptionId : undefined"
                tabindex="-1"
                class="relative z-(--z-modal) flex max-h-[calc(100dvh-1.5rem)] w-full flex-col overflow-y-auto rounded-2xl bg-surface p-5 text-ink shadow-overlay focus:outline-none sm:max-h-[calc(100dvh-3rem)] sm:p-6"
                :class="widths[size]"
            >
                <h2
                    :id="titleId"
                    class="text-wrap-anywhere pr-10 text-xl font-semibold text-ink"
                >
                    {{ title }}
                </h2>
                <p
                    v-if="description"
                    :id="descriptionId"
                    class="mt-1.5 text-sm text-ink-muted"
                >
                    {{ description }}
                </p>

                <div class="mt-4 min-w-0">
                    <slot />
                </div>

                <div
                    v-if="$slots.footer"
                    class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:flex-wrap sm:justify-end"
                >
                    <slot name="footer" />
                </div>

                <button
                    v-if="dismissible"
                    type="button"
                    class="absolute top-3 right-3 inline-flex size-10 items-center justify-center rounded-full text-ink-muted hover:bg-tint hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                    :aria-label="closeLabel"
                    @click="emit('close')"
                >
                    <X class="size-5" aria-hidden="true" />
                </button>
            </div>
        </div>
    </Teleport>
</template>

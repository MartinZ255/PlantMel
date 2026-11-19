import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref, type Ref } from 'vue';

/**
 * Gate C: Aufklappmenüs in der Kopfnavigation (Benutzer-Menü, Sammlungen).
 *
 * Verhalten wie das User-Menü seit Slice 1 (F-04):
 * - Escape schließt und gibt den Fokus an den Auslöser zurück
 * - Klick/Tippen außerhalb schließt (ohne Fokusraub)
 * - verlässt der Tastaturfokus das Menü (Tab), schließt es
 * - nach jeder Inertia-Navigation geschlossen
 * Geschlossen ist das Panel per `inert` nicht fokussierbar und per CSS
 * unsichtbar und nicht klickbar (`visibility: hidden`, `pointer-events: none`).
 */
export interface UseDisclosureOptions {
    /** Umschließt Auslöser und Panel (Außenklick-Grenze). */
    root: Ref<HTMLElement | null>;
    /** Auslöser; bekommt bei Escape den Fokus zurück. */
    trigger: Ref<HTMLElement | null>;
}

export function useDisclosure(options: UseDisclosureOptions) {
    const isOpen = ref(false);

    const open = () => {
        isOpen.value = true;
    };

    const close = (restoreFocus = false) => {
        if (!isOpen.value) return;
        isOpen.value = false;
        if (restoreFocus) options.trigger.value?.focus();
    };

    const toggle = () => {
        if (isOpen.value) close();
        else open();
    };

    const onKeydown = (event: KeyboardEvent) => {
        if (event.key !== 'Escape' || !isOpen.value) return;
        // Ein offener Dialog hat Vorrang (useModal fängt Escape in der Capture-Phase)
        event.preventDefault();
        close(true);
    };

    const onPointerdown = (event: PointerEvent) => {
        if (!isOpen.value) return;
        const target = event.target as Node | null;
        if (target && options.root.value?.contains(target)) return;
        close();
    };

    const onFocusin = (event: FocusEvent) => {
        if (!isOpen.value) return;
        const target = event.target as Node | null;
        if (target && options.root.value?.contains(target)) return;
        close();
    };

    let stopNavigate: (() => void) | null = null;

    onMounted(() => {
        document.addEventListener('keydown', onKeydown);
        document.addEventListener('pointerdown', onPointerdown);
        document.addEventListener('focusin', onFocusin);
        stopNavigate = router.on('navigate', () => close());
    });

    onBeforeUnmount(() => {
        document.removeEventListener('keydown', onKeydown);
        document.removeEventListener('pointerdown', onPointerdown);
        document.removeEventListener('focusin', onFocusin);
        stopNavigate?.();
    });

    return { isOpen, open, close, toggle };
}

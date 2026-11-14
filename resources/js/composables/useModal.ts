import {
    nextTick,
    onBeforeUnmount,
    watch,
    type Ref,
    type WatchSource,
} from 'vue';

/**
 * Slice 3 (F-30): gemeinsames Verhalten der Dialoge.
 *
 * - Scroll-Sperre des Hintergrunds (`<body>` ist der Scroll-Container)
 * - Hintergrund (`#app`) ist `inert`, solange ein Dialog offen ist: nicht
 *   klickbar, nicht fokussierbar. Die Dialoge selbst liegen per
 *   `<Teleport to="body">` außerhalb von `#app`.
 * - Escape schließt den obersten Dialog
 * - Fokus beim Öffnen in den Dialog, beim Schließen zurück an den Auslöser
 *
 * Mehrere gleichzeitig offene Dialoge werden als Stapel verwaltet; Sperre und
 * `inert` werden erst aufgehoben, wenn der letzte geschlossen ist.
 */

type StackEntry = { id: number; close: () => void };

const stack: StackEntry[] = [];
let nextId = 1;
let saved: {
    bodyOverflow: string;
    htmlOverflow: string;
    appInert: boolean;
} | null = null;

const FOCUSABLE =
    'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

function appRoot(): HTMLElement | null {
    return document.getElementById('app');
}

function lockBackground() {
    if (saved) return;
    const app = appRoot();
    saved = {
        bodyOverflow: document.body.style.overflow,
        htmlOverflow: document.documentElement.style.overflow,
        appInert: app?.inert ?? false,
    };
    document.body.style.overflow = 'hidden';
    document.documentElement.style.overflow = 'hidden';
    if (app) app.inert = true;
}

function unlockBackground() {
    if (!saved) return;
    document.body.style.overflow = saved.bodyOverflow;
    document.documentElement.style.overflow = saved.htmlOverflow;
    const app = appRoot();
    if (app) app.inert = saved.appInert;
    saved = null;
}

function onKeydown(event: KeyboardEvent) {
    if (event.key !== 'Escape' || stack.length === 0) return;
    event.preventDefault();
    event.stopPropagation();
    stack[stack.length - 1].close();
}

export interface UseModalOptions {
    /** Offen-Zustand des Dialogs (Prop oder Ref). */
    open: WatchSource<boolean>;
    /** Wird bei Escape aufgerufen; der Dialog entscheidet selbst, ob er schließt. */
    onClose: () => void;
    /** Das Element mit `role="dialog"`. */
    dialog: Ref<HTMLElement | null>;
    /** Optional: Element, das beim Öffnen den Fokus bekommt. */
    initialFocus?: Ref<HTMLElement | null>;
}

export function useModal(options: UseModalOptions) {
    const id = nextId++;
    let returnFocus: HTMLElement | null = null;
    let isOpen = false;

    const open = async () => {
        if (isOpen || typeof document === 'undefined') return;
        isOpen = true;
        const active = document.activeElement;
        returnFocus =
            active instanceof HTMLElement && active !== document.body
                ? active
                : null;

        if (stack.length === 0) {
            document.addEventListener('keydown', onKeydown, true);
        }
        stack.push({ id, close: () => options.onClose() });
        lockBackground();

        await nextTick();
        const dialog = options.dialog.value;
        const target =
            options.initialFocus?.value ??
            dialog?.querySelector<HTMLElement>(FOCUSABLE) ??
            dialog;
        target?.focus();
    };

    const close = () => {
        if (!isOpen || typeof document === 'undefined') return;
        isOpen = false;
        const index = stack.findIndex((entry) => entry.id === id);
        if (index !== -1) stack.splice(index, 1);

        if (stack.length === 0) {
            document.removeEventListener('keydown', onKeydown, true);
            unlockBackground();
        }

        // Fokus erst nach dem Aufheben von `inert` zurückgeben
        const target = returnFocus;
        returnFocus = null;
        if (target && target.isConnected) {
            target.focus();
        }
    };

    watch(
        options.open,
        (value) => {
            if (value) open();
            else close();
        },
        { immediate: true },
    );

    onBeforeUnmount(close);

    return { close };
}

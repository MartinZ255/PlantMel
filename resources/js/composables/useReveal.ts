import { onBeforeUnmount, onMounted, type Ref } from 'vue';

/**
 * Gate C1b: sanftes Einblenden beim Scrollen (IntersectionObserver).
 *
 * Grundsatz: Inhalte sind standardmäßig sichtbar. Der Composable versteckt ein
 * Element (`data-reveal="hidden"`) nur, wenn
 * - Bewegung erwünscht ist (`prefers-reduced-motion: no-preference`),
 * - der Browser IntersectionObserver kennt und
 * - das Element beim Laden noch unterhalb des Sichtbereichs liegt.
 * Sobald es in den Sichtbereich kommt, wird es eingeblendet
 * (`data-reveal="shown"`, Übergang in app.css) und nicht mehr beobachtet.
 * Ohne JavaScript, beim Drucken und bei reduzierter Bewegung erscheint alles
 * sofort; das Layout springt nie (nur Deckkraft und `translate`).
 */
export const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)';

export function prefersReducedMotion(): boolean {
    return (
        typeof window === 'undefined' ||
        typeof window.matchMedia !== 'function' ||
        window.matchMedia(REDUCED_MOTION_QUERY).matches
    );
}

export function useReveal(
    target: Ref<HTMLElement | null>,
    options: { delay?: number } = {},
) {
    let observer: IntersectionObserver | null = null;

    onMounted(() => {
        const el = target.value;
        if (!el) return;
        if (prefersReducedMotion() || !('IntersectionObserver' in window))
            return;

        // bereits (fast) sichtbar: nicht verstecken, kein Aufblitzen
        const rect = el.getBoundingClientRect();
        if (rect.top < window.innerHeight * 0.9) return;

        if (options.delay)
            el.style.setProperty('--reveal-delay', `${options.delay}ms`);
        el.dataset.reveal = 'hidden';

        observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    if (!entry.isIntersecting) continue;
                    el.dataset.reveal = 'shown';
                    observer?.disconnect();
                    observer = null;
                }
            },
            { rootMargin: '0px 0px -6% 0px', threshold: 0.08 },
        );
        observer.observe(el);
    });

    onBeforeUnmount(() => {
        observer?.disconnect();
        observer = null;
    });
}

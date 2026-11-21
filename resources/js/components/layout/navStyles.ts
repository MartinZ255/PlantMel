/** Gate C: gemeinsame Klassen der Kopfnavigation (AppHeader, CollectionsMenu). */

/**
 * E18: zwischen md und xl etwas schmaler (Abstand, Schrift), damit die
 * Hauptnavigation mit dem Eintrag „Community“ bei 768 und 1024 px in die Kopfzeile passt.
 */
export const navLinkBase =
    'relative inline-flex min-h-10 items-center gap-1 rounded-pebble px-2 text-[0.875rem] font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus xl:gap-1.5 xl:px-3.5 xl:text-[0.9375rem]';

/**
 * C1b: aktiver Bereich als Matcha-„Kiesel“ mit Waldgrün-Text (8,5:1);
 * sonst Hover in hellem Grün.
 */
export const navLinkState = (active: boolean) =>
    active
        ? 'bg-matcha text-forest'
        : 'text-ink hover:bg-tint hover:text-primary';

export const dropdownPanelBase =
    'absolute top-full z-(--z-dropdown) mt-3 rounded-[1.5rem_1.25rem_1.75rem_1.25rem] border border-line/70 bg-surface p-2 shadow-overlay transition-[opacity,visibility,translate] duration-200';

/** Geschlossen: unsichtbar und nicht klickbar (zusätzlich `inert`, F-04). */
export const dropdownPanelState = (open: boolean) =>
    open
        ? 'visible translate-y-0 opacity-100'
        : 'pointer-events-none invisible -translate-y-1 opacity-0';

export const dropdownItem =
    'flex min-h-10 w-full items-center rounded-[0.9rem_0.7rem_0.95rem_0.75rem] px-3 text-left text-[0.9375rem] text-ink hover:bg-tint focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-focus';

export const dropdownItemDanger =
    'flex min-h-10 w-full items-center rounded-[0.9rem_0.7rem_0.95rem_0.75rem] px-3 text-left text-[0.9375rem] text-danger hover:bg-danger-surface focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-focus';

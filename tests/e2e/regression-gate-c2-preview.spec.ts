import type { Page } from '@playwright/test';
import path from 'node:path';
import { authFile, DATA, expect, settle, test } from './support/fixtures';

/**
 * Gate C2 Integration – Gesamtvorschau aller Seiten (Endstand ohne rezeptly/app.css).
 *
 * Erzeugt die Bilder unter docs/modernization/gate-c2-vorschau/ (Dateinamen wie in
 * den Paketen B und C). Die Bilder des Formular-Pakets (forms-*.png) erzeugt
 * regression-gate-c2-forms.spec.ts › „Gate C2 – Vorschau“.
 *
 * Nur mit GATE_C2_PREVIEW=1; sonst uebersprungen. Keine Datenveraenderung: Im
 * Bearbeitungsmodus der Sammlung wird nur die Rueckfrage geoeffnet, nicht bestaetigt.
 */

type Role = 'guest' | 'user' | 'host';
type Shot = {
    name: string;
    path: string;
    role: Role;
    prepare?: (page: Page) => Promise<void>;
};

const SHOTS: Shot[] = [
    { name: 'home', path: '/home', role: 'user' },
    { name: 'sammlungen-uebersicht', path: '/sammlungen', role: 'user' },
    // Der Host hat im Seed keine Sammlungen
    { name: 'sammlungen-uebersicht-leer', path: '/sammlungen', role: 'host' },
    { name: 'sammlungen-anlegen', path: '/sammlungen/erstellen', role: 'user' },
    {
        name: 'sammlungen-detail',
        path: `/sammlung/${DATA.collections.filled.id}`,
        role: 'user',
    },
    {
        name: 'sammlungen-detail-entfernen',
        path: `/sammlung/${DATA.collections.filled.id}`,
        role: 'user',
        prepare: async (page) => {
            await page
                .getByRole('button', { name: 'Rezepte entfernen' })
                .click();
            await page
                .getByRole('button', { name: /^Aus Sammlung entfernen/ })
                .first()
                .click();
            await expect(page.getByText('Wirklich entfernen?')).toBeVisible();
        },
    },
    {
        name: 'sammlungen-detail-leer',
        path: `/sammlung/${DATA.collections.empty.id}`,
        role: 'user',
    },
    { name: 'zutaten-meine', path: '/meineZutaten', role: 'user' },
    { name: 'zutaten-suche', path: '/zutatensuche', role: 'guest' },
    {
        name: 'zutaten-suche-filter',
        path: '/zutatensuche',
        role: 'guest',
        prepare: async (page) => {
            const toggle = page.getByRole('button', {
                name: 'Filter',
                exact: true,
            });
            await toggle.click();
            await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        },
    },
    { name: 'shell-login', path: '/login', role: 'guest' },
    { name: 'shell-registrieren', path: '/register', role: 'guest' },
    { name: 'shell-einstellungen', path: '/settings', role: 'user' },
    { name: 'shell-profil', path: '/settings/profile', role: 'user' },
    { name: 'shell-impressum', path: '/impressum', role: 'guest' },
    { name: 'shell-404', path: '/gibt-es-nicht', role: 'guest' },
];

/** Einmal durch die Seite scrollen, damit lazy-Bilder geladen sind (nur Vorschau). */
async function loadLazyImages(page: Page) {
    await page.evaluate(async () => {
        const step = window.innerHeight;
        for (let y = 0; y < document.documentElement.scrollHeight; y += step) {
            window.scrollTo(0, y);
            await new Promise((r) => setTimeout(r, 120));
        }
        window.scrollTo(0, 0);
    });
    await settle(page);
}

const out = (name: string) =>
    path.join(
        process.cwd(),
        'docs',
        'modernization',
        'gate-c2-vorschau',
        `${name}.png`,
    );

test.describe('Gate C2 – Gesamtvorschau', () => {
    test.skip(
        !process.env.GATE_C2_PREVIEW,
        'nur fuer die Abnahme-Vorschau (GATE_C2_PREVIEW=1)',
    );
    test.use({ reducedMotion: 'reduce' });

    for (const role of ['guest', 'user', 'host'] as const) {
        test.describe(role, () => {
            test.use({
                storageState:
                    role === 'guest'
                        ? { cookies: [], origins: [] }
                        : authFile(role),
            });

            for (const vp of [
                { label: '1440', width: 1440, height: 900, mobile: false },
                { label: '390', width: 390, height: 844, mobile: true },
            ]) {
                test.describe(vp.label, () => {
                    test.use({
                        viewport: { width: vp.width, height: vp.height },
                        isMobile: vp.mobile,
                        hasTouch: vp.mobile,
                    });

                    for (const shot of SHOTS.filter((s) => s.role === role)) {
                        test(`${shot.name}-${vp.label}`, async ({ page }) => {
                            await page.goto(shot.path);
                            await settle(page);
                            await loadLazyImages(page);
                            if (shot.prepare) await shot.prepare(page);
                            // Ganzseitenbild: fixierte/klebende Elemente (Kopfzeile,
                            // Skip-Link) zeichnet Chromium an der aktuellen Scroll-
                            // position - deshalb vor der Aufnahme nach oben.
                            await page.evaluate(() => window.scrollTo(0, 0));
                            await page.waitForTimeout(200);
                            await page.screenshot({
                                path: out(`${shot.name}-${vp.label}`),
                                fullPage: true,
                                animations: 'disabled',
                            });
                        });
                    }
                });
            }
        });
    }
});

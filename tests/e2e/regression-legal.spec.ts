import type { Page } from '@playwright/test';
import { authFile, expect, settle, test } from './support/fixtures';

/**
 * Regressionstests Slice 4 – Impressum, Datenschutz, selbst gehostete Schrift
 * (F-10, F-58).
 *
 * Die E2E-Instanz setzt keine LEGAL_*-Variablen: beide Seiten zeigen die
 * Platzhalter aus config/legal.php und den Entwurf-Hinweis.
 * Keine Datenveraenderungen.
 */

const MOBILE_390 = {
    viewport: { width: 390, height: 844 },
    isMobile: true,
    hasTouch: true,
} as const;

const footer = (page: Page) => page.getByRole('contentinfo');

async function followFooterLink(
    page: Page,
    name: 'Impressum' | 'Datenschutz',
    expectedPath: string,
    expectedH1: string,
) {
    const nav = footer(page).getByRole('navigation', { name: 'Rechtliches' });
    const link = nav.getByRole('link', { name, exact: true });
    await link.scrollIntoViewIfNeeded();
    await expect(link).toBeVisible();
    await link.click();
    await page.waitForURL((url) => url.pathname === expectedPath);
    await expect(page.locator('h1')).toHaveText(expectedH1);
}

for (const [label, use] of [
    ['1440', {}],
    ['390', MOBILE_390],
] as const) {
    test.describe(`Footer-Links (${label} px)`, () => {
        test.use(use);

        for (const start of ['/', '/rezepte']) {
            test(`F-10 Footer auf ${start} fuehrt zu Impressum und Datenschutz`, async ({
                page,
            }) => {
                await page.goto(start);
                await settle(page);
                await expect(footer(page)).toHaveCount(1);
                await followFooterLink(
                    page,
                    'Impressum',
                    '/impressum',
                    'Impressum',
                );

                await page.goto(start);
                await settle(page);
                await followFooterLink(
                    page,
                    'Datenschutz',
                    '/datenschutz',
                    'Datenschutzerklärung',
                );
            });
        }

        test('F-10 Footer erzeugt keinen horizontalen Ueberlauf', async ({
            page,
        }) => {
            for (const path of ['/', '/impressum', '/datenschutz']) {
                await page.goto(path);
                await settle(page);
                const overflow = await page.evaluate(() => {
                    const f = document.querySelector('footer.app-footer');
                    const r = f?.getBoundingClientRect();
                    return {
                        doc:
                            document.documentElement.scrollWidth >
                            window.innerWidth,
                        footer: !!r && r.right > window.innerWidth + 1,
                    };
                });
                expect(overflow, path).toEqual({ doc: false, footer: false });
            }
        });
    });
}

test.describe('angemeldet', () => {
    test.use({ storageState: authFile('user') });

    test('F-10 Footer auch auf /home', async ({ page }) => {
        await page.goto('/home');
        await settle(page);
        await followFooterLink(page, 'Impressum', '/impressum', 'Impressum');
    });
});

test('F-10 Auth-Seiten haben beide Links', async ({ page }) => {
    for (const path of ['/login', '/register', '/forgot-password']) {
        await page.goto(path);
        await settle(page);
        const nav = footer(page).getByRole('navigation', {
            name: 'Rechtliches',
        });
        await expect(
            nav.getByRole('link', { name: 'Impressum', exact: true }),
            path,
        ).toHaveAttribute('href', /\/impressum$/);
        await expect(
            nav.getByRole('link', { name: 'Datenschutz', exact: true }),
            path,
        ).toHaveAttribute('href', /\/datenschutz$/);
    }
});

test('F-10 Registrierung zeigt Datenschutz-Hinweis mit Link, ohne Pflicht-Checkbox', async ({
    page,
}) => {
    await page.goto('/register');
    await settle(page);
    // Gate C2: Hinweis im Registrierungsformular (Datenattribut statt Legacy-Klasse)
    const form = page.getByRole('form', { name: 'Registrieren' });
    const note = form.locator('[data-auth-privacy-note]');
    await expect(note).toContainText(
        'Mit der Registrierung gilt unsere Datenschutzerklärung',
    );
    await expect(
        note.getByRole('link', { name: 'Datenschutzerklärung' }),
    ).toHaveAttribute('href', /\/datenschutz$/);
    await expect(form.locator('input[type="checkbox"]')).toHaveCount(0);
});

test('F-10 Rechtsseiten: Entwurf-Hinweis, genau ein h1, Titel mit PlantMel', async ({
    page,
}) => {
    const expected = {
        '/impressum': 'Impressum – PlantMel',
        '/datenschutz': 'Datenschutzerklärung – PlantMel',
    } as const;
    for (const [path, title] of Object.entries(expected)) {
        const response = await page.goto(path);
        expect(response?.status(), path).toBe(200);
        await settle(page);
        await expect(page.locator('h1'), path).toHaveCount(1);
        expect(await page.title(), path).toBe(title);
        await expect(
            page.locator('main [data-legal-draft-notice]'),
            path,
        ).toContainText('Entwurf – Angaben des Betreibers fehlen noch');
        await expect(
            page.locator('main mark[data-legal-placeholder]').first(),
        ).toBeVisible();
        // Hinweis nur auf den Seiten, nicht im Footer
        await expect(
            footer(page).locator('[data-legal-draft-notice]'),
        ).toHaveCount(0);
    }
    // Auf anderen Seiten kein Entwurf-Hinweis
    await page.goto('/rezepte');
    await settle(page);
    await expect(page.locator('[data-legal-draft-notice]')).toHaveCount(0);
});

test('F-58 Schrift wird von /build/ geladen, kein Request an fonts.bunny.net', async ({
    page,
}) => {
    const requests: string[] = [];
    page.on('request', (req) => requests.push(req.url()));

    for (const path of ['/', '/login', '/datenschutz']) {
        await page.goto(path);
        await settle(page);
        const font = await page.evaluate(async () => {
            await document.fonts.ready;
            const faces = Array.from(document.fonts).filter((f) =>
                f.family.replace(/["']/g, '').includes('Instrument Sans'),
            );
            return {
                check: document.fonts.check('16px "Instrument Sans"'),
                loaded: faces
                    .filter((f) => f.status === 'loaded')
                    .map((f) => f.weight),
                bodyFont: getComputedStyle(document.body).fontFamily,
            };
        });
        expect(font.check, path).toBe(true);
        expect(font.loaded.length, path).toBeGreaterThan(0);
        expect(font.bodyFont, path).toContain('Instrument Sans');
    }

    const woff2 = requests.filter((u) =>
        /\/build\/assets\/instrument-sans-latin-[456]00-normal-[^/]+\.woff2$/.test(
            new URL(u).pathname,
        ),
    );
    expect(woff2.length).toBeGreaterThan(0);
    expect(requests.filter((u) => u.includes('fonts.bunny.net'))).toEqual([]);
    expect(
        requests.filter((u) => new URL(u).hostname !== '127.0.0.1'),
        'keine Fremd-Hosts',
    ).toEqual([]);
});

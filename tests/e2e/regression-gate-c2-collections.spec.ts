import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';
import {
    authFile,
    DATA,
    expect,
    measureLayout,
    settle,
    test,
} from './support/fixtures';
import { inertiaProps } from './support/helpers';
import { writeRaw } from './support/raw';

/**
 * Gate C2 (Schritt 2, ADR-003 Rev. 1) – Dashboard, Sammlungen und Zutaten im Stil
 * „Natur & organisch“.
 *
 * - Home: Begruessung, Zaehler und schnelle Wege nur aus vorhandenen Props
 * - Sammlungen: Kacheln verlinkt (Tastatur), Leerzustaende, Anlegen mit Fehler am Feld,
 *   Loeschen nur nach Rueckfrage im Dialog (Abbrechen/Escape senden nichts)
 * - Zutatensuche: Filterblatt (aria-expanded, Escape, Fokus-Rueckgabe), Vorschlags-Chips,
 *   alphabetische Gruppen, Leerzustand
 * - Meine Zutaten: ehrlicher Leerzustand mit Wegen
 * - 320 px ohne horizontales Scrollen, axe = 0 (alle Regeln) bei 390 und 1440 px
 *
 * Datenveraenderungen: keine. Das Anlegen wird nur mit leerem Namen abgeschickt (Server
 * lehnt ab); Loeschen wird nur geoeffnet und abgebrochen.
 */

const MOBILE_390 = {
    viewport: { width: 390, height: 844 },
    isMobile: true,
    hasTouch: true,
} as const;

type Role = 'guest' | 'user' | 'host';

const PAGES: { role: Role; slug: string; path: string }[] = [
    { role: 'user', slug: 'home', path: '/home' },
    { role: 'user', slug: 'sammlungen', path: '/sammlungen' },
    { role: 'host', slug: 'sammlungen-leer-host', path: '/sammlungen' },
    { role: 'user', slug: 'sammlung-erstellen', path: '/sammlungen/erstellen' },
    {
        role: 'user',
        slug: 'sammlung-gefuellt',
        path: `/sammlung/${DATA.collections.filled.id}`,
    },
    {
        role: 'user',
        slug: 'sammlung-leer',
        path: `/sammlung/${DATA.collections.empty.id}`,
    },
    { role: 'guest', slug: 'zutatensuche', path: '/zutatensuche' },
    { role: 'user', slug: 'meine-zutaten', path: '/meineZutaten' },
];

const useRole = (role: Role) => {
    if (role !== 'guest') test.use({ storageState: authFile(role) });
};

function logRequests(page: Page, pattern: RegExp) {
    const log: { method: string; url: string }[] = [];
    page.on('request', (r) => {
        if (pattern.test(new URL(r.url()).pathname))
            log.push({ method: r.method(), url: r.url() });
    });
    return log;
}

// ---------------------------------------------------------------------------------------------
test.describe('Gate C2 – Home (Nutzerin)', () => {
    useRole('user');

    test('Begruessung, Zaehler und schnelle Wege aus den Props; Links fuehren an die richtigen Ziele', async ({
        page,
        diag,
    }) => {
        await page.goto('/home');
        await settle(page);
        const props = await inertiaProps<{
            stats: { totalRecipes: number; totalCollections: number };
            latestRecipes: { id: number; name: string }[];
            auth: {
                user: { name: string };
                collections: { id: number; name: string }[];
            };
        }>(page);

        const h1 = page.getByRole('heading', { level: 1 });
        await expect(h1).toHaveCount(1);
        await expect(h1).toContainText(
            `Hallo, ${props.auth.user.name.split(/\s+/)[0]}`,
        );

        // Zaehler = Props (keine erfundenen Werte)
        const overview = page.getByRole('main').locator('dl', {
            has: page.locator('dd'),
        });
        const dds = await overview.first().locator('dd').allTextContents();
        expect(dds.map((d) => d.trim())).toEqual([
            String(props.stats.totalRecipes),
            String(props.stats.totalCollections),
        ]);

        // schnelle Wege
        const paths = page.getByRole('region', { name: 'Wohin heute?' });
        const expectHref = async (name: string, path: string) => {
            const link = paths.getByRole('link', { name, exact: true });
            await expect(link).toHaveCount(1);
            expect(new URL((await link.getAttribute('href'))!).pathname).toBe(
                path,
            );
        };
        await expectHref('Rezepte', '/rezepte');
        await expectHref('Sammlungen', '/sammlungen');
        await expectHref('Zutatensuche', '/zutatensuche');
        await expectHref('Meine Zutaten', '/meineZutaten');

        // eigene Sammlungen als Links (aus auth.collections)
        const own = paths.getByRole('list', { name: 'Deine Sammlungen' });
        for (const c of props.auth.collections.slice(0, 4)) {
            const link = own.getByRole('link', { name: c.name, exact: true });
            expect(new URL((await link.getAttribute('href'))!).pathname).toBe(
                `/sammlung/${c.id}`,
            );
        }

        // zuletzt hinzugefuegt: genau die Rezepte aus den Props
        const latest = page.getByRole('list', { name: /Frisch aus dem/ });
        await expect(latest.locator(':scope > li')).toHaveCount(
            props.latestRecipes.length,
        );
        for (const r of props.latestRecipes) {
            const link = latest.getByRole('link', {
                name: r.name,
                exact: true,
            });
            expect(new URL((await link.getAttribute('href'))!).pathname).toBe(
                `/rezept/${r.id}`,
            );
        }

        // ganze Kachel klickbar: Klick in die Kachel-Mitte navigiert
        const tile = paths.getByRole('listitem').first();
        const box = (await tile.boundingBox())!;
        await Promise.all([
            page.waitForURL('**/rezepte'),
            page.mouse.click(box.x + box.width * 0.7, box.y + box.height * 0.3),
        ]);
        expect([...diag.pageErrors, ...diag.consoleErrors]).toEqual([]);
    });
});

// ---------------------------------------------------------------------------------------------
test.describe('Gate C2 – Sammlungen (Nutzerin)', () => {
    useRole('user');

    test('Uebersicht: eine Kachel je Sammlung, Titel-Link per Tastatur, Zaehler als Text', async ({
        page,
    }) => {
        await page.goto('/sammlungen');
        await settle(page);
        const props = await inertiaProps<{
            collections: { id: number; name: string; recipeCount: number }[];
        }>(page);
        expect(props.collections.length).toBeGreaterThanOrEqual(2);

        const list = page.getByRole('region', { name: 'Sammlungen' });
        const cards = list.getByRole('article');
        await expect(cards).toHaveCount(props.collections.length);
        for (const c of props.collections) {
            const card = cards.filter({
                has: page.getByRole('link', { name: c.name, exact: true }),
            });
            await expect(card).toHaveCount(1);
            await expect(card).toContainText(
                c.recipeCount === 1 ? '1 Rezept' : `${c.recipeCount} Rezepte`,
            );
        }

        // Anlegen-Wege
        for (const name of ['Neue Sammlung', 'Weitere Sammlung anlegen']) {
            const link = page.getByRole('link', { name, exact: true });
            expect(new URL((await link.getAttribute('href'))!).pathname).toBe(
                '/sammlungen/erstellen',
            );
        }

        // Tastatur: Fokus auf den Titel-Link, Enter oeffnet die Sammlung
        const filled = list.getByRole('link', {
            name: DATA.collections.filled.name,
            exact: true,
        });
        await filled.focus();
        await expect(filled).toBeFocused();
        await Promise.all([
            page.waitForURL(`**/sammlung/${DATA.collections.filled.id}`),
            page.keyboard.press('Enter'),
        ]);
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(
            DATA.collections.filled.name,
        );
    });

    test('Detail: Eckdaten und Rezeptkarten aus den Props; Loeschen nur nach Rueckfrage, Abbrechen/Escape senden nichts', async ({
        page,
        diag,
    }) => {
        const requests = logRequests(page, /^\/collections\//);
        await page.goto(`/sammlung/${DATA.collections.filled.id}`);
        await settle(page);
        const props = await inertiaProps<{
            recipes: { id: number; name: string }[];
        }>(page);
        expect(props.recipes.length).toBe(3);

        const facts = page.locator('dl[aria-label="Eckdaten"]');
        await expect(facts).toContainText(String(props.recipes.length));

        const items = page.locator('.collection-recipe-item');
        await expect(items).toHaveCount(props.recipes.length);
        for (const r of props.recipes) {
            const link = page.getByRole('link', { name: r.name, exact: true });
            expect(new URL((await link.getAttribute('href'))!).pathname).toBe(
                `/rezept/${r.id}`,
            );
        }

        const trigger = page.getByRole('button', { name: 'Sammlung löschen' });
        await expect(trigger).toHaveAttribute('aria-haspopup', 'dialog');

        // Abbrechen
        await trigger.click();
        const dialog = page.getByRole('dialog', { name: 'Sammlung löschen?' });
        await expect(dialog).toBeVisible();
        await expect(dialog).toContainText(DATA.collections.filled.name);
        await dialog.getByRole('button', { name: 'Abbrechen' }).click();
        await expect(dialog).toHaveCount(0);
        await expect(trigger).toBeFocused();

        // Escape
        await trigger.click();
        await expect(dialog).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(dialog).toHaveCount(0);
        await expect(trigger).toBeFocused();

        await page.waitForTimeout(300);
        expect(requests.filter((r) => r.method === 'DELETE')).toEqual([]);
        await page.reload();
        await settle(page);
        await expect(items).toHaveCount(3);
        expect([...diag.pageErrors, ...diag.consoleErrors]).toEqual([]);
    });

    test('Leere Sammlung: Leerzustand mit Weg zu den Rezepten, kein Entfernen-Modus', async ({
        page,
    }) => {
        await page.goto(`/sammlung/${DATA.collections.empty.id}`);
        await settle(page);
        await expect(
            page.getByRole('heading', { name: 'Diese Sammlung ist noch leer' }),
        ).toBeVisible();
        const link = page
            .getByRole('main')
            .getByRole('link', { name: 'Rezepte entdecken' });
        expect(new URL((await link.getAttribute('href'))!).pathname).toBe(
            '/rezepte',
        );
        await expect(
            page.getByRole('button', { name: 'Rezepte entfernen' }),
        ).toHaveCount(0);
        // Loeschen bleibt moeglich
        await expect(
            page.getByRole('button', { name: 'Sammlung löschen' }),
        ).toBeVisible();
    });

    test('Anlegen: Pflichtfeld markiert, leerer Name -> Fehler am Feld mit Fokus, nichts angelegt', async ({
        page,
    }) => {
        await page.goto('/sammlungen/erstellen');
        await settle(page);
        const name = page.getByLabel('Name der Sammlung');
        await expect(name).toHaveAttribute('aria-required', 'true');
        await expect(page.getByLabel('Beschreibung (optional)')).toHaveCount(1);

        const [res] = await Promise.all([
            page.waitForResponse(
                (r) =>
                    r.request().method() === 'POST' &&
                    new URL(r.url()).pathname === '/collections',
            ),
            page.getByRole('button', { name: 'Sammlung erstellen' }).click(),
        ]);
        expect([302, 303]).toContain(res.status());
        await expect(name).toHaveAttribute('aria-invalid', 'true');
        await expect(page.locator('#collection-name-error')).toBeVisible();
        await expect(name).toBeFocused();
        expect(new URL(page.url()).pathname).toBe('/sammlungen/erstellen');

        // Abbrechen fuehrt zur Uebersicht; dort weiterhin nur die Seed-Sammlungen
        await Promise.all([
            page.waitForURL('**/sammlungen'),
            page.getByRole('link', { name: 'Abbrechen', exact: true }).click(),
        ]);
        // Props stehen nur im Dokument der ersten Antwort: neu laden
        await page.reload();
        await settle(page);
        const props = await inertiaProps<{ collections: unknown[] }>(page);
        expect(props.collections.length).toBe(2);
    });
});

test.describe('Gate C2 – Sammlungen (Host ohne Sammlung)', () => {
    useRole('host');

    test('Leerzustand mit Anlegen-Weg statt leerer Flaeche', async ({
        page,
    }) => {
        await page.goto('/sammlungen');
        await settle(page);
        const props = await inertiaProps<{ collections: unknown[] }>(page);
        test.skip(
            props.collections.length > 0,
            'Host hat inzwischen Sammlungen',
        );
        await expect(
            page.getByRole('heading', { name: 'Noch keine Sammlungen' }),
        ).toBeVisible();
        const link = page.getByRole('link', { name: 'Erste Sammlung anlegen' });
        expect(new URL((await link.getAttribute('href'))!).pathname).toBe(
            '/sammlungen/erstellen',
        );
        await expect(page.getByRole('main').getByRole('article')).toHaveCount(
            0,
        );
    });
});

// ---------------------------------------------------------------------------------------------
test.describe('Gate C2 – Zutatensuche (Gast)', () => {
    test('Filterblatt: aria-expanded, Escape und Abbrechen geben den Fokus zurueck; Vorschlag ergaenzt Begriffe', async ({
        page,
        diag,
    }) => {
        await page.goto('/zutatensuche');
        await settle(page);
        const toggle = page.getByRole('button', { name: /^Filter\b/ });
        await expect(toggle).toHaveAttribute('aria-expanded', 'false');
        const panel = page.getByRole('group', { name: 'Nach Zutaten filtern' });
        await expect(panel).toBeHidden();

        await toggle.click();
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        await expect(panel).toBeVisible();
        await expect(panel.getByLabel('Enthaltene Begriffe')).toHaveCount(1);
        await expect(panel.getByLabel('Ausschließen')).toHaveCount(1);

        // Escape schliesst, Fokus zurueck
        await panel.getByLabel('Enthaltene Begriffe').focus();
        await page.keyboard.press('Escape');
        await expect(panel).toBeHidden();
        await expect(toggle).toBeFocused();

        // Abbrechen schliesst, Fokus zurueck
        await toggle.click();
        await panel.getByRole('button', { name: 'Abbrechen' }).click();
        await expect(panel).toBeHidden();
        await expect(toggle).toBeFocused();

        // Vorschlag: ergaenzt die Begriffe, wendet an, Zaehler am Knopf
        await toggle.click();
        await panel
            .getByRole('button', {
                name: 'Tomaten zu den enthaltenen Begriffen hinzufügen',
            })
            .click();
        await expect(panel).toBeHidden();
        await expect(toggle).toBeFocused();
        await expect(toggle).toContainText('1');
        await toggle.click();
        await expect(panel.getByLabel('Enthaltene Begriffe')).toHaveValue(
            'Tomaten',
        );
        const names = await page
            .locator('[data-ingredient-name]')
            .allTextContents();
        for (const n of names) expect(n.toLowerCase()).toContain('tomaten');
        await expect(page.getByRole('main').getByRole('status')).toHaveText(
            `${names.length} Zutaten gefunden`,
        );
        expect([...diag.pageErrors, ...diag.consoleErrors]).toEqual([]);
    });

    test('Zutaten alphabetisch gruppiert; ohne Treffer Leerzustand', async ({
        page,
    }) => {
        await page.goto('/zutatensuche');
        await settle(page);
        const props = await inertiaProps<{
            ingredients: { id: number; name: string }[];
        }>(page);
        await expect(page.locator('[data-ingredient]')).toHaveCount(
            props.ingredients.length,
        );
        // jede Zutat steht in der Gruppe ihres Anfangsbuchstabens
        const groups = page.getByRole('list', { name: /^Zutaten mit [A-Z]$/ });
        expect(await groups.count()).toBeGreaterThan(1);
        for (const g of await groups.all()) {
            const label = (await g.getAttribute('aria-labelledby'))!;
            const letter = label.replace('ingredient-letter-', '');
            for (const n of await g
                .locator('[data-ingredient-name]')
                .allTextContents()) {
                expect(
                    n
                        .trim()
                        .normalize('NFD')
                        .replace(/\p{M}/gu, '')
                        .charAt(0)
                        .toUpperCase(),
                ).toBe(letter);
            }
        }

        await page.getByLabel('Zutat suchen').fill('xyzkeinezutat');
        await expect(
            page.getByRole('heading', { name: 'Keine Zutaten gefunden' }),
        ).toBeVisible();
        await expect(page.locator('[data-ingredient]')).toHaveCount(0);
        await expect(page.getByRole('main').getByRole('status')).toHaveText(
            '0 Zutaten gefunden',
        );
    });
});

test.describe('Gate C2 – Meine Zutaten (Nutzerin)', () => {
    useRole('user');

    test('ehrlicher Leerzustand mit Wegen zur Zutatensuche und zu den Rezepten', async ({
        page,
    }) => {
        await page.goto('/meineZutaten');
        await settle(page);
        await expect(
            page.getByRole('heading', { level: 1, name: 'Meine Zutaten' }),
        ).toBeVisible();
        await expect(
            page.getByText('Diese Funktion wird gerade ausgebaut.', {
                exact: false,
            }),
        ).toBeVisible();
        const main = page.getByRole('main');
        for (const [name, path] of [
            ['Zur Zutatensuche', '/zutatensuche'],
            ['Rezepte entdecken', '/rezepte'],
        ]) {
            const link = main.getByRole('link', { name, exact: true });
            expect(new URL((await link.getAttribute('href'))!).pathname).toBe(
                path,
            );
        }
        // ohne gespeicherte Zutaten keine Suche
        await expect(page.getByLabel('Zutat suchen')).toHaveCount(0);
    });
});

// ---------------------------------------------------------------------------------------------
test.describe('Gate C2 – kein horizontaler Ueberlauf bei 320 px', () => {
    test.use({
        viewport: { width: 320, height: 640 },
        isMobile: true,
        hasTouch: true,
    });
    for (const p of PAGES) {
        test.describe(p.slug, () => {
            useRole(p.role);
            test(`320 px ${p.slug}`, async ({ page }) => {
                await page.goto(p.path);
                await settle(page);
                if (p.slug === 'zutatensuche') {
                    // auch mit offenem Filterblatt
                    await page
                        .getByRole('button', { name: /^Filter\b/ })
                        .click();
                }
                const m = await measureLayout(page);
                writeRaw('regression', `gate-c2-320-${p.slug}`, m);
                expect(m.horizontalOverflow).toBe(false);
                expect(
                    m.overflowingElementCount,
                    m.overflowingElements.join(', '),
                ).toBe(0);
                expect(m.clippedH1).toBe(false);
                expect(m.h1Count).toBe(1);
            });
        });
    }
});

// ---------------------------------------------------------------------------------------------
// axe: alle Regeln = 0
// ---------------------------------------------------------------------------------------------
for (const vp of [
    { label: '1440', use: { viewport: { width: 1440, height: 900 } } },
    { label: '390', use: MOBILE_390 },
]) {
    test.describe(`Gate C2 – axe (${vp.label})`, () => {
        test.use(vp.use);
        for (const p of PAGES) {
            test.describe(p.slug, () => {
                useRole(p.role);
                test(`axe ${p.slug} ${vp.label}: keine Verstoesse`, async ({
                    page,
                }) => {
                    await page.goto(p.path);
                    await settle(page);
                    // Zustaende mit zusaetzlichen Bedienelementen mitpruefen
                    if (p.slug === 'zutatensuche')
                        await page
                            .getByRole('button', { name: /^Filter\b/ })
                            .click();
                    if (p.slug === 'sammlung-gefuellt') {
                        await page
                            .getByRole('button', { name: 'Rezepte entfernen' })
                            .click();
                        await page
                            .getByRole('button', {
                                name: /^Aus Sammlung entfernen: /,
                            })
                            .first()
                            .click();
                    }
                    const results = await new AxeBuilder({ page }).analyze();
                    const summary = Object.fromEntries(
                        results.violations.map((v) => [
                            `${v.id} (${v.impact ?? 'n/a'})`,
                            v.nodes.map((n) => n.target.join(' ')),
                        ]),
                    );
                    writeRaw(
                        'regression',
                        `gate-c2-axe-${p.slug}-${vp.label}`,
                        {
                            summary,
                        },
                    );
                    expect(summary).toEqual({});
                });
            });
        }
    });
}

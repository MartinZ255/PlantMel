import AxeBuilder from '@axe-core/playwright';
import type { Browser, Page } from '@playwright/test';
import { BASE_URL } from './support/env';
import { authFile, expect, settle, test } from './support/fixtures';
import { createRecipe, deleteRecipe } from './support/helpers';

/**
 * Gate D2 (F-23) – serverseitige Pagination der Rezeptliste (24 je Seite).
 *
 * Datenveränderung: legt im beforeAll 26 synthetische Wegwerf-Rezepte
 * („E2E-Seite NN“) als Host über POST /recipes an und löscht genau diese im
 * afterAll wieder (DELETE /rezept/{id}). Mit den 8 Seeder-Rezepten ergeben
 * sich 34 Rezepte = 2 Seiten; die Suche „E2E-Seite“ trifft 26 = 2 Seiten.
 * Der E2eSeeder bleibt unverändert, damit Baselines und andere Specs (die
 * nach dieser Datei laufen) dieselben Daten sehen.
 */

const SYNTHETIC = 26;
const SEEDED = 8;
const TOTAL = SEEDED + SYNTHETIC;
const PREFIX = 'E2E-Seite';

const createdIds: number[] = [];

test.use({ storageState: authFile('host') });

/** Host-Kontext ohne Fremd-Requests (nur zum Anlegen/Löschen der Wegwerf-Rezepte). */
async function hostPage(browser: Browser) {
    const context = await browser.newContext({
        baseURL: BASE_URL,
        storageState: authFile('host'),
    });
    await context.route(
        (url) => url.origin !== BASE_URL,
        (route) => route.abort('blockedbyclient'),
    );
    return { context, page: await context.newPage() };
}

test.beforeAll(async ({ browser }) => {
    const { context, page } = await hostPage(browser);
    for (let i = 1; i <= SYNTHETIC; i++) {
        createdIds.push(
            await createRecipe(page, {
                title: `${PREFIX} ${String(i).padStart(2, '0')}`,
                ingredients: [{ name: 'Haferflocken', amount: '50 g' }],
                steps: ['Alles verrühren.'],
            }),
        );
    }
    await context.close();
});

test.afterAll(async ({ browser }) => {
    const { context, page } = await hostPage(browser);
    for (const id of createdIds.splice(0)) {
        await deleteRecipe(page, id);
    }
    await context.close();
});

const cards = (page: Page) =>
    page.locator('ul[aria-labelledby="recipe-results-title"] > li');
const nav = (page: Page) => page.getByRole('navigation', { name: 'Seiten' });
const count = (page: Page) => page.locator('[data-result-count]');

/** Verstöße mit Fundstellen, damit ein Fehlschlag die Ursache zeigt. */
const axeSummary = (
    violations: { id: string; nodes: { target: unknown[] }[] }[],
) =>
    violations.map(
        (v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`,
    );

/** Link per Tastatur auslösen: Tab bis zum Link, dann Enter. */
async function activateByKeyboard(page: Page, name: string) {
    const link = nav(page).getByRole('link', { name, exact: true });
    // Start am Listenanfang (Überschrift, tabindex=-1), dann per Tab bis zum
    // Link (höchstens 400 Schritte) – kein .click()
    await page.locator('#recipe-results-title').focus();
    for (let i = 0; i < 400; i++) {
        await page.keyboard.press('Tab');
        if (await link.evaluate((el) => el === document.activeElement)) break;
    }
    await expect(link).toBeFocused();
    await page.keyboard.press('Enter');
}

test.describe('Rezeptliste: Pagination (F-23, Gate D2)', () => {
    test('Seite 1: 24 Karten, Gesamtzahl, zugängliche Navigation', async ({
        page,
    }) => {
        await page.goto('/rezepte');
        await settle(page);

        await expect(cards(page)).toHaveCount(24);
        await expect(count(page)).toHaveText(
            `${TOTAL} Ergebnisse · Seite 1 von 2`,
        );
        await expect(nav(page)).toBeVisible();
        await expect(
            nav(page).getByRole('link', { name: 'Seite 1', exact: true }),
        ).toHaveAttribute('aria-current', 'page');
        await expect(
            nav(page).getByRole('link', { name: 'Seite 2', exact: true }),
        ).not.toHaveAttribute('aria-current', /.*/);
        // „Zurück“ ist auf Seite 1 kein Link
        await expect(
            nav(page).getByRole('link', { name: 'Zurück' }),
        ).toHaveCount(0);
    });

    test('Seitenwechsel per Tastatur: Fokus und Sicht am Listenanfang', async ({
        page,
    }) => {
        await page.goto('/rezepte');
        await settle(page);

        await activateByKeyboard(page, 'Weiter');
        await page.waitForURL(/[?&]page=2(&|$)/);
        await expect(cards(page)).toHaveCount(TOTAL - 24);
        await expect(count(page)).toHaveText(
            `${TOTAL} Ergebnisse · Seite 2 von 2`,
        );
        await expect(
            nav(page).getByRole('link', { name: 'Seite 2', exact: true }),
        ).toHaveAttribute('aria-current', 'page');
        await expect(page.locator('#recipe-results-title')).toBeFocused();
        await expect(page.locator('#recipe-results-title')).toBeInViewport();
        await expect(
            nav(page).getByRole('link', { name: 'Weiter' }),
        ).toHaveCount(0);

        // zurück per Tastatur über die Seitenzahl
        await activateByKeyboard(page, 'Seite 1');
        await page.waitForURL(/[?&]page=1(&|$)/);
        await expect(cards(page)).toHaveCount(24);
        await expect(page.locator('#recipe-results-title')).toBeFocused();
    });

    test('Filter bleiben beim Blättern erhalten', async ({ page }) => {
        await page.goto(`/rezepte?search=${encodeURIComponent(PREFIX)}`);
        await settle(page);

        await expect(count(page)).toHaveText(
            `${SYNTHETIC} Ergebnisse · Seite 1 von 2`,
        );
        await activateByKeyboard(page, 'Weiter');
        await page.waitForURL(/[?&]page=2(&|$)/);

        const url = new URL(page.url());
        expect(url.searchParams.get('search')).toBe(PREFIX);
        expect(url.searchParams.get('page')).toBe('2');
        await expect(cards(page)).toHaveCount(SYNTHETIC - 24);
        await expect(cards(page).first()).toContainText(`${PREFIX} 25`);
        await expect(page.locator('#recipe-search')).toHaveValue(PREFIX);
        await expect(count(page)).toHaveText(
            `${SYNTHETIC} Ergebnisse · Seite 2 von 2`,
        );
    });

    test('Filteränderung setzt auf Seite 1 zurück', async ({ page }) => {
        await page.goto('/rezepte?page=2');
        await settle(page);
        await expect(count(page)).toContainText('Seite 2 von 2');

        await page.locator('#recipe-search').fill(PREFIX);
        await page.waitForURL(
            (u) =>
                u.searchParams.get('search') === PREFIX &&
                !u.searchParams.has('page'),
        );
        await expect(count(page)).toHaveText(
            `${SYNTHETIC} Ergebnisse · Seite 1 von 2`,
        );
        await expect(cards(page)).toHaveCount(24);
    });

    test('Seite hinter der letzten: Umleitung auf die letzte Seite', async ({
        page,
    }) => {
        const response = await page.goto(
            `/rezepte?search=${encodeURIComponent(PREFIX)}&page=99`,
        );
        expect(response?.status()).toBe(200);
        const url = new URL(page.url());
        expect(url.searchParams.get('page')).toBe('2');
        expect(url.searchParams.get('search')).toBe(PREFIX);
        await expect(count(page)).toHaveText(
            `${SYNTHETIC} Ergebnisse · Seite 2 von 2`,
        );
    });

    test('axe: 0 Verstöße auf Seite 2 (1440 px)', async ({ page }) => {
        await page.goto('/rezepte?page=2');
        await settle(page);
        const results = await new AxeBuilder({ page }).analyze();
        expect(axeSummary(results.violations)).toEqual([]);
    });

    test.describe('320 px', () => {
        test.use({
            viewport: { width: 320, height: 640 },
            isMobile: true,
            hasTouch: true,
        });

        test('nutzbar ohne seitliches Scrollen, axe 0', async ({ page }) => {
            await page.goto('/rezepte?page=2');
            await settle(page);

            await nav(page).scrollIntoViewIfNeeded();
            await expect(nav(page)).toBeVisible();
            await expect(nav(page)).toContainText('Seite 2 von 2');
            const back = nav(page).getByRole('link', { name: 'Zurück' });
            await expect(back).toBeVisible();
            const box = await back.boundingBox();
            expect(box?.height ?? 0).toBeGreaterThanOrEqual(44);
            // eine Zeile: „Weiter“ (hier ausgegraut) bricht nicht um
            const firstBox = await nav(page)
                .locator('li')
                .first()
                .boundingBox();
            const next = nav(page).locator('li').last();
            const nextBox = await next.boundingBox();
            expect(
                Math.abs((nextBox?.y ?? 0) - (firstBox?.y ?? 0)),
            ).toBeLessThanOrEqual(4);

            const overflow = await page.evaluate(
                () =>
                    document.documentElement.scrollWidth -
                    document.documentElement.clientWidth,
            );
            expect(overflow).toBeLessThanOrEqual(0);

            const navBox = await nav(page).boundingBox();
            expect((navBox?.x ?? 0) + (navBox?.width ?? 0)).toBeLessThanOrEqual(
                320,
            );

            const results = await new AxeBuilder({ page }).analyze();
            expect(axeSummary(results.violations)).toEqual([]);

            await back.tap();
            await page.waitForURL(/[?&]page=1(&|$)/);
            await expect(cards(page)).toHaveCount(24);
        });
    });
});

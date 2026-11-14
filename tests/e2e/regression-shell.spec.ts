import type { Browser, Locator, Page } from '@playwright/test';
import { BASE_URL } from './support/env';
import { authFile, DATA, expect, settle, test } from './support/fixtures';
import { freshLogin } from './support/helpers';
import { writeRaw } from './support/raw';

/**
 * Regressionstests Slice 3 – Navigation, App-Shell und Seitenrahmen.
 *
 * Herkunft: tests/e2e/known-defects.spec.ts (dort mit `test.fail()`): F-05, F-17, F-21a/b,
 * F-29, F-42a/b. Die frueheren F-20-Tests (Darstellung/2FA wiederherstellen) sind durch die
 * Entscheidung E4 gegenstandslos und hier durch "404 statt Seite" ersetzt.
 * Neu: F-35/F-69 (Auth-Seiten, Drawer-Versatz), F-68 (Fehlerseiten), Skip-Link, Flash.
 *
 * Datenveraenderungen: nur user2 (eigene Session). Angelegte Sammlungen werden im selben
 * Test wieder geloescht; Profil und Passwort werden mit unveraenderten Werten gespeichert.
 */

const MOBILE_390 = {
    viewport: { width: 390, height: 844 },
    isMobile: true,
    hasTouch: true,
} as const;

const sidebar = (page: Page) => page.locator('#app-sidebar');
const menuButton = (page: Page) => page.locator('.app-header__icon--menu');

async function isSidebarClosed(page: Page) {
    return sidebar(page).evaluate((el) =>
        el.classList.contains('sidebar--closed'),
    );
}

/** elementFromPoint an 10 %, 50 % und 90 % der Breite: trifft jeweils das Element selbst? */
async function samplePoints(locator: Locator) {
    return locator.evaluate((el) => {
        const r = el.getBoundingClientRect();
        return [0.1, 0.5, 0.9].map((f) => {
            const x = Math.round(r.left + r.width * f);
            const y = Math.round(r.top + r.height / 2);
            const h = document.elementFromPoint(x, y);
            return {
                x,
                y,
                hit: h
                    ? `${h.tagName.toLowerCase()}.${String(h.className).trim().split(/\s+/).join('.')}`
                    : 'null',
                hitsTarget: !!h && (h === el || el.contains(h)),
            };
        });
    });
}

/** Drueckt n-mal Tab und meldet, ob der Fokus je in `selector` lag. */
async function tabVisits(page: Page, selector: string, presses: number) {
    const visits: string[] = [];
    for (let i = 0; i < presses; i++) {
        await page.keyboard.press('Tab');
        const inside = await page.evaluate((sel) => {
            const a = document.activeElement;
            return a && a.closest(sel)
                ? (a.textContent ?? '').trim().slice(0, 40)
                : null;
        }, selector);
        if (inside !== null) visits.push(inside);
    }
    return visits;
}

async function newContext(
    browser: Browser,
    role: 'user' | 'user2',
    viewport = { width: 1440, height: 900 },
) {
    const context = await browser.newContext({
        baseURL: BASE_URL,
        storageState: authFile(role),
        viewport,
    });
    await context.route(
        (url) => url.origin !== BASE_URL,
        (route) => route.abort('blockedbyclient'),
    );
    return context;
}

const flashSuccess = (page: Page) =>
    page.locator('.flash-message--success[role="status"]');

// ---------------------------------------------------------------------------------------------
test.describe('Gast', () => {
    test('F-21a Gast klickt Logo und landet auf der Landing-Seite', async ({
        page,
    }) => {
        await page.goto('/rezepte');
        await settle(page);
        await Promise.all([
            page.waitForURL((u) => u.pathname === '/'),
            page.locator('.app-header__logo').click(),
        ]);
        expect(new URL(page.url()).pathname).toBe('/');
    });

    test('F-21 Gast: "Start" fuehrt auf "/" und ist dort als aktuelle Seite markiert', async ({
        page,
    }) => {
        await page.goto('/');
        await settle(page);
        const start = sidebar(page).getByRole('link', { name: 'Start' });
        await expect(start).toHaveAttribute('aria-current', 'page');
        await expect(start).toHaveClass(/sidebar-link--active/);
        expect(new URL((await start.getAttribute('href'))!).pathname).toBe('/');
    });

    test('F-21 Landing: "Rezepte entdecken" fuehrt Gaeste zu /rezepte', async ({
        page,
    }) => {
        await page.goto('/');
        await settle(page);
        const cta = page
            .locator('main')
            .getByRole('link', { name: 'Rezepte entdecken' });
        await expect(cta).toBeVisible();
        await Promise.all([page.waitForURL('**/rezepte'), cta.click()]);
        expect(new URL(page.url()).pathname).toBe('/rezepte');
    });

    test('E8/F-55 Landing bewirbt keine Bewertungen/Notizen', async ({
        page,
    }) => {
        await page.goto('/');
        await settle(page);
        await expect(page.locator('main')).not.toContainText(
            'Bewertungen und Notizen',
        );
        await expect(page.locator('main li')).toHaveCount(3);
    });

    test('F-21 Gast: Sammlungen und Meine Zutaten als "Anmeldung erforderlich" erkennbar', async ({
        page,
    }) => {
        await page.goto('/rezepte');
        await settle(page);
        for (const name of ['Sammlungen', 'Meine Zutaten']) {
            const link = sidebar(page).getByRole('link', {
                name: `${name} (Anmeldung erforderlich)`,
            });
            await expect(link).toBeVisible();
        }
        await expect(sidebar(page)).toContainText(
            'Nach dem Anmelden verfügbar',
        );
    });

    test('F-21 Untermenue: aktive Seite traegt aria-current und kein type an Links', async ({
        page,
    }) => {
        await page.goto('/zutatensuche');
        await settle(page);
        const toggle = page.locator('.sidebar-link--submenu-toggle');
        await expect(toggle).toHaveAttribute('aria-expanded', 'false');
        await expect(toggle).toHaveAttribute(
            'aria-controls',
            'sidebar-search-submenu',
        );
        await expect(toggle).toHaveClass(/sidebar-link--active/);
        await toggle.click();
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        await expect(
            page.locator('#sidebar-search-submenu a[aria-current="page"]'),
        ).toHaveText('Zutatensuche');
        expect(
            await page.locator('.app-shell a[type]').count(),
            'kein type-Attribut an <a>',
        ).toBe(0);
    });

    test('F-42 html[lang] ist "de"', async ({ page }) => {
        await page.goto('/');
        expect(await page.locator('html').getAttribute('lang')).toBe('de');
    });

    test('F-42 Skip-Link ist das erste fokussierbare Element und springt zum Inhalt', async ({
        page,
    }) => {
        await page.goto('/zutatensuche');
        await settle(page);
        await page.keyboard.press('Tab');
        const skip = page.locator('.skip-link');
        await expect(skip).toBeFocused();
        await expect(skip).toHaveText('Zum Inhalt springen');
        // bei Fokus sichtbar im Viewport
        const box = await skip.boundingBox();
        expect(box && box.y >= 0, 'Skip-Link im Viewport').toBe(true);
        await page.keyboard.press('Enter');
        await expect(page.locator('#main-content')).toBeFocused();
        // Landmarken: genau ein main, alle nav mit Namen
        await expect(page.locator('main')).toHaveCount(1);
        const unnamedNav = await page
            .locator('nav:not([aria-label]):not([aria-labelledby])')
            .count();
        expect(unnamedNav, 'nav ohne aria-label').toBe(0);
        // Titel im App-Header ist keine Ueberschrift
        expect(
            await page
                .locator('.app-header__title')
                .evaluate((el) => el.tagName.toLowerCase()),
        ).toBe('div');
    });

    test('F-42 fehlerhafter Login zeigt deutsche Validierungsmeldung', async ({
        page,
    }) => {
        await page.goto('/login');
        await settle(page);
        // nicht existierendes Konto: beruehrt das Login-Limit der Testkonten nicht
        await page.locator('#email').fill('niemand@example.test');
        await page.locator('#password').fill('falsches-passwort');
        await page.getByRole('button', { name: 'Einloggen' }).click();
        const error = page.locator('.auth-form .error').first();
        await expect(error).toBeVisible();
        await expect(error).toHaveText(
            'Diese Zugangsdaten stimmen nicht mit unseren Daten überein.',
        );
    });

    test('F-35 Auth-Seiten: Logo fuehrt zur Startseite, Eingaben mobil mindestens 16 px', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        const rows: Record<string, unknown>[] = [];
        for (const path of [
            '/login',
            '/register',
            '/forgot-password',
            `/reset-password/e2e-dummy-token?email=${encodeURIComponent(DATA.users.user.email)}`,
        ]) {
            await page.goto(path);
            await settle(page);
            const brand = page.locator('a.auth-brand');
            await expect(brand, `${path}: Logo`).toBeVisible();
            expect(new URL((await brand.getAttribute('href'))!).pathname).toBe(
                '/',
            );
            const sizes = await page
                .locator(
                    'input[type="email"], input[type="password"], input[type="text"]',
                )
                .evaluateAll((els) =>
                    els.map((e) => parseFloat(getComputedStyle(e).fontSize)),
                );
            rows.push({ path, sizes });
            expect(sizes.length).toBeGreaterThan(0);
            for (const s of sizes)
                expect(s, `${path}: Schriftgroesse`).toBeGreaterThanOrEqual(16);
            expect(await page.title()).not.toContain('favRezepte');
        }
        writeRaw('regression', 'F-35-auth', { rows });
    });

    test('F-35 Login: Statusmeldung steht ueber dem Formular', async ({
        page,
    }) => {
        // Die Statusmeldung kommt nach "Passwort zuruecksetzen"; hier per Inertia-Prop simuliert
        await page.route('**/login', async (route) => {
            if (route.request().method() !== 'GET') return route.continue();
            const res = await route.fetch();
            const html = (await res.text()).replace(
                /(&quot;status&quot;:)null/,
                '$1&quot;E2E Statusmeldung&quot;',
            );
            await route.fulfill({ response: res, body: html });
        });
        await page.goto('/login');
        await settle(page);
        const status = page.locator('.status-message');
        await expect(status).toHaveText('E2E Statusmeldung');
        const s = (await status.boundingBox())!;
        const f = (await page.locator('.auth-form').boundingBox())!;
        expect(
            s.y + s.height,
            'Meldung endet oberhalb des Formulars',
        ).toBeLessThanOrEqual(f.y);
    });

    test('F-35 Login: abgelaufene Sitzung (419) zeigt Fehlermeldung', async ({
        page,
    }) => {
        await page.goto('/login');
        await settle(page);
        // Session- und XSRF-Cookie entfernen => CSRF-Pruefung schlaegt fehl (419)
        await page.context().clearCookies();
        await page.locator('#email').fill('niemand@example.test');
        await page.locator('#password').fill('egal');
        await page.getByRole('button', { name: 'Einloggen' }).click();
        const alert = page.locator('.flash-message--error[role="alert"]');
        await expect(alert).toHaveText(
            'Die Sitzung ist abgelaufen. Bitte versuche es erneut.',
        );
    });

    test('F-68 unbekannte Seite zeigt die deutsche 404-Fehlerseite', async ({
        page,
    }) => {
        const res = await page.goto('/gibt-es-nicht');
        await settle(page);
        expect(res?.status()).toBe(404);
        await expect(page.locator('h1')).toHaveText('Seite nicht gefunden');
        await expect(page.locator('main')).toContainText(
            'Die angeforderte Seite wurde nicht gefunden.',
        );
        expect(await page.title()).toBe('Seite nicht gefunden – PlantMel');
        const home = page.getByRole('link', { name: 'Zur Startseite' });
        expect(new URL((await home.getAttribute('href'))!).pathname).toBe('/');
        await expect(
            page.locator('main').getByRole('link', { name: 'Anmelden' }),
        ).toBeVisible();
    });

    test.describe('mobil 390', () => {
        test.use(MOBILE_390);

        test('F-05 Such-Untermenue: Rezeptsuche/Zutatensuche sichtbar und klickbar', async ({
            page,
        }) => {
            await page.goto('/zutatensuche');
            await settle(page);
            const submenu = page.locator('#sidebar-search-submenu');
            await menuButton(page).click();
            await expect(sidebar(page)).not.toHaveClass(/sidebar--closed/);
            await expect(submenu).toBeHidden();
            await page.locator('.sidebar-link--submenu-toggle').click();
            await page.waitForTimeout(300);
            const results: unknown[] = [];
            for (const name of ['Rezeptsuche', 'Zutatensuche']) {
                const link = page
                    .locator('.sidebar-submenu__item')
                    .filter({ hasText: name });
                await expect(link).toBeVisible();
                const samples = await samplePoints(link);
                // liegt innerhalb des Drawers
                const inDrawer = await link.evaluate(
                    (el) => !!el.closest('#app-sidebar'),
                );
                results.push({ name, samples, inDrawer });
                expect(inDrawer, `${name} im Drawer`).toBe(true);
                expect(
                    samples.every((s) => s.hitsTarget),
                    `${name} erhaelt an allen Messpunkten den Klick: ${JSON.stringify(samples)}`,
                ).toBe(true);
            }
            writeRaw('regression', 'F-05', { results });
            await Promise.all([
                page.waitForURL('**/rezepte'),
                page
                    .locator('.sidebar-submenu__item')
                    .filter({ hasText: 'Rezeptsuche' })
                    .click(),
            ]);
            await settle(page);
            // F-29: Drawer nach der Navigation geschlossen
            expect(await isSidebarClosed(page)).toBe(true);
        });

        test('F-05 geschlossenes Untermenue ist nicht per Tab erreichbar', async ({
            page,
        }) => {
            await page.goto('/zutatensuche');
            await settle(page);
            await menuButton(page).click();
            await page.locator('.sidebar-link--submenu-toggle').focus();
            const visits = await tabVisits(page, '.sidebar-submenu', 3);
            expect(visits, 'Fokus im geschlossenen Untermenue').toEqual([]);
            await page.keyboard.press('Shift+Tab');
            await page.keyboard.press('Shift+Tab');
            await page.keyboard.press('Shift+Tab');
            await page.locator('.sidebar-link--submenu-toggle').focus();
            await page.keyboard.press('Enter');
            await page.keyboard.press('Tab');
            await expect(
                page.locator('.sidebar-submenu__item').first(),
            ).toBeFocused();
        });

        test('F-29 Backdrop sichtbar, Tippen neben den Drawer schliesst ihn', async ({
            page,
        }) => {
            await page.goto('/rezepte');
            await settle(page);
            await expect(menuButton(page)).toHaveAttribute(
                'aria-expanded',
                'false',
            );
            await expect(menuButton(page)).toHaveAttribute(
                'aria-label',
                'Navigation',
            );
            await menuButton(page).click();
            await expect(menuButton(page)).toHaveAttribute(
                'aria-expanded',
                'true',
            );
            await page.waitForTimeout(300);
            const backdrop = page.locator('.sidebar-backdrop');
            expect(
                await backdrop.evaluate((el) => getComputedStyle(el).display),
            ).not.toBe('none');
            // Hintergrund scrollt nicht
            expect(
                await page.evaluate(
                    () => getComputedStyle(document.body).overflowY,
                ),
            ).toBe('hidden');
            const hit = await page.evaluate(() => {
                const e = document.elementFromPoint(375, 500);
                return e?.className ?? 'null';
            });
            expect(hit).toContain('sidebar-backdrop');
            await page.mouse.click(375, 500);
            await expect(sidebar(page)).toHaveClass(/sidebar--closed/);
            await expect(backdrop).toHaveCount(0);
            await expect(menuButton(page)).toHaveAttribute(
                'aria-expanded',
                'false',
            );
            expect(
                await page.evaluate(
                    () => getComputedStyle(document.body).overflowY,
                ),
            ).not.toBe('hidden');
        });

        test('F-29 Escape schliesst den Drawer und gibt den Fokus an den Hamburger', async ({
            page,
        }) => {
            await page.goto('/rezepte');
            await settle(page);
            await menuButton(page).click();
            await expect(sidebar(page)).not.toHaveClass(/sidebar--closed/);
            await sidebar(page).getByRole('link', { name: 'Start' }).focus();
            await page.keyboard.press('Escape');
            await expect(sidebar(page)).toHaveClass(/sidebar--closed/);
            await expect(menuButton(page)).toBeFocused();
        });

        test('F-29/F-40 geschlossener Drawer erhaelt keinen Tab-Fokus', async ({
            page,
        }) => {
            await page.goto('/rezepte');
            await settle(page);
            expect(await isSidebarClosed(page)).toBe(true);
            const visits = await tabVisits(page, '#app-sidebar', 12);
            writeRaw('regression', 'F-29-tab-mobil', { visits });
            expect(visits).toEqual([]);
        });

        test('F-69 Drawer schliesst buendig an den Header an (kein Versatz)', async ({
            page,
        }) => {
            for (const path of ['/rezepte', '/']) {
                await page.goto(path);
                await settle(page);
                await menuButton(page).click();
                await page.waitForTimeout(300);
                const header = (await page
                    .locator('.app-header')
                    .boundingBox())!;
                const drawer = (await sidebar(page).boundingBox())!;
                writeRaw('regression', `F-69-${path}`, { header, drawer });
                expect(
                    Math.abs(drawer.y - (header.y + header.height)),
                    `${path}: Drawer-Oberkante = Header-Unterkante`,
                ).toBeLessThanOrEqual(1);
                expect(drawer.y + drawer.height).toBeLessThanOrEqual(844 + 1);
            }
        });
    });
});

// ---------------------------------------------------------------------------------------------
test.describe('Nutzer', () => {
    test.use({ storageState: authFile('user') });

    test('F-42 html[lang] ist "de" (angemeldet)', async ({ page }) => {
        await page.goto('/home');
        expect(await page.locator('html').getAttribute('lang')).toBe('de');
    });

    test('F-42 jede Seite hat genau ein h1 und einen eigenen Titel mit "PlantMel"', async ({
        page,
        browser,
    }) => {
        // Seiten dieses Pakets (Rezeptseiten pruefen die Tests des Rezept-Pakets)
        const userPaths = [
            '/home',
            '/sammlungen',
            '/sammlungen/erstellen',
            `/sammlung/${DATA.collections.filled.id}`,
            `/sammlung/${DATA.collections.empty.id}`,
            '/meineZutaten',
            '/settings',
            '/settings/profile',
            '/settings/password',
            '/user/confirm-password',
            '/settings/appearance', // 404-Fehlerseite
            '/rezepte/erstellen', // 403-Fehlerseite (kein Host)
        ];
        const guestPaths = [
            '/',
            '/zutatensuche',
            '/login',
            '/register',
            '/forgot-password',
            `/reset-password/e2e-dummy-token?email=${encodeURIComponent(DATA.users.user.email)}`,
        ];
        const rows: { path: string; title: string; h1: number }[] = [];
        const collect = async (p: Page, paths: string[]) => {
            for (const path of paths) {
                await p.goto(path);
                await settle(p);
                rows.push({
                    path,
                    title: await p.title(),
                    h1: await p.locator('h1').count(),
                });
            }
        };
        await collect(page, userPaths);
        // ausdruecklich ohne Session (sonst erbt der Kontext den storageState aus test.use)
        const guest = await browser.newContext({
            baseURL: BASE_URL,
            storageState: { cookies: [], origins: [] },
        });
        await guest.route(
            (url) => url.origin !== BASE_URL,
            (route) => route.abort('blockedbyclient'),
        );
        await collect(await guest.newPage(), guestPaths);
        await guest.close();
        writeRaw('regression', 'F-42b', { rows });
        expect(
            rows.filter((r) => r.h1 !== 1),
            'Seiten ohne genau ein h1',
        ).toEqual([]);
        expect(
            rows.filter(
                (r) =>
                    !r.title.includes('PlantMel') ||
                    r.title.includes('Laravel'),
            ),
            'Titel ohne Marke',
        ).toEqual([]);
        const titles = rows.map((r) => r.title);
        expect(
            new Set(titles).size,
            `Titel eindeutig: ${titles.join(' | ')}`,
        ).toBe(titles.length);
    });

    test('F-21 angemeldet: Logo und Start fuehren auf /home, aktive Sammlung markiert', async ({
        page,
    }) => {
        await page.goto(`/sammlung/${DATA.collections.filled.id}`);
        await settle(page);
        const logo = page.locator('.app-header__logo');
        expect(new URL((await logo.getAttribute('href'))!).pathname).toBe(
            '/home',
        );
        const start = sidebar(page).getByRole('link', { name: 'Start' });
        expect(new URL((await start.getAttribute('href'))!).pathname).toBe(
            '/home',
        );
        await expect(start).not.toHaveAttribute('aria-current', 'page');
        const current = sidebar(page).locator('[aria-current="page"]');
        await expect(current).toHaveCount(1);
        await expect(current).toHaveText(DATA.collections.filled.name);
        await expect(sidebar(page)).not.toContainText('Anmeldung');
    });

    test('F-20 (E4) /settings/appearance und /settings/two-factor liefern die deutsche 404-Seite', async ({
        page,
    }) => {
        for (const path of ['/settings/appearance', '/settings/two-factor']) {
            const res = await page.goto(path);
            await settle(page);
            expect(res?.status(), path).toBe(404);
            await expect(page.locator('h1'), path).toHaveText(
                'Seite nicht gefunden',
            );
            // angemeldete Shell bleibt erhalten
            await expect(page.locator('.app-header')).toBeVisible();
        }
    });

    test('F-20 (E4) Einstellungen verlinken weder 2FA noch Darstellung', async ({
        page,
    }) => {
        await page.goto('/settings');
        await settle(page);
        const hrefs = await page
            .locator('main a')
            .evaluateAll((els) => els.map((e) => e.getAttribute('href') ?? ''));
        expect(hrefs.filter((h) => /two-factor|appearance/.test(h))).toEqual(
            [],
        );
        const text = (await page.locator('main').textContent()) ?? '';
        expect(text).not.toMatch(
            /Zwei-Faktor|2FA|Darstellung|Erscheinungsbild/i,
        );
        await page.goto('/settings/profile');
        await settle(page);
        expect((await page.locator('main').textContent()) ?? '').not.toMatch(
            /Zwei-Faktor|2FA|Darstellung/i,
        );
    });

    test('F-68 Nutzer ohne Host-Recht: /rezepte/erstellen zeigt die 403-Fehlerseite', async ({
        page,
    }) => {
        const res = await page.goto('/rezepte/erstellen');
        await settle(page);
        expect(res?.status()).toBe(403);
        await expect(page.locator('h1')).toHaveText('Kein Zugriff');
        expect(await page.title()).toBe('Kein Zugriff – PlantMel');
        const home = page.getByRole('link', { name: 'Zur Startseite' });
        expect(new URL((await home.getAttribute('href'))!).pathname).toBe(
            '/home',
        );
    });

    test('F-29/F-40 Desktop: zugeklappte Sidebar erhaelt keinen Tab-Fokus', async ({
        page,
    }) => {
        await page.goto('/home');
        await settle(page);
        // offen: Sidebar ist per Tab erreichbar
        await menuButton(page).focus();
        expect(
            await tabVisits(page, '#app-sidebar', 6),
            'offene Sidebar erreichbar',
        ).not.toEqual([]);
        await menuButton(page).click();
        await expect(sidebar(page)).toHaveClass(/sidebar--closed/);
        await expect(menuButton(page)).toHaveAttribute(
            'aria-expanded',
            'false',
        );
        await menuButton(page).focus();
        expect(await tabVisits(page, '#app-sidebar', 10)).toEqual([]);
    });

    test('F-05 Desktop: Untermenue erscheint neben der Sidebar auf Hoehe des Ausloesers', async ({
        page,
    }) => {
        await page.goto('/home');
        await settle(page);
        const toggle = page.locator('.sidebar-link--submenu-toggle');
        await toggle.click();
        const submenu = page.locator('#sidebar-search-submenu');
        await expect(submenu).toBeVisible();
        await page.waitForTimeout(300);
        const t = (await toggle.boundingBox())!;
        const s = (await submenu.boundingBox())!;
        const side = (await sidebar(page).boundingBox())!;
        writeRaw('regression', 'F-05-desktop', { toggle: t, submenu: s, side });
        expect(Math.abs(s.x - (side.x + side.width))).toBeLessThanOrEqual(1);
        expect(Math.abs(s.y - t.y)).toBeLessThanOrEqual(1);
        for (const name of ['Rezeptsuche', 'Zutatensuche']) {
            const samples = await samplePoints(
                page
                    .locator('.sidebar-submenu__item')
                    .filter({ hasText: name }),
            );
            expect(
                samples.every((x) => x.hitsTarget),
                name,
            ).toBe(true);
        }
        // zugeklappt wieder unsichtbar
        await toggle.click();
        await expect(submenu).toBeHidden();
    });
});

// ---------------------------------------------------------------------------------------------
test.describe('Nutzer 2 (eigene Daten/Session)', () => {
    test('F-17 Sammlung anlegen und loeschen zeigt sichtbare Erfolgsmeldungen', async ({
        browser,
    }) => {
        const context = await newContext(browser, 'user2');
        const page = await context.newPage();
        await page.goto('/sammlungen/erstellen');
        await settle(page);
        await page.locator('#collection-name').fill('E2E Shell-Sammlung');
        await Promise.all([
            page.waitForURL('**/sammlungen'),
            page.locator('form button[type="submit"]').click(),
        ]);
        await settle(page);
        await expect(flashSuccess(page)).toHaveText(
            /Sammlung wurde erstellt\./,
        );
        // Meldung nimmt keinen Fokus
        expect(
            await page.evaluate(
                () => !!document.activeElement?.closest('.flash-message'),
            ),
        ).toBe(false);
        await page.screenshot({
            path: test.info().outputPath('F-17-nach-anlegen.png'),
        });

        // schliessbar
        await flashSuccess(page)
            .getByRole('button', { name: 'Meldung schließen' })
            .click();
        await expect(flashSuccess(page)).toHaveCount(0);

        // Aufraeumen ueber die Oberflaeche: Loeschen zeigt ebenfalls eine Meldung
        await page
            .locator('.collection-card', { hasText: 'E2E Shell-Sammlung' })
            .click();
        await page.waitForURL(/\/sammlung\/\d+$/);
        await settle(page);
        page.once('dialog', (d) => d.accept());
        await Promise.all([
            page.waitForURL('**/sammlungen'),
            page.getByRole('button', { name: 'Sammlung löschen' }).click(),
        ]);
        await expect(flashSuccess(page)).toHaveText(
            /Sammlung wurde gelöscht\./,
        );
        await expect(
            page.locator('.collection-card', { hasText: 'E2E Shell-Sammlung' }),
        ).toHaveCount(0);

        // naechster Seitenwechsel: Meldung weg
        await page.goto('/home');
        await settle(page);
        await expect(page.locator('.flash-message')).toHaveCount(0);
        await context.close();
    });

    test('F-17 Profil speichern zeigt "Profil gespeichert."', async ({
        browser,
    }) => {
        const context = await newContext(browser, 'user2');
        const page = await context.newPage();
        await page.goto('/settings/profile');
        await settle(page);
        // unveraenderte Werte speichern (keine Datenaenderung)
        await expect(page.locator('#name')).toHaveValue(DATA.users.user2.name);
        await page.getByRole('button', { name: 'Speichern' }).click();
        await expect(flashSuccess(page)).toHaveText(/Profil gespeichert\./);
        await expect(flashSuccess(page)).toBeInViewport();
        await context.close();
    });

    test('F-17 Passwort aendern zeigt "Passwort geändert."', async ({
        browser,
    }) => {
        const context = await newContext(browser, 'user2');
        const page = await context.newPage();
        await page.goto('/settings/password');
        await settle(page);
        // gleiches Passwort erneut setzen (keine fachliche Aenderung)
        await page.locator('#current_password').fill(DATA.password);
        await page.locator('#password').fill(DATA.password);
        await page.locator('#password_confirmation').fill(DATA.password);
        await page.getByRole('button', { name: 'Passwort ändern' }).click();
        await expect(flashSuccess(page)).toHaveText(/Passwort geändert\./);
        // Erfolgsmeldungen verschwinden nach einigen Sekunden von selbst
        await expect(flashSuccess(page)).toHaveCount(0, { timeout: 9000 });
        await context.close();
    });

    test('F-21b nach dem Login landet man auf /home', async ({ browser }) => {
        const { context, landedOn } = await freshLogin(
            browser,
            DATA.users.user.email,
        );
        writeRaw('regression', 'F-21b', { landedOn });
        await context.close();
        expect(landedOn).toBe('/home');
    });
});

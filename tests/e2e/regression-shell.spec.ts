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

// Gate C: Kopfnavigation statt Seitenleiste; Selektoren ueber Rollen und Namen
const banner = (page: Page) => page.getByRole('banner');
const mainNav = (page: Page) =>
    banner(page).getByRole('navigation', { name: 'Hauptnavigation' });
const logo = (page: Page) =>
    banner(page).getByRole('link', { name: 'PlantMel – Startseite' });
const menuButton = (page: Page) =>
    page.getByRole('button', { name: 'Menü', exact: true });
/** Mobilmenue (Dialog, per Teleport ausserhalb von #app) */
const mobileMenu = (page: Page) => page.getByRole('dialog', { name: 'Menü' });
const collectionsButton = (page: Page) =>
    mainNav(page).getByRole('button', { name: 'Sammlungen' });

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

/** Erfolgsmeldung der App-Shell (F-17): Status mit Schliessen-Knopf im Inhalt */
const flashSuccess = (page: Page) =>
    page
        .getByRole('main')
        .getByRole('status')
        .filter({
            has: page.getByRole('button', { name: 'Meldung schließen' }),
        });

// ---------------------------------------------------------------------------------------------
test.describe('Gast', () => {
    test('F-21a Gast klickt Logo und landet auf der Landing-Seite', async ({
        page,
    }) => {
        await page.goto('/rezepte');
        await settle(page);
        await Promise.all([
            page.waitForURL((u) => u.pathname === '/'),
            logo(page).click(),
        ]);
        expect(new URL(page.url()).pathname).toBe('/');
    });

    test('F-21 Gast: Logo (Start) fuehrt auf "/" und ist dort als aktuelle Seite markiert', async ({
        page,
    }) => {
        // Gate C: "Start" der Seitenleiste ist jetzt das Logo der Kopfzeile
        await page.goto('/');
        await settle(page);
        await expect(logo(page)).toHaveAttribute('aria-current', 'page');
        expect(new URL((await logo(page).getAttribute('href'))!).pathname).toBe(
            '/',
        );
        await page.goto('/rezepte');
        await settle(page);
        await expect(logo(page)).not.toHaveAttribute('aria-current', 'page');
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
        // Gate C1b: die Startseite hat jetzt auch ein Rezeptband (weitere Listen);
        // die Vorteilsliste selbst hat weiterhin genau drei Punkte
        const benefits = page.getByRole('list', {
            name: 'Was du mit PlantMel machen kannst',
        });
        await expect(benefits.getByRole('listitem')).toHaveCount(3);
        await expect(benefits).not.toContainText(/Bewertung|Notiz/);
    });

    test('F-21 Gast: Sammlungen und Meine Zutaten als "Anmeldung erforderlich" erkennbar', async ({
        page,
    }) => {
        await page.goto('/rezepte');
        await settle(page);
        for (const name of ['Sammlungen', 'Meine Zutaten']) {
            const link = mainNav(page).getByRole('link', {
                name: `${name} (Anmeldung erforderlich)`,
            });
            await expect(link).toBeVisible();
        }
        // Gaeste bekommen kein Sammlungen-Aufklappmenue (nur den Link zur Anmeldung)
        await expect(collectionsButton(page)).toHaveCount(0);
    });

    test('F-21 Kopfnavigation: aktive Seite traegt aria-current und kein type an Links', async ({
        page,
    }) => {
        // Gate C: ersetzt den Test des Such-Untermenues (Rezeptsuche/Zutatensuche
        // sind jetzt direkte Eintraege der Hauptnavigation)
        await page.goto('/zutatensuche');
        await settle(page);
        const current = mainNav(page).locator('[aria-current="page"]');
        await expect(current).toHaveCount(1);
        await expect(current).toHaveText('Zutatensuche');
        await expect(
            mainNav(page).getByRole('link', { name: 'Rezepte', exact: true }),
        ).not.toHaveAttribute('aria-current', 'page');
        await page.goto('/rezepte');
        await settle(page);
        await expect(mainNav(page).locator('[aria-current="page"]')).toHaveText(
            'Rezepte',
        );
        expect(
            await page.locator('a[type]').count(),
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
        const skip = page.getByRole('link', { name: 'Zum Inhalt springen' });
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
        // Die Kopfzeile enthaelt keine Ueberschrift (das h1 steht im Inhalt)
        await expect(
            banner(page).locator('h1, h2, h3, h4, h5, h6'),
        ).toHaveCount(0);
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

        // Gate C: ersetzt "F-05 Such-Untermenue sichtbar und klickbar" (Seitenleiste)
        test('F-05/Gate C Mobilmenue: alle Eintraege sichtbar und klickbar, schliesst nach Navigation', async ({
            page,
        }) => {
            await page.goto('/zutatensuche');
            await settle(page);
            // Hauptnavigation der Kopfzeile ist mobil ausgeblendet
            await expect(mainNav(page)).toBeHidden();
            await expect(mobileMenu(page)).toHaveCount(0);
            await menuButton(page).click();
            await expect(mobileMenu(page)).toBeVisible();
            await page.waitForTimeout(300);
            const results: unknown[] = [];
            const nav = mobileMenu(page).getByRole('navigation', {
                name: 'Hauptnavigation',
            });
            for (const name of [
                'Startseite',
                'Rezepte',
                'Zutatensuche',
                'Sammlungen (Anmeldung erforderlich)',
                'Meine Zutaten (Anmeldung erforderlich)',
            ]) {
                const link = nav.getByRole('link', { name, exact: true });
                await expect(link).toBeVisible();
                const samples = await samplePoints(link);
                results.push({ name, samples });
                expect(
                    samples.every((s) => s.hitsTarget),
                    `${name} erhaelt an allen Messpunkten den Klick: ${JSON.stringify(samples)}`,
                ).toBe(true);
            }
            // aktuelle Seite markiert
            await expect(
                nav.getByRole('link', { name: 'Zutatensuche', exact: true }),
            ).toHaveAttribute('aria-current', 'page');
            for (const name of ['Anmelden', 'Registrieren']) {
                await expect(
                    mobileMenu(page).getByRole('link', { name, exact: true }),
                ).toBeVisible();
            }
            writeRaw('regression', 'F-05', { results });
            await Promise.all([
                page.waitForURL('**/rezepte'),
                nav.getByRole('link', { name: 'Rezepte', exact: true }).click(),
            ]);
            await settle(page);
            // F-29: Menue nach der Navigation geschlossen, Sperren aufgehoben
            await expect(mobileMenu(page)).toHaveCount(0);
            await expect(menuButton(page)).toHaveAttribute(
                'aria-expanded',
                'false',
            );
            const after = await page.evaluate(() => ({
                overflow: getComputedStyle(document.body).overflowY,
                inert: document.getElementById('app')?.inert,
            }));
            expect(after.overflow).not.toBe('hidden');
            expect(after.inert).toBe(false);
        });

        // Gate C/F-40: Fokus beim Oeffnen ins Menue, Fokusfalle
        test('F-40 Mobilmenue: Fokus beim Oeffnen im Menue, Tab bleibt im Menue (Fokusfalle)', async ({
            page,
        }) => {
            await page.goto('/rezepte');
            await settle(page);
            await menuButton(page).focus();
            await page.keyboard.press('Enter');
            await expect(mobileMenu(page)).toBeVisible();
            // erster Eintrag hat den Fokus
            await expect(
                mobileMenu(page).getByRole('link', {
                    name: 'Startseite',
                    exact: true,
                }),
            ).toBeFocused();
            const outside: string[] = [];
            const count = await mobileMenu(page)
                .locator('a[href], button:not([disabled])')
                .count();
            for (let i = 0; i < count + 3; i++) {
                await page.keyboard.press('Tab');
                const inMenu = await page.evaluate(
                    () =>
                        !!document.activeElement?.closest('#mobile-menu'),
                );
                if (!inMenu)
                    outside.push(
                        await page.evaluate(
                            () => document.activeElement?.tagName ?? 'null',
                        ),
                    );
            }
            for (let i = 0; i < 3; i++) {
                await page.keyboard.press('Shift+Tab');
                if (
                    !(await page.evaluate(
                        () => !!document.activeElement?.closest('#mobile-menu'),
                    ))
                )
                    outside.push('shift-tab');
            }
            writeRaw('regression', 'F-40-fokusfalle', { count, outside });
            expect(outside, 'Fokus verlaesst das offene Menue').toEqual([]);
        });

        test('F-05 geschlossenes Mobilmenue ist nicht per Tab erreichbar, geoeffnet schon', async ({
            page,
        }) => {
            await page.goto('/zutatensuche');
            await settle(page);
            expect(
                await tabVisits(page, '#mobile-menu', 12),
                'Fokus im geschlossenen Menue',
            ).toEqual([]);
            await menuButton(page).focus();
            await page.keyboard.press('Enter');
            await page.keyboard.press('Tab');
            expect(
                await page.evaluate(
                    () => !!document.activeElement?.closest('#mobile-menu'),
                ),
            ).toBe(true);
        });

        test('F-29 Backdrop sichtbar, Tippen neben das Menue schliesst es', async ({
            page,
        }) => {
            await page.goto('/rezepte');
            await settle(page);
            await expect(menuButton(page)).toHaveAttribute(
                'aria-expanded',
                'false',
            );
            await expect(menuButton(page)).toHaveAttribute(
                'aria-controls',
                'mobile-menu',
            );
            await menuButton(page).click();
            await expect(mobileMenu(page)).toBeVisible();
            await page.waitForTimeout(300);
            // Hintergrund scrollt nicht und ist inert
            expect(
                await page.evaluate(() => ({
                    overflow: getComputedStyle(document.body).overflowY,
                    inert: document.getElementById('app')?.inert,
                })),
            ).toEqual({ overflow: 'hidden', inert: true });
            // links neben dem Seitenblatt liegt die abgedunkelte Flaeche
            const hit = await page.evaluate(() => {
                const e = document.elementFromPoint(20, 500);
                return {
                    inMenu: !!e?.closest('#mobile-menu'),
                    inApp: !!e?.closest('#app'),
                    hidden: e?.getAttribute('aria-hidden') ?? null,
                };
            });
            expect(hit).toEqual({ inMenu: false, inApp: false, hidden: 'true' });
            await page.mouse.click(20, 500);
            await expect(mobileMenu(page)).toHaveCount(0);
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

        test('F-29 Escape schliesst das Menue und gibt den Fokus an den Menue-Knopf', async ({
            page,
        }) => {
            await page.goto('/rezepte');
            await settle(page);
            await menuButton(page).click();
            await expect(mobileMenu(page)).toBeVisible();
            await mobileMenu(page)
                .getByRole('link', { name: 'Zutatensuche', exact: true })
                .focus();
            await page.keyboard.press('Escape');
            await expect(mobileMenu(page)).toHaveCount(0);
            await expect(menuButton(page)).toBeFocused();
            // ebenso der Schliessen-Knopf
            await menuButton(page).click();
            await mobileMenu(page)
                .getByRole('button', { name: 'Menü schließen' })
                .click();
            await expect(mobileMenu(page)).toHaveCount(0);
            await expect(menuButton(page)).toBeFocused();
        });

        test('F-29/F-40 geschlossenes Menue erhaelt keinen Tab-Fokus', async ({
            page,
        }) => {
            await page.goto('/rezepte');
            await settle(page);
            await expect(mobileMenu(page)).toHaveCount(0);
            const visits = await tabVisits(page, '#mobile-menu', 12);
            writeRaw('regression', 'F-29-tab-mobil', { visits });
            expect(visits).toEqual([]);
        });

        // Gate C: ersetzt "F-69 Drawer schliesst buendig an den Header an"
        test('F-69 Mobilmenue deckt die volle Hoehe ab und liegt ueber der Kopfzeile', async ({
            page,
        }) => {
            for (const path of ['/rezepte', '/']) {
                await page.goto(path);
                await settle(page);
                await menuButton(page).click();
                await page.waitForTimeout(300);
                const panel = (await mobileMenu(page).boundingBox())!;
                const headerHit = await page.evaluate(() => {
                    const e = document.elementFromPoint(20, 20);
                    return !!e?.closest('header');
                });
                writeRaw('regression', `F-69-${path}`, { panel, headerHit });
                expect(panel.y, `${path}: Oberkante`).toBeLessThanOrEqual(1);
                expect(
                    Math.abs(panel.y + panel.height - 844),
                    `${path}: Unterkante = Viewport`,
                ).toBeLessThanOrEqual(1);
                expect(panel.x + panel.width).toBeLessThanOrEqual(390 + 1);
                expect(headerHit, 'Kopfzeile liegt unter dem Backdrop').toBe(
                    false,
                );
                await page.keyboard.press('Escape');
            }
        });

        test('Gate C Kopfzeile mobil: kein Ueberlauf bei 390 und 320 px', async ({
            page,
        }) => {
            for (const width of [390, 320]) {
                await page.setViewportSize({ width, height: 700 });
                await page.goto('/rezepte');
                await settle(page);
                const box = (await banner(page).boundingBox())!;
                const right = await banner(page).evaluate((h) =>
                    Math.max(
                        ...Array.from(h.querySelectorAll('*'))
                            .filter((e) => e.getClientRects().length > 0)
                            .map((e) => e.getBoundingClientRect().right),
                    ),
                );
                expect(box.width).toBeLessThanOrEqual(width);
                expect(right, `${width}: rechter Rand`).toBeLessThanOrEqual(
                    width + 1,
                );
                await expect(menuButton(page)).toBeVisible();
                await expect(logo(page)).toBeVisible();
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

    test('F-21 angemeldet: Logo fuehrt auf /home, aktive Sammlung im Sammlungen-Menue markiert', async ({
        page,
    }) => {
        await page.goto(`/sammlung/${DATA.collections.filled.id}`);
        await settle(page);
        expect(
            new URL((await logo(page).getAttribute('href'))!).pathname,
        ).toBe('/home');
        await expect(logo(page)).not.toHaveAttribute('aria-current', 'page');
        // Gate C: die eigenen Sammlungen (frueher in der Seitenleiste) liegen im Menue "Sammlungen"
        await expect(collectionsButton(page)).toHaveAttribute(
            'aria-expanded',
            'false',
        );
        await collectionsButton(page).click();
        await expect(collectionsButton(page)).toHaveAttribute(
            'aria-expanded',
            'true',
        );
        const menu = page.locator('#collections-menu');
        await expect(menu).toBeVisible();
        const current = menu.locator('[aria-current="page"]');
        await expect(current).toHaveCount(1);
        await expect(current).toHaveText(DATA.collections.filled.name);
        await expect(
            menu.getByRole('link', { name: 'Alle Sammlungen' }),
        ).toBeVisible();
        await expect(
            menu.getByRole('link', { name: DATA.collections.empty.name }),
        ).toBeVisible();
        await expect(banner(page)).not.toContainText('Anmeldung');
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
            // Shell mit Kopfzeile bleibt erhalten (ohne Session, siehe F-81)
            await expect(banner(page)).toBeVisible();
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

    // Gate C: ersetzt "F-29/F-40 Desktop: zugeklappte Sidebar erhaelt keinen Tab-Fokus"
    test('F-40 Desktop: geschlossenes Sammlungen-Menue erhaelt keinen Tab-Fokus, Escape und Aussenklick schliessen', async ({
        page,
    }) => {
        await page.goto('/home');
        await settle(page);
        const menu = page.locator('#collections-menu');
        await collectionsButton(page).focus();
        expect(
            await tabVisits(page, '#collections-menu', 6),
            'geschlossenes Menue erreichbar',
        ).toEqual([]);
        // geoeffnet: per Tab erreichbar
        await collectionsButton(page).focus();
        await page.keyboard.press('Enter');
        await expect(menu).toBeVisible();
        await page.keyboard.press('Tab');
        await expect(
            menu.getByRole('link', { name: 'Alle Sammlungen' }),
        ).toBeFocused();
        // Escape: zu, Fokus zurueck am Ausloeser
        await page.keyboard.press('Escape');
        await expect(menu).toBeHidden();
        await expect(collectionsButton(page)).toBeFocused();
        // Aussenklick schliesst
        await collectionsButton(page).click();
        await expect(menu).toBeVisible();
        await page.locator('h1').click();
        await expect(menu).toBeHidden();
        await expect(collectionsButton(page)).toHaveAttribute(
            'aria-expanded',
            'false',
        );
        // Tab aus dem Menue heraus schliesst es ebenfalls
        await collectionsButton(page).click();
        await menu.getByRole('link', { name: 'Neue Sammlung' }).focus();
        await page.keyboard.press('Tab');
        await expect(menu).toBeHidden();
    });

    // Gate C: ersetzt "F-05 Desktop: Untermenue erscheint neben der Sidebar"
    test('F-05 Desktop: Sammlungen-Menue erscheint unter dem Ausloeser, Eintraege klickbar', async ({
        page,
    }) => {
        await page.goto('/home');
        await settle(page);
        const menu = page.locator('#collections-menu');
        // geschlossen: unsichtbar, nicht klickbar, inert
        expect(
            await menu.evaluate((el) => ({
                visibility: getComputedStyle(el).visibility,
                pointerEvents: getComputedStyle(el).pointerEvents,
                inert: (el as HTMLElement).inert,
            })),
        ).toEqual({ visibility: 'hidden', pointerEvents: 'none', inert: true });
        await collectionsButton(page).click();
        await expect(menu).toBeVisible();
        await page.waitForTimeout(300);
        const t = (await collectionsButton(page).boundingBox())!;
        const m = (await menu.boundingBox())!;
        writeRaw('regression', 'F-05-desktop', { trigger: t, menu: m });
        expect(m.y).toBeGreaterThanOrEqual(t.y + t.height - 1);
        expect(Math.abs(m.x - t.x)).toBeLessThanOrEqual(1);
        for (const name of [
            'Alle Sammlungen',
            DATA.collections.filled.name,
            DATA.collections.empty.name,
            'Neue Sammlung',
        ]) {
            const samples = await samplePoints(
                menu.getByRole('link', { name, exact: true }),
            );
            expect(
                samples.every((x) => x.hitsTarget),
                `${name}: ${JSON.stringify(samples)}`,
            ).toBe(true);
        }
        await Promise.all([
            page.waitForURL(`**/sammlung/${DATA.collections.filled.id}`),
            menu
                .getByRole('link', { name: DATA.collections.filled.name })
                .click(),
        ]);
        await settle(page);
        // nach der Navigation wieder zu
        await expect(page.locator('#collections-menu')).toBeHidden();
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
                () => !!document.activeElement?.closest('[role="status"]'),
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
        // Gate C2: Kachel per Titel-Link; Loeschen fragt im Dialog statt per confirm()
        await page
            .getByRole('link', { name: 'E2E Shell-Sammlung', exact: true })
            .click();
        await page.waitForURL(/\/sammlung\/\d+$/);
        await settle(page);
        await page.getByRole('button', { name: 'Sammlung löschen' }).click();
        const confirmDialog = page.getByRole('dialog', {
            name: 'Sammlung löschen?',
        });
        await expect(confirmDialog).toBeVisible();
        await Promise.all([
            page.waitForURL('**/sammlungen'),
            confirmDialog
                .getByRole('button', { name: 'Endgültig löschen' })
                .click(),
        ]);
        await expect(flashSuccess(page)).toHaveText(
            /Sammlung wurde gelöscht\./,
        );
        await expect(
            page.getByRole('link', {
                name: 'E2E Shell-Sammlung',
                exact: true,
            }),
        ).toHaveCount(0);

        // naechster Seitenwechsel: Meldung weg
        await page.goto('/home');
        await settle(page);
        await expect(
            page.getByRole('button', { name: 'Meldung schließen' }),
        ).toHaveCount(0);
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

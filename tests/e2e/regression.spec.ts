import type { Browser, Page } from '@playwright/test';
import { BASE_URL } from './support/env';
import { authFile, DATA, expect, settle, test } from './support/fixtures';
import { freshLogin, hitTest } from './support/helpers';
import { writeRaw } from './support/raw';

/**
 * Regressionstests fuer behobene Audit-Befunde (docs/modernization/FINDINGS.md).
 *
 * Herkunft: tests/e2e/known-defects.spec.ts (dort bis Slice 0 mit `test.fail()` markiert).
 * Die Befund-ID steht im Titel. Seit Slice 1a: F-01, F-02, F-03, F-04.
 *
 * Datenveraenderungen: keine. F-04 nutzt eine eigene Anmeldung (user2) bzw. dessen Session.
 */

const MOBILE_390 = {
    viewport: { width: 390, height: 844 },
    isMobile: true,
    hasTouch: true,
} as const;

const dropdown = (page: Page) => page.locator('.user-menu__dropdown');
const trigger = (page: Page) =>
    page.locator('.user-menu > button.app-header__icon');
const isMenuOpen = (page: Page) =>
    dropdown(page).evaluate((el) => el.classList.contains('is-open'));

/** Wartet, bis die Schliess-Transition durch ist (visibility wird verzoegert umgeschaltet). */
async function expectMenuClosed(page: Page) {
    await expect(dropdown(page)).not.toHaveClass(/is-open/);
    await expect(dropdown(page)).toBeHidden();
    await expect(trigger(page)).toHaveAttribute('aria-expanded', 'false');
}

async function openMenu(page: Page) {
    await trigger(page).click();
    await expect(dropdown(page)).toHaveClass(/is-open/);
    await expect(dropdown(page)).toBeVisible();
    await expect(trigger(page)).toHaveAttribute('aria-expanded', 'true');
}

async function newUser2Context(
    browser: Browser,
    viewport = { width: 1440, height: 900 },
) {
    const context = await browser.newContext({
        baseURL: BASE_URL,
        storageState: authFile('user2'),
        viewport,
    });
    await context.route(
        (url) => url.origin !== BASE_URL,
        (route) => route.abort('blockedbyclient'),
    );
    return context;
}

// ---------------------------------------------------------------------------------------------
test.describe('Gast', () => {
    test('F-01 Passwort-Reset-Formular sendet POST /reset-password', async ({
        page,
        diag,
    }) => {
        const posts: string[] = [];
        page.on('request', (r) => {
            if (
                r.method() === 'POST' &&
                new URL(r.url()).pathname === '/reset-password'
            )
                posts.push(r.url());
        });
        await page.goto(
            `/reset-password/e2e-dummy-token?email=${encodeURIComponent(DATA.users.user.email)}`,
        );
        await settle(page);
        await page.locator('#password').fill('Neues-E2e-Passwort-1!');
        await page
            .locator('#password_confirmation')
            .fill('Neues-E2e-Passwort-1!');
        await page.locator('button[type="submit"]').click();
        await page.waitForTimeout(1500);
        writeRaw('regression', 'F-01', {
            posts,
            pageErrors: diag.pageErrors,
            consoleErrors: diag.consoleErrors,
        });
        expect(diag.pageErrors, 'keine unbehandelte Exception').toEqual([]);
        expect(
            diag.consoleErrors.filter((e) => e.includes('Ziggy')),
            'kein Ziggy-Fehler',
        ).toEqual([]);
        expect(
            posts.length,
            'POST /reset-password wurde gesendet',
        ).toBeGreaterThan(0);
    });

    test('F-02 Zutatensuche filtert beim Tippen ohne JS-Fehler', async ({
        page,
        diag,
    }) => {
        await page.goto('/zutatensuche');
        await settle(page);
        const before = await page.locator('.ingredient-card').count();
        await page
            .locator('.search-row__input')
            .first()
            .pressSequentially('Linsen', { delay: 30 });
        await expect
            .poll(() => page.locator('.ingredient-card').count())
            .toBeLessThan(before);
        const names = await page
            .locator('.ingredient-card__name')
            .allTextContents();
        writeRaw('regression', 'F-02', {
            before,
            after: names.length,
            names: names.slice(0, 10),
            pageErrors: diag.pageErrors,
            consoleErrors: diag.consoleErrors,
        });
        expect(
            [...diag.pageErrors, ...diag.consoleErrors],
            'keine JS-Fehler',
        ).toEqual([]);
        expect(names.length).toBeGreaterThan(0);
        expect(names.length).toBeLessThan(before);
        for (const n of names) expect(n).toContain('Linsen');
        // Trefferzahl in der Meta-Zeile folgt der Liste
        await expect(page.locator('.search-meta')).toHaveText(
            `${names.length} Zutaten gefunden`,
        );
    });

    test('F-02 Zutatensuche: Ein- und Ausschluss im Filterpanel filtern', async ({
        page,
        diag,
    }) => {
        await page.goto('/zutatensuche');
        await settle(page);
        const all = await page.locator('.ingredient-card').count();
        expect(all, 'Seeder liefert Zutaten').toBeGreaterThan(1);

        // Einschluss
        await page.locator('.search-row__filter-button').click();
        await page.locator('#includeIngredients').fill('Linsen');
        await page.getByRole('button', { name: 'Anwenden' }).click();
        await expect
            .poll(() => page.locator('.ingredient-card').count())
            .toBeLessThan(all);
        const included = await page
            .locator('.ingredient-card__name')
            .allTextContents();
        expect(included.length).toBeGreaterThan(0);
        for (const n of included) expect(n.toLowerCase()).toContain('linsen');

        // Ausschluss (Einschluss leeren)
        await page.locator('.search-row__filter-button').click();
        await page.locator('#includeIngredients').fill('');
        await page.locator('#excludeIngredients').fill('Linsen');
        await page.getByRole('button', { name: 'Anwenden' }).click();
        await expect
            .poll(() => page.locator('.ingredient-card').count())
            .toBe(all - included.length);
        const excluded = await page
            .locator('.ingredient-card__name')
            .allTextContents();
        for (const n of excluded)
            expect(n.toLowerCase()).not.toContain('linsen');

        writeRaw('regression', 'F-02-filter', {
            all,
            included: included.length,
            excluded: excluded.length,
        });
        expect(
            [...diag.pageErrors, ...diag.consoleErrors],
            'keine JS-Fehler',
        ).toEqual([]);
    });

    test('F-02 Zutatensuche zeigt keine erfundenen Kategorie-Chips und Zaehler', async ({
        page,
    }) => {
        await page.goto('/zutatensuche');
        await settle(page);
        await expect(page.locator('.tag-chips')).toHaveCount(0);
        await expect(page.locator('.filter-panel--tags')).toHaveCount(0);
    });

    test('F-04 Gast: geschlossenes User-Menue ist nicht klickbar (Anmelden-Position)', async ({
        page,
    }) => {
        await page.goto('/rezepte');
        await settle(page);
        expect(await isMenuOpen(page)).toBe(false);
        const login = page.locator('.user-menu__item', { hasText: 'Anmelden' });
        const hit = await hitTest(login);
        writeRaw('regression', 'F-04-gast', { hit });
        expect(
            hit.hitsTarget,
            `Element an der Anmelden-Position: ${hit.hit}`,
        ).toBe(false);
        expect(hit.hit).not.toContain('user-menu');
    });

    test('F-03 Gast: Sidebar-Eintrag "Meine Zutaten" fuehrt zum Login', async ({
        page,
    }) => {
        await page.goto('/rezepte');
        await settle(page);
        await Promise.all([
            page.waitForURL('**/login'),
            page
                .locator('.sidebar')
                .getByText('Meine Zutaten', { exact: true })
                .click(),
        ]);
        expect(new URL(page.url()).pathname).toBe('/login');
    });

    test.describe('mobil 390', () => {
        test.use(MOBILE_390);

        test('F-04/F-05 unsichtbares User-Dropdown ueberdeckt das Such-Untermenue nicht mehr', async ({
            page,
        }) => {
            await page.goto('/rezepte');
            await settle(page);
            await page.locator('.app-header__icon--menu').click();
            await page.locator('.sidebar-link--submenu-toggle').click();
            await page.waitForTimeout(400);
            const hits: string[] = [];
            for (const name of ['Rezeptsuche', 'Zutatensuche']) {
                const link = page
                    .locator('.sidebar-submenu__item')
                    .filter({ hasText: name });
                const samples = await link.evaluate((el) => {
                    const r = el.getBoundingClientRect();
                    return [0.1, 0.5, 0.9].map((f) => {
                        const h = document.elementFromPoint(
                            Math.round(r.left + r.width * f),
                            Math.round(r.top + r.height / 2),
                        );
                        return h
                            ? h.closest('.user-menu')
                                ? 'user-menu'
                                : h.tagName.toLowerCase()
                            : 'null';
                    });
                });
                hits.push(...samples);
            }
            writeRaw('regression', 'F-04-F-05', { hits });
            expect(
                hits.filter((h) => h === 'user-menu'),
                'kein Treffer auf dem User-Menue',
            ).toEqual([]);
        });
    });
});

// ---------------------------------------------------------------------------------------------
test.describe('Nutzer', () => {
    test.use({ storageState: authFile('user') });

    test('F-03 /meineZutaten rendert ohne JS-Fehler mit Leerzustand', async ({
        page,
        diag,
    }) => {
        await page.goto('/meineZutaten');
        await settle(page);
        writeRaw('regression', 'F-03a', {
            pageErrors: diag.pageErrors,
            consoleErrors: diag.consoleErrors,
        });
        expect([...diag.pageErrors, ...diag.consoleErrors]).toEqual([]);
        await expect(
            page.getByText('Du hast noch keine Zutaten gespeichert.', {
                exact: false,
            }),
        ).toBeVisible();
        // keine erfundenen Kategorien/Zaehler
        await expect(page.locator('.tag-chips')).toHaveCount(0);
        await expect(page.locator('.filter-panel--tags')).toHaveCount(0);
    });

    test('F-03 Sidebar-Eintrag "Meine Zutaten" navigiert', async ({
        page,
        diag,
    }) => {
        await page.goto('/home');
        await settle(page);
        await Promise.all([
            page.waitForURL('**/meineZutaten'),
            page
                .locator('.sidebar')
                .getByText('Meine Zutaten', { exact: true })
                .click(),
        ]);
        await settle(page);
        const landed = new URL(page.url()).pathname;
        writeRaw('regression', 'F-03b', { landed });
        expect(landed).toBe('/meineZutaten');
        await expect(
            page.getByText('Du hast noch keine Zutaten gespeichert.', {
                exact: false,
            }),
        ).toBeVisible();
        expect([...diag.pageErrors, ...diag.consoleErrors]).toEqual([]);
    });
});

// ---------------------------------------------------------------------------------------------
test.describe('Nutzer 2 (eigene Session)', () => {
    test('F-04 geschlossenes User-Menue: Klick auf "Abmelden"-Position meldet nicht ab (1440)', async ({
        browser,
    }) => {
        await runF04(browser, { width: 1440, height: 900 }, 'F-04-1440');
    });

    test('F-04 geschlossenes User-Menue: Klick auf "Abmelden"-Position meldet nicht ab (390)', async ({
        browser,
    }) => {
        await runF04(browser, { width: 390, height: 844 }, 'F-04-390');
    });

    test('F-04 User-Menue: Escape schliesst und gibt den Fokus an den Ausloeser zurueck', async ({
        browser,
    }) => {
        const context = await newUser2Context(browser);
        const page = await context.newPage();
        await page.goto('/home');
        await settle(page);
        await expect(trigger(page)).toHaveAttribute('aria-haspopup', 'true');
        await openMenu(page);
        await page.keyboard.press('Escape');
        await expectMenuClosed(page);
        const focusedIsTrigger = await trigger(page).evaluate(
            (el) => document.activeElement === el,
        );
        await context.close();
        expect(focusedIsTrigger, 'Fokus liegt wieder auf dem Ausloeser').toBe(
            true,
        );
    });

    test('F-04 User-Menue: Klick ausserhalb schliesst', async ({ browser }) => {
        const context = await newUser2Context(browser);
        const page = await context.newPage();
        await page.goto('/home');
        await settle(page);
        await openMenu(page);
        await page.locator('.app-header__title').click();
        await expectMenuClosed(page);
        await context.close();
    });

    test('F-04 User-Menue: geschlossen nicht per Tab erreichbar, geoeffnet bedienbar', async ({
        browser,
    }) => {
        const context = await newUser2Context(browser);
        const page = await context.newPage();
        await page.goto('/home');
        await settle(page);
        await trigger(page).focus();
        const inDropdownAfterTab: boolean[] = [];
        for (let i = 0; i < 3; i++) {
            await page.keyboard.press('Tab');
            inDropdownAfterTab.push(
                await page.evaluate(
                    () =>
                        !!document.activeElement?.closest(
                            '.user-menu__dropdown',
                        ),
                ),
            );
        }
        writeRaw('regression', 'F-04-tab', { inDropdownAfterTab });
        expect(
            inDropdownAfterTab,
            'geschlossenes Menue erhaelt keinen Tab-Fokus',
        ).toEqual([false, false, false]);

        // Geoeffnet: Eintraege per Tab erreichbar und klickbar
        await openMenu(page);
        await page.keyboard.press('Tab');
        const focusedText = await page.evaluate(() =>
            document.activeElement?.closest('.user-menu__dropdown')
                ? document.activeElement.textContent?.trim()
                : null,
        );
        expect(focusedText).toBe('Einstellungen');
        await Promise.all([
            page.waitForURL('**/settings'),
            page
                .locator('.user-menu__item', { hasText: 'Einstellungen' })
                .click(),
        ]);
        await context.close();
    });
});

async function runF04(
    browser: Browser,
    viewport: { width: number; height: number },
    name: string,
) {
    const { context, page } = await freshLogin(
        browser,
        DATA.users.user2.email,
        viewport,
    );
    await page.goto('/home');
    await settle(page);
    const isOpen = await isMenuOpen(page);
    const style = await dropdown(page).evaluate((el) => {
        const s = getComputedStyle(el);
        return {
            opacity: s.opacity,
            visibility: s.visibility,
            pointerEvents: s.pointerEvents,
            zIndex: s.zIndex,
            inert: (el as HTMLElement).inert,
        };
    });
    const logout = page.locator('.user-menu__item--danger');
    const box = await logout.boundingBox();
    const hit = await hitTest(logout);
    let logoutPosts = 0;
    page.on('request', (r) => {
        if (r.method() === 'POST' && new URL(r.url()).pathname === '/logout')
            logoutPosts++;
    });
    await page.mouse.click(hit.x, hit.y);
    await page.waitForTimeout(1500);
    const urlAfter = new URL(page.url()).pathname;
    await page.goto('/home');
    const stillLoggedIn = new URL(page.url()).pathname === '/home';
    writeRaw('regression', name, {
        viewport,
        menuOpen: isOpen,
        dropdownStyle: style,
        logoutBox: box,
        hit,
        logoutPosts,
        urlAfterClick: urlAfter,
        stillLoggedIn,
    });
    await context.close();
    expect(isOpen, 'Menue war geschlossen').toBe(false);
    expect(style.visibility, 'geschlossen unsichtbar').toBe('hidden');
    expect(style.pointerEvents, 'geschlossen nicht klickbar').toBe('none');
    expect(hit.hitsTarget, `Element an der Abmelden-Position: ${hit.hit}`).toBe(
        false,
    );
    expect(logoutPosts, 'kein POST /logout').toBe(0);
    expect(stillLoggedIn, 'weiterhin angemeldet').toBe(true);
}

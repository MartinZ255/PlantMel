import type { Browser, Page, Request } from '@playwright/test';
import { BASE_URL } from './support/env';
import { authFile, DATA, expect, settle, test } from './support/fixtures';
import {
    createRecipe,
    deleteRecipe,
    freshLogin,
    hitTest,
    inertiaProps,
    ingredientIds,
    xsrfHeaders,
} from './support/helpers';
import { writeRaw } from './support/raw';

/**
 * Regressionstests fuer behobene Audit-Befunde (docs/modernization/FINDINGS.md).
 *
 * Herkunft: tests/e2e/known-defects.spec.ts (dort bis Slice 0 mit `test.fail()` markiert).
 * Die Befund-ID steht im Titel. Seit Slice 1a: F-01, F-02, F-03, F-04.
 * Seit Slice 2: F-07, F-13, F-14, F-15, F-39, F-65 (Block am Dateiende).
 *
 * Datenveraenderungen bis Slice 1a: keine. F-04 nutzt eine eigene Anmeldung (user2) bzw.
 * dessen Session. Slice 2: siehe Kommentar am Block "Slice 2" (nur Wegwerf-Daten).
 */

const MOBILE_390 = {
    viewport: { width: 390, height: 844 },
    isMobile: true,
    hasTouch: true,
} as const;

// Gate C: Benutzer-Menue der Kopfnavigation (Panel = aria-controls des Ausloesers)
const dropdown = (page: Page) => page.locator('#user-menu');
const trigger = (page: Page) =>
    page.getByRole('button', { name: 'Benutzermenü' });
const isMenuOpen = async (page: Page) =>
    (await trigger(page).getAttribute('aria-expanded')) === 'true';
const mainNav = (page: Page) =>
    page
        .getByRole('banner')
        .getByRole('navigation', { name: 'Hauptnavigation' });
/** Zutatenliste der Rezeptdetailseite (F-13) */
const ingredientList = (page: Page) =>
    page.getByRole('list', { name: 'Zutaten', exact: true });
/** Zutatenzeile mit Alternativen ("oder: …"); ohne Alternativen nicht vorhanden */
const alternativesOf = (page: Page, ingredient: string) =>
    ingredientList(page)
        .getByRole('listitem')
        .filter({ hasText: ingredient })
        .filter({ hasText: 'oder:' });

/** Wartet, bis die Schliess-Transition durch ist (visibility wird verzoegert umgeschaltet). */
async function expectMenuClosed(page: Page) {
    await expect(dropdown(page)).toBeHidden();
    await expect(trigger(page)).toHaveAttribute('aria-expanded', 'false');
}

async function openMenu(page: Page) {
    await trigger(page).click();
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

    // Gate C2: Zutaten-Karten liegen als Listeneintraege [data-ingredient] in den
    // Buchstaben-Gruppen; Name in [data-ingredient-name]; Suchfeld per Label.
    test('F-02 Zutatensuche filtert beim Tippen ohne JS-Fehler', async ({
        page,
        diag,
    }) => {
        await page.goto('/zutatensuche');
        await settle(page);
        const before = await page.locator('[data-ingredient]').count();
        await page
            .getByLabel('Zutat suchen')
            .pressSequentially('Linsen', { delay: 30 });
        await expect
            .poll(() => page.locator('[data-ingredient]').count())
            .toBeLessThan(before);
        const names = await page
            .locator('[data-ingredient-name]')
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
        await expect(page.getByRole('main').getByRole('status')).toHaveText(
            `${names.length} Zutaten gefunden`,
        );
    });

    test('F-02 Zutatensuche: Ein- und Ausschluss im Filterpanel filtern', async ({
        page,
        diag,
    }) => {
        await page.goto('/zutatensuche');
        await settle(page);
        const all = await page.locator('[data-ingredient]').count();
        expect(all, 'Seeder liefert Zutaten').toBeGreaterThan(1);

        // Einschluss
        await page.getByRole('button', { name: /^Filter\b/ }).click();
        await page.locator('#includeIngredients').fill('Linsen');
        await page.getByRole('button', { name: 'Anwenden' }).click();
        await expect
            .poll(() => page.locator('[data-ingredient]').count())
            .toBeLessThan(all);
        const included = await page
            .locator('[data-ingredient-name]')
            .allTextContents();
        expect(included.length).toBeGreaterThan(0);
        for (const n of included) expect(n.toLowerCase()).toContain('linsen');

        // Ausschluss (Einschluss leeren)
        await page.getByRole('button', { name: /^Filter\b/ }).click();
        await page.locator('#includeIngredients').fill('');
        await page.locator('#excludeIngredients').fill('Linsen');
        await page.getByRole('button', { name: 'Anwenden' }).click();
        await expect
            .poll(() => page.locator('[data-ingredient]').count())
            .toBe(all - included.length);
        const excluded = await page
            .locator('[data-ingredient-name]')
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
        // Gate C2: Kategorie-Chips als Gruppe "Kategorien", Kategorienliste "Weitere Kategorien"
        await expect(
            page.getByRole('group', { name: 'Kategorien' }),
        ).toHaveCount(0);
        await expect(
            page.getByRole('button', { name: 'Weitere Kategorien' }),
        ).toHaveCount(0);
        await expect(
            page.getByRole('list', { name: 'Weitere Kategorien' }),
        ).toHaveCount(0);
    });

    // Gate C: Gaeste haben kein verstecktes Menue mehr, sondern sichtbare Links.
    // Gepruefte Idee bleibt: keine unsichtbare Flaeche faengt Klicks ab.
    test('F-04 Gast: Anmelden/Registrieren sichtbar und klickbar, kein unsichtbares Menue', async ({
        page,
    }) => {
        await page.goto('/rezepte');
        await settle(page);
        await expect(dropdown(page)).toHaveCount(0);
        const banner = page.getByRole('banner');
        const hits = [];
        for (const name of ['Anmelden', 'Registrieren']) {
            const link = banner.getByRole('link', { name, exact: true });
            await expect(link).toBeVisible();
            const hit = await hitTest(link);
            hits.push({ name, hit });
            expect(hit.hitsTarget, `${name}: ${hit.hit}`).toBe(true);
        }
        // keine unsichtbaren, aber klickbaren Flaechen in der Kopfzeile
        const invisibleClickable = await banner.evaluate((h) =>
            Array.from(h.querySelectorAll<HTMLElement>('*'))
                .filter((e) => {
                    const s = getComputedStyle(e);
                    return (
                        (s.visibility === 'hidden' || s.opacity === '0') &&
                        s.pointerEvents !== 'none' &&
                        e.getClientRects().length > 0
                    );
                })
                .map((e) => e.tagName),
        );
        writeRaw('regression', 'F-04-gast', { hits, invisibleClickable });
        expect(invisibleClickable).toEqual([]);
    });

    test('F-03 Gast: Navigations-Eintrag "Meine Zutaten" fuehrt zum Login', async ({
        page,
    }) => {
        await page.goto('/rezepte');
        await settle(page);
        await Promise.all([
            page.waitForURL('**/login'),
            mainNav(page)
                .getByRole('link', {
                    name: 'Meine Zutaten (Anmeldung erforderlich)',
                })
                .click(),
        ]);
        expect(new URL(page.url()).pathname).toBe('/login');
    });

    test.describe('mobil 390', () => {
        test.use(MOBILE_390);

        // Gate C: das Such-Untermenue ist durch das Mobilmenue ersetzt; gleiche Pruefidee:
        // nichts aus der Kopfzeile oder einem Menue verdeckt die Eintraege
        test('F-04/F-05 Kopfzeile und Menues ueberdecken das Mobilmenue nicht', async ({
            page,
        }) => {
            await page.goto('/rezepte');
            await settle(page);
            await page
                .getByRole('button', { name: 'Menü', exact: true })
                .click();
            const menu = page.getByRole('dialog', { name: 'Menü' });
            await expect(menu).toBeVisible();
            await page.waitForTimeout(400);
            const hits: string[] = [];
            for (const name of ['Rezepte', 'Zutatensuche', 'Anmelden']) {
                const link = menu.getByRole('link', { name, exact: true });
                const samples = await link.evaluate((el) => {
                    const r = el.getBoundingClientRect();
                    return [0.1, 0.5, 0.9].map((f) => {
                        const h = document.elementFromPoint(
                            Math.round(r.left + r.width * f),
                            Math.round(r.top + r.height / 2),
                        );
                        if (!h) return 'null';
                        if (h.closest('header')) return 'header';
                        return el.contains(h) || h === el ? 'ok' : h.tagName;
                    });
                });
                hits.push(...samples);
            }
            writeRaw('regression', 'F-04-F-05', { hits });
            expect(hits.filter((h) => h !== 'ok')).toEqual([]);
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
        // Gate C2: Kategorie-Chips als Gruppe "Kategorien", Kategorienliste "Weitere Kategorien"
        await expect(
            page.getByRole('group', { name: 'Kategorien' }),
        ).toHaveCount(0);
        await expect(
            page.getByRole('button', { name: 'Weitere Kategorien' }),
        ).toHaveCount(0);
        await expect(
            page.getByRole('list', { name: 'Weitere Kategorien' }),
        ).toHaveCount(0);
    });

    test('F-03 Navigations-Eintrag "Meine Zutaten" navigiert', async ({
        page,
        diag,
    }) => {
        await page.goto('/home');
        await settle(page);
        await Promise.all([
            page.waitForURL('**/meineZutaten'),
            mainNav(page)
                .getByRole('link', { name: 'Meine Zutaten', exact: true })
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
        // Gate C: Disclosure-Muster (kein role="menu"), daher aria-controls statt aria-haspopup
        await expect(trigger(page)).toHaveAttribute(
            'aria-controls',
            'user-menu',
        );
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
        await page.locator('h1').click();
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
                    () => !!document.activeElement?.closest('#user-menu'),
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
            document.activeElement?.closest('#user-menu')
                ? document.activeElement.textContent?.trim()
                : null,
        );
        expect(focusedText).toBe('Einstellungen');
        await Promise.all([
            page.waitForURL('**/settings'),
            dropdown(page).getByRole('link', { name: 'Einstellungen' }).click(),
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
    // geschlossen ist der Eintrag versteckt => includeHidden
    const logout = dropdown(page).getByRole('button', {
        name: 'Abmelden',
        includeHidden: true,
    });
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

// =============================================================================================
// Slice 2 – Datenverlust beim Bearbeiten von Rezepten (F-07, F-65, F-13, F-14, F-15, F-39)
//
// Datenveraenderungen (alle Tests nutzen eigene Wegwerf-Daten; keine Baseline-Seite betroffen,
// die Baseline-Projekte laufen in der Konfiguration ausserdem vorher):
// - F-07 loescht die geseedete Kategorie E2E-Wegwerf-Tag (Rezept 6) – einziger Nutzer.
// - F-65 legt eine Wegwerf-Zutat an und loescht sie wieder (netto keine Aenderung).
// - F-13/F-14 legen eigene Rezepte an (nur bestehende Zutaten) und loeschen sie am Ende.
// - F-39 nutzt user2 mit eigener Sammlung, die am Ende geloescht wird.
// =============================================================================================

type ReqLog = { method: string; url: string };

/** Protokolliert Requests, deren Pfad zum Muster passt. */
function logRequests(page: Page, pattern: RegExp): ReqLog[] {
    const log: ReqLog[] = [];
    page.on('request', (r) => {
        if (pattern.test(new URL(r.url()).pathname))
            log.push({ method: r.method(), url: r.url() });
    });
    return log;
}

/** Zerlegt einen multipart/form-data-Body in Feldname -> Wert (nur Textfelder). */
function parseMultipart(
    body: string,
    contentType: string,
): Record<string, string> {
    const boundary = contentType.match(/boundary=(.+)$/)?.[1];
    const fields: Record<string, string> = {};
    if (!boundary) return fields;
    for (const part of body.split(`--${boundary}`)) {
        const sep = part.indexOf('\r\n\r\n');
        if (sep === -1) continue;
        const head = part.slice(0, sep);
        const name = head.match(/name="([^"]+)"/)?.[1];
        if (!name || /filename=/.test(head)) continue;
        fields[name] = part
            .slice(sep + 4)
            .replace(/\r\n$/, '')
            .replace(/\r\n/g, '\n');
    }
    return fields;
}

const sentFields = (request: Request) =>
    parseMultipart(
        request.postData() ?? '',
        request.headers()['content-type'] ?? '',
    );

const isUpdateOf = (recipeId: number) => (r: Request) =>
    r.method() === 'POST' &&
    new URL(r.url()).pathname === `/rezept/${recipeId}`;

test.describe('Host – Slice 2', () => {
    test.use({ storageState: authFile('host') });

    // ------------------------------------------------------------ F-07 Kategorien
    test('F-07 Tag loeschen: erst 409 mit Anzahl, Abbrechen sendet nichts, "Endgueltig loeschen" mit confirmed=1', async ({
        page,
        diag,
    }) => {
        const requests = logRequests(page, /^\/categories\//);
        let dialogs = 0;
        page.on('dialog', async (d) => {
            dialogs++;
            await d.dismiss();
        });

        await page.goto('/rezepte/erstellen');
        await settle(page);
        const chip = page.locator('[data-tag-chip]', {
            hasText: DATA.throwawayCategory,
        });
        await chip.dblclick();
        const modal = page.getByRole('dialog', { name: 'Tag bearbeiten' });
        await expect(modal).toBeVisible();
        await expect(modal.locator('[data-field-hint]')).toContainText(
            'gelten für alle Rezepte',
        );

        // 1) Erster Klick: DELETE ohne confirmed -> 409, nichts geloescht
        const [first] = await Promise.all([
            page.waitForResponse(
                (r) =>
                    r.request().method() === 'DELETE' &&
                    r.url().includes('/categories/'),
            ),
            modal.getByRole('button', { name: 'Löschen' }).click(),
        ]);
        expect(first.status()).toBe(409);
        expect(
            new URL(first.url()).searchParams.has('confirmed'),
            'erster DELETE ohne confirmed',
        ).toBe(false);
        const confirm = modal.getByRole('group');
        await expect(confirm).toBeVisible();
        const message =
            (
                await confirm.locator('[data-confirm-text]').textContent()
            )?.trim() ?? '';
        expect(message).toContain(`„${DATA.throwawayCategory}“`);
        expect(message).toContain('in einem Rezept verwendet');
        // Fokus liegt auf "Abbrechen" (sicherer Standard)
        await expect(
            confirm.getByRole('button', { name: 'Abbrechen' }),
        ).toBeFocused();

        // 2) Abbrechen: kein weiterer Request, Bestaetigung weg
        await confirm.getByRole('button', { name: 'Abbrechen' }).click();
        await expect(confirm).toHaveCount(0);
        await page.waitForTimeout(300);
        expect(requests.filter((r) => r.method === 'DELETE')).toHaveLength(1);

        // 3) Schliessen und erneut oeffnen: Bestaetigungszustand zurueckgesetzt
        await modal.getByRole('button', { name: 'Löschen' }).click();
        await expect(modal.getByRole('group')).toBeVisible();
        await modal
            .getByRole('group')
            .getByRole('button', { name: 'Abbrechen' })
            .click();
        await modal.getByRole('button', { name: 'Abbrechen' }).click();
        await expect(modal).toBeHidden();
        await chip.dblclick();
        await expect(modal).toBeVisible();
        await expect(modal.getByRole('group')).toHaveCount(0);

        // 4) Erneute Rueckfrage, dann "Endgültig löschen" -> confirmed=1 -> 200
        await modal.getByRole('button', { name: 'Löschen' }).click();
        await expect(modal.getByRole('group')).toBeVisible();
        const [final] = await Promise.all([
            page.waitForResponse(
                (r) =>
                    r.request().method() === 'DELETE' &&
                    r.url().includes('confirmed=1'),
            ),
            modal.getByRole('button', { name: 'Endgültig löschen' }).click(),
        ]);
        expect(final.status()).toBe(200);
        await expect(modal).toBeHidden();
        await expect(chip).toHaveCount(0);

        // Serverseitig geloescht: nach Neuladen kein Chip mehr
        await page.reload();
        await settle(page);
        await expect(
            page.locator('[data-tag-chip]', {
                hasText: DATA.throwawayCategory,
            }),
        ).toHaveCount(0);

        const deletes = requests.filter((r) => r.method === 'DELETE');
        writeRaw('regression', 'F-07', {
            requests: deletes,
            message,
            nativeDialogs: dialogs,
        });
        expect(
            deletes.map((r) => new URL(r.url).searchParams.get('confirmed')),
        ).toEqual([null, null, null, '1']);
        expect(dialogs, 'kein window.confirm').toBe(0);
        expect(diag.pageErrors).toEqual([]);
    });

    test('F-07 Tag loeschen: Netzwerkfehler und 500 werden sichtbar im Modal gemeldet', async ({
        page,
    }) => {
        let mode: 'abort' | '500' = 'abort';
        await page.route('**/categories/**', async (route) => {
            if (route.request().method() !== 'DELETE') return route.continue();
            if (mode === 'abort') return route.abort('internetdisconnected');
            return route.fulfill({
                status: 500,
                contentType: 'application/json',
                body: '{"message":"Server Error"}',
            });
        });
        await page.goto('/rezepte/erstellen');
        await settle(page);
        await page
            .locator('[data-tag-chip]', { hasText: 'Dessert' })
            .dblclick();
        const modal = page.getByRole('dialog', { name: 'Tag bearbeiten' });
        await modal.getByRole('button', { name: 'Löschen' }).click();
        await expect(modal.getByRole('alert')).toContainText(
            'Keine Verbindung zum Server',
        );
        mode = '500';
        await modal.getByRole('button', { name: 'Löschen' }).click();
        await expect(modal.getByRole('alert')).toContainText(
            'Auf dem Server ist ein Fehler aufgetreten',
        );
        await expect(modal.getByRole('group')).toHaveCount(0);
    });

    test('F-07/F-74 Tag umbenennen: Fehler (Name vergeben) wird sichtbar angezeigt', async ({
        page,
    }) => {
        await page.goto('/rezepte/erstellen');
        await settle(page);
        await page
            .locator('[data-tag-chip]', { hasText: 'Dessert' })
            .dblclick();
        const modal = page.getByRole('dialog', { name: 'Tag bearbeiten' });
        await modal.locator('#tag-name').fill('Snack');
        const [res] = await Promise.all([
            page.waitForResponse(
                (r) =>
                    r.request().method() === 'PUT' &&
                    r.url().includes('/categories/'),
            ),
            modal.getByRole('button', { name: 'Speichern' }).click(),
        ]);
        expect(res.status()).toBe(422);
        await expect(modal.getByRole('alert')).toBeVisible();
        await expect(modal).toBeVisible();
    });

    test.describe('mobil 390', () => {
        test.use(MOBILE_390);

        test('F-07 Bestaetigungsbereich passt bei 390 px; Abbrechen loescht nichts', async ({
            page,
        }) => {
            const requests = logRequests(page, /^\/categories\//);
            await page.goto('/rezepte/erstellen');
            await settle(page);
            await page
                .locator('[data-tag-chip]', { hasText: 'Dessert' })
                .dblclick();
            const modal = page.getByRole('dialog', { name: 'Tag bearbeiten' });
            await modal.getByRole('button', { name: 'Löschen' }).click();
            const confirm = modal.getByRole('group');
            await expect(confirm).toContainText('„Dessert“');
            for (const name of ['Abbrechen', 'Endgültig löschen']) {
                const box = await confirm
                    .getByRole('button', { name })
                    .boundingBox();
                expect(box, name).not.toBeNull();
                expect(box!.x).toBeGreaterThanOrEqual(0);
                expect(box!.x + box!.width).toBeLessThanOrEqual(390);
            }
            await page.screenshot({
                path: test.info().outputPath('F-07-bestaetigung-390.png'),
            });
            await confirm.getByRole('button', { name: 'Abbrechen' }).click();
            await page.waitForTimeout(300);
            const deletes = requests.filter((r) => r.method === 'DELETE');
            expect(deletes).toHaveLength(1);
            expect(deletes[0].url).not.toContain('confirmed');
        });
    });

    // ------------------------------------------------------------ F-65 / F-18 Zutaten
    test('F-65/F-18 Zutat in Verwendung: Loeschen zeigt sichtbare Fehlermeldung, keine Bestaetigung', async ({
        page,
    }) => {
        await page.goto('/rezepte/erstellen');
        await settle(page);
        const row = page.locator('[data-ingredient-row]').first();
        await row.locator('[data-ingredient-name]').fill('Rote Linsen');
        await page.locator('#name').click(); // Dropdown schliessen
        await row.getByRole('button', { name: 'Zutat bearbeiten' }).click();
        const modal = page.getByRole('dialog', { name: 'Zutat bearbeiten' });
        await expect(modal.locator('[data-field-hint]')).toContainText(
            'gelten für alle Rezepte',
        );
        const [res] = await Promise.all([
            page.waitForResponse(
                (r) =>
                    r.request().method() === 'DELETE' &&
                    r.url().includes('/ingredients/'),
            ),
            modal.getByRole('button', { name: 'Löschen' }).click(),
        ]);
        expect(res.status()).toBe(422);
        await expect(modal.getByRole('alert')).toContainText(
            'wird in mindestens einem Rezept verwendet',
        );
        await expect(modal.getByRole('group')).toHaveCount(0);

        // F-74: Umbenennen auf einen vergebenen Namen -> sichtbarer Fehler
        await modal.locator('#ingredient-name').fill('Braune Linsen');
        const [put] = await Promise.all([
            page.waitForResponse(
                (r) =>
                    r.request().method() === 'PUT' &&
                    r.url().includes('/ingredients/'),
            ),
            modal.getByRole('button', { name: 'Speichern' }).click(),
        ]);
        expect(put.status()).toBe(422);
        await expect(
            // Gate C2: Fehlermeldung direkt am Namensfeld (nicht die Loesch-Meldung)
            modal.locator(
                '#ingredient-name-hint ~ [data-field-error][role="alert"]',
            ),
        ).toBeVisible();
        writeRaw('regression', 'F-65-in-use', {
            deleteStatus: res.status(),
            renameStatus: put.status(),
        });
    });

    test('F-65 unbenutzte Wegwerf-Zutat: Bestaetigung, dann geloescht', async ({
        page,
    }) => {
        const name = `E2E Wegwerfzutat ${Date.now()}`;
        const requests = logRequests(page, /^\/ingredients/);
        await page.goto('/rezepte/erstellen');
        await settle(page);
        const row = page.locator('[data-ingredient-row]').first();
        await row.locator('[data-ingredient-name]').fill(name);
        await page.locator('#name').click();
        const [created] = await Promise.all([
            page.waitForResponse(
                (r) =>
                    r.request().method() === 'POST' &&
                    new URL(r.url()).pathname === '/ingredients',
            ),
            row
                .getByRole('button', { name: 'Zutat neu anlegen', exact: true })
                .click(),
        ]);
        expect(created.status()).toBe(201);

        await row.getByRole('button', { name: 'Zutat bearbeiten' }).click();
        const modal = page.getByRole('dialog', { name: 'Zutat bearbeiten' });
        const [first] = await Promise.all([
            page.waitForResponse((r) => r.request().method() === 'DELETE'),
            modal.getByRole('button', { name: 'Löschen' }).click(),
        ]);
        expect(first.status()).toBe(409);
        const confirm = modal.getByRole('group');
        await expect(confirm).toContainText(`„${name}“ wird global gelöscht`);
        const [final] = await Promise.all([
            page.waitForResponse(
                (r) =>
                    r.request().method() === 'DELETE' &&
                    r.url().includes('confirmed=1'),
            ),
            confirm.getByRole('button', { name: 'Endgültig löschen' }).click(),
        ]);
        expect(final.status()).toBe(200);
        await expect(modal).toBeHidden();
        // Zeile geleert, Zutat nach Neuladen nicht mehr in den Vorschlaegen
        await expect(row.locator('[data-ingredient-name]')).toHaveValue('');
        await page.reload();
        await settle(page);
        const props = await inertiaProps<{ ingredients: { name: string }[] }>(
            page,
        );
        expect(props.ingredients.some((i) => i.name === name)).toBe(false);
        writeRaw('regression', 'F-65-throwaway', {
            requests: requests.map(
                (r) =>
                    `${r.method} ${new URL(r.url).pathname}${new URL(r.url).search}`,
            ),
        });
    });

    // ------------------------------------------------------------ F-13 Alternativen
    test('F-13 Alternative hinzufuegen und entfernen: kein Request an ingredientAlternatives, gespeichert mit dem Rezept', async ({
        page,
        diag,
    }) => {
        const ids = await ingredientIds(page, ['Tempeh']);
        const recipeId = await createRecipe(page, {
            title: 'E2E F-13 Hinzufuegen',
            ingredients: [
                { name: 'Brokkoli', amount: '1 Kopf' },
                { name: 'Sojasauce', amount: '3 EL' },
            ],
            steps: ['Alles braten.'],
        });
        const altRequests = logRequests(page, /alternatives/);
        try {
            await page.goto(`/rezept/${recipeId}/bearbeiten`);
            await settle(page);
            const rows = page.locator('[data-ingredient-row]');
            await rows
                .nth(0)
                .getByRole('button', { name: 'Alternativzutaten bearbeiten' })
                .click();
            const modal = page.getByRole('dialog', {
                name: /Alternativen für: Brokkoli/,
            });
            await expect(modal).toContainText(
                'Wird mit dem Rezept gespeichert',
            );
            await modal.locator('#alt-search').fill('Tempeh');
            await modal
                .locator('[data-alternatives-panel]')
                .first()
                .getByRole('button', { name: 'Tempeh', exact: true })
                .click();
            await modal
                .getByRole('button', { name: 'Als Alternative hinzufügen' })
                .click();
            await modal.getByRole('button', { name: 'Übernehmen' }).click();
            await expect(modal).toBeHidden();

            const [update] = await Promise.all([
                page.waitForRequest(isUpdateOf(recipeId)),
                page
                    .getByRole('button', { name: 'Rezept aktualisieren' })
                    .click(),
            ]);
            const fields = sentFields(update);
            expect(fields['ingredients[0][alternativeIngredientIds][0]']).toBe(
                String(ids.Tempeh),
            );
            expect(fields['source'], 'kein Feld source').toBeUndefined();
            await page.waitForURL(new RegExp(`/rezept/${recipeId}$`));
            await settle(page);
            await expect(alternativesOf(page, 'Brokkoli')).toContainText(
                'Tempeh',
            );

            // Wieder entfernen: Modal startet mit den Alternativen aus den Edit-Props (ohne GET)
            await page.goto(`/rezept/${recipeId}/bearbeiten`);
            await settle(page);
            await rows
                .nth(0)
                .getByRole('button', { name: 'Alternativzutaten bearbeiten' })
                .click();
            const right = modal.locator('[data-alternatives-panel]').last();
            await right
                .getByRole('button', { name: 'Tempeh', exact: true })
                .click();
            await modal
                .getByRole('button', { name: 'Alternative entfernen' })
                .click();
            await expect(right).toContainText(
                'Noch keine Alternativzutaten hinterlegt.',
            );
            await modal.getByRole('button', { name: 'Übernehmen' }).click();
            await Promise.all([
                page.waitForURL(new RegExp(`/rezept/${recipeId}$`)),
                page
                    .getByRole('button', { name: 'Rezept aktualisieren' })
                    .click(),
            ]);
            await settle(page);
            await expect(alternativesOf(page, 'Brokkoli')).toHaveCount(0);

            writeRaw('regression', 'F-13-add-remove', { altRequests, fields });
            expect(
                altRequests,
                'kein Request an ingredientAlternatives.*',
            ).toEqual([]);
            expect([...diag.pageErrors, ...diag.consoleErrors]).toEqual([]);
        } finally {
            await deleteRecipe(page, recipeId);
        }
    });

    test('F-13 Abbrechen im Modal verwirft die Aenderungen der Sitzung', async ({
        page,
    }) => {
        const recipeId = await createRecipe(page, {
            title: 'E2E F-13 Abbrechen',
            ingredients: [{ name: 'Brokkoli', amount: '1 Kopf' }],
            steps: ['Braten.'],
        });
        const altRequests = logRequests(page, /alternatives/);
        try {
            await page.goto(`/rezept/${recipeId}/bearbeiten`);
            await settle(page);
            const openButton = page
                .getByRole('button', { name: 'Alternativzutaten bearbeiten' })
                .first();
            await openButton.click();
            const modal = page.getByRole('dialog', {
                name: /Alternativen für: Brokkoli/,
            });
            await modal
                .locator('[data-alternatives-panel]')
                .first()
                .getByRole('button', { name: 'Naturtofu', exact: true })
                .click();
            await modal
                .getByRole('button', { name: 'Als Alternative hinzufügen' })
                .click();
            await expect(
                modal.locator('[data-alternatives-panel]').last(),
            ).toContainText('Naturtofu');
            await modal.getByRole('button', { name: 'Abbrechen' }).click();
            await expect(modal).toBeHidden();

            await openButton.click();
            await expect(
                modal.locator('[data-alternatives-panel]').last(),
            ).toContainText('Noch keine Alternativzutaten hinterlegt.');
            await modal.getByRole('button', { name: 'Abbrechen' }).click();

            const [update] = await Promise.all([
                page.waitForRequest(isUpdateOf(recipeId)),
                page
                    .getByRole('button', { name: 'Rezept aktualisieren' })
                    .click(),
            ]);
            expect(
                Object.keys(sentFields(update)).filter((k) =>
                    k.includes('alternativeIngredientIds'),
                ),
            ).toEqual([]);
            await page.waitForURL(new RegExp(`/rezept/${recipeId}$`));
            await settle(page);
            await expect(
                ingredientList(page)
                    .getByRole('listitem')
                    .filter({ hasText: 'oder:' }),
            ).toHaveCount(0);
            expect(altRequests).toEqual([]);
        } finally {
            await deleteRecipe(page, recipeId);
        }
    });

    test('F-13 Alternativen bleiben bei neuer Zeile, Umsortieren, Umbenennen und nie geoeffnetem Modal erhalten', async ({
        page,
    }) => {
        const ids = await ingredientIds(page, [
            'Tempeh',
            'Naturtofu',
            'Basmatireis',
        ]);
        const recipeId = await createRecipe(page, {
            title: 'E2E F-13 Erhalten',
            ingredients: [
                {
                    name: 'Brokkoli',
                    amount: '1 Kopf',
                    alternativeIds: [ids.Tempeh],
                },
                {
                    name: 'Sojasauce',
                    amount: '3 EL',
                    alternativeIds: [ids.Naturtofu],
                },
            ],
            steps: ['Braten.'],
        });
        const altRequests = logRequests(page, /alternatives/);
        try {
            await page.goto(`/rezept/${recipeId}/bearbeiten`);
            await settle(page);
            const rows = page.locator('[data-ingredient-row]');

            // Neue Zeile mit Alternative
            await page.getByRole('button', { name: '+ weitere Zutat' }).click();
            await rows.nth(2).locator('[data-ingredient-amount]').fill('150 g');
            await rows.nth(2).locator('[data-ingredient-name]').fill('Quinoa');
            await page.locator('#name').click();
            await rows
                .nth(2)
                .getByRole('button', { name: 'Alternativzutaten bearbeiten' })
                .click();
            const modal = page.getByRole('dialog', {
                name: /Alternativen für: Quinoa/,
            });
            await modal.locator('#alt-search').fill('Basmatireis');
            await modal
                .locator('[data-alternatives-panel]')
                .first()
                .getByRole('button', { name: 'Basmatireis', exact: true })
                .click();
            await modal
                .getByRole('button', { name: 'Als Alternative hinzufügen' })
                .click();
            await modal.getByRole('button', { name: 'Übernehmen' }).click();
            await expect(modal).toBeHidden();

            // Umsortieren: neue Zeile (Index 2) per Drag-Griff ganz nach oben
            const handle = rows.nth(2).locator('[data-ingredient-handle]');
            const hb = (await handle.boundingBox())!;
            const top = (await rows.nth(0).boundingBox())!;
            await page.mouse.move(hb.x + hb.width / 2, hb.y + hb.height / 2);
            await page.mouse.down();
            await page.mouse.move(hb.x + hb.width / 2, top.y + 2, {
                steps: 15,
            });
            await page.mouse.up();
            const names = await rows
                .locator('[data-ingredient-name]')
                .evaluateAll((els) =>
                    els.map((e) => (e as HTMLInputElement).value),
                );
            expect(names, 'Reihenfolge nach Drag').toEqual([
                'Quinoa',
                'Brokkoli',
                'Sojasauce',
            ]);

            // Brokkoli-Zeile (jetzt Index 1) umbenennen; Sojasauce-Modal nie geoeffnet
            await rows
                .nth(1)
                .locator('[data-ingredient-name]')
                .fill('Zucchini');
            await page.locator('#name').click();

            const [update] = await Promise.all([
                page.waitForRequest(isUpdateOf(recipeId)),
                page
                    .getByRole('button', { name: 'Rezept aktualisieren' })
                    .click(),
            ]);
            const fields = sentFields(update);
            const sent = [0, 1, 2].map((i) => ({
                name: fields[`ingredients[${i}][name]`],
                alternatives: Object.entries(fields)
                    .filter(([k]) =>
                        k.startsWith(
                            `ingredients[${i}][alternativeIngredientIds]`,
                        ),
                    )
                    .map(([, v]) => Number(v)),
            }));
            expect(sent).toEqual([
                { name: 'Quinoa', alternatives: [ids.Basmatireis] },
                { name: 'Zucchini', alternatives: [ids.Tempeh] },
                { name: 'Sojasauce', alternatives: [ids.Naturtofu] },
            ]);
            await page.waitForURL(new RegExp(`/rezept/${recipeId}$`));
            await settle(page);
            const alt = (n: string) => alternativesOf(page, n);
            await expect(alt('Quinoa')).toContainText('Basmatireis');
            await expect(alt('Zucchini')).toContainText('Tempeh');
            await expect(alt('Sojasauce')).toContainText('Naturtofu');
            writeRaw('regression', 'F-13-keep', { sent, altRequests });
            expect(altRequests).toEqual([]);
        } finally {
            await deleteRecipe(page, recipeId);
        }
    });

    // ------------------------------------------------------------ F-14 mehrzeilige Schritte
    test('F-14 mehrzeiliger Schritt: anlegen, bearbeiten, unveraendert speichern, Umbruch sichtbar', async ({
        page,
    }) => {
        const multi = 'Brokkoli waschen.\nIn Röschen teilen.';
        await page.goto('/rezepte/erstellen');
        await settle(page);
        await page.locator('#name').fill(`E2E F-14 Mehrzeilig ${Date.now()}`);
        const row = page.locator('[data-ingredient-row]').first();
        await row.locator('[data-ingredient-amount]').fill('1 Kopf');
        await row.locator('[data-ingredient-name]').fill('Brokkoli');
        await page.locator('#name').click();
        await page
            .getByRole('button', { name: '+ weiteren Schritt hinzufügen' })
            .click();
        const areas = page.locator('[data-step-text]');
        await areas.nth(0).fill(multi);
        await areas.nth(1).fill('Kurz braten.');
        await Promise.all([
            page.waitForURL(/\/rezept\/\d+$/),
            page.getByRole('button', { name: 'Rezept speichern' }).click(),
        ]);
        const recipeId = Number(new URL(page.url()).pathname.split('/').pop());
        try {
            await settle(page);
            const steps = page
                .getByRole('list', { name: 'Zubereitung', exact: true })
                .getByRole('listitem')
                .locator('p');
            await expect(steps).toHaveCount(2);
            expect(
                await steps
                    .nth(0)
                    .evaluate((el) => (el as HTMLElement).innerText),
            ).toBe(multi);
            expect(
                await steps
                    .nth(0)
                    .evaluate((el) => getComputedStyle(el).whiteSpace),
            ).toBe('pre-line');

            // Bearbeiten: weiterhin 2 Schritte, Text identisch, unveraendert absenden
            await page.goto(`/rezept/${recipeId}/bearbeiten`);
            await settle(page);
            await expect(areas).toHaveCount(2);
            await expect(areas.nth(0)).toHaveValue(multi);
            await expect(areas.nth(1)).toHaveValue('Kurz braten.');
            const [update] = await Promise.all([
                page.waitForRequest(isUpdateOf(recipeId)),
                page
                    .getByRole('button', { name: 'Rezept aktualisieren' })
                    .click(),
            ]);
            const fields = sentFields(update);
            expect(fields['steps[0]']).toBe(multi);
            expect(fields['steps[1]']).toBe('Kurz braten.');
            expect(fields['steps[2]']).toBeUndefined();
            await page.waitForURL(new RegExp(`/rezept/${recipeId}$`));
            await settle(page);
            await expect(steps).toHaveCount(2);
            expect(
                await steps
                    .nth(0)
                    .evaluate((el) => (el as HTMLElement).innerText),
            ).toBe(multi);
            const multiBox = (await steps.nth(0).boundingBox())!;
            const singleBox = (await steps.nth(1).boundingBox())!;
            await page.screenshot({
                path: test.info().outputPath('F-14-detail.png'),
                fullPage: true,
            });
            writeRaw('regression', 'F-14', {
                recipeId,
                multiHeight: multiBox.height,
                singleHeight: singleBox.height,
            });
            expect(
                multiBox.height,
                'mehrzeiliger Schritt ist hoeher als ein einzeiliger',
            ).toBeGreaterThan(singleBox.height * 1.5);
        } finally {
            await deleteRecipe(page, recipeId);
        }
    });

    // ------------------------------------------------------------ F-15 Quelle
    test('F-15 Formular hat kein Feld "Quelle"; Import-Vorbelegung mit altem source-Feld funktioniert', async ({
        page,
        diag,
    }) => {
        for (const path of [
            '/rezepte/erstellen',
            `/rezept/${DATA.recipes.withImage.id}/bearbeiten`,
        ]) {
            await page.goto(path);
            await settle(page);
            await expect(page.locator('#source')).toHaveCount(0);
            await expect(page.getByLabel('Quelle')).toHaveCount(0);
            await expect(page.locator('[data-recipe-form]')).not.toContainText(
                'Quelle',
            );
        }
        await page.goto(`/rezept/${DATA.recipes.withImage.id}`);
        await settle(page);
        await expect(page.getByText('Quelle:')).toHaveCount(0);

        // Seeder: Import des Hosts enthaelt im Ergebnis noch `source`
        await page.goto(
            `/rezepte/erstellen?import=${DATA.importWithSource.id}`,
        );
        await settle(page);
        await expect(page.locator('#name')).toHaveValue(
            DATA.importWithSource.name,
        );
        const names = await page
            .locator('[data-ingredient-name]')
            .evaluateAll((els) =>
                els.map((e) => (e as HTMLInputElement).value),
            );
        expect(names).toEqual(['Brokkoli', 'Sojasauce']);
        await expect(page.locator('[data-step-text]')).toHaveCount(2);
        await expect(
            page.locator('[data-tag-chip][aria-pressed="true"]', {
                hasText: 'Abendessen',
            }),
        ).toHaveCount(1);
        await expect(page.locator('#source')).toHaveCount(0);
        expect([...diag.pageErrors, ...diag.consoleErrors]).toEqual([]);
    });
});

// ------------------------------------------------------------ F-39 Sammlungen (user2)
async function setupCollection(
    page: Page,
    name: string,
    recipeIds: number[],
): Promise<number> {
    const headers = await xsrfHeaders(page);
    const created = await page.request.post('/collections', {
        form: { name },
        headers,
        maxRedirects: 0,
    });
    expect(created.status()).toBe(302);
    await page.goto('/sammlungen');
    const props = await inertiaProps<{
        collections: { id: number; name: string }[];
    }>(page);
    const id = props.collections.find((c) => c.name === name)?.id;
    if (!id) throw new Error(`Sammlung ${name} nicht gefunden`);
    for (const recipeId of recipeIds) {
        const res = await page.request.post(`/collections/${id}/recipes`, {
            form: { recipe_id: String(recipeId) },
            headers,
            maxRedirects: 0,
        });
        expect(res.status()).toBe(302);
    }
    return id;
}

async function removeCollection(page: Page, id: number) {
    await page.request.delete(`/collections/${id}`, {
        headers: await xsrfHeaders(page),
        maxRedirects: 0,
    });
}

const R4 = 'Gebratener Räuchertofu mit Brokkoli';
const R5 = 'Quinoa-Bowl mit schwarzen Bohnen';

test.describe('Nutzer 2 – Slice 2 (F-39)', () => {
    test.use({ storageState: authFile('user2') });

    test('F-39 Entfernen-Modus: Kartenklick loescht nicht, Button -> Rueckfrage -> DELETE; Abbrechen sendet nichts; Tastatur', async ({
        page,
        diag,
    }) => {
        const collectionId = await setupCollection(
            page,
            `E2E F-39 ${Date.now()}`,
            [4, 5],
        );
        const requests = logRequests(page, /^\/collections\/\d+\/recipes\//);
        try {
            await page.goto(`/sammlung/${collectionId}`);
            await settle(page);
            await page
                .getByRole('button', { name: 'Rezepte entfernen' })
                .click();
            await expect(
                page.getByRole('button', { name: 'Bearbeitungsmodus beenden' }),
            ).toHaveAttribute('aria-pressed', 'true');
            await expect(page.locator('.collection-remove-hint')).toBeVisible();

            // Klick auf die Karte entfernt nichts
            const card4 = page.locator('.collection-recipe-item', {
                hasText: R4,
            });
            await card4.getByRole('heading', { name: R4 }).click();
            await page.waitForTimeout(500);
            expect(requests).toEqual([]);
            await expect(page.locator('.collection-recipe-item')).toHaveCount(
                2,
            );

            // Button -> Rueckfrage -> Abbrechen: nichts gesendet, Fokus zurueck
            const removeR4 = page.getByRole('button', {
                name: `Aus Sammlung entfernen: ${R4}`,
            });
            const cancelR4 = card4.getByRole('button', {
                name: `Abbrechen, „${R4}“ behalten`,
            });
            await removeR4.click();
            await expect(card4.getByText('Wirklich entfernen?')).toBeVisible();
            await expect(cancelR4).toBeFocused();
            await cancelR4.click();
            await expect(card4.getByText('Wirklich entfernen?')).toHaveCount(0);
            await expect(removeR4).toBeFocused();
            await page.waitForTimeout(300);
            expect(requests).toEqual([]);

            // Tastatur: Enter oeffnet die Rueckfrage, Shift+Tab auf "Ja", Enter entfernt
            await removeR4.focus();
            await page.keyboard.press('Enter');
            await expect(card4.getByText('Wirklich entfernen?')).toBeVisible();
            await page.keyboard.press('Shift+Tab');
            await expect(
                card4.getByRole('button', {
                    name: `Ja, „${R4}“ aus der Sammlung entfernen`,
                }),
            ).toBeFocused();
            const [res] = await Promise.all([
                page.waitForResponse(
                    (r) =>
                        r.request().method() === 'DELETE' &&
                        r.url().includes('/recipes/4'),
                ),
                page.keyboard.press('Enter'),
            ]);
            // removeRecipe leitet auf die Sammlung um (Inertia: 303 statt 302)
            expect(res.status()).toBe(303);
            await expect(page.locator('.collection-recipe-item')).toHaveCount(
                1,
            );
            await expect(
                page.locator('.collection-recipe-item', { hasText: R5 }),
            ).toHaveCount(1);

            // Serverseitig entfernt
            await page.reload();
            await settle(page);
            await expect(page.locator('.collection-recipe-item')).toHaveCount(
                1,
            );
            writeRaw('regression', 'F-39', { requests });
            expect(requests.filter((r) => r.method === 'DELETE')).toHaveLength(
                1,
            );
            expect([...diag.pageErrors, ...diag.consoleErrors]).toEqual([]);
        } finally {
            await removeCollection(page, collectionId);
        }
    });

    test('F-39 Fehler beim Entfernen wird an der Karte angezeigt', async ({
        page,
    }) => {
        const collectionId = await setupCollection(
            page,
            `E2E F-39 Fehler ${Date.now()}`,
            [4],
        );
        try {
            await page.route('**/collections/*/recipes/*', (route) =>
                route.request().method() === 'DELETE'
                    ? route.fulfill({
                          status: 500,
                          contentType: 'text/html',
                          body: 'Server Error',
                      })
                    : route.continue(),
            );
            await page.goto(`/sammlung/${collectionId}`);
            await settle(page);
            await page
                .getByRole('button', { name: 'Rezepte entfernen' })
                .click();
            await page
                .getByRole('button', { name: `Aus Sammlung entfernen: ${R4}` })
                .click();
            await page
                .getByRole('button', {
                    name: `Ja, „${R4}“ aus der Sammlung entfernen`,
                })
                .click();
            await expect(page.getByRole('alert')).toContainText(
                'Auf dem Server ist ein Fehler aufgetreten',
            );
            await expect(page.locator('.collection-recipe-item')).toHaveCount(
                1,
            );
        } finally {
            await page.unrouteAll({ behavior: 'ignoreErrors' });
            await removeCollection(page, collectionId);
        }
    });

    test.describe('mobil 390', () => {
        test.use(MOBILE_390);

        test('F-39 Entfernen-Button und Rueckfrage passen bei 390 px; Abbrechen sendet nichts', async ({
            page,
        }) => {
            const collectionId = await setupCollection(
                page,
                `E2E F-39 mobil ${Date.now()}`,
                [4],
            );
            const requests = logRequests(
                page,
                /^\/collections\/\d+\/recipes\//,
            );
            try {
                await page.goto(`/sammlung/${collectionId}`);
                await settle(page);
                await page
                    .getByRole('button', { name: 'Rezepte entfernen' })
                    .tap();
                await page
                    .getByRole('button', {
                        name: `Aus Sammlung entfernen: ${R4}`,
                    })
                    .tap();
                const card = page.locator('.collection-recipe-item', {
                    hasText: R4,
                });
                await expect(
                    card.getByText('Wirklich entfernen?'),
                ).toBeVisible();
                for (const b of await card.getByRole('button').all()) {
                    const box = (await b.boundingBox())!;
                    expect(box.x).toBeGreaterThanOrEqual(0);
                    expect(box.x + box.width).toBeLessThanOrEqual(390);
                }
                await page.screenshot({
                    path: test.info().outputPath('F-39-rueckfrage-390.png'),
                    fullPage: true,
                });
                await card
                    .getByRole('button', {
                        name: `Abbrechen, „${R4}“ behalten`,
                    })
                    .tap();
                await page.waitForTimeout(300);
                expect(requests).toEqual([]);
            } finally {
                await removeCollection(page, collectionId);
            }
        });
    });
});

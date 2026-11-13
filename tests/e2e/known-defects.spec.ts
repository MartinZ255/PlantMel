import { BASE_URL } from './support/env';
import {
    authFile,
    DATA,
    expect,
    measureLayout,
    settle,
    test,
} from './support/fixtures';
import { freshLogin, hitTest } from './support/helpers';
import { writeRaw } from './support/raw';

/**
 * Gezielte Pruefungen der Audit-Befunde (docs/modernization/FINDINGS.md).
 *
 * Jeder Test prueft das KORREKTE Sollverhalten. Bestaetigte Defekte sind mit `test.fail()`
 * markiert: Der Lauf bleibt gruen, solange der Defekt besteht; wird er behoben, schlaegt der
 * Test als "unexpectedly passed" fehl und die Markierung muss entfernt werden.
 * Beobachtete Messwerte werden zusaetzlich als Rohdaten abgelegt (kind = "defects").
 * Behobene Befunde wandern nach tests/e2e/regression.spec.ts (seit Slice 1a: F-01 bis F-04,
 * seit Slice 2: F-07).
 *
 * Datenveraenderungen: nur F-17 legt eine Sammlung fuer user2 an (nicht in der Baseline genutzt).
 * F-04 nutzt eine eigene Anmeldung.
 */

const MOBILE_390 = {
    viewport: { width: 390, height: 844 },
    isMobile: true,
    hasTouch: true,
} as const;
const MOBILE_320 = {
    viewport: { width: 320, height: 640 },
    isMobile: true,
    hasTouch: true,
} as const;

function intersects(
    a: { x: number; y: number; width: number; height: number },
    b: { x: number; y: number; width: number; height: number },
) {
    return (
        a.x < b.x + b.width &&
        a.x + a.width > b.x &&
        a.y < b.y + b.height &&
        a.y + a.height > b.y
    );
}

// ---------------------------------------------------------------------------------------------
test.describe('Gast', () => {
    test('F-21a Gast klickt Logo und bleibt auf einer oeffentlichen Seite', async ({
        page,
    }) => {
        test.fail(
            true,
            'F-21: Logo verlinkt route("home") (auth) => Redirect auf /login',
        );
        await page.goto('/rezepte');
        await settle(page);
        await page.locator('.app-header__logo').click();
        await page.waitForLoadState('load');
        const landed = new URL(page.url()).pathname;
        writeRaw('defects', 'F-21a', { landed });
        expect(landed).not.toBe('/login');
    });

    test.describe('mobil 390', () => {
        test.use(MOBILE_390);

        test('F-05 Such-Untermenue: Rezeptsuche/Zutatensuche sichtbar und klickbar', async ({
            page,
        }) => {
            // Zweite Ursache (unsichtbares User-Dropdown, F-04) ist seit Slice 1a behoben; der Drawer verdeckt die Links weiterhin (Slice 3).
            test.fail(
                true,
                'F-05: Untermenue liegt ausserhalb der Sidebar hinter dem Drawer',
            );
            await page.goto('/rezepte');
            await settle(page);
            await page.locator('.app-header__icon--menu').click();
            await page.locator('.sidebar-link--submenu-toggle').click();
            await page.waitForTimeout(400);
            const links = page.locator('.sidebar-submenu__item');
            const results: {
                name: string;
                visible: boolean;
                box: unknown;
                samples: {
                    x: number;
                    y: number;
                    hit: string;
                    hitsTarget: boolean;
                }[];
            }[] = [];
            for (const name of ['Rezeptsuche', 'Zutatensuche']) {
                const link = links.filter({ hasText: name });
                const visible = await link.isVisible();
                const box = await link.boundingBox();
                // elementFromPoint an 10 %, 50 % und 90 % der Linkbreite
                const samples = await link.evaluate((el) => {
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
                results.push({ name, visible, box, samples });
            }
            await page.screenshot({
                path: test.info().outputPath('F-05-mobil-390.png'),
            });
            writeRaw('defects', 'F-05', { results });
            for (const r of results) {
                expect(r.visible, `${r.name} sichtbar`).toBe(true);
                // Seit F-04 (Slice 1a) trifft der rechte Messpunkt den Link; links und Mitte verdeckt weiterhin der Drawer.
                // Sollverhalten ist ein vollstaendig bedienbarer Link, daher muessen alle Messpunkte treffen.
                expect(
                    r.samples.every((s) => s.hitsTarget),
                    `${r.name} erhaelt an allen Messpunkten den Klick`,
                ).toBe(true);
            }
        });

        test('F-29 Backdrop sichtbar, Tippen neben den Drawer schliesst ihn', async ({
            page,
        }) => {
            test.fail(
                true,
                'F-29: .sidebar-backdrop { display: none !important }',
            );
            await page.goto('/rezepte');
            await settle(page);
            await page.locator('.app-header__icon--menu').click();
            await page.waitForTimeout(400);
            const backdrop = page.locator('.sidebar-backdrop');
            const backdropCount = await backdrop.count();
            const backdropDisplay = backdropCount
                ? await backdrop.evaluate((el) => getComputedStyle(el).display)
                : null;
            const sidebarBox = await page.locator('.sidebar').boundingBox();
            const x = 375;
            const y = 500;
            const hitBefore = await page.evaluate(
                ([px, py]) => {
                    const e = document.elementFromPoint(px, py);
                    return e
                        ? `${e.tagName.toLowerCase()}.${String(e.className).trim().split(/\s+/).join('.')}`
                        : 'null';
                },
                [x, y],
            );
            await page.mouse.click(x, y);
            await page.waitForTimeout(400);
            const closed = await page
                .locator('.sidebar')
                .evaluate((el) => el.classList.contains('sidebar--closed'));
            writeRaw('defects', 'F-29', {
                backdropCount,
                backdropDisplay,
                sidebarBox,
                clickPoint: { x, y },
                hitBefore,
                closedAfterClick: closed,
                url: page.url(),
            });
            expect(backdropDisplay, 'Backdrop wird angezeigt').not.toBe('none');
            expect(closed, 'Drawer nach Tippen daneben geschlossen').toBe(true);
        });
    });

    test.describe('mobil 320', () => {
        test.use(MOBILE_320);

        test('F-32 langer Rezepttitel: kein horizontaler Overflow, Titel nicht abgeschnitten', async ({
            page,
        }) => {
            test.fail(
                true,
                'F-32: kein Scroll-Overflow (overflow-x: hidden), aber h1 wird abgeschnitten (kein overflow-wrap)',
            );
            await page.goto(`/rezept/${DATA.recipes.longTitle.id}`);
            await settle(page);
            const layout = await measureLayout(page);
            const h1 = await page
                .locator('h1')
                .first()
                .evaluate((el) => {
                    const r = el.getBoundingClientRect();
                    const s = getComputedStyle(el);
                    return {
                        right: Math.round(r.right),
                        width: Math.round(r.width),
                        scrollWidth: el.scrollWidth,
                        clientWidth: el.clientWidth,
                        overflowWrap: s.overflowWrap,
                        wordBreak: s.wordBreak,
                        hyphens: s.hyphens,
                    };
                });
            await page.screenshot({
                path: test.info().outputPath('F-32-langer-titel-320.png'),
            });
            writeRaw('defects', 'F-32', { layout, h1 });
            expect(
                layout.horizontalOverflow,
                'documentElement.scrollWidth <= innerWidth',
            ).toBe(false);
            expect(
                layout.overflowingElementCount,
                `ueberstehende Elemente: ${layout.overflowingElements.join(', ')}`,
            ).toBe(0);
            expect(layout.clippedH1, 'h1 nicht abgeschnitten').toBe(false);
        });
    });
});

// ---------------------------------------------------------------------------------------------
test.describe('Nutzer', () => {
    test.use({ storageState: authFile('user') });

    test('F-20 /settings/appearance rendert ohne Fehler', async ({
        page,
        diag,
    }) => {
        test.fail(true, 'F-20: Vue-Seite settings/Appearance fehlt');
        const res = await page.goto('/settings/appearance');
        await settle(page);
        writeRaw('defects', 'F-20a', {
            status: res?.status(),
            pageErrors: diag.pageErrors,
            title: await page.title(),
        });
        expect(res?.status()).toBe(200);
        expect(diag.pageErrors).toEqual([]);
    });

    test('F-20 /settings/two-factor rendert nach Passwortbestaetigung ohne Fehler', async ({
        page,
        diag,
    }) => {
        test.fail(true, 'F-20: Vue-Seite settings/TwoFactor fehlt');
        const first = await page.goto('/settings/two-factor');
        await settle(page);
        const redirectedTo = new URL(page.url()).pathname;
        let confirmStatus: number | null = null;
        if (redirectedTo === '/user/confirm-password') {
            await page.locator('#password').fill(DATA.password);
            const [confirm] = await Promise.all([
                page.waitForResponse(
                    (r) =>
                        r.request().method() === 'POST' &&
                        new URL(r.url()).pathname === '/user/confirm-password',
                    { timeout: 15_000 },
                ),
                page
                    .locator('form button[type="submit"], form button')
                    .first()
                    .click(),
            ]);
            confirmStatus = confirm.status();
            await page.waitForTimeout(1000);
        }
        const res = await page.goto('/settings/two-factor');
        await settle(page);
        writeRaw('defects', 'F-20b', {
            firstStatus: first?.status(),
            redirectedTo,
            confirmStatus,
            status: res?.status(),
            finalPath: new URL(page.url()).pathname,
            pageErrors: diag.pageErrors,
        });
        expect(res?.status()).toBe(200);
        expect(new URL(page.url()).pathname).toBe('/settings/two-factor');
        expect(diag.pageErrors).toEqual([]);
    });

    test('F-42 html[lang] ist "de"', async ({ page }) => {
        test.fail(true, 'F-42: config/app.php locale-Default "en"');
        await page.goto('/home');
        const lang = await page.locator('html').getAttribute('lang');
        writeRaw('defects', 'F-42a', { lang });
        expect(lang).toBe('de');
    });

    test('F-42 jede Seite hat genau ein h1 und einen eigenen Titel', async ({
        page,
    }) => {
        test.fail(true, 'F-42: fehlende h1 und Head-Titel');
        const paths = [
            '/',
            '/rezepte',
            `/rezept/${DATA.recipes.withImage.id}`,
            '/zutatensuche',
            '/home',
            '/sammlungen',
            '/sammlungen/erstellen',
            `/sammlung/${DATA.collections.filled.id}`,
            '/settings',
            '/settings/profile',
            '/settings/password',
        ];
        const rows: { path: string; title: string; h1: number }[] = [];
        for (const p of paths) {
            await page.goto(p);
            await settle(page);
            rows.push({
                path: p,
                title: await page.title(),
                h1: await page.locator('h1').count(),
            });
        }
        writeRaw('defects', 'F-42b', { rows });
        const titles = rows.map((r) => r.title);
        expect(
            rows.filter((r) => r.h1 !== 1),
            'Seiten ohne genau ein h1',
        ).toEqual([]);
        expect(
            titles.filter((t) => t === 'Laravel'),
            'Seiten mit Fallback-Titel',
        ).toEqual([]);
        expect(new Set(titles).size, 'Titel eindeutig').toBe(titles.length);
    });
});

test.describe('Nutzer 2 (eigene Daten/Session)', () => {
    test('F-17 Sammlung anlegen zeigt sichtbare Erfolgsmeldung', async ({
        browser,
    }) => {
        test.fail(true, 'F-17: Flash-Meldungen werden nicht geteilt/angezeigt');
        const context = await browser.newContext({
            baseURL: BASE_URL,
            storageState: authFile('user2'),
            viewport: { width: 1440, height: 900 },
        });
        await context.route(
            (url) => url.origin !== BASE_URL,
            (route) => route.abort('blockedbyclient'),
        );
        const page = await context.newPage();
        await page.goto('/sammlungen/erstellen');
        await settle(page);
        await page.locator('#collection-name').fill('E2E Testsammlung');
        await Promise.all([
            page.waitForURL('**/sammlungen'),
            page.locator('form button[type="submit"]').click(),
        ]);
        await settle(page);
        const listed = await page.getByText('E2E Testsammlung').count();
        const successVisible = await page
            .getByText('Sammlung wurde erstellt')
            .isVisible()
            .catch(() => false);
        await page.screenshot({
            path: test.info().outputPath('F-17-nach-anlegen.png'),
        });
        writeRaw('defects', 'F-17', {
            landed: new URL(page.url()).pathname,
            listed,
            successVisible,
        });
        await context.close();
        expect(listed, 'Sammlung wurde angelegt').toBeGreaterThan(0);
        expect(successVisible, 'Erfolgsmeldung sichtbar').toBe(true);
    });

    test('F-21b nach dem Login landet man auf /home', async ({ browser }) => {
        test.fail(true, 'F-21: fortify.home = "/"');
        const { context, landedOn } = await freshLogin(
            browser,
            DATA.users.user.email,
        );
        writeRaw('defects', 'F-21b', { landedOn });
        await context.close();
        expect(landedOn).toBe('/home');
    });
});

// ---------------------------------------------------------------------------------------------
test.describe('Host', () => {
    test.use({ storageState: authFile('host') });

    test('F-06 Rezept nur mit Titel speichern zeigt Fehler an Zutat/Schritt', async ({
        page,
    }) => {
        test.fail(
            true,
            'F-06: RecipeForm zeigt nur Fehler fuer name/description/image',
        );
        await page.goto('/rezepte/erstellen');
        await settle(page);
        await page.locator('#name').fill('E2E Nur Titel');
        const inertiaErrors: Record<string, string>[] = [];
        page.on('response', async (res) => {
            if (
                res.request().headers()['x-inertia'] &&
                res.request().method() === 'GET'
            ) {
                try {
                    const body = await res.json();
                    if (body?.props?.errors)
                        inertiaErrors.push(body.props.errors);
                } catch {
                    /* kein JSON */
                }
            }
        });
        const [post] = await Promise.all([
            page.waitForResponse(
                (r) =>
                    r.request().method() === 'POST' &&
                    new URL(r.url()).pathname === '/recipes',
            ),
            page.getByRole('button', { name: 'Rezept speichern' }).click(),
        ]);
        await settle(page);
        const visibleErrors = await page
            .locator('.form-field__error:visible')
            .allTextContents();
        const scoped = page
            .locator('.recipe-form__section--wide')
            .locator('.form-field__error, [class*="error"]');
        const scopedVisible = await scoped.filter({ visible: true }).count();
        await page.screenshot({
            path: test.info().outputPath('F-06-nach-speichern.png'),
            fullPage: true,
        });
        writeRaw('defects', 'F-06', {
            postStatus: post.status(),
            serverErrors: inertiaErrors,
            visibleErrors,
            scopedVisible,
            url: page.url(),
        });
        expect(
            scopedVisible,
            'sichtbare Fehlermeldung bei Zutaten/Schritten',
        ).toBeGreaterThan(0);
    });

    test('F-38 Drag-Griff der Schritte ist per Tastatur bedienbar', async ({
        page,
    }) => {
        test.fail(true, 'F-38: Umsortieren nur per Pointer-Events');
        await page.goto('/rezepte/erstellen');
        await settle(page);
        await page
            .getByRole('button', { name: '+ weiteren Schritt hinzufügen' })
            .click();
        const areas = page.locator('.steps-builder__textarea');
        await areas.nth(0).fill('Schritt A');
        await areas.nth(1).fill('Schritt B');
        const handle = page.locator('.steps-builder__handle').first();
        await handle.focus();
        const focused = await handle.evaluate(
            (el) => el === document.activeElement,
        );
        for (const key of [
            'Space',
            'ArrowDown',
            'Space',
            'Alt+ArrowDown',
            'Enter',
            'ArrowDown',
        ]) {
            await page.keyboard.press(key);
        }
        const order = await areas.evaluateAll((els) =>
            els.map((e) => (e as HTMLTextAreaElement).value),
        );
        writeRaw('defects', 'F-38', {
            handleFocusable: focused,
            orderAfterKeys: order,
        });
        expect(order, 'Schritt A nach unten verschoben').toEqual([
            'Schritt B',
            'Schritt A',
        ]);
    });

    test.describe('1024', () => {
        test.use({ viewport: { width: 1024, height: 768 } });

        test('F-31 Aktionsbuttons ueberlappen den Rezepttitel nicht', async ({
            page,
        }) => {
            test.fail(
                true,
                'F-31: absolut positionierte Aktionen ueberlagern den Titel (mit und ohne Bild)',
            );
            const rows: Record<string, unknown>[] = [];
            for (const r of [
                DATA.recipes.withImage,
                DATA.recipes.longTitle,
                DATA.recipes.withoutImage,
            ]) {
                await page.goto(`/rezept/${r.id}`);
                await settle(page);
                const actions = await page
                    .locator('.recipe-actions')
                    .boundingBox();
                const title = await page.locator('h1.title').boundingBox();
                const desc = await page
                    .locator('.header-content .description')
                    .boundingBox()
                    .catch(() => null);
                rows.push({
                    id: r.id,
                    actions,
                    title,
                    overlapsTitle:
                        !!actions && !!title && intersects(actions, title),
                    overlapsDescription:
                        !!actions && !!desc && intersects(actions, desc),
                });
                await page.screenshot({
                    path: test
                        .info()
                        .outputPath(`F-31-rezept-${r.id}-1024.png`),
                });
            }
            writeRaw('defects', 'F-31', { rows });
            expect(
                rows.filter((r) => r.overlapsTitle).map((r) => r.id),
                'Rezepte mit Ueberlappung Titel',
            ).toEqual([]);
        });
    });

    for (const vp of [MOBILE_320, MOBILE_390]) {
        test.describe(`mobil ${vp.viewport.width}`, () => {
            test.use(vp);

            const openModal = async (page: import('@playwright/test').Page) => {
                await page.goto(
                    `/rezept/${DATA.recipes.withImage.id}/bearbeiten`,
                );
                await settle(page);
                const btn = page
                    .locator(
                        'button[aria-label="Alternativzutaten bearbeiten"]',
                    )
                    .first();
                await btn.scrollIntoViewIfNeeded();
                await btn.click();
                const title = page.locator('.alternatives-modal .modal__title');
                await expect(title).toBeVisible();
                await page.waitForTimeout(300);
                return title;
            };

            test(`F-30 Alternativen-Modal direkt nach dem Oeffnen: Titel nicht vom Header verdeckt (${vp.viewport.width})`, async ({
                page,
            }) => {
                const title = await openModal(page);
                const hit = await hitTest(title);
                const titleBox = await title.boundingBox();
                const headerBox = await page
                    .locator('.app-header')
                    .boundingBox();
                const modalBox = await page
                    .locator('.alternatives-modal')
                    .boundingBox();
                await page.screenshot({
                    path: test
                        .info()
                        .outputPath(`F-30-modal-${vp.viewport.width}.png`),
                });
                writeRaw('defects', `F-30-${vp.viewport.width}`, {
                    hit,
                    titleBox,
                    headerBox,
                    modalBox,
                });
                expect(
                    hit.hitsTarget,
                    `Element am Titel-Mittelpunkt: ${hit.hit}`,
                ).toBe(true);
            });

            test(`F-30 Alternativen-Modal nach Zurueckscrollen (keine Scroll-Sperre): Titel nicht verdeckt (${vp.viewport.width})`, async ({
                page,
            }) => {
                test.fail(
                    true,
                    'F-30: ohne Scroll-Sperre laesst sich der Header (z-index 1100) ueber das Modal (z-index 40) schieben',
                );
                const title = await openModal(page);
                // Modal offen lassen und an den Seitenanfang scrollen (Scroll-Container ist <body>)
                await page.evaluate(() => {
                    document.body.scrollTo(0, 0);
                    window.scrollTo(0, 0);
                });
                await page.waitForTimeout(300);
                const hit = await hitTest(title);
                const titleBox = await title.boundingBox();
                const headerBox = await page
                    .locator('.app-header')
                    .boundingBox();
                await page.screenshot({
                    path: test
                        .info()
                        .outputPath(
                            `F-30-modal-gescrollt-${vp.viewport.width}.png`,
                        ),
                });
                writeRaw('defects', `F-30-scrolled-${vp.viewport.width}`, {
                    hit,
                    titleBox,
                    headerBox,
                });
                expect(
                    hit.hitsTarget,
                    `Element am Titel-Mittelpunkt: ${hit.hit}`,
                ).toBe(true);
                // Seit Slice 1a (F-04) ist bei 390 px der Titel-Mittelpunkt frei (vorher: unsichtbares
                // User-Dropdown); der Header (z-index 1100) liegt aber weiterhin ueber dem oberen Titelrand.
                expect(
                    titleBox && headerBox
                        ? intersects(titleBox, headerBox)
                        : true,
                    'Header ueberlappt den Modaltitel nicht',
                ).toBe(false);
            });
        });
    }
});

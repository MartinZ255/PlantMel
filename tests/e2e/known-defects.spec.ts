import {
    authFile,
    DATA,
    expect,
    measureLayout,
    settle,
    test,
} from './support/fixtures';
import { hitTest } from './support/helpers';
import { writeRaw } from './support/raw';

/**
 * Gezielte Pruefungen der Audit-Befunde (docs/modernization/FINDINGS.md).
 *
 * Jeder Test prueft das KORREKTE Sollverhalten. Bestaetigte Defekte sind mit `test.fail()`
 * markiert: Der Lauf bleibt gruen, solange der Defekt besteht; wird er behoben, schlaegt der
 * Test als "unexpectedly passed" fehl und die Markierung muss entfernt werden.
 * Beobachtete Messwerte werden zusaetzlich als Rohdaten abgelegt (kind = "defects").
 * Behobene Befunde wandern nach tests/e2e/regression.spec.ts (seit Slice 1a: F-01 bis F-04,
 * seit Slice 2: F-07) bzw. tests/e2e/regression-shell.spec.ts (Slice 3: F-05, F-17, F-21a/b,
 * F-29, F-42a/b; die F-20-Tests entfielen mit Entscheidung E4 und wurden dort ersetzt).
 *
 * Datenveraenderungen: keine.
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

const alternativesDialog = (page: import('@playwright/test').Page) =>
    page.getByRole('dialog', { name: /^Alternativen für/ });

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
    test.describe('mobil 320', () => {
        test.use(MOBILE_320);

        test('F-32 langer Rezepttitel: kein horizontaler Overflow, Titel nicht abgeschnitten', async ({
            page,
        }) => {
            // F-32 behoben in Slice 3 (overflow-wrap/hyphens in Inhaltsbereichen).
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
test.describe('Host', () => {
    test.use({ storageState: authFile('host') });

    test('F-06 Rezept nur mit Titel speichern zeigt Fehler an Zutat/Schritt', async ({
        page,
    }) => {
        // F-06 behoben in Slice 3; ausfuehrlich in regression-forms.spec.ts.
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
        // Gate C2: erst messen, wenn die Server-Fehler gerendert sind (Inertia-Folgeseite);
        // der alte Selektor [class*="error"] traf auch ohne Meldung Elemente.
        await page
            .locator('[data-error-summary]')
            .waitFor({ state: 'visible', timeout: 10_000 })
            .catch(() => undefined);
        const visibleErrors = await page
            .locator('[data-field-error]:visible')
            .allTextContents();
        // Gate C2: Fehler in den breiten Abschnitten Zutaten/Schritte/Notizen
        const scoped = page.locator(
            [
                '[data-form-section="ingredients"] [data-field-error]',
                '[data-form-section="steps"] [data-field-error]',
                '[data-form-section="notes"] [data-field-error]',
            ].join(', '),
        );
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
        const areas = page.locator('[data-step-text]');
        await areas.nth(0).fill('Schritt A');
        await areas.nth(1).fill('Schritt B');
        const handle = page.locator('[data-step-handle]').first();
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
            // F-31 behoben in Slice 3; weitere Breiten in regression-forms.spec.ts.
            const rows: Record<string, unknown>[] = [];
            for (const r of [
                DATA.recipes.withImage,
                DATA.recipes.longTitle,
                DATA.recipes.withoutImage,
            ]) {
                await page.goto(`/rezept/${r.id}`);
                await settle(page);
                // Gate C: Aktionen ueber Rollen; Beschreibung = Absatz direkt nach dem h1
                const actionBoxes = await Promise.all(
                    [
                        page.getByRole('button', {
                            name: 'Zur Sammlung hinzufügen',
                        }),
                        page.getByRole('link', { name: 'Rezept bearbeiten' }),
                        page.getByRole('button', { name: 'Rezept löschen' }),
                    ].map((l) => l.boundingBox()),
                );
                const valid = actionBoxes.filter(
                    (b): b is NonNullable<typeof b> => !!b,
                );
                const actions = valid.length
                    ? {
                          x: Math.min(...valid.map((b) => b.x)),
                          y: Math.min(...valid.map((b) => b.y)),
                          width:
                              Math.max(...valid.map((b) => b.x + b.width)) -
                              Math.min(...valid.map((b) => b.x)),
                          height:
                              Math.max(...valid.map((b) => b.y + b.height)) -
                              Math.min(...valid.map((b) => b.y)),
                      }
                    : null;
                const title = await page.locator('h1').boundingBox();
                const desc = await page
                    .locator('h1 + p')
                    .boundingBox({ timeout: 1000 })
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
                // Gate C2: gemeinsamer Dialog-Baustein, Titel = Ueberschrift des Dialogs
                const title = alternativesDialog(page).getByRole('heading', {
                    name: /^Alternativen für/,
                });
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
                    .locator('#app header')
                    .first()
                    .boundingBox();
                const modalBox = await alternativesDialog(page).boundingBox();
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
                // F-30 behoben in Slice 3: Modals liegen ueber der Shell (z-index 1300+) und
                // sperren das Scrollen des Hintergrunds.
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
                    .locator('#app header')
                    .first()
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
                // Frueher pruefte dieser Test nur die Geometrie (Titel- und Header-Box schneiden sich).
                // Seit die Modals ueber der Shell liegen, ist das kein Verdecken mehr: Der Header liegt
                // dann hinter dem Modal-Hintergrund. Geprueft wird deshalb, welches Element am oberen
                // Titelrand tatsaechlich oben liegt.
                const topEdgeInModal = await title.evaluate((el) => {
                    const r = el.getBoundingClientRect();
                    const top = document.elementFromPoint(
                        r.left + r.width / 2,
                        r.top + 2,
                    );
                    const dialog = el.closest('[role="dialog"]');
                    return (
                        !!top &&
                        !!dialog &&
                        top.closest('[role="dialog"]') === dialog
                    );
                });
                expect(
                    topEdgeInModal,
                    'oberer Titelrand liegt im Modal, nicht unter dem Header',
                ).toBe(true);
            });
        });
    }
});

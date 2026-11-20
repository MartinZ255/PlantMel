import AxeBuilder from '@axe-core/playwright';
import type { Locator, Page, Request } from '@playwright/test';
import path from 'node:path';
import { authFile, DATA, expect, settle, test } from './support/fixtures';
import { deleteRecipe } from './support/helpers';
import { writeRaw } from './support/raw';

/**
 * Gate C2 – Rezeptformular (Erstellen/Bearbeiten), TikTok-Import-Fortschritt
 * und die drei Formular-Dialoge im Stil „Natur & organisch“.
 *
 * Prueft, dass beim Umbau nichts verloren ging, und die neuen Zusagen:
 * - Bedienung: Autocomplete (Blur/Escape, F-36), mehrzeilige Schritte (ADR-001),
 *   Sterne-Eingabe per Tastatur, Kategorien als Umschalter, Kapitelnavigation
 * - Dialoge auf `ui/Dialog.vue`: Fokusfalle, Escape, Fokus zurueck, Entwurf
 *   der Alternativen wird bei Escape verworfen
 * - Trefferflaechen >= 44 px fuer Griffe und Knoepfe
 * - kein horizontales Scrollen bei 320 px
 * - axe: 0 Verstoesse (alle Regeln) auf Erstellen/Bearbeiten bei 390 und 1440 px
 *
 * Datenveraenderungen: keine. Absendungen werden abgefangen (`page.route`),
 * bevor sie den Server erreichen.
 *
 * Vorschau-Screenshots fuer die Abnahme entstehen nur mit
 * `GATE_C2_PREVIEW=1` (docs/modernization/gate-c2-vorschau/forms-*.png).
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

const CREATE = '/rezepte/erstellen';
const EDIT = `/rezept/${DATA.recipes.withImage.id}/bearbeiten`;

const isRecipeSubmit = (r: Request) =>
    r.method() === 'POST' &&
    (new URL(r.url()).pathname === '/recipes' ||
        /^\/rezept\/\d+$/.test(new URL(r.url()).pathname));

/** Faengt jedes Absenden des Rezeptformulars ab (nichts erreicht den Server). */
async function blockSubmits(page: Page) {
    const bodies: string[] = [];
    await page.route(
        (url) =>
            url.pathname === '/recipes' || /^\/rezept\/\d+$/.test(url.pathname),
        (route) => {
            if (route.request().method() !== 'POST') return route.continue();
            bodies.push(route.request().postData() ?? '');
            return route.abort('blockedbyclient');
        },
    );
    return bodies;
}

const ingredientRows = (page: Page) => page.locator('[data-ingredient-row]');
const stepTexts = (page: Page) => page.locator('[data-step-text]');

/** Fokus bleibt bei `count` Tab-Druecken im Dialog. */
async function expectFocusTrapped(page: Page, dialog: Locator, count: number) {
    for (const key of ['Tab', 'Shift+Tab']) {
        for (let i = 0; i < count; i++) {
            await page.keyboard.press(key);
            expect(
                await dialog.evaluate((el) =>
                    el.contains(document.activeElement),
                ),
                `${key} #${i + 1}: Fokus im Dialog`,
            ).toBe(true);
        }
    }
}

// =============================================================================================
// Bedienung des Formulars
// =============================================================================================
test.describe('Gate C2 – Rezeptformular: Bedienung', () => {
    test.use({ storageState: authFile('host') });

    test('Autocomplete: Vorschlaege filtern, Auswahl per Maus, schliesst bei Escape und Blur (F-36)', async ({
        page,
        diag,
    }) => {
        await page.goto(CREATE);
        await settle(page);
        const name = ingredientRows(page)
            .first()
            .locator('[data-ingredient-name]');
        const suggestions = page.locator('[data-ingredient-suggestions]');

        await name.fill('linsen');
        await expect(suggestions).toBeVisible();
        await expect(
            suggestions.getByRole('button', { name: 'Rote Linsen' }),
        ).toBeVisible();
        // gefiltert: Brokkoli passt nicht zu "linsen"
        await expect(
            suggestions.getByRole('button', { name: 'Brokkoli' }),
        ).toHaveCount(0);

        // Escape schliesst, Fokus bleibt im Feld
        await name.press('Escape');
        await expect(suggestions).toHaveCount(0);
        await expect(name).toBeFocused();

        // erneut tippen oeffnet; Blur schliesst
        await name.press('End');
        await page.keyboard.type(' ');
        await page.keyboard.press('Backspace');
        await expect(suggestions).toBeVisible();
        await page.locator('#name').click();
        await expect(suggestions).toHaveCount(0);

        // Auswahl per Maus uebernimmt den Namen und schliesst
        await name.click();
        await suggestions.getByRole('button', { name: 'Rote Linsen' }).click();
        await expect(name).toHaveValue('Rote Linsen');
        await expect(suggestions).toHaveCount(0);
        // bekannte Zutat: Stift statt "neu anlegen"
        await expect(
            ingredientRows(page)
                .first()
                .getByRole('button', { name: 'Zutat bearbeiten' }),
        ).toBeVisible();
        expect(diag.pageErrors).toEqual([]);
    });

    test('mehrzeiliger Schritt (ADR-001): Enter fuegt eine Zeile ein, sendet nichts; gesendet wird EIN Schritt mit Umbruch', async ({
        page,
    }) => {
        const bodies = await blockSubmits(page);
        await page.goto(CREATE);
        await settle(page);
        const step = stepTexts(page).first();
        await step.click();
        await page.keyboard.type('Zwiebeln schneiden.');
        await page.keyboard.press('Enter');
        await page.keyboard.type('Dann anbraten.');
        await page.waitForTimeout(300);
        expect(bodies, 'Enter im Schritt sendet nicht').toEqual([]);
        await expect(step).toHaveValue('Zwiebeln schneiden.\nDann anbraten.');

        await page.locator('#name').fill('E2E Gate C2 mehrzeilig');
        await ingredientRows(page)
            .first()
            .locator('[data-ingredient-name]')
            .fill('Brokkoli');
        await page.locator('#name').click();
        await Promise.all([
            page.waitForRequest(isRecipeSubmit),
            page.getByRole('button', { name: 'Rezept speichern' }).click(),
        ]);
        // der Route-Handler laeuft nach dem request-Ereignis: auf ihn warten
        await expect.poll(() => bodies.length).toBe(1);
        const body = bodies[0];
        expect(body).toContain('name="steps[0]"');
        expect(body).not.toContain('name="steps[1]"');
        expect(body).toMatch(/Zwiebeln schneiden\.\r?\nDann anbraten\./);
    });

    test('Schritte und Zutaten: hinzufuegen, entfernen, per Griff umsortieren', async ({
        page,
    }) => {
        await page.goto(CREATE);
        await settle(page);
        // einzelne Zeile: kein Griff, kein Entfernen
        await expect(page.locator('[data-step-handle]')).toHaveCount(0);
        await expect(page.locator('[data-ingredient-handle]')).toHaveCount(0);

        await page
            .getByRole('button', { name: '+ weiteren Schritt hinzufügen' })
            .click();
        await page
            .getByRole('button', { name: '+ weiteren Schritt hinzufügen' })
            .click();
        await expect(stepTexts(page)).toHaveCount(3);
        await stepTexts(page).nth(0).fill('Schritt A');
        await stepTexts(page).nth(1).fill('Schritt B');
        await stepTexts(page).nth(2).fill('Schritt C');
        await expect(stepTexts(page).nth(1)).toHaveAccessibleName('Schritt 2');

        // C nach oben ziehen
        const handle = page.locator('[data-step-handle]').nth(2);
        await handle.scrollIntoViewIfNeeded();
        const hb = (await handle.boundingBox())!;
        const tb = (await page
            .locator('[data-step-row]')
            .first()
            .boundingBox())!;
        await page.mouse.move(hb.x + hb.width / 2, hb.y + hb.height / 2);
        await page.mouse.down();
        await page.mouse.move(hb.x + hb.width / 2, tb.y + 2, { steps: 15 });
        await page.mouse.up();
        expect(
            await stepTexts(page).evaluateAll((els) =>
                els.map((e) => (e as HTMLTextAreaElement).value),
            ),
        ).toEqual(['Schritt C', 'Schritt A', 'Schritt B']);

        await page.getByRole('button', { name: 'Schritt 2 entfernen' }).click();
        expect(
            await stepTexts(page).evaluateAll((els) =>
                els.map((e) => (e as HTMLTextAreaElement).value),
            ),
        ).toEqual(['Schritt C', 'Schritt B']);

        await page.getByRole('button', { name: '+ weitere Zutat' }).click();
        await expect(ingredientRows(page)).toHaveCount(2);
        await page.getByRole('button', { name: 'Zutat 2 entfernen' }).click();
        await expect(ingredientRows(page)).toHaveCount(1);
    });

    test('Bewertung: EINE Sterne-Eingabe "Geschmack", Pfeiltasten, Wert als Text, "Bewertung entfernen"; nur dieser Wert wird gesendet', async ({
        page,
    }) => {
        const bodies = await blockSubmits(page);
        await page.goto(CREATE);
        await settle(page);
        const section = page.locator('[data-form-section="ratings"]');
        // keine Liste von Dimensionen mehr
        const groups = section.getByRole('radiogroup');
        await expect(groups).toHaveCount(1);
        const first = section.getByRole('radiogroup', { name: 'Geschmack' });
        await expect(first).toBeVisible();
        for (const old of ['Schwierigkeit', 'Aufwand', 'Gesamt'])
            await expect(section.getByText(old, { exact: true })).toHaveCount(
                0,
            );
        const radios = first.getByRole('radio');
        await expect(radios).toHaveCount(5);

        await radios.nth(0).focus();
        await page.keyboard.press('Space');
        await page.keyboard.press('ArrowRight');
        await page.keyboard.press('ArrowRight');
        await expect(radios.nth(2)).toBeChecked();
        await expect(section).toContainText('3 von 5 Sternen gewählt');

        const groupId = await first
            .locator('xpath=ancestor::fieldset[1]')
            .getAttribute('id');
        const dimId = groupId!.replace('rating-', '');

        await page.locator('#name').fill('E2E Gate C2 Bewertung');
        await ingredientRows(page)
            .first()
            .locator('[data-ingredient-name]')
            .fill('Brokkoli');
        await stepTexts(page).first().fill('Braten.');
        await Promise.all([
            page.waitForRequest(isRecipeSubmit),
            page.getByRole('button', { name: 'Rezept speichern' }).click(),
        ]);
        await expect.poll(() => bodies.length).toBe(1);
        expect(bodies[0]).toMatch(
            new RegExp(`name="ratings\\[${dimId}\\]"\\r?\\n\\r?\\n3\\r?\\n`),
        );
        expect(
            bodies[0].match(/name="ratings\[\d+\]"/g),
            'genau ein Bewertungswert',
        ).toHaveLength(1);

        // zuruecksetzen: keine Bewertung = leerer Wert (Server: null)
        await section
            .getByRole('button', { name: 'Bewertung entfernen' })
            .click();
        await expect(radios.nth(2)).not.toBeChecked();
        await expect(section).toContainText('Noch keine Bewertung gewählt');
        await Promise.all([
            page.waitForRequest(isRecipeSubmit),
            page.getByRole('button', { name: 'Rezept speichern' }).click(),
        ]);
        await expect.poll(() => bodies.length).toBe(2);
        expect(bodies[1]).toMatch(
            new RegExp(`name="ratings\\[${dimId}\\]"\\r?\\n\\r?\\n\\r?\\n`),
        );
    });

    test('Bewertung "Geschmack" wird gespeichert und ist beim Bearbeiten vorbelegt', async ({
        page,
    }) => {
        // legt ein Wegwerf-Rezept in der E2E-Datenbank an und loescht es danach wieder
        await page.goto(CREATE);
        await settle(page);
        await page.locator('#name').fill('E2E Gate C2 Geschmack');
        await ingredientRows(page)
            .first()
            .locator('[data-ingredient-name]')
            .fill('Brokkoli');
        await page.locator('#name').click();
        await stepTexts(page).first().fill('Braten.');
        const taste = page.getByRole('radiogroup', { name: 'Geschmack' });
        await taste.getByRole('radio').first().focus();
        await page.keyboard.press('Space');
        for (let i = 0; i < 3; i++) await page.keyboard.press('ArrowRight');
        await expect(taste.getByRole('radio').nth(3)).toBeChecked();
        await Promise.all([
            page.waitForURL(/\/rezept\/\d+$/),
            page.getByRole('button', { name: 'Rezept speichern' }).click(),
        ]);
        const id = Number(new URL(page.url()).pathname.split('/').pop());
        expect(id).toBeGreaterThan(0);
        try {
            await page.goto(`/rezept/${id}/bearbeiten`);
            await settle(page);
            const radios = page
                .getByRole('radiogroup', { name: 'Geschmack' })
                .getByRole('radio');
            await expect(radios.nth(3)).toBeChecked();
            await expect(
                page.locator('[data-form-section="ratings"]'),
            ).toContainText('4 von 5 Sternen gewählt');
        } finally {
            await deleteRecipe(page, id);
        }
    });

    test('Kategorien: Umschalter mit aria-pressed; Kapitelnavigation fuehrt zu den Abschnitten (1440)', async ({
        page,
    }) => {
        await page.goto(CREATE);
        await settle(page);
        const group = page.getByRole('group', { name: 'Kategorien' });
        const chip = group.getByRole('button', { name: 'Dessert' });
        await expect(chip).toHaveAttribute('aria-pressed', 'false');
        await chip.click();
        await expect(chip).toHaveAttribute('aria-pressed', 'true');
        await chip.press('Enter');
        await expect(chip).toHaveAttribute('aria-pressed', 'false');

        const nav = page.getByRole('navigation', {
            name: 'Abschnitte des Formulars',
        });
        await expect(nav).toBeVisible();
        const links = nav.getByRole('link');
        expect(await links.count()).toBeGreaterThanOrEqual(6);
        for (const href of await links.evaluateAll((els) =>
            els.map((e) => e.getAttribute('href')!),
        )) {
            await expect(
                page.locator(`section${href}`),
                `Ziel ${href}`,
            ).toHaveCount(1);
        }
        // genau ein h1, Abschnitte als h2
        await expect(page.locator('h1')).toHaveCount(1);
        expect(
            await page.locator('[data-form-section] h2').count(),
        ).toBeGreaterThanOrEqual(6);
    });
});

// =============================================================================================
// Trefferflaechen >= 44 px (390 px, Touch)
// =============================================================================================
test.describe('Gate C2 – Trefferflaechen (390)', () => {
    test.use({ storageState: authFile('host'), ...MOBILE_390 });

    test('Griffe, Zeilen-Knoepfe, Kategorien und Hauptaktionen sind mindestens 44 x 44 px', async ({
        page,
    }) => {
        await page.goto(EDIT);
        await settle(page);
        await page.getByRole('button', { name: '+ weitere Zutat' }).click();
        await page
            .getByRole('button', { name: '+ weiteren Schritt hinzufügen' })
            .click();
        const targets = page.locator(
            [
                '[data-ingredient-handle]',
                '[data-step-handle]',
                '[data-ingredient-row] button',
                '[data-tag-chip]',
                '[data-recipe-form] button[type="submit"]',
            ].join(', '),
        );
        const sizes = await targets.evaluateAll((els) =>
            els
                .filter((e) => e.getClientRects().length > 0)
                .map((e) => {
                    const r = e.getBoundingClientRect();
                    return {
                        label:
                            e.getAttribute('aria-label') ??
                            (e.textContent ?? '').trim(),
                        w: Math.round(r.width),
                        h: Math.round(r.height),
                    };
                }),
        );
        writeRaw('regression', 'gate-c2-targets-390', { sizes });
        expect(sizes.length).toBeGreaterThan(8);
        const small = sizes.filter((s) => s.w < 44 || s.h < 44);
        expect(small, JSON.stringify(small)).toEqual([]);
    });
});

// =============================================================================================
// Dialoge: Fokusfalle, Escape, Fokus zurueck
// =============================================================================================
test.describe('Gate C2 – Dialoge auf ui/Dialog.vue', () => {
    test.use({ storageState: authFile('host') });

    test('Tag bearbeiten: Fokus im Namensfeld, Fokusfalle, Escape gibt Fokus an den Umschalter zurueck', async ({
        page,
    }) => {
        await page.goto(CREATE);
        await settle(page);
        const chip = page.locator('[data-tag-chip]', { hasText: 'Dessert' });
        await chip.dblclick();
        const dialog = page.getByRole('dialog', { name: 'Tag bearbeiten' });
        await expect(dialog).toBeVisible();
        await expect(dialog.locator('#tag-name')).toBeFocused();
        await expect(dialog.locator('#tag-name')).toHaveValue('Dessert');
        await expect(dialog.locator('#tag-name')).toHaveAccessibleDescription(
            /gelten für alle Rezepte/,
        );
        // Name, Schliessen, Abbrechen, Loeschen, Speichern
        await expectFocusTrapped(page, dialog, 6);
        await page.keyboard.press('Escape');
        await expect(dialog).toHaveCount(0);
        await expect(chip).toBeFocused();
    });

    test('Zutat bearbeiten: Fokusfalle, "Dialog schliessen" gibt den Fokus an den Stift zurueck', async ({
        page,
    }) => {
        await page.goto(CREATE);
        await settle(page);
        const row = ingredientRows(page).first();
        await row.locator('[data-ingredient-name]').fill('Rote Linsen');
        await page.locator('#name').click();
        const trigger = row.getByRole('button', { name: 'Zutat bearbeiten' });
        await trigger.click();
        const dialog = page.getByRole('dialog', { name: 'Zutat bearbeiten' });
        await expect(dialog.locator('#ingredient-name')).toBeFocused();
        await expectFocusTrapped(page, dialog, 6);
        await dialog.getByRole('button', { name: 'Dialog schließen' }).click();
        await expect(dialog).toHaveCount(0);
        await expect(trigger).toBeFocused();
    });

    for (const vp of [
        { label: '1440', use: { viewport: { width: 1440, height: 900 } } },
        { label: '320', use: MOBILE_320 },
    ]) {
        test.describe(`Alternativen (${vp.label})`, () => {
            test.use(vp.use);

            test(`Alternativen ${vp.label}: Entwurf, Escape verwirft, "Übernehmen" merkt vor, Fokusfalle, kein Ueberlauf`, async ({
                page,
            }) => {
                await page.goto(CREATE);
                await settle(page);
                const row = ingredientRows(page).first();
                await row.locator('[data-ingredient-name]').fill('Brokkoli');
                await page.locator('#name').click();
                const trigger = row.getByRole('button', {
                    name: 'Alternativzutaten bearbeiten',
                });
                await trigger.click();
                const dialog = page.getByRole('dialog', {
                    name: 'Alternativen für: Brokkoli',
                });
                await expect(dialog.locator('#alt-search')).toBeFocused();
                const panels = dialog.locator('[data-alternatives-panel]');
                await expect(panels).toHaveCount(2);

                await dialog.locator('#alt-search').fill('Tempeh');
                const tempeh = panels
                    .first()
                    .getByRole('button', { name: 'Tempeh', exact: true });
                await tempeh.click();
                await expect(tempeh).toHaveAttribute('aria-pressed', 'true');
                await dialog
                    .getByRole('button', { name: 'Als Alternative hinzufügen' })
                    .click();
                await expect(
                    panels.last().getByRole('button', { name: 'Tempeh' }),
                ).toBeVisible();

                const overflow = await page.evaluate(() => {
                    const d = document.querySelector('[role="dialog"]')!;
                    return {
                        doc:
                            document.documentElement.scrollWidth >
                            window.innerWidth,
                        // Inhalt (ohne die Ornamente des Dialog-Bausteins) passt hinein
                        panels: Array.from(
                            d.querySelectorAll('[data-alternatives-panel]'),
                        ).every(
                            (el) =>
                                el.getBoundingClientRect().right <=
                                d.getBoundingClientRect().right + 1,
                        ),
                        outside: Array.from(d.querySelectorAll('*'))
                            .filter((el) => {
                                const r = el.getBoundingClientRect();
                                return (
                                    r.width > 0 &&
                                    r.right > window.innerWidth + 1 &&
                                    !el.closest('svg')
                                );
                            })
                            .map((el) => el.tagName),
                    };
                });
                expect(overflow).toEqual({
                    doc: false,
                    panels: true,
                    outside: [],
                });

                await expectFocusTrapped(page, dialog, 9);

                // Escape = Abbrechen: Entwurf verworfen, Fokus zurueck
                await page.keyboard.press('Escape');
                await expect(dialog).toHaveCount(0);
                await expect(trigger).toBeFocused();
                await trigger.click();
                await expect(
                    panels.last().getByRole('button', { name: 'Tempeh' }),
                ).toHaveCount(0);

                // Uebernehmen merkt vor (Zahl am Ausloeser), gespeichert wird erst mit dem Rezept
                await dialog.locator('#alt-search').fill('Tempeh');
                await panels
                    .first()
                    .getByRole('button', { name: 'Tempeh', exact: true })
                    .click();
                await dialog
                    .getByRole('button', { name: 'Als Alternative hinzufügen' })
                    .click();
                await dialog
                    .getByRole('button', { name: 'Übernehmen' })
                    .click();
                await expect(dialog).toHaveCount(0);
                await expect(trigger).toBeFocused();
                await expect(
                    row.locator('[data-alternatives-count]'),
                ).toHaveText('1');
                await trigger.click();
                await expect(
                    panels.last().getByRole('button', { name: 'Tempeh' }),
                ).toBeVisible();
            });
        });
    }
});

// =============================================================================================
// 320 px: kein horizontales Scrollen
// =============================================================================================
test.describe('Gate C2 – 320 px ohne horizontales Scrollen', () => {
    test.use({ storageState: authFile('host'), ...MOBILE_320 });

    for (const p of [
        { slug: 'erstellen', path: CREATE },
        { slug: 'bearbeiten', path: EDIT },
    ]) {
        test(`${p.slug}: kein Ueberlauf, auch mit mehreren Zeilen, Fehlern und Vorschlaegen`, async ({
            page,
        }) => {
            await page.goto(p.path);
            await settle(page);
            await page.getByRole('button', { name: '+ weitere Zutat' }).click();
            await page
                .getByRole('button', { name: '+ weiteren Schritt hinzufügen' })
                .click();
            // lange Woerter in Zutat und Schritt
            await ingredientRows(page)
                .last()
                .locator('[data-ingredient-name]')
                .fill('Kichererbsenmehlpfannkuchenteig');
            await stepTexts(page)
                .last()
                .fill('Donaudampfschifffahrtsgesellschaftskapitaensmuetze');
            await page.locator('#name').click();

            const measure = () =>
                page.evaluate(() => {
                    const vw = window.innerWidth;
                    const main = document.getElementById('main-content')!;
                    const offenders = Array.from(main.querySelectorAll('*'))
                        .filter((el) => {
                            if (el.closest('[aria-hidden="true"], svg'))
                                return false;
                            const r = el.getBoundingClientRect();
                            return r.width > 0 && r.right > vw + 1;
                        })
                        .map(
                            (el) =>
                                `${el.tagName.toLowerCase()}${el.id ? '#' + el.id : ''}`,
                        );
                    return {
                        docScroll: document.documentElement.scrollWidth,
                        bodyScroll: document.body.scrollWidth,
                        vw,
                        offenders: offenders.slice(0, 10),
                    };
                });
            const m = await measure();
            writeRaw('regression', `gate-c2-320-${p.slug}`, m);
            expect(m.docScroll, 'documentElement').toBeLessThanOrEqual(m.vw);
            expect(m.bodyScroll, 'body').toBeLessThanOrEqual(m.vw);
            expect(m.offenders).toEqual([]);

            // Vorschlagsliste geoeffnet
            await ingredientRows(page)
                .first()
                .locator('[data-ingredient-name]')
                .click();
            const m2 = await measure();
            expect(m2.docScroll).toBeLessThanOrEqual(m2.vw);
            expect(m2.offenders).toEqual([]);
        });
    }
});

// =============================================================================================
// axe: alle Regeln = 0
// =============================================================================================
for (const vp of [
    { label: '1440', use: { viewport: { width: 1440, height: 900 } } },
    { label: '390', use: MOBILE_390 },
]) {
    test.describe(`Gate C2 – axe (${vp.label})`, () => {
        test.use({ storageState: authFile('host'), ...vp.use });

        for (const p of [
            { slug: 'rezept-erstellen', path: CREATE },
            { slug: 'rezept-bearbeiten', path: EDIT },
        ]) {
            test(`axe ${p.slug} ${vp.label}: 0 Verstoesse (alle Regeln)`, async ({
                page,
            }) => {
                await page.goto(p.path);
                await settle(page);
                const results = await new AxeBuilder({ page }).analyze();
                const summary = results.violations.map((v) => ({
                    id: v.id,
                    impact: v.impact,
                    targets: v.nodes.map((n) => n.target.join(' ')),
                }));
                writeRaw('regression', `gate-c2-axe-${p.slug}-${vp.label}`, {
                    summary,
                });
                expect(summary).toEqual([]);
            });
        }

        test(`axe Dialog "Zutat bearbeiten" offen ${vp.label}: 0 Verstoesse`, async ({
            page,
        }) => {
            await page.goto(CREATE);
            await settle(page);
            const row = ingredientRows(page).first();
            await row.locator('[data-ingredient-name]').fill('Rote Linsen');
            await page.locator('#name').click();
            await row.getByRole('button', { name: 'Zutat bearbeiten' }).click();
            await expect(
                page.getByRole('dialog', { name: 'Zutat bearbeiten' }),
            ).toBeVisible();
            const results = await new AxeBuilder({ page }).analyze();
            const summary = results.violations.map((v) => ({
                id: v.id,
                targets: v.nodes.map((n) => n.target.join(' ')),
            }));
            expect(summary).toEqual([]);
        });
    });
}

// =============================================================================================
// Vorschau-Screenshots (nur mit GATE_C2_PREVIEW=1; reduzierte Bewegung ist Projektstandard)
// =============================================================================================
test.describe('Gate C2 – Vorschau', () => {
    test.skip(
        !process.env.GATE_C2_PREVIEW,
        'nur fuer die Abnahme-Vorschau (GATE_C2_PREVIEW=1)',
    );
    test.use({ storageState: authFile('host'), reducedMotion: 'reduce' });

    /**
     * rezeptly/app.css macht <body> zum Scroll-Container (height: 100%);
     * fuer ganzseitige Aufnahmen nur in der Vorschau aufheben.
     */
    const unclip = (page: Page) =>
        page.addStyleTag({
            content:
                'html, body { height: auto !important; overflow: visible !important; }',
        });

    const out = (name: string) =>
        path.join(
            process.cwd(),
            'docs',
            'modernization',
            'gate-c2-vorschau',
            `forms-${name}.png`,
        );

    for (const vp of [
        { label: '1440', use: { viewport: { width: 1440, height: 900 } } },
        { label: '390', use: MOBILE_390 },
    ]) {
        test.describe(`Vorschau ${vp.label}`, () => {
            test.use(vp.use);

            test(`Vorschau Erstellen/Bearbeiten ${vp.label}`, async ({
                page,
            }) => {
                await page.goto(CREATE);
                await settle(page);
                await unclip(page);
                await page.screenshot({
                    path: out(`erstellen-${vp.label}`),
                    fullPage: true,
                });
                await page.goto(EDIT);
                await settle(page);
                await unclip(page);
                await page.screenshot({
                    path: out(`bearbeiten-${vp.label}`),
                    fullPage: true,
                });
            });
        });
    }

    test('Vorschau Dialog und Import-Fortschritt', async ({ page }) => {
        await page.goto(EDIT);
        await settle(page);
        await page
            .getByRole('button', { name: 'Alternativzutaten bearbeiten' })
            .first()
            .click();
        const dialog = page.getByRole('dialog', { name: /Alternativen für/ });
        await expect(dialog).toBeVisible();
        await dialog
            .locator('[data-alternatives-panel]')
            .first()
            .getByRole('button')
            .first()
            .click();
        await page.screenshot({ path: out('dialog-alternativen-1440') });
        await page.keyboard.press('Escape');

        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(CREATE);
        await settle(page);
        await page
            .locator('[data-tag-chip]', { hasText: 'Dessert' })
            .dblclick();
        await expect(
            page.getByRole('dialog', { name: 'Tag bearbeiten' }),
        ).toBeVisible();
        await page.screenshot({ path: out('dialog-tag-390') });
        await page.keyboard.press('Escape');

        // Import-Fortschritt (Server-Antworten simuliert, kein echter Import)
        await page.setViewportSize({ width: 1440, height: 900 });
        await page.route(
            (url) => url.pathname === '/rezept-import',
            (route) =>
                route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ id: 999 }),
                }),
        );
        let state = 'downloading';
        await page.route(
            (url) => /^\/rezept-import\/\d+$/.test(url.pathname),
            (route) =>
                route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify(
                        state === 'failed'
                            ? {
                                  id: 999,
                                  status: 'failed',
                                  error: 'Das Video konnte nicht geladen werden.',
                              }
                            : { id: 999, status: state, started: true },
                    ),
                }),
        );
        await page.goto(CREATE);
        await settle(page);
        await page
            .getByLabel('Rezept aus TikTok importieren')
            .fill('https://www.tiktok.com/@e2e/video/1');
        await page.getByRole('button', { name: 'Importieren' }).click();
        state = 'analyzing';
        await expect(
            page.locator('[data-import-progress] li[data-state="active"]'),
        ).toContainText('Rezept wird erkannt', { timeout: 10_000 });
        await page
            .locator('[data-tiktok-import]')
            .screenshot({ path: out('import-laeuft-1440') });
        state = 'failed';
        await expect(
            page.getByRole('button', { name: 'Erneut versuchen' }),
        ).toBeVisible({ timeout: 10_000 });
        await page.setViewportSize({ width: 390, height: 844 });
        await page
            .locator('[data-tiktok-import]')
            .screenshot({ path: out('import-fehler-390') });
    });
});

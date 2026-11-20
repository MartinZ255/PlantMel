import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';
import {
    authFile,
    expect,
    measureLayout,
    settle,
    test,
} from './support/fixtures';
import { createRecipe, deleteRecipe } from './support/helpers';
import { writeRaw } from './support/raw';

/**
 * Gate D1 – F-38 (WCAG 2.1.1 / 2.5.7): Rezeptformular ohne Zeiger bedienbar.
 *
 * - Zutaten und Schritte per „nach oben/unten“-Knopf umsortieren; der Fokus bleibt auf
 *   dem gleichen Knopf der verschobenen Zeile (am Rand: Gegenrichtung), eine Live-Region
 *   sagt die neue Position an. Der Drag-Griff ist nicht mehr fokussierbar.
 * - Speichern uebernimmt die neue Reihenfolge (nach Reload aus dem Server gelesen).
 * - Zutaten-Autocomplete als Combobox (Pfeiltasten, Enter, Escape, aria-activedescendant).
 * - axe ohne Befunde, 320/390 px ohne horizontales Scrollen, Knoepfe >= 44 px.
 *
 * Ersetzt den frueheren Soll-Test „F-38 Drag-Griff der Schritte ist per Tastatur
 * bedienbar“ aus known-defects.spec.ts: Der Tastaturweg sind die Knoepfe, der Griff ist
 * bewusst kein Tab-Stopp mehr.
 *
 * Datenveraenderungen: ein Wegwerf-Rezept wird angelegt, bearbeitet und wieder geloescht.
 */

const CREATE = '/rezepte/erstellen';

const MOBILE = [
    { label: '320', viewport: { width: 320, height: 640 } },
    { label: '390', viewport: { width: 390, height: 844 } },
] as const;

const stepTexts = (page: Page) => page.locator('[data-step-text]');
const ingredientNames = (page: Page) =>
    page.locator('[data-ingredient-row] [data-ingredient-name]');
const status = (page: Page) => page.locator('[data-reorder-status]');

const values = (loc: ReturnType<typeof stepTexts>) =>
    loc.evaluateAll((els) =>
        els.map((e) => (e as HTMLInputElement | HTMLTextAreaElement).value),
    );

/** Name des fokussierten Elements (aria-label bzw. Text). */
const focusedLabel = (page: Page) =>
    page.evaluate(
        () =>
            document.activeElement?.getAttribute('aria-label') ??
            document.activeElement?.textContent?.trim() ??
            null,
    );

async function addSteps(page: Page, texts: string[]) {
    for (let i = 1; i < texts.length; i++) {
        await page
            .getByRole('button', { name: '+ weiteren Schritt hinzufügen' })
            .click();
    }
    for (const [i, t] of texts.entries()) await stepTexts(page).nth(i).fill(t);
}

async function addIngredients(page: Page, names: string[]) {
    for (let i = 1; i < names.length; i++) {
        await page.getByRole('button', { name: '+ weitere Zutat' }).click();
    }
    for (const [i, n] of names.entries()) {
        await ingredientNames(page).nth(i).fill(n);
    }
    // Vorschlagsliste schliessen
    await page.locator('#name').click();
}

test.describe('Gate D1 – F-38 Umsortieren per Tastatur', () => {
    test.use({ storageState: authFile('host') });

    test('Schritte: Knoepfe verschieben, Fokus folgt, Ansage, Griff kein Tab-Stopp', async ({
        page,
    }) => {
        await page.goto(CREATE);
        await settle(page);
        await addSteps(page, ['Schritt A', 'Schritt B', 'Schritt C']);

        // Rand: erster Schritt kann nicht hoch, letzter nicht runter
        await expect(
            page.getByRole('button', {
                name: 'Schritt 1 nach oben verschieben',
            }),
        ).toBeDisabled();
        await expect(
            page.getByRole('button', {
                name: 'Schritt 3 nach unten verschieben',
            }),
        ).toBeDisabled();

        // Der Griff ist weder Knopf noch fokussierbar
        const handles = await page
            .locator('[data-step-handle]')
            .evaluateAll((els) =>
                els.map((e) => ({
                    tag: e.tagName.toLowerCase(),
                    tabIndex: (e as HTMLElement).tabIndex,
                    hidden: e.getAttribute('aria-hidden'),
                })),
            );
        expect(handles).toHaveLength(3);
        for (const h of handles) {
            expect(h.tag).not.toBe('button');
            expect(h.tabIndex).toBe(-1);
            expect(h.hidden).toBe('true');
        }

        // Tab-Reihenfolge: Text 1 -> (hoch deaktiviert) -> runter -> entfernen
        await stepTexts(page).nth(0).focus();
        await page.keyboard.press('Tab');
        expect(await focusedLabel(page)).toBe(
            'Schritt 1 nach unten verschieben',
        );
        await page.keyboard.press('Tab');
        expect(await focusedLabel(page)).toBe('Schritt 1 entfernen');

        // C (Schritt 3) per Enter eins nach oben
        await page
            .getByRole('button', { name: 'Schritt 3 nach oben verschieben' })
            .focus();
        await page.keyboard.press('Enter');
        expect(await values(stepTexts(page))).toEqual([
            'Schritt A',
            'Schritt C',
            'Schritt B',
        ]);
        expect(await focusedLabel(page)).toBe(
            'Schritt 2 nach oben verschieben',
        );
        await expect(status(page)).toHaveText('Schritt 3 ist jetzt Schritt 2');
        await expect(status(page)).toHaveAttribute('role', 'status');

        // per Leertaste ganz nach oben: Fokus wechselt auf „nach unten“
        await page.keyboard.press('Space');
        expect(await values(stepTexts(page))).toEqual([
            'Schritt C',
            'Schritt A',
            'Schritt B',
        ]);
        expect(await focusedLabel(page)).toBe(
            'Schritt 1 nach unten verschieben',
        );
        await expect(status(page)).toHaveText('Schritt 2 ist jetzt Schritt 1');

        // und zurueck nach unten (gleicher Knopf bleibt fokussiert)
        await page.keyboard.press('Enter');
        expect(await values(stepTexts(page))).toEqual([
            'Schritt A',
            'Schritt C',
            'Schritt B',
        ]);
        expect(await focusedLabel(page)).toBe(
            'Schritt 2 nach unten verschieben',
        );
        await expect(status(page)).toHaveText('Schritt 1 ist jetzt Schritt 2');

        // Barrierefreie Namen der Textfelder folgen der Position
        await expect(stepTexts(page).nth(1)).toHaveAccessibleName('Schritt 2');
        await expect(stepTexts(page).nth(1)).toHaveValue('Schritt C');
    });

    test('Zutaten: Knoepfe verschieben, Fokus folgt, Ansage', async ({
        page,
    }) => {
        await page.goto(CREATE);
        await settle(page);
        await addIngredients(page, ['Brokkoli', 'Sojasauce', 'Tempeh']);

        await expect(
            page.getByRole('button', { name: 'Zutat 1 nach oben verschieben' }),
        ).toBeDisabled();
        await expect(
            page.getByRole('button', {
                name: 'Zutat 3 nach unten verschieben',
            }),
        ).toBeDisabled();
        for (const tabIndex of await page
            .locator('[data-ingredient-handle]')
            .evaluateAll((els) =>
                els.map((e) => (e as HTMLElement).tabIndex),
            )) {
            expect(tabIndex).toBe(-1);
        }

        await page
            .getByRole('button', { name: 'Zutat 1 nach unten verschieben' })
            .focus();
        await page.keyboard.press('Enter');
        expect(await values(ingredientNames(page))).toEqual([
            'Sojasauce',
            'Brokkoli',
            'Tempeh',
        ]);
        expect(await focusedLabel(page)).toBe('Zutat 2 nach unten verschieben');
        await expect(status(page)).toHaveText('Zutat 1 ist jetzt Zutat 2');

        await page.keyboard.press('Enter');
        expect(await values(ingredientNames(page))).toEqual([
            'Sojasauce',
            'Tempeh',
            'Brokkoli',
        ]);
        // am unteren Rand: Fokus auf „nach oben“
        expect(await focusedLabel(page)).toBe('Zutat 3 nach oben verschieben');
        await expect(status(page)).toHaveText('Zutat 2 ist jetzt Zutat 3');

        // Mengenfeld wandert mit
        await expect(
            page
                .locator('[data-ingredient-row]')
                .nth(2)
                .locator('[data-ingredient-name]'),
        ).toHaveAccessibleName('Bezeichnung, Zutat 3');
    });

    test('Drag & Drop bleibt erhalten und wird ebenfalls angesagt', async ({
        page,
    }) => {
        await page.goto(CREATE);
        await settle(page);
        await addSteps(page, ['Schritt A', 'Schritt B', 'Schritt C']);
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
        expect(await values(stepTexts(page))).toEqual([
            'Schritt C',
            'Schritt A',
            'Schritt B',
        ]);
        await expect(status(page)).toHaveText('Schritt 3 ist jetzt Schritt 1');
    });

    test('Speichern uebernimmt die per Tastatur geaenderte Reihenfolge (nach Reload)', async ({
        page,
    }) => {
        const recipeId = await createRecipe(page, {
            title: 'E2E D1 Reihenfolge',
            ingredients: [
                { name: 'Brokkoli', amount: '1 Stück' },
                { name: 'Sojasauce', amount: '2 EL' },
                { name: 'Tempeh', amount: '200 g' },
            ],
            steps: ['Eins', 'Zwei', 'Drei'],
        });
        try {
            await page.goto(`/rezept/${recipeId}/bearbeiten`);
            await settle(page);
            expect(await values(ingredientNames(page))).toEqual([
                'Brokkoli',
                'Sojasauce',
                'Tempeh',
            ]);

            // Tempeh ganz nach oben (zweimal Enter auf demselben Knopf)
            await page
                .getByRole('button', { name: 'Zutat 3 nach oben verschieben' })
                .focus();
            await page.keyboard.press('Enter');
            await page.keyboard.press('Enter');
            // Schritt „Eins“ ans Ende
            await page
                .getByRole('button', {
                    name: 'Schritt 1 nach unten verschieben',
                })
                .focus();
            await page.keyboard.press('Enter');
            await page.keyboard.press('Enter');

            expect(await values(ingredientNames(page))).toEqual([
                'Tempeh',
                'Brokkoli',
                'Sojasauce',
            ]);
            expect(await values(stepTexts(page))).toEqual([
                'Zwei',
                'Drei',
                'Eins',
            ]);

            await page
                .getByRole('button', { name: 'Rezept aktualisieren' })
                .click();
            await page.waitForURL(new RegExp(`/rezept/${recipeId}$`));

            await page.goto(`/rezept/${recipeId}/bearbeiten`);
            await settle(page);
            const amounts = await values(
                page.locator('[data-ingredient-row] [data-ingredient-amount]'),
            );
            const saved = {
                ingredients: await values(ingredientNames(page)),
                amounts,
                steps: await values(stepTexts(page)),
            };
            writeRaw('regression', 'D1-keyboard-reorder-saved', saved);
            expect(saved).toEqual({
                ingredients: ['Tempeh', 'Brokkoli', 'Sojasauce'],
                amounts: ['200 g', '1 Stück', '2 EL'],
                steps: ['Zwei', 'Drei', 'Eins'],
            });
        } finally {
            await deleteRecipe(page, recipeId);
        }
    });

    test('Autocomplete: Combobox mit Pfeiltasten, Enter und Escape', async ({
        page,
    }) => {
        await page.goto(CREATE);
        await settle(page);
        const input = ingredientNames(page).first();
        const listbox = page.getByRole('listbox', {
            name: 'Vorschläge für Zutat 1',
        });

        await input.focus();
        await expect(input).toHaveAttribute('role', 'combobox');
        await input.fill('linsen');
        await expect(listbox).toBeVisible();
        await expect(input).toHaveAttribute('aria-expanded', 'true');
        await expect(input).toHaveAttribute(
            'aria-controls',
            (await listbox.getAttribute('id'))!,
        );
        await expect(input).not.toHaveAttribute('aria-activedescendant');

        // Pfeil runter waehlt Option fuer Option (genau eine ist ausgewaehlt)
        const allOptions = listbox.getByRole('option');
        const names = (await allOptions.allTextContents()).map((t) => t.trim());
        const target = names.indexOf('Rote Linsen');
        expect(target, `Rote Linsen in ${names.join(', ')}`).toBeGreaterThan(
            -1,
        );
        for (let i = 0; i <= target; i++) {
            await page.keyboard.press('ArrowDown');
            await expect(allOptions.nth(i)).toHaveAttribute(
                'aria-selected',
                'true',
            );
        }
        await expect(listbox.locator('[aria-selected="true"]')).toHaveCount(1);
        const option = listbox.getByRole('option', { name: 'Rote Linsen' });
        await expect(option).toHaveAttribute('aria-selected', 'true');
        await expect(input).toHaveAttribute(
            'aria-activedescendant',
            (await option.getAttribute('id'))!,
        );
        await expect(input).toBeFocused();

        // Enter uebernimmt, schliesst, Fokus bleibt; Formular wird nicht abgeschickt
        const submits: string[] = [];
        page.on('request', (r) => {
            if (r.method() === 'POST') submits.push(r.url());
        });
        await page.keyboard.press('Enter');
        await expect(input).toHaveValue('Rote Linsen');
        await expect(listbox).toHaveCount(0);
        await expect(input).toHaveAttribute('aria-expanded', 'false');
        await expect(input).toBeFocused();
        expect(submits).toEqual([]);

        // Pfeil hoch oeffnet und springt auf die letzte Option, Escape schliesst
        await input.fill('');
        await expect(listbox).toBeVisible();
        await page.keyboard.press('ArrowUp');
        const options = listbox.getByRole('option');
        await expect(options.last()).toHaveAttribute('aria-selected', 'true');
        await page.keyboard.press('Escape');
        await expect(listbox).toHaveCount(0);
        await expect(input).toBeFocused();
        await expect(input).toHaveValue('');

        // geschlossen: Pfeil runter oeffnet wieder
        await page.keyboard.press('ArrowDown');
        await expect(listbox).toBeVisible();
        await expect(options.first()).toHaveAttribute('aria-selected', 'true');
    });

    test('axe: 0 Verstoesse mit Umsortier-Knoepfen, Ansage und offener Combobox', async ({
        page,
    }) => {
        await page.goto(CREATE);
        await settle(page);
        await addIngredients(page, ['Brokkoli', 'Sojasauce']);
        await addSteps(page, ['Schritt A', 'Schritt B']);
        await page
            .getByRole('button', { name: 'Schritt 2 nach oben verschieben' })
            .click();
        await expect(status(page)).toHaveText('Schritt 2 ist jetzt Schritt 1');

        const afterMove = await new AxeBuilder({ page }).analyze();

        await ingredientNames(page).first().focus();
        await ingredientNames(page).first().fill('o');
        await page.keyboard.press('ArrowDown');
        await expect(page.getByRole('listbox')).toBeVisible();
        const withCombobox = await new AxeBuilder({ page }).analyze();

        const summary = (r: typeof afterMove) =>
            r.violations.map((v) => ({
                id: v.id,
                nodes: v.nodes.map((n) => n.target.join(' ')),
            }));
        writeRaw('regression', 'D1-axe', {
            afterMove: summary(afterMove),
            withCombobox: summary(withCombobox),
        });
        expect(summary(afterMove)).toEqual([]);
        expect(summary(withCombobox)).toEqual([]);
    });
});

for (const vp of MOBILE) {
    test.describe(`Gate D1 – Umsortieren mobil ${vp.label}`, () => {
        test.use({
            storageState: authFile('host'),
            viewport: vp.viewport,
            isMobile: true,
            hasTouch: true,
        });

        test(`${vp.label} px: kein horizontales Scrollen, Knoepfe >= 44 px im Bild`, async ({
            page,
        }) => {
            await page.goto(CREATE);
            await settle(page);
            await addIngredients(page, ['Brokkoli', 'Sojasauce', 'Tempeh']);
            await addSteps(page, ['Schritt A', 'Schritt B', 'Schritt C']);

            // per Tippen auf den Knopf verschieben (Touch)
            await page
                .getByRole('button', { name: 'Zutat 3 nach oben verschieben' })
                .tap();
            expect(await values(ingredientNames(page))).toEqual([
                'Brokkoli',
                'Tempeh',
                'Sojasauce',
            ]);

            const layout = await measureLayout(page);
            const buttons = await page
                .locator('[data-move-up], [data-move-down]')
                .evaluateAll((els) =>
                    els.map((e) => {
                        const r = e.getBoundingClientRect();
                        return {
                            label: e.getAttribute('aria-label'),
                            w: Math.round(r.width),
                            h: Math.round(r.height),
                            left: Math.round(r.left),
                            right: Math.round(r.right),
                        };
                    }),
                );
            writeRaw('regression', `D1-mobile-${vp.label}`, {
                layout,
                buttons,
            });
            expect(layout.horizontalOverflow, 'horizontales Scrollen').toBe(
                false,
            );
            expect(layout.overflowingElements).toEqual([]);
            expect(buttons).toHaveLength(12);
            for (const b of buttons) {
                expect(b.w, `${b.label} Breite`).toBeGreaterThanOrEqual(44);
                expect(b.h, `${b.label} Hoehe`).toBeGreaterThanOrEqual(44);
                expect(
                    b.left,
                    `${b.label} links im Bild`,
                ).toBeGreaterThanOrEqual(0);
                expect(
                    b.right,
                    `${b.label} rechts im Bild`,
                ).toBeLessThanOrEqual(vp.viewport.width);
            }
        });
    });
}

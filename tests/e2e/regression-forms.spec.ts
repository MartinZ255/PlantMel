import type { Locator, Page, Request } from '@playwright/test';
import { authFile, DATA, expect, settle, test } from './support/fixtures';
import { hitTest, inertiaProps } from './support/helpers';
import { writeRaw } from './support/raw';

/**
 * Slice 3 – Formulare, Rezeptseiten und Modals (F-06, F-24, F-30, F-31, F-32,
 * F-39, F-44, F-56, F-74). Projekt `regression` (1440×900); mobile Fälle setzen
 * den Viewport selbst.
 *
 * Unabhängig vom Wortlaut der Validierungsmeldungen (APP_LOCALE en/de): geprüft
 * werden Sichtbarkeit, Zuordnung zum Feld und aria-Verknüpfung.
 *
 * Datenveränderungen: keine. Absendungen, die gelingen würden, gibt es nicht;
 * Import, Kategorien/Zutaten anlegen und Sammlungs-Fehler werden per
 * `page.route` simuliert. Der Teilerfolg in F-39 fügt Rezept 1 erneut zu
 * „Wochenplan“ hinzu, in der es bereits liegt (syncWithoutDetaching, keine Änderung).
 */

const MOBILE_390 = {
    viewport: { width: 390, height: 844 },
    isMobile: true,
    hasTouch: true,
} as const;

type Box = { x: number; y: number; width: number; height: number };

const intersects = (a: Box, b: Box) =>
    a.x < b.x + b.width &&
    a.x + a.width > b.x &&
    a.y < b.y + b.height &&
    a.y + a.height > b.y;

const isRecipeStore = (r: Request) =>
    r.method() === 'POST' && new URL(r.url()).pathname === '/recipes';

/** Prüft aria-invalid und dass aria-describedby auf ein sichtbares Element mit Text zeigt. */
async function expectFieldError(page: Page, field: Locator, label: string) {
    await expect(field, `${label}: aria-invalid`).toHaveAttribute(
        'aria-invalid',
        'true',
    );
    const describedBy = (await field.getAttribute('aria-describedby')) ?? '';
    expect(describedBy, `${label}: aria-describedby`).not.toBe('');
    const ids = describedBy.split(/\s+/).filter(Boolean);
    let visibleTexts = 0;
    for (const id of ids) {
        const el = page.locator(`[id="${id}"]`);
        await expect(el, `${label}: #${id} existiert`).toHaveCount(1);
        if (
            (await el.isVisible()) &&
            ((await el.textContent()) ?? '').trim().length > 0
        )
            visibleTexts++;
    }
    expect(visibleTexts, `${label}: sichtbarer Fehlertext`).toBeGreaterThan(0);
}

/** Antwort im Inertia-Format mit den gegebenen Validierungsfehlern (simuliert 422-Redirect). */
async function fulfillWithErrors(page: Page, errors: Record<string, string>) {
    const pageObject = await page.evaluate(() => {
        const el = document.querySelector(
            '#app[data-page]',
        ) as HTMLElement | null;
        const script = document.querySelector(
            'script[data-page]',
        ) as HTMLScriptElement | null;
        return JSON.parse(el?.dataset.page ?? script?.textContent ?? '{}');
    });
    await page.route(
        (url) => url.pathname === '/recipes',
        (route) =>
            route.request().method() !== 'POST'
                ? route.continue()
                : route.fulfill({
                      status: 200,
                      headers: {
                          'Content-Type': 'application/json',
                          'X-Inertia': 'true',
                          Vary: 'X-Inertia',
                      },
                      body: JSON.stringify({
                          ...pageObject,
                          props: { ...pageObject.props, errors },
                      }),
                  }),
    );
}

/** Ziehen einer Zeile per Griff an die Position einer anderen Zeile (wie Slice 2). */
async function dragRow(page: Page, handle: Locator, target: Locator) {
    await handle.scrollIntoViewIfNeeded();
    const hb = (await handle.boundingBox())!;
    const tb = (await target.boundingBox())!;
    await page.mouse.move(hb.x + hb.width / 2, hb.y + hb.height / 2);
    await page.mouse.down();
    await page.mouse.move(hb.x + hb.width / 2, tb.y + 2, { steps: 15 });
    await page.mouse.up();
}

// =============================================================================================
// F-06 / F-74 / F-56 – Rezeptformular
// =============================================================================================
test.describe('Host – Rezeptformular (F-06/F-74/F-56)', () => {
    test.use({ storageState: authFile('host') });

    test('F-06 nur Titel: leere Startzeilen werden nicht gesendet, Server-Meldung an Zutaten/Schritten, Zusammenfassung fokussiert', async ({
        page,
        diag,
    }) => {
        await page.goto('/rezepte/erstellen');
        await settle(page);
        await page.locator('#name').fill('E2E F-06 Nur Titel');
        const [post] = await Promise.all([
            page.waitForRequest(isRecipeStore),
            page.getByRole('button', { name: 'Rezept speichern' }).click(),
        ]);
        const body = post.postData() ?? '';
        expect(body, 'leere Startzeile nicht gesendet').not.toContain(
            'name="ingredients[0][name]"',
        );
        expect(body, 'leerer Schritt nicht gesendet').not.toContain(
            'name="steps[0]"',
        );

        const summary = page.locator('[data-error-summary]');
        await expect(summary).toBeVisible();
        await expect(summary).toHaveAttribute('role', 'alert');
        await expect(summary).toBeFocused();
        await expect(summary.locator('li')).toHaveCount(2);
        await expect(summary).toContainText('Zutaten');
        await expect(summary).toContainText('Zubereitung');
        await expect(page.locator('#ingredients-error')).toBeVisible();
        await expect(page.locator('#steps-error')).toBeVisible();
        // Kriterium des bisherigen Defekt-Tests F-06
        expect(
            await page
                .locator(
                    '[data-form-section="ingredients"] [data-field-error], [data-form-section="steps"] [data-field-error], [data-form-section="notes"] [data-field-error]',
                )
                .filter({ visible: true })
                .count(),
        ).toBeGreaterThan(0);
        // Seite bleibt (kein Redirect auf ein Rezept)
        expect(new URL(page.url()).pathname).toBe('/rezepte/erstellen');

        // Sprunglink fokussiert das Feld
        await summary.getByRole('link', { name: /Zutaten/ }).click();
        await expect(
            page.locator('[data-ingredient-name]').first(),
        ).toBeFocused();
        await page.screenshot({
            path: test.info().outputPath('F-06-zusammenfassung.png'),
        });
        writeRaw('regression', 'F-06-only-title', {
            summary: await summary.innerText(),
        });
        expect(diag.pageErrors).toEqual([]);
    });

    test('F-06 Zeilen-Fehler: Zuordnung trotz gefilterter Leerzeile, aria-Verknuepfung, Verwerfen beim Umsortieren', async ({
        page,
    }) => {
        await page.goto('/rezepte/erstellen');
        await settle(page);
        await page.locator('#name').fill('E2E F-06 Zeilen');
        await page.getByRole('button', { name: '+ weitere Zutat' }).click();
        const rows = page.locator('[data-ingredient-row]');
        // Zeile 1 bleibt leer (wird gefiltert), Zeile 2 nur mit Menge -> ingredients.0.name
        await rows.nth(1).locator('[data-ingredient-amount]').fill('200 g');
        await page.locator('[data-step-text]').first().fill('Kochen.');

        await Promise.all([
            page.waitForRequest(isRecipeStore),
            page.getByRole('button', { name: 'Rezept speichern' }).click(),
        ]);
        const summary = page.locator('[data-error-summary]');
        await expect(summary).toBeFocused();
        await expect(summary.locator('li')).toHaveCount(1);
        await expect(summary).toContainText('Zutat 2 – Bezeichnung');

        const name0 = rows.nth(0).locator('[data-ingredient-name]');
        const name1 = rows.nth(1).locator('[data-ingredient-name]');
        await expectFieldError(page, name1, 'Zutat 2 Bezeichnung');
        await expect(name0).not.toHaveAttribute('aria-invalid', 'true');

        // Sprunglink fokussiert genau dieses Feld
        await summary.getByRole('link').click();
        await expect(name1).toBeFocused();

        // Umsortieren: Zeile 2 nach oben -> alte Zeilen-Fehler werden verworfen
        await page.locator('#name').click();
        await dragRow(
            page,
            rows.nth(1).locator('[data-ingredient-handle]'),
            rows.nth(0),
        );
        await expect(
            rows.nth(0).locator('[data-ingredient-amount]'),
        ).toHaveValue('200 g');
        await expect(
            page.locator('[data-ingredients-list] [aria-invalid="true"]'),
        ).toHaveCount(0);
        await expect(
            page.locator('[data-ingredient-errors] [data-field-error]'),
        ).toHaveCount(0);
        await expect(summary).toHaveCount(0);
    });

    test('F-06 Titel leer und negative Dauer: Fehler am Feld, Pflichtfeld markiert', async ({
        page,
    }) => {
        await page.goto('/rezepte/erstellen');
        await settle(page);
        const name = page.locator('#name');
        await expect(name).toHaveAttribute('required', '');
        await expect(name).toHaveAttribute('aria-required', 'true');
        await expect(page.locator('form[data-recipe-form]')).toHaveAttribute(
            'novalidate',
            '',
        );
        await page.locator('#cook_time').fill('-5');
        const row = page.locator('[data-ingredient-row]').first();
        await row.locator('[data-ingredient-name]').fill('Brokkoli');
        await page.locator('[data-step-text]').first().fill('Braten.');
        await page.locator('#servings').click();
        await Promise.all([
            page.waitForRequest(isRecipeStore),
            page.getByRole('button', { name: 'Rezept speichern' }).click(),
        ]);
        await expect(page.locator('[data-error-summary]')).toBeFocused();
        await expectFieldError(page, name, 'Titel');
        await expectFieldError(page, page.locator('#cook_time'), 'Dauer');
        await expect(page.locator('[data-error-summary] li')).toHaveCount(2);
    });

    test('F-74 alle Fehler-Keys werden am Feld angezeigt (Portionen, Tags, Bewertung, Menge, Schritt, Notizen)', async ({
        page,
    }) => {
        await page.goto('/rezepte/erstellen');
        await settle(page);
        const props = await inertiaProps<{
            ratingDimensions: { id: number; name: string }[];
        }>(page);
        // Gate C2: bewertet wird nur noch der Geschmack (sonst der erste Eintrag)
        const dim =
            props.ratingDimensions.find((d) => d.name === 'Geschmack') ??
            props.ratingDimensions[0];
        await page.locator('#name').fill('E2E F-74');
        const row = page.locator('[data-ingredient-row]').first();
        await row.locator('[data-ingredient-amount]').fill('x');
        await row.locator('[data-ingredient-name]').fill('Brokkoli');
        await page.locator('[data-step-text]').first().fill('Braten.');
        await page.locator('#notes').fill('Notiz');

        await fulfillWithErrors(page, {
            servings: 'Fehler Portionen',
            'tags.0': 'Fehler Tag',
            [`ratings.${dim.id}`]: 'Fehler Bewertung',
            'ingredients.0.amount': 'Fehler Menge',
            'steps.0': 'Fehler Schritt',
            notes: 'Fehler Notizen',
            unbekannt: 'Fehler ohne Feld',
        });
        await page.getByRole('button', { name: 'Rezept speichern' }).click();

        const summary = page.locator('[data-error-summary]');
        await expect(summary).toBeFocused();
        await expect(summary.locator('li')).toHaveCount(7);
        await expectFieldError(page, page.locator('#servings'), 'Portionen');
        await expectFieldError(page, page.locator('#tag-draft'), 'Tags');
        await expectFieldError(
            page,
            row.locator('[data-ingredient-amount]'),
            'Menge',
        );
        await expectFieldError(
            page,
            page.locator('[data-step-text]').first(),
            'Schritt',
        );
        await expectFieldError(page, page.locator('#notes'), 'Notizen');
        await expect(
            page.locator(`[id="rating-${dim.id}-error"]`),
        ).toBeVisible();
        // Gate C2: Sterne-Eingabe ist der Baustein StarRating (Fieldset mit
        // Radiogroup); die Fehler-ID haengt an der Gruppe der Dimension
        const ratingGroup = page.locator(`[id="rating-${dim.id}"]`);
        await expect(ratingGroup).toHaveAttribute(
            'aria-describedby',
            new RegExp(`(^|\\s)rating-${dim.id}-error(\\s|$)`),
        );
        // nicht zuordenbarer Key erscheint trotzdem (ohne Link)
        await expect(summary).toContainText('Fehler ohne Feld');
        // Sprunglink zur Bewertung: Fokus auf ein Optionsfeld der Gruppe
        await summary.getByRole('link', { name: /Bewertung/ }).click();
        expect(
            await ratingGroup.evaluate(
                (el) =>
                    el.contains(document.activeElement) &&
                    (document.activeElement as HTMLInputElement).type ===
                        'radio',
            ),
            'Fokus auf einem Stern der Dimension',
        ).toBe(true);
        await page.unrouteAll({ behavior: 'ignoreErrors' });
    });

    test('F-74 Fehler beim Anlegen von Tag und Zutat erscheinen am Eingabefeld', async ({
        page,
        diag,
    }) => {
        await page.route(
            (url) =>
                url.pathname === '/categories' ||
                url.pathname === '/ingredients',
            (route) => {
                if (route.request().method() !== 'POST')
                    return route.continue();
                const isTag = new URL(route.request().url()).pathname.endsWith(
                    'categories',
                );
                return isTag
                    ? route.fulfill({
                          status: 422,
                          contentType: 'application/json',
                          body: JSON.stringify({
                              message: 'Tag-Fehler',
                              errors: { name: ['Tag-Fehler vom Server'] },
                          }),
                      })
                    : route.fulfill({
                          status: 500,
                          contentType: 'application/json',
                          body: '{"message":"Server Error"}',
                      });
            },
        );
        await page.goto('/rezepte/erstellen');
        await settle(page);

        const tagInput = page.locator('#tag-draft');
        await tagInput.fill('E2E Neuer Tag');
        await tagInput.press('Enter');
        await expect(page.locator('#tag-draft-error')).toContainText(
            'Tag-Fehler vom Server',
        );
        await expectFieldError(page, tagInput, 'Tag anlegen');

        const row = page.locator('[data-ingredient-row]').first();
        const name = row.locator('[data-ingredient-name]');
        await name.fill('E2E Unbekannte Zutat');
        await page.locator('#name').click();
        await row.getByRole('button', { name: 'Zutat neu anlegen' }).click();
        const alert = page.locator('[data-ingredient-errors] [role="alert"]');
        await expect(alert).toContainText('Auf dem Server ist ein Fehler');
        await expectFieldError(page, name, 'Zutat anlegen');
        expect(diag.pageErrors).toEqual([]);
    });

    test('F-56 Bearbeiten: Abbrechen-Link, Zuruecksetzen nur nach Rueckfrage, Bildvorschau', async ({
        page,
    }) => {
        let dialogs = 0;
        page.on('dialog', async (d) => {
            dialogs++;
            await d.dismiss();
        });
        const id = DATA.recipes.withImage.id;
        await page.goto(`/rezept/${id}/bearbeiten`);
        await settle(page);
        await expect(page.locator('h1')).toHaveCount(1);
        await expect(page.locator('main main')).toHaveCount(0);

        const cancel = page.getByRole('link', { name: 'Abbrechen' });
        await expect(cancel).toHaveAttribute(
            'href',
            new RegExp(`/rezept/${id}$`),
        );

        const name = page.locator('#name');
        await name.fill('E2E geändert');
        const reset = page.getByRole('button', { name: 'Zurücksetzen' });
        await reset.click();
        const confirm = page.getByRole('group', {
            name: 'Alle ungespeicherten Änderungen verwerfen?',
        });
        await expect(confirm).toBeVisible();
        await expect(
            confirm.getByRole('button', { name: 'Behalten' }),
        ).toBeFocused();
        await confirm.getByRole('button', { name: 'Behalten' }).click();
        await expect(name).toHaveValue('E2E geändert');
        await expect(reset).toBeFocused();

        await reset.click();
        await page.getByRole('button', { name: 'Ja, zurücksetzen' }).click();
        await expect(name).toHaveValue(DATA.recipes.withImage.title);
        expect(dialogs, 'kein window.confirm').toBe(0);

        // Vorschau einer neu gewählten Datei (1×1-PNG)
        await page.locator('#image').setInputFiles({
            name: 'e2e.png',
            mimeType: 'image/png',
            buffer: Buffer.from(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
                'base64',
            ),
        });
        await expect(page.locator('[data-image-preview] img')).toBeVisible();

        // Abbrechen fuehrt zurueck zum Rezept, ohne zu speichern
        await Promise.all([
            page.waitForURL(new RegExp(`/rezept/${id}$`)),
            cancel.click(),
        ]);
    });
});

// =============================================================================================
// F-30 – Modals
// =============================================================================================
type ModalCase = {
    name: string;
    role: 'host' | 'user';
    open: (page: Page) => Promise<Locator>; // liefert den Ausloeser
    dialogName: RegExp;
    enterIn?: string; // Eingabefeld fuer den Enter-Test
};

const modalCases: ModalCase[] = [
    {
        name: 'Tag bearbeiten',
        role: 'host',
        dialogName: /Tag bearbeiten/,
        enterIn: '#tag-name',
        open: async (page) => {
            await page.goto('/rezepte/erstellen');
            await settle(page);
            const chip = page.locator('[data-tag-chip]', {
                hasText: 'Dessert',
            });
            await chip.scrollIntoViewIfNeeded();
            await chip.dblclick();
            return chip;
        },
    },
    {
        name: 'Zutat bearbeiten',
        role: 'host',
        dialogName: /Zutat bearbeiten/,
        enterIn: '#ingredient-name',
        open: async (page) => {
            await page.goto('/rezepte/erstellen');
            await settle(page);
            const row = page.locator('[data-ingredient-row]').first();
            await row.locator('[data-ingredient-name]').fill('Rote Linsen');
            await page.locator('#name').click();
            const trigger = row.getByRole('button', {
                name: 'Zutat bearbeiten',
            });
            await trigger.click();
            return trigger;
        },
    },
    {
        name: 'Alternativen',
        role: 'host',
        dialogName: /Alternativen für/,
        enterIn: '#alt-search',
        open: async (page) => {
            await page.goto(`/rezept/${DATA.recipes.withImage.id}/bearbeiten`);
            await settle(page);
            const trigger = page
                .getByRole('button', { name: 'Alternativzutaten bearbeiten' })
                .first();
            await trigger.scrollIntoViewIfNeeded();
            await trigger.click();
            return trigger;
        },
    },
    {
        name: 'Zur Sammlung hinzufuegen',
        role: 'user',
        dialogName: /Zur Sammlung hinzufügen/,
        open: async (page) => {
            await page.goto(`/rezept/${DATA.recipes.withImage.id}`);
            await settle(page);
            const trigger = page.getByRole('button', {
                name: 'Zur Sammlung hinzufügen',
            });
            await trigger.click();
            return trigger;
        },
    },
];

for (const vp of [
    { label: '1440', use: { viewport: { width: 1440, height: 900 } } },
    { label: '390', use: MOBILE_390 },
]) {
    for (const mc of modalCases) {
        test.describe(`F-30 Modal ${mc.name} (${vp.label})`, () => {
            test.use({ storageState: authFile(mc.role), ...vp.use });

            test(`F-30 ${mc.name} ${vp.label}: ueber Header, Scroll-Sperre, Escape, Fokus, Enter sendet nicht`, async ({
                page,
                diag,
            }) => {
                const stores: string[] = [];
                page.on('request', (r) => {
                    if (
                        isRecipeStore(r) ||
                        (/\/rezept\/\d+$/.test(new URL(r.url()).pathname) &&
                            r.method() === 'POST')
                    )
                        stores.push(r.url());
                });
                const trigger = await mc.open(page);
                const dialog = page.getByRole('dialog', {
                    name: mc.dialogName,
                });
                await expect(dialog).toBeVisible();
                await expect(dialog).toHaveAttribute('aria-modal', 'true');
                const labelledBy = await dialog.getAttribute('aria-labelledby');
                await expect(
                    page.locator(`[id="${labelledBy}"]`),
                ).toBeVisible();
                // Fokus liegt im Dialog
                expect(
                    await dialog.evaluate((el) =>
                        el.contains(document.activeElement),
                    ),
                    'Fokus im Dialog',
                ).toBe(true);

                // Hintergrund an den Seitenanfang scrollen (wie im Defekt-Test): Titel bleibt frei
                await page.evaluate(() => {
                    document.body.scrollTo(0, 0);
                    window.scrollTo(0, 0);
                });
                await page.waitForTimeout(200);
                const title = page.locator(`[id="${labelledBy}"]`);
                const titleHit = await hitTest(title);
                expect(
                    titleHit.hitsTarget,
                    `Titel verdeckt von ${titleHit.hit}`,
                ).toBe(true);

                // Header ist nicht klickbar (Punkt in der Header-Mitte trifft nicht den Header)
                const headerHit = await page.evaluate(() => {
                    // Gate C: Kopfzeile der App-Shell (erstes <header>, liegt in #app)
                    const h = document.querySelector('#app header')!;
                    const r = h.getBoundingClientRect();
                    const x = r.left + 20;
                    const y = Math.max(1, r.top + r.height / 2);
                    const el = document.elementFromPoint(x, y);
                    return {
                        inHeader: !!el && h.contains(el),
                        hit: el?.className ?? null,
                    };
                });
                expect(
                    headerHit.inHeader,
                    `Header-Punkt trifft ${headerHit.hit}`,
                ).toBe(false);

                // Scroll-Sperre: Mausrad auf dem Backdrop bewegt den Hintergrund nicht
                const before = await page.evaluate(() => ({
                    body: document.body.scrollTop,
                    win: window.scrollY,
                    overflow: getComputedStyle(document.body).overflow,
                }));
                const vpSize = page.viewportSize()!;
                await page.mouse.move(4, vpSize.height - 4);
                await page.mouse.wheel(0, 800);
                await page.waitForTimeout(300);
                const after = await page.evaluate(() => ({
                    body: document.body.scrollTop,
                    win: window.scrollY,
                }));
                expect(before.overflow).toBe('hidden');
                expect(after).toEqual({ body: before.body, win: before.win });
                // Hintergrund ist inert
                expect(
                    await page.evaluate(
                        () => document.getElementById('app')?.inert,
                    ),
                ).toBe(true);

                // Enter im Dialog sendet das Rezeptformular nicht ab
                if (mc.enterIn) {
                    await dialog.locator(mc.enterIn).focus();
                    await page.keyboard.press('Enter');
                    await page.waitForTimeout(500);
                    expect(stores, 'kein Absenden des Rezeptformulars').toEqual(
                        [],
                    );
                    await expect(dialog).toBeVisible();
                }

                await page.screenshot({
                    path: test
                        .info()
                        .outputPath(
                            `F-30-${mc.name.replace(/\s/g, '-')}-${vp.label}.png`,
                        ),
                });

                // Escape schliesst, Fokus zurueck am Ausloeser, Sperre aufgehoben
                await page.keyboard.press('Escape');
                await expect(dialog).toHaveCount(0);
                await expect(trigger).toBeFocused();
                const unlocked = await page.evaluate(() => ({
                    overflow: document.body.style.overflow,
                    inert: document.getElementById('app')?.inert,
                }));
                expect(unlocked).toEqual({ overflow: '', inert: false });
                writeRaw('regression', `F-30-${mc.name}-${vp.label}`, {
                    titleHit,
                    headerHit,
                    before,
                    after,
                });
                expect(stores).toEqual([]);
                expect(diag.pageErrors).toEqual([]);
            });
        });
    }
}

// =============================================================================================
// F-31 / F-32 – Rezeptdetail
// =============================================================================================
test.describe('Host – Rezeptdetail-Kopf (F-31)', () => {
    test.use({ storageState: authFile('host') });

    for (const width of [768, 1024, 1440]) {
        test(`F-31 Titel und Aktionen ueberschneiden sich nicht (${width})`, async ({
            page,
        }) => {
            await page.setViewportSize({ width, height: 900 });
            const rows: Record<string, unknown>[] = [];
            for (const r of [
                DATA.recipes.withImage,
                DATA.recipes.longTitle,
                DATA.recipes.withoutImage,
            ]) {
                await page.goto(`/rezept/${r.id}`);
                await settle(page);
                await expect(page.locator('h1')).toHaveCount(1);
                await expect(
                    page.locator('main main, main main *'),
                ).toHaveCount(0);
                // Gate C: Aktionen ueber Rollen (Button/Link) statt .recipe-actions
                const h1 = page.locator('h1');
                const title = (await h1.boundingBox())!;
                const actionLocators = [
                    page.getByRole('button', {
                        name: 'Zur Sammlung hinzufügen',
                    }),
                    page.getByRole('link', { name: 'Rezept bearbeiten' }),
                    page.getByRole('button', { name: 'Rezept löschen' }),
                ];
                const buttons: Box[] = [];
                for (const l of actionLocators) {
                    await expect(l).toBeVisible();
                    buttons.push((await l.boundingBox())!);
                }
                const titleBox = title;
                const overlapping = buttons.filter((b) =>
                    intersects(b, titleBox),
                );
                // Eckdaten-Kacheln (Zubereitung, Portionen, Bewertung)
                const meta = page.getByRole('definition').locator('..');
                const metaBoxes = await meta.evaluateAll((els) =>
                    els.map((e) => e.getBoundingClientRect().toJSON()),
                );
                rows.push({ id: r.id, title, buttons, metaBoxes });
                expect(
                    overlapping,
                    `Rezept ${r.id}: Button ueber Titel`,
                ).toEqual([]);
                // Aktionen liegen unterhalb des Titels und ueberlappen sich nicht gegenseitig
                for (const b of buttons)
                    expect(b.y).toBeGreaterThanOrEqual(title.y + title.height);
                for (let a = 0; a < buttons.length; a++)
                    for (let b = a + 1; b < buttons.length; b++)
                        expect(
                            intersects(buttons[a], buttons[b]),
                            `Aktionen ${a}/${b} ueberlappen`,
                        ).toBe(false);
                if (r.id === DATA.recipes.withoutImage.id && width > 900) {
                    // ohne Bild: Titel ueber die volle Inhaltsbreite statt schmaler Spalte
                    const metaWidth =
                        Math.max(...metaBoxes.map((m) => m.right)) -
                        Math.min(...metaBoxes.map((m) => m.left));
                    expect(title.width).toBeGreaterThan(metaWidth * 0.9);
                }
                // Meta-Kacheln fuellen eine Zeile (gleiche Oberkante, 3 Kacheln)
                expect(metaBoxes).toHaveLength(3);
                expect(
                    new Set(metaBoxes.map((m) => Math.round(m.top))).size,
                    'Eckdaten in einer Zeile',
                ).toBe(1);
                await page.screenshot({
                    path: test
                        .info()
                        .outputPath(`F-31-rezept-${r.id}-${width}.png`),
                });
            }
            writeRaw('regression', `F-31-${width}`, { rows });
        });
    }

    test('F-31 Notizen-Abschnitt nur mit Notizen; Ueberschriften-Hierarchie', async ({
        page,
    }) => {
        await page.goto(`/rezept/${DATA.recipes.withImage.id}`);
        await settle(page);
        await expect(
            page.getByRole('heading', { level: 2, name: 'Notizen & Tipps' }),
        ).toHaveCount(1);
        await page.goto(`/rezept/${DATA.recipes.withoutImage.id}`);
        await settle(page);
        const props = await inertiaProps<{ recipe: { notes: string[] } }>(page);
        await expect(page.getByText('Notizen & Tipps')).toHaveCount(
            props.recipe.notes.length ? 1 : 0,
        );
        await expect(
            page.getByRole('heading', { level: 2, name: 'Zutaten' }),
        ).toHaveCount(1);
    });
});

test.describe('Gast – lange Woerter (F-32)', () => {
    for (const vp of [
        { width: 320, height: 640, mobile: true },
        { width: 390, height: 844, mobile: true },
        { width: 1024, height: 768, mobile: false },
    ]) {
        test.describe(`${vp.width}`, () => {
            test.use({
                viewport: { width: vp.width, height: vp.height },
                isMobile: vp.mobile,
                hasTouch: vp.mobile,
            });

            test(`F-32 langer Titel bricht um, nichts ragt aus dem Inhalt (${vp.width})`, async ({
                page,
            }) => {
                const result: Record<string, unknown> = {};
                for (const path of [
                    `/rezept/${DATA.recipes.longTitle.id}`,
                    '/rezepte',
                ]) {
                    await page.goto(path);
                    await settle(page);
                    const m = await page.evaluate(() => {
                        const content =
                            document.getElementById('main-content')!;
                        const cr = content.getBoundingClientRect();
                        // Gate C1b: nur der sichtbare Teil zaehlt – Vorfahren mit
                        // overflow-x != visible (dekorative Blobs/Blaetter in
                        // Abschnitten mit overflow-hidden) schneiden ab
                        const visible = (el: Element) => {
                            const r = el.getBoundingClientRect();
                            let left = r.left;
                            let right = r.right;
                            for (
                                let cur = el.parentElement;
                                cur && cur !== content.parentElement;
                                cur = cur.parentElement
                            ) {
                                if (
                                    getComputedStyle(cur).overflowX ===
                                    'visible'
                                )
                                    continue;
                                const pr = cur.getBoundingClientRect();
                                left = Math.max(left, pr.left);
                                right = Math.min(right, pr.right);
                            }
                            return { left, right };
                        };
                        const out: string[] = [];
                        for (const el of Array.from(
                            content.querySelectorAll('*'),
                        )) {
                            const s = getComputedStyle(el);
                            if (
                                s.display === 'none' ||
                                s.visibility === 'hidden'
                            )
                                continue;
                            const r = el.getBoundingClientRect();
                            if (r.width === 0 || r.height === 0) continue;
                            const v = visible(el);
                            if (v.right <= v.left) continue; // vollstaendig abgeschnitten
                            if (v.right > cr.right + 1 || v.left < cr.left - 1)
                                out.push(
                                    `${el.tagName.toLowerCase()}.${String(el.className)} (${Math.round(r.left)}–${Math.round(r.right)})`,
                                );
                        }
                        const clipped = Array.from(
                            content.querySelectorAll<HTMLElement>(
                                'h1, h2, h3, p, li, a',
                            ),
                        )
                            .filter((el) => el.scrollWidth > el.clientWidth + 1)
                            .map((el) =>
                                (el.textContent ?? '').trim().slice(0, 40),
                            );
                        return {
                            contentRight: Math.round(cr.right),
                            overflowing: out.slice(0, 10),
                            clipped,
                            docOverflow:
                                document.documentElement.scrollWidth >
                                window.innerWidth,
                        };
                    });
                    result[path] = m;
                    expect(
                        m.overflowing,
                        `${path}: Elemente ausserhalb`,
                    ).toEqual([]);
                    expect(m.clipped, `${path}: abgeschnittener Text`).toEqual(
                        [],
                    );
                    expect(m.docOverflow).toBe(false);
                }
                const h1 = page.locator('h1');
                await page.goto(`/rezept/${DATA.recipes.longTitle.id}`);
                await settle(page);
                const sizes = await h1.evaluate((el) => ({
                    scrollWidth: el.scrollWidth,
                    clientWidth: el.clientWidth,
                }));
                expect(sizes.scrollWidth).toBeLessThanOrEqual(
                    sizes.clientWidth,
                );
                await page.screenshot({
                    path: test.info().outputPath(`F-32-${vp.width}.png`),
                });
                writeRaw('regression', `F-32-${vp.width}`, { result, sizes });
            });
        });
    }
});

// =============================================================================================
// F-39 – Zur Sammlung hinzufuegen
// =============================================================================================
test.describe('F-39 Gast', () => {
    test('F-39 Gast sieht Login-Hinweis statt Sackgasse', async ({ page }) => {
        await page.goto(`/rezept/${DATA.recipes.withImage.id}`);
        await settle(page);
        await page
            .getByRole('button', { name: 'Zur Sammlung hinzufügen' })
            .click();
        const dialog = page.getByRole('dialog', {
            name: 'Zur Sammlung hinzufügen',
        });
        await expect(dialog).toContainText(
            'Melde dich an, um Rezepte in Sammlungen zu speichern',
        );
        await expect(
            dialog.getByRole('link', { name: 'Anmelden' }),
        ).toHaveAttribute('href', /\/login$/);
        await expect(dialog.getByRole('checkbox')).toHaveCount(0);
        await expect(dialog).not.toContainText('noch keine Sammlungen');
    });
});

test.describe('F-39 Host ohne Sammlung', () => {
    test.use({ storageState: authFile('host') });

    test('F-39 ohne Sammlung: Link "Neue Sammlung anlegen"', async ({
        page,
    }) => {
        await page.goto(`/rezept/${DATA.recipes.withImage.id}`);
        await settle(page);
        const props = await inertiaProps<{ collections: unknown[] }>(page);
        test.skip(
            props.collections.length > 0,
            'Host hat inzwischen Sammlungen',
        );
        await page
            .getByRole('button', { name: 'Zur Sammlung hinzufügen' })
            .click();
        const dialog = page.getByRole('dialog', {
            name: 'Zur Sammlung hinzufügen',
        });
        await expect(
            dialog.getByRole('link', { name: 'Neue Sammlung anlegen' }),
        ).toHaveAttribute('href', /\/sammlungen\/erstellen$/);
    });
});

test.describe('F-39 Nutzerin', () => {
    test.use({ storageState: authFile('user') });

    test('F-39 ohne Auswahl deaktiviert; Teilerfolg und 500 werden sichtbar gemeldet', async ({
        page,
    }) => {
        const posts: string[] = [];
        await page.route('**/collections/*/recipes', async (route) => {
            if (route.request().method() !== 'POST') return route.continue();
            posts.push(new URL(route.request().url()).pathname);
            if (
                route
                    .request()
                    .url()
                    .includes(`/collections/${DATA.collections.empty.id}/`)
            )
                return route.fulfill({
                    status: 500,
                    contentType: 'text/html',
                    body: 'Server Error',
                });
            return route.continue();
        });
        await page.goto(`/rezept/${DATA.recipes.withImage.id}`);
        await settle(page);
        await page
            .getByRole('button', { name: 'Zur Sammlung hinzufügen' })
            .click();
        const dialog = page.getByRole('dialog', {
            name: 'Zur Sammlung hinzufügen',
        });
        const add = dialog.getByRole('button', { name: 'Hinzufügen' });
        await expect(add).toBeDisabled();

        // Reihenfolge der Auswahl = Reihenfolge der Requests: erst Wochenplan (echt), dann Leere (500)
        await dialog.getByLabel(DATA.collections.filled.name).check();
        await expect(add).toBeEnabled();
        await dialog.getByLabel(DATA.collections.empty.name).check();
        await add.click();

        await expect(dialog.getByRole('alert')).toContainText(
            `„${DATA.collections.empty.name}“`,
        );
        await expect(dialog.getByRole('alert')).toContainText(
            'Auf dem Server ist ein Fehler aufgetreten',
        );
        await expect(dialog.getByRole('status')).toContainText(
            DATA.collections.filled.name,
        );
        await expect(dialog).toBeVisible();
        expect(posts).toEqual([
            `/collections/${DATA.collections.filled.id}/recipes`,
            `/collections/${DATA.collections.empty.id}/recipes`,
        ]);
        await page.unrouteAll({ behavior: 'ignoreErrors' });
    });
});

// =============================================================================================
// F-24 – TikTok-Import (alle Server-Antworten simuliert; es laeuft nie ein echter Import)
// =============================================================================================
test.describe('Host – TikTok-Import (F-24)', () => {
    test.use({ storageState: authFile('host') });

    const URL_OK = 'https://www.tiktok.com/@e2e/video/1';

    type Mode = {
        store: { status: number; body: unknown };
        show: () => { status: number; body?: unknown } | 'abort' | 'hang';
    };

    async function mockImport(page: Page, mode: Mode) {
        const log = { store: 0, show: 0 };
        await page.route(
            (url) => url.pathname === '/rezept-import',
            (route) => {
                log.store++;
                return route.fulfill({
                    status: mode.store.status,
                    contentType: 'application/json',
                    body: JSON.stringify(mode.store.body),
                });
            },
        );
        await page.route(
            (url) => /^\/rezept-import\/\d+$/.test(url.pathname),
            async (route) => {
                log.show++;
                const r = mode.show();
                if (r === 'abort') return route.abort('internetdisconnected');
                if (r === 'hang') return; // nie beantwortet
                return route.fulfill({
                    status: r.status,
                    contentType: 'application/json',
                    body: JSON.stringify(r.body),
                });
            },
        );
        return log;
    }

    async function start(page: Page) {
        await page.goto('/rezepte/erstellen');
        await settle(page);
        const input = page.getByLabel('Rezept aus TikTok importieren');
        await input.fill(URL_OK);
        await page.getByRole('button', { name: 'Importieren' }).click();
    }

    test('F-24 Start-Fehler 429/409/422 zeigen die Server-Meldung', async ({
        page,
    }) => {
        for (const [status, body] of [
            [429, { message: 'E2E Limit erreicht' }],
            [409, { message: 'E2E Import läuft bereits' }],
            [422, { message: 'x', errors: { url: ['E2E ungültiger Link'] } }],
        ] as const) {
            await page.unrouteAll({ behavior: 'ignoreErrors' });
            await mockImport(page, {
                store: { status, body },
                show: () => ({ status: 500 }),
            });
            await start(page);
            const alert = page.locator('[data-tiktok-import] [role="alert"]');
            await expect(alert).toContainText(
                status === 422 ? 'E2E ungültiger Link' : body.message,
            );
            await expect(
                page.getByLabel('Rezept aus TikTok importieren'),
            ).toHaveAttribute('aria-invalid', 'true');
        }
    });

    test('F-24 Status "failed" zeigt die Meldung; Status laeuft in role=status', async ({
        page,
    }) => {
        let n = 0;
        const log = await mockImport(page, {
            store: { status: 200, body: { id: 999 } },
            show: () =>
                ++n < 2
                    ? { status: 200, body: { id: 999, status: 'downloading' } }
                    : {
                          status: 200,
                          body: {
                              id: 999,
                              status: 'failed',
                              error: 'E2E Import fehlgeschlagen',
                          },
                      },
        });
        await start(page);
        const status = page.locator('[data-tiktok-import] [role="status"]');
        await expect(status).toContainText('Video wird geladen', {
            timeout: 8000,
        });
        await expect(
            page.locator('[data-tiktok-import] [role="alert"]'),
        ).toContainText('E2E Import fehlgeschlagen', { timeout: 8000 });
        const count = log.show;
        await page.waitForTimeout(4000);
        expect(log.show, 'nach "failed" keine weiteren Abfragen').toBe(count);
    });

    test('F-24 ein Netzwerkfehler wird toleriert, drei in Folge beenden das Polling', async ({
        page,
    }) => {
        test.setTimeout(60_000);
        const log = await mockImport(page, {
            store: { status: 200, body: { id: 999 } },
            show: () => 'abort',
        });
        await start(page);
        // nach dem ersten Fehler laeuft der Import weiter
        await expect.poll(() => log.show, { timeout: 8000 }).toBe(1);
        await expect(
            page.getByRole('button', { name: 'Abbrechen' }),
        ).toBeVisible();
        await expect(
            page.locator('[data-tiktok-import] [role="alert"]'),
        ).toHaveCount(0);
        await expect(
            page.locator('[data-tiktok-import] [role="alert"]'),
        ).toContainText('mehrfach fehlgeschlagen', { timeout: 15_000 });
        expect(log.show).toBe(3);
        await page.waitForTimeout(4000);
        expect(log.show, 'keine weiteren Abfragen').toBe(3);
    });

    test('F-24 "Abbrechen" stoppt das Polling', async ({ page }) => {
        const log = await mockImport(page, {
            store: { status: 200, body: { id: 999 } },
            show: () => ({ status: 200, body: { id: 999, status: 'pending' } }),
        });
        await start(page);
        await expect.poll(() => log.show, { timeout: 8000 }).toBeGreaterThan(0);
        await page.getByRole('button', { name: 'Abbrechen' }).click();
        const count = log.show;
        await expect(
            page.locator('[data-tiktok-import] [role="status"]'),
        ).toContainText('abgebrochen');
        await page.waitForTimeout(7000);
        expect(log.show, 'keine Abfragen nach Abbrechen').toBe(count);
        await expect(
            page.getByRole('button', { name: 'Importieren' }),
        ).toBeEnabled();
    });

    test('F-24 Zeitlimit (Playwright-Clock): nach 16 Minuten Abbruch mit Meldung', async ({
        page,
    }) => {
        await page.clock.install({ time: new Date('2026-01-15T10:00:00Z') });
        const log = await mockImport(page, {
            store: { status: 200, body: { id: 999 } },
            show: () => 'hang',
        });
        await start(page);
        await page.clock.fastForward('00:04');
        await expect.poll(() => log.show).toBe(1);
        await page.clock.fastForward('16:00');
        await expect(
            page.locator('[data-tiktok-import] [role="alert"]'),
        ).toContainText('ungewöhnlich lange');
        await expect(
            page.getByRole('button', { name: 'Abbrechen' }),
        ).toHaveCount(0);
    });

    test('F-24 fertiger Import ueberschreibt ein ausgefuelltes Formular erst nach Rueckfrage', async ({
        page,
    }) => {
        await mockImport(page, {
            store: { status: 200, body: { id: DATA.importWithSource.id } },
            show: () => ({
                status: 200,
                body: { id: DATA.importWithSource.id, status: 'done' },
            }),
        });
        await page.goto('/rezepte/erstellen');
        await settle(page);
        await page.locator('#name').fill('E2E Eigene Eingabe');
        await page.getByLabel('Rezept aus TikTok importieren').fill(URL_OK);
        await page.getByRole('button', { name: 'Importieren' }).click();
        const confirm = page.getByRole('group', {
            name: /Der Import ist fertig/,
        });
        await expect(confirm).toBeVisible({ timeout: 8000 });
        await expect(
            confirm.getByRole('button', { name: 'Eingaben behalten' }),
        ).toBeFocused();
        await confirm
            .getByRole('button', { name: 'Eingaben behalten' })
            .click();
        await expect(page.locator('#name')).toHaveValue('E2E Eigene Eingabe');
        expect(new URL(page.url()).searchParams.get('import')).toBeNull();

        // erneut, diesmal übernehmen -> Vorbelegung aus dem Import
        await page.getByRole('button', { name: 'Importieren' }).click();
        await expect(confirm).toBeVisible({ timeout: 8000 });
        await Promise.all([
            page.waitForURL(/import=/),
            confirm.getByRole('button', { name: 'Import übernehmen' }).click(),
        ]);
        await settle(page);
        await expect(page.locator('#name')).toHaveValue(
            DATA.importWithSource.name,
        );
    });

    // -----------------------------------------------------------------------------------------
    // Slice 4 – Fortschrittsanzeige (ImportProgress)
    // -----------------------------------------------------------------------------------------
    const STEPS = [
        'Gestartet',
        'Video wird geladen',
        'Rezept wird erkannt',
        'Fertig',
    ] as const;
    type StepState = 'done' | 'active' | 'failed' | 'todo';

    const stepper = (page: Page) =>
        page.getByRole('list', { name: 'Import-Fortschritt' });

    /** Prüft Zustand aller Schritte und dass genau der erwartete `aria-current="step"` trägt. */
    async function expectSteps(
        page: Page,
        current: (typeof STEPS)[number],
        currentState: StepState,
    ) {
        const items = stepper(page).getByRole('listitem');
        await expect(items).toHaveCount(4);
        const currentIndex = STEPS.indexOf(current);
        await expect(
            stepper(page).locator('[aria-current="step"]'),
            `aria-current auf "${current}"`,
        ).toContainText(current, { timeout: 8000 });
        await expect(stepper(page).locator('[aria-current]')).toHaveCount(1);
        for (const [i, label] of STEPS.entries()) {
            const expected: StepState =
                i < currentIndex
                    ? 'done'
                    : i === currentIndex
                      ? currentState
                      : 'todo';
            await expect(items.nth(i), `${label}: ${expected}`).toHaveAttribute(
                'data-state',
                expected,
            );
        }
    }

    const statusRegion = (page: Page) =>
        page.locator('[data-tiktok-import] [role="status"]');

    for (const variant of [
        { name: '1440', use: {} },
        { name: '390', use: MOBILE_390 },
    ] as const) {
        test.describe(`Fortschritt ${variant.name} px`, () => {
            test.use(variant.use);

            test(`F-24 Stepper durchlaeuft pending -> downloading -> analyzing -> done (${variant.name})`, async ({
                page,
            }) => {
                test.setTimeout(60_000);
                let state = 'pending';
                const log = await mockImport(page, {
                    store: {
                        status: 200,
                        body: { id: DATA.importWithSource.id },
                    },
                    show: () => ({
                        status: 200,
                        body: { id: DATA.importWithSource.id, status: state },
                    }),
                });
                await start(page);
                await expectSteps(page, 'Gestartet', 'active');
                await expect(statusRegion(page)).toContainText('angenommen');
                await expect(
                    page.locator('[data-progress-bar]'),
                    'unbestimmter Balken',
                ).toBeVisible();
                await expect(
                    page.locator('[data-progress-bar] [aria-valuenow]'),
                    'keine Prozentwerte',
                ).toHaveCount(0);
                await expect
                    .poll(() => log.show, { timeout: 8000 })
                    .toBeGreaterThan(0);

                state = 'downloading';
                await expectSteps(page, 'Video wird geladen', 'active');
                await expect(statusRegion(page)).toContainText(
                    'Video wird geladen',
                );

                state = 'analyzing';
                await expectSteps(page, 'Rezept wird erkannt', 'active');
                await expect(statusRegion(page)).toContainText(
                    'einige Minuten',
                );

                state = 'done';
                await expectSteps(page, 'Fertig', 'done');
                await expect(statusRegion(page)).toContainText('Fertig');
                await expect(
                    page.getByRole('button', { name: 'Abbrechen' }),
                ).toHaveCount(0);
                // danach wie bisher: Formular wird aus dem Import befuellt
                await page.waitForURL(/import=/, { timeout: 8000 });
                await settle(page);
                await expect(page.locator('#name')).toHaveValue(
                    DATA.importWithSource.name,
                );
            });

            test(`F-24 "failed" waehrend downloading markiert den Schritt; "Erneut versuchen" sendet neuen POST (${variant.name})`, async ({
                page,
            }) => {
                test.setTimeout(60_000);
                let state: 'downloading' | 'failed' = 'downloading';
                const mode = {
                    store: { status: 200, body: { id: 999 } as unknown },
                    show: () => ({
                        status: 200,
                        body: {
                            id: 999,
                            status: state,
                            error:
                                state === 'failed'
                                    ? 'E2E Video nicht ladbar'
                                    : null,
                        },
                    }),
                };
                const log = await mockImport(page, mode);
                await start(page);
                await expectSteps(page, 'Video wird geladen', 'active');
                state = 'failed';
                await expectSteps(page, 'Video wird geladen', 'failed');
                await expect(
                    page.locator('[data-tiktok-import] [role="alert"]'),
                ).toHaveText('E2E Video nicht ladbar');
                await expect(page.locator('[data-progress-bar]')).toHaveCount(
                    0,
                );
                expect(log.store).toBe(1);

                // Erneut versuchen: neuer POST mit demselben Link; Server lehnt mit Limit ab
                mode.store = {
                    status: 429,
                    body: { message: 'E2E Limit erreicht' },
                };
                const post = page.waitForRequest(
                    (r) =>
                        r.method() === 'POST' &&
                        new URL(r.url()).pathname === '/rezept-import',
                );
                await page
                    .getByRole('button', { name: 'Erneut versuchen' })
                    .click();
                const body = (await post).postDataJSON() as { url: string };
                expect(body.url).toBe(URL_OK);
                await expect.poll(() => log.store).toBe(2);
                await expect(
                    page.locator('[data-tiktok-import] [role="alert"]'),
                ).toHaveText('E2E Limit erreicht');
                await expectSteps(page, 'Gestartet', 'failed');

                // dritter Versuch wird angenommen -> laeuft wieder
                mode.store = { status: 200, body: { id: 999 } };
                state = 'downloading';
                await page
                    .getByRole('button', { name: 'Erneut versuchen' })
                    .click();
                await expect.poll(() => log.store).toBe(3);
                await expect(
                    page.locator('[data-tiktok-import] [role="alert"]'),
                ).toHaveCount(0);
                await expect(
                    page.getByRole('button', { name: 'Abbrechen' }),
                ).toBeFocused();
                await expectSteps(page, 'Video wird geladen', 'active');
            });
        });
    }

    test('F-24 Warte-Hinweis erscheint erst, wenn "pending" laenger als 30 s dauert (Clock)', async ({
        page,
    }) => {
        await page.clock.install({ time: new Date('2026-01-15T10:00:00Z') });
        // zunaechst ohne das optionale Feld `started` (aelterer Server)
        let started: boolean | null = null;
        const log = await mockImport(page, {
            store: { status: 200, body: { id: 999 } },
            show: () => ({
                status: 200,
                body:
                    started === null
                        ? { id: 999, status: 'pending' }
                        : { id: 999, status: 'pending', started },
            }),
        });
        await start(page);
        await expectSteps(page, 'Gestartet', 'active');
        const hint = page.locator('[data-import-hint]');
        await page.clock.fastForward('00:20');
        await expect.poll(() => log.show).toBeGreaterThan(0);
        await expect(hint).toHaveCount(0);
        await expect(page.locator('[data-import-time]')).toContainText(
            /seit 0:2\d/,
        );
        await page.clock.fastForward('00:15');
        await expect(hint).toBeVisible();
        await expect(hint).toContainText('Queue-Worker');
        // Hinweis wird ueber die Live-Region angesagt
        await expect(statusRegion(page)).toContainText(
            'wartet auf die Verarbeitung',
        );

        // meldet der Server started: true, verschwindet der Hinweis
        started = true;
        const before = log.show;
        await page.clock.fastForward('00:04');
        await expect.poll(() => log.show).toBeGreaterThan(before);
        await expect(hint).toHaveCount(0);
    });

    test('F-24 Live-Region aendert sich nur bei Schrittwechsel, nicht mit der Uhr (Clock)', async ({
        page,
    }) => {
        await page.clock.install({ time: new Date('2026-01-15T10:00:00Z') });
        let state = 'pending';
        const log = await mockImport(page, {
            store: { status: 200, body: { id: 999 } },
            show: () => ({ status: 200, body: { id: 999, status: state } }),
        });
        await page.goto('/rezepte/erstellen');
        await settle(page);
        await page.evaluate(() => {
            const el = document.querySelector(
                '[data-tiktok-import] [role="status"]',
            )!;
            const w = window as unknown as { __live: string[] };
            w.__live = [];
            new MutationObserver(() => {
                const text = (el.textContent ?? '').trim();
                if (text && w.__live[w.__live.length - 1] !== text)
                    w.__live.push(text);
            }).observe(el, {
                childList: true,
                subtree: true,
                characterData: true,
            });
        });
        await page.getByLabel('Rezept aus TikTok importieren').fill(URL_OK);
        await page.getByRole('button', { name: 'Importieren' }).click();
        await expectSteps(page, 'Gestartet', 'active');
        const time = page.locator('[data-import-time]');
        const t0 = await time.textContent();
        await page.clock.runFor('00:10');
        await expect.poll(() => log.show).toBeGreaterThan(0);
        await expect(time).not.toHaveText(t0 ?? '');

        state = 'downloading';
        await page.clock.runFor('00:04');
        await expectSteps(page, 'Video wird geladen', 'active');
        await page.clock.runFor('00:10');

        const live = await page.evaluate(
            () => (window as unknown as { __live: string[] }).__live,
        );
        // hoechstens: "wird gestartet", "angenommen", "Video wird geladen"
        expect(live.length, live.join(' | ')).toBeLessThanOrEqual(3);
        expect(live[live.length - 1]).toContain('Video wird geladen');
        for (const text of live) expect(text).not.toMatch(/\d:\d\d/);
    });

    test('F-24 Fortschritt bei 320 px ohne horizontalen Overflow, Schritte untereinander', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 320, height: 720 });
        let state = 'downloading';
        await mockImport(page, {
            store: { status: 200, body: { id: 999 } },
            show: () => ({
                status: 200,
                body: {
                    id: 999,
                    status: state,
                    error: state === 'failed' ? 'E2E Fehler' : null,
                },
            }),
        });
        await start(page);
        await expectSteps(page, 'Video wird geladen', 'active');
        const noOverflow = () =>
            page.evaluate(
                () =>
                    document.documentElement.scrollWidth <= window.innerWidth &&
                    Array.from(
                        document.querySelectorAll('[data-import-progress] *'),
                    ).every(
                        (el) =>
                            el.getBoundingClientRect().right <=
                            window.innerWidth + 1,
                    ),
            );
        expect(await noOverflow(), 'laufend').toBe(true);
        const ys = await stepper(page)
            .getByRole('listitem')
            .evaluateAll((els) =>
                els.map((e) => e.getBoundingClientRect().top),
            );
        for (let i = 1; i < ys.length; i++)
            expect(ys[i], 'Schritte untereinander').toBeGreaterThan(ys[i - 1]);

        state = 'failed';
        await expectSteps(page, 'Video wird geladen', 'failed');
        await expect(
            page.getByRole('button', { name: 'Erneut versuchen' }),
        ).toBeVisible();
        expect(await noOverflow(), 'fehlgeschlagen').toBe(true);
    });

    test('F-24 Video-Weg: Ruecksprung analyzing -> downloading -> analyzing setzt nichts zurueck', async ({
        page,
    }) => {
        test.setTimeout(60_000);
        let state = 'downloading';
        const log = await mockImport(page, {
            store: { status: 200, body: { id: DATA.importWithSource.id } },
            // mit den Zusatzfeldern des neuen Backends
            show: () => ({
                status: 200,
                body: {
                    id: DATA.importWithSource.id,
                    status: state,
                    started: state !== 'pending',
                    created_at: '2026-01-15T10:00:00Z',
                    updated_at: '2026-01-15T10:00:05Z',
                },
            }),
        });
        await start(page);
        await expectSteps(page, 'Video wird geladen', 'active');
        state = 'analyzing';
        await expectSteps(page, 'Rezept wird erkannt', 'active');

        // Ruecksprung: Beschreibung reichte nicht, Video wird geladen
        state = 'downloading';
        const before = log.show;
        await expect(statusRegion(page)).toContainText(
            'Beschreibung reichte nicht',
            { timeout: 8000 },
        );
        expect(log.show).toBeGreaterThan(before);
        await expectSteps(page, 'Rezept wird erkannt', 'active');
        await expect(
            page.locator('[data-import-progress] li[data-state="failed"]'),
        ).toHaveCount(0);
        await expect(
            page.locator('[data-tiktok-import] [role="alert"]'),
        ).toHaveCount(0);

        state = 'analyzing';
        await expect(statusRegion(page)).toContainText(
            'aus dem Video erkannt',
            { timeout: 8000 },
        );
        await expectSteps(page, 'Rezept wird erkannt', 'active');

        state = 'done';
        await expectSteps(page, 'Fertig', 'done');
        await page.waitForURL(/import=/, { timeout: 8000 });
    });

    test('F-24 Stepper bei 1440 px waagerecht', async ({ page }) => {
        await mockImport(page, {
            store: { status: 200, body: { id: 999 } },
            show: () => ({ status: 200, body: { id: 999, status: 'pending' } }),
        });
        await start(page);
        await expectSteps(page, 'Gestartet', 'active');
        const ys = await stepper(page)
            .getByRole('listitem')
            .evaluateAll((els) =>
                els.map((e) => Math.round(e.getBoundingClientRect().top)),
            );
        expect(new Set(ys).size, 'alle Schritte in einer Zeile').toBe(1);
    });

    for (const reduced of [false, true]) {
        test(`F-24 Animation ${reduced ? 'aus bei prefers-reduced-motion: reduce' : 'an ohne reduced motion'}`, async ({
            page,
        }) => {
            await page.emulateMedia({
                reducedMotion: reduced ? 'reduce' : 'no-preference',
            });
            await mockImport(page, {
                store: { status: 200, body: { id: 999 } },
                show: () => ({
                    status: 200,
                    body: { id: 999, status: 'downloading' },
                }),
            });
            await start(page);
            await expectSteps(page, 'Video wird geladen', 'active');
            const names = await page.evaluate(() => {
                const marker = document.querySelector(
                    '[data-import-progress] li[data-state="active"] [data-progress-marker]',
                )!;
                const bar = document.querySelector('[data-progress-bar-fill]')!;
                return [
                    getComputedStyle(marker, '::after').animationName,
                    getComputedStyle(bar).animationName,
                ];
            });
            if (reduced) expect(names).toEqual(['none', 'none']);
            else for (const n of names) expect(n).not.toBe('none');
        });
    }
});

// =============================================================================================
// F-44 – Rezeptliste
// =============================================================================================
test.describe('Gast – Rezeptliste (F-44)', () => {
    test('F-44 Suche entprellt, Leerzustand mit "Filter zuruecksetzen", Trefferzahl als Status', async ({
        page,
    }) => {
        await page.goto('/rezepte');
        await settle(page);
        await expect(page.locator('h1')).toHaveCount(1);
        const status = page.getByRole('status').filter({ hasText: 'Ergebnis' });
        const initial = await status.textContent();
        const searches: string[] = [];
        page.on('request', (r) => {
            const u = new URL(r.url());
            if (u.pathname === '/rezepte' && r.headers()['x-inertia'])
                searches.push(u.search);
        });
        const input = page.getByLabel('Rezepte nach Namen durchsuchen');
        await expect(input).toHaveAttribute(
            'placeholder',
            'Rezeptname eingeben …',
        );
        await input.pressSequentially('zzzqx', { delay: 60 });
        await page.waitForTimeout(800);
        expect(searches, 'genau eine Anfrage fuer fuenf Tastendruecke').toEqual(
            ['?search=zzzqx'],
        );
        // Taste ohne Textaenderung loest nichts aus
        await input.press('ArrowLeft');
        await input.press('Shift');
        await page.waitForTimeout(600);
        expect(searches).toHaveLength(1);

        const empty = page.getByRole('heading', {
            name: 'Keine Rezepte gefunden',
        });
        await expect(empty).toBeVisible();
        await expect(status).toHaveText('0 Ergebnisse');
        await page.getByRole('button', { name: 'Filter zurücksetzen' }).click();
        await expect(empty).toHaveCount(0);
        await expect(status).toHaveText(initial ?? '');
        await expect(input).toHaveValue('');
        await expect(
            page.getByRole('main').getByRole('article').first(),
        ).toBeVisible();
        writeRaw('regression', 'F-44', { searches });
    });
});

import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';
import { authFile, DATA, expect, settle, test } from './support/fixtures';
import { inertiaProps } from './support/helpers';

/**
 * Gate C2 – Nacharbeiten Rezeptformular und Sammlungen:
 *
 * - Kategorie bearbeiten ohne Doppelklick: sichtbarer Stift-Knopf je Kategorie
 *   (Tastatur/Touch), Fokus kehrt nach dem Dialog an den Knopf zurück
 * - Rezeptbild: deutscher Datei-Knopf „Bild auswählen“ statt „Choose File“,
 *   nativer Input bleibt fokussierbar und per Label verknüpft
 * - Sammlungs-Collage zeigt Rezeptbilder (dekorativ, lazy)
 * - Sammlung bearbeiten (Name/Beschreibung) im Dialog, Fehler am Feld, Flash
 *
 * Datenveränderungen: Kategorien werden nicht gespeichert (nur geöffnet und
 * abgebrochen). Die leere Sammlung wird umbenannt und am Ende des Tests auf
 * den Seeder-Stand zurückgesetzt.
 */

const CREATE = '/rezepte/erstellen';
const MOBILE_390 = {
    viewport: { width: 390, height: 844 },
    isMobile: true,
    hasTouch: true,
} as const;

const PNG_1X1 = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    'base64',
);

const editButton = (page: Page, name: string) =>
    page.getByRole('button', { name: `Kategorie „${name}“ bearbeiten` });

// ---------------------------------------------------------------------------------------------
test.describe('Kategorien: Bearbeiten per Knopf (Host)', () => {
    test.use({ storageState: authFile('host') });

    test('Tastatur: Tab vom Umschalter auf den Stift, Enter öffnet den Dialog, Escape gibt den Fokus zurück', async ({
        page,
    }) => {
        await page.goto(CREATE);
        await settle(page);

        const group = page.getByRole('group', { name: 'Kategorien' });
        const chip = group.getByRole('button', {
            name: 'Dessert',
            exact: true,
        });
        const edit = editButton(page, 'Dessert');
        await expect(edit).toBeVisible();
        await expect(edit).toHaveAttribute('aria-haspopup', 'dialog');

        // Hinweistext nennt den Stift (nicht nur den Doppelklick)
        await expect(
            page.locator('#abschnitt-kategorien'),
        ).toContainText('der Stift');

        await chip.focus();
        await page.keyboard.press('Tab');
        await expect(edit).toBeFocused();
        await page.keyboard.press('Enter');

        const dialog = page.getByRole('dialog', { name: 'Tag bearbeiten' });
        await expect(dialog).toBeVisible();
        await expect(dialog.locator('#tag-name')).toBeFocused();
        await expect(dialog.locator('#tag-name')).toHaveValue('Dessert');
        // Umschalter wurde beim Öffnen nicht verändert
        await expect(chip).toHaveAttribute('aria-pressed', 'false');

        await page.keyboard.press('Escape');
        await expect(dialog).toHaveCount(0);
        await expect(edit).toBeFocused();

        // Abbrechen-Knopf gibt den Fokus ebenfalls zurück
        await page.keyboard.press('Space');
        await expect(dialog).toBeVisible();
        await dialog.getByRole('button', { name: 'Abbrechen' }).click();
        await expect(dialog).toHaveCount(0);
        await expect(edit).toBeFocused();
    });

    test('Doppelklick auf den Umschalter öffnet weiterhin den Dialog', async ({
        page,
    }) => {
        await page.goto(CREATE);
        await settle(page);
        const chip = page.locator('[data-tag-chip]', { hasText: 'Dessert' });
        await chip.dblclick();
        const dialog = page.getByRole('dialog', { name: 'Tag bearbeiten' });
        await expect(dialog).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(chip).toBeFocused();
    });

    test('Touch 390: Stift-Knöpfe sind mindestens 44 × 44 px und öffnen den Dialog per Tippen', async ({
        browser,
    }) => {
        const context = await browser.newContext({
            ...MOBILE_390,
            storageState: authFile('host'),
            locale: 'de-DE',
            reducedMotion: 'reduce',
        });
        const page = await context.newPage();
        try {
            await page.goto(CREATE);
            await settle(page);
            const sizes = await page
                .locator('[data-tag-edit]')
                .evaluateAll((els) =>
                    els.map((e) => {
                        const r = e.getBoundingClientRect();
                        return {
                            label: e.getAttribute('aria-label'),
                            w: Math.round(r.width),
                            h: Math.round(r.height),
                        };
                    }),
                );
            expect(sizes.length).toBeGreaterThan(0);
            const small = sizes.filter((s) => s.w < 44 || s.h < 44);
            expect(small, JSON.stringify(small)).toEqual([]);

            const edit = editButton(page, 'Dessert');
            await edit.scrollIntoViewIfNeeded();
            await edit.tap();
            const dialog = page.getByRole('dialog', {
                name: 'Tag bearbeiten',
            });
            await expect(dialog).toBeVisible();
            await dialog.getByRole('button', { name: 'Abbrechen' }).tap();
            await expect(dialog).toHaveCount(0);

            // kein horizontales Scrollen durch die zusätzlichen Knöpfe
            const overflow = await page.evaluate(
                () =>
                    document.documentElement.scrollWidth -
                    document.documentElement.clientWidth,
            );
            expect(overflow).toBeLessThanOrEqual(0);
        } finally {
            await context.close();
        }
    });
});

// ---------------------------------------------------------------------------------------------
test.describe('Rezeptbild: deutscher Datei-Knopf (Host)', () => {
    test.use({ storageState: authFile('host') });

    test('„Bild auswählen“ statt „Choose File“; Label verknüpft, Dateiname wird angezeigt', async ({
        page,
    }) => {
        await page.goto(CREATE);
        await settle(page);

        const input = page.locator('#image');
        const picker = page.locator('[data-image-picker]');
        await expect(picker.locator('[data-image-picker-button]')).toHaveText(
            'Bild auswählen',
        );
        await expect(page.locator('[data-image-file-name]')).toHaveText(
            'Keine Datei ausgewählt',
        );
        await expect(picker).not.toContainText('Choose');
        await expect(picker).not.toContainText('No file');

        // nativer Input: per Label erreichbar, fokussierbar, beschrieben
        await expect(page.getByLabel('Bild hochladen')).toHaveAttribute(
            'id',
            'image',
        );
        await expect(input).toHaveAttribute('type', 'file');
        await input.focus();
        await expect(input).toBeFocused();
        await expect(input).toHaveAccessibleDescription(
            /Optional.*Keine Datei ausgewählt/,
        );

        // Fokus per Tastatur ist sichtbar (Rahmen um den Bereich)
        await page.keyboard.press('Shift+Tab');
        await page.keyboard.press('Tab');
        await expect(input).toBeFocused();
        const outline = await picker.evaluate(
            (el) => getComputedStyle(el).outlineStyle,
        );
        expect(outline).not.toBe('none');

        // Klick auf den gestalteten Knopf öffnet die Dateiauswahl
        const [chooser] = await Promise.all([
            page.waitForEvent('filechooser'),
            picker.locator('[data-image-picker-button]').click(),
        ]);
        await chooser.setFiles({
            name: 'e2e-knopf.png',
            mimeType: 'image/png',
            buffer: PNG_1X1,
        });
        await expect(page.locator('[data-image-file-name]')).toHaveText(
            'e2e-knopf.png',
        );
        await expect(page.locator('[data-image-preview] img')).toBeVisible();

        // setInputFiles direkt auf #image (wie bestehende Tests)
        await input.setInputFiles({
            name: 'e2e.png',
            mimeType: 'image/png',
            buffer: PNG_1X1,
        });
        await expect(page.locator('[data-image-file-name]')).toHaveText(
            'e2e.png',
        );
        await expect(input).toHaveAccessibleDescription(/e2e\.png/);

        // Auswahl leeren -> wieder „Keine Datei ausgewählt“
        await input.setInputFiles([]);
        await expect(page.locator('[data-image-file-name]')).toHaveText(
            'Keine Datei ausgewählt',
        );
    });
});

// ---------------------------------------------------------------------------------------------
test.describe('Sammlungen (Nutzerin)', () => {
    test.use({ storageState: authFile('user') });

    test('Übersicht: Collage aus Rezeptbildern (alt="", lazy); leere Sammlung behält den Platzhalter', async ({
        page,
    }) => {
        await page.goto('/sammlungen');
        await settle(page);
        const props = await inertiaProps<{
            collections: { id: number; name: string; images: string[] }[];
        }>(page);

        const filled = props.collections.find(
            (c) => c.id === DATA.collections.filled.id,
        )!;
        const empty = props.collections.find(
            (c) => c.id === DATA.collections.empty.id,
        )!;
        expect(filled.images.length).toBeGreaterThan(0);
        expect(filled.images.length).toBeLessThanOrEqual(4);
        expect(empty.images).toEqual([]);

        const card = (name: string) =>
            page.locator('article', {
                has: page.getByRole('link', { name, exact: true }),
            });

        const imgs = card(filled.name).locator('[data-collage] img');
        await expect(imgs).toHaveCount(filled.images.length);
        for (const img of await imgs.all()) {
            await expect(img).toHaveAttribute('alt', '');
            await expect(img).toHaveAttribute('loading', 'lazy');
        }
        await expect(
            card(empty.name).locator('[data-collage]'),
        ).toHaveCount(0);

        const axe = await new AxeBuilder({ page }).analyze();
        expect(axe.violations.map((v) => v.id)).toEqual([]);
    });

    test('Sammlung bearbeiten: Dialog, Fehler am Feld, Speichern mit Erfolgsmeldung, Fokus zurück', async ({
        page,
    }) => {
        const id = DATA.collections.empty.id;
        const original = { name: DATA.collections.empty.name, description: '' };

        await page.goto(`/sammlung/${id}`);
        await settle(page);

        const trigger = page.getByRole('button', {
            name: 'Bearbeiten',
            exact: true,
        });
        await expect(trigger).toHaveAttribute('aria-haspopup', 'dialog');
        await trigger.click();

        const dialog = page.getByRole('dialog', {
            name: 'Sammlung bearbeiten',
        });
        await expect(dialog).toBeVisible();
        const name = dialog.getByLabel('Name der Sammlung');
        const description = dialog.getByLabel('Beschreibung (optional)');
        await expect(name).toBeFocused();
        await expect(name).toHaveValue(original.name);

        try {
            // 1) Escape verwirft, nichts wird gesendet
            const patches: string[] = [];
            page.on('request', (r) => {
                if (r.method() === 'PATCH') patches.push(r.url());
            });
            await name.fill('Verworfen');
            await page.keyboard.press('Escape');
            await expect(dialog).toHaveCount(0);
            await expect(trigger).toBeFocused();
            expect(patches).toEqual([]);
            await expect(page.getByRole('heading', { level: 1 })).toHaveText(
                original.name,
            );

            // 2) Leerer Name -> Fehler am Feld, Dialog bleibt offen
            await trigger.click();
            await expect(name).toHaveValue(original.name);
            await name.fill('');
            await dialog.getByRole('button', { name: 'Speichern' }).click();
            await expect(name).toHaveAttribute('aria-invalid', 'true');
            await expect(name).toBeFocused();
            await expect(dialog.locator('#collection-edit-name-error')).toBeVisible();
            await expect(dialog).toBeVisible();

            // 3) Speichern
            await name.fill('E2E Umbenannt');
            await description.fill('Neue Beschreibung aus dem Test');
            await Promise.all([
                page.waitForResponse(
                    (r) =>
                        r.url().includes(`/sammlung/${id}`) &&
                        r.request().method() === 'GET',
                ),
                dialog.getByRole('button', { name: 'Speichern' }).click(),
            ]);
            await expect(dialog).toHaveCount(0);
            await expect(page.getByRole('heading', { level: 1 })).toHaveText(
                'E2E Umbenannt',
            );
            await expect(page.getByRole('main')).toContainText(
                'Neue Beschreibung aus dem Test',
            );
            await expect(
                page.getByRole('status').filter({
                    hasText: 'Sammlung wurde gespeichert.',
                }),
            ).toBeVisible();
            await expect(trigger).toBeFocused();
        } finally {
            // Seeder-Stand wiederherstellen (andere Specs erwarten den Namen)
            if (await dialog.isVisible()) await page.keyboard.press('Escape');
            await trigger.click();
            await name.fill(original.name);
            await description.fill(original.description);
            await dialog.getByRole('button', { name: 'Speichern' }).click();
            await expect(dialog).toHaveCount(0);
            await expect(page.getByRole('heading', { level: 1 })).toHaveText(
                original.name,
            );
        }
    });
});

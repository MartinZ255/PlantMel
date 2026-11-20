import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';
import { DATA, expect, settle, test } from './support/fixtures';
import { inertiaProps } from './support/helpers';
import { writeRaw } from './support/raw';

/**
 * E14 – Bewertung nur noch als Geschmack (1–5 Sterne), keine Kategorien.
 *
 * Seed (database/seeders/E2eSeeder.php, unveraendert):
 * - Rezept 1 „Cremige Linsen-Bolognese“: Geschmack 5, dazu Altbewertungen
 *   Schwierigkeit 2, Aufwand 2, Gesamt 4 (bleiben in der DB, werden ignoriert).
 * - Rezept 5 „Quinoa-Bowl mit schwarzen Bohnen“: nur Gesamt 5 -> unbewertet.
 * - Rezept 6 „Schnelle Tomatenpasta“: keine Bewertungen -> unbewertet.
 *
 * Datenveraenderungen: keine.
 */

const RATED = DATA.recipes.withImage;
const ONLY_OTHER_DIMENSION = {
    id: 5,
    title: 'Quinoa-Bowl mit schwarzen Bohnen',
};
const NO_RATINGS = { id: 6, title: 'Schnelle Tomatenpasta' };
const OLD_CATEGORIES = /Schwierigkeit|Aufwand|Gesamt/;

type ShowProps = {
    recipe: {
        name: string;
        rating: number | null;
        ratingCount: number;
        ratingDimensions: unknown[];
    };
};

async function openRecipe(page: Page, id: number, title: string) {
    await page.goto(`/rezept/${id}`);
    await settle(page);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(title);
}

/** Eckdaten-Kiesel „Geschmack“ im Kopf (dt/dd) */
const headPebble = (page: Page) =>
    page.locator('dl > div').filter({
        has: page.getByRole('term').filter({ hasText: /^\s*Geschmack\s*$/ }),
    });

const ratingSection = (page: Page) =>
    page.getByRole('region', { name: 'Bewertung', exact: true });

async function axeViolations(page: Page) {
    const results = await new AxeBuilder({ page }).analyze();
    return results.violations.map((v) => `${v.id} (${v.impact ?? 'n/a'})`);
}

test.describe('E14 – Geschmacks-Bewertung auf der Rezeptdetailseite', () => {
    test('bewertetes Rezept: eine Geschmacks-Bewertung, keine Kategorien', async ({
        page,
    }) => {
        await openRecipe(page, RATED.id, RATED.title);

        const props = await inertiaProps<ShowProps>(page);
        expect(props.recipe.rating).toBe(5);
        expect(props.recipe.ratingCount).toBe(1);
        expect(props.recipe.ratingDimensions).toEqual([]);

        // Kopf: Kiesel heisst „Geschmack“, Sterne mit Textaequivalent
        const pebble = headPebble(page);
        await expect(pebble).toHaveCount(1);
        await expect(pebble.getByRole('definition')).toContainText('5 von 5');
        await expect(
            page.getByRole('term').filter({ hasText: /^\s*Bewertung\s*$/ }),
        ).toHaveCount(0);

        // Abschnitt: grosse Zahl, Sterne, Beschriftung „Geschmack“
        const section = ratingSection(page);
        await expect(section).toBeVisible();
        await expect(
            section.getByText('Geschmack', { exact: true }),
        ).toBeVisible();
        await expect(section).toContainText('5 von 5');
        await expect(
            section.locator('[aria-hidden="true"]').first(),
        ).toHaveText('5');
        // keine Kategorien, keine Balken, keine Liste je Dimension
        await expect(section).not.toContainText(OLD_CATEGORIES);
        await expect(section.locator('li')).toHaveCount(0);
        await expect(section.locator('.bg-tint')).toHaveCount(0);
        await expect(section).not.toContainText('Noch nicht bewertet');

        const violations = await axeViolations(page);
        writeRaw('regression', 'rating-e14-axe-rated', { violations });
        expect(violations).toEqual([]);
    });

    for (const recipe of [ONLY_OTHER_DIMENSION, NO_RATINGS]) {
        test(`unbewertet (${recipe.id}): „Noch nicht bewertet“ in Kopf und Abschnitt`, async ({
            page,
        }) => {
            await openRecipe(page, recipe.id, recipe.title);

            const props = await inertiaProps<ShowProps>(page);
            expect(props.recipe.rating).toBeNull();
            expect(props.recipe.ratingCount).toBe(0);
            expect(props.recipe.ratingDimensions).toEqual([]);

            await expect(headPebble(page).getByRole('definition')).toHaveText(
                'Noch nicht bewertet',
            );
            const section = ratingSection(page);
            await expect(section).toContainText('Noch nicht bewertet');
            await expect(section).not.toContainText(/von 5/);
            await expect(section).not.toContainText(OLD_CATEGORIES);

            const violations = await axeViolations(page);
            writeRaw('regression', `rating-e14-axe-unrated-${recipe.id}`, {
                violations,
            });
            expect(violations).toEqual([]);
        });
    }

    test('Rezeptliste: Karten ohne Geschmacks-Bewertung zeigen einheitlich „Noch nicht bewertet“', async ({
        page,
    }) => {
        // Gate C2 Integration: StarRating nutzt denselben Text wie die Detailseite
        await page.goto('/rezepte');
        await settle(page);
        const card = (title: string) =>
            page.getByRole('article').filter({
                has: page.getByRole('heading', { name: title, exact: true }),
            });
        for (const recipe of [ONLY_OTHER_DIMENSION, NO_RATINGS]) {
            await expect(card(recipe.title)).toHaveCount(1);
            await expect(card(recipe.title)).toContainText(
                'Noch nicht bewertet',
            );
            await expect(card(recipe.title)).not.toContainText(/von 5/);
        }
        await expect(card(RATED.title)).toContainText('5');
        await expect(card(RATED.title)).not.toContainText(
            'Noch nicht bewertet',
        );
        await expect(page.locator('#app')).not.toContainText(
            'Noch keine Bewertung',
        );
    });

    test('320 px: Bewertungsabschnitt ohne horizontalen Ueberlauf', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 320, height: 640 });
        await openRecipe(page, RATED.id, RATED.title);

        const section = ratingSection(page);
        await section.scrollIntoViewIfNeeded();
        const overflow = await page.evaluate(
            () =>
                document.documentElement.scrollWidth -
                document.documentElement.clientWidth,
        );
        expect(overflow).toBeLessThanOrEqual(0);
        const box = (await section.boundingBox())!;
        expect(box.x).toBeGreaterThanOrEqual(0);
        expect(box.x + box.width).toBeLessThanOrEqual(320);
    });
});

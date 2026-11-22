import AxeBuilder from '@axe-core/playwright';
import type { Browser, Page } from '@playwright/test';
import { BASE_URL } from './support/env';
import {
    authFile,
    DATA,
    expect,
    measureLayout,
    settle,
    test,
} from './support/fixtures';
import { inertiaProps, xsrfHeaders } from './support/helpers';

/**
 * E18 – Community-Rezepte (ADR-005).
 *
 * Datenveränderungen: legt eigene Community-Rezepte der E2E-Nutzerin an
 * (Titel mit Präfix „E2E-Community“), eine Meldung, und setzt/löscht einen
 * Gemini-Schlüssel bei user2. Freigegeben wird nur ein Rezept mit Zutaten
 * aus dem Katalog; der einzige neue Zutaten-Vorschlag hängt an einem
 * abgelehnten Rezept und erscheint damit nie im Katalog. Host-Seiten,
 * Zutatensuche und Baselines bleiben dadurch unverändert.
 * Keine KI-Aufrufe (GEMINI_API_KEY leer, kein Queue-Worker).
 */

const PREFIX = 'E2E-Community';
const FAKE_KEY = `AIza${'E'.repeat(31)}2e2e`;

async function roleContext(browser: Browser, role: 'user' | 'host' | 'user2') {
    const context = await browser.newContext({
        baseURL: BASE_URL,
        storageState: authFile(role),
        viewport: { width: 1440, height: 900 },
    });
    await context.route(
        (url) => url.origin !== BASE_URL,
        (route) => route.abort('blockedbyclient'),
    );
    return { context, page: await context.newPage() };
}

async function axeViolations(page: Page) {
    const results = await new AxeBuilder({ page }).analyze();
    return results.violations.map(
        (v) => `${v.id} (${v.impact ?? 'n/a'}): ${v.nodes.length}`,
    );
}

/** Community-Rezept per Request einreichen (wie das Formular, FormData). */
async function submitViaRequest(
    page: Page,
    title: string,
    ingredients: { name: string; amount?: string }[],
): Promise<number> {
    await page.goto('/community/erstellen');
    const form: Record<string, string> = { name: title, 'steps[0]': 'Kochen.' };
    ingredients.forEach((row, i) => {
        form[`ingredients[${i}][name]`] = row.name;
        form[`ingredients[${i}][amount]`] = row.amount ?? '';
    });
    const response = await page.request.post('/community', {
        headers: { ...(await xsrfHeaders(page)), Accept: 'text/html' },
        multipart: form,
        maxRedirects: 0,
    });
    expect(response.status()).toBe(302);
    const location = response.headers()['location'] ?? '';
    const id = Number(location.match(/\/community\/rezept\/(\d+)/)?.[1]);
    expect(id).toBeGreaterThan(0);
    return id;
}

test.describe('E18 Community-Rezepte', () => {
    test('Nutzerin reicht ein → wird geprüft → Host gibt frei → öffentlich', async ({
        browser,
        page: guest,
    }) => {
        const title = `${PREFIX} Linsen-Pfanne`;
        const user = await roleContext(browser, 'user');

        // 1) Formular ausfüllen (nur Katalog-Zutat) und einreichen
        await user.page.goto('/community/erstellen');
        await settle(user.page);
        await expect(
            user.page.getByRole('heading', { level: 1 }),
        ).toContainText('Rezept teilen');
        // ohne eigenen Schlüssel: Import deaktiviert mit Hinweis und Link
        await expect(user.page.locator('[data-import-disabled]')).toBeVisible();
        await expect(user.page.locator('#tiktok-url')).toBeDisabled();
        await expect(
            user.page.getByRole('link', {
                name: 'Schlüssel unter „KI-Import“ hinterlegen',
            }),
        ).toHaveAttribute('href', /\/settings\/ki-import$/);
        // keine Katalog-Bearbeitung
        await expect(user.page.locator('#tag-draft')).toHaveCount(0);
        await expect(user.page.locator('[data-tag-edit]')).toHaveCount(0);

        await user.page.locator('#name').fill(title);
        await user.page
            .locator('[data-ingredient-amount]')
            .first()
            .fill('200 g');
        await user.page
            .locator('[data-ingredient-name]')
            .first()
            .fill('Rote Linsen');
        await user.page.keyboard.press('Escape');
        await expect(
            user.page.locator('[data-proposed-ingredient]'),
        ).toHaveCount(0);
        await user.page
            .locator('[data-step-text]')
            .first()
            .fill('Linsen garen.');
        const ratingSection = user.page.locator(
            '[data-form-section="ratings"]',
        );
        const taste = ratingSection.getByRole('radiogroup', {
            name: 'Geschmack',
        });
        const stars = taste.getByRole('radio');
        await expect(stars).toHaveCount(5);
        await stars.nth(0).focus();
        await user.page.keyboard.press('Space');
        await user.page.keyboard.press('ArrowRight');
        await user.page.keyboard.press('ArrowRight');
        await user.page.keyboard.press('ArrowRight');
        await expect(stars.nth(3)).toBeChecked();
        await Promise.all([
            user.page.waitForURL(/\/community\/rezept\/\d+$/),
            user.page
                .getByRole('button', { name: 'Zur Prüfung einreichen' })
                .click(),
        ]);
        await settle(user.page);
        const id = Number(user.page.url().match(/(\d+)$/)?.[1]);
        await expect(user.page.locator('[data-recipe-status]')).toHaveAttribute(
            'data-status',
            'pending',
        );
        await expect(user.page.getByRole('status').first()).toContainText(
            'wird geprüft',
        );

        // 2) Gast sieht es noch nicht
        const hidden = await guest.goto(`/community/rezept/${id}`);
        expect(hidden?.status()).toBe(404);

        // 3) Host gibt frei (Badge „Zu prüfen“ im Nutzermenü)
        const host = await roleContext(browser, 'host');
        await host.page.goto('/home');
        await settle(host.page);
        await host.page.getByRole('button', { name: /Benutzermenü/ }).click();
        const entry = host.page.locator(
            '#user-menu [data-account-nav="moderation"]',
        );
        await expect(entry).toBeVisible();
        await expect(entry.locator('[data-moderation-count]')).toHaveText(
            /\d+/,
        );
        await entry.click();
        await host.page.waitForURL('**/moderation');
        await settle(host.page);
        const card = host.page.locator(`[data-pending-recipe="${id}"]`);
        await expect(card).toContainText(title);
        await expect(card).toContainText(`von ${DATA.users.user.name}`);
        await card.locator(`[data-approve="${id}"]`).click();
        await expect(host.page.getByRole('status').first()).toContainText(
            'freigegeben',
        );
        await expect(card).toHaveCount(0);

        // 4) öffentlich in der Liste und auf der Detailseite
        await guest.goto('/community');
        await settle(guest);
        const listItem = guest
            .locator('[data-community-list] li')
            .filter({ hasText: title });
        await expect(listItem).toBeVisible();
        await expect(listItem.locator('[data-recipe-byline]')).toHaveText(
            `von ${DATA.users.user.name}`,
        );
        await listItem.getByRole('link', { name: title }).click();
        await guest.waitForURL(`**/community/rezept/${id}`);
        await settle(guest);
        await expect(guest.locator('[data-community-badge]')).toBeVisible();
        await expect(guest.locator('[data-recipe-author]')).toHaveText(
            `von ${DATA.users.user.name}`,
        );
        await expect(guest.locator('[data-recipe-status]')).toHaveCount(0);

        // Host-Rezeptbuch bleibt ohne Community-Rezepte
        await guest.goto('/rezepte?search=E2E-Community');
        await settle(guest);
        await expect(guest.locator('[data-result-count]')).toHaveText(
            '0 Ergebnisse',
        );

        // 5) Bearbeiten eines freigegebenen Rezepts kündigt die erneute Prüfung an
        await user.page.goto(`/community/rezept/${id}/bearbeiten`);
        await settle(user.page);
        await expect(user.page.locator('[data-review-warning]')).toContainText(
            'erneut geprüft',
        );

        // 6) Bewertung des Autors wie bei Host-Rezepten; kein Widget für andere
        await guest.goto(`/community/rezept/${id}`);
        await settle(guest);
        await expect(guest.locator('[data-recipe-rating]')).toContainText(
            'Bewertung von ' + DATA.users.user.name,
        );
        await expect(guest.locator('[data-recipe-rating]')).toContainText('4');
        const user2 = await roleContext(browser, 'user2');
        await user2.page.goto(`/community/rezept/${id}`);
        await settle(user2.page);
        await expect(user2.page.getByRole('radio')).toHaveCount(0);
        // Host bearbeitet ein fremdes Rezept: kein Bewertungs-Abschnitt
        await host.page.goto(`/community/rezept/${id}/bearbeiten`);
        await settle(host.page);
        await expect(
            host.page.locator('[data-form-section="ratings"]'),
        ).toHaveCount(0);

        await Promise.all([
            user.context.close(),
            host.context.close(),
            user2.context.close(),
        ]);
    });

    test('Host lehnt mit Grund ab; Vorschlag bleibt außerhalb des Katalogs', async ({
        browser,
        page: guest,
    }) => {
        const title = `${PREFIX} Abgelehnt`;
        const proposal = 'E2E-Vorschlag Yuzu';
        const user = await roleContext(browser, 'user');
        const id = await submitViaRequest(user.page, title, [
            { name: 'Rote Linsen', amount: '1 Tasse' },
            { name: proposal, amount: '1 TL' },
        ]);

        const host = await roleContext(browser, 'host');
        await host.page.goto('/moderation');
        await settle(host.page);
        const card = host.page.locator(`[data-pending-recipe="${id}"]`);
        await expect(card.locator('[data-proposed]')).toContainText(proposal);
        await expect(card.locator('[data-proposed]')).toContainText(
            'Neuer Vorschlag',
        );
        await card.locator(`[data-reject="${id}"]`).click();
        const dialog = host.page.getByRole('dialog', {
            name: 'Rezept ablehnen',
        });
        await expect(dialog).toBeVisible();
        await dialog.locator('[data-reject-send]').click();
        await expect(dialog.locator('#reject-reason-error')).toBeVisible();
        await dialog
            .getByLabel('Begründung')
            .fill('Bitte Mengen und Garzeit ergänzen.');
        await dialog.locator('[data-reject-send]').click();
        await expect(dialog).toBeHidden();

        await user.page.goto('/meine-rezepte');
        await settle(user.page);
        const mine = user.page.locator(`[data-my-recipes] li`).filter({
            hasText: title,
        });
        await expect(mine).toHaveAttribute('data-status', 'rejected');
        await expect(mine.locator('[data-rejection-reason]')).toContainText(
            'Bitte Mengen und Garzeit ergänzen.',
        );

        // Vorschlag nicht in der Zutatensuche, nicht im Autocomplete
        await guest.goto('/zutatensuche');
        await settle(guest);
        await expect(guest.getByText(proposal)).toHaveCount(0);
        await user.page.goto('/community/erstellen');
        const props = await inertiaProps<{ ingredients: { name: string }[] }>(
            user.page,
        );
        expect(props.ingredients.map((i) => i.name)).not.toContain(proposal);

        await Promise.all([user.context.close(), host.context.close()]);
    });

    test('Gast meldet ein Rezept; Host sieht die Meldung', async ({
        browser,
        page: guest,
    }) => {
        const title = `${PREFIX} Gemeldet`;
        const user = await roleContext(browser, 'user');
        const id = await submitViaRequest(user.page, title, [
            { name: 'Haferflocken', amount: '60 g' },
        ]);
        const host = await roleContext(browser, 'host');
        const approve = await host.page.request.post(
            `/moderation/rezept/${id}/freigeben`,
            { headers: await xsrfHeaders(host.page), maxRedirects: 0 },
        );
        expect(approve.status()).toBe(302);

        await guest.goto(`/community/rezept/${id}`);
        await settle(guest);
        await guest.locator('[data-report-open]').click();
        const dialog = guest.getByRole('dialog', { name: 'Rezept melden' });
        await expect(dialog).toBeVisible();
        expect(await axeViolations(guest)).toEqual([]);
        await dialog.getByLabel('Spam oder Werbung').check();
        await dialog.locator('[data-report-send]').click();
        await expect(dialog.getByText(/nach bestem Wissen/)).toBeVisible();
        await expect(dialog.locator('[data-field-error]')).toContainText(
            'bestätige',
        );
        await dialog.getByRole('checkbox').check();
        await dialog.locator('[data-report-send]').click();
        await expect(dialog).toBeHidden();
        await expect(guest.getByRole('status').first()).toContainText(
            'Meldung ist eingegangen',
        );

        await host.page.goto('/moderation');
        await settle(host.page);
        const report = host.page
            .locator('[data-moderation-reports] li')
            .filter({ hasText: title });
        await expect(report).toContainText('Spam oder Werbung');
        await expect(report).toContainText('Gast');

        await Promise.all([user.context.close(), host.context.close()]);
    });

    test('KI-Import: eigener Schlüssel wird geprüft, maskiert und gelöscht', async ({
        browser,
    }) => {
        const { context, page } = await roleContext(browser, 'user2');
        await page.goto('/settings/ki-import');
        await settle(page);
        await expect(page.locator('[data-ai-key-status]')).toContainText(
            'Noch kein Schlüssel hinterlegt',
        );

        await page.getByLabel('Gemini-API-Schlüssel').fill('kein-schluessel');
        await page.getByRole('button', { name: 'Schlüssel speichern' }).click();
        await expect(page.locator('#gemini_api_key-error')).toContainText(
            'AIza',
        );

        await page.getByLabel('Gemini-API-Schlüssel').fill(FAKE_KEY);
        await page.getByRole('button', { name: 'Schlüssel speichern' }).click();
        await expect(page.locator('[data-key-hint]')).toHaveText('…2e2e');
        await expect(page.getByLabel('Gemini-API-Schlüssel')).toHaveValue('');
        expect(await page.content()).not.toContain(FAKE_KEY);
        expect(await axeViolations(page)).toEqual([]);

        // mit Schlüssel ist der Import im Formular aktiv
        await page.goto('/community/erstellen');
        await settle(page);
        await expect(page.locator('[data-import-disabled]')).toHaveCount(0);
        await expect(page.locator('#tiktok-url')).toBeEnabled();

        await page.goto('/settings/ki-import');
        await page.locator('[data-remove-key]').click();
        await expect(page.locator('[data-ai-key-status]')).toContainText(
            'Noch kein Schlüssel hinterlegt',
        );
        await context.close();
    });

    test('Barrierefreiheit (axe) und 320 px ohne Überlauf', async ({
        browser,
    }) => {
        const user = await roleContext(browser, 'user');
        const own = await submitViaRequest(user.page, `${PREFIX} Axe`, [
            { name: 'Chiasamen', amount: '1 EL' },
        ]);
        const host = await roleContext(browser, 'host');

        const pages: { page: Page; path: string }[] = [
            { page: user.page, path: '/community' },
            { page: user.page, path: `/community/rezept/${own}` },
            { page: user.page, path: '/community/erstellen' },
            { page: user.page, path: `/community/rezept/${own}/bearbeiten` },
            { page: user.page, path: '/meine-rezepte' },
            { page: user.page, path: '/settings/ki-import' },
            { page: host.page, path: '/moderation' },
        ];

        for (const { page, path } of pages) {
            await page.setViewportSize({ width: 1440, height: 900 });
            await page.goto(path);
            await settle(page);
            expect(await axeViolations(page), `axe ${path}`).toEqual([]);

            await page.setViewportSize({ width: 320, height: 640 });
            await page.goto(path);
            await settle(page);
            const layout = await measureLayout(page);
            expect(
                layout.horizontalOverflow,
                `Überlauf ${path}: ${layout.overflowingElements.join(', ')}`,
            ).toBe(false);
            expect(layout.h1Count, `h1 ${path}`).toBe(1);
        }

        await Promise.all([user.context.close(), host.context.close()]);
    });

    test('Kopfnavigation mit „Community“ ohne Überlauf (768–1440 px, alle Rollen)', async ({
        browser,
        page: guestPage,
    }) => {
        const roles = [
            { role: 'guest', page: guestPage, close: async () => {} },
            ...(await Promise.all(
                (['user', 'host'] as const).map(async (role) => {
                    const ctx = await roleContext(browser, role);
                    return {
                        role,
                        page: ctx.page,
                        close: () => ctx.context.close(),
                    };
                }),
            )),
        ];

        for (const { role, page } of roles) {
            for (const width of [768, 1024, 1440]) {
                const label = `${role} bei ${width} px`;
                await page.setViewportSize({ width, height: 900 });
                await page.goto('/community');
                await settle(page);
                const nav = page.getByRole('navigation', {
                    name: 'Hauptnavigation',
                });
                const link = nav.getByRole('link', { name: 'Community' });
                await expect(link, label).toBeVisible();
                await expect(link).toHaveAttribute('aria-current', 'page');
                const layout = await measureLayout(page);
                expect(
                    layout.horizontalOverflow,
                    `${label}: ${layout.overflowingElements.join(', ')}`,
                ).toBe(false);
                // Kopfzeile einzeilig: alles innerhalb ihrer Höhe und Breite
                const header = page.locator('header').first();
                const headerBox = await header.boundingBox();
                const inner = header.locator(':scope > div');
                const fits = await inner.evaluate(
                    (el) => el.scrollWidth <= el.clientWidth + 1,
                );
                expect(fits, `${label}: Kopfzeile zu breit`).toBe(true);
                const box = await link.boundingBox();
                expect(
                    !!box &&
                        !!headerBox &&
                        box.y + box.height <= headerBox.y + headerBox.height,
                    label,
                ).toBe(true);
            }
        }

        await Promise.all(roles.map((r) => r.close()));
    });
});

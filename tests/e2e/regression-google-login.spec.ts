import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';
import { BASE_URL } from './support/env';
import { expect, settle, test } from './support/fixtures';
import { inertiaProps } from './support/helpers';

/**
 * E17 / ADR-004: Knopf „Mit Google anmelden/registrieren“.
 *
 * Die E2E-Instanz setzt Dummy-Werte für GOOGLE_CLIENT_ID/SECRET
 * (tests/e2e/support/env.ts), damit der Knopf erscheint. Es gibt KEINEN
 * Aufruf bei Google: Der Knopf wird nicht angeklickt; die Weiterleitung wird
 * nur per Request ohne Redirect-Verfolgung geprüft, und die Fixture blockiert
 * ohnehin jeden fremden Host.
 *
 * „Ohne Konfiguration“: Der Server-Schalter ist in PHPUnit geprüft
 * (GoogleLoginTest, SharedInertiaPropsTest). Hier wird die Seite mit
 * `features.googleLogin = false` ausgeliefert (Antwort umgeschrieben), um das
 * Ausblenden im Frontend zu prüfen.
 */

const PAGES = [
    { path: '/login', label: 'Mit Google anmelden' },
    { path: '/register', label: 'Mit Google registrieren' },
] as const;

const W320 = {
    viewport: { width: 320, height: 640 },
    isMobile: true,
    hasTouch: true,
} as const;

const googleLink = (page: Page, label: string) =>
    page.getByRole('link', { name: label, exact: true });

/** Liefert die Seite aus, als wäre Google nicht konfiguriert. */
async function withoutGoogleConfig(page: Page, path: string): Promise<void> {
    await page.route(`${BASE_URL}${path}`, async (route) => {
        const response = await route.fetch();
        const body = (await response.text()).replace(
            /(&quot;|")googleLogin\1:true/g,
            '$1googleLogin$1:false',
        );
        await route.fulfill({ response, body });
    });
}

test.describe('E17 Google-Anmeldung', () => {
    for (const { path, label } of PAGES) {
        test(`${path}: Knopf sichtbar, normaler Link auf /auth/google/redirect`, async ({
            page,
        }) => {
            await page.goto(path);
            await settle(page);

            const props = await inertiaProps<{
                features: { googleLogin: boolean };
            }>(page);
            expect(props.features.googleLogin).toBe(true);

            const link = googleLink(page, label);
            await expect(link).toBeVisible();
            const href = await link.getAttribute('href');
            expect(new URL(href ?? '', BASE_URL).pathname).toBe(
                '/auth/google/redirect',
            );
            // kein Popup, kein neues Fenster
            await expect(link).not.toHaveAttribute('target', /.+/);
            // Logo ist dekorativ, der Name kommt aus dem Text
            await expect(link.locator('svg')).toHaveAttribute(
                'aria-hidden',
                'true',
            );
            await expect(page.getByText('oder', { exact: true })).toBeVisible();

            // Knopf steht vor dem Formular und ist per Tastatur erreichbar
            await link.focus();
            await expect(link).toBeFocused();
            const box = await link.boundingBox();
            expect(box?.height ?? 0).toBeGreaterThanOrEqual(44);
        });

        test(`${path}: ohne Konfiguration kein Knopf`, async ({ page }) => {
            await withoutGoogleConfig(page, path);
            await page.goto(path);
            await settle(page);

            const props = await inertiaProps<{
                features: { googleLogin: boolean };
            }>(page);
            expect(props.features.googleLogin).toBe(false);
            await expect(googleLink(page, label)).toHaveCount(0);
            await expect(page.getByText('oder', { exact: true })).toHaveCount(
                0,
            );
        });

        test(`${path}: axe 0 Verstoesse mit Knopf`, async ({ page }) => {
            await page.goto(path);
            await settle(page);
            const results = await new AxeBuilder({ page }).analyze();
            expect(results.violations.map((v) => v.id)).toEqual([]);
        });

        test.describe('320 px', () => {
            test.use(W320);

            test(`${path}: kein horizontaler Ueberlauf`, async ({ page }) => {
                await page.goto(path);
                await settle(page);
                const link = googleLink(page, label);
                await expect(link).toBeVisible();

                const metrics = await page.evaluate(() => {
                    const a = document.querySelector('[data-google-login]');
                    const r = a?.getBoundingClientRect();
                    return {
                        docOverflow:
                            document.documentElement.scrollWidth >
                            window.innerWidth,
                        left: r?.left ?? -1,
                        right: r?.right ?? Infinity,
                        width: window.innerWidth,
                    };
                });
                expect(metrics.docOverflow).toBe(false);
                expect(metrics.left).toBeGreaterThanOrEqual(0);
                expect(metrics.right).toBeLessThanOrEqual(metrics.width);

                const results = await new AxeBuilder({ page }).analyze();
                expect(results.violations.map((v) => v.id)).toEqual([]);
            });
        });
    }

    test('Login: "Angemeldet bleiben" wird an die Weiterleitung uebergeben', async ({
        page,
    }) => {
        await page.goto('/login');
        await settle(page);
        const link = googleLink(page, 'Mit Google anmelden');
        expect(
            new URL((await link.getAttribute('href')) ?? '', BASE_URL).search,
        ).toBe('');

        await page.getByLabel('Angemeldet bleiben').check();
        await expect
            .poll(async () =>
                new URL(
                    (await link.getAttribute('href')) ?? '',
                    BASE_URL,
                ).searchParams.get('remember'),
            )
            .toBe('1');
    });

    test('Weiterleitung zeigt auf Google mit minimalen Scopes (ohne sie zu verfolgen)', async ({
        request,
    }) => {
        const response = await request.get('/auth/google/redirect', {
            maxRedirects: 0,
        });
        expect(response.status()).toBe(302);
        const location = new URL(response.headers()['location'] ?? '');
        expect(location.origin).toBe('https://accounts.google.com');
        expect(location.searchParams.get('scope')?.split(' ')).toEqual([
            'openid',
            'email',
            'profile',
        ]);
        expect(location.searchParams.get('redirect_uri')).toBe(
            `${BASE_URL}/auth/google/callback`,
        );
    });

    test('Abbruch bei Google zeigt die Fehlermeldung auf der Login-Seite', async ({
        page,
    }) => {
        await page.goto('/auth/google/callback?error=access_denied');
        await settle(page);
        expect(new URL(page.url()).pathname).toBe('/login');
        await expect(page.getByRole('alert')).toHaveText(
            'Die Anmeldung mit Google wurde abgebrochen.',
        );
    });
});

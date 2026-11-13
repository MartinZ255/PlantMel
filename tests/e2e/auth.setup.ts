import fs from 'node:fs';
import { AUTH_DIR, authFile, DATA, expect, settle, test } from './support/fixtures';

/**
 * Erzeugt je Rolle einen storageState ueber das echte Login-Formular.
 * Ergebnis: playwright/.auth/{user,host,user2}.json (gitignored).
 */
const roles = [
    ['user', DATA.users.user],
    ['host', DATA.users.host],
    ['user2', DATA.users.user2],
] as const;

test.beforeAll(() => {
    fs.mkdirSync(AUTH_DIR, { recursive: true });
});

for (const [role, account] of roles) {
    test(`Login als ${role}`, async ({ page }) => {
        await page.goto('/login');
        await settle(page);
        await page.locator('#email').fill(account.email);
        await page.locator('#password').fill(DATA.password);
        await Promise.all([
            page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 20_000 }),
            page.getByRole('button', { name: 'Einloggen' }).click(),
        ]);

        // Beleg, dass der Server die E2E-Datenbank benutzt: das Seeder-Konto ist angemeldet.
        await page.goto('/home');
        await expect(page.locator('.user-menu__dropdown')).toContainText(`Eingeloggt als ${account.name}`);
        await page.context().storageState({ path: authFile(role) });
    });
}

test('Server nutzt die E2E-Datenbank (Seeder-Rezepte sichtbar)', async ({ page }) => {
    await page.goto('/rezepte');
    await settle(page);
    await expect(page.locator('body')).toContainText(DATA.recipes.withImage.title);
    await expect(page.locator('body')).toContainText(DATA.recipes.withoutImage.title);
});

import type { Browser, Locator } from '@playwright/test';
import { BASE_URL } from './env';
import { DATA } from './fixtures';

export type Hit = { hitsTarget: boolean; hit: string; x: number; y: number };

/** Prueft per elementFromPoint, welches Element am Mittelpunkt des Locators einen Klick erhaelt. */
export async function hitTest(locator: Locator): Promise<Hit> {
    return locator.evaluate((el) => {
        const r = el.getBoundingClientRect();
        const x = r.left + r.width / 2;
        const y = r.top + r.height / 2;
        const hitEl = document.elementFromPoint(x, y);
        const describe = (e: Element | null) =>
            e
                ? `${e.tagName.toLowerCase()}${e.className && typeof e.className === 'string' ? '.' + e.className.trim().split(/\s+/).join('.') : ''}`
                : 'null';
        return {
            hitsTarget: !!hitEl && (hitEl === el || el.contains(hitEl)),
            hit: describe(hitEl),
            x: Math.round(x),
            y: Math.round(y),
        };
    });
}

/** Eigene Anmeldung per Formular in einem neuen Kontext (eigene Session). */
export async function freshLogin(
    browser: Browser,
    email: string,
    viewport = { width: 1440, height: 900 },
) {
    const context = await browser.newContext({ baseURL: BASE_URL, viewport });
    await context.route(
        (url) => url.origin !== BASE_URL,
        (route) => route.abort('blockedbyclient'),
    );
    const page = await context.newPage();
    await page.goto('/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(DATA.password);
    await Promise.all([
        page.waitForURL((url) => !url.pathname.startsWith('/login')),
        page.getByRole('button', { name: 'Einloggen' }).click(),
    ]);
    return { context, page, landedOn: new URL(page.url()).pathname };
}

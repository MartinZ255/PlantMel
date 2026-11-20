import type { Browser, Locator, Page } from '@playwright/test';
import { BASE_URL } from './env';
import { DATA } from './fixtures';

/**
 * Header fuer schreibende Requests ueber page.request (Laravel prueft das
 * verschluesselte XSRF-TOKEN-Cookie im Header X-XSRF-TOKEN). Ab Slice 2.
 */
export async function xsrfHeaders(page: Page): Promise<Record<string, string>> {
    const cookies = await page.context().cookies(BASE_URL);
    let token = cookies.find((c) => c.name === 'XSRF-TOKEN')?.value;
    if (!token) {
        await page.goto('/');
        token = (await page.context().cookies(BASE_URL)).find(
            (c) => c.name === 'XSRF-TOKEN',
        )?.value;
    }
    if (!token) throw new Error('XSRF-TOKEN-Cookie fehlt');
    return {
        'X-XSRF-TOKEN': decodeURIComponent(token),
        Accept: 'application/json',
    };
}

/** Liest die Props der aktuellen Inertia-Seite (data-page). */
export async function inertiaProps<T = Record<string, unknown>>(
    page: Page,
): Promise<T> {
    return page.evaluate(() => {
        const el = document.querySelector(
            '#app[data-page]',
        ) as HTMLElement | null;
        const script = document.querySelector(
            'script[data-page]',
        ) as HTMLScriptElement | null;
        const raw = el?.dataset.page ?? script?.textContent ?? '{}';
        return (JSON.parse(raw) as { props: unknown }).props;
    }) as Promise<T>;
}

/** Zutat-IDs nach Name (aus den Props von /rezepte/erstellen). */
export async function ingredientIds(
    page: Page,
    names: string[],
): Promise<Record<string, number>> {
    await page.goto('/rezepte/erstellen');
    const props = await inertiaProps<{
        ingredients: { id: number; name: string }[];
    }>(page);
    const result: Record<string, number> = {};
    for (const name of names) {
        const found = props.ingredients.find((i) => i.name === name);
        if (!found) throw new Error(`Zutat "${name}" nicht im Seeder`);
        result[name] = found.id;
    }
    return result;
}

export type RecipeRowSeed = {
    name: string;
    amount?: string;
    alternativeIds?: number[];
};

/**
 * Legt ein Wegwerf-Rezept direkt ueber POST /recipes an (nur bestehende Zutaten
 * verwenden, damit /zutatensuche unveraendert bleibt). Liefert die Rezept-ID.
 */
export async function createRecipe(
    page: Page,
    data: { title: string; ingredients: RecipeRowSeed[]; steps: string[] },
): Promise<number> {
    const form: Record<string, string> = { name: data.title };
    data.ingredients.forEach((row, i) => {
        form[`ingredients[${i}][name]`] = row.name;
        form[`ingredients[${i}][amount]`] = row.amount ?? '';
        (row.alternativeIds ?? []).forEach((id, j) => {
            form[`ingredients[${i}][alternativeIngredientIds][${j}]`] =
                String(id);
        });
    });
    data.steps.forEach((step, i) => {
        form[`steps[${i}]`] = step;
    });
    const res = await page.request.post('/recipes', {
        form,
        headers: await xsrfHeaders(page),
        maxRedirects: 0,
    });
    const location = res.headers()['location'] ?? '';
    const match = location.match(/\/rezept\/(\d+)$/);
    if (res.status() !== 302 || !match) {
        throw new Error(
            `Rezept anlegen fehlgeschlagen: ${res.status()} ${location}`,
        );
    }
    return Number(match[1]);
}

/** Entfernt ein Wegwerf-Rezept wieder (Host-Session). */
export async function deleteRecipe(page: Page, id: number): Promise<void> {
    const res = await page.request.delete(`/rezept/${id}`, {
        headers: await xsrfHeaders(page),
        maxRedirects: 0,
    });
    if (res.status() !== 302)
        throw new Error(`Rezept ${id} loeschen: ${res.status()}`);
}

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

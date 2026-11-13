import { test as base, expect, type Page } from '@playwright/test';
import path from 'node:path';
import { BASE_URL, ROOT } from './env';

/** Spiegelt database/seeders/E2eSeeder.php (frische DB => stabile IDs). */
export const DATA = {
    password: 'E2e-Passwort-2026!',
    users: {
        user: { email: 'user@example.test', name: 'E2E Nutzerin' },
        host: { email: 'host@example.test', name: 'E2E Host' },
        user2: { email: 'user2@example.test', name: 'E2E Zweitnutzer' },
    },
    recipes: {
        withImage: { id: 1, title: 'Cremige Linsen-Bolognese' },
        longTitle: { id: 2, title: 'Kürbis-Kichererbsen-Erdnusscurrygewürzmischung mit Röstzwiebel-Topping' },
        withoutImage: { id: 3, title: 'Overnight Oats mit Beeren' },
    },
    collections: {
        filled: { id: 1, name: 'Wochenplan' },
        empty: { id: 2, name: 'Leere Sammlung' },
    },
    throwawayCategory: 'E2E-Wegwerf-Tag',
} as const;

export const AUTH_DIR = path.join(ROOT, 'playwright', '.auth');
export const authFile = (role: 'user' | 'host' | 'user2') => path.join(AUTH_DIR, `${role}.json`);

/** Feste Zeit fuer den Browser (Date.now/new Date); Timer laufen normal weiter. */
export const FIXED_TIME = new Date('2026-01-15T10:00:00Z');

export type Diagnostics = {
    consoleErrors: string[];
    pageErrors: string[];
    failedRequests: string[];
    httpErrors: string[];
    blockedExternal: string[];
};

type Fixtures = { diag: Diagnostics };

/**
 * Basis-Test:
 * - blockiert jeden Request an fremde Hosts (u. a. fonts.bunny.net) => reproduzierbar, kein Drittzugriff
 * - feste Browserzeit, deterministisches Math.random
 * - sammelt console.error, pageerror, fehlgeschlagene Requests und HTTP-Fehler
 */
export const test = base.extend<Fixtures>({
    diag: [
        async ({ page }, use) => {
            const diag: Diagnostics = {
                consoleErrors: [],
                pageErrors: [],
                failedRequests: [],
                httpErrors: [],
                blockedExternal: [],
            };
            const origin = new URL(BASE_URL).origin;

            await page.route(
                (url) => url.origin !== origin && (url.protocol === 'http:' || url.protocol === 'https:'),
                async (route) => {
                    diag.blockedExternal.push(route.request().url());
                    await route.abort('blockedbyclient');
                },
            );
            await page.clock.setFixedTime(FIXED_TIME);
            await page.addInitScript(() => {
                let seed = 42;
                Math.random = () => {
                    seed = (seed * 16807) % 2147483647;
                    return (seed - 1) / 2147483646;
                };
            });

            page.on('console', (msg) => {
                if (msg.type() !== 'error') return;
                // Folgemeldung der bewusst blockierten Fremd-Requests (fonts.bunny.net) - separat erfasst
                if (msg.text().includes('ERR_BLOCKED_BY_CLIENT')) return;
                diag.consoleErrors.push(msg.text().slice(0, 500));
            });
            page.on('pageerror', (err) => diag.pageErrors.push(String(err.message ?? err).slice(0, 500)));
            page.on('requestfailed', (req) => {
                const reason = req.failure()?.errorText ?? 'unknown';
                if (reason.includes('ERR_BLOCKED_BY_CLIENT')) return; // bewusst blockiert, separat erfasst
                diag.failedRequests.push(`${req.method()} ${req.url()} (${reason})`);
            });
            page.on('response', (res) => {
                if (res.status() >= 400) diag.httpErrors.push(`${res.status()} ${res.request().method()} ${res.url()}`);
            });

            await use(diag);
        },
        { auto: true },
    ],
});

export { expect };

/** Wartet, bis Inertia gerendert hat, Netzwerk ruhig ist und Schriften/Bilder geladen sind. */
export async function settle(page: Page): Promise<void> {
    await page.waitForLoadState('load');
    await page.waitForLoadState('networkidle').catch(() => undefined);
    await page.evaluate(async () => {
        await document.fonts.ready;
        await Promise.all(
            Array.from(document.images)
                .filter((img) => !img.complete)
                .map(
                    (img) =>
                        new Promise((resolve) => {
                            img.addEventListener('load', resolve, { once: true });
                            img.addEventListener('error', resolve, { once: true });
                        }),
                ),
        );
    });
}

export type LayoutMetrics = {
    title: string;
    lang: string | null;
    h1Count: number;
    h1Texts: string[];
    innerWidth: number;
    docScrollWidth: number;
    bodyScrollWidth: number;
    horizontalOverflow: boolean;
    overflowingElementCount: number;
    overflowingElements: string[];
    clippedH1: boolean;
};

/** Misst horizontalen Overflow und Strukturmerkmale im aktuellen Viewport. */
export async function measureLayout(page: Page): Promise<LayoutMetrics> {
    return page.evaluate(() => {
        const vw = window.innerWidth;
        const describe = (el: Element): string => {
            const parts: string[] = [];
            let cur: Element | null = el;
            for (let i = 0; cur && i < 3; i++) {
                let s = cur.tagName.toLowerCase();
                if (cur.id) s += `#${cur.id}`;
                const cls = Array.from(cur.classList).slice(0, 2);
                if (cls.length) s += '.' + cls.join('.');
                parts.unshift(s);
                cur = cur.parentElement;
            }
            return parts.join(' > ');
        };
        const over: string[] = [];
        let count = 0;
        for (const el of Array.from(document.body.querySelectorAll('*'))) {
            const style = getComputedStyle(el);
            if (style.display === 'none' || style.visibility === 'hidden') continue;
            const r = el.getBoundingClientRect();
            if (r.width === 0 || r.height === 0) continue;
            if (r.left >= vw) continue; // komplett ausserhalb (z. B. Off-Canvas) zaehlt nicht als Ueberstand
            if (r.right > vw + 1) {
                // nur aeusserstes Element einer Kette melden
                const parent = el.parentElement;
                const pr = parent?.getBoundingClientRect();
                count++;
                if (!pr || pr.right <= vw + 1) {
                    if (over.length < 10) over.push(`${describe(el)} (right=${Math.round(r.right)})`);
                }
            }
        }
        const h1s = Array.from(document.querySelectorAll('h1'));
        const firstH1 = h1s[0] as HTMLElement | undefined;
        return {
            title: document.title,
            lang: document.documentElement.getAttribute('lang'),
            h1Count: h1s.length,
            h1Texts: h1s.map((h) => (h.textContent ?? '').trim().slice(0, 120)),
            innerWidth: vw,
            docScrollWidth: document.documentElement.scrollWidth,
            bodyScrollWidth: document.body.scrollWidth,
            horizontalOverflow: document.documentElement.scrollWidth > vw,
            overflowingElementCount: count,
            overflowingElements: over,
            clippedH1: firstH1 ? firstH1.scrollWidth > firstH1.clientWidth + 1 || firstH1.getBoundingClientRect().right > vw + 1 : false,
        };
    });
}

import AxeBuilder from '@axe-core/playwright';
import type { Browser, Locator, Page } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { BASE_URL, ROOT } from './support/env';
import { authFile, DATA, expect, settle, test } from './support/fixtures';
import { writeRaw } from './support/raw';

/**
 * Gate C2 (Schritt 2) – App-Shell, Anmelde-Seiten, Einstellungen, Rechtsseiten, Fehlerseite.
 *
 * - `auth.user.is_host` ist global geteilt: „Rezept erstellen“ erscheint in Kopfzeile,
 *   Mobilmenue und Nutzermenue auf JEDER Seite fuer Hosts und nie fuer Nutzer/Gaeste
 * - axe: 0 Verstoesse (alle Regeln) auf allen Seiten dieses Pakets bei 390 und 1440 px
 * - 320 px: kein horizontales Scrollen
 * - keine Klassen aus resources/css/rezeptly/app.css auf diesen Seiten
 * - die Kopfzeile bleibt beim Scrollen oben haften
 * - Konto loeschen: Bestaetigung im Dialog statt window.confirm
 *
 * Datenveraenderungen: keine (Konto loeschen nur mit falschem Passwort, user2).
 */

const MOBILE_390 = {
    viewport: { width: 390, height: 844 },
    isMobile: true,
    hasTouch: true,
} as const;

type Role = 'guest' | 'user' | 'host' | 'user2';
type ShellPage = { slug: string; path: string; role: Role; status?: number };

const resetPath = `/reset-password/e2e-dummy-token?email=${encodeURIComponent(DATA.users.user.email)}`;

/** Alle Seiten dieses Pakets (VerifyEmail separat, siehe serveVerifyEmail). */
const PAGES: ShellPage[] = [
    { slug: 'login', path: '/login', role: 'guest' },
    { slug: 'register', path: '/register', role: 'guest' },
    { slug: 'forgot-password', path: '/forgot-password', role: 'guest' },
    { slug: 'reset-password', path: resetPath, role: 'guest' },
    { slug: 'confirm-password', path: '/user/confirm-password', role: 'user' },
    { slug: 'settings', path: '/settings', role: 'user' },
    { slug: 'settings-profile', path: '/settings/profile', role: 'user' },
    { slug: 'settings-password', path: '/settings/password', role: 'user' },
    { slug: 'impressum', path: '/impressum', role: 'guest' },
    { slug: 'datenschutz', path: '/datenschutz', role: 'guest' },
    { slug: 'error-404', path: '/gibt-es-nicht', role: 'guest', status: 404 },
    {
        slug: 'error-403',
        path: '/rezepte/erstellen',
        role: 'user',
        status: 403,
    },
];

async function contextFor(
    browser: Browser,
    role: Role,
    viewport: { width: number; height: number },
    isMobile = false,
) {
    const context = await browser.newContext({
        baseURL: BASE_URL,
        viewport,
        isMobile,
        hasTouch: isMobile,
        reducedMotion: 'reduce',
        locale: 'de-DE',
        storageState:
            role === 'guest' ? { cookies: [], origins: [] } : authFile(role),
    });
    await context.route(
        (url) => url.origin !== BASE_URL,
        (route) => route.abort('blockedbyclient'),
    );
    return context;
}

const decode = (s: string) =>
    s
        .replace(/&quot;/g, '"')
        .replace(/&#0?39;/g, "'")
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&amp;/g, '&');
const encode = (s: string) =>
    s
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

/**
 * Zeigt auth/VerifyEmail, ohne ein unbestaetigtes Konto anzulegen: die Antwort von
 * /login wird abgefangen und das Inertia-Seitenobjekt auf die Komponente umgestellt.
 */
async function serveVerifyEmail(page: Page) {
    await page.route(
        (url) => url.pathname === '/login',
        async (route) => {
            if (route.request().resourceType() !== 'document')
                return route.continue();
            const response = await route.fetch();
            let html = await response.text();
            const patch = (data: {
                component: string;
                props: Record<string, unknown>;
            }) => {
                data.component = 'auth/VerifyEmail';
                data.props.status = 'verification-link-sent';
                return data;
            };
            const attr = html.match(/data-page="([^"]*)"/);
            if (attr) {
                const data = patch(JSON.parse(decode(attr[1])));
                html = html.replace(
                    attr[0],
                    `data-page="${encode(JSON.stringify(data))}"`,
                );
            } else {
                const script = html.match(
                    /(<script[^>]*data-page[^>]*>)([\s\S]*?)(<\/script>)/,
                );
                if (!script)
                    throw new Error('Inertia-Seitenobjekt nicht gefunden');
                const data = patch(JSON.parse(script[2]));
                html = html.replace(
                    script[0],
                    `${script[1]}${JSON.stringify(data).replace(/</g, '\\u003c')}${script[3]}`,
                );
            }
            await route.fulfill({ response, body: html });
        },
    );
}

/** Klassennamen aus der Legacy-Datei (alle `.name`-Selektoren). */
function legacyClassNames(): Set<string> {
    const css = fs.readFileSync(
        path.join(ROOT, 'resources', 'css', 'rezeptly', 'app.css'),
        'utf8',
    );
    const withoutComments = css.replace(/\/\*[\s\S]*?\*\//g, '');
    const names = new Set<string>();
    // nur Selektor-Teile (vor "{"), keine Werte wie "0.15s"
    for (const block of withoutComments.split('}')) {
        const selector = block.split('{')[0] ?? '';
        for (const m of selector.matchAll(/\.(-?[A-Za-z_][\w-]*)/g))
            names.add(m[1]);
    }
    return names;
}

const banner = (page: Page) => page.getByRole('banner');
const createLink = (scope: Locator) =>
    scope.getByRole('link', { name: 'Rezept erstellen' });

// ---------------------------------------------------------------------------------------------
test.describe('Gate C2 – is_host global', () => {
    const shellPaths = [
        '/home',
        '/sammlungen',
        '/settings',
        '/settings/profile',
        '/impressum',
        '/meineZutaten',
        '/zutatensuche',
        `/sammlung/${DATA.collections.filled.id}`,
    ];

    test('Host sieht „Rezept erstellen“ in der Kopfzeile und im Nutzermenue auf jeder Seite (1440)', async ({
        browser,
    }) => {
        const context = await contextFor(browser, 'host', {
            width: 1440,
            height: 900,
        });
        const page = await context.newPage();
        const rows: unknown[] = [];
        for (const p of shellPaths) {
            await page.goto(p);
            await settle(page);
            const link = createLink(banner(page)).first();
            await expect(link, p).toBeVisible();
            expect(
                new URL((await link.getAttribute('href'))!).pathname,
                p,
            ).toBe('/rezepte/erstellen');
            // im (geoeffneten) Nutzermenue ebenfalls
            await banner(page)
                .getByRole('button', { name: /Benutzermenü/ })
                .click();
            await expect(
                createLink(page.locator('#user-menu')),
                `${p}: Nutzermenue`,
            ).toBeVisible();
            await page.keyboard.press('Escape');
            rows.push({ path: p, ok: true });
        }
        writeRaw('regression', 'gate-c2-is-host-1440', { rows });
        await context.close();
    });

    test('Host sieht „Rezept erstellen“ im Mobilmenue auf jeder Seite (390)', async ({
        browser,
    }) => {
        const context = await contextFor(
            browser,
            'host',
            MOBILE_390.viewport,
            true,
        );
        const page = await context.newPage();
        for (const p of shellPaths) {
            await page.goto(p);
            await settle(page);
            await page
                .getByRole('button', { name: 'Menü', exact: true })
                .click();
            const menu = page.getByRole('dialog', { name: 'Menü' });
            await expect(createLink(menu), p).toBeVisible();
            await page.keyboard.press('Escape');
            await expect(menu).toHaveCount(0);
        }
        await context.close();
    });

    for (const role of ['user', 'guest'] as const) {
        test(`${role}: „Rezept erstellen“ erscheint nirgends in Kopfzeile, Mobil- oder Nutzermenue`, async ({
            browser,
        }) => {
            const paths =
                role === 'user'
                    ? [
                          ...shellPaths,
                          '/rezepte',
                          `/rezept/${DATA.recipes.withImage.id}`,
                      ]
                    : [
                          '/',
                          '/rezepte',
                          '/zutatensuche',
                          '/impressum',
                          '/datenschutz',
                      ];
            for (const [vp, mobile] of [
                [{ width: 1440, height: 900 }, false],
                [MOBILE_390.viewport, true],
            ] as const) {
                const context = await contextFor(browser, role, vp, mobile);
                const page = await context.newPage();
                for (const p of paths) {
                    await page.goto(p);
                    await settle(page);
                    await expect(
                        createLink(banner(page)),
                        `${vp.width} ${p}: Kopfzeile`,
                    ).toHaveCount(0);
                    await expect(
                        page.locator(
                            '#user-menu a[href$="/rezepte/erstellen"]',
                        ),
                        `${vp.width} ${p}: Nutzermenue`,
                    ).toHaveCount(0);
                    if (mobile) {
                        await page
                            .getByRole('button', { name: 'Menü', exact: true })
                            .click();
                        const menu = page.getByRole('dialog', { name: 'Menü' });
                        await expect(menu).toBeVisible();
                        await expect(
                            createLink(menu),
                            `${p}: Mobilmenue`,
                        ).toHaveCount(0);
                        await page.keyboard.press('Escape');
                    }
                }
                await context.close();
            }
        });
    }
});

// ---------------------------------------------------------------------------------------------
for (const vp of [
    { label: '1440', viewport: { width: 1440, height: 900 }, mobile: false },
    { label: '390', viewport: MOBILE_390.viewport, mobile: true },
] as const) {
    test.describe(`Gate C2 – axe alle Regeln (${vp.label})`, () => {
        for (const p of PAGES) {
            test(`axe ${p.slug} ${vp.label}: 0 Verstoesse`, async ({
                browser,
            }) => {
                const context = await contextFor(
                    browser,
                    p.role,
                    vp.viewport,
                    vp.mobile,
                );
                const page = await context.newPage();
                const res = await page.goto(p.path);
                await settle(page);
                expect(res?.status(), p.path).toBe(p.status ?? 200);
                const results = await new AxeBuilder({ page }).analyze();
                const violations = results.violations.map((v) => ({
                    id: v.id,
                    nodes: v.nodes.map((n) => n.target.join(' ')).slice(0, 5),
                }));
                writeRaw('regression', `gate-c2-axe-${p.slug}-${vp.label}`, {
                    violations,
                });
                expect(violations, `${p.slug} ${vp.label}`).toEqual([]);
                await context.close();
            });
        }

        test(`axe verify-email ${vp.label}: 0 Verstoesse`, async ({
            browser,
        }) => {
            const context = await contextFor(
                browser,
                'guest',
                vp.viewport,
                vp.mobile,
            );
            const page = await context.newPage();
            await serveVerifyEmail(page);
            await page.goto('/login');
            await settle(page);
            await expect(page.locator('h1')).toHaveText(
                'E-Mail-Adresse bestätigen',
            );
            await expect(page.getByRole('main').getByRole('status')).toHaveText(
                'Ein neuer Bestätigungslink wurde an deine E-Mail-Adresse gesendet.',
            );
            await expect(
                page.getByRole('button', { name: 'Link erneut senden' }),
            ).toBeVisible();
            const results = await new AxeBuilder({ page }).analyze();
            expect(results.violations.map((v) => v.id)).toEqual([]);
            await context.close();
        });

        test(`axe Konto-loeschen-Dialog ${vp.label}: 0 Verstoesse`, async ({
            browser,
        }) => {
            const context = await contextFor(
                browser,
                'user',
                vp.viewport,
                vp.mobile,
            );
            const page = await context.newPage();
            await page.goto('/settings/profile');
            await settle(page);
            await page.getByRole('button', { name: 'Konto löschen' }).click();
            await expect(
                page.getByRole('dialog', { name: 'Konto endgültig löschen?' }),
            ).toBeVisible();
            const results = await new AxeBuilder({ page }).analyze();
            expect(results.violations.map((v) => v.id)).toEqual([]);
            await context.close();
        });
    });
}

// ---------------------------------------------------------------------------------------------
test.describe('Gate C2 – Layout', () => {
    test('320 px: kein horizontales Scrollen auf allen Seiten des Pakets', async ({
        browser,
    }) => {
        const rows: { path: string; scrollWidth: number }[] = [];
        for (const role of ['guest', 'user'] as const) {
            const context = await contextFor(
                browser,
                role,
                { width: 320, height: 640 },
                true,
            );
            const page = await context.newPage();
            for (const p of PAGES.filter((x) => x.role === role)) {
                await page.goto(p.path);
                await settle(page);
                const m = await page.evaluate(() => ({
                    scrollWidth: document.documentElement.scrollWidth,
                    bodyScrollWidth: document.body.scrollWidth,
                    inner: window.innerWidth,
                }));
                rows.push({ path: p.path, scrollWidth: m.scrollWidth });
                expect(m.scrollWidth, `${p.path}: html`).toBeLessThanOrEqual(
                    m.inner,
                );
                expect(
                    m.bodyScrollWidth,
                    `${p.path}: body`,
                ).toBeLessThanOrEqual(m.inner);
                // kein Element ragt sichtbar ueber den rechten Rand hinaus
                // (Ornamente liegen in Abschnitten mit overflow-hidden)
                const overflowing = await page.evaluate(() => {
                    const out: string[] = [];
                    const isClipped = (el: Element) => {
                        for (
                            let a = el.parentElement;
                            a && a !== document.body;
                            a = a.parentElement
                        ) {
                            const o = getComputedStyle(a);
                            if (
                                o.overflowX !== 'visible' &&
                                a.getBoundingClientRect().right <=
                                    window.innerWidth + 1
                            )
                                return true;
                        }
                        return false;
                    };
                    for (const el of Array.from(
                        document.querySelectorAll('#app *'),
                    )) {
                        const r = el.getBoundingClientRect();
                        if (r.width === 0 || r.height === 0) continue;
                        if (r.right > window.innerWidth + 1 && !isClipped(el))
                            out.push(
                                `${el.tagName.toLowerCase()}.${String(el.getAttribute('class') ?? '').slice(0, 60)}`,
                            );
                    }
                    return out.slice(0, 5);
                });
                expect(overflowing, `${p.path}: Ueberstand`).toEqual([]);
            }
            await context.close();
        }
        writeRaw('regression', 'gate-c2-320', { rows });
    });

    test('keine Klassen aus rezeptly/app.css auf den Seiten des Pakets', async ({
        browser,
    }) => {
        const legacy = legacyClassNames();
        expect(legacy.size, 'Legacy-Klassen gelesen').toBeGreaterThan(100);
        const found: Record<string, string[]> = {};
        for (const role of ['guest', 'user'] as const) {
            const context = await contextFor(browser, role, {
                width: 1440,
                height: 900,
            });
            const page = await context.newPage();
            for (const p of PAGES.filter((x) => x.role === role)) {
                await page.goto(p.path);
                await settle(page);
                const classes = await page.evaluate(() => {
                    const set = new Set<string>();
                    for (const el of Array.from(
                        document.querySelectorAll('[class]'),
                    ))
                        for (const c of Array.from(el.classList)) set.add(c);
                    return Array.from(set);
                });
                const hit = classes.filter((c) => legacy.has(c));
                if (hit.length) found[p.path] = hit;
            }
            await context.close();
        }
        writeRaw('regression', 'gate-c2-legacy-classes', { found });
        expect(found).toEqual({});
    });

    test('Kopfzeile haftet beim Scrollen oben (unabhaengig vom Scroll-Container)', async ({
        page,
    }) => {
        // Hinweis: <body> ist wegen rezeptly/app.css (html, body { height: 100%;
        // overflow-x: hidden }) derzeit der Scroll-Container. Das wird erst beim
        // Entfernen der Legacy-Datei behoben; dieser Test gilt in beiden Faellen.
        await page.goto('/datenschutz');
        await settle(page);
        await page.mouse.move(700, 500);
        await page.mouse.wheel(0, 1600);
        const scrolled = () =>
            page.evaluate(() =>
                Math.max(
                    window.scrollY,
                    document.body.scrollTop,
                    document.documentElement.scrollTop,
                ),
            );
        await expect.poll(scrolled).toBeGreaterThan(500);
        const box = (await banner(page).boundingBox())!;
        expect(Math.abs(box.y), 'Kopfzeile oben').toBeLessThanOrEqual(1);
        await expect(
            banner(page).getByRole('link', { name: 'PlantMel – Startseite' }),
        ).toBeInViewport();
    });

    test('Auth-Seiten: Landmarken, genau ein h1, Wortmarke zur Startseite, Formular mit Namen', async ({
        page,
    }) => {
        for (const [p, form] of [
            ['/login', 'Anmelden'],
            ['/register', 'Registrieren'],
            ['/forgot-password', 'Passwort zurücksetzen'],
            [resetPath, 'Neues Passwort setzen'],
        ] as const) {
            await page.goto(p);
            await settle(page);
            await expect(page.locator('main')).toHaveCount(1);
            await expect(page.locator('h1')).toHaveCount(1);
            await expect(page.getByRole('banner')).toHaveCount(1);
            await expect(page.getByRole('contentinfo')).toHaveCount(1);
            await expect(
                page.getByRole('main').getByRole('form', { name: form }),
            ).toBeVisible();
            const brand = banner(page).getByRole('link', {
                name: 'PlantMel – Startseite',
            });
            expect(new URL((await brand.getAttribute('href'))!).pathname).toBe(
                '/',
            );
            // Zitat ist echter Text (kein Bild)
            await expect(banner(page).locator('blockquote')).toHaveCount(1);
        }
        // Login: alle Wege erhalten
        await page.goto('/login');
        await settle(page);
        await expect(page.getByLabel('Angemeldet bleiben')).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Passwort vergessen?' }),
        ).toHaveAttribute('href', /\/forgot-password$/);
        await expect(
            page.getByRole('link', { name: 'Registrieren', exact: true }),
        ).toHaveAttribute('href', /\/register$/);
        await page.goto('/register');
        await settle(page);
        await expect(
            page.getByRole('link', { name: 'Zum Login' }),
        ).toHaveAttribute('href', /\/login$/);
        await page.goto('/forgot-password');
        await settle(page);
        await expect(
            page.getByRole('link', { name: 'Zurück zum Login' }),
        ).toHaveAttribute('href', /\/login$/);
    });

    test('Auth-Seiten 390: Formular folgt direkt auf das gruene Band (gestapelt)', async ({
        browser,
    }) => {
        const context = await contextFor(
            browser,
            'guest',
            MOBILE_390.viewport,
            true,
        );
        const page = await context.newPage();
        await page.goto('/login');
        await settle(page);
        const head = (await banner(page).boundingBox())!;
        const email = (await page.locator('#email').boundingBox())!;
        expect(head.width).toBeLessThanOrEqual(390);
        expect(email.y).toBeGreaterThan(head.y + head.height);
        // das E-Mail-Feld ist ohne langes Scrollen erreichbar
        expect(email.y).toBeLessThan(844 * 1.2);
        await context.close();
    });
});

// ---------------------------------------------------------------------------------------------
test.describe('Gate C2 – Konto loeschen', () => {
    test('Bestaetigung im Dialog, kein window.confirm; falsches Passwort loescht nichts', async ({
        browser,
    }) => {
        const context = await contextFor(browser, 'user2', {
            width: 1440,
            height: 900,
        });
        const page = await context.newPage();
        let nativeDialogs = 0;
        page.on('dialog', async (d) => {
            nativeDialogs++;
            await d.dismiss();
        });
        await page.goto('/settings/profile');
        await settle(page);
        const trigger = page.getByRole('button', { name: 'Konto löschen' });
        await trigger.click();
        const dialog = page.getByRole('dialog', {
            name: 'Konto endgültig löschen?',
        });
        await expect(dialog).toBeVisible();
        await expect(dialog).toHaveAttribute('aria-modal', 'true');
        // Fokus im Dialog auf dem Passwortfeld
        await expect(
            dialog.getByLabel('Passwort zur Bestätigung'),
        ).toBeFocused();
        // Escape schliesst, Fokus zurueck am Ausloeser
        await page.keyboard.press('Escape');
        await expect(dialog).toHaveCount(0);
        await expect(trigger).toBeFocused();

        // falsches Passwort: Fehlermeldung im Dialog, Konto bleibt
        await trigger.click();
        await dialog
            .getByLabel('Passwort zur Bestätigung')
            .fill('ganz-falsches-passwort');
        await dialog.getByRole('button', { name: 'Endgültig löschen' }).click();
        await expect(page.locator('#delete_password-error')).toBeVisible();
        await expect(dialog).toBeVisible();
        await expect(
            dialog.getByLabel('Passwort zur Bestätigung'),
        ).toHaveAttribute('aria-invalid', 'true');
        await dialog.getByRole('button', { name: 'Abbrechen' }).click();
        await expect(dialog).toHaveCount(0);
        expect(new URL(page.url()).pathname).toBe('/settings/profile');
        // weiterhin angemeldet
        await page.goto('/settings');
        await settle(page);
        expect(new URL(page.url()).pathname).toBe('/settings');
        expect(nativeDialogs, 'kein window.confirm').toBe(0);
        await context.close();
    });
});

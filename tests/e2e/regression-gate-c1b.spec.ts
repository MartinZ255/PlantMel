import type { Page } from '@playwright/test';
import { DATA, expect, settle, test } from './support/fixtures';
import { inertiaProps } from './support/helpers';
import { writeRaw } from './support/raw';

/**
 * Gate C1b (ADR-003 Revision 1) – Konzept „Natur & organisch“.
 *
 * Prueft die neuen Bestandteile der drei Musterseiten:
 * - Startseite: Prop `featuredRecipes` (Band wischbar, Links korrekt) und Fallback ohne Bilder
 * - Rezeptdetail: Zutaten zum Abhaken (echte Checkboxen mit Label, Tastatur, nichts gespeichert)
 * - Scroll-Einblendung (useReveal) respektiert prefers-reduced-motion
 * - Blob-Bildmasken verursachen bei 320 px keinen sichtbaren Ueberlauf, feste Seitenverhaeltnisse
 * - Fraunces kommt selbst gehostet aus /build/, keine Requests an fremde Hosts
 *
 * Datenveraenderungen: keine.
 */

const MOBILE_390 = {
    viewport: { width: 390, height: 844 },
    isMobile: true,
    hasTouch: true,
} as const;

type Featured = {
    id: number;
    name: string;
    image: string;
    categories: string[];
    duration_minutes: number | null;
};

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
 * Liefert die Startseite mit leerer Rezeptauswahl aus (ohne die Datenbank zu aendern):
 * die Antwort des Servers wird abgefangen und die Prop `featuredRecipes` im
 * Inertia-Seitenobjekt geleert.
 */
async function serveLandingWithoutFeatured(page: Page) {
    await page.route(
        (url) => url.pathname === '/',
        async (route) => {
            if (route.request().resourceType() !== 'document')
                return route.continue();
            const response = await route.fetch();
            let html = await response.text();
            const attr = html.match(/data-page="([^"]*)"/);
            if (attr) {
                const data = JSON.parse(decode(attr[1]));
                data.props.featuredRecipes = [];
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
                const data = JSON.parse(script[2]);
                data.props.featuredRecipes = [];
                html = html.replace(
                    script[0],
                    `${script[1]}${JSON.stringify(data).replace(/</g, '\\u003c')}${script[3]}`,
                );
            }
            await route.fulfill({ response, body: html });
        },
    );
}

// ---------------------------------------------------------------------------------------------
test.describe('Gate C1b – Startseite mit Rezeptauswahl', () => {
    test.use(MOBILE_390);

    test('Band zeigt die Rezepte aus featuredRecipes, ist wischbar und verlinkt korrekt', async ({
        page,
        diag,
    }) => {
        await page.goto('/');
        await settle(page);
        const props = await inertiaProps<{ featuredRecipes: Featured[] }>(page);
        const featured = props.featuredRecipes;
        // Seeder: 7 Rezepte mit Bild => Begrenzung auf 6, neueste zuerst, Rezept 3 hat kein Bild
        expect(featured.length).toBe(6);
        expect(featured.map((r) => r.id)).not.toContain(
            DATA.recipes.withoutImage.id,
        );

        const section = page.getByRole('region', {
            name: 'Eine kleine Auswahl',
        });
        await expect(section).toBeVisible();
        const list = page.locator('#landing-featured-list');
        const items = list.locator(':scope > li');
        await expect(items).toHaveCount(featured.length);

        // Links: Name und Ziel stimmen mit den Props ueberein
        for (const [i, r] of featured.entries()) {
            const link = items.nth(i).getByRole('link', { name: r.name });
            await expect(link).toHaveCount(1);
            expect(new URL((await link.getAttribute('href'))!).pathname).toBe(
                `/rezept/${r.id}`,
            );
        }

        // wischbar: eigener horizontaler Scrollbereich mit Scroll-Snap, Seite selbst ohne Ueberlauf
        const box = await list.evaluate((el) => {
            const s = getComputedStyle(el);
            return {
                overflowX: s.overflowX,
                snap: s.scrollSnapType,
                scrollWidth: el.scrollWidth,
                clientWidth: el.clientWidth,
            };
        });
        expect(box.overflowX).toBe('auto');
        expect(box.snap).toContain('x');
        expect(box.scrollWidth).toBeGreaterThan(box.clientWidth);
        await list.evaluate((el) => el.scrollBy({ left: 600 }));
        await expect
            .poll(() => list.evaluate((el) => el.scrollLeft))
            .toBeGreaterThan(0);
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth > window.innerWidth,
            ),
        ).toBe(false);

        // Tastatur: der zuletzt fokussierte Link wird in den Sichtbereich des Bands gescrollt
        const last = items.last().getByRole('link', {
            name: featured[featured.length - 1].name,
        });
        await last.focus();
        await expect
            .poll(() =>
                last.evaluate((el) => {
                    const r = el.getBoundingClientRect();
                    return r.left >= 0 && r.right <= window.innerWidth + 1;
                }),
            )
            .toBe(true);

        // Bilder im Band: lazy, festes Seitenverhaeltnis, object-fit cover
        const imgs = await list.locator('img').evaluateAll((els) =>
            els.map((e) => ({
                loading: (e as HTMLImageElement).loading,
                fit: getComputedStyle(e).objectFit,
                ratio: getComputedStyle(e.parentElement!).aspectRatio,
            })),
        );
        expect(imgs).toHaveLength(featured.length);
        for (const i of imgs)
            expect(i).toEqual({
                loading: 'lazy',
                fit: 'cover',
                ratio: '4 / 5',
            });

        // Klick auf eine Karte oeffnet das Rezept
        await Promise.all([
            page.waitForURL(`**/rezept/${featured[0].id}`),
            items.first().getByRole('link', { name: featured[0].name }).click(),
        ]);
        expect(diag.blockedExternal).toEqual([]);
        writeRaw('regression', 'C1b-featured', { featured, box, imgs });
    });

    test('Einstieg: echte Rezeptbilder, nur das erste ohne lazy; schwebende Karte verlinkt das neueste Rezept', async ({
        page,
    }) => {
        await page.goto('/');
        await settle(page);
        const props = await inertiaProps<{ featuredRecipes: Featured[] }>(page);
        const hero = page.getByRole('region', {
            name: /Lieblings.*rezepte, liebevoll gesammelt\./,
        });
        const imgs = await hero.locator('img').evaluateAll((els) =>
            els.map((e) => ({
                src: new URL((e as HTMLImageElement).src).pathname,
                loading: (e as HTMLImageElement).loading,
                alt: (e as HTMLImageElement).alt,
            })),
        );
        expect(imgs.length).toBe(3);
        expect(imgs[0].loading).toBe('eager');
        for (const i of imgs.slice(1)) expect(i.loading).toBe('lazy');
        expect(imgs.map((i) => i.src)).toEqual(
            props.featuredRecipes
                .slice(0, 3)
                .map((r) => new URL(r.image).pathname),
        );
        const card = hero.getByRole('link', {
            name: new RegExp(props.featuredRecipes[0].name),
        });
        await expect(card).toBeVisible();
        expect(new URL((await card.getAttribute('href'))!).pathname).toBe(
            `/rezept/${props.featuredRecipes[0].id}`,
        );
    });

    test('ohne Rezepte mit Bild: kein Band, grafische Komposition ohne Bilder, keine Fehler', async ({
        page,
        diag,
    }) => {
        await serveLandingWithoutFeatured(page);
        await page.goto('/');
        await settle(page);
        const props = await inertiaProps<{ featuredRecipes: Featured[] }>(page);
        expect(props.featuredRecipes).toEqual([]);
        await expect(page.locator('#landing-featured-list')).toHaveCount(0);
        await expect(
            page.getByRole('heading', { name: 'Eine kleine Auswahl' }),
        ).toHaveCount(0);
        await expect(page.getByRole('main').locator('img')).toHaveCount(0);
        // Einstieg, Vorteile und Abschluss sind weiterhin da
        await expect(page.locator('h1')).toHaveCount(1);
        await expect(
            page
                .getByRole('list', {
                    name: 'Was du mit PlantMel machen kannst',
                })
                .getByRole('listitem'),
        ).toHaveCount(3);
        await expect(
            page
                .getByRole('main')
                .getByRole('link', { name: 'Zu den Rezepten' }),
        ).toBeVisible();
        expect(diag.pageErrors).toEqual([]);
        expect(diag.consoleErrors).toEqual([]);
    });
});

// ---------------------------------------------------------------------------------------------
test.describe('Gate C1b – Zutaten zum Abhaken', () => {
    test('Checkboxen mit Label, per Tastatur bedienbar, nichts wird gespeichert', async ({
        page,
    }) => {
        const writes: string[] = [];
        page.on('request', (r) => {
            if (r.method() !== 'GET') writes.push(`${r.method()} ${r.url()}`);
        });
        await page.goto(`/rezept/${DATA.recipes.withImage.id}`);
        await settle(page);

        const list = page.getByRole('list', { name: 'Zutaten', exact: true });
        const boxes = list.getByRole('checkbox');
        const count = await boxes.count();
        expect(count).toBe(await list.getByRole('listitem').count());
        expect(count).toBeGreaterThan(2);

        // Label: Menge und Name der Zutat
        await expect(
            list.getByRole('checkbox', { name: /200 g\s+Rote Linsen/ }),
        ).toHaveCount(1);
        for (let i = 0; i < count; i++)
            expect(
                (await boxes
                    .nth(i)
                    .evaluate((e) => (e as HTMLInputElement).labels?.length)) ??
                    0,
            ).toBe(1);

        // Fortschritt als Text in einer hoeflichen Live-Region
        const status = page
            .getByRole('region', { name: 'Zutaten' })
            .locator('[aria-live="polite"]');
        await expect(status).toHaveText(`0 von ${count} abgehakt`);

        // Tastatur: Fokus auf die erste Checkbox, Leertaste hakt ab, Tab zur naechsten
        await boxes.first().focus();
        await expect(boxes.first()).toBeFocused();
        const ring = await boxes.first().evaluate((e) => {
            const s = getComputedStyle(e);
            return { style: s.outlineStyle, width: parseFloat(s.outlineWidth) };
        });
        expect(ring.style).not.toBe('none');
        expect(ring.width).toBeGreaterThanOrEqual(2);
        await page.keyboard.press('Space');
        await expect(boxes.first()).toBeChecked();
        await page.keyboard.press('Tab');
        await expect(boxes.nth(1)).toBeFocused();
        await page.keyboard.press('Space');
        await expect(boxes.nth(1)).toBeChecked();
        await expect(status).toHaveText(`2 von ${count} abgehakt`);

        // Abgehakt: durchgestrichen (nicht nur Farbe)
        const deco = await list
            .getByRole('listitem')
            .first()
            .locator('label span.text-wrap-anywhere')
            .first()
            .evaluate((e) => getComputedStyle(e).textDecorationLine);
        expect(deco).toContain('line-through');

        // Klick auf das Label schaltet ebenfalls um
        await list.getByRole('listitem').nth(2).locator('label').click();
        await expect(boxes.nth(2)).toBeChecked();

        // Zuruecksetzen
        await page
            .getByRole('button', { name: 'Häkchen zurücksetzen' })
            .click();
        await expect(status).toHaveText(`0 von ${count} abgehakt`);
        for (let i = 0; i < count; i++)
            await expect(boxes.nth(i)).not.toBeChecked();

        // Nichts gespeichert: kein schreibender Request, nach Neuladen alles frei
        await boxes.first().check();
        await page.reload();
        await settle(page);
        await expect(
            page
                .getByRole('list', { name: 'Zutaten', exact: true })
                .getByRole('checkbox')
                .first(),
        ).not.toBeChecked();
        expect(writes).toEqual([]);
    });
});

// ---------------------------------------------------------------------------------------------
test.describe('Gate C1b – Bewegung', () => {
    test('prefers-reduced-motion: Einblend-Elemente sofort sichtbar, keine Animation', async ({
        page,
    }) => {
        await page.emulateMedia({ reducedMotion: 'reduce' });
        for (const path of [
            '/',
            '/rezepte',
            `/rezept/${DATA.recipes.withImage.id}`,
        ]) {
            await page.goto(path);
            await settle(page);
            const state = await page.evaluate(() => {
                const reveals = Array.from(
                    document.querySelectorAll<HTMLElement>('.reveal'),
                );
                const animated = Array.from(
                    document.querySelectorAll<HTMLElement>(
                        '.animate-float, .animate-float-slow, .animate-morph, .animate-sway',
                    ),
                );
                return {
                    reveals: reveals.length,
                    hidden: reveals.filter((e) => e.dataset.reveal).length,
                    invisible: reveals.filter(
                        (e) => parseFloat(getComputedStyle(e).opacity) < 1,
                    ).length,
                    animated: animated.length,
                    running: animated.filter(
                        (e) => getComputedStyle(e).animationName !== 'none',
                    ).length,
                };
            });
            expect(state.reveals, path).toBeGreaterThan(0);
            expect(state.hidden, path).toBe(0);
            expect(state.invisible, path).toBe(0);
            expect(state.running, path).toBe(0);
        }
    });

    test('ohne reduzierte Bewegung: Elemente unterhalb des Sichtbereichs blenden beim Scrollen ein', async ({
        page,
    }) => {
        await page.emulateMedia({ reducedMotion: 'no-preference' });
        await page.goto('/');
        await settle(page);
        const cta = page.locator('.reveal').filter({
            has: page.getByRole('heading', { name: /Lust auf etwas/ }),
        });
        await expect(cta).toHaveAttribute('data-reveal', 'hidden');
        // Ornamente schweben (Animation aktiv)
        expect(
            await page
                .locator('.animate-morph')
                .first()
                .evaluate((e) => getComputedStyle(e).animationName),
        ).not.toBe('none');
        await cta.scrollIntoViewIfNeeded();
        await expect(cta).toHaveAttribute('data-reveal', 'shown');
        await expect
            .poll(() =>
                cta.evaluate((e) => parseFloat(getComputedStyle(e).opacity)),
            )
            .toBe(1);
        // Einblenden veraendert das Layout nicht (nur opacity/translate)
        const layoutShift = await cta.evaluate(
            (e) => getComputedStyle(e).position,
        );
        expect(layoutShift).not.toBe('absolute');
    });
});

// ---------------------------------------------------------------------------------------------
test.describe('Gate C1b – Bildmasken bei 320 px', () => {
    test.use({
        viewport: { width: 320, height: 640 },
        isMobile: true,
        hasTouch: true,
    });

    for (const path of [
        '/',
        '/rezepte',
        `/rezept/${DATA.recipes.withImage.id}`,
        `/rezept/${DATA.recipes.withoutImage.id}`,
    ]) {
        test(`Blob-Masken ohne sichtbaren Ueberlauf, festes Seitenverhaeltnis (${path})`, async ({
            page,
        }) => {
            await page.goto(path);
            await settle(page);
            const masks = await page.evaluate(() => {
                const vw = window.innerWidth;
                return Array.from(
                    document.querySelectorAll<HTMLElement>('main .blob-mask'),
                ).map((el) => {
                    const r = el.getBoundingClientRect();
                    let right = r.right;
                    let left = r.left;
                    for (
                        let cur = el.parentElement;
                        cur && cur !== document.body;
                        cur = cur.parentElement
                    ) {
                        if (getComputedStyle(cur).overflowX === 'visible')
                            continue;
                        const pr = cur.getBoundingClientRect();
                        right = Math.min(right, pr.right);
                        left = Math.max(left, pr.left);
                    }
                    const hasImg = !!el.querySelector(':scope > img');
                    return {
                        visibleOverflow:
                            right > vw + 1 || (right > left && left < -1),
                        ratio: getComputedStyle(el).aspectRatio,
                        height: Math.round(r.height),
                        hasImg,
                    };
                });
            });
            expect(masks.length).toBeGreaterThan(0);
            expect(masks.filter((m) => m.visibleOverflow)).toEqual([]);
            // Bildmasken haben ein festes Seitenverhaeltnis (kein Springen beim Laden)
            for (const m of masks.filter((x) => x.hasImg)) {
                expect(m.ratio).not.toBe('auto');
                expect(m.height).toBeGreaterThan(0);
            }
            expect(
                await page.evaluate(
                    () =>
                        document.documentElement.scrollWidth >
                        window.innerWidth,
                ),
            ).toBe(false);
        });
    }
});

// ---------------------------------------------------------------------------------------------
test.describe('Gate C1b – Schrift und fremde Hosts', () => {
    test('Fraunces wird von /build/ geladen; keine Requests an fremde Hosts', async ({
        page,
        diag,
    }) => {
        const requests: string[] = [];
        page.on('request', (r) => requests.push(r.url()));
        for (const path of [
            '/',
            '/rezepte',
            `/rezept/${DATA.recipes.withImage.id}`,
        ]) {
            await page.goto(path);
            await settle(page);
            const fonts = await page.evaluate(async () => {
                await document.fonts.ready;
                const h1 = document.querySelector('h1')!;
                return {
                    check: document.fonts.check('600 48px "Fraunces"'),
                    loaded: Array.from(document.fonts)
                        .filter(
                            (f) => f.family.replace(/"/g, '') === 'Fraunces',
                        )
                        .map((f) => `${f.style}:${f.status}`),
                    h1Family: getComputedStyle(h1).fontFamily,
                };
            });
            expect(fonts.check, path).toBe(true);
            expect(fonts.loaded, path).toContain('normal:loaded');
            expect(fonts.h1Family, path).toMatch(/^"?Fraunces/);
        }
        const woff2 = requests.filter((u) =>
            /\/build\/assets\/fraunces-latin-[a-z]+-(normal|italic)-[^/]+\.woff2$/.test(
                new URL(u).pathname,
            ),
        );
        expect(woff2.length).toBeGreaterThan(0);
        const origin = new URL(page.url()).origin;
        expect(
            requests.filter(
                (u) => /^https?:/.test(u) && new URL(u).origin !== origin,
            ),
        ).toEqual([]);
        expect(diag.blockedExternal).toEqual([]);
        writeRaw('regression', 'C1b-fonts', { woff2: [...new Set(woff2)] });
    });
});

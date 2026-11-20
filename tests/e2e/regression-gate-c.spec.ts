import AxeBuilder from '@axe-core/playwright';
import type { Locator } from '@playwright/test';
import {
    authFile,
    DATA,
    expect,
    measureLayout,
    settle,
    test,
} from './support/fixtures';
import { writeRaw } from './support/raw';

/**
 * Gate C (ADR-003) – Design-System, Kopfnavigation und drei Musterseiten.
 *
 * Prueft die neuen Bausteine ueber die Musterseiten (keine Testseite in der App):
 * Dialog (Fokusfalle, Escape, Rueckgabe), Chip (aria-pressed, entfernbare Filter),
 * StarRating (Textaequivalent), Karten (Bild mit festem Seitenverhaeltnis, lazy),
 * Kontraste der Tokens per getComputedStyle und axe-Zaehlung (color-contrast = 0).
 *
 * Datenveraenderungen: keine (Loeschen wird nur geoeffnet und abgebrochen).
 */

const MOBILE_390 = {
    viewport: { width: 390, height: 844 },
    isMobile: true,
    hasTouch: true,
} as const;

// ---------------------------------------------------------------------------------------------
// Kontrast (WCAG 2.x), gleiche Formel wie in DESIGN-SYSTEM.md
// ---------------------------------------------------------------------------------------------
type Rgb = [number, number, number];

function parseRgb(value: string): Rgb {
    const m = value.match(/rgba?\(([^)]+)\)/);
    if (!m) throw new Error(`Farbe nicht lesbar: ${value}`);
    const [r, g, b] = m[1].split(/[ ,/]+/).map(Number);
    return [r, g, b];
}

function luminance([r, g, b]: Rgb): number {
    const lin = (c: number) => {
        const v = c / 255;
        return v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
    };
    return 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
}

function ratio(a: Rgb, b: Rgb): number {
    const [x, y] = [luminance(a), luminance(b)].sort((p, q) => q - p);
    return (x + 0.05) / (y + 0.05);
}

const hex = (h: string): Rgb => {
    const v = h.replace('#', '');
    return [0, 2, 4].map((i) => parseInt(v.slice(i, i + 2), 16)) as Rgb;
};

/** Vordergrund- und effektive Hintergrundfarbe (erster nicht transparenter Vorfahre). */
async function colorsOf(locator: Locator, prop: 'color' | 'outlineColor' = 'color') {
    return locator.evaluate((el, p) => {
        const fg = getComputedStyle(el)[p as 'color'];
        let cur: Element | null = el;
        let bg = 'rgb(255, 255, 255)';
        while (cur) {
            const c = getComputedStyle(cur).backgroundColor;
            if (c && c !== 'transparent' && !/rgba\(.*,\s*0\)$/.test(c)) {
                bg = c;
                break;
            }
            cur = cur.parentElement;
        }
        return { fg, bg };
    }, prop);
}

test.describe('Gate C – Kontraste der Tokens im Browser', () => {
    test.use({ storageState: authFile('host') });

    test('Kontrast wichtiger Paare entspricht der Berechnung (Text >= 4,5, Bedienelemente/Fokus >= 3)', async ({
        page,
    }) => {
        await page.goto(`/rezept/${DATA.recipes.withImage.id}`);
        await settle(page);

        type Case = {
            name: string;
            locator: Locator;
            expected: number; // aus den Token-Hexwerten berechnet
            min: number;
            prop?: 'color' | 'outlineColor';
        };
        // Gate C1b: Tokens „Natur & organisch“ (DESIGN-SYSTEM.md). Der Kopf der
        // Detailseite ist Waldgrün, der Inhalt steht auf dem Naturgrund.
        const T = {
            canvas: '#f2f5ea',
            surface: '#fcfdf8',
            tint: '#e4edd3',
            ink: '#142a1f',
            forest: '#143d2d',
            forestDeep: '#0d2a1f',
            onForest: '#f2f5ea',
            onForestMuted: '#b9cfb6',
            matcha: '#c8e28a',
            dangerOnForest: '#ffc2b8',
        };
        const cases: Case[] = [
            {
                name: 'Titel on-forest auf forest',
                locator: page.locator('h1'),
                expected: ratio(hex(T.onForest), hex(T.forest)),
                min: 4.5,
            },
            {
                name: 'Beschreibung on-forest-muted auf forest',
                locator: page.locator('h1 + p'),
                expected: ratio(hex(T.onForestMuted), hex(T.forest)),
                min: 4.5,
            },
            {
                name: 'Hauptaktion (lime) forest auf matcha',
                locator: page.getByRole('button', {
                    name: 'Zur Sammlung hinzufügen',
                }),
                expected: ratio(hex(T.forest), hex(T.matcha)),
                min: 4.5,
            },
            {
                name: 'Nebenaktion (outline-light) on-forest auf forest',
                locator: page.getByRole('link', { name: 'Rezept bearbeiten' }),
                expected: ratio(hex(T.onForest), hex(T.forest)),
                min: 4.5,
            },
            {
                name: 'Loeschen danger-on-forest auf forest',
                locator: page.getByRole('button', { name: 'Rezept löschen' }),
                expected: ratio(hex(T.dangerOnForest), hex(T.forest)),
                min: 4.5,
            },
            {
                name: 'Badge forest auf matcha',
                locator: page
                    .getByRole('list', { name: 'Kategorien' })
                    .getByRole('listitem')
                    .first()
                    .locator('span')
                    .first(),
                expected: ratio(hex(T.forest), hex(T.matcha)),
                min: 4.5,
            },
            {
                name: 'Zutaten-Ueberschrift ink auf surface',
                locator: page.getByRole('heading', { name: 'Zutaten' }),
                expected: ratio(hex(T.ink), hex(T.surface)),
                min: 4.5,
            },
            {
                name: 'Schritt-Text ink auf canvas',
                locator: page
                    .getByRole('list', { name: 'Zubereitung', exact: true })
                    .locator('p')
                    .first(),
                expected: ratio(hex(T.ink), hex(T.canvas)),
                min: 4.5,
            },
            {
                name: 'Notizen ink auf tint',
                locator: page.getByRole('heading', { name: 'Notizen & Tipps' }),
                expected: ratio(hex(T.ink), hex(T.tint)),
                min: 4.5,
            },
            {
                name: 'Footer-Link on-forest-muted auf forest-deep',
                locator: page
                    .getByRole('contentinfo')
                    .getByRole('link', { name: 'Impressum' }),
                expected: ratio(hex(T.onForestMuted), hex(T.forestDeep)),
                min: 4.5,
            },
        ];

        const rows: Record<string, unknown>[] = [];
        for (const c of cases) {
            const { fg, bg } = await colorsOf(c.locator, c.prop);
            const measured = ratio(parseRgb(fg), parseRgb(bg));
            rows.push({ name: c.name, fg, bg, measured, expected: c.expected });
            expect(measured, `${c.name}: ${fg} auf ${bg}`).toBeGreaterThanOrEqual(
                c.min,
            );
            expect(
                Math.abs(measured - c.expected),
                `${c.name}: gemessen ${measured.toFixed(2)} statt ${c.expected.toFixed(2)}`,
            ).toBeLessThan(0.05);
        }

        // Fokus-Ring (Tastatur) >= 3:1 gegen die Flaeche dahinter: auf Waldgruen
        // schaltet die Utility `on-forest` den Ring auf Matcha um
        const btn = page.getByRole('button', { name: 'Zur Sammlung hinzufügen' });
        await page.getByRole('link', { name: 'Zur Übersicht' }).focus();
        await page.keyboard.press('Tab');
        // naechste Fokusstelle kann je nach Kategorien variieren: bis zum Button tabben
        for (let i = 0; i < 10 && !(await btn.evaluate((e) => e === document.activeElement)); i++)
            await page.keyboard.press('Tab');
        await expect(btn).toBeFocused();
        const ring = await btn.evaluate((el) => {
            const s = getComputedStyle(el);
            return {
                color: s.outlineColor,
                width: parseFloat(s.outlineWidth),
                style: s.outlineStyle,
            };
        });
        const ringRatio = ratio(parseRgb(ring.color), hex(T.forest));
        rows.push({ name: 'Fokus-Ring', ...ring, ringRatio });
        expect(ring.style).not.toBe('none');
        expect(ring.width).toBeGreaterThanOrEqual(2);
        expect(ringRatio).toBeGreaterThanOrEqual(3);

        // Sterne (Grafik) >= 3:1: im Waldgruen-Kopf Matcha; die Kachel ist dunkler als
        // forest, der Vergleich mit forest ist daher die ungünstigste Stelle
        const star = page
            .getByRole('definition')
            .locator('svg')
            .last();
        const starColor = await star.evaluate(
            (el) => getComputedStyle(el).color,
        );
        const starRatio = ratio(parseRgb(starColor), hex(T.forest));
        rows.push({ name: 'Stern gefuellt', starColor, starRatio });
        expect(starRatio).toBeGreaterThanOrEqual(3);

        // Sterne im hellen Inhalt (Bewertung, E14: nur Geschmack): Beere auf
        // der Beeren-Flaeche (berry-surface) der Geschmacks-Karte
        const lightStar = page
            .getByRole('region', { name: 'Bewertung', exact: true })
            .locator('svg')
            .last();
        const lightStarColor = await lightStar.evaluate(
            (el) => getComputedStyle(el).color,
        );
        const lightStarRatio = ratio(parseRgb(lightStarColor), hex('#f7e7ec'));
        rows.push({ name: 'Stern hell', lightStarColor, lightStarRatio });
        expect(lightStarRatio).toBeGreaterThanOrEqual(3);

        writeRaw('regression', 'gate-c-kontrast', { rows });
    });

    test('Eingabefeld: Rahmen >= 3:1, Schrift 16 px', async ({ page }) => {
        await page.goto('/rezepte');
        await settle(page);
        const input = page.getByLabel('Rezepte nach Namen durchsuchen');
        const st = await input.evaluate((el) => {
            const s = getComputedStyle(el);
            return { border: s.borderTopColor, size: parseFloat(s.fontSize) };
        });
        expect(st.size).toBeGreaterThanOrEqual(16);
        // Gate C1b: Feld auf surface (#fcfdf8)
        expect(
            ratio(parseRgb(st.border), hex('#fcfdf8')),
        ).toBeGreaterThanOrEqual(3);
    });
});

// ---------------------------------------------------------------------------------------------
// axe: color-contrast muss 0 sein; andere Regeln werden gezaehlt und dokumentiert
// ---------------------------------------------------------------------------------------------
const axePages = [
    { role: 'guest', slug: 'startseite', path: '/' },
    { role: 'guest', slug: 'rezeptliste', path: '/rezepte' },
    {
        role: 'guest',
        slug: 'rezeptdetail-ohne-bild',
        path: `/rezept/${DATA.recipes.withoutImage.id}`,
    },
    {
        role: 'host',
        slug: 'rezeptdetail-mit-bild-host',
        path: `/rezept/${DATA.recipes.withImage.id}`,
    },
] as const;

for (const vp of [
    { label: '1440', use: { viewport: { width: 1440, height: 900 } } },
    { label: '390', use: MOBILE_390 },
]) {
    test.describe(`Gate C – axe (${vp.label})`, () => {
        test.use(vp.use);
        for (const p of axePages) {
            test.describe(p.slug, () => {
                if (p.role === 'host')
                    test.use({ storageState: authFile('host') });
                test(`axe ${p.slug} ${vp.label}: color-contrast = 0`, async ({
                    page,
                }) => {
                    await page.goto(p.path);
                    await settle(page);
                    const results = await new AxeBuilder({ page }).analyze();
                    const summary = Object.fromEntries(
                        results.violations.map((v) => [
                            `${v.id} (${v.impact ?? 'n/a'})`,
                            v.nodes.length,
                        ]),
                    );
                    writeRaw('regression', `gate-c-axe-${p.slug}-${vp.label}`, {
                        summary,
                    });
                    test.info().annotations.push({
                        type: 'axe',
                        description: JSON.stringify(summary),
                    });
                    const contrast = results.violations.find(
                        (v) => v.id === 'color-contrast',
                    );
                    expect(
                        contrast?.nodes.map((n) => n.target.join(' ')) ?? [],
                        'color-contrast',
                    ).toEqual([]);
                });
            });
        }
    });
}

// ---------------------------------------------------------------------------------------------
// Musterseiten: Layout und Bausteine
// ---------------------------------------------------------------------------------------------
test.describe('Gate C – kein horizontaler Ueberlauf bei 320 px', () => {
    test.use({
        viewport: { width: 320, height: 640 },
        isMobile: true,
        hasTouch: true,
    });
    for (const path of [
        '/',
        '/rezepte',
        `/rezept/${DATA.recipes.longTitle.id}`,
        `/rezept/${DATA.recipes.withoutImage.id}`,
    ]) {
        test(`320 px ${path}`, async ({ page }) => {
            await page.goto(path);
            await settle(page);
            const m = await measureLayout(page);
            expect(m.horizontalOverflow).toBe(false);
            expect(
                m.overflowingElementCount,
                m.overflowingElements.join(', '),
            ).toBe(0);
            expect(m.clippedH1).toBe(false);
            expect(m.h1Count).toBe(1);
        });
    }
});

test.describe('Gate C – Rezeptliste (Gast)', () => {
    test('Karten: Bild oben mit festem Seitenverhaeltnis, lazy, object-fit cover; Titel-Link deckt die Karte', async ({
        page,
    }) => {
        await page.goto('/rezepte');
        await settle(page);
        const cards = page.getByRole('main').getByRole('article');
        expect(await cards.count()).toBeGreaterThan(2);
        const imgs = await page
            .getByRole('main')
            .locator('article img')
            .evaluateAll((els) =>
                els.map((e) => {
                    const img = e as HTMLImageElement;
                    const box = img.parentElement!.getBoundingClientRect();
                    const s = getComputedStyle(img);
                    return {
                        loading: img.loading,
                        fit: s.objectFit,
                        ratio: Math.round((box.width / box.height) * 100) / 100,
                    };
                }),
            );
        expect(imgs.length).toBeGreaterThan(0);
        for (const i of imgs) {
            expect(i).toEqual({ loading: 'lazy', fit: 'cover', ratio: 1.33 });
        }
        // Karte ohne Bild hat denselben Bildbereich (kein Layout-Springen)
        const noImage = cards.filter({
            hasText: DATA.recipes.withoutImage.title,
        });
        await expect(noImage.getByText('Noch kein Foto')).toBeVisible();
        // Bewertung mit Textaequivalent
        await expect(
            cards
                .filter({ hasText: DATA.recipes.withImage.title })
                .getByText(/von 5/),
        ).toHaveCount(1);
        // Klick irgendwo auf die Karte (Bild) oeffnet das Rezept
        const card = cards.filter({ hasText: DATA.recipes.withImage.title });
        const box = (await card.boundingBox())!;
        await Promise.all([
            page.waitForURL(`**/rezept/${DATA.recipes.withImage.id}`),
            page.mouse.click(box.x + box.width / 2, box.y + 40),
        ]);
    });

    test('Filterbereich (Desktop): aria-expanded, Kategorie-Chips mit aria-pressed, aktive Filter als entfernbare Chips', async ({
        page,
    }) => {
        await page.goto('/rezepte');
        await settle(page);
        const toggle = page.getByRole('button', { name: /^Filter/ }).first();
        await expect(toggle).toHaveAttribute('aria-expanded', 'false');
        const panel = page.locator('#recipe-filter-panel');
        await expect(panel).toBeHidden();
        await toggle.click();
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        await expect(panel).toBeVisible();

        // Kategorie wirkt sofort
        const dessert = panel.getByRole('button', { name: /^Dessert/ });
        await expect(dessert).toHaveAttribute('aria-pressed', 'false');
        await Promise.all([
            page.waitForURL(/tags/),
            dessert.click(),
        ]);
        await settle(page);
        await expect(dessert).toHaveAttribute('aria-pressed', 'true');
        const active = page.getByRole('list', { name: 'Aktive Filter' });
        await expect(
            active.getByRole('button', { name: 'Filter entfernen: Dessert' }),
        ).toBeVisible();
        await expect(page.getByRole('status').filter({ hasText: 'Ergebnis' })).toHaveText(
            /^\d+ Ergebnis(se)?$/,
        );

        // Zutat einschliessen ueber Vorschlag + Anwenden
        await panel.getByRole('button', { name: 'Tomaten' }).click();
        await expect(page.getByLabel('Enthaltene Zutaten')).toHaveValue(
            'Tomaten',
        );
        await Promise.all([
            page.waitForURL(/includeIngredients/),
            panel
                .getByRole('button', { name: 'Zutatenfilter anwenden' })
                .click(),
        ]);
        await settle(page);
        await expect(
            active.getByRole('button', {
                name: 'Filter entfernen: mit Tomaten',
            }),
        ).toBeVisible();
        await expect(toggle).toHaveAccessibleName('Filter 2 aktiv');

        // Chip entfernen
        await Promise.all([
            page.waitForURL((u) => !u.search.includes('tags')),
            active
                .getByRole('button', { name: 'Filter entfernen: Dessert' })
                .click(),
        ]);
        await settle(page);
        await expect(
            active.getByRole('button', { name: 'Filter entfernen: Dessert' }),
        ).toHaveCount(0);

        // Alle zuruecksetzen
        await page
            .getByRole('button', { name: 'Alle Filter entfernen' })
            .click();
        await page.waitForURL((u) => u.search === '');
        await settle(page);
        await expect(active).toHaveCount(0);

        // Escape im Bereich schliesst ihn und gibt den Fokus zurueck
        await panel.getByRole('button', { name: /^Dessert/ }).focus();
        await page.keyboard.press('Escape');
        await expect(panel).toBeHidden();
        await expect(toggle).toBeFocused();
    });

    test.describe('mobil', () => {
        test.use(MOBILE_390);
        test('Filter mobil als Dialog mit Fokusfalle', async ({ page }) => {
            await page.goto('/rezepte');
            await settle(page);
            const toggle = page.getByRole('button', { name: /^Filter/ });
            await expect(toggle).toHaveAttribute('aria-haspopup', 'dialog');
            await toggle.click();
            const dialog = page.getByRole('dialog', { name: 'Filter' });
            await expect(dialog).toBeVisible();
            for (let i = 0; i < 25; i++) {
                await page.keyboard.press('Tab');
                expect(
                    await dialog.evaluate((d) =>
                        d.contains(document.activeElement),
                    ),
                    `Tab ${i + 1}`,
                ).toBe(true);
            }
            await page.keyboard.press('Escape');
            await expect(dialog).toHaveCount(0);
            await expect(toggle).toBeFocused();
        });
    });
});

test.describe('Gate C – Rezeptdetail', () => {
    test('Gast: Bewertung mit Textaequivalent, Zutaten mit Alternativen, nummerierte Schritte', async ({
        page,
    }) => {
        await page.goto(`/rezept/${DATA.recipes.withImage.id}`);
        await settle(page);
        // StarRating (Anzeige): "x von 5" als Text
        const rating = page
            .getByRole('definition')
            .filter({ has: page.locator('svg') });
        await expect(rating).toContainText(/\d(,\d)? von 5/);
        // Bild: festes Seitenverhaeltnis, deckend
        const img = page.getByRole('img', {
            name: `Foto: ${DATA.recipes.withImage.title}`,
        });
        await expect(img).toBeVisible();
        expect(await img.evaluate((e) => getComputedStyle(e).objectFit)).toBe(
            'cover',
        );
        await expect(
            page
                .getByRole('list', { name: 'Zutaten', exact: true })
                .getByRole('listitem')
                .filter({ hasText: 'oder:' })
                .first(),
        ).toBeVisible();
        const steps = page.getByRole('list', {
            name: 'Zubereitung',
            exact: true,
        });
        expect(await steps.evaluate((e) => e.tagName)).toBe('OL');
        expect(await steps.getByRole('listitem').count()).toBeGreaterThan(0);
    });

    test('Gast: ohne Bild dekorative Flaeche ohne Leerraum (F-85)', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 1440, height: 900 });
        await page.goto(`/rezept/${DATA.recipes.withoutImage.id}`);
        await settle(page);
        await expect(page.getByRole('main').getByRole('img')).toHaveCount(0);
        const h1 = (await page.locator('h1').boundingBox())!;
        // Titel beginnt kurz unter der Bildflaeche (kein gestreckter Leerraum)
        expect(h1.y).toBeLessThan(600);
    });

    test.describe('Host', () => {
        test.use({ storageState: authFile('host') });

        test('Loeschen fragt per Dialog nach; Abbrechen sendet nichts und gibt den Fokus zurueck', async ({
            page,
        }) => {
            const deletes: string[] = [];
            page.on('request', (r) => {
                if (r.method() === 'DELETE') deletes.push(r.url());
            });
            await page.goto(`/rezept/${DATA.recipes.withImage.id}`);
            await settle(page);
            const trigger = page.getByRole('button', { name: 'Rezept löschen' });
            await trigger.click();
            const dialog = page.getByRole('dialog', { name: 'Rezept löschen?' });
            await expect(dialog).toBeVisible();
            await expect(dialog).toHaveAttribute('aria-modal', 'true');
            await expect(dialog).toContainText(DATA.recipes.withImage.title);
            // Fokus im Dialog, Fokusfalle
            for (let i = 0; i < 6; i++) {
                await page.keyboard.press('Tab');
                expect(
                    await dialog.evaluate((d) =>
                        d.contains(document.activeElement),
                    ),
                ).toBe(true);
            }
            await dialog.getByRole('button', { name: 'Abbrechen' }).click();
            await expect(dialog).toHaveCount(0);
            await expect(trigger).toBeFocused();
            // Schliessen-Knopf und Escape
            await trigger.click();
            await page
                .getByRole('dialog', { name: 'Rezept löschen?' })
                .getByRole('button', { name: 'Dialog schließen' })
                .click();
            await expect(dialog).toHaveCount(0);
            expect(deletes).toEqual([]);
        });

        test('Kopfzeile: "Rezept erstellen" fuer Hosts sichtbar', async ({
            page,
        }) => {
            await page.goto('/rezepte');
            await settle(page);
            const create = page
                .getByRole('banner')
                .getByRole('link', { name: 'Rezept erstellen' });
            await expect(create).toBeVisible();
            expect(new URL((await create.getAttribute('href'))!).pathname).toBe(
                '/rezepte/erstellen',
            );
        });
    });

    test.describe('Nutzerin', () => {
        test.use({ storageState: authFile('user') });
        test('Zur Sammlung hinzufuegen: Dialog mit Fokusfalle', async ({
            page,
        }) => {
            await page.goto(`/rezept/${DATA.recipes.withImage.id}`);
            await settle(page);
            await page
                .getByRole('button', { name: 'Zur Sammlung hinzufügen' })
                .click();
            const dialog = page.getByRole('dialog', {
                name: 'Zur Sammlung hinzufügen',
            });
            // erster Fokus auf der ersten Sammlung (Reihenfolge wie vom Server geliefert)
            await expect(dialog.getByRole('checkbox').first()).toBeFocused();
            for (let i = 0; i < 8; i++) {
                await page.keyboard.press('Shift+Tab');
                expect(
                    await dialog.evaluate((d) =>
                        d.contains(document.activeElement),
                    ),
                ).toBe(true);
            }
            await page.keyboard.press('Escape');
            await expect(dialog).toHaveCount(0);
        });
    });
});

test.describe('Gate C – Navigation und Bewegung', () => {
    test('Interne Links der Kopfnavigation laden die Seite nicht neu (Inertia, F-54)', async ({
        page,
    }) => {
        await page.goto('/');
        await settle(page);
        await page.evaluate(() => {
            (window as unknown as { __marker: number }).__marker = 42;
        });
        await Promise.all([
            page.waitForURL('**/zutatensuche'),
            page
                .getByRole('banner')
                .getByRole('link', { name: 'Zutatensuche' })
                .click(),
        ]);
        await settle(page);
        expect(
            await page.evaluate(
                () => (window as unknown as { __marker?: number }).__marker,
            ),
        ).toBe(42);
    });

    test('prefers-reduced-motion: Uebergaenge praktisch aus', async ({
        page,
    }) => {
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.goto('/rezepte');
        await settle(page);
        const d = await page
            .getByRole('button', { name: /^Filter/ })
            .evaluate((e) => parseFloat(getComputedStyle(e).transitionDuration));
        expect(d).toBeLessThan(0.001);
        await page.emulateMedia({ reducedMotion: 'no-preference' });
        await page.reload();
        await settle(page);
        const d2 = await page
            .getByRole('button', { name: /^Filter/ })
            .evaluate((e) => parseFloat(getComputedStyle(e).transitionDuration));
        expect(d2).toBeGreaterThan(0.05);
    });
});

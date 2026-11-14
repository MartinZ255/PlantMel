import AxeBuilder from '@axe-core/playwright';
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
 * Browser-Baseline: zeichnet den IST-Zustand je Seite x Viewport auf.
 * Schlaegt nur bei Infrastrukturfehlern fehl (Server nicht erreichbar, Navigation bricht ab)
 * oder wenn sich ein Screenshot gegenueber der abgelegten Baseline aendert.
 * Defekte (JS-Fehler, Overflow, 500er) werden NICHT als Fehlschlag gewertet, sondern
 * in test-results/e2e-raw bzw. docs/modernization/baseline/browser/baseline-results.json erfasst.
 */
type PageSpec = { slug: string; path: string };

const R = DATA.recipes;
const C = DATA.collections;

const pagesByRole: Record<'guest' | 'user' | 'host', PageSpec[]> = {
    guest: [
        { slug: 'landing', path: '/' },
        { slug: 'rezepte', path: '/rezepte' },
        { slug: 'rezept-mit-bild', path: `/rezept/${R.withImage.id}` },
        { slug: 'rezept-ohne-bild', path: `/rezept/${R.withoutImage.id}` },
        { slug: 'rezept-langer-titel', path: `/rezept/${R.longTitle.id}` },
        { slug: 'zutatensuche', path: '/zutatensuche' },
        { slug: 'login', path: '/login' },
        { slug: 'register', path: '/register' },
        { slug: 'forgot-password', path: '/forgot-password' },
        // Slice 3 (F-68): Inertia-Fehlerseite 404
        { slug: 'fehlerseite-404', path: '/gibt-es-nicht' },
    ],
    user: [
        { slug: 'home', path: '/home' },
        { slug: 'sammlungen', path: '/sammlungen' },
        { slug: 'sammlung-erstellen', path: '/sammlungen/erstellen' },
        { slug: 'sammlung-leer', path: `/sammlung/${C.empty.id}` },
        { slug: 'sammlung-gefuellt', path: `/sammlung/${C.filled.id}` },
        { slug: 'meine-zutaten', path: '/meineZutaten' },
        { slug: 'settings', path: '/settings' },
        { slug: 'settings-profile', path: '/settings/profile' },
        { slug: 'settings-password', path: '/settings/password' },
    ],
    host: [
        { slug: 'rezept-erstellen', path: '/rezepte/erstellen' },
        {
            slug: 'rezept-bearbeiten',
            path: `/rezept/${R.withImage.id}/bearbeiten`,
        },
        { slug: 'rezept-host-langer-titel', path: `/rezept/${R.longTitle.id}` },
    ],
};

const AXE_PROJECTS = new Set(['d1440', 'm390']);
const MAX_SHOT_HEIGHT = 5000;

for (const role of ['guest', 'user', 'host'] as const) {
    test.describe(`${role}`, () => {
        if (role !== 'guest') test.use({ storageState: authFile(role) });

        for (const spec of pagesByRole[role]) {
            test(`${role} ${spec.slug} (${spec.path})`, async ({
                page,
                diag,
            }, testInfo) => {
                const project = testInfo.project.name;
                const response = await page.goto(spec.path, {
                    waitUntil: 'domcontentloaded',
                });
                expect(response, 'Server antwortet').not.toBeNull();
                await settle(page);

                const status = response!.status();
                const finalPath = new URL(page.url()).pathname;
                const layout = await measureLayout(page);
                const heights = await page.evaluate(() => ({
                    docScrollHeight: document.documentElement.scrollHeight,
                    bodyScrollHeight: document.body.scrollHeight,
                    innerHeight: window.innerHeight,
                    inertiaMounted:
                        !!document.querySelector('#app')?.children.length,
                }));

                let axe: Record<string, number> | null = null;
                if (AXE_PROJECTS.has(project) && status < 500) {
                    const results = await new AxeBuilder({ page }).analyze();
                    axe = Object.fromEntries(
                        results.violations
                            .map(
                                (v) =>
                                    [
                                        `${v.id} (${v.impact ?? 'n/a'})`,
                                        v.nodes.length,
                                    ] as const,
                            )
                            .sort((a, b) => a[0].localeCompare(b[0])),
                    );
                }

                // 5xx-Seiten (Debug-Fehlerseite mit lokalen Pfaden/Zeiten) werden nicht als Bild abgelegt.
                const snapshotArg =
                    status < 500 ? `${role}-${spec.slug}.png` : null;
                const screenshot = snapshotArg
                    ? `tests/e2e/baseline.spec.ts-snapshots/${role}-${spec.slug}-${project}-${process.platform}.png`
                    : null;

                writeRaw('baseline', `${role}__${spec.slug}__${project}`, {
                    role,
                    slug: spec.slug,
                    path: spec.path,
                    project,
                    status,
                    finalPath,
                    ...layout,
                    ...heights,
                    consoleErrorCount: diag.consoleErrors.length,
                    consoleErrors: diag.consoleErrors.slice(0, 5),
                    pageErrorCount: diag.pageErrors.length,
                    pageErrors: diag.pageErrors.slice(0, 5),
                    failedRequests: diag.failedRequests.slice(0, 10),
                    httpErrors: diag.httpErrors.slice(0, 10),
                    blockedExternal: [...new Set(diag.blockedExternal)],
                    axeViolations: axe,
                    screenshot,
                });

                if (snapshotArg) {
                    // Laut Messung ist <body> der Scroll-Container (html/body height:100%), nicht das
                    // Dokument. `fullPage` wuerde dann nur den Viewport abbilden. Deshalb wird der
                    // Viewport fuer das Bild auf die Inhaltshoehe vergroessert (max. MAX_SHOT_HEIGHT).
                    const viewport = page.viewportSize()!;
                    const contentHeight = Math.max(
                        heights.docScrollHeight,
                        heights.bodyScrollHeight,
                        viewport.height,
                    );
                    const shotHeight = Math.min(contentHeight, MAX_SHOT_HEIGHT);
                    if (shotHeight !== viewport.height) {
                        await page.setViewportSize({
                            width: viewport.width,
                            height: shotHeight,
                        });
                        await settle(page);
                    }
                    await expect(page).toHaveScreenshot(snapshotArg, {
                        fullPage: true,
                    });
                }
            });
        }
    });
}

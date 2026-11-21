import type { Page } from '@playwright/test';
import vue from '@vitejs/plugin-vue';
import { spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { existsSync, mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createServer, type Plugin, type ViteDevServer } from 'vite';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { expect, settle, test } from './support/fixtures';

/**
 * Logo-Vorbereitung (docs/modernization/LOGO.md).
 *
 * 1. Live: Wortmarke in Header, Footer und AuthShell kommt aus BrandLogo
 *    (Modus `builtin`), zugänglicher Name des Startseiten-Links.
 * 2. BrandLogo in beiden Modi (`builtin`/`custom`), ohne den Schalter im Repo zu
 *    ändern: Vite rendert die Komponente serverseitig, brand.config.json wird
 *    nur im Test-Server ersetzt.
 * 3. Icon-Pipeline: scripts/brand/generate-icons.mjs mit Platzhalter in einen
 *    temporären Ordner; Größen der PNG/ICO-Dateien; public/ bleibt unverändert.
 *
 * Keine Datenveränderungen.
 */

const ROOT = path.resolve(
    path.dirname(fileURLToPath(import.meta.url)),
    '..',
    '..',
);
const SCRIPT = path.join(ROOT, 'scripts', 'brand', 'generate-icons.mjs');
const HOME_LABEL = 'PlantMel – Startseite';

// ---------------------------------------------------------------------------
// 1. Live
// ---------------------------------------------------------------------------

test.describe('Marke – live (Modus builtin)', () => {
    test('Header, Footer und AuthShell zeigen die Wortmarke aus BrandLogo', async ({
        page,
    }) => {
        await page.goto('/');
        await settle(page);

        const headerLink = page
            .getByRole('banner')
            .getByRole('link', { name: HOME_LABEL, exact: true });
        await expect(headerLink).toBeVisible();
        expect(new URL((await headerLink.getAttribute('href'))!).pathname).toBe(
            '/',
        );
        await expect(headerLink).toHaveAttribute('aria-current', 'page');
        // builtin: kein Bild, Signet ist dekorativ
        await expect(headerLink.locator('img')).toHaveCount(0);
        await expect(
            headerLink.locator('[aria-hidden="true"] svg'),
        ).toHaveCount(1);
        await expect(headerLink).not.toHaveAttribute('data-brand-mode', /.*/);

        // Footer: Marke ohne Link
        const footer = page.getByRole('contentinfo');
        await expect(
            footer.getByText('PlantMel', { exact: true }),
        ).toBeVisible();
        await expect(
            footer.getByRole('link', { name: HOME_LABEL }),
        ).toHaveCount(0);

        await page.goto('/login');
        await settle(page);
        const authLink = page
            .getByRole('banner')
            .getByRole('link', { name: HOME_LABEL, exact: true });
        await expect(authLink).toBeVisible();
        expect(new URL((await authLink.getAttribute('href'))!).pathname).toBe(
            '/',
        );
    });

    test('Kopfbereich: theme-color gesetzt, Manifest erst mit erzeugten Icons', async ({
        page,
    }) => {
        await page.goto('/');
        await expect(page.locator('meta[name="theme-color"]')).toHaveAttribute(
            'content',
            '#143D2D',
        );
        const iconsGenerated = existsSync(
            path.join(ROOT, 'public', 'icons', 'icon-192.png'),
        );
        await expect(page.locator('link[rel="manifest"]')).toHaveCount(
            iconsGenerated ? 1 : 0,
        );
        const manifest = await page.request.get('/site.webmanifest');
        expect(manifest.ok()).toBe(true);
        expect((await manifest.json()).name).toBe('PlantMel');
    });
});

// ---------------------------------------------------------------------------
// 2. BrandLogo in beiden Modi (serverseitig gerendert)
// ---------------------------------------------------------------------------

type Config = {
    logo: 'builtin' | 'custom';
    lightVariants: boolean;
    fullHasWordmark: boolean;
    version: string;
};

/** Ersetzt brand.config.json nur innerhalb dieses Test-Servers */
function brandConfigOverride(config: Config): Plugin {
    return {
        name: 'test-brand-config',
        enforce: 'pre',
        load(id) {
            if (id.split('?')[0].endsWith('brand.config.json')) {
                return JSON.stringify(config);
            }
        },
    };
}

async function brandServer(config: Config): Promise<ViteDevServer> {
    return createServer({
        configFile: false,
        root: ROOT,
        logLevel: 'error',
        appType: 'custom',
        server: { middlewareMode: true, hmr: false, ws: false },
        optimizeDeps: { noDiscovery: true, include: [] },
        resolve: { alias: { '@': path.join(ROOT, 'resources', 'js') } },
        plugins: [brandConfigOverride(config), vue()],
    });
}

async function renderLogo(
    server: ViteDevServer,
    props: Record<string, unknown>,
): Promise<string> {
    const mod = await server.ssrLoadModule(
        '/resources/js/components/brand/BrandLogo.vue',
    );
    return renderToString(
        createSSRApp({ render: () => h(mod.default, props) }),
    );
}

async function show(page: Page, html: string) {
    await page.setContent(`<!doctype html><html><body>${html}</body></html>`);
}

test.describe('BrandLogo – beide Modi', () => {
    test.describe.configure({ timeout: 120_000 });

    const base: Config = {
        logo: 'builtin',
        lightVariants: false,
        fullHasWordmark: true,
        version: '',
    };

    test('builtin: Signet + Text-Wortmarke, Link-Name, Töne und nur Signet', async ({
        page,
    }) => {
        const server = await brandServer(base);
        try {
            const link = await renderLogo(server, { href: '/' });
            expect(link).not.toContain('<img');
            expect(link).toContain('bg-forest text-matcha');
            expect(link).toContain('size-9');
            await show(page, link);
            await expect(
                page.getByRole('link', { name: HOME_LABEL, exact: true }),
            ).toHaveCount(1);

            const light = await renderLogo(server, { tone: 'light' });
            expect(light).toContain('bg-matcha text-forest');
            expect(light).toContain('text-on-forest');
            expect(light).not.toContain('Startseite');

            const mark = await renderLogo(server, {
                href: '/',
                variant: 'mark',
            });
            expect(mark).not.toContain('Plant<span');
            await show(page, mark);
            await expect(
                page.getByRole('link', { name: HOME_LABEL, exact: true }),
            ).toHaveCount(1);

            const large = await renderLogo(server, { size: 'lg' });
            expect(large).toContain('size-12');
        } finally {
            await server.close();
        }
    });

    test('custom: Dateien aus public/brand/, alt-Texte und Link-Name', async ({
        page,
    }) => {
        const server = await brandServer({
            ...base,
            logo: 'custom',
            lightVariants: true,
            version: '20261121',
        });
        try {
            const full = await renderLogo(server, { href: '/' });
            expect(full).toContain('src="/brand/logo-full.svg?v=20261121"');
            expect(full).toContain('alt="PlantMel"');
            expect(full).toContain('data-brand-mode="custom"');
            expect(full).not.toContain('Plant<span');
            await show(page, full);
            await expect(
                page.getByRole('link', { name: HOME_LABEL, exact: true }),
            ).toHaveCount(1);

            const light = await renderLogo(server, { tone: 'light' });
            expect(light).toContain(
                'src="/brand/logo-full-light.svg?v=20261121"',
            );
            expect(light).not.toContain('Startseite');
            await show(page, light);
            await expect(
                page.getByRole('img', { name: 'PlantMel' }),
            ).toHaveCount(1);

            const mark = await renderLogo(server, {
                href: '/',
                variant: 'mark',
                tone: 'light',
            });
            expect(mark).toContain(
                'src="/brand/logo-mark-light.svg?v=20261121"',
            );
            await show(page, mark);
            await expect(
                page.getByRole('link', { name: HOME_LABEL, exact: true }),
            ).toHaveCount(1);
        } finally {
            await server.close();
        }

        // Nur eine Bildmarke geliefert: Bild mit alt="" + Text-Wortmarke
        const markOnly = await brandServer({
            ...base,
            logo: 'custom',
            fullHasWordmark: false,
        });
        try {
            const html = await renderLogo(markOnly, { href: '/' });
            expect(html).toContain('src="/brand/logo-mark.svg"');
            expect(html).toContain('Plant<span');
            await show(page, html);
            // Vue-SSR schreibt ein leeres alt als blosses Attribut `alt`
            await expect(page.locator('img')).toHaveAttribute('alt', '');
            await expect(
                page.getByRole('link', { name: HOME_LABEL, exact: true }),
            ).toHaveCount(1);
            await expect(page.getByRole('img')).toHaveCount(0);
        } finally {
            await markOnly.close();
        }
    });
});

// ---------------------------------------------------------------------------
// 3. Icon-Pipeline
// ---------------------------------------------------------------------------

function pngSize(buf: Buffer): [number, number] {
    expect(buf.subarray(0, 8).toString('hex')).toBe('89504e470d0a1a0a');
    expect(buf.subarray(12, 16).toString('ascii')).toBe('IHDR');
    return [buf.readUInt32BE(16), buf.readUInt32BE(20)];
}

function icoEntries(buf: Buffer) {
    expect(buf.readUInt16LE(0)).toBe(0);
    expect(buf.readUInt16LE(2)).toBe(1);
    const count = buf.readUInt16LE(4);
    return Array.from({ length: count }, (_, i) => {
        const e = 6 + i * 16;
        const size = buf.readUInt32LE(e + 8);
        const offset = buf.readUInt32LE(e + 12);
        return {
            declared: buf.readUInt8(e) || 256,
            png: pngSize(buf.subarray(offset, offset + size)),
        };
    });
}

const hashOf = (file: string) =>
    existsSync(file)
        ? createHash('sha256').update(readFileSync(file)).digest('hex')
        : null;

test.describe('Icon-Pipeline (Platzhalter)', () => {
    test.describe.configure({ timeout: 180_000 });

    test('erzeugt alle Dateien in korrekter Größe, public/ bleibt unverändert', () => {
        const publicFiles = [
            'favicon.ico',
            'favicon.svg',
            'apple-touch-icon.png',
            'og-image.png',
            'icons/icon-192.png',
        ].map((f) => path.join(ROOT, 'public', f));
        const before = publicFiles.map(hashOf);

        const out = mkdtempSync(path.join(tmpdir(), 'plantmel-brand-'));
        try {
            const run = spawnSync(
                process.execPath,
                [SCRIPT, '--placeholder', '--out', out],
                { cwd: ROOT, encoding: 'utf8', timeout: 150_000 },
            );
            expect(run.status, run.stderr).toBe(0);

            const expected: Record<string, [number, number]> = {
                'apple-touch-icon.png': [180, 180],
                'icons/icon-192.png': [192, 192],
                'icons/icon-512.png': [512, 512],
                'icons/icon-512-maskable.png': [512, 512],
                'og-image.png': [1200, 630],
            };
            for (const [file, size] of Object.entries(expected)) {
                expect(
                    pngSize(readFileSync(path.join(out, file))),
                    file,
                ).toEqual(size);
            }

            const ico = icoEntries(readFileSync(path.join(out, 'favicon.ico')));
            expect(ico.map((e) => e.declared)).toEqual([16, 32, 48]);
            expect(ico.map((e) => e.png)).toEqual([
                [16, 16],
                [32, 32],
                [48, 48],
            ]);

            expect(readFileSync(path.join(out, 'favicon.svg'), 'utf8')).toBe(
                readFileSync(
                    path.join(
                        ROOT,
                        'public',
                        'brand',
                        'placeholder',
                        'logo-mark.svg',
                    ),
                    'utf8',
                ),
            );
        } finally {
            rmSync(out, { recursive: true, force: true });
        }

        expect(publicFiles.map(hashOf)).toEqual(before);
    });

    test('verweigert Platzhalter-Ausgabe nach public/ und --apply ohne eigene Marke', () => {
        const target = path.join(ROOT, 'public', 'brand-test-should-not-exist');
        const toPublic = spawnSync(
            process.execPath,
            [SCRIPT, '--placeholder', '--out', target],
            { cwd: ROOT, encoding: 'utf8' },
        );
        expect(toPublic.status).toBe(1);
        expect(toPublic.stderr).toContain('nicht nach public/');
        expect(existsSync(target)).toBe(false);

        if (!existsSync(path.join(ROOT, 'public', 'brand', 'logo-mark.svg'))) {
            const apply = spawnSync(process.execPath, [SCRIPT, '--apply'], {
                cwd: ROOT,
                encoding: 'utf8',
            });
            expect(apply.status).toBe(1);
            expect(apply.stderr).toContain('--apply braucht eine eigene Marke');
        }
    });
});

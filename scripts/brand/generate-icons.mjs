#!/usr/bin/env node
/**
 * PlantMel – Favicon-/Icon-Pipeline (Logo-Vorbereitung, docs/modernization/LOGO.md).
 *
 * Rastert die Bildmarke mit dem bereits installierten Playwright-Chromium
 * (keine zusätzliche Abhängigkeit) und schreibt:
 *
 *   favicon.ico                 16/32/48 px (PNG-Einträge), transparent
 *   favicon.svg                 Kopie der SVG-Bildmarke (nur bei SVG-Eingabe)
 *   apple-touch-icon.png        180 px, Hintergrund canvas #F2F5EA
 *   icons/icon-192.png          192 px, transparent
 *   icons/icon-512.png          512 px, transparent
 *   icons/icon-512-maskable.png 512 px, Hintergrund, Logo in der Safe Zone (80 %-Kreis)
 *   og-image.png                1200×630, Logo + Schriftzug auf Waldgrün
 *
 * Aufruf:
 *   npm run brand:icons                  Eigene Marke aus public/brand/ → public/
 *   npm run brand:apply                  wie oben + Schalter in brand.config.json auf "custom"
 *   node scripts/brand/generate-icons.mjs --placeholder --out <ordner>
 *
 * Optionen:
 *   --input <datei>   Bildmarke (SVG bevorzugt, PNG ≥ 1024 px möglich); Standard public/brand/logo-mark.svg
 *   --out <ordner>    Zielordner; Standard public/ (Platzhalter: test-results/brand-icons-preview)
 *   --placeholder     Platzhalter aus public/brand/placeholder/ verwenden (heutiges Signet)
 *   --apply           nach dem Erzeugen brand.config.json umschalten (nur mit eigener Marke)
 *
 * Sicherheitsregel: Mit dem Platzhalter wird nie nach public/ geschrieben, damit die
 * heute ausgelieferten Favicons unverändert bleiben.
 */
import { chromium } from '@playwright/test';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import {
    dirname,
    extname,
    isAbsolute,
    join,
    relative,
    resolve,
} from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');
const PUBLIC_DIR = join(ROOT, 'public');
const BRAND_DIR = join(PUBLIC_DIR, 'brand');
const PLACEHOLDER_DIR = join(BRAND_DIR, 'placeholder');
const CONFIG_FILE = join(
    ROOT,
    'resources',
    'js',
    'components',
    'brand',
    'brand.config.json',
);
const PREVIEW_DIR = join(ROOT, 'test-results', 'brand-icons-preview');

/** Farben aus resources/css/app.css (Gate C) */
export const COLORS = {
    forest: '#143D2D',
    forestSoft: '#1F5A41',
    matcha: '#C8E28A',
    canvas: '#F2F5EA',
    onForest: '#F2F5EA',
    onForestMuted: '#B9CFB6',
};

/** Erwartete Ausgabe: Datei → Größe (Breite × Höhe) */
export const OUTPUTS = {
    'apple-touch-icon.png': [180, 180],
    'icons/icon-192.png': [192, 192],
    'icons/icon-512.png': [512, 512],
    'icons/icon-512-maskable.png': [512, 512],
    'og-image.png': [1200, 630],
};
export const ICO_SIZES = [16, 32, 48];

function parseArgs(argv) {
    const args = { placeholder: false, apply: false, input: null, out: null };
    for (let i = 0; i < argv.length; i++) {
        const a = argv[i];
        if (a === '--placeholder') args.placeholder = true;
        else if (a === '--apply') args.apply = true;
        else if (a === '--input') args.input = argv[++i];
        else if (a === '--out') args.out = argv[++i];
        else if (a === '--help' || a === '-h') args.help = true;
        else throw new Error(`Unbekannte Option: ${a}`);
    }
    return args;
}

function dataUrl(file) {
    const ext = extname(file).toLowerCase();
    const mime =
        ext === '.svg'
            ? 'image/svg+xml'
            : ext === '.png'
              ? 'image/png'
              : ext === '.woff2'
                ? 'font/woff2'
                : null;
    if (!mime) throw new Error(`Nicht unterstütztes Format: ${file}`);
    return `data:${mime};base64,${readFileSync(file).toString('base64')}`;
}

function isInside(child, parent) {
    const rel = relative(parent, child);
    return rel === '' || (!rel.startsWith('..') && !isAbsolute(rel));
}

/** ICO-Datei mit PNG-Einträgen (seit Windows Vista von allen Browsern unterstützt) */
export function buildIco(pngs) {
    const header = Buffer.alloc(6);
    header.writeUInt16LE(0, 0);
    header.writeUInt16LE(1, 2);
    header.writeUInt16LE(pngs.length, 4);
    const entries = [];
    let offset = 6 + 16 * pngs.length;
    for (const { size, data } of pngs) {
        const e = Buffer.alloc(16);
        e.writeUInt8(size >= 256 ? 0 : size, 0);
        e.writeUInt8(size >= 256 ? 0 : size, 1);
        e.writeUInt8(0, 2); // Palette
        e.writeUInt8(0, 3); // reserviert
        e.writeUInt16LE(1, 4); // Farbebenen
        e.writeUInt16LE(32, 6); // Bit pro Pixel
        e.writeUInt32LE(data.length, 8);
        e.writeUInt32LE(offset, 12);
        offset += data.length;
        entries.push(e);
    }
    return Buffer.concat([header, ...entries, ...pngs.map((p) => p.data)]);
}

function iconHtml({ src, size, background, scale }) {
    const inner = Math.round(size * scale);
    return `<!doctype html><html><head><style>
html,body{margin:0;padding:0;background:transparent}
.c{width:${size}px;height:${size}px;display:flex;align-items:center;justify-content:center;background:${background ?? 'transparent'}}
img{width:${inner}px;height:${inner}px;object-fit:contain;display:block}
</style></head><body><div class="c"><img src="${src}" alt=""></div></body></html>`;
}

function ogHtml({ markSrc, fullSrc, fonts }) {
    const brand = fullSrc
        ? `<img class="full" src="${fullSrc}" alt="">`
        : `<img class="mark" src="${markSrc}" alt=""><span class="word">Plant<em>Mel</em></span>`;
    return `<!doctype html><html><head><style>
@font-face{font-family:'Fraunces';font-style:normal;font-weight:100 900;src:url(${fonts.normal}) format('woff2')}
@font-face{font-family:'Fraunces';font-style:italic;font-weight:100 900;src:url(${fonts.italic}) format('woff2')}
html,body{margin:0;padding:0}
.og{position:relative;width:1200px;height:630px;overflow:hidden;background:${COLORS.forest};color:${COLORS.onForest};font-family:'Fraunces',Georgia,serif;display:flex;flex-direction:column;justify-content:center;padding:0 110px;box-sizing:border-box}
.blob{position:absolute;border-radius:58% 42% 52% 48%/46% 54% 46% 54%}
.b1{width:620px;height:620px;right:-170px;top:-210px;background:${COLORS.forestSoft};opacity:.75}
.b2{width:300px;height:300px;right:120px;bottom:-150px;background:${COLORS.matcha};opacity:.9;border-radius:44% 56% 40% 60%/56% 44% 58% 42%}
.brand{position:relative;display:flex;align-items:center;gap:34px}
.mark{width:150px;height:150px;object-fit:contain}
.full{max-width:720px;max-height:220px;object-fit:contain}
.word{font-size:128px;line-height:1;font-weight:600;letter-spacing:-0.02em;font-variation-settings:'SOFT' 100,'WONK' 1}
.word em{font-style:italic;font-weight:500;color:${COLORS.matcha}}
.tag{position:relative;margin:44px 0 0;font-size:44px;line-height:1.25;max-width:780px;color:${COLORS.onForest};font-variation-settings:'SOFT' 60,'WONK' 0}
.tag em{font-style:italic;color:${COLORS.matcha}}
</style></head><body><div class="og">
<span class="blob b1"></span><span class="blob b2"></span>
<div class="brand">${brand}</div>
<p class="tag">Pflanzenbetonte Rezepte – <em>gesammelt</em> an einem Ort.</p>
</div></body></html>`;
}

async function render(page, html, width, height, { transparent }) {
    await page.setViewportSize({ width, height });
    await page.setContent(html, { waitUntil: 'load' });
    await page.evaluate(async () => {
        await document.fonts.ready;
        await Promise.all(
            [...document.images].map((img) =>
                img.decode().catch(() => {
                    throw new Error(
                        `Bild nicht ladbar: ${img.src.slice(0, 40)}`,
                    );
                }),
            ),
        );
    });
    return page.screenshot({
        type: 'png',
        omitBackground: transparent,
        clip: { x: 0, y: 0, width, height },
    });
}

/**
 * Erzeugt alle Dateien. Gibt die Liste der geschriebenen Pfade (relativ zu `out`) zurück.
 */
export async function generateIcons({ input, out, lightMark, fullLight }) {
    const isSvg = extname(input).toLowerCase() === '.svg';
    const src = dataUrl(input);
    mkdirSync(join(out, 'icons'), { recursive: true });

    const fontDir = join(
        ROOT,
        'node_modules',
        '@fontsource-variable',
        'fraunces',
        'files',
    );
    const fonts = {
        normal: dataUrl(join(fontDir, 'fraunces-latin-full-normal.woff2')),
        italic: dataUrl(join(fontDir, 'fraunces-latin-opsz-italic.woff2')),
    };

    const written = [];
    const write = (name, data) => {
        writeFileSync(join(out, name), data);
        written.push(name);
    };

    const browser = await chromium.launch();
    try {
        const page = await browser.newPage({ deviceScaleFactor: 1 });

        const icon = (size, opts = {}) =>
            render(
                page,
                iconHtml({
                    src,
                    size,
                    background: opts.background,
                    scale: opts.scale ?? 1,
                }),
                size,
                size,
                { transparent: !opts.background },
            );

        // favicon.ico (16/32/48), transparent
        const icoPngs = [];
        for (const size of ICO_SIZES) {
            icoPngs.push({ size, data: await icon(size) });
        }
        write('favicon.ico', buildIco(icoPngs));

        if (isSvg) write('favicon.svg', readFileSync(input));
        else
            console.warn(
                'Hinweis: Eingabe ist kein SVG – favicon.svg wird nicht erzeugt.',
            );

        // Apple: kein Transparenz-Support, deshalb Naturgrund; etwas Rand
        write(
            'apple-touch-icon.png',
            await icon(180, { background: COLORS.canvas, scale: 0.8 }),
        );
        write('icons/icon-192.png', await icon(192));
        write('icons/icon-512.png', await icon(512));
        // Maskable: Safe Zone ist ein Kreis mit 80 % Durchmesser; ein Quadrat
        // passt vollständig hinein bei 0,8 / √2 ≈ 56 %.
        write(
            'icons/icon-512-maskable.png',
            await icon(512, { background: COLORS.canvas, scale: 0.56 }),
        );

        write(
            'og-image.png',
            await render(
                page,
                ogHtml({
                    markSrc: lightMark ? dataUrl(lightMark) : src,
                    fullSrc: fullLight ? dataUrl(fullLight) : null,
                    fonts,
                }),
                1200,
                630,
                { transparent: false },
            ),
        );
    } finally {
        await browser.close();
    }
    return written;
}

function writeConfig({ hasFull, hasFullLight, hasMarkLight }) {
    const now = new Date();
    const version = now.toISOString().slice(0, 10).replaceAll('-', '');
    const config = {
        logo: 'custom',
        lightVariants: hasMarkLight && (!hasFull || hasFullLight),
        fullHasWordmark: hasFull,
        version,
    };
    writeFileSync(CONFIG_FILE, `${JSON.stringify(config, null, 4)}\n`);
    return config;
}

async function main() {
    const args = parseArgs(process.argv.slice(2));
    if (args.help) {
        console.log(
            readFileSync(fileURLToPath(import.meta.url), 'utf8')
                .split('*/')[0]
                .replace(/^#!.*\n\/\*\*?/, ''),
        );
        return;
    }

    const customMark = join(BRAND_DIR, 'logo-mark.svg');
    let placeholder = args.placeholder;
    let input = args.input ? resolve(args.input) : customMark;
    if (!args.input && !placeholder && !existsSync(customMark)) {
        console.warn(
            'Keine eigene Marke unter public/brand/logo-mark.svg – verwende den Platzhalter.',
        );
        placeholder = true;
    }
    if (placeholder) input = join(PLACEHOLDER_DIR, 'logo-mark.svg');
    if (!existsSync(input)) throw new Error(`Eingabe fehlt: ${input}`);

    const out = resolve(args.out ?? (placeholder ? PREVIEW_DIR : PUBLIC_DIR));
    if (placeholder && isInside(out, PUBLIC_DIR)) {
        throw new Error(
            'Mit dem Platzhalter wird nicht nach public/ geschrieben (heutige Favicons bleiben). Bitte --out <ordner> außerhalb von public/ angeben.',
        );
    }
    if (args.apply && placeholder) {
        throw new Error(
            '--apply braucht eine eigene Marke unter public/brand/logo-mark.svg.',
        );
    }

    const brandDir = dirname(input);
    const markLight = join(brandDir, 'logo-mark-light.svg');
    const fullLight = join(brandDir, 'logo-full-light.svg');
    const written = await generateIcons({
        input,
        out,
        lightMark: existsSync(markLight) ? markLight : null,
        fullLight: existsSync(fullLight) ? fullLight : null,
    });

    console.log(`Icons erzeugt in ${relative(ROOT, out) || '.'}:`);
    for (const f of written) console.log(`  ${f}`);

    if (args.apply) {
        const config = writeConfig({
            hasFull: existsSync(join(BRAND_DIR, 'logo-full.svg')),
            hasFullLight: existsSync(fullLight),
            hasMarkLight: existsSync(markLight),
        });
        console.log(
            `brand.config.json umgeschaltet: ${JSON.stringify(config)}\nJetzt "npm run build" ausführen.`,
        );
    }
}

if (
    process.argv[1] &&
    resolve(process.argv[1]) === fileURLToPath(import.meta.url)
) {
    main().catch((err) => {
        console.error(err instanceof Error ? err.message : err);
        process.exit(1);
    });
}

import fs from 'node:fs';
import path from 'node:path';
import { ROOT } from './support/env';
import { RAW_DIR } from './support/raw';

const OUT_DIR = path.join(ROOT, 'docs', 'modernization', 'baseline', 'browser');

/**
 * Fuehrt die Einzel-Messwerte (eine JSON-Datei je Seite x Viewport bzw. Defekt-Test)
 * zu stabil sortierten Rohdaten-Dateien im Dokumentationsordner zusammen.
 * Nur wenn E2E_WRITE_RAW=1 gesetzt ist, damit normale Laeufe das Repo nicht veraendern.
 */
export default async function globalTeardown(): Promise<void> {
    if (process.env.E2E_WRITE_RAW !== '1' || !fs.existsSync(RAW_DIR)) return;
    fs.mkdirSync(OUT_DIR, { recursive: true });

    const groups: Record<string, unknown[]> = {};
    for (const file of fs.readdirSync(RAW_DIR).sort()) {
        if (!file.endsWith('.json')) continue;
        const entry = JSON.parse(fs.readFileSync(path.join(RAW_DIR, file), 'utf8'));
        (groups[entry.kind] ??= []).push(entry);
    }
    for (const [kind, entries] of Object.entries(groups)) {
        fs.writeFileSync(path.join(OUT_DIR, `${kind}-results.json`), JSON.stringify(entries, null, 2) + '\n');
    }
}

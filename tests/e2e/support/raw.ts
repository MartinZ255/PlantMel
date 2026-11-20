import fs from 'node:fs';
import path from 'node:path';
import { ROOT } from './env';

export const RAW_DIR = path.join(ROOT, 'test-results', 'e2e-raw');

/** Schreibt einen Messdatensatz; global-teardown fasst sie zusammen (nur bei E2E_WRITE_RAW=1). */
export function writeRaw(
    kind: 'baseline' | 'defects' | 'regression',
    name: string,
    data: Record<string, unknown>,
): void {
    fs.mkdirSync(RAW_DIR, { recursive: true });
    const safe = name.replace(/[^a-zA-Z0-9_-]+/g, '_');
    fs.writeFileSync(
        path.join(RAW_DIR, `${kind}__${safe}.json`),
        JSON.stringify({ kind, name, ...data }, null, 2),
    );
}

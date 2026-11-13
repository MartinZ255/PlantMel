import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { childEnv, DB_PATH, ROOT } from './support/env';

/**
 * Bereitet die E2E-Instanz vor:
 * 1. Sicherheitspruefungen (kein Config-Cache, DB-Pfad ist database/e2e.sqlite)
 * 2. database/e2e.sqlite loeschen und leer neu anlegen (kein migrate:fresh / db:wipe)
 * 3. php artisan migrate --force und db:seed --class=E2eSeeder --force gegen diese Datei
 * 4. public/storage-Link anlegen, falls er fehlt
 * 5. Assets bauen, falls public/build/manifest.json fehlt (oder E2E_REBUILD=1)
 *
 * Hinweis: Playwright startet den webServer VOR diesem Setup. Das ist unkritisch, weil der
 * eingebaute PHP-Server die SQLite-Datei nur waehrend eines Requests oeffnet und der
 * Bereitschafts-Check (`/up`) keine Datenbank benutzt.
 */
function run(cmd: string, args: string[], label: string): void {
    const result = spawnSync(cmd, args, {
        cwd: ROOT,
        env: childEnv(),
        stdio: 'inherit',
        shell: process.platform === 'win32' && cmd === 'npm',
    });
    if (result.status !== 0) {
        throw new Error(`[e2e global-setup] ${label} fehlgeschlagen (Exit ${result.status ?? result.error?.message})`);
    }
}

export default async function globalSetup(): Promise<void> {
    if (path.basename(DB_PATH) !== 'e2e.sqlite' || path.basename(path.dirname(DB_PATH)) !== 'database') {
        throw new Error(`[e2e global-setup] unerwarteter DB-Pfad: ${DB_PATH}`);
    }

    // Ein gecachter Config-Stand wuerde die Umgebungsvariablen ignorieren.
    const cachedConfig = path.join(ROOT, 'bootstrap', 'cache', 'config.php');
    if (fs.existsSync(cachedConfig)) {
        throw new Error(
            '[e2e global-setup] bootstrap/cache/config.php existiert (config:cache). ' +
                'Die E2E-Umgebung waere dann wirkungslos. Bitte bewusst `php artisan config:clear` ausfuehren und erneut starten.',
        );
    }

    for (const suffix of ['', '-journal', '-wal', '-shm']) {
        fs.rmSync(DB_PATH + suffix, { force: true });
    }
    fs.writeFileSync(DB_PATH, '');

    run('php', ['artisan', 'migrate', '--force', '--no-interaction'], 'migrate');
    run('php', ['artisan', 'db:seed', '--class=E2eSeeder', '--force', '--no-interaction'], 'db:seed E2eSeeder');

    const storageLink = path.join(ROOT, 'public', 'storage');
    if (!fs.existsSync(storageLink)) {
        run('php', ['artisan', 'storage:link', '--no-interaction'], 'storage:link');
    }

    const manifest = path.join(ROOT, 'public', 'build', 'manifest.json');
    if (process.env.E2E_REBUILD === '1' || !fs.existsSync(manifest)) {
        run('npm', ['run', 'build'], 'npm run build');
    }
}

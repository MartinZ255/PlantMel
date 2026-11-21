import path from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * Zentrale Konfiguration der E2E-Testinstanz.
 *
 * Es wird KEINE `.env` benoetigt oder gelesen. Alle relevanten Werte werden als
 * Prozess-Umgebungsvariablen an `php`/`npm` uebergeben. Laravel (vlucas/phpdotenv,
 * immutable) ueberschreibt bereits gesetzte Umgebungsvariablen nicht - auch dann nicht,
 * wenn im Projektverzeichnis eine `.env` existiert. Nicht hier gesetzte Variablen koennten
 * aus einer vorhandenen `.env` kommen; deshalb ist die Liste bewusst vollstaendig fuer
 * alles, was Datenbank, Mail, Queue, Session, Cache und externe Dienste betrifft.
 */
export const ROOT = path.resolve(
    path.dirname(fileURLToPath(import.meta.url)),
    '..',
    '..',
    '..',
);

export const PORT = Number(process.env.E2E_PORT ?? 8123);
export const HOST = '127.0.0.1';
export const BASE_URL = `http://${HOST}:${PORT}`;

export const DB_PATH = path.join(ROOT, 'database', 'e2e.sqlite');

/**
 * Fester, oeffentlich dokumentierter NICHT geheimer Schluessel nur fuer die lokale
 * E2E-Instanz (erzeugt mit `php -r 'echo base64_encode(random_bytes(32));'`).
 * Niemals fuer andere Umgebungen verwenden.
 */
export const E2E_APP_KEY =
    'base64:evN0XK+wwv/q3ZFyU3Ekx8Hi3OQtaVvoQNy735gHvnQ=';

export const E2E_ENV: Record<string, string> = {
    APP_NAME: 'Laravel', // wie .env.example; bewusst nicht "PlantMel", damit der Ist-Zustand gemessen wird
    APP_ENV: 'local',
    // Slice 3: false, damit Fehlerseiten als Inertia-Seite `Error` erscheinen (lokal mit
    // APP_DEBUG=true liefert bootstrap/app.php bewusst die Debug-Seite).
    APP_DEBUG: 'false',
    APP_KEY: E2E_APP_KEY,
    APP_URL: BASE_URL,
    APP_LOCALE: 'de', // Slice 3 (F-42): deutsche Oberflaeche und Validierungstexte
    APP_FALLBACK_LOCALE: 'en',
    APP_MAINTENANCE_DRIVER: 'file',
    BCRYPT_ROUNDS: '4',
    LOG_CHANNEL: 'stderr', // nicht in storage/logs/laravel.log schreiben
    LOG_LEVEL: 'debug',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: DB_PATH,
    DB_URL: '',
    SESSION_DRIVER: 'file',
    SESSION_LIFETIME: '120',
    SESSION_ENCRYPT: 'false',
    SESSION_DOMAIN: '',
    SESSION_SECURE_COOKIE: 'false',
    CACHE_STORE: 'file',
    QUEUE_CONNECTION: 'database', // ohne Worker: Import-Jobs werden nie ausgefuehrt
    BROADCAST_CONNECTION: 'log',
    FILESYSTEM_DISK: 'local',
    MAIL_MAILER: 'array', // keine echten E-Mails
    GEMINI_API_KEY: '', // keine KI-Aufrufe
    YTDLP_PATH: 'e2e-ytdlp-disabled', // nicht existierendes Programm
    // E17: Dummy-Werte, damit der Google-Knopf erscheint. Nicht geheim, bei
    // Google ungueltig; die Tests verfolgen die Weiterleitung zu Google nie.
    GOOGLE_CLIENT_ID: 'e2e-google-client-id.invalid',
    GOOGLE_CLIENT_SECRET: 'e2e-google-client-secret-not-real',
    GOOGLE_REDIRECT_URI: `${BASE_URL}/auth/google/callback`,
    VITE_APP_NAME: 'Laravel',
};

/** Umgebung fuer Kindprozesse: aktuelle Prozessumgebung + E2E-Werte (E2E gewinnt). */
export function childEnv(): NodeJS.ProcessEnv {
    return { ...process.env, ...E2E_ENV };
}

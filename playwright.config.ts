import { defineConfig, devices } from '@playwright/test';
import { BASE_URL, E2E_ENV, HOST, PORT } from './tests/e2e/support/env';

/**
 * Browser-Baseline PlantMel (Slice 0).
 *
 * - Keine `.env` noetig: die Testinstanz bekommt alles ueber Prozess-Umgebungsvariablen
 *   (tests/e2e/support/env.ts).
 * - Eigene SQLite-Datei database/e2e.sqlite, wird in tests/e2e/global-setup.ts neu angelegt.
 * - Eingebauter PHP-Server direkt (nicht `artisan serve`), damit die Umgebungsvariablen
 *   vollstaendig ankommen. Er ist single-threaded => workers: 1.
 */
const chromium = devices['Desktop Chrome'];

const viewports = [
    { name: 'w320', viewport: { width: 320, height: 640 }, isMobile: true, hasTouch: true },
    { name: 'm390', viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true },
    { name: 't768', viewport: { width: 768, height: 1024 }, isMobile: false, hasTouch: false },
    { name: 'd1024', viewport: { width: 1024, height: 768 }, isMobile: false, hasTouch: false },
    { name: 'd1440', viewport: { width: 1440, height: 900 }, isMobile: false, hasTouch: false },
] as const;

export default defineConfig({
    testDir: './tests/e2e',
    globalSetup: './tests/e2e/global-setup.ts',
    globalTeardown: './tests/e2e/global-teardown.ts',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    forbidOnly: !!process.env.CI,
    timeout: 60_000,
    expect: {
        timeout: 10_000,
        toHaveScreenshot: {
            animations: 'disabled',
            caret: 'hide',
            scale: 'css',
            maxDiffPixelRatio: 0.001,
        },
    },
    outputDir: './test-results',
    reporter: [['list'], ['html', { open: 'never', outputFolder: 'playwright-report' }], ['json', { outputFile: 'test-results/results.json' }]],
    use: {
        baseURL: BASE_URL,
        locale: 'de-DE',
        timezoneId: 'Europe/Berlin',
        colorScheme: 'light',
        reducedMotion: 'reduce',
        serviceWorkers: 'block',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'setup',
            testMatch: /auth\.setup\.ts/,
            use: { ...chromium, viewport: { width: 1440, height: 900 } },
        },
        ...viewports.map((v) => ({
            name: v.name,
            testMatch: /baseline\.spec\.ts/,
            dependencies: ['setup'],
            use: {
                ...chromium,
                viewport: v.viewport,
                isMobile: v.isMobile,
                hasTouch: v.hasTouch,
                deviceScaleFactor: 1,
            },
        })),
        {
            name: 'defects',
            testMatch: /known-defects\.spec\.ts/,
            dependencies: ['setup'],
            use: { ...chromium, viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 },
        },
        {
            // behobene Befunde (aus known-defects.spec.ts verschoben, ab Slice 1a)
            name: 'regression',
            testMatch: /regression.*\.spec\.ts/,
            dependencies: ['setup'],
            use: { ...chromium, viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 },
        },
    ],
    webServer: {
        // server.php erwartet public/ als Arbeitsverzeichnis (wie `artisan serve`).
        command: `php -S ${HOST}:${PORT} ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`,
        cwd: 'public',
        url: `${BASE_URL}/up`,
        reuseExistingServer: false,
        timeout: 60_000,
        env: E2E_ENV,
        stdout: 'ignore',
        stderr: 'ignore', // php -S protokolliert jeden Request auf stderr
    },
});

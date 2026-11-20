# PlantMel – Architektur-Inventar

Stand: 2026-11-13 · Branch `improvement/full-website-overhaul` · Commit `c7227e4`

Diese Bestandsaufnahme wurde rein lesend erstellt. Es wurden keine Abhängigkeiten installiert, keine Builds oder Tests ausgeführt und keine Secret-Werte gelesen (aus `.env.example` nur Schlüsselnamen und unkritische Treiber-Defaults).

## 1. Überblick

PlantMel ist ein Laravel-Monolith mit Vue-Frontend, verbunden über Inertia.js. Es gibt kein getrenntes Frontend- und Backend-Projekt: Laravel liefert Routing, Controller und Daten, Vue rendert die Seiten. Grundlage ist das offizielle `laravel/vue-starter-kit` (so steht es noch in `composer.json` unter `name`).

Fachlich ist es eine Rezeptverwaltung: Rezepte, Zutaten und Alternativzutaten, Kategorien, Bewertungsdimensionen, Kommentare, Sammlungen sowie ein Rezeptimport aus TikTok-Videos.

| Bereich | Technologie |
|---|---|
| Backend | PHP `^8.2`, Laravel `^12.0` |
| Frontend | Vue `^3.5` mit TypeScript `^5.2`, Inertia.js v2 (`@inertiajs/vue3`) |
| Styling | Tailwind CSS v4 (über `@tailwindcss/vite`), `tw-animate-css` |
| UI-Bausteine | `reka-ui`, `lucide-vue-next`, `class-variance-authority`, `clsx`, `tailwind-merge` |
| Build | Vite `^7` mit `laravel-vite-plugin`, `@vitejs/plugin-vue`, `@laravel/vite-plugin-wayfinder` |
| Authentifizierung | Laravel Fortify (Registrierung, Passwort-Reset, E-Mail-Verifizierung, 2FA) |
| Routen im Frontend | Ziggy (`tightenco/ziggy`, `ziggy-js`) und Laravel Wayfinder |
| Datenbank | SQLite als Standard |
| Paketmanager | Composer (PHP) und npm (JavaScript) |
| Tests | PHPUnit `^11.5` |
| CI | GitHub Actions (`lint.yml`, `tests.yml`) |

## 2. Programmiersprachen

Zeilen in versionierten Dateien:

| Sprache | Dateien | Zeilen |
|---|---|---|
| PHP | 91 | ca. 6.080 |
| Vue (Single File Components, `<script setup lang="ts">`) | 35 | ca. 5.430 |
| CSS | 2 | ca. 3.420 |
| TypeScript | 9 | ca. 390 |

Dazu kommt ein Blade-Template (`resources/views/app.blade.php`) als HTML-Hülle für Inertia.

## 3. Backend

Einstiegspunkte sind `public/index.php` (HTTP) und `artisan` (CLI). Die Anwendung wird in `bootstrap/app.php` konfiguriert.

**Routen**

- `routes/web.php` – Seitenrouten. Öffentlich: Landing, Rezeptliste, Rezeptdetail, Zutatensuche. Mit `auth` + `verified`: Home, eigene Zutaten, Sammlungen. Mit `auth` + `is_host`: Anlegen, Bearbeiten und Löschen von Rezepten, Kategorien, Zutaten und Alternativen sowie der TikTok-Import.
- `routes/api.php` – wenige JSON-Endpunkte (`/api/categories`, `/api/rating-dimensions`, Rezept-Schreibzugriffe für Hosts).
- `routes/settings.php` – Profil, Passwort, 2FA, Darstellung.
- `routes/console.php` – nur der Standardbefehl `inspire`, keine geplanten Aufgaben.
- Health-Check unter `/up`.

**Middleware** (`app/Http/Middleware`)

- `EnsureUserIsHost` (Alias `is_host`) – prüft die Spalte `users.is_host`, sonst 403.
- `HandleInertiaRequests`, `HandleAppearance` – Inertia-Props und Hell/Dunkel-Cookie.

**Controller** (`app/Http/Controllers`): `Recipe`, `RecipeImport`, `Category`, `Collection`, `Ingredient`, `IngredientAlternative`, `RatingDimension` sowie `Settings/Profile`, `Settings/Password`, `Settings/TwoFactorAuthentication`.

**Modelle** (`app/Models`, 12 Stück): `User`, `Recipe`, `RecipeImage`, `RecipeIngredient`, `RecipeRating`, `RecipeImport`, `Ingredient`, `IngredientAlternative`, `Category`, `RatingDimension`, `Comment`, `Collection`.

**Hintergrundverarbeitung**

- `app/Jobs/ImportTikTokRecipe.php` läuft über die Laravel-Queue.
- `app/Services/TikTokRecipeImporter.php` ruft das externe Programm `yt-dlp` als Prozess auf (Metadaten, bei Bedarf Video-Download) und sendet Text bzw. Video an die Google-Gemini-API (`generativelanguage.googleapis.com`).

Der Import funktioniert deshalb nur, wenn ein Queue-Worker läuft, `yt-dlp` auf dem Server installiert ist und ein Gemini-Schlüssel konfiguriert ist.

## 4. Frontend

- Einstieg: `resources/js/app.ts` (Client) und `resources/js/ssr.ts` (optionales Server-Side-Rendering).
- Seiten liegen unter `resources/js/pages/**` und werden von Inertia per Namen aufgelöst: `Landing`, `Home`, `IngredientSearch`, `MyIngredients`, `auth/*`, `recipes/*`, `collections/*`, `settings/*`.
- Komponenten: `components/layout`, `components/recipes`, `components/widgets`.
- Composables: `useAppearance`, `useInitials`, `useTwoFactorAuth`.
- Styles: `resources/css/app.css` und `resources/css/rezeptly/app.css`.
- Generierter Code, nicht versioniert: `resources/js/actions`, `resources/js/routes`, `resources/js/wayfinder` (Wayfinder erzeugt diese beim Vite-Lauf).

## 5. Datenbank

- Standardverbindung ist SQLite (`config/database.php`, Default `sqlite`; Datei `database/database.sqlite`, nicht versioniert). MySQL, MariaDB, PostgreSQL und SQL Server sind als Laravel-Standardverbindungen vorkonfiguriert, aber nicht aktiv.
- Sessions, Cache und Queue nutzen laut `.env.example` ebenfalls die Datenbank (`SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` = `database`).
- 17 Migrationen in `database/migrations`: Users (inkl. `is_host` und 2FA-Spalten), Cache, Jobs, Recipes, Recipe-Images, Ingredients, Recipe-Ingredients, Ingredient-Alternatives, Categories, Recipe-Categories, Rating-Dimensions, Recipe-Ratings, Comments, Collections, Collection-Recipes, Recipe-Imports.
- Seeder: `CategorySeeder`, `IngredientSeeder`, `RatingDimensionSeeder`, aufgerufen über `DatabaseSeeder`. Factory: nur `UserFactory`.
- Hochgeladene Rezeptbilder liegen auf dem `public`-Disk (`storage/app/public`); dafür ist der Symlink aus `php artisan storage:link` nötig.
- Das npm-Paket `sqlite3` steht in `package.json` unter `dependencies`; im Frontend-Code ist dafür kein Verwendungszweck erkennbar.

## 6. Paketmanager, Build- und Startbefehle

Lockfiles sind `composer.lock` und `package-lock.json`. Es gibt kein Yarn-, pnpm- oder Bun-Lockfile.

| Zweck | Befehl |
|---|---|
| Ersteinrichtung (Install, `.env`, Key, Migration, Build) | `composer setup` |
| Entwicklung (Server, Queue-Listener und Vite parallel) | `composer dev` |
| Entwicklung mit SSR | `composer dev:ssr` |
| Nur Vite-Dev-Server | `npm run dev` |
| Produktions-Build nach `public/build` | `npm run build` |
| Build inkl. SSR-Bundle nach `bootstrap/ssr` | `npm run build:ssr` |
| Tests | `composer test` (entspricht `php artisan config:clear` + `php artisan test`) |
| PHP-Codestil | `vendor/bin/pint` |
| Frontend formatieren / prüfen | `npm run format` / `npm run format:check` |
| Frontend linten (mit Auto-Fix) | `npm run lint` |
| Migrationen und Seeder | `php artisan migrate`, `php artisan db:seed` |
| Queue-Worker für den TikTok-Import | `php artisan queue:work` (in `composer dev` als `queue:listen` enthalten) |

**Lokaler Zustand dieser Arbeitskopie:** `vendor/`, `node_modules/`, `.env` und `database/database.sqlite` fehlen. Die Anwendung ist so nicht startbar, bevor `composer setup` gelaufen ist. Installiert sind PHP 8.4.0 (Herd) und Node 22.19.0; `composer` liegt in der Git-Bash nicht im Pfad. `yt-dlp` und `ffmpeg` sind über WinGet vorhanden.

## 7. Tests

- Framework: PHPUnit 11, konfiguriert in `phpunit.xml` mit den Suites `Unit` und `Feature`. Die Testumgebung nutzt SQLite im Arbeitsspeicher, Queue `sync`, Cache und Session `array`.
- Bestand: 13 Testdateien mit 41 Testmethoden. Sie decken Authentifizierung (Login, Registrierung, Passwort-Reset, E-Mail-Verifizierung, 2FA) und Einstellungen (Profil, Passwort, 2FA) ab und stammen aus dem Starter-Kit.
- Es gibt keine Tests für die eigene Fachlogik (Rezepte, Zutaten, Sammlungen, Kategorien, TikTok-Import, Host-Berechtigung).
- Es gibt keine Frontend-Tests (kein Vitest, Jest, Playwright oder Cypress) und kein `vue-tsc`-Skript für die Typprüfung, obwohl `vue-tsc` installiert wird.

**Voraussichtlich fehlschlagende Tests** (aus dem Code abgeleitet, nicht ausgeführt):

- `tests/Feature/DashboardTest.php` verwendet `route('dashboard')`. Diese Route existiert nicht mehr; die Seite heißt jetzt `home`. Weitere Tests nutzen `dashboard` als Weiterleitungsziel (insgesamt 8 Verwendungen).
- `tests/Feature/ExampleTest.php` erwartet Status 200 für `route('home')` ohne Anmeldung. Die Route liegt hinter `auth` + `verified` und leitet Gäste um.

## 8. Konfigurationsdateien

| Datei | Inhalt |
|---|---|
| `composer.json`, `composer.lock` | PHP-Abhängigkeiten und Composer-Skripte |
| `package.json`, `package-lock.json` | JS-Abhängigkeiten und npm-Skripte |
| `vite.config.ts` | Vite mit Laravel-, Tailwind-, Wayfinder- und Vue-Plugin; Einstieg `resources/js/app.ts`, SSR `resources/js/ssr.ts` |
| `tsconfig.json` | TypeScript-Einstellungen |
| `eslint.config.js`, `.prettierrc`, `.prettierignore`, `.editorconfig` | Linting und Formatierung |
| `components.json` | shadcn-vue-Konfiguration |
| `phpunit.xml` | Testkonfiguration |
| `.env.example` | Vorlage für Umgebungsvariablen |
| `config/*.php` | `app`, `auth`, `cache`, `database`, `filesystems`, `fortify`, `inertia`, `logging`, `mail`, `queue`, `services`, `session` |
| `bootstrap/app.php`, `bootstrap/providers.php` | Routing, Middleware, Service-Provider |
| `public/.htaccess` | Apache-Rewrite auf `index.php` |
| `.gitignore`, `.gitattributes` | Git-Einstellungen |

**Umgebungsvariablen**

`.env.example` enthält die Laravel-Standardgruppen: `APP_*`, `LOG_*`, `DB_*`, `SESSION_*`, `CACHE_*`, `QUEUE_CONNECTION`, `REDIS_*`, `MAIL_*`, `AWS_*`, `VITE_APP_NAME`.

`config/services.php` liest zusätzlich drei Variablen, die in `.env.example` fehlen:

- `GEMINI_API_KEY` – Secret, für den TikTok-Import zwingend
- `GEMINI_MODEL` – Default `gemini-2.5-flash`
- `YTDLP_PATH` – Default `yt-dlp`

Eine `.env` existiert lokal nicht und ist per `.gitignore` von der Versionierung ausgeschlossen, ebenso `.env.backup` und `.env.production`.

## 9. Deployment

Im Repository gibt es keine Deployment-Artefakte:

- kein `Dockerfile`, kein `docker-compose.yml` (Laravel Sail ist als Dev-Abhängigkeit vorhanden, aber nicht eingerichtet)
- kein `Procfile`, `vercel.json`, `netlify.toml`, `fly.toml`, `render.yaml`, keine Forge-, Envoy- oder Deployer-Skripte
- keine Shell- oder PowerShell-Deploy-Skripte
- kein GitHub-Actions-Workflow mit Deploy-Schritt
- keine lokalen Git-Hooks (nur die `.sample`-Dateien)

`public/.htaccess` deutet auf klassisches Hosting mit Apache hin, ist aber Laravel-Standard und kein Beleg für ein bestimmtes Ziel.

Ein produktiver Betrieb bräuchte mindestens: `composer install --no-dev`, `npm ci && npm run build`, `php artisan migrate --force`, `php artisan storage:link`, einen dauerhaft laufenden Queue-Worker, `yt-dlp` auf dem Server und die Gemini-Variablen.

## 10. Git-Status

| Punkt | Zustand |
|---|---|
| Aktueller Branch | `improvement/full-website-overhaul` |
| Arbeitsverzeichnis | sauber, keine ignorierten Dateien vorhanden (vor Anlage dieser Datei) |
| Remote | `origin` → `https://github.com/MartinZ255/PlantMel.git` |
| Branches auf dem Remote | nur `master` |
| Upstream des aktuellen Branches | keiner gesetzt, der Branch existiert nur lokal |
| Verhältnis zu `origin/master` | identisch (`c7227e4`, 0 Commits voraus, 0 zurück) |
| Historie | 14 Commits, 2025-11-21 bis 2026-07-09 |
| Stashes | keine |

Diese Datei (`docs/architecture-inventory.md`) ist neu und nicht committet.

## 11. Kann ein Push ein Deployment auslösen?

**Aus dem Repository heraus: nein.**

- Es gibt keinen Deploy-Workflow.
- Die beiden vorhandenen Workflows (`lint.yml`, `tests.yml`) reagieren nur auf Pushes und Pull Requests gegen `develop` und `main`. Das Repository verwendet `master` und einen Feature-Branch. Ein Push auf `master` oder `improvement/full-website-overhaul` startet deshalb nicht einmal die CI.
- `lint.yml` hat zwar `contents: write`, der Auto-Commit-Schritt ist aber auskommentiert.

**Nicht prüfbar** war die GitHub-Seite: Webhooks, verbundene Dienste (z. B. Laravel Forge, Ploi, Vercel, Render), GitHub Pages und Environments. Die GitHub-CLI ist auf diesem Rechner nicht installiert, und diese Einstellungen stehen nicht im Repository. Ein dort eingerichteter Webhook könnte bei einem Push auf `master` ein Deployment starten. Nachsehen lässt sich das unter *Settings → Webhooks*, *Settings → Environments* und *Settings → Pages* im GitHub-Repository.

Ein erster Push des Feature-Branches (`git push -u origin improvement/full-website-overhaul`) legt nur einen neuen Remote-Branch an.

## 12. Auffälligkeiten

1. **CI läuft nie.** Die Workflow-Trigger (`develop`, `main`) passen nicht zum Branch `master`.
2. **Tests passen nicht mehr zu den Routen.** `dashboard` existiert nicht, `home` ist geschützt (siehe Abschnitt 7).
3. **Fehlende Inertia-Seiten.** `routes/settings.php` rendert `settings/Appearance`, `TwoFactorAuthenticationController` rendert `settings/TwoFactor`. Unter `resources/js/pages/settings` gibt es nur `Index.vue`, `Password.vue` und `Profile.vue`. Die Routen `/settings/appearance` und `/settings/two-factor` schlagen dadurch im Browser fehl.
4. **`.env.example` ist unvollständig.** `GEMINI_API_KEY`, `GEMINI_MODEL` und `YTDLP_PATH` fehlen.
5. **Verweise auf nicht vorhandene Dateien.** `components.json` und `eslint.config.js` nennen `resources/js/components/ui` und `tailwind.config.js`; beides existiert nicht.
6. **Keine README.** Einrichtung und Betrieb sind nirgends beschrieben.
7. **Starter-Kit-Reste.** `composer.json` trägt noch Name und Beschreibung des Starter-Kits; `fortify.home` zeigt auf `/` statt auf `/home`.
8. **Systemabhängigkeit `yt-dlp`.** Sie ist in keinem Manifest erfasst und muss auf jedem Zielsystem separat installiert werden.

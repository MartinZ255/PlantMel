# Abschlussbericht Slice 0 – Testumgebung und Baseline

Stand: 2026-11-13 · Branch `chore/slice-0-test-baseline` (lokal, kein Push) · Ausgangspunkt `c7227e4`

## 1. Ergebnis

Slice 0 ist lokal vollständig umgesetzt. Es gibt jetzt eine grüne reguläre Testsuite, eine getrennte Suite reproduzierbarer bekannter Defekte, eine Browser-Baseline mit Screenshots und korrigierte CI-Workflows.

| Messung | Ergebnis | Exit |
|---|---|---|
| Bestehende Suite, unverändert, ohne Build | 18 bestanden, 23 fehlgeschlagen | 2 |
| Bestehende Suite, unverändert, mit Build | 27 bestanden, 14 fehlgeschlagen | 2 |
| Reguläre Suite nach Slice 0 (`php artisan test`) | **334 bestanden**, 1892 Assertions | 0 |
| Bekannte Defekte (`--testsuite=KnownDefects`) | **36 fehlgeschlagen** (beabsichtigt), 19 Befunde | 1 |
| Browser (`npx playwright test`) | **143 bestanden**: 121 regulär, 22 erwartete Defekte (`test.fail()`) | 0 |

**Am Anwendungscode geändert:** nur `use HasFactory` (mit Import und PHPDoc) in 11 Modellen, je 4 Zeilen. Controller, Routen, Vue, CSS, Migrationen und Konfiguration sind unverändert (`git diff c7227e4 -- app routes resources config database/migrations bootstrap public`).

**Nicht belegt:** ein CI-Lauf auf GitHub. Die Workflows sind syntaktisch geprüft, liefen aber noch nie, weil nichts gepusht wurde.

## 2. Modelle und Arbeitsweise

- Lead: `claude-fable-5-2`. Spezialisten mit dem Alias `opus` gestartet; zwei Agenten haben in ihrem Bericht selbst Opus 5.5 (`claude-opus-5-5`) als laufendes Modell angegeben und ihre Commits entsprechend gekennzeichnet. Für die übrigen ist die Modell-ID nicht gesondert bestätigt.
- Sieben Agenten in isolierten Git-Worktrees mit exklusiver Dateizuständigkeit: Factories/TestCase, CI, Browser-Baseline, Rezept-Tests, Autorisierung/Sicherheit, Sammlungen/Stammdaten/Import. Der Lead hat gemergt und die Starter-Tests angepasst.
- Die Worktrees starteten auf `c7227e4` statt auf dem Slice-Branch; alle Agenten haben per reinem Fast-Forward aufgeholt.
- Kein formaler Review-Lauf durch einen zusätzlichen Agenten. Geprüft wurde stattdessen: App-Diff (siehe oben), beide Suiten und die Browser-Tests nach dem Merge im Hauptverzeichnis.

## 3. Entwicklungsumgebung

| Werkzeug | Stand |
|---|---|
| PHP | 8.4.0 (Herd) |
| Composer | 2.8.10, liegt als `~/.config/herd/bin/composer.phar`; `composer` fehlt im Git-Bash-PATH. Aufruf: `php ~/.config/herd/bin/composer.phar …`. Kein manueller Installationsschritt nötig. |
| Node / npm | 22.19.0 / 10.9.3 |
| Laravel | 12.39.0 |
| Playwright / Chromium | 1.65.0 / 158.0.8127.18 |

- `composer install` aus dem Lockfile (Exit 0).
- `npm ci --ignore-scripts` (Exit 0). Die Install-Skripte laufen bewusst nicht, weil das ungenutzte `sqlite3` sonst native Binaries nachlädt. `npm run build` funktioniert damit (Exit 0).
- **`.env`:** Die vorhandene `.env` wurde auf Wunsch unverändert gelassen und nicht gelesen. PHPUnit braucht sie nicht (Werte in `phpunit.xml`, In-Memory-SQLite). Die Browser-Tests setzen Datenbank, Mail, Queue, Cache, Session und KI-Schlüssel per Umgebungsvariable; der Seeder bricht ab, wenn die Verbindung nicht auf `database/e2e.sqlite` zeigt.
- Keine Produktionsdatenbank, keine echten API-Schlüssel, keine echten E-Mails, kein yt-dlp- oder Gemini-Aufruf.

## 4. Test-Baseline

### 4.1 Ursachen der 23 bzw. 14 Fehler im unveränderten Stand

| Ursache | Anzahl | Behandlung |
|---|---|---|
| Vite-Manifest fehlt (ohne Build) | 9 | `withoutVite()` in `TestCase` |
| `route('dashboard')` existiert nicht (`RouteNotFoundException`) | 8 | Tests auf `home` bzw. `landing` umgestellt |
| Erwartetes Redirect-Ziel `/home`, Code leitet auf `/` | 4 | Ist-Zustand festgeschrieben, Entscheidung E1 offen |
| Seite `settings/TwoFactor` fehlt (F-20) | 2 | in `tests/KnownDefects` verschoben |

Protokolle: `baseline/phpunit-00-unveraendert.txt`, `phpunit-01-mit-build.txt`. Die Prognose aus dem Audit traf exakt zu.

### 4.2 Aufbau nach Slice 0

- `tests/TestCase.php`: `withoutVite()`, `Http::preventStrayRequests()`, `Process::fake([])` + `Process::preventStrayProcesses()`, `actingAsUser()`, `actingAsHost()`.
- `phpunit.xml`: fester, nicht geheimer Test-Key; `APP_DEBUG=false`, `APP_URL=http://localhost`; Suiten `Unit`, `Feature`, `KnownDefects`; Default nur `Unit,Feature`.
- Factories für Recipe, RecipeImage, Ingredient, RecipeIngredient, IngredientAlternative, Category, RatingDimension, RecipeRating, Collection, RecipeImport, Comment; `UserFactory::host()`.
- Reguläre Suite nach Bereichen: Auth und Settings (Starter, angepasst), Authorization (Rollen-Matrix aller Routen mit Vollständigkeitstest, API, 403-Antworten), Security (Rechteausweitung, Throttle, CSRF mit Gegenprobe), Recipes (Liste, Detail, Anlegen, Validierung, Bearbeiten, Löschen), Collections, Catalog, Import (Endpunkte und Job mit Fakes), Pages, Support.

### 4.3 Dokumentierte Ausnahmen des regulären Laufs

Der reguläre Lauf ist ohne Skip grün. Ausnahmen gibt es nur an drei klar benannten Stellen:

1. **Suite `KnownDefects`** (36 Tests, nicht im Default): prüft das Sollverhalten und ist rot. Befunde: F-02, F-03, F-08, F-12 bis F-20, F-22, F-23, F-24, F-26, F-27, F-46, F-47. Ausführen: `php artisan test --testsuite=KnownDefects`. Regeln in `tests/KnownDefects/README.md`.
2. **Grüne Ist-Tests zu Defekten** (`…_currently_…` bzw. mit Befund-ID im Namen): halten das heutige fehlerhafte Verhalten fest, damit eine Änderung auffällt.
3. **Rollen-Matrix:** `ingredients.show` und `GET /api/categories` sind ausgenommen, weil sie mit 500 antworten (F-16, in `RouteIntegrityTest` abgedeckt).

### 4.4 Codequalität und Abhängigkeiten (gemessen, nichts repariert)

| Prüfung | Exit | Ergebnis |
|---|---|---|
| `vendor/bin/pint --test` (Altbestand) | 1 | 27 Verstöße |
| `npm run format:check` | 1 | 36 Dateien |
| `npx eslint .` | 1 | 4 Fehler |
| `npx vue-tsc --noEmit` | 2 | 6 Typfehler |
| `npm run build` | 0 | erfolgreich |
| `composer validate --strict` | 0 | gültig |
| `composer audit` | 1 | 55 Advisories in 15 Paketen, 1 critical |
| `npm audit --omit=dev` | 1 | 24 Schwachstellen, 1 critical |

Die in Slice 0 neu angelegten PHP-Dateien sind Pint-sauber. Protokolle: `baseline/lint-*.txt`. Details zu den Advisories: F-64.

## 5. CI

- Trigger: `push` auf `master`, alle Pull Requests, manueller Start.
- `permissions: contents: read`, `concurrency`, `timeout-minutes`.
- `tests.yml`: Build als Gate, dann `php artisan test --testsuite=Unit,Feature` (blockierend); eigener Job `known-defects` (nicht blockierend, hält die Defekte sichtbar).
- `lint.yml`: Prüfmodus statt Auto-Fix, `npm ci`, Typecheck, Audits. Sechs Schritte laufen mit `continue-on-error` und Kommentar samt Messwert, weil sie am unveränderten Altbestand scheitern (Pint, Prettier, ESLint, `vue-tsc`, `composer audit`, `npm audit`).
- Playwright ist noch nicht in der CI (die Screenshot-Baseline gilt nur für Windows).

## 6. Browser-Baseline

Vollständiger Bericht: `baseline/browser/BROWSER-BASELINE.md`, Rohdaten daneben.

- **Aufbau:** Chromium, Viewports 320×640, 390×844 (Touch), 768×1024, 1024×768, 1440×900; Rollen Gast, Nutzer, Host mit synthetischen Konten aus `E2eSeeder`; eigene Datenbank `database/e2e.sqlite`, die vor jedem Lauf neu angelegt wird; PHP-Server auf Port 8123; Requests an fremde Hosts blockiert.
- **Erfasst je Seite und Viewport:** HTTP-Status, Konsolenfehler, Exceptions, fehlgeschlagene Requests, horizontaler Overflow, Titel, `lang`, Anzahl `h1`, Screenshot.
- **Screenshots:** 110 PNGs (ca. 22 MB) in `tests/e2e/baseline.spec.ts-snapshots/` als Ausgangszustand für visuelle Regressionstests. Zwei Vergleichsläufe im Worktree und ein Lauf im Hauptverzeichnis waren stabil grün, 0 flaky.
- **Befehle:** `npm run e2e` (prüfen), `npm run e2e:update` (Baseline neu erzeugen).

### Befunde im Browser

| Ergebnis | Befunde |
|---|---|
| bestätigt | F-01, F-02, F-03, F-04, F-05, F-06, F-07, F-17, F-20, F-21, F-29, F-31, F-38, F-42 |
| teilweise | F-30 (verdeckt erst nach Scrollen), F-32 (kein Scroll-Overflow, aber abgeschnittener Titel) |
| weggefallen | keiner |

Wichtigste Präzisierungen: F-04 löst nachweislich `POST /logout` aus und betrifft auch Gäste; F-05 hat das unsichtbare Dropdown als zweite Ursache; `/settings/appearance` antwortet mit HTTP 500.

Neu beobachtet: `<body>` als Scroll-Container, Header-/Drawer-Versatz von 10 px, nicht fixierter mobiler Header, Auth-Seiten ohne Weg zurück, englische Validierungstexte, axe-Zählung als Ausgangswert für Kontrast (F-68 bis F-70 in `FINDINGS.md`).

## 7. Änderungen

| Bereich | Dateien |
|---|---|
| Anwendungscode | 11 Modelle (`HasFactory`) |
| Tests | `tests/TestCase.php`, `phpunit.xml`, `tests/Feature/**`, `tests/KnownDefects/**`, `tests/e2e/**`, `playwright.config.ts` |
| Testdaten | `database/factories/**`, `database/seeders/E2eSeeder.php` (nicht im `DatabaseSeeder`) |
| CI | `.github/workflows/lint.yml`, `tests.yml` |
| Abhängigkeiten | `package.json`, `package-lock.json`: `@playwright/test` 1.65.0, `@axe-core/playwright` 4.14.0 (dev), Skripte `e2e`, `e2e:update` |
| Sonstiges | `.gitignore` (Playwright-Artefakte, Agenten-Worktrees) |
| Dokumentation | `docs/modernization/**`, `CLAUDE.md`, `.claude/**`, `START-HIER.md` (erstmals committet) |

Alles liegt als lokale Commits auf `chore/slice-0-test-baseline`. Kein Push, kein Merge nach `master`, kein Deployment.

**Rollback:** Branch verwerfen oder nicht mergen; `master` und `improvement/full-website-overhaul` sind unberührt. Lokal entstanden außerdem (alles gitignored): `vendor/`, `node_modules/`, `public/build`, `public/storage` (Symlink), `database/e2e.sqlite`, synthetische Bilder unter `storage/app/public/recipes`, Session-/Cache-Dateien unter `storage/framework`, Agenten-Worktrees unter `.claude/worktrees/`.

## 8. Wichtigste ausgeführte Befehle (Lead)

| Befehl | Exit |
|---|---|
| `git switch -c chore/slice-0-test-baseline` | 0 |
| `php ~/.config/herd/bin/composer.phar validate` / `install` | 0 / 0 |
| `npm ci --ignore-scripts --no-audit --no-fund` | 0 |
| `php artisan test` (unverändert, ohne Build) | 2 |
| `npm run build` | 0 |
| `php artisan route:list` | 0 |
| `php artisan test` (unverändert, mit Build) | 2 |
| `php artisan test` nach Anpassung der Starter-Tests | 2 (2 rot, F-20) |
| Merges der sechs Agenten-Branches | 0 (ein Konflikt in `.gitignore`, beide Ergänzungen behalten) |
| `php artisan test` nach allen Merges, erster Lauf | 1 (2 umgebungsabhängige Tests, behoben) |
| `php artisan test` (final) | 0 |
| `php artisan test --testsuite=KnownDefects` (final) | 1 (beabsichtigt) |
| `npx playwright test` im Hauptverzeichnis | 0 |
| `vendor/bin/pint --test tests database/factories database/seeders/E2eSeeder.php` | 0 |

Die Befehle der Agenten stehen in deren Protokollen (`baseline/`) bzw. in `BROWSER-BASELINE.md`.

## 9. Verbleibende Probleme

- Alle 36 bekannten Defekte und 22 Browser-Defekte bestehen weiter – Slice 0 behebt nichts.
- CI ist auf GitHub ungetestet.
- Nur SQLite geprüft; F-12 und Teile von F-22 zeigen sich erst unter MySQL/Postgres.
- `php artisan test` meldet in einer Arbeitskopie ohne `.env` pro Test eine harmlose Warnung (Collision liest die fehlende Datei); `vendor/bin/phpunit` nicht.
- Die Screenshot-Baseline gilt für Windows/Chromium und zeigt die Fallback-Schrift.
- Die Gemini-Warteschleife (`sleep(3)`), liegengebliebene `.part`-Dateien und der Throttle der Registrierung sind nicht getestet.
- Produktion, Schema-Stand (F-11) und rechtliche Punkte sind unverändert offen.

## 10. Empfehlung

**Slice 1 – Kritische Sicherheits- und Funktionsprobleme**, in zwei parallelen Paketen (Details in `ROADMAP.md`):

- **1a Frontend:** F-04 (unbeabsichtigtes Abmelden), F-01 (Passwort-Reset), F-02 (Zutatensuche), F-03 („Meine Zutaten“).
- **1b Backend:** F-16 (500er-Routen), F-46 (`is_host`), F-22/F-18 (500 statt 422), F-26/F-27 (Import-Missbrauch und Fehlertexte), F-64 (Abhängigkeiten), F-47 (Security-Header).

Dafür nötig: Freigabe von Slice 1 und die Entscheidungen E3 („Meine Zutaten“ entfernen?), E6 (API-Routen entfernen?) und E11 (Header in Laravel oder Nginx?).

Danach in dieser Reihenfolge: Slice 2 (Datenverlust beim Bearbeiten von Rezepten), Slice 3 (Navigation, mobile Darstellung, Formulare), Gate C (konsistentes Redesign).

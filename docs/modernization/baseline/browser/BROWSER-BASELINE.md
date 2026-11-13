# Browser-Baseline (Slice 0)

Stand: 2026-11-13 · Basis-Commit `16add69` (Nachtrag Slice 1a: Abschnitt 7a) (Branch `chore/slice-0-test-baseline`) · Anwendungscode unverändert.

Dieser Bericht hält fest, was im echten Browser gemessen wurde. Alles, was hier nicht als Messung steht, ist nicht geprüft.

## 1. Aufbau

| Baustein | Datei |
|---|---|
| Playwright-Konfiguration (5 Viewport-Projekte + `setup` + `defects`, nur Chromium, `workers: 1`) | `playwright.config.ts` |
| E2E-Umgebung (alle Umgebungsvariablen, fester E2E-Schlüssel) | `tests/e2e/support/env.ts` |
| DB anlegen, migrieren, seeden, `storage:link`, ggf. Build | `tests/e2e/global-setup.ts` |
| Rohdaten zusammenführen (nur bei `E2E_WRITE_RAW=1`) | `tests/e2e/global-teardown.ts`, `tests/e2e/support/raw.ts` |
| Login je Rolle per Formular → `playwright/.auth/*.json` (gitignored) | `tests/e2e/auth.setup.ts` |
| Fixtures: Fremd-Hosts blockieren, feste Zeit, Messfunktionen | `tests/e2e/support/fixtures.ts` |
| Baseline je Seite × Viewport | `tests/e2e/baseline.spec.ts` (+ `baseline.spec.ts-snapshots/`) |
| Gezielte Befundprüfungen | `tests/e2e/known-defects.spec.ts` |
| Synthetische Testdaten | `database/seeders/E2eSeeder.php` (nicht im `DatabaseSeeder` registriert) |
| Rohdaten | `docs/modernization/baseline/browser/baseline-results.json`, `defects-results.json` |

**Server:** eingebauter PHP-Server, direkt gestartet (`php -S 127.0.0.1:8123 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`, Arbeitsverzeichnis `public/`). Nicht `artisan serve`, weil das nur eine Whitelist von Umgebungsvariablen weiterreicht. Single-threaded, deshalb seriell (`workers: 1`). `reuseExistingServer: false`, damit nie versehentlich ein anderer Server auf dem Port benutzt wird. Bereitschaftsprüfung über `/up`.

**Konfiguration ohne `.env`:** Es wird keine `.env` benötigt, gelesen oder angelegt. Alle relevanten Werte stehen in `tests/e2e/support/env.ts` und werden als Prozessumgebung an `php` und `npm` übergeben: u. a. `APP_ENV=local`, `APP_DEBUG=true`, `APP_KEY` (fester, öffentlich dokumentierter, **nicht geheimer** E2E-Schlüssel), `APP_URL=http://127.0.0.1:8123`, `DB_CONNECTION=sqlite`, `DB_DATABASE=<absoluter Pfad>/database/e2e.sqlite`, `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=database` (kein Worker wird gestartet), `MAIL_MAILER=array`, `GEMINI_API_KEY=` (leer), `YTDLP_PATH=e2e-ytdlp-disabled`, `LOG_CHANNEL=stderr` (schreibt nicht in `storage/logs`). `APP_NAME=Laravel` und `APP_LOCALE=en` entsprechen bewusst `.env.example`, damit der Ist-Zustand gemessen wird.
- Laravel überschreibt gesetzte Umgebungsvariablen nicht durch eine `.env` (immutable Dotenv). Das ist im Worktree belegt (Login mit Seeder-Konto klappt, Seeder-Rezepte erscheinen), **im Hauptverzeichnis mit vorhandener `.env` wurde es nicht ausgeführt**. Leere Werte (`GEMINI_API_KEY=`) kommen unter Windows als gesetzte, leere Variable an (geprüft mit `getenv()` → `string(0) ""`).
- Das Global-Setup bricht ab, wenn `bootstrap/cache/config.php` existiert (ein Config-Cache würde die Umgebung ignorieren).
- Der Seeder bricht ab, wenn die Verbindung nicht auf `database/e2e.sqlite` zeigt.

**Datenbank:** `database/e2e.sqlite` (ignoriert durch `database/.gitignore` `*.sqlite*`, geprüft mit `git check-ignore`). Vor jedem Lauf wird die Datei gelöscht und leer neu angelegt, danach `php artisan migrate --force` und `php artisan db:seed --class=E2eSeeder --force`. Kein `migrate:fresh`, kein `db:wipe`.

**Fremd-Hosts:** Jeder Request an einen anderen Origin als `127.0.0.1:8123` wird im Browser abgebrochen (`blockedbyclient`) und separat als `blockedExternal` protokolliert. Betroffen ist nur `https://fonts.bunny.net/css?family=instrument-sans:400,500,600`. Die Seiten werden also **mit Fallback-Schrift** aufgenommen, nicht mit Instrument Sans. Das macht die Bilder reproduzierbar und vermeidet Drittzugriffe; die Folge-Konsolenmeldung `ERR_BLOCKED_BY_CLIENT` wird nicht als JS-Fehler gezählt.

**Determinismus:** Browserzeit fest (`2026-01-15T10:00:00Z`, `page.clock.setFixedTime`), `Math.random` deterministisch, `locale de-DE`, `timezoneId Europe/Berlin`, `colorScheme light`, `reducedMotion reduce`, Screenshots mit `animations: 'disabled'`, `caret: 'hide'`, `deviceScaleFactor 1`. Seeder-Zeitstempel fest (ab 2025-06-01).

**Viewports:** `w320` 320×640, `m390` 390×844, `t768` 768×1024, `d1024` 1024×768, `d1440` 1440×900. `isMobile`/`hasTouch` bei 320 und 390 (bei 320 zusätzlich zur Vorgabe gesetzt).

## 2. Reproduzieren

```bash
php ~/.config/herd/bin/composer.phar install --no-interaction --no-progress
npm ci --ignore-scripts --no-audit --no-fund
npx playwright install chromium
npm run e2e                          # = npx playwright test (vergleicht mit Baseline)
npm run e2e:update                   # = npx playwright test --update-snapshots (Baseline neu schreiben)
E2E_WRITE_RAW=1 npx playwright test  # zusätzlich Rohdaten nach docs/modernization/baseline/browser/ schreiben
E2E_REBUILD=1 npx playwright test    # Assets vor dem Lauf neu bauen
```

Assets werden nur gebaut, wenn `public/build/manifest.json` fehlt. **Achtung im Hauptverzeichnis:** Ein vorhandener Build (ggf. mit anderem `VITE_APP_NAME` aus der `.env`) wird dann wiederverwendet; für eine exakt vergleichbare Baseline `E2E_REBUILD=1` setzen.

**Versionen:** `@playwright/test` 1.65.0 (exakt gepinnt), `@axe-core/playwright` 4.14.0 (exakt gepinnt), Chromium 158.0.8127.18 (Playwright-Build 1254, „Chrome for Testing“), PHP 8.4.0, Node 22.19.0, Laravel 12.39.0. Screenshots sind plattformgebunden (Suffix `-win32`); unter Linux/CI müssen eigene Baselines erzeugt werden.

**Testkonten (synthetisch, nur E2E-DB):** Passwort für alle `E2e-Passwort-2026!`, verifiziert, ohne 2FA.

| Konto | Rolle | Verwendung |
|---|---|---|
| `user@example.test` | Nutzerin | Baseline Nutzer; besitzt Sammlung 1 „Wochenplan“ (3 Rezepte) und 2 „Leere Sammlung“ |
| `host@example.test` | Host | Baseline Host, F-06/F-07/F-30/F-31/F-38 |
| `user2@example.test` | Nutzer | F-04 (eigene Anmeldung), F-17 (legt eine Sammlung an) |

**Testdaten:** Basis-Seeder (Kategorien, Bewertungsdimensionen, Zutaten) plus Wegwerf-Kategorie `E2E-Wegwerf-Tag`; 8 Rezepte: 1 „Cremige Linsen-Bolognese“ (Bild, Alternativen, mehrzeilige Notizen), 2 „Kürbis-Kichererbsen-Erdnusscurrygewürzmischung mit Röstzwiebel-Topping“ (Bild, 4 Kategorien), 3 „Overnight Oats mit Beeren“ (**ohne Bild**), 4–8 mit Varianz (ohne Beschreibung, ohne Bewertungen, ohne Kategorien). Bilder sind lokal per GD erzeugte einfarbige PNGs (1200×800) unter `storage/app/public/e2e/`.

## 3. Ergebnis je Seite × Viewport

Legende: `Status · JS <Anzahl console.error + pageerror> · Layout`. Layout: `ok` = kein Dokument-Overflow und kein Element ragt rechts über den Viewport; `h1-clip` = Text des ersten `h1` ist breiter als sein Kasten (`scrollWidth > clientWidth`) bzw. ragt über den Viewport; `OVF-el(n)` = n Elemente ragen rechts über den Viewport. Fehlgeschlagene Requests: in keinem Fall (außer den bewusst blockierten Schrift-Requests). Quelle: `baseline-results.json`.

| Rolle | Seite | w320 | m390 | t768 | d1024 | d1440 | JS-Fehler (erste Meldung) | Weitere Auffälligkeiten (d1440) |
|---|---|---|---|---|---|---|---|---|
| Gast | `/` | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | – | Titel „Laravel“, h1=0 |
| Gast | `/rezepte` | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | – | Titel „Laravel“, h1=0 |
| Gast | `/rezept/1` (Bild) | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | – | Titel „Laravel“, h1=1 |
| Gast | `/rezept/3` (ohne Bild) | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | – | Titel „Laravel“, h1=1 |
| Gast | `/rezept/2` (langer Titel) | 200 · JS 0 · h1-clip | 200 · JS 0 · h1-clip | 200 · JS 0 · ok | 200 · JS 0 · h1-clip | 200 · JS 0 · ok | – | Titel „Laravel“, h1=1 |
| Gast | `/zutatensuche` | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | – (Fehler erst beim Tippen, F-02) | Titel „Laravel“, h1=0 |
| Gast | `/login` | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | – | Titel „Login - Laravel“, h1=1 |
| Gast | `/register` | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | – | Titel „Registrieren - Laravel“, h1=1 |
| Gast | `/forgot-password` | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | – | Titel „Passwort vergessen – favRezepte - Laravel“, h1=1 |
| Nutzer | `/home` | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | – | Titel „Laravel“, h1=0 |
| Nutzer | `/sammlungen` | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | – | Titel „Laravel“, h1=0 |
| Nutzer | `/sammlungen/erstellen` | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | – | Titel „Neue Sammlung anlegen - Laravel“, h1=0 |
| Nutzer | `/sammlung/2` (leer) | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | – | Titel „Laravel“, h1=1 |
| Nutzer | `/sammlung/1` (gefüllt) | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | – | Titel „Laravel“, h1=1 |
| Nutzer | `/meineZutaten` | 200 · JS 1 · ok | 200 · JS 1 · ok | 200 · JS 1 · ok | 200 · JS 1 · ok | 200 · JS 1 · ok | `TypeError: Cannot read properties of undefined (reading 'filter')` | Titel „Laravel“, h1=0 |
| Nutzer | `/settings` | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | – | Titel „Laravel“, h1=0 |
| Nutzer | `/settings/profile` | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | – | Titel „Profil bearbeiten - Laravel“, h1=0 |
| Nutzer | `/settings/password` | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | – | Titel „Passwort ändern - Laravel“, h1=0 |
| Nutzer | `/settings/appearance` | **500** | **500** | **500** | **500** | **500** | Ressource mit Status 500 | Laravel-Debugseite (kein Screenshot abgelegt) |
| Nutzer | `/settings/two-factor` | 200 → `/user/confirm-password` | ebenso | ebenso | ebenso | ebenso | – | Weiterleitung zur Passwortbestätigung; danach 500 (siehe F-20) |
| Host | `/rezepte/erstellen` | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | – | Titel „Laravel“, h1=0 |
| Host | `/rezept/1/bearbeiten` | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | 200 · JS 0 · ok | – | Titel „Laravel“, h1=0 |
| Host | `/rezept/2` (Host-Aktionen) | 200 · JS 0 · h1-clip | 200 · JS 0 · h1-clip | 200 · JS 0 · ok | 200 · JS 0 · h1-clip | 200 · JS 0 · ok | – | Titel „Laravel“, h1=1; Aktionen überlagern Titel (F-31) |

`html[lang]` ist auf **allen** Seiten `en`. Ein Dokument-Overflow (`documentElement.scrollWidth > innerWidth`) trat **nirgends** auf – auch nicht bei 320 px –, weil `overflow-x: hidden` Überstände abschneidet (siehe F-32).

### axe-core (Zählung, nur 1440 und 390, kein Testkriterium)

Summen über alle gescannten Seiten × Viewports (46 Scans; die 500er-Seite ist ausgenommen). Pro Seite in `baseline-results.json` → `axeViolations`.

| axe-Regel (Impact) | betroffene Scans | Knoten gesamt |
|---|---|---|
| color-contrast (serious) | 38 | 216 |
| region (moderate) | 26 | 50 |
| landmark-unique (moderate) | 36 | 44 |
| page-has-heading-one (moderate) | 24 | 24 |
| landmark-one-main (moderate) | 8 | 8 |
| landmark-main-is-top-level (moderate) | 8 | 8 |
| landmark-no-duplicate-main (moderate) | 8 | 8 |
| image-redundant-alt (minor) | 2 | 2 |
| heading-order (moderate) | 2 | 2 |

Hinweis: Kontraste wurden mit Fallback-Schrift gemessen (Schrift blockiert); das Ergebnis kann mit Instrument Sans leicht abweichen.

## 4. Audit-Befunde im Browser

Alle Belege stammen aus `defects-results.json` bzw. den Screenshots/Messwerten der Tests in `tests/e2e/known-defects.spec.ts`. „bestätigt“-Tests sind mit `test.fail()` markiert.

| ID | Ergebnis | Beleg |
|---|---|---|
| F-01 | **bestätigt** | Absenden auf `/reset-password/e2e-dummy-token?email=…` → Konsole: `Error: Ziggy error: route 'password.store' is not in the route list.`; 0 POST-Requests. `php artisan route:list` bestätigt den Fortify-Namen `password.update` (Audit-Hypothese damit verifiziert). Der Fehler erscheint als `console.error`, nicht als unbehandelte `pageerror`. |
| F-02 | **bestätigt** | „Linsen“ getippt → `TypeError: Cannot read properties of undefined (reading 'map')`; Liste bleibt bei 39 von 39 Einträgen (nicht gefiltert). Laden der Seite selbst ist fehlerfrei. |
| F-03 | **bestätigt** | `/meineZutaten`: `TypeError: … (reading 'filter')` bei jedem Viewport (Vue fängt ihn ab → `console.error`, Seite rendert teilweise). Klick auf „Meine Zutaten“ in der Sidebar: URL bleibt `/home`. |
| F-04 | **bestätigt** | Menü geschlossen, Dropdown `opacity: 0; visibility: visible; pointer-events: auto; z-index: 1200`. `elementFromPoint` an der Position von „Abmelden“ (1440: 1316/138; 390: 276/146) trifft `button.user-menu__item--danger`; Klick → 1× `POST /logout`, Weiterleitung `/`, danach abgemeldet. Bei 1440 und 390. |
| F-05 | **bestätigt, Ursache präzisiert** | 390 px, ☰ → „Suche“: Links „Rezeptsuche“/„Zutatensuche“ liegen bei y = 79–145 px oben im Inhaltsbereich (`isVisible` = true), werden aber an allen drei Messpunkten verdeckt: links vom Drawer (`a.sidebar-collection-pill`, `nav.sidebar-nav`), Mitte und rechts vom **unsichtbaren Gast-User-Dropdown** (`a.user-menu__item`, F-04). Das Audit nannte nur den Drawer. Screenshot im Testlauf: `F-05-mobil-390.png`. |
| F-06 | **bestätigt** | Nur Titel, „Rezept speichern“ → `POST /recipes` 302; Server liefert `ingredients.0.name: The ingredients.0.name field is required.` und `steps.0: The steps.0 field is required.`; sichtbare Fehlermeldungen im Formular: 0. Englische Validierungstexte (siehe F-42). |
| F-07 | **bestätigt** (ohne zu löschen) | Doppelklick auf `E2E-Wegwerf-Tag`, „Löschen“ → sofort `DELETE /categories/8`, 0 native Dialoge, kein Bestätigungstext im DOM. Der Request wurde per `page.route` abgefangen und mit 200 beantwortet; in der DB wurde nichts gelöscht. |
| F-17 | **bestätigt** | Sammlung „E2E Testsammlung“ angelegt (erscheint in der Liste, Weiterleitung `/sammlungen`), Text „Sammlung wurde erstellt“ nicht sichtbar. |
| F-20 | **bestätigt, präzisiert** | `/settings/appearance` → HTTP 500 (Server-Fehler beim Rendern; die Seite existiert nicht im Vite-Manifest). `/settings/two-factor` → zuerst Weiterleitung zu `/user/confirm-password` (Fortify `confirmPassword`); nach Bestätigung (302) bei Inertia-Navigation `pageerror: Page not found: ./pages/settings/TwoFactor.vue`, bei direktem Aufruf HTTP 500. Das Audit beschrieb nur „Vue-Datei fehlt“; tatsächlich ist der direkte Aufruf ein Serverfehler. |
| F-21 | **bestätigt** | Gast klickt Logo auf `/rezepte` → landet auf `/login`. Login mit `user@example.test` → landet auf `/` statt `/home` (Fortify-Hypothese bestätigt). |
| F-29 | **bestätigt** | 390 px, Drawer offen: `.sidebar-backdrop` gerendert, aber `display: none`; Tippen bei (375/500) trifft `section.content-section`, Drawer bleibt offen. |
| F-30 | **teilweise** | Direkt nach dem Öffnen des Alternativen-Modals (320/390, Rezept 1 bearbeiten) ist der Titel **nicht** verdeckt: Die Seite ist zum Button gescrollt, der Header (nicht fixiert) ist außer Sicht. Weil es keine Scroll-Sperre gibt, lässt sich bei offenem Modal zurückscrollen; dann liegen Header (0–60 px) und unsichtbares User-Dropdown über dem Modaltitel (y ≈ 43–70 px): `elementFromPoint` trifft `div.user-menu__dropdown` (320) bzw. `div.user-menu__item--label` (390). Grüner Test für den Normalfall, `test.fail` für den Scroll-Fall. |
| F-31 | **bestätigt** | 1024 px, Host: `.recipe-actions` überlappt `h1.title` bei Rezept 1 (mit Bild), 2 (langer Titel) und 3 (ohne Bild); bei 1 und 3 zusätzlich die Beschreibung. Ohne Bild liegt der Inhalt in einer schmalen Spalte (Titel 224 px breit bei x = 313). Screenshots `F-31-rezept-{1,2,3}-1024.png` im Testlauf; Baseline `host-rezept-host-langer-titel-d1024-win32.png`. |
| F-32 | **teilweise** | Kein horizontaler Scroll-Overflow bei 320 px (`scrollWidth` = 320). Aber der lange Titel wird **abgeschnitten**: `h1` 240 px breit, Inhalt 367 px (`overflow-wrap: normal`, `word-break: normal`, `hyphens: manual`), sichtbar als „Erdnusscurrygewürzmi…“ ohne Umbruch. Gleiches bei 390 und 1024 (schmale Spalte neben den Aktionen). Die Audit-Hypothese „abgeschnitten statt umbrochen“ stimmt; einen Overflow mit Scrollbalken gibt es nicht. |
| F-38 | **bestätigt** (Stichprobe) | Griff „Schritt verschieben“ ist fokussierbar (`<button>`), aber Space/ArrowDown/Alt+ArrowDown/Enter ändern die Reihenfolge nicht (`["Schritt A","Schritt B"]`). |
| F-42 | **bestätigt** | `html[lang="en"]` auf allen Seiten. Von 11 geprüften Seiten haben 9 kein `h1`, nämlich `/`, `/rezepte`, `/zutatensuche`, `/home`, `/sammlungen`, `/sammlungen/erstellen`, `/settings`, `/settings/profile`, `/settings/password` sowie auf den Host-Formularen; 8 der 11 Seiten tragen den Fallback-Titel „Laravel“. Zwei Auth-Titel enthalten noch „favRezepte“. |

Nicht im Browser geprüft (außerhalb des Auftrags oder nicht beobachtbar): F-08 (Laufzeit-Verifizierung), F-13/F-14/F-15/F-19 (Datenflüsse), F-24–F-27 (Import – bewusst nicht ausgelöst), F-34 (Dark Mode, nicht aufgenommen), F-36 (Touch-Drag), F-43 nur indirekt über axe `color-contrast`.

## 5. Neue Beobachtungen (nicht im Audit)

1. **`<body>` ist der Scroll-Container** (Audit: „vermutlich“): `documentElement.scrollHeight` = Viewporthöhe, `body.scrollHeight` größer (z. B. 1570 px bei `/rezept/2`, 1440). Folge für Tests: `fullPage`-Screenshots bilden nur den Viewport ab; deshalb wird der Viewport für die Aufnahme auf die Inhaltshöhe vergrößert (siehe Abschnitt 6). Folge für Nutzer: `window.scrollTo`, Anker und Browser-Scroll-Wiederherstellung wirken nicht wie erwartet (Hypothese, nicht gemessen).
2. **Das unsichtbare User-Dropdown blockiert auch für Gäste** (Links „Anmelden“/„Registrieren“) und überdeckt das mobile Such-Untermenü (F-05) sowie Modaltitel (F-30). F-04 betrifft also nicht nur angemeldete Nutzer.
3. **Mobiler Header ist 60 px hoch**, der Drawer beginnt fest bei `top: 50px` – die oberen 10 px des Drawers liegen unter dem Header (gemessen: Header 0–60 px, Sidebar ab y = 50 px bei 390).
4. **Mobiler Header ist nicht fixiert** (`position: relative`), scrollt also mit; das relativiert F-30 im Normalfall.
5. **`/settings/two-factor` verlangt zuerst eine Passwortbestätigung**; die Baseline zeigt daher die Seite „Passwort bestätigen – favRezepte“.
6. **Passwort-Reset-Fehler ist eine abgefangene Konsolenmeldung**, keine unbehandelte Exception: Wer nur `pageerror` überwacht, sieht F-01, F-02 und F-03 nicht. Die Tests werten deshalb auch `console.error`.
7. **Langer Titel auch bei 1024 px abgeschnitten**, nicht nur mobil (Spalte neben den absolut positionierten Aktionen).
8. **Login/Registrierung haben weder App-Shell noch Logo/Zurück-Link** (Screenshot `guest-login-m390-win32.png`); von dort gibt es keinen Weg zur Startseite außer über den Browser. UX-Beobachtung, nicht weiter untersucht.
9. **Placeholder im zweizeiligen Beschreibungsfeld werden abgeschnitten** (320 px, `host-rezept-erstellen-w320-win32.png`). Kosmetisch.
10. **Validierungsmeldungen auf Englisch** („The steps.0 field is required.“) – ergänzt F-42 um einen Laufzeitbeleg.

## 6. Screenshot-Baseline und Stabilität

- 110 PNGs unter `tests/e2e/baseline.spec.ts-snapshots/` (23 Seiten × 5 Viewports = 115, abzüglich 5 × `/settings/appearance`, weil 5xx-Debugseiten lokale Pfade und Zeiten enthalten und nicht abgelegt werden). Gesamtgröße **ca. 22 MB** (`du -sh`), größte Datei ca. 0,37 MB.
- **Ganzseite:** Da `<body>` scrollt, wird der Viewport vor der Aufnahme auf `max(documentElement.scrollHeight, body.scrollHeight)` vergrößert (Obergrenze 5000 px). Das verändert `vh`-abhängige Elemente (z. B. Sidebar-Höhe) gegenüber dem echten Viewport; die Mess-JSON-Werte (Overflow, h1, Titel) werden **vorher** im echten Viewport erhoben.
- Toleranz: `maxDiffPixelRatio: 0.001`, keine Maskierung nötig.
- **Stabilität:** Nach dem Erzeugen (`--update-snapshots`, 143 bestanden) liefen zwei Vergleichsläufe ohne Update; Ergebnisse siehe Abschnitt 7. Keine Flakiness beobachtet. Bekanntes Risiko: Sammlungen zeigen `updatedAt` als `diffForHumans` („… ago“) – bei festen Seeder-Zeitstempeln (2025-06-01) ändert sich der Text erst nach längerer Zeit (z. B. Jahreswechsel „1 year“ → „2 years“), dann muss die Baseline erneuert werden.
- Die Datenänderungen der Defekt-Tests (F-17 legt eine Sammlung für `user2` an) betreffen kein in der Baseline verwendetes Konto.

## 7. Läufe

| Lauf | Befehl | Ergebnis | Exit |
|---|---|---|---|
| Baseline erzeugen | `E2E_WRITE_RAW=1 npx playwright test --update-snapshots` | 143 passed (4,8 min) | 0 |
| Vergleich 1 | `npx playwright test --reporter=line` | 143 passed (4,2 min), 0 failed, 0 flaky | 0 |
| Vergleich 2 | `npx playwright test --reporter=line,json` | 143 passed (4,4 min); JSON-Statistik: expected 143, unexpected 0, flaky 0, skipped 0; davon 121 „passed/expected“ und 22 „failed/expected“ | 0 |

Die Wrapper-Shell von Vergleich 1 meldete Exit 1, weil ein nachgelagertes `cp test-results/results.json` scheiterte (der CLI-Reporter `line` ersetzt den JSON-Reporter der Konfiguration); Playwright selbst endete mit Exit 0.

Playwright zählt Tests mit `test.fail()`, die erwartungsgemäß scheitern, als „passed“. Aufschlüsselung: 4 Setup-Tests, 115 Baseline-Tests, 24 Defekt-Tests, davon **22 erwartete Fehlschläge** (`test.fail`) und 2 normal grüne Tests (F-30 Normalfall bei 320 und 390).

## 7a. Nachtrag Slice 1a – Frontend-Funktion (2026-11-13)

Basis-Commit `67a82d5`. Behoben: F-01, F-02, F-03, F-04 (sowie der F-04-Anteil von F-05). Die zugehoerigen Tests stehen jetzt ohne `test.fail()` in `tests/e2e/regression.spec.ts` (eigenes Playwright-Projekt `regression`, 1440×900; mobile Tests per `test.use`). Gemeinsame Helfer `hitTest`/`freshLogin` liegen in `tests/e2e/support/helpers.ts`. Rohdaten der Regressionstests: `kind = "regression"`.

**Regressionstests (14):** F-01 POST `/reset-password` ohne Ziggy-Fehler · F-02 Tippen filtert (39 → 2, Meta-Zeile folgt) · F-02 Ein-/Ausschluss im Filterpanel (Einschluss „Linsen“ 2, Ausschluss 37 von 39) · F-02 keine Kategorie-Chips/-Zaehler · F-04 Gast: Anmelden-Position nicht klickbar · F-03 Gast: Sidebar „Meine Zutaten“ fuehrt zu `/login` · F-04/F-05 (390) Untermenue-Links nicht mehr vom User-Menue verdeckt · F-03 `/meineZutaten` ohne JS-Fehler mit Leerzustand · F-03 Sidebar-Eintrag navigiert · F-04 Abmelden-Position nicht klickbar (1440/390; gemessen `visibility: hidden`, `pointer-events: none`, `inert`, 0× `POST /logout`) · F-04 Escape schliesst, Fokus zurueck auf Ausloeser · F-04 Aussenklick schliesst · F-04 geschlossen nicht per Tab erreichbar, geoeffnet per Tab/Klick bedienbar.

**Geschaerfte Defekt-Tests (bleiben `test.fail`):**
- F-05: Nach F-04 trifft der rechte Messpunkt (90 %) den Link, links/Mitte verdeckt weiterhin der Drawer (`a.sidebar-collection-pill`, `nav.sidebar-nav`). Das bisherige Kriterium „mindestens ein Messpunkt“ wurde dadurch faelschlich gruen; Sollkriterium ist jetzt „alle Messpunkte“. Eigentliche Korrektur: Slice 3.
- F-30 (Zurueckscrollen): Bei 390 px lag am Titel-Mittelpunkt bisher das unsichtbare Dropdown; jetzt ist der Mittelpunkt frei (y = 64), der Header (0–60 px) ueberlappt aber weiter den oberen Titelrand (Titel ab y ≈ 51). Zusaetzliches Kriterium: Titel- und Header-Box ueberschneiden sich nicht. Bei 320 px trifft der Mittelpunkt weiterhin den Header.

**Screenshot-Baselines aktualisiert (10 von 110):**
- `guest-zutatensuche-{w320,m390,t768,d1024,d1440}`: erfundene Kategorie-Chips („alle, gemüse, obst, gewürz, vegan, glutenfrei, …“) und das Panel mit Dummy-Zaehlern entfernt; Suchbereich dadurch niedriger, Liste unveraendert (39 Zutaten). Vorher/Nachher bei 390 px angesehen: kein Layoutbruch.
- `user-meine-zutaten-{w320,m390,t768,d1024,d1440}`: statt teilgerenderter Fehlerseite („Zutaten … 0 Zutaten gefunden“) jetzt Leerzustand „Meine Zutaten – Du hast noch keine Zutaten gespeichert. Diese Funktion wird gerade ausgebaut.“
- Alle anderen 100 Aufnahmen unveraendert (Vergleichslauf ohne Update: nur diese 10 wichen ab). Der Sidebar-Eintrag „Meine Zutaten“ (`<button>` → `<a>`) und das unsichtbare Dropdown (`visibility: hidden`) erzeugen keinen Pixelunterschied.

**Laeufe Slice 1a:** siehe Bericht des Slices; Kennzahlen: 151 Tests (4 Setup, 115 Baseline, 14 Regression, 18 Defekt-Tests, davon 16 `test.fail` und 2 normal gruene F-30-Normalfaelle). Fuer parallele Arbeitskopien den Port per `E2E_PORT=<port>` setzen.

## 7b. Nachtrag Slice 2 – Datenverlust beim Bearbeiten (2026-11-13)

Basis-Commit `3e8e34c` (Backend-Teil von Slice 2). Behoben im Frontend: F-07, F-65 (Bestätigung beim Löschen mit Servermeldung, Fehler sichtbar), F-13 (Alternativen nur noch als Entwurf, gespeichert mit dem Rezept), F-14 (mehrzeilige Schritte), F-15 (Feld „Quelle“ entfernt), F-39 (Entfernen aus Sammlungen mit Rückfrage an der Karte); Teil von F-74 (Fehler beim Umbenennen/Löschen in den beiden Bearbeiten-Modals).

**Regressionstests (+14, Block „Slice 2“ am Ende von `regression.spec.ts`):** F-07 Löschen mit 409 → Abbrechen → Zurücksetzen beim erneuten Öffnen → „Endgültig löschen“ mit `confirmed=1` (Kategorie danach weg) · F-07 Netzwerkfehler und 500 sichtbar · F-07/F-74 Umbenennen auf vergebenen Namen sichtbar · F-07 bei 390 px (nur 409 + Abbrechen) · F-65/F-18 verwendete Zutat → 422 sichtbar, Umbenennen-Fehler sichtbar · F-65 Wegwerf-Zutat anlegen → Bestätigung → gelöscht · F-13 Alternative hinzufügen/entfernen ohne Request an `…/alternatives` · F-13 „Abbrechen“ verwirft · F-13 neue Zeile, Drag & Drop, Umbenennen, nie geöffnetes Modal (gesendete Felder je Zeile geprüft) · F-14 mehrzeiliger Schritt: Anlegen, Bearbeiten, unverändert gesendet, `white-space: pre-line` · F-15 kein Feld „Quelle“, Import-Vorbelegung mit altem `source` · F-39 Kartenklick löscht nicht, Rückfrage, Abbrechen, Tastatur (Enter/Shift+Tab/Enter) · F-39 Fehler (500) an der Karte · F-39 bei 390 px.
Der bisherige Defekt-Test F-07 (`test.fail`) wurde aus `known-defects.spec.ts` entfernt.

**Testdaten (Seeder):**
- `E2E-Wegwerf-Tag` ist jetzt Rezept 6 („Schnelle Tomatenpasta“) zugeordnet, damit die Bestätigung eine Anzahl nennt. Rezept 6 wurde gewählt, weil es in keiner Detail- oder Sammlungs-Baseline vorkommt und seine Variante („ohne Bewertungen“) erhalten bleibt; Rezept 8 bleibt „ohne Kategorien“.
- Neuer abgeschlossener Import des Hosts (ID 1) im Altformat mit `source` im Ergebnis; nur über `/rezepte/erstellen?import=1` erreichbar.
- Mutierende Tests nutzen eigene Wegwerf-Daten (Rezepte per `POST /recipes` nur mit bestehenden Zutaten, am Ende gelöscht; Wegwerf-Zutat wird angelegt und gelöscht; Sammlungen von `user2`, am Ende gelöscht). Einzige bleibende Änderung im Lauf: F-07 löscht `E2E-Wegwerf-Tag`. Die Baseline-Projekte stehen in der Konfiguration vor `regression` und laufen mit `workers: 1` zuerst (im Lauf-Log beobachtet); die Datenbank wird vor jedem Lauf neu angelegt.

**Screenshot-Baselines aktualisiert (20 von 110):**
- `host-rezept-erstellen-*` und `host-rezept-bearbeiten-*` (je 5): Feld „Quelle“ entfällt (Formular 78–80 px niedriger bei 320–768 px). **Zusätzlich** sind die Bewertungsdimensionen jetzt nach Name sortiert (Aufwand, Gesamt, Geschmack, Schwierigkeit) – das stammt aus dem Backend-Teil (E5/F-12, `3e8e34c`), nicht aus dem Frontend; die Baselines waren dort nicht nachgezogen worden.
- `guest-rezepte-*` und `user-home-*` (je 5): Karte „Schnelle Tomatenpasta“ zeigt zusätzlich den Tag „E2E-Wegwerf-Tag“ (Seeder). Diese Abweichung lag unter der Toleranz (Vergleich blieb grün), die Bilder wurden trotzdem erneuert, damit sie den Seeder-Stand zeigen.
- Nicht übernommen: 16 weitere Aufnahmen (320/390 px), die beim Neuschreiben nur durch Text-Antialiasing im Schriftzug „PLANTMEL“ (Subpixel statt Graustufen, 495 Pixel, y = 24–36) abwichen. Das ist Rendering-Rauschen, Playwright wertet es nicht als Abweichung; die alten Bilder bleiben.
- Detailseiten der Rezepte 1–3 unverändert: kein Seeder-Rezept hat mehrzeilige Schritte; `white-space: pre-line` ändert ohne Umbrüche nichts. Sammlungsseite unverändert (Änderungen nur im Entfernen-Modus).

**Gefundener und behobener Fehler während der Umsetzung:** Ein erster Entwurf entfernte Rezepte aus Sammlungen per axios-`DELETE`. Der Browser folgte dem 302 von `removeRecipe` mit derselben Methode (`DELETE /sammlung/{id}` → 405), die Liste wurde nicht aktualisiert. Jetzt: Inertia-`router.delete` (Laravel antwortet mit 303), Fehler über die abbrechbaren Inertia-Events `invalid`/`exception`.

**Läufe Slice 2:** zwei vollständige Läufe `E2E_PORT=8151 npx playwright test` nach dem letzten Build: je 164 bestanden (Exit 0, ca. 5,0 min); 164 Tests (4 Setup, 115 Baseline, 28 Regression, 17 Defekt-Tests, davon 15 `test.fail` und 2 normal grüne F-30-Normalfälle).

## 8. Offene Risiken

- Im Hauptverzeichnis mit `.env` nicht ausgeführt; die Umgebungsvariablen-Priorität ist dort begründet, aber nicht gemessen.
- Baselines nur für Windows/Chromium; Fallback-Schrift statt Instrument Sans.
- `SESSION_DRIVER=file`/`CACHE_STORE=file` schreiben nach `storage/framework/{sessions,cache}` des jeweiligen Verzeichnisses (gemeinsam mit einer lokalen Entwicklungsinstanz, falls diese ebenfalls `file` nutzt).
- Login-Throttling (5/min je E-Mail+IP, Cache `file`): Zwei Gesamtläufe direkt hintereinander unterschreiten das Limit; bei sehr vielen Wiederholungen innerhalb einer Minute könnte der Setup-Login scheitern.
- Inertia-SSR ist in `config/inertia.php` aktiv; ohne SSR-Server fällt die App auf Client-Rendering zurück (so gemessen). Lauscht zufällig etwas auf Port 13714, wäre das Verhalten anders.

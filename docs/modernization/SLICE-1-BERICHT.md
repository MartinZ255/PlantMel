# Abschlussbericht Slice 1 – Kritische Sicherheits- und Funktionsprobleme

Stand: 2026-11-13 · Branch `fix/slice-1-kritisch` (lokal, kein Push) · aufbauend auf `chore/slice-0-test-baseline` (`67a82d5`)

## 1. Ergebnis

Slice 1 ist lokal umgesetzt und durch Tests belegt. Nichts wurde gepusht oder deployt; der VPS wurde nicht berührt.

| Messung | Vor Slice 1 | Nach Slice 1 | Exit |
|---|---|---|---|
| Reguläre Suite (`php artisan test`) | 334 bestanden | **410 bestanden** | 0 |
| Bekannte Defekte (`--testsuite=KnownDefects`) | 36 rot | **14 rot** | 1 (beabsichtigt) |
| Browser (`npx playwright test`) | 143, davon 22 erwartete Defekte | **151, davon 16 erwartete Defekte** | 0 |
| `composer audit` | 55 Advisories | **0** | 0 |
| `npm audit --omit=dev` | 24 Schwachstellen | **0** | 0 |
| `vue-tsc` (Altbestand) | 6 Typfehler | 3 | – |

Protokolle: `baseline/phpunit-04-slice1-default.txt`, `phpunit-05-slice1-known-defects.txt`.

## 2. Entscheidungen des Auftraggebers und ihre Umsetzung

| Entscheidung | Umsetzung |
|---|---|
| „Meine Zutaten“ ist ein gewolltes Feature und wird später ausgebaut | Nicht entfernt. Die Seite stürzt nicht mehr ab und zeigt einen ehrlichen Leerzustand; der Sidebar-Link funktioniert. **Speichern eigener Zutaten ist noch nicht gebaut.** |
| Ungenutzte API-Routen dürfen weg, sofern wirklich ungenutzt | Verifiziert: Nur generierte, nicht versionierte Wayfinder-Dateien verweisen auf `/api/…`. Alle fünf Routen und `ingredients.show` entfernt. |
| Security-Header: egal wo, Hauptsache es läuft auf dem VPS | Als Laravel-Middleware umgesetzt (testbar, unabhängig von Nginx). Ob Nginx sie durchreicht und HTTPS korrekt erkannt wird, ist erst auf dem VPS prüfbar. |

## 3. Was behoben wurde

### Frontend (1a)

- **F-04 Unbeabsichtigtes Abmelden:** Das geschlossene User-Menü ist nicht mehr klickbar oder per Tab erreichbar. Escape und Klick außerhalb schließen es. Im Browser gemessen: 0 `POST /logout` beim Klick auf die frühere unsichtbare Fläche, bei 1440 und 390 px, für Nutzer und Gäste.
- **F-01 Passwort-Reset:** Das Formular sendet jetzt den POST an `password.update`.
- **F-02 Zutatensuche:** Tippen filtert (39 → 2 Einträge im Test), Ein- und Ausschluss funktionieren. Die erfundenen Kategorie-Chips und Zähler sind entfernt.
- **F-03 Meine Zutaten:** siehe Abschnitt 2.
- Nebenbei teilweise verbessert: F-05 und F-30 (das unsichtbare Dropdown verdeckt nichts mehr). Beide bleiben für Slice 3 offen.

### Backend (1b)

- **F-16 Tote Routen** entfernt.
- **F-46 Host-Rechte:** `is_host` ist nicht mehr mass-assignable. Neues Command: `php artisan user:make-host {email}` bzw. `--revoke`.
- **F-18, F-22 422 statt 500** bei fehlerhaften Suchparametern, unbekannter Bewertungsdimension, doppelter Alternative, zu langem Kategorienamen und beim Löschen einer als Alternative genutzten Zutat.
- **F-24 bis F-27 Import:**
  - höchstens 5 Starts pro Stunde und Nutzer (429), ein laufender Import pro Nutzer (409)
  - nur noch `https://`-Links, URL höchstens 255 Zeichen
  - generische deutsche Fehlermeldungen; der Rohfehler steht nur im Log, der API-Schlüssel wird geschwärzt
  - Temp-Dateien und die zu Gemini hochgeladene Datei werden auch im Fehlerfall gelöscht
  - Importe ohne Fortschritt gelten nach 15 Minuten als fehlgeschlagen
  - yt-dlp wird mit `--ignore-config`, `--no-exec` und `--` vor der URL aufgerufen
- **F-47 Security-Header:** `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Content-Security-Policy: frame-ancestors 'none'`, `Referrer-Policy`, `Permissions-Policy`, HSTS nur bei HTTPS. Die ausführliche CSP läuft als Report-Only und blockiert nichts. Betriebsnotiz: `SECURITY-HEADERS.md`.
- **F-64 Abhängigkeiten:** Laravel 12.39 → 12.72, Fortify 1.32 → 1.42, Inertia-Laravel 2.0.28, Vite 7.3.8, Vue 3.5.45, Tailwind 4.3.5. Das ungenutzte `sqlite3` ist entfernt.

## 4. Was Sie wissen sollten

1. **PHP-Version für das Deployment (F-72):** Das Lockfile verlangt jetzt PHP ≥ 8.4. Vor Slice 1 genügte 8.2. Der VPS läuft laut Ihrer Angabe mit 8.4.27; geprüft ist das nicht. Ist dort eine ältere Version aktiv, bricht `composer install` mit einer klaren Meldung ab – es geht nichts still kaputt.
2. **Neue Fortify-Abhängigkeiten (F-73):** Das Update bringt Passkey-Bibliotheken mit, die die App nicht nutzt. Zwei davon (`symfony/serializer`, `symfony/yaml`) habe ich bewusst auf die gepatchten 7.4-Versionen gelegt, weil die 8.0-Linie offene Advisories hatte und die gefixte 8.1 mit dem lokalen PHP 8.4.0 nicht installierbar war.
3. **Neue Framework-Route:** Laravel 12.72 registriert `PUT storage/{path}` für signierte Uploads. Der Vollständigkeitstest der Rollen-Matrix hat das sofort gemeldet. Per Test belegt: Unsignierte Requests werden mit 403 abgewiesen, auch für Hosts.
4. **Hosts anlegen:** Wer bisher `is_host` per Tinker oder Datenbank gesetzt hat, kann das weiter tun; neu gibt es das Artisan-Command. Bestehende Hosts sind nicht betroffen.
5. **Import-Limit:** Auch abgelehnte Versuche zählen. Ein Tippfehler im Link verbraucht einen der fünf Starts pro Stunde.
6. **Neue Fehlermeldungen sind im Frontend noch unsichtbar (F-74):** Die neuen 422-Antworten landen wie bisher nur in der Konsole. Das gehört zu den Formular-Arbeiten in Slice 3.
7. **Eigener Fehler unterwegs:** Ein von mir geschriebener Test wurde mit fehlendem Import committet (Suite brach mit Exit 255 ab) und im Folge-Commit korrigiert. Der Endstand ist grün.

## 5. Arbeitsweise

- Lead: `claude-fable-5-2`. Vier Spezialisten in isolierten Worktrees; alle vier liefen laut Fehlermeldung der API bzw. eigener Angabe mit `claude-opus-5-5`.
- Alle vier wurden kurz nach dem Start vom Nutzungslimit gestoppt und nach dessen Ablauf an derselben Stelle fortgesetzt.
- Dateizuständigkeiten waren getrennt; `routes/web.php` wurde von zwei Agenten an verschiedenen Zeilen geändert und ließ sich konfliktfrei mergen.
- Abweichung: Der Frontend-Agent hat `playwright.config.ts` um ein Projekt `regression` ergänzt (7 Zeilen), obwohl die Datei nicht in seiner Liste stand. Ohne das wäre die neue Regressions-Spec nicht gelaufen; geprüft und übernommen.
- Parallele Browser-Läufe blockierten sich am Standardport 8123. Jeder Lauf nutzt jetzt einen eigenen Port über `E2E_PORT`.
- Kein zusätzlicher Review-Agent. Stattdessen: beide PHP-Suiten und die komplette Browser-Suite nach jedem Merge bzw. auf dem integrierten Endstand im Hauptverzeichnis.

## 6. Geänderter Anwendungscode

22 Dateien, +826 / −282 Zeilen gegenüber `67a82d5` (`git diff 67a82d5 --stat -- app routes config bootstrap resources`):

- neu: `app/Console/Commands/MakeHost.php`, `app/Http/Middleware/SecurityHeaders.php`
- Controller: `Recipe`, `RecipeImport`, `Ingredient`, `IngredientAlternative`, `Category`
- `app/Jobs/ImportTikTokRecipe.php`, `app/Services/TikTokRecipeImporter.php`, `app/Providers/AppServiceProvider.php`, `app/Models/User.php`
- `bootstrap/app.php`, `config/queue.php`, `routes/web.php`, `routes/api.php`
- Frontend: `AppLayout.vue`, `IngredientSearchArea.vue`, `IngredientSearch.vue`, `MyIngredients.vue`, `ResetPassword.vue`, `rezeptly/app.css` (nur `.user-menu*`)
- Lockfiles: `composer.lock`, `package.json`, `package-lock.json`

Keine Migration, keine Änderung an der Datenbankstruktur.

Screenshots: 10 von 110 Baselines aktualisiert (Zutatensuche und „Meine Zutaten“, je 5 Viewports). Alle anderen Seiten sind pixelgleich, auch nach dem Paket-Update.

## 7. Wichtigste Befehle (Lead)

| Befehl | Exit |
|---|---|
| `git switch -c fix/slice-1-kritisch` | 0 |
| `composer update` (innerhalb der Versionsgrenzen) | 0 |
| `composer update "symfony/serializer:^7.4.23" "symfony/yaml:^7.4.23" --with-all-dependencies` | 0 |
| `composer audit` | 0 (keine Advisories) |
| `npm uninstall sqlite3`, `npm update` (beide mit `--ignore-scripts`) | 0 |
| `npm audit --omit=dev` | 0 |
| `npm run build` | 0 |
| `npx playwright test` nach dem Paket-Update | 0 (143 bestanden) |
| Merges der vier Agenten-Branches | 0, ohne Konflikt |
| `php artisan test` nach dem ersten Merge | 2 bzw. 255 (neue Framework-Route; eigener Testfehler) – behoben |
| `php artisan test` (final) | 0 (410) |
| `php artisan test --testsuite=KnownDefects` (final) | 1 (14 rot, beabsichtigt) |
| `E2E_PORT=8133 npx playwright test` (final, integriert) | 0 (151) |

## 8. Offen nach Slice 1

**Weiter rot in `KnownDefects` (14 Tests):** F-08 (E-Mail-Verifizierung, 3), F-12, F-13 (2), F-14, F-15 (2), F-17, F-19, F-20 (2), F-23.

**Weiter als erwarteter Defekt im Browser (16):** F-05, F-06, F-07, F-17, F-20, F-21, F-29, F-30, F-31, F-32, F-38, F-42.

**Nur auf dem Server prüfbar:**
- PHP-Version (F-72)
- ob die Security-Header hinter Nginx ankommen und HSTS gesetzt wird (`curl -I`, Prüfliste in `SECURITY-HEADERS.md`)
- Import Ende-zu-Ende mit Queue-Worker, echtem yt-dlp und echter Gemini-API; `retry_after` mit mehreren Workern; `Cache::lock` mit dem Produktions-Cache
- Schema-Stand der Produktions-DB (F-11) und welcher Stand dort überhaupt läuft

**CI:** weiterhin kein Lauf auf GitHub (kein Push).

## 9. Rollback

Branch `fix/slice-1-kritisch` nicht mergen bzw. verwerfen. `chore/slice-0-test-baseline`, `improvement/full-website-overhaul` und `master` sind unverändert. Lokal installierte Pakete lassen sich mit `composer install` / `npm ci` auf dem jeweiligen Branch zurücksetzen.

## 10. Empfehlung

**Slice 2 – Datenverlust beim Bearbeiten von Rezepten** (F-13, F-14/F-66, F-15, F-19, F-07/F-65, F-39, F-12). Die roten Tests dafür existieren bereits.

Vor einem Deployment, unabhängig von Slice 2, mit Ihrer ausdrücklichen Freigabe für genau diese lesenden Schritte auf dem VPS:
1. `php -v` und `composer --version`
2. welcher Git-Stand dort ausgecheckt ist
3. `php artisan migrate:status` und die Spalten von `ingredient_alternatives` (F-11)
4. welcher Datenbank-Treiber aktiv ist (E10)

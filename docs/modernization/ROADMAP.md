# Roadmap / Backlog

Stand: 2026-11-14 · Branch `fix/slice-3-navigation-formulare` · **Slice 0 bis 3 abgeschlossen; Slice 4 wartet auf Entscheidungen.**

Grundlage: `FINDINGS.md` (F-01 bis F-85; Abschnitt 11 Testlauf und Browser, Abschnitte 12 bis 14 Stand nach Slice 1 bis 3), `FEATURE-MATRIX.md` (Stand Audit), `SLICE-0-BERICHT.md`, `SLICE-1-BERICHT.md`, `SLICE-2-BERICHT.md`, `SLICE-3-BERICHT.md`, `DECISIONS.md`.

**Prioritäten:** P0 Datenverlust/Sicherheitsvorfall/kompletter Ausfall; P1 Kernfunktion kaputt; P2 merkliche UX-/Darstellungsprobleme; P3 Politur/optional.

## Gate A – Bestandsaufnahme (abgeschlossen)

- [x] Inventar, Feature-Matrix, Audit-Befunde, priorisierter Plan
- [x] Freigabe für Slice 0 erteilt (2026-11-13)

## Gate B – Stabilisierung

### Slice 0 – Testumgebung und Baseline · **abgeschlossen**

- [x] Lokale Umgebung: Composer (Herd-phar), `composer install`, `npm ci --ignore-scripts`, Build
- [x] Unveränderte Suite gemessen: 18 bestanden / 23 rot ohne Build, 27 / 14 mit Build
- [x] `TestCase` mit `withoutVite()`, HTTP- und Prozess-Sperre, `actingAsUser()`/`actingAsHost()`; fester Test-Key, `APP_DEBUG`/`APP_URL` fixiert
- [x] Factories für alle 11 Fachmodelle, `HasFactory` in den Modellen (einzige Änderung am Anwendungscode)
- [x] Charakterisierungstests: reguläre Suite **334 Tests grün**
- [x] Bekannte Defekte als eigene Suite `KnownDefects`: **36 Tests rot** (beabsichtigt, 19 Befunde)
- [x] Starter-Tests an echte Routen angepasst (Ist-Zustand, E1 bleibt offen)
- [x] CI: Trigger `master` + Pull Requests, Prüfmodus, `contents: read`, Audits; Altbestand als dokumentierte Ausnahmen
- [x] Browser-Baseline: Playwright, 5 Viewports, 3 Rollen, 110 Screenshots, **143 Tests** (121 regulär, 22 erwartete Defekte)
- [ ] Offen: erster echter CI-Lauf auf GitHub (braucht einen Push – nicht ohne Freigabe)

### Arbeitsweise ab Slice 1

Jeder Fix macht mindestens einen Test aus `tests/KnownDefects` bzw. ein `test.fail()` aus `tests/e2e/known-defects.spec.ts` grün. Der Test wandert dann in die reguläre Suite; der zugehörige grüne Ist-Test (`…_currently_…`) wird angepasst oder entfernt. Geänderte Seiten brauchen neue Screenshot-Baselines (`npm run e2e:update`), der Diff wird vorher angesehen.

### Slice 1 – Kritische Sicherheits- und Funktionsprobleme · **abgeschlossen (lokal)**

Ergebnis: reguläre Suite 410 grün, bekannte Defekte 36 → 14, Browser 151 grün (16 erwartete Defekte), beide Audits 0. Erledigt: F-01, F-02, F-04, F-16, F-18, F-22 (Kern), F-25, F-26, F-27, F-46, F-47, F-64; teilweise F-03 (Absturz behoben, Feature offen), F-05, F-24 (Server-Teil), F-30, F-67. Details: `SLICE-1-BERICHT.md`.

Ursprünglicher Plan (zur Nachvollziehbarkeit):

**1a – Frontend-Funktion**

| Befund | Änderung | Dateien |
|---|---|---|
| F-04 | Geschlossenes User-Menü nicht klickbar; Escape/Außenklick schließen | `AppLayout.vue`, `rezeptly/app.css` (Shell) |
| F-01 | `route('password.update')` | `pages/auth/ResetPassword.vue` |
| F-02 | Zutatensuche ohne `category` absichern, Dummy-Zähler entfernen | `IngredientSearchArea.vue`, `pages/IngredientSearch.vue` |
| F-03 | nach E3: Menüpunkt und Route entfernen oder Seite absichern | `AppLayout.vue`, `pages/MyIngredients.vue`, `routes/web.php` |

- **Akzeptanz:** die E2E-Tests zu F-01 bis F-04 laufen ohne `test.fail()`; PHPUnit F-02/F-03 je nach Entscheidung grün oder entfernt; keine neuen Konsolenfehler in der Baseline.
- **Risiko:** `AppLayout.vue` betrifft jede Seite → ein Besitzer, Screenshot-Diff aller 110 Aufnahmen prüfen.

**1b – Backend-Sicherheit**

| Befund | Änderung | Dateien |
|---|---|---|
| F-16 | drei tote Routen entfernen (E6) | `routes/web.php`, `routes/api.php` |
| F-46 | `is_host` aus `$fillable`; Artisan-Command `user:make-host` | `app/Models/User.php`, `CreateNewUser.php`, neues Command |
| F-22 | 422 statt 500: `search` als String, Bewertungsdimension prüfen, doppelte Alternative | `RecipeController.php`, `IngredientAlternativeController.php` |
| F-18 | 422 beim Löschen einer als Alternative genutzten Zutat | `IngredientController.php` |
| F-26, F-27 | Import: Rate-Limit, ein laufender Import pro Nutzer, generische Fehlermeldung, Gemini-Datei löschen | `routes/web.php`, `RecipeImportController.php`, `TikTokRecipeImporter.php`, `ImportTikTokRecipe.php` |
| F-64 | Abhängigkeiten innerhalb der Major-Version aktualisieren, `sqlite3` entfernen | `composer.lock`, `package.json`, `package-lock.json` |
| F-47 | Security-Header als Middleware (CSP zunächst Report-Only) – oder Entscheidung „in Nginx“ | `bootstrap/app.php`, neue Middleware |

- **Akzeptanz:** zugehörige KnownDefects-Tests grün und in die reguläre Suite verschoben; Rollen-Matrix und CSRF-Tests unverändert grün; `composer audit`/`npm audit` ohne critical.
- **Risiko:** F-64 kann Verhalten ändern → eigener Commit, beide Suiten und Browser-Baseline danach. F-46: vorher klären, wie Hosts heute angelegt werden.
- **Nicht in Slice 1:** F-08 (E-Mail-Verifizierung) – braucht E2 und Auskunft über Bestandskonten, sonst werden Nutzer ausgesperrt.
- **Aus Slice 1 offen geblieben:** Array-Größen und Obergrenzen (F-22), sichtbare Anzeige der neuen 422-Meldungen (F-74), CSP erzwingen (F-75).

### Slice 2 – Datenverlust beim Bearbeiten von Rezepten · **abgeschlossen (lokal)**

Ergebnis: reguläre Suite 465 grün, bekannte Defekte 14 → 7, Browser 164 grün (15 erwartete Defekte). Erledigt: F-07, F-12, F-13, F-14, F-15 (Feld entfernt), F-19, F-65, F-66; teilweise F-39 (Entfernen aus Sammlungen) und F-74 (Fehler in den Modals). Keine Migration. Details: `SLICE-2-BERICHT.md`.

Ursprünglicher Plan (zur Nachvollziehbarkeit):

| Befund | Änderung |
|---|---|
| F-13 | Alternativen im Update synchronisieren, einheitliche Speicherlogik im Modal |
| F-14, F-66 | mehrzeilige Schritte und „Notizen:“-Zeilen verlustfrei speichern |
| F-15 | Quelle speichern (additive Migration) oder Feld entfernen |
| F-19 | Bilddateien beim Löschen/Ersetzen nach Commit entfernen |
| F-07, F-65 | Bestätigung vor globalem Löschen/Umbenennen von Kategorien und Zutaten, mit Zahl betroffener Rezepte |
| F-39 | Entfernen aus Sammlungen mit Bestätigung oder Rückgängig |
| F-12 | Sortierung ohne fehlende Spalte (E5) |

- **Dateien:** `RecipeController.php`, `IngredientController.php`, `CategoryController.php`, `RecipeForm.vue`, `TagEditModal.vue`, `IngredientEditModal.vue`, `IngredientAlternativesModal.vue`, `collections/Show.vue`, ggf. eine additive Migration.
- **Vorbedingung für jede Migration:** F-11 (Schema-Abgleich der Produktion) und E10 – nur mit gesonderter Freigabe.

### Slice 3 – Navigation, mobile Darstellung, Formulare · **abgeschlossen (lokal)**

Ergebnis: reguläre Suite 509 grün, bekannte Defekte 7 → 4, Browser 212 grün (1 erwarteter Defekt: F-38), ESLint und `vue-tsc` 0. Erledigt: F-05, F-06, F-17, F-20 (durch Entscheidung), F-21, F-24, F-29, F-30, F-31, F-32, F-39, F-42, F-55, F-68, F-69, F-74; teilweise F-34, F-35, F-40, F-41, F-44, F-56, F-62, F-70. Details: `SLICE-3-BERICHT.md`.

Ursprünglicher Plan (zur Nachvollziehbarkeit):

| Befund | Änderung |
|---|---|
| F-05, F-29, F-69 | Such-Untermenü mobil in der Sidebar, Backdrop, Header-Höhe |
| F-21 | Gäste-Links auf öffentliche Seiten; Login-Ziel nach E1 |
| F-06, F-17, F-74 | alle Validierungsfehler im Rezeptformular sichtbar, Erfolgsmeldungen zentral |
| F-39 (Rest) | „Zur Sammlung hinzufügen“: Gast-Hinweis, Fehleranzeige |
| F-20 | 2FA-Seite wiederherstellen, Darstellung deaktivieren (E4) |
| F-30, F-31, F-32 | Modal-Ebene und Scroll-Sperre, Rezeptdetail-Kopf, Umbruch langer Titel |
| F-42, F-68 | `lang="de"`, Seitentitel, `h1`, deutsche Validierungstexte, Inertia-Fehlerseite |
| F-24 | Import: Zeitlimit und Abbrechen im Client (Server-Teil erledigt) |

### Slice 4 – Konto und Recht · **nächster Schritt, braucht Entscheidungen E2 und E9**

- F-08 (E2), F-10 (E9), F-48, F-60
- „Meine Zutaten“ als echtes Feature (eigene Zutaten speichern): eigenes Paket mit Datenmodell und Migration, nach Klärung des gewünschten Umfangs

## Gate C – Konsistentes Redesign (nach Gate B)

- [ ] Design-Tokens und eine Breakpoint-Skala (F-33); CSS-Layer und Dark Mode (F-34)
- [ ] Gemeinsame Bausteine: Button, Dialog, Formularfeld, Suchleiste, Sternebewertung, Auth-Layout (F-35, F-40, F-41)
- [ ] Barrierefreiheit: Tastatur-Alternativen, Kontrast nach Messung (F-38, F-43; axe-Zählung als Ausgangswert vorhanden)
- [ ] Leer-, Lade- und Fehlerzustände, Filter-Chips, Touch-Drag (F-36, F-44)
- Die Screenshot-Baseline aus Slice 0 ist der Vergleichsstand.

## Gate D – Qualitätsverbesserung

- [ ] Altbestand Lint/Typen bereinigen und CI-Ausnahmen entfernen (F-70)
- [ ] Performance: Pagination, N+1, Shared Props, Bilder (F-23, F-37)
- [ ] Refactoring: Form Requests, Recipe-Action, Policies (F-53, ggf. F-45 nach E7)
- [ ] Toter Code, Abhängigkeiten, Typecheck, Inertia-Links (F-51, F-52, F-54, F-57)
- [ ] Playwright in der CI (Linux-Baselines), Lauf gegen MySQL
- [ ] README, `.env.example` (F-62)

## Gate E – Release (gesonderte Freigabe)

- [ ] Staging mit synthetischen Daten, Schema-Abgleich (F-11)
- [ ] Runbook: Nginx, Queue-Worker, `storage:link`, env-Schlüssel, yt-dlp (F-49)
- [ ] CI grün, Smoke-Tests, Backup-/Rollback-Plan
- [ ] Manuelle Abnahme, erst danach Production

## Offene Entscheidungen

| # | Frage | Empfehlung | Blockiert |
|---|---|---|---|
| E1 | Ziel nach Login/Registrierung | **entschieden und umgesetzt:** `/home` | – |
| E2 | E-Mail-Verifizierung durchsetzen oder entfernen? | durchsetzen, nach Prüfung der Bestandskonten | Slice 4 |
| E3 | „Meine Zutaten“ bauen oder entfernen? | **entschieden:** bleibt, wird später ausgebaut | – |
| E4 | 2FA und Darstellung | **entschieden und umgesetzt:** keine 2FA, nur helle Darstellung | – |
| E5 | `sort_order`: Migration oder nach Name sortieren? | **umgesetzt:** nach Name (Empfehlung, nicht widersprochen) | – |
| E6 | API-Routen entfernen oder reparieren? | **entschieden:** entfernt | – |
| E7 | Mehrere Hosts geplant? | bei einem Host: dokumentieren | Gate D |
| E8 | Nutzerbewertungen/Kommentare bauen oder Landing-Text anpassen? | **entschieden und umgesetzt:** Text angepasst | – |
| E9 | Impressum/Datenschutz: wer liefert die Inhalte? | Betreiber | Slice 4 |
| E10 | Welche Datenbank läuft in Produktion? | – | F-11, F-12 |
| E11 | Security-Header in Laravel oder in Nginx? | **entschieden:** Laravel-Middleware; Wirkung auf dem VPS noch zu prüfen | – |
| E12 | Umfang von „Meine Zutaten“: nur Liste vorhandener Zutaten, oder auch Mengen/Haltbarkeit und „Was kann ich kochen?“ | zuerst nur die Liste | Feature-Paket |

## Verbleibende Risiken

- **CI ist ungetestet:** Die Workflows liefen noch nie auf GitHub.
- **PHP ≥ 8.4 nötig (F-72):** Das Lockfile verlangt seit dem Paket-Update PHP 8.4; die Version auf dem VPS ist ungeprüft.
- **Security-Header und Import-Härtung** sind nur lokal mit Fakes geprüft, nicht hinter Nginx bzw. mit echtem Worker.
- **Nur SQLite geprüft:** F-12 und die Längenverletzung aus F-22 zeigen sich erst unter MySQL/Postgres.
- **Produktion unbekannt:** Schema (F-11), Treiber, Header, Worker, ausgelieferter Stand.
- **Screenshot-Baseline gilt nur für Windows/Chromium** und ohne die Web-Schrift (fremde Hosts sind im Test blockiert).
- **Rechtliche Punkte** (F-10, F-48, F-58) brauchen eine Bewertung durch einen Menschen.

## Nächste empfohlene Aufgabe

**Slice 4 – Konto und Recht**, sobald E2 (E-Mail-Verifizierung: durchsetzen mit Bestandskonten als verifiziert markieren, oder abschalten) und E9 (Inhalte für Impressum und Datenschutz) geklärt sind. Danach Gate C (konsistentes Redesign) und – wenn lokal alles fertig ist – die VPS-Prüfung und das Release (Gate E). Parallel sinnvoll: lesende Prüfung des VPS (PHP-Version, ausgecheckter Stand, Schema) – nur mit ausdrücklicher Freigabe für genau diese Befehle.

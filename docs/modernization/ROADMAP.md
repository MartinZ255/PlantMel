# Roadmap / Backlog

Stand: 2026-11-13 · Branch `chore/slice-0-test-baseline` · **Slice 0 abgeschlossen; Slice 1 wartet auf Freigabe.**

Grundlage: `FINDINGS.md` (F-01 bis F-71, Abschnitt 11 mit den Ergebnissen aus Testlauf und Browser), `FEATURE-MATRIX.md`, `SLICE-0-BERICHT.md`.

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

### Slice 1 – Kritische Sicherheits- und Funktionsprobleme · **nächster Schritt, Freigabe nötig**

Zwei Pakete mit getrennten Dateien, parallel umsetzbar.

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

### Slice 2 – Datenverlust beim Bearbeiten von Rezepten

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

### Slice 3 – Navigation, mobile Darstellung, Formulare

| Befund | Änderung |
|---|---|
| F-05, F-29, F-69 | Such-Untermenü mobil in der Sidebar, Backdrop, Header-Höhe |
| F-21 | Gäste-Links auf öffentliche Seiten; Login-Ziel nach E1 |
| F-06, F-17 | alle Validierungsfehler sichtbar, Erfolgsmeldungen zentral |
| F-20 | 2FA-Seite wiederherstellen, Darstellung deaktivieren (E4) |
| F-30, F-31, F-32 | Modal-Ebene und Scroll-Sperre, Rezeptdetail-Kopf, Umbruch langer Titel |
| F-42, F-68 | `lang="de"`, Seitentitel, `h1`, deutsche Validierungstexte, Inertia-Fehlerseite |
| F-24 | Import-Zeitlimit und Abbrechen |

### Slice 4 – Konto und Recht (braucht Entscheidungen)

- F-08 (E2), F-10 (E9), F-48, F-60

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
| E1 | Ziel nach Login/Registrierung: `/home` oder `/`? Der Code setzt heute bewusst `/`. | `/home` | Slice 3 |
| E2 | E-Mail-Verifizierung durchsetzen oder entfernen? | durchsetzen, nach Prüfung der Bestandskonten | Slice 4 |
| E3 | „Meine Zutaten“ bauen oder entfernen? | vorerst entfernen | Slice 1a |
| E4 | 2FA und Darstellung: wiederherstellen oder deaktivieren? | 2FA wiederherstellen, Darstellung deaktivieren | Slice 3 |
| E5 | `sort_order`: Migration oder nach Name sortieren? | nach Name | Slice 2 |
| E6 | API-Routen entfernen oder reparieren? | entfernen | Slice 1b |
| E7 | Mehrere Hosts geplant? | bei einem Host: dokumentieren | Gate D |
| E8 | Nutzerbewertungen/Kommentare bauen oder Landing-Text anpassen? | Text anpassen | Slice 3 |
| E9 | Impressum/Datenschutz: wer liefert die Inhalte? | Betreiber | Slice 4 |
| E10 | Welche Datenbank läuft in Produktion? | – | F-11, F-12 |
| E11 | Security-Header in Laravel oder in Nginx? | Laravel-Middleware (testbar) | Slice 1b |

## Verbleibende Risiken

- **CI ist ungetestet:** Die Workflows liefen noch nie auf GitHub.
- **Nur SQLite geprüft:** F-12 und die Längenverletzung aus F-22 zeigen sich erst unter MySQL/Postgres.
- **Produktion unbekannt:** Schema (F-11), Treiber, Header, Worker, ausgelieferter Stand.
- **Screenshot-Baseline gilt nur für Windows/Chromium** und ohne die Web-Schrift (fremde Hosts sind im Test blockiert).
- **Rechtliche Punkte** (F-10, F-48, F-58) brauchen eine Bewertung durch einen Menschen.

## Nächste empfohlene Aufgabe

Freigabe von **Slice 1** (1a und 1b), dazu die Entscheidungen E3, E6 und E11.

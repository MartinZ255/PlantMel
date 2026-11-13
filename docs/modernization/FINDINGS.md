# Audit Findings – Gate A

Stand: 2026-11-13 · Branch `improvement/full-website-overhaul` · Commit `c7227e4`

## 1. Methode und Grenzen

- **Rein statische Analyse.** `vendor/`, `node_modules/`, `.env` und die SQLite-Datei fehlen lokal, `composer` ist nicht im PATH. Es gab keinen Build, keinen Dev-Server, keinen Browser, keine Testausführung und keinen Netzwerkzugriff.
- **Kein Test wurde ausgeführt.** Alle Aussagen über bestehende oder scheiternde Tests sind aus dem Code abgeleitet.
- **Keine Screenshots, keine gemessenen Kontraste, keine Performance-Zahlen.** Kontrastwerte sind aus hart kodierten Farben gerechnet.
- **Keine Produktion.** plantmel.de, der VPS, die Produktions-DB und die GitHub-Einstellungen wurden nicht angesehen.
- **Fortify-Verhalten** (Routennamen, Redirects, `verified`-Middleware) stammt aus dem bekannten Standard von Fortify 1.32 / Laravel 12, nicht aus `vendor/`.
- Anwendungscode wurde nicht verändert; geändert wurden nur `FINDINGS.md`, `FEATURE-MATRIX.md` und `ROADMAP.md`.

**Agenten:** sechs parallele, lesende Audits (Architektur, Frontend, UX, Backend, QA, Security). Lead: `claude-fable-5-2`. Die Spezialisten wurden nach Rückfrage mit dem Modell-Alias `opus` (Opus 5.5) gestartet; die exakte Modell-ID der Subagenten gibt das Werkzeug nicht zurück und ist daher nicht unabhängig bestätigt.

**Status-Begriffe**

| Status | Bedeutung |
|---|---|
| verifiziert | folgt direkt aus dem Code (Datei:Zeile); Laufzeitbestätigung steht noch aus |
| verifiziert + Stichprobe | zusätzlich vom Lead am Code gegengeprüft |
| Hypothese | plausibel, braucht Browser, Testlauf oder Serverprüfung |

**Prioritäten:** P0 Datenverlust / Sicherheitsvorfall / Totalausfall · P1 Kernfunktion kaputt · P2 merkliche UX-/Darstellungsprobleme · P3 Politur.

Quell-IDs in Klammern verweisen auf die Agentenberichte (ARCH, FE, UX, BE, QA, SEC).

## 2. Überblick

| Priorität | Anzahl | Kern |
|---|---|---|
| P0 | 0 verifiziert | ein unverifiziertes Risiko mit P0/P1-Potenzial: Schema-Drift der Produktions-DB (F-11) |
| P1 | 10 | kaputte Seiten und Formulare, unsichtbar klickbares Abmelden, fehlende Testbasis, wirkungslose E-Mail-Verifizierung, fehlende Rechtstexte |
| P2 | 39 | Datenverlust beim Rezept-Update, 500er-Routen, Responsive-/Overlay-Fehler, Barrierefreiheit, Import-Robustheit, Härtung |
| P3 | 14 | Aufräumen, Konsistenz, Performance, Lieferkette |

**Ohne Befund (positiv):** kein `v-html`, kein `{!! !!}`, kein Raw-SQL; yt-dlp wird als Argument-Array ohne Shell aufgerufen; die TikTok-URL wird mit verankertem Regex geprüft; der Gemini-Schlüssel steht im Header, nicht in der URL; Besitzprüfung bei Sammlungen und Importen; `is_host` wird bei der Registrierung fest auf `false` gesetzt; in der gesamten Git-Historie keine eingecheckten Secrets, `.env`- oder DB-Dateien; `php -l` über alle PHP-Dateien ohne Syntaxfehler; kein Zoom-Lock im Viewport; Doppel-Submit-Schutz in den Inertia-Formularen.

## 3. P1 – Kernfunktion kaputt

| ID | Bereich | Beobachtung / Beleg | Reproduktion | Vorschlag | Test | Status |
|---|---|---|---|---|---|---|
| F-01 | Auth | Passwort-Reset-Formular ruft `route('password.store')` auf (`resources/js/pages/auth/ResetPassword.vue:118`). Fortify benennt den POST `/reset-password` als `password.update`; Ziggy wirft dann einen Fehler, es geht kein Request raus. (ARCH-01) | Reset-Link anfordern, `/reset-password/{token}` öffnen, absenden → Konsolenfehler | `route('password.update')` | E2E-Smoke des Formulars; Ziggy-Namensabgleich als Test | verifiziert + Stichprobe; Routenname Hypothese (vendor fehlt) |
| F-02 | Zutatensuche | `IngredientController::index` liefert nur `id`, `name` (`IngredientController.php:13-20`); `IngredientSearchArea.vue:209,222,232,244` ruft `ingredient.category.map/.join` auf → TypeError bei erster Eingabe oder Tag-Auswahl. (UX-03, FE-04) | `/zutatensuche`, „a“ tippen | `category ?? []` absichern; Dummy-Kategorien entfernen oder Backend liefert echte Daten | Vitest-Komponententest mit `{id,name}`; Feature-Test Prop-Struktur | verifiziert + Stichprobe |
| F-03 | Meine Zutaten | Seite übergibt kein `ingredients` (`MyIngredients.vue:4-9`), Komponente erwartet es als Pflicht-Prop (`IngredientSearchArea.vue:158,251`) → TypeError beim ersten Render. Sidebar-Eintrag ist ein `<button>` ohne Aktion (`AppLayout.vue:137-139`). Kein Backend, nur `console.log`/TODO (`MyIngredients.vue:110-111`). (UX-02, FE-05, QA-18) | eingeloggt `/meineZutaten` öffnen | Produktentscheidung: umsetzen oder Route und Menüpunkt entfernen | Feature-Test Props; Komponententest | verifiziert + Stichprobe |
| F-04 | App-Shell | Geschlossenes User-Dropdown ist nur `opacity: 0`, ohne `visibility`/`pointer-events`, bei `z-index: 1200` (`rezeptly/app.css:111-133`); Einträge werden immer gerendert (`AppLayout.vue:45-84`). Unsichtbare Fläche unter dem Avatar löst „Abmelden“ bzw. „Einstellungen“ aus und blockiert Inhalte darunter. (FE-01, UX-23) | angemeldet, Menü zu, ca. 20–100 px unter dem Avatar klicken | geschlossen `visibility: hidden; pointer-events: none` oder `v-show`; Escape und Außenklick schließen | Playwright: Klick auf diese Koordinaten navigiert nicht (390 und 1280 px) | verifiziert + Stichprobe; Klickfläche im Browser bestätigen |
| F-05 | App-Shell mobil | `SidebarSearchSubmenu` liegt außerhalb von `<aside>` (`AppLayout.vue:166`), wird ≤900 px `position: static` (`rezeptly/app.css:2863-2892`) und landet hinter dem fixierten Drawer (`z-index: 1000`). „Rezeptsuche“ und „Zutatensuche“ sind mobil über die Sidebar nicht erreichbar. (FE-02) | 390×844, ☰ → „Suche“ | Untermenü mobil als Akkordeon innerhalb der Sidebar | Playwright mobil: beide Links sichtbar und navigierbar | verifiziert + Stichprobe (Struktur); Optik im Browser bestätigen |
| F-06 | Rezeptformular | `RecipeForm.vue` zeigt Fehler nur für `name`, `description`, `image` (`:17,31,133`). Server verlangt `ingredients.*.name` und `steps.*` (`RecipeController.php:335,342,604,611`); das Formular startet mit einer leeren Zeile und einem leeren Schritt (`:530-536`). Speichern scheitert ohne sichtbare Meldung. (BE-04, UX-05, FE-14) | Host, `/rezepte/erstellen`, nur Titel ausfüllen, speichern | Fehler für alle Keys anzeigen, Fehlerzusammenfassung, Fokus aufs erste Feld; leere Zeilen vor dem Senden entfernen | Feature-Test `assertSessionHasErrors('steps.0')`; E2E Meldung sichtbar | verifiziert |
| F-07 | Kategorien/Zutaten | „Löschen“ im `TagEditModal.vue:31-38,129-147` sendet ohne Rückfrage `DELETE`; `CategoryController.php:49-60` löst die Kategorie von allen Rezepten und löscht sie. Gleiches Muster im `IngredientEditModal.vue:125-143` (Backend blockiert dort verwendete Zutaten). Umbenennen wirkt ebenfalls global. (UX-06, FE-15) | Host, Tag im Formular doppelklicken, „Löschen“ | Bestätigung mit Name und Zahl betroffener Rezepte | E2E: ohne Bestätigung kein DELETE; Feature-Test dokumentiert Pivot-Verhalten | verifiziert |
| F-08 | Auth | `User` implementiert `MustVerifyEmail` nicht (`app/Models/User.php:10`), obwohl `Features::emailVerification()` aktiv ist (`config/fortify.php:149`) und `routes/web.php:40` `verified` nutzt. Die Middleware lässt alle durch, es wird keine Verifizierungsmail verschickt. `auth.user` enthält kein `email_verified_at` (`HandleInertiaRequests.php:51-57`), der Hinweis in `settings/Profile.vue:113` erscheint nie. (SEC-01, ARCH-06, UX-09, QA-08, BE-12) | `POST /register` mit beliebiger Adresse, dann `GET /home` → 200 | Entscheidung per ADR: Interface implementieren (vorher Bestandskonten klären) oder Feature samt `verified` entfernen | Feature-Test: unverifizierter Nutzer wird auf `verification.notice` umgeleitet | verifiziert + Stichprobe (Code); Laufzeit Hypothese |
| F-09 | QA / CI | Keine grüne Baseline: laut Analyse scheitern voraussichtlich 14 der 41 Tests in CI (8× `route('dashboard')` existiert nicht, 4× Redirect-Ziel `/` statt `/home`, 2× fehlende Seite `settings/TwoFactor`); lokal ohne Build weitere 9 wegen fehlendem Vite-Manifest (kein `withoutVite()`). 0 von 35 Fachrouten sind getestet. CI triggert nur auf `develop`/`main` (`tests.yml:3-11`, `lint.yml:3-11`), das Repo nutzt `master`. 11 Factories und `HasFactory` in den Fachmodellen fehlen. (QA-01, QA-02, QA-14, QA-16, ARCH-17) | – | Testumgebung herstellen, Baseline-Suite anlegen, Starter-Tests anpassen, CI-Trigger korrigieren | Suite einmal ausführen und Prognose bestätigen | verifiziert (Code); Testergebnisse nicht ausgeführt |
| F-10 | Recht | Kein Impressum, keine Datenschutzerklärung: Grep nach `impressum`, `datenschutz`, `privacy`, `imprint` in `resources/js` und `routes` ohne Treffer. Gleichzeitig Registrierung, Drittanbieter-Schrift (F-58) und Übermittlung an Google (F-48). (SEC-02) | – | Statische Seiten anlegen und im Footer und bei der Registrierung verlinken; Inhalt rechtlich prüfen lassen | Smoke: `/impressum`, `/datenschutz` liefern 200 und sind verlinkt | verifiziert (Code); rechtliche Bewertung durch Menschen nötig |

## 4. P2 – Merkliche Probleme

### 4.1 Daten und Backend

| ID | Bereich | Beobachtung / Beleg | Reproduktion | Vorschlag | Test | Status |
|---|---|---|---|---|---|---|
| F-11 | Schema / Produktion | Commit `e27ac31` (11.12.2025) hat die Migration `2025_11_30_223356_create_ingredient_alternatives_table.php` nachträglich umgebaut (`recipe_id`, neuer Unique-Index) und `…_create_category_ingredient_table.php` gelöscht. Eine vorher migrierte DB hätte ein anderes Schema; `migrate` korrigiert das nicht. **P0/P1, falls die Produktion betroffen ist.** (ARCH-05, BE-12) | nur lesend auf Staging-Kopie: `migrate:status`, Spaltenliste – nur mit Freigabe | Schema-Abgleich auf Staging, danach additive Reparatur-Migration mit `hasColumn`-Guards | Migrationstest frische DB + Schema-Diff Staging | Historie verifiziert; Zustand der Produktion unbekannt |
| F-12 | Schema | `orderBy('sort_order')` auf `categories` und `rating_dimensions` (`routes/web.php:107,109`, `RecipeController.php:303,306,517,520`), keine Migration legt die Spalte an. SQLite wertet den unbekannten Bezeichner als String-Literal (per In-Memory-PDO nachgestellt): kein Fehler, keine Sortierung. Unter MySQL/Postgres: 500 auf Rezept anlegen und bearbeiten. **P1, falls die Produktion nicht SQLite nutzt.** (ARCH-04, BE-07, QA-05) | Feature-Test gegen MySQL | `orderBy('name')` oder additive Migration | Feature-Test `recipes.create`/`edit` als Host; CI-Job gegen MySQL | verifiziert + Stichprobe; Produktions-Treiber unbekannt |
| F-13 | Rezept-Update | `update()` validiert `alternativeIngredientIds` (`RecipeController.php:605-606`), speichert sie aber nie (`:657-674`); `store()` tut es (`:409-418`). Alternativen zu neuen Zutaten gehen verloren, Alternativen entfernter Zutaten bleiben verwaist. Das Modal speichert im Bearbeitungsmodus teils sofort per API, teils als Entwurf (`IngredientAlternativesModal.vue:156-158,290-358`). (BE-01, ARCH-07, QA-09, UX-18) | Rezept bearbeiten, neue Zutat tippen, Alternative wählen, speichern | Alternativen in `update()` synchronisieren; einheitliche Speicherlogik | Feature-Test Update mit Alternativen, DB-Assertions | verifiziert + Stichprobe |
| F-14 | Rezept-Schritte | Schritte werden als nummerierter Text gespeichert (`RecipeController.php:451-475`) und beim Lesen an jedem Zeilenumbruch getrennt (`:65-97`, `:553-579`). Ein mehrzeiliger Schritt wird bei jedem Bearbeiten zu mehreren Schritten. (BE-02, QA-20) | Schritt „A⏎B“ speichern, bearbeiten → zwei Schritte | minimal: Zeilenumbrüche im Schritt normalisieren; mittelfristig strukturierte Ablage (ADR) | Roundtrip-Test | verifiziert |
| F-15 | Rezept-Quelle | `source` wird validiert (`RecipeController.php:326,595`), aber es gibt keine Spalte; `show`/`edit` liefern fest `null` (`:150,536`). Auch die TikTok-Herkunft geht verloren (`TikTokRecipeImporter.php:349`). (BE-03, ARCH-15) | Quelle eintragen, speichern, neu laden | additive Migration `source` oder Feld entfernen | `assertDatabaseHas` | verifiziert |
| F-16 | Routen | Drei Routen zeigen auf nicht vorhandene Methoden: `IngredientController@show` (`routes/web.php:35`), `CategoryController@index` (`routes/api.php:9`), `RecipeController@destroy` (`routes/api.php:16`). `GET /ingredients/{id}` und `GET /api/categories` liefern 500, öffentlich erreichbar. Die API-Schreibrouten sind ohne Session-Guard nie nutzbar. Das Frontend ruft keine davon auf. (ARCH-02/03, BE-06, SEC-12, QA-06/07, UX-11) | `GET /ingredients/1` | tote Routen entfernen oder Methoden ergänzen | Routen-Integritätstest (`method_exists` je Route) | verifiziert + Stichprobe |
| F-17 | Rückmeldung | Flash-Meldungen kommen nie an: Controller setzen `->with('success', …)` (`RecipeController.php:447,714,730`, `CollectionController.php:57,115,130,151`), `HandleInertiaRequests::share` teilt kein `flash`, kein Vue-Code liest es. Profil- und Passwortseite zeigen ebenfalls keine Bestätigung. (BE-05, ARCH-12, UX-04) | Sammlung anlegen → keine Meldung | `flash` teilen, zentrale Live-Region im Layout | `assertInertia` auf `flash.success` | verifiziert + Stichprobe |
| F-18 | Zutaten löschen | Löschen einer Zutat, die nur als Alternative genutzt wird, endet in FK-Verletzung (500): `IngredientController.php:64-78` prüft nur `recipeIngredients()`; `alternative_ingredient_id` hat kein `onDelete`. (BE-10, ARCH-08) | solche Zutat im Modal löschen | zusätzlich `alternativeFor()->exists()` prüfen → 422 | Feature-Test erwartet 422 | verifiziert (Code); Laufzeit Hypothese |
| F-19 | Dateien | Rezept löschen entfernt die Bilddateien nicht (`RecipeController.php:717-731`); im Update werden alte Dateien vor dem Commit gelöscht (`:693-709`), im Store bleibt die Datei bei Rollback liegen (`:435-441`). Gelöschte Bilder bleiben per URL abrufbar. (BE-09, ARCH-09, SEC-15, QA-19) | Rezept mit Bild löschen, Datei prüfen | Dateien nach Commit löschen | `Storage::fake('public')` + `assertMissing` | verifiziert |
| F-20 | 2FA / Darstellung | `settings/Appearance` und `settings/TwoFactor` werden gerendert (`routes/settings.php:24-29`, `TwoFactorAuthenticationController.php:32`), die Vue-Dateien fehlen. 2FA ist in Fortify aktiv, aber nicht einrichtbar; die 2FA-Challenge zeigt keine Fehlermeldung (`TwoFactorChallenge.vue:41-89`). (ARCH-11, QA-04, UX-08, UX-10, FE-28) | `/settings/appearance` direkt aufrufen | Entscheidung per ADR: Seiten wiederherstellen (Vorlage im Commit `7c6951e`) oder Feature deaktivieren | `assertInertia` je Seite | verifiziert |
| F-21 | Login-Redirect | Nach Login und Registrierung landet man auf `/` statt `/home`: `config/fortify.php:76` `home => '/'`; `Fortify::redirects('login','/')` in `FortifyServiceProvider.php:93-100` ist vermutlich ein Getter ohne Wirkung. Logo und „Start“ zeigen für Gäste auf die geschützte Route `home` (`AppLayout.vue:16,110`); die Landing verlinkt die Rezeptliste nicht (`Landing.vue:51-65`). (ARCH-10, QA-10, UX-01, UX-30, FE-16) | als Gast Logo klicken → Login | Ziel festlegen (ADR), `home` setzen; Gäste-Links auf `landing`/`recipes.index` | Feature-Test Redirect; E2E Gast | Ziel verifiziert; Fortify-Verhalten Hypothese |
| F-22 | Validierung | Lücken, die zu 500 statt 422 führen: `ratings.*`-Schlüssel nicht gegen `rating_dimensions` geprüft (`RecipeController.php:422-432`); `?search[]=x` → `TypeError` in `trim()` (`:167-188`); doppelte Alternative per API (`IngredientAlternativeController.php:40`); keine `max` bei mehreren Strings, Kategorie `max:255` gegen Spalte 100; unbegrenzte Array-Größen. (SEC-13, BE-11, ARCH-19) | `GET /rezepte?search[]=a` | Form Requests mit `max`, `exists`, `string` | Datenprovider: 422 statt 500 | verifiziert (Code); Laufzeit Hypothese |
| F-23 | Performance | Rezeptliste lädt alle Rezepte ohne Pagination und mit ungenutztem Eager Loading (`RecipeController.php:190-191,246-273`); N+1 in `CollectionController.php:28` trotz `withCount`; `HandleInertiaRequests.php:39,58-68` lädt bei jedem Request alle Sammlungen und ein ungenutztes Zitat. (BE-16/17, ARCH-14) | – | ungenutzte Relationen entfernen, `recipes_count` nutzen, Lazy Props; Pagination mit Frontend abstimmen | Query-Count-Test | verifiziert; Auswirkung nicht gemessen |

### 4.2 TikTok-Import

| ID | Bereich | Beobachtung / Beleg | Reproduktion | Vorschlag | Test | Status |
|---|---|---|---|---|---|---|
| F-24 | Import-Ablauf | Ohne Queue-Worker bleibt der Import dauerhaft `pending`; der Client pollt alle 3 s ohne Obergrenze und ohne Abbrechen (`recipes/Create.vue:130-158`). Nach Erfolg lädt `router.visit` das Formular neu, bereits Eingegebenes geht verloren (`:140`). Kein Label, keine Live-Region. (BE-13, UX-17) | Import starten ohne laufenden Worker | Zeitlimit im Client, serverseitig veraltete Importe als `failed` melden, Abbrechen, Warnung vor Überschreiben | Vitest mit Fake-Timern; Feature-Test mit `travel` | verifiziert |
| F-25 | Import-Timeouts | Job `timeout=600` (`ImportTikTokRecipe.php:15-17`) gegen `retry_after=90` (`config/queue.php:43`); die Teil-Timeouts im Service summieren sich auf deutlich über 600 s. Bei mehreren Workern kann der Status springen. (BE-14, ARCH-16) | – | `retry_after` > `timeout`, Teil-Timeouts kürzen, Status nur vorwärts | Unit-Test Zustandsfolge mit Fakes | Konfiguration verifiziert; Laufzeit Hypothese |
| F-26 | Import-Missbrauch | Kein `throttle` auf `recipeImports.store` (`routes/web.php:143`), kein Limit paralleler Importe, Video bis 80 MB wird per `file_get_contents` komplett in den Speicher geladen (`TikTokRecipeImporter.php:100,144`), URL ohne `max`. Ein (übernommener) Host-Account kann Worker, Speicher und Gemini-Kontingent belasten. (SEC-05) | als Host wiederholt importieren | Rate-Limit pro Nutzer, ein laufender Import pro Nutzer, Stream-Upload, `--max-filesize` | Feature-Test: 6. Import → 429 | verifiziert (Code); Last nicht gemessen |
| F-27 | Import-Aufräumen | Temp-Dateien (`.part`) bleiben bei fehlgeschlagenem Download liegen; zu Gemini hochgeladene Dateien werden nie gelöscht; Fehlertexte von yt-dlp/Gemini (bis 400 Zeichen, evtl. Serverpfade) gehen an den Nutzer (`RecipeImportController.php:39`). Importierte Tags ohne passende Kategorie werden still verworfen. (BE-15, SEC-07, SEC-08) | – | `finally`-Aufräumen per `glob`, `DELETE` der Gemini-Datei, generische Nutzermeldung | Unit-Tests mit `Process::fake`/`Http::fake` | verifiziert |
| F-28 | Tests / Import | In Tests läuft die Queue `sync` (`phpunit.xml:29`): Ein Test auf `recipeImports.store` ohne Fakes würde echtes yt-dlp starten und die Gemini-API aufrufen. (QA-12) | – | `Http::preventStrayRequests()`, `Process::preventStrayProcesses()` in `TestCase` | – | verifiziert |

### 4.3 Darstellung und Responsive

| ID | Bereich | Beobachtung / Beleg | Reproduktion | Vorschlag | Test | Status |
|---|---|---|---|---|---|---|
| F-29 | Sidebar mobil | `.sidebar-backdrop { display: none !important; }` (`rezeptly/app.css:2735-2737`) gilt immer; die mobile Regel (`:2827-2832`) setzt kein `display`. Kein Abdunkeln, Tippen daneben schließt den Drawer nicht. (FE-03) | ≤900 px, ☰, daneben tippen | `!important` entfernen, Desktop per `min-width: 901px` ausblenden | E2E Backdrop schließt Drawer | verifiziert + Stichprobe |
| F-30 | Modals mobil | `.modal-backdrop` hat `z-index: 40`, mobiler Header 1100, Sidebar 1000 (`rezeptly/app.css:2516-2524,2815,2841`). Modals liegen unter Header und Sidebar; kein Scroll-Lock, kein Teleport. (FE-08) | 360×640, Rezept bearbeiten, Buch-Icon | z-index-Skala als Variablen, Modal per `<Teleport>` | E2E Modaltitel sichtbar | verifiziert (CSS); Optik Hypothese |
| F-31 | Rezeptdetail | Aktionsbuttons absolut oben rechts überlagern Titel/Beschreibung ab 721 px (`rezeptly/app.css:891-904,1741-1752`); ohne Bild rutscht der Inhalt in die schmale Spalte des `2fr/3fr`-Grids (`:1706-1712`, `Show.vue:113`). (FE-06, FE-07) | 1024 px, Host, langer Titel | Aktionen in den Fluss, Modifier für „ohne Bild“ | visueller Regressionstest 768/1024/1280 | verifiziert (CSS); Ausmaß Hypothese |
| F-32 | Overflow | `overflow-x: hidden` auf fünf Ebenen (`rezeptly/app.css:53,72,205,394`, `app.css:92`), kein einziges `overflow-wrap`/`word-break`/`hyphens`, `.pill { white-space: nowrap }` (`:1800`). Lange Wörter werden bei 320–390 px abgeschnitten statt umbrochen. `html, body { height: 100% }` macht `body` vermutlich zum Scroll-Container. Zutatenzeile im Formular bricht erst ≤480 px um (`:2196-2201,2972-2975`). (FE-09, FE-10, FE-11) | 320 px, Rezept mit sehr langem Titel | `overflow-wrap`, Ursachen beheben statt abschneiden, Zutatenzeile als Grid | Playwright: `scrollWidth <= innerWidth` bei 320 px | Hypothese (CSS-Fakten verifiziert) |
| F-33 | Breakpoints | Sieben verschiedene Werte (900/720/640/600/540/480/420 px), alle `max-width`, mehrfach verstreute Blöcke; 0 Tailwind-Breakpoint-Präfixe; 900 px zusätzlich in JS dupliziert (`AppLayout.vue:205`); Header-Höhe hart als `50px` (`rezeptly/app.css:2838-2839`); `100vh` statt `dvh` (`:68,2558,2839`). Tabelle in Abschnitt 6. (FE-20) | – | Breakpoint-Skala als Tokens (ADR), schrittweise konsolidieren | Screenshot-Baseline je Breakpoint | verifiziert |
| F-34 | CSS-Architektur | `@import './rezeptly/app.css'` ohne Layer (`resources/css/app.css:5`): 3.241 Zeilen schlagen alle Tailwind-Utilities. Dark Mode: `.dark` wird gesetzt (`app.blade.php:8-31`), aber 0 `dark:`-Utilities und 64 + 92 Hex-Farben → gemischte Darstellung bei dunklem System. Zwei Token-Systeme parallel. 35 doppelt definierte Selektoren (u. a. `.modal`, `.filter-panel*`), mind. 15 tote Selektoren. (FE-18, FE-19, ARCH-18, UX-35) | System auf Dunkel, App öffnen | ADR: Rezeptly in `@layer components` oder Dark Mode bewusst deaktivieren; Duplikate abbauen | visueller Test `prefers-color-scheme: dark` | verifiziert (Code); Darstellung Hypothese |
| F-35 | Auth-Seiten | Zwei Designsysteme (Login/Register mit Klassen, fünf Seiten mit Tailwind-Hex); Titel mit alter Marke „favRezepte“; Inputs 14 px → iOS-Zoom; Login-Statusmeldung steht neben statt über dem Formular (`Login.vue:27-33`, `.auth-container` ohne `flex-direction`). (FE-21, FE-22) | Reset-Link anfordern, Login bei 390 px | gemeinsames `AuthLayout` und Input-Feld | Screenshots aller 7 Seiten bei 390 px | verifiziert (Code); iOS-Zoom Hypothese |
| F-36 | Touch-Drag | Drag & Drop nutzt Pointer-Events (gut), aber Griff nur 24×24 px, kein Auto-Scroll beim Ziehen, `window`-Listener ohne Cleanup, Autocomplete-Dropdown schließt nie (`RecipeForm.vue:221-238,640-748`). (FE-12, FE-13) | Smartphone, 8 Schritte, Schritt 1 ans Ende | 44 px Trefferfläche, Auto-Scroll, Hoch/Runter-Buttons, Cleanup | Playwright mit `hasTouch`; Unit-Test `move` | Code verifiziert; Bedienbarkeit Hypothese |
| F-37 | Bilder | Kein `loading="lazy"`, keine `width`/`height`, Uploads bis 16 MB werden im Original ausgeliefert (`RecipeController.php:352,621`); lokale `php.ini` erlaubt nur 2 MB Upload. (FE-23, BE-17) | – | `lazy`, feste `aspect-ratio`, Thumbnails, Limits angleichen | Lighthouse auf Staging | Code verifiziert; Performance nicht gemessen |

### 4.4 Bedienbarkeit und Barrierefreiheit

| ID | Bereich | Beobachtung / Beleg | Reproduktion | Vorschlag | Test | Status |
|---|---|---|---|---|---|---|
| F-38 | Tastatur | Nur per Zeiger bedienbar: Drag & Drop der Schritte/Zutaten (`RecipeForm.vue:195-205,333-343`, WCAG 2.1.1/2.5.7), Tag-Bearbeitung nur per `@dblclick` (`:164`), Autocomplete nur `@mousedown` (`:234`), Sammlungskarten und Rezept-Entfernen als `article`/`div @click` (`CollectionCard.vue:2`, `collections/Show.vue:46-56`). (UX-12, UX-13, UX-15, FE-36) | Seite nur mit Tastatur bedienen | Hoch/Runter-Buttons, sichtbarer Bearbeiten-Button, Combobox-Muster, echte Links/Buttons | Tastatur-E2E, axe | verifiziert |
| F-39 | Sammlungen | Im Modus „Rezepte entfernen“ löscht ein Klick sofort, ohne Bestätigung oder Rückgängig (`collections/Show.vue:84-105`). „Zur Sammlung hinzufügen“ ist für Gäste eine Sackgasse mit falscher Meldung; Fehler beim Hinzufügen werden verschluckt; `:disabled="!selectedCollectionIds"` ist nie wahr (`AddToCollectionModal.vue:56-71,101-106,120`). (UX-14, UX-16, FE-14) | als Gast Rezept öffnen, Button klicken | Entfernen-Button je Karte mit Rückgängig; Gast-Hinweis mit Login-Link | E2E Gast und Nutzer | verifiziert |
| F-40 | Menüs / Modals | Ausgeblendete Sidebar, Untermenü und Filterpanels bleiben per Tab fokussierbar; kein `aria-expanded`, kein Escape, kein Außenklick. Vier Modals ohne `role="dialog"`, Fokusfalle, Escape, Scroll-Sperre. (UX-23, UX-27) | Tab durch geschlossene Sidebar | gemeinsame Dialog-Komponente (z. B. das installierte `reka-ui`), `inert`/`visibility` | Playwright Fokus-Tests | verifiziert |
| F-41 | Formulare | Felder ohne Label (Suchfelder, Zutatenzeilen, Schritte, Notizen, TikTok-URL), doppelte IDs, kein `aria-describedby`/`aria-invalid` im gesamten Frontend, Pflichtfelder nicht markiert, `Register.vue` ohne `autocomplete`; Fehler aus axios-Aufrufen landen nur in `console.error` (`RecipeForm.vue:588-590,823-825`). Sternebewertung ohne zugängliche Namen und nicht zurücksetzbar (`:88-101`). (UX-19, UX-20, UX-32) | Screenreader/axe auf Formularseiten | Formularfeld-Komponente mit Label, Fehler, Verknüpfung | axe in Playwright | verifiziert |
| F-42 | Struktur | Die meisten Seiten ohne `<h1>` (Titel als `div`), verschachteltes `<main>` (`Show.vue:263`), kein Skip-Link, keine Live-Regionen, `<Head>`-Titel fehlen auf 11 Seiten (Fallback „Laravel“, `app.ts:9`), `lang` vermutlich `en` bei deutscher UI (`config/app.php:81`), keine deutschen Validierungstexte (`lang/` fehlt), keine eigenen Fehlerseiten für 403/404/419/500. (UX-24, UX-25, UX-26, UX-28, UX-29, FE-29) | `document.title` je Route prüfen | ein `h1` je Seite, Skip-Link, `<Head>`, `APP_LOCALE=de` + Sprachdateien, Inertia-Fehlerseite | axe, Feature-Test `lang="de"` | verifiziert (Code); `lang`-Wert der Produktion unbekannt |
| F-43 | Kontrast | Rechnerisch aus Hex-Werten: Weiß auf `#7DA879` (Primärbuttons, aktive Chips) ca. 2,7:1; `#877D6C` auf Weiß ca. 4,1:1; Sterne `#E8B453` auf Weiß ca. 1,9:1, Bewertung nur über Farbe. (UX-31) | – | Farbwerte anheben, Textäquivalent „x von 5“ | Messung mit axe/Lighthouse | Hypothese (gerechnet, nicht gemessen) |
| F-44 | Listen | Kein Leerzustand in Rezeptliste und Sammlungen, aktive Filter nach „Anwenden“ unsichtbar, Suche ohne Debounce bei jedem `keyup` (`RecipeSearchArea.vue:11`), erfundene Kategorien und Zähler in der Zutatensuche (`IngredientSearch.vue`, `MyIngredients.vue`), Home zeigt globale Rezeptzahl als „gespeicherte Rezepte“. (UX-21, UX-22, FE-25, FE-26) | Suche ohne Treffer | Leerzustände, Filter-Chips, Debounce, Dummydaten entfernen | E2E Leerzustand | verifiziert |

### 4.5 Sicherheit und Betrieb

| ID | Bereich | Beobachtung / Beleg | Reproduktion | Vorschlag | Test | Status |
|---|---|---|---|---|---|---|
| F-45 | Autorisierung | Keine Policies; `recipes` hat kein `user_id`. Jeder Host kann jedes Rezept, jede Kategorie, Zutat und Alternative ändern oder löschen (`RecipeController.php:587,717`, `IngredientAlternativeController.php:60`). Host-Routen verlangen kein `verified` (`routes/web.php:103`). **P1, falls es mehrere Hosts gibt.** (SEC-03, ARCH-13, QA-22) | Host B löscht Rezept von Host A | Entscheidung per ADR; ggf. `user_id` + Policies | Rollen-Matrix-Tests je Schreibroute | verifiziert; Bewertung hängt vom Betriebsmodell ab |
| F-46 | Rechte | `is_host` steht in `User::$fillable` (`app/Models/User.php:24`). Heute nicht ausnutzbar (`CreateNewUser.php:37`, `ProfileController.php:32` nutzen feste bzw. validierte Felder), aber latent. Es gibt keinen Weg, einen Host anzulegen (kein Command, kein Seeder). (SEC-04, ARCH-19) | – | aus `$fillable` entfernen, Artisan-Command `user:make-host` | `PATCH /settings/profile` mit `is_host=1` bleibt `false` | verifiziert + Stichprobe |
| F-47 | Header / Konfiguration | Keine Security-Header oder CSP im Code (`bootstrap/app.php:18-30`); `SESSION_SECURE_COOKIE` ohne Default (`config/session.php:172`); `.env.example` mit `APP_DEBUG=true`, `MAIL_MAILER=log` (Reset-Links im Log); kein `trustProxies`. Was Nginx bzw. die Produktions-`.env` setzen, ist unbekannt. (SEC-09, SEC-10) | – | Produktions-Checkliste, Header in Nginx oder Middleware, CSP zunächst Report-Only | Feature-Test auf Header; Serverprüfung mit Freigabe | Defaults verifiziert; Produktion unverifiziert |
| F-48 | Datenschutz Import | Komplettes Video und Caption gehen an die Gemini-API (`TikTokRecipeImporter.php:124-133,179`), ohne Hinweis, ohne Löschung, ohne belegten Auftragsverarbeitungsvertrag. (SEC-07) | – | Datei nach Analyse löschen, Datenschutzhinweis, Aufbewahrungsfrist für `recipe_imports` | Unit-Test: DELETE wird gesendet | verifiziert (Code); Aufbewahrung bei Google unverifiziert |
| F-49 | Deployment | Keine Deployment-Artefakte: keine Nginx-Vorlage, keine Worker-Unit, kein Scheduler, keine Bereinigung von `recipe_imports`/`failed_jobs`; `yt-dlp` als unversionierte Systemabhängigkeit; `CollectionController.php:77` erzeugt Bild-URLs über die Default-Disk statt `public`. (ARCH-20, BE-18) | – | Runbook, Disk vereinheitlichen | Staging-Smoke: Bild hochladen, Import Ende-zu-Ende | Repo verifiziert; Produktion unverifiziert |

## 5. P3 – Politur und technische Schulden

| ID | Bereich | Beobachtung / Beleg | Vorschlag | Status |
|---|---|---|---|---|
| F-50 | CI-Qualität | Lint-Workflow läuft im Fix-Modus (`lint.yml:33,36,39`) und kann nie scheitern; `contents: write` ohne Bedarf; `npm install` statt `npm ci`; kein `vue-tsc`, kein Audit-Schritt; Actions nur per Major-Tag. (QA-03, ARCH-17, SEC-17) | Prüfmodus, `contents: read`, `npm ci`, Typecheck, Audits | verifiziert |
| F-51 | Abhängigkeiten | Ungenutzt: `sqlite3` (native Install-Skripte), `reka-ui`, `class-variance-authority`, `@vueuse/core`; `axios` in 5 Dateien importiert, aber nicht in `package.json`; Rollup-Binaries auf 4.9.5 gepinnt bei Rollup 4.52.4; Wayfinder 0.1.x nur von einem verwaisten Composable genutzt, braucht aber PHP bei jedem Vite-Lauf. `composer audit`/`npm audit` nicht ausgeführt. (ARCH-18, SEC-17, QA-21/23, FE-27) | nach Build-Test bereinigen, `axios` deklarieren, Router-Entscheidung (ADR) | verifiziert |
| F-52 | Toter Code | `RecipeController::create()` ungenutzt (Route nutzt Closure), `RecipeList.vue`, `useInitials.ts`, `useTwoFactorAuth.ts`, `lib/utils.ts`, Modell `Comment` ohne Routen/UI, `components.json` verweist auf nicht vorhandene Pfade. (ARCH-13/18, FE-27, QA-17) | schrittweise entfernen | verifiziert |
| F-53 | Schichtung | `RecipeController.php` mit 732 Zeilen; Validierung und Schritt-Parser doppelt; Logik in Routen-Closures; Form Requests nur für Settings. (ARCH-13) | nach Charakterisierungstests: Form Requests, Action, Policy | verifiziert |
| F-54 | Navigation | `<a href>` statt Inertia-`<Link>` (volle Seitenneuladung), kein aktiver Zustand, `<a type="button">`, Sidebar-Zustand geht bei jedem Laden verloren. (FE-17, UX-30) | `<Link>`, persistentes Layout, `aria-current` | verifiziert |
| F-55 | Versprechen | Landing wirbt mit „Bewertungen und Notizen“ (`Landing.vue:33`); Nutzerbewertungen und Kommentare existieren nicht, `recipe_ratings` hat kein `user_id`. (UX-07, QA-17) | Text anpassen oder Feature per ADR planen | verifiziert |
| F-56 | Rezept-Editor | Kein Abbrechen beim Bearbeiten, keine Bildvorschau, Bild nicht entfernbar, keine Warnung bei ungespeicherten Änderungen; Reihenfolge der Zutaten nur implizit über Insert-Reihenfolge (kein `orderBy`). (UX-33, BE-12) | Abbrechen-Link, Vorschau, `orderBy('id')` oder `position` | verifiziert; Reihenfolge Hypothese |
| F-57 | Typen | 11× `any`, `AppPageProps` unvollständig, kein `vue-tsc`-Skript, `ssr.enabled => true` ohne SSR-Bundle und ohne Ziggy in `ssr.ts`. (FE-27, ARCH-18) | Typecheck-Skript, SSR per env abschalten | verifiziert; SSR-Verhalten Hypothese |
| F-58 | Externe Schrift | Instrument Sans von `fonts.bunny.net` (`app.blade.php:36-40`): IP der Besucher geht an Dritte. `@routes` legt alle Routen offen, auch Host-URIs. (SEC-16, FE-30) | Schrift selbst hosten, Ziggy filtern | verifiziert |
| F-59 | Uploads | Regel `image` + `max:16384` ohne `mimes`-/`dimensions`-Allowlist, EXIF bleibt erhalten. Ob `/storage` PHP ausführt, hängt von Nginx ab. (SEC-14) | Allowlist, neu kodieren, Nginx prüfen | Code verifiziert; Server unverifiziert |
| F-60 | Passwörter | Framework-Default (min. 8 Zeichen), Registrierung ohne Throttle. (SEC-11) | `Password::defaults()`, Throttle | verifiziert |
| F-61 | Portabilität | `ingredients.name` unique und `LIKE` verhalten sich je nach DB bei Groß/Klein und Umlauten unterschiedlich; `firstOrCreate` mit Race-Potenzial; Modellname `Collection` kollidiert mit `Illuminate\Support\Collection`. (ARCH-19, BE-11) | bei DB-Wechsel berücksichtigen | verifiziert; Verhalten Hypothese |
| F-62 | Dokumentation | Keine README; `.env.example` ohne `GEMINI_API_KEY`, `GEMINI_MODEL`, `YTDLP_PATH`; `composer.json` trägt noch den Namen des Starter-Kits. (Inventar §12) | README und `.env.example` ergänzen | verifiziert |
| F-63 | Testdaten | `UserFactory` erzeugt Nutzer standardmäßig mit aktivem 2FA und unverschlüsselten Werten (`UserFactory.php:32-34`); kein `host()`-State; kein `APP_KEY` in `phpunit.xml`. (QA-13, QA-15) | States ergänzen, fester Test-Key | verifiziert |

## 6. Responsive Breakpoints (Ist-Zustand)

| # | Ort | Regel | Betrifft |
|---|---|---|---|
| 1 | `rezeptly/app.css:2740` | `max-width: 900px` | Rezeptdetail-Header, Formular-Grid, Steps-Builder |
| 2 | `rezeptly/app.css:2809` | `max-width: 900px` | App-Shell, Sidebar-Drawer, Untermenü |
| 3 | `AppLayout.vue:205` (JS) | `matchMedia('(max-width: 900px)')` | Sidebar beim Start geschlossen |
| 4 | `rezeptly/app.css:2910` | `max-width: 720px` | Filterpanel, Formular-Aktionen, Header-Aktionen |
| 5 | `rezeptly/app.css:3117` | `max-width: 720px` | Section-Header, Rezept-Aktionen, 16-px-Inputs |
| 6 | `IngredientAlternativesModal.vue:477` | `max-width: 720px` | Modal einspaltig |
| 7 | `collections/Show.vue:175` | `max-width: 720px` | Sammlungs-Header |
| 8 | `rezeptly/app.css:2956` | `max-width: 640px` | `.auth-form` |
| 9 | `rezeptly/app.css:2963` | `max-width: 600px` | `.note-card` |
| 10 | `rezeptly/app.css:3173` | `max-width: 540px` | Bewertungskarten, `.meta-card` |
| 11 | `rezeptly/app.css:3236` | `max-width: 540px` | TikTok-Import |
| 12 | `rezeptly/app.css:2971` | `max-width: 480px` | Zutatenzeile, Header-Icons, `.search-row` |
| 13 | `rezeptly/app.css:2999` | `max-width: 420px` | Grids einspaltig, `.settings-card` (wirkungslos, steht vor der Basisregel) |

Tailwind-Präfixe `sm:`/`md:`/`lg:`/`xl:` kommen in `resources/js` nicht vor.

**z-index-Karte:** User-Dropdown 1200 · Header mobil 1100 · Sidebar mobil 1000 · Backdrop 900 (nie sichtbar) · Modals 40 · Filterpanel/Autocomplete 20 · Untermenü 10.

**Empfohlene Prüf-Viewports:** 320, 390, 768, 1024, 1280/1440 px.

## 7. Testlücken

- **Routenabdeckung:** 68 Routen (44 App inkl. `/up`, 24 Fortify laut Standard). 20 von Tests berührt (14 Fortify, 6 Settings), 1 teilweise (`home`), 47 ohne Test. **Fachrouten: 0 von 35.** Details in `FEATURE-MATRIX.md`.
- **Bestehende Suite (Prognose, nicht ausgeführt):** 27 bestehen / 14 scheitern in CI; lokal ohne Build 18 / 23.
- **Fehlt komplett:** Host-Autorisierung (403-Matrix), Besitzprüfung Sammlungen, Rezept-CRUD inkl. Bild und Reihenfolge, Import mit Fakes, Frontend-Tests (kein Vitest, kein Playwright), Typecheck, Build-Gate, MySQL-Lauf (SQLite verdeckt F-12).
- **Infrastruktur:** 11 Factories, `HasFactory`, `withoutVite()`, Stray-Guards, `host()`-State, Test-Key.

## 8. Widersprüche zwischen den Berichten (aufgelöst)

- **„Meine Zutaten“:** Ein Bericht nannte Dummydaten in `MyIngredients.vue:60-84`, ein anderer ein leeres `ref` in Zeile 90. Beides stimmt: Die Tag-Statistiken sind hart kodiert, die Ergebnisliste ist leer. Entscheidend ist das fehlende `ingredients`-Prop (Stichprobe bestätigt).
- **Zeilennummern in `HandleInertiaRequests.php`:** Ein Bericht nannte Zeilen 184–220; die Datei hat 76 Zeilen. Gültig sind 37–73.
- **Priorität der E-Mail-Verifizierung:** P1 (Security) gegen P2 (Architektur, UX, QA). Hier als P1 geführt, weil ein aktiviertes Sicherheitsmerkmal wirkungslos ist; die Umsetzung braucht eine Produktentscheidung.
- **Priorität `sort_order`:** P1 (QA) gegen P2 (Architektur, Backend). Als P2 geführt, weil der Default SQLite ist; wird P1, sobald klar ist, dass die Produktion MySQL/Postgres nutzt.

## 9. Ausgeführte Befehle (Lead)

| Befehl | Exit |
|---|---|
| `git status --short`, `git branch --show-current`, `ls` Projekt/`.claude`/`docs` | 0 |
| `head` der sechs Agenten-Definitionen, `cat` der Modernisierungs-Dokumente, `claude --version` (2.1.323) | 0 |
| `cat composer.json package.json`, `ls` Workflows/Routen/Tests/Migrationen/`resources/js` | 0 |
| `php -v` (8.4.0), `node -v` (22.19.0), `npm -v` (10.9.3) | 0 |
| `composer -V` | 127 (nicht im PATH) |
| `ls vendor node_modules` | Fehler: beide fehlen |
| Stichproben per `grep`/`sed` auf `ResetPassword.vue`, `User.php`, `IngredientController.php`, `CategoryController.php`, `IngredientSearchArea.vue`, `MyIngredients.vue`, `rezeptly/app.css`, `HandleInertiaRequests.php`, `RecipeController.php`, `routes/api.php`, `AppLayout.vue`, `resources/css/app.css` | 0 |
| `git status --short` nach den Audits (nur `.claude/`, `CLAUDE.md`, `START-HIER.md`, `docs/` untracked) | 0 |

Die Agenten haben zusätzlich nur lesende Befehle ausgeführt (u. a. `php -l` über alle PHP-Dateien ohne Fehler, `git log`-Abfragen, eine In-Memory-SQLite-Probe ohne Projektbezug).

## 10. Übersprungene Prüfungen

| Prüfung | Grund |
|---|---|
| `php artisan test`, PHPUnit, Coverage | `vendor/` fehlt, `composer` nicht im PATH, keine Installation in dieser Phase |
| `php artisan route:list` | `vendor/` fehlt |
| ESLint, Prettier, `vue-tsc`, `vite build` | `node_modules/` fehlt |
| `composer audit`, `npm audit` | kein Netzwerk |
| Browser-, Screenshot-, Kontrast-, Screenreader-Tests | App nicht startbar, kein Browser-Tooling |
| Migrationen gegen SQLite-Datei oder MySQL | in dieser Phase nicht erlaubt |
| Produktion: Schema, Nginx, Header, `.env`, Worker, yt-dlp-Version | keine Freigabe |
| GitHub: Webhooks, Environments, Actions-Status | keine `gh`-CLI, kein Netzwerk |

## 11. Nachtrag Slice 0 (2026-11-13): Bestätigung durch Testlauf und Browser

Seit Slice 0 gibt es ausgeführte Tests. Dieser Abschnitt ersetzt die Einschränkung „nichts wurde ausgeführt“ aus Abschnitt 1 für die hier genannten Befunde. Rohdaten: `baseline/phpunit-*.txt`, `baseline/lint-*.txt`, `baseline/browser/`.

**Belegarten:** PHPUnit = roter Test in `tests/KnownDefects` (Sollverhalten) plus meist ein grüner Ist-Test in `tests/Feature`; Browser = Playwright-Test in `tests/e2e/known-defects.spec.ts` (Chromium, 320–1440 px), als `test.fail()` markiert.

### 11.1 Bestätigte Befunde

| ID | Beleg | Ergebnis |
|---|---|---|
| F-01 | Browser, `route:list` | bestätigt: `Ziggy error: route 'password.store' is not in the route list`, kein POST; richtiger Name `password.update` |
| F-02 | Browser, PHPUnit | bestätigt: `TypeError (reading 'map')` beim Tippen, Liste bleibt bei 39 von 39 |
| F-03 | Browser, PHPUnit | bestätigt: `TypeError (reading 'filter')` auf allen Viewports; Sidebar-Eintrag navigiert nicht |
| F-04 | Browser | bestätigt bei 1440 und 390 px: Klick auf die unsichtbare Fläche löst `POST /logout` aus; betrifft auch Gäste (Anmelden/Registrieren) |
| F-05 | Browser | bestätigt, Ursache genauer: Links liegen hinter dem Drawer **und** unter dem unsichtbaren Dropdown aus F-04 |
| F-06 | Browser, PHPUnit | bestätigt: Server liefert Fehler für `ingredients.0.name` und `steps.0`, sichtbar ist keine Meldung |
| F-07 | Browser, PHPUnit | bestätigt: `DELETE /categories/{id}` geht ohne Dialog raus; Kategorie wird von allen Rezepten gelöst |
| F-08 | PHPUnit | bestätigt zur Laufzeit: unverifizierter Nutzer erreicht `/home` (200) und legt Sammlungen an; bei der Registrierung wird keine Verifizierungsmail versendet |
| F-09 | Testlauf | Prognose exakt bestätigt: unverändert 18 bestanden / 23 rot ohne Build, 27 / 14 mit Build |
| F-12 | PHPUnit | bestätigt: `recipes.create`/`edit` sortieren nach fehlender Spalte `sort_order`; unter SQLite ohne Fehler |
| F-13 | PHPUnit | bestätigt: nach Update ist `ingredient_alternatives` leer bzw. enthält Waisen |
| F-14 | PHPUnit | bestätigt: aus 2 Schritten werden 3; siehe auch F-66 |
| F-15 | PHPUnit | bestätigt: `source` kommt als `null` zurück |
| F-16 | PHPUnit | bestätigt: 3 von 61 Controller-Routen zeigen auf fehlende Methoden; API-Schreibrouten mit echter Session für niemanden nutzbar (302 bzw. 401) |
| F-17 | Browser, PHPUnit | bestätigt: `Property [flash.success] does not exist`, keine Meldung im Browser |
| F-18 | PHPUnit | bestätigt: 500 mit `FOREIGN KEY constraint failed` statt 422 |
| F-19 | PHPUnit | bestätigt: Bilddatei bleibt nach dem Löschen auf dem Disk |
| F-20 | Browser, PHPUnit | bestätigt, genauer: `/settings/appearance` liefert **HTTP 500**; `/settings/two-factor` nach Passwortbestätigung „Page not found“ |
| F-21 | Browser, PHPUnit | bestätigt: Gast-Klick aufs Logo führt zu `/login`; nach Login landet man auf `/`. Der Code setzt dieses Ziel ausdrücklich (`FortifyServiceProvider.php:93-100`), es ist also Absicht des bisherigen Stands; Entscheidung E1 offen |
| F-22 | PHPUnit | bestätigt: `?search[]=a` → 500 (`TypeError`); unbekannte Bewertungsdimension → 500; doppelte Alternative → 500 |
| F-23 | PHPUnit | bestätigt und gemessen: `collections.index` 4 Queries bei 2 Sammlungen, 12 bei 10 |
| F-24 | PHPUnit | bestätigt: Import wird nach 30 Minuten weiter als laufend gemeldet |
| F-26 | PHPUnit | bestätigt: 6. Import und paralleler Import werden mit 200 angenommen |
| F-27 | PHPUnit | bestätigt: roher yt-dlp-/Gemini-Fehlertext wird ausgeliefert; kein `DELETE` der Gemini-Datei |
| F-29 | Browser | bestätigt: Backdrop `display:none`, Tippen daneben schließt den Drawer nicht |
| F-31 | Browser | bestätigt bei 1024 px für alle drei geprüften Rezepte |
| F-38 | Browser | bestätigt (Stichprobe): Drag-Griff fokussierbar, reagiert auf keine Taste |
| F-42 | Browser | bestätigt: `lang="en"` überall; 9 von 11 Seiten ohne `h1`; 8 von 11 mit Titel „Laravel“; zweimal „favRezepte“ |
| F-43 | Browser (axe) | Tendenz bestätigt: `color-contrast` 216 Treffer in 38 von 46 Scans (Zählung, keine Einzelmessung) |
| F-46 | PHPUnit | bestätigt: `is_host` ist mass-assignable; Registrierung und Profil-Update setzen es trotzdem nicht |
| F-47 | PHPUnit | bestätigt im Code: weder `X-Content-Type-Options` noch Clickjacking-Schutz; Nginx ungeprüft |
| F-49 | PHPUnit | teilweise: in der Testumgebung liefern beide Disks dieselbe URL; mit gesetztem `APP_URL` weichen sie ab |
| F-50 | Messung | bestätigt, siehe F-70 |

### 11.2 Korrekturen am Audit

| ID | Korrektur |
|---|---|
| F-22 | Ein Kategoriename mit 101–255 Zeichen führt unter SQLite **nicht** zu 500, sondern wird gespeichert. Der 500 ist nur für MySQL/Postgres zu erwarten (Hypothese). |
| F-27 | „Importierte Tags ohne passende Kategorie werden still verworfen“ stimmt für die Route `recipes.create` nicht: Sie bleiben in `importedRecipe.tags`. Verworfen werden unbekannte Tags erst beim Speichern (`RecipeController.php:381,646`). |
| F-30 | Nur teilweise: Direkt nach dem Öffnen ist der Modaltitel nicht verdeckt. Erst nach Scrollen liegen Header und Dropdown darüber, weil die Scroll-Sperre fehlt. |
| F-32 | Nur teilweise: Es entsteht kein Scroll-Overflow. Der lange Titel wird aber abgeschnitten (h1 240 px breit, Inhalt 367 px), auch bei 390 und 1024 px. `<body>` ist tatsächlich der Scroll-Container. |
| F-28 | `Process::preventStrayProcesses()` allein schützt nicht; es braucht zusätzlich `Process::fake([])`. In `tests/TestCase.php` umgesetzt. |

Kein Befund ist vollständig weggefallen. Nicht erneut geprüft wurden u. a. F-10, F-11, F-25, F-33 bis F-37, F-39 bis F-41, F-44, F-45 (nur Ist-Zustand festgehalten), F-48, F-51 bis F-63.

### 11.3 Neue Befunde aus Slice 0

| ID | Priorität | Bereich | Beobachtung / Beleg | Vorschlag | Status |
|---|---|---|---|---|---|
| F-64 | P2 | Abhängigkeiten | `composer audit`: 55 Advisories in 15 Paketen (1 critical: laravel/framework, Umgehung der TokenGuard-Authentifizierung, GHSA-f9x3-7vq9-cph6; 15 high u. a. guzzle, league/commonmark, symfony/mime). `npm audit --omit=dev`: 24 (1 critical: `tar` über das ungenutzte `sqlite3`; 19 high u. a. vite, axios). Die App nutzt keinen Token-Guard (Grep in `config/`, `app/`, `routes/` ohne Treffer). Protokolle: `baseline/lint-composer-audit.txt`, `lint-npm-audit.txt`. | Framework und Pakete innerhalb der Major-Version aktualisieren, `sqlite3` entfernen; danach beide Suiten laufen lassen | gemessen; Betroffenheit im Einzelnen nicht bewertet |
| F-65 | P2 | Zutaten | Löschen einer Zutat, die nur Basiszutat von Alternativen ist, löscht die Alternativen per Kaskade ohne Rückfrage mit (200). Die Prüfung in `IngredientController.php:67` greift nicht. | in die Bestätigung aus F-07 aufnehmen bzw. blockieren | verifiziert (grüner Ist-Test) |
| F-66 | P2 | Rezept-Schritte | Variante von F-14: Enthält ein mehrzeiliger Schritt eine Zeile „Notizen:“, landen alle folgenden Schritte in den Notizen (`RecipeController.php:80,564`). Nummerierte Notizzeilen verlieren ihre Nummer (`:86,569`). | mit F-14 beheben | verifiziert (grüner Ist-Test) |
| F-67 | P3 | Alternativen / Import | `ingredientAlternatives.store` akzeptiert eine Basiszutat, die nicht im Rezept vorkommt, und die Zutat als ihre eigene Alternative. Import akzeptiert `http://`-Links und prüft keinen leeren API-Schlüssel vorab (`ImportTikTokRecipe.php:26`). | Validierung ergänzen | verifiziert |
| F-68 | P2 | Fehlerseiten | Die 403 der Host-Middleware kommt bei Inertia-Requests als vollständige HTML-Seite ohne `X-Inertia`-Header (`bootstrap/app.php:31-33`, kein Renderer). Vermutlich erscheint im Frontend das Inertia-Fehler-Modal mit Roh-HTML. | Inertia-Fehlerseite (mit F-42) | Antwort verifiziert; Darstellung Hypothese |
| F-69 | P3 | App-Shell mobil | Mobiler Header ist 60 px hoch, der Drawer beginnt bei `top: 50px` (10 px Überlappung); der Header ist nicht fixiert. Login und Registrierung haben kein Logo und keinen Weg zurück zur Startseite. Platzhaltertexte werden bei 320 px abgeschnitten. Validierungstexte sind englisch. | mit Slice 3 bzw. Gate C | im Browser beobachtet |
| F-70 | P3 | Codequalität | Altbestand im Prüfmodus: Pint 27 Verstöße, Prettier 36 Dateien, ESLint 4 Fehler, `vue-tsc` 6 Typfehler. In der CI als dokumentierte, nicht blockierende Ausnahmen hinterlegt. | in Gate D bereinigen, Ausnahmen dann entfernen | gemessen |
| F-71 | P3 | Rezept-Anzeige | `show` liefert `tags` fest als `[]` (`RecipeController.php:141`); die Liste rundet Bewertungen auf ganze Zahlen (4,67 → 5), die Detailseite auf eine Nachkommastelle; `tagStats.key` nutzt die nicht vorhandene Spalte `slug` (`:282`). | bei Gelegenheit vereinheitlichen | verifiziert |

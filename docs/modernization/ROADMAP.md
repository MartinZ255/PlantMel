# Roadmap / Backlog

Stand: 2026-11-13 · Commit `c7227e4` · **Status: Vorschlag, wartet auf Freigabe.**

Grundlage sind `FINDINGS.md` (F-01 bis F-63) und `FEATURE-MATRIX.md`. Alle Befunde stammen aus statischer Analyse; nichts wurde ausgeführt oder im Browser gesehen.

**Prioritäten:** P0 Datenverlust/Sicherheitsvorfall/kompletter Ausfall; P1 Kernfunktion kaputt; P2 merkliche UX-/Darstellungsprobleme; P3 Politur/optional.

## Gate A – Bestandsaufnahme (abgeschlossen, Freigabe offen)

- [x] Repo-/Architektur- und Abhängigkeitsinventar
- [x] Routen-/Feature-Matrix mit Auth-/Rollenprüfung
- [x] UX-/UI-Audit aller Ansichten und Breakpoints – **nur aus dem Code**, ohne Browser
- [x] Testabdeckung erfasst – **ohne Testlauf**; die Baseline entsteht in Slice 0
- [x] Sicherheits- und Deployment-Risiken erfasst – ohne Produktionszugriff, ohne `composer audit`/`npm audit`
- [x] Befunde mit Reproduktionsschritten und Evidenz dokumentiert
- [ ] Priorisierten Implementierungsplan freigeben ← **hier**

## Entscheidungen, die vor der Umsetzung nötig sind

Jede wird als ADR in `DECISIONS.md` festgehalten. Empfehlung jeweils zuerst.

| # | Frage | Empfehlung | Blockiert |
|---|---|---|---|
| E1 | Ziel nach Login/Registrierung: `/home` oder `/`? | `/home` | Slice 0 (Starter-Tests), Slice 1 |
| E2 | E-Mail-Verifizierung: durchsetzen oder entfernen? | durchsetzen, nach Prüfung der Bestandskonten | Slice 5 |
| E3 | „Meine Zutaten“: bauen oder Menüpunkt und Route entfernen? | vorerst entfernen | Slice 1 |
| E4 | 2FA und Darstellung: Seiten wiederherstellen oder deaktivieren? | 2FA wiederherstellen, Darstellung/Dark Mode deaktivieren | Slice 5, Gate C |
| E5 | `sort_order`: additive Migration oder nach Name sortieren? | nach Name sortieren (keine Migration nötig) | Slice 3 |
| E6 | API-Routen (`routes/api.php`): entfernen oder reparieren? | entfernen (ungenutzt) | Slice 3 |
| E7 | Mehrere Hosts geplant? Dann Besitz/Policies für Rezepte | bei einem einzigen Host: dokumentieren, nicht bauen | Gate D |
| E8 | Nutzerbewertungen/Kommentare: bauen oder Landing-Text anpassen? | Text anpassen | Slice 1 |
| E9 | Impressum/Datenschutz: wer liefert die Inhalte? | Betreiber liefert, Technik baut die Seiten | Slice 5 |
| E10 | Welche Datenbank läuft in Produktion? | – (Auskunft nötig) | Priorität von F-11, F-12 |

## Gate B – Stabilisierung (nach Freigabe)

Reihenfolge nach Risiko. Jeder Slice ist ein eigener Branch und einzeln rückrollbar.

### Slice 0 – Testumgebung und Baseline · **empfohlener erster Schritt**

Ändert kein Verhalten der Anwendung. Ohne diesen Slice lässt sich keine spätere Änderung absichern.

- **Umfang:**
  1. Lokale Umgebung: Composer verfügbar machen, `composer install`, `npm ci`, lokale `.env` aus `.env.example` mit Dummy-Werten, `key:generate`. **Nicht** `composer setup` (führt `migrate --force` aus).
  2. Bestehende Suite einmal ausführen und die Prognose (14 von 41 rot) bestätigen oder korrigieren.
  3. `TestCase`: `withoutVite()`, `Http::preventStrayRequests()`, `Process::preventStrayProcesses()`, `actingAsHost()`; fester Test-Key in `phpunit.xml`.
  4. Factories + `HasFactory` für Recipe, Ingredient, Category, RatingDimension, Collection, RecipeImport, RecipeImage, RecipeIngredient, IngredientAlternative, RecipeRating; `UserFactory` mit `host()`.
  5. Charakterisierungstests des Ist-Zustands: 403-Matrix aller Host-Routen, Besitz bei Sammlungen, Rezept store/update/delete inkl. Bild und Reihenfolge, Rezeptliste/-detail, Import-Start mit `Queue::fake`.
  6. Bekannte Defekte als markierte Tests (Gruppe `known-defect`), damit die Baseline grün bleibt.
  7. Starter-Tests an die echten Routen anpassen (braucht E1).
  8. CI: Trigger auf `master` und Pull Requests, Prüfmodus statt Fix-Modus, `npm ci`, `contents: read`.
- **Betroffene Dateien:** `tests/**`, `phpunit.xml`, `database/factories/**`, `app/Models/*.php` (nur `HasFactory`-Trait), `.github/workflows/*.yml`.
- **Befunde:** F-09, F-28, F-50, F-63.
- **Risiken:** `HasFactory` ist die einzige Berührung von Anwendungscode und verhaltensneutral. CI wird beim ersten Lauf rot sein, bis Schritt 7 erledigt ist.
- **Akzeptanz:** `php artisan test` läuft lokal grün (bekannte Defekte markiert); CI läuft auf einem PR; Befehle und Exit-Status dokumentiert.
- **Freigaben nötig:** Installation von Abhängigkeiten (Netzwerk), Anlegen einer lokalen `.env`.

### Slice 1 – App-Shell und kaputte Seiten (P1, Frontend)

- **Umfang:** F-04 (Dropdown), F-05 (Such-Untermenü mobil), F-29 (Backdrop), F-01 (Reset-Formular, eine Zeile), F-02 (Zutatensuche absichern), F-03 nach E3, F-21 Gäste-Links, F-55 Landing-Text nach E8.
- **Betroffene Dateien:** `resources/js/components/layout/AppLayout.vue`, `components/widgets/SidebarSearchSubmenu.vue`, `resources/css/rezeptly/app.css` (Shell-Abschnitte), `pages/auth/ResetPassword.vue`, `components/recipes/IngredientSearchArea.vue`, `pages/IngredientSearch.vue`, `pages/MyIngredients.vue`, `pages/Landing.vue`, ggf. `routes/web.php`.
- **Risiken:** `AppLayout.vue` und die Shell-CSS betreffen jede Seite → ein Besitzer, Sichtprüfung bei 320/390/768/1280 px.
- **Akzeptanz:** geschlossenes Menü nicht klickbar; Such-Links mobil erreichbar; Backdrop schließt den Drawer; Passwort-Reset funktioniert Ende-zu-Ende; Zutatensuche filtert ohne Konsolenfehler; kein Menüpunkt ohne Funktion.
- **Abhängigkeiten:** Slice 0; E1, E3, E8. Für Sichtprüfung lokale lauffähige App.

### Slice 2 – Rezeptformular: Rückmeldung und Schutz vor Fehlklicks (P1)

- **Umfang:** F-06 (alle Validierungsfehler anzeigen, leere Zeilen filtern), F-17 (Flash teilen und anzeigen), F-07 (Bestätigung beim Löschen/Umbenennen von Kategorien und Zutaten), F-39 (Entfernen aus Sammlung mit Bestätigung/Rückgängig).
- **Betroffene Dateien:** `components/recipes/RecipeForm.vue`, `widgets/TagEditModal.vue`, `widgets/IngredientEditModal.vue`, `widgets/AddToCollectionModal.vue`, `pages/collections/Show.vue`, `app/Http/Middleware/HandleInertiaRequests.php`, `AppLayout.vue` (Meldungsbereich).
- **Risiken:** `RecipeForm.vue` hat 912 Zeilen; Änderungen klein halten. `AppLayout.vue` überschneidet sich mit Slice 1 → nacheinander, nicht parallel.
- **Akzeptanz:** jeder Server-Fehler erscheint am Feld; nach Speichern/Löschen/Hinzufügen erscheint eine Meldung; kein globales Löschen ohne Bestätigung.
- **Abhängigkeiten:** Slice 0, Slice 1.

### Slice 3 – Datenintegrität beim Rezept (P2, Backend)

- **Umfang:** F-13 (Alternativen im Update), F-14 (mehrzeilige Schritte), F-15 (Quelle), F-16 (tote Routen, E6), F-18 (422 statt 500), F-19 (Bilddateien), F-12 (`sort_order`, E5), F-22 (Validierung), F-46 (`is_host` aus `$fillable`).
- **Betroffene Dateien:** `app/Http/Controllers/RecipeController.php`, `IngredientController.php`, `IngredientAlternativeController.php`, `CategoryController.php`, `routes/web.php`, `routes/api.php`, `app/Models/User.php`, ggf. eine additive Migration für `source`.
- **Risiken:** Kernlogik des Rezept-Speicherns. Nur mit den Charakterisierungstests aus Slice 0. F-15 braucht eine Migration → eigene Freigabe, vorher F-11 klären.
- **Akzeptanz:** die als `known-defect` markierten Tests werden grün; keine 500er bei den dokumentierten Eingaben.
- **Abhängigkeiten:** Slice 0; E5, E6.

### Slice 4 – TikTok-Import robust machen (P2)

- **Umfang:** F-24 (Zeitlimit, Abbrechen, kein Datenverlust), F-25 (Timeouts), F-26 (Rate-Limit, Stream), F-27 (Aufräumen, generische Fehlermeldung, Gemini-Datei löschen).
- **Betroffene Dateien:** `app/Services/TikTokRecipeImporter.php`, `app/Jobs/ImportTikTokRecipe.php`, `RecipeImportController.php`, `routes/web.php` (Throttle), `config/queue.php`, `pages/recipes/Create.vue`.
- **Risiken:** nur mit Fakes testbar; echter Ende-zu-Ende-Lauf erst auf Staging.
- **Abhängigkeiten:** Slice 0.

### Slice 5 – Konto, Recht, Härtung (P1/P2, braucht Entscheidungen)

- **Umfang:** F-08 (E2), F-20 (E4), F-10 (E9), F-47 (Header, Produktions-Checkliste), F-48 (Datenschutzhinweis Import), F-60.
- **Risiken:** F-08 kann Bestandskonten aussperren → vorher klären, wie viele Konten unverifiziert sind (Produktionsauskunft, eigene Freigabe).
- **Abhängigkeiten:** E2, E4, E9.

### Vor jedem Schema- oder Deployment-Schritt

- [ ] F-11: Schema-Abgleich Produktion gegen Migrationen – **nur lesend, nur mit ausdrücklicher Freigabe**, bevorzugt auf einer Staging-Kopie.
- [ ] E10 beantworten.

## Gate C – UX / Redesign (nach Gate B)

- [ ] Browser-Baseline: Playwright mit Screenshots bei 320/390/768/1024/1440 px, axe-Scan (neue Abhängigkeiten → Freigabe)
- [ ] Design-Tokens und eine Breakpoint-Skala (F-33); CSS-Layer-Entscheidung und Dark Mode (F-34)
- [ ] Gemeinsame Bausteine: Button, Dialog, Formularfeld, Suchleiste, Sternebewertung, Auth-Layout (F-35, F-40, F-41)
- [ ] Responsive-Korrekturen: Overflow, Rezeptdetail, Modals, Zutatenzeile, Touch-Drag (F-30, F-31, F-32, F-36)
- [ ] Barrierefreiheit: Tastatur-Alternativen, Struktur, Titel, Sprache, Fehlerseiten, Kontrast nach Messung (F-38, F-42, F-43)
- [ ] Leer-, Lade- und Fehlerzustände, Filter-Chips (F-44)

## Gate D – Qualitätsverbesserung

- [ ] Performance: Pagination, Eager Loading, Shared Props, Bilder/Thumbnails (F-23, F-37)
- [ ] Refactoring nach Tests: Form Requests, Recipe-Action, Policies (F-53, ggf. F-45 nach E7)
- [ ] Abhängigkeiten und toter Code (F-51, F-52), Typecheck (F-57), Inertia-Links (F-54)
- [ ] `composer audit` / `npm audit` in CI
- [ ] Dokumentation: README, `.env.example` (F-62)
- [ ] Optionaler Feature-Backlog mit Nutzen-/Aufwandsbewertung: eigene Zutaten, Nutzerbewertungen, Kommentare, Zutaten-Detailseite

## Gate E – Release (gesonderte Freigabe)

- [ ] Staging mit synthetischen Testdaten
- [ ] Runbook: Nginx-Vorlage, Queue-Worker-Unit, `storage:link`, env-Schlüssel, yt-dlp-Update (F-49)
- [ ] CI grün, Smoke-Tests, Backup-/Rollback-Plan
- [ ] Manuelle Abnahme, erst danach Production

## Verbleibende Risiken nach Gate A

- **Nichts wurde ausgeführt.** Einzelne Befunde können sich im Browser oder im Testlauf anders zeigen; Slice 0 und die Browser-Baseline schließen diese Lücke.
- **Produktion unbekannt:** Schema-Stand (F-11), Datenbank-Treiber (F-12), Header und `.env` (F-47), ob der Queue-Worker läuft, ob plantmel.de überhaupt den Stand `c7227e4` ausliefert.
- **Rechtliche Punkte** (F-10, F-48, F-58) brauchen eine Bewertung durch einen Menschen.
- **Abhängigkeits-Schwachstellen** sind ungeprüft (kein Netzwerk).

## Nächste empfohlene Aufgabe

Freigabe dieser Roadmap, Beantwortung von E1 und E10, dann **Slice 0** über `/plantmel-implement`.

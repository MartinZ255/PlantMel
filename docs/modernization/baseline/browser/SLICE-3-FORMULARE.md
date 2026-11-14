# Nachtrag Slice 3 – Formulare, Rezeptseiten und Modals

Stand: 2026-11-14 · Basis-Commit `8357b73` · Befunde F-06, F-24 (Client), F-30, F-31, F-32, F-39 (Rest), F-44 (Teil), F-56 (Teil), F-74.
Gezielte Korrekturen ohne Redesign: Farben, Typografie und Grundlayout bleiben gleich. **Screenshot-Baselines wurden nicht aktualisiert** (das macht der Lead zentral nach dem Zusammenführen mit dem Shell-Paket); alle Läufe mit `--ignore-snapshots`.

## 1. Regressionstests

Neue Datei `tests/e2e/regression-forms.spec.ts` (Projekt `regression`, 31 Tests). Unabhängig vom Wortlaut der Validierungsmeldungen (`APP_LOCALE` en oder de): geprüft werden Sichtbarkeit, Zuordnung zum Feld und `aria-invalid`/`aria-describedby`.

| Befund | Tests |
|---|---|
| F-06 | nur Titel → leere Startzeilen nicht gesendet, Server-Meldung an Zutaten/Schritten, Zusammenfassung fokussiert, Sprunglink · Zeilen-Fehler trotz gefilterter Leerzeile an der richtigen Zeile, Verwerfen nach Umsortieren · Titel leer + negative Dauer, `required`/`aria-required`, `novalidate` |
| F-74 | alle Fehler-Keys am Feld (Inertia-Antwort mit Fehlern für Portionen, `tags.0`, `ratings.<id>`, `ingredients.0.amount`, `steps.0`, Notizen, unbekannter Key) · Tag anlegen (422) und Zutat anlegen (500) zeigen die Meldung am Eingabefeld |
| F-56 | Abbrechen-Link zum Rezept, Zurücksetzen nur nach Inline-Rückfrage (kein `window.confirm`), Vorschau einer neu gewählten Bilddatei |
| F-30 | je Modal (Tag, Zutat, Alternativen, Sammlung) bei 1440 und 390 px: Titel nach Zurückscrollen nicht verdeckt (`elementFromPoint`), Header nicht klickbar, Mausrad scrollt den Hintergrund nicht, `#app` inert, Fokus im Dialog, Enter im Dialogfeld sendet das Rezeptformular nicht ab, Escape schließt, Fokus zurück am Auslöser (8 Tests) |
| F-31 | bei 768, 1024, 1440 px für Rezept 1 (Bild), 2 (langer Titel), 3 (ohne Bild): kein Aktions-Button überschneidet den Titel; ohne Bild > 90 % Breite; Meta-Spalten = Zahl der Karten · Notizen nur mit Notizen, `h2`-Hierarchie |
| F-32 | bei 320, 390, 1024 px auf `/rezept/2` und `/rezepte`: kein Element ragt aus `.app-content`, kein abgeschnittener Text, `h1.scrollWidth <= clientWidth` |
| F-39 | Gast sieht Login-Hinweis · Host ohne Sammlung sieht „Neue Sammlung anlegen“ · Nutzerin: Button ohne Auswahl deaktiviert, Teilerfolg („Gespeichert in: Wochenplan“) und simulierter 500 („Leere Sammlung“) sichtbar |
| F-24 | 429/409/422 beim Start zeigen die Server-Meldung · `failed` zeigt die Meldung, danach keine Abfragen · 1 Netzwerkfehler toleriert, 3 in Folge beenden mit Meldung · „Abbrechen“ stoppt · Zeitlimit 16 min per `page.clock` · fertiger Import überschreibt ein ausgefülltes Formular erst nach Rückfrage |
| F-44 | 5 Tastendrücke → genau 1 Anfrage, Taste ohne Textänderung → keine; Leerzustand, „Filter zurücksetzen“, Trefferzahl in `role="status"` |

Alle Import-Antworten sind per `page.route` simuliert; es wird kein echter Import gestartet und kein Import-Kontingent verbraucht.

## 2. Bekannte Defekt-Tests, die jetzt unerwartet bestehen

In `tests/e2e/known-defects.spec.ts` (nicht geändert) melden diese `test.fail()`-Tests „Expected to fail, but passed“:

- `Gast › mobil 320 › F-32 langer Rezepttitel: kein horizontaler Overflow, Titel nicht abgeschnitten`
- `Host › F-06 Rezept nur mit Titel speichern zeigt Fehler an Zutat/Schritt`
- `Host › 1024 › F-31 Aktionsbuttons ueberlappen den Rezepttitel nicht`
- zusätzlich, **nicht durch dieses Paket**: `Nutzer 2 (eigene Daten/Session) › F-21b nach dem Login landet man auf /home` (E1 ist im Backend-Stand `8357b73` bereits umgesetzt)

Weiterhin erwartete Fehlschläge: `Host › mobil 320/390 › F-30 Alternativen-Modal nach Zurueckscrollen (keine Scroll-Sperre): Titel nicht verdeckt (320|390)`. Gemessen: Der Titel-Mittelpunkt trifft jetzt den Titel (`h2.modal__title`, 320: y = 56, 390: y = 64), das Modal liegt also über dem Header. Es scheitert nur noch das zweite Kriterium „Bounding-Box Titel ∩ Header = leer“ (Titel ab y ≈ 43 bzw. 51, Header 0–60), das die rein geometrische Überlagerung misst, obwohl der Header hinter dem Backdrop liegt. Dieses Kriterium bildet den Defekt nicht mehr ab; ersetzt wird es durch die F-30-Tests in `regression-forms.spec.ts`.

## 3. Sichtbare Änderungen (für die spätere Baseline-Aktualisierung)

Kontrolliert anhand von Aufnahmen bei 320, 390, 768, 1024 und 1440 px (nicht abgelegt).

| Seite | Änderung |
|---|---|
| `/rezept/{id}` (alle Viewports) | Titel steht zuerst, Aktionen liegen im Fluss rechts daneben (breit) bzw. darunter (schmal, ≤ 720 px in einer umbrechenden Zeile, wie bisher volle Breite). Keine Überlagerung mehr. Langer Titel bricht um (bei 320/390/1024 mehrzeilig statt abgeschnitten). Ohne Bild (Rezept 3) nimmt die Kopfkarte ab 901 px die volle Breite ein. Meta-Karten füllen die Zeile (2 statt 4 Spalten). „Notizen & Tipps“ entfällt bei Rezepten ohne Notizen. Tags umbrechen statt abschneiden. |
| `/rezepte` | Placeholder „Rezeptname eingeben …“, Trefferzahl „1 Ergebnis“ im Singular; bei 0 Treffern Leerzustand mit „Filter zurücksetzen“. Sonst unverändert (Label ist visuell versteckt). |
| `/rezepte/erstellen` | Hinweis „Mit * markierte Angaben sind Pflicht.“, rote Sternchen an Titel/Zutaten/Zubereitung, längere Hinweistexte unter „Zutaten“ und „Zubereitung“. Nach fehlgeschlagenem Speichern: Fehlerkasten oben, rote Rahmen an fehlerhaften Feldern, Meldungen unter Zeilen. TikTok-Bereich: Label ist jetzt ein `<label>` (optisch gleich), während des Imports Button „Abbrechen“. |
| `/rezept/{id}/bearbeiten` | wie Erstellen; zusätzlich Link „Abbrechen“ vor „Zurücksetzen“; nach „Zurücksetzen“ Inline-Rückfrage statt sofortigem Zurücksetzen; nach Wahl einer Datei Bildvorschau. |
| Modals | liegen über Header und Sidebar, Hintergrund scrollt nicht; „Zur Sammlung hinzufügen“ für Gäste mit Login-Hinweis, ohne Sammlung mit Link „Neue Sammlung anlegen“; deaktivierter „Hinzufügen“-Button ist halbtransparent. |

## 4. Läufe

Nach dem letzten `npm run build`, zweimal hintereinander (jede Ausführung legt die E2E-Datenbank neu an):

| Lauf | Befehl | Ergebnis | Exit |
|---|---|---|---|
| 1a / 2a | `E2E_PORT=8162 npx playwright test --ignore-snapshots --project=setup --project=w320 --project=m390 --project=t768 --project=d1024 --project=d1440 --project=regression` | je 178 bestanden (4 Setup, 115 Baseline ohne Bildvergleich, 28 Regression Slice 1–2, 31 Regression Slice 3), ca. 4,9 min | 0 / 0 |
| 1b / 2b | `E2E_PORT=8162 npx playwright test --ignore-snapshots --project=defects --grep-invert "F-06\|F-30\|F-31\|F-32"` | je 13 bestanden (davon erwartete Fehlschläge), 1 „unerwartet bestanden“: F-21b (nicht aus diesem Paket) | 1 / 1 |

Ein Lauf ohne Ausschluss (alle Projekte) ergab 191 bestanden, 4 „unerwartet bestanden“ (F-06, F-31, F-32 sowie F-21b, siehe Abschnitt 2).

## 5. Offen

- **Aktuelles Bild beim Bearbeiten:** `RecipeController::edit` liefert kein `image`. Die Vorschau ist vorbereitet (`Edit.vue` reicht `recipe.image` durch) und erscheint, sobald das Backend das Feld liefert; ohne PHP-Änderung zeigt sie nur neu gewählte Dateien.
- **Bereits enthaltene Sammlungen** sind nicht erkennbar: `recipes.show` liefert je Sammlung nur `id` und `name`, keine Mitgliedschaft.
- In Produktion (ohne `APP_DEBUG`) liefert ein Serverfehler beim Hinzufügen zur Sammlung die Inertia-Seite `Error` statt des `invalid`-Ereignisses; dann erscheint die Fehlerseite statt der Meldung im Dialog (Verhalten des Backend-Fehlerrenderers).

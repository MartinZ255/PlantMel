# Abschlussbericht Gate C – Redesign „Natur & organisch“

Stand: 2026-11-20 · Branch `feat/gate-c-redesign` (lokal, kein Push, kein Deployment) · Grundlage: ADR-003 Revision 1, `DESIGN-SYSTEM.md`, Entscheidung E14 (`DECISIONS.md`)

## 1. Ergebnis

Alle Seiten sind im Konzept „Natur & organisch“ umgesetzt. Die Legacy-Stildatei `resources/css/rezeptly/app.css` ist entfernt, es gibt nur noch eine Stilquelle (`resources/css/app.css`). Keine Migration, keine Datenänderung, kein Zugriff auf Produktion.

| Messung | Ergebnis | Exit |
|---|---|---|
| PHPUnit (`php vendor/bin/phpunit`, Unit + Feature) | 607 Tests, 3955 Assertions, alle grün | 0 |
| Bekannte Defekte (`--testsuite KnownDefects`) | vor dem Merge 723badb: 1 Test rot (F-23, N+1); danach grün und in `tests/Feature/Collections/` verschoben – Suite jetzt leer („No tests executed“) | 0 |
| Browser (`E2E_PORT=8197 npx playwright test`, inkl. Screenshot-Vergleich) | zweimal vollständig: je 383 bestanden, 35 übersprungen (Vorschau-Generatoren, nur mit `GATE_C2_PREVIEW=1`) | 0 / 0 |
| Screenshot-Baseline (`baseline.spec.ts`) | `--update-snapshots` 124 bestanden, danach zweimal ohne Update je 124 bestanden | 0 / 0 / 0 |
| ESLint (`npx eslint .`) | 0 Befunde | 0 |
| `npx vue-tsc --noEmit` | 0 Fehler | 0 |
| `npm run build` | erfolgreich | 0 |
| `composer audit` | keine Hinweise | 0 |
| `npm audit` | 9 moderate, nur Entwicklungswerkzeuge (`minimatch` über `@typescript-eslint/*`, `vue-tsc`); `npm audit --omit=dev`: 0 | 1 / 0 |

## 2. Was in Gate C geschah

**C1 / C1b – Design-System und Musterseiten.** Palette Waldgrün, Moos, Matcha, Rote Bete auf grünlichem Naturgrund (alle Paare WCAG 2.2 AA gerechnet); Fraunces (selbst gehostet, OFL-1.1) als Display-Schrift neben Instrument Sans. Neue Bausteine `BlobImage`, `BlobBackdrop`, `WaveDivider`, `LeafOrnament`, `Reveal`, `LandingHeroArt`, `FeaturedRecipeBand`, `IngredientChecklist`. Startseite, Rezeptliste, Rezeptdetail, Kopfnavigation, Footer und Mobilmenü neu.

**C2-A – Rezeptformular, TikTok-Import, Formular-Dialoge.** Formular in nummerierte Kapitel gegliedert (`FormSection`), Kategorien als `SeedToggle` (`aria-pressed`), Kapitelnavigation ab `lg`, Bildvorschau als Blob. Drag & Drop, Autocomplete, mehrzeilige Schritte, Alternativen und Fehlerzusammenfassung bleiben erhalten; Griffe und Knöpfe 44 px. Tag-, Zutat- und Alternativen-Dialog auf `ui/Dialog.vue`. Import-Fortschritt neu gestaltet, Verhalten gleich.

**C2-B – Dashboard, Sammlungen, Zutaten.** Home mit Begrüßung, Zählern und zuletzt hinzugefügten Rezepten; Sammlungen als organische Kacheln, Detail mit Waldgrün-Kopf und Bearbeitungsmodus; Zutatensuche und „Meine Zutaten“ mit Such-Kapsel, aufklappbarem Filterblatt und A–Z-Raster (`IngredientGrid`).

**C2-C – Anmeldung, Einstellungen, Recht, Fehler, Shell.** `AuthShell` für alle Anmelde-Seiten, `SettingsShell` mit Bereichsnavigation, Rechtstexte als Lesespalte (`legal-prose`), Fehlerseite für 403/404/419/429/500/503. `auth.user.is_host` global geteilt (`useIsHost`).

**E14 – Bewertung nur noch Geschmack.** Eine Bewertung mit 1–5 Sternen; Dimension per Name aus `config/plantmel.php`. Andere Dimensionen bleiben in der Datenbank, werden aber nicht angezeigt oder eingerechnet.

**Nachtrag des Leads (Merge 723badb).** Stift-Knöpfe für Kategorien im Rezeptformular, deutscher Datei-Knopf, Sammlungs-Collage mit Bildern, Dialog „Bearbeiten“ auf der Sammlungsseite (neue Route `collections.update`); Tests `CollectionUpdateAndCollageTest`, `regression-collections-tags.spec.ts`.

**Integration (dieser Schritt).**
- Alte BEM-Selektoren in `tests/e2e/regression.spec.ts` und `tests/e2e/known-defects.spec.ts` auf `data-*`-Hooks und Rollen umgestellt (`data-tag-chip` + `aria-pressed`, `data-ingredient-*`, `data-step-*`, `data-recipe-form`, `data-field-error/-hint`, `data-confirm-text`, Dialog über `role="dialog"` „Alternativen für …“, Knopf „Zutat neu anlegen“ statt „+“). Prüfgedanken unverändert; F-06 misst jetzt erst nach dem Rendern der Fehlerzusammenfassung (vorher traf der breite Selektor `[class*="error"]` zufällig Elemente). F-38 bleibt `test.fail`; kein `test.fail` wurde unerwartet grün.
- `resources/css/rezeptly/app.css` (3249 Zeilen) und der Import entfernt. Vorher per Skript geprüft: keine Vue-Datei, kein Blade-Template und kein Test nutzt noch Klassen daraus. Wächtertests: Datei und Import fehlen; keine der 261 früheren Klassen (feste Liste `tests/e2e/support/legacy-classes.ts`) auf Paket-C- und allen Hauptseiten.
- Folgen: Das Fenster scrollt wieder statt `<body>`. `html { scroll-padding-top }` ersetzt `[id] { scroll-margin-top }` (sonst doppelter Abstand); die Scroll-Sperre in `useModal` sperrt `<html>` und `<body>` (Test prüft beides); die klebende Kopfzeile ist durch den bestehenden Test „Kopfzeile haftet beim Scrollen oben“ abgesichert.
- `settle()` (Testhilfe) wartet nicht mehr auf lazy-Bilder, die seitlich außerhalb eines horizontal scrollenden Containers oder des Fensters liegen (wischbares Rezeptband). Sichtbare Bilder werden weiter abgewartet.
- Baseline-Spec: Viewport-Vergrößerung entfernt, stattdessen `fullPage` mit Höhenbegrenzung 5000 px. Vorschau-Spec: Style-Tag-Workaround entfernt.
- Aufräumen: `useAppNavigation` ohne veraltetes `isHost`; `SettingsShell` nach `components/settings/`; `StarRating` ohne Wert „Noch nicht bewertet“ (wie die Detailseite, E2E-Test ergänzt).
- Backend: `RecipeController::edit` liefert `recipe.image` (erste Bilddatei, URL über Disk `public`, wie `show`); Feature-Test `RecipeEditImageTest`.
- Merge 723badb übernommen (ohne Konflikte); im Formular-Test den Kategorie-Umschalter mit `exact: true` adressiert, weil der Stift-Knopf „Kategorie „Dessert“ bearbeiten“ sonst mitgetroffen wird.
- Screenshot-Baselines nach dem Merge erneuert; Gesamtvorschau `docs/modernization/gate-c2-vorschau/` neu aufgenommen (Generator: `tests/e2e/regression-gate-c2-preview.spec.ts` und der Vorschau-Block in `regression-gate-c2-forms.spec.ts`, beide nur mit `GATE_C2_PREVIEW=1`).

## 3. Verhaltensänderungen für Nutzerinnen und Nutzer

| Vorher | Jetzt |
|---|---|
| Sammlung löschen mit `window.confirm` | Bestätigung im gestalteten Dialog (Fokusfalle, Escape, Fokus zurück) |
| Tag-/Zutat-Dialoge als eigene Modals | gemeinsamer `Dialog`-Baustein; die Lösch-Rückfrage (ADR-002) bleibt im Dialog, „Abbrechen“ hat den Fokus |
| „Rezept erstellen“ nur auf Rezeptseiten | für Hosts auf jeder Seite (Kopfzeile, Mobilmenü, Nutzermenü); nie für Nutzer oder Gäste |
| Konto löschen mit Browser-Rückfrage | Dialog mit Passwortabfrage |
| Bewertung mit mehreren Kategorien | nur „Geschmack“, 1–5 Sterne, optional und zurücksetzbar |
| Bearbeiten zeigte kein aktuelles Bild | Kopf und Bildvorschau zeigen das gespeicherte Bild |
| Kategorie bearbeiten nur per Doppelklick | zusätzlich Stift-Knopf je Kategorie |
| Sammlungen nicht umbenennbar | Dialog „Bearbeiten“ auf der Sammlungsseite |
| `<body>` war Scroll-Container | das Fenster scrollt (normales Browserverhalten, z. B. für Ankersprünge und Zurück-Navigation) |

## 4. Offene Punkte

- **Datenschutz §9** nennt Fraunces nicht (F-90) – der Betreiber füllt die Vorlage aus.
- Erledigt mit Merge 723badb (vor den Baselines integriert): Tag-Bearbeiten per Stift-Knopf statt nur Doppelklick (F-91), deutscher Datei-Knopf „Bild auswählen“ statt „Choose File“ (F-92), Collage mit Rezeptbildern für Sammlungen (F-93), Sammlung umbenennen im Dialog „Bearbeiten“ (F-94, Route `collections.update`).
- **Dimension „Geschmack“ in Produktion prüfen** (F-95): Heißt sie dort anders, zeigt die App keine Bewertungen.
- F-38 (Tastatur-Umsortieren der Schritte/Zutaten) bleibt bekannter Defekt (`test.fail`).
- F-23: N+1 in der Sammlungsübersicht ist mit Merge 723badb behoben (Test verschoben); die fehlende Pagination der Rezeptliste bleibt offen.
- `recipes/RecipeList.vue` ist ungenutzt (Gate D).

## 5. Verbleibende Risiken

- Screenshot-Baselines gelten nur für Windows/Chromium.
- Ohne `overflow-x: hidden` an `<html>/<body>` würde künftiger horizontaler Überlauf sichtbar scrollen; die 320-px-Tests decken die bekannten Seiten ab.
- Produktion, Datenbanktreiber und Schema sind weiterhin ungeprüft (Gate E).
- Die Collage lädt Rezepte per Eager Loading mit Limit je Sammlung; Laravel nutzt dafür Fensterfunktionen (MySQL ab 8.0, MariaDB ab 10.2, SQLite ab 3.25). Geprüft ist nur SQLite (E10/F-11).
- Die leere Suite `KnownDefects` liefert Exit 0 mit Warnung; der CI-Job dafür ist bis zu neuen bekannten Defekten ohne Wirkung.

# Eigenes Logo einbauen

Stand: 2026-11-21 · Branch `feat/logo-vorbereitung`

PlantMel zeigt heute ein eingebautes Logo: Keimling in einem waldgrünen Blob und daneben die Wortmarke „Plant*Mel*“ als Text in Fraunces. Sobald ein eigenes Logo vorliegt, wird es so getauscht:

1. Dateien nach `public/brand/` legen (siehe unten).
2. `npm run brand:apply` ausführen.
3. Wie gewohnt `npm run build` und deployen (Deployment nur mit Freigabe, siehe RELEASE-CHECKLIST.md).

Bis dahin bleibt alles pixelgleich. Die Screenshot-Baselines haben sich durch die Vorbereitung nicht geändert.

## 1. Welche Dateien der Auftraggeber liefern soll

| Datei | Inhalt | Pflicht |
|---|---|---|
| `logo-mark.svg` | Bildmarke (Signet) ohne Schrift, **quadratisch** (z. B. `viewBox="0 0 100 100"`), dunkle Fassung für helle Flächen | **ja** |
| `logo-full.svg` | Bildmarke + Schriftzug „PlantMel“ nebeneinander (Querformat), dunkle Fassung für helle Flächen | empfohlen |
| `logo-mark-light.svg` | Bildmarke, helle Fassung für Waldgrün-Flächen (Footer, Anmeldeseiten, Vorschaubild) | empfohlen |
| `logo-full-light.svg` | Bildmarke + Schriftzug, helle Fassung für Waldgrün-Flächen | empfohlen |

**Anforderungen an die Dateien**

- **SVG bevorzugt.** Texte in Pfade umgewandelt (keine Schriftart nötig), keine eingebetteten Bitmaps, keine Skripte, keine externen Verweise.
- **Transparenter Hintergrund**, kein Rahmen, kein weißes Rechteck.
- **Schutzraum:** um das Logo herum ein Freiraum von mindestens der halben Höhe der Bildmarke bzw. des Buchstabens „P“; der Schutzraum gehört **nicht** in die Datei (die `viewBox` liegt eng am Logo, höchstens 2–4 % Rand).
- **Farben** passend zu den Tokens der Seite (resources/css/app.css):

  | Token | Wert | Einsatz |
  |---|---|---|
  | forest | `#143D2D` | Logo auf hellen Flächen; Hintergrund der hellen Fassung |
  | matcha | `#C8E28A` | Akzent; Logo auf Waldgrün |
  | berry | `#A62D5B` | sparsamer Akzent (z. B. ein Detail) |
  | canvas | `#F2F5EA` | heller Naturgrund; Schrift auf Waldgrün |

  Die dunkle Fassung muss auf canvas `#F2F5EA`, die helle Fassung auf forest `#143D2D` einen Kontrast von mindestens 3:1 haben (WCAG 2.2, Grafiken und große Schrift).
- **Mindestgrößen:** Die Bildmarke muss als Favicon mit 16 × 16 px noch erkennbar sein (keine Haarlinien unter etwa 1/16 der Breite). Der Schriftzug wird im Kopf der Seite 36 px hoch angezeigt, im Footer ebenso.
- **Falls nur PNG möglich:** mindestens 1024 px an der kürzeren Seite, transparent (PNG-24 mit Alpha). Die Bildmarke kann dann mit `node scripts/brand/generate-icons.mjs --input public/brand/logo-mark.png` gerastert werden. Für die Anzeige auf der Seite werden aber SVG-Dateien erwartet; PNG-Logos bitte vorher in SVG umwandeln lassen.
- Zusätzlich gern die Quelldatei (AI/Figma/PDF) für spätere Anpassungen, die gehört aber nicht ins Repository.

## 2. Wo die Dateien hin kommen

```
public/brand/logo-mark.svg
public/brand/logo-full.svg          (optional)
public/brand/logo-mark-light.svg    (optional)
public/brand/logo-full-light.svg    (optional)
```

`public/brand/placeholder/` enthält nur Platzhalter (heutiges Signet) für die Tests der Icon-Pipeline. Nicht löschen.

## 3. Kommando

```
npm run brand:apply
```

Das Kommando

1. rastert `public/brand/logo-mark.svg` mit dem schon installierten Playwright-Chromium (keine neue Abhängigkeit) und überschreibt in `public/`:
   - `favicon.ico` (16, 32, 48 px), `favicon.svg` (Kopie der Bildmarke)
   - `apple-touch-icon.png` (180 px, Hintergrund canvas `#F2F5EA`, 10 % Rand)
   - `icons/icon-192.png`, `icons/icon-512.png` (transparent)
   - `icons/icon-512-maskable.png` (512 px, Hintergrund canvas, Logo in der Safe Zone: 56 % Kantenlänge, passt in den 80 %-Kreis)
   - `og-image.png` (1200 × 630, helles Logo + Schriftzug „PlantMel“ und Claim auf Waldgrün; nutzt `logo-full-light.svg`, sonst `logo-mark-light.svg` bzw. `logo-mark.svg` + Text)
2. schaltet `resources/js/components/brand/brand.config.json` um:
   - `logo: "custom"` – alle Logo-Stellen zeigen die Dateien aus `public/brand/`
   - `fullHasWordmark` – `true`, wenn `logo-full.svg` vorliegt; sonst Bildmarke + Text-Wortmarke
   - `lightVariants` – `true`, wenn die hellen Fassungen vorliegen; sonst wird auch auf Waldgrün die dunkle Datei gezeigt (dann bitte helle Fassungen nachliefern)
   - `version` – Datum als Cache-Buster (`?v=…`)

Nur Icons neu erzeugen, ohne den Schalter anzufassen: `npm run brand:icons`.

Ohne eigene Datei läuft `npm run brand:icons` mit dem Platzhalter und schreibt **nicht** nach `public/`, sondern nach `test-results/brand-icons-preview/` (zum Ansehen). Das Skript verweigert Platzhalter-Ausgaben nach `public/` und `--apply` ohne eigene Datei.

Danach sind Manifest und Vorschaubild automatisch aktiv: `resources/views/app.blade.php` verlinkt `site.webmanifest` erst, wenn `public/icons/icon-192.png` existiert, und die `og:image`-Tags erst, wenn `public/og-image.png` existiert.

## 4. Zurück zum eingebauten Logo

In `resources/js/components/brand/brand.config.json` `"logo": "builtin"` setzen und neu bauen. Die alten Favicons liegen in der Git-Historie (`git show <commit>:public/favicon.ico`).

## 5. Prüfen

1. `npm run build`, Seite lokal öffnen: Kopf (helle Fläche), Footer und Anmeldeseite (Waldgrün) zeigen das neue Logo scharf, nicht verzerrt, ohne Rand.
2. Tastatur: Tab auf das Logo im Kopf – Fokusrahmen sichtbar; Screenreader liest „PlantMel – Startseite, Link“.
3. Browser-Tab zeigt das neue Favicon (ggf. Cache leeren); `/site.webmanifest` und `/og-image.png` laden.
4. Tests: `npx playwright test tests/e2e/regression-brand.spec.ts` und `php artisan test --filter=BrandHeadTest`.
5. **Screenshot-Baselines ändern sich mit dem neuen Logo absichtlich.** Nach Sichtprüfung `npm run e2e:update` und die geänderten PNGs in `tests/e2e/baseline.spec.ts-snapshots/` im Review gegenprüfen (nur Logo-Bereich darf abweichen).
6. Vorschaubild testen, z. B. mit dem Sharing-Debugger des jeweiligen Netzwerks, erst auf Staging.

## 6. Technik im Überblick

- **Baustein:** `resources/js/components/brand/BrandLogo.vue` – Props `variant` (`full` | `mark`), `tone` (`dark` für helle Flächen | `light` für Waldgrün), `size` (`sm` | `md` | `lg`, `md` = heutige Größe), `href`, `current`.
- **Schalter:** `resources/js/components/brand/brand.config.json` (gelesen von `brand.ts`).
- **Barrierefreiheit:** Link mit zugänglichem Namen „PlantMel – Startseite“. Eigene Grafik mit Schriftzug: `alt="PlantMel"`. Bildmarke neben Text-Wortmarke: `alt=""`. Reines Signet im eingebauten Modus: unsichtbarer Text „PlantMel“.
- **Verwendet in:** `components/layout/AppHeader.vue` (Link, dunkel), `components/layout/AppFooter.vue` (ohne Link, hell), `components/auth/AuthShell.vue` (Link, hell). Das Mobilmenü zeigt kein Logo (Überschrift „Menü“), die Fehlerseite nutzt Header und Footer über `AppLayout`.
- **Icons:** `scripts/brand/generate-icons.mjs`; Manifest `public/site.webmanifest` (name PlantMel, theme_color `#143D2D`, background_color `#F2F5EA`, display `browser` – keine installierbare App).

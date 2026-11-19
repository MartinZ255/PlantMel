# PlantMel Design-System „Natur & organisch“ (Gate C1b)

Stand: 2026-11-19 · Grundlage: ADR-003 und **ADR-003 Revision 1** (`DECISIONS.md`) · Umfang Schritt 1: Tokens, Bausteine, Kopfnavigation, Footer, Startseite, Rezeptliste, Rezeptdetail. Ersetzt den abgelehnten Entwurf „Frisch & klar“ (Gate C1).

Alle Werte stehen als CSS-Variablen in `resources/css/app.css` (`@theme static`). Daraus erzeugt Tailwind v4 Utilities (`bg-forest`, `text-on-forest`, `bg-matcha`, `text-berry`, `font-display`, `shadow-float` …); eigene Regeln nutzen `var(--color-…)`. Die Token-Namen aus Gate C1 (`surface`, `canvas`, `tint`, `primary`, `accent` …) bleiben erhalten, nur die Werte sind neu – so funktionieren die noch nicht umgebauten Seiten unverändert weiter.

## 0. Gestaltungsidee

PlantMel soll sich anfühlen wie ein handgemachtes Rezeptbuch aus dem Kräutergarten, nicht wie ein Software-Template: **tiefes Waldgrün** trägt große Flächen (Bänder, Rezeptkopf, Footer, Mobilmenü) und gibt der Marke Gewicht; **Fraunces** – eine weiche, leicht eigenwillige Display-Serif – setzt große Headlines mit kursiven Akzentwörtern; **Bilder liegen in Blob-Masken** statt in Rechtecken, die beim Hover ihre Form wandeln; Abschnitte gehen über **Wellen** ineinander über; selbst gezeichnete **Zweige und Blätter** schweben am Rand; **Matcha** und ein sparsames **Rote-Bete-Rot** setzen lebendige Akzente (Unterstreichung, Sterne, Sticker). Bedienelemente haben eine „Kiesel“-Form (leicht unregelmäßige Rundung, die beim Hover atmet), Kategorien sind kleine „Samen“.

## 1. Farben

### Begründung der Palette

- **Waldgrün statt Kräutergrün:** ein dunkler, satter Ton trägt große Flächen, ohne grell zu wirken, und erlaubt hellen Text mit sehr hohem Kontrast (on-forest 10,96:1). Er unterscheidet PlantMel klar vom generischen „SaaS-Grün“.
- **Matcha/Limette** als frischer Gegenpol: auf Waldgrün leuchtet er (8,47:1), auf Hell dient er als Fläche mit Waldgrün-Text.
- **Moos** nur als Grafikton (Blätter, Keimlinge) – er verbindet Matcha und Waldgrün.
- **Rote Bete (Beere)** als warmer Akzent statt Tomate: wirkt hochwertiger, ist „pflanzlich“ und erreicht als Text ≥ 4,5:1 auf allen hellen Flächen – damit sind Sterne, Unterstreichungen und Hinweise ohne Zusatzton möglich.
- **Naturgrund** `#F2F5EA` (leicht grünlich) statt Reinweiß; Papierflächen `#FCFDF8`.
- **Bewusst kein** Creme/Salbei/Pfirsich-Dreiklang (altes Design).

| Token (Utility-Name) | Wert | Zweck |
|---|---|---|
| `canvas` | `#F2F5EA` | Naturgrund (Seitenhintergrund) |
| `surface` | `#FCFDF8` | Papier: Zutatenblatt, Dialoge, Felder, Such-Kapsel |
| `tint` / `tint-strong` | `#E4EDD3` / `#D2E3B5` | helle Grünflächen (Notizen, Hover, Leerzustand), Verläufe |
| `ink` / `ink-muted` | `#142A1F` / `#48594C` | Text / gedämpfter Text |
| `forest` | `#143D2D` | **tragender Ton**: Vorteil-Band, Rezeptkopf |
| `forest-deep` | `#0D2A1F` | Footer, Mobilmenü, Eckdaten-Kiesel |
| `forest-soft` | `#1F5A41` | Blobs/Hover auf Waldgrün |
| `on-forest` / `on-forest-muted` | `#F2F5EA` / `#B9CFB6` | Text auf Waldgrün |
| `primary` (+ `-hover`, `-active`) | `#1D5A3C` (`#174A31`, `#113826`) | Hauptaktion auf hellem Grund, Links, Fokus |
| `matcha` / `matcha-hover` | `#C8E28A` / `#B8D673` | Akzent auf Waldgrün, Flächen mit Waldgrün-Text, aktive Navigation |
| `moss` | `#5E8C3A` | **nur Grafik** (Blätter, Keimlinge), kein Text |
| `berry` / `berry-hover` / `berry-surface` | `#A62D5B` / `#8E2550` / `#F7E7EC` | Akzent: Sterne, Unterstreichungen, Sticker, Gesamtbewertung |
| `accent`, `accent-strong`, `accent-hover`, `accent-surface` | = Beere | Aliasse aus Gate C1 |
| `line` / `line-strong` | `#D6DEC8` / `#6B7A68` | dekorativ / Bedienelement-Rahmen (≥ 3:1) |
| `danger` / `danger-hover` / `danger-surface` | `#B42318` / `#912018` / `#FDEFEC` | Fehler, Löschen |
| `danger-on-forest` | `#FFC2B8` | Löschen-Auslöser auf Waldgrün |
| `success` / `warning` (+ `-surface`) | `#1D5A3C` / `#7F4E00` | Status |
| `focus` | `#1D5A3C`; auf Waldgrün `#C8E28A` | Fokus-Ring 2 px; die Utility `on-forest` schaltet `--color-focus` auf Matcha um |
| `star` / `star-empty` | `#A62D5B` / `#6B7A68` | Sterne; auf Waldgrün Matcha / on-forest-muted (`StarRating tone="on-forest"`) |

### Kontrasttabelle (WCAG 2.x, rechnerisch)

Berechnet mit der WCAG-Formel (relative Luminanz, sRGB) per Node-Skript; Text braucht ≥ 4,5:1, große Schrift, Bedienelemente, Grafik und Fokus ≥ 3:1. Halbtransparente Flächen und Verläufe sind **am ungünstigsten Punkt** gemischt gerechnet (Zeilen mit Klartext). Im Browser bestätigt `regression-gate-c.spec.ts` („Kontrast wichtiger Paare“) die Werte per `getComputedStyle` (Abweichung < 0,05); axe `color-contrast` = 0 auf allen drei Musterseiten bei 390 und 1440 px.

| Paar | Verhältnis | Anforderung | Ergebnis |
|---|---|---|---|
| `ink` auf `canvas` | 13,78:1 | Text 4,5 | erfüllt |
| `ink` auf `surface` | 14,88:1 | Text 4,5 | erfüllt |
| `ink` auf `tint` | 12,57:1 | Text 4,5 | erfüllt |
| `ink` auf `tint-strong` | 11,15:1 | Text 4,5 | erfüllt |
| `ink` auf `matcha` | 10,65:1 | Text 4,5 | erfüllt |
| `ink` auf `berry-surface` | 12,74:1 | Text 4,5 | erfüllt |
| `ink-muted` auf `canvas` / `surface` / `tint` / `matcha` | 6,77 / 7,31 / 6,18 / 5,23:1 | Text 4,5 | erfüllt |
| `on-forest` auf `primary` / `primary-hover` / `primary-active` | 7,37 / 9,23 / 11,76:1 | Text 4,5 | erfüllt |
| `primary` auf `canvas` / `surface` / `tint` / `matcha` | 7,37 / 7,96 / 6,73 / 5,70:1 | Text 4,5 | erfüllt |
| `on-forest` auf `forest` / `forest-deep` / `forest-soft` | 10,96 / 13,91 / 7,33:1 | Text 4,5 | erfüllt |
| `on-forest-muted` auf `forest` / `forest-deep` / `forest-soft` | 7,30 / 9,26 / 4,88:1 | Text 4,5 | erfüllt |
| `on-forest-muted` auf Moos-Blob (30 % Moos über forest, Zentrum) | 5,19:1 | Text 4,5 | erfüllt |
| `on-forest` auf Moos-Blob (Zentrum) | 7,79:1 | Text 4,5 | erfüllt |
| `on-forest-muted` auf Vorteil-Kachel (forest-deep 45 % über forest-soft) | 6,52:1 | Text 4,5 | erfüllt |
| `matcha` auf `forest` / `forest-soft` / `forest-deep` | 8,47 / 5,66 / 10,75:1 | Text 4,5 | erfüllt |
| `forest` auf `matcha` / `matcha-hover` | 8,47 / 7,44:1 | Text 4,5 | erfüllt |
| `berry` auf `canvas` / `surface` / `berry-surface` / `tint` | 6,04 / 6,53 / 5,59 / 5,51:1 | Text 4,5 | erfüllt |
| `berry` auf `matcha` (Akzentwort „Neues“, große Schrift) | 4,67:1 | große Schrift 3 (auch 4,5) | erfüllt |
| Weiß auf `berry` (Filter-Zähler) | 6,67:1 | Text 4,5 | erfüllt |
| `danger` auf `canvas` / `surface` / `danger-surface` | 5,96 / 6,43 / 5,87:1 | Text 4,5 | erfüllt |
| Weiß auf `danger` / `danger-hover` | 6,57 / 8,66:1 | Text 4,5 | erfüllt |
| `danger-on-forest` auf `forest` / `forest-soft` (Hover) | 7,87 / 5,26:1 | Text 4,5 | erfüllt |
| `berry-surface` auf `forest-deep` („Abmelden“ im Mobilmenü) | 12,86:1 | Text 4,5 | erfüllt |
| `warning` auf `warning-surface` | 6,24:1 | Text 4,5 | erfüllt |
| `line-strong` auf `canvas` / `surface` / `tint` | 4,13 / 4,46 / 3,77:1 | Bedienelement 3 | erfüllt |
| `focus` (primary) auf `canvas` | 7,37:1 | Fokus 3 | erfüllt |
| Fokus auf Waldgrün (`matcha`) auf `forest` / `forest-deep` | 8,47 / 10,75:1 | Fokus 3 | erfüllt |
| `star` (berry) auf `canvas` / `berry-surface` | 6,04 / 5,59:1 | Grafik 3 | erfüllt (plus Text „x von 5“) |
| Stern auf Waldgrün (`matcha`) auf `forest` | 8,47:1 | Grafik 3 | erfüllt |
| `moss` auf `canvas` / `surface` | 3,60 / 3,88:1 | Grafik 3 | erfüllt (nur Ornamente) |
| `line` auf `surface` | 1,36:1 | – | nur dekorativ |

Text über Bildern: nur auf eigenem Hintergrund (Dauer-Sticker `surface/90–95`, schwebende Karte `surface/95`).

## 2. Typografie

- **Display:** **Fraunces** (Paket `@fontsource-variable/fraunces` **5.3.0**, exakt gepinnt, Lizenz **OFL-1.1**, per `npm view` geprüft), selbst gehostet, nur Subset `latin`, nur woff2:
  - aufrecht `fraunces-latin-full-normal.woff2` (121 016 Byte): alle Achsen `wght` 100–900, `opsz` 9–144, `SOFT` 0–100, `WONK` 0–1
  - kursiv `fraunces-latin-opsz-italic.woff2` (81 520 Byte): `wght` + `opsz`; nur für Akzentwörter, lädt erst bei Bedarf
  - Utilities: `font-display` (opsz automatisch, `SOFT 60`, für h2/h3), `font-display-wonk` (`SOFT 100`, `WONK 1`, für große Headlines), `accent-word` (kursiv, weich, Gewicht 500)
- **Fließtext:** Instrument Sans 400/500/600 (unverändert, selbst gehostet). Kein `font-bold` für Instrument Sans.
- **Skala** (`text-*`, rem): xs 0,75 · sm 0,875 · base 1 · lg 1,125 · xl 1,25 · 2xl 1,5 · 3xl 1,875 · 4xl 2,375 · 5xl 3,25 · 6xl 4,25 · 7xl 5,5. Hero-Headline `clamp(2.4rem, 10.5vw, 5.75rem)`, Rezepttitel `clamp(2.25rem, 8vw, 4.5rem)`. Eingabefelder mindestens 16 px.
- **Überschriften:** genau ein `h1` je Seite; Abschnitte `h2` in Fraunces (3xl–5xl), Karten `h3`.
- **Lange Wörter:** `text-wrap-anywhere` (jetzt als `@utility`), Hero mit weichem Trennstrich (`Lieblings&shy;rezepte`).

## 3. Formen, Tiefe, Bewegung

- **Blob-Masken:** sechs Formen `blob-1` … `blob-6` (je Ruhe- und Hover-Form, `--blob-Na/-Nb`), Utility `blob-mask`; in einer `.group` wechselt die Form bei Hover/Fokus (900 ms, `--ease-organic`), das Bild zoomt leicht.
- **Kiesel / Samen:** `--pebble` (Buttons, Navigation, Such-Feld; `pebble-morph` atmet beim Hover), `--seed` (Chips, Badges, Checkboxen).
- **Wellen:** `WaveDivider` (3 Kurven, `flip`); sie liegen **in** Waldgrün-Flächen und sind in der Farbe des Naturgrunds gefüllt – so reichen Körnung und Verlauf nahtlos bis an die Welle.
- **Körnung:** winzige SVG-Noise-Kachel als Daten-URL (`grain`, `grain-light`), nur auf Waldgrün- und Matcha-Flächen; keine Bitmaps, keine Anfrage.
- **Schatten:** waldgrün getönt: `shadow-card`, `shadow-raised`, `shadow-float` (schwebende Bilder/Karten), `shadow-berry`, `shadow-overlay`.
- **Bewegung:** `useReveal`/`Reveal` (Einblenden beim Scrollen, nur `opacity`/`translate`, nur für Elemente unterhalb des Sichtbereichs), `animate-float`, `animate-float-slow`, `animate-sway` (Ornamente), `animate-morph` (Blobs). Bei `prefers-reduced-motion: reduce` ist alles aus: keine Animation, keine versteckten Elemente, Übergänge ≈ 0 (Test `regression-gate-c1b.spec.ts`). Druck: alles sichtbar.
- **Radien:** sm 0,5 · md 0,875 · lg 1,25 · xl 1,75 · 2xl 2,25 · 3xl 3 rem; große Flächen mit asymmetrischen Radien (z. B. `rounded-[2.25rem_1.75rem_2.5rem_2rem]`).

## 4. Ebenen (z-index) und Maße

| Variable | Wert | Verwendung |
|---|---|---|
| `--z-raised` | 10 | Inhalt über Dekoration |
| `--z-header` | 1100 | Kopfzeile (`sticky`, halbtransparent mit Unschärfe) |
| `--z-dropdown` | 1200 | Aufklappmenüs |
| `--z-modal-backdrop` / `--z-modal` | 1300 / 1310 | Dialog, Mobilmenü |
| `--z-skip-link` | 1400 | Skip-Link |

`--header-height` 4,25 rem, `--content-max` 80 rem. Breakpoints unverändert (Tailwind `sm 640`, `md 768`, `lg 1024`, `xl 1280`; Mobilmenü und Filter-Dialog unter `md`).

## 5. Bausteine (`resources/js/components/ui/`)

| Baustein | Props | C1b |
|---|---|---|
| `Button` | `variant` (`primary`·`secondary`·`ghost`·`danger`·**`lime`**·**`berry`**·**`outline-light`**), `size`, `href`, `external`, `type`, `disabled`, `loading`, `block` | Kiesel-Form mit Hover-Atmen, Anheben, waldgrüner Schatten. `lime`/`outline-light` für Waldgrün-Flächen |
| `Card` | `as`, `padded`, `interactive` | Papier mit organischen Ecken, Schatten statt Rahmen |
| `Field`, `TextInput`, `Textarea` | wie bisher | Kiesel-Ecken, 1,5-px-Rahmen `line-strong`, Hover Primär |
| `Chip` | `pressed`, `removable`, `removeLabel`, `disabled` | „Samen“-Form; gedrückt Waldgrün mit Keimling-Symbol |
| `Badge` | `tone` (+ **`matcha`** für Waldgrün) | Samen-Form mit kleinem Korn als Punkt |
| `Dialog` | wie bisher | organische Ecken, Fraunces-Titel, Zweig-Ornament, Waldgrün-Hintergrund mit Unschärfe |
| `StarRating` | wie bisher + **`tone`** (`default`·`on-forest`) | Sterne in Beere bzw. Matcha auf Waldgrün; Textäquivalent unverändert |
| `EmptyState` | wie bisher | Blob-Symbol, Fraunces-Titel, Zweige statt gestricheltem Kasten |
| `PageHeader` | wie bisher + **`size`** (`md`·`xl`), Slot `#title` | Fraunces-wonk-Titel, Dachzeile mit Keimling |
| **`BlobImage`** (neu) | `src`, `alt`, `shape` 1–6, `ratio` (`4/3`·`1/1`·`5/4`·`4/5`·`3/2`), `eager`, `sizes`; Slots `#fallback`, `#overlay` | Bild in Blob-Maske mit festem Seitenverhältnis; `loading="lazy"` außer `eager`; ohne Bild organische Komposition |
| **`BlobBackdrop`** (neu) | `tone` (`light`·`forest`) | weiche, atmende Verlaufs-Blobs (nur CSS), hell nach unten auslaufend |
| **`WaveDivider`** (neu) | `flip`, `variant` 1–3 | welliger Übergang, `currentColor` |
| **`LeafOrnament`** (neu) | `variant` (`leaf`·`sprig`·`eucalyptus`·`sprout`) | eigene SVG-Pfade, immer `aria-hidden` |
| **`Reveal`** (neu) | `as`, `delay` | Einblenden beim Scrollen (`useReveal`) |

Weitere neue Bausteine: `components/landing/LandingHeroArt.vue` (Bildkomposition), `components/landing/FeaturedRecipeBand.vue` (wischbares Band), `components/recipes/IngredientChecklist.vue` (Zutaten zum Abhaken). Composables: `useReveal` (+ `prefersReducedMotion`), `useModal`, `useDisclosure`, `useAppNavigation`.

## 6. Musterseiten

- **Startseite:** Hero mit Fraunces-Headline („Lieblingsrezepte, *liebevoll* gesammelt.“, Beeren-Strich unter dem Akzentwort), Bildkomposition aus bis zu drei echten Rezeptbildern (Prop `featuredRecipes`) auf atmendem Matcha-Blob, schwebende Karte „Zuletzt hinzugefügt“; Waldgrün-Band mit Wellen für die drei Vorteile (E8: nur vorhandene Funktionen); wischbares Band „Eine kleine Auswahl“ (Scroll-Snap, ab `md` Zurück/Weiter-Knöpfe, versetzte Höhen); Abschluss auf großem Matcha-Blob. Ohne Rezeptbilder: grafische Komposition, kein Band.
- **Rezeptliste:** Kopf mit Blob-Hintergrund und schwebenden Blättern, „Rezepte“ in großer Fraunces, Suche als schwebende Kapsel (Waldgrün-Blob als Suchsymbol); Karten ohne Kasten: Bild in Blob-Maske über einer versetzten zweiten Form, Dauer-Sticker, Samen-Kategorien, Titel in Fraunces, Sterne mit Text; leicht gestaffeltes Raster (DOM-Reihenfolge = Lesereihenfolge).
- **Rezeptdetail:** Waldgrün-Kopf mit Körnung, Titel in großer Fraunces, Eckdaten als schwebende Kiesel, Bild in großer Blob-Maske auf Matcha-Blob mit Beeren-Sticker (ohne Bild: Komposition aus Blatt, Topf, Zweig); Welle in den Naturgrund; Zutaten als Papierblatt mit **Abhak-Checkboxen** (rein clientseitig, Fortschritt als Live-Text); Schritte mit großen Fraunces-Ziffern in wechselnden Blob-Formen, verbunden durch eine gestrichelte Ranke; Notizen als Hinweis mit Blatt; Bewertungen mit großer Gesamtzahl in Beere und Balken je Dimension (Balken `aria-hidden`, Wert als Text).
- **Kopfnavigation/Footer/Mobilmenü:** Kopf halbtransparent mit Unschärfe, Logo = Keimling im Waldgrün-Blob + „Plant*Mel*“; aktiver Bereich als Matcha-Kiesel; Footer Waldgrün mit welliger Oberkante und Zweigen; Mobilmenü als Waldgrün-Blatt mit großen Einträgen und Matcha-Aktivzustand.

## 7. Regeln

- `primary` (Waldgrün) für die Hauptaktion auf hellem Grund, `lime` für die Hauptaktion auf Waldgrün; `secondary`/`outline-light` für Alternativen; `ghost` für Abbrechen; `danger` nur für die bestätigende Löschaktion. Der auslösende Lösch-Knopf ist `ghost` mit `text-danger` (hell) bzw. `text-danger-on-forest` (Waldgrün).
- Moos nie als Textfarbe; Beere sparsam (Sterne, Unterstreichung, Sticker, Akzentwort).
- Bewertung nie nur über Farbe: immer Text „x von 5“. Abgehakte Zutaten: Häkchen **und** Durchstreichung.
- Bilder: feste Seitenverhältnisse, `object-cover`, `loading="lazy"` außer dem ersten sichtbaren Bild (Hero-Hauptbild, Rezeptkopf). Ornamente und Blobs sind `aria-hidden` und liegen in Abschnitten mit `overflow-hidden`.
- Interne Navigation mit Inertia-`Link`; geschlossene Menüs `inert` + unsichtbar.
- Kein horizontales Scrollen bis 320 px. Das Rezeptband scrollt in sich (eigener Scrollbereich).
- Bewegung immer mit `prefers-reduced-motion`-Fallback; Einblenden verändert nie das Layout.

## 8. Was im zweiten Schritt umzustellen ist

Unverändert gegenüber Gate C1: Diese Dateien nutzen noch Klassen aus `resources/css/rezeptly/app.css` (funktionieren mit Kopfnavigation, Footer und neuen Tokens weiter, werden aber erst in Schritt 2 gestaltet):

- Seiten: `pages/Home.vue`, `pages/IngredientSearch.vue`, `pages/MyIngredients.vue`, `pages/Error.vue`, `pages/collections/{Index,Create,Show}.vue`, `pages/recipes/{Create,Edit}.vue`, `pages/settings/{Index,Profile,Password}.vue`, `pages/auth/*.vue`, `pages/legal/{Impressum,Datenschutz}.vue`
- Komponenten: `recipes/RecipeForm.vue` (dort `StarRating` im Eingabemodus), `recipes/ImportProgress.vue`, `recipes/IngredientSearchArea.vue`, `recipes/IngredientCard.vue`, `recipes/CollectionCard.vue`, `recipes/RecipeCollectionBoard.vue`, `widgets/{IngredientAlternativesModal,IngredientEditModal,TagEditModal}.vue`
- `recipes/RecipeList.vue` wird nirgends verwendet (Gate D: entfernen)
- Danach `rezeptly/app.css` und den Import in `app.css` entfernen (dabei auch `html, body { height: 100%; overflow-x: hidden }`, das `<body>` zum Scroll-Container macht).

`collections/Show.vue` nutzt bereits die neue `RecipeDetailCard` (Blob-Karte).

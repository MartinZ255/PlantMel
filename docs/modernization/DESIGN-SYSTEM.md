# PlantMel Design-System „Frisch & klar“ (Gate C, Schritt 1)

Stand: 2026-11-19 · Grundlage: ADR-003 (`DECISIONS.md`) · Umfang Schritt 1: Tokens, Bausteine, Kopfnavigation, Startseite, Rezeptliste, Rezeptdetail.

Alle Werte stehen als CSS-Variablen in `resources/css/app.css` (`@theme static`). Daraus erzeugt Tailwind v4 Utilities (`bg-primary`, `text-ink`, `border-line-strong`, `rounded-lg`, `shadow-card` …); eigene Regeln nutzen `var(--color-…)`. Neue Bausteine hängen nicht von `resources/css/rezeptly/app.css` ab.

## 1. Farben

| Token (Utility-Name) | Wert | Zweck |
|---|---|---|
| `surface` | `#FFFFFF` | Karten, Kopfzeile, Dialoge, Eingabefelder |
| `canvas` | `#F6F8F5` | Seitenhintergrund |
| `tint` | `#EAF2EC` | Flächen: aktive Navigation, Badges, Bildplatzhalter, Hover |
| `tint-strong` | `#D5E7DA` | Active-Zustand auf `tint`, Verläufe |
| `ink` | `#17211B` | Fließtext, Überschriften |
| `ink-muted` | `#4B5A50` | gedämpfter Text (Beschreibungen, Hinweise, Metadaten, Platzhalter) |
| `on-primary` / `on-accent` | `#FFFFFF` | Text auf Primär-/Akzentflächen |
| `line` | `#DCE3DD` | **nur dekorativ**: Trennlinien, Kartenrand |
| `line-strong` | `#6F7D73` | Rahmen von Bedienelementen (Eingabefelder, Chips) |
| `primary` | `#1F7A4D` | Kräutergrün: Hauptaktionen, Links, aktive Zustände |
| `primary-hover` | `#19663F` | Hover |
| `primary-active` | `#145233` | gedrückt |
| `accent` | `#E4572E` | Tomate: **nur Grafik/Flächen ohne Text** (Sterne, Formen) |
| `accent-strong` | `#B8401C` | Tomate für Text bzw. Flächen mit weißer Schrift |
| `accent-hover` | `#A93A19` | Hover von `accent-strong` |
| `accent-surface` | `#FDECE6` | helle Akzentfläche (Notizen) |
| `danger` / `danger-hover` / `danger-surface` | `#B42318` / `#912018` / `#FEF1EF` | Fehler, Löschen |
| `success` / `success-surface` | `#1F7A4D` / `#EAF2EC` | Erfolgsmeldungen |
| `warning` / `warning-surface` | `#8A5300` / `#FFF6E0` | Hinweise |
| `focus` | `#1F7A4D` | Fokus-Ring (2 px, 2 px Abstand) |
| `star` / `star-empty` | `#E4572E` / `#6F7D73` | Sterne gefüllt / leer |

### Kontrasttabelle (WCAG 2.x, rechnerisch)

Berechnet mit der WCAG-Formel (relative Luminanz, sRGB) per Node-Skript; Text braucht ≥ 4,5:1, große Schrift, Bedienelemente, Grafik und Fokus ≥ 3:1. Im Browser bestätigt der Test `regression-gate-c.spec.ts` („Kontrast wichtiger Paare“) die gemessenen Werte per `getComputedStyle` (Abweichung < 0,05).

| Paar | Verhältnis | Anforderung | Ergebnis |
|---|---|---|---|
| `ink` auf `surface` | 16,54:1 | Text 4,5 | erfüllt |
| `ink` auf `canvas` | 15,49:1 | Text 4,5 | erfüllt |
| `ink` auf `tint` | 14,50:1 | Text 4,5 | erfüllt |
| `ink` auf `accent-surface` | 14,43:1 | Text 4,5 | erfüllt |
| `ink-muted` auf `surface` | 7,30:1 | Text 4,5 | erfüllt |
| `ink-muted` auf `canvas` | 6,83:1 | Text 4,5 | erfüllt |
| `ink-muted` auf `tint` | 6,40:1 | Text 4,5 | erfüllt |
| Weiß auf `primary` | 5,32:1 | Text 4,5 | erfüllt |
| Weiß auf `primary-hover` | 6,97:1 | Text 4,5 | erfüllt |
| Weiß auf `primary-active` | 9,19:1 | Text 4,5 | erfüllt |
| `primary` auf `surface` | 5,32:1 | Text 4,5 | erfüllt |
| `primary` auf `canvas` | 4,98:1 | Text 4,5 | erfüllt |
| `primary` auf `tint` | 4,66:1 | Text 4,5 | erfüllt |
| **`accent` (#E4572E) auf Weiß** | **3,68:1** | Text 4,5 | **nicht erfüllt → nur Grafik**, für Text `accent-strong` |
| **Weiß auf `accent`** | **3,68:1** | Text 4,5 | **nicht erfüllt → Flächen mit Text nutzen `accent-strong`** |
| `accent-strong` auf `surface` | 5,54:1 | Text 4,5 | erfüllt |
| `accent-strong` auf `accent-surface` | 4,84:1 | Text 4,5 | erfüllt |
| Weiß auf `accent-strong` | 5,54:1 | Text 4,5 | erfüllt |
| Weiß auf `accent-hover` | 6,35:1 | Text 4,5 | erfüllt |
| `danger` auf `surface` / `canvas` / `danger-surface` | 6,57 / 6,16 / 5,96:1 | Text 4,5 | erfüllt |
| Weiß auf `danger` / `danger-hover` | 6,57 / 8,66:1 | Text 4,5 | erfüllt |
| `warning` auf `warning-surface` | 5,88:1 | Text 4,5 | erfüllt |
| `line-strong` auf `surface` / `canvas` / `tint` | 4,32 / 4,05 / 3,79:1 | Bedienelement 3 | erfüllt |
| `focus` auf `surface` / `canvas` / `tint` | 5,32 / 4,98 / 4,66:1 | Fokus 3 | erfüllt |
| `star` auf `surface` / `canvas` | 3,68 / 3,45:1 | Grafik 3 | erfüllt (zusätzlich Text „x von 5“) |
| `star-empty` auf `surface` | 4,32:1 | Grafik 3 | erfüllt |
| `line` auf `surface` | 1,31:1 | – | nur dekorativ (Bedienelemente nutzen `line-strong`) |

## 2. Typografie, Abstände, Radien, Schatten

- **Schrift:** Instrument Sans (selbst gehostet), nur Gewichte 400 (`font-normal`), 500 (`font-medium`), 600 (`font-semibold`). Kein `font-bold` (700 ist nicht eingebunden).
- **Schriftgrößen** (`text-*`, rem): `xs` 0,75 · `sm` 0,875 · `base` 1 · `lg` 1,125 · `xl` 1,25 · `2xl` 1,5 · `3xl` 1,875 · `4xl` 2,25 · `5xl` 3. Eingabefelder immer mindestens 16 px (`text-base`, kein iOS-Zoom).
- **Überschriften:** genau ein `h1` je Seite (`PageHeader` bzw. Seitentitel), Abschnitte `h2` (`text-xl`/`2xl`), Karten `h3` (`text-lg`). Die Kopfzeile enthält keine Überschrift.
- **Abstände:** Tailwind-Spacing (4-px-Raster). Seitenrand `px-4` / `sm:px-6` / `lg:px-8`, Inhaltsbreite `max-w-(--content-max)` = 80 rem, Fließtext-Spalten `max-w-5xl`. Abschnitte `py-8` bis `py-20`, Karten `p-4`/`sm:p-5`.
- **Radien:** `rounded-sm` 6 px · `md` 10 px (Eingaben) · `lg` 16 px · `xl` 20 px (Karten) · `2xl` 28 px (große Flächen, Dialoge) · `rounded-full` (Buttons, Chips, Badges).
- **Schatten:** `shadow-card` (Karten), `shadow-raised` (Hover, schwebende Elemente), `shadow-overlay` (Menüs, Dialoge).
- **Übergänge:** 150 ms, `cubic-bezier(0.2, 0, 0, 1)`; bei `prefers-reduced-motion: reduce` global praktisch 0.
- **Lange Wörter (F-32):** Utility `text-wrap-anywhere` (`overflow-wrap: anywhere; hyphens: auto`) an Titeln, Badges, Chips, Zutaten, Schritten.

## 3. Ebenen (z-index)

| Variable | Wert | Verwendung |
|---|---|---|
| `--z-raised` | 10 | Inhalt über Dekoration |
| `--z-header` | 1100 | feste Kopfzeile (`sticky`) |
| `--z-dropdown` | 1200 | Aufklappmenüs der Kopfzeile |
| `--z-modal-backdrop` | 1300 | Dialog-Hintergrund, Mobilmenü (wie seit Slice 3) |
| `--z-modal` | 1310 | Dialog |
| `--z-skip-link` | 1400 | Skip-Link bei Fokus |

Nutzung: `z-(--z-header)`. Weitere Layoutmaße: `--header-height` 4 rem, `--content-max` 80 rem.

## 4. Breakpoints

Tailwind-Standard, mobil zuerst: `sm` 640 px · `md` 768 px · `lg` 1024 px · `xl` 1280 px. Unter `md` gibt es das Mobilmenü und den Filter als Dialog; ab `md` Navigation und Filter im Fluss. JavaScript nutzt dieselbe Grenze (`(min-width: 48rem)`).

## 5. Bausteine (`resources/js/components/ui/`)

| Baustein | Props | Hinweise |
|---|---|---|
| `Button` | `variant` (`primary`·`secondary`·`ghost`·`danger`), `size` (`sm`·`md`·`lg`), `href`, `external`, `type`, `disabled`, `loading`, `block` | Ohne `href` `<button type="button">`, mit `href` Inertia-`Link`. `loading` → deaktiviert + `aria-busy`. Zusätzliche Klassen werden per tailwind-merge eingemischt. Mindesthöhe 36/44/48 px. |
| `Card` | `as`, `padded`, `interactive` | Slots `media` (oben), Standard, `footer`. Für ganz klickbare Karten den Titel-Link mit `after:absolute after:inset-0` versehen (siehe `RecipeDetailCard`). |
| `Field` | `id`, `label`, `required`, `hint`, `error`, `labelHidden` | Slot-Prop `inputAttrs` (id, `required`, `aria-required`, `aria-invalid`, `aria-describedby` auf `<id>-hint`/`<id>-error`). Muster aus dem Rezeptformular. |
| `TextInput` / `Textarea` | `v-model`, weitere Attribute | 16 px, Rahmen `line-strong`, Fehler über `aria-invalid`. |
| `Chip` | `pressed`, `removable`, `removeLabel`, `disabled` | Umschalter mit `aria-pressed` oder entfernbarer Filter (Name „Filter entfernen: …“). |
| `Badge` | `tone` (`neutral`·`primary`·`accent`·`success`·`warning`·`danger`) | Nur Anzeige. |
| `Dialog` | `open`, `title`, `description`, `size`, `closeLabel`, `dismissible`; Event `close` | Teleport, `role="dialog"`, `aria-modal`, `aria-labelledby`/`-describedby`, Fokus auf erstes Bedienelement, Fokusfalle, Escape, Scroll-Sperre, `#app` inert, Fokus zurück. Slot `footer` für Aktionen. |
| `StarRating` | `value`, `count`, `mode` (`display`·`input`), `compact`, `size`, `label`, `max`, `v-model` | Anzeige: Sterne `aria-hidden`, Text „3,3 von 5 (4 Bewertungen)“ bzw. kompakt „3“ + „von 5“ für Screenreader; ohne Wert „Noch keine Bewertung“. Eingabe: native Radiogroup (Pfeiltasten), „Bewertung entfernen“. **Eingabe noch nicht eingesetzt** (Schritt 2: Rezeptformular). |
| `EmptyState` | `title`, `description`, `icon`, `headingLevel` | Slot `actions`. |
| `PageHeader` | `title`, `description`, `eyebrow` | Genau ein `h1`; Slots `actions`, `meta`. |

Beispiel Formularfeld:

```vue
<Field id="name" label="Name" required :error="form.errors.name" v-slot="{ inputAttrs }">
    <TextInput v-model="form.name" v-bind="inputAttrs" autocomplete="name" />
</Field>
```

Beispiel Dialog:

```vue
<Dialog :open="open" title="Rezept löschen?" description="…" size="sm" @close="open = false">
    <template #footer>
        <Button variant="ghost" @click="open = false">Abbrechen</Button>
        <Button variant="danger" :loading="busy" @click="remove">Endgültig löschen</Button>
    </template>
</Dialog>
```

Composables: `useModal` (jetzt mit Option `trapFocus`, Standard aus für Bestandsdialoge), `useDisclosure` (Aufklappmenüs: Escape mit Fokus zurück, Außenklick, Fokusverlust, Inertia-Navigation), `useAppNavigation` (Einträge, Ziele, aktive Zustände der Navigation).

## 6. Regeln

- **primary** genau für die Hauptaktion eines Bereichs (z. B. „Rezepte entdecken“, „Zur Sammlung hinzufügen“, „Hinzufügen“). **secondary** für gleichwertige Alternativen (Anmelden, Bearbeiten), **ghost** für Abbrechen/Schließen und Nebenwege, **danger** nur für die bestätigende Löschaktion im Dialog; der auslösende Lösch-Button ist `ghost` mit Danger-Farbe.
- Tomate (`accent`) nie als Textfarbe auf Hell und nie als Fläche unter weißer Schrift; dafür `accent-strong`.
- Bewertung nie nur über Farbe: immer Text „x von 5“.
- Bilder mit festem Seitenverhältnis (`aspect-[4/3]` Karten, `16/9`–`21/9` Detail), `object-cover`, in Listen `loading="lazy"`; ohne Bild eine dekorative Fläche (`aria-hidden`), kein leerer Kasten.
- Interne Navigation mit Inertia-`Link` (F-54); `<a href>` nur für Anker (Skip-Link).
- Geschlossene Menüs: `inert` + `visibility: hidden` + `pointer-events: none` (oder gar nicht im DOM). Modale Ebenen über `useModal`.
- Kein horizontales Scrollen bis 320 px; Inhaltscontainer `min-w-0`, Titel mit `text-wrap-anywhere`.

## 7. Kopfnavigation

- **Ab `md`:** Logo (Gäste `/`, Angemeldete `/home`, `aria-current` auf der Startseite) · Hauptnavigation „Rezepte“, „Zutatensuche“, „Sammlungen“, „Meine Zutaten“ (`aria-current="page"` nur auf der exakten Seite, Bereich optisch hervorgehoben) · rechts „Rezept erstellen“ (Hosts), Benutzer-Menü (Angemeldet als …, Einstellungen, Abmelden) bzw. für Gäste „Anmelden“ und „Registrieren“.
- **Sammlungen:** Für Angemeldete ein Aufklappmenü mit „Alle Sammlungen“, den eigenen Sammlungen (aktuelle mit `aria-current`) und „Neue Sammlung“. Begründung: Die Seitenleiste machte jede Sammlung mit einem Klick erreichbar; das Menü erhält diesen Direktzugriff (zwei Klicks) ohne dauerhaft Platz zu belegen; die Übersicht bleibt der erste Eintrag. Für Gäste ist „Sammlungen“ ein Link mit Schloss-Symbol und „(Anmeldung erforderlich)“ für Screenreader.
- **Unter `md`:** Menü-Knopf (`aria-expanded`, `aria-controls="mobile-menu"`) öffnet ein Seitenblatt von rechts (`role="dialog"`): alle Einträge inkl. eigener Sammlungen, Konto-Aktionen. Fokus auf „Startseite“, Fokusfalle, Escape/Schließen-Knopf/Tippen auf den Hintergrund schließen, Fokus zurück an den Menü-Knopf, Scroll-Sperre, Hintergrund `inert`, nach Navigation geschlossen.
- **Einschränkung:** Der Server teilt `is_host` nicht global; „Rezept erstellen“ erscheint deshalb nur auf Seiten, die `isHost` liefern (Rezeptliste, -detail, -bearbeiten). Schritt 2: `auth.user.is_host` in `HandleInertiaRequests` teilen (Backend-Änderung, eigene Freigabe).

## 8. Was im zweiten Schritt umzustellen ist

Diese Dateien nutzen noch Klassen aus `resources/css/rezeptly/app.css` (in Schritt 1 nur funktional an die neue Kopfnavigation angepasst bzw. unverändert):

- Seiten: `pages/Home.vue`, `pages/IngredientSearch.vue`, `pages/MyIngredients.vue`, `pages/Error.vue`, `pages/collections/{Index,Create,Show}.vue`, `pages/recipes/{Create,Edit}.vue`, `pages/settings/{Index,Profile,Password}.vue`, `pages/auth/{Login,Register,ForgotPassword,ResetPassword,ConfirmPassword,VerifyEmail}.vue`, `pages/legal/{Impressum,Datenschutz}.vue`
- Komponenten: `recipes/RecipeForm.vue` (dort `StarRating` im Eingabemodus einsetzen), `recipes/ImportProgress.vue`, `recipes/IngredientSearchArea.vue`, `recipes/IngredientCard.vue`, `recipes/CollectionCard.vue`, `recipes/RecipeCollectionBoard.vue`, `widgets/{IngredientAlternativesModal,IngredientEditModal,TagEditModal}.vue` (auf `Dialog` umstellen)
- `recipes/RecipeList.vue` wird nirgends verwendet (Gate D: entfernen)
- Danach `rezeptly/app.css` und den Import in `app.css` entfernen.

Aus `rezeptly/app.css` entfernt wurden in Schritt 1 die Regeln der alten Shell (Header, Seitenleiste, Such-Untermenü, Drawer, User-Menü, Skip-Link, Footer), des alten Rezeptdetails und des Leerzustands der Liste; `--radius-lg` heißt dort jetzt `--legacy-radius-lg`, die Modal-Ebenen stehen in `app.css`. Farbe und Hintergrund von `<body>` kommen aus `app.css`.

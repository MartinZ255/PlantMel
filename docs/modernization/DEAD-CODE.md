# Toter Code – Kandidatenliste (Gate D3)

Stand: 2026-11-20, Basis `feat/gate-c-redesign` (c0f6628). Bezug: F-52, F-76 (`FINDINGS.md`).

Methode (lesend, im Worktree):

- `grep -rn -w <Name>` über `app bootstrap config database routes tests resources/js resources/views resources/css composer.json phpunit.xml package.json vite.config.ts eslint.config.js tsconfig.json playwright.config.ts .github` (generierte Wayfinder-Ordner `resources/js/{actions,routes,wayfinder}` ausgenommen, gitignoriert).
- Vue/TS-Module: Suche nach Import-Anweisungen `from '@/<pfad>'` bzw. relativ `from './<name>'`.
- Inertia-Seiten: alle `Inertia::render('…')`-Strings in `app/`, `routes/`, `bootstrap/` gegen `resources/js/pages/**` abgeglichen – **alle 23 Seiten werden gerendert**.
- Ziggy: alle `route('…')`-Aufrufe gegen alle `->name('…')` in `routes/` abgeglichen (dynamische Namen, z. B. `route(link.name)` in `AppFooter.vue`, manuell geprüft).
- Laufzeit-Registrierung: `bootstrap/app.php`, `bootstrap/providers.php`, `AppServiceProvider` (keine Container-Bindings, keine Event-Listener), `FortifyServiceProvider`, `routes/console.php` (kein Scheduler), Auto-Discovery von `app/Console/Commands`.
- npm: Paketname in Quelltext, CSS, Konfiguration (`vite.config.ts`, `eslint.config.js`, `.prettierrc`, `composer.json`-Skripte) und `npm ls`.

Bewertung: **sicher tot** = keine statische oder dynamische Verwendung, kein Daten-/Tabellenbezug, keine Laufzeit-Registrierung. **unklar** = ungenutzt im Code, aber Absicht, Tooling, Datenbezug oder Dateibesitz eines anderen Agenten spricht gegen eine Löschung in D3.

## Sicher tot – in D3 entfernt

| # | Kandidat | Belege | Maßnahme |
|---|---|---|---|
| T1 | `app/Http/Controllers/RatingDimensionController.php` | `grep -rnw RatingDimensionController` → nur die Datei selbst (Zeile 7). Keine Route in `routes/web.php`, `routes/api.php` (leer seit E6), `routes/settings.php`; keine Bindung in Providern; nicht in Tests. F-76 nennt ihn unbenutzt seit Entfernen der API-Routen; E14 nutzt `App\Services\TasteRating`. | gelöscht. Model `RatingDimension`, Tabelle `rating_dimensions`, Seeder und Factory **bleiben** (Daten, E14). |
| T2 | `resources/js/lib/utils.ts`: Funktionen `urlIsActive`, `toUrl` (und der dadurch unnötige Import `InertiaLinkProps`) | `grep -rnw "urlIsActive\|toUrl" resources/js tests` → nur Definition und gegenseitiger Aufruf in `lib/utils.ts` (Zeilen 9–18). `cn` bleibt (vielfach genutzt). | entfernt |
| T3 | `resources/js/types/index.d.ts`: `BreadcrumbItem`, `BreadcrumbItemType`, `NavItem`, `User` (und die dadurch unnötigen Importe `InertiaLinkProps`, `LucideIcon`) | `grep -rnw` → nur Definitionen in `types/index.d.ts` (Zeilen 34–64). Treffer für „User“ sonst nur in Kommentaren/Texten (`Datenschutz.vue` „User-Agent“, `Profile.vue`-Kommentar). Geteilter Nutzer heißt `SharedUser`. Starter-Kit-Reste (Breadcrumbs/Sidebar entfernt). | entfernt |
| T4 | npm `reka-ui` | kein Import in `resources/`, `tests/`, Konfiguration (`grep -rn "reka"` → nur `package.json`). Eigene UI-Bausteine in `components/ui/` nutzen es nicht. | `npm uninstall` |
| T5 | npm `@vueuse/core` | kein Import (`grep -rn "vueuse"` → nur `package.json`). War nur noch transitiv über `reka-ui` (dort eigene Version 14.4.0). | `npm uninstall` |
| T6 | npm `class-variance-authority` | kein Import (`grep -rn "class-variance\|cva"` → nur `package.json`). `Button.vue` nutzt `tailwind-merge`/`cn`. | `npm uninstall` (`clsx` bleibt, direkt in `lib/utils.ts`) |
| T7 | `components.json` (shadcn-vue-CLI-Konfiguration) | wird von keinem Build-/Lint-/Testwerkzeug gelesen; verweist auf nicht vorhandene `tailwind.config.js` (F-52). Der shadcn-vue-CLI ist nicht installiert, `reka-ui` (Basis von shadcn-vue) entfällt mit T4; die UI-Bausteine sind seit Gate C eigene Komponenten. | gelöscht |

## Sicher tot, aber Datei gehört einem anderen Agenten (nur gemeldet)

| # | Kandidat | Belege | Empfehlung |
|---|---|---|---|
| O1 | `resources/js/components/recipes/RecipeList.vue` | kein Import (`from '@/components/recipes/RecipeList'` → 0 Treffer, relativ ebenso); `DESIGN-SYSTEM.md:179`, `GATE-C-BERICHT.md:67`, `ROADMAP.md:137` nennen sie ungenutzt. | Rezeptliste-Komponente → Besitz D2. Nach Integration von D2 löschen, falls D2 sie nicht wiederverwendet. |
| O2 | `RecipeController::create()` (`app/Http/Controllers/RecipeController.php:315–336`) | Docblock bestätigt: keine Route; `recipes.create` ist eine Closure in `routes/web.php`. | Besitz D2. Entweder Methode löschen oder die Closure in die Methode verschieben (dann ist die Closure tot) – Entscheidung mit D2. |

## Unklar – nur aufgelistet, nicht gelöscht

| # | Kandidat | Belege | Warum unklar |
|---|---|---|---|
| U1 | `resources/js/components/ui/Card.vue` | kein Import (0 Treffer). In `DESIGN-SYSTEM.md:122` als Baustein dokumentiert. | Bewusster Design-System-Baustein für künftige Seiten. Löschen nur mit Anpassung von `DESIGN-SYSTEM.md` und Zustimmung. |
| U2 | `IngredientAlternativeController` + Routen `ingredientAlternatives.index/store/destroy` | Frontend ruft keine dieser Routen (`route('ingredientAlternatives.…')` 0 Treffer; Alternativen werden über das Rezeptformular gespeichert). Abgesichert durch `tests/Feature/Catalog/IngredientAlternativeEndpointsTest.php`, `RouteRoleMatrixTest.php`, `CatalogValidationGapsTest.php`, `tests/e2e/regression.spec.ts`. | Öffentliche HTTP-Schnittstelle (Host); Routing-Änderung braucht laut CLAUDE.md ADR. |
| U3 | Model `Comment`, Tabelle `comments`, `CommentFactory` | keine Route/UI; genutzt in `Recipe::comments()`, `User::comments()`, `RecipeDeleteTest`, `FactoriesTest`. | Tabelle mit möglichen Daten – nicht löschen (Vorgabe). |
| U4 | Model `RatingDimension`, `RecipeRating`, Tabelle `rating_dimensions`, `RatingDimensionSeeder` | aktiv genutzt durch `TasteRating` (E14). | Daten – nicht löschen (Vorgabe). |
| U5 | `app/Console/Commands/MakeHost.php` (`user:make-host`), `MarkUsersVerified.php` (`users:mark-verified`) | kein Code-Aufruf; Laravel registriert `app/Console/Commands` automatisch. | Betriebswerkzeuge (Artisan), kein toter Code. |
| U6 | `routes/console.php` Befehl `inspire` | Laravel-Standard, keine Verwendung. | harmlos; Entfernen ist Geschmackssache. |
| U7 | npm `tw-animate-css` | per `@import` in `resources/css/app.css:3` eingebunden; keine `animate-in`/`fade-*`/`slide-*`-Klassen in `resources/js` gefunden. | Entfernen ändert das CSS-Bundle (Keyframes/Variablen); erst mit Pixel-Baseline-Vergleich. |
| U8 | `resources/css/app.css:87` `@source` auf Laravel-Pagination-Blade-Views | Inertia-App, keine Blade-Pagination. | Wirkung auf generiertes CSS; mit U7 zusammen prüfen. |
| U9 | composer `laravel/sail` (dev) | kein `docker-compose.yml`/`Dockerfile` (`docs/architecture-inventory.md:152`). | Entwickler-Werkzeug; Entscheidung Auftraggeber. |
| U10 | composer `laravel/tinker`, `laravel/pail` | keine Code-Verwendung; `pail` im Skript `composer dev:ssr`. | Entwickler-Werkzeuge. |
| U11 | `config/services.php` Einträge `postmark`, `resend`, `ses`, `slack` | Laravel-Standard; nur über `MAIL_MAILER` bzw. Kanäle aktivierbar. | Konfigurationsoption, kein Code. |
| U12 | `useReveal.ts` Export `REDUCED_MOTION_QUERY` | nur intern genutzt. | harmlos, Export kostet nichts. |
| U13 | `@types/node` (dev) | kein direkter Treffer, aber `node:`-Importe in `tests/e2e/**`, `playwright.config.ts`, `vite.config.ts` brauchen die Typen. | in Benutzung (Typen), nicht tot. |
| U14 | `.prettierignore`: `resources/js/components/ui/*`, `resources/views/mail/*` | `ui/*` war die shadcn-Ausnahme; seit Gate C liegen dort eigene Design-System-Bausteine, die damit nicht Prettier-geprüft werden. `resources/views/mail/` existiert nicht. | Entfernen der `ui/*`-Zeile würde Formatierungsänderungen in `components/ui/` nach sich ziehen (keine Massenformatierung in D3). |
| U15 | `eslint.config.js` Ignore-Eintrag `tailwind.config.js` | Datei existiert nicht. | harmlos; bei nächster Änderung an `eslint.config.js` mit entfernen. |

## npm audit (Dev-Abhängigkeiten, Gate D3)

- Vorher: 9 moderate (GHSA-g3hv-75wg-m249, `minimatch` 7.0.0–11.0.0) über `typescript-eslint` und `vue-tsc`.
- `npm audit fix` (ohne `--force`): `typescript-eslint` und `@typescript-eslint/*` 8.72.2 → 8.73.0 (nur Lockfile), `minimatch` 10.2.6 → 11.0.9. ESLint, vue-tsc, Build grün.
- Rest: 3 moderate über `vue-tsc` 2.2.12 → `@vue/language-core` 2.2.12 → `minimatch` 9.0.9. Kein 2.x-Release mit behobenem `minimatch`; Behebung nur mit `vue-tsc` 3 (Major) – nicht übernommen. Nur Dev/Typecheck, nicht im Produktions-Bundle (`npm audit --omit=dev`: 0).

## Nicht geprüft

- Ungenutzte CSS-Klassen/Design-Tokens in `resources/css/app.css` (würde Pixel-Baselines betreffen).
- Ungenutzte Übersetzungsschlüssel in `lang/de.json`, `lang/de/*.php`.
- Ungenutzte Methoden/Relationen in Models (Models bleiben laut Vorgabe).

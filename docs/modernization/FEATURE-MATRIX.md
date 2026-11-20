# Feature-Matrix

Stand: 2026-11-13 · Commit `c7227e4` · Quelle: `routes/web.php`, `routes/api.php`, `routes/settings.php`, Controller und Vue-Seiten.

**Lesehinweise**

- Alles hier ist aus dem Quellcode abgeleitet. Die App lief nicht; **Mobile/Desktop sind Einschätzungen aus CSS und Markup, keine Browser-Ergebnisse.**
- Status: **ok** = laut Code funktionsfähig · **eingeschränkt** = funktioniert mit benannten Mängeln · **kaputt** = laut Code Fehler oder Absturz · **fehlt** = Funktion existiert nicht.
- Mobile/Desktop: **ok** / **Risiko** / **kaputt** (jeweils „laut Code“).
- Test: **ja** = von bestehendem Test berührt · **nein** · **rot** = Test vorhanden, scheitert voraussichtlich (nicht ausgeführt).
- Rollen: Gast · Nutzer (angemeldet; `verified` ist derzeit wirkungslos, F-08) · Host (`users.is_host`).
- Die App-Shell betrifft alle Seiten mit `AppLayout`: F-04 (unsichtbar klickbares Menü), F-05 (Such-Untermenü mobil verdeckt), F-29 (Backdrop). Das ist in den Spalten Mobile/Desktop nicht jedes Mal wiederholt.

## 1. Öffentliche Seiten

| Route / View | Rolle | Nutzerziel | Status | Mobile | Desktop | Test | Notizen |
|---|---|---|---|---|---|---|---|
| `GET /` · `landing` · `Landing.vue` | alle | Einstieg, anmelden | eingeschränkt | Risiko | ok | nein | kein Link zur Rezeptliste für Gäste; Logo/„Start“ führen zum Login (F-21); wirbt mit nicht vorhandenen Funktionen (F-55) |
| `GET /rezepte` · `recipes.index` · `recipes/Index.vue` | alle | Rezepte finden, filtern | eingeschränkt | Risiko | ok | nein | keine Pagination (F-23), kein Leerzustand, Suche ohne Debounce (F-44), `?search[]=` → 500 (F-22) |
| `GET /rezept/{recipe}` · `recipes.show` · `recipes/Show.vue` | alle | Rezept lesen | eingeschränkt | Risiko | Risiko | nein | Aktionen überlagern Titel, Layout ohne Bild (F-31); „Zur Sammlung“ für Gäste Sackgasse (F-39); alle Rezepte öffentlich, kein Entwurfsstatus |
| `GET /zutatensuche` · `IngredientSearch` · `IngredientSearch.vue` | alle | Zutaten durchsuchen | **kaputt** | kaputt | kaputt | nein | TypeError bei erster Eingabe (F-02); erfundene Kategorien und Zähler (F-44) |
| `GET /ingredients/{ingredient}` · `ingredients.show` | alle | Zutat ansehen | **kaputt** | – | – | nein | Controller-Methode fehlt → 500 (F-16); nirgends verlinkt |
| `GET /up` | alle | Health-Check | ok | – | – | nein | – |

## 2. Authentifizierung (Fortify)

Routennamen laut Fortify-Standard; nicht per `route:list` bestätigt.

| Route / View | Rolle | Nutzerziel | Status | Mobile | Desktop | Test | Notizen |
|---|---|---|---|---|---|---|---|
| `GET/POST /login` · `auth/Login.vue` | Gast | anmelden | eingeschränkt | Risiko | Risiko | ja (1 rot) | Redirect auf `/` statt `/home` (F-21); Statusmeldung neben dem Formular (F-35) |
| `POST /logout` | Nutzer | abmelden | ok | – | – | rot | Test erwartet `/home`; unbeabsichtigtes Abmelden möglich (F-04) |
| `GET/POST /register` · `auth/Register.vue` | Gast | Konto anlegen | eingeschränkt | ok | ok | ja (1 rot) | keine Verifizierungsmail (F-08), kein Throttle (F-60), kein `autocomplete` (F-41) |
| `GET/POST /forgot-password` · `auth/ForgotPassword.vue` | Gast | Reset-Link anfordern | ok | Risiko | ok | ja | iOS-Zoom, alte Marke im Titel (F-35) |
| `GET /reset-password/{token}` · `POST /reset-password` · `auth/ResetPassword.vue` | Gast | neues Passwort setzen | **kaputt** | kaputt | kaputt | ja (nur Backend) | Formular nutzt falschen Routennamen (F-01); Backend-Test deckt das nicht auf |
| `GET /email/verify` u. a. · `auth/VerifyEmail.vue` | Nutzer | E-Mail bestätigen | **wirkungslos** | Risiko | ok | ja (3 rot) | F-08; Seite ohne Abmelden-Link |
| `GET/POST /user/confirm-password` · `auth/ConfirmPassword.vue` | Nutzer | Passwort bestätigen | ok | Risiko | ok | teilweise | – |
| `GET/POST /two-factor-challenge` · `auth/TwoFactorChallenge.vue` | Gast | 2FA-Code eingeben | eingeschränkt | Risiko | ok | teilweise | keine Fehleranzeige (F-20) |
| `/user/two-factor-*` (8 Routen) | Nutzer | 2FA verwalten | **fehlt (UI)** | – | – | nein | Backend aktiv, keine Oberfläche (F-20) |

## 3. Angemeldete Nutzer

| Route / View | Rolle | Nutzerziel | Status | Mobile | Desktop | Test | Notizen |
|---|---|---|---|---|---|---|---|
| `GET /home` · `home` · `Home.vue` | Nutzer | Übersicht | eingeschränkt | Risiko | Risiko | rot | Test erwartet 200 für Gäste; Zähler irreführend (F-44); uneinheitliche Bildhöhen (F-37) |
| `GET /meineZutaten` · `myIngredients` · `MyIngredients.vue` | Nutzer | eigene Zutaten pflegen | **kaputt** | kaputt | kaputt | nein | Absturz beim Render, toter Menüpunkt, kein Backend (F-03) |
| `GET /sammlungen` · `collections.index` · `collections/Index.vue` | Nutzer | Sammlungen ansehen | eingeschränkt | ok | ok | nein | Karten nicht per Tastatur bedienbar (F-38), kein Leerzustand, N+1 (F-23) |
| `GET /sammlungen/erstellen` · `POST /collections` · `collections/Create.vue` | Nutzer | Sammlung anlegen | eingeschränkt | ok | ok | nein | keine Erfolgsmeldung (F-17) |
| `GET /sammlung/{collection}` · `collections/Show.vue` | Nutzer (Besitzer) | Sammlung ansehen | eingeschränkt | Risiko | Risiko | nein | Besitzprüfung vorhanden (`CollectionController.php:62`) |
| `POST /collections/{collection}/recipes` · `AddToCollectionModal.vue` | Nutzer (Besitzer) | Rezept hinzufügen | eingeschränkt | Risiko | ok | nein | Fehler verschluckt, keine Erfolgsmeldung (F-39, F-17); Modal unter Header (F-30) |
| `DELETE /collections/{collection}/recipes/{recipe}` | Nutzer (Besitzer) | Rezept entfernen | eingeschränkt | Risiko | Risiko | nein | ein Klick ohne Bestätigung (F-39) |
| `DELETE /collections/{collection}` | Nutzer (Besitzer) | Sammlung löschen | ok | ok | ok | nein | mit `confirm` |
| Rezept bewerten / kommentieren | Nutzer | Feedback geben | **fehlt** | – | – | – | keine Route, kein Controller, keine UI (F-55) |

## 4. Einstellungen

| Route / View | Rolle | Nutzerziel | Status | Mobile | Desktop | Test | Notizen |
|---|---|---|---|---|---|---|---|
| `GET /settings` · `settings/Index.vue` | Nutzer | Übersicht | ok | ok | ok | nein | verlinkt nur Profil und Passwort |
| `GET/PATCH /settings/profile` · `settings/Profile.vue` | Nutzer | Profil ändern | eingeschränkt | ok | ok | ja | keine Erfolgsmeldung (F-17); Verifizierungshinweis erscheint nie (F-08) |
| `DELETE /settings/profile` | Nutzer | Konto löschen | ok | ok | ok | rot | Test erwartet `/home`, Code leitet auf `/`; verlangt Passwort |
| `GET/PUT /settings/password` · `settings/Password.vue` | Nutzer | Passwort ändern | eingeschränkt | ok | ok | ja | keine Erfolgsmeldung (F-17) |
| `GET /settings/appearance` | Nutzer | Hell/Dunkel wählen | **kaputt** | kaputt | kaputt | nein | Vue-Seite fehlt (F-20); Dark Mode ohnehin unvollständig (F-34) |
| `GET /settings/two-factor` | Nutzer | 2FA einrichten | **kaputt** | kaputt | kaputt | 2 von 4 rot | Vue-Seite fehlt (F-20) |

## 5. Host-Funktionen

Alle Routen mit `auth` + `is_host` (`routes/web.php:103-197`). Keine Besitzgrenzen zwischen Hosts (F-45). Kein Test deckt die 403-Abgrenzung ab.

| Route / View | Rolle | Nutzerziel | Status | Mobile | Desktop | Test | Notizen |
|---|---|---|---|---|---|---|---|
| `GET /rezepte/erstellen` · `recipes.create` · `recipes/Create.vue` + `RecipeForm.vue` | Host | Rezept anlegen | eingeschränkt | Risiko | ok | nein | Fehler unsichtbar (F-06); `sort_order` (F-12); Zutatenzeile zu breit 481–720 px (F-32); Drag nur per Zeiger (F-36, F-38) |
| `POST /recipes` · `recipes.store` | Host | speichern | eingeschränkt | – | – | nein | Quelle wird nicht gespeichert (F-15); Validierungslücken (F-22) |
| `GET /rezept/{recipe}/bearbeiten` · `recipes.edit` · `recipes/Edit.vue` | Host | Rezept bearbeiten | eingeschränkt | Risiko | ok | nein | mehrzeilige Schritte zerfallen (F-14); kein Abbrechen, keine Bildvorschau (F-56) |
| `POST /rezept/{recipe}` · `recipes.update` | Host | Änderungen speichern | eingeschränkt | – | – | nein | Alternativen gehen verloren (F-13) |
| `DELETE /rezept/{recipe}` · `recipes.delete` | Host | Rezept löschen | eingeschränkt | – | – | nein | mit `confirm`; Bilddateien bleiben liegen (F-19) |
| `POST /rezept-import` · `GET /rezept-import/{id}` | Host | Rezept aus TikTok importieren | eingeschränkt | Risiko | ok | nein | braucht Worker, yt-dlp, Gemini-Key; hängt ohne Worker (F-24); kein Rate-Limit (F-26); Besitzprüfung beim Status vorhanden |
| `POST/PUT/DELETE /categories…` · `TagEditModal.vue` | Host | Kategorien pflegen | eingeschränkt | Risiko | Risiko | nein | Löschen global ohne Bestätigung (F-07); nur per Doppelklick erreichbar (F-38) |
| `POST/PUT/DELETE /ingredients…` · `IngredientEditModal.vue` | Host | Zutaten pflegen | eingeschränkt | Risiko | Risiko | nein | Löschen ohne Bestätigung (F-07); 500 bei Zutat als Alternative (F-18) |
| `GET/POST …/alternatives`, `DELETE /ingredient-alternatives/{id}` · `IngredientAlternativesModal.vue` | Host | Alternativzutaten pflegen | eingeschränkt | Risiko | ok | nein | uneinheitliche Speicherlogik (F-13); Modal unter Header (F-30) |

## 6. API (`routes/api.php`)

Vom Frontend nicht genutzt.

| Route | Rolle | Status | Test | Notizen |
|---|---|---|---|---|
| `GET /api/categories` | alle | **kaputt** | nein | Methode fehlt → 500 (F-16) |
| `GET /api/rating-dimensions` | alle | ok | nein | öffentlich, ungenutzt |
| `POST /api/recipes`, `PUT /api/recipes/{recipe}` | Host | **nicht erreichbar** | nein | `auth` ohne Session in der `api`-Gruppe |
| `DELETE /api/recipes/{recipe}` | Host | **kaputt** | nein | Methode `destroy` fehlt (F-16) |

## 7. Journeys im Überblick

| Journey | Rolle | Status laut Code |
|---|---|---|
| Landing → Rezeptliste → Filter → Detail | Gast | funktioniert, Einstieg versteckt (F-21) |
| Registrierung → Verifizierung → Login → Home | Gast | funktioniert ohne Verifizierung (F-08), landet auf Landing (F-21) |
| Passwort vergessen → neues Passwort | Gast | **bricht am Reset-Formular ab** (F-01) |
| Sammlung anlegen, Rezepte hinzufügen/entfernen | Nutzer | funktioniert per Maus, ohne Rückmeldung (F-17, F-38, F-39) |
| Eigene Zutaten pflegen | Nutzer | **nicht vorhanden / Absturz** (F-03) |
| Zutatensuche | alle | **Absturz bei erster Eingabe** (F-02) |
| Rezept bewerten / kommentieren | Nutzer | **nicht vorhanden** (F-55) |
| Rezept anlegen, bearbeiten, löschen | Host | funktioniert mit Datenverlust-Fallen (F-06, F-13, F-14, F-15, F-19) |
| TikTok-Import | Host | funktioniert laut Code bei laufendem Worker (F-24 bis F-27) |
| Profil, Passwort, Konto löschen | Nutzer | funktioniert ohne Rückmeldung (F-17) |
| 2FA einrichten, Darstellung wählen | Nutzer | **nicht möglich** (F-20) |

## 8. Routenabdeckung in Zahlen

| Gruppe | Routen | von Tests berührt |
|---|---|---|
| Fachrouten (`web.php` ohne Auth, `api.php`) | 35 | 0 (1 teilweise: `home`) |
| Settings | 8 | 6 |
| Fortify (laut Standard) | 24 | 14 |
| `/up` | 1 | 0 |
| **Gesamt** | **68** | **20** (+1 teilweise) |

Coverage-Prozente wurden nicht gemessen.

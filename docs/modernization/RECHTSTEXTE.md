# Impressum und Datenschutzerklärung – Vorlagen (Slice 4, F-10)

Stand: 2026-11-19. **Keine Rechtsberatung.** Beide Seiten sind Vorlagen: Die Impressum-Vorlage enthält
Platzhalter für die Angaben des Betreibers, die Datenschutzerklärung ist ein Entwurf, der nur die
Datenverarbeitungen beschreibt, die im Code belegt sind (Belege unten). Vor dem Release müssen alle
Werte gesetzt und alle `[PRÜFEN: …]`-Stellen bearbeitet werden. Empfohlen ist ein Abgleich mit einem
Datenschutz-Generator bekannter Anbieter oder eine rechtliche Prüfung.

| Seite | URL | Route | Vue-Datei |
|---|---|---|---|
| Impressum | `/impressum` | `legal.imprint` | `resources/js/pages/legal/Impressum.vue` |
| Datenschutzerklärung | `/datenschutz` | `legal.privacy` | `resources/js/pages/legal/Datenschutz.vue` |

Verlinkt im Footer jeder Seite mit App-Shell (`resources/js/components/layout/AppFooter.vue`), unter
allen Auth-Formularen und als Hinweis bei der Registrierung (ohne Checkbox – Information, keine Einwilligung).

## 1. Werte setzen

Alle Betreiberangaben stehen an einer Stelle: `config/legal.php`. Zwei Wege, gleichwertig:

- **direkt in `config/legal.php`:** den zweiten Parameter von `env(...)` (den Standardwert) ersetzen, z. B.
  `'operator_name' => env('LEGAL_OPERATOR_NAME', 'Erika Musterfrau'),`. Landet im Git-Repository –
  nur für Angaben, die ohnehin öffentlich im Impressum stehen.
- **per `.env` auf dem Server:** `LEGAL_OPERATOR_NAME="Erika Musterfrau"` usw. Werte mit Leerzeichen in
  Anführungszeichen. Bei gecachter Konfiguration danach `php artisan config:cache` (Teil des Deployments,
  nur mit Freigabe).

`.env.example` wurde in diesem Paket bewusst nicht geändert; die Schlüssel stehen hier.

| Config-Schlüssel | Env-Variable | Standard (Platzhalter) | Pflicht | Verwendet in |
|---|---|---|---|---|
| `operator_name` | `LEGAL_OPERATOR_NAME` | `[VOR- UND NACHNAME]` | ja | Impressum, Datenschutz 1 |
| `operator_street` | `LEGAL_OPERATOR_STREET` | `[STRASSE UND HAUSNUMMER]` | ja | Impressum, Datenschutz 1 |
| `operator_postal_city` | `LEGAL_OPERATOR_POSTAL_CITY` | `[PLZ UND ORT]` | ja | Impressum, Datenschutz 1 |
| `operator_country` | `LEGAL_OPERATOR_COUNTRY` | `Deutschland` | ja | Impressum, Datenschutz 1 |
| `operator_email` | `LEGAL_OPERATOR_EMAIL` | `[E-MAIL-ADRESSE]` | ja | Impressum, Datenschutz 1 (als `mailto:`-Link, sobald gesetzt) |
| `operator_phone` | `LEGAL_OPERATOR_PHONE` | leer | nein | wird nur angezeigt, wenn gesetzt |
| `content_responsible_name` | `LEGAL_CONTENT_RESPONSIBLE_NAME` | leer | nein | Impressum § 18 Abs. 2 MStV; leer = Anbieter wird genannt |
| `content_responsible_address` | `LEGAL_CONTENT_RESPONSIBLE_ADDRESS` | leer | nein | dito |
| `hosting_provider_name` | `LEGAL_HOSTING_NAME` | `[NAME DES HOSTING-ANBIETERS]` | ja | Datenschutz 2 |
| `hosting_provider_address` | `LEGAL_HOSTING_ADDRESS` | `[ANSCHRIFT DES HOSTING-ANBIETERS]` | ja | Datenschutz 2 |
| `server_log_retention` | `LEGAL_SERVER_LOG_RETENTION` | `[SPEICHERDAUER DER SERVER-LOGFILES, z. B. 14 Tage]` | ja | Datenschutz 2 |
| `mail_provider` | `LEGAL_MAIL_PROVIDER` | `[MAILANBIETER BZW. EIGENER MAILSERVER MIT ANSCHRIFT]` | ja | Datenschutz 7 |
| `last_updated` | `LEGAL_LAST_UPDATED` | `[TT.MM.JJJJ]` | ja | beide Seiten („Stand“) |

**Flag `filled`:** wird bei jedem Aufruf in `routes/web.php` berechnet. `false`, solange ein Pflichtwert
leer ist oder ein Wert (auch ein gesetzter optionaler) eine eckige Klammer `[` enthält. Dann zeigen beide
Seiten oben „Entwurf – Angaben des Betreibers fehlen noch“, Platzhalter sind gelb markiert. Achtung:
`filled` erkennt nur Platzhalter in den Config-Werten, **nicht** die `[PRÜFEN: …]`-Texte in den Vue-Dateien –
die müssen von Hand bearbeitet werden (Abschnitt 2).

Kontrolle nach dem Setzen: `/impressum` und `/datenschutz` aufrufen – kein Entwurf-Hinweis, keine gelbe Markierung.

## 2. Prüfstellen `[PRÜFEN: …]`

Jede Stelle ist im Text kursiv und braun markiert (Klasse `legal-check`). Nach der Klärung den Text
anpassen und die Markierung entfernen (Klasse und eckige Klammern).

| # | Datei / Abschnitt | Was zu klären ist | Warum |
|---|---|---|---|
| P1 | `Impressum.vue`, „Verantwortlich nach § 18 Abs. 2 MStV“ | Ob die Angabe nötig ist; sonst Abschnitt löschen | Pflicht nur bei journalistisch-redaktionellen Inhalten |
| P2 | `Datenschutz.vue`, 2 Hosting | Welche Felder der Webserver (Nginx) protokolliert, Speicherdauer, Vertrag zur Auftragsverarbeitung (Art. 28 DSGVO) mit dem Hoster | Serverkonfiguration ist unbekannt (kein Zugriff auf den VPS) |
| P3 | `Datenschutz.vue`, 7 E-Mail | Vertrag zur Auftragsverarbeitung mit dem Mailanbieter | Mailversand ist auf dem Server noch nicht eingerichtet (E2) |
| P4 | `Datenschutz.vue`, 7 E-Mail | Satz zur Bestätigungsmail übernehmen, **nur** wenn `REQUIRE_EMAIL_VERIFICATION=true` im Betrieb gesetzt ist; sonst löschen | Schalter aus dem Backend-Paket Slice 4, Standard aus |
| P5 | `Datenschutz.vue`, 8 Rezept-Import | Vertragspartner bei Google, Grundlage der Drittlandübermittlung (EU-US Data Privacy Framework/Standardvertragsklauseln), ob die genutzte Gemini-Stufe (kostenlos/bezahlt) eine Nutzung der Inhalte durch Google ausschließt, Speicherdauer bei Google | Bedingungen hängen vom Google-Konto ab, im Code nicht ablesbar (F-48) |
| P6 | `Datenschutz.vue`, 8 Rezept-Import | Rechtsgrundlage (Entwurf: lit. f) und Speicherdauer der Import-Einträge (`recipe_imports`) | Heute keine Löschfrist, Einträge verschwinden erst mit dem Konto (F-76) |

Außerdem allgemein prüfen: ob die Rechtsgrundlagen im Entwurf (lit. b für Konto/Sammlungen/Mail, lit. f für
Logfiles, Sitzungen, Login-Schutz und Import) passen, und ob eine konkrete Aufsichtsbehörde genannt werden soll.

## 3. Belege für die Aussagen der Datenschutz-Vorlage

| Abschnitt / Aussage | Beleg im Code |
|---|---|
| Keine Analyse-, Tracking-, Werbe-, Social-Dienste | Grep nach `analytics`, `gtag`, `googletagmanager`, `matomo`, `plausible`, `hotjar`, `facebook`, `pixel`, `sentry`, `recaptcha`, `youtube`, `gravatar`, CDN-Hosts in `resources/`, `app/`, `config/`, `routes/`, `package.json`, `composer.json`: keine Treffer. Einzige externe Hosts im Code: `generativelanguage.googleapis.com` (`app/Services/TikTokRecipeImporter.php:22`) und ein Platzhaltertext `https://www.tiktok.com/…` im Eingabefeld (`resources/js/pages/recipes/Create.vue`) |
| 1 Verantwortlicher | `config/legal.php` |
| 2 Server verarbeitet IP/Anfrage | technisch zwingend; Inhalt der Logfiles = P2. Laravel selbst protokolliert keine Zugriffe (nur Fehler, `config/logging.php`) |
| 3 nur notwendige Cookies, Name/Laufzeit | `config/session.php` (`cookie` aus `SESSION_COOKIE` bzw. `APP_NAME`-Slug + `-session`, `lifetime` 120 min, `http_only` true); `XSRF-TOKEN` mit gleicher Laufzeit (`vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/VerifyCsrfToken.php`, `newCookie`). Werte werden zur Laufzeit als Prop `session` übergeben (`routes/web.php`). Kein `document.cookie`/`localStorage`/`sessionStorage` in `resources/js` |
| 3 `remember_web_…`, bis 400 Tage | Checkbox „Angemeldet bleiben“ (`resources/js/pages/auth/Login.vue`); `SessionGuard::$rememberDuration = 576000` Minuten (`vendor/laravel/framework/src/Illuminate/Auth/SessionGuard.php:62`), keine Überschreibung in `app/`/`config/` |
| 4 Sitzungsdaten in der DB (nur bei `SESSION_DRIVER=database`) | Tabelle `sessions` mit `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity` (`database/migrations/0001_01_01_000000_create_users_table.php:32-37`); Standard-Treiber `database` (`config/session.php`); Text wechselt per Prop `session.database`; Aufräumen per `lottery` |
| 4 Login-Schutz 1 Minute nach E-Mail + IP | `app/Providers/FortifyServiceProvider.php:82-86` (`Limit::perMinute(5)->by(email|ip)`) |
| 5 Konto: Name, E-Mail, Passwort-Hash, Zeitstempel | `database/migrations/0001_01_01_000000_create_users_table.php:15-22`; `app/Models/User.php` (`$fillable`, `$hidden`) |
| 5 Nutzung ohne Konto möglich | öffentliche Routen `landing`, `recipes.index`, `recipes.show`, `IngredientSearch` (`routes/web.php`) |
| 5 Konto selbst löschen | `routes/settings.php:15` (`profile.destroy`), `app/Http/Controllers/Settings/ProfileController.php` (`destroy`), Oberfläche `resources/js/pages/settings/Profile.vue` („Konto löschen“) |
| 6 Sammlungen, nur selbst sichtbar, Löschung mit Konto | `database/migrations/2025_11_30_223402_create_collections_table.php` (`cascadeOnDelete`), Besitzprüfung `app/Http/Controllers/CollectionController.php` (`abort(403)`) |
| 7 Passwort-Reset, Token 60 min, gehasht | `config/fortify.php` (`Features::resetPasswords()`), `config/auth.php:97` (`expire` 60), Tabelle `password_reset_tokens` (Migration Zeile 26-28) |
| 7 Bestätigungsmail (P4) | `config/fortify.php` (`Features::emailVerification()`), Schalter `REQUIRE_EMAIL_VERIFICATION` aus dem Backend-Paket (nicht in diesem Worktree) |
| 8 Import nur für Hosts | `routes/web.php` Gruppe `['auth', 'is_host']` (`recipeImports.*`), `app/Http/Middleware/EnsureUserIsHost.php` |
| 8 Abruf bei TikTok per yt-dlp durch den Server | `TikTokRecipeImporter::fetchMetadata` und `downloadVideo` (`app/Services/TikTokRecipeImporter.php:108-181`) |
| 8 erst Beschreibung, nur bei Bedarf Video an Gemini | `TikTokRecipeImporter::import` (Zeilen 63-91), `analyzeCaption`, `uploadToGemini`, `analyzeVideo` |
| 8 keine Kontodaten an Google | Inhalte der Gemini-Requests: Prompt, Beschreibung, Video (`generate`, Zeilen 340-353); kein Nutzerbezug |
| 8 Löschung bei Google und lokal, auch im Fehlerfall | `finally`-Block mit `deleteGeminiFile` und `cleanupTempFiles` (Zeilen 92-99, 187-194, 280-299) |
| 8 gespeicherte Import-Daten | `database/migrations/2026_07_09_120000_create_recipe_imports_table.php` (`user_id` mit `cascadeOnDelete`, `url`, `status`, `source`, `error`, `result`); Fehlertexte zusätzlich im Server-Log ohne Schlüssel (`app/Jobs/ImportTikTokRecipe.php`, `logFailure`) |
| 9 Schrift selbst gehostet | `resources/css/app.css` (`@font-face` mit Dateien aus `@fontsource/instrument-sans`), kein Verweis auf `fonts.bunny.net` in `resources/views/app.blade.php`, `public/build` (Grep) |
| 10 keine automatisierte Entscheidung | keine solche Logik im Code; Gemini liefert nur einen Formularvorschlag, den der Host prüft und speichert (`routes/web.php`, `recipes.create` mit `importedRecipe`) |

**Bewusst weggelassen**, weil die App es nicht tut: Kommentare (Tabelle `comments` und Model existieren,
aber keine Route und keine Oberfläche schreibt Kommentare), Newsletter, Kontaktformular, Zahlungen,
Bewertungen durch Nutzer (Bewertungen pflegt nur der Host am Rezept), Profilbilder, Einbettungen.

## 4. Wann die Datenschutzerklärung angepasst werden muss

- ein neuer externer Dienst (CDN, Analyse, Fehler-Tracking, Captcha, Karten, Videos, Einbettungen),
- Einrichtung oder Wechsel des Mailanbieters (`mail_provider`, P3),
- Einschalten der E-Mail-Verifizierung (`REQUIRE_EMAIL_VERIFICATION=true`, P4),
- weitere Import-Quellen (z. B. Instagram, Rezept-Websites, E13) oder ein anderes KI-Modell bzw. ein anderer Anbieter,
- neue Funktionen mit Personenbezug (Kommentare, öffentliche Profile, geteilte Sammlungen, „Meine Zutaten“),
- Wechsel des Hosters oder geänderte Server-Logs, Änderung von `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_COOKIE`,
- eine Löschfrist für `recipe_imports` oder `failed_jobs` (F-76).

Danach `LEGAL_LAST_UPDATED` anpassen.

## 5. Technik

- Routen: `routes/web.php` (öffentlicher Teil oben, Closure `$legalProps`); `route:cache` wurde lokal erfolgreich ausgeführt und wieder gelöscht.
- Tests: `tests/Feature/Legal/LegalPagesTest.php`, `tests/Feature/Authorization/RouteRoleMatrixTest.php` (öffentliche Routen),
  `tests/Feature/Security/SecurityHeadersTest.php` (kein bunny.net in der CSP), `tests/e2e/regression-legal.spec.ts`.
- Schrift: `@fontsource/instrument-sans` 5.3.0 (exakt gepinnt, Lizenz OFL-1.1), nur Gewichte 400/500/600, Subset `latin`, nur woff2, `font-display: swap`.

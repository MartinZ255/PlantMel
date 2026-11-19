# Abschlussbericht Slice 4 – Konto, Recht und Import-Fortschritt

Stand: 2026-11-19 · Branch `fix/slice-4-konto-recht-import` (lokal, kein Push) · aufbauend auf `fix/slice-3-navigation-formulare` (`fb96176`)

## 1. Ergebnis

Slice 4 ist lokal umgesetzt und durch Tests belegt. Nichts wurde gepusht oder deployt; keine Migration; kein Zugriff auf den VPS.

| Messung | Vor Slice 4 | Nach Slice 4 | Exit |
|---|---|---|---|
| Reguläre Suite (`php artisan test`) | 509 bestanden | **562 bestanden** | 0 |
| Bekannte Defekte (`--testsuite=KnownDefects`) | 4 rot | **1 rot** (F-23) | 1 (beabsichtigt) |
| Browser (`npx playwright test`, mit Screenshot-Vergleich) | 212 | **244 bestanden**, zweimal | 0 / 0 |
| ESLint, `vue-tsc`, `composer audit`, `npm audit --omit=dev` | 0 / 0 / 0 / 0 | 0 / 0 / 0 / 0 | 0 |
| Prettier (`resources/`) | 5 Dateien | 5 Dateien | 1 (Altbestand) |

Beide abschließenden Browser-Läufe mit Screenshot-Vergleich: je 244 bestanden, Exit 0.

## 2. Entscheidungen des Auftraggebers und ihre Umsetzung

| Entscheidung | Umsetzung |
|---|---|
| E-Mail-Verifizierung ja, aber erst produktiv, wenn kostenloser Mailversand läuft (E2) | Schalter `REQUIRE_EMAIL_VERIFICATION`, Standard **aus**. Anleitung in `MAIL-SETUP.md` |
| Impressum und Datenschutz als Vorlagen, Auftraggeber füllt sie aus (E9) | Seiten `/impressum` und `/datenschutz` mit Platzhaltern; Checkliste in `RECHTSTEXTE.md` |
| Schrift selbst hosten (Empfehlung umgesetzt) | `@fontsource/instrument-sans` 5.3.0 (OFL-1.1), bunny.net entfernt |
| Fortschrittsanzeige für den Import | Stepper mit vier Schritten, Uhr, Warte-Hinweis, „Erneut versuchen“ |
| Weitere Import-Quellen langfristig (E13) | bewertet in Abschnitt 8, nicht gebaut |

## 3. Was neu ist

**E-Mail-Verifizierung (F-08)**
- **Schalter aus (Standard):** Die App verhält sich wie bisher. Es wird keine Mail verschickt, und niemand wird gesperrt.
- **Schalter an:**
  - Die Registrierung verschickt eine Bestätigungsmail.
  - Unverifizierte Konten werden auf die Bestätigungsseite geleitet, auch bei Host-Funktionen.
  - Eine geänderte E-Mail-Adresse muss neu bestätigt werden.
  - Die Einstellungen bleiben erreichbar, damit man eine vertippte Adresse korrigieren kann.
- **Bestehende Konten:** `php artisan users:mark-verified` (mit `--dry-run` zur Vorschau) markiert sie vor dem Einschalten als bestätigt.
- **Hintergrundanfragen:** Sie erhalten bei fehlender Bestätigung die deutsche Meldung „Bitte bestätige zuerst deine E-Mail-Adresse.“.

**Impressum und Datenschutz (F-10)**
- **Betreiberangaben:** Alle stehen an einer Stelle (`config/legal.php` oder per `LEGAL_*` in der `.env`).
- **Entwurfshinweis:** Solange ein Platzhalter `[…]` übrig ist, zeigen beide Seiten oben „Entwurf – Angaben des Betreibers fehlen noch“.
- **Datenschutz-Vorlage:**
  - Sie beschreibt nur Verarbeitungen, die im Code belegt sind (Belegtabelle in `RECHTSTEXTE.md`).
  - Behandelt sind Hosting, Cookies, Sitzungen, Konto, Sammlungen, E-Mail-Versand, TikTok-Import mit Übermittlung an Google, die Schrift und die Betroffenenrechte.
  - Sechs Stellen sind mit `[PRÜFEN: …]` markiert; diese erkennt der Entwurfshinweis nicht automatisch.
- **Verlinkung:** Der Footer auf allen Seiten und die Login-/Registrierungsseiten verlinken beide Seiten. Bei der Registrierung steht ein Hinweis auf die Datenschutzerklärung (keine Checkbox).

**Schrift (F-58)**
- Instrument Sans wird mit der App ausgeliefert, nur die Gewichte 400, 500 und 600 und das Subset `latin` (inklusive Umlaute).
- **Drittanbieter:** Es geht keine Anfrage mehr an fonts.bunny.net; die Report-Only-CSP erlaubt nur noch `'self'`.

**Import-Fortschritt (F-24)**
- **Schritte:** Gestartet → Video wird geladen → Rezept wird erkannt → Fertig, mit Haken, Ladeanimation, Laufbalken und vergangener Zeit.
- **Warte-Hinweis:** Bleibt der Import über 30 Sekunden in der Warteschlange, erscheint der Hinweis auf den Hintergrunddienst.
- **Video-Weg:** Reicht die Beschreibung nicht, wird das Video geladen und der Status springt zurück. Alle Haken bleiben stehen; nur der Hinweistext wechselt.
- **Fehler:** Der Schritt wird rot markiert, dazu gibt es „Erneut versuchen“. Limit- und Parallel-Meldungen des Servers werden angezeigt.
- **Barrierefreiheit:** Statuswechsel werden angesagt, die Uhr nicht. Mit reduzierter Bewegung gibt es keine Animation.
- **Server:** liefert zusätzlich `started`, `created_at` und `updated_at`.
- **Lokal ausprobieren:** `.\scripts\demo.ps1 -WithWorker` startet den Hintergrunddienst. Der Import läuft dann echt, mit TikTok und Google Gemini; dafür muss `GEMINI_API_KEY` gesetzt sein.

**Nebenbei behoben**
- Das Zutaten-Autocomplete im Rezeptformular schließt jetzt beim Verlassen des Feldes und mit Escape (Teil von F-36). Vorher verdeckte die offene Liste die Buttons der Zeile; sichtbar wurde das erst mit der echten Schrift.

## 4. Was Sie wissen sollten

1. **Vor dem Einschalten der E-Mail-Verifizierung** muss der Mailversand auf dem Server funktionieren. Bestätigungsmails werden direkt beim Registrieren verschickt; ist der Versand kaputt, scheitert die Registrierung.
2. **Ihre DNS-Einträge** nennen `mail.plantmel.de` (VPS-IP) als Mailserver. Ob dort einer läuft, prüfe ich in der VPS-Phase. Für zuverlässige Zustellung braucht es zusätzlich SPF, DKIM, DMARC und einen passenden Reverse-DNS-Eintrag (`MAIL-SETUP.md`).
3. **Rechtstexte:**
   - Name, Anschrift, E-Mail, Hoster und Mailanbieter tragen Sie ein.
   - Die sechs `[PRÜFEN]`-Stellen bearbeiten Sie in den Vue-Dateien von Hand.
   - Vor dem Release sollten Sie die Texte mit einem Datenschutz-Generator abgleichen oder prüfen lassen. Das ist keine Rechtsberatung.
4. **Löschfrist für Importe fehlt:** Gespeicherte Import-Daten (`recipe_imports`) haben noch keine. Die Datenschutz-Vorlage fragt danach (P6).
5. **Eigener Fehler beim Zusammenführen:**
   - Nach dem Merge hatte ich im Hauptverzeichnis das neue Schrift-Paket nicht installiert, bevor ich gebaut habe. Die Schrift-Pfade blieben dadurch ungebündelt und lieferten 404; ein Browser-Test hat das gemeldet.
   - Nach `npm ci` und neuem Build sind die Dateien korrekt gebündelt.
   - **Für den Server heißt das:** nach jedem Update erst `npm ci`, dann `npm run build`.
6. **Modelle:**
   - Lead: `claude-opus-5-5`, nach der Umstellung durch den Auftraggeber.
   - Spezialisten: drei Agenten mit dem Alias `opus`; die zwei unterbrochenen liefen laut API-Meldung mit `claude-opus-5-5`.
   - Ablauf: Zwei Agenten wurden vom Wochenlimit gestoppt und nach dessen Ablauf an derselben Stelle fortgesetzt.

## 5. Änderungen

- **Anwendung:** 27 Dateien in `app`, `routes`, `config`, `bootstrap`, `resources`, `lang` (+1745 / −72 gegenüber `fb96176`).
- **Neu:**
  - `config/plantmel.php`, `config/legal.php`
  - `app/Console/Commands/MarkUsersVerified.php`
  - `resources/js/pages/legal/{Impressum,Datenschutz}.vue`
  - `resources/js/components/layout/AppFooter.vue`
  - `resources/js/components/recipes/ImportProgress.vue`
  - Dokumente `MAIL-SETUP.md`, `RECHTSTEXTE.md`
- **Abhängigkeit:** `@fontsource/instrument-sans` 5.3.0 (Schriftdateien, keine Laufzeit-Logik).
- **Screenshots:** alle 110 Baselines erneuert (Schrift und Footer), dazu 10 neue (Impressum, Datenschutz). Insgesamt 120 Aufnahmen, ca. 30 MB.
- **Startskript:** Option `-WithWorker`.

## 6. Offen nach Slice 4

- **Rot in `KnownDefects` (1):** F-23 – eine zusätzliche Datenbankabfrage pro Sammlung in der Übersicht.
- **Erwarteter Defekt im Browser (1):** F-38 – Umsortieren nur per Maus bzw. Finger.
- **Vor dem Release:**
  - Rechtstexte ausfüllen
  - Mailversand einrichten (optional)
  - VPS-Prüfung: PHP 8.4, ausgecheckter Stand, Schema (F-11), `APP_LOCALE=de`, Header hinter Nginx
  - erster CI-Lauf auf GitHub
- **Gate C (konsistentes Redesign):** Design-Tokens, gemeinsame Bausteine, Tastatur-Alternative fürs Umsortieren, Kontrast, Fokus im Drawer.
- **Gate D:** Pint/Prettier-Altbestand, toter Code, Performance (F-23), Löschfrist für Importe.
- **Feature-Ideen:** „Meine Zutaten“ ausbauen (E12), weitere Import-Quellen (E13).

## 7. Wichtigste Befehle (Lead)

| Befehl | Exit |
|---|---|
| `git switch -c fix/slice-4-konto-recht-import` | 0 |
| Merges der drei Agenten-Branches | 0, ohne Konflikt |
| `php artisan test` nach jedem Merge, final | 0 (562) |
| `php artisan test --testsuite=KnownDefects` | 1 (nur F-23, beabsichtigt) |
| `npm run build` ohne vorheriges `npm ci` | 0, aber Schrift ungebündelt (eigener Fehler, s. o.) |
| `npm ci --ignore-scripts`, `npm run build` | 0, drei woff2-Dateien gebündelt |
| `E2E_PORT=8133 npx playwright test --ignore-snapshots` | 0 (244) |
| `npx playwright test --update-snapshots` (5 Viewport-Projekte) | 0 (124) |
| `E2E_PORT=8133 npx playwright test` (mit Screenshot-Vergleich, zweimal) | 0 / 0 (je 244) |
| `npx eslint .`, `npx vue-tsc --noEmit` | 0 / 0 |
| `composer audit`, `npm audit --omit=dev` | 0 / 0 |

## 8. Bewertung: weitere Import-Quellen (E13)

| Quelle | Machbarkeit | Ansatz | Risiken |
|---|---|---|---|
| **Rezept-Websites** | gut | Viele Rezeptseiten enthalten die Daten maschinenlesbar (schema.org `Recipe` als JSON-LD). Diese lassen sich direkt auslesen, ohne KI; nur Seiten ohne diese Daten bräuchten Gemini für den Seitentext. | Seiten blockieren teils automatisierte Abrufe. Server-seitiger Abruf beliebiger URLs braucht Schutz gegen das Abrufen interner Adressen (SSRF). Urheberrecht an Rezepttexten beachten. |
| **Instagram** | eingeschränkt | yt-dlp unterstützt Instagram grundsätzlich; danach gleicher Weg wie bei TikTok. | Öffentliche Reels oft nur mit Login-Cookies abrufbar; bricht bei Änderungen durch Instagram; Nutzungsbedingungen. |
| **Weitere Videoplattformen** | je nach Plattform | gleicher Weg wie TikTok, sofern yt-dlp sie unterstützt | wie Instagram |

**Empfehlung:** Rezept-Websites zuerst. Vorher den Import auf eine gemeinsame Schnittstelle umbauen, die anhand des Links die passende Quelle wählt; dazu Tests mit gespeicherten Beispielseiten. Die Datenschutz-Vorlage muss dann ergänzt werden. Umfang: ein eigenes Feature-Paket nach Gate C.

## 9. Rollback

Branch nicht mergen bzw. verwerfen. Der Schalter der E-Mail-Verifizierung steht standardmäßig aus; die Verifizierung lässt sich auf dem Server jederzeit per `.env` wieder abschalten. `users:mark-verified` ändert Daten – vor einer Ausführung auf dem Server Backup.

## 10. Empfehlung

**Gate C – konsistentes Redesign** auf Basis der jetzigen Screenshot-Baseline. Parallel können Sie die Rechtstexte ausfüllen. Sobald lokal alles abgeschlossen ist, folgt die VPS-Phase mit lesender Prüfung und danach das Release – jeweils mit Ihrer ausdrücklichen Freigabe.

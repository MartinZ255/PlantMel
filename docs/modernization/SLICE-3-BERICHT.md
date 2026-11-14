# Abschlussbericht Slice 3 – Navigation, mobile Darstellung, Formulare

Stand: 2026-11-14 · Branch `fix/slice-3-navigation-formulare` (lokal, kein Push) · aufbauend auf `fix/slice-2-datenverlust` (`57884e2`)

## 1. Ergebnis

Slice 3 ist lokal umgesetzt und durch Tests belegt. Nichts wurde gepusht oder deployt; keine Migration.

| Messung | Vor Slice 3 | Nach Slice 3 | Exit |
|---|---|---|---|
| Reguläre Suite (`php artisan test`) | 465 bestanden | **509 bestanden** | 0 |
| Bekannte Defekte (`--testsuite=KnownDefects`) | 7 rot | **4 rot** (F-08 ×3, F-23) | 1 (beabsichtigt) |
| Browser (`npx playwright test`, mit Screenshot-Vergleich) | 164, davon 15 erwartete Defekte | **212, davon 1 erwarteter Defekt** (F-38) | 0, zweimal |
| ESLint (`npx eslint .`) | 4 Fehler (Stand Slice 0) | **0** | 0 |
| Typprüfung (`npx vue-tsc --noEmit`) | 6 Fehler (Stand Slice 0) | **0** | 0 |
| Prettier (`resources/`) | 36 Dateien (Stand Slice 0) | 5 Dateien | 1 |
| Pint | 27 Verstöße (Stand Slice 0) | 40 Dateien | 1 |

Der Anstieg bei Pint stammt vermutlich überwiegend aus Regeln, die mit der neueren Pint-Version aus dem Paket-Update (Slice 1) hinzugekommen sind (z. B. `fully_qualified_strict_types`, `ordered_imports`); betroffen sind überwiegend unveränderte Altdateien. Nicht im Einzelnen geprüft.

Protokolle: `baseline/phpunit-08-slice3-default.txt`, `phpunit-09-slice3-known-defects.txt`.

## 2. Entscheidungen des Auftraggebers und ihre Umsetzung

| Entscheidung | Umsetzung |
|---|---|
| E1: nach Login und Registrierung auf `/home` | umgesetzt; Logout und Konto-Löschung enden auf der Startseite `/` |
| E4: keine Zwei-Faktor-Authentifizierung | vollständig entfernt (Feature, Routen, Seiten, Trait). DB-Spalten bleiben, keine Migration |
| E4: Hell/Dunkel abgeschaltet | Darstellungs-Seite und Umschaltlogik entfernt, die App rendert immer hell |
| E8: Landing-Text | bewirbt nur noch Rezeptsuche, Sammlungen und Zutatensuche |
| VPS erst, wenn lokal fertig | kein Zugriff erfolgt |

## 3. Was behoben wurde

**Navigation und mobile Darstellung**
- **Mobile Suche (F-05):** Rezeptsuche und Zutatensuche klappen im Drawer auf und sind erreichbar.
- **Drawer (F-29, F-69):** Der Hintergrund wird abgedunkelt. Tippen daneben, Escape oder eine Navigation schließen den Drawer. Der Versatz zum Header ist weg, der Hintergrund scrollt bei offenem Drawer nicht.
- **Für Gäste (F-21):** Logo und „Start“ führen auf die Startseite. Die Startseite hat „Rezepte entdecken“ als Hauptbutton. Einträge, die eine Anmeldung brauchen, sind gekennzeichnet. Der aktive Menüpunkt ist markiert.
- **Modals (F-30):** Alle vier Fenster liegen über Header und Sidebar, sperren das Scrollen, schließen mit Escape und geben den Fokus zurück. Enter im Fenster sendet das Rezeptformular nicht mehr ab.
- **Rezeptdetail (F-31):** Titel und Aktionen überlappen sich bei keiner Breite mehr. Ohne Bild wird die volle Breite genutzt. „Notizen & Tipps“ erscheint nur, wenn es Notizen gibt.
- **Lange Wörter (F-32):** Sie brechen um, statt abgeschnitten zu werden.

**Formulare und Rückmeldungen**
- **Rezeptformular (F-06, F-74):** Jeder Fehler steht am zugehörigen Feld. Oben erscheint eine Zusammenfassung mit Sprunglinks, die nach dem Absenden den Fokus erhält. Komplett leere Zeilen werden nicht mehr gesendet, sodass die leere Startzeile kein stilles Scheitern mehr auslöst. Pflichtfelder sind markiert.
- **Erfolgsmeldungen (F-17):** Sie erscheinen nach Speichern, Löschen und Hinzufügen sowie nach Profil- und Passwortänderung.
- **Bearbeiten (F-56):** Es gibt „Abbrechen“; „Zurücksetzen“ fragt vorher nach.
- **Zur Sammlung hinzufügen (F-39):** Gäste sehen einen Anmelde-Hinweis, Nutzer ohne Sammlung einen Link zum Anlegen. Der Button ist ohne Auswahl deaktiviert, und Fehler werden angezeigt.
- **TikTok-Import (F-24):**
  - Die Abfrage bricht nach 16 Minuten ab; es gibt einen Abbrechen-Button.
  - Ein einzelner Netzwerkfehler beendet den Import nicht mehr.
  - Die Server-Meldungen zu Limit, laufendem Import und ungültigem Link erscheinen.
  - Bevor ein ausgefülltes Formular überschrieben wird, kommt eine Rückfrage.
- **Rezeptliste (F-44):** Bei 0 Treffern erscheint ein Leerzustand mit „Filter zurücksetzen“. Die Suche ist entprellt, die Trefferzahl wird angesagt.

**Sprache, Seitenrahmen, Fehlerseiten**
- **Sprache und Seitenrahmen (F-42):**
  - `lang="de"`, deutsche Validierungs- und Login-Texte.
  - Jede Seite hat einen eigenen Titel „… – PlantMel“ und genau eine Hauptüberschrift.
  - Skip-Link „Zum Inhalt springen“, benannte Navigationen.
- **Fehlerseiten (F-68):** Eigene deutsche Fehlerseite für 403, 404, 429, 500 und 503. Eine abgelaufene Sitzung führt zurück zum Formular mit Hinweis.
- **Auth-Seiten (F-35):** Alle haben einen Weg zurück zur Startseite. Statusmeldungen stehen über dem Formular, Eingaben sind mobil 16 px groß (kein iOS-Zoom), die Registrierung hat `autocomplete`.

## 4. Was Sie wissen sollten

1. **Sprache hängt an der `.env`:** `APP_LOCALE` in der `.env` hat Vorrang. Ihre lokale `.env` steht offenbar auf `en`. Ich habe die Sprache deshalb für Tests und `scripts/demo.ps1` fest auf Deutsch gesetzt. Wer die App mit der eigenen `.env` startet, sieht englische Meldungen, bis dort `APP_LOCALE=de` steht. Dasselbe gilt später für den VPS.
2. **Fehlerseite bei eingeschaltetem Debug-Modus:** Mit `APP_ENV=local` und `APP_DEBUG=true` erscheint statt der deutschen Fehlerseite die Debug-Seite von Laravel. Das ist absichtlich so, damit Fehler beim Entwickeln sichtbar bleiben.
3. **Unbekannte URL für Angemeldete:** Auf einer Seite, die es nicht gibt, sehen angemeldete Nutzer die Gast-Navigation, weil für unbekannte URLs keine Sitzung geladen wird. Abhilfe wäre eine Fallback-Route.
4. **Bildvorschau beim Bearbeiten:** Das Formular zeigt nur ein neu gewähltes Bild, nicht das bereits gespeicherte. Die Bearbeiten-Seite liefert die Bild-URL nicht mit. Im Frontend ist die Anzeige vorbereitet; im Backend fehlt eine Zeile.
5. **Neues Verhalten bei offenen Fenstern:** Solange ein Modal offen ist, ist der Rest der Seite gesperrt. Das ist gewollt.
6. **Restliche Gast-Punkte:** Im Dialog „Zur Sammlung hinzufügen“ sieht man nicht, in welchen Sammlungen das Rezept schon liegt; die Daten dafür fehlen.
7. **Login-Sperre:** Nach mehr als fünf Fehlversuchen pro Minute erscheint die Fehlerseite 429 statt einer Meldung im Formular.
8. **Native Dateiauswahl:** Der Text „Choose File / No file chosen“ kommt vom Browser und folgt dessen Spracheinstellung, nicht der App.

## 5. Arbeitsweise

- **Modelle:** Lead bis zum Merge des Backend-Teils `claude-fable-5-2`, danach `claude-opus-5-5` – der Auftraggeber hat den Lead per `/model` umgestellt. `CLAUDE.md` nennt für den Lead weiterhin Fable 5.2. Alle drei Spezialisten wurden mit dem Alias `opus` gestartet; die zwei Frontend-Agenten liefen laut API-Meldung mit `claude-opus-5-5`.
- **Ablauf:** Backend zuerst. Danach zwei Frontend-Pakete parallel mit vereinbarter Aufteilung (Seitentitel, Überschriften und Flash zentral in der App-Shell), getrennten CSS-Bereichen und ohne Screenshot-Änderungen.
- **Unterbrechungen:** Beide Frontend-Agenten wurden vom Nutzungslimit gestoppt und nach dessen Ablauf an derselben Stelle fortgesetzt.
- **Zusammenführung durch den Lead:**
  - Beide Branches ohne Konflikt gemergt.
  - Die Browser-Tests zu den behobenen Befunden auf reguläre Tests umgestellt. Den F-30-Test, der nur die Geometrie prüfte, ersetzt durch eine Prüfung des tatsächlich obersten Elements.
  - Die Screenshot-Baseline erneuert und die wichtigsten Aufnahmen angesehen: langer Titel bei 320 px, Rezeptkopf bei 1024 px, Formular bei 390 px, Sammlung bei 1440 px, Startseite bei 390 px.
  - Dabei fehlende Aufzählungspunkte auf der Startseite gefunden und behoben.
- **Weitere Lead-Änderungen:**
  - Die Sprache für Tests fixiert, nachdem 13 Tests an der lokalen `.env` gescheitert waren.
  - ESLint ignoriert die lokalen Agenten-Worktrees. Vorher meldete `npx eslint .` lokal über 42.000 Fehler aus diesen Kopien; die Projektdateien selbst haben 0 Fehler.
- **Kein zusätzlicher Review-Agent.** Geprüft: beide PHP-Suiten, zwei vollständige Browser-Läufe mit Screenshot-Vergleich, ESLint, Typprüfung.

## 6. Änderungen

- **Anwendung:** 53 Dateien in `app`, `routes`, `config`, `bootstrap`, `resources` und `lang` (+3597 / −1455 gegenüber `57884e2`). Ein Teil ist reine Formatierung durch Prettier.
- **Neu:**
  - `lang/de/*`, `lang/de.json`
  - `resources/js/pages/Error.vue`
  - `resources/js/composables/useModal.ts`
  - `scripts/demo.ps1`
  - `tests/e2e/regression-shell.spec.ts`, `tests/e2e/regression-forms.spec.ts`
- **Entfernt:** 2FA-Controller, -Request und -Seite, `useTwoFactorAuth.ts`, `useAppearance.ts`, `HandleAppearance`.
- **CI (`lint.yml`):** ESLint, Typprüfung, `composer audit` und `npm audit` sind wieder blockierend, weil jeweils 0 Befunde. Pint und Prettier bleiben dokumentierte Ausnahmen.
- **Screenshots:** 69 Baselines erneuert, 5 neu (deutsche 404-Seite), 5 veraltete der entfernten 2FA-Seite gelöscht. Insgesamt 110 Aufnahmen, ca. 23 MB.

## 7. Wichtigste Befehle (Lead)

| Befehl | Exit |
|---|---|
| `git switch -c fix/slice-3-navigation-formulare` | 0 |
| `.\scripts\demo.ps1 -Fresh -Port 8019` (Probelauf, danach beendet) | Server lief, Login-Seite 200 |
| Merge Backend-Branch, `php artisan test` | 1 (13 rot wegen lokaler `.env`-Sprache) → nach Fixierung 0 (509) |
| Merge Shell-Branch, Merge Formular-Branch | 0, ohne Konflikt |
| `E2E_PORT=8133 npx playwright test --ignore-snapshots` | 0 (212) |
| `npx playwright test --update-snapshots` (5 Viewport-Projekte) | 0 (114) |
| `E2E_PORT=8133 npx playwright test` (mit Screenshot-Vergleich, zweimal) | 0 / 0 (je 212) |
| `php artisan test` / `--testsuite=KnownDefects` (final) | 0 (509) / 1 (4 rot, beabsichtigt) |
| `npx eslint .`, `npx vue-tsc --noEmit` | 0 / 0 |
| `npx prettier --check resources/`, `vendor/bin/pint --test` | 1 / 1 (Altbestand, CI-Ausnahme) |

## 8. Offen nach Slice 3

**Rot in `KnownDefects` (4):** F-08 E-Mail-Verifizierung (3), F-23 N+1 in der Sammlungsübersicht.

**Erwarteter Defekt im Browser (1):** F-38 – Umsortieren von Schritten und Zutaten geht nur per Maus bzw. Finger, nicht per Tastatur.

**Weiter offen:**
- **Slice 4 – Konto und Recht:** E-Mail-Verifizierung (F-08), Impressum und Datenschutz (F-10), Datenschutzhinweis zum Import (F-48), Passwortregeln (F-60).
- **„Meine Zutaten“** als echtes Feature (E12: gewünschter Umfang offen).
- **Gate C (Redesign):** Design-Tokens, gemeinsame Bausteine, Tastatur-Alternative fürs Umsortieren, Kontrast nach Messung, Fokus im Drawer.
- **Gate D:** Pint/Prettier-Altbestand, toter Code, Performance.
- **Vor dem Release:** VPS-Prüfung, erster CI-Lauf auf GitHub, Lauf gegen MySQL/Postgres.

## 9. Rollback

Branch nicht mergen bzw. verwerfen; die Vorgänger-Branches sind unverändert. 2FA ließe sich durch Rücknahme des Branches wiederherstellen; die DB-Spalten sind noch vorhanden.

## 10. Empfehlung

**Slice 4 – Konto und Recht.** Dafür nötig:
- **E2:** E-Mail-Verifizierung durchsetzen (Empfehlung) oder abschalten. Vor dem Durchsetzen muss feststehen, wie viele Konten auf dem VPS unverifiziert sind, sonst werden Nutzer ausgesperrt. Das geht nur mit einem Blick auf die Produktionsdaten – Alternative: alle bestehenden Konten beim Umstellen als verifiziert markieren.
- **E9:** Inhalte für Impressum und Datenschutzerklärung. Diese müssen vom Betreiber kommen bzw. rechtlich geprüft werden; technisch baue ich die Seiten, Verlinkungen und Platzhalter.

# Anmeldung mit Google – Einrichtung

Stand: 2026-11-21 · Entscheidung E17 / ADR-004 (`DECISIONS.md`)

> **Jeder Schritt auf dem Server (`.env` ändern, Migration ausführen, Cache neu bauen) braucht eine gesonderte, ausdrückliche Freigabe für genau diese Aktion.** Diese Anleitung ist keine Freigabe.

## Was die Funktion macht

- Auf `/login` erscheint „Mit Google anmelden“, auf `/register` „Mit Google registrieren“. Beide führen über `GET /auth/google/redirect` zu Google und zurück auf `GET /auth/google/callback`.
- Angefragt werden nur `openid`, `email` und `profile` (Name, E-Mail-Adresse, Google-Kennung). Kein Zugriff auf Kalender, Kontakte o. Ä.
- Akzeptiert wird nur eine E-Mail-Adresse, die Google als bestätigt meldet.
- Zuordnung: gleiche Google-Kennung → dieses Konto; sonst gleiche E-Mail-Adresse (ohne Groß-/Kleinschreibung) → Konto wird automatisch mit Google verknüpft; sonst neues Konto ohne Passwort und ohne Host-Recht.
- Konten ohne Passwort können über „Passwort vergessen“ oder Einstellungen → Passwort ein Passwort festlegen. Das Löschen des Kontos bestätigen sie mit ihrer E-Mail-Adresse.
- Ändert jemand im Profil seine E-Mail-Adresse, bleibt die Verknüpfung bestehen. Die Anmeldung mit Google funktioniert weiter, weil die Google-Kennung zählt und nicht die E-Mail-Adresse.
- Ohne `GOOGLE_CLIENT_ID` **und** `GOOGLE_CLIENT_SECRET` ist der Knopf ausgeblendet. Ein direkter Aufruf der Routen führt dann mit einem Hinweis zurück auf `/login`.

## 1. Google Cloud Console

1. <https://console.cloud.google.com/> → Projekt anlegen oder auswählen (z. B. „PlantMel“).
2. **APIs & Dienste → OAuth-Zustimmungsbildschirm** (bzw. „Google Auth Platform → Branding“):
   - Nutzertyp **Extern**.
   - App-Name „PlantMel“, Support-E-Mail, Logo optional.
   - **Autorisierte Domains:** die Domain der Anwendung (z. B. `plantmel.de`).
   - Links auf **Datenschutzerklärung** (`https://<domain>/datenschutz`) und optional Nutzungsbedingungen bzw. Impressum.
   - **Bereiche (Scopes):** nur `openid`, `.../auth/userinfo.email`, `.../auth/userinfo.profile`. Das sind nicht sensible Bereiche, dafür ist keine Prüfung durch Google nötig.
   - Veröffentlichungsstatus: Im Modus „Testen“ können sich nur eingetragene Testnutzer anmelden. Für alle Nutzer auf „In Produktion“ umstellen.
3. **APIs & Dienste → Anmeldedaten → Anmeldedaten erstellen → OAuth-Client-ID**:
   - Anwendungstyp **Webanwendung**.
   - **Autorisierte JavaScript-Quellen:** nicht nötig, PlantMel nutzt reine Server-Weiterleitungen.
   - **Autorisierte Weiterleitungs-URIs** (genau so, ohne Schrägstrich am Ende):
     - Produktion: `https://<domain>/auth/google/callback`
     - Lokal (optional, eigener Client empfohlen): `http://localhost:8000/auth/google/callback` bzw. die lokale `APP_URL` + `/auth/google/callback`
4. Client-ID und Clientschlüssel kopieren. Der Clientschlüssel ist geheim und gehört nur in die `.env`, nie ins Repository, nicht in Tickets und nicht in den Chat.

## 2. `.env`-Schlüssel

Nur die Namen. Die Werte trägt der Betreiber selbst in die `.env` ein.

| Schlüssel | Bedeutung |
|---|---|
| `GOOGLE_CLIENT_ID` | Client-ID aus der Google Cloud Console. Leer = Funktion aus. |
| `GOOGLE_CLIENT_SECRET` | Clientschlüssel. Geheim. Leer = Funktion aus. |
| `GOOGLE_REDIRECT_URI` | Optional. Ohne Eintrag gilt `APP_URL` + `/auth/google/callback` (Socialite ergänzt relative Pfade mit `APP_URL`). In Produktion empfohlen: absolut setzen, z. B. `https://<domain>/auth/google/callback`. Muss **exakt** einer der in Google eingetragenen Weiterleitungs-URIs entsprechen. |

Wichtig:
- `APP_URL` muss die öffentliche Adresse mit `https://` sein, sonst lehnt Google die Weiterleitung ab (`redirect_uri_mismatch`).
- Nach dem Ändern der `.env` auf dem Server ggf. `php artisan config:cache` neu ausführen (nur mit Freigabe).
- Eine leere Zeile `GOOGLE_REDIRECT_URI=` überschreibt den Default mit einem leeren Wert. Den Schlüssel deshalb weglassen oder auskommentieren, wenn er nicht gebraucht wird.

## 3. Datenbank

Migration `database/migrations/2026_11_21_120000_add_google_id_to_users_table.php` (additiv):

- `users.google_id` (Text, darf leer sein, eindeutig)
- `users.password` darf leer sein

Unter SQLite baut Laravel die Tabelle `users` dafür neu auf und schaltet währenddessen die Fremdschlüsselprüfung ab. Der Test `tests/Feature/Auth/GoogleLoginMigrationTest.php` zeigt, dass Daten, Indizes und Fremdschlüssel erhalten bleiben. **Vorher trotzdem eine Sicherung der SQLite-Datei anlegen** (RELEASE-CHECKLIST, F-11).

Rollback (`php artisan migrate:rollback --step=1`) bricht bewusst ab, solange Konten ohne Passwort existieren. Diese Konten müssen vorher ein Passwort festlegen. Es werden keine Daten automatisch geändert.

## 4. Prüfen nach dem Einrichten

1. `/login` aufrufen: Der Knopf „Mit Google anmelden“ ist sichtbar.
2. Mit einem Test-Google-Konto anmelden: Ziel ist `/home`.
3. Abbrechen bei Google („Abbrechen“ im Zustimmungsdialog): zurück auf `/login` mit der Meldung „Die Anmeldung mit Google wurde abgebrochen.“
4. Im Log stehen bei Fehlern nur Ereignis und Grund (`reason`, ggf. Klasse der Ausnahme), keine E-Mail-Adressen oder Namen.

Typische Fehler:
- `redirect_uri_mismatch`: `APP_URL`/`GOOGLE_REDIRECT_URI` passt nicht exakt zur eingetragenen Weiterleitungs-URI (http/https, www, Schrägstrich).
- „Die Anmeldung mit Google ist abgelaufen oder ungültig“: Sitzungs-Cookie ging verloren (z. B. `SESSION_DOMAIN` falsch, `SESSION_SECURE_COOKIE` bei http) oder der Nutzer hat zu lange gewartet.
- Knopf fehlt: `GOOGLE_CLIENT_ID` oder `GOOGLE_CLIENT_SECRET` leer bzw. Konfigurations-Cache veraltet.

## 5. Datenschutz-Hinweis für den Betreiber

Keine Rechtsberatung. Vor dem Einschalten prüfen und die Datenschutzerklärung (`/datenschutz`, Vorlage siehe `RECHTSTEXTE.md`) ergänzen:

- **Empfänger/Drittanbieter:** Google Ireland Limited (bzw. Google LLC) als Anbieter des Anmeldedienstes. Beim Klick auf den Knopf wird der Browser zu Google weitergeleitet. Erst dann erhält Google Daten wie IP-Adresse und Browserangaben. Vorher lädt die Seite nichts von Google (das Logo ist eingebettet).
- **Übermittelte Daten an PlantMel:** Google-Kennung (`sub`), E-Mail-Adresse, Bestätigungsstatus der E-Mail, Name. Gespeichert werden Google-Kennung, E-Mail-Adresse und Name. Profilbild und Zugriffstoken werden nicht gespeichert.
- **Rechtsgrundlage:** in der Regel Art. 6 Abs. 1 lit. b DSGVO (Nutzungsvertrag/Konto) und lit. a (die Person wählt die Anmeldung mit Google selbst).
- **Drittlandübermittlung:** Google LLC (USA), EU-US Data Privacy Framework bzw. Standardvertragsklauseln. Den aktuellen Stand bei Google prüfen.
- **Automatische Verknüpfung:** Darauf hinweisen, dass ein bestehendes Konto mit gleicher, bei Google bestätigter E-Mail-Adresse automatisch verknüpft wird.
- **Löschung:** Beim Löschen des PlantMel-Kontos wird auch die Google-Kennung gelöscht. Die Freigabe bei Google entfernt die Person selbst unter <https://myaccount.google.com/permissions>.
- Die Registrierungsseite verweist bereits auf die Datenschutzerklärung. Der Hinweis gilt für beide Wege.

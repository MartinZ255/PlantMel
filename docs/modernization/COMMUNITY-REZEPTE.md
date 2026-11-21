# Community-Rezepte – Ablauf, Moderation, Hinweise für den Betreiber

Stand: 2026-11-21 · Entscheidung E18 / ADR-005 (`DECISIONS.md`) · Branch `feat/community-rezepte`

> **Migration, `.env`-Änderungen und das Einspielen auf dem Server brauchen eine gesonderte, ausdrückliche Freigabe für genau diese Aktion.** Dieses Dokument ist keine Freigabe. Die Rechtshinweise unten sind keine Rechtsberatung; der Betreiber prüft sie selbst bzw. lässt sie prüfen.

## 1. Was die Funktion macht

- **Neue öffentliche Seite „Community“** (`/community`, Kopfnavigation „Community“): Liste der freigegebenen Community-Rezepte mit Suche, Kategorie- und Zutatenfiltern und 24 Rezepten je Seite (wie `/rezepte`). Detailseite `/community/rezept/{id}` mit Autor („von …“), Badge „Community“, Zutaten zum Abhaken, Schritten, Bewertung und Meldeknopf.
- **Das Rezeptbuch des Hosts bleibt getrennt:** `/rezepte`, Startseite, `/home` und die Zutatensuche zeigen weiterhin nur Host-Rezepte. `/rezept/{id}` eines Community-Rezepts leitet auf die Community-Seite um (bzw. 404, wenn es nicht sichtbar ist), die Host-Bearbeitungsrouten antworten für Community-Rezepte mit 404.
- **Jeder angemeldete Nutzer** kann Rezepte einreichen (`/community/erstellen`), eigene bearbeiten und löschen und sieht sie unter **„Meine Rezepte“** (`/meine-rezepte`, Nutzermenü und Mobilmenü) mit Status und ggf. Ablehnungsgrund.
- **Der Host** moderiert unter **„Zu prüfen“** (`/moderation`, Eintrag mit Anzahl im Nutzermenü und Mobilmenü) und kann jedes Community-Rezept bearbeiten oder löschen.

## 2. Status und Sichtbarkeit

| Status | Bedeutung | Wer sieht das Rezept |
|---|---|---|
| `draft` | Entwurf („Als Entwurf speichern“) | Autor, Host |
| `pending` | eingereicht, wartet auf Freigabe | Autor, Host |
| `approved` | freigegeben | alle, auch Gäste |
| `rejected` | abgelehnt, mit Grund | Autor (inkl. Grund), Host |

- **Bearbeitet der Autor ein Rezept** (auch ein freigegebenes), geht es zurück auf `pending` und ist bis zur erneuten Freigabe verborgen. Das Formular kündigt das an („Dein Rezept ist gerade öffentlich … nach dem Speichern erneut geprüft“). Ein erneut eingereichtes abgelehntes Rezept verliert den alten Ablehnungsgrund.
- **Bearbeitet der Host** ein fremdes Community-Rezept, bleibt der Status unverändert.
- Nicht sichtbare Rezepte antworten mit **404** (verrät nichts über Entwürfe anderer); sichtbare, aber fremde Rezepte beim Bearbeiten/Löschen mit **403**.
- In Sammlungen anderer Nutzer verschwindet ein Rezept, solange es nicht freigegeben ist; fremde nicht sichtbare Rezepte lassen sich nicht in Sammlungen aufnehmen.

## 3. Zutaten und Kategorien

- Nutzer wählen Zutaten aus dem Katalog (Autocomplete). Eine fehlende Zutat schreiben sie einfach hin; sie wird im Formular als **„Neuer Vorschlag“** markiert und als `ingredients.status = proposed` gespeichert.
- Vorschläge erscheinen **nicht** im Katalog, nicht im Autocomplete anderer Nutzer, nicht in der Zutatensuche und nicht im Host-Formular.
- Gleiche Schreibweise wie eine Katalog-Zutat (auch mit anderer Groß-/Kleinschreibung bei ASCII-Buchstaben) → die Katalog-Zutat wird verwendet. Gleicher Name wie ein offener Vorschlag → derselbe Vorschlag wird mitbenutzt.
- **Freigabe** übernimmt die Vorschläge des Rezepts in den Katalog.
- **Ablehnung:** Die Vorschläge bleiben am abgelehnten Rezept (weiter nicht im Katalog), damit der Autor es korrigieren und erneut einreichen kann. **Verworfen** werden Vorschläge, auf die kein Rezept mehr verweist – nach dem Löschen eines Rezepts, nach dem Entfernen beim Bearbeiten, nach dem Zuordnen, nach Konto-Löschung und bei jeder Ablehnung (für bereits verwaiste).
- Vor der Freigabe kann der Host einen Vorschlag **umbenennen** oder **einer vorhandenen Katalog-Zutat zuordnen** (Dubletten vermeiden; alle Verweise wandern auf die Katalog-Zutat).
- Alternativzutaten dürfen nur Katalog-Zutaten sein.
- **Kategorien** wählen Nutzer nur aus vorhandenen; sie legen keine an. Umbenennen/Löschen von Zutaten und Kategorien bleibt dem Host vorbehalten (unverändert).

## 4. Bewertung, Sammlungen, Meldeweg

- **Bewertung:** Angemeldete Nutzer außer dem Autor bewerten freigegebene Community-Rezepte mit 1–5 Sternen (Geschmack), je Nutzer eine Bewertung (änderbar, entfernbar). Gespeichert in `recipe_user_ratings`; Host-Rezepte behalten ihre Bewertung in `recipe_ratings` (E14, unverändert).
- **Sammlungen:** freigegebene Community-Rezepte (und eigene) lassen sich wie Host-Rezepte speichern.
- **Melden:** Auf jeder öffentlichen Community-Rezeptseite „Rezept melden“ – auch ohne Konto. Pflicht: Grund und Bestätigung „nach bestem Wissen richtig“; Beschreibung Pflicht bei „rechtswidrig“, „Urheberrecht“ und „Etwas anderes“; E-Mail für Rückfragen optional. Höchstens 5 Meldungen je Stunde und Absender (Nutzer bzw. IP, nur im Cache gezählt). Es wird **keine IP-Adresse gespeichert**.
- Der Host sieht offene Meldungen in der Moderation und kann das Rezept **ausblenden** (zurück auf „wird geprüft“), **löschen** oder die Meldung als **erledigt** markieren.

## 5. KI-Import (TikTok) mit eigenem Gemini-Schlüssel

- Der Server-Schlüssel (`GEMINI_API_KEY` → `services.gemini.key`) gilt **nur für Hosts**.
- Nutzer hinterlegen unter **Einstellungen → KI-Import** (`/settings/ki-import`) ihren eigenen Gemini-API-Schlüssel (Google AI Studio). Formatprüfung auf dem Server (`AIza` + 35 Zeichen). Gespeichert **verschlüsselt** (Cast `encrypted` mit `APP_KEY`), angezeigt wird nur „hinterlegt, endet auf …1234“; der Schlüssel ist löschbar. „Schlüssel prüfen“ ruft einmal die Modellliste von Gemini ab.
- Ohne eigenen Schlüssel ist der Import im Community-Formular deaktiviert, mit Hinweis und Link zur Einstellung; der Server lehnt den Start mit 422 ab.
- Der Import-Job verwendet den Schlüssel **des auslösenden Nutzers** (Host: Server-Schlüssel). Der Schlüssel steht nie im Job-Payload (nur die Import-ID), nie im Log (Server- und Nutzerschlüssel werden in Fehlermeldungen geschwärzt) und wird bei Validierungsfehlern nicht als „old input“ in der Sitzung gespeichert.
- Das bestehende Limit (5 Importe je Stunde und Nutzer, ein laufender Import) gilt für Host und Nutzer gemeinsam.
- **Wichtig:** Rotiert der Betreiber `APP_KEY`, sind hinterlegte Nutzerschlüssel nicht mehr lesbar; Nutzer müssen sie neu eingeben.

## 6. Konto löschen

Beim Löschen des Kontos werden alle eigenen Community-Rezepte samt Bildern, die eigenen Bewertungen und ungenutzte Vorschläge gelöscht; der Lösch-Dialog kündigt das mit Anzahl an. Meldungen des Nutzers bleiben für die Moderation erhalten, verlieren aber den Personenbezug (`user_id = null`).

## 7. Datenmodell (additiv, SQLite-kompatibel)

| Tabelle | Neu | Bestandsdaten |
|---|---|---|
| `recipes` | `type` (`host`/`community`, Default `host`), `status` (Default `approved`), `user_id` (FK users, cascade), `rejection_reason`, `submitted_at`, `reviewed_at`, `reviewed_by` (FK users, set null); Indizes `(type, status)`, `user_id` | über Spalten-Defaults `host`/`approved`, keine UPDATEs |
| `ingredients` | `status` (`approved`/`proposed`, Default `approved`), `proposed_by` (FK users, set null); Index `status` | über Default `approved` |
| `recipe_user_ratings` | neu: `recipe_id`, `user_id`, `value`, unique `(recipe_id, user_id)` | – |
| `recipe_reports` | neu: `recipe_id`, `user_id` (nullable), `reason`, `message`, `contact_email`, `status`, `resolved_at`, `resolved_by` | – |
| `users` | `gemini_api_key` (TEXT, verschlüsselt) | NULL |

Unter SQLite baut Laravel `recipes` und `ingredients` wegen der neuen Fremdschlüssel je **einmal** neu auf (mit `PRAGMA foreign_keys = 0` während des Umbaus). `CommunityMigrationTest` prüft mit Bestandsdaten und eingeschalteten Fremdschlüsseln: alle Daten, Indizes und Fremdschlüssel bleiben, Kaskaden wirken weiter, Rollback stellt das alte Schema wieder her. Die Rollbacks brechen bewusst ab, solange Community-Rezepte, Vorschläge, Bewertungen, Meldungen oder hinterlegte Schlüssel existieren.

**Vor dem Einspielen in Produktion:** Sicherung der SQLite-Datei, `php artisan migrate --pretend` (zeigt für den Neuaufbau unter SQLite unvollständige `__temp__`-Tabellen, siehe F-101 – kein Fehler), Probelauf auf einer Kopie (nur mit Freigabe).

## 8. Hinweise für den Betreiber (Recht und Datenschutz)

Keine Rechtsberatung. Vor dem Freischalten prüfen bzw. ergänzen:

1. **Nutzungsbedingungen für Nutzerinhalte** (fehlen bisher): Wer Rezepte einreicht, versichert, dass Texte und Bilder eigene sind bzw. verwendet werden dürfen, und räumt dem Betreiber ein einfaches Nutzungsrecht zur Veröffentlichung auf PlantMel ein; Regeln, was nicht erlaubt ist (rechtswidrig, beleidigend, Werbung, gesundheitsgefährdend); Hinweis auf Moderation, Ablehnung, Ausblenden und Löschen; Löschung mit dem Konto. Verlinken im Formular und bei der Registrierung.
2. **Bildrechte:** Rezeptfotos sind meist urheberrechtlich geschützt; Fotos mit erkennbaren Personen berühren deren Recht am eigenen Bild. Moderation sollte offensichtlich fremde Fotos (Stockfotos, Screenshots aus Videos) ablehnen. Das Formular weist bereits darauf hin, nur eigene oder erlaubte Bilder hochzuladen.
3. **Meldeweg (Digital Services Act, Art. 16):** umgesetzt mit Grund, Begründung, optionaler E-Mail und Bestätigung „nach bestem Wissen“. Empfohlen: in Impressum/Nutzungsbedingungen eine Kontaktstelle für Behörden und Nutzer (Art. 11/12 DSA) nennen; Meldende, die eine E-Mail angeben, über die Entscheidung informieren und die Entscheidung kurz begründen (Art. 17) – derzeit manuell per E-Mail, die App versendet nichts. Ablehnungsgründe werden dem Autor bereits angezeigt.
4. **Datenschutzerklärung ergänzen** (`/datenschutz`, Vorlage `RECHTSTEXTE.md`):
   - Veröffentlichung des **Anzeigenamens** als Autor bei freigegebenen Community-Rezepten (Art. 6 Abs. 1 lit. b DSGVO, Teil des Dienstes); Hinweis, dass Nutzer im Profil einen Spitznamen wählen können.
   - **Meldungen:** gespeichert werden Grund, Text, optionale E-Mail und ggf. die Nutzer-ID, **keine IP-Adresse**; die IP wird nur kurzzeitig im Cache für das Rate-Limit gezählt. Aufbewahrung festlegen (z. B. Löschung erledigter Meldungen nach 6 Monaten – derzeit keine automatische Löschung, siehe F-104).
   - **Bewertungen:** je Nutzer gespeichert, öffentlich nur als Durchschnitt und Anzahl.
   - **Eigener Gemini-Schlüssel:** verschlüsselt gespeichert, nur für Importe des Nutzers verwendet. Beim Import werden die TikTok-Beschreibung und ggf. das Video **mit dem Schlüssel des Nutzers** an Google (Gemini API) übermittelt; Verantwortlich für die Nutzung des Google-Kontos ist der Nutzer, die Verarbeitung auf PlantMel-Seite gehört zum Dienst. Google als Empfänger nennen (wie beim Host-Import und F-48).
5. **Altersgrenze und Inhalte Minderjähriger:** ggf. in den Nutzungsbedingungen regeln.
6. **Moderation organisieren:** Wer prüft wie schnell? Das Badge „Zu prüfen“ erscheint nur im Nutzermenü des Hosts; es gibt keine E-Mail-Benachrichtigung (F-105).

## 9. Routen (Rollen-Matrix in `RouteRoleMatrixTest`)

| Route | URL | Zugriff |
|---|---|---|
| `community.index`, `community.show` | `GET /community`, `GET /community/rezept/{id}` | öffentlich |
| `community.report` | `POST /community/rezept/{id}/melden` | öffentlich, `throttle:recipe-reports` |
| `community.mine`, `community.create`, `community.store` (`throttle:community-recipes`, 10/h), `community.edit`, `community.update`, `community.destroy`, `community.rate` | `/meine-rezepte`, `/community/erstellen`, `POST /community`, `/community/rezept/{id}/bearbeiten`, `POST/DELETE /community/rezept/{id}`, `POST …/bewertung` | angemeldet (`auth`, `verified`); Policy `RecipePolicy` |
| `communityImports.store` (`throttle:recipe-imports`), `communityImports.show` | `POST /community/import`, `GET /community/import/{id}` | angemeldet, nur mit eigenem Schlüssel (Host: Server-Schlüssel) |
| `moderation.*` | `/moderation`, `POST …/freigeben`, `…/ablehnen`, `…/ausblenden`, `…/meldung/{id}/erledigt`, `PATCH/POST /moderation/zutat/{id}[/zuordnen]` | Host (`is_host`) |
| `settings.ai.*` | `GET/PUT/DELETE /settings/ki-import`, `POST /settings/ki-import/pruefen` (PUT/POST `throttle:6,1`) | angemeldet |

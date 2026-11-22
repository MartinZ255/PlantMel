# Architecture Decision Log

| Datum | Entscheidung | Begründung | Status |
|---|---|---|---|
| offen | Bestehenden Stack zunächst beibehalten | Regressionen und unnötige Migrationen vermeiden | vorgeschlagen |
| offen | Produktionsdeployment nur nach Freigabe | Schutz der Live-Daten und Verfügbarkeit | vorgeschlagen |
| 2026-11-13 | „Meine Zutaten“ bleibt als Feature und wird später ausgebaut (E3) | Entscheidung des Auftraggebers: Nutzer speichern dort ihre vorhandenen Zutaten | entschieden |
| 2026-11-13 | Ungenutzte API-Routen entfernt (E6) | Entscheidung des Auftraggebers; Nichtnutzung per Grep belegt | umgesetzt (Slice 1) |
| 2026-11-13 | Security-Header als Laravel-Middleware (E11) | Auftraggeber: Ort egal, muss auf dem VPS laufen; Middleware ist testbar | umgesetzt (Slice 1), VPS-Prüfung offen |
| 2026-11-13 | Feld „Quelle“ am Rezept entfällt | Entscheidung des Auftraggebers; es wurde nie gespeichert (F-15) | Slice 2 |
| 2026-11-13 | Kategorien und Bewertungsdimensionen werden nach Name sortiert statt nach der nicht vorhandenen Spalte `sort_order` (E5) | Empfehlung des Leads, keine Migration nötig; vom Auftraggeber nicht widersprochen | Slice 2 |
| 2026-11-14 | Nach Login und Registrierung landet man auf `/home` (E1) | Entscheidung des Auftraggebers | Slice 3 |
| 2026-11-14 | Keine Zwei-Faktor-Authentifizierung; das Feature wird vollständig abgeschaltet (E4) | Entscheidung des Auftraggebers. DB-Spalten bleiben, keine Migration | Slice 3 |
| 2026-11-14 | Hell/Dunkel-Umschaltung abgeschaltet, die App rendert immer hell (E4) | Entscheidung des Auftraggebers; Dark Mode war nur teilweise umgesetzt (F-34) | Slice 3 |
| 2026-11-14 | Landing-Text ohne Verweis auf nicht vorhandene Bewertungen/Notizen (E8) | Entscheidung des Auftraggebers | Slice 3 |
| 2026-11-14 | Zugriff auf den VPS erst, wenn lokal alles fertig ist | Entscheidung des Auftraggebers | offen |
| 2026-11-14 | E-Mail-Verifizierung wird gebaut, bleibt aber per Schalter aus, bis auf dem Server ein kostenloser Mailversand eingerichtet ist (E2) | Entscheidung des Auftraggebers. DNS nennt `mail.plantmel.de` als MX; ob dort ein Mailserver läuft, ist ungeprüft | Slice 4 |
| 2026-11-14 | Impressum und Datenschutzerklärung als Vorlagen mit Platzhaltern; der Auftraggeber füllt sie vor dem Release aus (E9) | Entscheidung des Auftraggebers; öffentliche Seite mit Registrierung (DSGVO, § 18 MStV) | Slice 4 |
| 2026-11-14 | Schrift wird selbst gehostet statt von fonts.bunny.net geladen | Empfehlung des Leads, vom Auftraggeber freigegeben; entfernt einen Drittanbieter aus Datenschutz und CSP | Slice 4 |
| 2026-11-14 | Import zeigt den Fortschritt in Schritten an | Wunsch des Auftraggebers | Slice 4 |
| 2026-11-14 | Weitere Import-Quellen (Instagram, Rezept-Websites) als späteres Feature vormerken (E13) | Wunsch des Auftraggebers, langfristig; Bewertung im Slice-4-Bericht | vorgemerkt |
| 2026-11-19 | Rezepte werden nur noch mit 1–5 Sternen für den Geschmack bewertet, keine Bewertungskategorien mehr (E14) | Entscheidung des Auftraggebers. Die Dimension wird per Name gefunden (`config/plantmel.php`, `rating_dimension` = „Geschmack“; fehlt sie, gibt es keine Bewertung). Keine Migration, nichts gelöscht: Bewertungen anderer Dimensionen bleiben in `recipe_ratings`, werden aber nicht mehr angezeigt oder eingerechnet; `update` ersetzt nur noch die Geschmacks-Bewertung. Reversibel durch Rücknahme des Codes | umgesetzt (Backend + Rezeptdetail), Formular folgt |
| 2026-11-20 | Separate Endpunkte für Zutaten-Alternativen (`ingredientAlternatives.index/store/destroy`, `IngredientAlternativeController`) entfernt (E15) | Entscheidung des Auftraggebers: ungenutzte API-Routen entfernen, sofern Alternativen weiter im Rezept angezeigt werden. Das Frontend rief die Routen nie auf; Alternativen werden mit dem Rezept gespeichert (`RecipeController` store/update) und auf der Rezeptseite angezeigt. Tabelle und Daten bleiben unverändert. Kein Umbau der Routing-Architektur, nur Entfernen dreier ungenutzter Routen; reversibel per Git | umgesetzt, `IngredientAlternativeRoutesRemovedTest`, `RecipeShowTest`, `RecipeAlternativesSyncTest` |
| 2026-11-20 | Ungenutzter Baustein `ui/Card.vue` gelöscht; Artisan-Befehle `user:make-host` und `users:mark-verified` bleiben bewusst (E16) | Entscheidung des Auftraggebers | umgesetzt |
| 2026-11-21 | Anmeldung mit Google über Laravel Socialite zusätzlich zu Fortify; automatische Verknüpfung per verifizierter E-Mail; Konten ohne Passwort möglich (E17) | Freigabe des Auftraggebers, Details in ADR-004 | umgesetzt (Branch `feat/google-login`), Produktion offen |
| 2026-11-21 | Community-Rezepte: Nutzer reichen Rezepte ein, Host gibt frei; eigene Seite `/community`, getrennt vom Host-Rezeptbuch; Zutaten-Vorschläge erst mit Freigabe im Katalog; KI-Import für Nutzer nur mit eigenem Gemini-Schlüssel (E18) | Entscheidung des Auftraggebers, Details in ADR-005 | umgesetzt (Branch `feat/community-rezepte`), Produktion offen |

Neue Entscheidungen als ADR mit Alternativen, Risiken und Rollback dokumentieren.

## ADR-001: Mehrzeilige Zubereitungsschritte verlustfrei im bestehenden Textformat speichern

**Datum:** 2026-11-13 · **Status:** Slice 2 · **Befunde:** F-14, F-66

**Kontext.** Zubereitungsschritte und Notizen liegen als ein Textfeld `recipes.instructions` vor: nummerierte Zeilen („1. …“), danach optional ein Abschnitt „Notizen:“. Beim Lesen wird an jedem Zeilenumbruch getrennt. Ein mehrzeiliger Schritt zerfällt deshalb bei jedem Bearbeiten in mehrere Schritte; eine Zeile „Notizen:“ innerhalb eines Schritts verschiebt alle folgenden Schritte in die Notizen; nummerierte Notizzeilen verlieren ihre Nummer.

**Entscheidung.** Das Textformat bleibt, wird aber eindeutig gemacht: Die erste Zeile eines Schritts trägt die Nummer, jede weitere Zeile desselben Schritts wird beim Speichern mit einer festen Einrückung als Fortsetzungszeile geschrieben. Der Leser hängt eingerückte Zeilen an den vorherigen Schritt an. Der Abschnittskopf „Notizen:“ gilt nur uneingerückt am Zeilenanfang. Notizzeilen werden unverändert gelesen (keine Entfernung von Nummern).

**Alternativen.**
- Zeilenumbrüche innerhalb eines Schritts durch Leerzeichen ersetzen: einfach, verliert aber die Formatierung des Nutzers.
- JSON in derselben Spalte: verlustfrei, ändert aber das Speicherformat aller künftig gespeicherten Rezepte und erschwert das Lesen in der Datenbank.
- Eigene Tabelle `recipe_steps`: sauberste Lösung, braucht aber eine Migration samt Datenübernahme; vor dem Schema-Abgleich der Produktion (F-11) nicht vertretbar.

**Abwärtskompatibilität.** Bestehende Rezepte enthalten keine eingerückten Fortsetzungszeilen und werden gelesen wie bisher. Bereits zerfallene Schritte werden nicht automatisch wieder zusammengeführt.

**Risiken.** Ein Altbestand-Rezept, dessen Schritte zufällig mit genau der Einrückung beginnen, würde anders gelesen als bisher; die Einrückung wird so gewählt, dass das unwahrscheinlich ist, und per Test abgesichert.

**Rollback.** Code zurücknehmen. Rezepte, die inzwischen mit Fortsetzungszeilen gespeichert wurden, erscheinen dann wieder als getrennte Schritte (Zustand wie vor der Änderung), es gehen keine Inhalte verloren.

## ADR-002: Globales Löschen von Kategorien und Zutaten nur mit ausdrücklicher Bestätigung

**Datum:** 2026-11-13 · **Status:** Slice 2 · **Befunde:** F-07, F-65

**Kontext.** Kategorien und Zutaten sind global. Löschen im Bearbeiten-Dialog wirkte sofort und entfernte eine Kategorie aus allen Rezepten bzw. löschte hinterlegte Alternativen per Kaskade mit.

**Entscheidung.** Die Löschendpunkte verlangen einen Bestätigungsparameter. Ohne ihn antworten sie mit 409 und nennen die Zahl der betroffenen Einträge; gelöscht wird nichts. Das Frontend zeigt daraufhin genau einen Dialog mit dieser Zahl und sendet bei Zustimmung erneut mit Bestätigung. Der Schutz liegt damit auf dem Server und nicht nur in der Oberfläche.

**Alternativen.** Nur ein `confirm()` im Frontend (schützt nicht vor fehlerhaften Clients, kennt die Zahl nicht); Soft Deletes (braucht Migration).

**Rollback.** Code zurücknehmen; keine Datenänderung.

## ADR-003: Neues Design-System für Gate C

**Datum:** 2026-11-19 · **Status:** angenommen (Richtung vom Auftraggeber gewählt) · **Befunde:** F-33, F-34, F-35, F-40, F-41, F-43

**Kontext.** Das bestehende Design (Creme, Salbei, Pfirsich; ca. 4.200 Zeilen ungelayertes CSS in `resources/css/rezeptly/app.css`, sieben Breakpoint-Werte, zwei Token-Systeme, Seitenleiste) soll nicht beibehalten werden. Der Auftraggeber ist mit den Farben unzufrieden und wünscht eine umfassende Anpassung ohne konkretes Vorbild.

**Entscheidung.**
- **Farbwelt „Frisch & klar“** (Auswahl des Auftraggebers): Hintergrund `#FFFFFF` / `#F6F8F5`, Text `#17211B`, Primär Kräutergrün `#1F7A4D`, Akzent Tomate `#E4572E`, Flächen `#EAF2EC`, Rahmen `#DCE3DD`. Alle Text- und Bedienelement-Paare erfüllen WCAG 2.2 AA (Text ≥ 4,5:1, Bedienelemente und Fokus ≥ 3:1); wo ein gewählter Ton das nicht schafft, wird eine dunklere Variante für Text verwendet.
- **Layout „bildbetont“** (Auswahl des Auftraggebers): Navigation oben statt dauerhafter Seitenleiste, mobil als Menü; große Rezeptbilder; klare Typografie mit viel Weißraum.
- **Technik:** Design-Tokens als CSS-Variablen in `@theme` von Tailwind v4, damit Tailwind-Utilities und eigene Komponenten dieselben Werte nutzen. Neue Komponenten verwenden Tailwind-Utilities; das alte `rezeptly/app.css` wird seitenweise abgelöst und am Ende entfernt. Eine Breakpoint-Skala (Tailwind-Standard `sm 640`, `md 768`, `lg 1024`, `xl 1280`), mobil zuerst.
- **Bausteine** unter `resources/js/components/ui/`: Button, Card, Field (Label, Hinweis, Fehler, ARIA), Input/Textarea/Select, Chip/Tag, Badge, Dialog (auf Basis von `useModal`), StarRating (Anzeige und Eingabe, per Tastatur), EmptyState, PageHeader. Keine neue UI-Bibliothek.
- **Schrift** bleibt Instrument Sans (bereits selbst gehostet).
- **Stack bleibt** (Laravel, Inertia, Vue, Tailwind); Routen und Datenverträge ändern sich nicht.

**Vorgehen.** Zuerst Fundament, Kopfnavigation und drei Musterseiten (Startseite, Rezeptliste, Rezeptdetail) zur Abnahme durch den Auftraggeber; danach Ausrollen auf alle Seiten.

**Risiken.** Viele Browser-Tests nutzen CSS-Klassen der alten Oberfläche; sie werden auf rollen- und textbasierte Selektoren umgestellt. Alle Screenshot-Baselines ändern sich. Barrierefreiheits-Verbesserungen aus Slice 1–4 dürfen nicht verloren gehen (Regressionstests bleiben der Maßstab).

**Rollback.** Branch nicht mergen; der Stand von Slice 4 bleibt unverändert.

### ADR-003, Revision 1 (2026-11-19): Konzept „Natur & organisch“

**Anlass.** Der Auftraggeber hat die Musterseiten aus Gate C1 abgelehnt: „viel zu basic … ich will ein besonderes design und nicht so ein basic 0815 design“. Neu gewählt: Konzept **„Natur & organisch“**; die Farbpalette darf der Lead neu festlegen.

**Neue Gestaltungsrichtung (verbindlich für Gate C).**
- **Charakter:** hochwertig, warm und lebendig statt neutral; organische Formen, Tiefe durch Ebenen, sanfte Bewegung. Jede Seite braucht mindestens ein unverwechselbares Gestaltungselement – keine Standard-Karten-Raster ohne eigene Handschrift.
- **Palette (neu, ersetzt „Frisch & klar“):** dunkles Waldgrün als tragender Ton für große Flächen (Hero, Bänder, Footer), Moosgrün und ein heller Matcha-/Limettenton als lebendige Begleiter, ein warmer Beeren-/Rote-Bete-Ton als Akzent, ein heller, leicht grünlicher Naturgrund statt Reinweiß. Kein Creme/Salbei/Pfirsich-Dreiklang wie im alten Design. Genaue Werte legt der Design-Agent fest; WCAG 2.2 AA bleibt Pflicht.
- **Formen:** organische Blob-Masken für Bilder (auch Rezeptkarten), wellige Abschnittsübergänge (SVG), weiche mehrschichtige Verläufe und Blob-Hintergründe, Blatt- und Kräuter-Ornamente als SVG, feine Körnung (Grain) über Flächen.
- **Typografie:** ausdrucksstarke Display-Serif für Überschriften (Fraunces, OFL, selbst gehostet über Fontsource), Instrument Sans für Fließtext.
- **Bewegung:** sanftes Einblenden beim Scrollen, schwebende Elemente, Formwechsel der Bildmasken beim Hover – alles abgeschaltet bei `prefers-reduced-motion`.
- **Bilder:** Die Startseite zeigt echte Rezeptbilder aus der Datenbank (neue Prop `featuredRecipes`, kleine Backend-Ergänzung), statt nur Formen.

**Unverändert aus ADR-003:** Tailwind-v4-Tokens, Bausteine unter `components/ui/`, Navigation oben, Barrierefreiheit, Vorgehen in zwei Schritten (Musterseiten zur Abnahme, dann Ausrollen).

## ADR-004: Anmeldung mit Google (Laravel Socialite) neben Fortify (E17)

**Datum:** 2026-11-21 · **Status:** angenommen (vom Auftraggeber freigegeben), umgesetzt auf `feat/google-login` · **Anleitung:** `docs/modernization/GOOGLE-LOGIN.md`

**Kontext.** Nutzer sollen sich zusätzlich zu E-Mail und Passwort mit ihrem Google-Konto anmelden und registrieren können. CLAUDE.md verlangt für Änderungen am Auth-System ein ADR.

**Entscheidung.**
- **Fortify bleibt** das Auth-System (Login, Registrierung, Passwort vergessen, E-Mail-Verifizierung, Logout). Google kommt als zusätzlicher Weg über das offizielle Paket `laravel/socialite` (OAuth 2.0 / OpenID Connect, Scopes nur `openid`, `email`, `profile`).
- Zwei Routen unter Middleware `guest` und `throttle:google-login`: `GET /auth/google/redirect` (`auth.google.redirect`) und `GET /auth/google/callback` (`auth.google.callback`), Controller `App\Http\Controllers\Auth\GoogleLoginController`.
- **Schalter:** Der Knopf erscheint nur, wenn `services.google.client_id` und `client_secret` gesetzt sind (geteilte Prop `features.googleLogin`). Sonst leiten beide Routen mit Hinweis auf `/login` um (kein 500).
- **Zuordnung im Callback:** nur mit von Google als verifiziert gemeldeter E-Mail (`email_verified` bzw. `verified_email`). Reihenfolge: gleiche `google_id` → anmelden; sonst gleiche E-Mail (ohne Groß-/Kleinschreibung) → `google_id` **automatisch verknüpfen** (Entscheidung des Auftraggebers), `email_verified_at` setzen, falls leer; sonst neues Konto ohne Passwort und ohne Host-Recht (wie normale Registrierung).
- **Schema (additiv):** `users.google_id` (string, nullable, unique) und `users.password` nullable. Keine Datenänderung.
- **Konten ohne Passwort:** Passwort-Login schlägt mit der normalen Fehlermeldung fehl; „Passwort vergessen“ setzt ein Passwort; Einstellungen bieten „Passwort festlegen“ ohne aktuelles Passwort; Konto löschen wird durch Eingabe der eigenen E-Mail-Adresse bestätigt.
- **E-Mail-Änderung im Profil** bleibt erlaubt. Schlüssel für Google ist `google_id`, nicht die E-Mail; die Google-Anmeldung funktioniert nach einer Änderung weiter. Die Verknüpfung wird dabei nicht gelöst.

**Alternativen.**
- Eigene OAuth-Implementierung ohne Paket: mehr Code, mehr Fehlerquellen (state, Token-Austausch); verworfen.
- Separate Tabelle `social_accounts` (mehrere Anbieter je Nutzer): flexibler, aber für einen einzigen Anbieter überdimensioniert; bei weiteren Anbietern neu bewerten.
- Verknüpfung nur nach Passwort-Bestätigung statt automatisch: sicherer gegen Fehlzuordnung, aber umständlicher; vom Auftraggeber verworfen. Die Pflicht zur von Google verifizierten E-Mail mindert das Risiko.
- Wechsel des Auth-Systems (z. B. Breeze/Jetstream/Passport): nicht nötig, verworfen.

**Risiken.**
- *Automatische Verknüpfung:* Wer die Google-Adresse eines bestehenden PlantMel-Kontos kontrolliert, erhält Zugriff auf dieses Konto. Das gilt aber ohnehin über „Passwort vergessen“ (Zugriff auf das Postfach). Akzeptiert.
- *SQLite und `->change()`:* Laravel baut die Tabelle `users` dafür neu auf (neue Tabelle, Daten kopieren, alte löschen, umbenennen). Bei eingeschalteten Fremdschlüsseln würde ein `DROP TABLE users` die Kaskaden in `comments`, `collections`, `recipe_imports` auslösen. Laravel schaltet dafür die Fremdschlüsselprüfung ab; per Test abgesichert (`GoogleLoginMigrationTest`). Vor dem Einspielen in Produktion: Backup der SQLite-Datei (ROADMAP: Migrationen nur mit gesonderter Freigabe, F-11).
- *Datenschutz:* Google wird als weiterer Empfänger personenbezogener Daten genannt werden müssen (Datenschutzerklärung durch den Betreiber, siehe GOOGLE-LOGIN.md).
- *Konten ohne Passwort:* Funktionen, die ein Passwort verlangen (Passwort-Bestätigung), sind für sie nicht nutzbar; derzeit nutzt keine Route `password.confirm`.

**Rollback.**
- Code: Merge zurücknehmen bzw. `GOOGLE_CLIENT_ID` leeren (Knopf verschwindet, Routen leiten auf `/login`).
- Schema: `php artisan migrate:rollback --step=1` entfernt `google_id` und macht `password` wieder Pflicht. Der Rollback bricht bewusst mit Fehlermeldung ab, solange Konten mit `password = null` existieren (diese Konten müssten vorher ein Passwort setzen oder gelöscht werden; keine automatische Datenänderung).

## ADR-005: Community-Rezepte mit Host-Freigabe (E18)

**Datum:** 2026-11-21 · **Status:** angenommen (Funktionsumfang vom Auftraggeber festgelegt), umgesetzt auf `feat/community-rezepte` · **Beschreibung:** `docs/modernization/COMMUNITY-REZEPTE.md`

**Kontext.** Bisher pflegt nur der Host Rezepte. Angemeldete Nutzer sollen eigene Rezepte veröffentlichen können, ohne das Rezeptbuch des Hosts zu verändern. Nutzerinhalte brauchen Moderation, einen Meldeweg und klare Rechte. Der KI-Import (Gemini) darf für Nutzer keine Kosten auf dem Server-Schlüssel verursachen.

**Entscheidung.**
- **Ein Rezept-Modell, zwei Arten:** `recipes.type` (`host` | `community`) statt eigener Tabelle. Community-Rezepte nutzen dieselben Zutaten-, Kategorie-, Bild- und Sammlungs-Tabellen, dasselbe Textformat (ADR-001) und dasselbe Formular (`RecipeForm`, Variante `community`). Bestandsrezepte sind über den Spalten-Default `host`/`approved` eingeordnet – ohne Datenänderung.
- **Status** `draft | pending | approved | rejected` + `rejection_reason`, `submitted_at`, `reviewed_at`, `reviewed_by`. Öffentlich nur `approved`; Autor und Host sehen alle. Jede Änderung des Autors setzt auf `pending`, Änderungen des Hosts lassen den Status.
- **Trennung:** Host-Liste, Startseite, Home, Zutatensuche und Host-Routen filtern auf `type = host`; Community hat eigene Routen und Seiten. Nicht sichtbare Rezepte → 404.
- **Rechte über `App\Policies\RecipePolicy`** (`view`, `update`, `delete`, `moderate`, `rate`, `report`); Moderationsrouten zusätzlich hinter `is_host`.
- **Zutaten-Vorschläge** als `ingredients.status = proposed` (+ `proposed_by`) in der bestehenden Tabelle: `recipe_ingredients.ingredient_id` bleibt ein normaler Fremdschlüssel, Freigabe ist ein Status-Update. Katalogansichten filtern auf `approved`; ungenutzte Vorschläge werden gelöscht.
- **Bewertung wie bei Host-Rezepten (E14):** Der Autor vergibt im Formular optional 1–5 Sterne „Geschmack“ (zurücksetzbar), gespeichert in `recipe_ratings` über `TasteRating`. Andere Nutzer bewerten nicht. Bearbeitet der Host ein fremdes Community-Rezept, ist der Abschnitt ausgeblendet und der Server ignoriert `ratings`: Im Host-Formular gilt die Payload als Soll-Zustand (fehlt die Bewertung, wird sie gelöscht) – das passt für Host-Rezepte ohne Autor, würde bei Community-Rezepten aber die Bewertung des Autors überschreiben.
- **Revision 2026-11-22 (Änderungswunsch des Auftraggebers):** Die zunächst gebaute Bewertung durch andere Nutzer (`recipe_user_ratings`, Route `community.rate`, Widget auf der Detailseite) ist vollständig entfernt; die Migration war noch nirgends gelaufen und wurde gelöscht.
- **Meldungen** in `recipe_reports` (Gäste erlaubt, Rate-Limit, keine IP gespeichert).
- **Gemini:** `users.gemini_api_key` (Cast `encrypted`). Der Job ermittelt den Schlüssel über den Nutzer des Imports (`User::geminiKeyForImport()`): Host → Server-Schlüssel, sonst nur der eigene. Beide Schlüssel werden in Logs geschwärzt.
- **Kopfzeile:** neuer Eintrag „Community“. Damit die Kopfzeile bei 768 und 1024 px einzeilig bleibt, sind Navigationseinträge unter `xl` etwas schmaler, das Schloss-Symbol erscheint erst ab `xl` (Text für Screenreader bleibt), „Registrieren“ und „Rezept erstellen“ (Host) stehen erst ab `lg` in der Kopfzeile (sonst über Anmeldeseite bzw. Nutzermenü erreichbar). Bei 768 px war „Registrieren“ schon vorher abgeschnitten (Baseline).

**Alternativen.**
- Eigene Tabelle `community_recipes`: doppelte Zutaten-/Bild-/Sammlungslogik; verworfen.
- Vorschläge in eigener Tabelle: `recipe_ingredients` bräuchte eine zweite, nullable Referenz (Neuaufbau mit Änderung des NOT-NULL-Fremdschlüssels) oder Freitext; verworfen.
- Fremdschlüssel weglassen, um den SQLite-Neuaufbau zu vermeiden: keine Integrität auf Datenbankebene; verworfen, Neuaufbau stattdessen per Test abgesichert (je Tabelle genau ein Neuaufbau).
- Server-Schlüssel mit Kontingent je Nutzer: Kosten und Missbrauchsrisiko beim Betreiber; vom Auftraggeber verworfen.
- Bewertung durch alle angemeldeten Nutzer (eigene Tabelle je Nutzer und Rezept): gebaut und auf Wunsch des Auftraggebers wieder entfernt (Revision 2026-11-22).

**Risiken.**
- *SQLite-Neuaufbau* von `recipes` und `ingredients` (Laravel schaltet die Fremdschlüsselprüfung dabei ab). Geprüft mit Bestandsdaten (`CommunityMigrationTest`); vor Produktion Sicherung und Probelauf (F-102).
- *Nutzerinhalte:* Haftung und Bildrechte; Nutzungsbedingungen und Datenschutzerklärung vor dem Freischalten ergänzen (F-103).
- *Moderationslast:* keine Benachrichtigung außer dem Badge (F-105).
- *`APP_KEY`-Rotation* macht hinterlegte Nutzerschlüssel unlesbar.

**Rollback.**
- Code: Merge zurücknehmen – vorher Community-Rezepte löschen, sonst zeigt der alte Code sie als Host-Rezepte; am besten zusammen mit dem Schema-Rollback.
- Schema: `php artisan migrate:rollback --step=4`. Die Rollbacks brechen bewusst ab, solange Community-Rezepte, Vorschläge, Meldungen oder hinterlegte Schlüssel existieren (keine automatische Datenänderung).


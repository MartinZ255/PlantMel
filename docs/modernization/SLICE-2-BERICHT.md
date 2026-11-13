# Abschlussbericht Slice 2 – Datenverlust beim Bearbeiten von Rezepten

Stand: 2026-11-13 · Branch `fix/slice-2-datenverlust` (lokal, kein Push) · aufbauend auf `fix/slice-1-kritisch` (`cab0de4`)

## 1. Ergebnis

Slice 2 ist lokal umgesetzt und durch Tests belegt. Nichts wurde gepusht oder deployt; keine Migration.

| Messung | Vor Slice 2 | Nach Slice 2 | Exit |
|---|---|---|---|
| Reguläre Suite (`php artisan test`) | 410 bestanden | **465 bestanden** | 0 |
| Bekannte Defekte (`--testsuite=KnownDefects`) | 14 rot | **7 rot** | 1 (beabsichtigt) |
| Browser (`npx playwright test`) | 151, davon 16 erwartete Defekte | **164, davon 15 erwartete Defekte** | 0 |

Protokolle: `baseline/phpunit-06-slice2-default.txt`, `phpunit-07-slice2-known-defects.txt`.

## 2. Entscheidungen

| Entscheidung | Quelle | Umsetzung |
|---|---|---|
| Feld „Quelle“ entfällt | Auftraggeber | aus Formular, Validierung, Ausgabe und Import-Ergebnis entfernt (F-15) |
| Sortierung nach Name statt `sort_order` | Empfehlung des Leads, nicht widersprochen | F-12, ohne Migration |
| Mehrzeilige Schritte im bestehenden Textformat mit eingerückten Fortsetzungszeilen | ADR-001 | F-14, F-66 |
| Globales Löschen nur mit Bestätigung auf dem Server | ADR-002 | F-07, F-65 |

## 3. Was behoben wurde

- **Alternativzutaten (F-13):** Bearbeiten speichert Alternativen jetzt genauso wie Anlegen. Reste entfernter Zutaten verschwinden. Das Alternativen-Fenster speichert nichts mehr sofort, sondern nur noch zusammen mit dem Rezept; „Abbrechen“ verwirft die Auswahl. Im Browser belegt: Beim Hinzufügen und Entfernen geht kein einziger Request an die Alternativen-Endpunkte.
- **Mehrzeilige Schritte (F-14, F-66):** Ein Schritt mit Zeilenumbrüchen bleibt ein Schritt, auch nach mehrfachem Bearbeiten. Eine Zeile „Notizen:“ innerhalb eines Schritts verschiebt nichts mehr; nummerierte Notizen behalten ihre Nummer. Die Detailseite zeigt die Umbrüche. Zwölf Roundtrip-Fälle und sechs Altformat-Fälle sind getestet.
- **„Quelle“ (F-15):** entfernt.
- **Bilddateien (F-19):** Beim Löschen eines Rezepts werden die Dateien entfernt. Beim Ersetzen verschwindet die alte Datei erst nach erfolgreichem Speichern; schlägt das Speichern fehl, bleibt sie erhalten und die neue wird entfernt.
- **Sortierung (F-12):** Kategorien und Bewertungsdimensionen kommen alphabetisch. Der mögliche 500 unter MySQL/Postgres ist damit beseitigt (dort nicht ausgeführt).
- **Löschen von Kategorien und Zutaten (F-07, F-65):** Der Server verlangt eine Bestätigung und nennt vorher die Zahl der betroffenen Rezepte bzw. mitgelöschten Alternativen. Die Oberfläche zeigt dafür einen Bestätigungsbereich im Fenster. Fehler erscheinen sichtbar statt nur in der Konsole. Beim Umbenennen steht der Hinweis, dass die Änderung für alle Rezepte gilt.
- **Entfernen aus Sammlungen (F-39, Teil):** Ein Klick auf die Karte löscht nichts mehr. Jede Karte hat im Entfernen-Modus einen eigenen Button mit Rückfrage, per Tastatur bedienbar.

## 4. Was Sie wissen sollten

1. **Bereits zerfallene Schritte bleiben zerfallen.** Rezepte, deren mehrzeilige Schritte früher in mehrere Schritte zerlegt wurden, werden nicht automatisch repariert. Sie lassen sich im Formular von Hand wieder zusammenführen.
2. **Kleine sichtbare Änderung bei bestehenden Rezepten:** Nummerierte Notizzeilen zeigen jetzt ihre Nummer („1. Tipp“ statt „Tipp“).
3. **Restrisiko beim Altbestand (ADR-001):** Zeilen, die mit genau vier Leerzeichen beginnen, gelten als Fortsetzung des vorherigen Schritts. Über die App konnte das bisher nicht entstehen; bei von Hand in die Datenbank eingespielten Rezepten wäre es denkbar.
4. **Reihenfolge der Bewertungsdimensionen im Formular hat sich geändert** (alphabetisch statt Einfügereihenfolge).
5. **Verwaiste Bilddateien aus der Vergangenheit** werden nicht nachträglich aufgeräumt; die Korrektur wirkt ab jetzt.
6. **Im Zwischenstand kaputt, im Endstand behoben:** Zwischen dem Merge von Backend und Frontend schlug das Löschen von Kategorien in der Oberfläche fehl. Der Frontend-Agent hat außerdem einen eigenen Fehler beim Entfernen aus Sammlungen gefunden und behoben (DELETE per axios endete nach dem Redirect in 405; jetzt über Inertia).
7. **Toter Code entstanden:** Die Endpunkte `ingredientAlternatives.index/store/destroy` und `RecipeController::create()` nutzt niemand mehr. Sie sind nicht gelöscht.
8. **Englische Servertexte:** Validierungsmeldungen wie „The name has already been taken.“ werden jetzt sichtbar angezeigt, sind aber noch englisch (Slice 3).

## 5. Arbeitsweise

- Lead: `claude-fable-5-2`. Zwei Spezialisten nacheinander in isolierten Worktrees (Backend, dann Frontend auf dessen gemergtem Stand), weil die Bestätigungsdialoge den neuen Server-Vertrag brauchen. Der Backend-Agent lief laut API-Meldung mit `claude-opus-5-5`; für den Frontend-Agenten ist nur der angeforderte Alias `opus` belegt.
- Der Backend-Agent wurde einmal vom Nutzungslimit gestoppt und danach fortgesetzt.
- Eigener Ablauffehler: Die ADRs lagen beim Start des Backend-Agenten noch uncommittet im Hauptverzeichnis. Ich habe sie nachcommittet und dem Agenten den Commit nachgereicht, bevor er Code schrieb.
- Abweichung von der Dateizuständigkeit: Der Backend-Agent hat in `tests/Feature/Authorization/RouteRoleMatrixTest.php` eine Zeile ergänzt (`confirmed` für die beiden Lösch-Routen) – im Auftrag ausdrücklich erlaubt.
- Kein zusätzlicher Review-Agent. Geprüft: beide PHP-Suiten und die komplette Browser-Suite auf dem integrierten Endstand im Hauptverzeichnis.

## 6. Geänderter Anwendungscode

14 Dateien, +1301 / −667 Zeilen gegenüber `cab0de4`. Ein Teil davon ist reine Formatierung: Prettier hat die angefassten Vue-Dateien vollständig formatiert, und in `RecipeController::store()` ist der Rumpf wegen eines neuen `try/catch` eingerückt.

- `RecipeController.php` (gemeinsame Methode für Zutaten und Alternativen, ein Parser für Schritte, Datei-Aufräumen)
- `CategoryController.php`, `IngredientController.php` (Bestätigung, Zählwerte)
- `TikTokRecipeImporter.php` (eine Zeile: `source` entfernt), `routes/web.php` (Sortierung)
- `RecipeForm.vue`, `TagEditModal.vue`, `IngredientEditModal.vue`, `IngredientAlternativesModal.vue`
- `recipes/Show.vue`, `recipes/Create.vue`, `recipes/Edit.vue`, `collections/Show.vue`
- `rezeptly/app.css` (nur neue Regeln)

Screenshots: 20 von 110 Baselines erneuert – Rezept anlegen und bearbeiten (Feld „Quelle“ weg, neue Sortierung), Rezeptliste und Home (neuer Test-Tag im Seeder). Alle anderen sind unverändert.

## 7. Wichtigste Befehle (Lead)

| Befehl | Exit |
|---|---|
| `git switch -c fix/slice-2-datenverlust` | 0 |
| Merge Backend-Branch, `php artisan test` | 0 (465) |
| `php artisan test --testsuite=KnownDefects` | 1 (7 rot, beabsichtigt) |
| Merge Frontend-Branch, `npm run build` | 0 |
| `php artisan test` (final) | 0 (465) |
| `E2E_PORT=8133 npx playwright test` (final, integriert) | 0 (164) |

## 8. Offen nach Slice 2

**Rot in `KnownDefects` (7 Tests):** F-08 (3), F-17, F-20 (2), F-23.

**Erwartete Defekte im Browser (15):** F-05, F-06, F-17, F-20, F-21, F-29, F-30, F-31, F-32, F-38, F-42.

**Unverändert offen:** VPS-Prüfung (PHP-Version, Schema, Header), CI-Lauf auf GitHub, Lauf gegen MySQL/Postgres.

## 9. Rollback

Branch nicht mergen bzw. verwerfen; die Vorgänger-Branches sind unverändert. Rezepte, die inzwischen mit Fortsetzungszeilen gespeichert wurden, erscheinen nach einem Rollback wieder als getrennte Schritte; Inhalte gehen nicht verloren. Bereits gelöschte Bilddateien kommen nicht zurück.

## 10. Empfehlung

**Slice 3 – Navigation, mobile Darstellung, Formulare.** Dafür nötig:
- E1: Ziel nach Login und Registrierung (`/home` empfohlen; der Code setzt heute bewusst `/`)
- E4: 2FA-Seite wiederherstellen und die Darstellungs-Seite deaktivieren (Empfehlung)
- E8: Landing-Text zu „Bewertungen und Notizen“ anpassen (Empfehlung)

Vor einem Deployment weiterhin sinnvoll: die lesende VPS-Prüfung aus `SLICE-1-BERICHT.md`, Abschnitt 10.

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

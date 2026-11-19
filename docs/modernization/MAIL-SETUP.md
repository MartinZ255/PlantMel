# Mailversand und E-Mail-Verifizierung – Betriebsanleitung

Stand: 2026-11-14 · Slice 4 · Befunde F-08 (Verifizierung), F-47/F-10 (Konfiguration, Reset-Links im Log)

> **Jeder Schritt auf dem Server (Konfiguration ändern, Command ausführen, Cache neu bauen) braucht eine gesonderte, ausdrückliche Freigabe für genau diese Aktion.** Diese Anleitung ist keine Freigabe.

## Ausgangslage

- PlantMel verschickt Mails für „Passwort vergessen“ und – sobald eingeschaltet – für die E-Mail-Verifizierung.
- Die Verifizierung ist gebaut, aber per Schalter **aus** (`REQUIRE_EMAIL_VERIFICATION=false`, Default). Bei AUS verhält sich die App wie vor Slice 4: keine Verifizierungsmail, keine Sperre, kein Hinweis im Profil.
- Laut DNS ist `mail.plantmel.de` MX der Domain. Ob dort ein Mailserver läuft, ist **ungeprüft**.
- Die Mails werden **synchron** im Request verschickt (keine Queue). Ist der Versand bei eingeschalteter Verifizierung kaputt, schlagen Registrierung und E-Mail-Änderung mit einem Fehler fehl. Deshalb erst einschalten, wenn der Versand nachweislich funktioniert.

## `.env`-Schlüssel für SMTP

Nur Namen und Bedeutung – Werte gehören ausschließlich in die `.env` auf dem Server und nie ins Repository.

| Schlüssel | Bedeutung |
|---|---|
| `MAIL_MAILER` | Versandart. Für SMTP `smtp`. `log` schreibt Mails nur ins Log (siehe Warnung unten). |
| `MAIL_HOST` | Hostname des SMTP-Servers des gewählten Anbieters. |
| `MAIL_PORT` | Port des SMTP-Servers, üblich 587 (STARTTLS) oder 465 (TLS von Beginn an). |
| `MAIL_USERNAME` | Anmeldename am SMTP-Server (oft die Absenderadresse). |
| `MAIL_PASSWORD` | Passwort bzw. App-/SMTP-Kennwort. Geheim. |
| `MAIL_SCHEME` | Verschlüsselung: `smtps` für Port 465; leer bzw. `smtp` für Port 587 (STARTTLS wird automatisch genutzt, wenn der Server es anbietet). Der alte Schlüssel `MAIL_ENCRYPTION` wird von `config/mail.php` **nicht** mehr gelesen. |
| `MAIL_FROM_ADDRESS` | Absenderadresse, z. B. eine Adresse unter `plantmel.de`. Muss zum SMTP-Konto bzw. zu SPF/DKIM passen. |
| `MAIL_FROM_NAME` | Angezeigter Absendername (Default in `.env.example`: `${APP_NAME}`). |
| `REQUIRE_EMAIL_VERIFICATION` | `false` (Default) oder `true`. Schaltet die Verifizierungspflicht ein. |

Optional: `MAIL_EHLO_DOMAIN` (Name, mit dem sich die App beim SMTP-Server meldet; Default ist der Host aus `APP_URL`).

## Drei kostenlose Wege

1. **Postfach beim Domain-Anbieter.** Viele Domain- oder Hosting-Pakete enthalten Postfächer mit SMTP-Zugang. Am einfachsten: Zugangsdaten des Postfachs eintragen. SPF/DKIM richtet der Anbieter meist selbst ein; der MX-Eintrag muss dann ggf. auf den Anbieter zeigen statt auf `mail.plantmel.de`.
2. **Eigener Mailserver auf `mail.plantmel.de` (VPS).** Kostenlos, aber aufwendig. Ohne folgende Voraussetzungen landen Mails im Spam oder werden abgewiesen:
   - **SPF**-Eintrag (TXT) der Domain erlaubt die VPS-IP als Absender,
   - **DKIM**-Signatur der ausgehenden Mails mit veröffentlichtem Schlüssel im DNS,
   - **DMARC**-Eintrag (TXT `_dmarc`),
   - **Reverse-DNS (PTR)** der VPS-IP zeigt auf `mail.plantmel.de` (beim VPS-Anbieter einzustellen),
   - ausgehender Port 25 ist beim VPS-Anbieter nicht gesperrt; Wartung, Updates und Spam-Schutz liegen beim Betreiber.
3. **Versanddienst mit Gratis-Kontingent.** Mehrere Transaktionsmail-Dienste bieten ein kostenloses Kontingent mit SMTP-Zugang. Die Domain wird dort per DNS-Einträgen (SPF/DKIM) bestätigt. Kontingent und Bedingungen vor der Wahl beim Anbieter prüfen; Datenschutz: der Dienst verarbeitet Empfängeradressen → in der Datenschutzerklärung nennen (F-10).

## Versand testen (ohne `tinker`)

1. SMTP-Schlüssel setzen, `php artisan config:cache`.
2. Im Browser auf `/forgot-password` („Passwort vergessen“) die Adresse eines eigenen Testkontos eingeben.
3. Prüfen: Mail kommt an (auch Spam-Ordner), Absender und Link stimmen, der Link öffnet die Seite „Passwort zurücksetzen“.
4. Bei Fehlern: `storage/logs/laravel.log` lesen (keine Inhalte weitergeben, die personenbezogene Daten enthalten).

## Reihenfolge beim Einschalten der Verifizierung

1. **Backup** der Datenbank anlegen und prüfen, dass es sich zurückspielen lässt.
2. `php artisan users:mark-verified --dry-run` – zeigt nur die Zahl der Konten ohne Verifizierung, ändert nichts.
3. `php artisan users:mark-verified` – setzt `email_verified_at` für alle diese Konten auf „jetzt“. **Das ist eine Datenänderung in der Produktion** (ein `UPDATE` auf `users`, keine Rückfrage, keine Mails). Ohne diesen Schritt würden alle Bestandskonten nach dem Einschalten ausgesperrt, bis sie ihre Adresse bestätigen.
4. In der `.env` `REQUIRE_EMAIL_VERIFICATION=true` setzen.
5. `php artisan config:cache`.
6. Kontrolle: neues Testkonto registrieren → Verifizierungsmail kommt an, `/home` leitet bis zur Bestätigung auf „E-Mail bestätigen“ um, nach Klick auf den Link ist `/home` erreichbar.

**Zurück (Rollback):** `REQUIRE_EMAIL_VERIFICATION=false`, `php artisan config:cache`. Die in Schritt 3 gesetzten Zeitstempel bleiben stehen und schaden nicht.

## Warnung: `MAIL_MAILER=log` in Produktion

`.env.example` setzt `MAIL_MAILER=log`. Steht das in der Produktion, werden Mails nicht verschickt, sondern **mit vollständigem Inhalt ins Log geschrieben** – darunter Links zum Zurücksetzen von Passwörtern und Verifizierungslinks. Wer das Log lesen kann, kann damit fremde Konten übernehmen (F-47). In Produktion daher entweder einen echten Mailer konfigurieren oder „Passwort vergessen“ als nicht funktionsfähig betrachten und Log-Zugriff streng begrenzen.

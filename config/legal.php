<?php

/*
|--------------------------------------------------------------------------
| Angaben fuer Impressum und Datenschutzerklaerung (Slice 4, F-10)
|--------------------------------------------------------------------------
|
| VORLAGE – keine Rechtsberatung. Alle Werte in eckigen Klammern sind
| Platzhalter und muessen vor dem Release vom Betreiber ersetzt werden,
| entweder direkt hier (Standardwert ueberschreiben) oder per `.env`
| (Schluessel LEGAL_*). Liste aller Schluessel und Pruefstellen:
| docs/modernization/RECHTSTEXTE.md
|
| Solange ein Pflichtwert leer ist oder "[" enthaelt, zeigen /impressum und
| /datenschutz den Hinweis "Entwurf – Angaben des Betreibers fehlen noch"
| (Flag `filled`, wird in routes/web.php aus diesen Werten berechnet, damit
| es auch zur Laufzeit gesetzte Werte beruecksichtigt).
|
| Optionale Werte (null = wird nicht angezeigt): operator_phone,
| content_responsible_name, content_responsible_address.
|
*/

return [

    // Anbieter nach § 5 DDG / § 18 Abs. 1 MStV
    'operator_name' => env('LEGAL_OPERATOR_NAME', '[VOR- UND NACHNAME]'),
    'operator_street' => env('LEGAL_OPERATOR_STREET', '[STRASSE UND HAUSNUMMER]'),
    'operator_postal_city' => env('LEGAL_OPERATOR_POSTAL_CITY', '[PLZ UND ORT]'),
    'operator_country' => env('LEGAL_OPERATOR_COUNTRY', 'Deutschland'),
    'operator_email' => env('LEGAL_OPERATOR_EMAIL', '[E-MAIL-ADRESSE]'),
    'operator_phone' => env('LEGAL_OPERATOR_PHONE'),

    // Verantwortlich fuer den Inhalt nach § 18 Abs. 2 MStV – nur setzen, wenn
    // abweichend vom Anbieter; sonst wird der Anbieter genannt.
    'content_responsible_name' => env('LEGAL_CONTENT_RESPONSIBLE_NAME'),
    'content_responsible_address' => env('LEGAL_CONTENT_RESPONSIBLE_ADDRESS'),

    // Hosting (Datenschutzerklaerung: Server und Logfiles)
    'hosting_provider_name' => env('LEGAL_HOSTING_NAME', '[NAME DES HOSTING-ANBIETERS]'),
    'hosting_provider_address' => env('LEGAL_HOSTING_ADDRESS', '[ANSCHRIFT DES HOSTING-ANBIETERS]'),
    'server_log_retention' => env('LEGAL_SERVER_LOG_RETENTION', '[SPEICHERDAUER DER SERVER-LOGFILES, z. B. 14 Tage]'),

    // E-Mail-Versand (Passwort zuruecksetzen)
    'mail_provider' => env('LEGAL_MAIL_PROVIDER', '[MAILANBIETER BZW. EIGENER MAILSERVER MIT ANSCHRIFT]'),

    // Stand beider Texte (z. B. "14.11.2026")
    'last_updated' => env('LEGAL_LAST_UPDATED', '[TT.MM.JJJJ]'),

];

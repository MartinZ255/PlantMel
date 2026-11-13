# Security-Header (Befund F-47) – Betriebsnotiz

Stand: Slice 1b, 2026-11-13. Entscheidung E11: Die Header setzt Laravel, nicht Nginx.

Umsetzung: `app/Http/Middleware/SecurityHeaders.php`, global registriert in `bootstrap/app.php`
(`$middleware->append(...)`). Damit tragen alle Antworten die Header: HTML, Inertia-JSON,
Redirects, 403/404/500-Fehlerseiten, JSON-Endpunkte, `/up`, Dateiauslieferung über Laravel.
Regressionstest: `tests/Feature/Security/SecurityHeadersTest.php`.

## Gesetzte Header

| Header | Wert | Warum |
|---|---|---|
| `X-Content-Type-Options` | `nosniff` | kein MIME-Sniffing (wichtig bei Uploads unter `/storage`) |
| `X-Frame-Options` | `DENY` | Clickjacking-Schutz für ältere Browser; die App bettet sich nirgends selbst ein (kein `<iframe>` in `resources/`) |
| `Content-Security-Policy` (erzwungen) | `frame-ancestors 'none'` | moderner Clickjacking-Schutz; muss erzwungen sein, weil Browser `frame-ancestors` in Report-Only ignorieren |
| `Content-Security-Policy-Report-Only` | `default-src 'self'; script-src 'self'; style-src 'self' https://fonts.bunny.net; font-src 'self' https://fonts.bunny.net; img-src 'self' data: blob:; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'` | Ziel-Policy, nur beobachtend (siehe unten) |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | fremde Seiten sehen nur den Ursprung, keine Pfade/Query |
| `Permissions-Policy` | `accelerometer=(), camera=(), display-capture=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), midi=(), payment=(), usb=()` | die App nutzt keine dieser Funktionen (Grep in `resources/` ohne Treffer) |
| `Strict-Transport-Security` | `max-age=15552000` (180 Tage) | nur wenn der Request laut Laravel über HTTPS kam (`$request->isSecure()`) |

Regeln der Middleware:

- Ein Header, der schon gesetzt ist (z. B. von einem Controller), wird nicht überschrieben.
- Im Vite-Dev-Modus (`public/hot` existiert) entfällt nur die Report-Only-CSP: Der Dev-Server
  läuft auf einem anderen Origin, nutzt WebSocket (HMR) und injiziert CSS per `<style>` – das
  würde nur Rauschen erzeugen. Gemessen wird gegen gebaute Assets (`npm run build`), wie in Produktion.
- Schalter ohne Env-Variable: Klassenkonstanten `CSP_REPORT_ONLY_ENABLED`, `HSTS_MAX_AGE`,
  `REPORT_ONLY_CSP`, `PERMISSIONS_POLICY_DISABLED`.
- HSTS bewusst **ohne** `includeSubDomains` und **ohne** `preload`: Beides betrifft die ganze Domain
  bzw. ist schwer rückgängig zu machen und ist eine Betreiberentscheidung.

## Report-Only: Bedeutung und gemessene Verstöße

`Content-Security-Policy-Report-Only` blockiert nichts. Der Browser meldet Verstöße nur in der
Konsole (kein `report-uri`, weil es keinen Endpunkt gibt).

Gemessen lokal (Chromium/Playwright, gebaute Assets, E2E-Instanz) auf `/`, `/rezepte`, `/login`
und als Host `/rezepte/erstellen` – auf allen vier Seiten dieselben 4 Verstöße:

| # | Direktive | Quelle | Ursache |
|---|---|---|---|
| 1 | `script-src-elem` | `app.blade.php` Zeile 8 | Inline-Script zur Dark-Mode-Erkennung (enthält `$appearance`, Hash daher nicht stabil) |
| 2 | `style-src-elem` | `app.blade.php` Zeile 23 | Inline-`<style>` für die Hintergrundfarbe (statisch) |
| 3 | `script-src-elem` | Ausgabe von `@routes` (Ziggy) | Inline-Script mit der Routenliste |
| 4 | `style-src-elem` | `build/assets/app-*.js` | Inertia-Fortschrittsbalken (`@inertiajs/core` 2.2.7, `injectCSS`) fügt ein `<style>` ein |

Die Schrift von `fonts.bunny.net` ist in der Policy erlaubt (heutiger Bedarf); sie wurde im Test
nicht geladen (fremde Hosts blockiert). Bilder unter `/storage` erzeugten keinen Verstoß.

## Schritte zum Scharfschalten

1. Nonce einführen: in einer Middleware `Vite::useCspNonce()` aufrufen und den Nonce in die Policy
   schreiben (`script-src 'self' 'nonce-…'`, `style-src 'self' 'nonce-…'`).
2. `resources/views/app.blade.php`: `<script nonce="{{ Vite::cspNonce() }}">`, `<style nonce="…">`,
   `@routes(nonce: Vite::cspNonce())`.
3. Inertia-Fortschrittsbalken: in `resources/js/app.ts` `progress: { includeCSS: false, … }` und die
   NProgress-Styles in die eigene CSS-Datei übernehmen (oder Nonce/Hash, falls die Inertia-Version das unterstützt).
4. F-58: Schrift selbst hosten, danach `https://fonts.bunny.net` aus `style-src`/`font-src` entfernen.
5. Einige Tage Report-Only in Staging/Produktion beobachten (Konsole, ggf. später `report-to`-Endpunkt).
6. Erst dann die Policy als `Content-Security-Policy` erzwingen (inkl. `frame-ancestors 'none'`).

## Zusammenspiel mit Nginx/HTTPS auf dem VPS (ungeprüft – Prüfliste für die Serverprüfung mit Freigabe)

- [ ] Setzt Nginx selbst Security-Header (`add_header`)? Wenn ja: **Laravel ist zuständig**, die
      Nginx-Zeilen entfernen. Sonst erscheinen Header doppelt (die Middleware sieht Nginx-Header nicht)
      und bei zwei CSP-Headern gelten beide gleichzeitig.
- [ ] Ausnahme: Dateien, die Nginx direkt ausliefert (`/build/*`, `/storage/*`, `favicon`), laufen nicht
      durch Laravel. Für diese reicht `X-Content-Type-Options: nosniff` in Nginx (nur diese Location-Blöcke).
- [ ] Erkennt Laravel HTTPS? Nur dann wird HSTS gesendet. Voraussetzung: `fastcgi_param HTTPS on;`
      (PHP-FPM direkt) bzw. bei vorgeschaltetem Proxy `trustProxies` in `bootstrap/app.php`
      (heute nicht konfiguriert, siehe F-47) und `X-Forwarded-Proto`.
- [ ] Leitet Nginx HTTP auf HTTPS um? Sonst greift HSTS erst nach dem ersten HTTPS-Besuch.
- [ ] Gilt das Zertifikat für alle genutzten Hostnamen (mit/ohne `www`)?
- [ ] `includeSubDomains`/`preload` erst nach Prüfung aller Subdomains entscheiden.
- [ ] `SESSION_SECURE_COOKIE=true` in der Produktions-`.env` (F-47, nicht Teil dieser Änderung).

## Lesend prüfen nach einem Deployment

```bash
curl -sI https://plantmel.de/ | grep -iE 'x-content-type|x-frame|referrer-policy|permissions-policy|content-security|strict-transport'
curl -sI https://plantmel.de/gibt-es-nicht | grep -iE 'x-frame|content-security'
curl -sI http://plantmel.de/          # erwartet: Redirect auf HTTPS, kein HSTS über HTTP
```

Erwartet: jeder Header genau einmal mit den Werten aus der Tabelle oben. Nur lesende Anfragen,
nur nach Freigabe.

# Bekannte Defekte (`tests/KnownDefects`)

Zweck: bekannte, dokumentierte Defekte reproduzierbar machen, ohne die reguläre Suite rot zu färben.

- Jeder Test prüft das **korrekte Sollverhalten** und ist deshalb so lange **rot**, bis der Defekt behoben ist.
- Kein `markTestSkipped()` und kein `markTestIncomplete()`: Ein Defekt soll sichtbar scheitern, sobald man die Suite ausführt.
- Jede Testklasse nennt die Befund-ID aus `docs/modernization/FINDINGS.md` im Docblock und trägt `#[Group('known-defect')]` sowie eine Gruppe mit der Befund-ID (z. B. `#[Group('F-16')]`).
- Die Suite läuft **nicht** im Default (`defaultTestSuite="Unit,Feature"` in `phpunit.xml`).

## Ausführen

```bash
php artisan test --testsuite=KnownDefects
php artisan test --testsuite=KnownDefects --group=F-16
```

## Leere Suite (Stand Gate D3, 2026-11-20)

Die Suite ist derzeit **leer**: Alle früher hier erfassten Defekte sind behoben und als Regressionstests in die Default-Suiten verschoben oder mit einer Entscheidung entfallen (z. B. 2FA, F-20); letzter Umzug: Commit 78feb96 (F-23). Das Verzeichnis und der Eintrag in `phpunit.xml` bleiben bestehen, damit künftige Soll-Tests ohne Konfigurationsänderung hier abgelegt werden können.

PHPUnit meldet eine leere Suite mit „No tests executed!“ und **Exit 0** (gemessen mit PHPUnit 11.5; `php artisan test` meldet „No tests found.“, ebenfalls Exit 0). Damit das nicht still als Erfolg durchgeht, regelt die CI (`.github/workflows/tests.yml`, Job `known-defects`) den Fall ausdrücklich:

- Schritt „Check Suite“ zählt die Dateien `tests/KnownDefects/**/*Test.php`.
- **0 Dateien:** Hinweis-Annotation und Eintrag in der Job-Zusammenfassung („Suite leer, Lauf bewusst übersprungen“); die übrigen Schritte entfallen.
- **mindestens 1 Datei:** Die Suite läuft mit `--fail-on-empty-test-suite` (Dateien ohne Testmethoden fallen dann auf). Der Job blockiert weiterhin nicht (`continue-on-error: true`), weil die Tests hier absichtlich rot sind.

Lokal entsprechend: `php artisan test --testsuite=KnownDefects --fail-on-empty-test-suite` (Exit 1 bei leerer Suite).

## Wenn ein Defekt behoben ist

Der zugehörige Test wird grün. Dann den Test in `tests/Feature/` (oder `tests/Unit/`) verschieben, Namespace anpassen und die Gruppe `known-defect` entfernen, damit er künftig als Regressionstest im Default mitläuft.

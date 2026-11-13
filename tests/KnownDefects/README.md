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

## Wenn ein Defekt behoben ist

Der zugehörige Test wird grün. Dann den Test in `tests/Feature/` (oder `tests/Unit/`) verschieben, Namespace anpassen und die Gruppe `known-defect` entfernen, damit er künftig als Regressionstest im Default mitläuft.

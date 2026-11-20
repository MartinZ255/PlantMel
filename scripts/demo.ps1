<#
  Startet PlantMel lokal mit synthetischen Testdaten - ohne die .env zu aendern.

  Aufruf im Projektordner (PowerShell):
      .\scripts\demo.ps1            # startet, legt die Testdatenbank bei Bedarf an
      .\scripts\demo.ps1 -Fresh     # setzt die Testdatenbank vorher zurueck
      .\scripts\demo.ps1 -WithWorker  # startet zusaetzlich den Hintergrunddienst fuer den TikTok-Import

  Danach im Browser: http://127.0.0.1:8010
  Testkonten (Passwort jeweils: E2e-Passwort-2026!):
      host@example.test   (darf Rezepte anlegen und bearbeiten)
      user@example.test   (normaler Nutzer)

  Alle Einstellungen kommen aus Umgebungsvariablen dieses Fensters und haben
  Vorrang vor der .env. Verwendet wird nur database/e2e.sqlite (synthetisch).
  Es werden keine E-Mails versendet (sie landen im Log).

  Ohne -WithWorker laeuft kein Hintergrunddienst: Ein TikTok-Import bleibt dann im
  Zustand "wartet auf Verarbeitung" stehen. Mit -WithWorker wird der Import WIRKLICH
  ausgefuehrt: yt-dlp laedt das Video von TikTok und der Text bzw. das Video geht an die
  Google-Gemini-API. Dafuer muss GEMINI_API_KEY gesetzt sein - entweder in der .env oder
  vorher in diesem Fenster per  $env:GEMINI_API_KEY = '...'. Das verbraucht Ihr Gemini-
  Kontingent.
#>
param(
    [switch]$Fresh,
    [switch]$WithWorker,
    [int]$Port = 8010
)

$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)

$db = Join-Path (Get-Location) 'database\e2e.sqlite'

$env:APP_ENV = 'local'
$env:APP_DEBUG = 'true'
# Fester, NICHT geheimer Schluessel nur fuer die lokale Testinstanz (wie in tests/e2e/support/env.ts).
$env:APP_KEY = 'base64:evN0XK+wwv/q3ZFyU3Ekx8Hi3OQtaVvoQNy735gHvnQ='
$env:APP_URL = "http://127.0.0.1:$Port"
$env:APP_LOCALE = 'de'
$env:APP_FALLBACK_LOCALE = 'en'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = $db
$env:SESSION_DRIVER = 'file'
$env:CACHE_STORE = 'file'
$env:QUEUE_CONNECTION = 'database'
$env:MAIL_MAILER = 'log'
$env:FILESYSTEM_DISK = 'local'

if ($Fresh -and (Test-Path $db)) {
    Remove-Item $db -Confirm:$false
}

if (-not (Test-Path $db)) {
    Write-Host 'Lege Testdatenbank an ...'
    New-Item -ItemType File $db | Out-Null
    php artisan migrate --force
    if ($LASTEXITCODE -ne 0) { throw 'Migration der Testdatenbank fehlgeschlagen.' }
    php artisan db:seed --class=E2eSeeder --force
    if ($LASTEXITCODE -ne 0) { throw 'Seeder fehlgeschlagen.' }
}

if (-not (Test-Path 'public\storage')) {
    php artisan storage:link
}

if (-not (Test-Path 'public\build\manifest.json')) {
    Write-Host 'Baue Frontend ...'
    npm run build
    if ($LASTEXITCODE -ne 0) { throw 'Build fehlgeschlagen.' }
}

Write-Host ''
Write-Host "PlantMel laeuft auf http://127.0.0.1:$Port  (Beenden: Strg+C)"
Write-Host 'Login: host@example.test oder user@example.test, Passwort: E2e-Passwort-2026!'
Write-Host ''

$worker = $null
if ($WithWorker) {
    Write-Host 'Starte Hintergrunddienst fuer den Import (eigenes Fenster) ...'
    # Das neue Fenster erbt die Umgebungsvariablen dieses Fensters (Testdatenbank usw.).
    $worker = Start-Process -FilePath 'php' -ArgumentList 'artisan', 'queue:work', '--tries=1', '--timeout=620' -PassThru
}

try {
    php artisan serve --no-reload --host=127.0.0.1 --port=$Port
}
finally {
    if ($worker -and -not $worker.HasExited) {
        Write-Host 'Beende Hintergrunddienst ...'
        Stop-Process -Id $worker.Id -Confirm:$false
    }
}

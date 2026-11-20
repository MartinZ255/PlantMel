# PlantMel Multi-Agent Starterpaket

## 1\. Installation (PowerShell im PlantMel-Repo)

ZIP herunterladen und **nur** die Ordner `.claude`, `docs/modernization` sowie `CLAUDE.md` in das Repository kopieren. Die bestehenden Projektdateien bleiben erhalten. Prüfe vorher, ob `CLAUDE.md` oder `.claude` bereits existieren, und sichere/merge diese gegebenenfalls statt sie zu überschreiben.

Beispiel, nachdem das ZIP in `Downloads` liegt:

```powershell
cd C:\\Users\\PC-OEM-41468\\PlantMel
Get-ChildItem -Force CLAUDE.md,.claude -ErrorAction SilentlyContinue
Expand-Archive "$HOME\\Downloads\\PlantMel-Claude-Starter.zip" -DestinationPath "$env:TEMP\\plantmel-claude-starter" -Force
Copy-Item "$env:TEMP\\plantmel-claude-starter\\CLAUDE.md" . -ErrorAction Stop
Copy-Item "$env:TEMP\\plantmel-claude-starter\\.claude" . -Recurse -Force
Copy-Item "$env:TEMP\\plantmel-claude-starter\\docs\\modernization" .\\docs -Recurse -Force

git status --short
```

**Achtung:** Die Beispiel-Kopierbefehle sind nur für den Fall gedacht, dass die Zielpfade noch nicht existieren. Andernfalls Dateien zuerst vergleichen und selektiv übernehmen.

## 2\. Claude Code starten

```powershell
claude --version
claude
```

In Claude Code: `/plantmel-audit` (wenn Projekt-Slash-Commands von der installierten Version erkannt werden). Andernfalls folgenden Prompt direkt eingeben:

> Lies CLAUDE.md und führe die Anweisungen aus `.claude/commands/plantmel-audit.md` aus. Führe zunächst nur das Audit durch und stoppe vor der Implementierung.

## 3\. Nach dem Audit

Befunde und Roadmap lesen, eine begrenzte Aufgabe freigeben. Dann `/plantmel-implement` oder die entsprechende Prompt-Datei verwenden. `/plantmel-review` dient als unabhängiges Review-Gate.

## 4\. Modellwahl

Die vom Nutzer gewünschten Bezeichnungen **Fable 5.2** (Orchestrator) und **Opus 5.5** (Subagenten) sind nicht als validierte Claude-Code-Modell-IDs eingetragen. Prüfe in deiner Installation `/model` und die unterstützte Agent-Frontmatter. Erst danach explizite `model:`-Einträge setzen. Ohne diese Einstellung wählen die Agenten das Modell gemäß Claude-Code-Konfiguration/Vererbung; die gewünschte Modelltrennung ist also noch **nicht garantiert**.

## 5\. Git

```powershell
git switch -c chore/claude-multiagent-setup
git add CLAUDE.md .claude docs/modernization
git commit -m "Add Claude multi-agent modernization workflow"
```

Vor dem Commit `git diff --cached` prüfen. Kein Push oder Deployment automatisch.


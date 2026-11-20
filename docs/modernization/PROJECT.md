# PlantMel – Projektauftrag

**Ziel:** Bestehende Website plantmel.de umfassend verbessern, insbesondere Darstellungsprobleme, Responsive Design, Bedienbarkeit und Funktionsstabilität. Visuelles Redesign ist erlaubt; neue Features nur nach Nutzenbewertung.

**Bekannte lokale Struktur:** Laravel-typische Ordner `app`, `routes`, `database`, `resources/js`, `resources/css`, `tests`; Bereiche `recipes`, `collections`, `widgets`, `auth`, `settings`. Konkrete Versionen, Implementierungen und tatsächliche Fehler müssen im Audit verifiziert werden.

**Bekannte VPS-Angaben (vom Nutzer, nicht unabhängig verifiziert):** Ubuntu 24.04.5, Nginx, PHP 8.4.27, Node, npm, Git; Docker und PM2 wurden nicht gefunden. Produktionskonfiguration unbekannt.

**Erfolgskriterien:** Kernnutzerreisen funktionieren; keine bekannten P0/P1-Regressionen; mobile/desktop Layouts geprüft; Tastaturbedienung und Formularfeedback verbessert; Tests/Build bestehen; Staging abgenommen; Rollback dokumentiert.

**Freigabegrenzen:** Audit autonom und lesend; Codeänderungen nach Roadmap-Freigabe; jede produktive Änderung nur nach separater ausdrücklicher Freigabe.

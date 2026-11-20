# PlantMel Audit

Act as the lead orchestrator. Follow `CLAUDE.md`. First inspect git status and the actual installed Claude model identifiers; ask before substituting the requested lead/subagent model versions. Read `docs/architecture-inventory.md` and project configuration. Delegate **read-only** independent audits to architecture, frontend, ux, backend, qa and security agents when agent tooling is available; otherwise perform them sequentially and disclose the limitation. Do not alter application code, do not deploy, do not access production or secrets.

Consolidate findings into `docs/modernization/FINDINGS.md`, `FEATURE-MATRIX.md`, and a prioritized proposal in `ROADMAP.md`. Editing only these audit documentation files is allowed in this phase. Distinguish verified defects from unverified risks. Include test gaps, responsive breakpoints, route coverage and recommended first implementation slice. STOP and ask for roadmap approval.

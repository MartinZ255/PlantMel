# PlantMel – Claude Code operating contract

## Mission
Modernize the existing PlantMel application without losing working functionality or user data. Prioritize broken layouts, responsive behavior, usability, accessibility, correctness, security, and regression protection. Feature additions are optional and require justification.

## First actions in every session
1. Read `docs/architecture-inventory.md` if present, `composer.json`, `package.json`, `routes/`, `resources/js/`, `resources/css/`, `app/`, `database/migrations/`, and `tests/` as relevant.
2. Inspect `git status --short`, current branch, available toolchain, and existing CI before changing files.
3. Verify actual framework versions, frontend stack, testing tools and Claude model IDs; never infer versions from folder names.
4. Read `docs/modernization/PROJECT.md`, `docs/modernization/ROADMAP.md`, and `docs/modernization/DECISIONS.md`.

## Agent policy
- Lead coordinator plans and integrates; specialists inspect or implement bounded tasks.
- Requested model mapping: lead 'Fable 5.2' and specialists 'Opus 5.5' (user-supplied labels; verify availability and exact identifiers with Claude Code). If unavailable, ask before substitution; never claim a model was used without confirmation.
- Delegate independent, read-only audits in parallel. For implementation, assign exclusive file ownership and isolated git worktrees when supported.
- Agent outputs must contain evidence (file paths and line numbers when possible), severity, impact, reproduction steps, proposed fix, and tests.
- Never run destructive commands or launch deployment automatically.

## Hard safety rules
- No production SSH commands, deployment, migrations, data modification, service restarts or secrets access without explicit human approval for that exact action.
- Do not read, print, copy, commit or expose `.env`, credentials, tokens, private keys, personal user data or database dumps.
- Never use production databases for development or testing. Use synthetic fixtures and a dedicated test database.
- No `migrate:fresh`, `db:wipe`, destructive SQL, `git reset --hard`, force-push, or unreviewed mass deletion.
- Do not replace the tech stack, auth system, routing or database architecture without a written ADR and approval.
- Keep changes small, reviewable, reversible, and compatible with existing workflows.
- If working tree is dirty, preserve existing changes and request guidance before touching overlapping files.

## Required workflow
1. **Audit**: inventory features, routes, user journeys, UI defects, accessibility, dependencies, security, tests, hosting assumptions. Document verified facts separately from hypotheses.
2. **Baseline**: capture current behavior with automated tests and screenshots where browser tooling is available.
3. **Plan**: prioritize P0/P1/P2/P3 with scope, affected files, risks, acceptance criteria, dependencies.
4. **Implement**: small feature branches/worktrees; specialists own non-overlapping files.
5. **Validate**: run relevant tests, lint, typecheck, build, and browser smoke tests if configured; report skipped checks.
6. **Review**: QA and security review; orchestrator integrates only after passing gates.
7. **Release**: staging, backup verification, human acceptance, then separate explicit production approval.

## Output style
Write reports in German. Do not invent findings, test results, performance numbers or screenshots. Record commands executed and their exit status. At each gate, summarize completed work, remaining risk, and next recommended task.

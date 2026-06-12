---
name: changelog
description: Update CHANGELOG.md with the current branch's changes following Keep a Changelog format.
allowed-tools: Bash(git log:*), Bash(git diff:*), Read, Edit, Glob, Grep
---

# Update Changelog

Update `CHANGELOG.md` with changes from the current feature branch.

## Steps

1. **Gather changes** — Run `git log main..HEAD --oneline` and `git diff main --stat` to understand what changed.

2. **Read existing changelog** — Read `CHANGELOG.md` to understand the current format and entries.

3. **Identify new/modified files** — Categorize changes into:
   - **Services** — `app/Services/`
   - **Controllers** — `app/Http/Controllers/`
   - **Models** — `app/Models/`
   - **Views** — `resources/views/`
   - **Tests** — `tests/`
   - **Migrations** — `database/migrations/`
   - **API Endpoints** — new routes added

4. **Write entry** — Add under the `[Unreleased]` section following the existing format:
   - Use `### Added`, `### Changed`, `### Fixed`, `### Removed` subsections
   - Include a descriptive title and summary paragraph
   - List affected files grouped by type with descriptions
   - List new API endpoints with method and path

5. **Match the existing style** exactly — see how Peak Hours Analysis and Profit Margin Tracking entries are structured in the changelog.

## Rules

- Do NOT modify existing entries
- Add new entries BELOW the `[Unreleased]` header but ABOVE previous entries
- Use present tense and active voice
- Include file counts per category

---
name: lint
description: Run Laravel Pint to fix code style on dirty files. Use after making code changes.
allowed-tools: Bash(vendor/bin/sail bin pint:*)
---

# Lint with Pint

Run Laravel Pint code formatter on changed files.

## Behavior

1. Run `vendor/bin/sail bin pint --dirty` to fix formatting on git-dirty files only.
2. Summarize what files were fixed and what changed.
3. If no dirty files, report that everything is clean.

## Notes

- Never run `--test` mode — just fix directly
- Pint enforces `test_snake_case` method naming in tests
- Run this before committing

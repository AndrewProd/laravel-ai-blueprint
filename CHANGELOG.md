# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.0] - 2026-10-15

First MVP release.

### Added

- Artisan commands: `ai-blueprint:init`, `scan`, `map`, `review`, `task`, `check`, `sync`, `doctor`.
- Static analyzers (no code execution, no database access): Composer, PHP classes (tokenizer-based),
  routes, migrations, tests, frontend stack detection.
- Versioned machine-readable blueprint (`.ai-blueprint/blueprint.json`, schema 1.0) with
  observed / inferred / confirmed / unknown status and source evidence; JSON Schema in
  `resources/schemas/blueprint.schema.json`.
- Generated documentation: architecture overview, modules, coding standards, testing, security,
  specs and ADR indexes.
- Agent adapters: generic `AGENTS.md`, Codex (`AGENTS.md`), Claude Code (`CLAUDE.md`,
  `.claude/rules/`), Cursor (`.cursor/rules/*.mdc`).
- Task briefs (`task.md`, `context.md`, `checklist.md`) with five built-in workflows:
  feature, bugfix, refactor, api-endpoint, integration.
- Rules BP001–BP009 with table/JSON output, `--strict` and git-based `--changed`.
- Manifest-based safe synchronization: manual edits and pre-existing files are never
  overwritten without confirmation or `--force`.
- Security defaults: built-in sensitive-file patterns, excluded dependency directories,
  path validation for all writes, analysis cache without file contents.

[Unreleased]: https://github.com/AndrewProd/laravel-ai-blueprint/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/AndrewProd/laravel-ai-blueprint/releases/tag/v0.1.0

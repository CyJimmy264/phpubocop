# Repository Guidelines

## Project Structure & Module Organization
`PHPuboCop` is a Composer library with a CLI entrypoint.

- `src/` — production code:
  - `Cop/` (rules grouped by namespace: `Layout`, `Lint`, `Metrics`, `Security`, `Style`)
  - `Core/` (runner, offense model, autocorrect flow)
  - `Config/` (config loading/merging)
  - `Formatter/` (text/json output)
  - `Util/` (AST/file discovery helpers)
- `tests/` — PHPUnit tests, usually one test class per cop or core component.
- `bin/phpubocop` — executable used by Composer `bin`.
- `.phpubocop.yml` — repository lint config used for local development.

## Build, Test, and Development Commands
- `composer install` — install dependencies.
- `vendor/bin/phpunit` — run full test suite.
- `vendor/bin/phpunit tests/FileFinderTest.php` — run targeted tests.
- `php bin/phpubocop .` — lint repository with default text output.
- `php bin/phpubocop . --verbose` — show config source and discovery stats.
- `php bin/phpubocop . --autocorrect` — apply safe autocorrections.
- `php bin/phpubocop . --format=json` — machine-readable report.

## Coding Style & Naming Conventions
- PHP 8.1+ with `declare(strict_types=1);` in source files.
- Use spaces for indentation (tabs are flagged by `Layout/IndentationStyle`).
- Class names: `*Cop`, `*Formatter`, etc.; tests: `*Test.php`.

## Engineering Principles
- Avoid WET code; keep shared logic DRY from the start.
- If the same business logic is needed in multiple places (e.g. page render + backend validation), extract it into a shared module/helper immediately.
- If immediate extraction is risky in current scope, explicitly propose a follow-up DRY refactor in the same task.
- During refactoring, group functions with similar signatures, names, and business meaning into cohesive classes (domain-oriented), instead of leaving them as scattered standalone functions.

## Testing Guidelines
- Framework: PHPUnit 11.
- Add/adjust tests for every behavior change, especially false-positive fixes.
- Keep tests deterministic and focused (single behavior per test where practical).
- Run full suite before commit: `vendor/bin/phpunit`.

## Commit & Pull Request Guidelines
- Use concise imperative commit messages (e.g., `Fix false positives in useless assignment`).
- Group related code + tests in one commit.
- PRs should include:
  - what changed and why,
  - risk/compatibility notes,
  - commands run (`phpunit`, linter checks),
  - sample CLI output for formatter/CLI behavior changes.

## Security & Configuration Tips
- Avoid introducing new raw command execution (`exec`, `shell_exec`) unless strictly needed.
- Prefer config-driven behavior via `.phpubocop.yml`; document new keys in `README.md`.
- For performance on large repos, keep `AllCops.UseGitFileList: true`.

# PHPuboCop

PHPuboCop is a RuboCop-inspired linter for PHP projects.

## Goals

- Bring Ruby-style strictness and consistency to PHP code.
- Keep rules composable and easy to extend.
- Work as a normal Composer library + executable binary.

## Installation

```bash
composer require --dev mveynberg/phpubocop
```

## Usage

```bash
vendor/bin/phpubocop
vendor/bin/phpubocop src
vendor/bin/phpubocop src tests
vendor/bin/phpubocop src --format=json
vendor/bin/phpubocop src --config=.phpubocop.yml
```

Exit code:

- `0` if no offenses were found.
- `1` if at least one offense was found.
- Files ignored by the project's `.gitignore` are skipped automatically.

## Configuration

Create `.phpubocop.yml`:

```yaml
AllCops:
  EnabledByDefault: true
  Exclude:
    - vendor/**

Layout/LineLength:
  Enabled: true
  Max: 120

Layout/TrailingWhitespace:
  Enabled: true

Style/DoubleQuotes:
  Enabled: true

Lint/DuplicateArrayKey:
  Enabled: true

Lint/EvalUsage:
  Enabled: true

Lint/UnusedVariable:
  Enabled: true
  IgnorePrefixedUnderscore: true
  IgnoreParameters: true

Metrics/AbcSize:
  Enabled: true
  Max: 17

Metrics/CyclomaticComplexity:
  Enabled: true
  Max: 7

Metrics/PerceivedComplexity:
  Enabled: true
  Max: 8
```

## Included cops (MVP)

- `Layout/TrailingWhitespace`
- `Layout/LineLength`
- `Style/DoubleQuotes`
- `Lint/DuplicateArrayKey`
- `Lint/EvalUsage`
- `Lint/UnusedVariable`
- `Metrics/AbcSize`
- `Metrics/CyclomaticComplexity`
- `Metrics/PerceivedComplexity`

## Architecture

- `CopInterface` defines one rule.
- `Runner` applies enabled cops to discovered PHP files.
- `ConfigLoader` merges defaults with `.phpubocop.yml`.
- Formatters output in text or JSON.

## Roadmap

- Auto-correct support (`--autocorrect`) for safe cops.
- Namespaced cop packs (e.g. `Rails`, `Laravel`, `Doctrine`).
- More style cops that mirror RuboCop semantics where meaningful in PHP.
- Baseline file support for incremental adoption.
- SARIF and GitHub Actions annotations.

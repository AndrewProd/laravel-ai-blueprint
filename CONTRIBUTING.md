# Contributing

Thanks for helping improve Laravel AI Blueprint.

## Setup

```bash
git clone https://github.com/AndrewProd/laravel-ai-blueprint.git
cd laravel-ai-blueprint
composer install
```

## Before opening a pull request

```bash
composer test          # PHPUnit (unit + feature, includes JSON Schema validation)
composer analyse       # PHPStan
composer format:check  # Laravel Pint
```

- Add tests for new behavior. Feature tests run commands against fixture projects in
  `tests/Fixtures` copied to a temporary directory.
- Document new rules (id, what they check, known false positives) in the README.
- Do not introduce network access, code execution of the analyzed project, or destructive
  file operations. Every write must go through `SafeFileWriter`.
- Analyzers must report uncertainty (`inferred`, `partial`, findings) instead of guessing.

## Adding an analyzer

Implement `AndrewProd\AiBlueprint\Contracts\Analyzer`, read files only through
`ProjectContext::$files`, and return components with evidence. Register it in
`Application\Workspace::analyzers()` and add a config toggle.

## Adding a rule

Implement `Contracts\Rule` (and `Contracts\RequiresValidBlueprint` if it needs blueprint data),
use the next free `BPxxx` id, and register it in `Workspace::rules()`. Rules must be
deterministic and should avoid false positives.

## Commit messages and releases

Use clear, imperative commit messages. Releases follow Semantic Versioning; update
`CHANGELOG.md` and `Support\Version::PACKAGE` together.

# Turistické známky

Personal web app for tracking a collection of Czech tourist stamps (turistické známky). Nette 3.2, Latte 3, MySQL, PHP 8.3.

## Commands

- `composer install` – install dependencies (`config.platform.php` pins resolution to PHP 8.3, so it works with a newer local PHP too)
- `composer test` – Nette Tester suite in `tests/` (`*.phpt`)
- `composer phpstan` – static analysis, level 5, config in `phpstan.neon`
- `composer check` – PHPStan + tests; run before every PR
- `docker compose up` – app on http://localhost:8082, MySQL on port 13306 (schema from `.docker/db/init.sql`)

Tests run on the host PHP and need no database. There are no DB/integration tests yet; acceptance criteria that need a DB are verified manually.

## Conventions

- Code, comments, commit messages in English; Conventional Commits referencing the issue, e.g. `fix: return 404 for unknown stamp (#7)`.
- Tabs for indentation, match the style of the surrounding file.
- `phpstan-baseline.neon` holds known pre-existing errors. When you fix one, remove its baseline entry (PHPStan fails on unmatched entries). Never add new entries to hide new errors.
- New tests go to `tests/<same path as app/>/<Class>Test.phpt`.
- `app/config/local.neon` (DB credentials) is not in the repo.
- Issues are tracked on GitHub; tickets ready for implementation carry the `rtm` label.

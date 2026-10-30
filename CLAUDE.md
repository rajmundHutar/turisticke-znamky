# Turistické známky

Personal web app for tracking a collection of Czech tourist stamps (turistické známky). Nette 3.3, Latte 3.1, MySQL, PHP 8.5.

## Commands

Everything runs in Docker (PHP 8.5, `.docker/php/Dockerfile`); the host PHP is not used.

- `docker compose run --rm app composer install` – install dependencies (also `composer update`; `config.platform.php` pins resolution to PHP 8.5)
- `docker compose run --rm app composer test` – Nette Tester suite in `tests/` (`*.phpt`)
- `docker compose run --rm app composer phpstan` – static analysis, level 5, config in `phpstan.neon`
- `docker compose run --rm app composer check` – PHPStan + tests; run before every PR
- `docker compose up` – app on http://localhost:8082 (container listens on 8080), MySQL on port 13306 (schema from `.docker/db/init.sql`)

Tests need no database. There are no DB/integration tests yet; acceptance criteria that need a DB are verified manually against the running app.

## Conventions

- Code, comments, commit messages in English; Conventional Commits referencing the issue, e.g. `fix: return 404 for unknown stamp (#7)`.
- Tabs for indentation, match the style of the surrounding file.
- `phpstan-baseline.neon` holds known pre-existing errors. When you fix one, remove its baseline entry (PHPStan fails on unmatched entries). Never add new entries to hide new errors.
- New tests go to `tests/<same path as app/>/<Class>Test.phpt`.
- `app/config/local.neon` (DB credentials) is not in the repo.
- Issues are tracked on GitHub; tickets ready for implementation carry the `rtm` label.

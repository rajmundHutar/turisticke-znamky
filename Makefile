# Deploy to production over FTPS (see CLAUDE.md, section Deploy).
# error_reporting without E_WARNING: the FTPS server drops TLS without a clean shutdown
# and the stray "SSL_shutdown failed" warning would abort the run.
DEPLOY = docker compose run --rm app php -d error_reporting=32765 deployment.phar deployment.ini

.PHONY: deploy deploy-test

deploy-test:
	$(DEPLOY) --test
	docker compose run --rm app composer install --no-interaction

deploy:
	@test "$$(git branch --show-current)" = main || { echo "Deploy only from main."; exit 1; }
	@test -z "$$(git status --porcelain)" || { echo "Working tree is not clean."; exit 1; }
	@git fetch -q origin && test "$$(git rev-parse HEAD)" = "$$(git rev-parse origin/main)" || { echo "main is not in sync with origin/main."; exit 1; }
	$(DEPLOY)

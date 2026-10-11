DC = docker compose
# Pao turns PHPUnit/PHPStan/Rector output into one JSON line when an agent is calling, and it keys off
# the caller's AI_AGENT — which `docker compose exec` does not forward. Human runs stay human.
AGENT_ENV = $(if $(AI_AGENT),-e AI_AGENT='$(AI_AGENT)')
EXEC = $(DC) exec -T $(AGENT_ENV) php
ARTISAN = $(EXEC) php artisan
NPM = $(DC) exec -T node npm

.DEFAULT_GOAL := quality

# ======================================================================================================================
# Infra
# ======================================================================================================================

.PHONY: install start stop restart build rebuild destroy ps logs shell ci

install:
	cp .env.example .env
	cp .env.testing.example .env.testing
	$(MAKE) start
	$(EXEC) composer install
	# php-fpm's workers run as www-data, and on Linux a bind mount keeps the host's owner and modes.
	$(EXEC) chmod -R a+rwX storage/app storage/framework storage/logs bootstrap/cache
	$(ARTISAN) key:generate
	$(ARTISAN) key:generate --env=testing
	$(ARTISAN) passport:keys
	$(EXEC) chown www-data storage/oauth-private.key storage/oauth-public.key
	$(MAKE) seed test-db
	@echo 'Front: http://localhost:5173 (julien@marque-page.test / marque-page) - API: http://localhost:8090'

start:
	$(DC) up -d --remove-orphans --wait

stop:
	$(DC) down

restart: stop start

build:
	$(DC) build

rebuild:
	$(DC) build --no-cache && $(MAKE) start

destroy:
	$(DC) down -v --remove-orphans

ps:
	$(DC) ps

logs:
	$(DC) logs -f --tail=100

shell:
	$(DC) exec php sh

# What a CI would run, from a fresh test database. The demo seeds the test database, never the dev one, and
# leaves it empty again: the suite runs in transactions over an empty database.
ci: test-db quality quality-front seed-check

# ======================================================================================================================
# Back
# ======================================================================================================================

.PHONY: quality ide-helper rector pint openapi stan ark test infection coverage metrics \
        test-db seed-check migrate fresh seed composer artisan tinker \
        inspector raw-session raw-http-session benchmark mcp-log

# The back's gate. The order is load-bearing: the writing tools come first, Pint last among them (it imports
# and shortens the fully-qualified names ide-helper writes), then the read-only checks judge the settled tree.
quality: ide-helper rector pint openapi stan ark test coverage infection

# --reset keeps annotations honest with the schema.
ide-helper:
	$(ARTISAN) ide-helper:generate --env=testing || true
	$(ARTISAN) ide-helper:eloquent --env=testing
	$(ARTISAN) ide-helper:meta --env=testing
	$(ARTISAN) ide-helper:models --env=testing --write --reset --no-interaction
	# ide-helper isn't fully Larastan-friendly: drop the noisy, unused `@method static …Builder…()` lines
	# (the `@mixin \Eloquent` already covers them).
	$(EXEC) sh -c "grep -Rl '@mixin \\\\Eloquent' app | xargs -r sed -i -E '/ \* @method static /d'"

rector:
	$(EXEC) ./vendor/bin/rector process

pint:
	$(EXEC) ./vendor/bin/pint --parallel

# The contract of the REST API, generated from its code and versioned: a change of the API shows in the diff.
openapi:
	$(ARTISAN) scramble:export

stan:
	$(EXEC) ./vendor/bin/phpstan analyse --memory-limit=1G

ark:
	$(EXEC) ./vendor/bin/phparkitect check --config=phparkitect.php

test:
	$(EXEC) php artisan test

# Not --threads=max: a saturated container kills runs, which Infection counts as detected, silently
# inflating the MSI (measured on ddd/). PAO_DISABLE: Infection judges a mutant by regexing PHPUnit's human
# output, and Pao's JSON would make every mutant read as killed. Errored and syntax-error mutants count as
# killed in the MSI, and Infection has no setting to refuse them.
infection:
	$(EXEC) env PAO_DISABLE=true php vendor/bin/infection --threads=4 --log-verbosity=all
	@grep -qx 'Errored: 0' storage/logs/infection-summary.log && grep -qx 'Syntax Errors: 0' storage/logs/infection-summary.log \
		|| { echo 'Errored mutants count as killed: see storage/logs/infection.log'; exit 1; }

# Every line of app/ is run by a test, or the gate fails. PAO_DISABLE: Pao's one JSON line would swallow the
# coverage summary.
coverage:
	$(EXEC) env PAO_DISABLE=true php vendor/bin/phpunit --coverage-html storage/coverage --coverage-text=storage/logs/coverage-summary.log --colors=never
	@cat storage/logs/coverage-summary.log
	@grep -qE '^ +Lines: +100\.00%' storage/logs/coverage-summary.log \
		|| { echo 'A line no test runs: see storage/coverage/index.html'; exit 1; }

# Outside the gate: figures to read, not thresholds to hold. PhpMetrics still runs on a parser that PHP 8.5
# deprecates, and it asks Packagist for the latest version of each dependency.
metrics:
	$(EXEC) php -d error_reporting='E_ALL & ~E_DEPRECATED' vendor/bin/phpmetrics --report-html=storage/metrics app

test-db:
	$(DC) exec -T postgres psql -U marquepage -d marquepage -tc "SELECT 1 FROM pg_database WHERE datname = 'marquepage_testing'" | grep -q 1 || $(DC) exec -T postgres createdb -U marquepage marquepage_testing
	$(ARTISAN) migrate:fresh --env=testing --force

seed-check:
	$(ARTISAN) migrate:fresh --seed --env=testing --force
	$(ARTISAN) migrate:fresh --env=testing --force

migrate:
	$(ARTISAN) migrate

fresh:
	$(ARTISAN) migrate:fresh

seed:
	$(ARTISAN) migrate:fresh --seed

composer:
	$(EXEC) composer $(ARGS)

artisan:
	$(ARTISAN) $(ARGS)

tinker:
	$(DC) exec php php artisan tinker

# Runs on the host (needs Node): the Inspector launches the server exactly as Claude Code does, from .mcp.json.
inspector:
	npx -y @modelcontextprotocol/inspector --config .mcp.json --server library

# A hand-written MCP client (client/) plays a read-only session against the server and prints every JSON-RPC line.
raw-session:
	$(DC) exec -T php php client/session.php

# The same hand-written client, over HTTP, in a modern stateless session: no initialize, every request its own POST.
raw-http-session:
	$(DC) exec -T php sh -c 'MCP_TOKEN=$$(php artisan library:token julien@marque-page.test --resource=mcp) php client/http-session.php'

# Times the same tools/call over stdio and HTTP, from inside the container: the transports, not Docker's port forwarding.
benchmark:
	$(DC) exec -T php sh -c 'MCP_TOKEN=$$(php artisan library:token julien@marque-page.test --resource=mcp) php client/benchmark.php'

# What a proxy sees of the HTTP traffic, read from the MCP headers alone, never from the body.
mcp-log:
	$(DC) exec nginx tail -n 50 -f /var/log/nginx/mcp.log

# ======================================================================================================================
# Front
# ======================================================================================================================

.PHONY: quality-front front-contract front-format front-lint front-typecheck front-test front-mutation front-install

# The front's gate. Starts from openapi so that the front is never judged against a stale contract.
quality-front: openapi front-contract front-format front-lint front-typecheck front-test front-mutation

# The types of the front's HTTP client, generated from that contract: an API drift breaks the front's compilation.
front-contract:
	$(NPM) run contract

front-format:
	$(NPM) run format

front-lint:
	$(NPM) run lint

front-typecheck:
	$(NPM) run typecheck

front-test:
	$(NPM) run test

front-mutation:
	$(NPM) run mutation

front-install:
	$(NPM) ci

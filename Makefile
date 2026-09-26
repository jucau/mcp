DC = docker compose
# Pao turns PHPUnit/PHPStan/Rector output into one JSON line when an agent is calling, and it keys off
# the caller's AI_AGENT — which `docker compose exec` does not forward. Human runs stay human.
AGENT_ENV = $(if $(AI_AGENT),-e AI_AGENT='$(AI_AGENT)')
EXEC = $(DC) exec -T $(AGENT_ENV) php
ARTISAN = $(EXEC) php artisan

.PHONY: quality ide-helper rector pint stan ark test coverage infection inspector raw-session test-db start stop restart build rebuild destroy ps logs shell \
        migrate fresh seed composer artisan tinker

# The one gate, run before every commit. The order is load-bearing: the writing tools come first, Pint
# last among them (it imports and shortens the fully-qualified names ide-helper writes), then the
# read-only checks judge the settled tree.
quality: ide-helper rector pint stan ark test infection

# --reset keeps annotations honest with the schema.
ide-helper:
	$(ARTISAN) ide-helper:generate --env=testing || true
	$(ARTISAN) ide-helper:eloquent --env=testing
	$(ARTISAN) ide-helper:meta --env=testing
	$(ARTISAN) ide-helper:models --env=testing --write --reset --no-interaction
	# ide-helper isn't fully Larastan-friendly: drop the noisy, unused `@method static …Builder…()` lines
	# (the `@mixin \Eloquent` already covers them).
	$(EXEC) sh -c "grep -Rl '@mixin \\\\Eloquent' app | xargs -r sed -i -E '/ \* @method static /d'"

pint:
	$(EXEC) ./vendor/bin/pint --parallel

stan:
	$(EXEC) ./vendor/bin/phpstan analyse --memory-limit=1G

rector:
	$(EXEC) ./vendor/bin/rector process

ark:
	$(EXEC) ./vendor/bin/phparkitect check --config=phparkitect.php

test:
	$(EXEC) php artisan test

# Outside the gate, on demand. PAO_DISABLE: Pao's one JSON line would swallow the coverage summary.
coverage:
	$(EXEC) env PAO_DISABLE=true php vendor/bin/phpunit --coverage-html storage/coverage --coverage-text

# Not --threads=max: a saturated container kills runs, which Infection counts as detected, silently
# inflating the MSI (measured on ddd/). PAO_DISABLE: Infection judges a mutant by regexing PHPUnit's human
# output, and Pao's JSON would make every mutant read as killed.
infection:
	$(EXEC) env PAO_DISABLE=true php vendor/bin/infection --threads=4

# Runs on the host (needs Node): the Inspector launches the server exactly as Claude Code does, from .mcp.json.
inspector:
	npx -y @modelcontextprotocol/inspector --config .mcp.json --server library

# A hand-written MCP client (client/) plays a read-only session against the server and prints every JSON-RPC line.
raw-session:
	$(DC) exec -T php php client/session.php

test-db:
	$(DC) exec -T postgres psql -U marquepage -d marquepage -tc "SELECT 1 FROM pg_database WHERE datname = 'marquepage_testing'" | grep -q 1 || $(DC) exec -T postgres createdb -U marquepage marquepage_testing
	$(ARTISAN) migrate:fresh --env=testing --force

start:
	$(DC) up -d --remove-orphans

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

DC = docker compose
# Pao turns PHPUnit/PHPStan/Rector output into one JSON line when an agent is calling, and it keys off
# the caller's AI_AGENT — which `docker compose exec` does not forward. Human runs stay human.
AGENT_ENV = $(if $(AI_AGENT),-e AI_AGENT='$(AI_AGENT)')
EXEC = $(DC) exec -T $(AGENT_ENV) php
ARTISAN = $(EXEC) php artisan

.PHONY: quality ide-helper rector pint stan test test-db start stop restart build rebuild destroy ps logs shell \
        migrate fresh seed composer artisan tinker

# The one gate, run before every commit. The order is load-bearing: the writing tools come first, Pint
# last among them (it imports and shortens the fully-qualified names ide-helper writes), then the
# read-only checks judge the settled tree.
quality: ide-helper rector pint stan test

# --reset keeps annotations honest with the schema.
ide-helper:
	$(ARTISAN) ide-helper:generate --env=testing || true
	$(ARTISAN) ide-helper:eloquent --env=testing
	$(ARTISAN) ide-helper:meta --env=testing
	$(ARTISAN) ide-helper:models --env=testing --write --reset --no-interaction
	# ide-helper isn't fully Larastan-friendly: drop the noisy, unused `@method static …Builder…()` lines
	# (the `@mixin \Eloquent` already covers them).
	$(EXEC) sh -c "grep -Rl '@mixin \\\\Eloquent' app | xargs sed -i -E '/ \* @method static /d'"

pint:
	$(EXEC) ./vendor/bin/pint --parallel

stan:
	$(EXEC) ./vendor/bin/phpstan analyse --memory-limit=1G

rector:
	$(EXEC) ./vendor/bin/rector process

test:
	$(EXEC) php artisan test

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

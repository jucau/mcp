DC = docker compose
EXEC = $(DC) exec -T php
ARTISAN = $(EXEC) php artisan

.PHONY: quality rector pint stan test test-db start stop restart build rebuild destroy ps logs shell \
        migrate fresh seed composer artisan tinker

# The one gate, run before every commit. The writing tools come first, then the checks judge the settled
# tree.
quality: rector pint stan test

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

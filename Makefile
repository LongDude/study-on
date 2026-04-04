COMPOSE=docker compose
ENV_FILES=--env-file .env --env-file .env.local
PHP=$(COMPOSE) exec php
CONSOLE=$(PHP) bin/console
COMPOSER=$(PHP) composer

up:
	@${COMPOSE} ${ENV_FILES} up -d

down:
	@${COMPOSE} down

clear:
	@${CONSOLE} cache:clear

migration:
	@${CONSOLE} make:migration

migrate:
	@${CONSOLE} doctrine:migration:migrate

fixtload:
	@${CONSOLE} doctrine:fixtures:load

-include local.mk

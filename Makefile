COMPOSE=docker compose
ENV_FILES=--env-file .env --env-file .env.local
PHP=$(COMPOSE) exec php
CONSOLE=$(PHP) bin/console
COMPOSER=$(PHP) composer

help:
	@echo "up			запуск контейнеров"
	@echo "down			остановка контейнеров"
	@echo "clear		очистка кэша"
	@echo "migration	создание миграций"
	@echo "migrate		применение миграций"
	@echo "fixtload		загрузка фикстур"


up:
	@${COMPOSE} ${ENV_FILES} up -d

down:
	@${COMPOSE} --profile all down

clear:
	@${CONSOLE} cache:clear

migration:
	@${CONSOLE} make:migration

migrate:
	@${CONSOLE} doctrine:migration:migrate

fixtload:
	@${CONSOLE} doctrine:fixtures:load

encore_dev:
	@${COMPOSE} run node yarn encore dev

encore_prod:
	@${COMPOSE} run node yarn encore production

up-prod:
	@APP_ENV="prod" ${COMPOSE} ${ENV_FILES} up -d

up-dev:
	@APP_ENV="dev" ${COMPOSE} ${ENV_FILES} --profile dev up -d

up-test:
	@APP_ENV="test" ${COMPOSE} ${ENV_FILES} --profile "test" up -d

phpunit:
	@${PHP} bin/phpunit

-include local.mk

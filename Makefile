COMPOSE=docker compose
ENV_FILES=--env-file .env --env-file .env.local
PHP=$(COMPOSE) exec php
CONSOLE=$(PHP) bin/console
COMPOSER=$(PHP) composer

help:
	@echo "up-prod		запуск контейнеров (production-ready)"
	@echo "up-dev		запуск контейнеров (development)"
	@echo "up-test		запуск контейнеров (test)"
	@echo "down			остановка контейнеров"
	@echo "clear		очистка кэша"
	@echo "migration	создание миграций"
	@echo "migrate		применение миграций"
	@echo "fixtload		загрузка фикстур"
	@echo "encore_dev	сборка фронта фикстур"
	@echo "encore_prod	загрузка фикстур"
	@echo "phpunit		загрузка фикстур"


up-prod:
	@APP_ENV="prod" ${COMPOSE} ${ENV_FILES} up -d

up-dev:
	@APP_ENV="dev" ${COMPOSE} ${ENV_FILES} --profile dev up -d

up-test:
	@APP_ENV="test" ${COMPOSE} ${ENV_FILES} --profile "test" up -d

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

run_tests:
	@${CONSOLE} doctrine:database:drop --env=test --if-exists --force
	@${CONSOLE} doctrine:database:create --env=test
	@${CONSOLE} doctrine:migration:migrate --env=test --no-interaction
	@${CONSOLE} doctrine:fixtures:load --env=test --no-interaction
	@${PHP} bin/phpunit

phpunit:
	@${PHP} sh -c 'APP_ENV=test bin/phpunit'

-include local.mk

export APP_NAME=wrp-distributor
export APP_ENV=local
export COMPOSE_FILE=docker/docker-compose.yml
export PROJECT_NAME=wrpdistributor

# Color Config
NOCOLOR=\033[0m
GREEN=\033[0;32m
BGREEN=\033[1;32m
YELLOW=\033[0;33m
CYAN=\033[0;36m

# Default action
.DEFAULT_GOAL := help

up:
	@echo ""
	@echo "${YELLOW}Start all container${NOCOLOR}"
	@echo ""
	docker-compose up -d

	@if [ ! -d vendor ]; then\
		echo "";\
		echo "${YELLOW}Install composer dependencies${NOCOLOR}";\
		echo "";\
		$(MAKE) composer;\
	fi

	$(MAKE) migrate_database

stop: cleanup_sessions
	@echo ""
	@echo "${YELLOW}Stop all container${NOCOLOR}"
	@echo ""
	docker-compose stop

destroy:
	@echo ""
	@echo "${YELLOW}Destroy all container${NOCOLOR}"
	@echo ""
	docker-compose down

build:
	@echo ""
	@echo "${YELLOW}Build all container${NOCOLOR}"
	@echo ""
	docker-compose up --build -d

composer:
	docker-compose exec -it php_$(PROJECT_NAME) composer install

composer_require:
	docker-compose exec php_$(PROJECT_NAME) composer require $(PACKAGE)

psalm:
	docker-compose exec -it php_$(PROJECT_NAME) vendor/bin/psalm --use-baseline=/var/www/.tooling/psalm/psalm-baseline.xml

update_psalm_baseline:
	docker-compose exec php_$(PROJECT_NAME) vendor/bin/psalm --use-baseline=/var/www/.tooling/psalm/psalm-baseline.xml --update-baseline

phpcs:
	docker-compose exec -it php_$(PROJECT_NAME) vendor/bin/phpcs src/ index.php bin/console

phpcs-fix:
	docker-compose exec php_$(PROJECT_NAME) vendor/bin/php-cs-fixer fix src/ index.php bin/console

bash_php:
	docker-compose exec php_$(PROJECT_NAME) bash

bash_nginx:
	docker-compose exec nginx_$(PROJECT_NAME) bash

bash_mysql:
	docker-compose exec mysql_$(PROJECT_NAME) bash

bash_sshd:
	docker-compose exec sshd_testing_$(PROJECT_NAME) bash

restart: stop up

cleanup_sessions:
	docker-compose exec php_$(PROJECT_NAME) ./bin/console cleanup:sessions

statistics_collect:
	docker-compose exec php_$(PROJECT_NAME) ./bin/console statistics:collect

statistics_dashboard_update:
	docker-compose exec php_$(PROJECT_NAME) ./bin/console statistics:dashboard:update

migrate_database:
	docker-compose exec php_$(PROJECT_NAME) ./vendor/bin/doctrine-migrations migrations:migrate -n -v

phpunit:
	docker-compose exec -it php_$(PROJECT_NAME) ./vendor/bin/phpunit \
		--bootstrap vendor/autoload.php \
		--coverage-html .tooling/phpunit/coverage \
		--coverage-filter src \
		--testdox-html .tooling/phpunit/testdox.html \
		tests

help:
	@echo ""
	@echo "${NOCOLOR}Usage: ${CYAN}make [TARGET] [EXTRA_ARGUMENTS]"
	@echo ""
	@echo "${NOCOLOR}Targets:"
	@echo ""
	@echo "  ${BGREEN}build${YELLOW}                        Build the containers"
	@echo "  ${BGREEN}up${YELLOW}                           Start the containers"
	@echo "  ${BGREEN}stop${YELLOW}                         Stop the containers"
	@echo "  ${BGREEN}destroy${YELLOW}                      Destroy the containers"
	@echo "  ${BGREEN}composer${YELLOW}                     Run composer install"
	@echo "  ${BGREEN}composer_require${YELLOW}             Run composer require [PACKAGE]"
	@echo "  ${BGREEN}phpcs${YELLOW}                        Run phpcs"
	@echo "  ${BGREEN}psalm${YELLOW}                        Run psalm"
	@echo "  ${BGREEN}phpunit${YELLOW}                      Run phpunit"
	@echo "  ${BGREEN}bash_php${YELLOW}                     Open bash in php container"
	@echo "  ${BGREEN}bash_nginx${YELLOW}                   Open bash in nginx container"
	@echo "  ${BGREEN}bash_mysql${YELLOW}                   Open bash in mysql container"
	@echo "  ${BGREEN}restart${YELLOW}                      Restart the containers"
	@echo "  ${BGREEN}migrate_database${YELLOW}             Run database migrations"
	@echo "  ${BGREEN}cleanup_sessions${YELLOW}             Run session cleanup"
	@echo "  ${BGREEN}statistics_collect${YELLOW}           Collect statistics for the dashboard at this point in time"
	@echo "  ${BGREEN}statistics_dashboard_update${YELLOW}  Generate dashboard.html from statistics table"
	@echo ""
	@echo "${NOCOLOR}Examples:"
	@echo ""
	@echo "${BGREEN}up${WHITE}                 ${CYAN}      make up"
	@echo "${BGREEN}composer_require${WHITE}   ${CYAN}      make composer_require -e PACKAGE=symfony/dotenv"
	@echo ""
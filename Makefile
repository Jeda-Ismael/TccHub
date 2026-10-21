# hubtcc — comandos do ambiente Docker
# Uso: make <alvo>   (make help lista todos)

DOCKER_COMP = docker compose
PHP_CONT    = $(DOCKER_COMP) exec php
CONSOLE     = $(PHP_CONT) bin/console
COMPOSER    = $(PHP_CONT) composer

.DEFAULT_GOAL := help
.PHONY: help build up down restart logs ps sh composer console \
        db-create db-reset migration migrate \
        test-db test coverage phpstan cs cs-fix qa

help: ## Lista os comandos disponíveis
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

## —— Docker ——————————————————————————————————————————————
build: ## Constrói as imagens
	@$(DOCKER_COMP) build --pull

up: ## Sobe o ambiente (API em https://localhost, front em http://localhost:5173)
	@$(DOCKER_COMP) up --detach --wait

down: ## Derruba o ambiente
	@$(DOCKER_COMP) down --remove-orphans

restart: down up ## Reinicia o ambiente

logs: ## Acompanha os logs de todos os serviços
	@$(DOCKER_COMP) logs --tail=100 --follow

ps: ## Mostra o estado dos containers
	@$(DOCKER_COMP) ps

sh: ## Abre um shell no container da API
	@$(PHP_CONT) sh

## —— Symfony / Composer ——————————————————————————————————
composer: ## Roda o composer. Ex.: make composer c='require foo/bar'
	@$(COMPOSER) $(c)

console: ## Roda o bin/console. Ex.: make console c='debug:router'
	@$(CONSOLE) $(c)

## —— Banco de dados ——————————————————————————————————————
db-create: ## Cria o banco (se não existir)
	@$(CONSOLE) doctrine:database:create --if-not-exists

db-reset: ## Recria o banco do zero e roda as migrations
	@$(CONSOLE) doctrine:database:drop --force --if-exists
	@$(CONSOLE) doctrine:database:create
	@$(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration

migration: ## Gera uma migration a partir das entidades
	@$(CONSOLE) make:migration

migrate: ## Aplica as migrations pendentes
	@$(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration

## —— Qualidade ———————————————————————————————————————————
test-db: ## Prepara o banco de testes (hubtcc_test)
	@$(CONSOLE) --env=test doctrine:database:drop --force --if-exists
	@$(CONSOLE) --env=test doctrine:database:create
	@$(CONSOLE) --env=test doctrine:migrations:migrate --no-interaction --allow-no-migration

test: test-db ## Roda os testes. Ex.: make test c='--filter=Health'
	@$(PHP_CONT) bin/phpunit $(c)

coverage: test-db ## Testes com relatório de cobertura em api/var/coverage
	@$(DOCKER_COMP) exec -e XDEBUG_MODE=off php php -d pcov.enabled=1 bin/phpunit --coverage-html var/coverage --coverage-text

phpstan: ## Análise estática (PHPStan nível 8)
	@$(CONSOLE) cache:warmup --env=dev -q
	@$(COMPOSER) phpstan

cs: ## Verifica o estilo do código
	@$(COMPOSER) cs

cs-fix: ## Corrige o estilo do código
	@$(COMPOSER) cs:fix

qa: cs phpstan test ## Roda tudo o que a CI roda

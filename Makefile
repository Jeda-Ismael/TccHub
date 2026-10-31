# hubtcc — comandos do ambiente Docker
# Uso: make <alvo>   (make help lista todos)

DOCKER_COMP = docker compose
PROD_COMP   = docker compose -f compose.yaml -f compose.prod.yaml --env-file .env.prod
PHP_CONT    = $(DOCKER_COMP) exec php
CONSOLE     = $(PHP_CONT) bin/console
COMPOSER    = $(PHP_CONT) composer

.DEFAULT_GOAL := help
.PHONY: help build up down restart logs ps sh composer console \
        db-create db-reset fixtures demo migration migrate \
        test-db test coverage test-coverage phpstan cs cs-fix web-test web-coverage qa \
        prod-up prod-down prod-logs prod-user

help: ## Lista os comandos disponíveis
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

## —— Docker ——————————————————————————————————————————————
build: ## Constrói as imagens
	@$(DOCKER_COMP) build --pull

up: ## Sobe o ambiente (sistema em http://localhost:5173, API em https://localhost/api)
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

db-reset: ## Recria o banco do zero, roda as migrations e carrega as fixtures
	@# Apaga as tabelas em vez do banco: a API mantém conexões abertas e o Postgres não deixa dropar
	@$(CONSOLE) doctrine:schema:drop --full-database --force
	@$(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration
	@$(CONSOLE) doctrine:fixtures:load --no-interaction --group=base

fixtures: ## Recarrega os dados base: usuários, catálogos e períodos (apaga os dados atuais)
	@$(CONSOLE) doctrine:fixtures:load --no-interaction --group=base

demo: up migrate ## Sobe tudo com dados de demonstração: TCCs em vários status e o repositório com publicados (apaga os dados atuais)
	@$(CONSOLE) doctrine:fixtures:load --no-interaction --group=demo
	@echo ""
	@echo "  Pronto: abra http://localhost:5173 e entre com coordenacao@hubtcc.dev / 123456"
	@echo "  (outros usuários no README; todos com a senha 123456)"

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

coverage: test-db ## Testes com relatório HTML de cobertura, copiado para api/var/coverage
	@$(DOCKER_COMP) exec -e XDEBUG_MODE=off php php -d pcov.enabled=1 -d pcov.directory=src bin/phpunit --coverage-html var/coverage --coverage-text
	@# var/ fica num volume do container: copia o relatório para a pasta do projeto
	@$(DOCKER_COMP) cp php:/app/var/coverage ./api/var/
	@echo "Relatório: api/var/coverage/index.html"

test-coverage: test-db ## Testes da API exigindo cobertura mínima de 85% (usado na CI)
	@$(DOCKER_COMP) exec -e XDEBUG_MODE=off php php -d pcov.enabled=1 -d pcov.directory=src bin/phpunit --coverage-clover var/coverage/clover.xml
	@$(PHP_CONT) php bin/coverage-check var/coverage/clover.xml 85

phpstan: ## Análise estática (PHPStan nível 8)
	@$(CONSOLE) cache:warmup --env=test -q
	@$(COMPOSER) phpstan

cs: ## Verifica o estilo do código
	@$(COMPOSER) cs

cs-fix: ## Corrige o estilo do código
	@$(COMPOSER) cs:fix

web-test: ## Roda os testes e a checagem de tipos do frontend (pasta web/)
	@$(DOCKER_COMP) exec web npm run typecheck
	@$(DOCKER_COMP) exec web npm test

web-coverage: ## Testes do frontend exigindo cobertura mínima de 85%
	@$(DOCKER_COMP) exec web npm run test:coverage

qa: cs phpstan test-coverage web-test web-coverage ## Roda tudo o que a CI roda

## —— Produção (no servidor; ver docs/DEPLOY.md) ———————————————
prod-up: ## Constrói e sobe a versão de produção (usa .env.prod)
	@$(PROD_COMP) up --detach --build --wait

prod-down: ## Derruba a produção (os dados ficam nos volumes)
	@$(PROD_COMP) down

prod-logs: ## Logs da produção
	@$(PROD_COMP) logs --tail=100 --follow

prod-user: ## Cria usuário em produção. Ex.: make prod-user c='"Carla Souza" carla@faculdade.edu.br --role=COORDINATION'
	@$(PROD_COMP) exec php bin/console app:user:create $(c)

DOCKER_COMPOSE = docker compose
PHP_CONTAINER  = beakon_php

.PHONY: help up down restart shell logs \
        install composer-update \
        migrate migrate-status diff rollback \
        cache-clear cc

help: ## Exibe esta mensagem de ajuda
	@awk 'BEGIN {FS = ":.*##"; printf "\nUso: make \033[36m<target>\033[0m\n\nTargets:\n"} \
	/^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

# ── Docker ───────────────────────────────────────────────────
up: ## Sobe todos os serviços em background (build automático)
	$(DOCKER_COMPOSE) up -d --build

down: ## Para e remove os containers
	$(DOCKER_COMPOSE) down

restart: ## Reinicia todos os serviços
	$(DOCKER_COMPOSE) restart

logs: ## Acompanha os logs de todos os serviços (Ctrl-C para sair)
	$(DOCKER_COMPOSE) logs -f

# ── Shell ────────────────────────────────────────────────────
shell: ## Abre bash dentro do container PHP
	docker exec -it $(PHP_CONTAINER) bash

# ── Composer ─────────────────────────────────────────────────
install: ## Instala dependências PHP via Composer
	docker exec -u root $(PHP_CONTAINER) composer install --no-interaction --prefer-dist

composer-update: ## Atualiza dependências PHP
	docker exec -u root $(PHP_CONTAINER) composer update --no-interaction

# ── Doctrine Migrations ──────────────────────────────────────
migrate: ## Executa todas as migrations pendentes
	docker exec $(PHP_CONTAINER) php bin/console doctrine:migrations:migrate --no-interaction

migrate-status: ## Exibe o status das migrations
	docker exec $(PHP_CONTAINER) php bin/console doctrine:migrations:status

diff: ## Gera uma nova migration comparando entidades com o schema
	docker exec $(PHP_CONTAINER) php bin/console doctrine:migrations:diff

rollback: ## Desfaz a última migration executada
	docker exec $(PHP_CONTAINER) php bin/console doctrine:migrations:migrate prev --no-interaction

# ── Cache ────────────────────────────────────────────────────
cache-clear cc: ## Limpa e regenera o cache do Symfony
	docker exec $(PHP_CONTAINER) php bin/console cache:clear

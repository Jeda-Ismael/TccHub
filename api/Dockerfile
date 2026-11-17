#syntax=docker/dockerfile:1

# Baseado no template oficial dunglas/symfony-docker (MIT):
# https://github.com/dunglas/symfony-docker

FROM dunglas/frankenphp:1-php8.5 AS frankenphp_upstream


# Imagem base
FROM frankenphp_upstream AS frankenphp_base

SHELL ["/bin/bash", "-euxo", "pipefail", "-c"]

WORKDIR /app

# hadolint ignore=DL3008
RUN <<-EOF
	apt-get update
	apt-get install -y --no-install-recommends \
		file \
		git \
		unzip
	install-php-extensions \
		@composer \
		intl \
		opcache \
		pdo_pgsql \
		zip
	rm -rf /var/lib/apt/lists/*
EOF

# Extensões do PECL: o pecl.php.net às vezes fica fora do ar e derrubava o build.
# Tenta 3 vezes e, se não conseguir, segue sem elas (nenhuma é obrigatória).
COPY --link --chmod=755 frankenphp/install-pecl-extensions.sh /usr/local/bin/install-pecl-extensions
RUN install-pecl-extensions apcu

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"

COPY --link frankenphp/conf.d/10-app.ini $PHP_INI_DIR/app.conf.d/
COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link frankenphp/Caddyfile /etc/frankenphp/Caddyfile

ENTRYPOINT ["docker-entrypoint"]

HEALTHCHECK --start-period=60s CMD php -r 'exit(false === @file_get_contents("http://localhost:2019/metrics", context: stream_context_create(["http" => ["timeout" => 5]])) ? 1 : 0);'
CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile" ]


# Imagem de desenvolvimento
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev
ENV XDEBUG_MODE=off
ENV FRANKENPHP_WORKER_CONFIG=watch

# Xdebug para depuração, PCOV para cobertura de testes (mais rápido que o Xdebug)
RUN <<-EOF
	mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
	install-pecl-extensions xdebug pcov
	git config --system --add safe.directory /app
EOF

COPY --link frankenphp/conf.d/20-app.dev.ini $PHP_INI_DIR/app.conf.d/

CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--watch" ]


# Build do frontend React para produção. O código vem do contexto "web"
# (pasta frontend/), passado pelo compose.prod.yaml em additional_contexts.
FROM node:22-alpine AS web_build

WORKDIR /web

COPY --from=web package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY --from=web . ./
RUN npm run build


# Imagem de produção: API + frontend compilado no mesmo container
FROM frankenphp_base AS frankenphp_prod

ENV APP_ENV=prod

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link frankenphp/conf.d/20-app.prod.ini $PHP_INI_DIR/app.conf.d/

# Instala as dependências antes de copiar o código, para aproveitar o cache de camadas
COPY --link composer.* symfony.* ./
RUN composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

COPY --link --exclude=frankenphp/ . ./
COPY --link --from=web_build /web/dist/ ./public/

RUN <<-EOF
	mkdir -p var/cache var/log var/share
	composer dump-autoload --classmap-authoritative --no-dev
	composer dump-env prod
	composer run-script --no-dev post-install-cmd
	chmod +x bin/console
	sync
EOF

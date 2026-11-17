#!/bin/sh
set -e

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
	if [ -z "$(ls -A 'vendor/' 2>/dev/null)" ]; then
		composer install --prefer-dist --no-progress --no-interaction
	fi

	php bin/console -V

	if grep -q ^DATABASE_URL= .env; then
		echo 'Aguardando o banco de dados...'
		ATTEMPTS_LEFT_TO_REACH_DATABASE=60
		until [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ] || DATABASE_ERROR=$(php bin/console dbal:run-sql -q "SELECT 1" 2>&1); do
			if [ $? -eq 255 ]; then
				# Código 255 = erro irrecuperável do Doctrine (ex.: credenciais erradas)
				ATTEMPTS_LEFT_TO_REACH_DATABASE=0
				break
			fi
			sleep 1
			ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
			echo "Banco ainda indisponível. $ATTEMPTS_LEFT_TO_REACH_DATABASE tentativas restantes."
		done

		if [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ]; then
			echo 'Não foi possível conectar ao banco de dados:'
			echo "$DATABASE_ERROR"
			exit 1
		fi

		echo 'Banco de dados pronto.'

		if find ./migrations -iname '*.php' -print -quit | grep --quiet .; then
			php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing
		fi
	fi

	echo 'API pronta!'
fi

exec docker-php-entrypoint "$@"

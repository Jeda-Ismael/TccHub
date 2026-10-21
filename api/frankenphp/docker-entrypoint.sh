#!/bin/sh
set -e

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
	# Instala as dependências na primeira subida e sempre que o composer.lock
	# mudar (ex.: depois de um git pull que trouxe bibliotecas novas)
	if [ -z "$(ls -A 'vendor/' 2>/dev/null)" ] || [ composer.lock -nt vendor/composer/installed.json ]; then
		composer install --prefer-dist --no-progress --no-interaction
	fi

	php bin/console -V

	# Chaves do JWT (ficam fora do git); geradas só na primeira subida
	php bin/console lexik:jwt:generate-keypair --skip-if-exists

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

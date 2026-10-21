#!/bin/sh
# Instala extensões do PECL com até 3 tentativas cada. Se o PECL estiver
# fora do ar, avisa e segue: o build não deve quebrar por uma extensão opcional.
for extension in "$@"; do
	for attempt in 1 2 3; do
		if install-php-extensions "$extension"; then
			break
		fi
		echo "PECL indisponível ao instalar $extension (tentativa $attempt de 3)"
		sleep $((attempt * 10))
	done

	if ! php -m | grep -qi "^$extension\$"; then
		echo "AVISO: extensão $extension não instalada; a imagem segue sem ela."
	fi
done

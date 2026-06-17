#!/usr/bin/env sh
set -eu

PHP_BIN="${PHP_BIN:-}"

if [ -z "$PHP_BIN" ]; then
    if command -v php >/dev/null 2>&1; then
        PHP_BIN="$(command -v php)"
    elif [ -x /opt/homebrew/bin/php ]; then
        PHP_BIN="/opt/homebrew/bin/php"
    else
        echo "PHP nao encontrado. Instale o PHP ou informe PHP_BIN=/caminho/para/php."
        exit 1
    fi
fi

"$PHP_BIN" scripts/init-db.php
echo "Sistema EBD em http://localhost:8000"
echo "Use Ctrl+C para parar."
"$PHP_BIN" -S localhost:8000 -t public public/router.php

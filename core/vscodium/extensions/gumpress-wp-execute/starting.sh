#!/bin/bash
# GumPress - MIT License

echo

NAME_FILE="$TEMP/gumpress-wp-execute.name"
DONE_FILE="$TEMP/gumpress-wp-execute.done"

# Rimuovi eventuale done precedente
rm -f "$DONE_FILE"

# Crea il done file sempre, anche su Ctrl+C o errore
trap 'touch "$DONE_FILE"' EXIT

if [ -f "$NAME_FILE" ]; then
    encodedPath=$(cat "$NAME_FILE" | tr -d '[:space:]')
else
    echo "Errore: File $NAME_FILE non trovato." >&2
    # Crea done anche in caso di errore per sbloccare JS
    touch "$DONE_FILE"
    exit 1
fi

curl.exe -x "$GP_PROXY_URL" -N --tls-max 1.2 --ssl-no-revoke --max-time 333 \
    --cacert "$NODE_EXTRA_CA_CERTS" \
    -H "X-GumPress-Auth: $GP_AUTH_SECRET" \
    "$GP_WORDPRESS_ENDPOINT/wp-json/gumpress/execute/$encodedPath"

# echo "Exit code curl: $?"
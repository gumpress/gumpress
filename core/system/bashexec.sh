#!/usr/bin/env bash

 # GumPress - MIT License

set -euo pipefail

ORIGIN="${1:-}"
ACTION="${ORIGIN^^}"

case "$ACTION" in

	"?")
		echo
		echo -e "\e[90m › GumPress commands:\e[0m"
		echo
		echo -e "\e[32m   ?\e[90m             Show this help message"
		echo -e "\e[32m   open\e[90m          Open WordPress dashboard"
	 	echo -e "\e[32m   wp\e[90m            Run WP-CLI commands"
		echo -e "\e[32m   composer\e[90m      Run PHP dependency manager"
		echo -e "\e[32m   exit\e[90m          Stop all services and quit"
		echo
		echo -e "\e[90m › Additionally, all standard git-bash commands are available.\e[0m"
		exit 0
		;;

	"OPEN_ALIAS"|"PLACEHOLDER")
		MESSAGE="${ACTION}${2:+#$2}"
		echo "$MESSAGE" > "$SENTINEL"
		KILLID=$(ps -ef | awk -v ppid="$PPID" '$2 == ppid {print $3}')
		kill -9 "${KILLID:-$PPID}"
		exit 0
		;;

	"")
		exit 0
		;;

	*)
		echo "bash: $ORIGIN: command not found" >&2
		exit 127
		;;

esac

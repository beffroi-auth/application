#!/bin/sh
set -e

# Each size the Caddyfiles take from the environment to bound a request, read by Caddy itself. Caddy starts on a size it
# reads as zero bytes, whatever the unit, or on one of 8EiB and more, which wraps below zero: the body is then not
# bounded, and the headers fall back to the default of Caddy. A size Caddy cannot read is left to the error it stops on.
for variable in $(grep -ohE 'max_(header_)?size \{\$[A-Z_]+' /etc/frankenphp/*Caddyfile | sed 's/.*\$//' | sort -u); do
	size="$(printenv "${variable}")" || continue
	adapted="$(printf ':0 {\n\trequest_body {\n\t\tmax_size {$SIZE}\n\t}\n}\n' \
		| SIZE="${size}" frankenphp adapt --adapter caddyfile --config /dev/stdin 2>/dev/null)" || continue
	case "${adapted}" in
	*'"max_size":'[1-9]*) ;;
	*)
		echo "${variable}=${size} is not a bound: give it a size of 1B or more, below 8EiB." >&2
		exit 1
		;;
	esac
done

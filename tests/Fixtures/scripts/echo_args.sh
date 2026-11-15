#!/usr/bin/env bash

set -euo pipefail

for arg in "$@"; do
    printf '%s\n' "$arg"
done

if [ "${1:-}" = "fail" ]; then
    printf 'script failed\n' >&2
    exit 3
fi

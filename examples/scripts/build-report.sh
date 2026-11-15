#!/usr/bin/env bash

set -euo pipefail

format="${1:---format=pdf}"

printf 'building report (%s)\n' "$format"

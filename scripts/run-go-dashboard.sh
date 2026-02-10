#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "$0")/.." && pwd)"
cd "$root_dir/services/go-control-plane"

if [[ ! -f .env ]]; then
  echo "Missing services/go-control-plane/.env"
  echo "Copy and edit: cp .env.example .env"
  exit 1
fi

set -a
source .env
set +a

go run .

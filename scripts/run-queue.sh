#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

./vendor/bin/sail artisan horizon

#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

./vendor/bin/sail artisan queue:work --timeout=1800

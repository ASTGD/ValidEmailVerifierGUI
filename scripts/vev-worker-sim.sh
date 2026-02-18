#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "$0")/.." && pwd)"
worker_dir="$root_dir/engine-worker-go"
state_dir="$root_dir/storage/app/worker-sim"
pid_dir="$state_dir/pids"
log_dir="$state_dir/logs"

usage() {
  cat <<'EOF'
Usage:
  ./scripts/vev-worker-sim.sh start [count]
  ./scripts/vev-worker-sim.sh stop
  ./scripts/vev-worker-sim.sh status
  ./scripts/vev-worker-sim.sh restart [count]

Examples:
  ./scripts/vev-worker-sim.sh start 10
  ./scripts/vev-worker-sim.sh stop
EOF
}

ensure_state_dirs() {
  mkdir -p "$pid_dir" "$log_dir"
}

load_worker_env() {
  local env_file="$worker_dir/.env"
  if [[ -f "$env_file" ]]; then
    set -a
    # shellcheck disable=SC1090
    source "$env_file"
    set +a
  fi
}

assert_required_env() {
  local missing=()
  local required=(ENGINE_API_BASE_URL ENGINE_API_TOKEN)
  local key
  for key in "${required[@]}"; do
    if [[ -z "${!key-}" ]]; then
      missing+=("$key")
    fi
  done

  if [[ ${#missing[@]} -gt 0 ]]; then
    echo "Missing required env vars: ${missing[*]}"
    echo "Set them in engine-worker-go/.env or your shell."
    exit 1
  fi
}

is_running_pid() {
  local pid="$1"
  kill -0 "$pid" 2>/dev/null
}

pool_for_index() {
  local index="$1"
  case $(( (index - 1) % 3 )) in
    0) echo "default" ;;
    1) echo "reputation-a" ;;
    *) echo "reputation-b" ;;
  esac
}

start_workers() {
  local count="${1:-10}"
  if ! [[ "$count" =~ ^[0-9]+$ ]] || [[ "$count" -lt 1 ]]; then
    echo "Count must be a positive integer."
    exit 1
  fi

  ensure_state_dirs
  load_worker_env
  assert_required_env

  local started=0
  local skipped=0
  local i
  for i in $(seq 1 "$count"); do
    local worker_id="sim-worker-$i"
    local pid_file="$pid_dir/$worker_id.pid"
    local log_file="$log_dir/$worker_id.log"

    if [[ -f "$pid_file" ]]; then
      local existing_pid
      existing_pid="$(cat "$pid_file")"
      if [[ -n "$existing_pid" ]] && is_running_pid "$existing_pid"; then
        echo "Skipping $worker_id (already running pid $existing_pid)."
        skipped=$((skipped + 1))
        continue
      fi

      rm -f "$pid_file"
    fi

    (
      cd "$worker_dir"
      export WORKER_ID="$worker_id"
      export ENGINE_SERVER_NAME="$worker_id"
      export ENGINE_SERVER_IP="127.0.1.$((100 + i))"
      export WORKER_POOL="$(pool_for_index "$i")"
      export WORKER_CAPABILITY="${WORKER_CAPABILITY:-smtp_probe}"
      nohup go run ./cmd/worker >"$log_file" 2>&1 &
      echo "$!" >"$pid_file"
    )

    echo "Started $worker_id (pid $(cat "$pid_file"))."
    started=$((started + 1))
  done

  echo "Done. Started: $started, Skipped: $skipped"
  echo "Logs: $log_dir"
}

stop_workers() {
  ensure_state_dirs

  shopt -s nullglob
  local pid_files=("$pid_dir"/*.pid)
  shopt -u nullglob

  if [[ ${#pid_files[@]} -eq 0 ]]; then
    echo "No simulated workers are running."
    return
  fi

  local stopped=0
  local stale=0
  local pid_file
  for pid_file in "${pid_files[@]}"; do
    local worker_id
    worker_id="$(basename "$pid_file" .pid)"
    local pid
    pid="$(cat "$pid_file")"

    if [[ -n "$pid" ]] && is_running_pid "$pid"; then
      kill "$pid" 2>/dev/null || true
      sleep 0.1
      if is_running_pid "$pid"; then
        kill -9 "$pid" 2>/dev/null || true
      fi
      echo "Stopped $worker_id (pid $pid)."
      stopped=$((stopped + 1))
    else
      stale=$((stale + 1))
    fi

    rm -f "$pid_file"
  done

  echo "Done. Stopped: $stopped, Cleared stale: $stale"
}

status_workers() {
  ensure_state_dirs

  shopt -s nullglob
  local pid_files=("$pid_dir"/*.pid)
  shopt -u nullglob

  if [[ ${#pid_files[@]} -eq 0 ]]; then
    echo "No simulated workers are running."
    return
  fi

  local running=0
  local pid_file
  for pid_file in "${pid_files[@]}"; do
    local worker_id
    worker_id="$(basename "$pid_file" .pid)"
    local pid
    pid="$(cat "$pid_file")"
    if [[ -n "$pid" ]] && is_running_pid "$pid"; then
      echo "$worker_id: running (pid $pid)"
      running=$((running + 1))
    else
      echo "$worker_id: stale pid file"
    fi
  done

  echo "Running workers: $running"
}

command="${1:-}"
case "$command" in
  start)
    start_workers "${2:-10}"
    ;;
  stop)
    stop_workers
    ;;
  status)
    status_workers
    ;;
  restart)
    stop_workers
    start_workers "${2:-10}"
    ;;
  *)
    usage
    exit 1
    ;;
esac

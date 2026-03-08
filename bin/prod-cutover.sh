#!/usr/bin/env bash
set -euo pipefail

SOURCE_ENV_FILE="artifacts/.prod.env"
DUMP_FILE="artifacts/migration/prod.sql"
SKIP_DUMP=0
SKIP_IMPORT=0
SKIP_MIGRATE=0
EXECUTE=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --source-env)
      SOURCE_ENV_FILE="$2"
      shift 2
      ;;
    --dump-file)
      DUMP_FILE="$2"
      shift 2
      ;;
    --skip-dump)
      SKIP_DUMP=1
      shift
      ;;
    --skip-import)
      SKIP_IMPORT=1
      shift
      ;;
    --skip-migrate)
      SKIP_MIGRATE=1
      shift
      ;;
    --execute)
      EXECUTE=1
      shift
      ;;
    *)
      echo "Unknown argument: $1" >&2
      exit 1
      ;;
  esac
done

require_cmd() {
  local cmd="$1"
  if ! command -v "$cmd" >/dev/null 2>&1; then
    echo "Missing required command: $cmd" >&2
    exit 1
  fi
}

require_value() {
  local name="$1"
  local value="${2:-}"
  if [[ -z "$value" ]]; then
    echo "Missing required value: $name" >&2
    exit 1
  fi
}

read_env_key() {
  local key="$1"
  local file="$2"
  local line value

  line="$(grep -E "^[[:space:]]*${key}[[:space:]]*=" "$file" | tail -n 1 || true)"
  if [[ -z "$line" ]]; then
    echo ""
    return
  fi

  value="${line#*=}"
  value="$(printf '%s' "$value" | sed -E "s/[[:space:]]+#.*$//; s/^[[:space:]]+//; s/[[:space:]]+$//")"
  value="${value%\'}"
  value="${value#\'}"
  value="${value%\"}"
  value="${value#\"}"
  printf '%s' "$value"
}

SOURCE_DB_HOST="${LEAN_OLD_DB_HOST:-}"
SOURCE_DB_USER="${LEAN_OLD_DB_USER:-}"
SOURCE_DB_PASSWORD="${LEAN_OLD_DB_PASSWORD:-}"
SOURCE_DB_NAME="${LEAN_OLD_DB_DATABASE:-}"
SOURCE_DB_PORT="${LEAN_OLD_DB_PORT:-3306}"

# Fallback: load legacy source DB creds from file using LEAN_DB_* keys.
if [[ -f "$SOURCE_ENV_FILE" ]]; then
  [[ -n "$SOURCE_DB_HOST" ]] || SOURCE_DB_HOST="$(read_env_key LEAN_DB_HOST "$SOURCE_ENV_FILE")"
  [[ -n "$SOURCE_DB_USER" ]] || SOURCE_DB_USER="$(read_env_key LEAN_DB_USER "$SOURCE_ENV_FILE")"
  [[ -n "$SOURCE_DB_PASSWORD" ]] || SOURCE_DB_PASSWORD="$(read_env_key LEAN_DB_PASSWORD "$SOURCE_ENV_FILE")"
  [[ -n "$SOURCE_DB_NAME" ]] || SOURCE_DB_NAME="$(read_env_key LEAN_DB_DATABASE "$SOURCE_ENV_FILE")"
  [[ "${SOURCE_DB_PORT:-}" != "3306" ]] || SOURCE_DB_PORT="$(read_env_key LEAN_DB_PORT "$SOURCE_ENV_FILE")"
fi
SOURCE_DB_PORT="${SOURCE_DB_PORT:-3306}"

TARGET_DB_HOST="${LEAN_DB_HOST:-}"
TARGET_DB_USER="${LEAN_DB_USER:-}"
TARGET_DB_PASSWORD="${LEAN_DB_PASSWORD:-}"
TARGET_DB_NAME="${LEAN_DB_DATABASE:-}"
TARGET_DB_PORT="${LEAN_DB_PORT:-3306}"

require_value "LEAN_OLD_DB_HOST (source)" "$SOURCE_DB_HOST"
require_value "LEAN_OLD_DB_USER (source)" "$SOURCE_DB_USER"
require_value "LEAN_OLD_DB_PASSWORD (source)" "$SOURCE_DB_PASSWORD"
require_value "LEAN_OLD_DB_DATABASE (source)" "$SOURCE_DB_NAME"
require_value "LEAN_DB_HOST (target env)" "$TARGET_DB_HOST"
require_value "LEAN_DB_USER (target env)" "$TARGET_DB_USER"
require_value "LEAN_DB_PASSWORD (target env)" "$TARGET_DB_PASSWORD"
require_value "LEAN_DB_DATABASE (target env)" "$TARGET_DB_NAME"

mkdir -p "$(dirname "$DUMP_FILE")"

echo "Cutover plan"
echo "  Source DB: ${SOURCE_DB_HOST}:${SOURCE_DB_PORT} / ${SOURCE_DB_NAME}"
echo "  Target DB: ${TARGET_DB_HOST}:${TARGET_DB_PORT} / ${TARGET_DB_NAME}"
echo "  Dump file: ${DUMP_FILE}"
echo "  Execute:   ${EXECUTE}"
echo

USE_DIRECT_COPY=0
if [[ "$SKIP_DUMP" -eq 0 && "$SKIP_IMPORT" -eq 0 ]]; then
  if ! command -v mysqldump >/dev/null 2>&1 || ! command -v mysql >/dev/null 2>&1; then
    USE_DIRECT_COPY=1
    echo "mysqldump/mysql not found; will use php bin/leantime migration:copy-db instead."
    echo
  fi
fi

if [[ "$USE_DIRECT_COPY" -eq 1 ]]; then
  if [[ "$EXECUTE" -eq 1 ]]; then
    require_cmd php
    php bin/leantime migration:copy-db
  else
    echo "Step 1+2: Direct DB copy (dry-run)"
    echo "  [dry-run] php bin/leantime migration:copy-db --dry-run"
    php bin/leantime migration:copy-db --dry-run
  fi
else
if [[ "$SKIP_DUMP" -eq 0 ]]; then
  echo "Step 1: Export source DB"
  if [[ "$EXECUTE" -eq 1 ]]; then
    require_cmd mysqldump
    mysqldump \
      --single-transaction \
      --quick \
      --set-gtid-purged=OFF \
      --column-statistics=0 \
      --host="$SOURCE_DB_HOST" \
      --port="$SOURCE_DB_PORT" \
      --user="$SOURCE_DB_USER" \
      --password="$SOURCE_DB_PASSWORD" \
      "$SOURCE_DB_NAME" \
      --result-file="$DUMP_FILE"
  else
    echo "  [dry-run] mysqldump ..."
  fi
fi

if [[ "$SKIP_IMPORT" -eq 0 ]]; then
  echo "Step 2: Import dump into target DB"
  if [[ "$EXECUTE" -eq 1 ]]; then
    require_cmd mysql
    mysql \
      --host="$TARGET_DB_HOST" \
      --port="$TARGET_DB_PORT" \
      --user="$TARGET_DB_USER" \
      --password="$TARGET_DB_PASSWORD" \
      "$TARGET_DB_NAME" < "$DUMP_FILE"
  else
    echo "  [dry-run] mysql ... < $DUMP_FILE"
  fi
fi
fi

if [[ "$SKIP_MIGRATE" -eq 0 ]]; then
  echo "Step 3: Run Leantime db:migrate against target"
  if [[ "$EXECUTE" -eq 1 ]]; then
    require_cmd php
    php bin/leantime db:migrate
  else
    echo "  [dry-run] php bin/leantime db:migrate"
  fi
fi

echo
echo "Done."
if [[ "$EXECUTE" -ne 1 ]]; then
  echo "Re-run with --execute to perform the migration."
fi

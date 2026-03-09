#!/usr/bin/env bash
set -euo pipefail

REMOTE_PATH="${REMOTE_PATH:-userfiles}"
LOCAL_DIR="${LOCAL_DIR:-./artifacts/migration/userfiles-mirror}"
EXECUTE=0
SKIP_DOWNLOAD=0
SKIP_UPLOAD=0
VERIFY_ONLY=0
PRESERVE_TIMES=1

while [[ $# -gt 0 ]]; do
  case "$1" in
    --remote-path)
      REMOTE_PATH="$2"
      shift 2
      ;;
    --local-dir)
      LOCAL_DIR="$2"
      shift 2
      ;;
    --skip-download)
      SKIP_DOWNLOAD=1
      shift
      ;;
    --skip-upload)
      SKIP_UPLOAD=1
      shift
      ;;
    --verify-only)
      VERIFY_ONLY=1
      SKIP_DOWNLOAD=1
      SKIP_UPLOAD=1
      shift
      ;;
    --no-preserve-times)
      PRESERVE_TIMES=0
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

count_local_files() {
  local dir="$1"
  if [[ ! -d "$dir" ]]; then
    echo "0"
    return
  fi
  find "$dir" -type f | wc -l | awk '{print $1}'
}

count_s3_objects() {
  local bucket="$1"
  local prefix="$2"
  local endpoint="$3"

  local query='length(Contents[])'
  local out
  if [[ -n "$endpoint" ]]; then
    out="$(aws s3api list-objects-v2 --bucket "$bucket" --prefix "$prefix" --endpoint-url "$endpoint" --query "$query" --output text 2>/dev/null || true)"
  else
    out="$(aws s3api list-objects-v2 --bucket "$bucket" --prefix "$prefix" --query "$query" --output text 2>/dev/null || true)"
  fi

  if [[ "$out" == "None" || -z "$out" ]]; then
    echo "0"
  else
    echo "$out"
  fi
}

FTP_HOST="${FTP_OLD_HOST:-${FTP_HOST:-}}"
FTP_USER="${FTP_OLD_USER:-${FTP_USER:-}}"
FTP_PASS="${FTP_OLD_PASS:-${FTP_OLD_PASSWORD:-${FTP_PASSWORD:-}}}"
FTP_PORT="${FTP_OLD_PORT:-${FTP_PORT:-21}}"

S3_BUCKET="${LEAN_S3_BUCKET:-}"
S3_ENDPOINT="${LEAN_S3_END_POINT:-}"
S3_PREFIX="${LEAN_S3_FOLDER_NAME:-}"

require_value "FTP_OLD_HOST (or FTP_HOST)" "$FTP_HOST"
require_value "FTP_OLD_USER (or FTP_USER)" "$FTP_USER"
require_value "FTP_OLD_PASS (or FTP_PASSWORD)" "$FTP_PASS"
require_value "LEAN_S3_BUCKET" "$S3_BUCKET"

if [[ "$VERIFY_ONLY" -eq 0 && "$SKIP_DOWNLOAD" -eq 0 ]]; then
  require_cmd lftp
fi
if [[ "$VERIFY_ONLY" -eq 1 || "$SKIP_UPLOAD" -eq 0 ]]; then
  require_cmd aws
fi

mkdir -p "$LOCAL_DIR"

S3_URI="s3://$S3_BUCKET"
if [[ -n "$S3_PREFIX" ]]; then
  S3_URI="$S3_URI/$S3_PREFIX"
fi
S3_URI="${S3_URI%/}/"

S3_COUNT_PREFIX="$S3_PREFIX"
if [[ -n "$S3_COUNT_PREFIX" && "${S3_COUNT_PREFIX: -1}" != "/" ]]; then
  S3_COUNT_PREFIX="$S3_COUNT_PREFIX/"
fi

echo "File cutover plan"
echo "  FTP host:         $FTP_HOST:$FTP_PORT"
echo "  FTP remote path:  $REMOTE_PATH"
echo "  Local mirror dir: $LOCAL_DIR"
echo "  S3 destination:   $S3_URI"
echo "  Execute:          $EXECUTE"
echo

if [[ "$VERIFY_ONLY" -eq 0 && "$SKIP_DOWNLOAD" -eq 0 ]]; then
  if [[ "$EXECUTE" -eq 1 ]]; then
    echo "Step 1: Download FTP files -> local mirror"
    LFTP_CMD="set ftp:passive-mode true; set net:max-retries 2; set net:timeout 30;"
    if [[ "$PRESERVE_TIMES" -eq 1 ]]; then
      LFTP_CMD="$LFTP_CMD mirror --verbose --parallel=2 --only-newer \"$REMOTE_PATH\" \"$LOCAL_DIR\"; quit"
    else
      LFTP_CMD="$LFTP_CMD mirror --verbose --parallel=2 --no-perms --no-umask \"$REMOTE_PATH\" \"$LOCAL_DIR\"; quit"
    fi
    lftp -u "$FTP_USER","$FTP_PASS" -p "$FTP_PORT" "$FTP_HOST" -e "$LFTP_CMD"
  else
    echo "Step 1: Download FTP files -> local mirror"
    echo "  [dry-run] lftp mirror $REMOTE_PATH $LOCAL_DIR"
  fi
fi

if [[ "$VERIFY_ONLY" -eq 0 && "$SKIP_UPLOAD" -eq 0 ]]; then
  echo "Step 2: Upload local mirror -> S3"
  if [[ "$EXECUTE" -eq 1 ]]; then
    if [[ -n "$S3_ENDPOINT" ]]; then
      aws s3 sync "$LOCAL_DIR" "$S3_URI" --endpoint-url "$S3_ENDPOINT" --only-show-errors
    else
      aws s3 sync "$LOCAL_DIR" "$S3_URI" --only-show-errors
    fi
  else
    if [[ -n "$S3_ENDPOINT" ]]; then
      aws s3 sync "$LOCAL_DIR" "$S3_URI" --endpoint-url "$S3_ENDPOINT" --dryrun
    else
      aws s3 sync "$LOCAL_DIR" "$S3_URI" --dryrun
    fi
  fi
fi

echo "Step 3: Verify counts"
LOCAL_COUNT="$(count_local_files "$LOCAL_DIR")"
S3_COUNT="$(count_s3_objects "$S3_BUCKET" "$S3_COUNT_PREFIX" "$S3_ENDPOINT")"
echo "  Local files: $LOCAL_COUNT"
echo "  S3 objects:  $S3_COUNT (prefix='${S3_COUNT_PREFIX}')"

if [[ "$EXECUTE" -eq 1 && "$VERIFY_ONLY" -eq 0 ]]; then
  if [[ "$S3_COUNT" -lt "$LOCAL_COUNT" ]]; then
    echo "WARNING: S3 object count is lower than local file count." >&2
    echo "Investigate missing uploads before final DNS cutover." >&2
    exit 2
  fi
fi

echo
echo "Done."
if [[ "$EXECUTE" -ne 1 ]]; then
  echo "Re-run with --execute to perform transfer."
fi


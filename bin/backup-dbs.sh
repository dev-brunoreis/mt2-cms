#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ -f .env ]]; then
  set -a
  # shellcheck disable=SC1091
  source .env
  set +a
fi

STAMP="$(date +%Y%m%d-%H%M%S)"
OUT_DIR="${BACKUP_DIR:-$ROOT/var/backups}"
mkdir -p "$OUT_DIR"

GAME_HOST="${DB_HOST:-game}"
GAME_PORT="${DB_PORT:-3306}"
GAME_USER="${DB_USER:-root}"
GAME_PASS="${DB_PASSWORD:?Set DB_PASSWORD in .env}"

CMS_HOST="${CMS_DB_HOST:-mysql}"
CMS_PORT="${CMS_DB_PORT:-3306}"
CMS_USER="${CMS_DB_USER:-root}"
CMS_PASS="${CMS_DB_PASSWORD:?Set CMS_DB_PASSWORD in .env}"
CMS_NAME="${CMS_DB_NAME:-cms}"

GAME_OUT="$OUT_DIR/game-$STAMP.sql.gz"
CMS_OUT="$OUT_DIR/cms-$STAMP.sql.gz"

echo "Backing up game database to $GAME_OUT"
mysqldump -h "$GAME_HOST" -P "$GAME_PORT" -u "$GAME_USER" -p"$GAME_PASS" \
  --single-transaction --routines --triggers \
  account player common log \
  | gzip > "$GAME_OUT"

echo "Backing up CMS database to $CMS_OUT"
mysqldump -h "$CMS_HOST" -P "$CMS_PORT" -u "$CMS_USER" -p"$CMS_PASS" \
  --single-transaction --routines --triggers \
  "$CMS_NAME" \
  | gzip > "$CMS_OUT"

echo "Done."

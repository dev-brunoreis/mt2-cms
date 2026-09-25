#!/bin/sh
# Build a bare-metal release tree under dist/ (no Docker, no GitHub). POSIX sh.
# Usage: ./bin/package-release.sh VERSION
#        ./bin/package-release.sh          # only when HEAD is an exact git tag
# Does not ship vendor/ — run composer install on the host after deploy.
# Env: SKIP_ASSETS=1 — skip npm ci/build when public/ assets are already built
#      SKIP_ARCHIVE=1 — keep dist/mt2-cms-VERSION/ only (no .tar.gz)
set -eu

ROOT="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

VERSION="${1:-}"
if [ -z "$VERSION" ]; then
  if command -v git >/dev/null 2>&1 && git rev-parse --git-dir >/dev/null 2>&1; then
    VERSION="$(git describe --tags --exact-match 2>/dev/null || true)"
  fi
fi
if [ -z "$VERSION" ]; then
  echo "usage: $0 VERSION" >&2
  echo "  e.g. $0 1.0.0-beta.0" >&2
  echo "  omit VERSION only when HEAD is an exact git tag (1.0.0-beta.0)" >&2
  exit 1
fi

# Strip leading v from tags
case "$VERSION" in
  v*) VERSION="${VERSION#v}" ;;
esac

NAME="mt2-cms-${VERSION}"
OUT_DIR="${OUT_DIR:-$ROOT/dist}"
STAGE="$OUT_DIR/$NAME"
ARCHIVE="$OUT_DIR/${NAME}.tar.gz"
CHECKSUM="$OUT_DIR/${NAME}.tar.gz.sha256"

mkdir -p "$OUT_DIR"
rm -rf "$STAGE"
mkdir -p "$STAGE"

if [ "${SKIP_ASSETS:-0}" != "1" ]; then
  npm ci
  npm run build
fi

# Copy release tree (no vendor/, no docs/, no tests, no Docker)
for path in \
  src \
  public \
  themes \
  lang \
  deploy \
  game \
  composer.json \
  composer.lock \
  LICENSE \
  README.md \
  .env.prod-example
do
  if [ ! -e "$path" ]; then
    echo "missing required path: $path" >&2
    exit 1
  fi
  cp -R "$path" "$STAGE/"
done

# Host cron/CLI only — not maintainer tools
mkdir -p "$STAGE/bin"
for f in migrate.php payments-process.php economy-tick.php backup-dbs.sh; do
  if [ ! -f "bin/$f" ]; then
    echo "missing required path: bin/$f" >&2
    exit 1
  fi
  cp "bin/$f" "$STAGE/bin/"
done
chmod +x "$STAGE/bin/"*.sh "$STAGE/bin/"*.php

# Schema and empty dirs only — strip any local proto, drops, client text, maps, and icons
rm -rf "$STAGE/game/maps" "$STAGE/game/client/icon" "$STAGE/game/client/ui"
find "$STAGE/game/db" -name '*.txt' -delete 2>/dev/null || true
find "$STAGE/game/client" -maxdepth 1 -name '*.txt' -delete 2>/dev/null || true
find "$STAGE/game/server" -type f ! -name '.gitkeep' -delete 2>/dev/null || true
mkdir -p \
  "$STAGE/game/db" \
  "$STAGE/game/client/icon/item" \
  "$STAGE/game/client/icon/face" \
  "$STAGE/game/client/ui" \
  "$STAGE/game/server"

# DeepL cache is local
rm -rf "$STAGE/lang/.deepl-cache"

# Empty writable stubs — do not ship local sessions or uploads
mkdir -p \
  "$STAGE/var/sessions" \
  "$STAGE/var/rate-limit" \
  "$STAGE/var/cache" \
  "$STAGE/var/uploads/tickets" \
  "$STAGE/var/downloads" \
  "$STAGE/var/backups" \
  "$STAGE/public/uploads/banners" \
  "$STAGE/public/uploads/logo" \
  "$STAGE/public/uploads/seo"
find "$STAGE/var" -type f -delete 2>/dev/null || true
find "$STAGE/public/uploads" -type f -delete 2>/dev/null || true
printf '%s\n' '*' '!.gitignore' > "$STAGE/var/.gitignore"
touch "$STAGE/var/sessions/.gitkeep" \
  "$STAGE/public/uploads/.gitkeep"

if [ "${SKIP_ARCHIVE:-0}" != "1" ]; then
  rm -f "$ARCHIVE" "$CHECKSUM"
  tar -czf "$ARCHIVE" -C "$OUT_DIR" "$NAME"

  if command -v sha256sum >/dev/null 2>&1; then
    (cd "$OUT_DIR" && sha256sum "${NAME}.tar.gz" > "${NAME}.tar.gz.sha256")
  elif command -v sha256 >/dev/null 2>&1; then
    (cd "$OUT_DIR" && sha256 -r "${NAME}.tar.gz" | awk '{print $1 "  " $2}' > "${NAME}.tar.gz.sha256")
  else
    echo "warning: no sha256sum/sha256; skipping checksum file" >&2
  fi
fi

echo "Wrote $STAGE"
if [ -f "$ARCHIVE" ]; then
  echo "Wrote $ARCHIVE"
fi
if [ -f "$CHECKSUM" ]; then
  echo "Wrote $CHECKSUM"
fi
